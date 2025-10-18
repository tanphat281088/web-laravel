<?php

namespace App\Class;

class CustomResponse
{
  public static function success($data = [], $message = 'Success', $code = 200)
  {
    return response()->json([
      'success' => true,
      'message' => $message,
      'data' => $data
    ], $code);
  }

  public static function error($message = 'Error', $errors = [], $code = 400)
  {
    return response()->json([
      'success' => false,
      'message' => $message,
      'errors' => $errors
    ], $code);
  }
}
