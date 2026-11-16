{{--
    Saklar boolean (pengganti Toggle Filament) yang ikut terkirim bersama form (nilai 1/0).
    Props: name, model (ekspresi Alpine boolean), label, hint, disabled.
--}}
@props([
    'name',
    'model',
    'label' => null,
    'hint' => null,
    'disabled' => false,
])
<div {{ $attributes->merge(['class' => 'flex flex-col gap-1.5']) }}>
    <label class="flex w-fit cursor-pointer items-center gap-3 text-sm font-medium text-gray-700 dark:text-gray-400">
        <x-ui.switch :model="$model" :disabled="$disabled" />
        @if ($label)
            <span>{{ $label }}</span>
        @endif
    </label>
    <input type="hidden" name="{{ $name }}" x-bind:value="{{ $model }} ? 1 : 0">
    @if ($hint)
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</p>
    @endif
</div>
