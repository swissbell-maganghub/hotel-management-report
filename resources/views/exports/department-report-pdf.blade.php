<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $report->report_code }} - {{ $report->title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0F172A; }
        .header { border-bottom: 3px solid #0F2942; padding-bottom: 10px; margin-bottom: 14px; }
        .header-table { width: 100%; border-collapse: collapse; }
        .brand { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #1E6B7B; font-weight: bold; }
        h1 { font-size: 16px; color: #0F2942; margin: 4px 0 0 0; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .meta td { padding: 4px 6px; border: 1px solid #CBD5E1; font-size: 10px; }
        .meta td.label { background: #F1F5F9; font-weight: bold; width: 18%; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.data th { background: #0F2942; color: #FFFFFF; padding: 6px 8px; text-align: left; font-size: 10px; }
        table.data td { border: 1px solid #CBD5E1; padding: 6px 8px; font-size: 10px; }
        table.data tr:nth-child(even) td { background: #F8FAFC; }
        .num { text-align: right; }
        .box { border: 1px solid #CBD5E1; background: #F8FAFC; padding: 8px 10px; margin-bottom: 12px; }
        .box-title { font-weight: bold; color: #0F2942; margin-bottom: 4px; }
    </style>
</head>
<body>
    @php
        $logoFile = public_path('images/swissbel-logo.png');
        $logoBase64 = file_exists($logoFile) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoFile)) : null;
    @endphp
    <div class="header">
        <table class="header-table">
            <tr>
                @if ($logoBase64)
                    <td style="width: 58px; vertical-align: middle;">
                        <img src="{{ $logoBase64 }}" style="height: 46px; width: 46px;" alt="Swiss-Belhotel">
                    </td>
                @endif
                <td style="vertical-align: middle;">
                    <div class="brand">Swiss-Belhotel Waterfront Makassar • Hotel Management Report System</div>
                    <h1>{{ $report->title }}</h1>
                </td>
            </tr>
        </table>
    </div>

    <table class="meta">
        <tr>
            <td class="label">Kode Laporan</td>
            <td>{{ $report->report_code }}</td>
            <td class="label">Departemen</td>
            <td>{{ $report->department->name }} ({{ $report->department->code }})</td>
        </tr>
        <tr>
            <td class="label">Template</td>
            <td>{{ $report->template->name }}</td>
            <td class="label">Periode</td>
            <td>{{ strtoupper($report->period_type) }} — {{ $report->period_date->format('d M Y') }} <span style="color:#64748b;">(Dicetak: {{ now()->timezone('Asia/Makassar')->format('d M Y H:i:s') }} WITA)</span></td>
        </tr>
        <tr>
            <td class="label">Status</td>
            <td>{{ strtoupper($report->status) }}</td>
            <td class="label">Disusun / Direview</td>
            <td>{{ $report->creator->name }} / {{ $report->reviewer?->name ?? '-' }}</td>
        </tr>
    </table>

    @php $columns = $report->template->columns_schema ?? []; @endphp
    <table class="data">
        <thead>
            <tr>
                <th style="width: 32px;">No</th>
                @foreach ($columns as $col)
                    <th class="{{ in_array($col['type'] ?? 'text', ['number', 'currency']) ? 'num' : '' }}">
                        {{ $col['label'] ?? $col['key'] }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($report->rows_data ?? [] as $idx => $row)
                <tr>
                    <td>{{ $idx + 1 }}</td>
                    @foreach ($columns as $col)
                        @php
                            $val = $row[$col['key']] ?? '';
                            $type = $col['type'] ?? 'text';
                        @endphp
                        <td class="{{ in_array($type, ['number', 'currency']) ? 'num' : '' }}">
                            @if ($type === 'currency' && is_numeric($val))
                                Rp {{ number_format((float) $val, 0, ',', '.') }}
                            @elseif ($type === 'number' && is_numeric($val))
                                {{ number_format((float) $val, 0, ',', '.') }}
                            @else
                                {{ $val }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($report->summary_notes)
        <div class="box">
            <div class="box-title">Ringkasan / Catatan Operasional</div>
            <div>{{ $report->summary_notes }}</div>
        </div>
    @endif

    @if ($report->review_note)
        <div class="box">
            <div class="box-title">Catatan Pemeriksaan HOD ({{ $report->reviewer?->name }})</div>
            <div>{{ $report->review_note }}</div>
        </div>
    @endif
    <div style="margin-top: 18px; padding-top: 8px; border-top: 1px solid #cbd5e1; font-size: 9px; color: #64748b; text-align: right;">
        Swiss-Belhotel Makassar • Dicetak pada {{ now()->timezone('Asia/Makassar')->format('d M Y H:i:s') }} WITA (Asia/Makassar)
    </div>
</body>
</html>
