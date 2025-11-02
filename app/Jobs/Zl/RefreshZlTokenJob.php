<?php

namespace App\Jobs\Zl;

use App\Modules\Utilities\Zalo\Services\ZaloOAuthService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;

class RefreshZlTokenJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // 1) Đọc token hiện tại
        $row = DB::table('zl_tokens')->orderByDesc('id')->first();
        if (!$row) {
            Log::notice('[ZL][Refresh] no token row yet');
            return;
        }

        // 2) Kiểm tra còn bao lâu hết hạn
        $refreshBeforeMin = (int) env('ZL_TOKEN_REFRESH_BEFORE_MIN', 10);
        $shouldRefresh = $row->expires_at && now()->addMinutes($refreshBeforeMin)->gte($row->expires_at);
        if (!$shouldRefresh) {
            // Không làm gì nếu chưa tới ngưỡng
            return;
        }

        // 3) Giải mã refresh_token
        if (empty($row->refresh_token_enc)) {
            Log::warning('[ZL][Refresh] missing refresh_token_enc');
            return;
        }
        try {
            $refreshToken = Crypt::decryptString($row->refresh_token_enc);
        } catch (\Throwable $e) {
            Log::error('[ZL][Refresh] decrypt refresh token failed', ['err' => $e->getMessage()]);
            return;
        }

        // 4) Gọi /v4/access_token (grant_type=refresh_token)
        $accessBase = rtrim((string) env('ZL_OAUTH_BASE', 'https://oauth.zaloapp.com'), '/');
        $tokenPath  = (string) env('ZL_ACCESS_TOKEN_PATH', '/v4/access_token');
        $secretKey  = (string) env('ZL_SECRET_KEY', '');
        $appId      = (string) env('ZL_APP_ID', '');

        if ($secretKey === '' || $appId === '') {
            Log::warning('[ZL][Refresh] missing ZL_SECRET_KEY/ZL_APP_ID');
            return;
        }

        try {
            $resp = Http::timeout(20)
                ->withHeaders([
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'secret_key'   => $secretKey,
                    // 'DPoP'      => $this->makeDpopHeader("$accessBase$tokenPath", 'POST'), // bật khi triển khai DPoP
                ])
                ->asForm()
                ->post("$accessBase$tokenPath", [
                    'refresh_token' => $refreshToken,
                    'app_id'        => $appId,
                    'grant_type'    => 'refresh_token',
                ]);
        } catch (\Throwable $e) {
            Log::error('[ZL][Refresh] http error', ['err' => $e->getMessage()]);
            return;
        }

        if (!$resp->ok()) {
            Log::warning('[ZL][Refresh] http status', ['status' => $resp->status(), 'body' => $resp->body()]);
            return;
        }

        $j = $resp->json();
        $accessToken  = (string) ($j['access_token']  ?? '');
        $newRefresh   = (string) ($j['refresh_token'] ?? '');
        $expiresIn    = (int)    ($j['expires_in']     ?? 3600);
        $refreshTtl   = (int)    ($j['refresh_token_expires_in'] ?? 0); // Zalo trả thời gian còn lại

        if ($accessToken === '' || $newRefresh === '') {
            Log::warning('[ZL][Refresh] invalid payload', ['json' => $j]);
            return;
        }

        // 5) Lưu token mới (thêm 1 bản ghi mới để có lịch sử)
        try {
            (new ZaloOAuthService())->storeEncrypted($accessToken, $newRefresh, $expiresIn, $refreshTtl ?: 0, $row->dpop_kid);
            Log::info('[ZL][Refresh] token refreshed', ['access_ttl' => $expiresIn, 'refresh_ttl' => $refreshTtl]);
        } catch (\Throwable $e) {
            Log::error('[ZL][Refresh] store failed', ['err' => $e->getMessage()]);
        }
    }
}
