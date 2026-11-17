<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Models\CashierShift;
use App\Models\Inbound;
use App\Models\InboundItem;
use App\Models\Item;
use App\Models\ItemSupplier;
use App\Models\Outbound;
use App\Models\OutboundItem;
use App\Models\OutboundPayment;
use App\Models\ReturnTransaction;
use App\Models\StockMovement;
use App\Models\WasteLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SATU-SATUNYA tempat yang boleh mengubah stok barang.
 *
 * Semua transaksi (barang masuk, barang keluar/POS, retur) memanggil class ini,
 * baik dari tombol di panel admin, portal kasir, maupun model. Kalau aturan stok
 * berubah (mis. jumlah desimal, FIFO per batch), cukup ubah file ini.
 *
 * Setiap perubahan stok selalu:
 *  1. mengunci baris barang (lockForUpdate) supaya aman dipakai bersamaan,
 *  2. mengubah items.current_stock,
 *  3. menyesuaikan stok per supplier (item_supplier),
 *  4. mencatat mutasi di stock_movements.
 */
class StockService
{
    /* =====================================================================
     |  BARANG MASUK
     * ===================================================================== */

    /**
     * Setujui barang masuk: status jadi "diterima" dan stok bertambah.
     */
    public function approveInbound(Inbound $inbound, string $notes = 'Barang Masuk (Approved)'): void
    {
        DB::transaction(function () use ($inbound, $notes) {
            $inbound->update(['status' => TransactionStatus::RECEIVED]);

            foreach ($inbound->items as $line) {
                $this->receiveInboundLine($inbound, $line, $notes);
            }
        });
    }

    /**
     * Tambah stok untuk satu baris barang masuk yang dibuat langsung berstatus
     * "diterima" (dipanggil dari model InboundItem).
     */
    public function receiveInboundItem(InboundItem $line): void
    {
        DB::transaction(fn () => $this->receiveInboundLine($line->inbound, $line, 'Barang Masuk'));
    }

    /**
     * Batalkan barang masuk yang sudah diterima: stok dikurangi kembali.
     */
    public function voidInbound(Inbound $inbound, string $reason): void
    {
        DB::transaction(function () use ($inbound, $reason) {
            $inbound->update([
                'status' => TransactionStatus::VOIDED,
                'voided_at' => now(),
                'voided_by' => auth()->id(),
                'void_reason' => $reason,
            ]);

            foreach ($inbound->items as $line) {
                $item = $this->lockItem($line->item_id);

                if ($inbound->supplier_id) {
                    ItemSupplier::where('item_id', $item->id)
                        ->where('supplier_id', $inbound->supplier_id)
                        ->decrement('current_stock', $line->quantity);
                }

                $this->move($item, -$line->quantity, MovementType::VOID, 'inbounds', $inbound->id, $line->buy_price, 'Batal Barang Masuk: ' . $reason);
            }
        });
    }

    private function receiveInboundLine(Inbound $inbound, InboundItem $line, string $notes): void
    {
        $item = $this->lockItem($line->item_id);

        if ($inbound->supplier_id) {
            ItemSupplier::where('item_id', $item->id)
                ->where('supplier_id', $inbound->supplier_id)
                ->increment('current_stock', $line->quantity);
        }

        $this->move($item, $line->quantity, MovementType::INBOUND, 'inbounds', $inbound->id, $line->buy_price, $notes);
    }

    /* =====================================================================
     |  BARANG KELUAR / PENJUALAN (termasuk POS admin & portal kasir)
     * ===================================================================== */

    /**
     * Simpan transaksi barang keluar dari keranjang POS. Transaksi langsung
     * berstatus "selesai" dan stok langsung berkurang (FIFO per supplier),
     * tanpa perlu persetujuan admin.
     *
     * @param  array<string, mixed>  $header  transaction_code, date, customer_name, destination, price_type_applied, notes
     * @param  array<int, array{item_id:int, quantity:int, sell_price:float, cogs_price:float, subtotal:float}>  $lines
     *
     * @throws ValidationException jika stok salah satu barang tidak cukup (tidak ada yang tersimpan).
     */
    public function createOutbound(array $header, array $lines, bool $isOpenBill = false): Outbound
    {
        return DB::transaction(function () use ($header, $lines, $isOpenBill) {
            $activeShiftId = $header['cashier_shift_id'] ?? CashierShift::activeFor(auth()->id())?->id;

            $outbound = Outbound::create([
                ...$header,
                'status' => TransactionStatus::PENDING,
                'payment_status' => $isOpenBill ? PaymentStatus::UNPAID : PaymentStatus::PAID,
                'paid_at' => $isOpenBill ? null : ($header['paid_at'] ?? now()),
                'created_by' => auth()->id(),
                'cashier_shift_id' => $activeShiftId,
                'total_amount' => collect($lines)->sum('subtotal'),
                'total_cogs' => collect($lines)->sum(fn ($l) => $l['cogs_price'] * $l['quantity']),
            ]);

            foreach ($lines as $line) {
                $modifiersTotal = (float) ($line['modifiers_total'] ?? 0);
                $unitSell = (float) $line['sell_price'] + $modifiersTotal;

                OutboundItem::create([
                    'outbound_id' => $outbound->id,
                    'item_id' => $line['item_id'],
                    'quantity' => $line['quantity'],
                    'sell_price' => $line['sell_price'],
                    'cogs_price' => $line['cogs_price'],
                    'subtotal' => $line['subtotal'],
                    'profit' => ($unitSell - (float) $line['cogs_price']) * $line['quantity'],
                    'modifiers_snapshot' => $line['modifiers_snapshot'] ?? null,
                    'modifiers_total' => $modifiersTotal,
                    'notes' => $line['notes'] ?? null,
                ]);
            }

            $targetStatus = $isOpenBill ? TransactionStatus::OPEN : TransactionStatus::COMPLETED;
            $this->completeOutbound($outbound->load('items'), $isOpenBill ? 'Open Bill (Pesanan Meja)' : 'Barang Keluar', $targetStatus);

            if (! $isOpenBill && ! empty($header['payment_method'])) {
                OutboundPayment::create([
                    'outbound_id' => $outbound->id,
                    'cashier_shift_id' => $activeShiftId,
                    'payment_method' => $header['payment_method'],
                    'amount' => $outbound->payableTotal(),
                    'paid_at' => now(),
                    'created_by' => auth()->id(),
                ]);
            }

            return $outbound->refresh();
        });
    }

    /**
     * Tambahkan item baru ke Open Bill yang sudah ada, potong stok untuk item baru,
     * dan hitung ulang total + pajak.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function addItemsToOutbound(Outbound $outbound, array $lines): Outbound
    {
        return DB::transaction(function () use ($outbound, $lines) {
            $outbound = Outbound::lockForUpdate()->findOrFail($outbound->id);

            if ($outbound->status !== TransactionStatus::OPEN) {
                throw ValidationException::withMessages([
                    'order' => 'Hanya pesanan Open Bill yang bisa ditambah item.',
                ]);
            }

            $createdLines = [];
            foreach ($lines as $line) {
                $item = $this->lockItem($line['item_id']);
                $this->ensureSufficientStock($item, (float) $line['quantity']);

                $modifiersTotal = (float) ($line['modifiers_total'] ?? 0);
                $unitSell = (float) $line['sell_price'] + $modifiersTotal;

                $created = OutboundItem::create([
                    'outbound_id' => $outbound->id,
                    'item_id' => $line['item_id'],
                    'quantity' => $line['quantity'],
                    'sell_price' => $line['sell_price'],
                    'cogs_price' => $line['cogs_price'],
                    'subtotal' => $line['subtotal'],
                    'profit' => ($unitSell - (float) $line['cogs_price']) * $line['quantity'],
                    'modifiers_snapshot' => $line['modifiers_snapshot'] ?? null,
                    'modifiers_total' => $modifiersTotal,
                    'notes' => $line['notes'] ?? null,
                ]);

                $this->deductSaleItemStock($item, (float) $line['quantity'], $outbound->id, $unitSell, 'Tambah Pesanan Open Bill');
                $createdLines[] = $created;
            }

            $this->recalculateOutboundTotals($outbound);

            return $outbound->refresh();
        });
    }

    /**
     * Batalkan satu baris item dari Open Bill dan kembalikan stoknya.
     */
    public function voidOutboundItem(Outbound $outbound, OutboundItem $line, string $reason): Outbound
    {
        return DB::transaction(function () use ($outbound, $line, $reason) {
            $outbound = Outbound::lockForUpdate()->findOrFail($outbound->id);

            if ($outbound->status !== TransactionStatus::OPEN) {
                throw ValidationException::withMessages([
                    'order' => 'Hanya item pada Open Bill yang bisa dibatalkan per baris.',
                ]);
            }

            if ((int) $line->outbound_id !== (int) $outbound->id) {
                abort(404);
            }

            $item = $this->lockItem($line->item_id);
            $this->restoreSaleItemStock($item, (float) $line->quantity, $outbound->id, (float) $line->sell_price, 'Batal Item Open Bill: '.$reason);

            $line->delete();
            $this->recalculateOutboundTotals($outbound);

            return $outbound->refresh();
        });
    }

    /**
     * Bayar Open Bill (mendukung pembayaran tunggal maupun split payment).
     *
     * @param  array<int, array{payment_method: string, amount: float, notes?: ?string}>  $payments
     */
    public function payOutbound(Outbound $outbound, array $payments, ?float $cashReceived = null): Outbound
    {
        return DB::transaction(function () use ($outbound, $payments, $cashReceived) {
            $outbound = Outbound::lockForUpdate()->findOrFail($outbound->id);

            if ($outbound->status !== TransactionStatus::OPEN) {
                throw ValidationException::withMessages([
                    'order' => 'Pesanan ini sudah dibayar atau dibatalkan.',
                ]);
            }

            $activeShiftId = CashierShift::activeFor(auth()->id())?->id;

            foreach ($payments as $p) {
                OutboundPayment::create([
                    'outbound_id' => $outbound->id,
                    'cashier_shift_id' => $activeShiftId,
                    'payment_method' => $p['payment_method'],
                    'amount' => $p['amount'],
                    'paid_at' => now(),
                    'created_by' => auth()->id(),
                    'notes' => $p['notes'] ?? null,
                ]);
            }

            $paidTotal = (float) $outbound->payments()->sum('amount');
            $payable = $outbound->payableTotal();
            $isFullyPaid = $paidTotal + 0.01 >= $payable;

            $methods = $outbound->payments()->pluck('payment_method')->unique()->values();
            $summaryMethod = $methods->count() > 1 ? 'split' : ($methods->first() ?? $outbound->payment_method);

            $outbound->update([
                'payment_method' => $summaryMethod,
                'paid_amount' => $cashReceived !== null && $cashReceived >= $paidTotal ? $cashReceived : $paidTotal,
                'payment_status' => $isFullyPaid ? PaymentStatus::PAID : PaymentStatus::PARTIAL,
                'status' => $isFullyPaid ? TransactionStatus::COMPLETED : TransactionStatus::OPEN,
                'paid_at' => $isFullyPaid ? now() : null,
            ]);

            return $outbound->refresh();
        });
    }

    private function recalculateOutboundTotals(Outbound $outbound): void
    {
        $outbound->load('items.item');
        $taxLines = $outbound->items->map(fn (OutboundItem $l) => [
            'subtotal' => (float) $l->subtotal,
            'tax_rate' => $l->item?->tax_rate !== null ? (float) $l->item->tax_rate : null,
            'tax_method' => $l->item?->tax_method,
        ])->all();

        $taxes = app(TaxCalculator::class)->calculate($taxLines, $outbound->order_type?->value, null, (float) ($outbound->discount_amount ?? 0));

        $outbound->update([
            'total_amount' => $taxes['subtotal'],
            'total_cogs' => $outbound->items->sum(fn (OutboundItem $l) => (float) $l->cogs_price * $l->quantity),
            'service_amount' => $taxes['service_amount'],
            'tax_amount' => $taxes['tax_amount'],
            'grand_total' => $taxes['grand_total'],
            'tax_details' => $taxes['details'] ?: null,
        ]);
    }

    /**
     * Status jadi "selesai" (atau "open" untuk Open Bill) dan stok berkurang (FIFO per supplier).
     * Harus dipanggil di dalam DB::transaction.
     */
    private function completeOutbound(Outbound $outbound, string $notes, TransactionStatus $targetStatus = TransactionStatus::COMPLETED): void
    {
        // Cek semua stok dulu, supaya transaksi tidak setengah jadi.
        foreach ($outbound->items as $line) {
            $item = $this->lockItem($line->item_id);
            $this->ensureSufficientStock($item, (float) $line->quantity);
        }

        $outbound->update(['status' => $targetStatus]);

        foreach ($outbound->items as $line) {
            $item = $this->lockItem($line->item_id);
            $unitSell = (float) $line->sell_price + (float) ($line->modifiers_total ?? 0);
            $this->deductSaleItemStock($item, (float) $line->quantity, $outbound->id, $unitSell, $notes);
        }
    }

    /**
     * Batalkan barang keluar: status jadi "voided" dan stok dikembalikan (mutasi VOID).
     * Pembatalan kedua kali diabaikan sehingga stok tidak bertambah dobel.
     */
    public function voidOutbound(Outbound $outbound, string $reason): void
    {
        DB::transaction(function () use ($outbound, $reason) {
            $outbound = Outbound::lockForUpdate()->findOrFail($outbound->id);

            if ($outbound->status === TransactionStatus::VOIDED) {
                return;
            }

            $wasDeducted = in_array($outbound->status, [TransactionStatus::COMPLETED, TransactionStatus::OPEN], true);

            $outbound->update([
                'status' => TransactionStatus::VOIDED,
                'voided_at' => now(),
                'voided_by' => auth()->id(),
                'void_reason' => $reason,
            ]);

            if (! $wasDeducted) {
                return;
            }

            foreach ($outbound->items as $line) {
                $item = $this->lockItem($line->item_id);
                $this->restoreSaleItemStock($item, (float) $line->quantity, $outbound->id, (float) $line->sell_price, 'Batal Barang Keluar: ' . $reason);
            }
        });
    }

    /* =====================================================================
     |  RETUR
     * ===================================================================== */

    /**
     * Setujui retur.
     *  - Retur ke supplier  : stok berkurang (barang dikembalikan ke supplier).
     *  - Retur dari customer: stok bertambah (barang kembali ke toko).
     *
     * @throws ValidationException jika stok tidak cukup untuk retur ke supplier.
     */
    public function approveReturn(ReturnTransaction $return, string $errorKey = 'approve'): void
    {
        DB::transaction(function () use ($return, $errorKey) {
            $return->update(['status' => 'selesai']);

            $isSupplierReturn = $return->type === 'supplier' || empty($return->type);

            foreach ($return->items as $line) {
                $item = $this->lockItem($line->item_id);

                $itemSupplier = $return->supplier_id
                    ? ItemSupplier::firstOrCreate(
                        ['item_id' => $item->id, 'supplier_id' => $return->supplier_id],
                        ['harga_beli' => $line->price ?? $item->harga_beli, 'current_stock' => 0]
                    )
                    : null;

                if ($isSupplierReturn) {
                    if ($item->current_stock < $line->quantity) {
                        throw ValidationException::withMessages([
                            $errorKey => "Stok {$item->name} tidak cukup. Tersedia: {$item->current_stock}",
                        ]);
                    }
                    if ($itemSupplier && $itemSupplier->current_stock < $line->quantity) {
                        throw ValidationException::withMessages([
                            $errorKey => "Stok {$item->name} dari supplier ini tidak cukup. Tersedia: {$itemSupplier->current_stock}",
                        ]);
                    }

                    $qty = -$line->quantity;
                    $type = MovementType::RETURN_OUT;
                    $notes = 'Retur ke Supplier (Approved)';
                } else {
                    $qty = $line->quantity;
                    $type = MovementType::RETURN_IN;
                    $notes = 'Retur dari Customer (Approved)';
                }

                if ($itemSupplier) {
                    $itemSupplier->current_stock += $qty;
                    $itemSupplier->save();
                }

                $this->move($item, $qty, $type, 'returns', $return->id, $line->price ?? 0, $notes);
            }
        });
    }

    /**
     * Tolak retur. Stok tidak berubah.
     */
    public function rejectReturn(ReturnTransaction $return): void
    {
        $return->update(['status' => 'ditolak']);
    }

    /* =====================================================================
     |  WASTE / SPOILAGE (MODUL F&B)
     * ===================================================================== */

    /**
     * Catat bahan baku atau barang rusak/basi/terbuang dan potong stoknya.
     */
    public function recordWaste(Item $item, float $quantity, string $reason, ?string $notes = null): WasteLog
    {
        return DB::transaction(function () use ($item, $quantity, $reason, $notes) {
            $locked = $this->lockItem($item->id);

            if ($locked->current_stock + 0.0001 < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => "Stok {$locked->name} tidak cukup. Tersedia: {$locked->current_stock}",
                ]);
            }

            $unitCost = (float) $locked->calculateFifoCogs($quantity);
            $totalCost = round($unitCost * $quantity, 2);

            $waste = WasteLog::create([
                'item_id' => $locked->id,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'reason' => $reason,
                'notes' => $notes,
                'created_by' => auth()->id(),
                'wasted_at' => now(),
            ]);

            $this->deductSupplierStockFifo($locked, $quantity);
            $this->move($locked, -$quantity, MovementType::WASTE, 'waste_logs', $waste->id, $unitCost, 'Waste: '.(WasteLog::REASONS[$reason] ?? $reason));

            return $waste;
        });
    }

    /* =====================================================================
     |  INTI: satu-satunya kode yang menulis current_stock & stock_movements
     * ===================================================================== */

    private function ensureSufficientStock(Item $item, float $qty): void
    {
        if (! $item->usesRecipeStock()) {
            if ($item->current_stock + 0.0001 < $qty) {
                throw ValidationException::withMessages([
                    'stock' => "Stok {$item->name} tidak cukup. Tersedia: {$item->current_stock}",
                ]);
            }

            return;
        }

        $recipe = $item->recipe()->with('ingredients.ingredientItem')->first();
        if (! $recipe || $recipe->yield_qty <= 0) {
            return;
        }

        foreach ($recipe->ingredients as $ing) {
            $needed = round(($ing->quantityInBaseUnit() / $recipe->yield_qty) * $qty, 4);
            $ingredient = $this->lockItem($ing->ingredient_item_id);

            if ($ingredient->current_stock + 0.0001 < $needed) {
                throw ValidationException::withMessages([
                    'stock' => "Bahan baku {$ingredient->name} untuk {$item->name} tidak cukup (butuh {$needed}, tersedia {$ingredient->current_stock}).",
                ]);
            }
        }
    }

    private function deductSaleItemStock(Item $item, float $qty, int $outboundId, float $unitSell, string $notes): void
    {
        if (! $item->usesRecipeStock()) {
            $this->deductSupplierStockFifo($item, $qty);
            $this->move($item, -$qty, MovementType::OUTBOUND, 'outbounds', $outboundId, $unitSell, $notes);

            return;
        }

        $recipe = $item->recipe()->with('ingredients.ingredientItem')->first();
        if (! $recipe || $recipe->yield_qty <= 0) {
            return;
        }

        foreach ($recipe->ingredients as $ing) {
            $needed = round(($ing->quantityInBaseUnit() / $recipe->yield_qty) * $qty, 4);
            $ingredient = $this->lockItem($ing->ingredient_item_id);
            $this->deductSupplierStockFifo($ingredient, $needed);
            $this->move($ingredient, -$needed, MovementType::OUTBOUND, 'outbounds', $outboundId, (float) $ingredient->harga_beli, "Bahan Resep: {$item->name}");
        }
    }

    private function restoreSaleItemStock(Item $item, float $qty, int $outboundId, float $unitSell, string $notes): void
    {
        if (! $item->usesRecipeStock()) {
            $this->restoreSupplierStock($item, $qty);
            $this->move($item, $qty, MovementType::VOID, 'outbounds', $outboundId, $unitSell, $notes);

            return;
        }

        $recipe = $item->recipe()->with('ingredients.ingredientItem')->first();
        if (! $recipe || $recipe->yield_qty <= 0) {
            return;
        }

        foreach ($recipe->ingredients as $ing) {
            $needed = round(($ing->quantityInBaseUnit() / $recipe->yield_qty) * $qty, 4);
            $ingredient = $this->lockItem($ing->ingredient_item_id);
            $this->restoreSupplierStock($ingredient, $needed);
            $this->move($ingredient, $needed, MovementType::VOID, 'outbounds', $outboundId, (float) $ingredient->harga_beli, "Batal Bahan Resep: {$item->name}");
        }
    }

    /**
     * Potong stok per supplier secara FIFO: supplier yang paling awal
     * terdaftar (id terkecil) dihabiskan dulu.
     */
    private function deductSupplierStockFifo(Item $item, float|int $quantity): void
    {
        $remaining = (float) $quantity;

        $supplierStocks = $item->itemSuppliers()
            ->where('current_stock', '>', 0)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($supplierStocks as $supplierStock) {
            if ($remaining <= 0) {
                break;
            }

            $deduct = min($remaining, $supplierStock->current_stock);
            $supplierStock->current_stock -= $deduct;
            $supplierStock->save();

            $remaining -= $deduct;
        }
    }

    /**
     * Kembalikan stok per supplier saat penjualan dibatalkan. Pemotongan FIFO
     * mengambil dari supplier paling awal, jadi stok dikembalikan ke sana juga.
     * (Rincian potongan per supplier tidak disimpan, sehingga ini pendekatan.)
     */
    private function restoreSupplierStock(Item $item, float|int $quantity): void
    {
        $supplierStock = $item->itemSuppliers()->orderBy('id')->lockForUpdate()->first();

        if ($supplierStock) {
            $supplierStock->current_stock = round((float) $supplierStock->current_stock + (float) $quantity, 4);
            $supplierStock->save();
        }
    }

    private function lockItem(int $itemId): Item
    {
        return Item::lockForUpdate()->findOrFail($itemId);
    }

    /**
     * Ubah stok barang sebesar $quantity (positif = masuk, negatif = keluar)
     * dan catat mutasinya.
     */
    private function move(
        Item $item,
        float|int $quantity,
        MovementType $type,
        string $referenceType,
        int $referenceId,
        float|string|null $price,
        string $notes,
    ): StockMovement {
        $stockBefore = (float) $item->current_stock;

        $item->current_stock = round($stockBefore + (float) $quantity, 4);
        $item->save();

        return StockMovement::create([
            'item_id' => $item->id,
            'type' => $type->value,
            'reference_id' => $referenceId,
            'reference_type' => $referenceType,
            'quantity' => $quantity,
            'stock_before' => $stockBefore,
            'stock_after' => $item->current_stock,
            'price' => $price ?? 0,
            'notes' => $notes,
            'created_by' => auth()->id() ?? 1,
        ]);
    }
}
