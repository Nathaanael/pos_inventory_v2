<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/** Format tampilan yang dipakai berulang di tabel & detail. */
class Format
{
    public static function rupiah(float|int|string|null $value): string
    {
        return 'Rp '.number_format((float) $value, 0, ',', '.');
    }

    public static function number(float|int|string|null $value): string
    {
        return number_format((float) $value, 0, ',', '.');
    }

    /** "16 November 2026" */
    public static function date(CarbonInterface|string|null $date, string $format = 'd F Y'): string
    {
        if (! $date) {
            return '-';
        }

        return Carbon::parse($date)->locale('id')->translatedFormat($format);
    }

    /** Jumlah stok, desimal hanya bila ada (maks. 4 digit): "1.250", "1,25", "0,0005". */
    public static function qty(float|int|string|null $value): string
    {
        $formatted = number_format(round((float) $value, 4), 4, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }

    /** Jumlah stok dengan tanda: "+1,5", "-2", "0" */
    public static function signedQty(float|int|string|null $value): string
    {
        $value = round((float) $value, 4);

        return ($value > 0 ? '+' : '').static::qty($value);
    }

    /** Angka dengan tanda: "+3", "-2", "0" */
    public static function signed(int|float|null $value): string
    {
        $value = (int) $value;

        return $value > 0 ? "+{$value}" : (string) $value;
    }
}
