<?php

namespace App\Http\Middleware;

use App\Models\CauHinhChung;
use App\Models\ThoiGianLamViec;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class JWT
{
  /**
   * Handle an incoming request.
   *
   * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
   */
  public function handle(Request $request, Closure $next): Response
  {
    if (strpos($request->url(), 'download-template-excel') !== false) {
      return $next($request);
    }

    try {
      $token = $request->header('Authorization');

      if ($token) {
        if (strpos($token, 'Bearer ') !== 0) {
          $token = 'Bearer ' . $token;
          $request->headers->set('Authorization', $token);
        }
      }

      $user = JWTAuth::parseToken()->authenticate();

      Log::info('Người dùng id: ' . $user->id . ', email: ' . $user->email . ' đang truy cập vào ' . $request->url() . ' với method: ' . $request->method());

      $cauHinhChung = CauHinhChung::getAllConfig();

      if ($user->status == config('constant.STATUS.KHOA')) {
        return response()->json([
          'error' => 'Tài khoản đã bị khóa. Vui lòng liên hệ với quản trị viên để được hỗ trợ.',
          'error_code' => 'ACCOUNT_LOCKED'
        ], Response::HTTP_UNAUTHORIZED);
      }

      if ($user->is_ngoai_gio == config('constant.CHO_PHEP_NGOAI_GIO.KHONG_CHO_PHEP') && $cauHinhChung['CHECK_THOI_GIAN_LAM_VIEC'] == config('constant.CHECK_THOI_GIAN_LAM_VIEC.KICH_HOAT')) {
        $currentDay = config('constant.CONVERT_DATE_TIME')[now()->format('l')];
        $currentTime = now()->format('H:i');

        $thoiGianLamViec = ThoiGianLamViec::where('thu', $currentDay)->first();

        if ($thoiGianLamViec) {
          $gioBatDau = $thoiGianLamViec->gio_bat_dau;
          $gioKetThuc = $thoiGianLamViec->gio_ket_thuc;

          if ($currentTime < $gioBatDau || $currentTime > $gioKetThuc) {
            return response()->json([
              'error' => 'Không được phép truy cập ngoài giờ làm việc',
              'error_code' => 'OUT_OF_WORKING_TIME'
            ], Response::HTTP_UNAUTHORIZED);
          }
        }
      }
    } catch (TokenExpiredException $e) {
      return response()->json([
        'error' => 'Token đã hết hạn',
        'error_code' => 'TOKEN_EXPIRED'
      ], Response::HTTP_UNAUTHORIZED);
    } catch (TokenInvalidException $e) {
      return response()->json([
        'error' => 'Token không hợp lệ',
        'error_code' => 'INVALID_TOKEN'
      ], Response::HTTP_UNAUTHORIZED);
    } catch (JWTException $e) {
      return response()->json([
        'error' => 'Token không tồn tại',
        'error_code' => 'TOKEN_NOT_FOUND'
      ], Response::HTTP_UNAUTHORIZED);
    }

    return $next($request);
  }
}