<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>POMEC {{ strtoupper($utilityType) }} Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0F172A; }
        .header { border-bottom: 3px solid #0F2942; padding-bottom: 10px; margin-bottom: 14px; }
        .header-table { width: 100%; border-collapse: collapse; }
        .brand { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #1E6B7B; font-weight: bold; }
        h1 { font-size: 16px; color: #0F2942; margin: 4px 0 2px 0; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.data th { background: #0F2942; color: #FFFFFF; padding: 6px 8px; text-align: left; font-size: 10px; }
        table.data td { border: 1px solid #CBD5E1; padding: 6px 8px; font-size: 10px; }
        table.data tr:nth-child(even) td { background: #F8FAFC; }
        .num { text-align: right; }
    </style>
</head>
<body>
    @php
        $type = \App\Models\PomecRecord::TYPES[$utilityType];
        $logoFile = public_path('images/swissbel-logo.png');
        $logoBase64 = file_exists($logoFile) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoFile)) : null;
    @endphp
    <div class="header">
        <table class="header-table">
            <tr>
                @if ($logoBase64)
                    <td style="width: 56px; vertical-align: middle;">
                        <img src="{{ $logoBase64 }}" style="height: 44px; width: 44px;" alt="Swiss-Belhotel">
                    </td>
                @endif
                <td style="vertical-align: middle;">
                    <div class="brand">Swiss-Belhotel Waterfront Makassar • POMEC Control</div>
                    <h1>Laporan POMEC Control — {{ $type['id'] }} ({{ $utilityType === 'electricity' ? 'LWBP & WBP, kWh' : $type['unit'] }})</h1>
                    <div style="font-size: 10px; color: #475569;">Dicetak oleh: {{ $generatedBy->name }} • {{ now()->timezone('Asia/Makassar')->format('d M Y H:i:s') }} WITA</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="data">
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Meter / Panel</th>
                @if ($utilityType === 'electricity')
                    <th class="num">LWBP (kWh)</th>
                    <th class="num">WBP (kWh)</th>
                    <th class="num">Total (kWh)</th>
                @else
                    <th class="num">Pemakaian ({{ $type['unit'] }})</th>
                @endif
                @if ($type['cost'])
                    <th class="num">Biaya sebelum pajak (Rp)</th>
                @endif
                @if ($utilityType === 'gas')
                    <th class="num">Gas Masuk</th>
                    <th class="num">Stok Penuh</th>
                    <th class="num">Stok Kosong</th>
                    <th class="num">Terpasang</th>
                @endif
                <th>Batch Import</th>
                <th>Catatan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($records as $rec)
                <tr>
                    <td>{{ $rec->reading_date->format('d M Y') }}</td>
                    <td>{{ $rec->meter_name }}</td>
                    @if ($utilityType === 'electricity')
                        <td class="num">{{ number_format((float) $rec->lwbp_value, 2, ',', '.') }}</td>
                        <td class="num">{{ number_format((float) $rec->wbp_value, 2, ',', '.') }}</td>
                        <td class="num">{{ number_format((float) $rec->total_usage, 2, ',', '.') }}</td>
                    @else
                        <td class="num">{{ number_format((float) $rec->total_usage, 2, ',', '.') }}</td>
                    @endif
                    @if ($type['cost'])
                        <td class="num">{{ $rec->cost === null ? '-' : number_format((float) $rec->cost, 0, ',', '.') }}</td>
                    @endif
                    @if ($utilityType === 'gas')
                        @foreach (\App\Models\PomecRecord::GAS_STOCK as $key)
                            <td class="num">{{ $rec->{$key} ?? '-' }}</td>
                        @endforeach
                    @endif
                    <td>{{ $rec->import?->batch_code }}</td>
                    <td>{{ $rec->notes }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
