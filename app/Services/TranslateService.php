<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TranslateService
{
    /**
     * Nhà cung cấp dịch (env):
     * - google_apikey  : dùng Google Translate qua API key
     * - none / ''      : tắt dịch (trả về nguyên văn)
     */
    private string $provider;

    private string $googleApiKey;

    // OpenAI (polish - tùy chọn)
    private string $openaiKey;
    private ?string $openaiProject;
    private string $openaiModel;
    private bool $aiPolish;
    private string $aiTone;

    public function __construct()
    {
        $this->provider      = (string) env('FB_TRANSLATE_PROVIDER', 'google_apikey');
        $this->googleApiKey  = (string) env('GOOGLE_TRANSLATE_API_KEY', '');

        $this->openaiKey     = (string) env('OPENAI_API_KEY', '');
        $this->openaiProject = env('OPENAI_PROJECT_ID');
        $this->openaiModel   = (string) env('OPENAI_CHAT_MODEL', 'gpt-4o');
        $this->aiPolish      = (bool)   env('FB_AI_POLISH', false);
        $this->aiTone        = (string) env('FB_AI_TONE', 'neutral');
    }

    /* ---------------------------- PUBLIC API ---------------------------- */

    /** Phát hiện ngôn ngữ (vi/en/…) — null nếu không xác định. */
    public function detect(string $text): ?string
    {
        $text = trim($text);
        if ($text === '') return null;

        if ($this->provider === 'google_apikey' && $this->googleApiKey !== '') {
            return $this->detectWithGoogle($text);
        }

        return null; // không bật provider => không đoán
    }

    /**
     * Dịch text sang ngôn ngữ $target. Nếu $source có giá trị sẽ gợi ý nguồn.
     * Khi provider tắt/thiếu key: trả nguyên văn.
     */
    public function translate(string $text, string $target, ?string $source = null): string
    {
        $text = (string) $text;
        $target = $this->normalizeLang($target);
        $source = $source ? $this->normalizeLang($source) : null;

        if ($text === '' || $this->provider === 'none' || $this->provider === '') {
            return $text;
        }

        if ($this->provider === 'google_apikey' && $this->googleApiKey !== '') {
            return $this->translateWithGoogle($text, $target, $source);
        }

        // fallback: không có provider hợp lệ
        return $text;
    }

    /**
     * “Đánh bóng” câu tiếng Anh cho CSKH (tùy chọn).
     * Chỉ chạy khi FB_AI_POLISH=true & có OPENAI_API_KEY.
     * Nếu không thỏa, trả nguyên văn $textEn.
     */
    public function polishEn(string $textEn): string
    {
        $textEn = trim($textEn);
        if ($textEn === '') return '';

        if (!$this->aiPolish || $this->openaiKey === '') {
            return $textEn;
        }

        return $this->polishWithOpenAI($textEn, $this->aiTone);
    }

    /* ------------------------ GOOGLE TRANSLATE (API KEY) ------------------------ */

    private function detectWithGoogle(string $text): ?string
    {
        try {
            $resp = Http::timeout(10)
                ->asJson()
                ->post(
                    'https://translation.googleapis.com/language/translate/v2/detect?key=' . $this->googleApiKey,
                    ['q' => $text]
                );

            if (!$resp->ok()) return null;

            $data = $resp->json();
            // data.detections[0][0].language
            $lang = $data['data']['detections'][0][0]['language'] ?? null;
            return $lang ? $this->normalizeLang($lang) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function translateWithGoogle(string $text, string $target, ?string $source = null): string
    {
        try {
            $payload = [
                'q'      => $text,
                'target' => $target,
                'format' => 'text',
            ];
            if ($source) {
                $payload['source'] = $source;
            }

            $resp = Http::timeout(15)
                ->asJson()
                ->post(
                    'https://translation.googleapis.com/language/translate/v2?key=' . $this->googleApiKey,
                    $payload
                );

            if (!$resp->ok()) return $text;

            $data = $resp->json();
            $translated = $data['data']['translations'][0]['translatedText'] ?? null;
            if (is_string($translated) && $translated !== '') {
                // Google có thể trả về HTML escaped; decode nhẹ
                return html_entity_decode($translated, ENT_QUOTES | ENT_HTML5);
            }
            return $text;
        } catch (\Throwable $e) {
            return $text;
        }
    }

    /* ------------------------------ OPENAI POLISH ------------------------------ */

    private function polishWithOpenAI(string $textEn, string $tone = 'neutral'): string
    {
        try {
            $headers = [
                'Authorization' => 'Bearer ' . $this->openaiKey,
                'Content-Type'  => 'application/json',
            ];
            if (!empty($this->openaiProject)) {
                $headers['OpenAI-Project'] = $this->openaiProject;
            }

            $system = "You rewrite messages to be concise and {$tone} for customer support. Do not add emojis.";
            $resp = Http::timeout(20)
                ->withHeaders($headers)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'    => $this->openaiModel,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user',   'content' => "Please rephrase:\n\n" . $textEn],
                    ],
                ]);

            if (!$resp->ok()) return $textEn;

            $data = $resp->json();
            $out  = $data['choices'][0]['message']['content'] ?? null;
            return is_string($out) && $out !== '' ? trim($out) : $textEn;
        } catch (\Throwable $e) {
            return $textEn;
        }
    }

    /* --------------------------------- HELPERS -------------------------------- */

    /** Chuẩn hóa mã ngôn ngữ thành dạng ngắn (vi, en). */
    private function normalizeLang(string $lang): string
    {
        $lang = Str::lower(trim($lang));
        if (str_starts_with($lang, 'en')) return 'en';
        if (str_starts_with($lang, 'vi') || $lang === 'vn') return 'vi';
        return $lang;
    }
}
