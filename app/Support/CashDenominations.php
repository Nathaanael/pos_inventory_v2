<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Pecahan Rupiah untuk menghitung uang fisik di laci, dan validasi input hitung kas
 * yang dipakai bersama oleh Tutup Shift dan Hitung Kas.
 *
 * Input form:
 *   count_mode      "pecahan" (jumlah lembar/keping per pecahan) atau "total" (nominal langsung)
 *   denominations[] [nilai pecahan => jumlah], dipakai bila count_mode = pecahan
 *   actual_cash     nominal total, dipakai bila count_mode = total (default bila count_mode kosong)
 */
class CashDenominations
{
    public const NOTES = [100000, 50000, 20000, 10000, 5000, 2000, 1000];

    public const COINS = [500, 200, 100];

    /** @return array<int, int> */
    public static function all(): array
    {
        return [...self::NOTES, ...self::COINS];
    }

    /**
     * Ambil pecahan yang dikenal dengan jumlah > 0.
     *
     * @return array<string, int>
     */
    public static function normalize(array $input): array
    {
        $result = [];
        foreach (self::all() as $value) {
            $qty = (int) ($input[$value] ?? $input[(string) $value] ?? 0);
            if ($qty > 0) {
                $result[(string) $value] = $qty;
            }
        }

        return $result;
    }

    /** @param  array<string|int, int>  $denominations */
    public static function total(array $denominations): float
    {
        return (float) collect($denominations)->reduce(fn ($sum, $qty, $value) => $sum + ((int) $value * (int) $qty), 0);
    }

    /**
     * Validasi input hitung kas.
     *
     * @return array{actual_cash: float, denominations: array<string, int>|null, notes: string|null}
     */
    public static function validate(Request $request): array
    {
        $byDenomination = $request->input('count_mode') === 'pecahan';

        $rules = [
            'count_mode' => ['nullable', 'in:pecahan,total'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];

        if ($byDenomination) {
            $rules['denominations'] = ['required', 'array'];
            foreach (self::all() as $value) {
                $rules["denominations.{$value}"] = ['nullable', 'integer', 'min:0', 'max:100000'];
            }
        } else {
            $rules['actual_cash'] = ['required', 'numeric', 'min:0'];
        }

        $data = $request->validate($rules, [
            'denominations.required' => 'Isi jumlah lembar/keping uang.',
        ], [
            'actual_cash' => 'Uang Fisik di Laci',
            'denominations.*' => 'Jumlah pecahan',
        ]);

        $denominations = $byDenomination ? self::normalize($data['denominations'] ?? []) : null;

        return [
            'actual_cash' => $byDenomination ? self::total($denominations) : (float) $data['actual_cash'],
            'denominations' => $denominations,
            'notes' => filled($data['notes'] ?? null) ? $data['notes'] : null,
        ];
    }

    /** "Rp100.000" / "Rp500" */
    public static function label(int|string $value): string
    {
        return 'Rp'.number_format((int) $value, 0, ',', '.');
    }
}
