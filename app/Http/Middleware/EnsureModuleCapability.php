<?php

namespace App\Http\Middleware;

use App\Models\StoreSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman hanya ada di modul (jenis usaha) yang memakainya.
 * Pemakaian: ->middleware('module:suppliers') atau ->middleware('module:taxes')
 */
class EnsureModuleCapability
{
    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $allowed = match ($capability) {
            'suppliers' => StoreSetting::usesSuppliers(),
            'taxes' => StoreSetting::usesTaxes(),
            'modifiers' => StoreSetting::usesModifiers(),
            'recipes' => StoreSetting::usesRecipes(),
            'cash_count' => StoreSetting::usesCashCount(),
            // Stock opname barang saat ini hanya untuk modul selain FNB (FNB memakai Hitung Kas).
            'item_opname' => ! StoreSetting::usesCashCount(),
            default => false,
        };

        abort_unless($allowed, 404);

        return $next($request);
    }
}
