<?php

namespace App\Http\Middleware;

use App\Enums\Feature;
use App\Models\StoreSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman hanya bisa dibuka bila fitur aktif (Pengaturan Toko → Fitur).
 * Pemakaian: ->middleware('feature:merek')
 */
class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless(StoreSetting::hasFeature(Feature::from($feature)), 403, 'Fitur ini belum diaktifkan.');

        return $next($request);
    }
}
