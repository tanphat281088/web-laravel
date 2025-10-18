<?php

namespace App\Modules\PhieuNhapKho;

use App\Http\Controllers\Controller;
use App\Modules\PhieuNhapKho\Validates\CreatePhieuNhapKhoRequest;
use App\Modules\PhieuNhapKho\Validates\UpdatePhieuNhapKhoRequest;
use App\Class\CustomResponse;
use App\Class\Helper;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\PhieuNhapKhoImport;
use Illuminate\Support\Str;

class PhieuNhapKhoController extends Controller
{
  protected $phieuNhapKhoService;

  public function __construct(PhieuNhapKhoService $phieuNhapKhoService)
  {
    $this->phieuNhapKhoService = $phieuNhapKhoService;
  }

  /**
   * Lấy danh sách PhieuNhapKhos
   */
  public function index(Request $request)
  {
    $params = $request->all();

    // Xử lý và validate parameters
    $params = Helper::validateFilterParams($params);

    $result = $this->phieuNhapKhoService->getAll($params);

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
   * Tạo mới PhieuNhapKho
   */
  public function store(CreatePhieuNhapKhoRequest $request)
  {
    $result = $this->phieuNhapKhoService->create($request->validated());

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result, 'Tạo mới thành công');
  }

  /**
   * Lấy thông tin PhieuNhapKho
   */
  public function show($id)
  {
    $result = $this->phieuNhapKhoService->getById($id);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  /**
   * Cập nhật PhieuNhapKho
   */
  public function update(UpdatePhieuNhapKhoRequest $request, $id)
  {
    $result = $this->phieuNhapKhoService->update($id, $request->validated());

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result, 'Cập nhật thành công');
  }

  /**
   * Xóa PhieuNhapKho
   */
  public function destroy($id)
  {
    $result = $this->phieuNhapKhoService->delete($id);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success([], 'Xóa thành công');
  }

  /**
   * Lấy danh sách PhieuNhapKho dạng option
   */
  public function getOptions()
  {
    $result = $this->phieuNhapKhoService->getOptions();

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  /**
   * Lấy danh sách PhieuNhapKho dạng option
   */
  public function getOptionsByNhaCungCap($nhaCungCapId, Request $request)
  {
    $params = $request->all();
    $result = $this->phieuNhapKhoService->getOptionsByNhaCungCap($nhaCungCapId, $params);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  /**
   * Lấy tổng tiền cần thanh toán theo nha cung cap
   */
  public function getTongTienCanThanhToanTheoNhaCungCap($nhaCungCapId)
  {
    $result = $this->phieuNhapKhoService->getTongTienCanThanhToanTheoNhaCungCap($nhaCungCapId);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  /**
   * Lấy tổng tiền cần thanh toán theo nhiều phiếu nhập kho
   */
  public function getTongTienCanThanhToanTheoNhieuPhieuNhapKho(Request $request)
  {
    $phieuNhapKhoIds = $request->query('phieu_nhap_kho_ids');
    $result = $this->phieuNhapKhoService->getTongTienCanThanhToanTheoNhieuPhieuNhapKho($phieuNhapKhoIds);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  public function downloadTemplateExcel()
  {
    $path = public_path('mau-excel/PhieuNhapKho.xlsx');

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

      $import = new PhieuNhapKhoImport();
      Excel::import($import, $path);

      $thanhCong = $import->getThanhCong();
      $thatBai = $import->getThatBai();

      // Xóa file sau khi import
      if (file_exists($path)) {
        unlink($path);
      }

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