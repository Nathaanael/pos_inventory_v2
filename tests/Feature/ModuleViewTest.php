<?php

use App\Enums\BusinessType;
use App\Models\StoreSetting;
use App\Support\ModuleView;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function useBusiness(BusinessType $business): void
{
    StoreSetting::flushCache();
    StoreSetting::current()->applyBusinessType($business);
    StoreSetting::flushCache();
}

it('memakai UI default untuk modul selain FNB', function () {
    useBusiness(BusinessType::Bangunan);

    expect(ModuleView::module())->toBe('default')
        ->and(ModuleView::name('pages.inventory.stock-opnames', 'form'))
        ->toBe('pages.inventory.stock-opnames.default.form');
});

it('memakai kunci modul fnb untuk FNB dan jatuh ke default bila view fnb belum ada', function () {
    useBusiness(BusinessType::Makanan);

    expect(ModuleView::module())->toBe('fnb')
        ->and(ModuleView::name('pages.inventory.stock-opnames', 'index'))
        ->toBe('pages.inventory.stock-opnames.default.index');
});

it('memakai view khusus modul bila tersedia', function () {
    useBusiness(BusinessType::Makanan);

    $dir = resource_path('views/pages/inventory/stock-opnames/fnb');
    $created = ! is_dir($dir);
    @mkdir($dir, 0777, true);
    file_put_contents("{$dir}/__probe.blade.php", 'fnb');

    try {
        expect(ModuleView::name('pages.inventory.stock-opnames', '__probe'))
            ->toBe('pages.inventory.stock-opnames.fnb.__probe');
    } finally {
        unlink("{$dir}/__probe.blade.php");
        if ($created) {
            rmdir($dir);
        }
    }
});
