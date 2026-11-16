<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Dashboard admin. Widget (statistik, grafik penjualan, stok terendah, dll.)
 * akan dikonversi pada tahap Dashboard & Laporan.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('pages.dashboard.index', ['title' => 'Dashboard']);
    }
}
