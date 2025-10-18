<?php

namespace App\Modules\PhieuChi;

use App\Http\Controllers\Controller;
use App\Modules\PhieuChi\Validates\CreatePhieuChiRequest;
use App\Modules\PhieuChi\Validates\UpdatePhieuChiRequest;
use App\Class\CustomResponse;
use App\Class\Helper;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\PhieuChiImport;
use Illuminate\Support\Str;

class PhieuChiController extends Controller
{
  protected $phieuChiService;

  public function __construct(PhieuChiService $phieuChiService)
  {
    $this->phieuChiService = $phieuChiService;
  }

  /**
   * Lấy danh sách PhieuChis
   */
  public function index(Request $request)
  {
    $params = $request->all();

    // Xử lý và validate parameters
    $params = Helper::validateFilterParams($params);

    $result = $this->phieuChiService->getAll($params);

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
   * Tạo mới PhieuChi
   */
  public function store(CreatePhieuChiRequest $request)
  {
    $result = $this->phieuChiService->create($request->validated());
    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }
    return CustomResponse::success($result, 'Tạo mới thành công');
  }

  /**
   * Lấy thông tin PhieuChi
   */
  public function show($id)
  {
    $result = $this->phieuChiService->getById($id);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  /**
   * Cập nhật PhieuChi
   */
  public function update(UpdatePhieuChiRequest $request, $id)
  {
    return CustomResponse::error('Không thể cập nhật phiếu chi');
  }

  /**
   * Xóa PhieuChi
   */
  public function destroy($id)
  {
    $result = $this->phieuChiService->delete($id);
    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }
    return CustomResponse::success([], 'Xóa thành công');
  }

  /**
   * Lấy danh sách PhieuChi dạng option
   */
  public function getOptions()
  {
    $result = $this->phieuChiService->getOptions();

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  public function downloadTemplateExcel()
  {
    $path = public_path('mau-excel/PhieuChi.xlsx');

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

      $import = new PhieuChiImport();
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