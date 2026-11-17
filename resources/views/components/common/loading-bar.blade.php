{{--
    Loading bar tipis di atas layar untuk request AJAX. Dikendalikan oleh Alpine.store('loadingBar')
    (resources/js/components/common/loading-bar.js); dipakai otomatis oleh ajaxForm.
--}}
@props([])
<div x-data x-show="$store.loadingBar.visible" x-cloak x-transition:leave.opacity.duration.200ms
    class="pointer-events-none fixed inset-x-0 top-0 z-999999 h-1" role="progressbar" aria-label="Memuat"
    x-bind:aria-valuenow="Math.round($store.loadingBar.progress)" aria-valuemin="0" aria-valuemax="100">
    <div class="bg-brand-500 dark:bg-brand-400 h-full rounded-e-full transition-[width] duration-200 ease-out"
        x-bind:style="{ width: $store.loadingBar.progress + '%' }"></div>
</div>
