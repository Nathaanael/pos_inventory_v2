<?php

use App\Enums\BusinessType;
use App\Models\CashierShift;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Item;
use App\Models\Outbound;
use App\Models\StoreSetting;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function setupShiftKasir(): array
{
    StoreSetting::flushCache();

    $kasir = User::create([
        'name' => 'Kasir Shift',
        'email' => 'kasir-shift@fnb.test',
        'password' => bcrypt('password'),
        'role' => User::ROLE_KASIR,
        'is_active' => true,
        'must_reset_password' => false,
    ]);

    $setting = StoreSetting::current();
    $setting->update(['store_name' => 'Kopi Nusantara', 'setup_completed_at' => now()]);
    $setting->applyBusinessType(BusinessType::Makanan);
    StoreSetting::flushCache();

    $category = Category::create(['name' => 'Minuman']);
    $unit = Unit::create(['name' => 'cup']);
    $table = DiningTable::create(['number' => 'T1', 'is_active' => true]);

    $item = Item::create([
        'code' => 'ITM-SHIFT-1',
        'name' => 'Americano',
        'category_id' => $category->id,
        'unit_id' => $unit->id,
        'harga_beli' => 6000,
        'harga_jual_retail' => 15000,
        'current_stock' => 20,
        'min_stock' => 2,
        'is_active' => true,
    ]);

    return compact('kasir', 'table', 'item');
}

it('tracks opening cash, sales, petty cash movements, and blind closing difference', function () {
    ['kasir' => $kasir, 'table' => $table, 'item' => $item] = setupShiftKasir();

    // 1. Open shift with Rp200.000
    $this->actingAs($kasir)->post(route('kasir.shift.open'), [
        'opening_cash' => 200000,
        'notes' => 'Shift pagi',
    ])->assertRedirect();

    $shift = CashierShift::activeFor($kasir->id);
    expect($shift)->not->toBeNull();
    expect($shift->opening_cash)->toBe(200000.0);

    // 2. Cash sale: 2x 15.000 = 30.000
    $this->actingAs($kasir)->postJson(route('kasir.checkout'), [
        'lines' => [['id' => $item->id, 'qty' => 2]],
        'order_type' => 'dine_in',
        'table_id' => $table->id,
        'payment_method' => 'tunai',
        'paid_amount' => 50000,
        'sale_id' => 'shift-sale-1',
    ])->assertOk()->assertJson(['ok' => true]);

    // 3. QRIS sale: 1x 15.000 = 15.000 (should NOT add to cash drawer)
    $this->actingAs($kasir)->postJson(route('kasir.checkout'), [
        'lines' => [['id' => $item->id, 'qty' => 1]],
        'order_type' => 'take_away',
        'payment_method' => 'qris',
        'sale_id' => 'shift-sale-2',
    ])->assertOk()->assertJson(['ok' => true]);

    expect(Outbound::where('cashier_shift_id', $shift->id)->count())->toBe(2);

    // 4. Petty cash: +50.000 in, -20.000 out
    $this->actingAs($kasir)->post(route('kasir.shift.cash-movement'), [
        'type' => 'in',
        'amount' => 50000,
        'reason' => 'Tambah receh',
    ])->assertRedirect();

    $this->actingAs($kasir)->post(route('kasir.shift.cash-movement'), [
        'type' => 'out',
        'amount' => 20000,
        'reason' => 'Beli es batu',
    ])->assertRedirect();

    // Expected cash = 200.000 + 30.000 + 50.000 - 20.000 = 260.000
    $summary = $shift->summary();
    expect($summary['expected_cash'])->toBe(260000.0);
    expect($summary['total_sales'])->toBe(45000.0);

    // 5. Close shift with actual cash = 255.000 (short 5.000)
    $this->actingAs($kasir)->post(route('kasir.shift.close'), [
        'actual_cash' => 255000,
        'notes' => 'Selisih 5rb',
    ])->assertRedirect();

    $shift->refresh();
    expect($shift->status)->toBe(CashierShift::STATUS_CLOSED);
    expect($shift->expected_cash)->toBe(260000.0);
    expect($shift->actual_cash)->toBe(255000.0);
    expect($shift->cash_difference)->toBe(-5000.0);
    expect(CashierShift::activeFor($kasir->id))->toBeNull();
});

it('renders the shift management page for authenticated cashier', function () {
    ['kasir' => $kasir] = setupShiftKasir();

    $this->actingAs($kasir)->get(route('kasir.shift.index'))->assertOk();
});
