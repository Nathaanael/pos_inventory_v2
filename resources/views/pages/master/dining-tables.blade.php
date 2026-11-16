@extends('layouts.app')
@use('Illuminate\Support\Js')

@section('content')
    <x-common.page-breadcrumb pageTitle="Master Meja" />

    <div x-data="crudPage({
            blank: { number: '', name: '', capacity: '', is_active: true },
            routes: { store: @js(route('meja.store')), update: @js(route('meja.update', '__ID__')) },
            reopen: @js(\App\Support\CrudPage::reopen()),
        })">
        <x-common.table-card title="Daftar Meja" :paginator="$tables">
            <x-slot:filters>
                <x-common.form.select name="is_active" class="h-10 sm:w-40" :value="request('is_active')" placeholder="Semua status"
                    :options="['1' => 'Aktif', '0' => 'Tidak aktif']" x-data x-on:change="$el.form.requestSubmit()" aria-label="Filter aktif" />
            </x-slot:filters>

            <x-slot:actions>
                <x-ui.button x-on:click="create()">
                    <x-svg.plus class="size-5" />
                    Tambah Meja
                </x-ui.button>
            </x-slot:actions>

            <thead class="border-y border-gray-100 dark:border-gray-800">
                <tr>
                    <th class="table-th min-w-28">Nomor</th>
                    <th class="table-th min-w-44">Nama</th>
                    <th class="table-th min-w-24">Kursi</th>
                    <th class="table-th min-w-28">Status</th>
                    <th class="table-th min-w-24">Aktif</th>
                    <th class="table-th w-16"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($tables as $table)
                    <tr class="table-row">
                        <td class="table-td font-semibold text-gray-800 dark:text-white/90">{{ $table->number }}</td>
                        <td class="table-td">{{ $table->name ?: '-' }}</td>
                        <td class="table-td whitespace-nowrap">{{ $table->capacity ? $table->capacity.' kursi' : '-' }}</td>
                        <td class="table-td">
                            <x-ui.badge size="sm" :color="$table->active_orders_count > 0 ? 'success' : 'error'">
                                {{ $table->active_orders_count > 0 ? 'Terisi' : 'Kosong' }}
                            </x-ui.badge>
                        </td>
                        <td class="table-td">
                            {{-- ToggleColumn: langsung simpan saat diklik --}}
                            <form method="POST" action="{{ route('meja.toggle', $table) }}" x-data="{ on: @js($table->is_active) }"
                                x-on:click="if ($event.target.closest('[role=switch]')) $nextTick(() => $el.requestSubmit())">
                                @csrf
                                @method('PATCH')
                                <x-ui.switch size="sm" model="on" aria-label="Aktif" />
                            </form>
                        </td>
                        <td class="table-td text-end">
                            <x-common.row-actions>
                                <x-ui.dropdown-menu.item x-on:click="edit({{ Js::from($table->only(['id', 'number', 'name', 'capacity', 'is_active'])) }})">
                                    <x-svg.pencil /> Ubah
                                </x-ui.dropdown-menu.item>
                                <x-ui.dropdown-menu.item variant="destructive" x-on:click="confirm({ title: 'Hapus Meja', message: 'Riwayat penjualan di meja ini tetap tersimpan, hanya tidak lagi terhubung ke meja.', action: {{ Js::from(route('meja.destroy', $table)) }} })">
                                    <x-svg.trash-bin /> Hapus
                                </x-ui.dropdown-menu.item>
                            </x-common.row-actions>
                        </td>
                    </tr>
                @empty
                    <x-common.table-empty colspan="6" title="Belum ada meja"
                        description="Tambahkan meja beserta nomornya agar bisa dipilih di kasir." />
                @endforelse
            </tbody>
        </x-common.table-card>

        <x-common.modal show="modalOpen" close="close()" max-width="2xl">
            <x-slot:heading><span x-text="isEdit ? 'Ubah Meja' : 'Tambah Meja'"></span></x-slot:heading>

            <form id="table-form" method="POST" x-bind:action="action" class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <x-common.crud-form-fields />

                <x-common.form.field label="Nomor Meja" for="table-number" error="number" required>
                    <x-ui.input id="table-number" name="number" x-model="form.number" maxlength="20" placeholder="mis. T1 atau 12" required
                        :aria-invalid="$errors->has('number') ? 'true' : null" />
                </x-common.form.field>

                <x-common.form.field label="Nama Meja" for="table-name" error="name">
                    <x-ui.input id="table-name" name="name" x-model="form.name" maxlength="100" placeholder="mis. Teras depan, VIP 1" />
                </x-common.form.field>

                <x-common.form.field label="Jumlah Kursi" for="table-capacity" error="capacity">
                    <x-ui.input type="number" id="table-capacity" name="capacity" x-model="form.capacity" min="1" max="100" placeholder="4"
                        :aria-invalid="$errors->has('capacity') ? 'true' : null" />
                </x-common.form.field>

                <x-common.form.toggle name="is_active" model="form.is_active" label="Aktif" hint="Meja nonaktif tidak muncul di kasir." class="sm:pt-7" />
            </form>

            <x-slot:footer>
                <x-common.crud-modal-footer form="table-form" />
            </x-slot:footer>
        </x-common.modal>
    </div>
@endsection
