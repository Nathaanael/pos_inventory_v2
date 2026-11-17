<?php

namespace App\Support;

use BackedEnum;

/**
 * Label & warna badge status transaksi (sama seperti formatStateUsing/color di tabel Filament lama).
 * Warna mengikuti <x-ui.badge>: success | warning | error | info | gray | primary.
 */
class StatusBadge
{
    private const MAP = [
        'inbound' => [
            'diterima' => ['Diterima', 'success'],
            'pending' => ['Pending', 'warning'],
            'voided' => ['Void', 'error'],
        ],
        'outbound' => [
            'selesai' => ['Selesai', 'success'],
            'pending' => ['Pending', 'warning'],
            'voided' => ['Void', 'error'],
            'dibatalkan' => ['Dibatalkan', 'error'],
        ],
        'return' => [
            'selesai' => ['Disetujui', 'success'],
            'pending' => ['Pending', 'warning'],
            'ditolak' => ['Ditolak', 'error'],
        ],
        'opname' => [
            'selesai' => ['Selesai', 'success'],
            'draft' => ['Draft', 'warning'],
        ],
        'cash_count' => [
            'cocok' => ['Cocok', 'success'],
            'perlu_review' => ['Perlu Review', 'warning'],
            'disetujui' => ['Selisih Disetujui', 'info'],
        ],
    ];

    /** @return array{0: string, 1: string} [label, warna] */
    public static function for(string $type, BackedEnum|string|null $status): array
    {
        $value = $status instanceof BackedEnum ? $status->value : (string) $status;

        return self::MAP[$type][$value] ?? [ucfirst($value), 'gray'];
    }

    public static function value(BackedEnum|string|null $status): string
    {
        return $status instanceof BackedEnum ? (string) $status->value : (string) $status;
    }
}
