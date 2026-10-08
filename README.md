# Project Manifest

Aplikasi web berbasis PHP untuk pengelolaan manifest keberangkatan dan muatan barang.

## Struktur & File Utama:
- **`index.php`**: Redirect otomatis ke halaman login.
- **`login.php` & `logout.php`**: Autentikasi pengguna (masuk dan keluar).
- **`dashboard.php`**: Halaman utama/dashboard sistem.
- **`input_keberangkatan.php`**: Form untuk mencatat data keberangkatan.
- **`input_muatan.php`**: Form untuk mencatat muatan barang.
- **`preview_manifest.php` & `preview_manifest_lama.php`**: Pratinjau dokumen manifest.
- **`cetak_pdf.php`**: Fitur cetak laporan/manifest ke format PDF.
- **`koneksi.php` & `koneksi_mongodb.php`**: Konfigurasi koneksi database (MySQL/MongoDB).
- **`daftar_barang.php`**: Pengelolaan daftar barang.
- **`sidebar.php` & `top_nav.php`**: Komponen navigasi antarmuka (UI).