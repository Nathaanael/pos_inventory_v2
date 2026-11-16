@extends('layouts.app')

@php
    $isEdit = $inbound->exists;
    $value = fn (string $field, $default = null) => old($field, $inbound->{$field} ?? $default);
    $invalid = fn (string $field) => $errors->has($field) ? 'true' : null;
@endphp

@section('content')
    <x-common.page-breadcrumb :pageTitle="$title" />

    @include('pages.inventory.partials.form-errors')

    <form method="POST" action="{{ $isEdit ? route('inbounds.update', $inbound) : route('inbounds.store') }}"
        x-data="inboundForm({ catalog: @js($catalog), supplierId: @js($value('supplier_id')), rows: @js($lines) })">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="space-y-6">
            <x-common.component-card title="Informasi Barang Masuk">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-common.form.field label="Kode Transaksi" for="code" required :hint="$isEdit ? null : 'Dibuat otomatis saat disimpan'">
                        <x-ui.input id="code" value="{{ $code }}" readonly class="bg-gray-50 dark:bg-white/3" />
                    </x-common.form.field>

                    <x-common.form.field label="Tanggal" for="date" required>
                        <x-ui.input type="date" id="date" name="date" value="{{ \Illuminate\Support\Carbon::parse($value('date', now()))->format('Y-m-d') }}" required
                            :aria-invalid="$invalid('date')" />
                    </x-common.form.field>

                    @if ($usesSuppliers)
                        <x-common.form.field label="Supplier" for="supplier_id" required>
                            <x-common.form.select id="supplier_id" name="supplier_id" :options="$supplierOptions" placeholder="Pilih supplier" required
                                x-model="supplierId" x-on:change="onSupplierChange()" :aria-invalid="$invalid('supplier_id')" />
                        </x-common.form.field>
                    @endif

                    <x-common.form.field :label="$usesSuppliers ? 'No. Invoice Supplier' : 'No. Invoice / Nota Pembelian'" for="supplier_invoice_no">
                        <x-ui.input id="supplier_invoice_no" name="supplier_invoice_no" value="{{ $value('supplier_invoice_no') }}" maxlength="50" />
                    </x-common.form.field>

                    <x-common.form.field label="No. Surat Jalan" for="delivery_note_no">
                        <x-ui.input id="delivery_note_no" name="delivery_note_no" value="{{ $value('delivery_note_no') }}" maxlength="50" />
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
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                            <x-common.form.field label="Barang" required>
                                <x-common.form.search-select options="itemOptions" model="row.item_id" bind-name="`items[${index}][item_id]`"
                                    placeholder="Pilih barang" required />
                            </x-common.form.field>
                            <x-common.form.field label="Kuantitas" required>
                                <x-ui.input type="number" min="1" x-bind:name="`items[${index}][quantity]`" x-model="row.quantity" required />
                            </x-common.form.field>
                            <x-common.form.field label="Harga Beli">
                                <x-ui.input x-bind:value="rupiah(price(row))" readonly class="bg-gray-50 dark:bg-white/3" />
                            </x-common.form.field>
                            <x-common.form.field label="Subtotal">
                                <x-ui.input x-bind:value="rupiah(subtotal(row))" readonly class="bg-gray-50 dark:bg-white/3" />
                            </x-common.form.field>
                        </div>
                    </div>
                </template>

                @include('pages.inventory.partials.line-errors')

                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <x-ui.button variant="outline" x-on:click="add()">
                        <x-svg.plus class="size-5" />
                        Tambah Barang
                    </x-ui.button>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Total Nominal: <span class="text-lg font-semibold text-gray-800 dark:text-white/90" x-text="rupiah(total)"></span>
                    </p>
                </div>
            </x-common.component-card>
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-3">
            @if ($isEdit)
                <x-ui.button type="submit">Simpan</x-ui.button>
            @else
                <x-ui.button type="submit">Tambah</x-ui.button>
                <x-ui.button type="submit" variant="outline" name="create_another" value="1">Tambah &amp; buat lagi</x-ui.button>
            @endif
            <x-ui.button as="a" href="{{ route('inbounds.index') }}" variant="outline">Batal</x-ui.button>
        </div>
    </form>
@endsection
