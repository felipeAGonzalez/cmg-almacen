<?php

namespace App\Services;

use App\Models\OperationalSetting;
use Carbon\CarbonInterface;

class OperationalScheduleService
{
    public function setting(): OperationalSetting
    {
        return OperationalSetting::current();
    }

    public function startTime(): string
    {
        return substr($this->setting()->warehouse_service_start_time, 0, 5);
    }

    public function endTime(): string
    {
        return substr($this->setting()->warehouse_service_end_time, 0, 5);
    }

    public function crossesMidnight(): bool
    {
        return $this->timeInSeconds($this->startTime()) > $this->timeInSeconds($this->endTime());
    }

    public function isWarehouseServiceOpen(CarbonInterface $dateTime): bool
    {
        $localDateTime = $dateTime->copy()->setTimezone(config('app.timezone'));

        if ($localDateTime->dayOfWeekIso === $this->setting()->warehouse_rest_day) {
            return false;
        }
        $current = $this->timeInSeconds($localDateTime->format('H:i:s'));
        $start = $this->timeInSeconds($this->startTime());
        $end = $this->timeInSeconds($this->endTime());

        if ($start < $end) {
            return $current >= $start && $current < $end;
        }

        return $current >= $start || $current < $end;
    }

    public function isWarehouseAvailableAt(CarbonInterface $dateTime): bool
    {
        return $this->isWarehouseServiceOpen($dateTime);
    }

    private function timeInSeconds(string $time): int
    {
        [$hours, $minutes, $seconds] = array_pad(array_map('intval', explode(':', $time)), 3, 0);

        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }
}
