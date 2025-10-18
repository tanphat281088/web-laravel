<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function getStatistics()
    {
        try {
            $stats = [
                'overview' => $this->getOverviewStats(),
                'revenue' => $this->getRevenueStats(),
                'inventory' => $this->getInventoryStats(),
                'orders' => $this->getOrderStats(),
                'suppliers' => $this->getSupplierStats(),
                'charts' => $this->getChartData(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể lấy dữ liệu thống kê',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function getOverviewStats()
    {
        // Thống kê tổng quan
        $totalProducts = DB::table('san_phams')->where('trang_thai', 1)->count();
        $totalSuppliers = DB::table('nha_cung_caps')->where('trang_thai', 1)->count();
        $totalCustomers = DB::table('khach_hangs')->where('trang_thai', 1)->count();
        $totalOrders = DB::table('don_hangs')->count();
        
        // Tính tổng giá trị kho
        $totalInventoryValue = DB::table('kho_tongs as kt')
            ->join('chi_tiet_phieu_nhap_khos as ct', 'kt.ma_lo_san_pham', '=', 'ct.ma_lo_san_pham')
            ->selectRaw('SUM(kt.so_luong_ton * ct.gia_von_don_vi) as total_value')
            ->value('total_value') ?? 0;

        // Sản phẩm sắp hết hàng
        $lowStockProducts = DB::table('kho_tongs as kt')
            ->join('san_phams as sp', 'kt.san_pham_id', '=', 'sp.id')
            ->selectRaw('COUNT(*) as count')
            ->whereRaw('kt.so_luong_ton <= sp.so_luong_canh_bao')
            ->value('count') ?? 0;

        return [
            'total_products' => $totalProducts,
            'total_suppliers' => $totalSuppliers,
            'total_customers' => $totalCustomers,
            'total_orders' => $totalOrders,
            'total_inventory_value' => $totalInventoryValue,
            'low_stock_products' => $lowStockProducts,
        ];
    }

    private function getRevenueStats()
    {
        $today = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();
        $thisYear = Carbon::now()->startOfYear();

        // Doanh thu hôm nay
        $todayRevenue = DB::table('don_hangs')
            ->whereDate('ngay_tao_don_hang', $today)
            ->sum('tong_tien_can_thanh_toan');

        // Doanh thu tháng này
        $monthRevenue = DB::table('don_hangs')
            ->whereDate('ngay_tao_don_hang', '>=', $thisMonth)
            ->sum('tong_tien_can_thanh_toan');

        // Doanh thu năm này
        $yearRevenue = DB::table('don_hangs')
            ->whereDate('ngay_tao_don_hang', '>=', $thisYear)
            ->sum('tong_tien_can_thanh_toan');

        // Chi phí nhập hàng tháng này
        $monthExpenses = DB::table('phieu_nhap_khos')
            ->whereDate('ngay_nhap_kho', '>=', $thisMonth)
            ->sum('tong_tien');

        // Lợi nhuận tháng này (ước tính)
        $monthProfit = $monthRevenue - $monthExpenses;

        return [
            'today_revenue' => $todayRevenue,
            'month_revenue' => $monthRevenue,
            'year_revenue' => $yearRevenue,
            'month_expenses' => $monthExpenses,
            'month_profit' => $monthProfit,
        ];
    }

    private function getInventoryStats()
    {
        // Tổng số lượng tồn kho
        $totalStock = DB::table('kho_tongs')->sum('so_luong_ton');
        
        // Số lượng sản phẩm theo trạng thái
        $stockByStatus = DB::table('kho_tongs')
            ->selectRaw('trang_thai, COUNT(*) as count, SUM(so_luong_ton) as total_quantity')
            ->groupBy('trang_thai')
            ->get()
            ->keyBy('trang_thai');

        // Top 5 sản phẩm tồn kho nhiều nhất
        $topStockProducts = DB::table('kho_tongs as kt')
            ->join('san_phams as sp', 'kt.san_pham_id', '=', 'sp.id')
            ->select('sp.ten_san_pham', 'sp.ma_san_pham', 
                    DB::raw('SUM(kt.so_luong_ton) as total_stock'))
            ->groupBy('kt.san_pham_id', 'sp.ten_san_pham', 'sp.ma_san_pham')
            ->orderByDesc('total_stock')
            ->limit(5)
            ->get();

        // Sản phẩm sắp hết hàng
        $lowStockProducts = DB::table('kho_tongs as kt')
            ->join('san_phams as sp', 'kt.san_pham_id', '=', 'sp.id')
            ->select('sp.ten_san_pham', 'sp.ma_san_pham', 'kt.so_luong_ton', 'sp.so_luong_canh_bao')
            ->whereRaw('kt.so_luong_ton <= sp.so_luong_canh_bao')
            ->orderBy('kt.so_luong_ton')
            ->limit(10)
            ->get();

        return [
            'total_stock' => $totalStock,
            'stock_by_status' => $stockByStatus,
            'top_stock_products' => $topStockProducts,
            'low_stock_products' => $lowStockProducts,
        ];
    }

    private function getOrderStats()
    {
        // Đơn hàng theo trạng thái
        $ordersByStatus = DB::table('don_hangs')
            ->selectRaw('trang_thai_thanh_toan, trang_thai_xuat_kho, COUNT(*) as count')
            ->groupBy('trang_thai_thanh_toan', 'trang_thai_xuat_kho')
            ->get();

        // Đơn hàng hôm nay
        $todayOrders = DB::table('don_hangs')
            ->whereDate('ngay_tao_don_hang', Carbon::today())
            ->count();

        // Đơn hàng tháng này
        $monthOrders = DB::table('don_hangs')
            ->whereDate('ngay_tao_don_hang', '>=', Carbon::now()->startOfMonth())
            ->count();

        // Đơn hàng chưa xử lý
        $pendingOrders = DB::table('don_hangs')
            ->where('trang_thai_xuat_kho', 0)
            ->count();

        return [
            'orders_by_status' => $ordersByStatus,
            'today_orders' => $todayOrders,
            'month_orders' => $monthOrders,
            'pending_orders' => $pendingOrders,
        ];
    }

    private function getSupplierStats()
    {
        // Top 5 nhà cung cấp theo giá trị nhập hàng
        $topSuppliers = DB::table('phieu_nhap_khos as pnk')
            ->join('nha_cung_caps as ncc', 'pnk.nha_cung_cap_id', '=', 'ncc.id')
            ->select('ncc.ten_nha_cung_cap', 'ncc.ma_nha_cung_cap',
                    DB::raw('SUM(pnk.tong_tien) as total_value'),
                    DB::raw('COUNT(pnk.id) as total_orders'))
            ->whereNotNull('pnk.nha_cung_cap_id')
            ->groupBy('ncc.id', 'ncc.ten_nha_cung_cap', 'ncc.ma_nha_cung_cap')
            ->orderByDesc('total_value')
            ->limit(5)
            ->get();

        return [
            'top_suppliers' => $topSuppliers,
        ];
    }

    private function getChartData()
    {
        // Doanh thu 12 tháng gần nhất
        $revenueChart = DB::table('don_hangs')
            ->selectRaw('YEAR(ngay_tao_don_hang) as year, MONTH(ngay_tao_don_hang) as month, SUM(tong_tien_can_thanh_toan) as revenue')
            ->whereDate('ngay_tao_don_hang', '>=', Carbon::now()->subMonths(12))
            ->groupByRaw('YEAR(ngay_tao_don_hang), MONTH(ngay_tao_don_hang)')
            ->orderByRaw('year, month')
            ->get()
            ->map(function($item) {
                return [
                    'period' => Carbon::createFromDate($item->year, $item->month, 1)->format('Y-m'),
                    'revenue' => $item->revenue
                ];
            });

        // Nhập kho vs Xuất kho 6 tháng gần nhất
        $inventoryChart = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i)->startOfMonth();
            $monthEnd = Carbon::now()->subMonths($i)->endOfMonth();
            
            $imports = DB::table('phieu_nhap_khos')
                ->whereBetween('ngay_nhap_kho', [$month, $monthEnd])
                ->sum('tong_tien');
                
            $exports = DB::table('phieu_xuat_khos')
                ->whereBetween('ngay_xuat_kho', [$month, $monthEnd])
                ->sum('tong_tien');
            
            $inventoryChart[] = [
                'period' => $month->format('Y-m'),
                'imports' => $imports,
                'exports' => $exports
            ];
        }

        // Thống kê sản phẩm theo danh mục
        $categoryChart = DB::table('san_phams as sp')
            ->join('danh_muc_san_phams as dm', 'sp.danh_muc_id', '=', 'dm.id')
            ->select('dm.ten_danh_muc', DB::raw('COUNT(sp.id) as count'))
            ->where('sp.trang_thai', 1)
            ->groupBy('dm.id', 'dm.ten_danh_muc')
            ->get();

        return [
            'revenue_chart' => $revenueChart,
            'inventory_chart' => $inventoryChart,
            'category_chart' => $categoryChart,
        ];
    }

    public function getRecentActivities()
    {
        try {
            $activities = [];

            // Đơn hàng mới nhất (5 đơn)
            $recentOrders = DB::table('don_hangs')
                ->select('ma_don_hang', 'ten_khach_hang', 'tong_tien_can_thanh_toan', 'ngay_tao_don_hang')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function($order) {
                    return [
                        'type' => 'order',
                        'title' => "Đơn hàng {$order->ma_don_hang}",
                        'description' => "Khách hàng: {$order->ten_khach_hang} - " . number_format($order->tong_tien_can_thanh_toan) . " VNĐ",
                        'time' => Carbon::parse($order->ngay_tao_don_hang)->diffForHumans(),
                        'icon' => 'shopping-cart'
                    ];
                });

            // Phiếu nhập kho mới nhất (3 phiếu)
            $recentImports = DB::table('phieu_nhap_khos as pnk')
                ->leftJoin('nha_cung_caps as ncc', 'pnk.nha_cung_cap_id', '=', 'ncc.id')
                ->select('pnk.ma_phieu_nhap_kho', 'ncc.ten_nha_cung_cap', 'pnk.tong_tien', 'pnk.ngay_nhap_kho')
                ->orderBy('pnk.created_at', 'desc')
                ->limit(3)
                ->get()
                ->map(function($import) {
                    return [
                        'type' => 'import',
                        'title' => "Nhập kho {$import->ma_phieu_nhap_kho}",
                        'description' => ($import->ten_nha_cung_cap ?? 'Sản xuất') . " - " . number_format($import->tong_tien) . " VNĐ",
                        'time' => Carbon::parse($import->ngay_nhap_kho)->diffForHumans(),
                        'icon' => 'package'
                    ];
                });

            $activities = $recentOrders->concat($recentImports)->sortByDesc('time')->take(8);

            return response()->json([
                'success' => true,
                'data' => $activities->values()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể lấy dữ liệu hoạt động gần đây',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}