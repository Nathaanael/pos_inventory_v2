<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Retur Transaksi #{{ $returnTransaction->return_code }}</title>
    <style>
        /* Reset */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            background: #fff;
            padding: 30px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }

        .header h1 {
            font-size: 20px;
            margin-bottom: 5px;
        }

        .header p {
            font-size: 12px;
            color: #666;
        }

        .info-table {
            width: 100%;
            margin-bottom: 20px;
        }

        .info-table td {
            padding: 4px 0;
            vertical-align: top;
        }

        .info-table .label {
            width: 120px;
            font-weight: bold;
        }

        .info-table .colon {
            width: 15px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .data-table th, .data-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .data-table th {
            background-color: #f5f5f5;
            font-weight: bold;
            font-size: 11px;
        }

        .text-center { text-align: center !important; }
        .text-right { text-align: right !important; }
        
        .signatures {
            margin-top: 50px;
            width: 100%;
            clear: both;
        }

        .signatures table {
            width: 100%;
            text-align: center;
        }

        .signatures td {
            width: 33%;
        }

        .sign-area {
            height: 80px;
        }

        .sign-name {
            font-weight: bold;
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>LAPORAN RETUR TRANSAKSI</h1>
        <p>Dicetak pada: {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <table class="info-table">
        <tr>
            <td class="label">Kode Retur</td>
            <td class="colon">:</td>
            <td><strong>{{ $returnTransaction->return_code }}</strong></td>
            
            <td class="label">Tanggal</td>
            <td class="colon">:</td>
            <td>{{ $returnTransaction->date->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">Tipe Retur</td>
            <td class="colon">:</td>
            <td><strong>{{ $returnTransaction->type === 'supplier' ? 'Retur ke Supplier' : 'Retur dari Customer' }}</strong></td>
            
            <td class="label">Status</td>
            <td class="colon">:</td>
            <td>{{ strtoupper($returnTransaction->status->value ?? 'DRAFT') }}</td>
        </tr>
        <tr>
            <td class="label">{{ $returnTransaction->type === 'supplier' ? 'Pemasok' : 'Pelanggan' }}</td>
            <td class="colon">:</td>
            <td>
                @if($returnTransaction->type === 'supplier')
                    {{ $returnTransaction->supplier?->name ?? '-' }}
                @else
                    {{ $returnTransaction->customer_name ?? '-' }}
                @endif
            </td>
            
            <td class="label">Petugas</td>
            <td class="colon">:</td>
            <td>{{ $returnTransaction->createdBy?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Alasan Retur</td>
            <td class="colon">:</td>
            <td colspan="4">{{ $returnTransaction->reason ?: '-' }}</td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th width="5%" class="text-center">No</th>
                <th width="35%">Nama Barang</th>
                <th width="15%" class="text-center">Kuantitas</th>
                <th width="15%" class="text-center">Harga</th>
                <th width="15%" class="text-center">Subtotal</th>
                <th width="15%">Kondisi</th>
            </tr>
        </thead>
        <tbody>
            @foreach($returnTransaction->items as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $item->item?->name ?? 'Item Dihapus' }}</td>
                <td class="text-center">{{ number_format($item->quantity) }} {{ $item->item?->unit?->name }}</td>
                <td class="text-right">Rp {{ number_format($item->price, 0, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                <td>{{ ucfirst($item->condition) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="text-right" style="font-weight: bold; border: 1px solid #ddd; padding: 8px;">Total Retur</td>
                <td class="text-right" style="font-weight: bold; border: 1px solid #ddd; padding: 8px;">Rp {{ number_format($returnTransaction->items->sum('subtotal'), 0, ',', '.') }}</td>
                <td style="border: 1px solid #ddd; padding: 8px;"></td>
            </tr>
        </tfoot>
    </table>

    <div class="signatures">
        <table>
            <tr>
                <td>
                    <p>Dibuat Oleh,</p>
                    <div class="sign-area"></div>
                    <p class="sign-name">{{ $returnTransaction->createdBy?->name ?? '.......................' }}</p>
                </td>
                <td></td>
                <td>
                    <p>Penerima,</p>
                    <div class="sign-area"></div>
                    <p class="sign-name">.......................</p>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
