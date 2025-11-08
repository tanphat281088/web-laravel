<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Class\CustomResponse;

// ====== IMPORTS ======
use App\Exports\KQKDExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Exports\KQKDExcelExport;

class BaoCaoQuanTriController extends Controller
{
    public function kqkd(Request $request)
    {
        $from     = $request->query('from');
        $to       = $request->query('to');
        $groupBy  = $request->query('group_by', 'none'); // none | month

        // ===== Helper range =====
        $dateFilter = function ($q, $dateCol) use ($from, $to) {
            if ($from) $q->whereDate($dateCol, '>=', $from);
            if ($to)   $q->whereDate($dateCol, '<=', $to);
        };

        // ===== 01 & 04 từ Phiếu thu =====
        // Xác định cột loại phiếu thu: 'loai' hoặc 'loai_phieu_thu' (tuỳ DB hiện tại)
        $thuTypeCol = null;
        if ($this->columnExists('phieu_thus', 'loai')) {
            $thuTypeCol = 'loai';
        } elseif ($this->columnExists('phieu_thus', 'loai_phieu_thu')) {
            $thuTypeCol = 'loai_phieu_thu';
        }

// 01. Doanh thu bán hàng — THEO ĐƠN HÀNG ĐÃ GIAO (trang_thai_don_hang = 2; ngày = nguoi_nhan_thoi_gian)
$v01 = (int) DB::table('don_hangs')
    ->whereIn('trang_thai_don_hang', [2, 3])

    ->when($from, fn($q) => $q->whereDate('nguoi_nhan_thoi_gian', '>=', $from))
    ->when($to,   fn($q) => $q->whereDate('nguoi_nhan_thoi_gian', '<=', $to))
    ->sum('tong_tien_can_thanh_toan');


        // 04. Doanh thu HĐ tài chính = tổng phiếu thu loại 'TAI_CHINH' hoặc 5
        $dtQuery04 = DB::table('phieu_thus')
            ->when(
                $thuTypeCol,
                fn($q)=>$q->where(function($qq) use ($thuTypeCol){
                    $qq->where($thuTypeCol,'=','TAI_CHINH')
                       ->orWhere($thuTypeCol,'=',5);
                }),
                fn($q)=>$q->whereRaw('1=0') // nếu không xác định được cột loại
            )
            ->when($this->columnExists('phieu_thus','ngay_thu'),
                fn($q)=>$dateFilter($q,'ngay_thu'),
                fn($q)=>$dateFilter($q,'created_at')
            );
        $v04 = (int) $dtQuery04->sum('so_tien');

        // ===== 02/05/06/07/08/10: gom từ Phiếu chi theo statement_line (ở CHA) =====
        $ec = 'expense_categories';
        $pc = 'phieu_chis';

        $chiByLine = DB::table($pc)
            ->leftJoin($ec.' as c', 'c.id', '=', $pc.'.category_id')
            ->leftJoin($ec.' as p', 'p.id', '=', 'c.parent_id')
            ->when($from, fn($q)=>$q->whereDate($pc.'.ngay_chi','>=',$from))
            ->when($to,   fn($q)=>$q->whereDate($pc.'.ngay_chi','<=',$to))
            ->selectRaw('COALESCE(p.statement_line, 0) as line, SUM('.$pc.'.so_tien) as total')
            ->groupBy('line')
            ->pluck('total','line');

        $v02 = (int) ($chiByLine[2]  ?? 0);  // Giá vốn
        $v05 = (int) ($chiByLine[5]  ?? 0);  // CP tài chính
        $v06 = (int) ($chiByLine[6]  ?? 0);  // CP bán hàng
        $v07 = (int) ($chiByLine[7]  ?? 0);  // CP QLDN
        $v08 = (int) ($chiByLine[8]  ?? 0);  // CP đầu tư CCDC (MỚI)
        $v10 = (int) ($chiByLine[10] ?? 0);  // CP khác

        // ===== Thu nhập khác (11) — tạm thời = 0 nếu chưa có nguồn riêng =====
        $v11 = 0;

        // ===== Chỉ tiêu tổng hợp =====
        $v03 = $v01 - $v02;                                              // Lợi nhuận gộp
        $v09 = $v03 + $v04 - $v05 - $v06 - $v07 - $v08;                  // Lợi nhuận thuần HĐKD
        $v12 = $v10 - $v11;                                              // Lợi nhuận khác (12 = 10 − 11)
        $v13 = $v09 + $v12;                                              // LNTT

        $summary = [
            '01_doanh_thu_ban_hang'          => $v01,
            '02_gia_von_hang_ban'            => $v02,
            '03_loi_nhuan_gop'               => $v03,
            '04_doanh_thu_hd_tai_chinh'      => $v04,
            '05_chi_phi_tai_chinh'           => $v05,
            '06_chi_phi_ban_hang'            => $v06,
            '07_chi_phi_quan_ly_dn'          => $v07,
            '08_chi_phi_dau_tu_ccdc'         => $v08,   // MỚI
            '09_loi_nhuan_thuan_hd_kd'       => $v09,   // MỚI
            '10_chi_phi_khac'                => $v10,
            '11_thu_nhap_khac'               => $v11,   // MỚI (tạm 0)
            '12_loi_nhuan_khac'              => $v12,   // MỚI (12 = 10 − 11)
            '13_loi_nhuan_truoc_thue'        => $v13,
        ];

        // ===== Group theo tháng (tuỳ chọn) =====
        $series = [];
        if ($groupBy === 'month') {
            // Tháng cho phiếu thu (tách 01 & 04)
            $hasNgayThu = $this->columnExists('phieu_thus','ngay_thu');
// Series 01 — ĐƠN HÀNG ĐÃ GIAO theo tháng (group by DATE_FORMAT(nguoi_nhan_thoi_gian,'%Y-%m'))
$dtSeries01 = DB::table('don_hangs')
->whereIn('trang_thai_don_hang', [2, 3])

    ->when($from, fn($q)=>$q->whereDate('nguoi_nhan_thoi_gian','>=',$from))
    ->when($to,   fn($q)=>$q->whereDate('nguoi_nhan_thoi_gian','<=',$to))
    ->selectRaw("DATE_FORMAT(nguoi_nhan_thoi_gian,'%Y-%m') as ym, SUM(tong_tien_can_thanh_toan) as total")
    ->groupBy('ym')->pluck('total','ym');


            $dtSeries04 = DB::table('phieu_thus')
                ->when(
                    $thuTypeCol,
                    fn($q)=>$q->where(function($qq) use ($thuTypeCol){
                        $qq->where($thuTypeCol,'=','TAI_CHINH')
                           ->orWhere($thuTypeCol,'=',5);
                    }),
                    fn($q)=>$q->whereRaw('1=0')
                )
                ->when($hasNgayThu, fn($q)=>$dateFilter($q,'ngay_thu'), fn($q)=>$dateFilter($q,'created_at'))
                ->selectRaw(($hasNgayThu ? "DATE_FORMAT(ngay_thu,'%Y-%m')" : "DATE_FORMAT(created_at,'%Y-%m')") . " as ym, SUM(so_tien) as total")
                ->groupBy('ym')->pluck('total','ym');

            // Tháng cho phiếu chi theo line (đang theo created_at như code cũ)
            $chiSeries = DB::table($pc)
                ->leftJoin($ec.' as c','c.id','=',$pc.'.category_id')
                ->leftJoin($ec.' as p','p.id','=','c.parent_id')
                ->when($from, fn($q)=>$q->whereDate($pc.'.ngay_chi','>=',$from))
                ->when($to,   fn($q)=>$q->whereDate($pc.'.ngay_chi','<=',$to))
              ->selectRaw("DATE_FORMAT($pc.ngay_chi,'%Y-%m') as ym, COALESCE(p.statement_line,0) as line, SUM($pc.so_tien) as total")

                ->groupBy('ym','line')->get()->groupBy('ym');

            foreach ($chiSeries as $ym => $rows) {
                $s02 = (int) (collect($rows)->firstWhere('line',2 )->total ?? 0);
                $s05 = (int) (collect($rows)->firstWhere('line',5 )->total ?? 0);
                $s06 = (int) (collect($rows)->firstWhere('line',6 )->total ?? 0);
                $s07 = (int) (collect($rows)->firstWhere('line',7 )->total ?? 0);
                $s08 = (int) (collect($rows)->firstWhere('line',8 )->total ?? 0);
                $s10 = (int) (collect($rows)->firstWhere('line',10)->total ?? 0);

                $s01 = (int) ($dtSeries01[$ym] ?? 0);
                $s04 = (int) ($dtSeries04[$ym] ?? 0);
                $s03 = $s01 - $s02;
                $s09 = $s03 + $s04 - $s05 - $s06 - $s07 - $s08;

                $s11 = 0;                 // Thu nhập khác (chưa có nguồn) → 0
                $s12 = $s10 - $s11;       // 12 = 10 − 11
                $s13 = $s09 + $s12;

                $series[] = [
                    'ym'  => $ym,
                    '01'  => $s01, '02' => $s02, '03' => $s03, '04' => $s04,
                    '05'  => $s05, '06' => $s06, '07' => $s07, '08' => $s08,
                    '09'  => $s09, '10' => $s10, '11' => $s11, '12' => $s12,
                    '13'  => $s13,
                ];
            }

            // Tháng chỉ có thu (không chi)
            $allYm = array_unique(array_merge(array_keys($dtSeries01->toArray()), array_keys($dtSeries04->toArray())));
            foreach ($allYm as $ym) {
                if (!collect($series)->contains(fn($r)=>$r['ym']===$ym)) {
                    $s01 = (int) ($dtSeries01[$ym] ?? 0);
                    $s04 = (int) ($dtSeries04[$ym] ?? 0);
                    $s02=$s05=$s06=$s07=$s08=$s10=0;
                    $s03 = $s01 - $s02;
                    $s09 = $s03 + $s04 - $s05 - $s06 - $s07 - $s08;
                    $s11 = 0; $s12 = $s10 - $s11; $s13 = $s09 + $s12;

                    $series[] = [
                        'ym'=>$ym,
                        '01'=>$s01,'02'=>$s02,'03'=>$s03,'04'=>$s04,
                        '05'=>$s05,'06'=>$s06,'07'=>$s07,'08'=>$s08,
                        '09'=>$s09,'10'=>$s10,'11'=>$s11,'12'=>$s12,'13'=>$s13
                    ];
                }
            }

            usort($series, fn($a,$b)=>strcmp($a['ym'],$b['ym']));
        }

        return CustomResponse::success([
            'params'  => ['from'=>$from,'to'=>$to,'group_by'=>$groupBy],
            'summary' => $summary,
            'series'  => $series,
        ]);
    }

    public function kqkdDetail(Request $request)
    {
        $from = $request->query('from');
        $to   = $request->query('to');
        $line = (int) $request->query('line', 0); // 1|2|5|6|7|8|10

        if (!in_array($line, [1,2,5,6,7,8,10], true)) {
            return CustomResponse::error('Tham số line không hợp lệ (chỉ nhận 1,2,5,6,7,8,10)', 422);
        }

  if ($line === 1) {
    // Chi tiết doanh thu 01 — ĐƠN HÀNG ĐÃ GIAO (alias field giữ nguyên để FE dùng lại)
    $rows = DB::table('don_hangs as dh')
      ->whereIn('dh.trang_thai_don_hang', [2, 3])

        ->when($from, fn($q)=>$q->whereDate('dh.nguoi_nhan_thoi_gian','>=',$from))
        ->when($to,   fn($q)=>$q->whereDate('dh.nguoi_nhan_thoi_gian','<=',$to))
        ->selectRaw(
            "dh.id, dh.ma_don_hang as ma_phieu_thu, " .
            "DATE(dh.nguoi_nhan_thoi_gian) as ngay, " .
            "dh.ten_khach_hang as nguoi_tra, " .
            "dh.tong_tien_can_thanh_toan as so_tien"
        )
        ->orderBy('dh.nguoi_nhan_thoi_gian','desc')->orderBy('dh.id','desc')
        ->get();

    $byDay = $rows->groupBy('ngay')->map(fn($g)=>$g->sum('so_tien'))->map(fn($v)=>(int)$v)->all();

    return CustomResponse::success([
        'params'     => ['from'=>$from,'to'=>$to,'line'=>$line],
        'byCategory' => [],
        'byDay'      => $byDay,
        'rows'       => $rows,
    ]);
}


        // ====== Chi phí: 02/05/06/07/08/10 ======
        $pc = 'phieu_chis';
        $ec = 'expense_categories';

        $base = DB::table($pc.' as pc')
            ->leftJoin($ec.' as c','c.id','=','pc.category_id')
            ->leftJoin($ec.' as p','p.id','=','c.parent_id')
            ->when($from, fn($q)=>$q->whereDate('pc.ngay_chi','>=',$from))
            ->when($to,   fn($q)=>$q->whereDate('pc.ngay_chi','<=',$to))
            ->where('p.statement_line', $line);

        $byCategory = (clone $base)
            ->selectRaw('COALESCE(p.name,"Chưa phân loại") as parent_name,
                         COALESCE(c.name,"Chưa phân loại") as category_name,
                         COALESCE(c.code,"") as category_code,
                         SUM(pc.so_tien) as total')
            ->groupBy('parent_name','category_name','category_code')
            ->orderBy('parent_name')->orderBy('category_name')
            ->get();

        $rows = (clone $base)
            ->selectRaw('pc.id, pc.ma_phieu_chi, pc.ngay_chi, pc.so_tien, pc.nguoi_nhan,
                         pc.phuong_thuc_thanh_toan,
                         COALESCE(p.name,"Chưa phân loại") as parent_name,
                         COALESCE(c.name,"Chưa phân loại") as category_name')
            ->orderBy('pc.ngay_chi','desc')
            ->orderBy('pc.id','desc')
            ->get();

        return CustomResponse::success([
            'params'     => ['from'=>$from,'to'=>$to,'line'=>$line],
            'byCategory' => $byCategory,
            'rows'       => $rows,
        ]);
    }

    // ====== Export (Excel/PDF) ======
 // ====== Export (Excel/PDF) ======
public function kqkdExport(Request $request)
{
    $from       = $request->query('from');
    $to         = $request->query('to');
    $groupBy    = $request->query('group_by', 'month'); // giữ cho series
    $format     = strtolower($request->query('format', 'xlsx')); // xlsx | pdf
    $disposition= strtolower($request->query('disposition', 'download')); // download|stream
    $debug      = strtolower($request->query('debug', '')); // html|pdf|stream

    // Dùng lại logic kqkd()
    $response = $this->kqkd($request);
    $payload  = $response->getData(true)['data'] ?? [];

    $summary = $payload['summary'] ?? [];
    $series  = $payload['series']  ?? [];

    // ====== Breakdown theo CÂY DANH MỤC (hiển thị cả khi =0) ======
    $children = DB::table('expense_categories as c')
        ->leftJoin('expense_categories as p','p.id','=','c.parent_id')
        ->where('c.is_active', true)
        ->whereIn('p.code', ['COGS','BH','QLDN','TC','CHI_KHAC','CCDC'])
        ->selectRaw('c.id, c.code, c.name, COALESCE(c.statement_line, p.statement_line, 0) as line,
                     COALESCE(p.name,"Chưa phân loại") as parent_name')
        ->orderBy('p.sort_order')->orderBy('c.sort_order')->orderBy('c.name')
        ->get();

    $sumByCategory = DB::table('phieu_chis as pc')
        ->when($from, fn($q)=>$q->whereDate('pc.ngay_chi','>=',$from))
        ->when($to,   fn($q)=>$q->whereDate('pc.ngay_chi','<=',$to))
        ->selectRaw('pc.category_id, SUM(pc.so_tien) as total')
        ->groupBy('pc.category_id')
        ->pluck('total','category_id');

    $byCatMap = [2=>[],5=>[],6=>[],7=>[],8=>[],10=>[]];
    foreach ($children as $row) {
        $line = (int) ($row->line ?? 0);
        if (!isset($byCatMap[$line])) $byCatMap[$line] = [];
        $byCatMap[$line][] = [
            'parent_name'   => $row->parent_name,
            'category_code' => $row->code,
            'category_name' => $row->name,
            'total'         => (int) ($sumByCategory[$row->id] ?? 0),
        ];
    }

    // ===== DEBUG: xem HTML gốc (để phát hiện lỗi view) =====
    if ($debug === 'html') {
        return view('exports.kqkd', compact('summary','series','from','to','byCatMap'));
    }

    if ($format === 'pdf' || in_array($debug, ['pdf','stream'], true)) {
        // DỌN SẠCH TẤT CẢ BUFFER trước khi render PDF (tránh PDF rỗng/9 bytes)
        while (ob_get_level() > 0) { @ob_end_clean(); }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::setWarnings(true)
            ->loadView('exports.kqkd', compact('summary','series','from','to','byCatMap'))
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont'          => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled'      => true,
            ]);

        $filename = 'BaoCao_KQKD_'.date('Ymd_His').'.pdf';

        // DEBUG: stream trực tiếp
        if ($debug === 'stream' || $disposition === 'stream') {
            return $pdf->stream($filename);
        }

        // Ghi file tạm rồi trả về (ổn định hơn trên một số môi trường)
        $tmpPath = storage_path('app/'.$filename);
        file_put_contents($tmpPath, $pdf->output());

        \Log::info('KQKD PDF size='.((is_file($tmpPath)?filesize($tmpPath):0)).' bytes; path='.$tmpPath);

        if (is_file($tmpPath) && filesize($tmpPath) > 0) {
            return response()->file($tmpPath, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ])->deleteFileAfterSend(true);
        }

        // Fallback cuối nếu vì lý do gì đó file vẫn rỗng
        return \App\Class\CustomResponse::error('PDF rỗng. Mở ?debug=html để kiểm tra view.', 500);
    }

    // ===== Excel (giữ nguyên) =====
    $export   = new \App\Exports\KQKDExcelExport($summary, $series, $byCatMap, $from, $to);
    $filename = 'BaoCao_KQKD_'.date('Ymd_His').'.xlsx';
    return \Maatwebsite\Excel\Facades\Excel::download($export, $filename);
}


    // ====== Helper: kiểm tra cột tồn tại ======
    private function columnExists(string $table, string $column): bool
    {
        try {
            $exists = DB::getDoctrineSchemaManager()
                ->listTableDetails($table)
                ->hasColumn($column);
            return (bool)$exists;
        } catch (\Throwable $e) {
            // Fallback: thử query LIMIT 0
            try {
                DB::table($table)->select($column)->limit(0)->get();
                return true;
            } catch (\Throwable $e2) {
                return false;
            }
        }
    }
}
