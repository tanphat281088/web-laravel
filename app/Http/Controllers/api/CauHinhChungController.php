<?php

namespace App\Http\Controllers\api;

use App\Class\CustomResponse;
use App\Http\Controllers\Controller;
use App\Models\CauHinhChung;
use Illuminate\Http\Request;

class CauHinhChungController extends Controller
{
  public function create(Request $request)
  {
    $data = $request->all();
    foreach ($data as $key => $value) {
      if (is_string($value) || is_numeric($value)) {
        $data[$key] = (string) $value;
      } else {
        $data[$key] = $value ? "1" : "0";
      }
      $cauHinhChung = CauHinhChung::where('ten_cau_hinh', $key)->first();
      if ($cauHinhChung) {
        $cauHinhChung->gia_tri = $data[$key];
        $cauHinhChung->save();
      } else {
        CauHinhChung::create(['ten_cau_hinh' => $key, 'gia_tri' => $data[$key]]);
      }
    }

    return CustomResponse::success([], 'Cập nhật thành công');
  }

  public function index()
  {
    $data = CauHinhChung::getAllConfig();
    return CustomResponse::success($data, 'Lấy dữ liệu thành công');
  }
}