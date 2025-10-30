<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cargo de entrega</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        .header { text-align: left; margin-bottom: 20px; }
        .logo { width: 160px; }
        .title { text-align: center; font-weight: bold; font-size: 16px; margin-top: 10px; }
        .section { margin-top: 20px; }
        .field { margin-bottom: 8px; }
        .main {margin-top: 15em;}
    </style>
</head>
<body>
    <div class="header">
        @php
            $logoFs = public_path('storage/logo_icons/logo_header.png');
            $logoDataUri = null;
            if (file_exists($logoFs)) {
                try {
                    $mime = function_exists('mime_content_type') ? mime_content_type($logoFs) : 'image/png';
                    $data = base64_encode(file_get_contents($logoFs));
                    $logoDataUri = 'data:' . $mime . ';base64,' . $data;
                } catch (\Exception $e) {
                    $logoDataUri = null;
                }
            }
        @endphp
        @if($logoDataUri)
    </div>
    <div class="page">
        <div class="header">
            <div class="content">
                <img src="{{ $logoDataUri }}" class="logo" />
                @elseif(file_exists($logoFs))
                    {{-- Fallback to public path URL in case data URI fails for some renderers --}}
                    <img src="{{ asset('storage/logo_icons/logo_header.png') }}" class="logo" />
                @endif
            </div>
        </div>

        <div class="main">
            
            <div class="title">CARGO DE ENTREGA DE MERCANCÍA</div>

            <div class="section">
                <p>Yo, ,___________________________________ identificado(a) con DNI N.º _____________________, en representación de <strong>{{ $cliente ?? '' }}</strong>, declaro haber recibido de PRO BUSINESS la siguiente mercancía:</p>

                <ul>
                    <li>Número de consolidado: <strong>#{{ $carga ?? $cotizacion_id ?? '' }}</strong></li>
                    <li>Cantidad de cajas: <strong>{{ $qty ?? '' }}</strong></li>
                </ul>
            </div>
        </div>
        <p>Asumo desde esta fecha la responsabilidad total por la custodia, transporte y manipulación de la mercancía recibida.</p>
    </div>

    <div class="section">
        <p>Fecha: _________________</p>
        <p>Firma del receptor: _________________________</p>
    </div>

</body>
</html>
