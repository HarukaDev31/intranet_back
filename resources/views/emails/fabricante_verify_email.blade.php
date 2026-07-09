<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Verifica tu correo</title>
</head>
<body style="font-family: Arial, sans-serif; color: #0E1512; line-height: 1.6;">
    <h2 style="color: #1A6B3C;">ProBusiness Fabricante</h2>
    <p>Hola <strong>{{ $companyName }}</strong>,</p>
    <p>Gracias por registrarte en el portal de fabricantes. Para activar tu cuenta, verifica tu correo electrónico:</p>
    <p>
        <a href="{{ $verificationUrl }}"
           style="display:inline-block;padding:12px 20px;background:#1A6B3C;color:#fff;text-decoration:none;border-radius:8px;">
            Verificar correo
        </a>
    </p>
    <p>Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
    <p style="word-break:break-all;">{{ $verificationUrl }}</p>
    <p style="color:#7A8C82;font-size:13px;">Este enlace expira en 48 horas.</p>
</body>
</html>
