<?php

namespace App\Data\Boundary;

class Money
{
    public static function cents(string|int $amount): int
    {
        $value = (string) $amount;
        $negative = str_starts_with($value, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($value, '-'), 2), 2, '0');

        return ((int) $whole * 100 + (int) str_pad($fraction, 2, '0')) * ($negative ? -1 : 1);
    }

    public static function decimal(int $cents): string
    {
        return ($cents < 0 ? '-' : '').intdiv(abs($cents), 100).'.'.str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }
}
