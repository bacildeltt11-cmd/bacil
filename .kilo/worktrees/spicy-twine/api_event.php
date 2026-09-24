<?php
// Jangan ada output apapun sebelum JSON
ob_start();
error_reporting(0);

session_start();
include 'koneksi_mongodb.php';
include 'functions.php';

header('Content-Type: application/json; charset=utf-8');

function sendJson($data, $code = 200) {
    ob_end_clean();
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// Verify login
if (!isset($_SESSION['login_rifqy'])) {
    sendJson(['error' => 'Unauthorized'], 401);
}

// Only Boss can perform CRUD
if ($_SESSION['login_rifqy'] !== 'Boss') {
    sendJson(['error' => 'Forbidden'], 403);
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $id = $_GET['id'] ?? null;
        if (!$id) {
            sendJson(['error' => 'ID required'], 400);
        }

        try {
            $event = findOneDocument("manifest", ['_id' => new MongoDB\BSON\ObjectId($id)]);
            if ($event) {
                $arr = (array)$event;
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

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) $input = $_POST;

        if (!verify_csrf_token($input['csrf_token'] ?? '')) {
            sendJson(['error' => 'CSRF token invalid'], 403);
        }

        $action = $input['action'] ?? '';

        if ($action === 'add_event' || $action === 'edit_event') {
            $isEdit = ($action === 'edit_event');
            $id = $isEdit ? ($input['event_id'] ?? null) : null;

            if ($isEdit && !$id) {
                sendJson(['error' => 'Event ID required'], 400);
            }

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

                if ($isEdit) {
                    $result = updateDocument("manifest", ['_id' => new MongoDB\BSON\ObjectId($id)], $data);
                    sendJson(['success' => true, 'message' => 'Jadwal berhasil diperbarui']);
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

        if ($action === 'delete_event') {
            $event_id = $input['event_id'] ?? null;
            if (!$event_id) {
                sendJson(['error' => 'Event ID required'], 400);
            }
            $result = deleteDocument("manifest", ['_id' => new MongoDB\BSON\ObjectId($event_id)]);
            sendJson(['success' => true, 'message' => 'Jadwal berhasil dihapus']);
        }
        break;

    default:
        sendJson(['error' => 'Method not allowed'], 405);
}