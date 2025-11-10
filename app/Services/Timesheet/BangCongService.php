<?php

namespace App\Services\Timesheet;

use App\Models\BangCongThang;
use App\Models\ChamCong;
use App\Models\DonTuNghiPhep;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BangCongService
{
    protected WorkdayRule $rule;
    protected HolidayCalendar $calendar;

    public function __construct()
    {
        $this->rule     = new WorkdayRule();
        $this->calendar = new HolidayCalendar();
    }

    /**
     * Tổng hợp bảng công cho 1 kỳ/tháng (YYYY-MM).
     * Quy ước: 'thang' là THÁNG BẮT ĐẦU kỳ 6→5 (VD: 2025-10 = 2025-10-06 → 2025-11-05).
     */
public function computeMonth(string $thang, ?int $userId = null): void
{
    // Dùng range kỳ 6→5 theo cấu hình
    [$start, $end] = $this->cycleRange($thang);

    \Log::info('Timesheet::computeMonth START', [
        'thang'    => $thang,
        'range'    => [$start->toDateTimeString(), $end->toDateTimeString()],
        'user_id'  => $userId,
    ]);

    $userIds = $this->collectUserIds($start, $end, $userId);

    // ✅ Mỗi user một transaction ngắn (tránh giữ lock dài cho cả tháng)
    foreach ($userIds as $uid) {
        DB::transaction(function () use ($uid, $thang, $start, $end) {
            $existing = BangCongThang::query()->ofUser($uid)->month($thang)->first();
            if ($existing && $existing->locked) {
                \Log::info('Timesheet::computeMonth SKIP locked row', ['uid' => $uid, 'thang' => $thang]);
                return;
            }

            // 1) Ngày công (đủ in+out), có thể loại trừ weekend/holiday tùy config
            $workedDays = $this->countWorkedDaysAdvanced($uid, $start, $end);

            // 2) Nghỉ phép / không lương (lọc overlap theo tu_ngay/den_ngay)
            [$npNgay, $npGio, $klNgay, $klGio] = $this->sumLeaves($uid, $start, $end);

            // 3) Tổng giờ công, đi trễ, về sớm, OT
            [$soGioCong, $diTre, $veSom, $otGio] = $this->sumWorkHoursAndLateEarlyOT($uid, $start, $end);

            $note = [
                'computed_by' => 'BangCongService::computeMonth',
                'range'       => [$start->toDateString(), $end->toDateString()],
                'rule'        => [
                    'enabled'       => $this->rule->enabled(),
                    'start'         => $this->rule->start(),
                    'end'           => $this->rule->end(),
                    'break_start'   => $this->rule->breakStart(),
                    'break_end'     => $this->rule->breakEnd(),
                    'grace_minutes' => $this->rule->grace(),
                    'ot' => [
                        'enabled'         => (bool) config('timesheet.ot.enabled', false),
                        'after_end_only'  => (bool) config('timesheet.ot.after_end_only', true),
                        'min_minutes'     => (int)  config('timesheet.ot.min_minutes', 10),
                    ],
                    'calendar' => [
                        'weekend' => [
                            'enabled'  => $this->calendar->weekendEnabled(),
                            'days'     => $this->calendar->weekendDays(),
                            'exclude'  => $this->calendar->weekendExcludeFromWorkedDays(),
                        ],
                        'holiday' => [
                            'enabled'  => $this->calendar->holidayEnabled(),
                            'list_cnt' => count($this->calendar->holidays()),
                            'exclude'  => $this->calendar->holidayExcludeFromWorkedDays(),
                        ],
                    ],
                ],
            ];


            // ÉP KIỂU AN TOÀN TRƯỚC KHI GHI DB
$workedDays = (int) $workedDays;
$soGioCong  = max(0, (int) round($soGioCong));
$diTre      = max(0, (int) round($diTre));
$veSom      = max(0, (int) round($veSom));
$npNgay     = max(0, (int) round($npNgay));
$npGio      = max(0, (int) round($npGio));
$klNgay     = max(0, (int) round($klNgay));
$klGio      = max(0, (int) round($klGio));
$otGio      = max(0, (int) round($otGio));

            BangCongThang::query()->updateOrCreate(
                ['user_id' => $uid, 'thang' => $thang],
                [
                    'so_ngay_cong'          => $workedDays,
                    'so_gio_cong'           => $soGioCong,
                    'di_tre_phut'           => $diTre,
                    've_som_phut'           => $veSom,
                    'nghi_phep_ngay'        => $npNgay,
                    'nghi_phep_gio'         => $npGio,
                    'nghi_khong_luong_ngay' => $klNgay,
                    'nghi_khong_luong_gio'  => $klGio,
                    'lam_them_gio'          => $otGio,
                    'ghi_chu'               => $note,   // cột JSON → giữ mảng
                    'computed_at'           => now(),
                ]
            );

            \Log::info('Timesheet::computeMonth DONE row', [
                'uid' => $uid, 'thang' => $thang,
                'workedDays' => $workedDays, 'soGioCong' => $soGioCong,
                'late' => $diTre, 'early' => $veSom, 'ot' => $otGio
            ]);
        });
    }
}


    /**
     * Range tháng dương lịch (GIỮ LẠI để tương thích nếu nơi khác vẫn dùng).
     */
    private function monthRange(string $thang): array
    {
        $start = Carbon::createFromFormat('Y-m', $thang)->startOfMonth();
        $end   = (clone $start)->endOfMonth();
        return [$start, $end];
    }

    /**
     * Range kỳ công 6→5 theo config timesheet.cycle_start_day (mặc định 6).
     * VD: 2025-10 -> [2025-10-06 00:00:00, 2025-11-05 23:59:59]
     */
    private function cycleRange(string $thang): array
    {
        $startDay = (int) config('timesheet.cycle_start_day', 6);
        $start = Carbon::createFromFormat('Y-m', $thang)
            ->day($startDay)->startOfDay();

        $end = (clone $start)->addMonthNoOverflow()->subDay()->endOfDay();

        return [$start, $end];
    }

    /**
     * Suy ra nhãn kỳ (YYYY-MM) cho một ngày bất kỳ theo quy tắc 6→5.
     */
    public static function cycleLabelForDate(Carbon $date): string
    {
        $startDay = (int) config('timesheet.cycle_start_day', 6);
        $d = $date->copy();
        if ((int)$d->day < $startDay) {
            $d->subMonthNoOverflow();
        }
        return $d->format('Y-m');
    }

    private function collectUserIds(Carbon $start, Carbon $end, ?int $userId = null): array
    {
        if ($userId) return [$userId];

        $uids = [];

        $uids = array_merge(
            $uids,
            ChamCong::query()
                ->whereBetween('checked_at', [$start->toDateTimeString(), $end->toDateTimeString()])
                ->distinct()->pluck('user_id')->all()
        );

        $uids = array_merge(
            $uids,
            DonTuNghiPhep::query()
                ->where(function ($q) use ($start, $end) {
                    // Overlap theo khoảng nghỉ, không dựa created_at
                    $q->whereBetween('tu_ngay', [$start->toDateString(), $end->toDateString()])
                      ->orWhereBetween('den_ngay', [$start->toDateString(), $end->toDateString()])
                      ->orWhere(function($q2) use ($start, $end) {
                          $q2->where('tu_ngay', '<=', $start->toDateString())
                             ->where('den_ngay', '>=', $end->toDateString());
                      })
                      ->orWhereNull('tu_ngay')
                      ->orWhereNull('den_ngay');
                })
                ->distinct()->pluck('user_id')->all()
        );

        return array_values(array_unique(array_map('intval', $uids)));
    }

    /**
     * Đếm ngày công có đủ in+out; có thể loại trừ weekend/holiday tuỳ config.
     */
    private function countWorkedDaysAdvanced(int $userId, Carbon $start, Carbon $end): int
    {
        $ins = ChamCong::query()->ofUser($userId)->checkin()
            ->whereBetween('checked_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->selectRaw('DATE(checked_at) as d')->distinct()->pluck('d')->all();

        $outs = ChamCong::query()->ofUser($userId)->checkout()
            ->whereBetween('checked_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->selectRaw('DATE(checked_at) as d')->distinct()->pluck('d')->all();

        $days = array_intersect($ins, $outs);

        $count = 0;
        foreach ($days as $d) {
            $day = Carbon::parse($d);
            $isWeekend = $this->calendar->isWeekend($day);
            $isHoliday = $this->calendar->isHoliday($day);

            if ($isWeekend && $this->calendar->weekendExcludeFromWorkedDays()) {
                continue;
            }
            if ($isHoliday && $this->calendar->holidayExcludeFromWorkedDays()) {
                continue;
            }
            $count++;
        }
        return $count;
    }

    /**
     * Cộng tổng giờ công (trừ nghỉ trưa) + đi trễ/về sớm + OT.
     * Trả về: [soGioCong, diTrePhut, veSomPhut, otGio]
     */
    private function sumWorkHoursAndLateEarlyOT(int $userId, Carbon $start, Carbon $end): array
    {
        if (!$this->rule->enabled()) {
            return [0, 0, 0, 0];
        }

        $logs = ChamCong::query()
            ->ofUser($userId)
            ->whereBetween('checked_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->get(['type', 'checked_at']);

        if ($logs->isEmpty()) return [0, 0, 0, 0];

        $byDay = [];
        foreach ($logs as $r) {
            $d = $r->checked_at->toDateString();
            $byDay[$d] ??= ['ins' => [], 'outs' => []];
            if ($r->type === 'checkin')  $byDay[$d]['ins'][]  = $r->checked_at->copy();
            if ($r->type === 'checkout') $byDay[$d]['outs'][] = $r->checked_at->copy();
        }

        $totalMinutes = 0;
        $late = 0; $early = 0; $otMinutes = 0;

        $otEnabled      = (bool) config('timesheet.ot.enabled', false);
        $otAfterEndOnly = (bool) config('timesheet.ot.after_end_only', true);
        $otMin          = (int)  config('timesheet.ot.min_minutes', 10);

        foreach ($byDay as $date => $pairs) {
            if (empty($pairs['ins']) || empty($pairs['outs'])) continue;

            $day = Carbon::parse($date)->startOfDay();

            $firstIn = collect($pairs['ins'])->sort()->first();
            $lastOut = collect($pairs['outs'])->sort()->last();
            $tz = config('app.timezone', 'Asia/Ho_Chi_Minh');
$firstIn = $firstIn->copy()->setTimezone($tz);
$lastOut = $lastOut->copy()->setTimezone($tz);


            [$b1, $b2] = $this->rule->breakPeriod($day);
            $worked = $this->minutesExcludingBreak($firstIn, $lastOut, $b1, $b2);
            if ($worked < 0) $worked = 0;

         $totalMinutes += (int) $worked;


$expectedIn  = $this->rule->dayStart($day)->copy()->addMinutes($this->rule->grace());
$expectedOut = $this->rule->dayEnd($day)->copy()->subMinutes($this->rule->grace());

// Tính trễ/sớm an toàn theo timestamp, ép >= 0 và ép int
$lateDelta  = intdiv(max(0, $firstIn->getTimestamp() - $expectedIn->getTimestamp()), 60);
$earlyDelta = intdiv(max(0, $expectedOut->getTimestamp() - $lastOut->getTimestamp()), 60);
$late  += $lateDelta;
$early += $earlyDelta;


if ($otEnabled) {
    $dailyOT = 0;

    if ($otAfterEndOnly) {
        // chỉ tính sau giờ kết thúc kỳ vọng
        if ($lastOut->gt($expectedOut)) {
            $dailyOT = intdiv(max(0, $lastOut->getTimestamp() - $expectedOut->getTimestamp()), 60);
        }
    } else {
        $aft = intdiv(max(0, $lastOut->getTimestamp() - $expectedOut->getTimestamp()), 60);
        $bef = intdiv(max(0, $expectedIn->getTimestamp() - $firstIn->getTimestamp()), 60);
        $dailyOT = $aft + $bef;
    }

    if ($dailyOT >= $otMin) $otMinutes += $dailyOT;
}

        }
$soGioCong = $totalMinutes;   // lưu PHÚT thay vì GIỜ
$otGio     = $otMinutes;      // lưu PHÚT thay vì GIỜ


// DEBUG >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
\Log::debug('TS:hours', [
    'user_id'     => $userId,
    'range'       => [$start->toDateTimeString(), $end->toDateTimeString()],
    'enabled'     => $this->rule->enabled(),
    'tot_min'     => $totalMinutes,
    'so_gio_cong' => $soGioCong,
    'late_min'    => $late,
    'early_min'   => $early,
    'ot_min'      => $otMinutes,
]);
foreach ($byDay as $date => $pairs) {
    $firstIn = empty($pairs['ins'])  ? null : collect($pairs['ins'])->sort()->first();
    $lastOut = empty($pairs['outs']) ? null : collect($pairs['outs'])->sort()->last();
    if ($firstIn && $lastOut) {
        [$b1, $b2] = $this->rule->breakPeriod(\Carbon\Carbon::parse($date)->startOfDay());
$tz = config('app.timezone', 'Asia/Ho_Chi_Minh');
$fi = $firstIn->copy()->setTimezone($tz);
$lo = $lastOut->copy()->setTimezone($tz);
$b1z= $b1->copy()->setTimezone($tz);
$b2z= $b2->copy()->setTimezone($tz);

$all = max(0, intdiv($lo->getTimestamp() - $fi->getTimestamp(), 60));
if ($lo->lte($b1z) || $fi->gte($b2z)) {
    $worked = $all;
} else {
    $overlapStart = $fi->max($b1z);
    $overlapEnd   = $lo->min($b2z);
    $overlap = 0;
    if ($overlapEnd->greaterThan($overlapStart)) {
        $overlap = intdiv($overlapEnd->getTimestamp() - $overlapStart->getTimestamp(), 60);
    }
    $worked = max(0, $all - $overlap);
}
\Log::debug('TS:day', [
    'user_id'     => $userId,
    'date'        => $date,
    'first_in'    => $fi->toIso8601String(),
    'last_out'    => $lo->toIso8601String(),
    'all_min'     => $all,
    'break'       => [$b1z->toIso8601String(), $b2z->toIso8601String()],
    'worked_min'  => $worked,
]);

    } else {
        \Log::debug('TS:day-miss', [
            'user_id' => $userId,
            'date'    => $date,
            'ins'     => count($pairs['ins'] ?? []),
            'outs'    => count($pairs['outs'] ?? []),
        ]);
    }
}
// DEBUG <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<




        return [$soGioCong, $late, $early, $otGio];
    }

    /**
     * Trả tổng phút giữa [$start,$end] sau khi TRỪ phần giao với khoảng nghỉ [$b1,$b2].
     */
private function minutesExcludingBreak(Carbon $startAt, Carbon $endAt, Carbon $b1, Carbon $b2): int
{
    // Chuẩn hoá TZ để tránh diff âm/0 do lệch timezone
    $tz = config('app.timezone', 'Asia/Ho_Chi_Minh');
    $startAt = $startAt->copy()->setTimezone($tz);
    $endAt   = $endAt->copy()->setTimezone($tz);
    $b1      = $b1->copy()->setTimezone($tz);
    $b2      = $b2->copy()->setTimezone($tz);

    if ($endAt->lessThanOrEqualTo($startAt)) {
        return 0;
    }

    // Tổng phút thô theo timestamp (an toàn tuyệt đối với TZ)
    $all = intdiv($endAt->getTimestamp() - $startAt->getTimestamp(), 60);
    if ($all <= 0) return 0;

    // Nếu khoảng làm việc hoàn toàn ngoài giờ nghỉ → trả luôn
    if ($endAt->lte($b1) || $startAt->gte($b2)) {
        return $all;
    }

    // Trừ phần overlap với giờ nghỉ
    $overlapStart = $startAt->max($b1);
    $overlapEnd   = $endAt->min($b2);
    $overlap = 0;
    if ($overlapEnd->greaterThan($overlapStart)) {
        $overlap = intdiv($overlapEnd->getTimestamp() - $overlapStart->getTimestamp(), 60);
    }

    $worked = $all - $overlap;
    return $worked > 0 ? $worked : 0;
}


    /**
     * Tổng hợp nghỉ phép theo overlap tu_ngay/den_ngay trong khoảng kỳ [start,end].
     */
    private function sumLeaves(int $userId, Carbon $start, Carbon $end): array
    {
        $items = DonTuNghiPhep::query()
            ->ofUser($userId)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('tu_ngay', [$start->toDateString(), $end->toDateString()])
                  ->orWhereBetween('den_ngay', [$start->toDateString(), $end->toDateString()])
                  ->orWhere(function($q2) use ($start,$end){
                      $q2->where('tu_ngay','<=',$start->toDateString())
                         ->where('den_ngay','>=',$end->toDateString());
                  })
                  ->orWhereNull('tu_ngay')
                  ->orWhereNull('den_ngay');
            })
            ->get();

        $npNgay = 0; $npGio = 0; $klNgay = 0; $klGio = 0;

        foreach ($items as $r) {
            if (!$r->isApproved()) continue;

            $loai  = $r->loai;
            $soGio = (int) ($r->so_gio ?? 0);

            $from = $r->tu_ngay ? Carbon::parse($r->tu_ngay)->startOfDay() : null;
            $to   = $r->den_ngay ? Carbon::parse($r->den_ngay)->endOfDay()   : null;

            $overlapDays = 0;
            if ($from && $to) {
                $overlapStart = $from->max($start);
                $overlapEnd   = $to->min($end);
                if ($overlapStart <= $overlapEnd) {
                    $overlapDays = $overlapStart->diffInDays($overlapEnd) + 1;
                }
            }

            if ($loai === DonTuNghiPhep::LOAI_NGHI_PHEP) {
                $npNgay += $overlapDays;
                $npGio  += $soGio;
            } elseif ($loai === DonTuNghiPhep::LOAI_KHONG_LUONG) {
                $klNgay += $overlapDays;
                $klGio  += $soGio;
            }
        }

        return [$npNgay, $npGio, $klNgay, $klGio];
    }
}
