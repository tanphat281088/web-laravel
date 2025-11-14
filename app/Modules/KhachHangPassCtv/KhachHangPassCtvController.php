<?php

namespace App\Modules\KhachHangPassCtv;

use App\Class\CustomResponse;
use App\Http\Controllers\Controller;
use App\Models\KhachHang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Khách hàng Pass đơn & CTV
 *
 * - customer_mode = 1
 * - Dùng chung bảng khach_hangs, KHÁC với khách hàng vãng lai.
 * - Doanh số vẫn tích lũy như bình thường, nhưng KHÔNG hưởng giảm giá thành viên.
 */
class KhachHangPassCtvController extends Controller
{
    /**
     * GET /api/khach-hang-pass-ctv
     * Liệt kê danh sách khách hàng Pass/CTV (customer_mode = 1).
     *
     * Query:
     *  - q: tìm theo mã KH / tên / sđt / email
     *  - per_page: phân trang (mặc định 20)
     */
    public function index(Request $request)
    {
        $q       = trim((string) $request->input('q', ''));
        $perPage = (int) $request->input('per_page', 20);
        if ($perPage <= 0 || $perPage > 200) {
            $perPage = 20;
        }

        $query = KhachHang::query()
            ->where('customer_mode', 1)
            ->orderByDesc('id');

        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
            $query->where(function ($w) use ($like) {
                $w->where('ma_kh', 'like', $like)
                  ->orWhere('ten_khach_hang', 'like', $like)
                  ->orWhere('so_dien_thoai', 'like', $like)
                  ->orWhere('email', 'like', $like);
            });
        }

        $rows = $query->paginate($perPage);

        return CustomResponse::success($rows);
    }




        /**
     * GET /api/khach-hang-pass-ctv/options
     *
     * Trả về danh sách options KH Pass/CTV (customer_mode = 1)
     * để dùng cho dropdown trên FE.
     *
     * Query:
     *  - q|keyword|search|term: tìm theo mã KH / tên / SĐT
     *  - limit: số record (mặc định 30)
     */
    public function options(Request $request)
    {
        $kw = trim((string) (
            $request->input('keyword') ??
            $request->input('q') ??
            $request->input('search') ??
            $request->input('term') ??
            ''
        ));

        $limit = (int) $request->input('limit', 30);
        if ($limit <= 0 || $limit > 200) {
            $limit = 30;
        }

        $query = KhachHang::query()
            ->where('customer_mode', 1)
            ->selectRaw("
                id AS value,
                CONCAT(
                    COALESCE(ma_kh, ''),
                    ' - ',
                    COALESCE(ten_khach_hang, ''),
                    ' - ',
                    COALESCE(so_dien_thoai, '')
                ) AS label
            ")
            ->orderBy('ma_kh');

        if ($kw !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $kw) . '%';
            $query->where(function ($q) use ($like) {
                $q->where('ma_kh', 'like', $like)
                  ->orWhere('ten_khach_hang', 'like', $like)
                  ->orWhere('so_dien_thoai', 'like', $like);
            });
        }

        // Trả về COLLECTION thô giống /khach-hang/options (không bọc CustomResponse)
        return $query->limit($limit)->get();
    }

    /**
     * POST /api/khach-hang-pass-ctv/convert-to-pass/{id}
     *
     * Chuyển 1 khách hàng hệ thống THƯỜNG → Pass đơn & CTV.
     * - Đặt customer_mode = 1.
     * - Không chỉnh sửa các field khác.
     */
    public function convertToPass(int $id)
    {
        return DB::transaction(function () use ($id) {
            /** @var KhachHang|null $kh */
            $kh = KhachHang::lockForUpdate()->find($id);
            if (!$kh) {
                return CustomResponse::error('Không tìm thấy khách hàng.', 404);
            }

            // Nếu đã là Pass/CTV rồi thì coi như idempotent
            if ((int) ($kh->customer_mode ?? 0) === 1) {
                return CustomResponse::success([
                    'id'            => $kh->id,
                    'customer_mode' => 1,
                    'idempotent'    => true,
                ], 'Khách hàng đã ở trạng thái Pass/CTV.');
            }

            $kh->customer_mode = 1;
            $kh->save();

            return CustomResponse::success([
                'id'            => $kh->id,
                'customer_mode' => 1,
                'idempotent'    => false,
            ], 'Đã chuyển sang Khách hàng Pass đơn & CTV.');
        });
    }

    /**
     * POST /api/khach-hang-pass-ctv/convert-to-normal/{id}
     *
     * Chuyển 1 khách hàng Pass/CTV → Khách hàng hệ thống thường.
     * - Đặt customer_mode = 0.
     */
    public function convertToNormal(int $id)
    {
        return DB::transaction(function () use ($id) {
            /** @var KhachHang|null $kh */
            $kh = KhachHang::lockForUpdate()->find($id);
            if (!$kh) {
                return CustomResponse::error('Không tìm thấy khách hàng.', 404);
            }

            // Nếu đã là normal thì idempotent
            if ((int) ($kh->customer_mode ?? 0) === 0) {
                return CustomResponse::success([
                    'id'            => $kh->id,
                    'customer_mode' => 0,
                    'idempotent'    => true,
                ], 'Khách hàng đã ở trạng thái hệ thống thường.');
            }

            $kh->customer_mode = 0;
            $kh->save();

            return CustomResponse::success([
                'id'            => $kh->id,
                'customer_mode' => 0,
                'idempotent'    => false,
            ], 'Đã chuyển sang Khách hàng hệ thống.');
        });
    }
}
