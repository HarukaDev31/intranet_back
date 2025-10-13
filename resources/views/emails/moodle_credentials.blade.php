<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credenciales de acceso a Moodle</title>
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

        .banner {
            background: #ff6600;
            color: #fff;
            font-size: 1.8rem;
            font-weight: bold;
            padding: 18px 0;
            text-align: center;
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

        .info-value a {
            color: #ff6600;
            text-decoration: none;
        }

        .info-value a:hover {
            text-decoration: underline;
        }

        .button-container {
            text-align: center;
            margin: 30px 0;
        }

        .access-button {
            display: inline-block;
            background: #ff6600;
            color: #fff !important;
            padding: 14px 32px;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            font-size: 16px;
        }

        .access-button:hover {
            background: #e55a00;
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

        .highlight-box {
            background: #fff8e1;
            border-left: 4px solid #ff6600;
            padding: 16px;
            margin: 20px 0;
            border-radius: 4px;
        }

        .highlight-box strong {
            color: #333;
        }

        .footer {
            background: #333;
            padding: 25px 30px;
        }

        .footer-logo {
            height: 25px;
            max-width: 150px;
            display: block;
        }

        .footer-address {
            color: #999;
            font-size: 11px;
            line-height: 1.4;
            margin: 8px 0 0 0;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <img src="{{ $message->embed($logo_header) }}" alt="probusiness" class="logo">
        </div>
        
        <div class="banner">¡Bienvenido a Moodle!</div>
        
        <div class="content">
            <div class="title">Hola {{ $nombre }}, tus credenciales están listas</div>
            
            <div class="subtitle">
                Nos complace informarte que tu cuenta en nuestra plataforma ha sido configurada exitosamente.<br><br>
                A continuación encontrarás tus credenciales de acceso para comenzar tu aprendizaje.
            </div>

            <div class="access-section">
                <div class="access-title">Datos de acceso </div>
                
                <table class="info-table">
                    <tr>
                        <td class="info-label">Usuario:</td>
                        <td class="info-value"><strong>{{ $username }}</strong></td>
                    </tr>
                    <tr>
                        <td class="info-label">Contraseña:</td>
                        <td class="info-value"><strong>{{ $password }}</strong></td>
                    </tr>
                    <tr>
                        <td class="info-label">Email:</td>
                        <td class="info-value">{{ $email }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Plataforma:</td>
                        <td class="info-value">
                            <a href="{{ $moodleUrl }}">{{ $moodleUrl }}</a>
                        </td>
                    </tr>
                </table>
            </div>

           
            <div class="support-text">
                Si tienes alguna duda o necesitas asistencia para acceder a la plataforma, nuestro equipo de soporte estará encantado de ayudarte.
            </div>
            
            <div class="team-signature">
                El equipo de Probusiness
            </div>
        </div>

        <div class="footer">
            <img src="{{ $message->embed($logo_footer) }}" alt="probusiness" class="footer-logo">
            <div class="footer-address">
                Av Nicolás Arriola 374, La Victoria 15034,<br>
                Lima, Perú
            </div>
        </div>
    </div>
</body>

</html>

