<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recupera tu contraseña</title>
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
            color: #666;
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

        .action-box {
            background: #f8f9fa;
            border-left: 4px solid #ff6600;
            padding: 20px;
            margin: 24px 0;
            border-radius: 4px;
            text-align: center;
        }

        .reset-button {
            display: inline-block;
            background: #ff6600;
            color: #fff !important;
            padding: 14px 32px;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            margin: 16px 0;
        }

        .reset-button:hover {
            background: #e55a00;
        }

        .warning-box {
            background: #fff8e1;
            border: 1px solid #ffd54f;
            padding: 16px;
            margin: 24px 0;
            border-radius: 4px;
            font-size: 0.9rem;
            color: #666;
        }

        .link-text {
            background: #f5f5f5;
            padding: 12px;
            border-radius: 4px;
            word-break: break-all;
            font-size: 0.85rem;
            color: #666;
            margin-top: 16px;
            border: 1px solid #e0e0e0;
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
            color: #ff6600;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <img src="{{ $message->embed($logo_header) }}" alt="probusiness" class="logo">
        </div>
        <div class="banner" style="text-align:center;">¿Olvidaste tu contraseña?</div>
        <div class="content">
            <div class="title">¡No te preocupes! Estamos aquí para ayudarte</div>
            <div class="subtitle">
                Recuperar el acceso a tu cuenta es rápido y sencillo
            </div>

            <table class="info-table">
                <tr>
                    <td class="info-label">Información</td>
                    <td>
                        <span>
                            Recibimos tu solicitud para restablecer la contraseña de la cuenta asociada a <strong>{{ $email }}</strong>
                        </span>
                        <br><br>
                        <span style="color: #666;">
                            Para continuar y crear una nueva contraseña, simplemente haz clic en el botón de abajo.
                        </span>
                    </td>
                </tr>
            </table>

            <div class="action-box">
                <p style="margin: 0 0 12px 0; color: #444;">
                    <strong>Haz clic aquí para restablecer tu contraseña:</strong>
                </p>
                <a href="{{ $resetUrl }}" class="reset-button">Restablecer mi contraseña</a>
                <p style="margin: 16px 0 0 0; font-size: 0.85rem; color: #666;">
                    Este enlace es válido por <span class="highlight">60 minutos</span>
                </p>
            </div>

            <div class="warning-box">
                <strong>⚠️ Importante:</strong>
                <ul style="margin: 8px 0 0 0; padding-left: 20px; text-align: left;">
                    <li>Si no solicitaste este cambio, puedes ignorar este correo de forma segura.</li>
                    <li>Tu contraseña actual seguirá siendo válida hasta que crees una nueva.</li>
                    <li>Por seguridad, este enlace expirará automáticamente en 60 minutos.</li>
                </ul>
            </div>

            <p style="font-size: 0.9rem; color: #666; text-align: center;">
                <strong>¿El botón no funciona?</strong><br>
                Copia y pega este enlace en tu navegador:
            </p>
            <div class="link-text">
                {{ $resetUrl }}
            </div>

            <div class="footer">
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

