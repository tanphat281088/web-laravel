<?php

namespace App\Jobs\Zl;

use App\Models\ZlMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\TranslateService;

class ProcessZlMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $messageId;

    public function __construct(int $messageId)
    {
        $this->messageId = $messageId;
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $m = ZlMessage::query()->find($this->messageId);
        if (!$m) {
            Log::warning('[ZL][Job] inbound message not found', ['mid' => $this->messageId]);
            return;
        }
        // đã có bản dịch thì thôi
        if (!empty($m->text_translated)) return;

        $text = trim((string) $m->text_raw);
        if ($text === '') return;

        try {
            $ts = new TranslateService();
            // Ưu tiên dịch về VI. Nếu text gốc đã là VI, translate service có thể trả lại nguyên văn.
            $translated = $ts->translate($text, 'vi', 'auto') ?: $text;

            $m->text_translated = $translated;
            $m->src_lang = $m->src_lang ?: null; // giữ nguyên nếu sau này có detect
            $m->dst_lang = 'vi';
            $m->save();

            Log::info('[ZL][Job] inbound translated', ['mid' => $m->id]);
        } catch (\Throwable $e) {
            Log::error('[ZL][Job] inbound translate error', ['mid' => $m->id, 'err' => $e->getMessage()]);
        }
    }
}
