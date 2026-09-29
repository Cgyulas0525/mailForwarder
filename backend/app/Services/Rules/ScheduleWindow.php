<?php

namespace App\Services\Rules;

use App\Models\AppSetting;
use Carbon\Carbon;

class ScheduleWindow
{
    public function isOpen(?Carbon $now = null): bool
    {
        $now = ($now ?? now())->copy()->timezone('Europe/Budapest');
        $start = AppSetting::getValue('check_window_start', config('forwarding.window_start'));
        $end = AppSetting::getValue('check_window_end', config('forwarding.window_end'));

        if (! $this->isTime($start) || ! $this->isTime($end)) {
            return true;
        }

        $startAt = $now->copy()->setTimeFromTimeString($start);
        $endAt = $now->copy()->setTimeFromTimeString($end);

        if ($endAt->lessThanOrEqualTo($startAt)) {
            return $now->greaterThanOrEqualTo($startAt) || $now->lessThan($endAt);
        }

        return $now->greaterThanOrEqualTo($startAt) && $now->lessThan($endAt);
    }

    private function isTime(?string $value): bool
    {
        return is_string($value) && preg_match('/^\d{2}:\d{2}$/', $value) === 1;
    }
}
