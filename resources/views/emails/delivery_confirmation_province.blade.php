<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¡Confirmación de Envío - Provincia!</title>
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
            padding: 0;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
            border: 1px solid #e8e8e8;
        }

        .access-title {
            font-size: 14px;
            font-weight: 600;
            color: #fff;
            margin: 0;
            text-align: left;
            letter-spacing: 0.02em;
        }

        .card-header-orange {
            background: #f97316;
            padding: 12px 20px;
        }

        .card-body {
            padding: 20px 24px 22px;
            background: #fff;
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
            <img src="{{ $message->embed($logo_header) }}" alt="probusiness" class="logo">
        </div>
        
        <div class="content">
            <div class="title">¡Confirmación de Envío del Consolidado #{{ $carga }} - Provincia!</div>
            
            <div class="subtitle">
                Gracias por confiar en Probusiness, tu aliado en formación y gestión logística.<br><br>
                @if(!empty($primerNombre))
                    Hola, <strong>{{ $primerNombre }}</strong>.<br><br>
                @endif
                Hemos recibido tu solicitud de envío a provincia para el <strong>Consolidado #{{ $carga }}</strong>.
                Los datos registrados son los siguientes (coinciden con la notificación enviada por WhatsApp):
            </div>

            <div class="access-section">
                <div class="card-header-orange">
                    <div class="access-title">Destinatario confirmado</div>
                </div>
                <div class="card-body">
                    <table class="info-table">
                        <tr>
                            <td class="info-label">Nombre:</td>
                            <td class="info-value">{{ $nombreDestinatario }}</td>
                        </tr>
                        <tr>
                            <td class="info-label">{{ $tipoDocumento }}:</td>
                            <td class="info-value">{{ $numeroDocumento }}</td>
                        </tr>
                        <tr>
                            <td class="info-label">Celular:</td>
                            <td class="info-value">{{ $celularDestinatario }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="access-section">
                <div class="card-header-orange">
                    <div class="access-title">Agencia de transporte</div>
                </div>
                <div class="card-body">
                    <table class="info-table">
                        <tr>
                            <td class="info-label">Agencia:</td>
                            <td class="info-value">{{ $nombreAgenciaTransporte }}</td>
                        </tr>
                        <tr>
                            <td class="info-label">RUC:</td>
                            <td class="info-value">{{ $rucAgenciaTransporte }}</td>
                        </tr>
                        <tr>
                            <td class="info-label">Destino:</td>
                            <td class="info-value">{{ $destinoLinea }}</td>
                        </tr>
                        <tr>
                            <td class="info-label">Entrega en:</td>
                            <td class="info-value">{{ $entregaEn }}</td>
                        </tr>
                        @if(!empty($direccionEntrega))
                        <tr>
                            <td class="info-label">Dirección:</td>
                            <td class="info-value">{{ $direccionEntrega }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>
        </div>

        <div class="footer">
            <table class="footer-table">
                <tr>
                    <td class="footer-left">
                        <img src="{{ $message->embed($logo_footer) }}" alt="probusiness" class="footer-logo">
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
