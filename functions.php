<?php
// Kode di bawah digunakan untuk memastikan fungsi-fungsi di file ini hanya dimuat satu kali (mencegah error redeclare function)
if (defined('FUNCTIONS_LOADED')) {
    return;
}
define('FUNCTIONS_LOADED', true);

/**
 * File Kumpulan Fungsi Utilitas Global
 * Berisi fungsi bantuan untuk otentikasi role, format tanggal, keamanan CSRF, sanitasi input, dan pencegahan XSS.
 */

// Kode di bawah digunakan untuk memvalidasi bahwa hanya pengguna dengan role 'Boss' yang dapat mengakses halaman tertentu
function ensureBossAccess() {
    // Memeriksa apakah session login bukan Boss
    if (!isset($_SESSION['login_rifqy']) || $_SESSION['login_rifqy'] !== 'Boss') {
        // Menyimpan pesan peringatan akses ditolak ke dalam session
        $_SESSION['error'] = 'Akses ditolak. Halaman ini hanya untuk Boss.';
        // Mengalihkan pengguna kembali ke halaman login
        header("Location: login.php");
        exit;
    }
}

// Kode di bawah digunakan untuk mengubah format tanggal baku (YYYY-MM-DD) menjadi format bahasa Indonesia (contoh: 19 November 2026)
function tgl_indo($tanggal) {
    // Array pemetaan nama-nama bulan dalam bahasa Indonesia
    $bulan = array (
        1 => 'Januari','Februari','Maret','April','Mei','Juni',
        'Juli','Agustus','September','Oktober','November','Desember'
    );
    // Memecah string tanggal berdasarkan tanda hubung '-'
    $pecahkan = explode('-', $tanggal);
    if (count($pecahkan) < 3) return $tanggal;
    // Menggabungkan kembali tanggal, nama bulan Indonesia, dan tahun
    return $pecahkan[2] . ' ' . $bulan[(int)$pecahkan[1]] . ' ' . $pecahkan[0];
}

// Kode di bawah digunakan untuk melakukan sanitasi output HTML (escaping) guna mencegah celah keamanan Cross-Site Scripting (XSS)
function e($data, $flags = ENT_QUOTES, $encoding = 'UTF-8') {
    return htmlspecialchars($data ?? '', $flags, $encoding);
}

// Kode di bawah digunakan untuk membuat (generate) token keamanan CSRF baru dan menyimpannya di session serta cookie
function generate_csrf_token() {
    // Jika token CSRF belum ada di session, buat atau ambil dari cookie
    if (empty($_SESSION['csrf_token'])) {
        if (!empty($_COOKIE['csrf_token'])) {
            $_SESSION['csrf_token'] = $_COOKIE['csrf_token'];
        } else {
            // Membuat token acak 32-byte yang aman secara kriptografi
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }
    // Mengirim cookie token CSRF dengan perlindungan httponly dan samesite
    if (!headers_sent() && (empty($_COOKIE['csrf_token']) || $_COOKIE['csrf_token'] !== $_SESSION['csrf_token'])) {
        $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
                   (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        setcookie('csrf_token', $_SESSION['csrf_token'], [
            'expires' => time() + 3600,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    return $_SESSION['csrf_token'];
}

// Kode di bawah digunakan untuk memverifikasi kecocokan token CSRF yang dikirimkan oleh form atau request API
function verify_csrf_token($token) {
    // Jika token kosong, verifikasi gagal
    if (empty($token)) return false;
    // Memeriksa kecocokan token dengan session menggunakan perbandingan timing-safe
    if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        return true;
    }
    // Memeriksa kecocokan token dengan cookie
    if (isset($_COOKIE['csrf_token']) && hash_equals($_COOKIE['csrf_token'], $token)) {
        return true;
    }
    return false;
}

// Kode di bawah digunakan untuk memvalidasi format tanggal agar sesuai dengan pola yang diinginkan (default Y-m-d)
function validate_date($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

// Kode di bawah digunakan untuk menyaring (sanitasi) input angka dan memvalidasi batas nilai minimum/maksimum
function sanitize_number($value, $min = null, $max = null) {
    $value = filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    if ($min !== null && $value < $min) return false;
    if ($max !== null && $value > $max) return false;
    return $value;
}

// Kode di bawah digunakan untuk membersihkan string dari tag HTML berbahaya dan spasi berlebih
function sanitize_string($str, $max_length = null) {
    $str = trim($str);
    $str = strip_tags($str);
    // Memeriksa panjang string jika batas panjang ditentukan
    if ($max_length && strlen($str) > $max_length) {
        return false;
    }
    return $str;
}

// Kode di bawah digunakan untuk mengalihkan halaman (redirect) sambil membawa pesan status di dalam session
function redirect_with_message($url, $type, $message) {
    $_SESSION[$type] = $message;
    header("Location: $url");
    exit;
}

// Kode di bawah digunakan untuk mengambil dan mengamankan nilai parameter query URL ($_GET)
function get_param($key, $default = '', $sanitize = true) {
    $value = $_GET[$key] ?? $default;
    if ($sanitize && is_string($value)) {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    return $value;
}

// Kode di bawah digunakan untuk mengambil dan mengamankan nilai input form ($_POST)
function post_param($key, $default = '', $sanitize = true) {
    $value = $_POST[$key] ?? $default;
    if ($sanitize && is_string($value)) {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    return $value;
}
