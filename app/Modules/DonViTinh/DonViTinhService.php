<?php

namespace App\Modules\DonViTinh;

use App\Models\DonViTinh;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Class\CustomResponse;
use App\Class\FilterWithPagination;

class DonViTinhService
{
  /**
   * Lấy tất cả dữ liệu
   */
  public function getAll(array $params = [])
  {
    try {
      // Tạo query cơ bản
      $query = DonViTinh::query()->with('images');

      // Sử dụng FilterWithPagination để xử lý filter và pagination
      $result = FilterWithPagination::findWithPagination(
        $query,
        $params,
        ['don_vi_tinhs.*'] // Columns cần select
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
    $data = DonViTinh::with('images')->find($id);
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
      $result = DonViTinh::create($data);

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
      $model = DonViTinh::findOrFail($id);
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
      $model = DonViTinh::findOrFail($id);

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
   * Lấy danh sách DonViTinh dạng option
   */
  public function getOptions()
  {
    return DonViTinh::select('id as value', 'ten_don_vi as label')->get();
  }

  /**
   * Lấy danh sách DonViTinh dạng option theo sản phẩm
   */
  public function getOptionsBySanPham($sanPhamId)
  {
    return DonViTinh::whereHas('sanPhams', function ($query) use ($sanPhamId) {
      $query->withoutGlobalScope('withUserNames')
        ->where('san_phams.id', $sanPhamId);
    })
      ->withoutGlobalScope('withUserNames')
      ->select('id as value', 'ten_don_vi as label')
      ->get();
  }
}