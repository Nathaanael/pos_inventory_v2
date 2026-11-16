@extends('layouts.app')
@use('Illuminate\Support\Js')
@use('Illuminate\Support\Facades\Storage')

@php
    $pageIds = $brands->getCollection()->pluck('id')->values();
@endphp

@section('content')
    <x-common.page-breadcrumb pageTitle="Merk" />

    <div x-data="crudPage({
            blank: { name: '', description: '', is_active: true, logo_url: '', remove_logo: false },
            routes: { store: @js(route('brands.store')), update: @js(route('brands.update', '__ID__')) },
            reopen: @js(\App\Support\CrudPage::reopen()),
        })">
        <x-common.table-card title="Daftar Merk" :paginator="$brands">
            <x-slot:actions>
                <x-ui.button x-on:click="create()">
                    <x-svg.plus class="size-5" />
                    Tambah Merk
                </x-ui.button>
            </x-slot:actions>

            <x-slot:toolbar>
                @include('pages.master.partials.bulk-bar', [
                    'title' => 'Hapus Merk Terpilih',
                    'message' => 'Apakah Anda yakin ingin menghapus merk yang dipilih?',
                    'action' => route('brands.bulk-destroy'),
                ])
            </x-slot:toolbar>

            <thead class="border-y border-gray-100 dark:border-gray-800">
                <tr>
                    <th class="table-th w-12" x-data="{ ids: @js($pageIds) }">
                        <x-ui.checkbox aria-label="Pilih semua" x-bind:checked="ids.length && ids.every(id => isSelected(id))" x-on:change="toggleAll(ids)" />
                    </th>
                    <th class="table-th min-w-56">Nama</th>
                    <th class="table-th min-w-24">Logo</th>
                    <th class="table-th min-w-24">Aktif</th>
                    <th class="table-th w-16"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($brands as $brand)
                    @php $logoUrl = $brand->logo ? Storage::disk('public')->url($brand->logo) : ''; @endphp
                    <tr class="table-row">
                        <td class="table-td">
                            <x-ui.checkbox aria-label="Pilih {{ $brand->name }}" x-bind:checked="isSelected({{ $brand->id }})" x-on:change="toggle({{ $brand->id }})" />
                        </td>
                        <td class="table-td font-medium text-gray-800 dark:text-white/90">{{ $brand->name }}</td>
                        <td class="table-td">
                            @if ($logoUrl)
                                <img src="{{ $logoUrl }}" alt="{{ $brand->name }}" class="size-10 rounded-lg border border-gray-100 object-contain dark:border-gray-800" />
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="table-td">
                            @include('pages.master.partials.boolean-icon', ['value' => $brand->is_active])
                        </td>
                        <td class="table-td text-end">
                            <x-common.row-actions>
                                <x-ui.dropdown-menu.item x-on:click="edit({{ Js::from([...$brand->only(['id', 'name', 'description', 'is_active']), 'logo_url' => $logoUrl]) }})">
                                    <x-svg.pencil /> Ubah
                                </x-ui.dropdown-menu.item>
                                <x-ui.dropdown-menu.item variant="destructive" x-on:click="confirm({ title: 'Hapus Merk', message: 'Apakah Anda yakin ingin menghapus merk ini?', action: {{ Js::from(route('brands.destroy', $brand)) }} })">
                                    <x-svg.trash-bin /> Hapus
                                </x-ui.dropdown-menu.item>
                            </x-common.row-actions>
                        </td>
                    </tr>
                @empty
                    <x-common.table-empty colspan="5" title="Belum ada merk" />
                @endforelse
            </tbody>
        </x-common.table-card>

        <x-common.modal show="modalOpen" close="close()">
            <x-slot:heading><span x-text="isEdit ? 'Ubah Merk' : 'Tambah Merk'"></span></x-slot:heading>

            <form id="brand-form" method="POST" x-bind:action="action" enctype="multipart/form-data" class="space-y-5">
                <x-common.crud-form-fields />

                <x-common.form.field label="Nama" for="brand-name" error="name" required>
                    <x-ui.input id="brand-name" name="name" x-model="form.name" required :aria-invalid="$errors->has('name') ? 'true' : null" />
                </x-common.form.field>

                <x-common.form.field label="Logo" for="brand-logo" error="logo">
                    <div x-show="form.logo_url && !form.remove_logo" class="mb-2 flex items-center gap-3">
                        <img x-bind:src="form.logo_url" alt="Logo" class="size-14 rounded-lg border border-gray-200 object-contain dark:border-gray-800" />
                        <x-ui.button size="xs" variant="outline" x-on:click="form.remove_logo = true">Hapus logo</x-ui.button>
                    </div>
                    <input type="hidden" name="remove_logo" x-bind:value="form.remove_logo ? 1 : 0">
                    <x-ui.file-input id="brand-logo" name="logo" accept="image/*" />
                </x-common.form.field>

                <x-common.form.field label="Deskripsi" for="brand-description" error="description">
                    <x-ui.textarea id="brand-description" name="description" x-model="form.description" rows="3" />
                </x-common.form.field>

                <x-common.form.toggle name="is_active" model="form.is_active" label="Aktif" />
            </form>

            <x-slot:footer>
                <x-common.crud-modal-footer form="brand-form" />
            </x-slot:footer>
        </x-common.modal>
    </div>
@endsection
