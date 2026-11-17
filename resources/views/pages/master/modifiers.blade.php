@extends('layouts.app')
@use('Illuminate\Support\Js')

{{-- Master Modifier & Topping (modul FNB). --}}
@section('content')
    <x-common.page-breadcrumb pageTitle="Modifier & Topping" />

    <div class="mb-6 flex items-start gap-3 rounded-xl border border-brand-100 bg-brand-50 px-5 py-4 text-sm text-brand-700 dark:border-brand-500/20 dark:bg-brand-500/10 dark:text-brand-400">
        <x-svg.info-circle class="mt-0.5 size-5 shrink-0" />
        <div>
            Buat grup pilihan seperti <strong>Level Gula</strong>, <strong>Sajian (Hot/Ice)</strong>, atau <strong>Topping</strong> beserta tambahan harganya.
            Hubungkan grup ke menu pada halaman <strong>Master Barang</strong>.
        </div>
    </div>

    <div x-data="crudPage({
            blank: {
                name: '',
                min_select: 0,
                max_select: 1,
                is_required: false,
                sort_order: 0,
                is_active: true,
                modifiers: [{ id: null, name: '', price_delta: 0, is_active: true }]
            },
            routes: { store: @js(route('modifiers.store')), update: @js(route('modifiers.update', '__ID__')) },
            reopen: @js(\App\Support\CrudPage::reopen()),
        })">
        <x-common.table-card title="Daftar Grup Modifier" :paginator="$groups">
            <x-slot:actions>
                <x-ui.button x-on:click="create()">
                    <x-svg.plus class="size-5" />
                    Tambah
                </x-ui.button>
            </x-slot:actions>

            <thead class="border-y border-gray-100 dark:border-gray-800">
                <tr>
                    <th class="table-th min-w-44">Nama Grup</th>
                    <th class="table-th min-w-36">Aturan Pilih</th>
                    <th class="table-th min-w-64">Opsi Pilihan</th>
                    <th class="table-th min-w-24">Menu</th>
                    <th class="table-th min-w-20">Aktif</th>
                    <th class="table-th w-16"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($groups as $group)
                    @php
                        $payload = [
                            'id' => $group->id,
                            'name' => $group->name,
                            'min_select' => $group->min_select,
                            'max_select' => $group->max_select,
                            'is_required' => $group->is_required,
                            'sort_order' => $group->sort_order,
                            'is_active' => $group->is_active,
                            'modifiers' => $group->modifiers->map(fn ($m) => [
                                'id' => $m->id,
                                'name' => $m->name,
                                'price_delta' => (float) $m->price_delta,
                                'is_active' => $m->is_active,
                            ])->values()->all(),
                        ];
                    @endphp
                    <tr class="table-row">
                        <td class="table-td font-medium text-gray-800 dark:text-white/90">{{ $group->name }}</td>
                        <td class="table-td whitespace-nowrap">
                            @if ($group->is_required)
                                <x-ui.badge size="sm" color="warning">Wajib</x-ui.badge>
                            @else
                                <x-ui.badge size="sm" color="gray">Opsional</x-ui.badge>
                            @endif
                            <span class="ms-1 text-xs text-gray-500 dark:text-gray-400">Maks {{ $group->max_select }}</span>
                        </td>
                        <td class="table-td">
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($group->modifiers as $mod)
                                    <span class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2 py-0.5 text-xs text-gray-700 dark:bg-white/5 dark:text-gray-300 {{ ! $mod->is_active ? 'line-through opacity-50' : '' }}">
                                        {{ $mod->name }}
                                        @if ($mod->price_delta > 0)
                                            <span class="text-brand-600 dark:text-brand-400">+{{ number_format($mod->price_delta, 0, ',', '.') }}</span>
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                        </td>
                        <td class="table-td text-gray-500 dark:text-gray-400">{{ $group->items_count }} menu</td>
                        <td class="table-td">
                            <form method="POST" action="{{ route('modifiers.toggle', $group) }}" x-data="{ on: @js($group->is_active) }"
                                x-on:click="if ($event.target.closest('[role=switch]')) $nextTick(() => $el.requestSubmit())">
                                @csrf
                                @method('PATCH')
                                <x-ui.switch size="sm" model="on" aria-label="Aktif" />
                            </form>
                        </td>
                        <td class="table-td text-end">
                            <x-common.row-actions>
                                <x-ui.dropdown-menu.item x-on:click="edit({{ Js::from($payload) }})">
                                    <x-svg.pencil class="size-4" /> Ubah
                                </x-ui.dropdown-menu.item>
                                <x-ui.dropdown-menu.item variant="destructive" x-on:click="confirm({ title: 'Hapus Grup Modifier', message: {{ Js::from('Hapus grup ' . $group->name . '?') }}, action: {{ Js::from(route('modifiers.destroy', $group)) }} })">
                                    <x-svg.trash-bin /> Hapus
                                </x-ui.dropdown-menu.item>
                            </x-common.row-actions>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-10 text-center text-sm text-gray-400">Belum ada grup modifier.</td></tr>
                @endforelse
            </tbody>
        </x-common.table-card>

        <x-common.modal show="modalOpen" close="close()" max-width="xl">
            <x-slot:heading><span x-text="isEdit ? 'Ubah Grup Modifier' : 'Tambah Grup Modifier'"></span></x-slot:heading>

            <form id="modifier-form" method="POST" x-bind:action="action" class="space-y-4">
                <x-common.crud-form-fields />

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <x-common.form.field label="Nama Grup" for="mg_name" error="name" required>
                            <x-ui.input id="mg_name" name="name" x-model="form.name" placeholder="mis. Level Gula, Topping" required />
                        </x-common.form.field>
                    </div>
                    <x-common.form.field label="Maks Pilih" for="mg_max" error="max_select" required>
                        <x-ui.input id="mg_max" type="number" name="max_select" x-model="form.max_select" min="1" max="20" required />
                    </x-common.form.field>
                </div>

                <div class="flex flex-wrap items-center gap-6">
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="is_required" value="1" x-model="form.is_required" class="size-4 rounded border-gray-300 text-brand-500" />
                        Wajib dipilih kasir
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="is_active" value="1" x-model="form.is_active" class="size-4 rounded border-gray-300 text-brand-500" />
                        Aktif
                    </label>
                </div>

                <div class="border-t border-gray-200 pt-4 dark:border-gray-800">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Opsi Pilihan</span>
                        <button type="button" x-on:click="form.modifiers.push({ id: null, name: '', price_delta: 0, is_active: true })"
                            class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">
                            + Tambah Opsi
                        </button>
                    </div>
                    <div class="space-y-2">
                        <template x-for="(mod, idx) in form.modifiers" :key="idx">
                            <div class="flex items-center gap-2">
                                <input type="hidden" x-bind:name="`modifiers[${idx}][id]`" x-bind:value="mod.id ?? ''" />
                                <x-ui.input class="flex-1" type="text" x-bind:name="`modifiers[${idx}][name]`" x-model="mod.name" placeholder="Nama opsi (mis. Oat Milk)" required />
                                <x-ui.input class="w-32" type="number" x-bind:name="`modifiers[${idx}][price_delta]`" x-model="mod.price_delta" min="0" step="100" placeholder="Harga +" />
                                <input type="hidden" x-bind:name="`modifiers[${idx}][is_active]`" x-bind:value="mod.is_active ? 1 : 0" />
                                <button type="button" x-show="form.modifiers.length > 1" x-on:click="form.modifiers.splice(idx, 1)"
                                    class="rounded-lg p-2 text-gray-400 hover:bg-error-50 hover:text-error-500 dark:hover:bg-error-500/10" title="Hapus opsi">
                                    <x-svg.trash-bin class="size-4" />
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </form>

            <x-slot:footer>
                <x-common.crud-modal-footer form="modifier-form" />
            </x-slot:footer>
        </x-common.modal>
    </div>
@endsection
