<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $pageTitle }}</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font: 14px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            color: #1f2937;
            background: #f8fafc;
            padding: 2rem 1rem;
        }
        .wrap { max-width: 1100px; margin: 0 auto; }
        header { margin-bottom: 1.25rem; }
        h1 { font-size: 1.35rem; margin: 0 0 .25rem; }
        .meta { color: #6b7280; font-size: .82rem; }
        .meta a { color: #2563eb; text-decoration: none; }
        .meta a:hover { text-decoration: underline; }
        .filter-note {
            display: inline-block;
            margin-top: .5rem;
            padding: .3rem .6rem;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: .4rem;
            color: #1e40af;
            font-size: .8rem;
        }
        .subheader { margin-top: .35rem; font-weight: 600; color: #374151; }
        .card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: .6rem;
            overflow: hidden;
        }
        .table-scroll { overflow-x: auto; }
        table { border-collapse: collapse; width: 100%; font-size: .85rem; }
        thead th {
            text-align: left;
            padding: .6rem .8rem;
            background: #f3f4f6;
            border-bottom: 1px solid #e5e7eb;
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #6b7280;
            white-space: nowrap;
        }
        tbody td {
            padding: .55rem .8rem;
            border-bottom: 1px solid #f1f5f9;
            white-space: nowrap;
        }
        tbody tr:hover { background: #f9fafb; }
        td.null { color: #9ca3af; font-style: italic; }
        td a { color: #2563eb; font-weight: 600; text-decoration: none; }
        td a:hover { text-decoration: underline; }
        .empty, .error {
            padding: 2rem;
            text-align: center;
            color: #6b7280;
        }
        .error { color: #b91c1c; background: #fef2f2; }
        footer { margin-top: 1rem; color: #9ca3af; font-size: .75rem; }
        @media (prefers-color-scheme: dark) {
            body { color: #e5e7eb; background: #0b1120; }
            .card { background: #111827; border-color: #1f2937; }
            thead th { background: #1f2937; border-color: #374151; color: #9ca3af; }
            tbody td { border-color: #1f2937; }
            tbody tr:hover { background: #172033; }
            .filter-note { background: #172554; border-color: #1e3a8a; color: #bfdbfe; }
            .subheader { color: #d1d5db; }
            .error { background: #200a0a; color: #fca5a5; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <h1>{{ $pageTitle }}</h1>
        <div class="meta">
            @if ($isChild)
                <a href="{{ $rootUrl }}">&larr; Torna alla tabella principale</a>
            @else
                Vista condivisa &middot; sola lettura
            @endif
        </div>
        @if ($isChild && $masterField)
            <div class="subheader">{{ $masterField }}</div>
        @endif
        @if ($filterDescription)
            <div class="filter-note">{{ $filterDescription }}</div>
        @endif
    </header>

    <div class="card">
        @if ($error)
            <div class="error">{{ $error }}</div>
        @elseif (empty($rows))
            <div class="empty">La query non ha restituito risultati.</div>
        @else
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            @foreach ($columns as $column)
                                <th>{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                @foreach ($columns as $column)
                                    @php
                                        $value = $row[$column] ?? null;
                                        $child = in_array($column, $numericColumns, true) ? $drilldownFor($column) : null;
                                        $link = ($child && $value !== null)
                                            ? $childUrl($child->id, [
                                                'masterFilterColumn' => $column,
                                                'masterFilterValue' => $value,
                                                'MasterFilterField' => $firstColumn !== null ? ($row[$firstColumn] ?? null) : null,
                                            ])
                                            : null;
                                    @endphp
                                    @if (is_null($value))
                                        <td class="null">&mdash;</td>
                                    @elseif ($link)
                                        <td><a href="{{ $link }}">{{ $value }}</a></td>
                                    @else
                                        <td>{{ $value }}</td>
                                    @endif
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <footer>
        {{ count($rows) }} {{ count($rows) === 1 ? 'riga' : 'righe' }}
        @if (! $error && $share->dashboardWidget)
            &middot; aggiornato al {{ now()->format('d/m/Y H:i') }}
        @endif
    </footer>
</div>
</body>
</html>
