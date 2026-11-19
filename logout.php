<?php
// Kode di bawah digunakan untuk memulai session jika belum aktif, agar data session bisa dibersihkan
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kode di bawah digunakan untuk mengosongkan seluruh variabel data session yang tersimpan di memori server
$_SESSION = [];

// Kode di bawah digunakan untuk menghapus cookie session dari browser klien dengan menyetel masa kedaluwarsa ke masa lalu
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

// Kode di bawah digunakan untuk menghancurkan (destroy) seluruh session pengguna secara permanen di server
session_destroy();

// Kode di bawah digunakan untuk mengirim header larangan cache agar halaman yang memerlukan login tidak bisa diakses lewat tombol Back browser
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Kode di bawah digunakan untuk mengalihkan (redirect) pengguna yang sudah logout kembali ke halaman login
header("Location: login.php");

// Kode di bawah digunakan untuk menghentikan eksekusi script setelah proses logout selesai
exit;
?>
