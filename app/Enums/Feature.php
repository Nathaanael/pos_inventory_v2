<?php

namespace App\Enums;

/**
 * Fitur aplikasi yang bisa dinyalakan/dimatikan per toko (tabel store_features).
 * Mematikan fitur hanya menyembunyikan tampilannya; datanya tidak dihapus.
 *
 * Tambahkan case baru di sini setiap ada fitur baru, lalu masukkan ke preset
 * jenis usaha yang relevan di BusinessType::defaultFeatures().
 */
enum Feature: string
{
    case WholesalePrice = 'grosir';
    case Brand = 'merek';
    case ItemImage = 'gambar_barang';
    case Returns = 'retur';
    case StockOpname = 'stock_opname';

    // ---- Khusus FNB ----
    case DiningTables = 'meja';
    case TakeAway = 'take_away';

    public function label(): string
    {
        return match ($this) {
            self::WholesalePrice => 'Harga Grosir',
            self::Brand => 'Merek Barang',
            self::ItemImage => 'Foto Barang',
            self::Returns => 'Retur Barang',
            self::StockOpname => 'Stock Opname',
            self::DiningTables => 'Manajemen Meja',
            self::TakeAway => 'Take Away',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::WholesalePrice => 'Harga khusus untuk pembelian dalam jumlah banyak.',
            self::Brand => 'Kelompokkan barang berdasarkan merek.',
            self::ItemImage => 'Tampilkan foto barang di daftar dan layar kasir.',
            self::Returns => 'Catat retur ke supplier dan dari pelanggan.',
            self::StockOpname => 'Hitung ulang stok fisik dan sesuaikan selisihnya.',
            self::DiningTables => 'Master meja, nomor meja di kasir, dan pantauan meja terisi/kosong.',
            self::TakeAway => 'Pilihan pesanan dibawa pulang di kasir.',
        };
    }

    /**
     * Jenis usaha yang boleh memakai fitur ini; null = semua jenis usaha.
     *
     * @return array<int, BusinessType>|null
     */
    public function businessTypes(): ?array
    {
        return match ($this) {
            self::DiningTables, self::TakeAway => [BusinessType::Makanan],
            default => null,
        };
    }

    public function isAvailableFor(?BusinessType $business): bool
    {
        $only = $this->businessTypes();

        return $only === null || ($business !== null && in_array($business, $only, true));
    }

    /**
     * Fitur yang boleh dipilih untuk jenis usaha tertentu.
     *
     * @return array<int, self>
     */
    public static function availableFor(?BusinessType $business): array
    {
        return array_values(array_filter(self::cases(), fn (self $f) => $f->isAvailableFor($business)));
    }

    /** Ikon kecil (SVG path Heroicons outline) untuk kartu di wizard. */
    public function iconPath(): string
    {
        return match ($this) {
            self::WholesalePrice => 'M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z M6 6h.008v.008H6V6Z',
            self::Brand => 'M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z',
            self::ItemImage => 'm2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z',
            self::Returns => 'M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3',
            self::DiningTables => 'M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z',
            self::TakeAway => 'M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m6 4.125 2.25 2.25m0 0 2.25 2.25M12 13.875l2.25-2.25M12 13.875l-2.25 2.25M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z',
            self::StockOpname => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z',
        };
    }
}
