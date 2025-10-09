<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¡Registro confirmado!</title>
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

        .info-value a {
            color: #007bff;
            text-decoration: none;
        }

        .info-value a:hover {
            text-decoration: underline;
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

        .social-icons {
            text-align: right;
        }

        .social-icon {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            display: inline-block;
            text-align: center;
            text-decoration: none;
            margin-left: 5px;
            vertical-align: top;
        }

        .social-icon:hover {
            background: #ff6600;
        }


        .social-icon img {
            fill: white !important;
            width: 20px;
            height: 20px;
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
            
            .social-icons {
                text-align: center !important;
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
            <div class="title">¡Tu registro ha sido confirmado con éxito!</div>
            
            <div class="subtitle">
                Gracias por confiar en Probusiness, tu aliado en formación y gestión logística.<br><br>
                Ya puedes acceder a tu perfil para completar la información sobre ti y tu empresa. 
                Esto nos permitirá brindarte una atención más personalizada y optimizar tus futuros procesos de carga e importación.
            </div>

            <div class="access-section">
                <div class="access-title">Datos de acceso</div>
                
                <table class="info-table">
                    <tr>
                        <td class="info-label">Correo:</td>
                        <td class="info-value">{{ $email }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Contraseña:</td>
                        <td class="info-value">{{ $password }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Enlace intranet:</td>
                        <td class="info-value">
                            <a href="https://clientes.probusiness.pe/">www.probusiness.com/plataforma</a>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="support-text">
                Si tienes alguna duda o necesitas asistencia, nuestro equipo de soporte estará encantado de ayudarte.
            </div>
            
            <div class="team-signature">
                El equipo de Probusiness
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
                    <td class="footer-right">
                        <div class="social-icons">
                            <a href="https://www.facebook.com/share/1BKArQfZYm/" class="social-icon">
                                <img src="{{ $message->embed($social_icons['facebook']) }}" alt="Facebook">
                            </a>
                            <a href="https://www.instagram.com/probusinesspe/" class="social-icon">
                                <img src="{{ $message->embed($social_icons['instagram']) }}" alt="Instagram">
                            </a>
                            <a href="https://www.tiktok.com/@pro_business_impo?_t=ZS-90Ptf7Jyyaz&_r=1" class="social-icon">
                                <img src="{{ $message->embed($social_icons['tiktok']) }}" alt="TikTok">
                            </a>
                            <a href="https://youtube.com/@miguelvillegasimportaciones?si=fmxCdT7eOT2kgrf9" class="social-icon">
                                <img src="{{ $message->embed($social_icons['youtube']) }}" alt="YouTube">
                            </a>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>

</html>