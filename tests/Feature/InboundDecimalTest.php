<?php

use App\Enums\BusinessType;
use App\Models\Category;
use App\Models\Inbound;
use App\Models\Item;
use App\Models\StoreSetting;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function inboundDecimalStore(): array
{
    StoreSetting::flushCache();

    $admin = User::create([
        'name' => 'Admin Gudang',
        'email' => 'admin-gudang@toko.test',
        'password' => bcrypt('password'),
        'role' => User::ROLE_ADMIN,
        'is_active' => true,
        'must_reset_password' => false,
    ]);

    // FNB: tanpa supplier, harga beli dari HPP barang.
    StoreSetting::current()->update(['store_name' => 'Dapur', 'setup_completed_at' => now()]);
    StoreSetting::current()->applyBusinessType(BusinessType::Makanan);
    StoreSetting::flushCache();

    $sugar = Item::create([
        'code' => 'BB-GULA', 'name' => 'Gula Pasir',
        'category_id' => Category::create(['name' => 'Bahan Baku'])->id,
        'unit_id' => Unit::create(['name' => 'kg'])->id,
        'harga_beli' => 16000, 'harga_jual_retail' => 0, 'current_stock' => 1, 'min_stock' => 0, 'is_active' => true,
    ]);

    return compact('admin', 'sugar');
}

it('menampilkan satuan barang di form barang masuk', function () {
    ['admin' => $admin] = inboundDecimalStore();

    $this->actingAs($admin)->get(route('inbounds.create'))->assertOk()
        ->assertViewHas('catalog', fn (array $catalog) => $catalog[0]['unit'] === 'kg')
        ->assertSee("unit(row) || 'satuan'", false);
});

it('menerima kuantitas desimal dan menambah stok tanpa pembulatan', function () {
    ['admin' => $admin, 'sugar' => $sugar] = inboundDecimalStore();

    $this->actingAs($admin)->post(route('inbounds.store'), [
        'date' => now()->toDateString(),
        'items' => [['item_id' => $sugar->id, 'quantity' => '2.75']],
    ])->assertRedirect();

    $inbound = Inbound::with('items')->sole();
    expect((float) $inbound->items->sole()->quantity)->toEqual(2.75)
        ->and((float) $inbound->total_amount)->toEqual(44000.0); // 2,75 × 16.000

    $this->actingAs($admin)->post(route('inbounds.approve', $inbound))->assertRedirect();
    expect((float) $sugar->fresh()->current_stock)->toEqual(3.75);

    $this->actingAs($admin)->get(route('inbounds.show', $inbound))->assertOk()->assertSee('2,75 kg');
});

it('menolak kuantitas nol atau lebih dari 4 angka desimal', function () {
    ['admin' => $admin, 'sugar' => $sugar] = inboundDecimalStore();

    $this->actingAs($admin)->post(route('inbounds.store'), [
        'date' => now()->toDateString(),
        'items' => [['item_id' => $sugar->id, 'quantity' => '0']],
    ])->assertSessionHasErrors('items.0.quantity');

    $this->actingAs($admin)->post(route('inbounds.store'), [
        'date' => now()->toDateString(),
        'items' => [['item_id' => $sugar->id, 'quantity' => '1.23456']],
    ])->assertSessionHasErrors('items.0.quantity');

    expect(Inbound::count())->toBe(0);
});
