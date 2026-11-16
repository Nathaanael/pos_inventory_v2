@extends('layouts.app')
@use('Illuminate\Support\Js')

@php
    $pageIds = $categories->getCollection()->reject->trashed()->pluck('id')->values();
@endphp

@section('content')
    <x-common.page-breadcrumb pageTitle="Kategori" />

    <div x-data="crudPage({
            blank: { name: '', parent_id: '' },
            routes: { store: @js(route('categories.store')), update: @js(route('categories.update', '__ID__')) },
            reopen: @js(\App\Support\CrudPage::reopen()),
        })">
        <x-common.table-card title="Daftar Kategori" :paginator="$categories">
            <x-slot:actions>
                <x-ui.button x-on:click="create()">
                    <x-svg.plus class="size-5" />
                    Tambah Kategori
                </x-ui.button>
            </x-slot:actions>

            <x-slot:toolbar>
                @include('pages.master.partials.bulk-bar', [
                    'title' => 'Hapus Kategori Terpilih',
                    'message' => 'Apakah Anda yakin ingin menghapus kategori yang dipilih?',
                    'action' => route('categories.bulk-destroy'),
                ])
            </x-slot:toolbar>

            <thead class="border-y border-gray-100 dark:border-gray-800">
                <tr>
                    <th class="table-th w-12" x-data="{ ids: @js($pageIds) }">
                        <x-ui.checkbox aria-label="Pilih semua" x-bind:checked="ids.length && ids.every(id => isSelected(id))" x-on:change="toggleAll(ids)" />
                    </th>
                    <th class="table-th min-w-56">Nama</th>
                    <th class="table-th min-w-44">Kategori Induk</th>
                    <th class="table-th w-16"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($categories as $category)
                    <tr class="table-row">
                        <td class="table-td">
                            @unless ($category->trashed())
                                <x-ui.checkbox aria-label="Pilih {{ $category->name }}" x-bind:checked="isSelected({{ $category->id }})" x-on:change="toggle({{ $category->id }})" />
                            @endunless
                        </td>
                        <td class="table-td">
                            <div class="flex items-center gap-2">
                                <span class="font-medium whitespace-nowrap text-gray-800 dark:text-white/90">{{ $category->name }}</span>
                                @if ($category->trashed())
                                    <x-ui.badge size="sm" color="error">Dihapus</x-ui.badge>
                                @endif
                            </div>
                        </td>
                        <td class="table-td">
                            @if ($category->parent)
                                <x-ui.badge size="sm" color="primary">{{ $category->parent->name }}</x-ui.badge>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="table-td text-end">
                            <x-common.row-actions>
                                <x-ui.dropdown-menu.item x-on:click="edit({{ Js::from(['id' => $category->id, 'name' => $category->name, 'parent_id' => (string) $category->parent_id]) }})">
                                    <x-svg.pencil /> Ubah
                                </x-ui.dropdown-menu.item>
                                @if ($category->trashed())
                                    <x-ui.dropdown-menu.item x-on:click="confirm({ title: 'Pulihkan Kategori', message: {{ Js::from('Pulihkan kategori '.$category->name.'?') }}, action: {{ Js::from(route('categories.restore', $category)) }}, method: 'POST', confirmLabel: 'Pulihkan', variant: 'default' })">
                                        <x-svg.arrow-left class="rtl:rotate-180" /> Pulihkan
                                    </x-ui.dropdown-menu.item>
                                    <x-ui.dropdown-menu.item variant="destructive" x-on:click="confirm({ title: 'Hapus Permanen Kategori', message: 'Data yang dihapus permanen tidak bisa dikembalikan. Lanjutkan?', action: {{ Js::from(route('categories.force-delete', $category)) }}, confirmLabel: 'Hapus Permanen' })">
                                        <x-svg.trash-bin /> Hapus Permanen
                                    </x-ui.dropdown-menu.item>
                                @else
                                    <x-ui.dropdown-menu.item variant="destructive" x-on:click="confirm({ title: 'Hapus Kategori', message: 'Apakah Anda yakin ingin menghapus kategori ini?', action: {{ Js::from(route('categories.destroy', $category)) }} })">
                                        <x-svg.trash-bin /> Hapus
                                    </x-ui.dropdown-menu.item>
                                @endif
                            </x-common.row-actions>
                        </td>
                    </tr>
                @empty
                    <x-common.table-empty colspan="4" title="Belum ada kategori" />
                @endforelse
            </tbody>
        </x-common.table-card>

        <x-common.modal show="modalOpen" close="close()">
            <x-slot:heading><span x-text="isEdit ? 'Ubah Kategori' : 'Tambah Kategori'"></span></x-slot:heading>

            <form id="category-form" method="POST" x-bind:action="action" class="space-y-5">
                <x-common.crud-form-fields />

                <x-common.form.field label="Nama" for="category-name" error="name" required>
                    <x-ui.input id="category-name" name="name" x-model="form.name" required :aria-invalid="$errors->has('name') ? 'true' : null" />
                </x-common.form.field>

                <x-common.form.field label="Kategori Induk" for="category-parent" error="parent_id">
                    <select id="category-parent" name="parent_id" x-model="form.parent_id" class="form-select">
                        <option value="">Pilih opsi</option>
                        @foreach ($parentOptions as $id => $name)
                            <option value="{{ $id }}" x-bind:disabled="isEdit && editId == {{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </x-common.form.field>
            </form>

            <x-slot:footer>
                <x-common.crud-modal-footer form="category-form" />
            </x-slot:footer>
        </x-common.modal>
    </div>
@endsection
