<?php
session_start();

// Pengecekan login (allow read-only for non-Boss too, but require auth)
if (!isset($_SESSION['login_rifqy'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

include 'koneksi_mongodb.php';
include 'functions.php';

header('Content-Type: application/json');

// Allow CORS if needed (same origin)
// header('Access-Control-Allow-Origin: *');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Verify CSRF token for state-changing operations
if (in_array($action, ['add', 'delete']) && $method === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['status' => 'error', 'message' => 'CSRF token invalid']);
        exit;
    }
} elseif (in_array($action, ['add', 'delete']) && $method === 'GET') {
    // Legacy GET support - less secure, but keep for backward compatibility
    // Consider removing in production
    // Optionally check a token in query string
}

if ($action == 'get') {
    $docs = findDocuments("master_barang", []);
    $names = [];
    foreach ($docs as $d) {
        $names[] = ((array)$d)['nama'];
    }
    
    // Jika masih kosong, kembalikan daftar default
    if (empty($names)) {
        $names = ["Alpukat","Apel","Bawang Putih","Bawang Goreng","Bombay","Brambang","Cabe","Emping","Garam","Gula","Jipan","Kacang Hijau","Kacang Tanah","Kemiri","Kentang","Kertas","Ketan","Kol","Krupuk","Kunir","Mangga","Plastik","Rempah","Salak","Sawi","Telur","Tomat","Terong","Wortel","Trasi","Kurma","Keluak","Kacang kupas"];
    }
    
    echo json_encode($names);
} elseif ($action == 'add') {
    $nama = $_POST['nama'] ?? ($_GET['nama'] ?? '');
    if (!empty($nama)) {
        $nama = sanitize_string($nama);
        $exist = findOneDocument("master_barang", ['nama' => $nama]);
        if (!$exist) {
            insertDocument("master_barang", ['nama' => $nama]);
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'exists']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Nama barangrequired']);
    }
} elseif ($action == 'delete') {
    $nama = $_POST['nama'] ?? ($_GET['nama'] ?? '');
    if (!empty($nama)) {
        $nama = sanitize_string($nama);
        deleteDocument("master_barang", ['nama' => $nama]);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Nama barang required']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}
?>
