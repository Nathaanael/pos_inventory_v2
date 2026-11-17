<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\StoreFeature;
use App\Models\StoreSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Pengaturan Toko (dulu App\Filament\Pages\StoreSettings): identitas usaha, logo, kontak,
 * struk & printer. Jenis usaha & fitur hanya ditampilkan; menggantinya lewat wizard setup.
 */
class StoreSettingsController extends Controller
{
    public function edit(): View
    {
        return view('pages.app.store-settings', [
            'title' => 'Pengaturan Toko',
            'setting' => StoreSetting::current(),
            'enabledFeatures' => StoreFeature::query()->where('is_enabled', true)->pluck('feature')->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'store_name' => ['required', 'string', 'max:100'],
            'tagline' => ['nullable', 'string', 'max:150'],
            'logo' => ['nullable', 'image', 'max:1024'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:100'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
            'printer_name' => ['nullable', 'string', 'max:100'],
            'cash_count_tolerance' => ['nullable', 'numeric', 'min:0'],
        ], [], [
            'store_name' => 'Nama Usaha',
            'tagline' => 'Keterangan Singkat',
            'logo' => 'Logo',
            'address' => 'Alamat',
            'phone' => 'Telepon / WhatsApp',
            'email' => 'Email',
            'tax_number' => 'NPWP',
            'receipt_footer' => 'Teks Penutup Struk',
            'printer_name' => 'Nama Printer Thermal',
            'cash_count_tolerance' => 'Toleransi Selisih Kas',
        ]);

        $setting = StoreSetting::current();
        // Jenis usaha & fitur tidak diubah di sini: hanya lewat wizard (tombol "Ganti Jenis Usaha").
        $attributes = collect($data)->except(['logo'])->all();
        // Field toleransi hanya tampil di modul FNB; kosong = 0.
        if (array_key_exists('cash_count_tolerance', $attributes)) {
            $attributes['cash_count_tolerance'] = (float) ($attributes['cash_count_tolerance'] ?? 0);
        }
        $oldLogo = $setting->logo;

        // Logo: ganti dengan file baru, atau hapus bila diminta. File lama dibuang dari disk.
        if ($request->hasFile('logo')) {
            $attributes['logo'] = $request->file('logo')->store('store', 'public');
        } elseif ($request->boolean('remove_logo')) {
            $attributes['logo'] = null;
        }

        $setting->update($attributes);

        if (array_key_exists('logo', $attributes) && $oldLogo && $oldLogo !== $attributes['logo']) {
            Storage::disk('public')->delete($oldLogo);
        }

        StoreSetting::flushCache();

        return $request->expectsJson()
            ? $this->respond($request, 'Pengaturan toko disimpan')
            : redirect()->route('store-settings.edit')->with('success', 'Pengaturan toko disimpan');
    }
}
