<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Iniciar sesión — Sistema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="/estyle.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0f1117;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            background: #1a1d27;
            border: 1px solid #2a2d3a;
            border-radius: 14px;
            padding: 2.5rem 2rem;
        }
        .login-logo {
            width: 52px;
            height: 52px;
            background: linear-gradient(135deg, #4f8ef7, #7c5cfc);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            color: #fff;
            margin: 0 auto 1.25rem;
        }
        .login-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: #fff;
            text-align: center;
            margin-bottom: .25rem;
        }
        .login-subtitle {
            text-align: center;
            color: #8b8fa8;
            font-size: .88rem;
            margin-bottom: 1.75rem;
        }
        .form-label {
            color: #c0c4d8;
            font-size: .875rem;
            font-weight: 500;
            margin-bottom: .35rem;
        }
        .form-control {
            background: #0f1117;
            border: 1px solid #2a2d3a;
            color: #e4e6f0;
            border-radius: 8px;
            padding: .6rem .85rem;
        }
        .form-control:focus {
            background: #0f1117;
            border-color: #4f8ef7;
            color: #e4e6f0;
            box-shadow: 0 0 0 3px rgba(79,142,247,.15);
        }
        .form-control::placeholder { color: #484c62; }
        .input-group-text {
            background: #0f1117;
            border: 1px solid #2a2d3a;
            color: #6b6f87;
            cursor: pointer;
        }
        .input-group .form-control { border-right: 0; }
        .input-group .input-group-text { border-left: 0; border-radius: 0 8px 8px 0; }
        .input-group .form-control { border-radius: 8px 0 0 8px; }
        .btn-login {
            background: linear-gradient(135deg, #4f8ef7, #7c5cfc);
            border: none;
            border-radius: 8px;
            color: #fff;
            font-weight: 600;
            padding: .65rem;
            width: 100%;
            font-size: .95rem;
            transition: opacity .2s;
        }
        .btn-login:hover { opacity: .9; color: #fff; }
        .form-check-input:checked {
            background-color: #4f8ef7;
            border-color: #4f8ef7;
        }
        .form-check-label { color: #8b8fa8; font-size: .85rem; }
        .alert-danger {
            background: rgba(220,53,69,.15);
            border-color: rgba(220,53,69,.3);
            color: #f77;
            border-radius: 8px;
            font-size: .875rem;
        }
    </style>
</head>
<body>
<div class="login-card">
    <div class="login-logo"><i class="bi bi-layers"></i></div>
    <div class="login-title">Sistema de Gestión</div>
    <div class="login-subtitle">Ingresa tus credenciales para continuar</div>

    @if($errors->any())
    <div class="alert alert-danger mb-3">
        <i class="bi bi-exclamation-circle me-2"></i>
        {{ $errors->first() }}
    </div>
    @endif

    <form method="POST" action="/login">
        @csrf

        <div class="mb-3">
            <label class="form-label" for="identifier">Correo o nombre de usuario</label>
            <input
                type="text"
                id="identifier"
                name="identifier"
                class="form-control @error('identifier') is-invalid @enderror"
                placeholder="usuario@empresa.com o nombre"
                value="{{ old('identifier') }}"
                autocomplete="username"
                autofocus
                required
            >
            @error('identifier')
                <div class="invalid-feedback d-block" style="color:#f77;font-size:.8rem;margin-top:.25rem;">
                    {{ $message }}
                </div>
            @enderror
        </div>

        <div class="mb-3">
            <label class="form-label" for="password">Contraseña</label>
            <div class="input-group">
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-control"
                    placeholder="••••••••"
                    autocomplete="current-password"
                    required
                >
                <span class="input-group-text" id="togglePassword" onclick="togglePwd()">
                    <i class="bi bi-eye" id="eyeIcon"></i>
                </span>
            </div>
        </div>

        <div class="d-flex align-items-center justify-content-between mb-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember">
                <label class="form-check-label" for="remember">Recordar sesión</label>
            </div>
        </div>

        <button type="submit" class="btn btn-login">
            <i class="bi bi-box-arrow-in-right me-2"></i>Iniciar sesión
        </button>
    </form>
</div>

<script>
function togglePwd() {
    const input = document.getElementById('password');
    const icon  = document.getElementById('eyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
}
</script>
</body>
</html>
