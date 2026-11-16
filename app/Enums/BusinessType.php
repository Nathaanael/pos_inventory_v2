<?php

namespace App\Enums;

/**
 * Jenis usaha yang dipilih saat setup toko. Preset fitur hanya nilai awal;
 * setelah itu yang berlaku adalah isi tabel store_features.
 */
enum BusinessType: string
{
    case Bangunan = 'bangunan';
    case Makanan = 'makanan';
    case Elektronik = 'elektronik';
    case Fashion = 'fashion';
    case Umum = 'umum';

    public function label(): string
    {
        return match ($this) {
            self::Bangunan => 'Toko Bangunan',
            self::Makanan => 'FNB',
            self::Elektronik => 'Elektronik & HP',
            self::Fashion => 'Fashion',
            self::Umum => 'Toko Umum / Lainnya',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Bangunan => 'Material, cat, listrik, perkakas',
            self::Makanan => 'Restoran, kafe, warung makan, kedai minuman',
            self::Elektronik => 'Handphone, aksesoris, peralatan elektronik',
            self::Fashion => 'Pakaian, sepatu, tas, aksesoris',
            self::Umum => 'Toko kelontong, minimarket, usaha lain',
        };
    }

    /** Ikon kartu (SVG path Heroicons outline). */
    public function iconPath(): string
    {
        return match ($this) {
            self::Bangunan => 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z',
            self::Makanan => 'M12 8.25v-1.5m0 1.5c-1.355 0-2.697.056-4.024.166C6.845 8.51 6 9.473 6 10.608v2.513m6-4.871c1.355 0 2.697.056 4.024.166C17.155 8.51 18 9.473 18 10.608v2.513M15 8.25v-1.5m-6 1.5v-1.5m12 9.75-1.5.75a3.354 3.354 0 0 1-3 0 3.354 3.354 0 0 0-3 0 3.354 3.354 0 0 1-3 0 3.354 3.354 0 0 0-3 0 3.354 3.354 0 0 1-3 0L3 16.5m15-3.379a48.474 48.474 0 0 0-6-.371c-2.032 0-4.034.126-6 .371m12 0c.39.049.777.102 1.163.16 1.07.16 1.837 1.094 1.837 2.175v5.169c0 .621-.504 1.125-1.125 1.125H4.125A1.125 1.125 0 0 1 3 20.625v-5.17c0-1.08.768-2.014 1.837-2.174A47.78 47.78 0 0 1 6 13.12M12.265 3.11a.375.375 0 1 1-.53 0L12 2.845l.265.265Zm-3 0a.375.375 0 1 1-.53 0L9 2.845l.265.265Zm6 0a.375.375 0 1 1-.53 0L15 2.845l.265.265Z',
            self::Elektronik => 'M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3',
            self::Fashion => 'M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z',
            self::Umum => 'M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z',
        };
    }

    /**
     * Fitur yang langsung dicentang saat jenis usaha ini dipilih.
     *
     * @return array<int, Feature>
     */
    public function defaultFeatures(): array
    {
        return match ($this) {
            self::Bangunan => [Feature::WholesalePrice, Feature::Brand, Feature::Returns, Feature::StockOpname],
            self::Makanan => [Feature::ItemImage, Feature::StockOpname, Feature::DiningTables, Feature::TakeAway],
            self::Elektronik => [Feature::Brand, Feature::ItemImage, Feature::Returns, Feature::StockOpname],
            self::Fashion => [Feature::Brand, Feature::ItemImage, Feature::Returns, Feature::StockOpname],
            self::Umum => [Feature::WholesalePrice, Feature::ItemImage, Feature::Returns, Feature::StockOpname],
        };
    }
}
