<?php
/**
 * Shared utility functions for Manifest Cargo System
 * Prevent code duplication and centralize common operations
 */

/**
 * Ensure only Boss user can access certain pages
 */
function ensureBossAccess() {
    if (!isset($_SESSION['login_rifqy']) || $_SESSION['login_rifqy'] !== 'Boss') {
        $_SESSION['error'] = 'Akses ditolak. Halaman ini hanya untuk Boss.';
        header("Location: login.php");
        exit;
    }
}

/**
 * Format tanggal Indonesia
 */
function tgl_indo($tanggal) {
    $bulan = array (
        1 => 'Januari','Februari','Maret','April','Mei','Juni',
        'Juli','Agustus','September','Oktober','November','Desember'
    );
    $pecahkan = explode('-', $tanggal);
    if (count($pecahkan) < 3) return $tanggal;
    return $pecahkan[2] . ' ' . $bulan[(int)$pecahkan[1]] . ' ' . $pecahkan[0];
}

/**
 * Escape output untuk mencegah XSS
 */
function e($data, $flags = ENT_QUOTES, $encoding = 'UTF-8') {
    return htmlspecialchars($data ?? '', $flags, $encoding);
}

/**
 * Generate CSRF token
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    return true;
}

/**
 * Validate date format (YYYY-MM-DD)
 */
function validate_date($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Sanitize numeric input
 */
function sanitize_number($value, $min = null, $max = null) {
    $value = filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    if ($min !== null && $value < $min) return false;
    if ($max !== null && $value > $max) return false;
    return $value;
}

/**
 * Sanitize string input
 */
function sanitize_string($str, $max_length = null) {
    $str = trim($str);
    $str = strip_tags($str);
    if ($max_length && strlen($str) > $max_length) {
        return false;
    }
    return $str;
}

/**
 * Redirect with message
 */
function redirect_with_message($url, $type, $message) {
    $_SESSION[$type] = $message;
    header("Location: $url");
    exit;
}

/**
 * Get sanitized GET parameter
 */
function get_param($key, $default = '', $sanitize = true) {
    $value = $_GET[$key] ?? $default;
    if ($sanitize && is_string($value)) {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    return $value;
}

/**
 * Get sanitized POST parameter
 */
function post_param($key, $default = '', $sanitize = true) {
    $value = $_POST[$key] ?? $default;
    if ($sanitize && is_string($value)) {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    return $value;
}