<?php

use App\Enums\BusinessType;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Item;
use App\Models\Outbound;
use App\Models\StoreSetting;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function openBillStore(): array
{
    StoreSetting::flushCache();

    $kasir = User::create([
        'name' => 'Kasir Open Bill',
        'email' => 'kasir-ob@fnb.test',
        'password' => bcrypt('password'),
        'role' => User::ROLE_KASIR,
        'is_active' => true,
        'must_reset_password' => false,
    ]);

    StoreSetting::current()->update(['store_name' => 'Resto Bayar Nanti', 'setup_completed_at' => now()]);
    StoreSetting::current()->applyBusinessType(BusinessType::Makanan);
    StoreSetting::flushCache();

    $table = DiningTable::create(['number' => 'A2', 'name' => 'VIP 2', 'is_active' => true]);
    $item = Item::create([
        'code' => 'MN-NASGOR', 'name' => 'Nasi Goreng',
        'category_id' => Category::create(['name' => 'Makanan'])->id,
        'unit_id' => Unit::create(['name' => 'porsi'])->id,
        'harga_beli' => 10000, 'harga_jual_retail' => 25000, 'current_stock' => 50, 'min_stock' => 0, 'is_active' => true,
    ]);

    openShiftFor($kasir);

    return compact('kasir', 'table', 'item');
}

function createOpenBill(User $kasir, array $payload): int
{
    $result = test()->actingAs($kasir)->postJson(route('kasir.checkout'), [
        'save_as_open_bill' => true,
        'sale_id' => uniqid('ob-'),
        ...$payload,
    ])->assertOk()->assertJson(['ok' => true, 'is_open_bill' => true])->json();

    return $result['outbound_id'];
}

it('menampilkan Open Bill belum lunas (meja & take away) di POS', function () {
    ['kasir' => $kasir, 'table' => $table, 'item' => $item] = openBillStore();

    createOpenBill($kasir, ['lines' => [['id' => $item->id, 'qty' => 2]], 'order_type' => 'dine_in', 'table_id' => $table->id, 'customer_name' => 'Pak Andi']);
    createOpenBill($kasir, ['lines' => [['id' => $item->id, 'qty' => 1]], 'order_type' => 'take_away', 'customer_name' => 'Bu Rina']);

    $this->actingAs($kasir)->get(route('kasir.pos'))->assertOk()
        ->assertDontSee('Tagihan Belum Lunas') // daftar di bawah POS diganti modal
        ->assertSee('Tekan tagihan untuk membayar')
        ->assertSee('Bayar Open Bill')
        ->assertSee('Pak Andi')
        ->assertSee('Bu Rina'); // take away (tanpa meja) juga bisa dibayar dari POS
});

it('membayar Open Bill tunai dengan uang lebih dan mengembalikan kembalian', function () {
    ['kasir' => $kasir, 'table' => $table, 'item' => $item] = openBillStore();
    $id = createOpenBill($kasir, ['lines' => [['id' => $item->id, 'qty' => 2]], 'order_type' => 'dine_in', 'table_id' => $table->id]);

    $remaining = Outbound::findOrFail($id)->remainingBalance();

    $response = $this->actingAs($kasir)->postJson(route('kasir.orders.pay', $id), [
        'payment_method' => 'tunai',
        'paid_amount' => $remaining + 30000,
    ])->assertOk()->assertJson(['ok' => true]);

    expect($response->json('change'))->toEqual(30000)
        ->and($response->json('open_bills'))->toBe([])
        ->and(Outbound::findOrFail($id)->remainingBalance())->toEqual(0.0);
});

it('menolak pembayaran tunai yang kurang dari sisa tagihan', function () {
    ['kasir' => $kasir, 'item' => $item] = openBillStore();
    $id = createOpenBill($kasir, ['lines' => [['id' => $item->id, 'qty' => 1]], 'order_type' => 'take_away']);

    $this->actingAs($kasir)->postJson(route('kasir.orders.pay', $id), [
        'payment_method' => 'tunai',
        'paid_amount' => 1000,
    ])->assertOk()->assertJson(['ok' => false]);

    expect(Outbound::findOrFail($id)->remainingBalance())->toBeGreaterThan(0.0);
});

it('halaman Meja memakai modal bayar bersama', function () {
    ['kasir' => $kasir] = openBillStore();

    $this->actingAs($kasir)->get(route('kasir.meja'))->assertOk()
        ->assertSee('Lunasi Tagihan')
        ->assertSee('formLoading', false);
});
