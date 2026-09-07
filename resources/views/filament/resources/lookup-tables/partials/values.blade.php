<div style="max-height:60vh;overflow:auto">
    @if (empty($values))
        <p style="font-size:.875rem;color:#6b7280;font-style:italic">Nessun valore.</p>
    @else
        <table style="width:100%;border-collapse:collapse;font-size:.85rem">
            <thead>
                <tr>
                    <th style="text-align:left;padding:.4rem .6rem;border-bottom:1px solid #e5e7eb;color:#6b7280;font-size:.72rem;text-transform:uppercase">{{ $keyColumn ?: 'valore' }}</th>
                    @if ($labelColumn)
                        <th style="text-align:left;padding:.4rem .6rem;border-bottom:1px solid #e5e7eb;color:#6b7280;font-size:.72rem;text-transform:uppercase">{{ $labelColumn }}</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($values as $row)
                    <tr>
                        <td style="padding:.35rem .6rem;border-bottom:1px solid #f1f5f9">
                            {{ ($row['value'] ?? '') === '' ? '∅' : $row['value'] }}
                        </td>
                        @if ($labelColumn)
                            <td style="padding:.35rem .6rem;border-bottom:1px solid #f1f5f9">
                                {{ ($row['label'] ?? '') === '' ? '∅' : $row['label'] }}
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p style="margin-top:.5rem;font-size:.75rem;color:#9ca3af">{{ count($values) }} valori</p>
    @endif
</div>
