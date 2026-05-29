<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rotulado — Consolidado #{{ $carga }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; margin: 0; padding: 20px; line-height: 1.5; color: #333; }
        .container { max-width: 720px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        .header { background: #333; color: #fff; padding: 20px 24px; }
        .header h1 { margin: 0 0 8px; font-size: 18px; font-weight: 600; }
        .header p { margin: 0; font-size: 14px; opacity: .9; }
        .content { padding: 24px; }
        .section { margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid #eee; }
        .section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .section h2 { margin: 0 0 10px; font-size: 15px; color: #111; }
        .section pre { margin: 0; white-space: pre-wrap; word-wrap: break-word; font-family: inherit; font-size: 14px; background: #f9f9f9; padding: 12px; border-radius: 6px; border: 1px solid #eee; }
        .footer { padding: 16px 24px; background: #fafafa; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Resumen de rotulado (mismo contenido enviado por WhatsApp)</h1>
            <p>Cliente: {{ $cliente }} · Consolidado #{{ $carga }} · Cotización #{{ $idCotizacion }}@if($telefonoCliente) · Tel: {{ $telefonoCliente }}@endif</p>
        </div>
        <div class="content">
            @foreach ($sections as $section)
                <div class="section">
                    @if (!empty($section['title']))
                        <h2>{{ $section['title'] }}</h2>
                    @endif
                    <pre>{{ $section['body'] }}</pre>
                </div>
            @endforeach
        </div>
        <div class="footer">
            Los archivos adjuntos son los mismos que se enviaron al cliente por WhatsApp (el documento en chino del mensaje de bienvenida lo envía el servicio welcomeV2).
        </div>
    </div>
</body>
</html>
