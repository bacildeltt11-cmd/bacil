<?php
/**
 * File Konfigurasi Sistem Manifest Cargo CV. MANUNGGAL
 * Berisi pengaturan koneksi database MongoDB, identitas aplikasi, dan keamanan.
 */

// Kode di bawah digunakan untuk menentukan URI koneksi MongoDB Atlas (diambil dari environment variable atau string default)
$mongodb_uri = getenv('MONGODB_URI') ?: 'mongodb+srv://bacildeltt11_db_user:MjZXWWtI5XLRhJci@cluster0.c50ple3.mongodb.net/';

// Kode di bawah digunakan untuk menentukan nama database MongoDB yang digunakan oleh aplikasi
$database = getenv('MONGODB_DB') ?: 'cargo_manifest';

// Kode di bawah digunakan untuk menyimpan nama aplikasi yang ditampilkan pada judul dan header
$app_name = 'CV. MANUNGGAL - Cargo Manifest System';

// Kode di bawah digunakan untuk versi aplikasi
$version = '1.0.0';

// Kode di bawah digunakan untuk menentukan masa berlaku token keamanan CSRF dalam hitungan detik (3600 detik = 1 jam)
$csrf_token_lifetime = 3600;
