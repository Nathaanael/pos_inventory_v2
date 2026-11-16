<?php

namespace App\Http\Controllers\App;

use App\Enums\BusinessType;
use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Wizard setup toko (dulu App\Filament\Pages\SetupWizard). Muncul untuk admin selama
 * toko belum di-setup (lihat EnsureStoreIsSetUp).
 *
 * Langkah: 1 Identitas Toko → 2 Jenis Usaha → 3 Fitur → 4 Ringkasan.
 * Jenis usaha = modul. Langkah "Fitur" hanya menampilkan fitur bawaan modul (tidak bisa dicentang).
 * Perpindahan langkah terjadi di browser; data dikirim sekali saat "Mulai Gunakan".
 */
class SetupController extends Controller
{
    public const STEPS = [
        1 => 'Identitas Toko',
        2 => 'Jenis Usaha',
        3 => 'Fitur',
        4 => 'Ringkasan',
    ];

    /** Field → langkah, untuk membuka langkah yang berisi error. */
    private const FIELD_STEP = [
        'store_name' => 1, 'tagline' => 1, 'phone' => 1, 'address' => 1, 'logo' => 1,
        'business_type' => 2,
    ];

    public function show(): View|RedirectResponse
    {
        $setting = StoreSetting::current();

        if ($setting->isSetUp()) {
            return redirect()->route('dashboard');
        }

        return view('pages.app.setup', [
            'title' => 'Setup Toko',
            'setting' => $setting,
            'errorStep' => $this->errorStep(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $setting = StoreSetting::current();

        if ($setting->isSetUp()) {
            return redirect()->route('dashboard');
        }

        $data = $request->validate([
            'store_name' => ['required', 'string', 'max:100'],
            'tagline' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'logo' => ['nullable', 'image', 'max:1024'],
            'business_type' => ['required', Rule::enum(BusinessType::class)],
        ], [
            'business_type.required' => 'Pilih salah satu jenis usaha.',
        ], [
            'store_name' => 'Nama toko',
            'tagline' => 'Keterangan singkat',
            'phone' => 'Telepon',
            'address' => 'Alamat',
            'logo' => 'Logo',
        ]);

        $attributes = [
            'store_name' => trim($data['store_name']),
            'tagline' => filled($data['tagline'] ?? null) ? trim($data['tagline']) : null,
            'phone' => filled($data['phone'] ?? null) ? trim($data['phone']) : null,
            'address' => filled($data['address'] ?? null) ? trim($data['address']) : null,
            'setup_completed_at' => now(),
        ];

        if ($request->hasFile('logo')) {
            $attributes['logo'] = $request->file('logo')->store('store', 'public');
        }

        // Fitur tidak dipilih user: selalu mengikuti modul jenis usaha.
        DB::transaction(function () use ($setting, $attributes, $data) {
            $setting->update($attributes);
            $setting->applyBusinessType(BusinessType::from($data['business_type']));
        });

        StoreSetting::flushCache();

        return redirect()->route('dashboard')
            ->with('success', ['Toko siap digunakan', 'Pengaturan bisa diubah kapan saja di menu Pengaturan Toko.']);
    }

    /** Pengaturan Toko → "Ganti Jenis Usaha": buka wizard lagi. */
    public function restart(): RedirectResponse
    {
        StoreSetting::current()->restartSetup();

        return redirect()->route('setup');
    }

    /**
     * Batal ganti jenis usaha: kembali ke pengaturan semula tanpa perubahan.
     * Hanya bila toko sudah pernah punya jenis usaha (bukan setup pertama).
     */
    public function cancel(): RedirectResponse
    {
        $setting = StoreSetting::current();

        if ($setting->isSetUp() || ! $setting->business_type) {
            return redirect()->route($setting->isSetUp() ? 'store-settings.edit' : 'setup');
        }

        $setting->update(['setup_completed_at' => now()]);
        StoreSetting::flushCache();

        return redirect()->route('store-settings.edit')->with('success', 'Jenis usaha tidak diubah');
    }

    private function errorStep(): int
    {
        $errors = session('errors')?->keys() ?? [];

        foreach ($errors as $key) {
            $field = explode('.', $key)[0];
            if (isset(self::FIELD_STEP[$field])) {
                return self::FIELD_STEP[$field];
            }
        }

        return 1;
    }
}
