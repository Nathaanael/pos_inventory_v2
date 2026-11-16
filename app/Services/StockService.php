<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\TransactionStatus;
use App\Models\Inbound;
use App\Models\InboundItem;
use App\Models\Item;
use App\Models\ItemSupplier;
use App\Models\Outbound;
use App\Models\OutboundItem;
use App\Models\ReturnTransaction;
use App\Models\StockMovement;
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
    public function createOutbound(array $header, array $lines): Outbound
    {
        return DB::transaction(function () use ($header, $lines) {
            $outbound = Outbound::create([
                ...$header,
                'status' => TransactionStatus::PENDING,
                'created_by' => auth()->id(),
                'total_amount' => collect($lines)->sum('subtotal'),
                'total_cogs' => collect($lines)->sum(fn ($l) => $l['cogs_price'] * $l['quantity']),
            ]);

            foreach ($lines as $line) {
                OutboundItem::create([
                    'outbound_id' => $outbound->id,
                    'item_id' => $line['item_id'],
                    'quantity' => $line['quantity'],
                    'sell_price' => $line['sell_price'],
                    'cogs_price' => $line['cogs_price'],
                    'subtotal' => $line['subtotal'],
                    'profit' => ($line['sell_price'] - $line['cogs_price']) * $line['quantity'],
                ]);
            }

            $this->completeOutbound($outbound->load('items'), 'Barang Keluar');

            return $outbound->refresh();
        });
    }

    /**
     * Status jadi "selesai" dan stok berkurang (FIFO per supplier).
     * Harus dipanggil di dalam DB::transaction.
     */
    private function completeOutbound(Outbound $outbound, string $notes): void
    {
        // Cek semua stok dulu, supaya transaksi tidak setengah jadi.
        foreach ($outbound->items as $line) {
            $item = $this->lockItem($line->item_id);

            if ($item->current_stock < $line->quantity) {
                throw ValidationException::withMessages([
                    'stock' => "Stok {$item->name} tidak cukup. Tersedia: {$item->current_stock}",
                ]);
            }
        }

        $outbound->update(['status' => TransactionStatus::COMPLETED]);

        foreach ($outbound->items as $line) {
            $item = $this->lockItem($line->item_id);

            $this->deductSupplierStockFifo($item, $line->quantity);

            $this->move($item, -$line->quantity, MovementType::OUTBOUND, 'outbounds', $outbound->id, $line->sell_price, $notes);
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

            $wasCompleted = $outbound->status === TransactionStatus::COMPLETED;

            $outbound->update([
                'status' => TransactionStatus::VOIDED,
                'voided_at' => now(),
                'voided_by' => auth()->id(),
                'void_reason' => $reason,
            ]);

            if (! $wasCompleted) {
                return;
            }

            foreach ($outbound->items as $line) {
                $item = $this->lockItem($line->item_id);

                $this->restoreSupplierStock($item, $line->quantity);

                $this->move($item, $line->quantity, MovementType::VOID, 'outbounds', $outbound->id, $line->sell_price, 'Batal Barang Keluar: ' . $reason);
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
     |  INTI: satu-satunya kode yang menulis current_stock & stock_movements
     * ===================================================================== */

    /**
     * Potong stok per supplier secara FIFO: supplier yang paling awal
     * terdaftar (id terkecil) dihabiskan dulu.
     */
    private function deductSupplierStockFifo(Item $item, int $quantity): void
    {
        $remaining = $quantity;

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
    private function restoreSupplierStock(Item $item, int $quantity): void
    {
        $supplierStock = $item->itemSuppliers()->orderBy('id')->lockForUpdate()->first();

        if ($supplierStock) {
            $supplierStock->current_stock += $quantity;
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
        int $quantity,
        MovementType $type,
        string $referenceType,
        int $referenceId,
        float|string|null $price,
        string $notes,
    ): StockMovement {
        $stockBefore = $item->current_stock;

        $item->current_stock += $quantity;
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
