<?php

namespace App\Services;

use App\Models\Inbound;
use App\Models\Item;
use App\Models\Outbound;
use App\Models\OutboundItem;
use App\Models\ReturnItem;
use App\Models\ReturnTransaction;
use App\Models\WasteLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Data widget dashboard admin (dipindah dari App\Filament\Widgets\*).
 * Perhitungan sama persis dengan versi Filament.
 */
class DashboardStats
{
    public const PERIODS = [
        'today' => 'Hari Ini',
        '7' => '7 Hari Terakhir',
        '30' => '30 Hari Terakhir',
        'month' => 'Bulan Ini',
        'year' => 'Tahun Ini',
    ];

    public function __construct(private string $period = '30')
    {
        if (! array_key_exists($this->period, self::PERIODS)) {
            $this->period = '30';
        }
    }

    public function period(): string
    {
        return $this->period;
    }

    public function periodLabel(): string
    {
        return self::PERIODS[$this->period];
    }

    public function startDate(): Carbon
    {
        return match ($this->period) {
            'today' => Carbon::today(),
            '7' => Carbon::now()->subDays(6)->startOfDay(),
            'month' => Carbon::now()->startOfMonth(),
            'year' => Carbon::now()->startOfYear(),
            default => Carbon::now()->subDays(29)->startOfDay(),
        };
    }

    /**
     * Analitik khusus modul F&B: Food Cost %, Waste Total, Penjualan per Order Type, dan Top Menu.
     *
     * @return array<string, mixed>
     */
    public function fnbAnalytics(): array
    {
        $start = $this->startDate();

        $netSales = (float) Outbound::where('status', 'selesai')
            ->where('created_at', '>=', $start)
            ->selectRaw('COALESCE(SUM(total_amount - COALESCE(discount_amount, 0)), 0) as net')
            ->value('net');

        $totalCogs = (float) Outbound::where('status', 'selesai')
            ->where('created_at', '>=', $start)
            ->sum('total_cogs');

        $wasteTotal = (float) WasteLog::where('wasted_at', '>=', $start)->sum('total_cost');
        $foodCostPct = $netSales > 0 ? round((($totalCogs + $wasteTotal) / $netSales) * 100, 2) : 0.0;

        $byOrderType = Outbound::where('status', 'selesai')
            ->where('created_at', '>=', $start)
            ->whereNotNull('order_type')
            ->selectRaw('order_type, COUNT(*) as count, SUM(COALESCE(grand_total, total_amount)) as total')
            ->groupBy('order_type')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->order_type->value ?? (string) $r->order_type => ['count' => (int) $r->count, 'total' => (float) $r->total]])
            ->all();

        $topMenus = OutboundItem::join('outbounds', 'outbound_items.outbound_id', '=', 'outbounds.id')
            ->join('items', 'outbound_items.item_id', '=', 'items.id')
            ->where('outbounds.status', 'selesai')
            ->where('outbounds.created_at', '>=', $start)
            ->groupBy('items.id', 'items.name')
            ->selectRaw('items.id, items.name, SUM(outbound_items.quantity) as qty, SUM(outbound_items.subtotal) as revenue, SUM(outbound_items.profit) as profit')
            ->orderByDesc('qty')
            ->limit(10)
            ->get()
            ->all();

        return [
            'net_sales' => $netSales,
            'total_cogs' => $totalCogs,
            'waste_total' => $wasteTotal,
            'food_cost_pct' => $foodCostPct,
            'sales_by_order_type' => $byOrderType,
            'top_menus' => $topMenus,
        ];
    }

    /* ---------------------------- StatsOverviewWidget ---------------------------- */

    /** @return array<string, mixed> */
    public function overview(): array
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        // Penjualan dikurangi retur dari customer.
        $salesToday = $this->salesOn($today) - $this->customerReturnsOn($today);
        $salesYesterday = $this->salesOn($yesterday) - $this->customerReturnsOn($yesterday);

        // Laba kotor dikurangi laba yang batal karena diretur.
        $profitToday = $this->profitOn($today) - $this->returnedProfitOn($today);
        $profitYesterday = $this->profitOn($yesterday) - $this->returnedProfitOn($yesterday);

        $lowStock = Item::whereColumn('current_stock', '<=', 'min_stock')->where('min_stock', '>', 0)->count();

        return [
            'sales_today' => $salesToday,
            'sales_trend' => $this->trend($salesToday, $salesYesterday),
            'profit_today' => $profitToday,
            'profit_trend' => $this->trend($profitToday, $profitYesterday),
            'inbound_today' => (float) Inbound::whereDate('created_at', $today)->where('status', 'diterima')->sum('total_amount'),
            'low_stock' => $lowStock,
        ];
    }

    /* ---------------------------- SalesAndProfitChart ---------------------------- */

    /** @return array{labels: array<int, string>, sales: array<int, float>, profits: array<int, float>} */
    public function salesAndProfit(): array
    {
        $days = match ($this->period) {
            'today' => 1,
            '7' => 7,
            'month' => Carbon::now()->day,
            'year' => Carbon::now()->dayOfYear,
            default => 30,
        };
        $start = $this->startDate();

        $sales = Outbound::selectRaw('DATE(created_at) as date, SUM(total_amount) as total')
            ->where('status', 'selesai')
            ->where('created_at', '>=', $start)
            ->groupByRaw('DATE(created_at)')
            ->pluck('total', 'date');

        $profits = OutboundItem::join('outbounds', 'outbound_items.outbound_id', '=', 'outbounds.id')
            ->where('outbounds.status', 'selesai')
            ->where('outbounds.created_at', '>=', $start)
            ->groupByRaw('DATE(outbounds.created_at)')
            ->selectRaw('DATE(outbounds.created_at) as date, SUM(outbound_items.profit) as total')
            ->pluck('total', 'date');

        $result = ['labels' => [], 'sales' => [], 'profits' => []];

        for ($i = 0; $i < $days; $i++) {
            $date = Carbon::now()->subDays($days - 1 - $i);
            $key = $date->format('Y-m-d');
            $result['labels'][] = $date->locale('id')->translatedFormat('d M');
            $result['sales'][] = (float) $sales->get($key, 0);
            $result['profits'][] = (float) $profits->get($key, 0);
        }

        return $result;
    }

    /* ---------------------------- SalesByCategoryChart ---------------------------- */

    /** @return array{labels: array<int, string>, totals: array<int, float>} */
    public function salesByCategory(): array
    {
        $data = OutboundItem::select('categories.name as category_name', DB::raw('SUM(outbound_items.subtotal) as total'))
            ->join('outbounds', 'outbound_items.outbound_id', '=', 'outbounds.id')
            ->join('items', 'outbound_items.item_id', '=', 'items.id')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->where('outbounds.status', 'selesai')
            ->where('outbounds.created_at', '>=', $this->startDate())
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total')
            ->get();

        $top = $data->take(5);
        $others = (float) $data->skip(5)->sum('total');

        $labels = $top->pluck('category_name')->all();
        $totals = $top->pluck('total')->map(fn ($v) => (float) $v)->all();

        if ($others > 0) {
            $labels[] = 'Lainnya';
            $totals[] = $others;
        }

        return ['labels' => $labels, 'totals' => $totals];
    }

    /* ---------------------------- TopSellingItemsTable ---------------------------- */

    public function topSelling()
    {
        return Item::query()
            ->select(
                'items.id',
                'items.name as item_name',
                DB::raw('SUM(outbound_items.quantity) as total_qty'),
                DB::raw('SUM(outbound_items.subtotal) as total_revenue'),
                DB::raw('SUM(outbound_items.profit) as total_profit'),
            )
            ->join('outbound_items', 'items.id', '=', 'outbound_items.item_id')
            ->join('outbounds', 'outbound_items.outbound_id', '=', 'outbounds.id')
            ->where('outbounds.status', 'selesai')
            ->where('outbounds.created_at', '>=', $this->startDate())
            ->groupBy('items.id', 'items.name')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();
    }

    /* ---------------------------- BottomStockTable ---------------------------- */

    public function lowStock(int $perPage = 5)
    {
        return Item::query()
            ->whereColumn('current_stock', '<=', 'min_stock')
            ->where('min_stock', '>', 0)
            ->orderByRaw('(min_stock - current_stock) DESC')
            ->paginate($perPage, ['*'], 'stok_page')
            ->withQueryString();
    }

    /* ---------------------------- ProfitLossOverview ---------------------------- */

    /** @return array<string, float> */
    public function profitLoss(): array
    {
        $start = $this->startDate();
        $end = Carbon::now()->endOfDay();

        $revenue = (float) Outbound::where('status', 'selesai')
            ->whereDate('created_at', '>=', $start)
            ->whereDate('created_at', '<=', $end)
            ->sum('total_amount');

        $cogs = (float) OutboundItem::whereHas('outbound', fn ($q) => $q->where('status', 'selesai')->where('created_at', '>=', $start))
            ->sum(DB::raw('cogs_price * quantity'));

        $grossProfit = $revenue - $cogs;

        $returns = (float) ReturnTransaction::where('status', 'selesai')->where('created_at', '>=', $start)->sum('total_amount');

        return [
            'revenue' => $revenue,
            'cogs' => $cogs,
            'gross_profit' => $grossProfit,
            'returns' => $returns,
            'net_profit' => $grossProfit - $returns,
        ];
    }

    /* ---------------------------- Helpers ---------------------------- */

    private function salesOn(Carbon $date): float
    {
        return (float) Outbound::whereDate('created_at', $date)->where('status', 'selesai')->sum('total_amount');
    }

    private function customerReturnsOn(Carbon $date): float
    {
        return (float) ReturnTransaction::whereDate('created_at', $date)->where('status', 'selesai')->where('type', '!=', 'supplier')->sum('total_amount');
    }

    private function profitOn(Carbon $date): float
    {
        return (float) OutboundItem::whereHas('outbound', fn ($q) => $q->whereDate('created_at', $date)->where('status', 'selesai'))->sum('profit');
    }

    private function returnedProfitOn(Carbon $date): float
    {
        return (float) ReturnItem::whereHas('returnTransaction', fn ($q) => $q->whereDate('created_at', $date)->where('status', 'selesai')->where('type', '!=', 'supplier'))
            ->with('item')
            ->get()
            ->sum(fn ($line) => ($line->price - ($line->item->harga_beli ?? 0)) * $line->quantity);
    }

    private function trend(float $today, float $yesterday): float
    {
        return $yesterday > 0 ? (($today - $yesterday) / $yesterday) * 100 : ($today > 0 ? 100 : 0);
    }
}
