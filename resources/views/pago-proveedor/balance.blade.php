<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    @php($compacto = $varios_pagos && $mostrar_cuentas)
    <style>
        @page { margin: 37mm 15mm 24mm 15mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Helvetica, sans-serif; color: #2b2b2b; font-size: 9pt; }
        table { border-collapse: collapse; }
        td, th { padding: 0; vertical-align: top; }
        .cabecera { position: fixed; top: -30mm; left: 0; right: 0; height: 23.6mm; border-bottom: 0.9mm solid #f2662b; }
        .pie { position: fixed; bottom: -20mm; left: 0; right: 0; height: 12mm; border-top: 0.3mm solid #e4e4e4; padding-top: 1.2mm; color: #737373; font-size: 7.5pt; line-height: 4mm; }
        .seccion { position: relative; height: 11mm; }
        .seccion img { position: absolute; left: 0; top: 0; width: 6mm; height: 6mm; }
        .seccion-titulo { position: absolute; left: 8.5mm; top: 1.2mm; font-weight: bold; font-size: 11pt; color: #181818; }
        .seccion-linea { position: absolute; left: 0; right: 0; top: 8mm; border-top: 0.4mm solid #e2e2e2; }
        .mantener { page-break-inside: avoid; }
        .dato-etiqueta { position: absolute; top: 0.3mm; font-size: 7.8pt; color: #737373; white-space: nowrap; }
        .dato-valor { position: absolute; top: 5.3mm; font-weight: bold; color: #181818; white-space: nowrap; }
        .tarjeta { position: absolute; top: 0; height: 28mm; border-radius: 2.5mm; }
        .tarjeta-icono { position: absolute; left: 4.5mm; top: 4.5mm; width: 7mm; height: 7mm; }
        .tarjeta-etiqueta { position: absolute; left: 14mm; top: 7.3mm; font-weight: bold; font-size: 6.8pt; white-space: nowrap; }
        .tarjeta-valor { position: absolute; left: 4.5mm; top: 12.6mm; font-weight: bold; white-space: nowrap; }
        .tarjeta-nota { position: absolute; left: 4.5mm; top: 22.3mm; font-size: 8pt; white-space: nowrap; }
        .nota-gris { font-size: 7.8pt; color: #737373; }
        .detalle { border: 0.3mm solid #e2e2e2; border-radius: 2.5mm; padding: {{ $compacto ? '1mm' : '2mm' }} 3mm 3mm; }
        .detalle table { width: 100%; table-layout: fixed; }
        .detalle td, .detalle th { padding: {{ $compacto ? '1.6mm' : '2.3mm' }} 0; border-bottom: 0.2mm solid #e4e4e4; font-size: 9pt; vertical-align: middle; }
        .detalle tr.ultima td { border-bottom: 0; }
        .detalle td.etiqueta { color: #555555; }
        .detalle td.valor, .detalle th { text-align: right; white-space: nowrap; color: #181818; }
        .detalle th { font-weight: bold; font-size: 9.5pt; }
        .detalle .total { background: #ffede1; padding-right: 2mm; font-weight: bold; }
        .detalle th.total { color: #d6541a; }
        .conclusion { margin-top: 1.5mm; background: #ffede1; border-radius: 1.8mm; padding: 2.6mm 4mm; color: #d6541a; font-weight: bold; font-size: 9.5pt; }
    </style>
</head>
<body>
    <div class="cabecera">
        <img src="{{ $logo }}" style="position: absolute; left: 0; top: 0; width: 38mm;" alt="Pro Business">
        <div style="position: absolute; right: 0; top: 3.2mm; text-align: right; line-height: 5mm;">
            <div style="font-weight: bold; font-size: 14pt; color: #2b2b2b;">Balance de pago a proveedor</div>
            <div style="font-size: 9pt; color: #737373; margin-top: 1mm;">Emitido: {{ $fecha }}</div>
        </div>
    </div>

    <div class="pie">
        PRO MUNDO COMEX SAC · RUC 20612452432 · info@probusiness.pe<br>
        Av. Nicolás Arriola 314, Urb. Santa Catarina – La Victoria, Piso 11, Oficina 3
    </div>

    {{-- Datos del cliente --}}
    <div class="seccion">
        <img src="{{ $iconos['persona'] }}" alt="">
        <div class="seccion-titulo">DATOS DEL CLIENTE</div>
        <div class="seccion-linea"></div>
    </div>
    @php($x = 0)
    <div style="position: relative; height: 10.4mm;">
        @foreach($datos_cliente as $indice => $dato)
            @if($indice > 0)
                <div style="position: absolute; left: {{ $x }}mm; top: -0.4mm; height: 10.4mm; border-left: 0.3mm solid #e2e2e2;"></div>
            @endif
            <div class="dato-etiqueta" style="left: {{ $x + ($indice > 0 ? 4 : 0) }}mm;">{{ $dato['etiqueta'] }}</div>
            <div class="dato-valor" style="left: {{ $x + ($indice > 0 ? 4 : 0) }}mm; font-size: {{ $dato['pt'] }}pt;">{{ $dato['valor'] }}</div>
            @php($x += $dato['ancho'])
        @endforeach
    </div>

    {{-- Tarjetas: diferencia, monto cobrado y pagado al proveedor --}}
    @php($x = 0)
    <div style="position: relative; height: 28mm; margin-top: {{ $compacto ? 7 : 9.4 }}mm;">
        @foreach($tarjetas as $tarjeta)
            <div class="tarjeta" style="left: {{ $x }}mm; width: {{ $tarjeta['ancho'] }}mm; background: {{ $tarjeta['tipo'] === 'total' ? '#f2662b' : '#f4f4f4' }}; color: {{ $tarjeta['tipo'] === 'total' ? '#ffffff' : '#181818' }};">
                @if($tarjeta['tipo'] === 'total')
                    <div style="position: absolute; left: 4.5mm; top: 4.5mm; width: 8mm; height: 8mm; background: #ffffff; border-radius: 2mm;"></div>
                    <img src="{{ $iconos[$tarjeta['icono']] }}" style="position: absolute; left: 5.7mm; top: 5.7mm; width: 5.6mm; height: 5.6mm;" alt="">
                    <div class="tarjeta-etiqueta" style="left: 15mm;">{{ $tarjeta['etiqueta'] }}</div>
                @else
                    <img class="tarjeta-icono" src="{{ $iconos[$tarjeta['icono']] }}" alt="">
                    <div class="tarjeta-etiqueta" style="color: #737373;">{{ $tarjeta['etiqueta'] }}</div>
                @endif
                <div class="tarjeta-valor" style="font-size: {{ $tarjeta['valor_pt'] }}pt;">{{ $tarjeta['valor'] }}</div>
                <div class="tarjeta-nota" style="{{ $tarjeta['tipo'] === 'total' ? '' : 'color: #737373;' }}">{{ $tarjeta['nota'] }}</div>
            </div>
            @php($x += $tarjeta['ancho'] + 3)
        @endforeach
    </div>
    <div class="nota-gris" style="margin-top: 1.6mm;">Montos cobrados en dólares americanos (USD) y pagados al proveedor en yuanes (RMB).</div>

    {{-- Proveedor --}}
    <div class="seccion" style="margin-top: {{ $compacto ? 5 : 8 }}mm;">
        <img src="{{ $iconos['caja'] }}" alt="">
        <div class="seccion-titulo">PROVEEDOR</div>
        <div class="seccion-linea"></div>
    </div>
    @php($x = 0)
    <div style="position: relative; height: 10.4mm;">
        @foreach($datos_proveedor as $indice => $dato)
            @if($indice > 0)
                <div style="position: absolute; left: {{ $x }}mm; top: -0.4mm; height: 10.4mm; border-left: 0.3mm solid #e2e2e2;"></div>
            @endif
            <div class="dato-etiqueta" style="left: {{ $x + ($indice > 0 ? 4 : 0) }}mm;">{{ $dato['etiqueta'] }}</div>
            <div class="dato-valor" style="left: {{ $x + ($indice > 0 ? 4 : 0) }}mm; font-size: 10.5pt; width: {{ $dato['ancho'] - 6 }}mm; overflow: hidden;">{{ $dato['valor'] }}</div>
            @php($x += $dato['ancho'])
        @endforeach
    </div>

    {{-- Detalle del cambio --}}
    <div class="mantener" style="margin-top: {{ $compacto ? 4 : 7 }}mm;">
        <div class="seccion" style="height: 9mm;">
            <img src="{{ $iconos['documento'] }}" alt="">
            <div class="seccion-titulo">DETALLE DEL CAMBIO</div>
            <div class="seccion-linea"></div>
        </div>
        <div class="detalle">
            <div style="padding: 0 2mm;">
                @if($varios_pagos)
                    <table>
                        <tr>
                            <th style="width: {{ 170 - 38.5 * (count($columnas) + 1) }}mm;"></th>
                            @foreach($columnas as $columna)
                                <th style="width: 38.5mm;">{{ $columna }}</th>
                            @endforeach
                            <th class="total" style="width: 38.5mm;">Total</th>
                        </tr>
                        @foreach($filas as $indice => $fila)
                            <tr class="{{ $indice === count($filas) - 1 ? 'ultima' : '' }}">
                                <td class="etiqueta">{{ $fila[0] }}</td>
                                @foreach(array_slice($fila, 1, -1) as $valor)
                                    <td class="valor">{{ $valor }}</td>
                                @endforeach
                                <td class="valor total">{{ $fila[count($fila) - 1] }}</td>
                            </tr>
                        @endforeach
                    </table>
                @else
                    <table>
                        @foreach($filas as $indice => $fila)
                            <tr class="{{ $indice === count($filas) - 1 ? 'ultima' : '' }}">
                                <td class="etiqueta">{{ $fila[0] }}</td>
                                <td class="valor" style="font-weight: bold;">{{ $fila[1] }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endif
            </div>
            <div class="conclusion">{{ $conclusion }}</div>
        </div>
        <div class="nota-gris" style="font-size: 7.5pt; line-height: 3.3mm; margin-top: 2mm;">Cómo se calcula: los yuanes obtenidos son el monto cobrado multiplicado por el tipo de cambio aplicado; la diferencia es la resta entre los yuanes obtenidos y los yuanes pagados al proveedor.</div>
    </div>

    {{-- Cuentas: solo cuando el cliente tiene que depositar la diferencia --}}
    @if($mostrar_cuentas)
        <div class="mantener" style="margin-top: {{ $compacto ? 3 : 5 }}mm;">
            <div class="seccion">
                <img src="{{ $iconos['banco'] }}" alt="">
                <div class="seccion-titulo">CUENTAS PARA EL DEPÓSITO</div>
                <div class="seccion-linea"></div>
            </div>
            <div style="font-size: 8.4pt; color: #737373; margin: -2.4mm 0 1.4mm;">Razón social: <strong style="color: #181818;">PRO MUNDO COMEX SAC</strong> · RUC 20612452432 · Cuentas corrientes en dólares (USD)</div>
            <div style="position: relative; height: 26mm; border: 0.5mm solid #181818; border-radius: 2mm;">
                <div style="position: absolute; left: 90mm; top: 0; height: 25mm; border-left: 0.4mm solid #181818;"></div>
                @foreach($cuentas as $indice => $cuenta)
                    @php($x = $indice * 90)
                    <div style="position: absolute; left: {{ $x + 3.4 }}mm; top: 4mm; width: 1.6mm; height: 18mm; background: {{ $cuenta['color'] }};"></div>
                    <div style="position: absolute; left: {{ $x + 8 }}mm; top: 3.9mm; font-weight: bold; font-size: 13pt; color: {{ $cuenta['color'] }};">{{ $cuenta['banco'] }}</div>
                    <div style="position: absolute; left: {{ $x + 8 }}mm; top: 13.1mm; font-weight: bold; font-size: 7pt; color: #737373;">CUENTA</div>
                    <div style="position: absolute; left: {{ $x + 27 }}mm; top: 11.7mm; font-weight: bold; font-size: 11.5pt; color: #2b2b2b;">{{ $cuenta['cuenta'] }}</div>
                    <div style="position: absolute; left: {{ $x + 8 }}mm; top: 19.1mm; font-weight: bold; font-size: 7pt; color: #737373;">CCI</div>
                    <div style="position: absolute; left: {{ $x + 27 }}mm; top: 18.1mm; font-weight: bold; font-size: 10pt; color: #2b2b2b;">{{ $cuenta['cci'] }}</div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Conformidad --}}
    <div class="mantener" style="position: relative; height: 10mm; margin-top: {{ $mostrar_cuentas ? 3 : 8 }}mm; background: #ffede1; border-radius: 2mm;">
        <div style="position: absolute; left: 0; top: 0; width: 1.8mm; height: 10mm; background: #f2662b; border-radius: 0.9mm;"></div>
        <div style="position: absolute; left: 7mm; top: 3.2mm; font-weight: bold; font-size: 9.5pt; color: #181818;">Por favor, confírmanos tu conformidad para continuar con el proceso.</div>
    </div>
</body>
</html>
