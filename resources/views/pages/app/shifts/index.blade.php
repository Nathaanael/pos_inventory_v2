@extends('layouts.app')
@use('App\Models\User')

{{-- Shift Kasir (back office): satu shift = satu laci kas, berisi satu atau beberapa kasir. --}}
@php
    $money = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
    $initial = fn (?string $name) => mb_strtoupper(mb_substr(trim((string) $name), 0, 1)) ?: '?';
@endphp

@section('content')
    <x-common.page-breadcrumb pageTitle="Shift Kasir" />

    <div x-data="{ createOpen: @js($errors->any()) }" class="space-y-6">
        {{-- Shift berjalan --}}
        <x-common.component-card title="Shift Aktif ({{ $activeShifts->count() }})"
            desc="Satu shift = satu laci kas. Penanggung jawab membuka (isi modal awal) dan menutup shift dari panel kasir.">
            <div class="mb-5 flex justify-end">
                <x-ui.button x-on:click="createOpen = true">
                    <x-svg.plus class="size-5" />
                    Buat Shift
                </x-ui.button>
            </div>

            @if ($activeShifts->isEmpty())
                <p class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada shift aktif. Buat shift, pilih kasir dan penanggung jawabnya.</p>
            @else
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 2xl:grid-cols-3">
                    @foreach ($activeShifts as $shift)
                        <a href="{{ route('shifts.show', $shift) }}"
                            class="flex flex-col gap-4 rounded-xl border border-gray-200 p-5 transition hover:border-brand-300 hover:shadow-theme-sm dark:border-gray-800 dark:hover:border-brand-500/40">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-base font-semibold text-gray-800 dark:text-white/90">{{ $shift->label() }}</p>
                                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                        @if ($shift->isScheduled())
                                            Menunggu dibuka oleh {{ $shift->supervisor?->name ?? '-' }}
                                        @else
                                            Dibuka {{ $shift->opened_at?->format('d/m H:i') }} oleh {{ $shift->openedBy?->name ?? '-' }}
                                        @endif
                                    </p>
                                </div>
                                @if ($shift->isScheduled())
                                    <x-ui.badge size="sm" color="warning">Belum dibuka</x-ui.badge>
                                @else
                                    <x-ui.badge size="sm" color="success">Berjalan</x-ui.badge>
                                @endif
                            </div>

                            {{-- Siapa saja di shift ini --}}
                            <div>
                                <p class="mb-2 text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Kasir ({{ $shift->members->count() }})</p>
                                <div class="flex flex-wrap gap-2">
                                    @forelse ($shift->members as $member)
                                        <span class="inline-flex items-center gap-2 rounded-full bg-gray-100 py-1 ps-1 pe-3 text-sm text-gray-700 dark:bg-white/5 dark:text-gray-300">
                                            <span class="flex size-6 items-center justify-center rounded-full bg-brand-500 text-xs font-semibold text-white">{{ $initial($member->name) }}</span>
                                            {{ $member->name }}
                                            @if ($shift->isSupervisor($member->id))
                                                <span class="text-xs font-medium text-warning-600 dark:text-orange-400">· PJ</span>
                                            @endif
                                        </span>
                                    @empty
                                        <span class="text-sm text-warning-600 dark:text-orange-400">Belum ada kasir</span>
                                    @endforelse
                                </div>
                            </div>

                            <div class="mt-auto flex items-center justify-between border-t border-gray-100 pt-3 text-sm dark:border-gray-800">
                                <span class="text-gray-500 dark:text-gray-400">
                                    @if ($shift->isScheduled())
                                        Modal awal diisi saat dibuka
                                    @else
                                        Modal {{ $money($shift->opening_cash) }} · {{ $shift->outbounds_count }} transaksi
                                    @endif
                                </span>
                                <span class="font-medium text-brand-600 dark:text-brand-400">Kelola →</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </x-common.component-card>

        {{-- Riwayat --}}
        <x-common.table-card title="Riwayat Shift" :paginator="$history" :search="false">
            <thead class="border-y border-gray-100 dark:border-gray-800">
                <tr>
                    <th class="table-th min-w-40">Shift</th>
                    <th class="table-th min-w-48">Kasir</th>
                    <th class="table-th min-w-40">Waktu</th>
                    <th class="table-th min-w-32">Kas Sistem</th>
                    <th class="table-th min-w-32">Kas Aktual</th>
                    <th class="table-th min-w-28">Selisih</th>
                    <th class="table-th min-w-32">Ditutup Oleh</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($history as $row)
                    <tr class="table-row cursor-pointer" x-data x-on:click="window.location = @js(route('shifts.show', $row))">
                        <td class="table-td font-medium text-gray-800 dark:text-white/90">
                            <a href="{{ route('shifts.show', $row) }}" class="hover:text-brand-500">{{ $row->name ?: 'Shift #'.$row->id }}</a>
                        </td>
                        <td class="table-td">{{ $row->members->pluck('name')->implode(', ') ?: '-' }}</td>
                        <td class="table-td text-xs whitespace-nowrap text-gray-500 dark:text-gray-400">
                            {{ $row->opened_at?->format('d/m/Y H:i') }} – {{ $row->closed_at?->format('H:i') }}
                        </td>
                        <td class="table-td whitespace-nowrap">{{ $money($row->expected_cash) }}</td>
                        <td class="table-td whitespace-nowrap">{{ $money($row->actual_cash) }}</td>
                        <td class="table-td font-medium whitespace-nowrap {{ $row->cash_difference < 0 ? 'text-error-500' : ($row->cash_difference > 0 ? 'text-success-600 dark:text-success-500' : 'text-gray-500 dark:text-gray-400') }}">
                            {{ $row->cash_difference > 0 ? '+' : '' }}{{ $money($row->cash_difference) }}
                        </td>
                        <td class="table-td text-gray-500 dark:text-gray-400">{{ $row->closedBy?->name ?? '-' }}</td>
                    </tr>
                @empty
                    <x-common.table-empty colspan="7" title="Belum ada shift yang ditutup" />
                @endforelse
            </tbody>
        </x-common.table-card>

        {{-- Modal Buat Shift --}}
        <x-common.modal show="createOpen" close="createOpen = false" max-width="2xl" title="Buat Shift">
            {{-- members = kasir dicentang; supervisor = penanggung jawab (harus salah satu yang dicentang) --}}
            <form id="shift-create-form" method="POST" action="{{ route('shifts.store') }}" class="grid grid-cols-1 gap-5"
                x-data="Object.assign(ajaxForm(), {
                    members: @js(array_map('strval', (array) old('members', []))),
                    supervisor: @js((string) old('supervisor_id', '')),
                })"
                x-effect="if (supervisor && !members.includes(supervisor)) supervisor = ''; if (!supervisor && members.length) supervisor = members[0]"
                x-on:submit.prevent="submit($event)">
                @csrf
                <x-common.form.field label="Nama Shift" for="shift-name" error="name" hint="Opsional, mis. Shift Pagi.">
                    <x-ui.input id="shift-name" name="name" value="{{ old('name') }}" maxlength="100" placeholder="Shift Pagi" autocomplete="off" />
                </x-common.form.field>

                <x-common.form.field label="Kasir & Penanggung Jawab" error="members" required
                    hint="Centang kasir di shift ini, lalu pilih satu penanggung jawab (PJ) yang membuka & menutup shift. Modal awal diisi PJ saat membuka.">
                    @if ($availableUsers->isEmpty())
                        <p class="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-500 dark:bg-white/3 dark:text-gray-400">
                            Semua pengguna aktif sedang tergabung di shift lain.
                        </p>
                    @else
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            @foreach ($availableUsers as $user)
                                <div class="flex items-center gap-3 rounded-lg border px-3 py-2.5 transition"
                                    x-bind:class="members.includes('{{ $user->id }}') ? 'border-brand-500 bg-brand-50 dark:bg-brand-500/10' : 'border-gray-200 dark:border-gray-800'">
                                    <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-3">
                                        <input type="checkbox" name="members[]" value="{{ $user->id }}" x-model="members"
                                            class="size-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900" />
                                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-sm font-semibold text-gray-600 dark:bg-white/5 dark:text-gray-300">{{ $initial($user->name) }}</span>
                                        <span class="min-w-0">
                                            <span class="block truncate text-sm font-medium text-gray-800 dark:text-white/90">{{ $user->name }}</span>
                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ User::ROLES[$user->role] ?? $user->role }}</span>
                                        </span>
                                    </label>
                                    {{-- Pilih PJ (aktif bila kasir dicentang) --}}
                                    <label class="flex shrink-0 items-center gap-1.5 text-xs font-medium"
                                        x-bind:class="members.includes('{{ $user->id }}') ? 'cursor-pointer text-gray-700 dark:text-gray-300' : 'cursor-not-allowed text-gray-300 dark:text-gray-600'">
                                        <input type="radio" name="supervisor_id" value="{{ $user->id }}" x-model="supervisor"
                                            x-bind:disabled="!members.includes('{{ $user->id }}')"
                                            class="size-4 border-gray-300 text-warning-500 focus:ring-warning-500/20 dark:border-gray-700 dark:bg-gray-900" />
                                        PJ
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <p data-error-for="supervisor_id" @class(['text-sm text-error-500 dark:text-error-400', 'hidden' => ! $errors->has('supervisor_id')])>{{ $errors->first('supervisor_id') }}</p>
                </x-common.form.field>
            </form>

            <x-slot:footer>
                <x-ui.button variant="outline" x-on:click="createOpen = false">Batal</x-ui.button>
                <x-ui.button type="submit" form="shift-create-form">Buat Shift</x-ui.button>
            </x-slot:footer>
        </x-common.modal>
    </div>
@endsection
