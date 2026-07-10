<?php

namespace App\Support;

use Carbon\Carbon;

class FarmFormat
{
    public static function money($value): string
    {
        return 'R$ ' . number_format((float)$value, 2, ',', '.');
    }

    public static function decimal($value, int $places = 2): string
    {
        $formatted = number_format((float)$value, $places, ',', '.');
        return rtrim(rtrim($formatted, '0'), ',');
    }

    public static function date($value): string
    {
        if (!$value) {
            return '-';
        }

        return Carbon::parse($value)->format('d/m/Y');
    }

    public static function bool($value): string
    {
        return (int)$value === 1 ? 'Sim' : 'Não';
    }

    public static function value($value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return (string)$value;
    }
}
