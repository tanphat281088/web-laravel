<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    /**
     * GET /api/dashboard/statistics
     * Hỗ trợ query:
     *  - top: số lượng hạng mục tối đa cho chart nhiều danh mục (mặc định 10, min 5, max 30)
     *  - months: số tháng doanh thu (mặc định 12)
     *  - inv_months: số tháng nhập/xuất (mặc định 6)
     */
    public function getStatistics(Request $request)
    {
        try {
            // ---- Đọc & chuẩn hoá tham số ----
            $top        = (int) $request->query('top', 10);
            $months     = (int) $request->query('months', 12);
            $invMonths  = (int) $request->query('inv_months', 6);

            // Giới hạn an toàn
            $top       = max(5, min(30, $top));
            $months    = max(3, min(36, $months));
            $invMonths = max(3, min(24, $invMonths));

            $stats = [
                'overview'  => $this->getOverviewStats(),
                'revenue'   => $this->getRevenueStats(),
                'inventory' => $this->getInventoryStats(),
                'orders'    => $this->getOrderStats(),
                'suppliers' => $this->getSupplierStats(),
                'customer_channels' => $this->getCustomerChannelStats(), // <-- THÊM DÒNG NÀY
                'charts'    => $this->getChartData($top, $months, $invMonths),
            ];

            return response()->json([
                'success' => true,
                'data'    => $stats
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể lấy dữ liệu thống kê',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    private function getOverviewStats(): array
    {
        $totalProducts   = DB::table('san_phams')->where('trang_thai', 1)->count();
        $totalSuppliers  = DB::table('nha_cung_caps')->where('trang_thai', 1)->count();
        $totalCustomers  = DB::table('khach_hangs')->where('trang_thai', 1)->count();
        $totalOrders     = DB::table('don_hangs')->count();

        // Tổng giá trị kho (so_luong_ton * gia_von_don_vi)
        $totalInventoryValue = DB::table('kho_tongs as kt')
            ->join('chi_tiet_phieu_nhap_khos as ct', 'kt.ma_lo_san_pham', '=', 'ct.ma_lo_san_pham')
            ->selectRaw('SUM(kt.so_luong_ton * ct.gia_von_don_vi) as total_value')
            ->value('total_value') ?? 0;

        // Sản phẩm sắp hết hàng
        $lowStockProducts = DB::table('kho_tongs as kt')
            ->join('san_phams as sp', 'kt.san_pham_id', '=', 'sp.id')
            ->whereRaw('kt.so_luong_ton <= sp.so_luong_canh_bao')
            ->count();

        return [
            'total_products'        => (int) $totalProducts,
            'total_suppliers'       => (int) $totalSuppliers,
            'total_customers'       => (int) $totalCustomers,
            'total_orders'          => (int) $totalOrders,
            'total_inventory_value' => (float) $totalInventoryValue,
            'low_stock_products'    => (int) $lowStockProducts,
        ];
    }

    private function getRevenueStats(): array
    {
        $today     = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();
        $thisYear  = Carbon::now()->startOfYear();

        $todayRevenue = (float) DB::table('don_hangs')
            ->whereDate('ngay_tao_don_hang', $today)
            ->sum('tong_tien_can_thanh_toan');

        $monthRevenue = (float) DB::table('don_hangs')
            ->whereDate('ngay_tao_don_hang', '>=', $thisMonth)
            ->sum('tong_tien_can_thanh_toan');

        $yearRevenue = (float) DB::table('don_hangs')
            ->whereDate('ngay_tao_don_hang', '>=', $thisYear)
            ->sum('tong_tien_can_thanh_toan');

        $monthExpenses = (float) DB::table('phieu_nhap_khos')
            ->whereDate('ngay_nhap_kho', '>=', $thisMonth)
            ->sum('tong_tien');

        $monthProfit = $monthRevenue - $monthExpenses;

        return [
            'today_revenue'  => $todayRevenue,
            'month_revenue'  => $monthRevenue,
            'year_revenue'   => $yearRevenue,
            'month_expenses' => $monthExpenses,
            'month_profit'   => $monthProfit,
        ];
    }

    private function getInventoryStats(): array
    {
        $totalStock = (int) DB::table('kho_tongs')->sum('so_luong_ton');

        $stockByStatus = DB::table('kho_tongs')
            ->selectRaw('trang_thai, COUNT(*) as count, SUM(so_luong_ton) as total_quantity')
            ->groupBy('trang_thai')
            ->get()
            ->keyBy('trang_thai');

        $topStockProducts = DB::table('kho_tongs as kt')
            ->join('san_phams as sp', 'kt.san_pham_id', '=', 'sp.id')
            ->select('sp.ten_san_pham', 'sp.ma_san_pham', DB::raw('SUM(kt.so_luong_ton) as total_stock'))
            ->groupBy('kt.san_pham_id', 'sp.ten_san_pham', 'sp.ma_san_pham')
            ->orderByDesc('total_stock')
            ->limit(5)
            ->get();

        $lowStockProducts = DB::table('kho_tongs as kt')
            ->join('san_phams as sp', 'kt.san_pham_id', '=', 'sp.id')
            ->select('sp.ten_san_pham', 'sp.ma_san_pham', 'kt.so_luong_ton', 'sp.so_luong_canh_bao')
            ->whereRaw('kt.so_luong_ton <= sp.so_luong_canh_bao')
            ->orderBy('kt.so_luong_ton')
            ->limit(10)
            ->get();

        return [
            'total_stock'        => $totalStock,
            'stock_by_status'    => $stockByStatus,
            'top_stock_products' => $topStockProducts,
            'low_stock_products' => $lowStockProducts,
        ];
    }

    private function getOrderStats(): array
    {
        $ordersByStatus = DB::table('don_hangs')
            ->selectRaw('trang_thai_thanh_toan, trang_thai_xuat_kho, COUNT(*) as count')
            ->groupBy('trang_thai_thanh_toan', 'trang_thai_xuat_kho')
            ->get();

        $todayOrders  = (int) DB::table('don_hangs')->whereDate('ngay_tao_don_hang', Carbon::today())->count();
        $monthOrders  = (int) DB::table('don_hangs')->whereDate('ngay_tao_don_hang', '>=', Carbon::now()->startOfMonth())->count();
        $pendingOrders= (int) DB::table('don_hangs')->where('trang_thai_xuat_kho', 0)->count();

        return [
            'orders_by_status' => $ordersByStatus,
            'today_orders'     => $todayOrders,
            'month_orders'     => $monthOrders,
            'pending_orders'   => $pendingOrders,
        ];
    }

    private function getSupplierStats(): array
    {
        $topSuppliers = DB::table('phieu_nhap_khos as pnk')
            ->join('nha_cung_caps as ncc', 'pnk.nha_cung_cap_id', '=', 'ncc.id')
            ->select(
                'ncc.ten_nha_cung_cap',
                'ncc.ma_nha_cung_cap',
                DB::raw('SUM(pnk.tong_tien) as total_value'),
                DB::raw('COUNT(pnk.id) as total_orders')
            )
            ->whereNotNull('pnk.nha_cung_cap_id')
            ->groupBy('ncc.id', 'ncc.ten_nha_cung_cap', 'ncc.ma_nha_cung_cap')
            ->orderByDesc('total_value')
            ->limit(5)
            ->get();

        return [
            'top_suppliers' => $topSuppliers,
        ];
    }

    /**
     * Chuẩn hoá dữ liệu biểu đồ, giảm mật độ hiển thị.
     * - $top: Top-N tối đa (còn lại gộp "Khác")
     * - $months: số tháng doanh thu
     * - $invMonths: số tháng nhập/xuất kho
     
    
    */

/**
 * Chi tiết khách hàng theo kênh liên hệ.
 * Tự phát hiện tên cột: ưu tiên 'kenh_lien_he', fallback 'nguon_khach'.
 * Gộp rỗng/NULL => "Không rõ". Không lọc trạng_thái để tránh mất dữ liệu.
 */
private function getCustomerChannelStats(): array
{
    // Xác định cột kênh liên hệ
    $col = null;
    if (Schema::hasColumn('khach_hangs', 'kenh_lien_he')) {
        $col = 'kenh_lien_he';
    } elseif (Schema::hasColumn('khach_hangs', 'nguon_khach')) {
        $col = 'nguon_khach';
    }

    // Không có cột phù hợp -> trả rỗng an toàn
    if (!$col) {
        return ['total' => 0, 'items' => []];
    }

    // Gom nhóm, gộp chuỗi rỗng/NULL thành "Không rõ"
    $rows = DB::table('khach_hangs')
        ->selectRaw("COALESCE(NULLIF(TRIM($col), ''), 'Không rõ') AS channel, COUNT(*) AS count")
        // ->where('trang_thai', 1) // nếu muốn chỉ lấy KH đang hoạt động thì mở dòng này
        ->groupBy('channel')
        ->orderByDesc('count')
        ->get();

    return [
        'total' => (int) ($rows->sum('count') ?? 0),
        'items' => $rows,
    ];
}



    private function getChartData(int $top, int $months, int $invMonths): array
    {
        // ---- Doanh thu N tháng gần nhất ----
        $fromRevenue = Carbon::now()->startOfMonth()->subMonths($months - 1);
        $revenueChart = DB::table('don_hangs')
            ->selectRaw('YEAR(ngay_tao_don_hang) as y, MONTH(ngay_tao_don_hang) as m, SUM(tong_tien_can_thanh_toan) as revenue')
            ->whereDate('ngay_tao_don_hang', '>=', $fromRevenue)
            ->groupByRaw('YEAR(ngay_tao_don_hang), MONTH(ngay_tao_don_hang)')
            ->orderByRaw('y, m')
            ->get()
            ->map(function ($r) {
                return [
                    'period'  => Carbon::createFromDate($r->y, $r->m, 1)->format('Y-m'),
                    'revenue' => (float) $r->revenue,
                ];
            });

        // ---- Nhập kho / Xuất kho K tháng gần nhất ----
        $inventoryChart = [];
        for ($i = $invMonths - 1; $i >= 0; $i--) {
            $mStart = Carbon::now()->subMonths($i)->startOfMonth();
            $mEnd   = Carbon::now()->subMonths($i)->endOfMonth();

            $imports = (float) DB::table('phieu_nhap_khos')
                ->whereBetween('ngay_nhap_kho', [$mStart, $mEnd])
                ->sum('tong_tien');

            $exports = (float) DB::table('phieu_xuat_khos')
                ->whereBetween('ngay_xuat_kho', [$mStart, $mEnd])
                ->sum('tong_tien');

            $inventoryChart[] = [
                'period'  => $mStart->format('Y-m'),
                'imports' => $imports,
                'exports' => $exports,
            ];
        }

        // ---- Phân bố sản phẩm theo danh mục: Top-N + "Khác" ----
        // Ưu tiên window function (MySQL 8). Fallback nếu không hỗ trợ.
        $supportsWindow = true;
        try {
            DB::select('SELECT 1 AS t FROM (SELECT 1) x WINDOW w AS ()'); // thử cú pháp WINDOW (đánh dấu hỗ trợ)
        } catch (\Throwable $e) {
            $supportsWindow = false;
        }

        if ($supportsWindow) {
            // Dùng window function để xếp hạng & gộp Others
            $raw = DB::table('san_phams as sp')
                ->join('danh_muc_san_phams as dm', 'sp.danh_muc_id', '=', 'dm.id')
                ->selectRaw("
                    dm.id,
                    dm.ten_danh_muc as category,
                    COUNT(sp.id) as cnt,
                    DENSE_RANK() OVER (ORDER BY COUNT(sp.id) DESC) as rnk
                ")
                ->where('sp.trang_thai', 1)
                ->groupBy('dm.id', 'dm.ten_danh_muc')
                ->orderByDesc('cnt')
                ->get();

            $topRows   = [];
            $othersSum = 0;
            foreach ($raw as $row) {
                if ((int)$row->rnk <= $top) {
                    $topRows[] = ['name' => $row->category, 'value' => (int) $row->cnt];
                } else {
                    $othersSum += (int) $row->cnt;
                }
            }
        } else {
            // Fallback: 2 truy vấn (Top-N + tổng toàn bộ)
            $topRaw = DB::table('san_phams as sp')
                ->join('danh_muc_san_phams as dm', 'sp.danh_muc_id', '=', 'dm.id')
                ->select('dm.id', 'dm.ten_danh_muc as category', DB::raw('COUNT(sp.id) as cnt'))
                ->where('sp.trang_thai', 1)
                ->groupBy('dm.id', 'dm.ten_danh_muc')
                ->orderByDesc('cnt')
                ->limit($top)
                ->get();

            $totalCnt = (int) DB::table('san_phams')->where('trang_thai', 1)->count();
            $sumTop   = 0;
            $topRows  = [];
            foreach ($topRaw as $row) {
                $sumTop += (int) $row->cnt;
                $topRows[] = ['name' => $row->category, 'value' => (int) $row->cnt];
            }
            $othersSum = max(0, $totalCnt - $sumTop);
        }

        $categoryChart = $topRows;
        if ($othersSum > 0) {
            $categoryChart[] = ['name' => 'Khác', 'value' => $othersSum];
        }

        // Meta để FE (mới) có thể hiện tooltip/chi tiết nếu cần
        $categoryMeta = [
            'top'           => $top,
            'total_classes' => (int) DB::table('danh_muc_san_phams')->count(),
            'others_value'  => (int) $othersSum,
            'others_name'   => 'Khác',
        ];

        return [
            'revenue_chart'       => $revenueChart,
            'inventory_chart'     => $inventoryChart,
            // FE cũ dùng ngay field này (đã là Top-N + Khác)
            'category_chart'      => $categoryChart,
            // FE mới có thể dùng meta để mở modal drilldown nếu muốn
            'category_chart_meta' => $categoryMeta,
        ];
    }

    public function getRecentActivities()
    {
        try {
            // Đơn hàng mới nhất (5 đơn)
            $recentOrders = DB::table('don_hangs')
                ->select('ma_don_hang', 'ten_khach_hang', 'tong_tien_can_thanh_toan', 'ngay_tao_don_hang')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($order) {
                    return [
                        'type'        => 'order',
                        'title'       => "Đơn hàng {$order->ma_don_hang}",
                        'description' => "Khách hàng: {$order->ten_khach_hang} - " . number_format((float)$order->tong_tien_can_thanh_toan) . " VNĐ",
                        'time'        => Carbon::parse($order->ngay_tao_don_hang)->diffForHumans(),
                        'icon'        => 'shopping-cart',
                    ];
                });

            // Phiếu nhập kho mới nhất (3 phiếu)
            $recentImports = DB::table('phieu_nhap_khos as pnk')
                ->leftJoin('nha_cung_caps as ncc', 'pnk.nha_cung_cap_id', '=', 'ncc.id')
                ->select('pnk.ma_phieu_nhap_kho', 'ncc.ten_nha_cung_cap', 'pnk.tong_tien', 'pnk.ngay_nhap_kho')
                ->orderBy('pnk.created_at', 'desc')
                ->limit(3)
                ->get()
                ->map(function ($import) {
                    return [
                        'type'        => 'import',
                        'title'       => "Nhập kho {$import->ma_phieu_nhap_kho}",
                        'description' => ($import->ten_nha_cung_cap ?? 'Sản xuất') . " - " . number_format((float)$import->tong_tien) . " VNĐ",
                        'time'        => Carbon::parse($import->ngay_nhap_kho)->diffForHumans(),
                        'icon'        => 'package',
                    ];
                });

            $activities = $recentOrders->concat($recentImports)->take(8)->values();

            return response()->json([
                'success' => true,
                'data'    => $activities
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể lấy dữ liệu hoạt động gần đây',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
