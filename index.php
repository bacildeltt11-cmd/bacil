<?php
// Kode di bawah digunakan sebagai pintu masuk utama (root) aplikasi, yang langsung mengalihkan (redirect) pengunjung ke halaman login
header("Location: login.php");

// Kode di bawah digunakan untuk menghentikan eksekusi script setelah instruksi redirect dikirimkan
exit;
