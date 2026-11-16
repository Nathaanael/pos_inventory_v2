{{-- Isi panel "Lihat" barang (pengganti ItemViewForm Filament, hanya-baca). Dimuat lewat fetch dari ItemController@show. --}}
@php
    $rupiah = fn ($value) => 'Rp '.number_format((float) ($value ?? 0), 0, ',', '.');
    $sections = [
        'Informasi Utama' => [
            'Kode' => $item->code,
            'Barcode' => $item->barcode,
            'Nama' => $item->name,
            'Merk' => $item->brand?->name,
            'Kategori' => $item->category?->name,
            'Satuan' => $item->unit?->name,
            'Aktif' => $item->is_active ? 'Ya' : 'Tidak',
        ],
        'Harga & Stok' => [
            'Harga Jual Retail' => $rupiah($item->harga_jual_retail),
            'Harga Jual Grosir' => $rupiah($item->harga_jual_grosir),
            'Min Qty Grosir' => $item->min_grosir_qty,
            'Total Stok' => $item->current_stock,
            'Min Stok' => $item->min_stock,
            'Lokasi Rak' => $item->rack_location,
        ],
    ];
    $imageUrl = $item->image ? \Illuminate\Support\Facades\Storage::disk('public')->url($item->image) : null;
@endphp
<div class="space-y-6">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        @foreach ($sections as $heading => $fields)
            <div class="rounded-2xl border border-gray-200 p-5 dark:border-gray-800">
                <h4 class="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">{{ $heading }}</h4>
                <dl class="space-y-3">
                    @foreach ($fields as $label => $value)
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                            <dd class="text-sm font-medium text-gray-800 dark:text-white/90">{{ filled($value) ? $value : '-' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @endforeach

        <div class="rounded-2xl border border-gray-200 p-5 dark:border-gray-800">
            <h4 class="mb-4 text-base font-semibold text-gray-800 dark:text-white/90">Informasi Tambahan</h4>
            <dl class="space-y-3">
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">Deskripsi</dt>
                    <dd class="text-sm whitespace-pre-line text-gray-800 dark:text-white/90">{{ $item->description ?: '-' }}</dd>
                </div>
                <div>
                    <dt class="mb-2 text-xs text-gray-500 dark:text-gray-400">Gambar</dt>
                    <dd>
                        @if ($imageUrl)
                            <img src="{{ $imageUrl }}" alt="{{ $item->name }}" class="max-h-48 rounded-xl border border-gray-200 object-contain dark:border-gray-800" />
                        @else
                            <span class="text-sm text-gray-400">-</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 dark:border-gray-800">
        <div class="px-5 py-4">
            <h4 class="text-base font-semibold text-gray-800 dark:text-white/90">Supplier &amp; Harga Beli</h4>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Daftar supplier yang menyuplai barang ini</p>
        </div>
        <div class="custom-scrollbar overflow-x-auto">
            <table class="min-w-full">
                <thead class="border-y border-gray-100 dark:border-gray-800">
                    <tr>
                        <th class="table-th">Supplier</th>
                        <th class="table-th">Harga Beli</th>
                        <th class="table-th">Stok Supplier</th>
                        <th class="table-th">SKU Supplier</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($item->itemSuppliers as $row)
                        <tr>
                            <td class="table-td font-medium text-gray-800 dark:text-white/90">{{ $row->supplier?->name ?? '-' }}</td>
                            <td class="table-td whitespace-nowrap">{{ $rupiah($row->harga_beli) }}</td>
                            <td class="table-td">{{ $row->current_stock }}</td>
                            <td class="table-td">{{ $row->supplier_sku ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada supplier.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
