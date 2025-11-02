<?php

namespace App\Jobs\Zl;

use App\Models\ZlConversation;
use App\Models\ZlMessage;
use App\Modules\Utilities\Zalo\Services\ZaloApiService;
use App\Modules\Utilities\Zalo\Services\ZaloOAuthService;
use App\Services\TranslateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendZlReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $conversationId;
    public int $messageId;

    public function __construct(int $conversationId, int $messageId)
    {
        $this->conversationId = $conversationId;
        $this->messageId      = $messageId;
        $this->onQueue('default');
    }

    public function handle(): void
    {
        // 0) Lấy hội thoại + message
        $conv = ZlConversation::query()->with('user')->find($this->conversationId);
        $msg  = ZlMessage::query()->find($this->messageId);
        if (!$conv || !$msg) {
            Log::warning('[ZL][Send] conv or msg not found', ['cid' => $this->conversationId, 'mid' => $this->messageId]);
            return;
        }

        // 1) Dịch VI -> EN + polish (theo cấu hình kênh)
        $textVi = trim((string) $msg->text_raw);
        if ($textVi === '') {
            Log::notice('[ZL][Send] empty text', ['mid' => $msg->id]);
            return;
        }

        $ts = new TranslateService();
        $textEn = $ts->translate($textVi, 'en', 'vi') ?: $textVi;

        $textPolished = $ts->polishEn($textEn);
        if (!empty($textPolished)) {
            $textEn = $textPolished;
            $msg->text_polished = $textPolished;
        }

        // 2) Lấy access token đã GIẢI MÃ (từ bảng zl_tokens)
        $oauth       = new ZaloOAuthService();
        $accessToken = $oauth->currentAccessTokenDecrypted();
        if (!$accessToken) {
            Log::warning('[ZL][Send] no decrypted access token available');
            return;
        }

        // 3) Lấy Zalo user id thật từ hội thoại
        $zaloUserId = optional($conv->user)->zalo_user_id;
        if (!$zaloUserId) {
            Log::warning('[ZL][Send] missing zalo_user_id on conversation->user', ['cid' => $conv->id]);
            return;
        }

        // 4) Gửi tin nhắn OA thật
        $api    = new ZaloApiService();
        $result = $api->sendMessage($accessToken, $zaloUserId, $textEn);

        // 5) Cập nhật message (lưu EN đã gửi + provider_message_id nếu có)
$ok = !empty($result['success']);
$msg->text_translated = $textEn;
$msg->src_lang        = 'vi';
$msg->dst_lang        = 'en';
if (!empty($result['provider_message_id'])) {
    $msg->provider_message_id = $result['provider_message_id'];
}
/** ✅ Chỉ khi gửi OK mới set delivered_at */
if ($ok) {
    $msg->delivered_at = now();
}
$msg->save();

if (!$ok) {
    \Log::warning('[ZL][Send] OA send failed', ['cid' => $conv->id, 'mid' => $msg->id, 'result' => $result]);
    return; // không cập nhật last_message_at
}
$conv->last_message_at = now();
$conv->save();


        Log::info('[ZL][Send] OA send ok', [
            'cid' => $conv->id, 'mid' => $msg->id, 'provider_message_id' => $msg->provider_message_id
        ]);
    }
}
