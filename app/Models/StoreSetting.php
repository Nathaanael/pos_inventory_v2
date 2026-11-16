<?php

namespace App\Models;

use App\Enums\BusinessType;
use App\Enums\Feature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Identitas usaha (nama, alamat, kontak, logo, struk, printer), jenis usaha,
 * dan status setup. Fitur yang aktif disimpan di tabel store_features.
 * Tabel ini hanya berisi satu baris; akses selalu lewat StoreSetting::current().
 */
class StoreSetting extends Model
{
    public const DEFAULT_NAME = 'Toko Saya';

    protected $guarded = [];

    private static ?self $cached = null;

    /** @var array<string, bool>|null  fitur => aktif, dibaca sekali per request */
    private static ?array $featureCache = null;

    protected function casts(): array
    {
        return [
            'business_type' => BusinessType::class,
            'setup_completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::$cached = null);
    }

    public static function flushCache(): void
    {
        static::$cached = null;
        static::$featureCache = null;
    }

    /* ------------------------------ Setup & fitur ------------------------------ */

    /** Toko sudah melewati wizard setup? */
    public function isSetUp(): bool
    {
        return $this->setup_completed_at !== null;
    }

    /** Fitur aktif? Fitur yang belum punya baris di store_features dianggap nonaktif. */
    public static function hasFeature(Feature $feature): bool
    {
        if (static::$featureCache === null) {
            try {
                static::$featureCache = StoreFeature::query()->pluck('is_enabled', 'feature')
                    ->map(fn ($enabled) => (bool) $enabled)
                    ->all();
            } catch (Throwable) {
                // Tabel belum dimigrasi.
                return false;
            }
        }

        return static::$featureCache[$feature->value] ?? false;
    }

    /**
     * Simpan status SEMUA fitur: yang ada di $enabled aktif, sisanya nonaktif.
     * Simpan business_type lebih dulu, karena fitur khusus dicek terhadapnya.
     *
     * @param  array<int, Feature|string>  $enabled
     */
    public static function saveFeatures(array $enabled): void
    {
        $enabled = array_map(fn ($f) => $f instanceof Feature ? $f->value : $f, $enabled);
        $business = static::query()->first()?->business_type; // sudah di-cast ke BusinessType

        foreach (Feature::cases() as $feature) {
            StoreFeature::updateOrCreate(
                ['feature' => $feature->value],
                // Fitur khusus (mis. meja untuk FNB) otomatis nonaktif di jenis usaha lain.
                ['is_enabled' => in_array($feature->value, $enabled, true) && $feature->isAvailableFor($business)],
            );
        }

        static::$featureCache = null;
    }

    public static function current(): self
    {
        if (static::$cached) {
            return static::$cached;
        }

        try {
            $setting = static::query()->first();
        } catch (Throwable) {
            // Tabel belum dimigrasi (mis. saat setup awal): pakai nilai default.
            return new static(['store_name' => self::DEFAULT_NAME, 'receipt_footer' => 'Terima Kasih']);
        }

        return static::$cached = $setting
            ?? static::query()->create(['store_name' => self::DEFAULT_NAME, 'receipt_footer' => 'Terima Kasih']);
    }

    public static function storeName(): string
    {
        return static::current()->store_name ?: self::DEFAULT_NAME;
    }

    public function logoUrl(): ?string
    {
        return $this->logo ? Storage::disk('public')->url($this->logo) : null;
    }
}
