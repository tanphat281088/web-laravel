<?php

namespace App\Modules\LoaiKhachHang;

use App\Http\Controllers\Controller;
use App\Imports\LoaiKhachHangImport;
use App\Modules\LoaiKhachHang\Validates\CreateLoaiKhachHangRequest;
use App\Modules\LoaiKhachHang\Validates\UpdateLoaiKhachHangRequest;
use App\Class\CustomResponse;
use App\Class\Helper;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Str;

class LoaiKhachHangController extends Controller
{
  protected $loaiKhachHangService;

  public function __construct(LoaiKhachHangService $loaiKhachHangService)
  {
    $this->loaiKhachHangService = $loaiKhachHangService;
  }

  /**
   * Lấy danh sách LoaiKhachHangs
   */
  public function index(Request $request)
  {
    $params = $request->all();

    // Xử lý và validate parameters
    $params = Helper::validateFilterParams($params);

    $result = $this->loaiKhachHangService->getAll($params);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success([
      'collection' => $result['data'],
      'total' => $result['total'],
      'pagination' => $result['pagination'] ?? null
    ]);
  }

  /**
   * Tạo mới LoaiKhachHang
   */
  public function store(CreateLoaiKhachHangRequest $request)
  {
    $result = $this->loaiKhachHangService->create($request->validated());

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result, 'Tạo mới thành công');
  }

  /**
   * Lấy thông tin LoaiKhachHang
   */
  public function show($id)
  {
    $result = $this->loaiKhachHangService->getById($id);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  /**
   * Cập nhật LoaiKhachHang
   */
  public function update(UpdateLoaiKhachHangRequest $request, $id)
  {
    $result = $this->loaiKhachHangService->update($id, $request->validated());

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result, 'Cập nhật thành công');
  }

  /**
   * Xóa LoaiKhachHang
   */
  public function destroy($id)
  {
    $result = $this->loaiKhachHangService->delete($id);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success([], 'Xóa thành công');
  }

  public function getOptions()
  {
    $result = $this->loaiKhachHangService->getOptions();

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  public function downloadTemplateExcel()
  {
    $path = public_path('mau-excel/LoaiKhachHang.xlsx');

    if (!file_exists($path)) {
      return CustomResponse::error('File không tồn tại');
    }

    return response()->download($path);
  }

  public function importExcel(Request $request)
  {
    $request->validate([
      'file' => 'required|file|mimes:xlsx,xls,csv',
    ]);

    try {
      $data = $request->file('file');
      $filename = Str::random(10) . '.' . $data->getClientOriginalExtension();
      $path = $data->move(public_path('excel'), $filename);

      $import = new LoaiKhachHangImport($path);
      Excel::import($import, $path);

      $thanhCong = $import->getThanhCong();
      $thatBai = $import->getThatBai();

      if ($thatBai > 0) {
        return CustomResponse::error('Import không thành công. Có ' . $thatBai . ' bản ghi lỗi và ' . $thanhCong . ' bản ghi thành công. Vui lòng xem chi tiết lỗi trong mục "Lịch sử import".');
      }

      return CustomResponse::success([
        'success' => $thanhCong,
        'fail' => $thatBai
      ], 'Import thành công ' . $thanhCong . ' bản ghi');
    } catch (\Exception $e) {
      return CustomResponse::error('Lỗi import: ' . $e->getMessage(), 500);
    }
  }
}