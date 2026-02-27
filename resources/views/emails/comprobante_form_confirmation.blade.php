<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmación de Formulario de Comprobante</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
            overflow: hidden;
        }
        .header {
            background: #333;
            padding: 20px;
            text-align: center;
        }
        .content {
            padding: 40px 30px;
            background: #f9f9f9;
        }
        .title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 10px;
            text-align: center;
            color: #333;
        }
        .subtitle {
            font-size: 14px;
            color: #666;
            margin-bottom: 30px;
            text-align: center;
        }
        .card {
            background: #fff;
            padding: 24px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            margin-bottom: 20px;
        }
        .card-title {
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f3f4f6;
        }
        .field-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 8px 0;
            border-bottom: 1px solid #f3f4f6;
            font-size: 14px;
        }
        .field-row:last-child { border-bottom: none; }
        .field-label { color: #6b7280; min-width: 140px; }
        .field-value { color: #111827; font-weight: 500; text-align: right; }
        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-factura { background: #dbeafe; color: #1d4ed8; }
        .badge-boleta  { background: #d1fae5; color: #065f46; }
        .footer {
            background: #f3f4f6;
            padding: 20px 30px;
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
        }
        .footer strong { color: #6b7280; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <span style="color:#fff;font-size:18px;font-weight:700;">Pro Business</span>
    </div>

    <div class="content">
        <p class="title">✅ Formulario de Comprobante Registrado</p>
        <p class="subtitle">
            Consolidado #{{ $carga }} — Hola {{ $cotizacion->nombre }}, tu formulario fue enviado correctamente.
        </p>

        <div class="card">
            <div class="card-title">Datos de facturación enviados</div>

            <div class="field-row">
                <span class="field-label">Tipo de comprobante</span>
                <span class="field-value">
                    @if($form->tipo_comprobante === 'FACTURA')
                        <span class="badge badge-factura">FACTURA</span>
                    @else
                        <span class="badge badge-boleta">BOLETA</span>
                    @endif
                </span>
            </div>

            @if($form->destino_entrega)
            <div class="field-row">
                <span class="field-label">Destino de entrega</span>
                <span class="field-value">{{ $form->destino_entrega }}</span>
            </div>
            @endif

            @if($form->tipo_comprobante === 'FACTURA')
            <div class="field-row">
                <span class="field-label">RUC</span>
                <span class="field-value">{{ $form->ruc }}</span>
            </div>
            <div class="field-row">
                <span class="field-label">Razón social</span>
                <span class="field-value">{{ $form->razon_social }}</span>
            </div>
            @else
            <div class="field-row">
                <span class="field-label">Nombre completo</span>
                <span class="field-value">{{ $form->nombre_completo }}</span>
            </div>
            <div class="field-row">
                <span class="field-label">DNI / Carnet</span>
                <span class="field-value">{{ $form->dni_carnet }}</span>
            </div>
            @endif
        </div>

        <p style="font-size:13px;color:#6b7280;text-align:center;">
            Si tienes alguna consulta puedes comunicarte con nosotros. ¡Gracias por confiar en Pro Business!
        </p>
    </div>

    <div class="footer">
        <strong>Pro Business</strong><br>
        Este correo fue generado automáticamente, por favor no respondas a este mensaje.
    </div>
</div>
</body>
</html>
