<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1a1a1a; font-size: 12px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .muted { color: #666; }
        .orange { color: #e4570e; }
        .box { border: 1px solid #e5e5e5; border-radius: 6px; padding: 10px 12px; margin-top: 12px; }
        .cards { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin-top: 12px; }
        .card { border: 1px solid #e5e5e5; border-radius: 6px; padding: 10px; vertical-align: top; width: 33%; }
        .card strong { display: block; font-size: 11px; color: #666; margin-bottom: 4px; }
        .card .value { font-size: 16px; font-weight: bold; }
        table.detail { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.detail th, table.detail td { text-align: left; padding: 6px 4px; border-bottom: 1px solid #eee; }
        table.detail th { color: #666; font-weight: normal; font-size: 11px; }
        .right { text-align: right; }
        .conformidad { margin-top: 18px; border: 1px dashed #f0c7a8; background: #fff4ec; border-radius: 6px; padding: 10px 12px; }
    </style>
</head>
<body>
    <p class="muted" style="margin: 0;">Pro Business</p>
    <h1>Balance de pago a proveedor</h1>
    <p class="muted">{{ $fecha }}</p>

    <div class="box">
        <strong>Datos del cliente</strong>
        <p style="margin: 6px 0 0;">
            {{ $cliente ?: '—' }}<br>
            Consolidado: {{ $consolidado ?: '—' }}
        </p>
    </div>

    <table class="cards">
        <tr>
            <td class="card">
                <strong>{{ $diferencia_label }}</strong>
                <span class="value orange">${{ number_format($diferencia_usd, 2, '.', ',') }}</span>
            </td>
            <td class="card">
                <strong>Monto cobrado (USD)</strong>
                <span class="value">${{ number_format($cobrado_usd, 2, '.', ',') }}</span>
            </td>
            <td class="card">
                <strong>Pagado al proveedor (RMB)</strong>
                <span class="value">¥ {{ number_format($pagado_rmb, 2, '.', ',') }}</span>
            </td>
        </tr>
    </table>

    <div class="box">
        <strong>Proveedor</strong>
        <p style="margin: 6px 0 0;">
            Código: {{ $codigo_proveedor }}<br>
            @if($nombre_proveedor) {{ $nombre_proveedor }}<br>@endif
            Producto: {{ $producto ?: '—' }}
        </p>
    </div>

    <div class="box">
        <strong>Detalle del cambio</strong>
        <table class="detail">
            <thead>
                <tr>
                    <th>Pago</th>
                    <th class="right">T.C. real</th>
                    <th class="right">USD</th>
                    <th class="right">Obtenido ¥</th>
                    <th class="right">Pagado ¥</th>
                    <th class="right">Diferencia USD</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pagos as $pago)
                    <tr>
                        <td>Pago {{ $pago['n'] }}</td>
                        <td class="right">{{ number_format($pago['tc'], 4, '.', ',') }}</td>
                        <td class="right">${{ number_format($pago['usd'], 2, '.', ',') }}</td>
                        <td class="right">¥ {{ number_format($pago['obtenido'], 2, '.', ',') }}</td>
                        <td class="right">¥ {{ number_format($pago['pagado'], 2, '.', ',') }}</td>
                        <td class="right">${{ number_format($pago['diff_usd'], 2, '.', ',') }}</td>
                    </tr>
                @endforeach
                @if(count($pagos) > 1)
                    <tr>
                        <td><strong>Total</strong></td>
                        <td class="right">—</td>
                        <td class="right"><strong>${{ number_format($cobrado_usd, 2, '.', ',') }}</strong></td>
                        <td class="right"><strong>¥ {{ number_format($obtenido_rmb, 2, '.', ',') }}</strong></td>
                        <td class="right"><strong>¥ {{ number_format($pagado_rmb, 2, '.', ',') }}</strong></td>
                        <td class="right"><strong>${{ number_format($diferencia_usd, 2, '.', ',') }}</strong></td>
                    </tr>
                @endif
            </tbody>
        </table>
        <p class="muted" style="margin: 10px 0 0;"><strong>Cómo se calcula:</strong> yuanes obtenidos = dólares × tipo de cambio real. La diferencia en dólares es (obtenidos − pagados) ÷ tipo de cambio real.</p>
    </div>

    <div class="conformidad">
        Por favor confirma la conformidad de este balance con tu ejecutivo.
    </div>

    @if($mostrar_cuentas)
        <div class="box">
            <strong>Cuentas para el depósito de la diferencia</strong>
            <p class="muted" style="margin: 6px 0 0;">Usa las cuentas oficiales de Pro Business. El detalle numérico va en la imagen que te enviamos por WhatsApp.</p>
        </div>
    @endif
</body>
</html>
