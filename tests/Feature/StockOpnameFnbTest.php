<?php

use App\Enums\BusinessType;
use App\Models\Category;
use App\Models\Item;
use App\Models\StockOpname;
use App\Models\StoreSetting;
use App\Models\Unit;
use App\Models\User;
use App\Support\Format;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function opnameFnbStore(BusinessType $business = BusinessType::Makanan): array
{
    StoreSetting::flushCache();

    $admin = User::create([
        'name' => 'Admin Opname',
        'email' => 'admin-opname@fnb.test',
        'password' => bcrypt('password'),
        'role' => User::ROLE_ADMIN,
        'is_active' => true,
        'must_reset_password' => false,
    ]);

    StoreSetting::current()->update(['store_name' => 'Kopi Opname', 'setup_completed_at' => now()]);
    StoreSetting::current()->applyBusinessType($business);
    StoreSetting::flushCache();

    $category = Category::create(['name' => 'Bahan Baku']);
    $kg = Unit::create(['name' => 'kg']);

    $sugar = Item::create([
        'code' => 'BB-GULA', 'name' => 'Gula Pasir', 'category_id' => $category->id, 'unit_id' => $kg->id,
        'harga_beli' => 15000, 'harga_jual_retail' => 0, 'current_stock' => 2.5, 'min_stock' => 0,
        'is_active' => true, 'track_stock' => true,
    ]);

    // Menu berbasis resep: stok tidak dilacak, jadi tidak bisa di-opname.
    $latte = Item::create([
        'code' => 'MN-LATTE', 'name' => 'Latte', 'category_id' => $category->id, 'unit_id' => $kg->id,
        'harga_beli' => 0, 'harga_jual_retail' => 25000, 'current_stock' => 0, 'min_stock' => 0,
        'is_active' => true, 'track_stock' => false,
    ]);

    return compact('admin', 'sugar', 'latte');
}

it('menampilkan menu Stock Opname dan Hitung Kas di modul FNB', function () {
    ['admin' => $admin] = opnameFnbStore();

    $this->actingAs($admin)->get(route('stock-opnames.index'))->assertOk()
        ->assertSee('/stock-opnames', false)
        ->assertSee('/hitung-kas', false);
});

it('menyimpan stok fisik desimal tanpa pembulatan', function () {
    ['admin' => $admin, 'sugar' => $sugar] = opnameFnbStore();

    $this->actingAs($admin)->get(route('stock-opnames.create'))->assertOk()
        ->assertSee('Gula Pasir')->assertDontSee('Latte');

    $this->actingAs($admin)->post(route('stock-opnames.store'), [
        'session_name' => 'Opname Bahan Baku',
        'date' => now()->toDateString(),
        'submit_mode' => 'selesai',
        'items' => [['item_id' => $sugar->id, 'physical_stock' => '2.25']],
    ])->assertRedirect(route('stock-opnames.index'));

    $opname = StockOpname::with('items')->sole();
    $line = $opname->items->sole();

    expect($line->system_stock)->toBe(2.5)
        ->and($line->physical_stock)->toBe(2.25)
        ->and($line->difference)->toBe(-0.25)
        ->and($line->status)->toBe('kurang')
        ->and($opname->total_difference)->toBe(-0.25)
        ->and($opname->total_match)->toBe(0);

    $this->actingAs($admin)->get(route('stock-opnames.show', $opname))->assertOk()
        ->assertSee('2,5 kg')->assertSee('2,25 kg')->assertSee('-0,25');
});

it('menolak barang yang stoknya tidak dilacak (menu resep)', function () {
    ['admin' => $admin, 'latte' => $latte] = opnameFnbStore();

    $this->actingAs($admin)->post(route('stock-opnames.store'), [
        'session_name' => 'Opname Menu',
        'date' => now()->toDateString(),
        'submit_mode' => 'selesai',
        'items' => [['item_id' => $latte->id, 'physical_stock' => '1']],
    ])->assertSessionHasErrors('items.0.item_id');

    expect(StockOpname::count())->toBe(0);
});

it('format jumlah stok menampilkan desimal hanya bila ada', function () {
    expect(Format::qty(1250))->toBe('1.250')
        ->and(Format::qty(1.25))->toBe('1,25')
        ->and(Format::qty(0.1 + 0.2))->toBe('0,3')
        ->and(Format::signedQty(1.5))->toBe('+1,5')
        ->and(Format::signedQty(-2))->toBe('-2')
        ->and(Format::signedQty(0))->toBe('0');
});
