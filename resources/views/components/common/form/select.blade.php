{{--
    <select> native dengan gaya input TailAdmin.
    Props: options (value => label), placeholder (opsi kosong), value (terpilih; untuk form non-Alpine).
    Atribut lain (name, x-model, required, ...) diteruskan ke <select>.
--}}
@props([
    'options' => [],
    'placeholder' => null,
    'value' => null,
])
<select {{ $attributes->merge(['class' => 'form-select']) }}>
    @if ($placeholder !== null)
        <option value="">{{ $placeholder }}</option>
    @endif
    @foreach ($options as $key => $label)
        <option value="{{ $key }}" @selected($value !== null && (string) $value === (string) $key)>{{ $label }}</option>
    @endforeach
</select>
