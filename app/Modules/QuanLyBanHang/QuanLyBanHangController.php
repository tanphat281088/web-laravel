<?php

namespace App\Modules\QuanLyBanHang;

use App\Http\Controllers\Controller;
use App\Modules\QuanLyBanHang\Validates\CreateQuanLyBanHangRequest;
use App\Modules\QuanLyBanHang\Validates\UpdateQuanLyBanHangRequest;
use App\Class\CustomResponse;
use App\Class\Helper;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\QuanLyBanHangImport;
use Illuminate\Support\Str;

class QuanLyBanHangController extends Controller
{
  protected $quanLyBanHangService;

  public function __construct(QuanLyBanHangService $quanLyBanHangService)
  {
    $this->quanLyBanHangService = $quanLyBanHangService;
  }

  /**
   * Lấy danh sách QuanLyBanHangs
   */
  public function index(Request $request)
  {
    $params = $request->all();

    // Xử lý và validate parameters
    $params = Helper::validateFilterParams($params);

    $result = $this->quanLyBanHangService->getAll($params);

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
   * Lấy giá bán sản phẩm theo lựa chọn
   * Body: san_pham_id, don_vi_tinh_id, loai_gia (1=Đặt ngay, 2=Đặt trước 3 ngày)
   */
  public function getGiaBanSanPham(Request $request)
  {
    // ✅ THAY ĐỔI DUY NHẤT: validate & nhận thêm loai_gia
    $validated = $request->validate([
      'san_pham_id'    => 'required|integer|exists:san_phams,id',
      'don_vi_tinh_id' => 'required|integer',
      'loai_gia'       => 'required|integer|in:1,2', // 1=Đặt ngay, 2=Đặt trước 3 ngày
    ]);

    $result = $this->quanLyBanHangService->getGiaBanSanPham(
      (int) $validated['san_pham_id'],
      (int) $validated['don_vi_tinh_id'],
      (int) $validated['loai_gia'] // ✅ TRUYỀN XUỐNG SERVICE
    );

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  /**
   * Tạo mới QuanLyBanHang
   */
  public function store(CreateQuanLyBanHangRequest $request)
  {
    $result = $this->quanLyBanHangService->create($request->validated());

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result, 'Tạo mới thành công');
  }

  /**
   * Lấy thông tin QuanLyBanHang
   */
  public function show($id)
  {
    $result = $this->quanLyBanHangService->getById($id);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  /**
   * Cập nhật QuanLyBanHang
   */
  public function update(UpdateQuanLyBanHangRequest $request, $id)
  {
    $result = $this->quanLyBanHangService->update($id, $request->validated());

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result, 'Cập nhật thành công');
  }

  /**
   * Xóa QuanLyBanHang
   */
  public function destroy($id)
  {
    $result = $this->quanLyBanHangService->delete($id);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success([], 'Xóa thành công');
  }

  /**
   * Lấy danh sách QuanLyBanHang dạng option
   */
  public function getOptions(Request $request)
  {
    $params = $request->all();

    $params = Helper::validateFilterParams($params);

    $result = $this->quanLyBanHangService->getOptions([
      ...$params,
      'limit' => -1,
    ]);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  public function getSanPhamByDonHangId($donHangId)
  {
    $result = $this->quanLyBanHangService->getSanPhamByDonHangId($donHangId);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  public function getSoTienCanThanhToan($donHangId)
  {
    $result = $this->quanLyBanHangService->getSoTienCanThanhToan($donHangId);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  public function getDonHangByKhachHangId($khachHangId)
  {
    $result = $this->quanLyBanHangService->getDonHangByKhachHangId($khachHangId);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  public function downloadTemplateExcel()
  {
    $path = public_path('mau-excel/QuanLyBanHang.xlsx');

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

      $import = new QuanLyBanHangImport();
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

  /**
   * Xem trước hóa đơn HTML
   */
  public function xemTruocHoaDon($id)
  {
    $result = $this->quanLyBanHangService->xemTruocHoaDon($id);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return $result; // Trả về view HTML
  }
}
