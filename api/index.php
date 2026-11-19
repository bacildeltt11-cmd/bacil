<?php
/**
 * Entry Point Serverless Vercel untuk Sistem Cargo Manifest
 * Berfungsi sebagai router utama yang meneruskan setiap request URL ke file PHP tujuan.
 */

// Kode di bawah digunakan untuk mengubah direktori kerja aktif ke root direktori proyek
chdir(dirname(__DIR__));

// Kode di bawah digunakan untuk memuat koneksi database MongoDB dan fungsi-fungsi utilitas
require_once 'koneksi_mongodb.php';
require_once 'functions.php';

// Kode di bawah digunakan untuk mengurai (parse) path URI dari URL yang diminta pengunjung
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$uriPath = parse_url($requestUri, PHP_URL_PATH);

// Kode di bawah digunakan untuk membuang tanda garis miring (slash) di awal path
$path = ltrim($uriPath, '/');

// Kode di bawah digunakan untuk menetapkan index.php sebagai file default jika URL yang diakses adalah root ('/')
if ($path === '' || $path === '/') {
    $path = 'index.php';
}

// Menentukan path absolut folder root dan file target yang diminta
$rootPath = realpath(__DIR__ . '/..');
$targetFile = __DIR__ . '/../' . $path;
$realPath = realpath($targetFile);

// Kode di bawah digunakan untuk memvalidasi keamanan file: memastikan file ada di dalam root dan berekstensi .php
if ($realPath && strpos($realPath, $rootPath) === 0 && is_file($realPath) && pathinfo($realPath, PATHINFO_EXTENSION) === 'php') {
    // Memuat dan mengeksekusi file PHP yang diminta
    require $realPath;
} else {
    // Menampilkan halaman error 404 jika file tidak ditemukan atau mencoba mengakses direktori terlarang
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="id"><head><title>404 Not Found</title><style>body{font-family:sans-serif;text-align:center;padding:50px;}</style></head><body><h1>404 - Halaman Tidak Ditemukan</h1><p><a href="/">Kembali ke Beranda</a></p></body></html>';
}
