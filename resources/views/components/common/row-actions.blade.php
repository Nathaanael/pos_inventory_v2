{{--
    Menu aksi per baris (tombol titik tiga seperti "Basic Table 3"). Isi slot dengan <x-ui.dropdown-menu.item>.
    Menu di-teleport ke <body>, tetapi tetap berada di scope Alpine baris/halaman, jadi edit(...) / confirm(...) bisa dipanggil.
--}}
<x-ui.dropdown-menu>
    <x-ui.dropdown-menu.trigger aria-label="Aksi" class="inline-flex size-9 items-center justify-center rounded-lg text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">
        <x-svg.horizontal-dots class="size-5" />
    </x-ui.dropdown-menu.trigger>
    <x-ui.dropdown-menu.content align="end" class="min-w-44 p-2">
        {{ $slot }}
    </x-ui.dropdown-menu.content>
</x-ui.dropdown-menu>
