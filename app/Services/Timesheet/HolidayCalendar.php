<?php

namespace App\Services\Timesheet;

use Carbon\Carbon;

class HolidayCalendar
{
    public function weekendEnabled(): bool
    {
        return (bool) config('timesheet.calendar.weekend.enabled', false);
    }

    /** @return int[] 1=Mon .. 7=Sun */
    public function weekendDays(): array
    {
        $raw = config('timesheet.calendar.weekend.days', ['6','7']);
        return array_values(array_map('intval', is_array($raw) ? $raw : explode(',', (string) $raw)));
    }

    public function weekendExcludeFromWorkedDays(): bool
    {
        return (bool) config('timesheet.calendar.weekend.exclude_from_worked_days', true);
    }

    public function holidayEnabled(): bool
    {
        return (bool) config('timesheet.calendar.holiday.enabled', false);
    }

    /** @return string[] list YYYY-MM-DD */
    public function holidays(): array
    {
        $raw = config('timesheet.calendar.holiday.list', []);
        return is_array($raw) ? $raw : array_filter(array_map('trim', explode(',', (string) $raw)));
    }

    public function holidayExcludeFromWorkedDays(): bool
    {
        return (bool) config('timesheet.calendar.holiday.exclude_from_worked_days', true);
    }

    public function isWeekend(Carbon $day): bool
    {
        if (!$this->weekendEnabled()) return false;
        return in_array((int) $day->dayOfWeekIso, $this->weekendDays(), true);
    }

    public function isHoliday(Carbon $day): bool
    {
        if (!$this->holidayEnabled()) return false;
        return in_array($day->toDateString(), $this->holidays(), true);
    }
}
