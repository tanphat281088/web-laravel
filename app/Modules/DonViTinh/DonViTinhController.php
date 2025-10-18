<?php

namespace App\Modules\DonViTinh;

use App\Http\Controllers\Controller;
use App\Modules\DonViTinh\Validates\CreateDonViTinhRequest;
use App\Modules\DonViTinh\Validates\UpdateDonViTinhRequest;
use App\Class\CustomResponse;
use App\Class\Helper;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\DonViTinhImport;
use Illuminate\Support\Str;

class DonViTinhController extends Controller
{
  protected $donViTinhService;

  public function __construct(DonViTinhService $donViTinhService)
  {
    $this->donViTinhService = $donViTinhService;
  }

  /**
   * Lấy danh sách DonViTinhs
   */
  public function index(Request $request)
  {
    $params = $request->all();

    // Xử lý và validate parameters
    $params = Helper::validateFilterParams($params);

    $result = $this->donViTinhService->getAll($params);

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
   * Tạo mới DonViTinh
   */
  public function store(CreateDonViTinhRequest $request)
  {
    $result = $this->donViTinhService->create($request->validated());

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result, 'Tạo mới thành công');
  }

  /**
   * Lấy thông tin DonViTinh
   */
  public function show($id)
  {
    $result = $this->donViTinhService->getById($id);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  /**
   * Cập nhật DonViTinh
   */
  public function update(UpdateDonViTinhRequest $request, $id)
  {
    $result = $this->donViTinhService->update($id, $request->validated());

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result, 'Cập nhật thành công');
  }

  /**
   * Xóa DonViTinh
   */
  public function destroy($id)
  {
    $result = $this->donViTinhService->delete($id);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success([], 'Xóa thành công');
  }

  /**
   * Lấy danh sách DonViTinh dạng option
   */
  public function getOptions()
  {
    $result = $this->donViTinhService->getOptions();

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  public function getOptionsBySanPham($sanPhamId)
  {
    $result = $this->donViTinhService->getOptionsBySanPham($sanPhamId);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  public function downloadTemplateExcel()
  {
    $path = public_path('mau-excel/DonViTinh.xlsx');

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

      $import = new DonViTinhImport();
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