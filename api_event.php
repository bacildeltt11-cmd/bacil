<?php
// Kode di bawah digunakan untuk memulai output buffering agar tidak ada teks atau spasi yang keluar sebelum header JSON
ob_start();
// Menonaktifkan laporan error bawaan PHP ke output agar tidak merusak format respon JSON
error_reporting(0);

// Kode di bawah digunakan untuk menghubungkan skrip ke database MongoDB dan memuat fungsi helper
require_once 'koneksi_mongodb.php';
require_once 'functions.php';

// Kode di bawah digunakan untuk memulai session jika belum aktif
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kode di bawah digunakan untuk menetapkan header konten respon sebagai JSON dengan encoding UTF-8
header('Content-Type: application/json; charset=utf-8');

// Kode di bawah adalah fungsi helper untuk membersihkan buffer, mengatur status HTTP, dan mengirim data JSON
function sendJson($data, $code = 200) {
    ob_end_clean();
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// Kode di bawah digunakan untuk memverifikasi apakah pengguna sudah login ke sistem
if (!isset($_SESSION['login_rifqy'])) {
    sendJson(['error' => 'Unauthorized'], 401);
}

// Kode di bawah digunakan untuk membatasi aksi API kalender jadwal hanya untuk pengguna dengan role 'Boss'
if ($_SESSION['login_rifqy'] !== 'Boss') {
    sendJson(['error' => 'Forbidden'], 403);
}

// Kode di bawah digunakan untuk mendeteksi metode HTTP (GET atau POST) yang dikirimkan oleh klien
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    // Penanganan permintaan GET: Mengambil detail satu jadwal manifest berdasarkan ID
    case 'GET':
        $id = $_GET['id'] ?? null;
        if (!$id) {
            sendJson(['error' => 'ID required'], 400);
        }

        try {
            // Mencari dokumen manifest di MongoDB berdasarkan ObjectId
            $event = findOneDocument("manifest", ['_id' => new MongoDB\BSON\ObjectId($id)]);
            if ($event) {
                $arr = (array)$event;
                // Mengirimkan detail data jadwal ke klien
                sendJson([
                    'success' => true,
                    'data' => [
                        'id' => (string)$arr['_id'],
                        'kapal' => $arr['kapal'] ?? '',
                        'tanggal' => $arr['tanggal'] ?? '',
                        'tujuan' => $arr['tujuan'] ?? '',
                        'jenis' => $arr['jenis'] ?? '',
                        'nopol' => $arr['nopol'] ?? '',
                        'jam' => $arr['jam'] ?? '',
                        'status' => $arr['status'] ?? 'Menunggu'
                    ]
                ]);
            } else {
                sendJson(['error' => 'Event not found'], 404);
            }
        } catch (Exception $e) {
            sendJson(['error' => 'Server error'], 500);
        }
        break;

    // Penanganan permintaan POST: Menambah, mengedit, atau menghapus jadwal manifest
    case 'POST':
        // Membaca payload input berupa JSON dari request body
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) $input = $_POST;

        // Memvalidasi token CSRF untuk mencegah serangan Cross-Site Request Forgery
        if (!verify_csrf_token($input['csrf_token'] ?? '')) {
            sendJson(['error' => 'CSRF token invalid'], 403);
        }

        $action = $input['action'] ?? '';

        // Penanganan aksi tambah atau edit jadwal
        if ($action === 'add_event' || $action === 'edit_event') {
            $isEdit = ($action === 'edit_event');
            $id = $isEdit ? ($input['event_id'] ?? null) : null;

            if ($isEdit && !$id) {
                sendJson(['error' => 'Event ID required'], 400);
            }

            // Sanitasi dan validasi seluruh data input dari form kalender
            $kapal = sanitize_string($input['kapal'] ?? '');
            $tanggal = $input['tanggal'] ?? '';
            $tujuan = sanitize_string($input['tujuan'] ?? '');
            $jenis = sanitize_string($input['jenis'] ?? '');
            $nopol = sanitize_string($input['nopol'] ?? '');
            $jam = $input['jam'] ?? '';

            $errors = [];
            if (empty($kapal)) $errors[] = "Nama kapal harus diisi";
            if (empty($tanggal) || !validate_date($tanggal)) $errors[] = "Format tanggal tidak valid";
            if (empty($tujuan)) $errors[] = "Tujuan harus diisi";
            if (empty($jenis)) $errors[] = "Jenis kendaraan harus diisi";
            if (empty($nopol)) $errors[] = "Nomor polisi harus diisi";
            if (empty($jam)) $errors[] = "Jam berangkat harus diisi";

            if (empty($errors)) {
                $data = [
                    "kapal" => $kapal,
                    "tanggal" => $tanggal,
                    "tujuan" => $tujuan,
                    "jenis" => $jenis,
                    "nopol" => $nopol,
                    "jam" => $jam,
                    "created_by" => "Boss"
                ];

                // Jika aksi adalah edit, perbarui dokumen manifest yang ada
                if ($isEdit) {
                    $result = updateDocument("manifest", ['_id' => new MongoDB\BSON\ObjectId($id)], $data);
                    sendJson(['success' => true, 'message' => 'Jadwal berhasil diperbarui']);
                // Jika aksi adalah tambah baru, simpan sebagai dokumen manifest baru di MongoDB
                } else {
                    $id = insertDocument("manifest", $data);
                    if ($id) {
                        sendJson(['success' => true, 'message' => 'Jadwal berhasil ditambahkan', 'id' => $id]);
                    } else {
                        sendJson(['error' => 'Gagal menambahkan jadwal'], 500);
                    }
                }
            } else {
                sendJson(['error' => implode('<br>', $errors)], 400);
            }
        }

        // Penanganan aksi hapus jadwal manifest
        if ($action === 'delete_event') {
            $event_id = $input['event_id'] ?? null;
            if (!$event_id) {
                sendJson(['error' => 'Event ID required'], 400);
            }
            $result = deleteDocument("manifest", ['_id' => new MongoDB\BSON\ObjectId($event_id)]);
            sendJson(['success' => true, 'message' => 'Jadwal berhasil dihapus']);
        }
        break;

    // Respon jika metode HTTP tidak diizinkan
    default:
        sendJson(['error' => 'Method not allowed'], 405);
}
?>
