@extends('layouts.kasir')

{{-- Member (panel kasir): tambah & ubah. Hapus hanya dari panel admin. --}}
@section('content')
    {{-- Kasir: form tambah langsung tampil di kiri, daftar member di kanan. --}}
    <div class="mx-auto max-w-7xl space-y-6">
        <div>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">Member</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Daftarkan pelanggan sebagai member dengan nama, nomor telepon, dan tanggal lahir.</p>
        </div>

        <x-main.members.manager :members="$members" :route-prefix="$routePrefix" :can-delete="$canDelete" layout="split" />
    </div>
@endsection
