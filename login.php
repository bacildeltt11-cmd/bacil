<?php
include_once "koneksi_mongodb.php";
include_once "functions.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'use_strict_mode' => true
    ]);
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Jika sudah login, langsung lempar ke halaman input
if(isset($_SESSION['login_rifqy'])){
    header("Location: dashboard.php");
    exit;
}

$error_msg = '';

if(isset($_POST['login'])){
    // Verify CSRF token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("CSRF token validation failed");
    }
    
    $username = sanitize_string($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validate inputs
    if (empty($username) || empty($password)) {
        $error_msg = "Username dan Password harus diisi!";
    } else {
        // Sesuaikan dengan collection pengguna di MongoDB
        $data = findOneDocument("pengguna", [
            "username" => $username
        ]);

        if($data && password_verify($password, $data->password)){
            session_regenerate_id(true);
            $_SESSION['login_rifqy'] = $data->nama_pengguna;
            header("Location: dashboard.php");
            exit;
        } else {
            $error_msg = "Username atau Password Salah!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CV. MANUNGGAL</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, var(--light-blue), #cce0ff);
            padding: 20px 16px;
            margin: 0;
            box-sizing: border-box;
        }
        .login-container {
            background: var(--white);
            padding: 36px 28px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.08);
            width: 100%;
            max-width: 400px;
            text-align: center;
            border-top: 5px solid var(--primary-blue);
            box-sizing: border-box;
        }
        .logo-area { margin-bottom: 26px; }
        .logo-area h1 { color: var(--primary-blue); font-size: 26px; letter-spacing: 1.5px; font-weight: 800; margin: 0; }
        .logo-area p { color: var(--gray); font-size: 13px; margin-top: 6px; }
        .btn-login { width: 100%; min-height: 48px; font-size: 16px; margin-top: 10px; border-radius: 10px; font-weight: 700; }
        .footer-text { margin-top: 25px; font-size: 12px; color: #888; }
        .alert { background: #ffeded; color: var(--danger); padding: 12px 14px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; border: 1px solid var(--danger); }
        @media (max-width: 480px) {
            .login-container { padding: 28px 20px; border-radius: 16px; }
            .logo-area h1 { font-size: 22px; }
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="logo-area">
        <h1>CV. MANUNGGAL</h1>
        <p>Logistics & Cargo Manifest System</p>
    </div>

    <?php
    if(!empty($error_msg)){
        echo "<div class='alert'>$error_msg</div>";
    }
    ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" class="form-control" placeholder="Masukkan username..." required>
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" class="form-control" placeholder="Masukkan password..." required>
        </div>

        <button type="submit" name="login" class="btn btn-primary btn-login">Login Sekarang</button>
    </form>

    <div class="footer-text">
        &copy; 2026 CV. MANUNGGAL - Administrator System
    </div>
</div>

</body>
</html>