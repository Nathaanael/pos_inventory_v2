<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kasir yang membuka panel admin/portal (mis. lewat URL atau tombol back)
 * dialihkan ke portal kasir, bukan diberi halaman error 403.
 */
class RedirectKasirToPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->isKasir()) {
            return redirect()->to(url('/kasir'));
        }

        return $next($request);
    }
}
