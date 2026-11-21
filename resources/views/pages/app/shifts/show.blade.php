@extends('layouts.app')
@use('App\Models\User')
@use('Illuminate\Support\Js')

{{--
    Detail shift: siapa saja kasirnya & penanggung jawab, ringkasan laci kas bersama,
    tambah/keluarkan kasir, ganti penanggung jawab, batalkan (belum dibuka) / tutup (cadangan admin).
--}}
@php
    $money = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
    $initial = fn (?string $name) => mb_strtoupper(mb_substr(trim((string) $name), 0, 1)) ?: '?';
    $scheduled = $shift->isScheduled();
    $open = $shift->isOpen();
    $editable = ! $shift->isClosed();
    $supervisorName = $shift->supervisor?->name ?? 'penanggung jawab';
@endphp

@section('content')
    <x-common.page-breadcrumb pageTitle="Detail Shift" />

    {{-- id="shift-detail": diperbarui ajaxForm setelah tambah kasir / ganti PJ / tutup shift --}}
    <div id="shift-detail" class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">{{ $shift->label() }}</h2>
                    @if ($scheduled)
                        <x-ui.badge size="sm" color="warning">Belum dibuka</x-ui.badge>
                    @else
                        <x-ui.badge size="sm" :color="$open ? 'success' : 'gray'">{{ $open ? 'Berjalan' : 'Selesai' }}</x-ui.badge>
                    @endif
                    @unless ($shift->isManaged())
                        <x-ui.badge size="sm" color="gray">Dibuka kasir sendiri</x-ui.badge>
                    @endunless
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Penanggung jawab: <span class="font-medium text-gray-700 dark:text-gray-300">{{ $shift->supervisor?->name ?? '-' }}</span>
                    @if ($shift->opened_at)
                        · Dibuka {{ $shift->opened_at->format('d/m/Y H:i') }} oleh {{ $shift->openedBy?->name ?? '-' }}
                    @else
                        · Dibuat {{ $shift->created_at?->format('d/m/Y H:i') }}
                    @endif
                    @if ($shift->closed_at)
                        · Ditutup {{ $shift->closed_at->format('d/m/Y H:i') }} oleh {{ $shift->closedBy?->name ?? '-' }}
                    @endif
                </p>
            </div>
            <div class="flex gap-2">
                @if ($scheduled)
                    <x-ui.button variant="outline" size="sm" class="text-error-600 hover:text-error-700 dark:text-error-500"
                        x-data x-on:click="$dispatch('confirm', {
                            title: 'Batalkan Shift',
                            message: 'Shift yang belum dibuka ini akan dihapus beserta daftar kasirnya.',
                            action: {{ Js::from(route('shifts.destroy', $shift)) }},
                            method: 'DELETE',
                            confirmLabel: 'Batalkan Shift',
                        })">Batalkan Shift</x-ui.button>
                @endif
                <x-ui.button as="a" href="{{ route('shifts.index') }}" variant="outline" size="sm">Kembali</x-ui.button>
            </div>
        </div>

        @if ($scheduled)
            {{-- Belum dibuka: menunggu penanggung jawab --}}
            <div class="flex items-start gap-4 rounded-2xl border border-warning-200 bg-warning-50 p-5 dark:border-warning-500/30 dark:bg-warning-500/10">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-300">
                    <svg class="size-5 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                </span>
                <div class="text-sm text-warning-800 dark:text-warning-300">
                    <p class="font-semibold">Menunggu dibuka oleh {{ $supervisorName }}</p>
                    <p class="mt-1">
                        {{ $supervisorName }} membuka shift dari panel kasir (menu Shift Kasir) dengan mengisi modal awal hasil hitung laci.
                        Sebelum dibuka, kasir di shift ini belum bisa berjualan.
                    </p>
                </div>
            </div>
        @else
            {{-- Ringkasan laci kas bersama --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
                    <p class="text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Modal Awal</p>
                    <p class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">{{ $money($summary['opening_cash']) }}</p>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
                    <p class="text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Omzet Shift</p>
                    <p class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">{{ $money($summary['total_sales']) }}</p>
                    <p class="mt-1 text-xs text-gray-400">{{ $summary['orders_count'] }} transaksi · tunai {{ $money($summary['cash_sales']) }}</p>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
                    <p class="text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Kas Masuk / Keluar</p>
                    <p class="mt-2 text-lg font-bold text-success-600 dark:text-success-500">+{{ $money($summary['cash_in']) }}</p>
                    <p class="text-sm font-semibold text-error-500">-{{ $money($summary['cash_out']) }}</p>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
                    @if ($open)
                        <p class="text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Estimasi Kas di Laci</p>
                        <p class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">{{ $money($summary['expected_cash']) }}</p>
                    @else
                        <p class="text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Kas Aktual · Selisih</p>
                        <p class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">{{ $money($shift->actual_cash) }}</p>
                        <p class="text-sm font-semibold {{ $shift->cash_difference < 0 ? 'text-error-500' : ($shift->cash_difference > 0 ? 'text-success-600 dark:text-success-500' : 'text-gray-500') }}">
                            {{ $shift->cash_difference > 0 ? '+' : '' }}{{ $money($shift->cash_difference) }} dari sistem {{ $money($shift->expected_cash) }}
                        </p>
                    @endif
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
            {{-- Siapa saja di shift ini --}}
            <x-common.component-card title="Kasir di Shift Ini ({{ $shift->members->count() }})"
                desc="Semua kasir berjualan ke laci yang sama. Penanggung jawab membuka & menutup shift.">
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($shift->members as $member)
                        @php
                            $stat = $memberStats[$member->id] ?? null;
                            $isSupervisor = $shift->isSupervisor($member->id);
                        @endphp
                        <div class="flex flex-wrap items-center gap-4 py-3.5">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-500 text-sm font-semibold text-white">{{ $initial($member->name) }}</span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="truncate font-medium text-gray-800 dark:text-white/90">{{ $member->name }}</p>
                                    <x-ui.badge size="sm" :color="$member->role === User::ROLE_ADMIN ? 'primary' : 'gray'">{{ User::ROLES[$member->role] ?? $member->role }}</x-ui.badge>
                                    @if ($isSupervisor)
                                        <x-ui.badge size="sm" color="warning">Penanggung Jawab</x-ui.badge>
                                    @endif
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Bergabung {{ $member->pivot->created_at?->format('d/m H:i') }}
                                    @if ($member->pivot->added_by && isset($addedBy[$member->pivot->added_by]))
                                        · ditambahkan {{ $addedBy[$member->pivot->added_by] }}
                                    @endif
                                </p>
                            </div>
                            <div class="text-end">
                                <p class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ $money($stat?->total ?? 0) }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $stat?->count ?? 0 }} transaksi</p>
                            </div>
                            @if ($editable)
                                @if ($isSupervisor)
                                    {{-- Penanggung jawab tidak bisa dikeluarkan sebelum diganti --}}
                                    <span class="size-9" aria-hidden="true"></span>
                                @else
                                    <button type="button" title="Keluarkan dari shift"
                                        x-data x-on:click="$dispatch('confirm', {
                                            title: 'Keluarkan Kasir',
                                            message: {{ Js::from($member->name.' tidak bisa bertransaksi di shift ini lagi. Transaksi yang sudah dibuat tetap tercatat.') }},
                                            action: {{ Js::from(route('shifts.members.remove', [$shift, $member])) }},
                                            method: 'DELETE',
                                            confirmLabel: 'Keluarkan',
                                        })"
                                        class="flex size-9 items-center justify-center rounded-lg text-gray-400 hover:bg-error-50 hover:text-error-500 dark:hover:bg-error-500/15">
                                        <x-svg.x class="size-4" />
                                    </button>
                                @endif
                            @endif
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-warning-600 dark:text-orange-400">Belum ada kasir di shift ini. Tambahkan kasir agar bisa berjualan.</p>
                    @endforelse

                    @foreach ($formerMembers as $former)
                        @php $stat = $memberStats[$former->id] ?? null; @endphp
                        <div class="flex items-center gap-4 py-3.5 opacity-70">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-gray-200 text-sm font-semibold text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ $initial($former->name) }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium text-gray-700 dark:text-gray-300">{{ $former->name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Sudah tidak di shift ini</p>
                            </div>
                            <div class="text-end">
                                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $money($stat?->total ?? 0) }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $stat?->count ?? 0 }} transaksi</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($editable)
                    <div class="mt-4 space-y-3 border-t border-gray-100 pt-4 dark:border-gray-800">
                        {{-- Tambah kasir --}}
                        <form method="POST" action="{{ route('shifts.members.add', $shift) }}" class="flex flex-col gap-2 sm:flex-row sm:items-start"
                            x-data="ajaxForm({ regions: ['shift-detail'] })" x-on:submit.prevent="submit($event)">
                            @csrf
                            <x-common.form.field error="user_id" class="flex-1">
                                <x-common.form.select name="user_id" placeholder="Pilih kasir untuk ditambahkan…" aria-label="Kasir"
                                    :options="$availableUsers->mapWithKeys(fn ($u) => [$u->id => $u->name.' ('.(User::ROLES[$u->role] ?? $u->role).')'])->all()" />
                            </x-common.form.field>
                            <x-ui.button type="submit" variant="outline" x-bind:disabled="busy" class="shrink-0 sm:w-44">
                                <x-svg.spinner class="size-4" x-show="busy" x-cloak />
                                <x-svg.plus class="size-4" x-show="!busy" />
                                Tambah Kasir
                            </x-ui.button>
                        </form>

                        {{-- Ganti penanggung jawab --}}
                        @if ($shift->members->count() > 1)
                            <form method="POST" action="{{ route('shifts.supervisor', $shift) }}" class="flex flex-col gap-2 sm:flex-row sm:items-start"
                                x-data="ajaxForm({ regions: ['shift-detail'] })" x-on:submit.prevent="submit($event)">
                                @csrf
                                @method('PUT')
                                <x-common.form.field error="supervisor_id" class="flex-1">
                                    <x-common.form.select name="supervisor_id" aria-label="Penanggung jawab" :value="$shift->supervisor_id"
                                        :options="$shift->members->mapWithKeys(fn ($m) => [$m->id => 'Penanggung jawab: '.$m->name])->all()" />
                                </x-common.form.field>
                                <x-ui.button type="submit" variant="outline" x-bind:disabled="busy" class="shrink-0 sm:w-44">
                                    <x-svg.spinner class="size-4" x-show="busy" x-cloak />
                                    Ganti Penanggung Jawab
                                </x-ui.button>
                            </form>
                        @endif
                    </div>
                @endif
            </x-common.component-card>

            {{-- Kanan: menunggu dibuka / tutup shift (cadangan admin) / Z-Report --}}
            @if ($scheduled)
                <x-common.component-card title="Buka Shift">
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Shift dibuka oleh <strong>{{ $supervisorName }}</strong> dari panel kasir. Modal awal diisi saat membuka, sesuai uang di laci.
                    </p>
                </x-common.component-card>
            @elseif ($open)
                <x-common.component-card title="Tutup Shift"
                    desc="Biasanya ditutup oleh {{ $supervisorName }} dari panel kasir. Gunakan form ini bila penanggung jawab berhalangan.">
                    <form method="POST" action="{{ route('shifts.close', $shift) }}" class="space-y-4"
                        x-data="ajaxForm({ regions: ['shift-detail'] })" x-on:submit.prevent="submit($event)">
                        @csrf
                        <x-main.cash-count.denomination-input id-prefix="shift-close" />
                        <x-common.form.field label="Catatan Penutupan (Opsional)" for="shift-close-notes" error="notes">
                            <x-ui.input id="shift-close-notes" name="notes" maxlength="500" placeholder="Catatan selisih / serah terima" />
                        </x-common.form.field>
                        <x-ui.button type="submit" class="w-full bg-error-500 hover:bg-error-600" x-bind:disabled="busy">
                            <x-svg.spinner class="size-4" x-show="busy" x-cloak />
                            <span x-text="busy ? 'Menutup shift…' : 'Tutup Shift & Simpan Z-Report'">Tutup Shift &amp; Simpan Z-Report</span>
                        </x-ui.button>
                    </form>
                </x-common.component-card>
            @else
                <x-common.component-card title="Z-Report">
                    <dl class="divide-y divide-gray-100 text-sm dark:divide-gray-800">
                        @foreach ([
                            'Kas Sistem' => $money($shift->expected_cash),
                            'Kas Aktual' => $money($shift->actual_cash),
                            'Selisih' => ($shift->cash_difference > 0 ? '+' : '').$money($shift->cash_difference),
                            'Ditutup Oleh' => $shift->closedBy?->name ?? '-',
                        ] as $label => $value)
                            <div class="flex justify-between gap-4 py-2.5">
                                <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                                <dd class="font-medium text-gray-800 dark:text-white/90">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    @if ($shift->notes)
                        <p class="mt-3 rounded-lg bg-gray-50 p-3 text-sm text-gray-600 dark:bg-white/3 dark:text-gray-300">{{ $shift->notes }}</p>
                    @endif
                </x-common.component-card>
            @endif
        </div>
    </div>
@endsection
