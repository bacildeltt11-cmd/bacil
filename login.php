<?php
// Kode di bawah digunakan untuk memuat file koneksi MongoDB dan file fungsi pembantu
include_once "koneksi_mongodb.php";
include_once "functions.php";

// Kode di bawah digunakan untuk memulai session yang aman dengan proteksi HttpOnly dan Strict Mode
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'use_strict_mode' => true
    ]);
}

// Kode di bawah digunakan untuk membuat token keamanan CSRF acak jika belum tersedia di session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Kode di bawah digunakan untuk memeriksa apakah user sudah login; jika sudah, langsung diarahkan ke halaman dashboard
if(isset($_SESSION['login_rifqy'])){
    header("Location: dashboard.php");
    exit;
}

// Variabel penampung pesan error jika proses otentikasi login gagal
$error_msg = '';

// Kode di bawah digunakan untuk memproses form saat tombol login ditekan (metode POST)
if(isset($_POST['login'])){
    // Memverifikasi kecocokan token CSRF demi keamanan dari serangan form eksternal
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("CSRF token validation failed");
    }
    
    // Membersihkan dan mengambil nilai username dan password yang diinputkan user
    $username = sanitize_string($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Memvalidasi agar username dan password tidak boleh kosong
    if (empty($username) || empty($password)) {
        $error_msg = "Username dan Password harus diisi!";
    } else {
        // Mencari data pengguna di MongoDB collection 'pengguna' berdasarkan username
        $data = findOneDocument("pengguna", [
            "username" => $username
        ]);

        // Memverifikasi kecocokan password yang diinput dengan hash password tersimpan (Bcrypt)
        if($data && password_verify($password, $data->password)){
            // Memperbarui session ID untuk mencegah serangan session fixation
            session_regenerate_id(true);
            // Menyimpan nama role pengguna (Boss / Operator) ke dalam session
            $_SESSION['login_rifqy'] = $data->nama_pengguna;
            // Mengalihkan halaman ke dashboard
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
    <!-- Memuat file CSS utama untuk styling elemen form dan tema warna -->
    <link rel="stylesheet" href="style.css">
    <style>
        /* Pengaturan layout responsif layar penuh untuk halaman login */
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

<!-- Kode di bawah adalah kotak kontainer kartu login -->
<div class="login-container">
    <!-- Bagian logo dan identitas aplikasi -->
    <div class="logo-area">
        <h1>CV. MANUNGGAL</h1>
        <p>Logistics & Cargo Manifest System</p>
    </div>

    <!-- Menampilkan kotak peringatan merah jika login gagal -->
    <?php
    if(!empty($error_msg)){
        echo "<div class='alert'>$error_msg</div>";
    }
    ?>

    <!-- Form login untuk menginput username dan password -->
    <form method="post">
        <!-- Input tersembunyi token CSRF untuk validasi keamanan request -->
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
        
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" class="form-control" placeholder="Masukkan username..." required>
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" class="form-control" placeholder="Masukkan password..." required>
        </div>

        <!-- Tombol submit untuk memproses verifikasi login -->
        <button type="submit" name="login" class="btn btn-primary btn-login">Login Sekarang</button>
    </form>

    <div class="footer-text">
        &copy; 2026 CV. MANUNGGAL - Administrator System
    </div>
</div>

</body>
</html>
