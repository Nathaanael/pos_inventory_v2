<?php

use App\Enums\BusinessType;
use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Item;
use App\Models\StoreSetting;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function shiftMembersStore(): array
{
    StoreSetting::flushCache();

    $make = fn (string $name, string $role) => User::create([
        'name' => $name,
        'email' => strtolower(str_replace(' ', '', $name)).'@shift.test',
        'password' => bcrypt('password'),
        'role' => $role,
        'is_active' => true,
        'must_reset_password' => false,
    ]);

    StoreSetting::current()->update(['store_name' => 'Toko Shift', 'setup_completed_at' => now()]);
    StoreSetting::current()->applyBusinessType(BusinessType::Umum);
    StoreSetting::flushCache();

    $item = Item::create([
        'code' => 'ITM-SHF', 'name' => 'Sabun',
        'category_id' => Category::create(['name' => 'Umum'])->id,
        'unit_id' => Unit::create(['name' => 'pcs'])->id,
        'harga_beli' => 3000, 'harga_jual_retail' => 5000, 'current_stock' => 100, 'min_stock' => 0, 'is_active' => true,
    ]);

    return [
        'admin' => $make('Admin Toko', User::ROLE_ADMIN),
        'ani' => $make('Ani', User::ROLE_KASIR),
        'budi' => $make('Budi', User::ROLE_KASIR),
        'citra' => $make('Citra', User::ROLE_KASIR),
        'item' => $item,
    ];
}

/** Admin membuat shift bersama (status: belum dibuka) dengan penanggung jawab. */
function createGroupShift($test, User $admin, array $members, User $supervisor, string $name = 'Shift Pagi'): CashierShift
{
    $test->actingAs($admin)->postJson(route('shifts.store'), [
        'name' => $name,
        'members' => collect($members)->pluck('id')->all(),
        'supervisor_id' => $supervisor->id,
    ])->assertOk()->assertJsonStructure(['redirect']);

    return CashierShift::where('name', $name)->sole();
}

it('admin membuat shift berisi beberapa kasir dengan satu penanggung jawab, status belum dibuka', function () {
    ['admin' => $admin, 'ani' => $ani, 'budi' => $budi] = shiftMembersStore();

    $shift = createGroupShift($this, $admin, [$ani, $budi], $ani);

    expect($shift->status)->toBe(CashierShift::STATUS_SCHEDULED)
        ->and($shift->user_id)->toBeNull()
        ->and($shift->supervisor_id)->toBe($ani->id)
        ->and($shift->opened_at)->toBeNull()
        ->and($shift->members->pluck('name')->all())->toBe(['Ani', 'Budi'])
        // Belum dibuka: belum ada yang bisa berjualan.
        ->and(CashierShift::activeFor($ani->id))->toBeNull()
        ->and(CashierShift::pendingFor($budi->id)?->id)->toBe($shift->id);

    $this->actingAs($admin)->get(route('shifts.index'))->assertOk()
        ->assertSee('Belum dibuka')->assertSee('Menunggu dibuka oleh Ani');
    $this->actingAs($admin)->get(route('shifts.show', $shift))->assertOk()
        ->assertSee('Menunggu dibuka oleh Ani')->assertSee('Penanggung Jawab')->assertSee('Batalkan Shift');
});

it('penanggung jawab harus salah satu kasir di shift', function () {
    ['admin' => $admin, 'ani' => $ani, 'citra' => $citra] = shiftMembersStore();

    $this->actingAs($admin)->postJson(route('shifts.store'), [
        'name' => 'Shift Salah',
        'members' => [$ani->id],
        'supervisor_id' => $citra->id,
    ])->assertStatus(422)->assertJsonValidationErrors('supervisor_id');
});

it('hanya penanggung jawab yang bisa membuka shift dengan modal awal', function () {
    ['admin' => $admin, 'ani' => $ani, 'budi' => $budi] = shiftMembersStore();
    $shift = createGroupShift($this, $admin, [$ani, $budi], $ani);

    // Anggota biasa: menunggu PJ.
    $this->actingAs($budi)->get(route('kasir.shift.index'))->assertOk()
        ->assertSee('Shift Pagi')->assertSee('Menunggu')->assertSee('Budi (Anda)');
    $this->actingAs($budi)->postJson(route('kasir.shift.open'), ['opening_cash' => 50000])->assertStatus(403);

    // PJ melihat form Buka Shift lalu membukanya.
    $this->actingAs($ani)->get(route('kasir.shift.index'))->assertOk()->assertSee('Anda penanggung jawab shift ini');
    $this->actingAs($ani)->postJson(route('kasir.shift.open'), ['opening_cash' => 100000])->assertOk();

    $shift->refresh();
    expect($shift->status)->toBe(CashierShift::STATUS_OPEN)
        ->and($shift->opened_by)->toBe($ani->id)
        ->and((float) $shift->opening_cash)->toEqual(100000.0)
        ->and(CashierShift::activeFor($budi->id)?->id)->toBe($shift->id)
        ->and(CashierShift::where('user_id', $ani->id)->exists())->toBeFalse(); // tidak membuat shift pribadi
});

it('transaksi semua kasir masuk ke laci shift yang sama setelah dibuka', function () {
    ['admin' => $admin, 'ani' => $ani, 'budi' => $budi, 'item' => $item] = shiftMembersStore();
    $shift = createGroupShift($this, $admin, [$ani, $budi], $ani);
    $this->actingAs($ani)->postJson(route('kasir.shift.open'), ['opening_cash' => 100000])->assertOk();

    foreach ([$ani, $budi] as $i => $kasir) {
        $this->actingAs($kasir)->postJson(route('kasir.checkout'), [
            'lines' => [['id' => $item->id, 'qty' => 2]],
            'payment_method' => 'tunai',
            'paid_amount' => 10000,
            'sale_id' => 'group-'.$i,
        ])->assertOk()->assertJson(['ok' => true]);
    }

    $summary = $shift->fresh()->summary();
    expect($summary['orders_count'])->toBe(2)
        ->and($summary['expected_cash'])->toEqual(120000.0); // modal 100.000 + 2 × 10.000

    $this->actingAs($admin)->get(route('shifts.show', $shift))->assertOk()->assertSee('1 transaksi');
});

it('hanya penanggung jawab yang menutup shift dari panel kasir', function () {
    ['admin' => $admin, 'ani' => $ani, 'budi' => $budi] = shiftMembersStore();
    $shift = createGroupShift($this, $admin, [$ani, $budi], $ani);
    $this->actingAs($ani)->postJson(route('kasir.shift.open'), ['opening_cash' => 100000])->assertOk();

    $this->actingAs($budi)->get(route('kasir.shift.index'))->assertOk()
        ->assertSee('Penutupan shift dan penghitungan kas dilakukan oleh penanggung jawab');
    $this->actingAs($budi)->postJson(route('kasir.shift.close'), ['actual_cash' => 100000])->assertStatus(403);

    $this->actingAs($ani)->postJson(route('kasir.shift.close'), ['count_mode' => 'total', 'actual_cash' => 100000])->assertOk();

    $shift->refresh();
    expect($shift->status)->toBe(CashierShift::STATUS_CLOSED)
        ->and($shift->closed_by)->toBe($ani->id)
        ->and(CashierShift::activeFor($budi->id))->toBeNull();
});

it('admin mengganti penanggung jawab, mengelola kasir, dan bisa menutup sebagai cadangan', function () {
    ['admin' => $admin, 'ani' => $ani, 'budi' => $budi, 'citra' => $citra] = shiftMembersStore();
    $shift = createGroupShift($this, $admin, [$ani, $budi], $ani);

    // PJ tidak bisa dikeluarkan sebelum diganti.
    $this->actingAs($admin)->deleteJson(route('shifts.members.remove', [$shift, $ani]))->assertStatus(422);

    $this->actingAs($admin)->putJson(route('shifts.supervisor', $shift), ['supervisor_id' => $budi->id])->assertOk();
    $this->actingAs($admin)->putJson(route('shifts.supervisor', $shift), ['supervisor_id' => $citra->id])
        ->assertStatus(422)->assertJsonValidationErrors('supervisor_id'); // bukan anggota

    $this->actingAs($admin)->deleteJson(route('shifts.members.remove', [$shift, $ani]))->assertOk();
    $this->actingAs($admin)->postJson(route('shifts.members.add', $shift), ['user_id' => $citra->id])->assertOk();
    expect($shift->fresh()->members->pluck('name')->sort()->values()->all())->toBe(['Budi', 'Citra']);

    $this->actingAs($budi)->postJson(route('kasir.shift.open'), ['opening_cash' => 0])->assertOk();
    $this->actingAs($admin)->postJson(route('shifts.close', $shift), ['count_mode' => 'total', 'actual_cash' => 0])
        ->assertOk()->assertJson(['message' => 'Shift ditutup']);
    expect($shift->fresh()->closed_by)->toBe($admin->id);
});

it('satu kasir tidak bisa berada di dua shift yang belum selesai', function () {
    ['admin' => $admin, 'ani' => $ani, 'budi' => $budi, 'citra' => $citra] = shiftMembersStore();
    $pagi = createGroupShift($this, $admin, [$ani], $ani); // masih belum dibuka

    $this->actingAs($admin)->postJson(route('shifts.store'), [
        'name' => 'Shift Kedua',
        'members' => [$ani->id, $citra->id],
        'supervisor_id' => $citra->id,
    ])->assertStatus(422)->assertJsonValidationErrors('members');

    // Kasir yang membuka shift pribadi juga dianggap sibuk.
    $this->actingAs($citra)->post(route('kasir.shift.open'), ['opening_cash' => 50000])->assertRedirect();
    $this->actingAs($admin)->postJson(route('shifts.members.add', $pagi), ['user_id' => $citra->id])
        ->assertStatus(422)->assertJsonValidationErrors('user_id');
});

it('admin membatalkan shift yang belum dibuka', function () {
    ['admin' => $admin, 'ani' => $ani] = shiftMembersStore();
    $shift = createGroupShift($this, $admin, [$ani], $ani);

    $this->actingAs($admin)->delete(route('shifts.destroy', $shift))->assertRedirect(route('shifts.index'));

    expect(CashierShift::find($shift->id))->toBeNull()
        ->and(CashierShift::pendingFor($ani->id))->toBeNull();
});

it('shift yang dibuka kasir sendiri otomatis berisi kasir itu sebagai penanggung jawab', function () {
    ['ani' => $ani] = shiftMembersStore();

    $this->actingAs($ani)->post(route('kasir.shift.open'), ['opening_cash' => 50000])->assertRedirect();

    $shift = CashierShift::activeFor($ani->id);
    expect($shift)->not->toBeNull()
        ->and($shift->user_id)->toBe($ani->id)
        ->and($shift->supervisor_id)->toBe($ani->id)
        ->and($shift->members->pluck('id')->all())->toBe([$ani->id]);
});
