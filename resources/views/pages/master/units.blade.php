@extends('layouts.app')
@use('Illuminate\Support\Js')

@php
    $pageIds = $units->getCollection()->reject->trashed()->pluck('id')->values();
@endphp

@section('content')
    <x-common.page-breadcrumb pageTitle="Satuan" />

    <div x-data="crudPage({
            blank: { name: '' },
            routes: { store: @js(route('units.store')), update: @js(route('units.update', '__ID__')) },
            reopen: @js(\App\Support\CrudPage::reopen()),
        })">
        <x-common.table-card title="Daftar Satuan" :paginator="$units">
            <x-slot:actions>
                <x-ui.button x-on:click="create()">
                    <x-svg.plus class="size-5" />
                    Tambah Satuan
                </x-ui.button>
            </x-slot:actions>

            <x-slot:toolbar>
                @include('pages.master.partials.bulk-bar', [
                    'title' => 'Hapus Satuan Terpilih',
                    'message' => 'Apakah Anda yakin ingin menghapus satuan yang dipilih?',
                    'action' => route('units.bulk-destroy'),
                ])
            </x-slot:toolbar>

            <thead class="border-y border-gray-100 dark:border-gray-800">
                <tr>
                    <th class="table-th w-12" x-data="{ ids: @js($pageIds) }">
                        <x-ui.checkbox aria-label="Pilih semua" x-bind:checked="ids.length && ids.every(id => isSelected(id))" x-on:change="toggleAll(ids)" />
                    </th>
                    <th class="table-th min-w-56">Nama</th>
                    <th class="table-th w-16"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($units as $unit)
                    <tr class="table-row">
                        <td class="table-td">
                            @unless ($unit->trashed())
                                <x-ui.checkbox aria-label="Pilih {{ $unit->name }}" x-bind:checked="isSelected({{ $unit->id }})" x-on:change="toggle({{ $unit->id }})" />
                            @endunless
                        </td>
                        <td class="table-td">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-gray-800 dark:text-white/90">{{ $options[$unit->name] ?? $unit->name }}</span>
                                @if ($unit->trashed())
                                    <x-ui.badge size="sm" color="error">Dihapus</x-ui.badge>
                                @endif
                            </div>
                        </td>
                        <td class="table-td text-end">
                            <x-common.row-actions>
                                <x-ui.dropdown-menu.item x-on:click="edit({{ Js::from(['id' => $unit->id, 'name' => $unit->name]) }})">
                                    <x-svg.pencil /> Ubah
                                </x-ui.dropdown-menu.item>
                                @if ($unit->trashed())
                                    <x-ui.dropdown-menu.item x-on:click="confirm({ title: 'Pulihkan Satuan', message: 'Pulihkan satuan ini?', action: {{ Js::from(route('units.restore', $unit)) }}, method: 'POST', confirmLabel: 'Pulihkan', variant: 'default' })">
                                        <x-svg.arrow-left class="rtl:rotate-180" /> Pulihkan
                                    </x-ui.dropdown-menu.item>
                                    <x-ui.dropdown-menu.item variant="destructive" x-on:click="confirm({ title: 'Hapus Permanen Satuan', message: 'Data yang dihapus permanen tidak bisa dikembalikan. Lanjutkan?', action: {{ Js::from(route('units.force-delete', $unit)) }}, confirmLabel: 'Hapus Permanen' })">
                                        <x-svg.trash-bin /> Hapus Permanen
                                    </x-ui.dropdown-menu.item>
                                @else
                                    <x-ui.dropdown-menu.item variant="destructive" x-on:click="confirm({ title: 'Hapus Satuan', message: 'Apakah Anda yakin ingin menghapus satuan ini?', action: {{ Js::from(route('units.destroy', $unit)) }} })">
                                        <x-svg.trash-bin /> Hapus
                                    </x-ui.dropdown-menu.item>
                                @endif
                            </x-common.row-actions>
                        </td>
                    </tr>
                @empty
                    <x-common.table-empty colspan="3" title="Belum ada satuan" />
                @endforelse
            </tbody>
        </x-common.table-card>

        <x-common.modal show="modalOpen" close="close()">
            <x-slot:heading><span x-text="isEdit ? 'Ubah Satuan' : 'Tambah Satuan'"></span></x-slot:heading>

            <form id="unit-form" method="POST" x-bind:action="action" class="space-y-5">
                <x-common.crud-form-fields />

                <x-common.form.field label="Nama" for="unit-name" error="name" required>
                    <x-common.form.select id="unit-name" name="name" x-model="form.name" :options="$options" placeholder="Pilih opsi" required />
                </x-common.form.field>
            </form>

            <x-slot:footer>
                <x-common.crud-modal-footer form="unit-form" />
            </x-slot:footer>
        </x-common.modal>
    </div>
@endsection
