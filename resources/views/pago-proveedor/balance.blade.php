<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1a1a1a; font-size: 11px; margin: 24px; }
        h1 { font-size: 18px; margin: 0; }
        .brand { font-weight: bold; font-size: 14px; color: #e4570e; margin: 0 0 2px; }
        .muted { color: #666; }
        .box { border: 1px solid #e5e5e5; border-radius: 8px; padding: 10px 12px; margin-top: 12px; }
        .kicker { font-size: 10px; letter-spacing: 0.04em; color: #888; font-weight: bold; }
        .cards { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 12px 0 0; }
        .card { border: 1px solid #ececec; border-radius: 8px; padding: 10px; vertical-align: top; width: 33%; background: #fafafa; }
        .card.hot { background: #fff4ec; border-color: #f0c7a8; }
        .card strong { display: block; font-size: 10px; color: #666; margin-bottom: 4px; }
        .value { font-size: 16px; font-weight: bold; }
        .orange { color: #e4570e; }
        table.detail { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.detail th, table.detail td { text-align: left; padding: 6px 4px; border-bottom: 1px solid #eee; }
        table.detail th { color: #666; font-weight: normal; font-size: 10px; }
        .right { text-align: right; }
        .conformidad { margin-top: 14px; border: 1px dashed #f0c7a8; background: #fff4ec; border-radius: 8px; padding: 10px 12px; }
        .cuenta { border: 1px solid #e5e5e5; border-radius: 8px; padding: 8px 10px; margin-top: 8px; }
    </style>
</head>
<body>
    <p class="brand">probusiness</p>
    <h1>Balance de pago a proveedor</h1>
    <p class="muted" style="margin: 2px 0 0;">Emitido: {{ $fecha }}</p>

    <table class="cards">
        <tr>
            <td class="card hot">
                <strong>{{ $diferencia_label }}</strong>
                <span class="value orange">${{ number_format($diferencia_usd, 2, '.', ',') }}</span>
            </td>
            <td class="card">
                <strong>Monto cobrado (USD)</strong>
                <span class="value">${{ number_format($cobrado_usd, 2, '.', ',') }}</span>
            </td>
            <td class="card">
                <strong>Pagado al proveedor (RMB)</strong>
                <span class="value">¥{{ number_format($pagado_rmb, 2, '.', ',') }}</span>
            </td>
        </tr>
    </table>

    <div class="box">
        <div class="kicker">DATOS DEL CLIENTE</div>
        <p style="margin: 6px 0 0;">
            <strong>{{ $cliente ?: '—' }}</strong><br>
            Consolidado: {{ $consolidado ?: '—' }}<br>
            Pagos registrados: {{ count($pagos) }}
        </p>
    </div>

    <div class="box">
        <div class="kicker">PROVEEDOR</div>
        <p style="margin: 6px 0 0;">
            Código: <strong>{{ $codigo_proveedor }}</strong><br>
            @if($nombre_proveedor){{ $nombre_proveedor }}<br>@endif
            Producto: {{ $producto ?: '—' }}
        </p>
    </div>

    <div class="box">
        <div class="kicker">DETALLE DEL CAMBIO</div>
        <table class="detail">
            <thead>
                <tr>
                    <th>Pago</th>
                    <th class="right">T.C. real</th>
                    <th class="right">USD cobrados</th>
                    <th class="right">¥ obtenidos</th>
                    <th class="right">¥ pagados</th>
                    <th class="right">Diferencia USD</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pagos as $pago)
                    <tr>
                        <td>Pago {{ $pago['n'] }}</td>
                        <td class="right">{{ number_format($pago['tc'], 4, '.', ',') }}</td>
                        <td class="right">${{ number_format($pago['usd'], 2, '.', ',') }}</td>
                        <td class="right">¥{{ number_format($pago['obtenido'], 2, '.', ',') }}</td>
                        <td class="right">¥{{ number_format($pago['pagado'], 2, '.', ',') }}</td>
                        <td class="right">${{ number_format(abs($pago['diff_usd']), 2, '.', ',') }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td><strong>Total</strong></td>
                    <td class="right">—</td>
                    <td class="right"><strong>${{ number_format($cobrado_usd, 2, '.', ',') }}</strong></td>
                    <td class="right"><strong>¥{{ number_format($obtenido_rmb, 2, '.', ',') }}</strong></td>
                    <td class="right"><strong>¥{{ number_format($pagado_rmb, 2, '.', ',') }}</strong></td>
                    <td class="right"><strong class="orange">${{ number_format($diferencia_usd, 2, '.', ',') }}</strong></td>
                </tr>
            </tbody>
        </table>
        <p class="muted" style="margin: 10px 0 0;">Yuanes obtenidos = dólares × tipo de cambio real. La diferencia en dólares es (obtenidos − pagados) ÷ tipo de cambio real.</p>
    </div>

    <div class="conformidad">
        Por favor, confirma tu conformidad para continuar con el proceso.
    </div>

    @if($mostrar_cuentas)
        <div class="box">
            <div class="kicker">CUENTAS PARA EL DEPÓSITO DE LA DIFERENCIA</div>
            <p class="muted" style="margin: 6px 0 0;">Razón social: <strong style="color: #181818;">PRO MUNDO COMEX SAC</strong> · RUC 20612452432 · Cuentas en dólares (USD)</p>
            <div class="cuenta">
                <strong style="color: #004a99;">BCP</strong><br>
                Cuenta 191-4216222-1-10<br>
                CCI 002-191-00421622211-0-57
            </div>
            <div class="cuenta">
                <strong style="color: #009650;">Interbank</strong><br>
                Cuenta 200-3006218820<br>
                CCI 003-200-003006218820-33
            </div>
        </div>
    @endif
</body>
</html>
