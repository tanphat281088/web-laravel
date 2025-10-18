<?php

namespace App\Modules\DanhMucSanPham;

use App\Models\DanhMucSanPham;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Class\CustomResponse;
use App\Class\FilterWithPagination;

class DanhMucSanPhamService
{
  /**
   * Lấy tất cả dữ liệu
   */
  public function getAll(array $params = [])
  {
    try {
      // Tạo query cơ bản
      $query = DanhMucSanPham::query()->with('images');

      // Sử dụng FilterWithPagination để xử lý filter và pagination
      $result = FilterWithPagination::findWithPagination(
        $query,
        $params,
        ['danh_muc_san_phams.*'] // Columns cần select
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
    $data = DanhMucSanPham::with('images')->find($id);
    if (!$data) {
      return CustomResponse::error('Dữ liệu không tồn tại');
    }
    return $data;
  }

  /**
   * Tạo mới dữ liệu
   */
  public function create(array $data)
  {
    try {
      $result = DanhMucSanPham::create($data);

      // TODO: Thêm ảnh vào bảng images (nếu có)
      $result->images()->create([
        'path' => $data['image'],
      ]);

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
      $model = DanhMucSanPham::findOrFail($id);
      $model->update($data);

      // TODO: Cập nhật ảnh vào bảng images (nếu có)
      if ($data['image']) {
        $model->images()->get()->each(function ($image) use ($data) {
          $image->update([
            'path' => $data['image'],
          ]);
        });
      }


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
      $model = DanhMucSanPham::findOrFail($id);

      // TODO: Xóa ảnh vào bảng images (nếu có)
      $model->images()->get()->each(function ($image) {
        $image->delete();
      });

      return $model->delete();
    } catch (Exception $e) {
      return CustomResponse::error($e->getMessage());
    }
  }

  /**
   * Lấy danh sách DanhMucSanPham dạng option
   */
  public function getOptions()
  {
    return DanhMucSanPham::select('id as value', 'ten_danh_muc as label')->get();
  }
}