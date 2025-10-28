<?php

namespace App\Http\Controllers\ThuChi;

use App\Http\Controllers\Controller;
use App\Models\DonHang;
use App\Modules\PhieuThu\AutoPhieuThuService;
use Illuminate\Http\JsonResponse;

class AutoThuController extends Controller
{
    /**
     * Force re-sync auto receipts for a given order by numeric ID.
     * GET /admin/thu-chi/re-sync/{donHangId}
     */
    public function reSync(int $donHangId, AutoPhieuThuService $service): JsonResponse
    {
        $donHang = DonHang::find($donHangId);
        if (!$donHang) {
            return response()->json(['success' => false, 'message' => 'Đơn hàng không tồn tại'], 404);
        }

        $service->syncAutoReceiptForOrder($donHang);

        return response()->json([
            'success' => true,
            'message' => 'Đã đồng bộ lại phiếu thu cho đơn hàng #' . ($donHang->ma_don_hang ?? $donHang->id),
        ]);
    }

    /**
     * Force re-sync by order CODE (ma_don_hang), ví dụ: DH-20251019-160623
     * GET /admin/thu-chi/re-sync-by-code/{maDonHang}
     */
    public function reSyncByCode(string $maDonHang, AutoPhieuThuService $service): JsonResponse
    {
        $donHang = DonHang::where('ma_don_hang', $maDonHang)->first();
        if (!$donHang) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn hàng có mã: ' . $maDonHang,
            ], 404);
        }

        $service->syncAutoReceiptForOrder($donHang);

        return response()->json([
            'success' => true,
            'message' => 'Đã đồng bộ lại phiếu thu cho đơn hàng #' . ($donHang->ma_don_hang ?? $donHang->id),
        ]);
    }
}
