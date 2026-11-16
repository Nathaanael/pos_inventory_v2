<?php

namespace App\Http\Middleware;

use App\Models\StoreSetting;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Selama toko belum di-setup (store_settings.setup_completed_at kosong):
 *  - admin selalu diarahkan ke wizard setup (/setup),
 *  - kasir diarahkan ke halaman "Toko belum disiapkan, hubungi admin" (/kasir/belum-siap).
 */
class EnsureStoreIsSetUp
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            ! $user instanceof User
            || $request->routeIs('logout')
            || StoreSetting::current()->isSetUp()
        ) {
            return $next($request);
        }

        $target = $user->isKasir() ? 'kasir.belum-siap' : 'setup';

        // Halaman tujuan belum dibuat (tahap konversi berikutnya): jangan blokir.
        if (! Route::has($target) || $request->routeIs($target)) {
            return $next($request);
        }

        return redirect()->route($target);
    }
}
