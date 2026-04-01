<?php
if (session_status() === PHP_SESSION_NONE) {
    // SEC: Harden session cookies
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
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
 * If not, and $redirect is true, redirects to login.php
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
 * Attempt to login with username and password
 * Returns true on success, 'rate_limited' if too many attempts, false otherwise
 */
function attempt_login($username, $password, $conn)
{
    // FIX-4: Rate limiting — max 5 failed attempts per 15 minutes
    $now = time();
    $lockout_duration = 900; // 15 minutes in seconds
    $max_attempts = 5;

    $attempts      = $_SESSION['login_attempts'] ?? 0;
    $first_attempt = $_SESSION['login_first_attempt'] ?? $now;

    // Reset counter if lockout window has expired
    if (($now - $first_attempt) > $lockout_duration) {
        $attempts = 0;
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
            // Successful login — reset counter and set session
            $_SESSION['login_attempts'] = 0;
            unset($_SESSION['login_first_attempt']);

            // SEC: Regenerate session ID to prevent session fixation
            session_regenerate_id(true);

            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_user'] = $username;
            $_SESSION['role'] = $user['role'] ?? 'client';
            $_SESSION['client_id'] = $user['client_id'];
            // Generate CSRF token for chat widget
            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            return true;
        }
    }

    // Failed attempt — increment counter
    $_SESSION['login_attempts'] = $attempts + 1;
    if ($attempts === 0) {
        $_SESSION['login_first_attempt'] = $now;
    }
    return false;
}

/**
 * Logout and destroy session
 */
function logout()
{
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

// Handle logout action if requested
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logout();
}
?>