<?php

namespace App\Modules\Utilities\Zalo\Controllers;

use App\Models\ZlConversation;
use App\Models\ZlMessage;
use App\Models\ZlUser;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class ZlWebhookController extends BaseController
{
    public function handle(Request $request)
    {
        $raw  = $request->getContent() ?? '';
        $json = json_decode($raw, true) ?: [];
$oaIdFromPayload = (string) \Illuminate\Support\Arr::get($json, 'recipient.id', '');

        // ---- Hình dạng payload ----
        $hasDataField       = array_key_exists('data', $json);
        $hasPortalTestShape = !$hasDataField && (isset($json['message']) || isset($json['follower']) || isset($json['sender']));

        $appId   = (string) Arr::get($json, 'app_id', env('ZL_APP_ID', ''));
        $secret  = (string) env('ZL_SECRET_KEY', '');
        $evName  = (string) Arr::get($json, 'event_name', '');

        // ===== 1) VERIFY chữ ký (chỉ khi có 'data' theo chuẩn OA prod) =====
        if ($hasDataField) {
            $hdrSig   = (string) $request->header('X-ZEvent-Signature', '');
            $hdrTime  = (string) $request->header('X-ZEvent-Timestamp', '');
            $bodyTime = (string) Arr::get($json, 'time', '');
            $dataStr  = Arr::get($json, 'data', '');

            if (is_array($dataStr)) {
                $dataStr = json_encode($dataStr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $timeForMac = $hdrTime !== '' ? $hdrTime : $bodyTime;
            $base       = $appId . (string) $dataStr . (string) $timeForMac . $secret;
            $localMac   = hash('sha256', $base);
            $okSig      = hash_equals(strtolower($localMac), strtolower($hdrSig));

            if (!$okSig) {
                Log::warning('[ZL][Webhook] signature mismatch(prod)', [
                    'calc' => $localMac, 'recv' => $hdrSig,
                    'app_id' => $appId, 'time_hdr' => $hdrTime, 'time_body' => $bodyTime,
                    'data_sample' => mb_substr((string)$dataStr, 0, 200),
                ]);
                return response()->json(['ok' => false, 'reason' => 'bad_signature'], Response::HTTP_OK);
            }
        } else {
            if ($hasPortalTestShape) {
                Log::info('[ZL][Webhook] portal-test mode (no data/signature)');
            } else {
                Log::warning('[ZL][Webhook] unknown payload shape', ['raw' => mb_substr($raw, 0, 400)]);
                return response()->json(['ok' => false, 'reason' => 'unknown_shape'], Response::HTTP_OK);
            }
        }

        // ===== 2) Chuẩn hoá data + timestamp =====
        if ($hasDataField) {
            $dataStr = Arr::get($json, 'data', '');
            $d       = is_string($dataStr) ? (json_decode($dataStr, true) ?: []) : (is_array($dataStr) ? $dataStr : []);
            $tsEpoch = (int) (Arr::get($json, 'time', time()));
        } else {
            // Portal test map trực tiếp từ root
            $d = [
                'from_id'   => Arr::get($json, 'sender.id') ?? Arr::get($json, 'follower.id'),
                'from_name' => null,
                'message'   => Arr::get($json, 'message', []),
                'follower'  => Arr::get($json, 'follower'), // cho event follow
            ];
            $tsEpoch = (int) (Arr::get($json, 'timestamp', time()));
        }

        // ms -> s nếu cần
        if ($tsEpoch >= 1000000000000) {
            $tsEpoch = (int) floor($tsEpoch / 1000);
        }

        try {
            $createdAt = Carbon::createFromTimestamp($tsEpoch);
        } catch (\Throwable $e) {
            Log::warning('[ZL][Webhook] bad timestamp, fallback now()', ['ts' => $tsEpoch, 'err' => $e->getMessage()]);
            $createdAt = now();
        }

        // ===== 3) Các event đặc biệt =====
        // 3.1 Tin nhắn đã được nhận (ACK) → cập nhật delivered_at theo msg_id
        if (strtolower($evName) === 'user_received_message') {
            $ackId = Arr::get($d, 'message.msg_id') ?? Arr::get($json, 'message.msg_id');
            if ($ackId) {
                ZlMessage::query()
                    ->where('provider_message_id', (string) $ackId)
                    ->update(['delivered_at' => now()]);
                Log::info('[ZL][Webhook] ack delivered', ['msg_id' => $ackId]);
            }
            return response()->json(['ok' => true, 'ack' => (bool)$ackId], Response::HTTP_OK);
        }

        // 3.2 Follow OA → upsert user (không tạo message)
        if (strtolower($evName) === 'follow') {
            $followerId = Arr::get($json, 'follower.id') ?? Arr::get($d, 'from_id');
            if ($followerId) {
                $u = ZlUser::query()->where('zalo_user_id', $followerId)->first();
                if (!$u) {
                    $u = new ZlUser();
                    $u->zalo_user_id  = $followerId;
                    $u->first_seen_at = $createdAt;
                }
                $u->save();
                Log::info('[ZL][Webhook] user followed', ['zalo_user_id' => $followerId]);
            }
            return response()->json(['ok' => true], Response::HTTP_OK);
        }

        // ===== 4) Parse trường cơ bản cho tin inbound =====
        $senderId    = (string) (Arr::get($d, 'from_id') ?? Arr::get($d, 'user_id') ?? '');
        $senderName  = (string) (Arr::get($d, 'from_name') ?? '');
        $msgId       = Arr::get($d, 'message.msg_id') ?? Arr::get($d, 'message.id');
        $msgText     = (string) (Arr::get($d, 'message.text', '') ?? '');
        $attachments = Arr::get($d, 'message.attachments', null);

        if ($senderId === '') {
            Log::warning('[ZL][Webhook] missing sender id', ['d' => $d, 'event' => $evName]);
            return response()->json(['ok' => false, 'reason' => 'missing_sender'], Response::HTTP_OK);
        }

        // Idempotent theo provider_message_id
        if (!empty($msgId)) {
            $dup = ZlMessage::query()->where('provider_message_id', (string) $msgId)->exists();
            if ($dup) {
                Log::info('[ZL][Webhook] duplicate msg ignored', ['msg_id' => $msgId]);
                return response()->json(['ok' => true, 'dup' => true], Response::HTTP_OK);
            }
        }

        // ===== 5) Upsert user =====
        $user = ZlUser::query()->where('zalo_user_id', $senderId)->first();
        if (!$user) {
            $user = new ZlUser();
            $user->zalo_user_id  = $senderId;
            $user->first_seen_at = $createdAt;
        }
        if ($senderName !== '') {
            $user->name = $senderName;
        }
        $user->save();

        // ===== 6) Upsert conversation =====
        $conv = ZlConversation::query()->where('zl_user_id', $user->id)->first();
        if (!$conv) {
            $conv = new ZlConversation();
            $conv->zl_user_id = $user->id;
            $conv->status     = 1; // open
            $conv->tags       = [];
        }
        $conv->last_message_at   = $createdAt;
        $conv->can_send_until_at = $createdAt->copy()->addHours(24);
        $tags = is_array($conv->tags) ? $conv->tags : [];
if ($oaIdFromPayload !== '') {
    $tags['oa_id'] = $oaIdFromPayload;
}
$conv->tags = $tags;

        $conv->save();

        // ===== 7) Chuẩn hoá attachments cho FE =====
        $normAtt = $this->normalizeAttachments($attachments);

        // ===== 8) Insert inbound message =====
        $m = new ZlMessage();
        $m->conversation_id     = $conv->id;
        $m->direction           = 'in';
        $m->provider_message_id = $msgId ?: null;
        $m->text_raw            = $msgText;
        $m->attachments         = $normAtt ?: (is_array($attachments) ? $attachments : ($attachments ? [$attachments] : null));
        $m->created_at          = $createdAt;
        $m->save();
// Gọi job dịch inbound (EN→VI) sau khi lưu
dispatch(new \App\Jobs\Zl\ProcessZlMessageJob($m->id));

        Log::info('[ZL][Webhook] stored inbound', ['cid' => $conv->id, 'mid' => $m->id, 'from' => $senderId, 'event' => $evName]);

        return response()->json(['ok' => true], Response::HTTP_OK);
    }

    public function receive(Request $request) { return $this->handle($request); }

    public function ping()
    {
        return response()->json(['ok' => true, 'ts' => now()->toDateTimeString()], Response::HTTP_OK);
    }

    /**
     * Chuẩn hoá attachments từ các event image/link/location/sticker.
     * Trả về mảng đã phẳng: [{type,url,thumbnail,description,coordinates:{lat,lng},sticker_id}, ...]
     */
    private function normalizeAttachments($attachments): array
    {
        if (!$attachments) return [];
        $out = [];
        $arr = is_array($attachments) ? $attachments : [$attachments];
        foreach ($arr as $a) {
            $type = strtolower((string) ($a['type'] ?? 'other'));
            $p    = $a['payload'] ?? $a ?? [];

            $url   = $p['url']        ?? ($a['url'] ?? null);
            $thumb = $p['thumbnail']  ?? ($a['thumbnail'] ?? null);
            $desc  = $p['description']?? null;

            // Location
            $coords = $p['coordinates'] ?? null;
            if ($coords && is_array($coords)) {
                $lat = $coords['latitude']  ?? null;
                $lng = $coords['longitude'] ?? null;
                $out[] = [
                    'type'        => 'location',
                    'coordinates' => ['latitude' => $lat, 'longitude' => $lng],
                ];
                continue;
            }

            // Sticker
            if ($type === 'sticker') {
                $out[] = [
                    'type'       => 'sticker',
                    'sticker_id' => $p['id'] ?? null,
                    'url'        => $url,
                    'thumbnail'  => $thumb,
                    'description'=> $desc,
                ];
                continue;
            }

            // Link
            if ($type === 'link') {
                $out[] = [
                    'type'        => 'link',
                    'url'         => $url,
                    'thumbnail'   => $thumb,
                    'description' => $desc,
                ];
                continue;
            }

            // Image (mặc định) / other
            $out[] = [
                'type'       => $type ?: 'image',
                'url'        => $url,
                'thumbnail'  => $thumb,
                'description'=> $desc,
            ];
        }
        return $out;
    }
}
