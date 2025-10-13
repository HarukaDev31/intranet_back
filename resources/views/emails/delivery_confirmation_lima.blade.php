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
            <img src="{{ $message->embed($logo_header) }}" alt="probusiness" class="logo">
        </div>
        
        <div class="content">
            <div class="title">¡Confirmación de Recojo del Consolidado #{{ $carga }} - Lima!</div>
            
            <div class="subtitle">
                Gracias por confiar en Probusiness, tu aliado en formación y gestión logística.<br><br>
                Hemos recibido tu solicitud de recojo para Lima. Por favor asegúrate de estar disponible en la fecha y hora programada para el recojo de tu pedido.
            </div>


            <div class="access-section">
                <div class="access-title">Información de Recojo</div>
                
                <table class="info-table">
                    <tr>
                        <td class="info-label">Fecha de Recojo:</td>
                        <td class="info-value">{{ $fechaRecojo }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Horario:</td>
                        <td class="info-value">{{ $horaRecojo }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Persona de Recojo:</td>
                        <td class="info-value">{{ $deliveryForm->pick_name }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">DNI:</td>
                        <td class="info-value">{{ $deliveryForm->pick_doc }}</td>
                    </tr>
                </table>
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
