<?php
/**
 * Vercel Serverless Function Entry Point for Manifest Cargo System
 */

// Set working directory to project root directory
chdir(dirname(__DIR__));

// Parse URI path
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$uriPath = parse_url($requestUri, PHP_URL_PATH);

// Strip leading slash
$path = ltrim($uriPath, '/');

// Default to index.php if requesting root
if ($path === '' || $path === '/') {
    $path = 'index.php';
}

$rootPath = realpath(__DIR__ . '/..');
$targetFile = __DIR__ . '/../' . $path;
$realPath = realpath($targetFile);

// Verify file exists, is inside root directory, and is a PHP file
if ($realPath && strpos($realPath, $rootPath) === 0 && is_file($realPath) && pathinfo($realPath, PATHINFO_EXTENSION) === 'php') {
    require $realPath;
} else {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="id"><head><title>404 Not Found</title><style>body{font-family:sans-serif;text-align:center;padding:50px;}</style></head><body><h1>404 - Halaman Tidak Ditemukan</h1><p><a href="/">Kembali ke Beranda</a></p></body></html>';
}
