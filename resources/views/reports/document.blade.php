<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #1E1E1E; font-size: 11px; margin: 0; }
        .head { border-bottom: 3px solid #5D8F2F; padding-bottom: 10px; margin-bottom: 14px; }
        .shop { color: #5D8F2F; font-size: 16px; font-weight: bold; }
        h1 { font-size: 18px; margin: 6px 0 2px; }
        .subtitle { color: #6B7A5E; font-size: 11px; }
        .metrics { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 0 -8px 14px; }
        .metrics td { width: 25%; background: #F5FAF0; border: 1px solid #E3EBD9; border-radius: 8px; padding: 8px 10px; vertical-align: top; }
        .metric-label { color: #6B7A5E; font-size: 9px; text-transform: uppercase; letter-spacing: .04em; }
        .metric-value { font-size: 15px; font-weight: bold; margin-top: 2px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #2D4A1E; color: #FFFFFF; text-align: left; padding: 6px 8px; font-size: 10px; }
        table.data td { padding: 5px 8px; border-bottom: 1px solid #E3EBD9; }
        table.data tr:nth-child(even) td { background: #FAFCF7; }
        .right { text-align: right; }
        tr.total td { font-weight: bold; background: #EAF3DC !important; border-top: 2px solid #5D8F2F; }
        .muted { color: #6B7A5E; }
        .foot { margin-top: 16px; color: #8A9A7E; font-size: 9px; }
    </style>
</head>
<body>
    @php
        $fmt = function ($value, $format) {
            if ($value === null || $value === '') return '—';
            if ($format === 'money') return '₱' . number_format((float) $value, 2);
            if ($format === 'int') return number_format((int) $value);
            return (string) $value;
        };
        $metrics = $report['metrics'] ?? [];
        $columns = $report['columns'] ?? [];
        $rows = $report['rows'] ?? [];
        $totals = $report['totals'] ?? null;
    @endphp

    <div class="head">
        <div class="shop">{{ $shop }}</div>
        <h1>{{ $report['title'] }}</h1>
        <div class="subtitle">{{ $report['subtitle'] }} &middot; generated {{ now()->format('d M Y H:i') }}</div>
    </div>

    @if (count($metrics))
        <table class="metrics">
            <tr>
                @foreach ($metrics as $metric)
                    <td>
                        <div class="metric-label">{{ $metric['label'] }}</div>
                        <div class="metric-value">{{ $fmt($metric['value'], $metric['format']) }}</div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    <table class="data">
        <thead>
            <tr>
                @foreach ($columns as $col)
                    <th class="{{ ($col['align'] ?? 'left') === 'right' ? 'right' : '' }}">{{ $col['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($columns as $col)
                        <td class="{{ ($col['align'] ?? 'left') === 'right' ? 'right' : '' }}">
                            {{ $fmt($row[$col['key']] ?? null, $col['format'] ?? 'text') }}
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ max(count($columns), 1) }}" class="muted">No records for this period.</td>
                </tr>
            @endforelse
            @if ($totals)
                <tr class="total">
                    @foreach ($columns as $i => $col)
                        <td class="{{ ($col['align'] ?? 'left') === 'right' ? 'right' : '' }}">
                            @if ($i === 0)
                                TOTAL
                            @elseif (array_key_exists($col['key'], $totals))
                                {{ $fmt($totals[$col['key']], $col['format'] ?? 'text') }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>

    <div class="foot">{{ $shop }} &middot; internal report</div>
</body>
</html>
