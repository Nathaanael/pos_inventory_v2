@extends('layouts.app')
@use('Illuminate\Support\Js')

@php
    $pageIds = $suppliers->getCollection()->pluck('id')->values();
@endphp

@section('content')
    <x-common.page-breadcrumb pageTitle="Supplier" />

    <div x-data="crudPage({
            blank: { code: @js($nextCode), name: '', phone: '', address: '' },
            routes: { store: @js(route('suppliers.store')), update: @js(route('suppliers.update', '__ID__')) },
            reopen: @js(\App\Support\CrudPage::reopen()),
        })">
        <x-common.table-card title="Daftar Supplier" :paginator="$suppliers">
            <x-slot:actions>
                <x-ui.button x-on:click="create()">
                    <x-svg.plus class="size-5" />
                    Tambah Supplier
                </x-ui.button>
            </x-slot:actions>

            <x-slot:toolbar>
                @include('pages.master.partials.bulk-bar', [
                    'title' => 'Hapus Supplier Terpilih',
                    'message' => 'Apakah Anda yakin ingin menghapus supplier yang dipilih?',
                    'action' => route('suppliers.bulk-destroy'),
                ])
            </x-slot:toolbar>

            <thead class="border-y border-gray-100 dark:border-gray-800">
                <tr>
                    <th class="table-th w-12" x-data="{ ids: @js($pageIds) }">
                        <x-ui.checkbox aria-label="Pilih semua" x-bind:checked="ids.length && ids.every(id => isSelected(id))" x-on:change="toggleAll(ids)" />
                    </th>
                    <th class="table-th min-w-32">Kode</th>
                    <th class="table-th min-w-56">Nama</th>
                    <th class="table-th w-16"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($suppliers as $supplier)
                    <tr class="table-row">
                        <td class="table-td">
                            <x-ui.checkbox aria-label="Pilih {{ $supplier->name }}" x-bind:checked="isSelected({{ $supplier->id }})" x-on:change="toggle({{ $supplier->id }})" />
                        </td>
                        <td class="table-td whitespace-nowrap">{{ $supplier->code }}</td>
                        <td class="table-td font-medium text-gray-800 dark:text-white/90">{{ $supplier->name }}</td>
                        <td class="table-td text-end">
                            <x-common.row-actions>
                                <x-ui.dropdown-menu.item x-on:click="edit({{ Js::from($supplier->only(['id', 'code', 'name', 'phone', 'address'])) }})">
                                    <x-svg.pencil /> Ubah
                                </x-ui.dropdown-menu.item>
                                <x-ui.dropdown-menu.item variant="destructive" x-on:click="confirm({ title: 'Hapus Supplier', message: 'Apakah Anda yakin ingin menghapus supplier ini?', action: {{ Js::from(route('suppliers.destroy', $supplier)) }} })">
                                    <x-svg.trash-bin /> Hapus
                                </x-ui.dropdown-menu.item>
                            </x-common.row-actions>
                        </td>
                    </tr>
                @empty
                    <x-common.table-empty colspan="4" title="Belum ada supplier" />
                @endforelse
            </tbody>
        </x-common.table-card>

        <x-common.modal show="modalOpen" close="close()">
            <x-slot:heading><span x-text="isEdit ? 'Ubah Supplier' : 'Tambah Supplier'"></span></x-slot:heading>

            <form id="supplier-form" method="POST" x-bind:action="action" class="space-y-5">
                <x-common.crud-form-fields />

                <x-common.form.field label="Kode" for="supplier-code" required hint="Dibuat otomatis">
                    <x-ui.input id="supplier-code" x-model="form.code" readonly class="bg-gray-50 dark:bg-white/3" />
                </x-common.form.field>

                <x-common.form.field label="Nama Supplier" for="supplier-name" error="name" required>
                    <x-ui.input id="supplier-name" name="name" x-model="form.name" maxlength="150" placeholder="Masukkan nama supplier" required autofocus
                        :aria-invalid="$errors->has('name') ? 'true' : null" />
                </x-common.form.field>

                {{-- Section "Informasi Kontak" (collapsible, tertutup) --}}
                <div x-data="{ openContact: {{ $errors->hasAny(['phone', 'address']) ? 'true' : 'false' }} }"
                    class="rounded-2xl border border-gray-200 dark:border-gray-800">
                    <button type="button" x-on:click="openContact = !openContact" class="flex w-full items-center justify-between gap-3 px-5 py-4 text-start">
                        <span>
                            <span class="block text-sm font-medium text-gray-800 dark:text-white/90">Informasi Kontak</span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">Opsional — isi jika diperlukan</span>
                        </span>
                        <x-svg.chevron-down class="size-5 text-gray-500 transition-transform" x-bind:class="openContact && 'rotate-180'" />
                    </button>
                    <div x-show="openContact" x-transition x-cloak class="space-y-5 border-t border-gray-200 px-5 py-5 dark:border-gray-800">
                        <x-common.form.field label="Telepon" for="supplier-phone" error="phone">
                            <x-ui.input type="tel" id="supplier-phone" name="phone" x-model="form.phone" placeholder="08xxxxxxxxxx" maxlength="20"
                                :aria-invalid="$errors->has('phone') ? 'true' : null" />
                        </x-common.form.field>
                        <x-common.form.field label="Alamat" for="supplier-address" error="address">
                            <x-ui.textarea id="supplier-address" name="address" x-model="form.address" rows="3" placeholder="Alamat lengkap supplier" />
                        </x-common.form.field>
                    </div>
                </div>
            </form>

            <x-slot:footer>
                <x-common.crud-modal-footer form="supplier-form" />
            </x-slot:footer>
        </x-common.modal>
    </div>
@endsection
