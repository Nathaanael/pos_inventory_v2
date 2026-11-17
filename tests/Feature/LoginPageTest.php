<?php

use App\Enums\BusinessType;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('menampilkan carousel ilustrasi semua jenis usaha di halaman login', function () {
    StoreSetting::flushCache();
    StoreSetting::current()->update(['setup_completed_at' => now()]);
    StoreSetting::flushCache();

    $response = $this->get('/login')->assertOk()
        ->assertSee('animate-marquee-up', false)
        ->assertSee('animate-marquee-down', false);

    foreach (BusinessType::cases() as $business) {
        $response->assertSee($business->illustration(), false)->assertSee($business->label());
        expect(public_path("assets/images/business/{$business->value}.svg"))->toBeFile();
    }
});
