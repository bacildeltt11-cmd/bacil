<?php
// Kode di bawah digunakan untuk memuat helper koneksi database MongoDB
require_once 'koneksi_mongodb.php';

// Kode di bawah digunakan untuk memuat fungsi utilitas seperti verifikasi CSRF dan sanitasi input
require_once 'functions.php';

// Kode di bawah digunakan untuk memulai session jika belum berjalan
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kode di bawah digunakan untuk memvalidasi otentikasi login pengguna sebelum memproses API
if (!isset($_SESSION['login_rifqy'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Kode di bawah digunakan untuk menetapkan format respon endpoint ini sebagai JSON
header('Content-Type: application/json');

// Kode di bawah digunakan untuk menangkap aksi (action) yang diminta melalui metode GET atau POST
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Kode di bawah digunakan untuk memvalidasi token CSRF pada operasi yang mengubah data (add/delete) via POST
if (in_array($action, ['add', 'delete']) && $method === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['status' => 'error', 'message' => 'CSRF token invalid']);
        exit;
    }
}

// Kode di bawah digunakan untuk menangani aksi 'get' (mengambil daftar nama master barang)
if ($action == 'get') {
    // Mengambil seluruh data barang dari collection master_barang
    $docs = findDocuments("master_barang", []);
    $names = [];
    foreach ($docs as $d) {
        $names[] = ((array)$d)['nama'];
    }
    
    // Kode di bawah digunakan untuk mengembalikan daftar default jika collection master_barang masih kosong
    if (empty($names)) {
        $names = ["Alpukat","Apel","Bawang Putih","Bawang Goreng","Bombay","Brambang","Cabe","Emping","Garam","Gula","Jipan","Kacang Hijau","Kacang Tanah","Kemiri","Kentang","Kertas","Ketan","Kol","Krupuk","Kunir","Mangga","Plastik","Rempah","Salak","Sawi","Telur","Tomat","Terong","Wortel","Trasi","Kurma","Keluak","Kacang kupas"];
    }
    
    // Mengirimkan hasil daftar nama barang dalam format JSON
    echo json_encode($names);

// Kode di bawah digunakan untuk menangani aksi 'add' (menambahkan master barang baru)
} elseif ($action == 'add') {
    $nama = $_POST['nama'] ?? ($_GET['nama'] ?? '');
    if (!empty($nama)) {
        // Membersihkan string nama barang dari karakter berbahaya
        $nama = sanitize_string($nama);
        // Memeriksa apakah nama barang sudah ada di database untuk mencegah duplikasi
        $exist = findOneDocument("master_barang", ['nama' => $nama]);
        if (!$exist) {
            insertDocument("master_barang", ['nama' => $nama]);
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'exists']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Nama barang required']);
    }

// Kode di bawah digunakan untuk menangani aksi 'delete' (menghapus master barang)
} elseif ($action == 'delete') {
    $nama = $_POST['nama'] ?? ($_GET['nama'] ?? '');
    if (!empty($nama)) {
        // Membersihkan string nama barang
        $nama = sanitize_string($nama);
        // Menghapus data barang dari collection master_barang
        deleteDocument("master_barang", ['nama' => $nama]);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Nama barang required']);
    }

// Kode di bawah digunakan jika parameter aksi yang dikirimkan tidak valid
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}
?>
