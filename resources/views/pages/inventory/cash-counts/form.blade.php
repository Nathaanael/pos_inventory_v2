@extends('layouts.app')

{{-- Cek dadakan uang di laci. Angka sistem sengaja tidak ditampilkan (blind count). --}}
@section('content')
    <x-common.page-breadcrumb pageTitle="Hitung Kas" />

    @include('pages.inventory.partials.form-errors')

    <div class="mx-auto max-w-2xl">
        <x-common.component-card title="Cek Dadakan Kas — {{ $shift->user?->name ?? '-' }}"
            desc="Shift dibuka {{ $shift->opened_at->format('d/m/Y H:i') }}. Hitung uang di laci; angka sistem dan selisih tampil setelah disimpan.">
            <form method="POST" action="{{ route('cash-counts.store', $shift) }}" class="space-y-5"
                x-data="ajaxForm()" x-on:submit.prevent="submit">
                @csrf

                <x-main.cash-count.denomination-input id-prefix="spot" />

                <x-common.form.field label="Catatan (Opsional)" for="notes">
                    <x-ui.textarea id="notes" name="notes" rows="2" maxlength="500" placeholder="mis. Dihitung bersama kasir">{{ old('notes') }}</x-ui.textarea>
                </x-common.form.field>

                <div class="flex flex-wrap justify-end gap-3">
                    <x-ui.button as="a" href="{{ route('cash-counts.index') }}" variant="outline">Batal</x-ui.button>
                    <x-ui.button type="submit" x-bind:disabled="busy">
                        <span x-text="busy ? 'Menyimpan…' : 'Simpan Hitung Kas'">Simpan Hitung Kas</span>
                    </x-ui.button>
                </div>
            </form>
        </x-common.component-card>
    </div>
@endsection
