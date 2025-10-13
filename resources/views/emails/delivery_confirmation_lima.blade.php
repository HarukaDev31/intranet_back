<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¡Confirmación de Recojo - Lima!</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #fff;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
        }

        .header {
            background: #fff;
            padding: 24px 0 0 0;
            text-align: center;
        }

        .logo {
            width: 180px;
            margin-bottom: 10px;
        }

        .banner {
            background: #ff6600;
            color: #fff;
            font-size: 2rem;
            font-weight: bold;
            padding: 18px 0;
        }

        .content {
            padding: 24px;
        }

        .title {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 8px;
            text-align: center;
        }

        .subtitle {
            font-size: 1rem;
            margin-bottom: 18px;
            text-align: center;
        }

        .info-table {
            width: 100%;
            margin-bottom: 24px;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 4px 0;
            font-size: 0.98rem;
        }

        .info-label {
            color: #888;
            width: 120px;
            font-weight: bold;
        }

        .delivery-info {
            background: #f8f9fa;
            padding: 16px;
            border-radius: 8px;
            margin: 16px 0;
        }

        .delivery-title {
            background: #666;
            color: #fff;
            padding: 8px;
            font-weight: bold;
            font-size: 1rem;
            margin: -16px -16px 16px -16px;
            border-radius: 8px 8px 0 0;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }

        .summary-table th,
        .summary-table td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .summary-table th {
            background: #f5f5f5;
            color: #444;
            font-weight: bold;
        }

        .footer {
            text-align: center;
            color: #888;
            font-size: 0.95rem;
            padding: 16px 0 0 0;
        }

        .footer-logo {
            width: 120px;
            margin-top: 24px;
        }

        .highlight {
            background: #fff3cd;
            padding: 12px;
            border-radius: 4px;
            border-left: 4px solid #ffc107;
            margin: 16px 0;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <img src="{{ $message->embed($logo_header) }}" alt="probusiness" class="logo">
        </div>
        <div class="banner" style="text-align:center;">Consolidado #{{ $carga }}</div>
        <div class="content">
            <div class="title">¡Confirmación de Recojo - Lima!</div>
            <div class="subtitle">
                Tu reserva se realizó exitosamente. A continuación te proporcionamos los detalles de tu recojo.
            </div>

            <div class="delivery-info">
                <div class="delivery-title">Información de Recojo</div>
                <table class="info-table">
                    <tr>
                        <td class="info-label">Fecha de Recojo:</td>
                        <td><strong>{{ $fechaRecojo }}</strong></td>
                    </tr>
                    <tr>
                        <td class="info-label">Horario:</td>
                        <td><strong>{{ $horaRecojo }}</strong></td>
                    </tr>
                    <tr>
                        <td class="info-label">Persona de Recojo:</td>
                        <td><strong>{{ $deliveryForm->pick_name }}</strong></td>
                    </tr>
                    <tr>
                        <td class="info-label">DNI:</td>
                        <td><strong>{{ $deliveryForm->pick_doc }}</strong></td>
                    </tr>
                </table>
            </div>

         

            <div class="footer">
                <strong>¡Importante!</strong><br>
                Por favor asegúrate de estar disponible en la fecha y hora programada para el recojo de tu pedido.<br><br>
                Si tienes alguna consulta o necesitas modificar tu reserva, no dudes en contactarnos.<br><br>
                Gracias por confiar en Probusiness, donde conectamos tu negocio con los mejores productos y servicios.<br><br>
                Equipo Probusiness
            </div>
            <footer style="background:#111; padding:24px 0; text-align:left;">
                <img src="{{ $message->embed($logo_footer) }}" alt="probusiness" class="footer-logo" style="display:inline-block; margin-left:24px;">
            </footer>
        </div>
    </div>
</body>

</html>
