<?php

namespace App\Support;

use App\Models\StoreSetting;
use Illuminate\Support\Facades\View as ViewFactory;
use Illuminate\View\View;

/**
 * UI per modul (jenis usaha). Halaman yang tampilannya berbeda antar modul menyimpan
 * view-nya di subfolder bernama modul, dengan "default" sebagai cadangan:
 *
 *   resources/views/pages/inventory/stock-opnames/default/form.blade.php  ← modul tanpa UI khusus
 *   resources/views/pages/inventory/stock-opnames/fnb/form.blade.php      ← khusus modul FNB
 *
 * ModuleView::make('pages.inventory.stock-opnames', 'form', [...]) memakai view modul aktif
 * bila ada, dan jatuh ke "default" bila belum dibuat.
 */
class ModuleView
{
    public const DEFAULT = 'default';

    /** Kunci modul aktif (mis. "fnb"), atau "default" bila jenis usaha belum dipilih. */
    public static function module(): string
    {
        return StoreSetting::current()->business_type?->module() ?? self::DEFAULT;
    }

    /** Nama view untuk modul aktif, dengan fallback ke "{base}.default.{page}". */
    public static function name(string $base, string $page): string
    {
        $specific = "{$base}.".static::module().".{$page}";

        return ViewFactory::exists($specific) ? $specific : "{$base}.".self::DEFAULT.".{$page}";
    }

    /** @param  array<string, mixed>  $data */
    public static function make(string $base, string $page, array $data = []): View
    {
        return view(static::name($base, $page), [...$data, 'module' => static::module()]);
    }
}
