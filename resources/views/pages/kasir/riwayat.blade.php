@extends('layouts.kasir')
@use('Illuminate\Support\Js')
@use('App\Support\Format')
@use('App\Enums\OrderType')
@use('App\Enums\TransactionStatus')

{{-- Riwayat transaksi milik kasir yang sedang login (dulu App\Filament\Kasir\Pages\RiwayatTransaksi). --}}
@php
    $statuses = [
        'pending' => ['Menunggu Persetujuan', 'warning'],
        'selesai' => ['Selesai', 'success'],
        'voided' => ['Dibatalkan', 'error'],
    ];
    $colspan = $showOrderType ? 8 : 7;
@endphp

@section('content')
    <div class="mb-6">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">Riwayat Transaksi Saya</h2>
    </div>

    <div x-data="listPage()">
        <x-common.table-card title="Transaksi" :paginator="$orders">
            <x-slot:filters>
                <label class="flex h-10 shrink-0 cursor-pointer items-center gap-2 rounded-[10px] border border-gray-300 px-3 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-300">
                    <input type="hidden" name="hari_ini" value="0">
                    <input type="checkbox" name="hari_ini" value="1" @checked($todayOnly) x-data x-on:change="$el.form.requestSubmit()"
                        class="size-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900" />
                    Hanya hari ini
                </label>
            </x-slot:filters>

            <thead class="border-y border-gray-100 dark:border-gray-800">
                <tr>
                    <th class="table-th min-w-44">Kode</th>
                    <th class="table-th min-w-36">Waktu</th>
                    <th class="table-th min-w-40">Pelanggan</th>
                    @if ($showOrderType)
                        <th class="table-th min-w-28">Pesanan</th>
                    @endif
                    <th class="table-th min-w-28">Jenis Barang</th>
                    <th class="table-th min-w-32">Total</th>
                    <th class="table-th min-w-40">Status</th>
                    <th class="table-th w-16"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($orders as $order)
                    @php
                        [$statusLabel, $statusColor] = $statuses[$order->status->value] ?? [ucfirst($order->status->value), 'gray'];
                        $orderLabel = $order->orderLabel();
                        $isCompleted = $order->status === TransactionStatus::COMPLETED;
                    @endphp
                    <tr class="table-row">
                        <td class="table-td font-medium whitespace-nowrap text-gray-800 dark:text-white/90">{{ $order->transaction_code }}</td>
                        <td class="table-td whitespace-nowrap">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                        <td class="table-td">{{ $order->customer_name ?: '-' }}</td>
                        @if ($showOrderType)
                            <td class="table-td whitespace-nowrap">
                                @if ($orderLabel)
                                    <x-ui.badge size="sm" :color="$order->order_type === OrderType::TakeAway ? 'info' : 'warning'">{{ $orderLabel }}</x-ui.badge>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                        @endif
                        <td class="table-td">{{ $order->items_count }}</td>
                        <td class="table-td whitespace-nowrap">{{ Format::rupiah($order->payableTotal()) }}</td>
                        <td class="table-td"><x-ui.badge size="sm" :color="$statusColor">{{ $statusLabel }}</x-ui.badge></td>
                        <td class="table-td text-end">
                            @if ($isCompleted)
                                <x-common.row-actions>
                                    <x-ui.dropdown-menu.item x-on:click="confirm({ title: 'Cetak Nota', message: 'Cetak nota langsung ke printer thermal?', action: {{ Js::from(route('kasir.print-thermal', $order)) }}, method: 'POST', confirmLabel: 'Cetak', variant: 'default' })">
                                        <x-svg.page /> Cetak Nota
                                    </x-ui.dropdown-menu.item>
                                    <x-ui.dropdown-menu.item to="{{ route('outbound.print', $order) }}" target="_blank">
                                        <x-svg.eye /> Lihat Nota
                                    </x-ui.dropdown-menu.item>
                                </x-common.row-actions>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-common.table-empty :colspan="$colspan" title="Belum ada transaksi"
                        description="Transaksi yang Anda simpan di halaman Kasir akan muncul di sini." />
                @endforelse
            </tbody>
        </x-common.table-card>
    </div>
@endsection
