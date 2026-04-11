<?php
// PRODUCCIÓN: Iniciar buffer de salida inmediatamente.
// Previene que warnings de db.php/auth.php aparezcan antes del DOCTYPE
// y rompan el parseo CSS del navegador.
ob_start();

require_once 'db.php';
require_once 'auth.php';

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admin.php");
    exit;
}

$error = "";
$error_type = ""; // 'empty' | 'invalid' | 'rate_limited'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = mb_substr(trim($_POST['username'] ?? ''), 0, 254);
    $pass = mb_substr(trim($_POST['password'] ?? ''), 0, 1024);

    if (empty($user) || empty($pass)) {
        $error      = "Completa todos los campos para continuar.";
        $error_type = "empty";
    } else {
        $login_result = attempt_login($user, $pass, $conn);
        if ($login_result === true) {
            header("Location: admin.php");
            exit;
        } elseif (is_array($login_result) && ($login_result['error'] ?? '') === 'rate_limited') {
            $error      = "Demasiados intentos fallidos. Espera {$login_result['remaining']} minuto(s).";
            $error_type = "rate_limited";
        } else {
            $error      = "Usuario o contraseña incorrectos.";
            $error_type = "invalid";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skale IA — Acceso Administrativo</title>
    <meta name="description" content="Panel de administración Skale IA. Accede con tus credenciales para gestionar tu asistente de IA.">
    <meta name="robots" content="noindex, nofollow">

    <!-- Google Fonts: Inter + Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">

    <!-- FontAwesome 6.4 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* ── Design Tokens ─────────────────────────────────────── */
        :root {
            --bg:            #060d1a;
            --bg-2:          #0a1628;
            --glass-bg:      rgba(10, 22, 40, 0.75);
            --glass-border:  rgba(0, 212, 255, 0.12);
            --glass-border-h:rgba(0, 212, 255, 0.35);
            --primary:       #00d4ff;
            --primary-dim:   rgba(0, 212, 255, 0.15);
            --primary-glow:  rgba(0, 212, 255, 0.4);
            --accent:        #7c3aed;
            --danger:        #ef4444;
            --danger-dim:    rgba(239, 68, 68, 0.12);
            --warning-dim:   rgba(245, 158, 11, 0.12);
            --warning:       #f59e0b;
            --text-main:     #e2eeff;
            --text-muted:    #7a9bbf;
            --text-subtle:   #4a6a8a;
            --input-bg:      rgba(6, 13, 26, 0.8);
            --font-body:     'Inter', sans-serif;
            --font-heading:  'Outfit', sans-serif;
            --radius-card:   22px;
            --radius-input:  12px;
            --radius-btn:    12px;
            --transition:    0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* ── Reset ─────────────────────────────────────────────── */
        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* ── Base ──────────────────────────────────────────────── */
        body {
            background-color: var(--bg);
            color: var(--text-main);
            font-family: var(--font-body);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow: hidden;
        }

        /* ── Fondo animado ─────────────────────────────────────── */
        .scene {
            position: fixed;
            inset: 0;
            z-index: -1;
            overflow: hidden;
        }

        /* Gradientes radiales base */
        .scene::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 10% 60%, rgba(0, 212, 255, 0.07), transparent),
                radial-gradient(ellipse 60% 50% at 90% 20%, rgba(124, 58, 237, 0.08), transparent),
                radial-gradient(ellipse 40% 40% at 50% 90%, rgba(0, 100, 200, 0.06), transparent);
        }

        /* Grid sutil de fondo */
        .scene::after {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(0, 212, 255, 0.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 212, 255, 0.025) 1px, transparent 1px);
            background-size: 60px 60px;
        }

        /* Orbs flotantes */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            animation: orbFloat linear infinite;
            pointer-events: none;
            opacity: 0;
            animation-fill-mode: forwards;
        }

        .orb-1 {
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(0, 212, 255, 0.12) 0%, transparent 70%);
            top: -15%;
            left: -10%;
            animation-duration: 20s;
            animation-delay: 0s;
        }

        .orb-2 {
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(124, 58, 237, 0.1) 0%, transparent 70%);
            bottom: -10%;
            right: -8%;
            animation-duration: 25s;
            animation-delay: -8s;
        }

        .orb-3 {
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(0, 180, 255, 0.08) 0%, transparent 70%);
            top: 50%;
            right: 20%;
            animation-duration: 18s;
            animation-delay: -4s;
        }

        @keyframes orbFloat {
            0%   { transform: translate(0, 0) scale(1);    opacity: 1; }
            33%  { transform: translate(30px, -20px) scale(1.05); }
            66%  { transform: translate(-15px, 25px) scale(0.97); }
            100% { transform: translate(0, 0) scale(1);    opacity: 1; }
        }

        /* ── Login Card ────────────────────────────────────────── */
        .login-card {
            background: var(--glass-bg);
            backdrop-filter: blur(24px) saturate(1.4);
            -webkit-backdrop-filter: blur(24px) saturate(1.4);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-card);
            padding: 44px 40px 36px;
            width: 100%;
            max-width: 420px;
            box-shadow:
                0 0 0 1px rgba(255, 255, 255, 0.03) inset,
                0 32px 64px -12px rgba(0, 0, 0, 0.6),
                0 0 80px -20px rgba(0, 212, 255, 0.08);
            animation: cardEntrance 0.55s cubic-bezier(0.22, 1, 0.36, 1) both;
            position: relative;
            overflow: hidden;
        }

        /* Shimmer accent en la parte superior del card */
        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 10%;
            right: 10%;
            height: 1px;
            background: linear-gradient(90deg,
                transparent,
                rgba(0, 212, 255, 0.5),
                rgba(124, 58, 237, 0.3),
                transparent
            );
        }

        @keyframes cardEntrance {
            from {
                opacity: 0;
                transform: translateY(28px) scale(0.97);
                filter: blur(4px);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
                filter: blur(0);
            }
        }

        /* ── Header del Card ───────────────────────────────────── */
        .card-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .logo-wrap {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 72px;
            height: 72px;
            margin-bottom: 16px;
            animation: logoPop 0.6s cubic-bezier(0.22, 1, 0.36, 1) 0.2s both;
        }

        .logo-wrap img {
            width: 64px;
            height: 64px;
            object-fit: contain;
            filter:
                drop-shadow(0 0 16px rgba(0, 212, 255, 0.7))
                drop-shadow(0 0 40px rgba(0, 212, 255, 0.3));
        }

        @keyframes logoPop {
            from { opacity: 0; transform: scale(0.6) rotate(-10deg); }
            to   { opacity: 1; transform: scale(1)   rotate(0deg);   }
        }

        .card-header h1 {
            font-family: var(--font-heading);
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #e2eeff 0%, #00d4ff 60%, #7c3aed 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 6px;
        }

        .card-header p {
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 400;
        }

        /* ── Alerta de Error ───────────────────────────────────── */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 500;
            margin-bottom: 24px;
            animation: alertSlide 0.3s ease both;
            border-left-width: 3px;
            border-left-style: solid;
            line-height: 1.5;
        }

        @keyframes alertSlide {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .alert-danger {
            background: var(--danger-dim);
            border-color: var(--danger);
            color: #fca5a5;
        }

        .alert-warning {
            background: var(--warning-dim);
            border-color: var(--warning);
            color: #fcd34d;
        }

        .alert i {
            margin-top: 1px;
            flex-shrink: 0;
            font-size: 15px;
        }

        /* ── Formulario ────────────────────────────────────────── */
        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper .icon-left {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-subtle);
            font-size: 14px;
            pointer-events: none;
            transition: color var(--transition);
        }

        .input-wrapper input {
            width: 100%;
            background: var(--input-bg);
            border: 1px solid var(--glass-border);
            color: var(--text-main);
            padding: 13px 44px;
            border-radius: var(--radius-input);
            font-family: var(--font-body);
            font-size: 15px;
            outline: none;
            transition:
                border-color var(--transition),
                box-shadow var(--transition),
                background var(--transition);
        }

        .input-wrapper input::placeholder {
            color: var(--text-subtle);
            font-size: 14px;
        }

        .input-wrapper input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 212, 255, 0.12);
            background: rgba(0, 212, 255, 0.03);
        }

        /* Ícono izquierdo se ilumina al foco */
        .input-wrapper:focus-within .icon-left {
            color: var(--primary);
        }

        /* Toggle de visibilidad de contraseña */
        .toggle-pass {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-subtle);
            cursor: pointer;
            padding: 4px;
            font-size: 14px;
            transition: color var(--transition);
            line-height: 1;
        }

        .toggle-pass:hover {
            color: var(--primary);
        }

        /* ── Botón CTA ─────────────────────────────────────────── */
        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, #00d4ff 0%, #0099cc 100%);
            color: #050d1a;
            border: none;
            padding: 14px;
            border-radius: var(--radius-btn);
            cursor: pointer;
            font-family: var(--font-heading);
            font-weight: 700;
            font-size: 16px;
            letter-spacing: 0.2px;
            margin-top: 8px;
            transition:
                transform var(--transition),
                box-shadow var(--transition),
                filter var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
        }

        /* Shimmer interno del botón */
        .btn-login::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.15) 50%, transparent 100%);
            transform: translateX(-100%);
            transition: transform 0.5s ease;
        }

        .btn-login:hover::after {
            transform: translateX(100%);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px -4px rgba(0, 212, 255, 0.4);
            filter: brightness(1.05);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        /* Estado de carga */
        .btn-login.loading {
            pointer-events: none;
            opacity: 0.85;
        }

        .btn-login .btn-text   { display: inline; }
        .btn-login .btn-loader { display: none; }

        .btn-login.loading .btn-text   { display: none; }
        .btn-login.loading .btn-loader { display: inline-flex; align-items: center; gap: 8px; }

        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner {
            width: 18px;
            height: 18px;
            border: 2px solid rgba(5, 13, 26, 0.3);
            border-top-color: #050d1a;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }

        /* ── Divisor ───────────────────────────────────────────── */
        .divider {
            display: flex;
            align-items: center;
            gap: 14px;
            margin: 24px 0 0;
            color: var(--text-subtle);
            font-size: 12px;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--glass-border);
        }

        /* ── Footer ────────────────────────────────────────────── */
        .card-footer {
            margin-top: 20px;
            text-align: center;
            font-size: 12px;
            color: var(--text-subtle);
        }

        .card-footer a {
            color: var(--primary);
            text-decoration: none;
            transition: opacity var(--transition);
        }

        .card-footer a:hover {
            opacity: 0.75;
        }

        /* ── Responsive ────────────────────────────────────────── */
        @media (max-width: 480px) {
            .login-card {
                padding: 36px 24px 28px;
            }

            .logo-wrap {
                width: 60px;
                height: 60px;
            }

            .logo-wrap img {
                width: 54px;
                height: 54px;
            }

            .card-header h1 {
                font-size: 22px;
            }
        }
    </style>
</head>

<body>
    <!-- ── Fondo animado ── -->
    <div class="scene" aria-hidden="true">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
    </div>

    <!-- ── Login Card ── -->
    <main class="login-card" role="main">

        <!-- Header -->
        <div class="card-header">
            <div class="logo-wrap">
                <img src="icons/logo-skale.png"
                     alt="Skale IA Logo"
                     onerror="this.style.display='none'">
            </div>
            <h1>Skale Admin</h1>
            <p>Ingresa tus credenciales para continuar</p>
        </div>

        <!-- Alerta de error (PHP-rendered) -->
        <?php if ($error): ?>
            <div class="alert <?php echo $error_type === 'rate_limited' ? 'alert-warning' : 'alert-danger'; ?>"
                 role="alert">
                <i class="fa-solid <?php echo $error_type === 'rate_limited' ? 'fa-clock' : 'fa-triangle-exclamation'; ?>"></i>
                <span><?php echo htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
            </div>
        <?php endif; ?>

        <!-- Formulario -->
        <form method="POST" id="login-form" novalidate>

            <div class="form-group">
                <label for="username">Usuario</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-user icon-left" aria-hidden="true"></i>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        placeholder="nombre de usuario"
                        required
                        autocomplete="username"
                        autocapitalize="none"
                        spellcheck="false"
                        maxlength="254"
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-lock icon-left" aria-hidden="true"></i>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="••••••••"
                        required
                        autocomplete="current-password"
                        maxlength="254"
                    >
                    <!-- Toggle visibilidad de password — UX mejora -->
                    <button type="button"
                            class="toggle-pass"
                            aria-label="Mostrar contraseña"
                            id="toggle-pass-btn">
                        <i class="fa-solid fa-eye" id="toggle-pass-icon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login" id="submit-btn">
                <span class="btn-text">
                    <i class="fa-solid fa-right-to-bracket"></i>
                    Iniciar Sesión
                </span>
                <span class="btn-loader">
                    <div class="spinner"></div>
                    Verificando...
                </span>
            </button>

        </form>

        <div class="divider">acceso seguro</div>

        <div class="card-footer">
            &copy; <?php echo date('Y'); ?>
            <a href="https://skale.cl" target="_blank" rel="noopener noreferrer">Skale IA</a>.
            Todos los derechos reservados.
        </div>

    </main>

    <script>
    (() => {
        'use strict';

        // ── Toggle visibilidad de password ─────────────────────
        const passInput    = document.getElementById('password');
        const toggleBtn    = document.getElementById('toggle-pass-btn');
        const toggleIcon   = document.getElementById('toggle-pass-icon');

        toggleBtn.addEventListener('click', () => {
            const isHidden = passInput.type === 'password';
            passInput.type  = isHidden ? 'text' : 'password';
            toggleIcon.className = isHidden
                ? 'fa-solid fa-eye-slash'
                : 'fa-solid fa-eye';
            toggleBtn.setAttribute('aria-label',
                isHidden ? 'Ocultar contraseña' : 'Mostrar contraseña');
        });

        // ── Feedback de carga en el botón submit ───────────────
        const form      = document.getElementById('login-form');
        const submitBtn = document.getElementById('submit-btn');

        form.addEventListener('submit', (e) => {
            // Validación básica client-side antes de mostrar loading
            const user = document.getElementById('username').value.trim();
            const pass = passInput.value.trim();

            if (!user || !pass) {
                // Dejar que el backend maneje el error,
                // pero NO mostramos el spinner si está vacío.
                return;
            }

            // Activar estado de carga para feedback inmediato
            submitBtn.classList.add('loading');
        });
    })();
    </script>
</body>

</html>