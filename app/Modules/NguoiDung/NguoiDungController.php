<?php

namespace App\Modules\NguoiDung;

use App\Http\Controllers\Controller;
use App\Modules\NguoiDung\Validates\CreateNguoiDungRequest;
use App\Modules\NguoiDung\Validates\UpdateNguoiDungRequest;
use App\Class\CustomResponse;
use Illuminate\Http\Request;
use App\Class\Helper;

class NguoiDungController extends Controller
{
  protected $nguoiDungService;

  public function __construct(NguoiDungService $nguoiDungService)
  {
    $this->nguoiDungService = $nguoiDungService;
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
   * Tạo mới NguoiDung
   */
  public function store(CreateNguoiDungRequest $request)
  {
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
   * Cập nhật NguoiDung
   */
  public function update(UpdateNguoiDungRequest $request, $id)
  {
    $result = $this->nguoiDungService->update($id, $request->validated());

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result, 'Cập nhật thành công');
  }

  /**
   * Xóa NguoiDung
   */
  public function destroy($id)
  {
    $result = $this->nguoiDungService->delete($id);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success([], 'Xóa thành công');
    return CustomResponse::error('Không thể xóa tài khoản đang đăng nhập');
  }

  public function changeStatusNgoaiGio($id, Request $request)
  {
    $result = $this->nguoiDungService->changeStatusNgoaiGio($id, $request->all());

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result, 'Cập nhật thành công');
  }
}