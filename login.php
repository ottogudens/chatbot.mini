<?php
require_once 'db.php';
require_once 'auth.php';

// Check if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admin.php");
    exit;
}

$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // SEC FIX: Sanitizar inputs antes de pasarlos a attempt_login().
    // trim() elimina espacios/tabs accidentales; mb_substr() previene inputs
    // excesivamente largos que podrían causar timing attacks o errores de BD.
    $user = mb_substr(trim($_POST['username'] ?? ''), 0, 254);
    $pass = mb_substr(trim($_POST['password'] ?? ''), 0, 1024);

    if (empty($user) || empty($pass)) {
        $error = "Usuario y contraseña son requeridos.";
    } else {
        $login_result = attempt_login($user, $pass, $conn);
        if ($login_result === true) {
            header("Location: admin.php");
            exit;
        } elseif (is_array($login_result) && ($login_result['error'] ?? '') === 'rate_limited') {
            $error = "Demasiados intentos fallidos. Intenta nuevamente en {$login_result['remaining']} minuto(s).";
        } else {
            $error = "Usuario o contraseña incorrectos.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkaleBot - Iniciar Sesión</title>
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-color: #080f1e;
            --glass-bg: rgba(12, 26, 48, 0.8);
            --glass-border: rgba(0, 212, 255, 0.15);
            --primary: #00d4ff;
            --primary-hover: #00b8e6;
            --danger: #ef4444;
            --text-main: #e8f4ff;
            --text-muted: #8ab3cc;
            --input-bg: rgba(8, 15, 30, 0.7);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow: hidden;
        }

        .glass-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: radial-gradient(circle at 15% 50%, rgba(0, 212, 255, 0.1), transparent 30%),
                radial-gradient(circle at 85% 30%, rgba(30, 74, 122, 0.15), transparent 35%);
        }

        .login-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header .icon {
            font-size: 50px;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 700;
        }

        .header p {
            color: var(--text-muted);
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-muted);
            font-size: 14px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .form-group input {
            width: 100%;
            background: var(--input-bg);
            border: 1px solid var(--glass-border);
            color: white;
            padding: 12px 12px 12px 40px;
            border-radius: 10px;
            font-family: 'Inter', sans-serif;
            outline: none;
            transition: all 0.2s;
        }

        .form-group input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(0, 212, 255, 0.2);
        }

        .btn {
            width: 100%;
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.2s;
            margin-top: 10px;
        }

        .btn:hover {
            background: var(--primary-hover);
        }

        .error-msg {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
            padding: 10px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-left: 4px solid var(--danger);
        }

        .footer {
            margin-top: 25px;
            text-align: center;
            font-size: 12px;
            color: var(--text-muted);
        }

        .footer a {
            color: var(--primary);
            text-decoration: none;
        }
    </style>
</head>

<body>
    <div class="glass-bg"></div>

    <div class="login-card">
        <div class="header">
            <div class="icon">
                <img src="icons/logo-skale.png" alt="Skale" style="width:56px;height:56px;object-fit:contain;filter:drop-shadow(0 0 12px rgba(0,212,255,0.6));">
            </div>
            <h1>Skale Admin</h1>
            <p>Ingresa tus credenciales para continuar</p>
        </div>

        <?php if ($error): ?>
            <div class="error-msg">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <!-- SEC FIX: ENT_QUOTES|ENT_SUBSTITUTE asegura protección XSS
                     incluso dentro de atributos HTML (comillas simples y dobles). -->
                <?php echo htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Nombre de Usuario</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-user"></i>
                    <input type="text" name="username" placeholder="Ej. admin" required autocomplete="username">
                </div>
            </div>
            <div class="form-group">
                <label>Contraseña</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" name="password" placeholder="••••••••" required
                        autocomplete="current-password">
                </div>
            </div>
            <button type="submit" class="btn">Iniciar Sesión</button>
        </form>

        <div class="footer">
            &copy; <?php echo date('Y'); ?> <a href="https://skale.cl" target="_blank">Skale IA</a>. Todos los derechos reservados.
        </div>
    </div>
</body>

</html>