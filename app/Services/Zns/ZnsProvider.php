<?php

namespace App\Services\Zns;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * ZnsProvider
 *
 * Gửi ZNS theo template_id + params (KHÔNG tự ghép chuỗi).
 * - Trả về object:
 *   (object)[
 *      'success'       => bool,
 *      'provider_id'   => string|null,  // request_id / message_id / tracking_id nếu provider trả
 *      'error_code'    => string|null,
 *      'error_message' => string|null,
 *   ]
 *
 * ENV bắt buộc / khuyến nghị:
 * - ZNS_API_BASE="https://business.openapi.zalo.me"
 * - ZNS_ACCESS_TOKEN="__PLACEHOLDER__"
 * - ZNS_TIMEOUT=10
 * - ZNS_VERIFY_CA=        (để trống => verify=false; nếu có CA path thì set đường dẫn)
 * - ZNS_SEND_ENDPOINT="/message/template"  (tuỳ OA; cho phép override endpoint)
 * - ZNS_RATE_LIMIT_PER_SEC=15              (tuỳ quota OA; dùng sleep nhẹ để giới hạn)
 *
 * Lưu ý:
 * - $phoneE164 phải chuẩn "84xxxxxxxxx". Hãy chuẩn hoá ở Controller trước khi truyền vào.
 * - $params là mảng assoc [key => value] tương ứng các param trong template (customer_code, order_code,...).
 * - Chúng ta chuyển $params -> dạng mảng {key,value} theo thói quen API template ZNS.
 */
class ZnsProvider
{
    protected string $baseUrl;
    protected string $accessToken;
    protected int    $timeout;
    protected string $sendEndpoint;
    protected bool   $verifyTls;
    protected ?string $verifyPath;
    protected int    $rateLimitPerSec;

    public function __construct()
    {
        $this->baseUrl        = rtrim((string) env('ZNS_API_BASE', ''), '/');
        $this->accessToken    = (string) env('ZNS_ACCESS_TOKEN', '');
        $this->timeout        = (int) env('ZNS_TIMEOUT', 10);
        $this->sendEndpoint   = (string) env('ZNS_SEND_ENDPOINT', '/message/template');
        $this->rateLimitPerSec= (int) env('ZNS_RATE_LIMIT_PER_SEC', 15);

        $ca = (string) env('ZNS_VERIFY_CA', '');
        $this->verifyPath = $ca !== '' ? $ca : null;
        $this->verifyTls  = $ca !== ''; // nếu không có CA => verify=false (tránh lỗi cURL trên Windows)
    }

    /**
     * Gửi tin ZNS theo template_id + params
     *
     * @param string $templateId
     * @param string $phoneE164  e.g. "84xxxxxxxxx"
     * @param array  $params     Assoc: ['customer_code'=>'...', 'order_code'=>'...', ...]
     * @param array  $meta       Tuỳ chọn: metadata (event_id, khach_hang_id, ...)
     * @return object
     */
    public function send(string $templateId, string $phoneE164, array $params, array $meta = []): object
    {
        // Kiểm tra cấu hình
        if ($this->baseUrl === '' || $this->accessToken === '') {
            return (object) [
                'success'       => false,
                'provider_id'   => null,
                'error_code'    => 'CONFIG_MISSING',
                'error_message' => 'Thiếu ZNS_API_BASE / ZNS_ACCESS_TOKEN',
            ];
        }

        // Chuẩn bị payload theo thói quen API ZNS
        // - phone: số đích
        // - template_id: id mẫu ZNS đã ENABLE
// - template_data: object key-value theo yêu cầu một số OA
$templateDataAssoc = [];
foreach ($params as $k => $v) {
    $templateDataAssoc[(string)$k] = is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE);
}


$payload = [
    'phone'         => (string) $phoneE164,
    'template_id'   => (string) $templateId,
    'template_data' => $templateDataAssoc,   // ⬅️ gửi object KV trực tiếp
];


        // (Tuỳ chọn) kèm tracking để đối soát
        if (!empty($meta)) {
            // Nhiều OA hỗ trợ trường tracking_id hoặc metadata; tuỳ hệ thống có thể bỏ qua.
            $payload['tracking_id'] = $meta['event_id'] ?? ($meta['order_code'] ?? null);
        }

        // Rate limit đơn giản: sleep nếu > 0
        if ($this->rateLimitPerSec > 0) {
            // ngủ 1 / rateLimit giây (best-effort)
            usleep((int) floor(1_000_000 / max(1, $this->rateLimitPerSec)));
        }

        $client = new Client([
            'timeout'     => $this->timeout,
            'verify'      => $this->verifyPath ?: $this->verifyTls, // path hoặc bool
            'http_errors' => false,
            'base_uri'    => $this->baseUrl,
        ]);

        // Endpoint gửi
        $endpoint = $this->sendEndpoint;
        if (!str_starts_with($endpoint, '/')) {
            $endpoint = '/' . $endpoint;
        }

        // Gửi với 1 retry nhẹ cho lỗi mạng/timeout
        $attempts = 0;
        $lastErr  = null;

        while ($attempts < 2) {
            $attempts++;
            try {
                $resp = $client->post($endpoint, [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'access_token' => $this->accessToken,
                        'Accept'       => 'application/json',
                    ],
                    'json' => $payload,
                ]);

                $httpCode = $resp->getStatusCode();
                $raw      = (string) $resp->getBody();
                \Log::info('[ZNS][HTTP]', ['code'=>$httpCode, 'raw'=>$raw, 'payload'=>$payload]);

                $json     = null;
                try { $json = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); } catch (\Throwable $e) {}

                // Phổ biến trong ZNS:
                // - { "error": 0, "message": "Success", "data": { "request_id": "...", ... } }
                // hoặc dùng trường "code" / "message" khác.
                if (is_array($json)) {
                    $err   = $json['error']  ?? null;      // 0 = success (thường gặp)
                    $msg   = $json['message'] ?? null;
                    $data  = $json['data']    ?? [];

                    // Một số OA dùng "code" thay cho "error"
                    $code  = $json['code']    ?? null;

                    $providerId = $data['request_id'] ?? ($data['message_id'] ?? null);

                    // Điều kiện thành công (ưu tiên error===0; fallback nếu http 2xx và có request_id)
                    $success = ($err === 0)
                            || ($code === 0)
                            || (($httpCode >= 200 && $httpCode < 300) && $providerId);

                    return (object) [
                        'success'       => (bool) $success,
                        'provider_id'   => $providerId,
                        'error_code'    => $success ? null : (string) ($err ?? $code ?? $httpCode),
                        'error_message' => $success ? null : ((string) ($msg ?? 'ZNS provider error')),
                    ];
                }

                // Fallback: không JSON
                $ok = ($httpCode >= 200 && $httpCode < 300) && stripos($raw, 'success') !== false;
                return (object) [
                    'success'       => $ok,
                    'provider_id'   => null,
                    'error_code'    => $ok ? null : (string) $httpCode,
                    'error_message' => $ok ? null : 'Non-JSON response from ZNS',
                ];
            } catch (GuzzleException $e) {
                $lastErr = $e;
                // retry 1 lần
                usleep(300 * 1000);
            }
        }

        return (object) [
            'success'       => false,
            'provider_id'   => null,
            'error_code'    => 'HTTP_EXCEPTION',
            'error_message' => $lastErr ? substr($lastErr->getMessage(), 0, 240) : 'Unknown HTTP error',
        ];
    }
}
