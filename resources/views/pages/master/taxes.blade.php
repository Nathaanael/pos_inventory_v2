@extends('layouts.app')
@use('Illuminate\Support\Js')
@use('App\Models\Tax')

{{-- Master Pajak & Biaya Layanan (modul FNB). --}}
@section('content')
    <x-common.page-breadcrumb pageTitle="Pajak & Biaya Layanan" />

    <div class="mb-6 flex items-start gap-3 rounded-xl border border-brand-100 bg-brand-50 px-5 py-4 text-sm text-brand-700 dark:border-brand-500/20 dark:bg-brand-500/10 dark:text-brand-400">
        <x-svg.info-circle class="mt-0.5 size-5 shrink-0" />
        <div>
            Pajak & biaya layanan aktif dihitung otomatis di layar kasir. Biaya layanan dihitung dari subtotal; pajak dihitung setelah biaya layanan.
            <strong>Exclusive</strong> ditambahkan ke tagihan, <strong>Inclusive</strong> dianggap sudah termasuk di harga barang.
            Barang yang punya <em>Pajak (%)</em> sendiri di Master Barang memakai tarif barangnya.
        </div>
    </div>

    <div x-data="crudPage({
            blank: { name: '', type: 'tax', rate: '', method: 'exclusive', applies_to: 'all', sort_order: 0, is_active: true },
            routes: { store: @js(route('taxes.store')), update: @js(route('taxes.update', '__ID__')) },
            reopen: @js(\App\Support\CrudPage::reopen()),
        })">
        <x-common.table-card title="Daftar Pajak & Biaya Layanan" :paginator="$taxes">
            <x-slot:actions>
                <x-ui.button x-on:click="create()">
                    <x-svg.plus class="size-5" />
                    Tambah
                </x-ui.button>
            </x-slot:actions>

            <thead class="border-y border-gray-100 dark:border-gray-800">
                <tr>
                    <th class="table-th min-w-44">Nama</th>
                    <th class="table-th min-w-32">Jenis</th>
                    <th class="table-th min-w-24">Persentase</th>
                    <th class="table-th min-w-32">Tipe</th>
                    <th class="table-th min-w-36">Berlaku untuk</th>
                    <th class="table-th min-w-20">Aktif</th>
                    <th class="table-th w-16"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($taxes as $tax)
                    <tr class="table-row">
                        <td class="table-td font-medium text-gray-800 dark:text-white/90">{{ $tax->name }}</td>
                        <td class="table-td"><x-ui.badge size="sm" :color="$tax->type === 'service' ? 'info' : 'warning'">{{ Tax::TYPES[$tax->type] ?? $tax->type }}</x-ui.badge></td>
                        <td class="table-td font-medium whitespace-nowrap">{{ rtrim(rtrim(number_format($tax->rate, 2, ',', '.'), '0'), ',') }}%</td>
                        <td class="table-td"><x-ui.badge size="sm" :color="$tax->method === 'inclusive' ? 'gray' : 'primary'">{{ ucfirst($tax->method) }}</x-ui.badge></td>
                        <td class="table-td whitespace-nowrap">{{ Tax::APPLIES_TO[$tax->applies_to] ?? $tax->applies_to }}</td>
                        <td class="table-td">
                            <form method="POST" action="{{ route('taxes.toggle', $tax) }}" x-data="{ on: @js($tax->is_active) }"
                                x-on:click="if ($event.target.closest('[role=switch]')) $nextTick(() => $el.requestSubmit())">
                                @csrf
                                @method('PATCH')
                                <x-ui.switch size="sm" model="on" aria-label="Aktif" />
                            </form>
                        </td>
                        <td class="table-td text-end">
                            <x-common.row-actions>
                                <x-ui.dropdown-menu.item x-on:click="edit({{ Js::from($tax->only(['id', 'name', 'type', 'rate', 'method', 'applies_to', 'sort_order', 'is_active'])) }})">
                                    <x-svg.pencil /> Ubah
                                </x-ui.dropdown-menu.item>
                                <x-ui.dropdown-menu.item variant="destructive" x-on:click="confirm({ title: 'Hapus Pajak / Biaya', message: 'Transaksi lama tetap menyimpan rincian pajaknya. Lanjutkan?', action: {{ Js::from(route('taxes.destroy', $tax)) }} })">
                                    <x-svg.trash-bin /> Hapus
                                </x-ui.dropdown-menu.item>
                            </x-common.row-actions>
                        </td>
                    </tr>
                @empty
                    <x-common.table-empty colspan="7" title="Belum ada pajak / biaya layanan"
                        description="Tambahkan mis. PB1 10% (Pajak, Exclusive) dan Service Charge 5% (Biaya Layanan, Makan di Tempat)." />
                @endforelse
            </tbody>
        </x-common.table-card>

        <x-common.modal show="modalOpen" close="close()" max-width="2xl">
            <x-slot:heading><span x-text="isEdit ? 'Ubah Pajak / Biaya Layanan' : 'Tambah Pajak / Biaya Layanan'"></span></x-slot:heading>

            <form id="tax-form" method="POST" x-bind:action="action" class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <x-common.crud-form-fields />

                <x-common.form.field label="Nama" for="tax-name" error="name" required>
                    <x-ui.input id="tax-name" name="name" x-model="form.name" maxlength="100" placeholder="mis. PB1, Service Charge" required
                        :aria-invalid="$errors->has('name') ? 'true' : null" />
                </x-common.form.field>

                <x-common.form.field label="Jenis" for="tax-type" error="type" required>
                    <x-common.form.select id="tax-type" name="type" x-model="form.type" :options="Tax::TYPES" required />
                </x-common.form.field>

                <x-common.form.field label="Persentase (%)" for="tax-rate" error="rate" required>
                    <div class="relative">
                        <x-ui.input type="number" id="tax-rate" name="rate" x-model="form.rate" min="0" max="100" step="0.01" placeholder="10" required class="pe-10"
                            :aria-invalid="$errors->has('rate') ? 'true' : null" />
                        <span class="pointer-events-none absolute end-4 top-1/2 -translate-y-1/2 text-sm text-gray-400">%</span>
                    </div>
                </x-common.form.field>

                <x-common.form.field label="Tipe" for="tax-method" error="method" required>
                    <x-common.form.select id="tax-method" name="method" x-model="form.method" :options="Tax::METHODS" required />
                </x-common.form.field>

                <x-common.form.field label="Berlaku untuk" for="tax-applies" error="applies_to" required hint="Service charge biasanya hanya untuk Makan di Tempat.">
                    <x-common.form.select id="tax-applies" name="applies_to" x-model="form.applies_to" :options="Tax::APPLIES_TO" required />
                </x-common.form.field>

                <x-common.form.field label="Urutan" for="tax-order" error="sort_order" hint="Urutan tampil di struk.">
                    <x-ui.input type="number" id="tax-order" name="sort_order" x-model="form.sort_order" min="0" />
                </x-common.form.field>

                <x-common.form.toggle name="is_active" model="form.is_active" label="Aktif" hint="Nonaktif = tidak dihitung di kasir." />
            </form>

            <x-slot:footer>
                <x-common.crud-modal-footer form="tax-form" />
            </x-slot:footer>
        </x-common.modal>
    </div>
@endsection
