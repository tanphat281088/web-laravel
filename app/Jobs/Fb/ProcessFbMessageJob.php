<?php

namespace App\Jobs\Fb;

use App\Models\FbConversation;
use App\Models\FbMessage;
use App\Models\FbPage;
use App\Models\FbUser;
use App\Models\FbGlossary;
use App\Services\TranslateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ProcessFbMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $payload;

    /**
     * @param array $payload Raw JSON decoded from webhook
     */
    public function __construct(array $payload)
    {
        $this->payload = $payload;
        $this->onQueue('default');
    }

    public function handle(): void
    {
        // Cấu trúc webhook chuẩn: { object: "page", entry: [ { id, time, messaging: [ ... ] } ] }
        if (($this->payload['object'] ?? '') !== 'page') {
            Log::notice('[FB][Job] skip non-page payload');
            return;
        }

        foreach ((array)($this->payload['entry'] ?? []) as $entry) {
            $pageIdStr = (string)($entry['id'] ?? '');
            if ($pageIdStr === '') {
                continue;
            }

            $page = FbPage::query()->where('page_id', $pageIdStr)->first();
            if (!$page) {
                // Nếu chưa có record page → tạo nháp (inactive) để không rơi mất data
                $page = FbPage::query()->create([
                    'page_id' => $pageIdStr,
                    'name'    => null,
                    'status'  => 0,
                ]);
            }

            foreach ((array)($entry['messaging'] ?? []) as $event) {
                $this->processMessagingEvent($page, $event);
            }
        }
    }

    private function processMessagingEvent(FbPage $page, array $event): void
    {
        // Lấy PSID & nội dung
        $senderId    = (string)Arr::get($event, 'sender.id', '');
        $recipientId = (string)Arr::get($event, 'recipient.id', '');
        $timestampMs = (int)Arr::get($event, 'timestamp', now()->getTimestampMs());

        // Chỉ xử lý message.text (MVP). Nếu sau này có attachments, postback… sẽ bổ sung.
        $message     = Arr::get($event, 'message', []);
        $mid         = (string)Arr::get($message, 'mid', '');
        $text        = trim((string)Arr::get($message, 'text', ''));

        if ($senderId === '' || $recipientId === '' || $mid === '') {
            // Có thể là delivery/echo/postback — bỏ qua ở MVP
            return;
        }

        // Idempotent: nếu mid đã tồn tại -> bỏ qua (Meta có thể retry)
        $exists = FbMessage::query()->where('mid', $mid)->exists();
        if ($exists) {
            return;
        }

        // 1) Upsert FB User theo PSID
        $fbUser = FbUser::query()->firstOrCreate(['psid' => $senderId], [
            'name'          => null,
            'locale'        => null,
            'timezone'      => null,
            'avatar'        => null,
            'first_seen_at' => now(),
        ]);

        // 2) Tìm/ tạo Conversation trên page đó
        $conv = FbConversation::query()
            ->where('page_id', $page->id)
            ->where('fb_user_id', $fbUser->id)
            ->first();

        if (!$conv) {
            $conv = FbConversation::query()->create([
                'page_id'             => $page->id,
                'fb_user_id'          => $fbUser->id,
                'assigned_user_id'    => null,
                'status'              => 1, // open
                'lang_primary'        => null, // sẽ suy luận phía dưới
                'within_24h_until_at' => null,
                'tags'                => [],
                'last_message_at'     => null,
            ]);
        }

        // 3) Dịch EN/khác -> VI (dùng TranslateService)
        $createdAt  = Carbon::createFromTimestampMs($timestampMs)->tz(config('app.timezone'));
        $ts         = new TranslateService();

        $srcLang    = null;
        $dstLang    = null;
        $translated = null;

        if ($text !== '') {
            // Phát hiện ngôn ngữ
            $srcLang = $ts->detect($text);
            if ($srcLang === null) {
                // fallback nhẹ nhàng nếu detect không ra
                $srcLang = $this->fallbackDetect($text);
            }

            // cập nhật lang_primary nếu chưa có
            if ($conv->lang_primary === null && $srcLang !== null) {
                $conv->lang_primary = $srcLang;
            }

            // mục tiêu hiển thị cho NV là VI
            $dstLang = 'vi';

            if ($srcLang === 'vi') {
                $translated = $text; // để UI luôn có [Dịch]
            } else {
                $translated = $ts->translate($text, $dstLang, $srcLang);
                if ($translated === '' || $translated === null) {
                    $translated = $text; // fallback hiển thị gốc
                }
            }

            // Áp glossary sau dịch (nếu có)
            $translated = $this->applyGlossary($translated ?? $text, 'vi');
        }

        // 4) Lưu message IN
        $msg = new FbMessage();
        $msg->conversation_id = $conv->id;
        $msg->direction       = 'in';
        $msg->mid             = $mid;
        $msg->text_raw        = $text !== '' ? $text : null;
        $msg->text_translated = $translated;
        $msg->text_polished   = null;
        $msg->src_lang        = $srcLang;
        $msg->dst_lang        = $dstLang;
        $msg->attachments     = null;
        $msg->created_at      = $createdAt;
        $msg->delivered_at    = null;
        $msg->read_at         = null;
        $msg->save();

        // 5) Cập nhật trạng thái hội thoại (24h kể từ tin inbound)
        $conv->last_message_at      = $createdAt;
        $conv->within_24h_until_at  = (clone $createdAt)->addHours(24);
        $conv->save();
    }

    /**
     * Fallback detect rất nhẹ (chỉ dùng khi TranslateService.detect trả null)
     */
    private function fallbackDetect(string $text): string
    {
        // heuristics đơn giản: chứa ký tự tiếng Việt?
        if (preg_match('/[ăâêôơưđàáảãạèéẻẽẹìíỉĩịòóỏõọùúủũụỳýỷỹỵ]/iu', $text)) {
            return 'vi';
        }
        // nhiều chữ cái ASCII + khoảng trắng → giả định en
        if (preg_match('/[a-z]{3,}/i', $text)) {
            return 'en';
        }
        return 'en'; // mặc định 'en'
    }

    /**
     * Áp glossary: giữ nguyên hoặc thay bằng bản dịch ưu tiên.
     */
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
