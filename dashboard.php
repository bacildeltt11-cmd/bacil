<?php
// Security headers must be sent before any output
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

require_once 'koneksi_mongodb.php';
require_once 'functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Jika belum login (opsional, jika dashboard.php butuh login)
if(!isset($_SESSION['login_rifqy'])){
    header("Location: login.php");
    exit;
}

$nama_user = $_SESSION['login_rifqy'];
$is_boss = isset($_SESSION['login_rifqy']) && $_SESSION['login_rifqy'] === 'Boss';

// Handle CRUD actions for Boss
if ($_SESSION['login_rifqy'] === 'Boss' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Verify CSRF token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("CSRF token validation failed");
    }
    
    if ($action === 'add_event' || $action === 'edit_event') {
        $id = $action === 'edit_event' ? ($_POST['event_id'] ?? null) : null;
        $kapal = sanitize_string($_POST['kapal'] ?? '');
        $tanggal = $_POST['tanggal'] ?? ($_POST['tanggal_display'] ?? '');
        $tujuan = sanitize_string($_POST['tujuan'] ?? '');
        $jenis = sanitize_string($_POST['jenis'] ?? '');
        $nopol = sanitize_string($_POST['nopol'] ?? '');
        $jam = $_POST['jam'] ?? '';
        
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
            
            if ($action === 'add_event') {
                $result = insertDocument("manifest", $data);
                $_SESSION['success'] = $result ? 'Jadwal berhasil ditambahkan' : 'Gagal menambahkan jadwal';
            } else {
                $result = updateDocument("manifest", ['_id' => new MongoDB\BSON\ObjectId($id)], $data);
                $_SESSION['success'] = $result ? 'Jadwal berhasil diperbarui' : 'Gagal memperbarui jadwal';
            }
        } else {
            $_SESSION['error'] = implode('<br>', $errors);
        }
        
        header("Location: dashboard.php");
        exit;
    }
    
    if ($action === 'delete_event') {
        $event_id = $_POST['event_id'] ?? null;
        if ($event_id) {
            $result = deleteDocument("manifest", ['_id' => new MongoDB\BSON\ObjectId($event_id)]);
            $_SESSION['success'] = $result ? 'Jadwal berhasil dihapus' : 'Gagal menghapus jadwal';
        }
        header("Location: dashboard.php");
        exit;
    }
}

// Ambil total manifest menggunakan countDocuments
$total_manifest = countDocuments("manifest", []);

// Ambil 3 riwayat terbaru
$riwayat_terbaru = findDocuments("manifest", [], ['sort' => ['_id' => -1], 'limit' => 3]);

// Fungsi format tanggal Indonesia (moved to functions.php)

// Data untuk Chart (Statistik Manifest per Bulan) - using PHP processing (more compatible)
$semua_manifest = findDocuments("manifest", []); // Get all manifests
$stats_data = [];
foreach ($semua_manifest as $m) {
    $m_arr = (array)$m;
    if (isset($m_arr['tanggal'])) {
        $month = date("Y-m", strtotime($m_arr['tanggal']));
        if (!isset($stats_data[$month])) {
            $stats_data[$month] = 0;
        }
        $stats_data[$month]++;
    }
}
ksort($stats_data); // Urutkan berdasarkan bulan
$chart_labels = json_encode(array_keys($stats_data));
$chart_values = json_encode(array_values($stats_data));

// Data untuk Calendar
$jadwal_manifest = findDocuments("manifest", ["created_by" => "Boss"]) ?: [];

// Data dropdown untuk modal edit/tambah
$master_kapal = findDocuments("master_kapal", []);
$kapals = [];
foreach($master_kapal as $k) $kapals[] = $k->nama;
if(empty($kapals)) $kapals = ['KM. DHARMA FERRY II', 'KM. DHARMA FERRY III'];

$master_jenis = findDocuments("master_jenis", []);
$jeniss = [];
foreach($master_jenis as $j) $jeniss[] = $j->kode;
if(empty($jeniss)) $jeniss = ['TB', 'TS'];

$master_nopol = findDocuments("master_nopol", []);
$nopols = [];
foreach($master_nopol as $n) $nopols[] = $n->nopol;
if(empty($nopols)) $nopols = ['H 8454 QQ', 'H 1316 PH', 'H 1370 TA', 'H 8470 QQ', 'H 9773 BQ', 'H 8211 BA', 'AA 8519 OF', 'BA 9937 FU'];

// Prepare events for FullCalendar
$events = [];
foreach ($jadwal_manifest as $m) {
    $m_arr = (array)$m;
    if (isset($m_arr['tanggal']) && isset($m_arr['jam'])) {
        $title = 'Manifest ' . $m_arr['kapal'] . ' - ' . $m_arr['nopol'];
        $tz = new DateTimeZone('Asia/Jakarta');
        $dt = DateTime::createFromFormat('Y-m-d H:i', $m_arr['tanggal'] . ' ' . $m_arr['jam'], $tz);
        if (!$dt) {
            $dt = new DateTime($m_arr['tanggal'] . ' ' . $m_arr['jam'], $tz);
        }
        $start = $dt->format('c'); // ISO8601 with offset
        $end_dt = clone $dt;
        $end_dt->add(new DateInterval('PT1H'));
        // Jika berakhir keesokan hari (mis. start 23:30 +1h -> 00:30), clamp ke 23:59:59 supaya tidak melebar ke kotak tanggal berikutnya
        if ($end_dt->format('Y-m-d') !== $dt->format('Y-m-d')) {
            $end_dt = clone $dt;
            $end_dt->setTime(23, 59, 59);
        }
        $end = $end_dt->format('c');
        $id = (string)$m_arr['_id'];
        $url = ($_SESSION['login_rifqy'] !== 'Boss') ? 'input_muatan.php?id=' . $id : '';
        $muatan_count = count(findDocuments("muatan", ["id_manifest" => $id]));
        $status = $muatan_count > 0 ? 'Selesai' : 'Menunggu';
        $color = $muatan_count > 0 ? '#10b981' : '#f59e0b';
        $full_title = 'Manifest ' . ($m_arr['kapal'] ?? '') . ' - ' . ($m_arr['nopol'] ?? '');
        $description = 'Kapal: ' . ($m_arr['kapal'] ?? '') . '\nTujuan: ' . ($m_arr['tujuan'] ?? '') . '\nTanggal: ' . $m_arr['tanggal'] . '\nJam: ' . $m_arr['jam'] . '\nNopol: ' . ($m_arr['nopol'] ?? '') . '\nStatus: ' . $status . '\nMuatan: ' . $muatan_count . ' item';
        $events[] = [
            'title' => $full_title,
            'start' => $start,
            'end' => $end,
            'allDay' => false,
            'id' => $id,
            'color' => $color,
            'extendedProps' => [
                'description' => $description,
                'kapal' => $m_arr['kapal'] ?? '',
                'tujuan' => $m_arr['tujuan'] ?? '',
                'jenis' => $m_arr['jenis'] ?? '',
                'nopol' => $m_arr['nopol'] ?? '',
                'jam' => $m_arr['jam'] ?? '',
                'tanggal' => $m_arr['tanggal'] ?? '',
                'status' => $status,
                'muatan_count' => $muatan_count
            ],
            'url' => $url
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CV. MANUNGGAL</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css' rel='stylesheet' />
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
    <style>
        /* ====================================================
           PREMIUM FULLCALENDAR MODERN DESIGN SYSTEM
           ==================================================== */
        #calendar {
            background: transparent !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            padding: 0 !important;
            margin-top: 10px !important;
            width: 100% !important;
        }

        /* Toolbar Layout */
        .fc.fc-theme-standard .fc-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .fc .fc-toolbar-title {
            font-size: 17px !important;
            font-weight: 800 !important;
            color: #0f172a !important;
            letter-spacing: -0.2px;
        }

        /* Segmented Button Groups */
        .fc .fc-button-group {
            display: inline-flex !important;
            background: #f1f5f9 !important;
            border-radius: 10px !important;
            padding: 3px !important;
            gap: 3px !important;
            border: 1px solid #e2e8f0 !important;
        }

        .fc .fc-button {
            background: transparent !important;
            border: none !important;
            color: #475569 !important;
            font-weight: 700 !important;
            font-size: 13px !important;
            padding: 6px 14px !important;
            border-radius: 8px !important;
            box-shadow: none !important;
            transition: all 0.2s ease !important;
            cursor: pointer !important;
            line-height: 1.4 !important;
            min-height: 36px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        .fc .fc-button:hover {
            color: var(--primary-blue) !important;
            background: rgba(10, 77, 191, 0.08) !important;
        }

        /* Active Segment Tab */
        .fc .fc-button.fc-button-active {
            background: #ffffff !important;
            color: var(--primary-blue) !important;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08) !important;
        }

        /* Today Button */
        .fc .fc-today-button {
            background: #e0f2fe !important;
            color: #0369a1 !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            padding: 6px 14px !important;
            border: none !important;
        }
        .fc .fc-today-button:disabled {
            opacity: 0.5 !important;
        }

        /* Table & Grid Styling */
        .fc-theme-standard th, 
        .fc-theme-standard td {
            border-color: #f1f5f9 !important;
        }

        .fc .fc-col-header-cell {
            background: #f8fafc !important;
            padding: 8px 4px !important;
            border-color: #e2e8f0 !important;
        }

        .fc .fc-col-header-cell-cushion {
            font-size: 12px !important;
            font-weight: 700 !important;
            color: #475569 !important;
            text-decoration: none !important;
        }

        .fc-daygrid-day-number {
            font-size: 12px !important;
            font-weight: 700 !important;
            color: #334155 !important;
            padding: 4px 6px !important;
            text-decoration: none !important;
        }

        .fc .fc-day-today {
            background: rgba(10, 77, 191, 0.04) !important;
        }

        .fc .fc-day-today .fc-daygrid-day-number {
            background: var(--primary-blue) !important;
            color: #ffffff !important;
            border-radius: 50% !important;
            width: 22px !important;
            height: 22px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            margin: 2px !important;
        }

        /* Grid Event Pill Styling */
        .fc-daygrid-event {
            background: transparent !important;
            border: none !important;
            padding: 0 !important;
            margin: 2px 1px !important;
            box-shadow: none !important;
        }

        .fc-custom-grid-pill {
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 3px 6px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
            box-sizing: border-box;
            transition: transform 0.15s ease;
        }
        .fc-custom-grid-pill.status-selesai {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .fc-custom-grid-pill.status-menunggu {
            background: #fffbeb;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        .fc-custom-grid-pill:active {
            transform: scale(0.96);
        }
        .fc-grid-time {
            font-size: 10px;
            font-weight: 800;
            opacity: 0.85;
            flex-shrink: 0;
        }
        .fc-grid-title {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* List View (Agenda Cards) Styling */
        .fc-theme-standard .fc-list {
            border: 1px solid #e2e8f0 !important;
            border-radius: 12px !important;
            overflow: hidden !important;
            background: #f8fafc !important;
        }

        .fc-list-empty {
            background: #ffffff !important;
            padding: 30px 20px !important;
            color: #64748b !important;
            font-size: 14px !important;
            text-align: center !important;
            font-weight: 500 !important;
        }

        .fc-list-day-cushion {
            background: #e2e8f0 !important;
            padding: 10px 14px !important;
        }

        .fc-list-day-text {
            font-weight: 800 !important;
            color: #1e293b !important;
            font-size: 13px !important;
            text-decoration: none !important;
        }

        .fc-list-day-side-text {
            font-weight: 600 !important;
            color: #64748b !important;
            font-size: 12px !important;
        }

        .fc-list-event {
            background: #ffffff !important;
            transition: all 0.2s ease !important;
            cursor: pointer !important;
        }

        .fc-list-event:hover, .fc-list-event:active {
            background: #f0f7ff !important;
        }

        .fc-list-event td {
            border-color: #f1f5f9 !important;
            padding: 8px 10px !important;
            vertical-align: middle !important;
        }

        .fc-list-event-time {
            display: none !important;
        }

        .fc-list-event-graphic {
            display: none !important;
        }

        .fc-list-event-title {
            padding: 6px 8px !important;
        }

        /* Custom Card for List View */
        .fc-event-card-mobile {
            display: flex;
            flex-direction: column;
            gap: 8px;
            background: #ffffff;
            border-radius: 10px;
            padding: 12px 14px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
            border: 1px solid #f1f5f9;
        }

        .fc-event-card-mobile.status-selesai {
            border-left: 4px solid #10b981;
        }

        .fc-event-card-mobile.status-menunggu {
            border-left: 4px solid #f59e0b;
        }

        .fc-event-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .fc-ship-name {
            font-size: 14px;
            font-weight: 800;
            color: #1e293b;
            letter-spacing: -0.2px;
        }

        .fc-status-pill {
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 20px;
            white-space: nowrap;
        }

        .fc-status-pill.status-selesai {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .fc-status-pill.status-menunggu {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        .fc-event-details {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .fc-tag-badge {
            background: #f1f5f9;
            color: #475569;
            font-size: 12px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }

        .fc-tag-badge.count-tag {
            background: #e0f2fe;
            color: #0369a1;
            border-color: #bae6fd;
            font-weight: 700;
        }

        /* Mobile Adjustments for Calendar Toolbar */
        @media (max-width: 640px) {
            .fc.fc-theme-standard .fc-toolbar {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 10px !important;
                margin-bottom: 12px !important;
            }

            .fc-toolbar-chunk:first-child {
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
                width: 100% !important;
            }

            .fc-toolbar-title {
                font-size: 16px !important;
                text-align: center !important;
            }

            /* View Segment Bar Full Width on Mobile */
            .fc-toolbar-chunk:last-child {
                width: 100% !important;
            }

            .fc-toolbar-chunk:last-child .fc-button-group {
                width: 100% !important;
                display: flex !important;
            }

            .fc-toolbar-chunk:last-child .fc-button {
                flex: 1 !important;
                text-align: center !important;
                min-height: 42px !important;
                font-size: 13px !important;
                font-weight: 700 !important;
            }
        }
    </style>
    <style>

         .main-content h1 { 
             margin: 0 0 5px 0; 
             color: var(--text-color); 
             font-size: 24px;
         }
         .main-content p.subtitle { 
             color: var(--gray); 
             font-size: 14px; 
             margin: 0 0 20px 0;
         }
         
         .content-wrapper {
             padding: 0 20px 20px 20px;
         }
         
        .dashboard-grid { 
            display: grid; 
            grid-template-columns: repeat(2, minmax(0, 1fr)); 
            gap: 20px; 
            align-items: stretch;
        }
        .left-panel, .right-panel { 
            min-width: 0; 
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .left-panel .card:first-child,
        .left-panel .card:last-child {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        .left-panel .card:first-child .chart-container,
        .left-panel .card:last-child .history-list {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        .left-panel .card:last-child .history-list {
            overflow-y: auto;
            max-height: none;
        }
        .right-panel .card {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        .right-panel .card #calendar {
            flex: 1;
            min-height: 0;
        }
        
        @media (max-width: 768px) {
            .dashboard-grid { grid-template-columns: 1fr; }
            .left-panel, .right-panel {
                flex-direction: column;
            }
            .left-panel .card:first-child,
            .left-panel .card:last-child,
            .right-panel .card {
                flex: none;
                min-height: auto;
                padding: 15px !important;
            }
            .left-panel .card:first-child .chart-container,
            .left-panel .card:last-child .history-list,
            .right-panel .card #calendar {
                flex: none;
                height: auto;
                min-height: 0;
            }
            .chart-container {
                height: auto !important;
                max-height: none !important;
                min-height: 180px;
                width: 100%;
                overflow: hidden;
            }
            .chart-container canvas {
                width: 100% !important;
                height: auto !important;
            }
            #calendar {
                height: auto !important;
                max-height: none !important;
                min-height: 300px;
                width: 100%;
                overflow: hidden;
            }
            .history-list {
                max-height: 200px;
                overflow-y: auto;
                padding-right: 4px;
                display: flex;
                flex-direction: column;
            }
            .history-item {
                padding: 10px 12px;
                margin-bottom: 8px;
                font-size: 14px;
                flex-wrap: wrap;
                display: flex;
                flex-direction: column;
            }
            .history-item-details strong { font-size: 14px; }
            .history-item-details span { font-size: 12px; }
            .history-item a {
                font-size: 11px;
                margin-top: 4px;
                width: 100%;
                text-align: right;
            }
            .view-all-link {
                font-size: 12px;
                margin-top: 8px;
            }
        }

        /* Tablet: batasi grafik & kalender + rapikan riwayat */
        @media (min-width: 769px) and (max-width: 1024px) {
            .chart-container {
                height: 260px !important;
                max-height: 300px !important;
            }
            #calendar {
                height: 450px !important;
                max-height: 500px !important;
            }
            .history-list {
                max-height: 220px;
                overflow-y: auto;
            }
            .left-panel {
                gap: 15px;
            }
        }
         .card { 
             background: var(--white); 
             padding: 20px; 
             border-radius: 12px; 
             box-shadow: 0 4px 15px rgba(0,0,0,0.05); 
              border-top: 4px solid var(--primary-blue);
              height: auto;
          }

          @media (max-width: 768px) {
              .card {
                  height: auto !important;
                  padding: 15px !important;
              }
              .chart-container, #calendar {
                  min-height: 200px;
                  height: auto;
              }
              .chart-container canvas {
                  height: auto !important;
              }
          }
         .card h3 { 
             margin: 0 0 15px 0; 
             color: #555; 
             font-size: 16px;
             padding-bottom: 10px;
             border-bottom: 1px solid var(--border-color);
         }
         
        .chart-container {
            position: relative;
            flex: 1;
            min-height: 0;
            width: 100%;
        }
         
         .history-list { 
             list-style: none; 
             padding: 0; 
             margin: 0; 
         }
         .history-item { 
             background: var(--light-blue); 
             padding: 12px 15px; 
             margin-bottom: 10px; 
             border-radius: 8px; 
             border-left: 4px solid var(--hover-blue); 
             display: flex; 
             justify-content: space-between; 
             align-items: center;
         }
         .history-item-details strong { 
             display: block; 
             color: var(--text-color); 
             font-size: 14px;
             margin-bottom: 4px;
         }
         .history-item-details span { 
             color: var(--gray); 
             font-size: 12px; 
         }
         .history-item a { 
             color: var(--primary-blue); 
             text-decoration: none; 
             font-size: 12px; 
             font-weight: bold;
             white-space: nowrap;
         }
         .history-item a:hover {
             text-decoration: underline;
         }
         .view-all-link {
             display: block;
             margin-top: 12px;
             color: #0a4dbf; 
             text-decoration: none; 
             font-size: 13px; 
             font-weight: bold;
             text-align: right;
         }
          .view-all-link:hover {
              text-decoration: underline;
          }
          
          /* Modal Styles */
          .modal-overlay {
              position: fixed;
              top: 0;
              left: 0;
              width: 100%;
              height: 100%;
              background: rgba(0,0,0,0.5);
              display: flex;
              align-items: center;
              justify-content: center;
              z-index: 2000;
          }
          .modal {
              background: var(--white);
              padding: 25px;
              border-radius: 14px;
              width: 400px;
              max-width: 90%;
              max-height: 90vh;
              overflow-y: auto;
              box-shadow: 0 10px 40px rgba(0,0,0,0.3);
          }
          .modal h4 {
              margin: 0 0 20px 0;
              font-size: 18px;
              color: var(--primary-blue);
          }
          .modal .form-group {
              margin-bottom: 15px;
          }
          .modal label {
              display: block;
              margin-bottom: 5px;
              font-weight: 600;
              font-size: 14px;
          }
          .modal .form-control {
              width: 100%;
              padding: 10px;
              border: 1px solid var(--border-color);
              border-radius: 8px;
              font-size: 14px;
          }
          .modal .form-control:focus {
              border-color: var(--primary-blue);
              outline: none;
              box-shadow: 0 0 0 3px rgba(10, 77, 191, 0.15);
          }
           .modal .btn {
               margin-top: 8px;
               margin-right: 8px;
           }

           /* Success Popup / Toast */
           .success-popup {
               position: fixed;
               top: 50%;
               left: 50%;
               transform: translate(-50%, -50%);
               background: #d4edda;
               color: #155724;
               padding: 22px 35px;
               border-radius: 14px;
               box-shadow: 0 15px 40px rgba(0,0,0,0.25);
               z-index: 9999;
               text-align: center;
               font-size: 17px;
               font-weight: 700;
               border: 3px solid #c3e6cb;
               min-width: 280px;
           }
           .success-popup .check {
               font-size: 28px;
               display: block;
               margin-bottom: 6px;
           }

    </style>
</head>
<body>

<div class="layout-wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <?php include 'top_nav.php'; ?>

        <?php
        if(isset($_SESSION['success'])){
            $successMsg = $_SESSION['success'];
            echo '<script>window.__pendingSuccess = ' . json_encode($successMsg) . ';</script>';
            unset($_SESSION['success']);
        }
        if(isset($_SESSION['error'])){
            echo '<div class="alert alert-danger">'.$_SESSION['error'].'</div>';
            unset($_SESSION['error']);
        }
        ?>

        <div class="content-wrapper">
            <h1><?php if($is_boss){ echo 'hallo boss'; } else { echo 'Halo, ' . e($nama_user) . '! 👋'; } ?></h1>
            <p class="subtitle">Selamat datang di Pusat Kontrol Sistem Manifest Cargo.</p>

            <div class="dashboard-grid">
                <div class="left-panel">
                    <div class="card">
                        <h3>📊 Statistik Manifest Bulanan</h3>
                        <div class="chart-container">
                            <canvas id="myChart"></canvas>
                        </div>
                    </div>

                    <div class="card">
                        <h3>📜 Riwayat Terakhir</h3>
                        <ul class="history-list">
                            <?php if(count($riwayat_terbaru) > 0): ?>
                                <?php foreach($riwayat_terbaru as $row_obj):
                                    $row = (array)$row_obj;
                                ?>
                                    <li class="history-item">
                                        <div class="history-item-details">
                                            <strong><?= e($row['kapal']) ?> (<?= e($row['tujuan']) ?>)</strong>
                                            <span><?= tgl_indo($row['tanggal']) ?> | Nopol: <?= e($row['nopol']) ?></span>
                                        </div>
                                        <a href="preview_manifest_lama.php?id=<?= e((string)$row['_id']) ?>">Lihat Detail →</a>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="color: #888; font-size: 14px;">Belum ada riwayat manifest.</p>
                            <?php endif; ?>
                        </ul>
                        <a href="data.php" class="view-all-link">Lihat Semua Riwayat →</a>
                    </div>
                </div>

                <div class="right-panel">
                    <div class="card">
                        <h3>📅 Jadwal Manifest</h3>
                        <div id='calendar'></div>
                </div>
                <div id='tooltip'></div>
            </div>
        </div>
    </div>

<!-- Add Event Modal (Boss only) -->
     <?php if($is_boss): ?>
     <div id="add_modal" class="modal-overlay" style="display: none;">
         <div class="modal">
             <h4>Tambah Jadwal Manifest</h4>
             <form id="add_event_form" method="POST" action="api_event.php">
                 <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                 <input type="hidden" name="action" value="add_event">
                 <input type="hidden" name="tanggal" id="add_tanggal">

                 <div class="form-group">
                     <label>Tanggal</label>
                      <input type="text" name="tanggal_display" id="add_tanggal_display" class="form-control" readonly style="background:#f0f4f8; color:#333; font-weight:500;">
                 </div>

                 <div class="form-group">
                     <label>Nama Kapal</label>
                     <select name="kapal" class="form-control" required>
                         <?php foreach($kapals as $k): ?>
                             <option value="<?= e($k) ?>"><?= e($k) ?></option>
                         <?php endforeach; ?>
                     </select>
                 </div>

                 <div class="form-group">
                     <label>Tujuan</label>
                     <input type="text" name="tujuan" class="form-control" value="Semarang - Ketapang" required>
                 </div>

                 <div class="form-group">
                     <label>Jenis Kendaraan</label>
                     <select name="jenis" class="form-control" required>
                         <?php foreach($jeniss as $j): ?>
                             <option value="<?= e($j) ?>"><?= e($j) ?></option>
                         <?php endforeach; ?>
                     </select>
                 </div>

                 <div class="form-group">
                     <label>Nomor Polisi</label>
                     <select name="nopol" class="form-control" required>
                         <?php foreach($nopols as $n): ?>
                             <option value="<?= e($n) ?>"><?= e($n) ?></option>
                         <?php endforeach; ?>
                     </select>
                 </div>

                 <div class="form-group">
                     <label>Jam Berangkat</label>
                     <input type="time" name="jam" class="form-control" required>
                 </div>

                 <div style="display: flex; gap: 10px; margin-top: 18px;">
                     <button type="submit" class="btn btn-primary" style="flex: 1;">Simpan</button>
                     <button type="button" class="btn btn-secondary" onclick="document.getElementById('add_modal').style.display='none'">Batal</button>
                 </div>
                 <div id="add_form_message" style="margin-top: 10px; font-size: 13px;"></div>
             </form>
         </div>
     </div>

     <!-- Edit Event Modal (Boss only) -->
     <div id="edit_modal" class="modal-overlay" style="display: none;">
         <div class="modal">
             <h4>Edit Jadwal Manifest</h4>
             <form id="edit_event_form" method="POST" action="api_event.php">
                 <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                 <input type="hidden" name="action" value="edit_event">
                 <input type="hidden" name="event_id" id="edit_event_id">

                 <div class="form-group">
                     <label>Tanggal Keberangkatan</label>
                      <input type="date" name="tanggal" id="edit_tanggal" class="form-control" required>
                      <input type="text" id="edit_tanggal_display" class="form-control" readonly style="margin-top:6px; background:#f0f4f8; color:#333; font-weight:500; font-size:14px;" placeholder="Tanggal akan ditampilkan di sini">
                  </div>

                 <div class="form-group">
                     <label>Nama Kapal</label>
                     <select name="kapal" id="edit_kapal" class="form-control" required>
                         <?php foreach($kapals as $k): ?>
                             <option value="<?= e($k) ?>"><?= e($k) ?></option>
                         <?php endforeach; ?>
                     </select>
                 </div>

                 <div class="form-group">
                     <label>Tujuan</label>
                     <input type="text" name="tujuan" id="edit_tujuan" class="form-control" required>
                 </div>

                 <div class="form-group">
                     <label>Jenis Kendaraan</label>
                     <select name="jenis" id="edit_jenis" class="form-control" required>
                         <?php foreach($jeniss as $j): ?>
                             <option value="<?= e($j) ?>"><?= e($j) ?></option>
                         <?php endforeach; ?>
                     </select>
                 </div>

                 <div class="form-group">
                     <label>Nomor Polisi</label>
                     <select name="nopol" id="edit_nopol" class="form-control" required>
                         <?php foreach($nopols as $n): ?>
                             <option value="<?= e($n) ?>"><?= e($n) ?></option>
                         <?php endforeach; ?>
                     </select>
                 </div>

                 <div class="form-group">
                     <label>Jam Berangkat</label>
                     <input type="time" name="jam" id="edit_jam" class="form-control" required>
                 </div>

                 <div style="display: flex; gap: 8px; margin-top: 18px; flex-wrap: wrap;">
                     <button type="submit" class="btn btn-primary" style="flex: 1; min-width: 90px;">Update</button>
                     <button type="button" class="btn btn-danger" onclick="deleteEvent()" style="flex: 1; min-width: 90px;">Hapus</button>
                     <button type="button" class="btn btn-secondary" onclick="closeEditModal()" style="min-width: 80px;">Batal</button>
                 </div>
                 <div id="edit_form_message" style="margin-top: 10px; font-size: 13px;"></div>
             </form>
         </div>
     </div>
     <?php endif; ?>

<script>
        // Chart.js initialization
        const ctx = document.getElementById('myChart');
        if (ctx) {
            const chartLabels = <?php echo $chart_labels ?: '[]'; ?>;
            const chartValues = <?php echo $chart_values ?: '[]'; ?>;
            if (chartLabels.length > 0 && chartValues.length > 0) {
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: chartLabels,
                        datasets: [{
                            label: 'Jumlah Manifest',
                            data: chartValues,
                            backgroundColor: 'rgba(10, 77, 191, 0.2)',
                            borderColor: 'rgba(10, 77, 191, 1)',
                            borderWidth: 2,
                            borderRadius: 5
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
                        plugins: { legend: { display: false } }
                    }
                });
            } else {
                ctx.parentNode.innerHTML = '<p style="color:#888;font-size:14px;text-align:center;padding:40px;">Belum ada data statistik manifest.</p>';
            }
        }

        // Helper functions
        window.setSelectValue = function(selectId, value) {
            var select = document.getElementById(selectId);
            if (!select || !value) return;
            // Try direct value assignment first (fastest)
            select.value = value;
            if (select.value === value) return;
            // Fallback: loop through options
            var val = value.trim();
            for (var i = 0; i < select.options.length; i++) {
                if (select.options[i].value.trim() === val) {
                    select.selectedIndex = i;
                    return;
                }
            }
            // If still not found, add as new option so data is not lost
            var opt = document.createElement('option');
            opt.value = value;
            opt.textContent = value + ' (baru)';
            select.appendChild(opt);
            select.value = value;
            console.log('Added new option for', selectId, ':', value);
        };

        window.closeEditModal = function() {
            document.getElementById('edit_modal').style.display = 'none';
        };

        window.closeAddModal = function() {
            document.getElementById('add_modal').style.display = 'none';
        };

        window.deleteEvent = function() {
            if (confirm('Apakah Anda yakin ingin menghapus jadwal ini?')) {
                var eventId = document.getElementById('edit_event_id').value;
                if (!eventId) { alert('ID event tidak ditemukan!'); return; }
                fetch('api_event.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=delete_event&event_id=' + encodeURIComponent(eventId) + '&csrf_token=' + encodeURIComponent('<?= e($_SESSION['csrf_token']) ?>')
                }).then(function(r) { return r.json(); })
                  .then(function(d) {
                      if (d.success) { location.reload(); }
                      else { alert(d.error || 'Gagal menghapus'); }
                  }).catch(function() { alert('Error koneksi'); });
            }
        };

        // Helper: escape HTML for safe insertion
        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        // FullCalendar Modern Mobile & Desktop Setup
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            if (calendarEl) {
                var isMobile = window.innerWidth < 768;
                var calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: isMobile ? 'listMonth' : 'dayGridMonth',
                    buttonText: {
                        today: 'Hari Ini',
                        month: '📅 Kalender',
                        dayGridMonth: '📅 Kalender',
                        list: '📋 Agenda',
                        listMonth: '📋 Agenda'
                    },
                    headerToolbar: isMobile ? {
                        left: 'prev,next',
                        center: 'title',
                        right: 'dayGridMonth,listMonth'
                    } : {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,listMonth'
                    },
                    customButtons: {
                        addEvent: {
                            text: '+ Tambah',
                            click: function() {
                                var today = new Date().toISOString().split('T')[0];
                                document.getElementById('add_tanggal').value = today;
                                document.getElementById('add_tanggal_display').value = today;
                                document.getElementById('add_event_form').reset();
                                document.getElementById('add_form_message').innerHTML = '';
                                document.getElementById('add_modal').style.display = 'flex';
                            }
                        }
                    },
                    height: 'auto',
                    dayMaxEvents: isMobile ? 2 : 4,
                    eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
                    navLinks: false,
                    eventContent: function(arg) {
                        var ext = arg.event.extendedProps || {};
                        var isList = arg.view.type.indexOf('list') !== -1;
                        var statusClass = ext.status === 'Selesai' ? 'status-selesai' : 'status-menunggu';
                        var statusBadge = ext.status === 'Selesai' ? '✅ Selesai' : '⏳ Menunggu';
                        var kapal = ext.kapal || arg.event.title || 'Kapal';
                        var jam = ext.jam || (arg.timeText ? arg.timeText : '');
                        var nopol = ext.nopol || '';
                        var tujuan = ext.tujuan || '';
                        var muatanCount = parseInt(ext.muatan_count, 10) || 0;
                        var muatanText = muatanCount > 0 ? ('📦 ' + muatanCount + ' muatan') : '📦 Belum ada muatan';

                        if (isList) {
                            var html = '<div class="fc-event-card-mobile ' + statusClass + '">' +
                                '<div class="fc-event-top">' +
                                    '<span class="fc-ship-name">🚢 ' + escapeHtml(kapal) + '</span>' +
                                    '<span class="fc-status-pill ' + statusClass + '">' + statusBadge + '</span>' +
                                '</div>' +
                                '<div class="fc-event-details">' +
                                    (jam ? '<span class="fc-tag-badge">⏰ ' + escapeHtml(jam) + '</span>' : '') +
                                    (nopol ? '<span class="fc-tag-badge">🚛 ' + escapeHtml(nopol) + '</span>' : '') +
                                    (tujuan ? '<span class="fc-tag-badge">📍 ' + escapeHtml(tujuan) + '</span>' : '') +
                                    '<span class="fc-tag-badge count-tag">' + muatanText + '</span>' +
                                '</div>' +
                            '</div>';
                            return { html: html };
                        } else {
                            var html = '<div class="fc-custom-grid-pill ' + statusClass + '" title="' + escapeHtml(arg.event.title) + '">' +
                                (jam ? '<span class="fc-grid-time">' + escapeHtml(jam) + '</span>' : '') +
                                '<span class="fc-grid-title">' + escapeHtml(kapal) + '</span>' +
                            '</div>';
                            return { html: html };
                        }
                    },
                    eventClick: function(info) {
                        if (info.jsEvent) {
                            info.jsEvent.preventDefault();
                        }
                        <?php if($is_boss): ?>
                            var eventId = info.event.id;
                            var ext = info.event.extendedProps || {};
                            document.getElementById('edit_event_id').value = eventId;
                            document.getElementById('edit_form_message').innerHTML = '';

                            // Tampilkan modal
                            document.getElementById('edit_modal').style.display = 'flex';

                            // Set data langsung dari extendedProps
                            var startDate = info.event.start;
                            var dateStr = '';
                            if (startDate) {
                                if (typeof startDate === 'string') {
                                    dateStr = startDate.split('T')[0];
                                } else {
                                    var y = startDate.getFullYear();
                                    var m = String(startDate.getMonth()+1).padStart(2,'0');
                                    var d = String(startDate.getDate()).padStart(2,'0');
                                    dateStr = y + '-' + m + '-' + d;
                                }
                            }
                            // Gunakan tanggal asli dari extendedProps agar akurat (hindari shift timezone dari FullCalendar)
                            document.getElementById('edit_tanggal').value = ext.tanggal || dateStr;
                            document.getElementById('edit_jam').value = ext.jam || '';
                            document.getElementById('edit_tujuan').value = ext.tujuan || '';

                            // Tampilkan format Indonesia yang rapi (23 Mei 2026) di field display
                            var disp = document.getElementById('edit_tanggal_display');
                            if (disp) {
                                var tglVal = ext.tanggal || dateStr;
                                if (tglVal) {
                                    var parts = tglVal.split('-');
                                    var dt = new Date(parseInt(parts[0],10), parseInt(parts[1],10)-1, parseInt(parts[2],10));
                                    disp.value = dt.toLocaleDateString('id-ID', { day:'numeric', month:'long', year:'numeric' });
                                }
                            }

                            // Set dropdown - langsung set value
                            var kapalVal = String(ext.kapal || '');
                            var jenisVal = String(ext.jenis || '');
                            var nopolVal = String(ext.nopol || '');

                            var selKapal = document.getElementById('edit_kapal');
                            var selJenis = document.getElementById('edit_jenis');
                            var selNopol = document.getElementById('edit_nopol');

                            // Helper: set select value, tambah option jika tidak ketemu
                            function fillSelect(sel, val) {
                                if (!sel || !val) return;
                                var opts = sel.options;
                                for (var i = 0; i < opts.length; i++) {
                                    if (String(opts[i].value) === val) {
                                        sel.value = val;
                                        return;
                                    }
                                }
                                var opt = document.createElement('option');
                                opt.value = val;
                                opt.textContent = val;
                                sel.add(opt);
                                sel.value = val;
                            }

                            fillSelect(selKapal, kapalVal);
                            fillSelect(selJenis, jenisVal);
                            fillSelect(selNopol, nopolVal);

                            // Ambil data terbaru dari API
                            fetch('api_event.php?id=' + encodeURIComponent(eventId), { credentials: 'same-origin' })
                                .then(function(r) { return r.json(); })
                                .then(function(data) {
                                    if (data.success && data.data) {
                                        var d = data.data;
                                        document.getElementById('edit_tanggal').value = d.tanggal || dateStr;
                                        document.getElementById('edit_jam').value = d.jam || '';
                                        document.getElementById('edit_tujuan').value = d.tujuan || '';
                                        fillSelect(document.getElementById('edit_kapal'), d.kapal || kapalVal);
                                        fillSelect(document.getElementById('edit_jenis'), d.jenis || jenisVal);
                                        fillSelect(document.getElementById('edit_nopol'), d.nopol || nopolVal);
                                    }
                                })
                                .catch(function(err) {
                                    console.log('API fetch error (using fallback):', err);
                                });
                        <?php else: ?>
                            if (info.event.url) { 
                                window.location.href = info.event.url; 
                            }
                        <?php endif; ?>
                    },
                    dateClick: function(info) {
                         <?php if($is_boss): ?>
                              document.getElementById('add_event_form').reset();
                              document.getElementById('add_tanggal').value = info.dateStr;
                              var parts = info.dateStr.split('-');
                              var d = new Date(parseInt(parts[0],10), parseInt(parts[1],10)-1, parseInt(parts[2],10));
                              document.getElementById('add_tanggal_display').value = d.toLocaleDateString('id-ID', { day:'numeric', month:'long', year:'numeric' });
                              document.getElementById('add_form_message').innerHTML = '';
                              document.getElementById('add_modal').style.display = 'flex';
                         <?php endif; ?>
                    },
                    events: <?php echo json_encode($events ?: [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
                });
                calendar.render();

                window.addEventListener('resize', function() {
                    calendar.updateSize();
                });
            }
        });

        // Helper: Show nice success popup (toast/modal style)
        function showSuccessPopup(message) {
            var popup = document.createElement('div');
            popup.className = 'success-popup';
            popup.innerHTML = '<span class="check">✅</span>' + message;
            document.body.appendChild(popup);

            setTimeout(function() {
                if (popup && popup.parentNode) {
                    popup.parentNode.removeChild(popup);
                }
            }, 1800);
        }

        // Trigger popup if coming from manifest create or other success
        if (window.__pendingSuccess) {
            setTimeout(function() {
                showSuccessPopup(window.__pendingSuccess);
            }, 300);
        }

        // AJAX: Add Event Form
        document.getElementById('add_event_form').addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Menyimpan...';
            var formData = new FormData(this);
            fetch('api_event.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            }).then(function(r) { return r.json(); })
              .then(function(data) {
                  var msg = document.getElementById('add_form_message');
                   if (data.success) {
                       // Tampilkan pop up yang diminta user
                       document.getElementById('add_modal').style.display = 'none';
                       showSuccessPopup('Jadwal berhasil diinputkan');

                       setTimeout(function() {
                           location.reload();
                       }, 1400);
                   } else {
                       msg.innerHTML = '<span style="color:red;">' + (data.error || 'Gagal') + '</span>';
                   }
                  btn.disabled = false;
                  btn.textContent = 'Simpan';
              })
              .catch(function() {
                  document.getElementById('add_form_message').innerHTML = '<span style="color:red;">Error koneksi!</span>';
                  btn.disabled = false;
                  btn.textContent = 'Simpan';
              });
        });

        // AJAX: Edit Event Form
        document.getElementById('edit_event_form').addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Menyimpan...';
            var formData = new FormData(this);
            fetch('api_event.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            }).then(function(r) { return r.json(); })
              .then(function(data) {
                  var msg = document.getElementById('edit_form_message');
                  if (data.success) {
                      msg.innerHTML = '<span style="color:green;">' + (data.message || 'Berhasil') + '</span>';
                      setTimeout(function() {
                          document.getElementById('edit_modal').style.display = 'none';
                          location.reload();
                      }, 800);
                  } else {
                      msg.innerHTML = '<span style="color:red;">' + (data.error || 'Gagal') + '</span>';
                  }
                  btn.disabled = false;
                  btn.textContent = 'Update';
              })
              .catch(function() {
                  document.getElementById('edit_form_message').innerHTML = '<span style="color:red;">Error koneksi!</span>';
                  btn.disabled = false;
                  btn.textContent = 'Update';
              });
        });
    </script>

        </div>
    </div>
</div>

</body>
</html>