@extends('layouts.app')
@use('Illuminate\Support\Js')
@use('App\Support\Format')
@use('App\Support\StatusBadge')

@php
    $pageIds = $returns->getCollection()->pluck('id')->values();
@endphp

@section('content')
    <x-common.page-breadcrumb pageTitle="Transaksi Retur" />

    <div x-data="listPage()">
        <x-common.table-card title="Daftar Transaksi Retur" :paginator="$returns">
            <x-slot:filters>
                <x-common.form.select name="status" class="h-10 sm:w-40" :value="request('status')" placeholder="Semua status"
                    :options="['pending' => 'Pending', 'selesai' => 'Disetujui', 'ditolak' => 'Ditolak']"
                    x-data x-on:change="$el.form.requestSubmit()" aria-label="Filter status" />
            </x-slot:filters>

            <x-slot:actions>
                <x-ui.button as="a" href="{{ route('return-transactions.create') }}">
                    <x-svg.plus class="size-5" />
                    Tambah Retur
                </x-ui.button>
            </x-slot:actions>

            <x-slot:toolbar>
                @include('pages.inventory.partials.bulk-bar', ['actions' => [
                    ['label' => 'Setujui Terpilih', 'variant' => 'outline', 'confirm' => [
                        'title' => 'Setujui Retur Terpilih', 'message' => 'Apakah Anda yakin ingin menyetujui semua retur yang dipilih?',
                        'action' => route('return-transactions.bulk-approve'), 'method' => 'POST', 'confirmLabel' => 'Setujui', 'variant' => 'success',
                    ]],
                    ['label' => 'Tolak Terpilih', 'variant' => 'destructive', 'confirm' => [
                        'title' => 'Tolak Retur Terpilih', 'message' => 'Apakah Anda yakin ingin menolak semua retur yang dipilih?',
                        'action' => route('return-transactions.bulk-reject'), 'method' => 'POST', 'confirmLabel' => 'Tolak',
                    ]],
                ]])
            </x-slot:toolbar>

            <thead class="border-y border-gray-100 dark:border-gray-800">
                <tr>
                    <th class="table-th w-12" x-data="{ ids: @js($pageIds) }">
                        <x-ui.checkbox aria-label="Pilih semua" x-bind:checked="ids.length && ids.every(id => isSelected(id))" x-on:change="toggleAll(ids)" />
                    </th>
                    <th class="table-th min-w-44">Kode Retur</th>
                    <th class="table-th min-w-40">Tanggal</th>
                    <th class="table-th min-w-32">Tipe</th>
                    <th class="table-th min-w-44">Supplier / Customer</th>
                    <th class="table-th min-w-28">Status</th>
                    <th class="table-th min-w-36">Total Harga Retur</th>
                    <th class="table-th w-16"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($returns as $return)
                    @php
                        [$statusLabel, $statusColor] = StatusBadge::for('return', $return->status);
                        $isPending = StatusBadge::value($return->status) === 'pending';
                        $isSupplier = $return->type === 'supplier';
                        $viewCall = 'view('.Js::from(route('return-transactions.show', $return)).', '.Js::from('Retur '.$return->return_code).')';
                    @endphp
                    <tr class="table-row cursor-pointer" x-on:click="if (! $event.target.closest('button, a, [role=checkbox]')) {{ $viewCall }}">
                        <td class="table-td">
                            <x-ui.checkbox aria-label="Pilih {{ $return->return_code }}" x-bind:checked="isSelected({{ $return->id }})" x-on:change="toggle({{ $return->id }})" />
                        </td>
                        <td class="table-td font-medium whitespace-nowrap text-gray-800 dark:text-white/90">
                            @include('pages.inventory.partials.copyable', ['text' => $return->return_code])
                        </td>
                        <td class="table-td whitespace-nowrap">{{ Format::date($return->date) }}</td>
                        <td class="table-td">
                            <x-ui.badge size="sm" :color="$isSupplier ? 'warning' : 'info'">{{ $isSupplier ? 'Ke Supplier' : 'Dari Customer' }}</x-ui.badge>
                        </td>
                        <td class="table-td">{{ ($isSupplier ? $return->supplier?->name : $return->customer_name) ?: '-' }}</td>
                        <td class="table-td"><x-ui.badge size="sm" :color="$statusColor">{{ $statusLabel }}</x-ui.badge></td>
                        <td class="table-td whitespace-nowrap">{{ Format::rupiah($return->total_amount) }}</td>
                        <td class="table-td text-end">
                            <x-common.row-actions>
                                <x-ui.dropdown-menu.item to="{{ route('return-transaction.pdf', $return) }}" target="_blank">
                                    <x-svg.page /> Cetak PDF
                                </x-ui.dropdown-menu.item>
                                <x-ui.dropdown-menu.item x-on:click="{{ $viewCall }}">
                                    <x-svg.eye /> Lihat
                                </x-ui.dropdown-menu.item>
                                @if ($isPending)
                                    <x-ui.dropdown-menu.item x-on:click="confirm({ title: 'Setujui Retur', message: 'Apakah Anda yakin ingin menyetujui retur ini?', action: {{ Js::from(route('return-transactions.approve', $return)) }}, method: 'POST', confirmLabel: 'Setujui', variant: 'success' })">
                                        <x-svg.check-circle /> Setujui
                                    </x-ui.dropdown-menu.item>
                                    <x-ui.dropdown-menu.item variant="destructive" x-on:click="confirm({ title: 'Tolak Retur', message: 'Apakah Anda yakin ingin menolak retur ini?', action: {{ Js::from(route('return-transactions.reject', $return)) }}, method: 'POST', confirmLabel: 'Tolak' })">
                                        <x-svg.x /> Tolak
                                    </x-ui.dropdown-menu.item>
                                    <x-ui.dropdown-menu.item to="{{ route('return-transactions.edit', $return) }}">
                                        <x-svg.pencil /> Ubah
                                    </x-ui.dropdown-menu.item>
                                @endif
                            </x-common.row-actions>
                        </td>
                    </tr>
                @empty
                    <x-common.table-empty colspan="8" title="Belum ada transaksi retur" />
                @endforelse
            </tbody>
        </x-common.table-card>

        @include('pages.inventory.partials.detail-panel')
    </div>
@endsection
