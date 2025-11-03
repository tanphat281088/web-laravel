<?php

namespace App\Modules\NguoiDung;

use App\Class\CustomResponse;
use App\Class\FilterWithPagination;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class NguoiDungService
{
  /**
   * Lấy tất cả dữ liệu
   */
  public function getAll(array $params = [])
  {
    try {
      // Tạo query cơ bản
      $query = User::query()->with('images')->with('vaiTro');

      // Sử dụng FilterWithPagination để xử lý filter và pagination
      $result = FilterWithPagination::findWithPagination(
        $query,
        $params,
        ['users.*'] // Columns cần select
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
      throw new Exception('Lỗi khi lấy danh sách người dùng: ' . $e->getMessage());
    }
  }

  /**
   * Lấy dữ liệu theo ID
   */
  public function getById($id)
  {
    $data = User::with('images')->find($id);
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
$user = User::create($data);
if (!empty($data['image'])) {
  $user->images()->create([
    'path' => $data['image'],
  ]);
}

      return $user;
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
      $model = User::findOrFail($id);
            // Chặn đổi vai trò của tài khoản quản trị hệ thống
      if (strcasecmp($model->email ?? '', 'admin@gmail.com') === 0 && array_key_exists('ma_vai_tro', $data)) {
        return CustomResponse::error('Không thể đổi vai trò của tài khoản quản trị hệ thống (admin@gmail.com).');
      }

      $model->update($data);
if (array_key_exists('image', $data) && !empty($data['image'])) {
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
$currentUser = Auth::user();
if ($currentUser && (int)$currentUser->id === (int)$id) {
  return CustomResponse::error('Không thể xoá tài khoản đang đăng nhập.');
}

$model = User::findOrFail($id);

// Chặn xoá tài khoản admin hệ thống
if (strcasecmp($model->email ?? '', 'admin@gmail.com') === 0) {
  return CustomResponse::error('Không thể xoá tài khoản quản trị hệ thống (admin@gmail.com).');
}

$model->images()->get()->each(function ($image) {
  $image->delete();
});

return $model->delete();

    } catch (Exception $e) {
      return CustomResponse::error($e->getMessage());
    }
  }

  public function changeStatusNgoaiGio($id, $data)
  {
    try {
      $model = User::findOrFail($id);
      $model->is_ngoai_gio = $data['is_ngoai_gio'] ? 1 : 0;
      $model->save();
      return $model->fresh();
    } catch (Exception $e) {
      return CustomResponse::error($e->getMessage());
    }
  }
}