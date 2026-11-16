@extends('layouts.app')
@use('Illuminate\Support\Js')
@use('App\Support\Format')
@use('App\Support\StatusBadge')
@use('App\Enums\TransactionStatus')

@php
    $pageIds = $inbounds->getCollection()->pluck('id')->values();
@endphp

@section('content')
    <x-common.page-breadcrumb pageTitle="Barang Masuk" />

    <div x-data="listPage()">
        <x-common.table-card title="Daftar Barang Masuk" :paginator="$inbounds">
            <x-slot:actions>
                <x-ui.button as="a" href="{{ route('inbounds.create') }}">
                    <x-svg.plus class="size-5" />
                    Tambah Barang Masuk
                </x-ui.button>
            </x-slot:actions>

            <x-slot:toolbar>
                @include('pages.inventory.partials.bulk-bar', ['actions' => [
                    ['label' => 'Setujui Terpilih', 'variant' => 'outline', 'confirm' => [
                        'title' => 'Setujui Terpilih', 'message' => 'Apakah Anda yakin ingin menyetujui transaksi terpilih?',
                        'action' => route('inbounds.bulk-approve'), 'method' => 'POST', 'confirmLabel' => 'Setujui', 'variant' => 'success',
                    ]],
                    ['label' => 'Hapus', 'variant' => 'destructive', 'confirm' => [
                        'title' => 'Hapus Transaksi Terpilih', 'message' => 'Apakah Anda yakin ingin menghapus transaksi yang dipilih?',
                        'action' => route('inbounds.bulk-destroy'),
                    ]],
                ]])
            </x-slot:toolbar>

            <thead class="border-y border-gray-100 dark:border-gray-800">
                <tr>
                    <th class="table-th w-12" x-data="{ ids: @js($pageIds) }">
                        <x-ui.checkbox aria-label="Pilih semua" x-bind:checked="ids.length && ids.every(id => isSelected(id))" x-on:change="toggleAll(ids)" />
                    </th>
                    <th class="table-th min-w-44">Kode Transaksi</th>
                    <th class="table-th min-w-40">Tanggal</th>
                    <th class="table-th min-w-44">Supplier</th>
                    <th class="table-th min-w-36">Total Nominal</th>
                    <th class="table-th min-w-28">Status</th>
                    <th class="table-th w-16"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($inbounds as $inbound)
                    @php
                        [$statusLabel, $statusColor] = StatusBadge::for('inbound', $inbound->status);
                        $viewCall = 'view('.Js::from(route('inbounds.show', $inbound)).', '.Js::from('Barang Masuk '.$inbound->transaction_code).')';
                    @endphp
                    <tr class="table-row cursor-pointer" x-on:click="if (! $event.target.closest('button, a, [role=checkbox]')) {{ $viewCall }}">
                        <td class="table-td">
                            <x-ui.checkbox aria-label="Pilih {{ $inbound->transaction_code }}" x-bind:checked="isSelected({{ $inbound->id }})" x-on:change="toggle({{ $inbound->id }})" />
                        </td>
                        <td class="table-td font-medium whitespace-nowrap text-gray-800 dark:text-white/90">
                            @include('pages.inventory.partials.copyable', ['text' => $inbound->transaction_code])
                        </td>
                        <td class="table-td whitespace-nowrap">{{ Format::date($inbound->date) }}</td>
                        <td class="table-td">{{ $inbound->supplier?->name ?? '-' }}</td>
                        <td class="table-td whitespace-nowrap">{{ Format::rupiah($inbound->total_amount) }}</td>
                        <td class="table-td"><x-ui.badge size="sm" :color="$statusColor">{{ $statusLabel }}</x-ui.badge></td>
                        <td class="table-td text-end">
                            <x-common.row-actions>
                                <x-ui.dropdown-menu.item x-on:click="{{ $viewCall }}">
                                    <x-svg.eye /> Lihat
                                </x-ui.dropdown-menu.item>
                                @if ($inbound->status === TransactionStatus::PENDING)
                                    <x-ui.dropdown-menu.item to="{{ route('inbounds.edit', $inbound) }}">
                                        <x-svg.pencil /> Ubah
                                    </x-ui.dropdown-menu.item>
                                    <x-ui.dropdown-menu.item x-on:click="confirm({ title: 'Setujui Barang Masuk', message: 'Apakah Anda yakin ingin menyetujui transaksi ini? Stok barang akan bertambah secara otomatis.', action: {{ Js::from(route('inbounds.approve', $inbound)) }}, method: 'POST', confirmLabel: 'Setujui', variant: 'success' })">
                                        <x-svg.check-circle /> Setujui
                                    </x-ui.dropdown-menu.item>
                                @endif
                                @if ($inbound->status === TransactionStatus::RECEIVED && ! $inbound->voided_at)
                                    <x-ui.dropdown-menu.item variant="destructive" x-on:click="confirm({ title: 'Batalkan Transaksi', message: 'Apakah Anda yakin ingin membatalkan transaksi ini?', action: {{ Js::from(route('inbounds.void', $inbound)) }}, method: 'POST', confirmLabel: 'Batalkan', reason: { name: 'void_reason', label: 'Alasan Pembatalan' } })">
                                        <x-svg.x /> Batalkan
                                    </x-ui.dropdown-menu.item>
                                @endif
                            </x-common.row-actions>
                        </td>
                    </tr>
                @empty
                    <x-common.table-empty colspan="7" title="Belum ada barang masuk" />
                @endforelse
            </tbody>
        </x-common.table-card>

        @include('pages.inventory.partials.detail-panel')
    </div>
@endsection
