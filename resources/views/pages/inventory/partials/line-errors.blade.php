{{-- Pesan validasi per baris barang (items.N.field) dari server. Variabel opsional: $label (default "Barang"). --}}
@foreach ($errors->getMessages() as $key => $messages)
    @if (str_starts_with($key, 'items.'))
        <p class="text-sm text-error-500">{{ $label ?? 'Barang' }} {{ (int) explode('.', $key)[1] + 1 }}: {{ $messages[0] }}</p>
    @endif
@endforeach
