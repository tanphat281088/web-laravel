<?php

namespace App\Modules\ThuChi;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BaoCaoThuChiService
{
    /**
     * Tính tổng thu / tổng chi trong khoảng thời gian
     * Params:
     * - from (YYYY-MM-DD)
     * - to   (YYYY-MM-DD)
     * - preset: week | month (ưu tiên nếu không truyền from/to)
     */
    public function tongHop(array $params = []): array
    {
        [$from, $to] = $this->resolveRange($params);

        // Xác định cột ngày & cột loại cho phieu_thus
        $dateCol    = $this->columnExists('phieu_thus', 'ngay_thu') ? 'ngay_thu' : 'created_at';
        $thuTypeCol = $this->detectThuTypeColumn(); // 'loai' | 'loai_phieu_thu' | null

        // Tổng THU (tất cả loại)
        $tongThu = (float) DB::table('phieu_thus')
            ->when($from, fn($q) => $q->whereDate($dateCol, '>=', $from))
            ->when($to,   fn($q) => $q->whereDate($dateCol, '<=', $to))
            ->sum('so_tien');

        // Tách THU: bán hàng (01) & tài chính (04)
        if ($thuTypeCol) {
            // Thu HOẠT ĐỘNG TÀI CHÍNH: loại = 'TAI_CHINH' hoặc = 5
            $thuTaiChinh = (float) DB::table('phieu_thus')
                ->where(function ($qq) use ($thuTypeCol) {
                    $qq->where($thuTypeCol, '=', 'TAI_CHINH')
                       ->orWhere($thuTypeCol, '=', 5);
                })
                ->when($from, fn($q) => $q->whereDate($this->columnExists('phieu_thus', 'ngay_thu') ? 'ngay_thu' : 'created_at', '>=', $from))
                ->when($to,   fn($q) => $q->whereDate($this->columnExists('phieu_thus', 'ngay_thu') ? 'ngay_thu' : 'created_at', '<=', $to))
                ->sum('so_tien');

            // Thu BÁN HÀNG & CCDV: loại <> 'TAI_CHINH' và <> 5
            $thuBanHang = (float) DB::table('phieu_thus')
                ->where(function ($qq) use ($thuTypeCol) {
                    $qq->where($thuTypeCol, '!=', 'TAI_CHINH')
                       ->where($thuTypeCol, '!=', 5);
                })
                ->when($from, fn($q) => $q->whereDate($this->columnExists('phieu_thus', 'ngay_thu') ? 'ngay_thu' : 'created_at', '>=', $from))
                ->when($to,   fn($q) => $q->whereDate($this->columnExists('phieu_thus', 'ngay_thu') ? 'ngay_thu' : 'created_at', '<=', $to))
                ->sum('so_tien');
        } else {
            // Không có cột loại -> không tách được: coi toàn bộ là bán hàng, tài chính = 0
            $thuTaiChinh = 0.0;
            $thuBanHang  = $tongThu;
        }

        // Tổng CHI
        $tongChi = (float) DB::table('phieu_chis')
            ->when($from, fn($q) => $q->whereDate('ngay_chi', '>=', $from))
            ->when($to,   fn($q) => $q->whereDate('ngay_chi', '<=', $to))
            ->sum('so_tien');

        return [
            'from'          => $from,
            'to'            => $to,
            'tong_thu'      => $tongThu,
            'tong_chi'      => $tongChi,
            'chenh_lech'    => $tongThu - $tongChi,

            // ====== BỔ SUNG BREAKDOWN ======
            'thu_ban_hang'  => $thuBanHang,   // 01. Doanh thu bán hàng & CCDV
            'thu_tai_chinh' => $thuTaiChinh,  // 04. Doanh thu hoạt động tài chính
        ];
    }

    /**
     * Xác định khoảng thời gian [from, to]
     * Ưu tiên: from/to -> preset (week/month) -> default: tuần hiện tại
     */
    private function resolveRange(array $params): array
    {
        $from   = $params['from']   ?? null;
        $to     = $params['to']     ?? null;
        $preset = $params['preset'] ?? null;

        if ($from && $to) {
            $from = Carbon::parse($from)->startOfDay()->toDateString();
            $to   = Carbon::parse($to)->endOfDay()->toDateString();
            return [$from, $to];
        }

        if ($preset === 'month') {
            $from = Carbon::now()->startOfMonth()->toDateString();
            $to   = Carbon::now()->endOfMonth()->toDateString();
            return [$from, $to];
        }

        // default hoặc preset=week
        $from = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $to   = Carbon::now()->endOfWeek(Carbon::SUNDAY)->toDateString();
        return [$from, $to];
    }

    /**
     * Tìm cột loại phiếu thu hiện dùng trong DB: 'loai' | 'loai_phieu_thu' | null
     */
    private function detectThuTypeColumn(): ?string
    {
        if ($this->columnExists('phieu_thus', 'loai')) {
            return 'loai';
        }
        if ($this->columnExists('phieu_thus', 'loai_phieu_thu')) {
            return 'loai_phieu_thu';
        }
        return null;
    }

    /**
     * Helper: kiểm tra cột tồn tại (ưu tiên Doctrine, fallback LIMIT 0)
     */
    private function columnExists(string $table, string $column): bool
    {
        try {
            $exists = DB::getDoctrineSchemaManager()
                ->listTableDetails($table)
                ->hasColumn($column);
            return (bool) $exists;
        } catch (\Throwable $e) {
            try {
                DB::table($table)->select($column)->limit(0)->get();
                return true;
            } catch (\Throwable $e2) {
                return false;
            }
        }
    }
}
