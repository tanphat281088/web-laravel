<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use App\Services\Reports\FinanceReportService;

class DashboardController extends Controller
{
    public function getStatistics(Request $request)
    {
        try {
            $top       = (int) $request->query('top', 10);
            $months    = (int) $request->query('months', 12);
            $invMonths = (int) $request->query('inv_months', 6);

            $top       = max(5, min(30, $top));
            $months    = max(3, min(36, $months));
            $invMonths = max(3, min(24, $invMonths));

            $stats = [
                'overview'          => $this->getOverviewStats(),
                'revenue'           => $this->getRevenueStats(),     // month_profit = LNTT (13)
                'inventory'         => $this->getInventoryStats(),   // VT-aware + fallback
                'orders'            => $this->getOrderStats(),
                'suppliers'         => $this->getSupplierStats(),
                'customer_channels' => $this->getCustomerChannelStats(),
                'kpis'              => $this->getTodayKpis(),
                'kpis_month'        => $this->getMonthKpis(),
                'charts'            => $this->getChartData($top, $months, $invMonths),
            ];

            return response()->json(['success' => true, 'data' => $stats]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể lấy dữ liệu thống kê',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /* =================== helpers =================== */

    private function tzNow(): Carbon
    {
        return Carbon::now(config('app.timezone', 'Asia/Ho_Chi_Minh'));
    }

    private function todayRange(): array
    {
        $now = $this->tzNow();
        return [$now->copy()->startOfDay(), $now->copy()->endOfDay()];
    }

    private function monthRange(): array
    {
        $now = $this->tzNow();
        return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
    }

    /* =================== OVERVIEW =================== */

    private function getOverviewStats(): array
    {
        $db = config('database.connections.mysql.database');

        $totalProducts  = (int) DB::table('san_phams')->count();
        $totalCustomers = (int) DB::table('khach_hangs')->count();
        $totalOrders    = (int) DB::table('don_hangs')->count();

        // Chuẩn bị cả 2 nguồn để debug & fallback
        $productVal = 0.0;
        if (Schema::hasTable('kho_tongs') && Schema::hasTable('chi_tiet_phieu_nhap_khos')) {
            $productVal = (float) DB::table('kho_tongs as kt')
                ->leftJoin('chi_tiet_phieu_nhap_khos as ct', 'ct.ma_lo_san_pham', '=', 'kt.ma_lo_san_pham')
                ->selectRaw('COALESCE(SUM(COALESCE(kt.so_luong_ton,0)*COALESCE(ct.gia_von_don_vi,0)),0) AS v')
                ->value('v');
        }

        $vtAssetVal = 0.0; $vtConsVal = 0.0;
        if (Schema::hasTable('vt_stocks') && Schema::hasTable('vt_items')) {
            // ASSET: dùng gia_tri_ton
            $vtAssetVal = (float) DB::table('vt_stocks as s')
                ->join('vt_items as i','i.id','=','s.vt_item_id')
                ->where('i.loai','ASSET')
                ->selectRaw('COALESCE(SUM(COALESCE(s.gia_tri_ton,0)),0) AS v')
                ->value('v');

            // CONSUMABLE: ước tính = so_luong_ton * AVG(don_gia nhập)
            if (Schema::hasTable('vt_receipt_items')) {
                $vtConsVal = (float) DB::table('vt_stocks as s')
                    ->join('vt_items as i','i.id','=','s.vt_item_id')
                    ->leftJoin(DB::raw('(SELECT vt_item_id, AVG(don_gia) AS avg_dg FROM vt_receipt_items WHERE don_gia IS NOT NULL GROUP BY vt_item_id) r'),
                               'r.vt_item_id','=','s.vt_item_id')
                    ->where('i.loai','CONSUMABLE')
                    ->selectRaw('COALESCE(SUM(COALESCE(s.so_luong_ton,0)*COALESCE(r.avg_dg,0)),0) AS v')
                    ->value('v');
            }
        }
        $vtVal = $vtAssetVal + $vtConsVal;

        \Log::info('[DBG][inventory][overview] sources', [
            'db'           => $db,
            'engine_env'   => (string) env('INVENTORY_ENGINE', 'product'),
            'product_val'  => $productVal,
            'vt_asset_val' => $vtAssetVal,
            'vt_cons_val'  => $vtConsVal,
            'vt_total_val' => $vtVal,
        ]);

        $use = (string) env('INVENTORY_ENGINE', 'product');
        if ($use === 'vt') {
            if ($vtVal <= 0 && $productVal > 0) $use = 'product';
        } else {
            if ($productVal <= 0 && $vtVal > 0) $use = 'vt';
        }
        $totalInventoryValue = $use === 'vt' ? $vtVal : $productVal;

        \Log::info('[DBG][inventory][overview] chosen', [
            'chosen_engine'         => $use,
            'total_inventory_value' => $totalInventoryValue,
        ]);

        return [
            'total_products'        => $totalProducts,
            'total_customers'       => $totalCustomers,
            'total_orders'          => $totalOrders,
            'total_inventory_value' => (float) $totalInventoryValue,
        ];
    }

    /* =================== INVENTORY =================== */

    private function getInventoryStats(): array
    {
        $db = config('database.connections.mysql.database');

        // Tính song song để debug & fallback
        $vtQty = Schema::hasTable('vt_stocks')
            ? (int) DB::table('vt_stocks')->selectRaw('COALESCE(SUM(COALESCE(so_luong_ton,0)),0) AS s')->value('s')
            : 0;

        $productQty = Schema::hasTable('kho_tongs')
            ? (int) DB::table('kho_tongs')->selectRaw('COALESCE(SUM(COALESCE(so_luong_ton,0)),0) AS s')->value('s')
            : 0;

        \Log::info('[DBG][inventory] start', [
            'db'          => $db,
            'engine_env'  => (string) env('INVENTORY_ENGINE', 'product'),
            'vt_sum'      => $vtQty,
            'product_sum' => $productQty,
        ]);

        $use = (string) env('INVENTORY_ENGINE', 'product');
        if ($use === 'vt') {
            if ($vtQty === 0 && $productQty > 0) $use = 'product';
        } else {
            if ($productQty === 0 && $vtQty > 0) $use = 'vt';
        }

        if ($use === 'vt') {
            \Log::info('[DBG][inventory] chosen VT', ['total_stock' => $vtQty]);
            return [
                'total_stock'        => $vtQty,
                'stock_by_status'    => collect(),
                'top_stock_products' => collect(),
                'low_stock_products' => collect(),
            ];
        }

        \Log::info('[DBG][inventory] chosen PRODUCT', ['total_stock' => $productQty]);
        return [
            'total_stock'        => $productQty,
            'stock_by_status'    => $this->getProductStockStatus(),
            'top_stock_products' => $this->getProductTopStock(),
            'low_stock_products' => $this->getProductLowStock(),
        ];
    }

    private function getProductStockStatus()
    {
        if (!Schema::hasTable('kho_tongs') || !Schema::hasTable('san_phams')) return collect();
        return DB::table('kho_tongs')
            ->leftJoin('san_phams','kho_tongs.san_pham_id','=','san_phams.id')
            ->selectRaw("
                CASE 
                  WHEN COALESCE(kho_tongs.so_luong_ton,0)=0 THEN 0
                  WHEN COALESCE(kho_tongs.so_luong_ton,0) <= COALESCE(san_phams.so_luong_canh_bao,0) THEN 1
                  ELSE 2
                END AS trang_thai,
                COUNT(*) AS count,
                SUM(COALESCE(kho_tongs.so_luong_ton,0)) AS total_quantity
            ")
            ->groupBy('trang_thai')
            ->get();
    }

    private function getProductTopStock()
    {
        if (!Schema::hasTable('kho_tongs') || !Schema::hasTable('san_phams')) return collect();
        return DB::table('kho_tongs as kt')
            ->leftJoin('san_phams as sp','kt.san_pham_id','=','sp.id')
            ->selectRaw('sp.ten_san_pham, sp.ma_san_pham, SUM(COALESCE(kt.so_luong_ton,0)) AS total_stock')
            ->groupBy('kt.san_pham_id','sp.ten_san_pham','sp.ma_san_pham')
            ->orderByDesc('total_stock')
            ->limit(5)->get();
    }

    private function getProductLowStock()
    {
        if (!Schema::hasTable('kho_tongs') || !Schema::hasTable('san_phams')) return collect();
        return DB::table('kho_tongs as kt')
            ->leftJoin('san_phams as sp','kt.san_pham_id','=','sp.id')
            ->selectRaw('sp.ten_san_pham, sp.ma_san_pham, COALESCE(kt.so_luong_ton,0) AS so_luong_ton, COALESCE(sp.so_luong_canh_bao,0) AS so_luong_canh_bao')
            ->whereRaw('COALESCE(kt.so_luong_ton,0) <= COALESCE(sp.so_luong_canh_bao,0)')
            ->orderBy('so_luong_ton','asc')->limit(10)->get();
    }

    /* =================== REVENUE (13) =================== */

    private function getRevenueStats(): array
    {
        [$tStart, $tEnd] = $this->todayRange();
        $hasTT  = Schema::hasTable('don_hangs') && Schema::hasColumn('don_hangs','trang_thai_don_hang');
        $hasNRG = Schema::hasTable('don_hangs') && Schema::hasColumn('don_hangs','nguoi_nhan_thoi_gian');

        // Doanh thu đã giao hôm nay
        $todayDelivered = (float) (
            $hasNRG
                ? DB::table('don_hangs')
                    ->when($hasTT, fn($q)=>$q->where('trang_thai_don_hang',2))
                    ->whereNotNull('nguoi_nhan_thoi_gian')
                    ->whereBetween('nguoi_nhan_thoi_gian',[$tStart,$tEnd])
                    ->sum('tong_tien_can_thanh_toan')
                : 0
        );

        // Doanh thu đã giao trong tháng
        [$mStart,$mEnd] = $this->monthRange();
        $monthRevenue = (float) (
            $hasNRG
                ? DB::table('don_hangs')
                    ->when($hasTT, fn($q)=>$q->where('trang_thai_don_hang',2))
                    ->whereNotNull('nguoi_nhan_thoi_gian')
                    ->whereBetween('nguoi_nhan_thoi_gian',[$mStart,$mEnd])
                    ->sum('tong_tien_can_thanh_toan')
                : 0
        );

        // Lợi nhuận trước thuế (13) ≈ net_margin_pct * month_revenue
        $lntt = 0.0;
        try {
            /** @var FinanceReportService $svc */
            $svc = app(FinanceReportService::class);
            $sum = $svc->summary($mStart->toDateString(), $mEnd->toDateString());
            $nm  = $sum['insights']['profitability']['net_margin_pct'] ?? null;
            if ($nm !== null) $lntt = (float) round($monthRevenue * (float)$nm);
        } catch (\Throwable $e) {
            \Log::warning('[DBG][revenue] net margin fetch fail: '.$e->getMessage());
        }

        \Log::info('[DBG][revenue] summary', [
            'today_delivered' => $todayDelivered,
            'month_revenue'   => $monthRevenue,
            'month_lntt'      => $lntt,
        ]);

        return [
            'today_revenue'  => $todayDelivered,
            'month_revenue'  => $monthRevenue,
            'month_profit'   => $lntt,  // LNTT (13)
        ];
    }

    /* =================== ORDERS =================== */

    private function getOrderStats(): array
    {
        $hasTT = Schema::hasTable('don_hangs') && Schema::hasColumn('don_hangs','trang_thai_don_hang');
        $hasNT = Schema::hasTable('don_hangs') && Schema::hasColumn('don_hangs','ngay_tao_don_hang');
        $colNT = $hasNT ? 'ngay_tao_don_hang' : 'created_at';

        [$tStart,$tEnd] = $this->todayRange();
        [$mStart,$mEnd] = $this->monthRange();

        $todayOrders = (int) DB::table('don_hangs')
            ->whereBetween($colNT,[$tStart,$tEnd])->count();

        $monthOrders = (int) DB::table('don_hangs')
            ->whereBetween($colNT,[$mStart,$mEnd])->count();

        $pending = (int) DB::table('don_hangs')
            ->when($hasTT, fn($q)=>$q->whereIn('trang_thai_don_hang',[0,1]))
            ->when(!$hasTT, fn($q)=>$q->whereRaw('1=0'))
            ->count();

        return [
            'today_orders'  => $todayOrders,
            'month_orders'  => $monthOrders,
            'pending_orders'=> $pending,
        ];
    }

    /* =================== SUPPLIERS =================== */

    private function getSupplierStats(): array
    {
        if (!Schema::hasTable('phieu_nhap_khos')) return ['top_suppliers'=>[]];
        $rows = DB::table('phieu_nhap_khos as p')
            ->leftJoin('nha_cung_caps as n','p.nha_cung_cap_id','=','n.id')
            ->selectRaw('COALESCE(n.ten_nha_cung_cap,"") as ten_nha_cung_cap, COALESCE(n.ma_nha_cung_cap,"") as ma_nha_cung_cap, SUM(p.tong_tien) as total_value, COUNT(p.id) as total_orders')
            ->groupBy('n.id','n.ten_nha_cung_cap','n.ma_nha_cung_cap')
            ->orderByDesc('total_value')->limit(5)->get();

        return ['top_suppliers'=>$rows];
    }

    /* =================== CUSTOMER CHANNELS =================== */

    private function getCustomerChannelStats(): array
    {
        if (!Schema::hasTable('khach_hangs')) return ['total'=>0,'items'=>[]];

        $col = Schema::hasColumn('khach_hangs','kenh_lien_he') ? 'kenh_lien_he'
             : (Schema::hasColumn('khach_hangs','nguon_khach') ? 'nguon_khach' : null);

        if (!$col) return ['total'=>0,'items'=>[]];

        $rows = DB::table('khach_hangs')
            ->selectRaw("COALESCE(NULLIF(TRIM($col),''),'Không rõ') AS channel, COUNT(*) AS count")
            ->groupBy('channel')->get();

        return ['total'=>(int)($rows->sum('count')??0), 'items'=>$rows];
    }

    /* =================== KPIs: TODAY =================== */

    private function getTodayKpis(): array
    {
        [$tStart,$tEnd] = $this->todayRange();

        $hasTT  = Schema::hasTable('don_hangs') && Schema::hasColumn('don_hangs','trang_thai_don_hang');
        $hasNRG = Schema::hasTable('don_hangs') && Schema::hasColumn('don_hangs','nguoi_nhan_thoi_gian');
        $hasNT  = Schema::hasTable('don_hangs') && Schema::hasColumn('don_hangs','ngay_tao_don_hang');

        $todayDelivered = (float) (
            $hasNRG
                ? DB::table('don_hangs')
                    ->when($hasTT, fn($q)=>$q->where('trang_thai_don_hang',2))
                    ->whereNotNull('nguoi_nhan_thoi_gian')
                    ->whereBetween('nguoi_nhan_thoi_gian',[$tStart,$tEnd])
                    ->sum('tong_tien_can_thanh_toan')
                : 0
        );

        $todayNewOrdersRevenue = (float) DB::table('don_hangs')
            ->when($hasTT,fn($q)=>$q->where('trang_thai_don_hang','!=',3))
            ->when($hasNT,fn($q)=>$q->whereBetween('ngay_tao_don_hang',[$tStart,$tEnd]),
                           fn($q)=>$q->whereBetween('created_at',[$tStart,$tEnd]))
            ->sum('tong_tien_can_thanh_toan');

        $hasNgayThu = Schema::hasTable('phieu_thus') && Schema::hasColumn('phieu_thus','ngay_thu');
        $todayReceipts = (float) DB::table('phieu_thus')
            ->when($hasNgayThu,fn($q)=>$q->whereBetween('ngay_thu',[$tStart,$tEnd]),
                           fn($q)=>$q->whereBetween('created_at',[$tStart,$tEnd]))
            ->sum('so_tien');

        $todayPayments = (float) (Schema::hasTable('phieu_chis')
            ? DB::table('phieu_chis')->whereBetween('ngay_chi',[$tStart,$tEnd])->sum('so_tien')
            : 0);

        $todayDeliveries = (int) (
            $hasTT && $hasNRG
                ? DB::table('don_hangs')
                    ->whereIn('trang_thai_don_hang',[0,1])
                    ->whereNotNull('nguoi_nhan_thoi_gian')
                    ->whereBetween('nguoi_nhan_thoi_gian',[$tStart,$tEnd])
                    ->count()
                : 0
        );

        return [
            'today_revenue_delivered' => $todayDelivered,
            'today_new_orders_revenue'=> $todayNewOrdersRevenue,
            'today_receipts'          => $todayReceipts,
            'today_payments'          => $todayPayments,
            'today_deliveries_count'  => $todayDeliveries,
        ];
    }

    /* =================== KPIs: MONTH =================== */

    private function getMonthKpis(): array
    {
        [$mStart,$mEnd] = $this->monthRange();

        $hasTT = Schema::hasTable('don_hangs') && Schema::hasColumn('don_hangs','trang_thai_don_hang');
        $hasNT = Schema::hasTable('don_hangs') && Schema::hasColumn('don_hangs','ngay_tao_don_hang');

        $colNT = $hasNT ? 'ngay_tao_don_hang' : 'created_at';

        $monthNewOrdersRevenue = (float) DB::table('don_hangs')
            ->whereDate($colNT,'>=',$mStart->toDateString())
            ->whereDate($colNT,'<=',$mEnd->toDateString())
            ->when($hasTT,fn($q)=>$q->where('trang_thai_don_hang','!=',3))
            ->sum('tong_tien_can_thanh_toan');

        $hasNgayThu = Schema::hasTable('phieu_thus') && Schema::hasColumn('phieu_thus','ngay_thu');
        $colThu = $hasNgayThu ? 'ngay_thu' : 'created_at';
        $monthReceipts = (float) DB::table('phieu_thus')
            ->whereDate($colThu,'>=',$mStart->toDateString())
            ->whereDate($colThu,'<=',$mEnd->toDateString())
            ->sum('so_tien');

        $monthPayments = (float) DB::table('phieu_chis')
            ->whereDate('ngay_chi','>=',$mStart->toDateString())
            ->whereDate('ngay_chi','<=',$mEnd->toDateString())
            ->sum('so_tien');

        return [
            'month_new_orders_revenue' => $monthNewOrdersRevenue,
            'month_receipts'           => $monthReceipts,
            'month_payments'           => $monthPayments,
        ];
    }

    /* =================== CHARTS =================== */

    private function getChartData(int $top, int $months, int $invMonths): array
    {
        $fromRevenue = Carbon::now()->startOfMonth()->subMonths($months - 1);
        $revenueChart = DB::table('don_hangs')
            ->selectRaw('YEAR(ngay_tao_don_hang) as y, MONTH(ngay_tao_don_hang) as m, SUM(tong_tien_can_thanh_toan) as revenue')
            ->whereDate('ngay_tao_don_hang', '>=', $fromRevenue)
            ->groupByRaw('YEAR(ngay_tao_don_hang), MONTH(ngay_tao_don_hang)')
            ->orderByRaw('y, m')
            ->get()
            ->map(fn($r) => [
                'period'  => Carbon::createFromDate($r->y, $r->m, 1)->format('Y-m'),
                'revenue' => (float) $r->revenue,
            ]);

        $inventoryChart = [];
        for ($i=$invMonths-1; $i>=0; $i--) {
            $mStart = Carbon::now()->subMonths($i)->startOfMonth();
            $mEnd   = Carbon::now()->subMonths($i)->endOfMonth();

            $imports = (float) (Schema::hasTable('phieu_nhap_khos')
                ? DB::table('phieu_nhap_khos')->whereBetween('ngay_nhap_kho', [$mStart, $mEnd])->sum('tong_tien')
                : 0);

            $exports = (float) (Schema::hasTable('phieu_xuat_khos')
                ? DB::table('phieu_xuat_khos')->whereBetween('ngay_xuat_kho', [$mStart, $mEnd])->sum('tong_tien')
                : 0);

            $inventoryChart[] = [
                'period'  => $mStart->format('Y-m'),
                'imports' => $imports,
                'exports' => $exports,
            ];
        }

        // Category distribution (giữ concise)
        $categoryChart = [];
        $others = 0;
        if (Schema::hasTable('san_phams') && Schema::hasTable('danh_muc_san_phams')) {
            $top = max(5, min(30, $top));
            $topRaw = DB::table('san_phams as sp')
                ->join('danh_muc_san_phams as dm','sp.danh_muc_id','=','dm.id')
                ->selectRaw('dm.id, dm.ten_danh_muc as category, COUNT(sp.id) as cnt')
                ->groupBy('dm.id','dm.ten_danh_muc')
                ->orderByDesc('cnt')->limit($top)->get();
            $sumTop=0; $all = (int) DB::table('san_phams')->count();
            foreach ($topRaw as $r) { $sumTop += (int)$r->cnt; $categoryChart[] = ['name'=>$r->category,'value'=>(int)$r->cnt]; }
            $others = max(0, $all - $sumTop);
            if ($others>0) $categoryChart[]=['name'=>'Khác','value'=>$others];
        }

        return [
            'revenue_chart'       => $revenueChart,
            'inventory_chart'     => $inventoryChart,
            'category_chart'      => $categoryChart,
            'category_chart_meta' => ['top'=>$top,'others_value'=>$others],
        ];
    }

    /* =================== RECENT =================== */

public function getRecentActivities()
{
    try {
        // ==== Thiết lập ngôn ngữ & timezone cho chuỗi thời gian ====
        $tz = config('app.timezone', 'Asia/Ho_Chi_Minh');
        // Chỉ cần setLocale tại đây để diffForHumans ra tiếng Việt
        try { \Carbon\Carbon::setLocale('vi'); } catch (\Throwable $e) {}

        // === Helper: format “x giờ trước” theo TZ + VI ===
        $human = function ($dt) use ($tz) {
            if (!$dt) return '';
            // Nếu DB lưu UTC thì đổi 'UTC' → $tz. Nếu DB đã lưu local thì bỏ 'UTC' đi.
            // Phổ biến là DB lưu UTC; giữ dòng dưới an toàn nhất:
            return \Carbon\Carbon::parse($dt, 'UTC')->setTimezone($tz)->diffForHumans();
        };

        // ===== Đơn hàng mới nhất (5 đơn) — sắp xếp cùng cột đang hiển thị =====
        $orders = \Schema::hasTable('don_hangs')
            ? \DB::table('don_hangs')
                ->select('ma_don_hang', 'ten_khach_hang', 'tong_tien_can_thanh_toan', 'ngay_tao_don_hang')
                ->orderBy('ngay_tao_don_hang', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($o) use ($human) {
                    return [
                        'type'        => 'order',
                        'title'       => "Đơn hàng {$o->ma_don_hang}",
                        'description' => "Khách hàng: ".($o->ten_khach_hang ?? '')." - ".number_format((float)$o->tong_tien_can_thanh_toan)." VNĐ",
                        'time'        => $human($o->ngay_tao_don_hang),
                        'icon'        => 'shopping-cart',
                    ];
                })
            : collect();

        // ===== Phiếu nhập kho mới nhất (3 phiếu) — sắp xếp cùng cột hiển thị =====
        $imports = \Schema::hasTable('phieu_nhap_khos')
            ? \DB::table('phieu_nhap_khos as p')
                ->leftJoin('nha_cung_caps as n', 'n.id', '=', 'p.nha_cung_cap_id')
                ->select('p.ma_phieu_nhap_kho', 'n.ten_nha_cung_cap', 'p.tong_tien', 'p.ngay_nhap_kho')
                ->orderBy('p.ngay_nhap_kho', 'desc')
                ->limit(3)
                ->get()
                ->map(function ($i) use ($human) {
                    return [
                        'type'        => 'import',
                        'title'       => "Nhập kho {$i->ma_phieu_nhap_kho}",
                        'description' => (($i->ten_nha_cung_cap ?? 'Sản xuất').' - '.number_format((float)$i->tong_tien).' VNĐ'),
                        'time'        => $human($i->ngay_nhap_kho),
                        'icon'        => 'package',
                    ];
                })
            : collect();

        $activities = $orders->concat($imports)->take(8)->values();

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
