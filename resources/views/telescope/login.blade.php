<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Telescope</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #0f172a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.35);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }
        .login-header { text-align: center; margin-bottom: 30px; }
        .login-header h1 { color: #0f172a; font-size: 26px; margin-bottom: 8px; }
        .login-header p { color: #64748b; font-size: 14px; }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            color: #334155;
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }
        .form-group input:focus {
            outline: none;
            border-color: #0ea5e9;
        }
        .error-message {
            background: #fef2f2;
            color: #b91c1c;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #dc2626;
        }
        .btn-login {
            width: 100%;
            padding: 14px;
            background: #0ea5e9;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-login:hover { background: #0284c7; }
        .footer-text {
            text-align: center;
            margin-top: 20px;
            color: #94a3b8;
            font-size: 12px;
        }
        .logout-hint {
            text-align: center;
            margin-top: 12px;
            font-size: 12px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Telescope</h1>
            <p>Ingresa con tu usuario interno</p>
        </div>

        @if ($errors->any())
            <div class="error-message">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('telescope.login.post') }}">
            @csrf
            <div class="form-group">
                <label for="No_Usuario">Usuario</label>
                <input
                    type="text"
                    id="No_Usuario"
                    name="No_Usuario"
                    value="{{ old('No_Usuario') }}"
                    required
                    autofocus
                    placeholder="No_Usuario"
                >
            </div>
            <div class="form-group">
                <label for="No_Password">Contraseña</label>
                <input
                    type="password"
                    id="No_Password"
                    name="No_Password"
                    required
                    placeholder="Contraseña"
                >
            </div>
            <button type="submit" class="btn-login">Entrar</button>
        </form>

        <div class="footer-text">ProBusiness — Laravel Telescope</div>
        <div class="logout-hint">La sesión se guarda en cookie; no uses token en la URL.</div>
    </div>
</body>
</html>
