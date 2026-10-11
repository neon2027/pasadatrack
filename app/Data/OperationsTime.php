<?php

namespace App\Data;

use Carbon\CarbonImmutable;

class OperationsTime
{
    public const TIMEZONE = 'Asia/Manila';

    public static function utc(string $localTime): CarbonImmutable
    {
        return CarbonImmutable::parse($localTime, self::TIMEZONE)->utc();
    }

    public static function local(?CarbonImmutable $time): ?string
    {
        return $time?->setTimezone(self::TIMEZONE)->format('Y-m-d\TH:i');
    }
}
