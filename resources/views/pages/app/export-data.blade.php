@extends('layouts.app')
@use('Illuminate\Support\Js')
@use('App\Http\Controllers\App\ExportController')

{{-- Export Data (dulu App\Filament\Pages\ExportData). --}}
@section('content')
    <x-common.page-breadcrumb pageTitle="Export Data" />

    <div class="space-y-6">
        <form method="POST" action="{{ route('export-data.store') }}">
            @csrf
            <x-common.component-card title="Pilih Laporan & Periode" desc="Pilih jenis laporan yang ingin diexport dan tentukan periode data.">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-common.form.field label="Jenis Laporan" for="export_type" required class="sm:col-span-2">
                        <x-common.form.select id="export_type" name="export_type" :options="ExportController::TYPES" placeholder="Pilih jenis laporan..."
                            :value="old('export_type')" required :aria-invalid="$errors->has('export_type') ? 'true' : null" />
                    </x-common.form.field>
                    <x-common.form.field label="Tanggal Mulai" for="start_date" required>
                        <x-ui.input type="date" id="start_date" name="start_date" value="{{ old('start_date') }}" max="{{ now()->format('Y-m-d') }}" required
                            :aria-invalid="$errors->has('start_date') ? 'true' : null" />
                    </x-common.form.field>
                    <x-common.form.field label="Tanggal Akhir" for="end_date" required>
                        <x-ui.input type="date" id="end_date" name="end_date" value="{{ old('end_date') }}" max="{{ now()->format('Y-m-d') }}" required
                            :aria-invalid="$errors->has('end_date') ? 'true' : null" />
                    </x-common.form.field>
                </div>
                <div class="flex flex-wrap items-center gap-4">
                    <x-ui.button type="submit" size="lg" x-data="{ busy: false }" x-on:click="$el.form.checkValidity() && setTimeout(() => busy = true)" x-bind:disabled="busy">
                        <x-svg.arrow-down class="size-5" />
                        <span x-text="busy ? 'Memproses...' : 'Proses Export'">Proses Export</span>
                    </x-ui.button>
                    <p class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                        <x-svg.bell class="size-4" /> Anda akan mendapat notifikasi ketika file siap didownload.
                    </p>
                </div>
            </x-common.component-card>
        </form>

        <div x-data="listPage()">
            <x-common.table-card title="Riwayat Export" description="Daftar file export yang pernah dibuat" :search="false" :paginator="$files">
                <x-slot:actions>
                    <form method="GET" class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                        <label for="per_page" class="whitespace-nowrap">Tampilkan</label>
                        <x-common.form.select id="per_page" name="per_page" class="h-10 w-20" :options="[5 => 5, 10 => 10, 25 => 25, 50 => 50]" :value="$perPage"
                            x-data x-on:change="$el.form.requestSubmit()" />
                        <span class="whitespace-nowrap">per halaman</span>
                    </form>
                </x-slot:actions>

                <thead class="border-y border-gray-100 dark:border-gray-800">
                    <tr>
                        <th class="table-th min-w-72">Nama File</th>
                        <th class="table-th min-w-24">Ukuran</th>
                        <th class="table-th min-w-40">Dibuat</th>
                        <th class="table-th w-16"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($files as $file)
                        <tr class="table-row">
                            <td class="table-td">
                                <div class="flex items-center gap-3">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500">
                                        <x-svg.file class="size-5" />
                                    </span>
                                    <span class="font-medium break-all text-gray-800 dark:text-white/90">{{ $file['name'] }}</span>
                                </div>
                            </td>
                            <td class="table-td whitespace-nowrap">{{ $file['size'] }}</td>
                            <td class="table-td whitespace-nowrap">{{ $file['date'] }}</td>
                            <td class="table-td text-end">
                                <x-common.row-actions>
                                    <x-ui.dropdown-menu.item to="{{ $file['url'] }}">
                                        <x-svg.arrow-down /> Download
                                    </x-ui.dropdown-menu.item>
                                    <x-ui.dropdown-menu.item variant="destructive" x-on:click="confirm({ title: 'Hapus File', message: {{ Js::from('Hapus file '.$file['name'].'?') }}, action: {{ Js::from(route('export-data.destroy', $file['name'])) }} })">
                                        <x-svg.trash-bin /> Hapus
                                    </x-ui.dropdown-menu.item>
                                </x-common.row-actions>
                            </td>
                        </tr>
                    @empty
                        <x-common.table-empty colspan="4" title="Belum ada file export" description="File hasil export akan muncul di sini." />
                    @endforelse
                </tbody>
            </x-common.table-card>
        </div>
    </div>
@endsection
