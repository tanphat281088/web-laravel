<?php

namespace App\Class;

use Carbon\Carbon;

class Helper
{
  public static function generateOTP()
  {
    return rand(100000, 999999);
  }

  public static function validateFilterParams(array &$params)
  {
    // Đảm bảo các tham số có giá trị mặc định
    $params['page'] = isset($params['page']) ? (int) $params['page'] : 1;
    $params['limit'] = isset($params['limit']) ? (int) $params['limit'] : 10;
    $params['sort_direction'] = $params['sort_direction'] ?? 'desc';
    $params['sort_column'] = $params['sort_column'] ?? 'created_at';

    // Validate filter parameters
    if (isset($params['f']) && is_array($params['f'])) {
      foreach ($params['f'] as $index => $filter) {
        if (!isset($filter['field']) || !isset($filter['operator']) || !isset($filter['value'])) {
          unset($params['f'][$index]);
        }
      }
      // Reindex array để đảm bảo index liên tục
      $params['f'] = array_values($params['f']);
    }

    return $params;
  }

  public static function formatDateForSql($date): string
  {
    try {
      if ($date instanceof Carbon || $date instanceof \DateTime) {
        return $date->format('Y-m-d H:i:s');
      }
      return Carbon::parse($date)->format('Y-m-d H:i:s');
    } catch (\Exception $e) {
      return $date;
    }
  }

  public static function convertMethod($path, $method)
  {
    // Chuẩn hoá: bỏ "api/" ở đầu nếu có
    $cleanPath = ltrim(preg_replace('#^api/#', '', (string) $path), '/');

    // 1) Ưu tiên nhận diện EXPORT/template trước (áp dụng cho cả list/detail)
    //    Ví dụ: .../download-template-excel, .../export, .../kqkd-export
    if (preg_match('#(download-template-excel|kqkd-export|/export)(\?|/|$)#', $cleanPath) === 1) {
      return 'export';
    }

    // 2) CRUD cơ bản dựa theo số segment & method (giữ logic cũ, nhưng dùng $cleanPath)
    $pathArr = explode('/', $cleanPath);

    // /prefix (không có id)
    if (count($pathArr) === 1 || $pathArr[1] === '') {
      switch (strtoupper($method)) {
        case 'GET':
          return 'index';
        case 'POST':
          return 'create';
      }
    }

    // /prefix/{id} hoặc sâu hơn
    if (count($pathArr) > 1) {
      switch (strtoupper($method)) {
        case 'GET':
          return 'show';
        case 'PUT':
        case 'PATCH':
          return 'edit';
        case 'DELETE':
          return 'delete';
      }
    }

    // 3) Mặc định an toàn
    return 'index';
  }


  public static function generateMaLoSanPham()
  {
    $maLo = "LOSP_" . strtoupper(uniqid());

    return $maLo;
  }

  public static function checkIsToday($date)
  {
    try {
      // Chuyển đổi về Carbon nếu cần
      if (is_string($date)) {
        // Nếu là chuỗi định dạng d/m/Y H:i:s (từ trait DateTimeFormatter)
        $carbonDate = str_contains($date, '/')
          ? Carbon::createFromFormat('d/m/Y H:i:s', $date)
          : Carbon::parse($date);
      } elseif ($date instanceof \DateTime) {
        $carbonDate = Carbon::instance($date);
      } else {
        $carbonDate = $date; // Đã là Carbon
      }

      // So sánh ngày
      return $carbonDate->isToday();
    } catch (\Exception $e) {
      return false;
    }
  }
}