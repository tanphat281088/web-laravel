<?php

namespace App\Modules\Utilities\Facebook;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Webhook cho Facebook Messenger
 * - GET /api/fb/webhook   : verify (hub.challenge)
 * - POST /api/fb/webhook  : nhận sự kiện -> enqueue Job xử lý (ở bước sau)
 *
 * An toàn:
 * - Kiểm tra FB_VERIFY_TOKEN (GET)
 * - (Khuyến nghị) Kiểm tra X-Hub-Signature-256 với FB_WEBHOOK_SECRET (POST)
 * - Không xử lý nặng trong request; ở bước 2 sẽ tạo Job ProcessFbMessageJob
 */
class MessengerWebhookController extends Controller
{
    /**
     * GET /api/fb/webhook
     * Meta gọi verify: ?hub.mode=subscribe&hub.verify_token=...&hub.challenge=...
     */
    public function verify(Request $request)
    {
        $mode        = (string) $request->query('hub_mode', $request->query('hub.mode'));
        $verifyToken = (string) $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge   = (string) $request->query('hub_challenge', $request->query('hub.challenge'));

        $expected = (string) env('FB_VERIFY_TOKEN', '');

        if ($mode === 'subscribe' && $verifyToken && hash_equals($expected, $verifyToken)) {
            // OK: trả challenge để Meta xác thực webhook
            return response($challenge ?: 'OK', 200)
                ->header('Content-Type', 'text/plain');
        }

        Log::warning('[FB][Webhook][Verify] invalid token or mode', [
            'mode' => $mode,
            'provided' => $verifyToken ? Str::mask($verifyToken, '*', 2, 6) : null,
        ]);

        return response('Forbidden', 403);
    }

    /**
     * POST /api/fb/webhook
     * Nhận sự kiện từ Meta. Ở bước này:
     * - Kiểm tra chữ ký (nếu cấu hình FB_WEBHOOK_SECRET)
     * - Ghi log nhẹ và (nếu có Job) đẩy payload vào hàng đợi để xử lý ở bước sau
     */
    public function receive(Request $request)
    {
        // 1) (Khuyến nghị) Xác thực chữ ký HMAC
        $secret = (string) env('FB_WEBHOOK_SECRET', '');
        if ($secret !== '') {
            $signature = (string) ($request->header('X-Hub-Signature-256') ?? '');
            if (!$this->isValidSignature($request->getContent(), $secret, $signature)) {
                Log::warning('[FB][Webhook] invalid signature');
                return response()->json(['success' => false, 'message' => 'invalid signature'], 401);
            }
        }

        $payload = $request->json()->all() ?: [];
        Log::info('[FB][Webhook] received', ['len' => strlen($request->getContent() ?? ''), 'object' => $payload['object'] ?? null]);

        // 2) Đẩy vào Job ở Bước 2 (nếu class tồn tại); nếu chưa có Job thì chỉ log
        try {
            if (class_exists(\App\Jobs\Fb\ProcessFbMessageJob::class)) {
                // Mỗi entry chứa nhiều messaging events
                dispatch(new \App\Jobs\Fb\ProcessFbMessageJob($payload));
            } else {
                Log::notice('[FB][Webhook] Job class not found yet (will be added in next step). Payload kept in logs.');
            }
        } catch (\Throwable $e) {
            Log::error('[FB][Webhook] enqueue failed', ['err' => $e->getMessage()]);
            // Trả 200 để Meta không retry quá nhiều lần; lỗi sẽ quan sát trong log
            return response()->json(['success' => false, 'message' => 'enqueue failed'], Response::HTTP_OK);
        }

        // 3) Luôn trả 200 theo yêu cầu Meta
        return response()->json(['success' => true], 200);
    }

    /**
     * Kiểm tra chữ ký X-Hub-Signature-256 = "sha256=" . hash_hmac('sha256', body, secret)
     */
    private function isValidSignature(string $rawBody, string $secret, string $signatureHeader): bool
    {
        if ($signatureHeader === '' || !str_contains($signatureHeader, '=')) {
            return false;
        }
        [$algo, $sig] = explode('=', $signatureHeader, 2);
        if (strtolower($algo) !== 'sha256' || $sig === '') {
            return false;
        }
        $expected = hash_hmac('sha256', $rawBody, $secret);
        // so sánh timing safe
        return hash_equals($expected, $sig);
    }
}
