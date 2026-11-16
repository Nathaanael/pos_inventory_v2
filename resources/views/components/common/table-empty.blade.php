{{-- Baris "data kosong" untuk tabel. Props: colspan, title, description. --}}
@props([
    'colspan' => 1,
    'title' => 'Tidak ada data',
    'description' => null,
])
<tr>
    <td colspan="{{ $colspan }}" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
        <div class="flex flex-col items-center justify-center">
            <div class="mb-3 flex size-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                <x-svg.box class="size-6 text-gray-800 dark:text-white/90" />
            </div>
            <h4 class="text-base font-semibold text-gray-800 dark:text-white/90">{{ $title }}</h4>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $description ?? (request('q') ? 'Coba ubah kata pencarian atau filter.' : 'Belum ada data yang ditambahkan.') }}
            </p>
        </div>
    </td>
</tr>
