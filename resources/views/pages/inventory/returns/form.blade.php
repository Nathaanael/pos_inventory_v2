@extends('layouts.app')
@use('App\Http\Controllers\Inventory\ReturnTransactionController')

@php
    $isEdit = $return->exists;
    $value = fn (string $field, $default = null) => old($field, $return->{$field} ?? $default);
    $invalid = fn (string $field) => $errors->has($field) ? 'true' : null;
@endphp

@section('content')
    <x-common.page-breadcrumb :pageTitle="$title">
        @if ($isEdit)
            <div class="flex items-center gap-2">
                <x-ui.button as="a" variant="outline" href="{{ route('return-transaction.pdf', $return) }}" target="_blank">
                    <x-svg.page class="size-5" /> Cetak PDF
                </x-ui.button>
                <x-ui.button variant="destructive"
                    x-data x-on:click="$dispatch('confirm', { title: 'Hapus Transaksi Retur', message: 'Apakah Anda yakin ingin menghapus retur ini?', action: {{ \Illuminate\Support\Js::from(route('return-transactions.destroy', $return)) }} })">
                    <x-svg.trash-bin class="size-5" /> Hapus
                </x-ui.button>
            </div>
        @endif
    </x-common.page-breadcrumb>

    @include('pages.inventory.partials.form-errors')

    <form method="POST" action="{{ $isEdit ? route('return-transactions.update', $return) : route('return-transactions.store') }}"
        x-data="returnForm({ catalog: @js($catalog), type: @js($value('type', 'supplier')), supplierId: @js($value('supplier_id')), rows: @js($lines) })">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="space-y-6">
            <x-common.component-card title="Informasi Retur">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-common.form.field label="Kode Retur" for="code" required :hint="$isEdit ? null : 'Dibuat otomatis saat disimpan'">
                        <x-ui.input id="code" value="{{ $code }}" readonly class="bg-gray-50 dark:bg-white/3" />
                    </x-common.form.field>

                    <x-common.form.field label="Tanggal" for="date" required>
                        <x-ui.input type="date" id="date" name="date" value="{{ \Illuminate\Support\Carbon::parse($value('date', now()))->format('Y-m-d') }}" required
                            :aria-invalid="$invalid('date')" />
                    </x-common.form.field>

                    <x-common.form.field label="Tipe Retur" for="type" required>
                        <x-common.form.select id="type" name="type" :options="ReturnTransactionController::TYPES" required
                            x-model="type" x-on:change="onHeaderChange()" />
                    </x-common.form.field>

                    <x-common.form.field for="supplier_id" required>
                        <label for="supplier_id" class="text-sm font-medium text-gray-700 dark:text-gray-400">
                            <span x-text="isSupplier ? 'Supplier (Tujuan Retur)' : 'Alokasi Stok ke Supplier'"></span><span class="text-error-500">*</span>
                        </label>
                        <x-common.form.select id="supplier_id" name="supplier_id" :options="$supplierOptions" placeholder="Pilih supplier" required
                            x-model="supplierId" x-on:change="onHeaderChange()" :aria-invalid="$invalid('supplier_id')" />
                    </x-common.form.field>

                    <x-common.form.field label="Nama Pelanggan" for="customer_name" required x-show="!isSupplier" x-cloak>
                        <x-ui.input id="customer_name" name="customer_name" value="{{ $value('customer_name') }}" x-bind:required="!isSupplier"
                            x-bind:disabled="isSupplier" :aria-invalid="$invalid('customer_name')" />
                    </x-common.form.field>

                    <x-common.form.field label="No. Referensi (Asal)" for="reference_code">
                        <x-ui.input id="reference_code" name="reference_code" value="{{ $value('reference_code') }}" maxlength="30" />
                    </x-common.form.field>

                    <x-common.form.field label="Total Harga Retur">
                        <x-ui.input x-bind:value="rupiah(total)" readonly class="bg-gray-50 dark:bg-white/3" />
                    </x-common.form.field>

                    <x-common.form.field label="Alasan Retur" for="reason" required class="sm:col-span-2">
                        <x-ui.textarea id="reason" name="reason" rows="3" required :aria-invalid="$invalid('reason')">{{ $value('reason') }}</x-ui.textarea>
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
                            <x-common.form.field label="Barang" required class="sm:col-span-2">
                                <x-common.form.search-select options="itemOptions" model="row.item_id" bind-name="`items[${index}][item_id]`"
                                    placeholder="Cari barang..." placeholder-expr="itemPlaceholder" required x-on:selected="onItemChange(row)" />
                            </x-common.form.field>
                            <x-common.form.field label="Stok">
                                <x-ui.input x-bind:value="stockLabel(row)" disabled />
                            </x-common.form.field>
                            <x-common.form.field label="Kuantitas Retur" required>
                                <x-ui.input type="number" min="1" x-bind:name="`items[${index}][quantity]`" x-model="row.quantity" required
                                    x-bind:aria-invalid="qtyError(row) ? 'true' : null" />
                                <p x-show="qtyError(row)" x-text="qtyError(row)" class="text-sm text-error-500"></p>
                            </x-common.form.field>
                            <x-common.form.field label="Harga" required>
                                <x-common.form.money model="row.price" bind-name="`items[${index}][price]`" />
                            </x-common.form.field>
                            <x-common.form.field label="Subtotal">
                                <x-ui.input x-bind:value="rupiah(subtotal(row))" readonly class="bg-gray-50 dark:bg-white/3" />
                            </x-common.form.field>
                            <x-common.form.field label="Sisa Stok">
                                <x-ui.input x-bind:value="stockAfterLabel(row)" disabled />
                            </x-common.form.field>
                            <x-common.form.field label="Kondisi" required>
                                <x-common.form.select :options="ReturnTransactionController::CONDITIONS" x-bind:name="`items[${index}][condition]`" x-model="row.condition" required />
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
            @if ($isEdit)
                <x-ui.button type="submit">Simpan</x-ui.button>
            @else
                <x-ui.button type="submit">Tambah</x-ui.button>
                <x-ui.button type="submit" variant="outline" name="create_another" value="1">Tambah &amp; buat lagi</x-ui.button>
            @endif
            <x-ui.button as="a" href="{{ route('return-transactions.index') }}" variant="outline">Batal</x-ui.button>
        </div>
    </form>
@endsection
