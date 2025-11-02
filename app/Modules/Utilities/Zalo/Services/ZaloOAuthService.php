<?php

namespace App\Modules\Utilities\Zalo\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

class ZaloOAuthService
{
    /** Lấy token hiện hành (nếu có) */
    public function current(): ?array
    {
        $row = DB::table('zl_tokens')->orderByDesc('id')->first();
        if (!$row) return null;

        return [
            'access_token'  => null, // không giải mã ở skeleton
            'refresh_token' => null, // không giải mã ở skeleton
            'expires_at'    => $row->expires_at,
            'refresh_expires_at' => $row->refresh_expires_at,
            'dpop_kid'      => $row->dpop_kid,
        ];
    }

    /** Lưu token (mã hoá) — dùng khi bạn implement callback/refresh thật */
    public function storeEncrypted(string $accessToken, string $refreshToken, int $accessTtlSec, int $refreshTtlSec, ?string $dpopKid = null): void
    {
        DB::table('zl_tokens')->insert([
            'access_token_enc'      => Crypt::encryptString($accessToken),
            'refresh_token_enc'     => Crypt::encryptString($refreshToken),
            'expires_at'            => now()->addSeconds($accessTtlSec),
            'refresh_expires_at'    => now()->addSeconds($refreshTtlSec),
            'app_id'                => env('ZL_APP_ID'),
            'scopes'                => null,
            'settings'              => null,
            'dpop_kid'              => $dpopKid,
            'status'                => 1,
            'last_health_check_at'  => now(),
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);
    }

    /** TTL còn lại (giây) cho FE health */
    public function ttl(): array
    {
        $row = DB::table('zl_tokens')->orderByDesc('id')->first();
        if (!$row) return ['access' => null, 'refresh' => null];

        $access = $row->expires_at ? max(0, now()->diffInSeconds($row->expires_at, false)) : null;
        $refresh = $row->refresh_expires_at ? max(0, now()->diffInSeconds($row->refresh_expires_at, false)) : null;
        return ['access' => $access, 'refresh' => $refresh];
    }

    /** Giải mã access_token hiện hành (dùng cho call API thật) */
    public function currentAccessTokenDecrypted(): ?string
    {
        $row = DB::table('zl_tokens')->orderByDesc('id')->first();
        if (!$row || empty($row->access_token_enc)) return null;
        try {
            return \Illuminate\Support\Facades\Crypt::decryptString($row->access_token_enc);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Xoá state hết hạn (dọn rác định kỳ) */
    public function cleanupExpiredStates(): int
    {
        return DB::table('zl_oauth_states')->where('expires_at', '<', now())->delete();
    }


}
