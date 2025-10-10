<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Constancia de Participación</title>
    <style>
        @page {
            margin: 0;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: 'lucide-handwriting', 'Arial', sans-serif;
            background-image: url('data:image/png;base64,{{ $fondoImg }}');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            width: 100%;
            height: 100vh;
            position: relative;
        }
        
        .container {
            position: absolute;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 50px;
            box-sizing: border-box;
        }
        
        .nombre {
            font-size: 48px;
            font-weight: bold;
            color: #2c3e50;
            margin: 20px 0;
            text-transform: uppercase;
        }
        
        .fecha {
            font-size: 24px;
            color: #34495e;
            margin-top: 20px;
        }
        
        .titulo {
            font-size: 36px;
            color: #2c3e50;
            margin-bottom: 30px;
        }
        
        .descripcion {
            font-size: 20px;
            color: #34495e;
            max-width: 800px;
            margin: 20px auto;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="titulo">CONSTANCIA DE PARTICIPACIÓN</div>
        <div class="descripcion">
            Se otorga la presente constancia a:
        </div>
        <div class="nombre">{{ $nombre }}</div>
        <div class="descripcion">
            Por haber participado exitosamente en el<br>
            <strong>Taller Virtual de Importación</strong><br>
            Equivalente a 12 horas académicas
        </div>
        <div class="fecha">Fecha de emisión: {{ $fecha }}</div>
    </div>
</body>
</html>

