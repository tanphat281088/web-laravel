<?php

namespace App\Modules\CongThucSanXuat;

use App\Http\Controllers\Controller;
use App\Modules\CongThucSanXuat\Validates\CreateCongThucSanXuatRequest;
use App\Modules\CongThucSanXuat\Validates\UpdateCongThucSanXuatRequest;
use App\Class\CustomResponse;
use App\Class\Helper;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\CongThucSanXuatImport;
use Illuminate\Support\Str;

class CongThucSanXuatController extends Controller
{
  protected $congThucSanXuatService;

  public function __construct(CongThucSanXuatService $congThucSanXuatService)
  {
    $this->congThucSanXuatService = $congThucSanXuatService;
  }

  /**
   * Lấy danh sách CongThucSanXuats
   */
  public function index(Request $request)
  {
    $params = $request->all();

    // Xử lý và validate parameters
    $params = Helper::validateFilterParams($params);

    $result = $this->congThucSanXuatService->getAll($params);

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
   * Tạo mới CongThucSanXuat
   */
  public function store(CreateCongThucSanXuatRequest $request)
  {
    $result = $this->congThucSanXuatService->create($request->validated());

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result, 'Tạo mới thành công');
  }

  /**
   * Lấy thông tin CongThucSanXuat
   */
  public function show($id)
  {
    $result = $this->congThucSanXuatService->getById($id);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  public function getLichSuCapNhat($id)
  {
    $result = $this->congThucSanXuatService->getLichSuCapNhat($id);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  /**
   * Cập nhật CongThucSanXuat
   */
  public function update(UpdateCongThucSanXuatRequest $request, $id)
  {
    $result = $this->congThucSanXuatService->update($id, $request->validated());

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result, 'Cập nhật thành công');
  }

  /**
   * Xóa CongThucSanXuat
   */
  public function destroy($id)
  {
    $result = $this->congThucSanXuatService->delete($id);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success([], 'Xóa thành công');
  }

  /**
   * Lấy danh sách CongThucSanXuat dạng option
   */
  public function getOptions(Request $request)
  {
    $params = $request->all();

    $params = Helper::validateFilterParams($params);

    $result = $this->congThucSanXuatService->getOptions($params);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  public function getBySanPhamIdAndDonViTinhId(Request $request)
  {
    $params = $request->all();

    $result = $this->congThucSanXuatService->getBySanPhamIdAndDonViTinhId($params['san_pham_id'], $params['don_vi_tinh_id']);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  public function downloadTemplateExcel()
  {
    $path = public_path('mau-excel/CongThucSanXuat.xlsx');

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

      $import = new CongThucSanXuatImport();
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