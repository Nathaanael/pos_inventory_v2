<?php

namespace App\Http\Controllers;

use App\Services\DashboardStats;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard admin (dulu App\Filament\Pages\Dashboard + widget).
 * Filter periode: ?period=today|7|30|month|year (default 30 hari terakhir).
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $stats = new DashboardStats((string) $request->query('period', '30'));

        return view('pages.dashboard.index', [
            'title' => 'Dashboard',
            'stats' => $stats,
            'overview' => $stats->overview(),
            'salesChart' => $stats->salesAndProfit(),
            'categoryChart' => $stats->salesByCategory(),
            'topSelling' => $stats->topSelling(),
            'lowStock' => $stats->lowStock(),
            'profitLoss' => $stats->profitLoss(),
        ]);
    }
}
