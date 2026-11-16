@extends('layouts.fullscreen-layout')

{{-- Tampil untuk kasir selama admin belum menyelesaikan setup toko (lihat EnsureStoreIsSetUp). --}}
@section('content')
    <div class="flex min-h-screen items-center justify-center bg-gray-50 p-6 dark:bg-gray-900">
        <div class="w-full max-w-md rounded-3xl border border-gray-200 bg-white p-8 text-center shadow-theme-md dark:border-gray-800 dark:bg-white/3">
            <span class="mx-auto mb-5 flex size-16 items-center justify-center rounded-full bg-warning-50 text-warning-500 dark:bg-warning-500/15">
                <x-svg.info-circle class="size-8" />
            </span>
            <h1 class="text-xl font-semibold text-gray-800 dark:text-white/90">Toko belum disiapkan</h1>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Hubungi admin untuk menyelesaikan setup toko. Setelah selesai, tekan <strong>Cek lagi</strong> untuk mulai berjualan.
            </p>
            <div class="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:justify-center">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-ui.button type="submit" variant="outline" class="w-full sm:min-w-32">Keluar</x-ui.button>
                </form>
                <x-ui.button as="a" href="{{ route('kasir.belum-siap') }}" class="sm:min-w-32">Cek lagi</x-ui.button>
            </div>
        </div>
    </div>
@endsection
