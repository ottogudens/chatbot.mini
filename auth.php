<?php
if (session_status() === PHP_SESSION_NONE) {
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
 */
function attempt_login($username, $password, $conn)
{
    $stmt = mysqli_prepare($conn, "SELECT id, password_hash, role, client_id FROM users WHERE username = ?");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($user = mysqli_fetch_assoc($result)) {
        if (password_verify($password, $user['password_hash'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_user'] = $username;
            $_SESSION['role'] = $user['role'] ?? 'client';
            $_SESSION['client_id'] = $user['client_id'];
            return true;
        }
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