{{--
    Isi panel "Lihat" transaksi (pengganti *ViewForm Filament, hanya-baca).
    Variabel:
      $fields      ['Label' => 'nilai', ...]  (nilai boleh HtmlString)
      $notesLabel / $notes   teks panjang (opsional)
      $linesTitle  judul tabel barang
      $columns     ['Barang', 'Kuantitas', ...]
      $rows        [[sel, sel, ...], ...]
      $footer      [['label' => 'Total', 'value' => 'Rp ...'], ...] (opsional)
--}}
<div class="space-y-6">
    <div class="rounded-2xl border border-gray-200 p-5 dark:border-gray-800">
        <h4 class="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">Informasi Utama</h4>
        <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($fields as $label => $value)
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                    <dd class="mt-0.5 text-sm font-medium text-gray-800 dark:text-white/90">{{ filled((string) $value) ? $value : '-' }}</dd>
                </div>
            @endforeach
            @isset($notesLabel)
                <div class="sm:col-span-2 lg:col-span-3">
                    <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $notesLabel }}</dt>
                    <dd class="mt-0.5 text-sm whitespace-pre-line text-gray-800 dark:text-white/90">{{ $notes ?: '-' }}</dd>
                </div>
            @endisset
        </dl>
    </div>

    <div class="rounded-2xl border border-gray-200 dark:border-gray-800">
        <div class="px-5 py-4">
            <h4 class="text-base font-semibold text-gray-800 dark:text-white/90">{{ $linesTitle ?? 'Daftar Barang' }}</h4>
        </div>
        <div class="custom-scrollbar overflow-x-auto">
            <table class="min-w-full">
                <thead class="border-y border-gray-100 dark:border-gray-800">
                    <tr>
                        @foreach ($columns as $column)
                            <th class="table-th">{{ $column }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($rows as $row)
                        <tr>
                            @foreach ($row as $i => $cell)
                                <td @class(['table-td whitespace-nowrap', 'font-medium text-gray-800 dark:text-white/90' => $i === 0])>{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) }}" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada barang.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if (! empty($footer))
                    <tfoot class="border-t border-gray-200 dark:border-gray-800">
                        @foreach ($footer as $line)
                            <tr>
                                <td colspan="{{ count($columns) - 1 }}" class="table-td text-end font-medium">{{ $line['label'] }}</td>
                                <td class="table-td font-semibold whitespace-nowrap text-gray-800 dark:text-white/90">{{ $line['value'] }}</td>
                            </tr>
                        @endforeach
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
