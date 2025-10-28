<?php

namespace App\Class;

class CustomResponse
{
  /**
   * Success response (giữ nguyên hành vi cũ).
   *
   * @param mixed  $data
   * @param string $message
   * @param int    $code    HTTP status code
   */
  public static function success($data = [], $message = 'Success', $code = 200)
  {
    return response()->json([
      'success' => true,
      'message' => $message,
      'data'    => $data,
    ], $code);
  }

  /**
   * Error response (giữ nguyên hành vi cũ).
   *
   * @param string $message
   * @param mixed  $errors
   * @param int    $code    HTTP status code
   */
  public static function error($message = 'Error', $errors = [], $code = 400)
  {
    return response()->json([
      'success' => false,
      'message' => $message,
      'errors'  => $errors,
    ], $code);
  }

  /**
   * ✅ NEW: Alias cho các nơi đang gọi CustomResponse::failed($data, $appCode, $httpCode?)
   * - Không phá vỡ tính năng cũ.
   * - Trả về schema quen thuộc cho FE:
   *     { success:false, code:<appCode>, data:<payload> }
   *
   * @param mixed       $data     payload (errors/extra/…)
   * @param string|null $appCode  mã ứng dụng (vd: VALIDATION_ERROR, OUT_OF_GEOFENCE,…)
   * @param int         $httpCode HTTP status code (mặc định 400); có thể bị override bằng ->setStatusCode() phía ngoài.
   */
  public static function failed($data = [], ?string $appCode = 'ERROR', int $httpCode = 400)
  {
    return response()->json([
      'success' => false,
      'code'    => $appCode,
      'data'    => $data,
    ], $httpCode);
  }
}
