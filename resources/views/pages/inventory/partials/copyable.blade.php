{{-- Teks yang bisa disalin dengan sekali klik (pengganti ->copyable() Filament). Variabel: $text. --}}
<button type="button" x-data="{ copied: false }"
    x-on:click.stop="navigator.clipboard?.writeText(@js($text)); copied = true; setTimeout(() => copied = false, 1500)"
    class="group inline-flex items-center gap-1.5 hover:text-brand-500" title="Klik untuk menyalin">
    <span>{{ $text }}</span>
    <x-svg.copy class="size-3.5 text-gray-400 opacity-0 transition-opacity group-hover:opacity-100" x-show="!copied" />
    <span x-show="copied" x-cloak class="text-xs font-normal text-success-600">Disalin</span>
</button>
