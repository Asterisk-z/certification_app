<?php

namespace App\Enums;

use Carbon\Carbon;

enum DurationType: string
{
    case Day = 'day';
    case Month = 'month';
    case Year = 'year';

    public function addTo(Carbon $date, int $amount): Carbon
    {
        return match ($this) {
            self::Day => $date->copy()->addDays($amount),
            self::Month => $date->copy()->addMonths($amount),
            self::Year => $date->copy()->addYears($amount),
        };
    }
}
