<?php

use App\Enums\BusinessType;
use App\Models\Member;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function memberStore(): array
{
    StoreSetting::flushCache();

    $make = fn (string $role, string $email) => User::create([
        'name' => ucfirst($role).' Member',
        'email' => $email,
        'password' => bcrypt('password'),
        'role' => $role,
        'is_active' => true,
        'must_reset_password' => false,
    ]);

    StoreSetting::current()->update(['store_name' => 'Toko Member', 'setup_completed_at' => now()]);
    StoreSetting::current()->applyBusinessType(BusinessType::Umum);
    StoreSetting::flushCache();

    return ['admin' => $make(User::ROLE_ADMIN, 'admin@member.test'), 'kasir' => $make(User::ROLE_KASIR, 'kasir@member.test')];
}

it('admin bisa menambah, mengubah, dan menghapus member', function () {
    ['admin' => $admin] = memberStore();

    // Admin: tabel penuh + modal (tombol "Simpan & buat lagi" ada di footer modal).
    $this->actingAs($admin)->get(route('members.index'))->assertOk()->assertSee('Daftar Member')
        ->assertSee('Simpan &amp; buat lagi', false);

    $this->actingAs($admin)->postJson(route('members.store'), [
        'name' => 'Budi Santoso',
        'phone' => '0812-3456 7890',
        'birth_date' => '1990-05-17',
    ])->assertOk()->assertJson(['message' => 'Member berhasil ditambahkan']);

    $member = Member::sole();
    expect($member->phone)->toBe('081234567890') // tersimpan tanpa spasi / tanda hubung
        ->and($member->birth_date->format('Y-m-d'))->toBe('1990-05-17')
        ->and($member->created_by)->toBe($admin->id);

    $this->actingAs($admin)->get(route('members.index'))->assertSee('Budi Santoso')->assertSee('0812 3456 7890');

    $this->actingAs($admin)->putJson(route('members.update', $member), [
        'name' => 'Budi S.',
        'phone' => '081234567890', // nomor sendiri boleh dipakai lagi saat ubah
        'birth_date' => '1990-05-18',
    ])->assertOk();
    expect($member->fresh()->name)->toBe('Budi S.');

    $this->actingAs($admin)->delete(route('members.destroy', $member))->assertRedirect();
    expect(Member::count())->toBe(0);
});

it('kasir bisa menambah dan mengubah member dari panel kasir, tapi tidak menghapus', function () {
    ['kasir' => $kasir] = memberStore();

    // Kasir: form tambah langsung tampil (bukan modal), tabel di sampingnya.
    $this->actingAs($kasir)->get(route('kasir.members.index'))->assertOk()
        ->assertSee('Simpan Member')->assertSee('id="member-form"', false)->assertSee('id="members-table"', false)
        ->assertDontSee('Hapus Member')->assertDontSee('Simpan &amp; buat lagi', false);

    $this->actingAs($kasir)->postJson(route('kasir.members.store'), [
        'name' => 'Siti Aminah',
        'phone' => '+6281298765432',
        'birth_date' => '1995-01-02',
    ])->assertOk();

    $member = Member::sole();
    $this->actingAs($kasir)->putJson(route('kasir.members.update', $member), [
        'name' => 'Siti A.',
        'phone' => '+6281298765432',
        'birth_date' => '1995-01-02',
    ])->assertOk();
    expect($member->fresh()->name)->toBe('Siti A.');

    // Halaman & rute hapus admin tidak bisa dipakai kasir.
    $this->actingAs($kasir)->get(route('members.index'))->assertRedirect(url('/kasir'));
    $this->actingAs($kasir)->delete(route('members.destroy', $member))->assertRedirect(url('/kasir'));
    expect(Member::count())->toBe(1);
});

it('memvalidasi nama, nomor telepon unik, dan tanggal lahir', function () {
    ['kasir' => $kasir] = memberStore();
    Member::create(['name' => 'Ada', 'phone' => '081111111111', 'birth_date' => '1990-01-01']);

    $this->actingAs($kasir)->postJson(route('kasir.members.store'), [
        'name' => '',
        'phone' => '0811 1111 1111', // sama dengan member lain setelah dinormalisasi
        'birth_date' => now()->addDay()->format('Y-m-d'),
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'phone', 'birth_date'])
        ->assertJsonPath('errors.phone.0', 'Nomor telepon ini sudah terdaftar sebagai member.');

    $this->actingAs($kasir)->postJson(route('kasir.members.store'), [
        'name' => 'Salah Nomor',
        'phone' => 'abc123',
        'birth_date' => '2000-01-01',
    ])->assertStatus(422)->assertJsonValidationErrors('phone');

    expect(Member::count())->toBe(1);
});

it('mencari member berdasarkan nama atau nomor telepon', function () {
    ['admin' => $admin] = memberStore();
    Member::create(['name' => 'Rina', 'phone' => '081200000001', 'birth_date' => '1990-01-01']);
    Member::create(['name' => 'Joko', 'phone' => '081399999999', 'birth_date' => '1990-01-01']);

    $this->actingAs($admin)->get(route('members.index', ['q' => 'rin']))->assertSee('Rina')->assertDontSee('Joko');
    $this->actingAs($admin)->get(route('members.index', ['q' => '0813-9999']))->assertSee('Joko')->assertDontSee('Rina');
});

it('mencari member dari kolom nama pelanggan di POS (nama atau nomor HP)', function () {
    ['kasir' => $kasir] = memberStore();
    Member::create(['name' => 'Rina Wati', 'phone' => '081200000001', 'birth_date' => '1990-01-01']);
    Member::create(['name' => 'Joko', 'phone' => '081399999999', 'birth_date' => '1990-01-01']);

    $this->actingAs($kasir)->getJson(route('kasir.members.search', ['q' => 'rin']))
        ->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'Rina Wati')->assertJsonPath('0.phone_label', '0812 0000 0001');

    $this->actingAs($kasir)->getJson(route('kasir.members.search', ['q' => '0813-9999']))
        ->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'Joko');

    $this->actingAs($kasir)->getJson(route('kasir.members.search', ['q' => 'x']))->assertOk()->assertJsonCount(0);

    $this->actingAs($kasir)->get(route('kasir.pos'))->assertOk()->assertSee('Nama pelanggan / cari member');
});

it('tambah member cepat dari POS mengembalikan data member untuk langsung dipilih', function () {
    ['kasir' => $kasir] = memberStore();

    $this->actingAs($kasir)->postJson(route('kasir.members.store'), [
        'name' => 'Pelanggan Baru',
        'phone' => '0857 1111 2222',
        'birth_date' => '1999-09-09',
    ])->assertOk()
        ->assertJsonPath('member.name', 'Pelanggan Baru')
        ->assertJsonPath('member.phone', '085711112222');
});

it('menyimpan member terpilih ke transaksi POS dengan nama pelanggan = nama member', function () {
    ['kasir' => $kasir] = memberStore();
    $member = Member::create(['name' => 'Siti Member', 'phone' => '081277778888', 'birth_date' => '1992-02-02']);
    $item = \App\Models\Item::create([
        'code' => 'ITM-MBR', 'name' => 'Air Mineral',
        'category_id' => \App\Models\Category::create(['name' => 'Minuman'])->id,
        'unit_id' => \App\Models\Unit::create(['name' => 'botol'])->id,
        'harga_beli' => 2000, 'harga_jual_retail' => 4000, 'current_stock' => 10, 'min_stock' => 0, 'is_active' => true,
    ]);

    $this->actingAs($kasir)->postJson(route('kasir.checkout'), [
        'lines' => [['id' => $item->id, 'qty' => 1]],
        'payment_method' => 'tunai',
        'paid_amount' => 5000,
        'customer_name' => 'nama diketik lain',
        'member_id' => $member->id,
        'sale_id' => 'member-sale-1',
    ])->assertOk()->assertJson(['ok' => true]);

    $outbound = \App\Models\Outbound::sole();
    expect($outbound->member_id)->toBe($member->id)
        ->and($outbound->customer_name)->toBe('Siti Member');
});
