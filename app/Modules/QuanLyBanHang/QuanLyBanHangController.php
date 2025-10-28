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

// 🔽 BỔ SUNG: gọi service ghi nhận biến động điểm khi đơn đã thanh toán
use App\Services\MemberPointService;

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
    // ✅ validate & nhận thêm loai_gia
    $validated = $request->validate([
      'san_pham_id'    => 'required|integer|exists:san_phams,id',
      'don_vi_tinh_id' => 'required|integer',
      'loai_gia'       => 'required|integer|in:1,2',
    ]);

    $result = $this->quanLyBanHangService->getGiaBanSanPham(
      (int) $validated['san_pham_id'],
      (int) $validated['don_vi_tinh_id'],
      (int) $validated['loai_gia']
    );

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    return CustomResponse::success($result);
  }

  /**
   * Tạo mới QuanLyBanHang
   * - KHÔNG nhận ma_don_hang từ request (BE tự sinh theo id)
   */
  public function store(CreateQuanLyBanHangRequest $request)
  {
    // 🔒 Phòng thủ: loại bỏ ma_don_hang nếu FE gửi lên
    $payload = $request->validated();
    unset($payload['ma_don_hang']);

$result = $this->quanLyBanHangService->create($payload);

if ($result instanceof \Illuminate\Http\JsonResponse) {
  return $result;
}

// 🔽 Gọi ghi điểm (an toàn & idempotent)
$this->tryRecordPaidEvent((int) $result->id);

// Service đã return ->refresh() nên đảm bảo có ma_don_hang trong response
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
   * ➕ (BỔ SUNG HOOK AN TOÀN)
   * Sau khi update thành công, gọi MemberPointService để ghi nhận "biến động điểm"
   * nếu và chỉ nếu đơn đã ở trạng thái "đã thanh toán". Service sẽ tự kiểm tra:
   * - trạng thái thanh toán hiện tại của đơn (không phải ở FE),
   * - idempotency theo don_hang_id (1 đơn chỉ tạo 1 biến động),
   * - tính doanh thu, quy đổi điểm (1 điểm = 1.000 VND),
   * - không gửi ZNS ở đây (để anh chủ động gửi trong UI).
   */
  public function update(UpdateQuanLyBanHangRequest $request, $id)
  {
    // 🔒 phòng thủ tương tự (tránh sửa mã)
    $payload = $request->validated();
    unset($payload['ma_don_hang']);

    $result = $this->quanLyBanHangService->update($id, $payload);

    if ($result instanceof \Illuminate\Http\JsonResponse) {
      return $result;
    }

    // 🔽 HOOK MỀM: an toàn, không phá flow cũ, không throw lỗi ra ngoài.
    $this->tryRecordPaidEvent((int) $id);

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

  // ==========================
  // 🔽 PRIVATE HELPER BỔ SUNG
  // ==========================
  /**
   * Gọi service ghi nhận "biến động điểm" khi đơn đã thanh toán.
   * - Service tự kiểm tra trạng thái hiện tại của đơn trong DB.
   * - Tự idempotent theo don_hang_id (1 đơn 1 biến động).
   * - Không ném lỗi ra ngoài để không ảnh hưởng flow cập nhật đơn.
   */
  private function tryRecordPaidEvent(int $donHangId): void
  {
    try {
      /** @var \App\Services\MemberPointService $svc */
      $svc = app(MemberPointService::class);
      $svc->recordPaidOrder($donHangId);
    } catch (\Throwable $e) {
      // log lỗi nội bộ, không phá vỡ response cho FE
      report($e);
    }
  }
}
