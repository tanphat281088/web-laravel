<?php

namespace App\Modules\NhaCungCap;

use App\Http\Controllers\Controller;
use App\Modules\NhaCungCap\Validates\CreateNhaCungCapRequest;
use App\Modules\NhaCungCap\Validates\UpdateNhaCungCapRequest;
use App\Class\CustomResponse;
use App\Class\Helper;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\NhaCungCapImport;
use Illuminate\Support\Str;

class NhaCungCapController extends Controller
{
  protected $nhaCungCapService;

  public function __construct(NhaCungCapService $nhaCungCapService)
  {
    $this->nhaCungCapService = $nhaCungCapService;
  }

  /**
   * Lấy danh sách NhaCungCaps
   */
  public function index(Request $request)
  {
    $params = $request->all();

    // Xử lý và validate parameters
    $params = Helper::validateFilterParams($params);

    $result = $this->nhaCungCapService->getAll($params);

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
   * Tạo mới NhaCungCap
   */
  public function store(CreateNhaCungCapRequest $request)
  {
    $result = $this->nhaCungCapService->create($request->validated());

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result, 'Tạo mới thành công');
  }

  /**
   * Lấy thông tin NhaCungCap
   */
  public function show($id)
  {
    $result = $this->nhaCungCapService->getById($id);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  /**
   * Cập nhật NhaCungCap
   */
  public function update(UpdateNhaCungCapRequest $request, $id)
  {
    $result = $this->nhaCungCapService->update($id, $request->validated());

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result, 'Cập nhật thành công');
  }

  /**
   * Xóa NhaCungCap
   */
  public function destroy($id)
  {
    $result = $this->nhaCungCapService->delete($id);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success([], 'Xóa thành công');
  }

  /**
   * Lấy danh sách NhaCungCap dạng option
   */
  public function getOptions()
  {
    $result = $this->nhaCungCapService->getOptions();

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  public function downloadTemplateExcel()
  {
    $path = public_path('mau-excel/NhaCungCap.xlsx');

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
      logger()->info('Path: ' . $path);

      $import = new NhaCungCapImport($path);
      Excel::import($import, $path);

      $thanhCong = $import->getThanhCong();
      $thatBai = $import->getThatBai();

      if ($thatBai > 0) {
        return CustomResponse::error('Import không thành công. Có ' . $thatBai . ' bản ghi lỗi và ' . $thanhCong . ' bản ghi thành công');
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