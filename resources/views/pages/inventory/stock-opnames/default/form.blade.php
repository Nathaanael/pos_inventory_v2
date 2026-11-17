@extends('layouts.app')

@php
    $isEdit = $opname->exists;
    $value = fn (string $field, $default = null) => old($field, $opname->{$field} ?? $default);
    $invalid = fn (string $field) => $errors->has($field) ? 'true' : null;
@endphp

@section('content')
    <x-common.page-breadcrumb :pageTitle="$title">
        @if ($isEdit)
            <x-ui.button as="a" variant="outline" href="{{ route('stock-opname.pdf', $opname) }}" target="_blank">
                <x-svg.page class="size-5" /> Cetak PDF
            </x-ui.button>
        @endif
    </x-common.page-breadcrumb>

    @include('pages.inventory.partials.form-errors')

    <form method="POST" action="{{ $isEdit ? route('stock-opnames.update', $opname) : route('stock-opnames.store') }}"
        x-data="opnameForm({ catalog: @js($catalog), rows: @js($lines) })">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="space-y-6">
            <x-common.component-card title="Informasi Sesi">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-common.form.field label="Kode Sesi" for="code" required :hint="$isEdit ? null : 'Dibuat otomatis saat disimpan'">
                        <x-ui.input id="code" value="{{ $code }}" readonly class="bg-gray-50 dark:bg-white/3" />
                    </x-common.form.field>

                    <x-common.form.field label="Nama Sesi" for="session_name" required>
                        <x-ui.input id="session_name" name="session_name" value="{{ $value('session_name') }}" required autofocus :aria-invalid="$invalid('session_name')" />
                    </x-common.form.field>

                    <x-common.form.field label="Tanggal" for="date" required class="sm:col-span-2">
                        <x-ui.input type="date" id="date" name="date" value="{{ \Illuminate\Support\Carbon::parse($value('date', now()))->format('Y-m-d') }}" required
                            :aria-invalid="$invalid('date')" />
                    </x-common.form.field>

                    <x-common.form.field label="Catatan" for="notes" class="sm:col-span-2">
                        <x-ui.textarea id="notes" name="notes" rows="3">{{ $value('notes') }}</x-ui.textarea>
                    </x-common.form.field>
                </div>
            </x-common.component-card>

            <x-common.component-card title="Barang">
                <template x-for="(row, index) in rows" :key="index">
                    <div class="rounded-2xl border border-gray-200 p-5 dark:border-gray-800">
                        <div class="mb-4 flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-500 dark:text-gray-400" x-text="`Barang ${index + 1}`"></span>
                            <x-ui.button size="icon-sm" variant="ghost" aria-label="Hapus barang" class="text-error-500 hover:text-error-600" x-on:click="remove(index)">
                                <x-svg.trash-bin class="size-5" />
                            </x-ui.button>
                        </div>
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3 xl:grid-cols-6">
                            <x-common.form.field label="Barang" required class="sm:col-span-3 xl:col-span-2">
                                <x-common.form.search-select options="itemOptions" model="row.item_id" bind-name="`items[${index}][item_id]`"
                                    placeholder="Cari barang..." x-on:selected="onItemChange(row)" />
                            </x-common.form.field>
                            <x-common.form.field label="Stok Sistem">
                                {{-- Stok desimal ditampilkan "1,25 kg" (bahan baku FNB). --}}
                                <x-ui.input x-bind:value="row.item_id ? qty(row.system_stock || 0) + unitSuffix(row) : ''" readonly class="bg-gray-50 dark:bg-white/3" />
                            </x-common.form.field>
                            <x-common.form.field label="Stok Fisik" required>
                                <x-ui.input type="number" min="0" step="any" inputmode="decimal" x-bind:name="`items[${index}][physical_stock]`"
                                    x-model="row.physical_stock" x-bind:placeholder="unit(row) ? 'dalam ' + unit(row) : '0'" />
                            </x-common.form.field>
                            <x-common.form.field label="Selisih">
                                <x-ui.input x-bind:value="difference(row) === null ? '' : signedQty(difference(row)) + unitSuffix(row)" readonly class="bg-gray-50 dark:bg-white/3" />
                            </x-common.form.field>
                            <x-common.form.field label="Status">
                                <div class="flex h-11 items-center">
                                    <span x-show="status(row)" x-text="status(row)" class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        x-bind:class="{
                                            'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500': status(row) === 'Cocok',
                                            'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-orange-400': status(row) === 'Lebih',
                                            'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500': status(row) === 'Kurang',
                                        }"></span>
                                    <span x-show="!status(row)" class="text-gray-400">-</span>
                                </div>
                            </x-common.form.field>
                            <x-common.form.field label="Catatan" class="sm:col-span-3 xl:col-span-6">
                                <x-ui.input x-bind:name="`items[${index}][notes]`" x-model="row.notes" maxlength="255" />
                            </x-common.form.field>
                        </div>
                    </div>
                </template>

                @include('pages.inventory.partials.line-errors')

                <x-ui.button variant="outline" x-on:click="add()">
                    <x-svg.plus class="size-5" />
                    Tambah Barang
                </x-ui.button>
            </x-common.component-card>
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-3">
            <x-ui.button type="submit" name="submit_mode" value="selesai">Selesaikan</x-ui.button>
            <x-ui.button type="submit" name="submit_mode" value="draft" variant="outline" formnovalidate
                class="border-warning-300 text-warning-600 hover:bg-warning-50 dark:border-warning-500/40 dark:text-orange-400">Draft</x-ui.button>
            <x-ui.button as="a" href="{{ route('stock-opnames.index') }}" variant="outline">Batal</x-ui.button>
        </div>
    </form>
@endsection
