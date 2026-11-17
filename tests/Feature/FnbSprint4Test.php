<?php

use App\Enums\BusinessType;
use App\Enums\MovementType;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Item;
use App\Models\Outbound;
use App\Models\OutboundItem;
use App\Models\StockMovement;
use App\Models\StoreSetting;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\User;
use App\Models\WasteLog;
use App\Services\DashboardStats;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function setupSprint4Store(): array
{
    StoreSetting::flushCache();

    $admin = User::create([
        'name' => 'Admin S4',
        'email' => 'admin-s4@fnb.test',
        'password' => bcrypt('password'),
        'role' => 'admin',
        'is_active' => true,
        'must_reset_password' => false,
    ]);

    $kasir = User::create([
        'name' => 'Kasir S4',
        'email' => 'kasir-s4@fnb.test',
        'password' => bcrypt('password'),
        'role' => User::ROLE_KASIR,
        'is_active' => true,
        'must_reset_password' => false,
    ]);

    $setting = StoreSetting::current();
    $setting->update(['store_name' => 'Kopi Nusantara', 'setup_completed_at' => now()]);
    $setting->applyBusinessType(BusinessType::Makanan);
    StoreSetting::flushCache();

    $cat = Category::create(['name' => 'Makanan']);
    $unit = Unit::create(['name' => 'porsi']);
    $t1 = DiningTable::create(['number' => 'T1', 'is_active' => true]);
    $t2 = DiningTable::create(['number' => 'T2', 'is_active' => true]);

    Tax::create([
        'name' => 'PB1',
        'type' => 'tax',
        'rate' => 10,
        'method' => 'exclusive',
        'applies_to' => 'all',
        'is_active' => true,
    ]);

    $item = Item::create([
        'code' => 'MNU-S4',
        'name' => 'Nasi Goreng Spesial',
        'item_type' => Item::TYPE_MENU,
        'track_stock' => true,
        'category_id' => $cat->id,
        'unit_id' => $unit->id,
        'harga_beli' => 15000,
        'harga_jual_retail' => 50000,
        'current_stock' => 20,
        'min_stock' => 2,
        'is_active' => true,
    ]);

    // Modul FNB: checkout POS wajib di dalam shift kasir.
    openShiftFor($kasir);

    return compact('admin', 'kasir', 't1', 't2', 'item');
}

it('applies discount before tax and calculates net grand total accurately', function () {
    ['kasir' => $kasir, 't1' => $t1, 'item' => $item] = setupSprint4Store();

    // 2x 50.000 = 100.000; 10% discount = 10.000 -> Net = 90.000; PB1 10% = 9.000 -> Grand Total = 99.000
    $res = $this->actingAs($kasir)->postJson(route('kasir.checkout'), [
        'lines' => [['id' => $item->id, 'qty' => 2]],
        'order_type' => 'dine_in',
        'table_id' => $t1->id,
        'payment_method' => 'qris',
        'discount_type' => 'percent',
        'discount_value' => 10,
        'sale_id' => 's4-disc-1',
    ]);

    $res->assertOk()->assertJson(['ok' => true]);

    $outbound = Outbound::first();
    expect((float) $outbound->total_amount)->toBe(100000.0);
    expect((float) $outbound->discount_amount)->toBe(10000.0);
    expect((float) $outbound->tax_amount)->toBe(9000.0);
    expect((float) $outbound->grand_total)->toBe(99000.0);
});

it('moves active orders from one table to an empty table', function () {
    ['kasir' => $kasir, 't1' => $t1, 't2' => $t2, 'item' => $item] = setupSprint4Store();

    $this->actingAs($kasir)->postJson(route('kasir.checkout'), [
        'lines' => [['id' => $item->id, 'qty' => 1]],
        'order_type' => 'dine_in',
        'table_id' => $t1->id,
        'save_as_open_bill' => true,
        'sale_id' => 's4-move-1',
    ])->assertOk();

    expect($t1->activeOrders()->count())->toBe(1);
    expect($t2->activeOrders()->count())->toBe(0);

    $this->actingAs($kasir)->post(route('kasir.meja.move', $t1), [
        'target_table_id' => $t2->id,
    ])->assertRedirect();

    expect($t1->activeOrders()->count())->toBe(0);
    expect($t2->activeOrders()->count())->toBe(1);
});

it('updates kitchen item statuses via KDS', function () {
    ['kasir' => $kasir, 't1' => $t1, 'item' => $item] = setupSprint4Store();

    $this->actingAs($kasir)->postJson(route('kasir.checkout'), [
        'lines' => [['id' => $item->id, 'qty' => 1]],
        'order_type' => 'dine_in',
        'table_id' => $t1->id,
        'payment_method' => 'qris',
        'sale_id' => 's4-kds-1',
    ])->assertOk();

    $this->actingAs($kasir)->get(route('kasir.kds.index'))->assertOk();

    $line = OutboundItem::first();
    $this->actingAs($kasir)->postJson(route('kasir.kds.item-status', $line), [
        'kitchen_status' => 'preparing',
    ])->assertOk()->assertJson(['ok' => true]);

    expect($line->fresh()->kitchen_status)->toBe('preparing');
});

it('records waste log, deducts stock, and computes F&B analytics', function () {
    ['admin' => $admin, 'kasir' => $kasir, 't1' => $t1, 'item' => $item] = setupSprint4Store();

    // Sell 2 portions (net sales 100.000, COGS 30.000)
    $this->actingAs($kasir)->postJson(route('kasir.checkout'), [
        'lines' => [['id' => $item->id, 'qty' => 2]],
        'order_type' => 'dine_in',
        'table_id' => $t1->id,
        'payment_method' => 'qris',
        'sale_id' => 's4-analytics-1',
    ])->assertOk();

    // Record waste of 1 portion (cost 15.000)
    $this->actingAs($admin)->post(route('waste-logs.store'), [
        'item_id' => $item->id,
        'quantity' => 1,
        'reason' => 'spillage',
        'notes' => 'Jatuh di dapur',
    ])->assertRedirect();

    expect($item->fresh()->current_stock)->toBe(17.0);
    expect(WasteLog::count())->toBe(1);
    expect(StockMovement::where('type', MovementType::WASTE->value)->count())->toBe(1);

    $analytics = (new DashboardStats('today'))->fnbAnalytics();
    expect($analytics['net_sales'])->toBe(100000.0);
    expect($analytics['total_cogs'])->toBe(30000.0);
    expect($analytics['waste_total'])->toBe(15000.0);
    // (30.000 + 15.000) / 100.000 * 100 = 45%
    expect($analytics['food_cost_pct'])->toBe(45.0);
});
