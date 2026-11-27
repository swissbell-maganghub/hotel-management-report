{{-- [WASTAGE-REPORT] NEW FILE: Ekspor PDF Bulanan Food Waste Template Excel --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Food Waste Bulanan — {{ $periodLabel }}</title>
    <style>
        @page { margin: 18px 20px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #0F172A; margin: 0; }
        .header { border-bottom: 3px solid #0F2942; padding-bottom: 8px; margin-bottom: 10px; }
        .header-table { width: 100%; border-collapse: collapse; }
        .brand { font-size: 9px; text-transform: uppercase; letter-spacing: 0.8px; color: #1E6B7B; font-weight: bold; }
        h1 { font-size: 14px; color: #0F2942; margin: 2px 0 2px 0; }
        .meta { font-size: 8.5px; color: #475569; }
        table.kpi { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.kpi td { border: 1px solid #CBD5E1; padding: 5px 8px; width: 25%; background: #F8FAFC; }
        .kpi-label { font-size: 7.5px; text-transform: uppercase; color: #64748B; display: block; margin-bottom: 2px; }
        .kpi-val { font-size: 11px; font-weight: bold; color: #0F2942; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { border: 1px solid #CBD5E1; padding: 4px 4px; font-size: 8px; }
        table.data thead th { background: #FFFFFF; color: #0F2942; font-weight: bold; text-align: center; }
        table.data thead tr.banner th { background: #0F2942; color: #FFFFFF; font-size: 9.5px; padding: 6px 6px; letter-spacing: 0.5px; }
        table.data tfoot td { background: #F8FAFC; font-weight: bold; color: #0F2942; border-top: 2px solid #94A3B8; }
        .num { text-align: right; }
        .center { text-align: center; }
        .date-col { text-align: left; white-space: nowrap; font-weight: bold; color: #0F2942; }
        .bold { font-weight: bold; color: #0F2942; }
        .empty { text-align: center; padding: 20px; color: #64748B; font-size: 9px; }
    </style>
</head>
<body>
    @php
        $logoFile = public_path('images/swissbel-logo.png');
        $logoBase64 = file_exists($logoFile) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoFile)) : null;
        $fmtVal = fn ($v): string => fmod((float) $v, 1.0) == 0.0
            ? number_format((float) $v, 0, ',', '.')
            : number_format((float) $v, 1, ',', '.');
        $gramsPerGuest = $kgPerCover * 1000;
    @endphp

    <div class="header">
        <table class="header-table">
            <tr>
                @if ($logoBase64)
                    <td style="width: 48px; vertical-align: middle;">
                        <img src="{{ $logoBase64 }}" style="height: 36px; width: auto;" alt="Swiss-Belhotel">
                    </td>
                @endif
                <td style="vertical-align: middle;">
                    <div class="brand">Swiss-Belhotel Waterfront Makassar &bull; F&amp;B Wastage Report</div>
                    <h1>Laporan Food Waste Bulanan &mdash; {{ $periodLabel }} ({{ $period }})</h1>
                    <div class="meta">
                        Dicetak oleh: {{ $generatedBy->name }} &bull; {{ now()->timezone('Asia/Makassar')->format('d M Y H:i:s') }} WITA &bull; Total Baris: {{ $records->count() }} hari
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <table class="kpi">
        <tr>
            <td>
                <span class="kpi-label">Periode Bulan Masehi</span>
                <span class="kpi-val">{{ $periodLabel }} ({{ $period }})</span>
            </td>
            <td>
                <span class="kpi-label">Total Wastage</span>
                <span class="kpi-val">{{ number_format($totalKg, 2, ',', '.') }} KG ({{ $records->count() }} hari)</span>
            </td>
            <td>
                <span class="kpi-label">Total Cover Tamu</span>
                <span class="kpi-val">{{ number_format($totalCover, 0, ',', '.') }} Covers</span>
            </td>
            <td>
                <span class="kpi-label">Rasio KG / Cover</span>
                <span class="kpi-val">{{ number_format($kgPerCover, 3, ',', '.') }} KG/Cov ({{ number_format($gramsPerGuest, 1, ',', '.') }} g)</span>
            </td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr class="banner">
                <th colspan="15">FOOD WASTE SWISS-BELHOTEL WATERFRONT MAKASSAR &mdash; {{ strtoupper($periodLabel) }}</th>
            </tr>
            <tr>
                <th rowspan="2" style="width: 64px;">Date</th>
                <th colspan="3">Buffet Lunch, Dinner, Gravity</th>
                <th colspan="4">Preparation</th>
                <th rowspan="2">Plate<br>Waste</th>
                <th rowspan="2">Breakfast<br>Swiss</th>
                <th rowspan="2">TOTAL<br>KG/Day</th>
                <th rowspan="2">COVER</th>
                <th rowspan="2">KG YTD</th>
                <th rowspan="2">COVER<br>YTD</th>
                <th rowspan="2">KG/<br>COVER</th>
            </tr>
            <tr>
                <th>Lunch</th>
                <th>Dinner</th>
                <th>Gravity</th>
                <th>Butcher</th>
                <th>Main<br>Kitchen</th>
                <th>Pastry</th>
                <th>Cold<br>Kitchen</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($records as $rec)
                <tr>
                    <td class="date-col">{{ $rec->record_date?->format('M j, Y') ?? '-' }}</td>
                    <td class="num">{{ $fmtVal($rec->buffet_lunch) }}</td>
                    <td class="num">{{ $fmtVal($rec->buffet_dinner) }}</td>
                    <td class="num">{{ $fmtVal($rec->buffet_gravity) }}</td>
                    <td class="num">{{ $fmtVal($rec->prep_butcher) }}</td>
                    <td class="num">{{ $fmtVal($rec->prep_main_kitchen) }}</td>
                    <td class="num">{{ $fmtVal($rec->prep_pastry) }}</td>
                    <td class="num">{{ $fmtVal($rec->prep_cold_kitchen) }}</td>
                    <td class="num">{{ $fmtVal($rec->plate_waste) }}</td>
                    <td class="num">{{ $fmtVal($rec->breakfast_cafe) }}</td>
                    <td class="num bold">{{ $fmtVal($rec->total_kg) }}</td>
                    <td class="num">{{ number_format((int) $rec->covers, 0, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $rec->ytd_kg, 1, ',', '.') }}</td>
                    <td class="num">{{ number_format((int) $rec->ytd_cover, 0, ',', '.') }}</td>
                    <td class="num bold">{{ number_format((float) $rec->kg_per_cover, 3, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="15" class="empty">Belum ada data catatan food waste untuk periode {{ $periodLabel }}.</td>
                </tr>
            @endforelse
        </tbody>
        @if ($records->isNotEmpty())
            <tfoot>
                <tr>
                    <td class="date-col">TOTAL / SUMMARY</td>
                    <td class="num">{{ $fmtVal($records->sum('buffet_lunch')) }}</td>
                    <td class="num">{{ $fmtVal($records->sum('buffet_dinner')) }}</td>
                    <td class="num">{{ $fmtVal($records->sum('buffet_gravity')) }}</td>
                    <td class="num">{{ $fmtVal($records->sum('prep_butcher')) }}</td>
                    <td class="num">{{ $fmtVal($records->sum('prep_main_kitchen')) }}</td>
                    <td class="num">{{ $fmtVal($records->sum('prep_pastry')) }}</td>
                    <td class="num">{{ $fmtVal($records->sum('prep_cold_kitchen')) }}</td>
                    <td class="num">{{ $fmtVal($records->sum('plate_waste')) }}</td>
                    <td class="num">{{ $fmtVal($records->sum('breakfast_cafe')) }}</td>
                    <td class="num">{{ $fmtVal($totalKg) }}</td>
                    <td class="num">{{ number_format($totalCover, 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($totalKg, 1, ',', '.') }}</td>
                    <td class="num">{{ number_format($totalCover, 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($kgPerCover, 3, ',', '.') }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
