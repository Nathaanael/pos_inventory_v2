@extends('layouts.app')
@use('App\Models\CashCount')
@use('App\Support\CashDenominations')
@use('App\Support\StatusBadge')

@php
    $money = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
    $signedMoney = fn ($v) => ((float) $v > 0 ? '+' : ((float) $v < 0 ? '-' : '')).$money(abs((float) $v));
    [$statusLabel, $statusColor] = StatusBadge::for('cash_count', $count->status);
    $diffTone = $count->difference < 0 ? 'text-error-500' : ($count->difference > 0 ? 'text-success-600 dark:text-success-500' : 'text-gray-800 dark:text-white/90');
    $shift = $count->shift;
@endphp

@section('content')
    <x-common.page-breadcrumb pageTitle="Detail Hitung Kas" />

    @include('pages.inventory.partials.form-errors')

    {{-- id="cash-count-detail": diperbarui ajaxForm setelah selisih disetujui. --}}
    <div id="cash-count-detail" class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $count->code }}</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ CashCount::TYPES[$count->type] ?? $count->type }} · {{ $count->counted_at->format('d/m/Y H:i') }}
                </p>
            </div>
            <div class="flex items-center gap-3">
                <x-ui.badge :color="$statusColor">{{ $statusLabel }}</x-ui.badge>
                <x-ui.button as="a" href="{{ route('cash-counts.index') }}" variant="outline" size="sm">Kembali</x-ui.button>
            </div>
        </div>

        {{-- Ringkasan --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
                <p class="text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Uang di Sistem</p>
                <p class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">{{ $money($count->expected_cash) }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
                <p class="text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Uang Fisik</p>
                <p class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">{{ $money($count->actual_cash) }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
                <p class="text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Selisih</p>
                <p class="mt-2 text-2xl font-bold {{ $diffTone }}">{{ $signedMoney($count->difference) }}</p>
                <p class="mt-1 text-xs text-gray-400">Toleransi {{ $money($count->tolerance) }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- Rincian uang sistem & info shift --}}
            <x-common.component-card title="Rincian Uang di Sistem" desc="Angka saat hitung kas dilakukan.">
                <dl class="divide-y divide-gray-100 text-sm dark:divide-gray-800">
                    @foreach ([
                        'Kasir' => $shift?->user?->name ?? '-',
                        'Shift' => $shift ? $shift->opened_at->format('d/m/Y H:i').' – '.($shift->closed_at?->format('d/m/Y H:i') ?? 'berjalan') : '-',
                        'Dihitung Oleh' => $count->countedBy?->name ?? '-',
                    ] as $label => $text)
                        <div class="flex justify-between gap-4 py-2.5">
                            <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                            <dd class="text-end font-medium text-gray-800 dark:text-white/90">{{ $text }}</dd>
                        </div>
                    @endforeach
                    <div class="flex justify-between gap-4 py-2.5">
                        <dt class="text-gray-500 dark:text-gray-400">Modal Awal</dt>
                        <dd class="text-gray-800 dark:text-white/90">{{ $money($count->opening_cash) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2.5">
                        <dt class="text-gray-500 dark:text-gray-400">Penjualan Tunai</dt>
                        <dd class="text-gray-800 dark:text-white/90">+{{ $money($count->cash_sales) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2.5">
                        <dt class="text-gray-500 dark:text-gray-400">Kas Masuk</dt>
                        <dd class="text-gray-800 dark:text-white/90">+{{ $money($count->cash_in) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2.5">
                        <dt class="text-gray-500 dark:text-gray-400">Kas Keluar</dt>
                        <dd class="text-gray-800 dark:text-white/90">-{{ $money($count->cash_out) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2.5 font-semibold">
                        <dt class="text-gray-800 dark:text-white/90">Uang di Sistem</dt>
                        <dd class="text-gray-800 dark:text-white/90">{{ $money($count->expected_cash) }}</dd>
                    </div>
                </dl>
            </x-common.component-card>

            {{-- Rincian uang fisik --}}
            <x-common.component-card title="Rincian Uang Fisik">
                @if ($count->denominations)
                    <dl class="divide-y divide-gray-100 text-sm dark:divide-gray-800">
                        @foreach (CashDenominations::all() as $value)
                            @continue(empty($count->denominations[(string) $value]))
                            @php $qty = (int) $count->denominations[(string) $value]; @endphp
                            <div class="flex justify-between gap-4 py-2.5">
                                <dt class="text-gray-500 dark:text-gray-400">{{ CashDenominations::label($value) }} × {{ $qty }}</dt>
                                <dd class="text-gray-800 dark:text-white/90">{{ $money($value * $qty) }}</dd>
                            </div>
                        @endforeach
                        <div class="flex justify-between gap-4 py-2.5 font-semibold">
                            <dt class="text-gray-800 dark:text-white/90">Total</dt>
                            <dd class="text-gray-800 dark:text-white/90">{{ $money($count->actual_cash) }}</dd>
                        </div>
                    </dl>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">Diisi sebagai total langsung: <span class="font-medium text-gray-800 dark:text-white/90">{{ $money($count->actual_cash) }}</span></p>
                @endif

                @if ($count->notes)
                    <div class="mt-4 rounded-lg bg-gray-50 p-3 text-sm text-gray-700 dark:bg-white/3 dark:text-gray-300">
                        <p class="mb-1 text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Catatan</p>
                        {{ $count->notes }}
                    </div>
                @endif
            </x-common.component-card>
        </div>

        {{-- Review selisih --}}
        @if ($count->needsReview())
            <x-common.component-card title="Review Selisih" desc="Selisih melebihi toleransi. Isi penjelasan penyebab selisih untuk menyetujuinya.">
                <form method="POST" action="{{ route('cash-counts.approve', $count) }}" class="space-y-4"
                    x-data="ajaxForm({ regions: ['cash-count-detail'] })" x-on:submit.prevent="submit">
                    @csrf
                    <x-common.form.field label="Penjelasan Selisih" for="review_notes" required>
                        <x-ui.textarea id="review_notes" name="review_notes" rows="3" maxlength="500" required
                            placeholder="mis. Salah memberi kembalian Rp5.000, sudah dikonfirmasi dengan kasir">{{ old('review_notes') }}</x-ui.textarea>
                    </x-common.form.field>
                    <x-ui.button type="submit" x-bind:disabled="busy">
                        <span x-text="busy ? 'Menyimpan…' : 'Setujui Selisih'">Setujui Selisih</span>
                    </x-ui.button>
                </form>
            </x-common.component-card>
        @elseif ($count->status === CashCount::STATUS_APPROVED)
            <x-common.component-card title="Review Selisih">
                <p class="text-sm text-gray-700 dark:text-gray-300">{{ $count->review_notes }}</p>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    Disetujui oleh {{ $count->reviewedBy?->name ?? '-' }} · {{ $count->reviewed_at?->format('d/m/Y H:i') }}
                </p>
            </x-common.component-card>
        @endif
    </div>
@endsection
