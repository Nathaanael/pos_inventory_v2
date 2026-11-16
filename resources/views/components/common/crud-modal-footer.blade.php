{{--
    Tombol footer modal tambah/ubah: Batal, "Simpan & buat lagi" (hanya saat tambah), Simpan.
    Props: form (id form), createAnother (tampilkan tombol "Simpan & buat lagi").
--}}
@props(['form', 'createAnother' => true])
<x-ui.button variant="outline" x-on:click="close()">Batal</x-ui.button>
@if ($createAnother)
    <x-ui.button variant="outline" type="submit" form="{{ $form }}" name="create_another" value="1" x-show="!isEdit">Simpan &amp; buat lagi</x-ui.button>
@endif
<x-ui.button type="submit" form="{{ $form }}">Simpan</x-ui.button>
