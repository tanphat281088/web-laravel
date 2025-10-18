<?php

namespace App\Http\Controllers\api;

use App\Class\CustomResponse;
use App\Http\Controllers\Controller;
use App\Models\ThoiGianLamViec;
use Illuminate\Http\Request;

class ThoiGianLamViecController extends Controller
{
  public function index()
  {
    $thoiGianLamViec = ThoiGianLamViec::all();
    return CustomResponse::success($thoiGianLamViec);
  }

  public function update(Request $request, $id)
  {
    $thoiGianLamViec = ThoiGianLamViec::find($id);

    if (!$thoiGianLamViec) {
      return CustomResponse::error('Không tìm thấy thời gian làm việc với ID đã cho', 404);
    }

    $thoiGianLamViec->update([
      'gio_bat_dau' => $request->gio_bat_dau,
      'gio_ket_thuc' => $request->gio_ket_thuc,
      'ghi_chu' => $request->ghi_chu,
    ]);
    return CustomResponse::success($thoiGianLamViec, 'Cập nhật thời gian làm việc thành công');
  }
}