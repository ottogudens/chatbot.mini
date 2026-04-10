<?php
if (session_status() === PHP_SESSION_NONE) {
    // SEC: Harden session cookies
    ini_set('session.cookie_httponly', 1);
    // SEC FIX: SameSite=Strict es más seguro que Lax para una SPA de gestión.
    // Previene que cookies se envíen en navegaciones cross-site (CSRF de primer nivel).
    // Railway sirve el app desde un solo dominio, Strict no rompe flujos legítimos.
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    // Detect HTTPS: direct or behind reverse proxy (Railway, Cloudflare, etc.)
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
             || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
             || (!empty($_SERVER['HTTP_X_FORWARDED_PORT']) && $_SERVER['HTTP_X_FORWARDED_PORT'] === '443');
    if ($is_https) {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

/**
 * Checks if the user is logged in.
 * If not, and $redirect is true, redirects to login.php.
 */
function check_auth($redirect = true)
{
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        if ($redirect) {
            header("Location: login.php");
            exit;
        }
        return false;
    }
    return true;
}

/**
 * Attempt to login with username and password.
 * Returns true on success, array with error on rate limit, false otherwise.
 */
function attempt_login($username, $password, $conn)
{
    // Rate limiting — max 5 failed attempts per 15 minutes
    $now              = time();
    $lockout_duration = 900; // 15 minutes
    $max_attempts     = 5;

    $attempts      = $_SESSION['login_attempts']      ?? 0;
    $first_attempt = $_SESSION['login_first_attempt'] ?? $now;

    // Reset counter if lockout window has expired
    if (($now - $first_attempt) > $lockout_duration) {
        $attempts      = 0;
        $first_attempt = $now;
        $_SESSION['login_first_attempt'] = $first_attempt;
    }

    if ($attempts >= $max_attempts) {
        $remaining = $lockout_duration - ($now - $first_attempt);
        return ['error' => 'rate_limited', 'remaining' => ceil($remaining / 60)];
    }

    $stmt = mysqli_prepare($conn, "SELECT id, password_hash, role, client_id FROM users WHERE username = ?");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($user = mysqli_fetch_assoc($result)) {
        if (password_verify($password, $user['password_hash'])) {
            // ── Login exitoso ──────────────────────────────────────────────
            $_SESSION['login_attempts'] = 0;
            unset($_SESSION['login_first_attempt']);

            // SEC: Regenerar ID para prevenir session fixation
            session_regenerate_id(true);

            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id']        = $user['id'];
            $_SESSION['admin_user']      = $username;
            $_SESSION['role']            = $user['role'] ?? 'client';
            $_SESSION['client_id']       = $user['client_id'];

            // SEC FIX: SIEMPRE rotar el CSRF token en cada login exitoso.
            // Reutilizar el token pre-autenticación permitiría "CSRF token fixation":
            // un atacante que cargó la página antes del login conocería el token.
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            return true;
        }
    }

    // Intento fallido — incrementar contador
    $_SESSION['login_attempts'] = $attempts + 1;
    if ($attempts === 0) {
        $_SESSION['login_first_attempt'] = $now;
    }
    return false;
}

/**
 * Logout: destruye la sesión del servidor Y invalida la cookie del navegador.
 *
 * FIX: La versión anterior solo llamaba session_destroy(), que borra los datos
 * del servidor pero deja la cookie PHPSESSID activa en el navegador.
 * Si los datos de sesión se restauran (ej. fallo de Railway/Redis), la cookie
 * vieja podría reutilizarse. Enviamos explícitamente la cookie expirada.
 */
function logout()
{
    // 1. Limpiar variables en memoria
    $_SESSION = [];

    // 2. Expirar la cookie de sesión en el navegador de inmediato
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,     // Timestamp pasado = expiración inmediata
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    // 3. Destruir los datos de sesión en el servidor
    session_destroy();

    header("Location: login.php");
    exit;
}

// SEC FIX: Validar que la sesión existe y pertenece a un usuario autenticado
// ANTES de procesarla. Esto previene que un request GET anónimo llegue a logout().
// Nota arquitectónica: para protección CSRF total en logout, el botón de logout
// en admin.php debería enviar un POST con csrf_token (ver admin.php).
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    if (isset($_SESSION['admin_logged_in'])) {
        logout();
    } else {
        header("Location: login.php");
        exit;
    }
}