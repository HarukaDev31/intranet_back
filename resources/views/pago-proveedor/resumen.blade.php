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
        .total { background: #fff4ec; border-color: #f0c7a8; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { text-align: left; padding: 6px 4px; border-bottom: 1px solid #eee; vertical-align: top; }
        th { color: #666; font-weight: normal; font-size: 11px; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <p class="muted" style="margin: 0;">Pro Business</p>
    <h1>Resumen de pago a proveedor</h1>
    <p class="muted">{{ $fecha }} · {{ $cotizacion['codigo'] }}</p>

    <div class="box">
        <strong>Datos del cliente</strong>
        <p style="margin: 6px 0 0;">
            {{ $cotizacion['cliente_nombre'] ?: '—' }}<br>
            Consolidado: {{ $cotizacion['consolidado'] ?: '—' }}<br>
            Cotización C.C.: {{ $cotizacion['cod_cotizacion_cc'] }}<br>
            Proveedores: {{ $cotizacion['proveedores_cantidad'] }}<br>
            Tipo de cambio aplicado: {{ $cotizacion['tipo_cambio'] ? number_format($cotizacion['tipo_cambio'], 4, '.', ',') : '—' }}
        </p>
    </div>

    <div class="box total">
        <span class="orange"><strong>{{ $cotizacion['total_ahora'] != $cotizacion['total'] ? 'Total a pagar ahora' : 'Total a pagar' }}</strong></span>
        <span class="right orange" style="float: right; font-size: 16px;"><strong>$ {{ number_format($cotizacion['total_ahora'], 2, '.', ',') }}</strong></span>
        <div style="clear: both;"></div>
        <p class="muted" style="margin: 6px 0 0;">Comisiones: $ {{ number_format($cotizacion['comisiones'], 2, '.', ',') }}</p>
        @if($cotizacion['total_ahora'] != $cotizacion['total'])
            <p class="muted" style="margin: 4px 0 0;">Total del servicio: $ {{ number_format($cotizacion['total'], 2, '.', ',') }}</p>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Proveedor</th>
                <th>Moneda</th>
                <th class="right">Carga</th>
                <th class="right">Bancaria</th>
                <th class="right">Administrativa</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cotizacion['proveedores'] as $proveedor)
                @php
                    $calculo = $proveedor['calculo'] ?? [];
                    $segunda = $proveedor['nombre_proveedor']
                        ? $proveedor['nombre_proveedor'].' · '.$proveedor['producto']
                        : $proveedor['producto'];
                @endphp
                <tr>
                    <td>
                        <strong>Proveedor {{ $proveedor['orden'] }}: {{ $proveedor['code_supplier'] }}</strong><br>
                        <span class="muted">{{ $segunda }}</span>
                    </td>
                    <td>{{ $proveedor['moneda'] === 'RMB' ? 'RMB / Transferencia' : 'USD / T-T' }}</td>
                    <td class="right">$ {{ number_format($proveedor['valor_carga_usd'], 2, '.', ',') }}</td>
                    <td class="right">
                        @if(!empty($calculo['etiqueta_bancaria']))
                            {{ $calculo['etiqueta_bancaria'] }}
                        @else
                            $ {{ number_format($calculo['bancaria'] ?? 0, 2, '.', ',') }}
                        @endif
                    </td>
                    <td class="right">$ {{ number_format($calculo['administrativa'] ?? 0, 2, '.', ',') }}</td>
                    <td class="right">$ {{ number_format($calculo['total'] ?? 0, 2, '.', ',') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="box">
        <strong>Cuentas para el depósito</strong>
        <p class="muted">Las cuentas oficiales de BCP e Interbank se envían como imagen junto con el mensaje de WhatsApp. Concepto: Pago a proveedor.</p>
    </div>
</body>
</html>
