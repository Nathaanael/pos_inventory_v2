<?php

use App\Enums\BusinessType;
use App\Models\CashCount;
use App\Models\CashierShift;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function cashCountStore(BusinessType $business = BusinessType::Makanan, float $tolerance = 0): array
{
    StoreSetting::flushCache();

    $make = fn (string $role, string $email) => User::create([
        'name' => ucfirst($role).' Kas',
        'email' => $email,
        'password' => bcrypt('password'),
        'role' => $role,
        'is_active' => true,
        'must_reset_password' => false,
    ]);

    $admin = $make(User::ROLE_ADMIN, 'admin-kas@fnb.test');
    $kasir = $make(User::ROLE_KASIR, 'kasir-kas@fnb.test');

    $setting = StoreSetting::current();
    $setting->update(['store_name' => 'Kopi Kas', 'setup_completed_at' => now(), 'cash_count_tolerance' => $tolerance]);
    $setting->applyBusinessType($business);
    StoreSetting::flushCache();

    $shift = CashierShift::create([
        'user_id' => $kasir->id,
        'opened_at' => now(),
        'opening_cash' => 200000,
        'status' => CashierShift::STATUS_OPEN,
    ]);

    return compact('admin', 'kasir', 'shift');
}

it('hanya tersedia di modul FNB, berdampingan dengan stock opname barang', function () {
    ['admin' => $admin] = cashCountStore();

    $this->actingAs($admin)->get(route('cash-counts.index'))->assertOk()->assertSee('Kasir Kas');
    $this->actingAs($admin)->get(route('stock-opnames.index'))->assertOk();
});

it('tidak tersedia di modul selain FNB', function () {
    ['admin' => $admin] = cashCountStore(BusinessType::Bangunan);

    $this->actingAs($admin)->get(route('cash-counts.index'))->assertNotFound();
    $this->actingAs($admin)->get(route('stock-opnames.index'))->assertOk();
});

it('mencatat cek dadakan per pecahan dengan snapshot uang sistem', function () {
    ['admin' => $admin, 'shift' => $shift] = cashCountStore();

    // Form hitung tidak menampilkan angka sistem (blind count).
    $this->actingAs($admin)->get(route('cash-counts.create', $shift))->assertOk()->assertDontSee('200.000');

    $this->actingAs($admin)->post(route('cash-counts.store', $shift), [
        'count_mode' => 'pecahan',
        'denominations' => ['100000' => 1, '50000' => 1, '20000' => 2, '500' => 4],
        'actual_cash' => 999999, // diabaikan pada mode pecahan
    ])->assertRedirect();

    $count = CashCount::sole();
    expect($count->type)->toBe(CashCount::TYPE_SPOT)
        ->and($count->expected_cash)->toBe(200000.0)
        ->and($count->actual_cash)->toBe(192000.0)
        ->and($count->difference)->toBe(-8000.0)
        ->and($count->denominations)->toBe(['100000' => 1, '50000' => 1, '20000' => 2, '500' => 4])
        ->and($count->status)->toBe(CashCount::STATUS_REVIEW)
        ->and($count->counted_by)->toBe($admin->id);
});

it('menganggap cocok bila selisih masih dalam toleransi', function () {
    ['admin' => $admin, 'shift' => $shift] = cashCountStore(tolerance: 5000);

    $this->actingAs($admin)->post(route('cash-counts.store', $shift), [
        'count_mode' => 'total',
        'actual_cash' => 196000,
    ])->assertRedirect();

    expect(CashCount::sole()->status)->toBe(CashCount::STATUS_MATCH);
});

it('tidak bisa diakses kasir', function () {
    ['kasir' => $kasir, 'shift' => $shift] = cashCountStore();

    // Middleware "admin" mengarahkan kasir ke portal kasir.
    $this->actingAs($kasir)->get(route('cash-counts.index'))->assertRedirect(url('/kasir'));
    $this->actingAs($kasir)->post(route('cash-counts.store', $shift), ['actual_cash' => 1])->assertRedirect(url('/kasir'));
    expect(CashCount::count())->toBe(0);
});

it('tutup shift otomatis membuat hitung kas dan kasir tidak melihat estimasi kas', function () {
    ['kasir' => $kasir, 'shift' => $shift] = cashCountStore();
    $shift->movements()->create(['type' => 'in', 'amount' => 50000, 'reason' => 'Tambah receh', 'created_by' => $kasir->id]);

    // Estimasi kas = 250.000; kasir tidak boleh melihatnya sebelum shift ditutup.
    $this->actingAs($kasir)->get(route('kasir.shift.index'))->assertOk()
        ->assertSee('Rp •••••')
        ->assertDontSee('Rp250.000');

    $this->actingAs($kasir)->post(route('kasir.shift.close'), [
        'count_mode' => 'pecahan',
        'denominations' => ['100000' => 2, '50000' => 1],
        'notes' => 'Serah terima',
    ])->assertRedirect();

    $shift->refresh();
    $count = CashCount::sole();
    expect($shift->status)->toBe(CashierShift::STATUS_CLOSED)
        ->and($shift->actual_cash)->toBe(250000.0)
        ->and($count->type)->toBe(CashCount::TYPE_CLOSING)
        ->and($count->cashier_shift_id)->toBe($shift->id)
        ->and($count->status)->toBe(CashCount::STATUS_MATCH)
        ->and($count->notes)->toBe('Serah terima');
});

it('tutup shift di modul selain FNB tidak membuat hitung kas', function () {
    ['kasir' => $kasir] = cashCountStore(BusinessType::Bangunan);

    $this->actingAs($kasir)->post(route('kasir.shift.close'), ['actual_cash' => 200000])->assertRedirect();

    expect(CashCount::count())->toBe(0);
});

it('menyetujui selisih wajib dengan penjelasan', function () {
    ['admin' => $admin, 'shift' => $shift] = cashCountStore();
    $count = CashCount::record($shift, CashCount::TYPE_SPOT, 150000, null, $admin->id);
    expect($count->status)->toBe(CashCount::STATUS_REVIEW);

    $this->actingAs($admin)->get(route('cash-counts.show', $count))->assertOk()->assertSee('Setujui Selisih');

    $this->actingAs($admin)->post(route('cash-counts.approve', $count), ['review_notes' => ''])
        ->assertSessionHasErrors('review_notes');

    $this->actingAs($admin)->post(route('cash-counts.approve', $count), ['review_notes' => 'Kembalian salah'])
        ->assertRedirect();

    $count->refresh();
    expect($count->status)->toBe(CashCount::STATUS_APPROVED)
        ->and($count->reviewed_by)->toBe($admin->id)
        ->and($count->review_notes)->toBe('Kembalian salah');
});

it('menyimpan toleransi selisih kas dari Pengaturan Toko', function () {
    ['admin' => $admin] = cashCountStore();

    $this->actingAs($admin)->get(route('store-settings.edit'))->assertOk()->assertSee('Toleransi Selisih Kas');

    $this->actingAs($admin)->put(route('store-settings.update'), [
        'store_name' => 'Kopi Kas',
        'cash_count_tolerance' => 2500,
    ])->assertRedirect();

    StoreSetting::flushCache();
    expect(StoreSetting::current()->cash_count_tolerance)->toBe(2500.0);
});

it('menyimpan mutasi kas lewat AJAX dan mengembalikan JSON', function () {
    ['kasir' => $kasir, 'shift' => $shift] = cashCountStore();

    $this->actingAs($kasir)->postJson(route('kasir.shift.cash-movement'), [
        'type' => 'out',
        'amount' => 20000,
        'reason' => 'Beli es batu',
    ])->assertOk()->assertJson(['message' => 'Mutasi kas berhasil dicatat.']);

    $this->actingAs($kasir)->postJson(route('kasir.shift.cash-movement'), ['type' => 'out'])
        ->assertStatus(422)->assertJsonValidationErrors(['amount', 'reason']);

    expect($shift->movements()->count())->toBe(1);

    // Halaman yang diambil ulang ajaxForm memuat region ringkasan & kartu petty cash terbaru.
    $this->actingAs($kasir)->get(route('kasir.shift.index'))
        ->assertSee('id="shift-summary"', false)
        ->assertSee('id="shift-petty-cash"', false)
        ->assertSee('-Rp20.000');
});

function cashCountItem(): \App\Models\Item
{
    return \App\Models\Item::create([
        'code' => 'ITM-KAS-1',
        'name' => 'Es Teh',
        'category_id' => \App\Models\Category::create(['name' => 'Minuman'])->id,
        'unit_id' => \App\Models\Unit::create(['name' => 'gelas'])->id,
        'harga_beli' => 2000,
        'harga_jual_retail' => 5000,
        'current_stock' => 50,
        'min_stock' => 0,
        'is_active' => true,
    ]);
}

it('memblokir checkout POS FNB sebelum shift dibuka', function () {
    ['admin' => $admin] = cashCountStore();
    $item = cashCountItem();

    $this->actingAs($admin)->get(route('kasir.pos'))->assertOk()->assertSee('Transaksi tidak bisa disimpan sebelum shift dibuka');

    $this->actingAs($admin)->postJson(route('kasir.checkout'), [
        'lines' => [['id' => $item->id, 'qty' => 1]],
        'order_type' => 'take_away',
        'payment_method' => 'tunai',
        'paid_amount' => 5000,
        'sale_id' => 'no-shift-1',
    ])->assertOk()->assertJson(['ok' => false, 'shift_required' => true]);

    expect(\App\Models\Outbound::count())->toBe(0);

    // Setelah buka shift, checkout dengan sale_id yang sama berhasil (lock dobel tidak tertahan).
    openShiftFor($admin);
    $this->actingAs($admin)->postJson(route('kasir.checkout'), [
        'lines' => [['id' => $item->id, 'qty' => 1]],
        'order_type' => 'take_away',
        'payment_method' => 'tunai',
        'paid_amount' => 5000,
        'sale_id' => 'no-shift-1',
    ])->assertOk()->assertJson(['ok' => true]);
});

it('tidak memblokir checkout POS di modul selain FNB', function () {
    ['admin' => $admin] = cashCountStore(BusinessType::Umum);
    $item = cashCountItem();

    $this->actingAs($admin)->postJson(route('kasir.checkout'), [
        'lines' => [['id' => $item->id, 'qty' => 1]],
        'payment_method' => 'tunai',
        'paid_amount' => 5000,
        'sale_id' => 'retail-1',
    ])->assertOk()->assertJson(['ok' => true]);
});

it('menampilkan ringkasan shift aktif di halaman Hitung Kas', function () {
    ['admin' => $admin, 'shift' => $shift] = cashCountStore();
    $shift->movements()->create(['type' => 'out', 'amount' => 15000, 'reason' => 'Gas', 'created_by' => $admin->id]);

    $this->actingAs($admin)->get(route('cash-counts.index'))->assertOk()
        ->assertSee('Shift Kasir Sedang Berjalan (1)')
        ->assertSee('Estimasi Kas di Laci')
        ->assertSee('Rp185.000')   // 200.000 - 15.000
        ->assertSee('-Rp15.000')
        ->assertSee('belum pernah');
});

it('form shift, hitung kas, dan review membalas JSON untuk AJAX', function () {
    ['admin' => $admin, 'kasir' => $kasir, 'shift' => $shift] = cashCountStore();

    // Buka shift saat masih ada shift aktif → 409
    $this->actingAs($kasir)->postJson(route('kasir.shift.open'), ['opening_cash' => 0])
        ->assertStatus(409)->assertJson(['message' => 'Shift masih aktif']);

    // Cek dadakan → redirect ke detail (pesan sukses di-flash untuk halaman tujuan)
    $response = $this->actingAs($admin)->postJson(route('cash-counts.store', $shift), [
        'count_mode' => 'pecahan',
        'denominations' => ['500' => 1],
    ])->assertOk();
    $count = CashCount::sole();
    $response->assertJson(['message' => 'Hitung kas tersimpan', 'redirect' => route('cash-counts.show', $count)]);

    $this->actingAs($admin)->postJson(route('cash-counts.approve', $count), [])
        ->assertStatus(422)->assertJsonValidationErrors('review_notes');
    $this->actingAs($admin)->postJson(route('cash-counts.approve', $count), ['review_notes' => 'Kurang modal'])
        ->assertOk()->assertJson(['message' => 'Selisih disetujui']);

    // Tutup shift → JSON, lalu buka shift baru → JSON
    $this->actingAs($kasir)->postJson(route('kasir.shift.close'), ['count_mode' => 'total', 'actual_cash' => 200000])
        ->assertOk()->assertJson(['message' => 'Shift kasir berhasil ditutup (Z-Report tersimpan).']);
    $this->actingAs($kasir)->postJson(route('kasir.shift.open'), ['opening_cash' => 100000])
        ->assertOk()->assertJson(['message' => 'Shift kasir berhasil dibuka.']);

    expect(CashierShift::activeFor($kasir->id)?->opening_cash)->toBe(100000.0);
});

it('waste log dan pengaturan toko membalas JSON untuk AJAX', function () {
    ['admin' => $admin] = cashCountStore();
    $item = cashCountItem();
    $item->update(['track_stock' => true]);

    $this->actingAs($admin)->postJson(route('waste-logs.store'), ['item_id' => $item->id, 'quantity' => 2, 'reason' => 'spillage'])
        ->assertOk()->assertJson(['message' => 'Waste berhasil dicatat dan stok telah disesuaikan.']);
    $this->actingAs($admin)->postJson(route('waste-logs.store'), [])
        ->assertStatus(422)->assertJsonValidationErrors(['item_id', 'quantity', 'reason']);

    $this->actingAs($admin)->putJson(route('store-settings.update'), ['store_name' => 'Kopi Baru'])
        ->assertOk()->assertJson(['message' => 'Pengaturan toko disimpan']);
    $this->actingAs($admin)->get(route('store-settings.edit'))
        ->assertSee('id="store-settings-form"', false)->assertSee('data-ajax-region="store-brand"', false);
});
