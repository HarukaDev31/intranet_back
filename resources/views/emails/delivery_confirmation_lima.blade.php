<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¡Confirmación de Recojo - Lima!</title>
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
            border-radius: 0;
            overflow: hidden;
        }

        .header {
            background: #333;
            padding: 20px;
            text-align: center;
        }

        .logo {
            height: 40px;
            max-width: 200px;
        }

        .content {
            padding: 40px 30px;
            background: #f9f9f9;
        }

        .title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            text-align: center;
            color: #333;
        }

        .subtitle {
            font-size: 14px;
            color: #666;
            margin-bottom: 30px;
            text-align: center;
            line-height: 1.5;
        }

        .access-section {
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .access-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 25px;
            text-align: center;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table tr {
            border-bottom: 1px solid #f0f0f0;
        }

        .info-table tr:last-child {
            border-bottom: none;
        }

        .info-table td {
            padding: 12px 0;
            vertical-align: top;
        }

        .info-label {
            color: #666;
            font-size: 14px;
            width: 130px;
            font-weight: 500;
        }

        .info-value {
            color: #333;
            font-size: 14px;
            font-weight: 400;
        }

        .support-text {
            font-size: 13px;
            color: #666;
            text-align: center;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .team-signature {
            font-size: 13px;
            color: #666;
            text-align: center;
            font-weight: 500;
        }

        .footer {
            background: #333;
            padding: 25px 30px;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
        }

        .footer-left {
            vertical-align: top;
            width: 50%;
        }

        .footer-right {
            vertical-align: top;
            width: 50%;
            text-align: right;
        }

        .footer-logo {
            height: 25px;
            max-width: 150px;
            display: block;
            margin-bottom: 8px;
        }

        .footer-address {
            color: #999;
            font-size: 11px;
            line-height: 1.4;
            margin: 0;
        }

        .highlight {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #ffc107;
            margin: 20px 0;
            font-size: 14px;
            line-height: 1.5;
        }

        @media (max-width: 600px) {
            .footer-table td {
                display: block;
                width: 100% !important;
                text-align: center !important;
            }
            
            .footer-right {
                text-align: center !important;
                padding-top: 20px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            @if (!empty($logo_header))
            <img src="{{ $message->embed($logo_header) }}" alt="probusiness" class="logo">
            @endif
        </div>
        
        <div class="content">
            <div class="title">¡Formulario completado!</div>

            <div class="subtitle">
                Tu recojo ha sido agendado.<br>
                Te esperamos en nuestro almacén.
            </div>

            <div style="text-align:center; margin-bottom: 25px;">
                <span style="display:inline-block; background:#333; color:#fff; padding:8px 18px; border-radius:999px; font-size:13px; font-weight:600; letter-spacing:0.3px;">
                    Consolidado #{{ $carga }}
                </span>
            </div>

            <div class="access-section">
                <div class="access-title">👤 Persona que recoge</div>

                <table class="info-table">
                    <tr>
                        <td class="info-label">Nombre:</td>
                        <td class="info-value">{{ $pickName }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">DNI:</td>
                        <td class="info-value">{{ $pickDoc }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Celular:</td>
                        <td class="info-value">{{ $pickPhone }}</td>
                    </tr>
                </table>
            </div>

            <div class="access-section">
                <div class="access-title">📅 Fecha y hora de recojo</div>

                <div class="support-text" style="text-align:left; font-size:14px; color:#333;">
                    <strong>{{ $fechaTextual }}</strong> &nbsp;·&nbsp; {{ $horaRecojo }} hrs<br>
                    <span style="color:#666; font-size:13px;">Recojo agendado en nuestro almacén.</span>
                </div>
            </div>

            <div class="access-section">
                <div class="access-title">📍 Dirección de recojo</div>

                <div class="support-text" style="text-align:left; font-size:14px; color:#333;">
                    <strong>{{ $direccion }}</strong><br>
                    <span style="color:#666; font-size:13px;">{{ $referencia }}</span>
                </div>

                <div style="text-align:center; margin-top:20px;">
                    <a href="{{ $mapsUrl }}" target="_blank"
                       style="display:inline-block; background:#fff; color:#333; border:1px solid #333; padding:10px 22px; border-radius:6px; text-decoration:none; font-size:13px; font-weight:600;">
                        Ver en Google Maps
                    </a>
                </div>
            </div>

            <div class="access-section">
                <div class="access-title">¡Importante!</div>

                <div class="support-text">
                    Por favor asegúrate de estar disponible en la fecha y hora programada para el recojo de tu pedido.<br><br>
                    Si tienes alguna consulta o necesitas modificar tu reserva, no dudes en contactarnos.<br><br>
                    Gracias por confiar en Probusiness, donde conectamos tu negocio con los mejores productos y servicios.<br><br>
                    Equipo Probusiness
                </div>
            </div>
        </div>

        <div class="footer">
            <table class="footer-table">
                <tr>
                    <td class="footer-left">
                        @if (!empty($logo_footer))
                        <img src="{{ $message->embed($logo_footer) }}" alt="probusiness" class="footer-logo">
                        @endif
                        <div class="footer-address">
                            Av Nicolás Arriola 374, La Victoria 15034,<br>
                            Lima, Perú
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>

</html>
