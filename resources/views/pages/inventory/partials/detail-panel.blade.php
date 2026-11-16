{{-- Panel "Lihat" (slide-over) untuk halaman yang memakai listPage(). --}}
<x-common.slide-over show="panelOpen" max-width="6xl">
    <x-slot:heading><span x-text="panelTitle"></span></x-slot:heading>
    <div x-show="panelLoading" class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">Memuat...</div>
    <div x-show="!panelLoading" x-html="panelHtml"></div>
    <x-slot:footer>
        <x-ui.button variant="outline" x-on:click="panelOpen = false">Tutup</x-ui.button>
    </x-slot:footer>
</x-common.slide-over>
