<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Nota #{{ $outbound->transaction_code }}</title>
    <style>
        /* Reset */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: monospace;
            font-size: 12px;
            line-height: 1.4;
            color: #000 !important;
            background: #fff;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        /* Container for screen preview */
        .receipt {
            width: 270px;
            margin: 0 auto;
            padding: 8px;
            color: #000 !important;
        }

        /* Text alignment helpers */
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }

        /* Dashed line separator using characters */
        .dash {
            overflow: hidden;
            font-size: 12px;
            line-height: 1;
            margin: 4px 0;
            color: #000 !important;
        }

        /* Double line separator */
        .double-dash {
            overflow: hidden;
            font-size: 12px;
            line-height: 1;
            margin: 4px 0;
            color: #000 !important;
        }

        /* Info table */
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            vertical-align: top;
            padding: 1px 0;
            font-size: 11px;
            color: #000 !important;
        }
        .info-table .lbl {
            width: 70px;
            font-weight: bold;
        }
        .info-table .sep {
            width: 8px;
        }

        /* Items */
        .item-name {
            font-weight: bold;
            font-size: 11px;
            padding: 2px 0 0 0;
            color: #000 !important;
        }
        .item-calc {
            font-size: 11px;
            padding: 0 0 2px 8px;
            color: #000 !important;
        }
        .item-calc-table {
            width: 100%;
            border-collapse: collapse;
        }
        .item-calc-table td {
            padding: 0;
            font-size: 11px;
            color: #000 !important;
        }

        /* Total table */
        .total-table {
            width: 100%;
            border-collapse: collapse;
        }
        .total-table td {
            padding: 1px 0;
            font-size: 12px;
            font-weight: bold;
            color: #000 !important;
        }
        .total-table .grand td {
            font-size: 14px;
            padding-top: 2px;
        }

        /* Footer */
        .footer {
            text-align: center;
            font-size: 10px;
            margin-top: 4px;
            color: #000 !important;
        }
        .footer .thanks {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 2px;
        }

        /* Buttons - screen only */
        .no-print {
            text-align: center;
            padding: 12px;
        }
        .no-print button {
            padding: 10px 24px;
            margin: 4px;
            font-size: 14px;
            cursor: pointer;
            border: 1px solid #333;
            border-radius: 6px;
            font-family: monospace;
        }
        .no-print .bp { background: #222; color: #fff; }
        .no-print .bc { background: #eee; color: #333; }

        /* ===== PRINT ===== */
        @media print {
            .no-print { display: none !important; }

            body {
                margin: 0 !important;
                padding: 0 !important;
                color: #000 !important;
            }

            .receipt {
                width: 100% !important;
                max-width: none !important;
                margin: 0 !important;
                padding: 2px !important;
            }
        }

        /* ===== SCREEN ===== */
        @media screen {
            body { background: #e8e8e8; padding-top: 10px; }
            .receipt {
                background: #fff;
                box-shadow: 0 2px 12px rgba(0,0,0,0.25);
                border-radius: 2px;
            }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button class="bp" onclick="window.print()">🖨️ Cetak Nota</button>
    <button class="bc" onclick="window.close()">✕ Tutup</button>
</div>

<div class="receipt">
    {{-- HEADER --}}
    @php($store = \App\Models\StoreSetting::current())
    <div class="center bold" style="font-size:14px; color:#000 !important;">{{ strtoupper($store->store_name) }}</div>
    @if($store->tagline)<div class="center">{{ $store->tagline }}</div>@endif
    @if($store->address)<div class="center">{{ $store->address }}</div>@endif
    @if($store->phone)<div class="center">Telp: {{ $store->phone }}</div>@endif
    @if($store->tax_number)<div class="center">NPWP: {{ $store->tax_number }}</div>@endif
    <div class="dash">--------------------------------</div>

    {{-- TRANSACTION INFO --}}
    <table class="info-table">
        <tr><td class="lbl">No</td><td class="sep">:</td><td>{{ $outbound->transaction_code }}</td></tr>
        <tr><td class="lbl">Tgl</td><td class="sep">:</td><td>{{ $outbound->date->format('d/m/Y') }}</td></tr>
        @if($outbound->customer_name)
        <tr><td class="lbl">Customer</td><td class="sep">:</td><td>{{ $outbound->customer_name }}</td></tr>
        @endif
        @if($outbound->orderLabel())
        <tr><td class="lbl">Pesanan</td><td class="sep">:</td><td><strong>{{ $outbound->orderLabel() }}</strong></td></tr>
        @endif
        @if($outbound->destination)
        <tr><td class="lbl">Tujuan</td><td class="sep">:</td><td>{{ $outbound->destination }}</td></tr>
        @endif
        <tr><td class="lbl">Harga</td><td class="sep">:</td><td>{{ ucfirst($outbound->price_type_applied) }}</td></tr>
        <tr><td class="lbl">Kasir</td><td class="sep">:</td><td>{{ $outbound->createdBy?->name ?? '-' }}</td></tr>
    </table>

    <div class="dash">--------------------------------</div>

    {{-- ITEMS --}}
    @foreach($outbound->items as $outboundItem)
    <div class="item-name">{{ $outboundItem->item?->name ?? 'Item Dihapus' }}</div>
    <div class="item-calc">
        <table class="item-calc-table">
            <tr>
                <td>{{ number_format($outboundItem->quantity) }}{{ $outboundItem->item?->unit?->name ? ' '.$outboundItem->item->unit->name : '' }} x Rp{{ number_format($outboundItem->sell_price, 0, ',', '.') }}</td>
                <td class="right bold">Rp{{ number_format($outboundItem->subtotal, 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>
    @endforeach

    <div class="double-dash">================================</div>

    {{-- TOTAL --}}
    <table class="total-table">
        <tr>
            <td>Item</td>
            <td class="right">{{ $outbound->items->count() }} jenis</td>
        </tr>
        <tr>
            <td>Qty</td>
            <td class="right">{{ number_format($outbound->items->sum('quantity')) }}</td>
        </tr>
        <tr class="grand">
            <td>TOTAL</td>
            <td class="right">Rp{{ number_format($outbound->total_amount, 0, ',', '.') }}</td>
        </tr>
        @if($outbound->payment_method)
        <tr>
            <td>Bayar</td>
            <td class="right">{{ \App\Filament\Kasir\Pages\Pos::PAYMENT_METHODS[$outbound->payment_method] ?? ucfirst($outbound->payment_method) }}</td>
        </tr>
        @endif
        @if($outbound->payment_method === 'tunai' && $outbound->paid_amount)
        <tr>
            <td>Tunai</td>
            <td class="right">Rp{{ number_format($outbound->paid_amount, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Kembali</td>
            <td class="right">Rp{{ number_format($outbound->paid_amount - $outbound->total_amount, 0, ',', '.') }}</td>
        </tr>
        @endif
    </table>

    <div class="dash">--------------------------------</div>

    {{-- FOOTER --}}
    <div class="footer">
        @if($store->receipt_footer)<div class="thanks">{{ $store->receipt_footer }}</div>@endif
        <div>{{ now()->format('d/m/Y H:i') }}</div>
    </div>

    <br>

</div>

</body>
</html>

