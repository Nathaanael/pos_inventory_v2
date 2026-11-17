<?php

use App\Enums\BusinessType;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Item;
use App\Models\Outbound;
use App\Models\Recipe;
use App\Models\StoreSetting;
use App\Models\Unit;
use App\Models\UnitConversion;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function setupRecipeStore(): array
{
    StoreSetting::flushCache();

    $kasir = User::create([
        'name' => 'Kasir Resep',
        'email' => 'kasir-resep@fnb.test',
        'password' => bcrypt('password'),
        'role' => User::ROLE_KASIR,
        'is_active' => true,
        'must_reset_password' => false,
    ]);

    $setting = StoreSetting::current();
    $setting->update(['store_name' => 'Kopi Nusantara', 'setup_completed_at' => now()]);
    $setting->applyBusinessType(BusinessType::Makanan);
    StoreSetting::flushCache();

    $catMenu = Category::create(['name' => 'Minuman']);
    $catIng = Category::create(['name' => 'Bahan Baku']);

    $cup = Unit::create(['name' => 'cup']);
    $kg = Unit::create(['name' => 'kg']);
    $gram = Unit::create(['name' => 'gram']);
    $liter = Unit::create(['name' => 'liter']);
    $ml = Unit::create(['name' => 'ml']);

    UnitConversion::create(['from_unit_id' => $kg->id, 'to_unit_id' => $gram->id, 'multiplier' => 1000]);
    UnitConversion::create(['from_unit_id' => $liter->id, 'to_unit_id' => $ml->id, 'multiplier' => 1000]);

    $table = DiningTable::create(['number' => 'T1', 'is_active' => true]);

    // Bahan baku dalam kg (0.5 kg = 500 gram) & liter (1.5 liter = 1500 ml)
    $coffeeBeans = Item::create([
        'code' => 'ING-001',
        'name' => 'Biji Kopi Arabica',
        'item_type' => Item::TYPE_INGREDIENT,
        'track_stock' => true,
        'category_id' => $catIng->id,
        'unit_id' => $kg->id,
        'harga_beli' => 200000, // Rp200.000 / kg = Rp200 / gram
        'harga_jual_retail' => 0,
        'current_stock' => 0.5, // 500 gram
        'min_stock' => 0.1,
        'is_active' => true,
    ]);

    $milk = Item::create([
        'code' => 'ING-002',
        'name' => 'Susu Fresh',
        'item_type' => Item::TYPE_INGREDIENT,
        'track_stock' => true,
        'category_id' => $catIng->id,
        'unit_id' => $liter->id,
        'harga_beli' => 20000, // Rp20.000 / liter = Rp20 / ml
        'harga_jual_retail' => 0,
        'current_stock' => 1.5, // 1500 ml
        'min_stock' => 0.5,
        'is_active' => true,
    ]);

    // Menu jadi: Es Latte (20g kopi + 150ml susu -> COGS = 20*200 + 150*20 = 4000 + 3000 = 7000)
    $latte = Item::create([
        'code' => 'MNU-001',
        'name' => 'Es Cafe Latte',
        'item_type' => Item::TYPE_MENU,
        'track_stock' => false,
        'category_id' => $catMenu->id,
        'unit_id' => $cup->id,
        'harga_beli' => 0,
        'harga_jual_retail' => 22000,
        'current_stock' => 0,
        'min_stock' => 0,
        'is_active' => true,
    ]);

    $recipe = Recipe::create(['menu_item_id' => $latte->id, 'yield_qty' => 1]);
    $recipe->ingredients()->create(['ingredient_item_id' => $coffeeBeans->id, 'quantity' => 20, 'unit_id' => $gram->id]);
    $recipe->ingredients()->create(['ingredient_item_id' => $milk->id, 'quantity' => 150, 'unit_id' => $ml->id]);

    // Modul FNB: checkout POS wajib di dalam shift kasir.
    openShiftFor($kasir);

    return compact('kasir', 'table', 'coffeeBeans', 'milk', 'latte');
}

it('calculates available portions, recipe COGS, deducts decimal ingredient stock on sale, and restores on void', function () {
    ['kasir' => $kasir, 'table' => $table, 'coffeeBeans' => $coffeeBeans, 'milk' => $milk, 'latte' => $latte] = setupRecipeStore();

    // 500g / 20g = 25; 1500ml / 150ml = 10 -> max 10 portions
    expect($latte->availablePortions())->toBe(10);
    expect($latte->calculateRecipeCogs())->toBe(7000.0);

    // Sell 3 cups
    $res = $this->actingAs($kasir)->postJson(route('kasir.checkout'), [
        'lines' => [['id' => $latte->id, 'qty' => 3]],
        'order_type' => 'dine_in',
        'table_id' => $table->id,
        'payment_method' => 'qris',
        'sale_id' => 'recipe-sale-1',
    ]);

    $res->assertOk()->assertJson(['ok' => true]);

    // Ingredients excluded from POS catalog
    $catalogIds = collect($res->json('catalog'))->pluck('id')->all();
    expect($catalogIds)->toContain($latte->id);
    expect($catalogIds)->not->toContain($coffeeBeans->id);

    // 0.5 kg - (3 * 0.02 kg) = 0.44 kg; 1.5 L - (3 * 0.15 L) = 1.05 L
    expect((float) $coffeeBeans->fresh()->current_stock)->toBe(0.44);
    expect((float) $milk->fresh()->current_stock)->toBe(1.05);
    expect($latte->fresh()->availablePortions())->toBe(7);

    $outbound = Outbound::first();
    expect((float) $outbound->total_cogs)->toBe(21000.0);

    // Void the outbound -> ingredients restored!
    app(StockService::class)->voidOutbound($outbound, 'Salah input');
    expect((float) $coffeeBeans->fresh()->current_stock)->toBe(0.5);
    expect((float) $milk->fresh()->current_stock)->toBe(1.5);
});

it('rejects sale when recipe ingredient stock is insufficient', function () {
    ['kasir' => $kasir, 'table' => $table, 'latte' => $latte] = setupRecipeStore();

    // Max available is 10 portions, try ordering 11
    $res = $this->actingAs($kasir)->postJson(route('kasir.checkout'), [
        'lines' => [['id' => $latte->id, 'qty' => 11]],
        'order_type' => 'dine_in',
        'table_id' => $table->id,
        'payment_method' => 'qris',
        'sale_id' => 'recipe-sale-over',
    ]);

    $res->assertOk()->assertJson(['ok' => false]);
});
