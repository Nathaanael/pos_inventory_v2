{{--
    Select dengan pencarian (lihat resources/js/components/crud/search-select.js).
    Props:
      options      ekspresi Alpine yang menghasilkan array [{ value, label, description?, disabled? }]
      model        ekspresi Alpine yang diikat dua arah (mis. "row.item_id")
      name         nama field yang dikirim (statis) — atau bindName untuk nama dinamis
      bindName     ekspresi Alpine untuk nama field (mis. "`items[${index}][item_id]`")
      placeholder  teks saat belum ada pilihan (boleh ekspresi Alpine lewat placeholderExpr)
      invalid      tampilkan border merah
    Event: "selected" (detail.value) setelah pengguna memilih.
--}}
@props([
    'options',
    'model',
    'name' => null,
    'bindName' => null,
    'placeholder' => 'Pilih...',
    'placeholderExpr' => null,
    'invalid' => false,
    'required' => false,
])
<div x-data="searchSelect(() => {{ $options }})" x-modelable="value" x-model="{{ $model }}"
    x-on:click.outside="close()" x-on:keydown.escape.stop="close()"
    {{ $attributes->merge(['class' => 'relative w-full']) }}>
    <input type="hidden" @if ($name) name="{{ $name }}" @endif @if ($bindName) x-bind:name="{{ $bindName }}" @endif x-bind:value="value ?? ''">

    <button type="button" x-ref="trigger" x-on:click="toggle()" x-on:keydown.arrow-down.prevent="show()"
        aria-haspopup="listbox" x-bind:aria-expanded="open"
        class="form-select flex items-center text-start {{ $invalid ? 'border-error-500 dark:border-error-500/50' : '' }}">
        <span class="truncate" x-show="selected" x-text="selected?.label"></span>
        <span class="truncate text-gray-400" x-show="!selected" @if ($placeholderExpr) x-text="{{ $placeholderExpr }}" @endif>{{ $placeholder }}</span>
    </button>

    {{-- Validasi "wajib" bawaan browser untuk pilihan kosong --}}
    @if ($required)
        <input type="text" tabindex="-1" aria-hidden="true" required x-bind:value="value ?? ''"
            class="pointer-events-none absolute inset-x-0 bottom-0 h-px opacity-0" x-on:invalid="$refs.trigger.focus()">
    @endif

    <div x-show="open" x-cloak x-transition.opacity.duration.100ms
        class="absolute start-0 z-99 mt-1 w-full min-w-64 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-theme-lg dark:border-gray-800 dark:bg-gray-900">
        <div class="border-b border-gray-100 p-2 dark:border-gray-800">
            <input type="text" x-ref="search" x-model="query" placeholder="Cari..." autocomplete="off"
                x-on:input="highlighted = 0"
                x-on:keydown.arrow-down.prevent="move(1)" x-on:keydown.arrow-up.prevent="move(-1)"
                x-on:keydown.enter.prevent="choose(filtered[highlighted])" x-on:keydown.tab="close()"
                class="h-9 w-full rounded-lg border border-gray-200 bg-transparent px-3 text-sm text-gray-800 outline-hidden focus:border-brand-300 dark:border-gray-700 dark:text-white/90" />
        </div>
        <ul x-ref="list" role="listbox" class="custom-scrollbar max-h-60 overflow-y-auto p-1">
            <template x-for="(option, index) in filtered" :key="option.value">
                <li role="option" x-bind:data-index="index" x-bind:aria-selected="String(option.value) === String(value)"
                    x-on:click="choose(option)" x-on:mousemove="highlighted = index"
                    class="flex cursor-pointer flex-col rounded-lg px-3 py-2 text-sm"
                    x-bind:class="{
                        'bg-gray-100 dark:bg-white/5': highlighted === index,
                        'text-brand-500 dark:text-brand-400 font-medium': String(option.value) === String(value),
                        'text-gray-700 dark:text-gray-300': String(option.value) !== String(value),
                        'cursor-not-allowed opacity-40': option.disabled,
                    }">
                    <span x-text="option.label"></span>
                    <span x-show="option.description" x-text="option.description" class="text-xs text-gray-500 dark:text-gray-400"></span>
                </li>
            </template>
            <li x-show="!filtered.length" class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400">Tidak ditemukan</li>
        </ul>
    </div>
</div>
