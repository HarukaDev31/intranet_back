<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de documentación</title>
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
        .step {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            margin-bottom: 16px;
        }
        .step-text {
            font-size: 14px;
            color: #374151;
            white-space: pre-line;
        }
        .step-file {
            font-size: 14px;
            color: #374151;
        }
        .file-label {
            font-weight: 600;
            color: #111827;
        }
        .file-note {
            font-size: 12px;
            color: #6b7280;
            margin-top: 6px;
        }
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
        <p class="title">Solicitud de documentación</p>
        <p class="subtitle">
            Consolidado #{{ $cargaCode }} — Hola {{ $clienteNombre }}, te compartimos la misma información enviada por WhatsApp.
        </p>

        @foreach($steps as $step)
            @if(($step['type'] ?? '') === 'text')
                <div class="step">
                    <div class="step-text">{{ $step['content'] ?? '' }}</div>
                </div>
            @elseif(($step['type'] ?? '') === 'file')
                <div class="step">
                    <div class="step-file">
                        <span class="file-label">📎 {{ $step['caption'] ?? 'Archivo adjunto' }}</span>
                        <div class="file-note">Archivo incluido como adjunto en este correo.</div>
                    </div>
                </div>
            @endif
        @endforeach

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
