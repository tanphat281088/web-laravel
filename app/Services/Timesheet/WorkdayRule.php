<?php

namespace App\Services\Timesheet;

use Carbon\Carbon;

class WorkdayRule
{
    public function enabled(): bool
    {
        return (bool) config('timesheet.enabled', false);
    }

    public function start(): string      { return (string) config('timesheet.workday.start', '08:30'); }
    public function end(): string        { return (string) config('timesheet.workday.end',   '17:30'); }
    public function breakStart(): string { return (string) config('timesheet.workday.break.start', '12:00'); }
    public function breakEnd(): string   { return (string) config('timesheet.workday.break.end',   '13:30'); }
    public function grace(): int         { return (int) config('timesheet.workday.grace_minutes', 5); }

    public function dayStart(Carbon $day): Carbon
    {
        [$h, $m] = explode(':', $this->start());
        return $day->copy()->setTime((int)$h, (int)$m, 0);
    }

    public function dayEnd(Carbon $day): Carbon
    {
        [$h, $m] = explode(':', $this->end());
        return $day->copy()->setTime((int)$h, (int)$m, 0);
    }

    /** Khoảng nghỉ trưa trong ngày [start,end] */
    public function breakPeriod(Carbon $day): array
    {
        [$h1, $m1] = explode(':', $this->breakStart());
        [$h2, $m2] = explode(':', $this->breakEnd());
        $b1 = $day->copy()->setTime((int)$h1, (int)$m1, 0);
        $b2 = $day->copy()->setTime((int)$h2, (int)$m2, 0);
        return [$b1, $b2];
    }
}
