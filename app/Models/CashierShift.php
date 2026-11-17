<?php

namespace App\Models;

use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashierShift extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_cash' => 'float',
            'expected_cash' => 'float',
            'actual_cash' => 'float',
            'cash_difference' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class)->latest('id');
    }

    public function outbounds(): HasMany
    {
        return $this->hasMany(Outbound::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OutboundPayment::class);
    }

    public function cashCounts(): HasMany
    {
        return $this->hasMany(CashCount::class)->latest('counted_at');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public static function activeFor(?int $userId): ?self
    {
        if (! $userId) {
            return null;
        }

        return static::query()->open()->where('user_id', $userId)->latest('id')->first();
    }

    /**
     * Ringkasan X-Report / Z-Report untuk shift ini.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $paymentsByMethod = $this->payments()
            ->whereHas('outbound', fn ($q) => $q->where('status', '!=', TransactionStatus::VOIDED->value))
            ->selectRaw('payment_method, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('payment_method')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->payment_method => ['total' => (float) $row->total, 'count' => (int) $row->count]])
            ->all();

        $cashSales = (float) ($paymentsByMethod['tunai']['total'] ?? 0);
        $totalSales = (float) collect($paymentsByMethod)->sum('total');

        $cashIn = (float) $this->movements()->where('type', 'in')->sum('amount');
        $cashOut = (float) $this->movements()->where('type', 'out')->sum('amount');
        $expectedCash = (float) $this->opening_cash + $cashSales + $cashIn - $cashOut;

        $ordersCount = $this->outbounds()
            ->where('status', TransactionStatus::COMPLETED->value)
            ->count();

        $voidCount = $this->outbounds()
            ->where('status', TransactionStatus::VOIDED->value)
            ->count();

        return [
            'opening_cash' => (float) $this->opening_cash,
            'cash_sales' => $cashSales,
            'total_sales' => $totalSales,
            'payments_by_method' => $paymentsByMethod,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'expected_cash' => $expectedCash,
            'orders_count' => $ordersCount,
            'void_count' => $voidCount,
        ];
    }
}
