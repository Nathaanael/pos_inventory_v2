@extends('layouts.app')
@use('Illuminate\Support\Js')
@use('App\Support\Format')
@use('App\Support\StatusBadge')
@use('App\Enums\OrderType')
@use('App\Enums\TransactionStatus')

@php
    $pageIds = $outbounds->getCollection()->pluck('id')->values();
    $colspan = $showOrderType ? 10 : 9;
@endphp

@section('content')
    <x-common.page-breadcrumb pageTitle="Barang Keluar" />

    <div x-data="listPage()">
        <x-common.table-card title="Daftar Barang Keluar" :paginator="$outbounds">
            <x-slot:actions>
                <x-ui.button as="a" href="{{ route('outbounds.create') }}">
                    <x-svg.plus class="size-5" />
                    Tambah Barang Keluar
                </x-ui.button>
            </x-slot:actions>

            <x-slot:toolbar>
                @include('pages.inventory.partials.bulk-bar', ['actions' => [
                    ['label' => 'Hapus', 'variant' => 'destructive', 'confirm' => [
                        'title' => 'Hapus Transaksi Terpilih', 'message' => 'Apakah Anda yakin ingin menghapus transaksi yang dipilih?',
                        'action' => route('outbounds.bulk-destroy'),
                    ]],
                ]])
            </x-slot:toolbar>

            <thead class="border-y border-gray-100 dark:border-gray-800">
                <tr>
                    <th class="table-th w-12" x-data="{ ids: @js($pageIds) }">
                        <x-ui.checkbox aria-label="Pilih semua" x-bind:checked="ids.length && ids.every(id => isSelected(id))" x-on:change="toggleAll(ids)" />
                    </th>
                    <th class="table-th min-w-44">Kode Transaksi</th>
                    <th class="table-th min-w-32">Tanggal</th>
                    <th class="table-th min-w-40">Nama Pelanggan</th>
                    @if ($showOrderType)
                        <th class="table-th min-w-28">Pesanan</th>
                    @endif
                    <th class="table-th min-w-24">Tipe Harga</th>
                    <th class="table-th min-w-24">Status</th>
                    <th class="table-th min-w-32">Total Nominal</th>
                    <th class="table-th min-w-32">Total HPP</th>
                    <th class="table-th w-16"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($outbounds as $outbound)
                    @php
                        [$statusLabel, $statusColor] = StatusBadge::for('outbound', $outbound->status);
                        $viewCall = 'view('.Js::from(route('outbounds.show', $outbound)).', '.Js::from('Barang Keluar '.$outbound->transaction_code).')';
                        $orderLabel = $outbound->orderLabel();
                    @endphp
                    <tr class="table-row cursor-pointer" x-on:click="if (! $event.target.closest('button, a, [role=checkbox]')) {{ $viewCall }}">
                        <td class="table-td">
                            <x-ui.checkbox aria-label="Pilih {{ $outbound->transaction_code }}" x-bind:checked="isSelected({{ $outbound->id }})" x-on:change="toggle({{ $outbound->id }})" />
                        </td>
                        <td class="table-td font-medium whitespace-nowrap text-gray-800 dark:text-white/90">{{ $outbound->transaction_code }}</td>
                        <td class="table-td whitespace-nowrap">{{ Format::date($outbound->date, 'd M Y') }}</td>
                        <td class="table-td">{{ $outbound->customer_name ?: '-' }}</td>
                        @if ($showOrderType)
                            <td class="table-td whitespace-nowrap">
                                @if ($orderLabel)
                                    <x-ui.badge size="sm" :color="$outbound->order_type === OrderType::TakeAway ? 'info' : 'warning'">{{ $orderLabel }}</x-ui.badge>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                        @endif
                        <td class="table-td"><x-ui.badge size="sm" color="gray">{{ $outbound->price_type_applied }}</x-ui.badge></td>
                        <td class="table-td"><x-ui.badge size="sm" :color="$statusColor">{{ $statusLabel }}</x-ui.badge></td>
                        <td class="table-td whitespace-nowrap">{{ Format::rupiah($outbound->total_amount) }}</td>
                        <td class="table-td whitespace-nowrap">{{ Format::rupiah($outbound->total_cogs) }}</td>
                        <td class="table-td text-end">
                            <x-common.row-actions>
                                <x-ui.dropdown-menu.item x-on:click="{{ $viewCall }}">
                                    <x-svg.eye /> Lihat
                                </x-ui.dropdown-menu.item>
                                @if ($outbound->status === TransactionStatus::COMPLETED)
                                    <x-ui.dropdown-menu.item x-on:click="confirm({ title: 'Cetak Nota', message: 'Cetak nota langsung ke printer thermal?', action: {{ Js::from(route('outbounds.print-thermal', $outbound)) }}, method: 'POST', confirmLabel: 'Cetak', variant: 'default' })">
                                        <x-svg.page /> Cetak Nota
                                    </x-ui.dropdown-menu.item>
                                @endif
                                @if (in_array($outbound->status, [TransactionStatus::COMPLETED, TransactionStatus::PENDING], true))
                                    <x-ui.dropdown-menu.item variant="destructive" x-on:click="confirm({ title: 'Batalkan Transaksi', message: 'Apakah Anda yakin ingin membatalkan transaksi ini? Stok barang akan dikembalikan.', action: {{ Js::from(route('outbounds.void', $outbound)) }}, method: 'POST', confirmLabel: 'Batalkan', reason: { name: 'void_reason', label: 'Alasan Pembatalan' } })">
                                        <x-svg.x /> Batalkan
                                    </x-ui.dropdown-menu.item>
                                @endif
                            </x-common.row-actions>
                        </td>
                    </tr>
                @empty
                    <x-common.table-empty :colspan="$colspan" title="Belum ada barang keluar" />
                @endforelse
            </tbody>
        </x-common.table-card>

        @include('pages.inventory.partials.detail-panel')
    </div>
@endsection
