<?php

use App\Enums\BusinessType;
use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Item;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Outbound;
use App\Models\StoreSetting;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function setupFnbStore(): array
{
    StoreSetting::flushCache();

    $admin = User::create([
        'name' => 'Admin FNB',
        'email' => 'admin@fnb.test',
        'password' => bcrypt('password'),
        'role' => 'admin',
        'is_active' => true,
        'must_reset_password' => false,
    ]);

    $kasir = User::create([
        'name' => 'Kasir FNB',
        'email' => 'kasir@fnb.test',
        'password' => bcrypt('password'),
        'role' => User::ROLE_KASIR,
        'is_active' => true,
        'must_reset_password' => false,
    ]);

    $setting = StoreSetting::current();
    $setting->update([
        'store_name' => 'Kopi Nusantara',
        'setup_completed_at' => now(),
    ]);
    $setting->applyBusinessType(BusinessType::Makanan);
    StoreSetting::flushCache();

    $category = Category::create(['name' => 'Minuman']);
    $unit = Unit::create(['name' => 'cup']);
    $table = DiningTable::create(['number' => 'T1', 'name' => 'Indoor', 'capacity' => 4, 'is_active' => true]);

    Tax::create([
        'name' => 'Service Charge',
        'type' => 'service',
        'rate' => 5,
        'method' => 'exclusive',
        'applies_to' => 'dine_in',
        'is_active' => true,
    ]);

    Tax::create([
        'name' => 'PB1',
        'type' => 'tax',
        'rate' => 10,
        'method' => 'exclusive',
        'applies_to' => 'all',
        'is_active' => true,
    ]);

    $item = Item::create([
        'code' => 'ITM-001',
        'name' => 'Es Kopi Susu',
        'category_id' => $category->id,
        'unit_id' => $unit->id,
        'harga_beli' => 8000,
        'harga_jual_retail' => 20000,
        'harga_jual_grosir' => 0,
        'current_stock' => 10,
        'min_stock' => 2,
        'is_active' => true,
    ]);

    $sugarGroup = ModifierGroup::create([
        'name' => 'Level Gula',
        'min_select' => 1,
        'max_select' => 1,
        'is_required' => true,
        'is_active' => true,
    ]);
    $normalSugar = $sugarGroup->modifiers()->create(['name' => 'Normal Sugar', 'price_delta' => 0, 'is_active' => true]);
    $lessSugar = $sugarGroup->modifiers()->create(['name' => 'Less Sugar', 'price_delta' => 0, 'is_active' => true]);

    $toppingGroup = ModifierGroup::create([
        'name' => 'Topping',
        'min_select' => 0,
        'max_select' => 2,
        'is_required' => false,
        'is_active' => true,
    ]);
    $oatMilk = $toppingGroup->modifiers()->create(['name' => 'Oat Milk', 'price_delta' => 5000, 'is_active' => true]);

    $item->modifierGroups()->sync([$sugarGroup->id, $toppingGroup->id]);

    return compact('admin', 'kasir', 'table', 'item', 'normalSugar', 'lessSugar', 'oatMilk');
}

it('calculates modifiers, notes, and taxes accurately and keeps distinct modifier lines separate', function () {
    ['kasir' => $kasir, 'table' => $table, 'item' => $item, 'normalSugar' => $normalSugar, 'lessSugar' => $lessSugar, 'oatMilk' => $oatMilk] = setupFnbStore();

    $response = $this->actingAs($kasir)->postJson(route('kasir.checkout'), [
        'lines' => [
            ['id' => $item->id, 'qty' => 1, 'modifier_ids' => [$normalSugar->id, $oatMilk->id], 'notes' => 'Es sedikit'],
            ['id' => $item->id, 'qty' => 1, 'modifier_ids' => [$lessSugar->id], 'notes' => null],
        ],
        'order_type' => 'dine_in',
        'table_id' => $table->id,
        'payment_method' => 'qris',
        'sale_id' => 'sale-mod-1',
    ]);

    $response->assertOk()->assertJson(['ok' => true]);

    $outbound = Outbound::with('items')->first();
    expect($outbound)->not->toBeNull();
    expect($outbound->items)->toHaveCount(2);
    // Line 1: 20.000 + 5.000 = 25.000; Line 2: 20.000 => Subtotal = 45.000
    expect((float) $outbound->total_amount)->toBe(45000.0);
    // Service 5% of 45.000 = 2.250; PB1 10% of (45.000 + 2.250) = 4.725; Grand total = 51.975
    expect((float) $outbound->service_amount)->toBe(2250.0);
    expect((float) $outbound->tax_amount)->toBe(4725.0);
    expect((float) $outbound->grand_total)->toBe(51975.0);

    expect($item->fresh()->current_stock)->toBe(8.0);
});

it('rejects checkout when required modifier group is missing', function () {
    ['kasir' => $kasir, 'table' => $table, 'item' => $item] = setupFnbStore();

    $response = $this->actingAs($kasir)->postJson(route('kasir.checkout'), [
        'lines' => [
            ['id' => $item->id, 'qty' => 1, 'modifier_ids' => []],
        ],
        'order_type' => 'dine_in',
        'table_id' => $table->id,
        'payment_method' => 'qris',
        'sale_id' => 'sale-missing-req',
    ]);

    $response->assertOk()->assertJson(['ok' => false]);
    expect($item->fresh()->current_stock)->toBe(10.0);
});

it('supports full open bill lifecycle: create, add items, void item, and split payment', function () {
    ['kasir' => $kasir, 'table' => $table, 'item' => $item, 'normalSugar' => $normalSugar, 'oatMilk' => $oatMilk] = setupFnbStore();

    // 1. Create Open Bill
    $createRes = $this->actingAs($kasir)->postJson(route('kasir.checkout'), [
        'lines' => [
            ['id' => $item->id, 'qty' => 2, 'modifier_ids' => [$normalSugar->id]],
        ],
        'order_type' => 'dine_in',
        'table_id' => $table->id,
        'save_as_open_bill' => true,
        'sale_id' => 'open-bill-1',
    ]);

    $createRes->assertOk()->assertJson(['ok' => true, 'is_open_bill' => true]);
    $outbound = Outbound::with('items')->first();

    expect($outbound->status)->toBe(TransactionStatus::OPEN);
    expect($outbound->payment_status)->toBe(PaymentStatus::UNPAID);
    expect($item->fresh()->current_stock)->toBe(8.0);
    expect($table->activeOrders()->count())->toBe(1);

    // 2. Add-on item to Open Bill
    $addRes = $this->actingAs($kasir)->postJson(route('kasir.orders.add-items', $outbound), [
        'lines' => [
            ['id' => $item->id, 'qty' => 1, 'modifier_ids' => [$normalSugar->id, $oatMilk->id]],
        ],
    ]);

    $addRes->assertOk()->assertJson(['ok' => true]);
    $outbound->refresh();
    expect($outbound->items)->toHaveCount(2);
    expect((float) $outbound->total_amount)->toBe(65000.0); // 40.000 + 25.000
    expect($item->fresh()->current_stock)->toBe(7.0);

    // 3. Void the newly added line
    $lineToVoid = $outbound->items->last();
    $voidRes = $this->actingAs($kasir)->postJson(route('kasir.orders.void-item', [$outbound, $lineToVoid]), [
        'reason' => 'Pelanggan batal pesan tambahan',
    ]);

    $voidRes->assertOk()->assertJson(['ok' => true]);
    $outbound->refresh();
    expect($outbound->items)->toHaveCount(1);
    expect((float) $outbound->total_amount)->toBe(40000.0);
    expect($item->fresh()->current_stock)->toBe(8.0);

    // 4. Pay Open Bill with Split Payment (Tunai + QRIS)
    $payable = $outbound->payableTotal(); // 40.000 + 2.000 (5%) + 4.200 (10%) = 46.200
    expect($payable)->toBe(46200.0);

    $payRes = $this->actingAs($kasir)->postJson(route('kasir.orders.pay', $outbound), [
        'payments' => [
            ['payment_method' => 'tunai', 'amount' => 20000],
            ['payment_method' => 'qris', 'amount' => 26200],
        ],
    ]);

    $payRes->assertOk()->assertJson(['ok' => true]);
    $outbound->refresh();
    expect($outbound->status)->toBe(TransactionStatus::COMPLETED);
    expect($outbound->payment_status)->toBe(PaymentStatus::PAID);
    expect($outbound->payment_method)->toBe('split');
    expect($outbound->payments)->toHaveCount(2);
});

it('allows admin to create modifier groups', function () {
    ['admin' => $admin, 'item' => $item] = setupFnbStore();

    $this->actingAs($admin)->get(route('modifiers.index'))->assertOk();
    $this->actingAs($admin)->get(route('kasir.meja'))->assertOk();
    $this->actingAs($admin)->get(route('items.index'))->assertOk();
    $this->actingAs($admin)->get(route('items.show', $item))->assertOk();
    $this->actingAs($admin)->get(route('items.history', $item))->assertOk();

    $response = $this->actingAs($admin)->post(route('modifiers.store'), [
        'name' => 'Ukuran Gelas',
        'max_select' => 1,
        'is_required' => 1,
        'is_active' => 1,
        'modifiers' => [
            ['name' => 'Regular', 'price_delta' => 0, 'is_active' => 1],
            ['name' => 'Large', 'price_delta' => 6000, 'is_active' => 1],
        ],
    ]);

    $response->assertRedirect();
    expect(ModifierGroup::where('name', 'Ukuran Gelas')->exists())->toBeTrue();
    expect(Modifier::where('name', 'Large')->value('price_delta'))->toBe(6000.0);
});
