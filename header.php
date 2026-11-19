<?php
// Kode di bawah digunakan untuk mengirim header keamanan HTTP sebelum output apapun dikirim ke browser

// Kode di bawah digunakan untuk mencegah serangan clickjacking dengan melarang halaman ditampilkan dalam iframe/frame
header("X-Frame-Options: DENY");

// Kode di bawah digunakan untuk mencegah MIME-sniffing agar browser tidak mengeksekusi file selain tipe konten yang dideklarasikan
header("X-Content-Type-Options: nosniff");

// Kode di bawah digunakan untuk mengaktifkan filter XSS (Cross-Site Scripting) bawaan browser dan memblokir render jika terdeteksi serangan
header("X-XSS-Protection: 1; mode=block");

// Kode di bawah digunakan untuk membatasi informasi referrer saat pengguna berpindah halaman demi menjaga privasi
header("Referrer-Policy: strict-origin-when-cross-origin");
?>
