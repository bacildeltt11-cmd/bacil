<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Kosongkan semua data session di memory
$_SESSION = [];

// 2. Hapus cookie session dari browser jika ada
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// 3. Hancurkan session di server
session_destroy();

// 4. Cegah caching di browser & redirect ke login.php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Location: login.php");
exit;
?>