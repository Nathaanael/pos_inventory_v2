@extends('layouts.app')
@use('App\Models\CashCount')
@use('App\Support\StatusBadge')

{{-- Hitung Kas (modul FNB): shift yang sedang berjalan + riwayat hitung kas. --}}
@php
    $money = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
    $signedMoney = fn ($v) => ((float) $v > 0 ? '+' : ((float) $v < 0 ? '-' : '')).$money(abs((float) $v));
@endphp

@section('content')
    <x-common.page-breadcrumb pageTitle="Hitung Kas" />

    <div class="space-y-6">
        {{-- Shift berjalan --}}
        <x-common.component-card title="Shift Kasir Sedang Berjalan ({{ $activeShifts->count() }})"
            desc="Ringkasan laci kas setiap shift aktif (X-Report). Pilih Hitung Kas untuk cek dadakan; hitung kas saat tutup shift tercatat otomatis.">
            @if ($activeShifts->isEmpty())
                <p class="py-6 text-center text-sm text-gray-400">Tidak ada shift kasir yang sedang berjalan.</p>
            @else
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 2xl:grid-cols-3">
                    @foreach ($activeShifts as $shift)
                        @php
                            $sum = $summaries[$shift->id];
                            $nonCash = $sum['total_sales'] - $sum['cash_sales'];
                            $lastCount = $shift->cash_counts_max_counted_at ? \Illuminate\Support\Carbon::parse($shift->cash_counts_max_counted_at) : null;
                        @endphp
                        <div class="flex flex-col gap-4 rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-gray-800 dark:text-white/90">{{ $shift->user?->name ?? '-' }}</p>
                                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                        Buka {{ $shift->opened_at->format('d/m/Y H:i') }} · berjalan {{ $shift->opened_at->locale('id')->diffForHumans(null, \Carbon\CarbonInterface::DIFF_ABSOLUTE) }}
                                    </p>
                                </div>
                                <x-ui.badge size="sm" color="success">Aktif</x-ui.badge>
                            </div>

                            <dl class="grid grid-cols-2 gap-x-4 gap-y-2.5 text-sm">
                                <div>
                                    <dt class="text-xs text-gray-500 dark:text-gray-400">Modal Awal</dt>
                                    <dd class="font-medium text-gray-800 dark:text-white/90">{{ $money($sum['opening_cash']) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-gray-500 dark:text-gray-400">Omzet ({{ $sum['orders_count'] }} transaksi)</dt>
                                    <dd class="font-medium text-gray-800 dark:text-white/90">{{ $money($sum['total_sales']) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-gray-500 dark:text-gray-400">Penjualan Tunai</dt>
                                    <dd class="font-medium text-gray-800 dark:text-white/90">{{ $money($sum['cash_sales']) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-gray-500 dark:text-gray-400">Non-Tunai</dt>
                                    <dd class="font-medium text-gray-800 dark:text-white/90">{{ $money($nonCash) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-gray-500 dark:text-gray-400">Kas Masuk</dt>
                                    <dd class="font-medium text-success-600 dark:text-success-500">+{{ $money($sum['cash_in']) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-gray-500 dark:text-gray-400">Kas Keluar</dt>
                                    <dd class="font-medium text-error-500">-{{ $money($sum['cash_out']) }}</dd>
                                </div>
                            </dl>

                            <div class="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2.5 dark:bg-white/3">
                                <span class="text-sm text-gray-600 dark:text-gray-300">Estimasi Kas di Laci</span>
                                <span class="text-base font-bold text-gray-800 dark:text-white/90">{{ $money($sum['expected_cash']) }}</span>
                            </div>

                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Hitung kas terakhir: {{ $lastCount ? $lastCount->format('d/m H:i') : 'belum pernah' }}
                                @if ($sum['void_count'] > 0)
                                    · <span class="text-warning-600 dark:text-orange-400">{{ $sum['void_count'] }} transaksi void</span>
                                @endif
                            </p>

                            <x-ui.button as="a" href="{{ route('cash-counts.create', $shift) }}" class="mt-auto w-full">Hitung Kas</x-ui.button>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-common.component-card>

        {{-- Riwayat --}}
        <x-common.table-card title="Riwayat Hitung Kas" :paginator="$counts" searchPlaceholder="Cari kode / kasir...">
            <x-slot:filters>
                <x-common.form.select name="status" class="h-10 sm:w-44" :value="request('status')" placeholder="Semua status"
                    :options="$statusOptions" x-data x-on:change="$el.form.requestSubmit()" aria-label="Filter status" />
            </x-slot:filters>

            @if ($reviewCount > 0)
                <x-slot:toolbar>
                    <div class="mx-6 mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:bg-warning-500/15 dark:text-orange-400">
                        <span>{{ $reviewCount }} hitung kas memiliki selisih di luar toleransi dan perlu direview.</span>
                        <a href="{{ route('cash-counts.index', ['status' => CashCount::STATUS_REVIEW]) }}" class="font-medium underline">Lihat</a>
                    </div>
                </x-slot:toolbar>
            @endif

            <thead class="border-y border-gray-100 dark:border-gray-800">
                <tr>
                    <th class="table-th min-w-40">Kode</th>
                    <th class="table-th min-w-36">Waktu</th>
                    <th class="table-th min-w-36">Kasir</th>
                    <th class="table-th min-w-28">Jenis</th>
                    <th class="table-th min-w-32">Uang Sistem</th>
                    <th class="table-th min-w-32">Uang Fisik</th>
                    <th class="table-th min-w-28">Selisih</th>
                    <th class="table-th min-w-32">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($counts as $count)
                    @php [$statusLabel, $statusColor] = StatusBadge::for('cash_count', $count->status); @endphp
                    <tr class="table-row cursor-pointer" x-data x-on:click="window.location = @js(route('cash-counts.show', $count))">
                        <td class="table-td font-medium whitespace-nowrap text-gray-800 dark:text-white/90">
                            <a href="{{ route('cash-counts.show', $count) }}" class="hover:text-brand-500">{{ $count->code }}</a>
                        </td>
                        <td class="table-td whitespace-nowrap">{{ $count->counted_at->format('d/m/Y H:i') }}</td>
                        <td class="table-td">{{ $count->shift?->user?->name ?? '-' }}</td>
                        <td class="table-td">{{ CashCount::TYPES[$count->type] ?? $count->type }}</td>
                        <td class="table-td whitespace-nowrap">{{ $money($count->expected_cash) }}</td>
                        <td class="table-td whitespace-nowrap">{{ $money($count->actual_cash) }}</td>
                        <td class="table-td font-medium whitespace-nowrap {{ $count->difference < 0 ? 'text-error-500' : ($count->difference > 0 ? 'text-success-600 dark:text-success-500' : 'text-gray-500 dark:text-gray-400') }}">
                            {{ $signedMoney($count->difference) }}
                        </td>
                        <td class="table-td"><x-ui.badge size="sm" :color="$statusColor">{{ $statusLabel }}</x-ui.badge></td>
                    </tr>
                @empty
                    <x-common.table-empty colspan="8" title="Belum ada hitung kas" />
                @endforelse
            </tbody>
        </x-common.table-card>
    </div>
@endsection
