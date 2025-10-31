<?php

namespace App\Jobs\Fb;

use App\Models\FbConversation;
use App\Models\FbMessage;
use App\Models\FbPage;
use App\Models\FbGlossary;
use App\Services\TranslateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendFbReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $conversationId;
    public int $messageId;

    /**
     * @param int $conversationId fb_conversations.id
     * @param int $messageId      fb_messages.id (direction='out', text_raw=VI)
     */
    public function __construct(int $conversationId, int $messageId)
    {
        $this->conversationId = $conversationId;
        $this->messageId      = $messageId;
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $conv = FbConversation::query()->find($this->conversationId);
        $msg  = FbMessage::query()->find($this->messageId);
        if (!$conv || !$msg) {
            Log::warning('[FB][Send] conv or msg not found', ['cid' => $this->conversationId, 'mid' => $this->messageId]);
            return;
        }

        // 0) Kiểm tra 24h window
        $within = $conv->within_24h_until_at ? Carbon::parse($conv->within_24h_until_at)->isFuture() : true;
        if (!$within) {
            Log::notice('[FB][Send] blocked by 24h policy', ['cid' => $conv->id, 'mid' => $msg->id]);
            return;
        }

        $page = FbPage::query()->find($conv->page_id);
        if (!$page) {
            Log::warning('[FB][Send] page not found', ['page_id' => $conv->page_id]);
            return;
        }

        // 1) Lấy Page Access Token (PAT) đã mã hóa
        $enc = (string)($page->token_enc ?? '');
        if ($enc === '') {
            Log::warning('[FB][Send] token missing', ['cid' => $conv->id, 'page' => $page->id]);
            return;
        }
        try {
            $pageToken = Crypt::decryptString($enc);
        } catch (\Throwable $e) {
            Log::error('[FB][Send] decrypt token failed', ['err' => $e->getMessage()]);
            return;
        }

        // 2) Chuẩn bị nội dung: VI -> EN (+ glossary + polish tùy chọn)
        $textVi = trim((string)$msg->text_raw);
        if ($textVi === '') {
            Log::notice('[FB][Send] empty text', ['mid' => $msg->id]);
            return;
        }

        $ts       = new TranslateService();
        $textEn   = $ts->translate($textVi, 'en', 'vi');   // dịch chuẩn bằng Google (apikey)
        if ($textEn === '' || $textEn === null) {
            $textEn = $textVi; // fallback: không để mất message
        }
        $textEn   = $this->applyGlossary($textEn, 'en');

        // polish (tùy chọn theo .env: FB_AI_POLISH, FB_AI_TONE)
        $textPolished = $ts->polishEn($textEn);
        if (!empty($textPolished)) {
            $textEn = $textPolished;
            $msg->text_polished = $textPolished;
        }

        // 3) Gọi Graph API send message
        $appSecret = (string) env('FB_APP_SECRET', '');
        $appProof  = $appSecret !== '' ? hash_hmac('sha256', $pageToken, $appSecret) : null;

        $endpoint = 'https://graph.facebook.com/v19.0/' . urlencode((string)$this->extractPageId($page));
        $url      = $endpoint . '/messages';

        $payload = [
            'recipient'      => ['id' => $this->extractPsid($conv)],
            'message'        => ['text' => $textEn],
            'messaging_type' => 'RESPONSE', // trong 24h
            'access_token'   => $pageToken,
        ];

        $headers = ['Content-Type' => 'application/json'];
        if ($appProof) {
            $headers['X-Appsecret-Proof'] = $appProof;
        }

        try {
            $resp = Http::withHeaders($headers)->post($url, $payload);
            if (!$resp->ok()) {
                Log::warning('[FB][Send] Graph send failed', ['status' => $resp->status(), 'body' => $resp->body()]);
                return;
            }
            $j = $resp->json();
            $graphMid = $j['message_id'] ?? null;

            // 4) Cập nhật message record (lưu song ngữ)
            $msg->text_translated = $textEn; // lưu nội dung EN đã gửi (polished nếu có)
            $msg->src_lang        = 'vi';
            $msg->dst_lang        = 'en';
            if ($graphMid) {
                $msg->mid = $graphMid;
            }
            $msg->delivered_at = now();
            $msg->save();

            // 5) Cập nhật conversation (last_message_at + 24h)
            $conv->last_message_at     = now();
            $conv->within_24h_until_at = now()->addHours(24);
            $conv->save();

        } catch (\Throwable $e) {
            Log::error('[FB][Send] exception', ['err' => $e->getMessage()]);
        }
    }

    private function extractPageId(FbPage $page): string
    {
        // lưu ở fb_pages.page_id (string ID từ Meta)
        return (string)($page->page_id ?? '');
    }

    private function extractPsid(FbConversation $conv): string
    {
        // join để lấy psid
        $psid = optional($conv->user)->psid;
        return (string)$psid;
    }

    private function applyGlossary(string $text, string $lang): string
    {
        $items = FbGlossary::query()->get(['term','prefer_keep','prefer_translation']);
        foreach ($items as $g) {
            $term = (string)$g->term;
            if ($term === '') continue;
            if ($g->prefer_keep) {
                $text = preg_replace('/' . preg_quote($term, '/') . '/i', $term, $text);
            } elseif (!empty($g->prefer_translation)) {
                $text = preg_replace('/' . preg_quote($term, '/') . '/i', $g->prefer_translation, $text);
            }
        }
        return $text;
    }
}
