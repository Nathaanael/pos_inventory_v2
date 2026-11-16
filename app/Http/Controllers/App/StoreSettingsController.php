<?php

namespace App\Http\Controllers\App;

use App\Enums\BusinessType;
use App\Enums\Feature;
use App\Http\Controllers\Controller;
use App\Models\StoreFeature;
use App\Models\StoreSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pengaturan Toko (dulu App\Filament\Pages\StoreSettings): identitas usaha, logo,
 * jenis usaha & fitur, kontak, struk & printer.
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

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'store_name' => ['required', 'string', 'max:100'],
            'tagline' => ['nullable', 'string', 'max:150'],
            'logo' => ['nullable', 'image', 'max:1024'],
            'business_type' => ['required', Rule::enum(BusinessType::class)],
            'features' => ['array'],
            'features.*' => [Rule::enum(Feature::class)],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:100'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
            'printer_name' => ['nullable', 'string', 'max:100'],
        ], [], [
            'store_name' => 'Nama Usaha',
            'tagline' => 'Keterangan Singkat',
            'logo' => 'Logo',
            'business_type' => 'Jenis Usaha',
            'address' => 'Alamat',
            'phone' => 'Telepon / WhatsApp',
            'email' => 'Email',
            'tax_number' => 'NPWP',
            'receipt_footer' => 'Teks Penutup Struk',
            'printer_name' => 'Nama Printer Thermal',
        ]);

        $setting = StoreSetting::current();
        $features = $data['features'] ?? [];
        $attributes = collect($data)->except(['logo', 'features'])->all();
        $oldLogo = $setting->logo;

        // Logo: ganti dengan file baru, atau hapus bila diminta. File lama dibuang dari disk.
        if ($request->hasFile('logo')) {
            $attributes['logo'] = $request->file('logo')->store('store', 'public');
        } elseif ($request->boolean('remove_logo')) {
            $attributes['logo'] = null;
        }

        DB::transaction(function () use ($setting, $attributes, $features) {
            $setting->update($attributes);
            StoreSetting::saveFeatures($features);
        });

        if (array_key_exists('logo', $attributes) && $oldLogo && $oldLogo !== $attributes['logo']) {
            Storage::disk('public')->delete($oldLogo);
        }

        StoreSetting::flushCache();

        return redirect()->route('store-settings.edit')->with('success', 'Pengaturan toko disimpan');
    }
}
