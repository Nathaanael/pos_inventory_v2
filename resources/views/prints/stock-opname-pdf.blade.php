<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Stock Opname #{{ $stockOpname->code }}</title>
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
        
        .diff-plus { color: green; font-weight: bold; }
        .diff-minus { color: red; font-weight: bold; }

        .summary-box {
            float: right;
            width: 300px;
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 30px;
            background-color: #fcfcfc;
        }

        .summary-box table {
            width: 100%;
        }

        .summary-box td {
            padding: 4px 0;
        }

        .summary-box .summary-label {
            font-weight: bold;
        }

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
        <h1>LAPORAN HASIL STOCK OPNAME</h1>
        <p>Dicetak pada: {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <table class="info-table">
        <tr>
            <td class="label">Kode SO</td>
            <td class="colon">:</td>
            <td><strong>{{ $stockOpname->code }}</strong></td>
            
            <td class="label">Tanggal</td>
            <td class="colon">:</td>
            <td>{{ $stockOpname->date->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">Nama Sesi</td>
            <td class="colon">:</td>
            <td>{{ $stockOpname->session_name }}</td>
            
            <td class="label">Status</td>
            <td class="colon">:</td>
            <td>{{ strtoupper($stockOpname->status->value ?? 'DRAFT') }}</td>
        </tr>
        <tr>
            <td class="label">Petugas</td>
            <td class="colon">:</td>
            <td>{{ $stockOpname->createdBy?->name ?? '-' }}</td>
            
            <td class="label">Disetujui Oleh</td>
            <td class="colon">:</td>
            <td>{{ $stockOpname->approvedBy?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Keterangan</td>
            <td class="colon">:</td>
            <td colspan="4">{{ $stockOpname->notes ?: '-' }}</td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th width="5%" class="text-center">No</th>
                <th width="35%">Nama Barang</th>
                <th width="15%" class="text-center">Stok Sistem</th>
                <th width="15%" class="text-center">Stok Fisik</th>
                <th width="15%" class="text-center">Selisih</th>
                <th width="15%">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($stockOpname->items as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $item->item?->name ?? 'Item Dihapus' }}</td>
                <td class="text-center">{{ number_format($item->system_stock) }} {{ $item->item?->unit?->name }}</td>
                <td class="text-center">{{ number_format($item->physical_stock) }} {{ $item->item?->unit?->name }}</td>
                <td class="text-center">
                    @if($item->difference > 0)
                        <span class="diff-plus">+{{ number_format($item->difference) }}</span>
                    @elseif($item->difference < 0)
                        <span class="diff-minus">{{ number_format($item->difference) }}</span>
                    @else
                        -
                    @endif
                </td>
                <td>{{ $item->notes }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary-box">
        <table>
            <tr>
                <td class="summary-label">Total Item Diperiksa</td>
                <td>:</td>
                <td class="text-right">{{ number_format($stockOpname->total_items_scanned) }}</td>
            </tr>
            <tr>
                <td class="summary-label">Total Sesuai</td>
                <td>:</td>
                <td class="text-right">{{ number_format($stockOpname->total_match) }}</td>
            </tr>
            <tr>
                <td class="summary-label">Total Ada Selisih</td>
                <td>:</td>
                <td class="text-right">{{ number_format($stockOpname->total_difference) }}</td>
            </tr>
        </table>
    </div>

    <div class="signatures">
        <table style="width: 100%;">
            <tr>
                <td style="width: 60%;"></td>
                <td style="width: 40%; text-align: center;">
                    Mengetahui,<br>
                    Manager / Penanggung Jawab
                    <div class="sign-area"></div>
                    <span class="sign-name">{{ $stockOpname->approvedBy?->name ?? '(____________________)' }}</span>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
