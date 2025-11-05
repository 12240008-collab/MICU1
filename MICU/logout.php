<?php
session_start();
require_once 'config.php';

// Remember user type before destroying session
$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'customer';

// Clear all session data
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-42000, '/');
}

// Destroy the session
session_destroy();

// Clear remember me cookie if exists
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time()-42000, '/');
}

// Redirect based on user type
if ($user_type === 'partner') {
    header("Location: " . APP_URL . "/partner/login.php");
} else {
    header("Location: " . APP_URL . "/login.php");
}
exit();
?>