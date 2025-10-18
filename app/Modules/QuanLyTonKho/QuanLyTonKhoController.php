<?php

namespace App\Modules\QuanLyTonKho;

use App\Http\Controllers\Controller;
use App\Modules\QuanLyTonKho\Validates\CreateQuanLyTonKhoRequest;
use App\Modules\QuanLyTonKho\Validates\UpdateQuanLyTonKhoRequest;
use App\Class\CustomResponse;
use App\Class\Helper;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\QuanLyTonKhoImport;
use Illuminate\Support\Str;

class QuanLyTonKhoController extends Controller
{
  protected $quanLyTonKhoService;

  public function __construct(QuanLyTonKhoService $quanLyTonKhoService)
  {
    $this->quanLyTonKhoService = $quanLyTonKhoService;
  }

  /**
   * Lấy danh sách QuanLyTonKhos
   */
  /**
   * Lấy danh sách QuanLyKhos kho tổng
   */
  public function index(Request $request)
  {
    $params = $request->all();

    // Xử lý và validate parameters
    $params = Helper::validateFilterParams($params);

    $result = $this->quanLyTonKhoService->getAll($params, 1);

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
   * Lấy danh sách QuanLyKhos kho bán lẻ
   */
  public function indexBanLe(Request $request)
  {
    $params = $request->all();

    // Xử lý và validate parameters
    $params = Helper::validateFilterParams($params);

    $result = $this->quanLyTonKhoService->getAll($params, 2);

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
   * Tạo mới QuanLyTonKho
   */
  public function store(CreateQuanLyTonKhoRequest $request)
  {
    $result = $this->quanLyTonKhoService->create($request->validated());

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result, 'Tạo mới thành công');
  }

  /**
   * Lấy thông tin QuanLyTonKho
   */
  public function show($id)
  {
    $result = $this->quanLyTonKhoService->getById($id);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  /**
   * Cập nhật QuanLyTonKho
   */
  public function update(UpdateQuanLyTonKhoRequest $request, $id)
  {
    $result = $this->quanLyTonKhoService->update($id, $request->validated());

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result, 'Cập nhật thành công');
  }

  /**
   * Xóa QuanLyTonKho
   */
  public function destroy($id)
  {
    $result = $this->quanLyTonKhoService->delete($id);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success([], 'Xóa thành công');
  }

  /**
   * Lấy danh sách QuanLyTonKho dạng option
   */
  public function getOptions()
  {
    $result = $this->quanLyTonKhoService->getOptions();

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  public function downloadTemplateExcel()
  {
    $path = public_path('mau-excel/QuanLyTonKho.xlsx');

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

      $import = new QuanLyTonKhoImport();
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