{{-- Isi modal "Histori Aktivitas" (10 pergerakan stok terakhir). Dimuat lewat fetch dari ItemController@history. --}}
@php
    $fmtQty = fn ($value) => rtrim(rtrim(number_format((float) $value, 4, ',', '.'), '0'), ',');
    $types = [
        'inbound' => ['Inbound', 'success'],
        'return_in' => ['Return In', 'success'],
        'outbound' => ['Outbound', 'warning'],
        'return_out' => ['Retur', 'error'],
        'void' => ['Void', 'error'],
        'waste' => ['Waste', 'gray'],
        'adjustment' => ['Adjustment', 'info'],
    ];
@endphp
<div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
    <div class="custom-scrollbar overflow-x-auto">
        <table class="min-w-full">
            <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/3">
                <tr>
                    <th class="table-th">Tanggal</th>
                    <th class="table-th">Tipe</th>
                    <th class="table-th">Catatan</th>
                    <th class="table-th text-end">Qty</th>
                    <th class="table-th text-end">Sisa Stok</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($movements as $movement)
                    @php [$label, $color] = $types[$movement->type] ?? [ucfirst($movement->type), 'gray']; @endphp
                    <tr>
                        <td class="table-td whitespace-nowrap">{{ $movement->created_at->format('d M Y H:i') }}</td>
                        <td class="table-td"><x-ui.badge size="sm" :color="$color">{{ $label }}</x-ui.badge></td>
                        <td class="table-td">{{ $movement->notes ?: '-' }}</td>
                        <td @class(['table-td text-end font-semibold', 'text-success-600 dark:text-success-500' => $movement->quantity > 0, 'text-error-600 dark:text-error-500' => $movement->quantity <= 0])>
                            {{ $movement->quantity > 0 ? '+' : '' }}{{ $fmtQty($movement->quantity) }}
                        </td>
                        <td class="table-td text-end font-semibold text-gray-800 dark:text-white/90">{{ $fmtQty($movement->stock_after) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada riwayat pergerakan stok.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@if ($movements->count() > 0)
    <p class="mt-4 text-center text-xs text-gray-500 dark:text-gray-400">Menampilkan {{ $movements->count() }} riwayat terakhir (Maksimal 10).</p>
@endif
