<?php

namespace App\Modules\NhaCungCap;

use App\Models\NhaCungCap;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Class\CustomResponse;
use App\Class\FilterWithPagination;
use App\Models\PhieuNhapKho;

class NhaCungCapService
{
  /**
   * Lấy tất cả dữ liệu
   */
  public function getAll(array $params = [])
  {
    try {
      // Tạo query cơ bản
      $query = NhaCungCap::query()->with('images');

      // Sử dụng FilterWithPagination để xử lý filter và pagination
      $result = FilterWithPagination::findWithPagination(
        $query,
        $params,
        ['nha_cung_caps.*'] // Columns cần select
      );

      return [
        'data' => $result['collection'],
        'total' => $result['total'],
        'pagination' => [
          'current_page' => $result['current_page'],
          'last_page' => $result['last_page'],
          'from' => $result['from'],
          'to' => $result['to'],
          'total_current' => $result['total_current']
        ]
      ];
    } catch (Exception $e) {
      throw new Exception('Lỗi khi lấy danh sách: ' . $e->getMessage());
    }
  }

  /**
   * Lấy dữ liệu theo ID
   */
  public function getById($id)
  {
    $phieuNhapKho = PhieuNhapKho::where('nha_cung_cap_id', $id)->get();
    $congNo = $phieuNhapKho->sum('tong_tien') - $phieuNhapKho->sum('da_thanh_toan');

    $nhaCungCap = NhaCungCap::with('images')->find($id);
    $nhaCungCap->cong_no = $congNo;

    if (!$nhaCungCap) {
      return CustomResponse::error('Dữ liệu không tồn tại');
    }

    return $nhaCungCap;
  }

  /**
   * Tạo mới dữ liệu
   */
  public function create(array $data)
  {
    try {
      $result = NhaCungCap::create($data);

      // TODO: Thêm ảnh vào bảng images (nếu có)
      // $result->images()->create([
      //   'path' => $data['image'],
      // ]);

      return $result;
    } catch (Exception $e) {
      return CustomResponse::error($e->getMessage());
    }
  }

  /**
   * Cập nhật dữ liệu
   */
  public function update($id, array $data)
  {
    try {
      $model = NhaCungCap::findOrFail($id);
      $model->update($data);

      // TODO: Cập nhật ảnh vào bảng images (nếu có)
      // if ($data['image']) {
      //   $model->images()->get()->each(function ($image) use ($data) {
      //     $image->update([
      //       'path' => $data['image'],
      //     ]);
      //   });
      // }


      return $model->fresh();
    } catch (Exception $e) {
      return CustomResponse::error($e->getMessage());
    }
  }


  /**
   * Xóa dữ liệu
   */
  public function delete($id)
  {
    try {
      $model = NhaCungCap::findOrFail($id);

      // TODO: Xóa ảnh vào bảng images (nếu có)
      // $model->images()->get()->each(function ($image) {
      //   $image->delete();
      // });

      return $model->delete();
    } catch (Exception $e) {
      return CustomResponse::error($e->getMessage());
    }
  }

  /**
   * Lấy danh sách NhaCungCap dạng option
   */
  public function getOptions()
  {
    return NhaCungCap::select('id as value', 'ten_nha_cung_cap as label')->get();
  }
}