<?php

namespace App\Models;

use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Shift kasir = satu laci kas. Satu shift bisa berisi banyak kasir (anggota, tabel cashier_shift_members):
 *  - shift pribadi : dibuka sendiri oleh kasir (user_id = kasir itu, anggota tunggal & penanggung jawab).
 *  - shift bersama : dibuat admin (user_id kosong) dengan beberapa kasir anggota dan satu penanggung jawab
 *                    (supervisor_id). Status "scheduled" sampai penanggung jawab membukanya (isi modal awal);
 *                    penanggung jawab juga yang menutup shift.
 * Shift aktif seorang kasir = shift terbuka yang ia ikuti (activeFor).
 */
class CashierShift extends Model
{
    /** Dibuat admin, menunggu dibuka penanggung jawab. */
    public const STATUS_SCHEDULED = 'scheduled';

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

    protected static function booted(): void
    {
        // Shift pribadi (dibuka kasir sendiri): pemiliknya otomatis menjadi anggota, pembuka & penanggung jawab.
        static::created(function (self $shift) {
            if ($shift->user_id) {
                $shift->members()->syncWithoutDetaching([$shift->user_id => ['added_by' => $shift->opened_by ?? $shift->user_id]]);
                $shift->forceFill([
                    'opened_by' => $shift->opened_by ?? $shift->user_id,
                    'supervisor_id' => $shift->supervisor_id ?? $shift->user_id,
                ])->saveQuietly();
            }
        });
    }

    /** Pemilik shift pribadi (kosong untuk shift bersama buatan admin). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Kasir yang tergabung di shift ini. */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'cashier_shift_members')
            ->withPivot('added_by')
            ->withTimestamps()
            ->orderBy('cashier_shift_members.created_at');
    }

    /** Penanggung jawab: membuka (modal awal) & menutup shift. */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function isSupervisor(?int $userId): bool
    {
        return $userId !== null && (int) $this->supervisor_id === (int) $userId;
    }

    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SCHEDULED);
    }

    /** Shift yang belum selesai (belum dibuka maupun sedang berjalan). */
    public function scopeNotClosed(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_SCHEDULED, self::STATUS_OPEN]);
    }

    /** Shift bersama yang diikuti pengguna dan masih menunggu dibuka penanggung jawab. */
    public static function pendingFor(?int $userId): ?self
    {
        if (! $userId) {
            return null;
        }

        return static::query()->pending()
            ->whereHas('members', fn ($q) => $q->where('users.id', $userId))
            ->latest('id')
            ->first();
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** Shift bersama yang dibuat admin (bukan dibuka sendiri oleh satu kasir). */
    public function isManaged(): bool
    {
        return $this->user_id === null;
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /** Nama tampilan: nama shift, atau nama kasir anggotanya. */
    public function label(): string
    {
        if (filled($this->name)) {
            return $this->name;
        }

        $names = $this->members->pluck('name')->filter();

        return $names->isNotEmpty() ? $names->implode(', ') : ($this->user?->name ?? 'Shift #'.$this->id);
    }

    /**
     * Id pengguna yang tergabung di shift yang belum selesai (terjadwal / berjalan).
     * Satu kasir hanya boleh di satu shift yang belum selesai.
     *
     * @return array<int, int>
     */
    public static function busyUserIds(?int $exceptShiftId = null): array
    {
        return \Illuminate\Support\Facades\DB::table('cashier_shift_members')
            ->join('cashier_shifts', 'cashier_shifts.id', '=', 'cashier_shift_members.cashier_shift_id')
            ->whereIn('cashier_shifts.status', [self::STATUS_SCHEDULED, self::STATUS_OPEN])
            ->when($exceptShiftId, fn ($q) => $q->where('cashier_shifts.id', '!=', $exceptShiftId))
            ->pluck('cashier_shift_members.user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
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

        // Shift terbuka yang diikuti pengguna (pribadi maupun bersama).
        return static::query()->open()
            ->whereHas('members', fn ($q) => $q->where('users.id', $userId))
            ->latest('id')
            ->first();
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
