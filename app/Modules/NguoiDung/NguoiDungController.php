<?php

namespace App\Modules\NguoiDung;

use App\Http\Controllers\Controller;
use App\Modules\NguoiDung\Validates\CreateNguoiDungRequest;
use App\Modules\NguoiDung\Validates\UpdateNguoiDungRequest;
use App\Class\CustomResponse;
use Illuminate\Http\Request;
use App\Class\Helper;
use Illuminate\Support\Facades\Auth;

class NguoiDungController extends Controller
{
  protected $nguoiDungService;

  public function __construct(NguoiDungService $nguoiDungService)
  {
    $this->nguoiDungService = $nguoiDungService;
  }

  /**
   * Chỉ chủ hệ thống mới được thao tác (create/update/delete/changeStatusNgoaiGio).
   * RBAC_OWNER_EMAIL có thể cấu hình trong .env, mặc định admin@gmail.com
   */
  private function isSystemOwner(): bool
  {
    $owner = env('RBAC_OWNER_EMAIL', 'admin@gmail.com');
    return strcasecmp(Auth::user()->email ?? '', $owner) === 0;
  }

  /**
   * Lấy danh sách NguoiDungs
   */
  public function index(Request $request)
  {
    try {
      // Lấy tất cả parameters từ request
      $params = $request->all();

      // Xử lý và validate parameters
      $params = Helper::validateFilterParams($params);

      $result = $this->nguoiDungService->getAll($params);

      if ($result instanceof \Illuminate\Http\JsonResponse) {
        return $result;
      }

      return CustomResponse::success([
        'collection' => $result['data'],
        'total' => $result['total'],
        'pagination' => $result['pagination'] ?? null
      ]);
    } catch (\Exception $e) {
      return CustomResponse::error($e->getMessage(), 500);
    }
  }

  /**
   * Tạo mới NguoiDung (chỉ chủ hệ thống)
   */
  public function store(CreateNguoiDungRequest $request)
  {
    if (!$this->isSystemOwner()) {
      return CustomResponse::error('Chỉ tài khoản quản trị hệ thống (admin@gmail.com) mới được tạo người dùng.', 403);
    }

    $validated = $request->validated();
    unset($validated['confirm_password']);

    $result = $this->nguoiDungService->create($validated);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result, 'Tạo mới thành công');
  }

  /**
   * Lấy thông tin NguoiDung
   */
  public function show($id)
  {
    $result = $this->nguoiDungService->getById($id);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  /**
   * Cập nhật NguoiDung (chỉ chủ hệ thống)
   */
  public function update(UpdateNguoiDungRequest $request, $id)
  {
    if (!$this->isSystemOwner()) {
      return CustomResponse::error('Chỉ tài khoản quản trị hệ thống (admin@gmail.com) mới được sửa người dùng.', 403);
    }

    $result = $this->nguoiDungService->update($id, $request->validated());

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result, 'Cập nhật thành công');
  }

  /**
   * Xóa NguoiDung (chỉ chủ hệ thống)
   */
  public function destroy($id)
  {
    if (!$this->isSystemOwner()) {
      return CustomResponse::error('Chỉ tài khoản quản trị hệ thống (admin@gmail.com) mới được xoá người dùng.', 403);
    }

    $result = $this->nguoiDungService->delete($id);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success([], 'Xóa thành công');
  }

  /**
   * Đổi trạng thái "ngoài giờ" (chỉ chủ hệ thống)
   */
  public function changeStatusNgoaiGio($id, Request $request)
  {
    if (!$this->isSystemOwner()) {
      return CustomResponse::error('Chỉ tài khoản quản trị hệ thống (admin@gmail.com) mới được thay đổi trạng thái ngoài giờ.', 403);
    }

    $result = $this->nguoiDungService->changeStatusNgoaiGio($id, $request->all());

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result, 'Cập nhật thành công');
  }
}
