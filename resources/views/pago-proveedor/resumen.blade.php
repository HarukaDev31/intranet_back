<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1a1a1a; font-size: 11px; }
        h1 { font-size: 17px; margin: 0 0 4px; color: #0f2a4a; }
        h2 { font-size: 12px; margin: 0 0 6px; color: #0f2a4a; }
        .muted { color: #666; }
        .orange { color: #e4570e; }
        .box { border: 1px solid #e5e5e5; border-radius: 6px; padding: 9px 11px; margin-top: 10px; }
        .total { background: #fff4ec; border-color: #f0c7a8; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { text-align: left; padding: 5px 3px; border-bottom: 1px solid #eee; vertical-align: top; }
        th { color: #666; font-weight: normal; font-size: 10px; }
        .right { text-align: right; }
        .accounts { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .accounts td { border: 1px solid #0f2a4a; padding: 8px 10px; width: 50%; vertical-align: top; }
        .bank { font-size: 13px; font-weight: bold; margin-bottom: 4px; }
        .bank-bcp { color: #004a99; }
        .bank-ibk { color: #009650; }
        .label { font-size: 9px; color: #666; text-transform: uppercase; }
        .steps { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .steps td { width: 20%; text-align: center; vertical-align: top; padding: 4px; border: none; }
        .step-n { display: inline-block; background: #0f2a4a; color: #fff; border-radius: 10px; padding: 2px 7px; font-size: 9px; font-weight: bold; margin-bottom: 4px; }
        .step-t { font-weight: bold; font-size: 10px; color: #0f2a4a; }
        .step-d { font-size: 9px; color: #666; margin-top: 2px; }
        .terms li { margin-bottom: 3px; color: #444; font-size: 9.5px; }
        .footer { margin-top: 14px; border-top: 1px solid #ddd; padding-top: 6px; font-size: 9px; color: #777; }
        .img-cuentas { margin-top: 8px; max-width: 100%; }
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
        <span class="right orange" style="float: right; font-size: 15px;"><strong>$ {{ number_format($cotizacion['total_ahora'], 2, '.', ',') }}</strong></span>
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
        <h2>Cuentas para el depósito</h2>
        <p class="muted" style="margin: 0 0 6px;">
            Razón social: <strong>PRO MUNDO COMEX SAC</strong> · RUC 20612452432 · Cuentas corrientes en dólares (USD).
            Concepto: <strong>Pago a proveedor</strong>.
        </p>
        @if(!empty($cuentas_imagen))
            <img class="img-cuentas" src="{{ $cuentas_imagen }}" alt="Cuentas bancarias">
        @else
            <table class="accounts">
                <tr>
                    <td>
                        <div class="bank bank-bcp">BCP</div>
                        <div><span class="label">Cuenta</span><br><strong>191-4216222-1-10</strong></div>
                        <div style="margin-top:4px;"><span class="label">CCI</span><br><strong>002-191-00421622211-0-57</strong></div>
                    </td>
                    <td>
                        <div class="bank bank-ibk">Interbank</div>
                        <div><span class="label">Cuenta</span><br><strong>200-3006218820</strong></div>
                        <div style="margin-top:4px;"><span class="label">CCI</span><br><strong>003-200-003006218820-33</strong></div>
                    </td>
                </tr>
            </table>
        @endif
    </div>

    <div class="box">
        <h2>Tu pago al proveedor, paso a paso</h2>
        <p class="muted" style="margin: 0 0 4px;">Un proceso claro de principio a fin.</p>
        <table class="steps">
            <tr>
                <td>
                    <div class="step-n">INICIO</div>
                    <div class="step-t">Cotización</div>
                    <div class="step-d">Recibes el resumen con el total a depositar.</div>
                </td>
                <td>
                    <div class="step-n">PASO 1</div>
                    <div class="step-t">Depósito</div>
                    <div class="step-d">Abonas a nuestras cuentas y registramos tu voucher.</div>
                </td>
                <td>
                    <div class="step-n">PASO 2</div>
                    <div class="step-t">Validación</div>
                    <div class="step-d">Administración confirma el ingreso del dinero.</div>
                </td>
                <td>
                    <div class="step-n">PASO 3</div>
                    <div class="step-t">Pago al proveedor</div>
                    <div class="step-d">Transferimos a tu proveedor (USD o RMB).</div>
                </td>
                <td>
                    <div class="step-n">PASO 4</div>
                    <div class="step-t">Confirmación</div>
                    <div class="step-d">Constancia y, si aplica, balance de cambio.</div>
                </td>
            </tr>
        </table>
        @if(!empty($hay_cuotas))
            <p class="muted" style="margin: 8px 0 0; font-style: italic;">Si pagas en cuotas, los pasos 1 al 4 se repiten con cada cuota.</p>
        @endif
    </div>

    <div class="box">
        <h2>Términos y condiciones</h2>
        <ul class="terms">
            <li>El tipo de cambio del resumen es referencial; el aplicado al pago en yuanes es el del día de la transferencia.</li>
            <li>Las comisiones bancaria y administrativa se cobran según lo cotizado; si el banco aplica un cargo mayor, se refleja en la ganancia del servicio.</li>
            <li>Debes depositar el total indicado con el concepto «Pago a proveedor». Diferencias dentro de la tolerancia las asume Pro Business.</li>
            <li>Para pagos en dólares (T-T) el cliente autoriza los datos bancarios del proveedor antes de la transferencia.</li>
            <li>Pro Business no se responsabiliza por datos incorrectos del proveedor (WeChat, cuenta, SWIFT) proporcionados por el cliente.</li>
        </ul>
    </div>

    <div class="footer">
        PRO MUNDO COMEX SAC · RUC 20612452432 · info@probusiness.pe<br>
        Av. Nicolás Arriola 314, Urb. Santa Catarina – La Victoria, Piso 11, Oficina 3
    </div>
</body>
</html>
