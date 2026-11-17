@extends('layouts.app')
@use('Illuminate\Support\Js')
@use('App\Support\Format')
@use('App\Support\StatusBadge')

@section('content')
    <x-common.page-breadcrumb pageTitle="Stock Opname" />

    <div x-data="listPage()">
        <x-common.table-card title="Daftar Stock Opname" :paginator="$opnames">
            <x-slot:actions>
                <x-ui.button as="a" href="{{ route('stock-opnames.create') }}">
                    <x-svg.plus class="size-5" />
                    Mulai Stock Opname
                </x-ui.button>
            </x-slot:actions>

            <thead class="border-y border-gray-100 dark:border-gray-800">
                <tr>
                    <th class="table-th min-w-40">Kode Sesi</th>
                    <th class="table-th min-w-48">Nama Sesi</th>
                    <th class="table-th min-w-40">Tanggal</th>
                    <th class="table-th min-w-24">Total Scan</th>
                    <th class="table-th min-w-28">Total Selisih</th>
                    <th class="table-th min-w-28">Status Item</th>
                    <th class="table-th min-w-24">Status</th>
                    <th class="table-th w-16"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($opnames as $opname)
                    @php
                        [$statusLabel, $statusColor] = StatusBadge::for('opname', $opname->status);
                        $diff = (int) $opname->total_difference;
                        [$itemLabel, $itemColor] = $diff === 0 ? ['Cocok', 'success'] : ($diff > 0 ? ['Lebih', 'warning'] : ['Kurang', 'error']);
                        $viewCall = 'view('.Js::from(route('stock-opnames.show', $opname)).', '.Js::from('Stock Opname '.$opname->code).')';
                    @endphp
                    <tr class="table-row cursor-pointer" x-on:click="if (! $event.target.closest('button, a')) {{ $viewCall }}">
                        <td class="table-td font-medium whitespace-nowrap text-gray-800 dark:text-white/90">
                            @include('pages.inventory.partials.copyable', ['text' => $opname->code])
                        </td>
                        <td class="table-td">{{ $opname->session_name }}</td>
                        <td class="table-td whitespace-nowrap">{{ Format::date($opname->date) }}</td>
                        <td class="table-td">{{ Format::number($opname->total_items_scanned) }}</td>
                        <td class="table-td">{{ Format::signed($diff) }}</td>
                        <td class="table-td"><x-ui.badge size="sm" :color="$itemColor">{{ $itemLabel }}</x-ui.badge></td>
                        <td class="table-td"><x-ui.badge size="sm" :color="$statusColor">{{ $statusLabel }}</x-ui.badge></td>
                        <td class="table-td text-end">
                            <x-common.row-actions>
                                <x-ui.dropdown-menu.item to="{{ route('stock-opname.pdf', $opname) }}" target="_blank">
                                    <x-svg.page /> Cetak PDF
                                </x-ui.dropdown-menu.item>
                                <x-ui.dropdown-menu.item x-on:click="{{ $viewCall }}">
                                    <x-svg.eye /> Lihat
                                </x-ui.dropdown-menu.item>
                                @if (StatusBadge::value($opname->status) !== 'selesai')
                                    <x-ui.dropdown-menu.item to="{{ route('stock-opnames.edit', $opname) }}">
                                        <x-svg.pencil /> Lanjutkan
                                    </x-ui.dropdown-menu.item>
                                @endif
                            </x-common.row-actions>
                        </td>
                    </tr>
                @empty
                    <x-common.table-empty colspan="8" title="Belum ada stock opname" />
                @endforelse
            </tbody>
        </x-common.table-card>

        @include('pages.inventory.partials.detail-panel')
    </div>
@endsection
