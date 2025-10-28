<?php

namespace App\Services\Sms;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class PaVnSmsService
{
    protected string $endpoint;   // https://crm.pavietnam.vn/api/sms/campaign
    protected string $token;      // token ở https://crm.pavietnam.vn/account/api
    protected string $brand;      // ID brand (ví dụ "149") hoặc tên brand
    protected int $timeout;
    protected string $defaultQuota;

    public function __construct()
    {
        $this->endpoint     = rtrim((string) env('PA_VN_SMS_ENDPOINT', ''), '/');
        $this->token        = (string) env('PA_VN_SMS_TOKEN', '');
        $this->brand        = (string) env('PA_VN_SMS_SENDER', '');
        $this->timeout      = (int) env('PA_VN_SMS_TIMEOUT', 8);
        $this->defaultQuota = (string) env('PA_VN_SMS_QUOTA', '');
    }

    /**
     * Gửi 1 SMS CSKH (type=cus).
     * - scheduleDate: "" (gửi ngay) hoặc "dd-mm-YYYY HH:ii" để hẹn giờ
     * Trả về object:
     *  (object)[
     *    'success'       => bool,        // true nếu code=1000 và không blacklist
     *    'provider_id'   => string|null, // campaign_id nếu có
     *    'error_code'    => string|null,
     *    'error_message' => string|null,
     *    'blacklisted'   => bool,
     *  ]
     */
    public function send(string $rawPhone, string $message, ?string $title = null, string $scheduleDate = ''): object
    {
        $phone = $this->normalizePhoneVN($rawPhone);
        if (!$phone) {
            return (object)[
                'success'       => false,
                'provider_id'   => null,
                'error_code'    => 'INVALID_PHONE',
                'error_message' => 'Số điện thoại không hợp lệ',
                'blacklisted'   => false,
            ];
        }

        if (!$this->endpoint || !$this->token || !$this->brand) {
            return (object)[
                'success'       => false,
                'provider_id'   => null,
                'error_code'    => 'CONFIG_MISSING',
                'error_message' => 'Thiếu PA_VN_SMS_ENDPOINT/PA_VN_SMS_TOKEN/PA_VN_SMS_SENDER',
                'blacklisted'   => false,
            ];
        }

        // ---- Build form theo tài liệu CSKH (phones là STRING, không phải phones[]) ----
        $form = [
            'action'          => 'create',
            'token'           => $this->token,
            'title'           => $title ?: ('PHG_SMS_' . date('Ymd_His')),
            'type'            => 'cus',
            'couponGroup'     => '',
            'scheduleDate'    => $scheduleDate,    // "" => gửi ngay
            'brandName'       => $this->brand,     // ID brand (ví dụ "149")
            'message'         => $message,
            'phones'          => $phone,           // CSKH dùng phones dạng string
            'check_blacklist' => '1',
        ];
        if ($this->defaultQuota !== '') {
            $form['quota'] = $this->defaultQuota;  // thường không cần cho CSKH 1-1
        }

        // ---- Chuẩn bị Guzzle: nếu có CA, verify = đường dẫn; nếu không, tạm tắt verify để tránh cURL 77 trên Windows ----
        $cacert = (string) env('PA_VN_SMS_CACERT', '');
        $verify = $cacert !== '' ? $cacert : false;

        $client = new Client([
            'timeout'     => $this->timeout,
            'verify'      => $verify,
            'http_errors' => false,
        ]);

        $attempts = 0;
        $lastErr  = null;

        while ($attempts < 2) { // 1 lần chính + 1 retry ngắn
            $attempts++;
            try {
                $resp = $client->post($this->endpoint, [
                    'form_params' => $form,
                    'headers'     => ['Accept' => 'application/json, text/plain, */*'],
                ]);

                $codeHttp = $resp->getStatusCode();
                $raw      = (string) $resp->getBody();
                $json     = null;
                try { $json = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); } catch (\Throwable $e) {}

                // Mặc định
                $providerId  = null;
                $blacklisted = false;

                if (is_array($json)) {
                    // Map mã code PA -> thông điệp
                    $paCodeMap = [
                        '1000' => 'Successful',
                        '1001' => 'Failed to init API, please contact Administrator for more information',
                        '1002' => 'Missing param(s)',
                        '1003' => 'Param(s) is/are not valid',
                        '1004' => 'You are not allowed to perform this action',
                        '1006' => 'Attacker detected',
                        '1007' => 'Request timeout',
                        '1013' => 'Could not read customer information',
                    ];

                    $code       = (string) ($json['code'] ?? '');
                    $providerId = $json['data']['campaign_id'] ?? null;

                    // Kiểm tra blacklist (mảng số bị loại)
                    if (!empty($json['blacklist']) && is_array($json['blacklist'])) {
                        $list = array_map(function ($p) {
                            return $this->normalizePhoneVN((string) $p) ?? (string) $p;
                        }, $json['blacklist']);
                        $blacklisted = in_array($phone, $list, true);
                    }

                    $success    = ($code === '1000') && !$blacklisted;
                    $friendly   = $paCodeMap[$code] ?? ($json['message'] ?? null);

                    return (object)[
                        'success'       => (bool) $success,
                        'provider_id'   => $providerId,
                        'error_code'    => $success ? null : ($blacklisted ? 'BLACKLISTED' : ($json['code'] ?? (string) $codeHttp)),
                        'error_message' => $success
                            ? null
                            : ($blacklisted ? 'Số thuộc danh sách từ chối (blacklist)' : ($friendly ?: 'SMS provider error')),
                        'blacklisted'   => $blacklisted,
                    ];
                }

                // Fallback nếu provider trả text
                $ok = ($codeHttp >= 200 && $codeHttp < 300 && stripos($raw, 'Successful') !== false);
                return (object)[
                    'success'       => $ok,
                    'provider_id'   => $providerId,
                    'error_code'    => $ok ? null : (string) $codeHttp,
                    'error_message' => $ok ? null : 'Non-JSON response',
                    'blacklisted'   => false,
                ];
            } catch (GuzzleException $e) {
                $lastErr = $e;
                usleep(300 * 1000);
            }
        }

        return (object)[
            'success'       => false,
            'provider_id'   => null,
            'error_code'    => 'HTTP_EXCEPTION',
            'error_message' => $lastErr ? substr($lastErr->getMessage(), 0, 240) : 'Unknown HTTP error',
            'blacklisted'   => false,
        ];
    }

    /**
     * Chuẩn hoá số VN về 84xxxxxxxxx.
     */
    protected function normalizePhoneVN(string $raw): ?string
    {
        $p = preg_replace('/\D+/', '', $raw ?? '');
        if ($p === '') return null;
        $p = preg_replace('/^00/', '', $p);
        if (str_starts_with($p, '84')) return $p;
        if (str_starts_with($p, '0') && strlen($p) >= 10) return '84' . substr($p, 1);
        if (str_starts_with($p, '9') && strlen($p) === 9)  return '84' . $p;
        return null;
    }
}
