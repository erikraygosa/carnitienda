<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inventario — {{ $warehouse->nombre }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 12px; padding: 24px; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        .meta { color: #666; font-size: 11px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #f3f4f6;
            text-align: left;
            padding: 7px 10px;
            font-size: 11px;
            text-transform: uppercase;
            border-bottom: 2px solid #d1d5db;
        }
        tbody td { padding: 6px 10px; border-bottom: 1px solid #e5e7eb; }
        tbody tr:nth-child(even) td { background: #f9fafb; }
        .conteo { min-width: 120px; }
        .badge {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-family: monospace;
        }
        .ok   { background: #d1fae5; color: #065f46; }
        .warn { background: #fef3c7; color: #92400e; }
        .low  { background: #fee2e2; color: #991b1b; }
        .firma {
            margin-top: 48px;
            font-size: 11px;
            color: #555;
            border-top: 1px solid #e5e7eb;
            padding-top: 16px;
        }
        @media print {
            body { padding: 12px; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="margin-bottom:16px;">
        <button onclick="window.print()"
                style="padding:6px 16px;background:#4f46e5;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px;">
            🖨 Imprimir
        </button>
        <button onclick="window.close()"
                style="margin-left:8px;padding:6px 16px;background:#fff;border:1px solid #d1d5db;border-radius:6px;cursor:pointer;font-size:13px;">
            Cerrar
        </button>
    </div>

    <h1>Inventario físico — {{ $warehouse->nombre }}</h1>
    <p class="meta">Fecha: {{ now()->format('d/m/Y') }} &nbsp;·&nbsp; Total productos: {{ $stock->count() }}</p>

    <table>
        <thead>
            <tr>
                <th>SKU</th>
                <th>Producto</th>
                <th>Unidad</th>
                <th>Existencia sistema</th>
                <th>Stock mín.</th>
                <th class="conteo">Conteo físico</th>
            </tr>
        </thead>
        <tbody>
            @foreach($stock as $row)
            @php
                $qty      = (float) $row->existencia;
                $stockMin = (float) ($row->stock_min ?? 0);
                $badgeClass = $qty <= 0
                    ? 'low'
                    : ($stockMin > 0 && $qty <= $stockMin ? 'warn' : 'ok');
            @endphp
            <tr>
                <td style="font-family:monospace;font-size:11px;color:#6b7280;">{{ $row->sku ?: '—' }}</td>
                <td><strong>{{ $row->nombre }}</strong></td>
                <td>{{ $row->unidad }}</td>
                <td>
                    <span class="badge {{ $badgeClass }}">
                        {{ number_format($qty, 3) }}
                    </span>
                </td>
                <td style="font-family:monospace;color:#6b7280;">
                    {{ $stockMin > 0 ? number_format($stockMin, 3) : '—' }}
                </td>
                <td class="conteo" style="border-bottom: 1px solid #9ca3af;"></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="firma">
        Responsable: _____________________________
        &nbsp;&nbsp;&nbsp;
        Fecha conteo: _____________
    </div>

</body>
</html>