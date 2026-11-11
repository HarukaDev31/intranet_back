<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 37mm 15mm 24mm 15mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Helvetica, sans-serif; color: #2b2b2b; font-size: 9pt; }
        table { border-collapse: collapse; }
        td { padding: 0; vertical-align: top; }
        .cabecera { position: fixed; top: -30mm; left: 0; right: 0; height: 23.6mm; border-bottom: 0.9mm solid #f2662b; }
        .pie { position: fixed; bottom: -20mm; left: 0; right: 0; height: 12mm; border-top: 0.3mm solid #e4e4e4; padding-top: 1.2mm; color: #737373; font-size: 7.5pt; line-height: 4mm; }
        .seccion { position: relative; height: 11mm; }
        .seccion img { position: absolute; left: 0; top: 0; width: 6mm; height: 6mm; }
        .seccion-titulo { position: absolute; left: 8.5mm; top: 1.2mm; font-weight: bold; font-size: 11pt; color: #181818; }
        .seccion-nota { position: absolute; right: 0; top: 2mm; font-size: 7.6pt; color: #737373; }
        .seccion-marca { display: inline-block; width: 3mm; height: 3mm; background: #f2662b; border-radius: 0.7mm; margin-right: 1.5mm; }
        .seccion-linea { position: absolute; left: 0; right: 0; top: 8mm; border-top: 0.4mm solid #e2e2e2; }
        .mantener { page-break-inside: avoid; }
        .tarjeta { position: relative; height: 28mm; border-radius: 2.5mm; }
        .tarjeta-icono { position: absolute; left: 4.5mm; top: 4.5mm; width: 7mm; height: 7mm; }
        .tarjeta-etiqueta { position: absolute; left: 14mm; top: 7.3mm; font-weight: bold; font-size: 6.8pt; }
        .tarjeta-valor { position: absolute; left: 4.5mm; top: 12.6mm; font-weight: bold; white-space: nowrap; }
        .tarjeta-nota { position: absolute; left: 4.5mm; top: 22.3mm; white-space: nowrap; }
        .proveedor { border: 0.3mm solid #e2e2e2; border-radius: 2.5mm; margin-bottom: 4mm; page-break-inside: avoid; padding-bottom: 4mm; }
        .proveedor-cabeza { position: relative; height: 15mm; }
        .proveedor-n { position: absolute; left: 4.5mm; top: 4mm; width: 8mm; height: 8mm; line-height: 6.4mm; border-radius: 4mm; background: #181818; color: #fff; text-align: center; font-weight: bold; font-size: 10.5pt; }
        .proveedor-titulo { position: absolute; left: 16.5mm; top: 3.6mm; font-weight: bold; font-size: 10.5pt; color: #181818; white-space: nowrap; }
        .proveedor-sub { position: absolute; left: 16.5mm; top: 8.8mm; color: #737373; white-space: nowrap; }
        .proveedor-total { position: absolute; right: 4mm; top: 3mm; width: 43.5mm; height: 9.8mm; background: #f4f4f4; border-radius: 1.8mm; text-align: right; padding: 1.2mm 2.5mm 0 0; }
        .fila { width: 100%; }
        .fila td { border-bottom: 0.2mm solid #e4e4e4; padding: 1mm 0; font-size: 9pt; color: #2b2b2b; }
        .fila td.valor { text-align: right; white-space: nowrap; }
        .fila-cobro td { font-size: 8.8pt; padding: 0.9mm 0; }
        .subtotal { width: 100%; border-radius: 1.8mm; margin-top: 2mm; }
        .subtotal td { padding: 2.3mm 3mm; font-weight: bold; vertical-align: middle; }
        .pill { display: inline-block; position: relative; top: 0.6mm; border-radius: 2.3mm; padding: 0.5mm 2mm; font-size: 7.2pt; font-weight: bold; margin-left: 2mm; }
        .naranja-claro { background: #ffede1; color: #d6541a; }
        .gris-claro { background: #f4f4f4; color: #181818; }
    </style>
</head>
<body>
    <div class="cabecera">
        <img src="{{ $logo }}" style="position: absolute; left: 0; top: 0; width: 38mm;" alt="Pro Business">
        <div style="position: absolute; right: 0; top: 3.2mm; text-align: right; line-height: 5mm;">
            <div style="font-weight: bold; font-size: 14pt; color: #2b2b2b;">Resumen de pago a proveedor</div>
            <div style="font-size: 9pt; color: #737373; margin-top: 1mm;">Emitido: {{ $fecha }}</div>
            @if($referencia)
                <div style="font-size: 9pt; color: #737373;">{{ $referencia }}</div>
            @endif
        </div>
    </div>

    <div class="pie">
        PRO MUNDO COMEX SAC · RUC 20612452432 · info@probusiness.pe<br>
        Av. Nicolás Arriola 314, Urb. Santa Catarina – La Victoria, Piso 11, Oficina 3
    </div>

    <div class="seccion">
        <img src="{{ $iconos['persona'] }}" alt="">
        <div class="seccion-titulo">DATOS DEL CLIENTE</div>
        <div class="seccion-linea"></div>
    </div>
    @php($x = 0)
    @php($altoCliente = collect($datos_cliente)->contains(fn ($dato) => !empty($dato['segunda_linea'])) ? 14 : 10.4)
    <div style="position: relative; height: {{ $altoCliente }}mm;">
        @foreach($datos_cliente as $indice => $dato)
            @if($indice > 0)
                <div style="position: absolute; left: {{ $x }}mm; top: -0.4mm; height: {{ $altoCliente }}mm; border-left: 0.3mm solid #e2e2e2;"></div>
            @endif
            <div style="position: absolute; left: {{ $x + ($indice > 0 ? 4 : 0) }}mm; top: 0.3mm; font-size: 7.8pt; color: #737373; white-space: nowrap;">{{ $dato['etiqueta'] }}</div>
            <div style="position: absolute; left: {{ $x + ($indice > 0 ? 4 : 0) }}mm; top: 5.3mm; font-weight: bold; color: #181818; font-size: {{ $dato['pt'] }}pt; white-space: nowrap;">{{ $dato['valor'] }}@if(!empty($dato['segunda_linea']))<br>{{ $dato['segunda_linea'] }}@endif</div>
            @php($x += $dato['ancho'])
        @endforeach
    </div>

    @php($x = 0)
    <div style="position: relative; height: 28mm; margin-top: 9.4mm;">
        @foreach($tarjetas as $tarjeta)
            <div class="tarjeta" style="position: absolute; left: {{ $x }}mm; top: 0; width: {{ $tarjeta['ancho'] }}mm; background: {{ $tarjeta['tipo'] === 'total' ? '#f2662b' : '#f4f4f4' }}; color: {{ $tarjeta['tipo'] === 'total' ? '#ffffff' : '#181818' }};">
                @if($tarjeta['tipo'] === 'total')
                    <div style="position: absolute; left: 4.5mm; top: 4.5mm; width: 8mm; height: 8mm; background: #fff; border-radius: 2mm;"></div>
                    <img src="{{ $iconos[$tarjeta['icono']] }}" style="position: absolute; left: 5.7mm; top: 5.7mm; width: 5.6mm; height: 5.6mm;" alt="">
                    <div class="tarjeta-etiqueta" style="left: 15mm;">{{ $tarjeta['etiqueta'] }}</div>
                @else
                    <img class="tarjeta-icono" src="{{ $iconos[$tarjeta['icono']] }}" alt="">
                    <div class="tarjeta-etiqueta" style="color: #737373;">{{ $tarjeta['etiqueta'] }}</div>
                @endif
                <div class="tarjeta-valor" style="font-size: {{ $tarjeta['valor_pt'] }}pt;">{{ $tarjeta['valor'] }}</div>
                @if($tarjeta['nota'])
                    <div class="tarjeta-nota" style="font-size: {{ $tarjeta['nota_pt'] }}pt; {{ $tarjeta['tipo'] === 'total' ? '' : 'color: #737373;' }}">{{ $tarjeta['nota'] }}</div>
                @endif
            </div>
            @php($x += $tarjeta['ancho'] + 3)
        @endforeach
    </div>

    <div style="font-size: 7.8pt; color: #737373; margin-top: 1.6mm;">Montos expresados en dólares americanos (USD).</div>

    @if($vigencia)
        <div style="position: relative; height: 10mm; margin-top: 2mm; background: #ffede1; border-radius: 2mm;">
            <div style="position: absolute; left: 0; top: 0; width: 1.8mm; height: 10mm; background: #f2662b; border-radius: 0.9mm;"></div>
            <img src="{{ $reloj }}" style="position: absolute; left: 5.2mm; top: 2.4mm; width: 6.6mm; height: 6.6mm;" alt="">
            <div style="position: absolute; left: 14mm; top: 2.9mm; font-weight: bold; font-size: 9.8pt; color: #181818;">Cotización vigente hasta el <span style="color: #d6541a;">{{ $vigencia }}</span></div>
        </div>
    @endif

    <div style="height: 9mm;"></div>

    @foreach($bloques as $indice => $bloque)
        @if($indice === 0)
            <div class="seccion">
                <img src="{{ $iconos['caja'] }}" alt="">
                <div class="seccion-titulo">DETALLE POR PROVEEDOR</div>
                <div class="seccion-nota"><span class="seccion-marca"></span>Los importes en naranja suman el total a pagar ahora.</div>
                <div class="seccion-linea"></div>
            </div>
        @endif
        <div class="proveedor">
            <div class="proveedor-cabeza">
                <div class="proveedor-n">{{ $bloque['numero'] }}</div>
                <div class="proveedor-titulo">{{ $bloque['titulo'] }}</div>
                <div class="proveedor-sub" style="font-size: {{ $bloque['subtitulo_pt'] }}pt;">{{ $bloque['subtitulo'] }}</div>
                @if($bloque['cobros'])
                    <div class="proveedor-total">
                        <div style="font-size: 6.4pt; color: #737373;">Total del proveedor (2 cuotas)</div>
                        <div style="font-weight: bold; font-size: 12.5pt; color: #181818;">{{ $bloque['total'] }}</div>
                    </div>
                @endif
            </div>

            @if($bloque['cobros'])
                <div style="margin: 2.5mm 4mm 3.5mm; font-size: 8.2pt; color: #737373;">Valor de carga total: <strong style="color: #181818; margin-left: 2.5mm;">{{ $bloque['valor_texto'] }}</strong></div>
                <table style="width: 172mm; margin: 0 4mm; table-layout: fixed;">
                    <tr>
                        @foreach($bloque['cobros'] as $numeroCobro => $cobro)
                            <td style="width: 86mm; {{ $numeroCobro === 0 ? 'padding-right: 4mm;' : 'padding-left: 4mm; border-left: 0.3mm solid #e2e2e2;' }}">
                                <div style="height: 6.5mm; font-weight: bold; font-size: 9.5pt; color: #181818;">
                                    {{ $cobro['titulo'] }}<span class="pill {{ $numeroCobro === 0 ? 'naranja-claro' : '' }}" style="{{ $numeroCobro === 0 ? '' : 'background: #f4f4f4; color: #737373;' }}">{{ $cobro['etiqueta'] }}</span>
                                </div>
                                <table class="fila fila-cobro">
                                    @foreach($cobro['filas'] as $fila)
                                        <tr><td>{{ $fila['etiqueta'] }}</td><td class="valor">{{ $fila['valor'] }}</td></tr>
                                    @endforeach
                                </table>
                                <table class="subtotal {{ $numeroCobro === 0 ? 'naranja-claro' : 'gris-claro' }}">
                                    <tr>
                                        <td style="font-size: 9.6pt;">{{ $cobro['total_titulo'] }}</td>
                                        <td style="font-size: 12pt; text-align: right;">{{ $cobro['total'] }}</td>
                                    </tr>
                                </table>
                            </td>
                        @endforeach
                    </tr>
                </table>
            @else
                <div style="margin: 0 4mm;">
                    <table class="fila">
                        @foreach($bloque['filas'] as $fila)
                            <tr><td>{{ $fila['etiqueta'] }}</td><td class="valor">{{ $fila['valor'] }}</td></tr>
                        @endforeach
                    </table>
                    <table class="subtotal naranja-claro">
                        <tr>
                            <td style="font-size: 9.8pt;">Subtotal del proveedor</td>
                            <td style="font-size: 12.5pt; text-align: right;">{{ $bloque['total'] }}</td>
                        </tr>
                    </table>
                </div>
            @endif
        </div>
    @endforeach

    @if($compromiso)
        <table style="width: 180mm; border-top: 0.3mm solid #e2e2e2; margin: 1mm 0 3mm;">
            <tr>
                <td style="padding: 2.8mm 2mm; font-weight: bold; font-size: 10pt; color: #181818;">Compromiso total de pago</td>
                <td style="padding: 2.4mm 2mm; font-weight: bold; font-size: 13pt; color: #181818; text-align: right;">{{ $compromiso }}</td>
            </tr>
        </table>
    @endif

    @foreach($bloques as $bloque)
        @if($bloque['fraccion'])
            <div class="mantener" style="margin-bottom: 5mm;">
                <div style="font-weight: bold; font-size: 9.5pt; color: #181818; margin-bottom: 1.5mm;">
                    {{ $bloque['fraccion']['titulo'] }}
                    @if(count($bloques) > 1)
                        <span style="color: #2b2b2b;"> — {{ $bloque['titulo'] }}</span>
                    @endif
                </div>
                <table style="width: 180mm; table-layout: fixed;">
                    <tr>
                        @foreach($bloque['fraccion']['partes'] as $numeroParte => $parte)
                            <td style="width: 90mm; {{ $numeroParte === 0 ? 'padding-right: 2mm;' : 'padding-left: 2mm;' }}">
                                <div style="position: relative; height: 15mm; background: #f9f9f9; border: 0.3mm solid #e2e2e2; border-radius: 1.5mm;">
                                    <div style="position: absolute; left: 3mm; top: 1.6mm; font-size: 7.5pt; color: #737373;">{{ $parte['etiqueta'] }}</div>
                                    <div style="position: absolute; left: 3mm; top: 5.4mm; font-weight: bold; font-size: 15pt; color: #181818;">{{ $parte['porcentaje'] }}</div>
                                    <div style="position: absolute; left: 27mm; top: 4.5mm; height: 7.5mm; border-left: 0.2mm solid #e4e4e4;"></div>
                                    <div style="position: absolute; left: 31mm; top: 4.2mm; font-weight: bold; font-size: 11pt; color: #2b2b2b;">{{ $parte['monto'] }}</div>
                                    <div style="position: absolute; left: 31mm; top: 10mm; font-size: 7.5pt; color: #737373;">{{ $parte['nota'] }}</div>
                                </div>
                            </td>
                        @endforeach
                    </tr>
                </table>
                <div style="font-style: italic; font-size: 8.5pt; color: #737373; line-height: 4.4mm; margin: 2mm 0 0 1mm;">{{ $bloque['fraccion']['texto'] }}</div>
            </div>
        @endif
    @endforeach

    <div class="mantener" style="margin: 3mm 0 7mm;">
        <div class="seccion">
            <img src="{{ $iconos['banco'] }}" alt="">
            <div class="seccion-titulo">CUENTAS PARA EL DEPÓSITO</div>
            <div class="seccion-linea"></div>
        </div>
        <div style="font-size: 8.4pt; color: #737373; margin: -2.4mm 0 1.4mm;">Razón social: <strong style="color: #181818;">PRO MUNDO COMEX SAC</strong> · RUC 20612452432 · Cuentas corrientes en dólares (USD)</div>
        <div style="position: relative; height: 27mm; border: 0.5mm solid #181818; border-radius: 2mm;">
            <div style="position: absolute; left: 90mm; top: 0; height: 26mm; border-left: 0.4mm solid #181818;"></div>
            @foreach([['BCP', '#004a99', '191-4216222-1-10', '002-191-00421622211-0-57', 0], ['Interbank', '#009650', '200-3006218820', '003-200-003006218820-33', 90]] as [$banco, $color, $cuenta, $cci, $x])
                <div style="position: absolute; left: {{ $x + 3.4 }}mm; top: 4.5mm; width: 1.6mm; height: 18mm; background: {{ $color }};"></div>
                <div style="position: absolute; left: {{ $x + 8 }}mm; top: 4.4mm; font-weight: bold; font-size: 13pt; color: {{ $color }};">{{ $banco }}</div>
                <div style="position: absolute; left: {{ $x + 8 }}mm; top: 13.6mm; font-weight: bold; font-size: 7pt; color: #737373;">CUENTA</div>
                <div style="position: absolute; left: {{ $x + 27 }}mm; top: 12.2mm; font-weight: bold; font-size: 11.5pt; color: #2b2b2b;">{{ $cuenta }}</div>
                <div style="position: absolute; left: {{ $x + 8 }}mm; top: 19.6mm; font-weight: bold; font-size: 7pt; color: #737373;">CCI</div>
                <div style="position: absolute; left: {{ $x + 27 }}mm; top: 18.6mm; font-weight: bold; font-size: 10pt; color: #2b2b2b;">{{ $cci }}</div>
            @endforeach
        </div>
    </div>

    <div class="mantener" style="margin-bottom: 6mm;">
        <div class="seccion">
            <img src="{{ $iconos['ruta'] }}" alt="">
            <div class="seccion-titulo">TU PAGO AL PROVEEDOR, PASO A PASO</div>
            <div class="seccion-nota">Un proceso claro de principio a fin.</div>
            <div class="seccion-linea"></div>
        </div>
        <div style="position: relative; background: #f8f8f8; border: 0.3mm solid #e2e2e2; border-radius: 2.5mm; height: 46.7mm;">
            <img src="{{ $linea_tiempo }}" style="position: absolute; left: 0; top: 0; width: 180mm; height: 26mm;" alt="">
            <div style="position: absolute; left: {{ 9 + 36 * ($paso ?? 0) }}mm; top: 4.6mm; width: 18mm; text-align: center; font-weight: bold; font-size: 6.2pt; color: #ffffff;">ESTÁS AQUÍ</div>
            <table style="position: absolute; left: 0; top: 24.6mm; width: 180mm; table-layout: fixed;">
                <tr>
                    @foreach($pasos as $indice => $pasoActual)
                        <td style="width: 36mm; padding: 0 2mm; text-align: center;">
                            <div style="font-weight: bold; font-size: 6.8pt; color: {{ $pasoActual['color_etiqueta'] }}; margin-bottom: 1.2mm;">{{ $pasoActual['etiqueta'] }}</div>
                            <div style="height: 7.6mm; font-weight: bold; font-size: 8.4pt; color: {{ $pasoActual['color_titulo'] }}; line-height: 3.6mm;">{{ $pasoActual['titulo'] }}</div>
                            <div style="font-size: 7.6pt; color: {{ $pasoActual['color_nota'] }}; line-height: 3.1mm;">{{ $pasoActual['descripcion'] }}</div>
                        </td>
                    @endforeach
                </tr>
            </table>
        </div>
        @if($pago_en_cuotas)
            <div style="font-style: italic; font-size: 8.2pt; color: #737373; margin-top: 2mm;">Si pagas en cuotas, los pasos 1 al 4 se repiten con cada cuota.</div>
        @endif
    </div>

    @foreach($terminos as $numeroGrupo => $grupo)
        <div class="mantener" style="{{ $numeroGrupo > 0 ? 'page-break-before: always;' : '' }}">
            <div class="seccion">
                <img src="{{ $iconos['documento'] }}" alt="">
                <div class="seccion-titulo">{{ $grupo['titulo'] }}</div>
                <div class="seccion-linea"></div>
            </div>
            <table style="width: 180mm; table-layout: fixed;">
                <tr>
                    @foreach($grupo['columnas'] as $indiceColumna => $columna)
                        <td style="width: 90mm; {{ $indiceColumna === 0 ? 'padding-right: 5mm;' : 'padding-left: 5mm; border-left: 0.4mm solid #e2e2e2;' }}">
                            @foreach($columna as $seccion)
                                <div style="margin-bottom: 3mm;">
                                    @if($seccion['titulo'])
                                        <div style="font-weight: bold; font-size: 9.4pt; color: #181818; margin-bottom: 1.6mm;">{{ $seccion['titulo'] }}</div>
                                    @endif
                                    @foreach($seccion['items'] as $item)
                                        <div style="position: relative; padding-left: 4.5mm; margin-bottom: 2.4mm; font-size: 8.6pt; line-height: 3.9mm; color: #2b2b2b;">
                                            <span style="position: absolute; left: 0.4mm; top: 1.3mm; width: 1.2mm; height: 1.2mm; border-radius: 0.6mm; background: #181818;"></span>{{ $item }}
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </td>
                    @endforeach
                </tr>
            </table>
        </div>
    @endforeach
</body>
</html>
