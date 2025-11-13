<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FinanceReportService
{
    /**
     * KPI + chỉ số nâng cao cho Báo cáo Tài chính (READ-ONLY).
     * - Tổng doanh thu (kỳ lọc)
     * - Tổng doanh thu theo đơn hàng (drill-down = list đơn)
     * - Tổng thu (phiếu thu, kỳ lọc)
     * - Tổng chi (phiếu chi, kỳ lọc)
     * - Tổng công nợ KH (đến thời điểm 'to' hoặc hôm nay nếu không truyền)
     * - Tổng tiền tất cả tài khoản (ending balance đến thời điểm 'to' hoặc hôm nay)
     * - Insights: dòng tiền thuần, tỷ lệ thu/chi, aging 0–30/31–60/61–90/>90, DSO, cash_by_type
     */
    public function summary(?string $from, ?string $to): array
{
    $endDate   = $to   ?: date('Y-m-d');
    $startDate = $from ?: $this->firstDayOfMonth($endDate);
    $days      = max(1, $this->daysInclusive($startDate, $endDate));

    // Mặc định trả về số 0 để không rơi 500
    $kpi = [
        'tong_cong_no_kh'            => 0,
        'tong_doanh_thu'             => 0,
        'tong_thu'                   => 0,
        'tong_doanh_thu_don_hang'    => 0,
        'tong_chi'                   => 0,
        'tong_tien_tat_ca_tai_khoan' => 0,
    ];
    $ins = [
        'dong_tien_thuan' => 0,
        'ty_le_thu_chi'   => 0.0,
        'aging'           => ['age_0_30'=>0,'age_31_60'=>0,'age_61_90'=>0,'age_91_plus'=>0],
        'dso'             => null,
        'cash_by_type'    => ['cash'=>0,'bank'=>0,'ewallet'=>0],
    ];

    try {
        // ===== FLAGS CỘT =====
        $hasTrangThai = $this->columnExists('don_hangs', 'trang_thai_don_hang');
        $hasNgayGiao  = $this->columnExists('don_hangs', 'nguoi_nhan_thoi_gian');
        $hasNgayTao   = $this->columnExists('don_hangs', 'ngay_tao_don_hang');
        $hasNgayThu   = $this->columnExists('phieu_thus', 'ngay_thu');
        $hasUpdatedAt = $this->columnExists('don_hangs', 'updated_at');

        // ==== (1a) Doanh thu GHI NHẬN (đơn ĐÃ GIAO theo nguoi_nhan_thoi_gian) ====
        if ($hasNgayGiao && $hasTrangThai) {
            $kpi['tong_doanh_thu'] = (int) DB::table('don_hangs')
                ->where('trang_thai_don_hang', 2)
                ->whereNotNull('nguoi_nhan_thoi_gian')
                ->whereDate('nguoi_nhan_thoi_gian', '>=', $startDate)
                ->whereDate('nguoi_nhan_thoi_gian', '<=', $endDate)
                ->sum('tong_tien_can_thanh_toan');
        } elseif ($hasNgayGiao) { // không có trạng thái
            $kpi['tong_doanh_thu'] = (int) DB::table('don_hangs')
                ->whereNotNull('nguoi_nhan_thoi_gian')
                ->whereDate('nguoi_nhan_thoi_gian', '>=', $startDate)
                ->whereDate('nguoi_nhan_thoi_gian', '<=', $endDate)
                ->sum('tong_tien_can_thanh_toan');
        } elseif ($hasUpdatedAt) { // fallback cực hạn
            $kpi['tong_doanh_thu'] = (int) DB::table('don_hangs')
                ->whereDate('updated_at', '>=', $startDate)
                ->whereDate('updated_at', '<=', $endDate)
                ->sum('tong_tien_can_thanh_toan');
        }

        // ==== (1b) Doanh thu THEO ĐƠN ĐƯỢC TẠO (ngày ng a y_t ao_don_hang / created_at) ====
        $colNgayTao = $hasNgayTao ? 'ngay_tao_don_hang' : 'created_at';
        $qOrdersCreated = DB::table('don_hangs')
            ->whereDate($colNgayTao, '>=', $startDate)
            ->whereDate($colNgayTao, '<=', $endDate);
        if ($hasTrangThai) $qOrdersCreated->where('trang_thai_don_hang', '!=', 3); // loại hủy
        $kpi['tong_doanh_thu_don_hang'] = (int) $qOrdersCreated->sum('tong_tien_can_thanh_toan');

        // ==== (2) Tổng THU (phiếu thu) & (3) Tổng CHI (phiếu chi) ====
        $colNgayThu = $hasNgayThu ? 'ngay_thu' : 'created_at';
        $kpi['tong_thu'] = (int) DB::table('phieu_thus')
            ->whereDate($colNgayThu, '>=', $startDate)
            ->whereDate($colNgayThu, '<=', $endDate)
            ->sum('so_tien');

        $kpi['tong_chi'] = (int) DB::table('phieu_chis')
            ->whereDate('ngay_chi', '>=', $startDate)
            ->whereDate('ngay_chi', '<=', $endDate)
            ->sum('so_tien');

        // ==== (4) Tổng công nợ KH tới endDate ====
        $tongPhaiThuToiEnd = (int) DB::table('don_hangs')
            ->when($hasTrangThai, fn($q)=>$q->where('trang_thai_don_hang','!=',3))
            ->sum('tong_tien_can_thanh_toan');
        $tongThuToiEnd = (int) DB::table('phieu_thus')
            ->whereDate($colNgayThu, '<=', $endDate)
            ->sum('so_tien');
        $kpi['tong_cong_no_kh'] = max(0, $tongPhaiThuToiEnd - $tongThuToiEnd);

        // ==== (5) Cash — ending sum & breakdown ====
        if ($this->columnExists('tai_khoan_tiens','id') && $this->columnExists('so_quy_entries','tai_khoan_id')) {
// ==== (5) Cash — ending sum & breakdown (thêm tổng loại trừ TK “TRẦN TẤN PHÁT”) ====
$accounts = DB::table('tai_khoan_tiens')
    ->select('id','ten_tk','loai','opening_balance','opening_date','is_active')
    ->get();

$sumByAccount = DB::table('so_quy_entries')
    ->whereDate('ngay_ct', '<=', $endDate)
    ->selectRaw('tai_khoan_id, COALESCE(SUM(amount),0) as s')
    ->groupBy('tai_khoan_id')
    ->pluck('s', 'tai_khoan_id');

$totalAll  = 0.0;
$totalEx   = 0.0; // loại trừ TK “Trần Tấn Phát”
$byAll     = ['cash'=>0.0,'bank'=>0.0,'ewallet'=>0.0];

$needle = mb_strtolower('TRẦN TẤN PHÁT','UTF-8');

foreach ($accounts as $acc) {
    if (!$acc->is_active) continue;

    $ob = 0.0;
    if (empty($acc->opening_date) || strtotime($acc->opening_date) <= strtotime($startDate)) {
        $ob = (float) ($acc->opening_balance ?? 0);
    }
    $ending = $ob + (float) ($sumByAccount[$acc->id] ?? 0);

    // tổng tất cả TK
    $totalAll += $ending;
    $t = strtolower((string)$acc->loai);
    if (!isset($byAll[$t])) $t = 'bank';
    $byAll[$t] += $ending;

    // tổng loại trừ TK “Trần Tấn Phát”
    $isPhat = (mb_strtolower((string)$acc->ten_tk,'UTF-8') === $needle);
    if (!$isPhat) {
        $totalEx += $ending;
    }
}

$kpi['tong_tien_tat_ca_tai_khoan']            = (int) round($totalAll); // GIỮ nguyên: tất cả TK
$kpi['so_du_tien_toi_hien_tai_khong_ttp']     = (int) round($totalEx);  // MỚI: loại trừ TK “TRẦN TẤN PHÁT”

$ins['cash_by_type'] = [
    'cash'    => (int) round($byAll['cash']),
    'bank'    => (int) round($byAll['bank']),
    'ewallet' => (int) round($byAll['ewallet']),
];

        }

        // ==== Insights ====
        $ins['dong_tien_thuan'] = (int) ($kpi['tong_thu'] - $kpi['tong_chi']);
        $den = $kpi['tong_thu'] + $kpi['tong_chi'];
        $ins['ty_le_thu_chi'] = $den > 0 ? round($kpi['tong_thu'] / $den, 4) : 0.0;

        // Aging (chỉ chạy khi có cột cần thiết)
        if ($hasNgayTao) {
            $receiptsPerOrder = DB::table('phieu_thus')
                ->whereDate($colNgayThu, '<=', $endDate)
                ->whereNotNull('don_hang_id')
                ->selectRaw('don_hang_id, COALESCE(SUM(so_tien),0) as paid')
                ->groupBy('don_hang_id');

            $agingRows = DB::table('don_hangs as dh')
                ->leftJoinSub($receiptsPerOrder, 'pt', 'pt.don_hang_id', '=', 'dh.id')
                ->when($hasTrangThai, fn($q)=>$q->where('dh.trang_thai_don_hang','!=',3))
                ->whereDate('dh.ngay_tao_don_hang','<=',$endDate)
                ->selectRaw("
                    dh.id,
                    DATE(dh.ngay_tao_don_hang) as ngay,
                    GREATEST(dh.tong_tien_can_thanh_toan - COALESCE(pt.paid,0), 0) as du_no
                ")
                ->havingRaw('du_no > 0')->get();

            $a0=$a1=$a2=$a3=0;
            foreach ($agingRows as $r) {
                $diff = $this->daysInclusive($r->ngay, $endDate);
                if ($diff <= 30)        $a0 += (int)$r->du_no;
                elseif ($diff <= 60)    $a1 += (int)$r->du_no;
                elseif ($diff <= 90)    $a2 += (int)$r->du_no;
                else                    $a3 += (int)$r->du_no;
            }
            $ins['aging'] = ['age_0_30'=>$a0,'age_31_60'=>$a1,'age_61_90'=>$a2,'age_91_plus'=>$a3];
        }

        $dtBqNgay = $kpi['tong_doanh_thu'] > 0 ? ($kpi['tong_doanh_thu'] / $days) : 0;
        $ins['dso'] = $dtBqNgay > 0 ? round($kpi['tong_cong_no_kh'] / $dtBqNgay, 1) : null;

        // ====== GROWTH (kỳ hiện tại vs kỳ trước, cùng số ngày) ======
try {
    $periodDays = max(1, $this->daysInclusive($startDate, $endDate));
    $prevEnd   = date('Y-m-d', strtotime($startDate . ' -1 day'));
    $prevStart = date('Y-m-d', strtotime($prevEnd . " -".($periodDays - 1)." day"));

    $hasTrangThai = $this->columnExists('don_hangs','trang_thai_don_hang');
    $hasNgayGiao  = $this->columnExists('don_hangs','nguoi_nhan_thoi_gian');

    // Doanh thu ĐÃ GIAO kỳ trước
    $revenuePrev = 0;
    if ($hasNgayGiao) {
        $revenuePrev = (int) DB::table('don_hangs')
            ->when($hasTrangThai, fn($q)=>$q->where('trang_thai_don_hang',2))
            ->whereNotNull('nguoi_nhan_thoi_gian')
            ->whereDate('nguoi_nhan_thoi_gian','>=',$prevStart)
            ->whereDate('nguoi_nhan_thoi_gian','<=',$prevEnd)
            ->sum('tong_tien_can_thanh_toan');
    }
    $revenueNow = (float) $kpi['tong_doanh_thu'];

    // Giá vốn kỳ này/kỳ trước (parent COGS → line=2)
    $pc = 'phieu_chis'; $ec = 'expense_categories';
    $cogsNow = (int) DB::table("$pc as pc")
        ->leftJoin("$ec as c",'c.id','=','pc.category_id')
        ->leftJoin("$ec as p",'p.id','=','c.parent_id')
        ->whereDate('pc.ngay_chi','>=',$startDate)
        ->whereDate('pc.ngay_chi','<=',$endDate)
        ->where('p.statement_line',2)
        ->sum('pc.so_tien');

    $cogsPrev = (int) DB::table("$pc as pc")
        ->leftJoin("$ec as c",'c.id','=','pc.category_id')
        ->leftJoin("$ec as p",'p.id','=','c.parent_id')
        ->whereDate('pc.ngay_chi','>=',$prevStart)
        ->whereDate('pc.ngay_chi','<=',$prevEnd)
        ->where('p.statement_line',2)
        ->sum('pc.so_tien');

    $gpNow  = $revenueNow - (float)$cogsNow;
    $gpPrev = (float)$revenuePrev - (float)$cogsPrev;

    $ins['growth'] = [
        'period'                   => ['cur'=>$startDate.'→'.$endDate, 'prev'=>$prevStart.'→'.$prevEnd],
       'revenue_growth_pct'       => ($revenuePrev > 0)
    ? round(($revenueNow - $revenuePrev) / $revenuePrev, 4)
    : null,

'gross_profit_growth_pct'  => (function($prev, $now) {
    if ($prev > 0 && $now >= 0) {
        // lãi -> lãi
        return round(($now - $prev) / $prev, 4);
    } elseif ($prev < 0 && $now <= 0) {
        // lỗ -> lỗ (giảm lỗ = dương)
        $pa = max(1, abs($prev));
        return round((abs($prev) - abs($now)) / $pa, 4);
    } elseif ($prev < 0 && $now > 0) {
        // lỗ -> lãi (đảo chiều dương)
        $pa = max(1, abs($prev));
        return round(($now + abs($prev)) / $pa, 4);
    } elseif ($prev > 0 && $now < 0) {
        // lãi -> lỗ (đảo chiều âm)
        return round(-(abs($now) + $prev) / $prev, 4);
    }
    return null; // prev == 0
})($gpPrev, $gpNow),

        'net_income_growth_pct'    => null, // sẽ set phía dưới nếu có Thuế
    ];
} catch (\Throwable $e) {
    \Log::warning('Finance.growth warn: '.$e->getMessage());
    $ins['growth'] = [
        'period'=>['cur'=>$startDate.'→'.$endDate,'prev'=>'?'],
        'revenue_growth_pct'=>null,'gross_profit_growth_pct'=>null,'net_income_growth_pct'=>null
    ];
}

// ====== EBITDA MARGIN (EBIT + Depreciation) / Revenue ======
// EBIT = 03 + 04 − (05+06+07+08). Depreciation dùng child code 'CCDC_KHAU'
try {
    // Gom chi phí các line 2/5/6/7/8/10 kỳ này
    $sumLine = DB::table("$pc as pc")
        ->leftJoin("$ec as c",'c.id','=','pc.category_id')
        ->leftJoin("$ec as p",'p.id','=','c.parent_id')
        ->whereDate('pc.ngay_chi','>=',$startDate)
        ->whereDate('pc.ngay_chi','<=',$endDate)
        ->selectRaw('COALESCE(p.statement_line,0) as line, SUM(pc.so_tien) as total')
        ->groupBy('line')->pluck('total','line');

    $dt_tc = 0; // chưa bóc riêng doanh thu tài chính
    $ebit  = ($revenueNow - (float)($sumLine[2]  ?? 0))   // 03
           + $dt_tc
           - (float)($sumLine[5]  ?? 0)
           - (float)($sumLine[6]  ?? 0)
           - (float)($sumLine[7]  ?? 0)
           - (float)($sumLine[8]  ?? 0);

    // Khấu hao/Phân bổ CCDC: code 'CCDC_KHAU'
    $depr = (int) DB::table("$pc as pc")
        ->leftJoin("$ec as c",'c.id','=','pc.category_id')
        ->where('c.code','CCDC_KHAU')
        ->whereDate('pc.ngay_chi','>=',$startDate)
        ->whereDate('pc.ngay_chi','<=',$endDate)
        ->sum('pc.so_tien');

    $ebitda = $ebit + (float)$depr;
    $ins['profitability']['ebitda_margin_pct'] = $revenueNow>0 ? round($ebitda / $revenueNow, 4) : null;
} catch (\Throwable $e) {
    \Log::warning('Finance.ebitda warn: '.$e->getMessage());
    $ins['profitability']['ebitda_margin_pct'] = null;
}

// ====== Net Income Growth (xấp xỉ) = (LNTT − Thuế TNDN) tăng trưởng ======
try {
    $taxNow = (int) DB::table("$pc as pc")
        ->leftJoin("$ec as c",'c.id','=','pc.category_id')
        ->where('c.code','THUE')
        ->whereDate('pc.ngay_chi','>=',$startDate)
        ->whereDate('pc.ngay_chi','<=',$endDate)
        ->sum('pc.so_tien');

    $taxPrev = (int) DB::table("$pc as pc")
        ->leftJoin("$ec as c",'c.id','=','pc.category_id')
        ->where('c.code','THUE')
        ->whereDate('pc.ngay_chi','>=',$prevStart)
        ->whereDate('pc.ngay_chi','<=',$prevEnd)
        ->sum('pc.so_tien');

    // LNTT (kỳ này/kỳ trước)
    $cp_khac_now = (float)($sumLine[10] ?? 0);
    $lntt_now = $ebit - $cp_khac_now;
    // Kỳ trước: cần lại sumLinePrev 10 (chi khác)
    $sumLinePrev = DB::table("$pc as pc")
        ->leftJoin("$ec as c",'c.id','=','pc.category_id')
        ->leftJoin("$ec as p",'p.id','=','c.parent_id')
        ->whereDate('pc.ngay_chi','>=',$prevStart)
        ->whereDate('pc.ngay_chi','<=',$prevEnd)
        ->selectRaw('COALESCE(p.statement_line,0) as line, SUM(pc.so_tien) as total')
        ->groupBy('line')->pluck('total','line');
    $cp_khac_prev = (float)($sumLinePrev[10] ?? 0);

    // EBIT_prev tương tự ebit (dùng revenuePrev và sumLinePrev)
    $ebit_prev = (($revenuePrev - (float)($sumLinePrev[2] ?? 0)) + 0
                 - (float)($sumLinePrev[5] ?? 0)
                 - (float)($sumLinePrev[6] ?? 0)
                 - (float)($sumLinePrev[7] ?? 0)
                 - (float)($sumLinePrev[8] ?? 0));

    $lntt_prev = $ebit_prev - $cp_khac_prev;

    $lnst_now  = $lntt_now  - (float)$taxNow;
    $lnst_prev = $lntt_prev - (float)$taxPrev;
$ins['growth']['net_income_growth_pct'] = (function($prev, $now) {
    if ($prev > 0 && $now >= 0) {
        // lãi -> lãi
        return round(($now - $prev) / $prev, 4);
    } elseif ($prev < 0 && $now <= 0) {
        // lỗ -> lỗ (giảm lỗ = dương)
        $pa = max(1, abs($prev));
        return round((abs($prev) - abs($now)) / $pa, 4);
    } elseif ($prev < 0 && $now > 0) {
        // lỗ -> lãi (đảo chiều dương)
        $pa = max(1, abs($prev));
        return round(($now + abs($prev)) / $pa, 4);
    } elseif ($prev > 0 && $now < 0) {
        // lãi -> lỗ (đảo chiều âm)
        return round(-(abs($now) + $prev) / $prev, 4);
    }
    return null; // prev == 0
})($lnst_prev, $lnst_now);

} catch (\Throwable $e) {
    \Log::warning('Finance.netIncomeGrowth warn: '.$e->getMessage());
    // để nguyên null
}



        // ====== PROFITABILITY (theo KQKD) ======
try {
    // Gom chi phí theo statement_line (2,5,6,7,8,10)
    $pc = 'phieu_chis'; $ec = 'expense_categories';
    $chiByLine = DB::table("$pc as pc")
        ->leftJoin("$ec as c",'c.id','=','pc.category_id')
        ->leftJoin("$ec as p",'p.id','=','c.parent_id')
        ->whereDate('pc.ngay_chi','>=',$startDate)
        ->whereDate('pc.ngay_chi','<=',$endDate)
        ->selectRaw('COALESCE(p.statement_line,0) as line, SUM(pc.so_tien) as total')
        ->groupBy('line')->pluck('total','line');

    $revenue = (float) $kpi['tong_doanh_thu'];          // DT ghi nhận (đã giao)
    $cogs    = (float) ($chiByLine[2]  ?? 0);           // Giá vốn
    $dt_tc   = (float) 0;                               // (04) DT tài chính – chưa bóc riêng -> 0
    $cp_tc   = (float) ($chiByLine[5]  ?? 0);
    $cp_bh   = (float) ($chiByLine[6]  ?? 0);
    $cp_qldn = (float) ($chiByLine[7]  ?? 0);
    $cp_ccdc = (float) ($chiByLine[8]  ?? 0);
    $cp_khac = (float) ($chiByLine[10] ?? 0);
    $gp      = $revenue - $cogs;                        // 03
    $op      = $gp + $dt_tc - $cp_tc - $cp_bh - $cp_qldn - $cp_ccdc;  // 09
    $lntt    = $op - $cp_khac;                          // 13 ~ 09 + (−10) (11≈0)

$ins['profitability'] = array_replace(
    $ins['profitability'] ?? [],
    [
        'gross_margin_pct'     => $revenue>0 ? round($gp/$revenue, 4) : null,
        'operating_margin_pct' => $revenue>0 ? round($op/$revenue, 4) : null,
        'net_margin_pct'       => $revenue>0 ? round($lntt/$revenue, 4) : null, // gần đúng (trước thuế)
    ]
);

} catch (\Throwable $e) {
    \Log::warning('FinanceReport.profitability warn: '.$e->getMessage());
    $ins['profitability'] = [
        'gross_margin_pct'=>null,'operating_margin_pct'=>null,'net_margin_pct'=>null
    ];
}

// ====== CAC & LTV (đơn giản hoá) ======
// CAC = Marketing spend / KH mới;  LTV ≈ AOV × Tần suất × Biên gộp × lifetime_months
try {
    // MKT spend
    $mktSpend = (int) DB::table("$pc as pc")
        ->leftJoin("$ec as c",'c.id','=','pc.category_id')
        ->where('c.code','MKT')
        ->whereDate('pc.ngay_chi','>=',$startDate)
        ->whereDate('pc.ngay_chi','<=',$endDate)
        ->sum('pc.so_tien');

    // KH mới
    $newCustomers = (int) DB::table('khach_hangs')
        ->whereDate('created_at','>=',$startDate)
        ->whereDate('created_at','<=',$endDate)
        ->count();

    $cac = ($newCustomers>0) ? round($mktSpend / $newCustomers) : null;

    // Biên gộp% & AOV, Tần suất — tính trực tiếp tại đây (không phụ thuộc ins['ops'])
    $gross_margin_pct = $ins['profitability']['gross_margin_pct'] ?? null;

    // Đếm đơn đã giao & KH có đơn trong kỳ
    $ordersQ_forLtv = DB::table('don_hangs')
        ->when($this->columnExists('don_hangs','trang_thai_don_hang'),
               fn($q)=>$q->where('trang_thai_don_hang',2))
        ->whereNotNull('nguoi_nhan_thoi_gian')
        ->whereDate('nguoi_nhan_thoi_gian','>=',$startDate)
        ->whereDate('nguoi_nhan_thoi_gian','<=',$endDate);

    $orderCount_forLtv    = (int) $ordersQ_forLtv->count();
    $customerCount_forLtv = (int) DB::query()->fromSub($ordersQ_forLtv, 'x')
        ->whereNotNull('x.khach_hang_id')
        ->distinct()->count('x.khach_hang_id');

    // AOV & Tần suất mua
    $aov = $orderCount_forLtv>0 ? (int) round($kpi['tong_doanh_thu'] / $orderCount_forLtv) : 0;
    $pf  = $customerCount_forLtv>0 ? round($orderCount_forLtv / $customerCount_forLtv, 2) : 0.0;


    // lifetime tháng (mặc định 12 – có thể thay bằng config/env)
    $lifetimeMonths = (int) (env('LTV_MONTHS', 12));
    $ltv = ($gross_margin_pct !== null)
        ? (int) round($aov * $pf * (float)$gross_margin_pct * $lifetimeMonths)
        : null;

    $ins['ops']['cac'] = $cac;
    $ins['ops']['ltv'] = $ltv;
    $ins['ops']['ltv_cac'] = ($cac && $cac>0 && $ltv) ? round($ltv / $cac, 2) : null;
} catch (\Throwable $e) {
    \Log::warning('Finance.cac/ltv warn: '.$e->getMessage());
    $ins['ops']['cac'] = null;
    $ins['ops']['ltv'] = null;
    $ins['ops']['ltv_cac'] = null;
}

// ====== OPS (AOV, Purchase Frequency) – dùng đơn ĐÃ GIAO trong kỳ ======
try {
    $ordersQ = DB::table('don_hangs')
        ->when($this->columnExists('don_hangs','trang_thai_don_hang'),
               fn($q)=>$q->where('trang_thai_don_hang',2))
        ->whereNotNull('nguoi_nhan_thoi_gian')
        ->whereDate('nguoi_nhan_thoi_gian','>=',$startDate)
        ->whereDate('nguoi_nhan_thoi_gian','<=',$endDate);

    $orderCount = (int) $ordersQ->count();
    $customerCount = (int) DB::query()->fromSub($ordersQ, 'x')
        ->whereNotNull('x.khach_hang_id')->distinct()->count('x.khach_hang_id');

    $revenue = (float) $kpi['tong_doanh_thu']; // DT đã giao trong kỳ
$ins['ops'] = array_replace(
    $ins['ops'] ?? [],
    [
        'aov'                 => $orderCount>0 ? (int) round($revenue/$orderCount) : 0,
        'purchase_frequency'  => $customerCount>0 ? round($orderCount/$customerCount, 2) : 0.0,
        'orders'              => $orderCount,
        'customers'           => $customerCount,
    ]
);

} catch (\Throwable $e) {
    \Log::warning('FinanceReport.ops warn: '.$e->getMessage());
    $ins['ops'] = ['aov'=>0,'purchase_frequency'=>0.0,'orders'=>0,'customers'=>0];
}


    } catch (\Throwable $e) {
        \Log::error('FinanceReport.summary error: '.$e->getMessage(), ['trace'=>$e->getTraceAsString()]);
        // giữ kpi/ins = 0; không throw -> FE vẫn render được
    }

    return [
        'params'   => ['from'=>$startDate,'to'=>$endDate],
        'kpi'      => $kpi,
        'insights' => $ins,
    ];
}



    /**
     * Danh sách công nợ KH (tổng hợp theo khách) + aging (paging).
     * (Sẽ bơm logic ở bước tiếp theo)
     */
public function receivables(string $q, ?string $from, ?string $to, int $page, int $per): array
{
    // ===== Khoảng ngày =====
    $endDate   = $to   ?: date('Y-m-d');
    $startDate = $from ?: $this->firstDayOfMonth($endDate);

    // ===== Cột ngày & trạng thái đơn =====
    $hasNgayTaoDH = $this->columnExists('don_hangs', 'ngay_tao_don_hang');
    $colNgayDon   = $hasNgayTaoDH ? 'ngay_tao_don_hang' : 'created_at';
    $hasTrangThai = $this->columnExists('don_hangs', 'trang_thai_don_hang');

    // ===== Tiền đã thu theo đơn (đến endDate) =====
    $hasNgayThu = $this->columnExists('phieu_thus', 'ngay_thu');
    $colNgayThu = $hasNgayThu ? 'ngay_thu' : 'created_at';

    $receiptsPerOrder = DB::table('phieu_thus')
        ->whereDate($colNgayThu, '<=', $endDate)
        ->whereNotNull('don_hang_id')
        ->selectRaw('don_hang_id, COALESCE(SUM(so_tien),0) as paid')
        ->groupBy('don_hang_id');

    // ===== Dữ liệu đơn hàng ở kỳ lọc (exclude hủy) -> outstanding từng đơn =====
    // age = số ngày kể từ ngày đơn đến 'endDate'
    $ordersSub = DB::table('don_hangs as dh')
        ->leftJoinSub($receiptsPerOrder, 'pt', 'pt.don_hang_id', '=', 'dh.id')
        ->when($hasTrangThai, fn($q) => $q->where('dh.trang_thai_don_hang', '!=', 3)) // 3=Đã hủy (mapping mới)
        ->whereNotNull('dh.khach_hang_id')
        ->whereDate("dh.$colNgayDon", '>=', $startDate)
        ->whereDate("dh.$colNgayDon", '<=', $endDate)
        ->selectRaw("
            dh.khach_hang_id,
            DATE(dh.$colNgayDon) as ngay,
            dh.tong_tien_can_thanh_toan as phai_thu,
            COALESCE(pt.paid,0) as da_thu,
            GREATEST(dh.tong_tien_can_thanh_toan - COALESCE(pt.paid,0), 0) as du_no,
            TIMESTAMPDIFF(DAY, DATE(dh.$colNgayDon), ?) as age
        ", [$endDate]);

    // ===== Tổng hợp theo khách hàng + Aging =====
    $agg = DB::query()
        ->fromSub($ordersSub, 'o')
        ->selectRaw("
            o.khach_hang_id,
            SUM(o.phai_thu)                                  as tong_phai_thu,
            SUM(o.da_thu)                                    as da_thu,
            SUM(o.du_no)                                     as con_lai,
            SUM(CASE WHEN o.du_no > 0 THEN 1 ELSE 0 END)     as so_don_con_no,
            SUM(CASE WHEN o.du_no > 0 AND o.age <= 30  THEN o.du_no ELSE 0 END) as age_0_30,
            SUM(CASE WHEN o.du_no > 0 AND o.age BETWEEN 31 AND 60 THEN o.du_no ELSE 0 END) as age_31_60,
            SUM(CASE WHEN o.du_no > 0 AND o.age BETWEEN 61 AND 90 THEN o.du_no ELSE 0 END) as age_61_90,
            SUM(CASE WHEN o.du_no > 0 AND o.age > 90 THEN o.du_no ELSE 0 END) as age_91_plus
        ")
        ->groupBy('o.khach_hang_id');

    // ===== Join thông tin khách hàng để lọc & hiển thị =====
    $wrapped = DB::query()
        ->fromSub($agg, 'x')
        ->leftJoin('khach_hangs as kh', 'kh.id', '=', 'x.khach_hang_id')
        ->selectRaw("
            x.khach_hang_id,
            kh.ten_khach_hang,
            kh.so_dien_thoai,
            x.tong_phai_thu, x.da_thu, x.con_lai, x.so_don_con_no,
            x.age_0_30, x.age_31_60, x.age_61_90, x.age_91_plus
        ");

    if ($q !== '') {
        $kw = '%' . str_replace(['%','_'], ['\%','\_'], $q) . '%';
        $wrapped->where(function($qq) use ($kw) {
            $qq->where('kh.ten_khach_hang', 'like', $kw)
               ->orWhere('kh.so_dien_thoai', 'like', $kw);
        });
    }

    // ===== Tổng số dòng (sau lọc) =====
    $total = DB::query()->fromSub($wrapped, 't')->count();

    // ===== Phân trang + sắp xếp (mặc định: còn lại desc, sau đó tên KH asc) =====
    $rows = $wrapped
        ->orderBy('x.con_lai', 'desc')
        ->orderBy('kh.ten_khach_hang', 'asc')
        ->offset(($page - 1) * $per)
        ->limit($per)
        ->get()
        ->map(function ($r) {
            return [
                'khach_hang_id'  => (int) $r->khach_hang_id,
                'ten_khach_hang' => $r->ten_khach_hang,
                'so_dien_thoai'  => $r->so_dien_thoai,
                'tong_phai_thu'  => (int) $r->tong_phai_thu,
                'da_thu'         => (int) $r->da_thu,
                'con_lai'        => (int) $r->con_lai,
                'so_don_con_no'  => (int) $r->so_don_con_no,
                'age_0_30'       => (int) $r->age_0_30,
                'age_31_60'      => (int) $r->age_31_60,
                'age_61_90'      => (int) $r->age_61_90,
                'age_91_plus'    => (int) $r->age_91_plus,
            ];
        })
        ->values()
        ->all();

    return [
        'collection' => $rows,
        'total'      => (int) $total,
        'page'       => $page,
        'per_page'   => $per,
    ];
}

    /**
     * Danh sách đơn hàng trong kỳ (paging).
     * (Sẽ bơm logic ở bước tiếp theo)
     */
public function orders(string $q, ?string $from, ?string $to, int $page, int $per): array
{
    // ===== Chuẩn hoá khoảng ngày =====
    $endDate   = $to   ?: date('Y-m-d');
    $startDate = $from ?: $this->firstDayOfMonth($endDate);

    // ===== Cột ngày/ trạng thái/ mã đơn =====
    $hasNgayDon   = $this->columnExists('don_hangs', 'ngay_tao_don_hang');
    $colNgayDon   = $hasNgayDon ? 'ngay_tao_don_hang' : 'created_at';
    $hasTrangThai = $this->columnExists('don_hangs', 'trang_thai_don_hang');
    $hasMaDon     = $this->columnExists('don_hangs', 'ma_don_hang');

    // ===== Tiền đã thu theo đơn (đến endDate) =====
    $hasNgayThu = $this->columnExists('phieu_thus', 'ngay_thu');
    $colNgayThu = $hasNgayThu ? 'ngay_thu' : 'created_at';

    $receiptsPerOrder = DB::table('phieu_thus')
        ->whereDate($colNgayThu, '<=', $endDate)
        ->whereNotNull('don_hang_id')
        ->selectRaw('don_hang_id, COALESCE(SUM(so_tien),0) as da_thu')
        ->groupBy('don_hang_id');

    // ===== Base query: đơn hàng trong kỳ lọc (exclude hủy) =====
    $base = DB::table('don_hangs as dh')
        ->leftJoinSub($receiptsPerOrder, 'pt', 'pt.don_hang_id', '=', 'dh.id')
        ->leftJoin('khach_hangs as kh', 'kh.id', '=', 'dh.khach_hang_id')
        ->when($hasTrangThai, fn($q) => $q->where('dh.trang_thai_don_hang', '!=', 3)) // 3 = Đã hủy (mapping mới)
        ->whereDate("dh.$colNgayDon", '>=', $startDate)
        ->whereDate("dh.$colNgayDon", '<=', $endDate)
        ->selectRaw("
            dh.id,
            " . ($hasMaDon ? "dh.ma_don_hang" : "NULL as ma_don_hang") . ",
            DATE(dh.$colNgayDon) as ngay_don,
            COALESCE(dh.ten_khach_hang, kh.ten_khach_hang) as ten_khach_hang,
            kh.so_dien_thoai,
            dh.tong_tien_can_thanh_toan as tong_phai_thu,
            COALESCE(pt.da_thu, 0) as da_thu,
            GREATEST(dh.tong_tien_can_thanh_toan - COALESCE(pt.da_thu,0), 0) as con_lai,
            COALESCE(dh.trang_thai_thanh_toan, 0) as trang_thai_thanh_toan,
            " . ($hasTrangThai ? "dh.trang_thai_don_hang" : "0 as trang_thai_don_hang") . "
        ");

    // ===== Keyword filter (mã đơn / tên KH / SĐT) =====
    if ($q !== '') {
        $kw = '%' . str_replace(['%','_'], ['\\%','\\_'], $q) . '%';
        $base->where(function ($qq) use ($kw, $hasMaDon) {
            if ($hasMaDon) {
                $qq->orWhere('dh.ma_don_hang', 'like', $kw);
            }
            $qq->orWhere('dh.ten_khach_hang', 'like', $kw)
               ->orWhere('kh.ten_khach_hang', 'like', $kw)
               ->orWhere('kh.so_dien_thoai', 'like', $kw);
        });
    }

    // ===== Đếm tổng sau lọc =====
    $total = DB::query()->fromSub($base, 't')->count();

    // ===== Lấy trang dữ liệu (mặc định sort: ngày desc, id desc) =====
    $rows = DB::query()
        ->fromSub($base, 't')
        ->orderBy('t.ngay_don', 'desc')
        ->orderBy('t.id', 'desc')
        ->offset(($page - 1) * $per)
        ->limit($per)
        ->get()
        ->map(function ($r) {
            return [
                'id'                     => (int) $r->id,
                'ma_don_hang'            => $r->ma_don_hang,
                'ngay_tao_don_hang'      => $r->ngay_don, // giữ tên field quen thuộc ở FE
                'ten_khach_hang'         => $r->ten_khach_hang,
                'so_dien_thoai'          => $r->so_dien_thoai,
                'tong_phai_thu'          => (int) $r->tong_phai_thu,
                'da_thu'                 => (int) $r->da_thu,
                'con_lai'                => (int) $r->con_lai,
                'trang_thai_thanh_toan'  => (int) $r->trang_thai_thanh_toan,
                'trang_thai_don_hang'    => (int) $r->trang_thai_don_hang,
            ];
        })
        ->values()
        ->all();

    return [
        'collection' => $rows,
        'total'      => (int) $total,
        'page'       => $page,
        'per_page'   => $per,
    ];
}


    /**
     * Danh sách phiếu thu trong kỳ (paging).
     * (Sẽ bơm logic ở bước tiếp theo)
     */
public function receipts(string $q, ?string $from, ?string $to, int $page, int $per): array
{
    // Khoảng ngày
    $endDate   = $to   ?: date('Y-m-d');
    $startDate = $from ?: $this->firstDayOfMonth($endDate);

    // Cột ngày & các cột tùy chọn
    $hasNgayThu  = $this->columnExists('phieu_thus', 'ngay_thu');
    $colNgayThu  = $hasNgayThu ? 'ngay_thu' : 'created_at';
    $hasMaDon    = $this->columnExists('don_hangs', 'ma_don_hang');
    $hasTkId     = $this->columnExists('phieu_thus', 'tai_khoan_id') && $this->columnExists('tai_khoan_tiens', 'id');

    // Base query
    $base = DB::table('phieu_thus as pt')
        ->leftJoin('khach_hangs as kh', 'kh.id', '=', 'pt.khach_hang_id')
        ->leftJoin('don_hangs as dh', 'dh.id', '=', 'pt.don_hang_id')
        ->when($hasTkId, fn($q) => $q->leftJoin('tai_khoan_tiens as tk', 'tk.id', '=', 'pt.tai_khoan_id'))
        ->whereDate("pt.$colNgayThu", '>=', $startDate)
        ->whereDate("pt.$colNgayThu", '<=', $endDate)
        ->selectRaw("
            pt.id,
            pt.ma_phieu_thu,
            DATE(pt.$colNgayThu) as ngay,
            pt.so_tien,
            COALESCE(pt.nguoi_tra, kh.ten_khach_hang) as nguoi_tra,
            pt.phuong_thuc_thanh_toan,
            pt.so_tai_khoan,
            pt.ngan_hang,
            pt.ly_do_thu,
            kh.ten_khach_hang,
            kh.so_dien_thoai,
            " . ($hasMaDon ? "dh.ma_don_hang" : "NULL as ma_don_hang") . ",
            " . ($hasTkId ? "tk.ten_tk as tai_khoan_ten" : "NULL as tai_khoan_ten") . "
        ");

    // Tìm kiếm
    if ($q !== '') {
        $kw = '%' . str_replace(['%','_'], ['\\%','\\_'], $q) . '%';
        $base->where(function ($qq) use ($kw, $hasMaDon) {
            $qq->orWhere('pt.ma_phieu_thu', 'like', $kw)
               ->orWhere('pt.nguoi_tra', 'like', $kw)
               ->orWhere('pt.ly_do_thu', 'like', $kw)
               ->orWhere('pt.so_tai_khoan', 'like', $kw)
               ->orWhere('pt.ngan_hang', 'like', $kw)
               ->orWhere('kh.ten_khach_hang', 'like', $kw)
               ->orWhere('kh.so_dien_thoai', 'like', $kw);
            if ($hasMaDon) {
                $qq->orWhere('dh.ma_don_hang', 'like', $kw);
            }
        });
    }

    // Tổng sau lọc
    $total = DB::query()->fromSub($base, 't')->count();

    // Trang dữ liệu
    $rows = DB::query()->fromSub($base, 't')
        ->orderBy('t.ngay', 'desc')->orderBy('t.id', 'desc')
        ->offset(($page - 1) * $per)->limit($per)
        ->get()
        ->map(function ($r) {
            return [
                'id'                        => (int) $r->id,
                'ma_phieu_thu'              => $r->ma_phieu_thu,
                'ngay'                      => $r->ngay,
                'so_tien'                   => (int) $r->so_tien,
                'nguoi_tra'                 => $r->nguoi_tra,
                'phuong_thuc_thanh_toan'    => (int) $r->phuong_thuc_thanh_toan,
                'so_tai_khoan'              => $r->so_tai_khoan,
                'ngan_hang'                 => $r->ngan_hang,
                'ly_do_thu'                 => $r->ly_do_thu,
                'ten_khach_hang'            => $r->ten_khach_hang,
                'so_dien_thoai'             => $r->so_dien_thoai,
                'ma_don_hang'               => $r->ma_don_hang,
                'tai_khoan_ten'             => $r->tai_khoan_ten,
            ];
        })
        ->values()
        ->all();

    return [
        'collection' => $rows,
        'total'      => (int) $total,
        'page'       => $page,
        'per_page'   => $per,
    ];
}


    /**
     * Danh sách phiếu chi trong kỳ (paging).
     * (Sẽ bơm logic ở bước tiếp theo)
     */
public function payments(string $q, ?string $from, ?string $to, int $page, int $per): array
{
    $endDate   = $to   ?: date('Y-m-d');
    $startDate = $from ?: $this->firstDayOfMonth($endDate);

    // Kiểm tra có bảng danh mục chi phí không
    $hasCat = $this->columnExists('phieu_chis', 'category_id') && $this->columnExists('expense_categories', 'id');

    $base = DB::table('phieu_chis as pc')
        ->when($hasCat, fn($q) => $q->leftJoin('expense_categories as c', 'c.id', '=', 'pc.category_id'))
        ->when($hasCat, fn($q) => $q->leftJoin('expense_categories as p', 'p.id', '=', 'c.parent_id'))
        ->whereDate('pc.ngay_chi', '>=', $startDate)
        ->whereDate('pc.ngay_chi', '<=', $endDate)
        ->selectRaw("
            pc.id,
            pc.ma_phieu_chi,
            pc.ngay_chi,
            pc.so_tien,
            pc.nguoi_nhan,
            pc.phuong_thuc_thanh_toan,
            pc.so_tai_khoan,
            pc.ngan_hang,
            pc.ly_do_chi,
            " . ($hasCat ? "COALESCE(p.name,'') as parent_name" : "NULL as parent_name") . ",
            " . ($hasCat ? "COALESCE(c.name,'') as category_name" : "NULL as category_name") . "
        ");

    if ($q !== '') {
        $kw = '%' . str_replace(['%','_'], ['\\%','\\_'], $q) . '%';
        $base->where(function ($qq) use ($kw, $hasCat) {
            $qq->orWhere('pc.ma_phieu_chi', 'like', $kw)
               ->orWhere('pc.nguoi_nhan', 'like', $kw)
               ->orWhere('pc.ly_do_chi', 'like', $kw)
               ->orWhere('pc.so_tai_khoan', 'like', $kw)
               ->orWhere('pc.ngan_hang', 'like', $kw);
            if ($hasCat) {
                $qq->orWhere('c.name', 'like', $kw)
                   ->orWhere('p.name', 'like', $kw);
            }
        });
    }

    $total = DB::query()->fromSub($base, 't')->count();

    $rows = DB::query()->fromSub($base, 't')
        ->orderBy('t.ngay_chi', 'desc')->orderBy('t.id', 'desc')
        ->offset(($page - 1) * $per)->limit($per)
        ->get()
        ->map(function ($r) {
            return [
                'id'                       => (int) $r->id,
                'ma_phieu_chi'             => $r->ma_phieu_chi,
                'ngay_chi'                 => $r->ngay_chi,
                'so_tien'                  => (int) $r->so_tien,
                'nguoi_nhan'               => $r->nguoi_nhan,
                'phuong_thuc_thanh_toan'   => (int) $r->phuong_thuc_thanh_toan,
                'so_tai_khoan'             => $r->so_tai_khoan,
                'ngan_hang'                => $r->ngan_hang,
                'ly_do_chi'                => $r->ly_do_chi,
                'parent_name'              => $r->parent_name,   // nhóm cha (COGS/BH/QLDN/… nếu có)
                'category_name'            => $r->category_name, // danh mục con (nếu có)
            ];
        })
        ->values()
        ->all();

    return [
        'collection' => $rows,
        'total'      => (int) $total,
        'page'       => $page,
        'per_page'   => $per,
    ];
}

    /**
     * Sổ quỹ theo tài khoản (paging).
     * (Sẽ bơm logic ở bước tiếp theo)
     */
public function ledger($accountId, string $q, ?string $from, ?string $to, int $page, int $per): array
{
    $endDate   = $to   ?: date('Y-m-d');
    $startDate = $from ?: $this->firstDayOfMonth($endDate);

    // ========= BẢNG/ CỘT =========
    // so_quy_entries: tai_khoan_id, ngay_ct (DATETIME), amount (+ vào / - ra), ref_type, ref_id, ref_code, mo_ta
    // tai_khoan_tiens: id, ten_tk, loai, opening_balance, opening_date, is_active
    $hasTK = $this->columnExists('so_quy_entries', 'tai_khoan_id') && $this->columnExists('tai_khoan_tiens', 'id');

    // ========= TÓM TẮT (opening / in / out / ending) =========
    // Opening = SUM(opening_balance where opening_date IS NULL or <= startDate) + SUM(entries.amount where ngay_ct < startDate)
    // In/Out = entries trong [startDate, endDate] (in = tổng dương, out = tổng |âm|)
    // Ending = Opening + In - Out
    // (Nếu lọc 1 account: chỉ tính riêng account đó; nếu không: tính gộp tất cả active accounts)
    $openingBalanceQuery = DB::table('tai_khoan_tiens')
        ->when($accountId, fn($q2) => $q2->where('id', $accountId))
        ->where('is_active', true)
        ->where(function ($qq) use ($startDate) {
            $qq->whereNull('opening_date')
               ->orWhereDate('opening_date', '<=', $startDate);
        })
        ->selectRaw('COALESCE(SUM(opening_balance),0) as ob')
        ->value('ob');

    $sumBefore = DB::table('so_quy_entries')
        ->when($accountId, fn($q2) => $q2->where('tai_khoan_id', $accountId))
        ->whereDate('ngay_ct', '<', $startDate)
        ->selectRaw('COALESCE(SUM(amount),0) as s')
        ->value('s');

    $inRangePos = DB::table('so_quy_entries')
        ->when($accountId, fn($q2) => $q2->where('tai_khoan_id', $accountId))
        ->whereDate('ngay_ct', '>=', $startDate)
        ->whereDate('ngay_ct', '<=', $endDate)
        ->where('amount', '>', 0)
        ->selectRaw('COALESCE(SUM(amount),0) as s')
        ->value('s');

    $inRangeNegAbs = DB::table('so_quy_entries')
        ->when($accountId, fn($q2) => $q2->where('tai_khoan_id', $accountId))
        ->whereDate('ngay_ct', '>=', $startDate)
        ->whereDate('ngay_ct', '<=', $endDate)
        ->where('amount', '<', 0)
        ->selectRaw('COALESCE(SUM(-amount),0) as s') // lấy trị tuyệt đối tổng âm
        ->value('s');

    $opening = (float) ($openingBalanceQuery + $sumBefore);
    $in      = (float) $inRangePos;
    $out     = (float) $inRangeNegAbs;
    $ending  = $opening + $in - $out;

    // ========= DANH SÁCH BÚT TOÁN (paging) =========
    $base = DB::table('so_quy_entries as e')
        ->when($hasTK, fn($q2) => $q2->leftJoin('tai_khoan_tiens as tk', 'tk.id', '=', 'e.tai_khoan_id'))
        ->when($accountId, fn($q2) => $q2->where('e.tai_khoan_id', $accountId))
        ->whereDate('e.ngay_ct', '>=', $startDate)
        ->whereDate('e.ngay_ct', '<=', $endDate)
        ->selectRaw("
            e.id,
            e.tai_khoan_id,
            " . ($hasTK ? "tk.ten_tk" : "NULL") . " as tai_khoan_ten,
            e.ngay_ct,
            e.amount,
            e.ref_type,
            e.ref_id,
            e.ref_code,
            e.mo_ta
        ");

    if ($q !== '') {
        $kw = '%' . str_replace(['%','_'], ['\\%','\\_'], $q) . '%';
        $base->where(function ($qq) use ($kw) {
            $qq->orWhere('e.ref_code', 'like', $kw)
               ->orWhere('e.ref_type', 'like', $kw)
               ->orWhere('e.mo_ta', 'like', $kw);
        });
    }

    $total = DB::query()->fromSub($base, 't')->count();

    $rows = DB::query()->fromSub($base, 't')
        ->orderBy('t.ngay_ct', 'desc')
        ->orderBy('t.id', 'desc')
        ->offset(($page - 1) * $per)
        ->limit($per)
        ->get()
        ->map(function ($r) {
            return [
                'id'             => (int) $r->id,
                'tai_khoan_id'   => (int) $r->tai_khoan_id,
                'tai_khoan_ten'  => $r->tai_khoan_ten,
                'ngay_ct'        => $r->ngay_ct,              // DATETIME
                'amount'         => (float) $r->amount,       // dương = vào; âm = ra
                'ref_type'       => $r->ref_type,
                'ref_id'         => $r->ref_id ? (int) $r->ref_id : null,
                'ref_code'       => $r->ref_code,
                'mo_ta'          => $r->mo_ta,
            ];
        })
        ->values()
        ->all();

    return [
        'collection' => $rows,
        'total'      => (int) $total,
        'page'       => $page,
        'per_page'   => $per,
        'summary'    => [
            'from'    => $startDate,
            'to'      => $endDate,
            'opening' => (float) round($opening, 2),
            'in'      => (float) round($in, 2),
            'out'     => (float) round($out, 2),
            'ending'  => (float) round($ending, 2),
        ],
    ];
}


    /* ================= Helpers ================ */

    private function emptyPage(int $page, int $per): array
    {
        return [
            'collection' => [],
            'total'      => 0,
            'page'       => $page,
            'per_page'   => $per,
        ];
    }

    private function firstDayOfMonth(string $ymd): string
    {
        $ts = strtotime($ymd . ' 00:00:00');
        return date('Y-m-01', $ts);
    }

    private function daysInclusive(string $from, string $to): int
    {
        $a = strtotime(substr($from, 0, 10) . ' 00:00:00');
        $b = strtotime(substr($to,   0, 10) . ' 00:00:00');
        return (int) max(1, floor(($b - $a) / 86400) + 1);
    }

    /**
     * Kiểm tra cột tồn tại (robust, không yêu cầu doctrine/dbal).
     */
    private function columnExists(string $table, string $column): bool
    {
        try {
            // Thử query LIMIT 0
            DB::table($table)->select($column)->limit(0)->get();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
