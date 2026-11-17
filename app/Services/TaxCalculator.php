<?php

namespace App\Services;

use App\Models\StoreSetting;
use App\Models\Tax;

/**
 * Hitung Pajak & Biaya Layanan sebuah transaksi (modul FNB).
 * Rumus yang SAMA ada di resources/js/components/kasir/tax-calculator.js untuk pratinjau di layar;
 * hasil server inilah yang disimpan.
 *
 * Aturan:
 *  1. Biaya layanan (type=service) dihitung dari subtotal seluruh barang.
 *  2. Pajak (type=tax) dihitung per barang dari (subtotal barang + porsi biaya layanan exclusive-nya).
 *     Barang dengan Pajak (%) sendiri memakai tarif itu dan mengabaikan pajak master.
 *  3. Exclusive  → ditambahkan ke tagihan.
 *     Inclusive  → sudah termasuk di harga; hanya dicatat sebagai keterangan (tidak menambah tagihan).
 *  4. applies_to: all = semua pesanan; dine_in/take_away hanya untuk tipe pesanan tsb.
 */
class TaxCalculator
{
    /**
     * @param  array<int, array{subtotal: float, tax_rate?: ?float, tax_method?: ?string}>  $lines
     * @param  array<int, array<string, mixed>>|null  $rules  aturan master (default: dari database)
     * @return array{subtotal: float, service_amount: float, tax_amount: float, grand_total: float, details: array<int, array<string, mixed>>}
     */
    public function calculate(array $lines, ?string $orderType = null, ?array $rules = null, float $discountAmount = 0.0): array
    {
        $subtotal = (float) collect($lines)->sum('subtotal');
        $discount = min($subtotal, max(0.0, $discountAmount));
        $netSubtotal = max(0.0, $subtotal - $discount);
        $empty = [
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'net_subtotal' => $netSubtotal,
            'service_amount' => 0.0,
            'tax_amount' => 0.0,
            'grand_total' => $netSubtotal,
            'details' => [],
        ];

        if (! StoreSetting::usesTaxes() || $netSubtotal <= 0) {
            return $empty;
        }

        $ratio = $subtotal > 0 ? ($netSubtotal / $subtotal) : 0.0;

        $rules ??= Tax::active()->get()->map->toRule()->all();
        $applicable = array_values(array_filter($rules, fn ($r) => $r['applies_to'] === 'all' || ($orderType && $r['applies_to'] === $orderType)));
        $services = array_values(array_filter($applicable, fn ($r) => $r['type'] === 'service'));
        $taxes = array_values(array_filter($applicable, fn ($r) => $r['type'] === 'tax'));

        $details = [];
        $add = function (string $key, array $rule, float $amount) use (&$details) {
            $details[$key] ??= [
                'name' => $rule['name'],
                'type' => $rule['type'],
                'rate' => (float) $rule['rate'],
                'method' => $rule['method'],
                'included' => $rule['method'] === 'inclusive',
                'amount' => 0.0,
            ];
            $details[$key]['amount'] += $amount;
        };

        // 1. Biaya layanan dari net subtotal (setelah diskon).
        $exclusiveServiceRate = 0.0;
        foreach ($services as $rule) {
            $r = $rule['rate'] / 100;
            if ($rule['method'] === 'exclusive') {
                $add('s'.$rule['id'], $rule, $netSubtotal * $r);
                $exclusiveServiceRate += $r;
            } else {
                $add('s'.$rule['id'], $rule, $netSubtotal - $netSubtotal / (1 + $r));
            }
        }

        // 2. Pajak per barang (override barang mengalahkan master).
        foreach ($lines as $line) {
            $base = ((float) $line['subtotal'] * $ratio) * (1 + $exclusiveServiceRate);
            $itemRate = $line['tax_rate'] ?? null;

            $lineTaxes = $itemRate !== null && $itemRate !== ''
                ? [[
                    'id' => 'item-'.$itemRate.'-'.($line['tax_method'] ?? 'exclusive'),
                    'name' => 'Pajak Barang', // persentase ditampilkan terpisah (rate)
                    'type' => 'tax',
                    'rate' => (float) $itemRate,
                    'method' => ($line['tax_method'] ?? null) ?: 'exclusive',
                ]]
                : $taxes;

            foreach ($lineTaxes as $rule) {
                if ($rule['rate'] <= 0) {
                    continue;
                }
                $r = $rule['rate'] / 100;
                $amount = $rule['method'] === 'exclusive' ? $base * $r : $base - $base / (1 + $r);
                $add('t'.$rule['id'], $rule, $amount);
            }
        }

        $details = array_values(array_map(fn ($d) => [...$d, 'amount' => round($d['amount'])], $details));
        $details = array_values(array_filter($details, fn ($d) => $d['amount'] > 0));

        $service = (float) collect($details)->where('type', 'service')->where('included', false)->sum('amount');
        $tax = (float) collect($details)->where('type', 'tax')->where('included', false)->sum('amount');

        return [
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'net_subtotal' => $netSubtotal,
            'service_amount' => $service,
            'tax_amount' => $tax,
            'grand_total' => $netSubtotal + $service + $tax,
            'details' => $details,
        ];
    }

    /** Aturan aktif untuk dikirim ke browser (kosong bila modul tidak memakai pajak). */
    public function rules(): array
    {
        return StoreSetting::usesTaxes() ? Tax::active()->get()->map->toRule()->all() : [];
    }
}
