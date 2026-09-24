<?php
// Security headers must be sent before any output
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

session_start();
include 'koneksi_mongodb.php';
include 'functions.php';

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
        $color = $muatan_count > 0 ? 'green' : 'red';
        $full_title = $title . ' (' . $status . ')';
        $description = 'Kapal: ' . $m_arr['kapal'] . '\nTujuan: ' . $m_arr['tujuan'] . '\nTanggal: ' . $m_arr['tanggal'] . '\nJam: ' . $m_arr['jam'] . '\nNopol: ' . $m_arr['nopol'] . '\nStatus: ' . $status . '\nMuatan: ' . $muatan_count . ' item';
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
                'tanggal' => $m_arr['tanggal'] ?? ''
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
        #calendar {
            background: var(--white);
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            padding: 20px;
            margin-top: 20px;
            overflow: hidden;
        }
        .fc-header-toolbar {
            margin-bottom: 20px;
        }
        .fc-button {
            background: var(--primary-blue) !important;
            border: none !important;
            border-radius: 8px !important;
            color: white !important;
            font-weight: bold;
        }
        .fc-button:hover {
            background: var(--dark-blue) !important;
        }
        .fc-event {
            background: var(--primary-blue);
            border: none;
            border-radius: 6px;
            color: white;
            font-size: 12px;
            padding: 4px 8px;
            cursor: pointer;
        }
        .fc-event:hover {
            background: var(--hover-blue);
        }
        .fc-daygrid-day:hover {
            background: var(--light-blue);
        }
        .fc-col-header-cell {
            background: var(--light-blue);
            color: var(--text-color);
            font-weight: bold;
        }
        .fc-day-today {
            background: rgba(0,123,255,0.1) !important;
        }
        #tooltip {
            position: absolute;
            display: none;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            white-space: pre-line;
            z-index: 1000;
            pointer-events: none;
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

                 <button type="submit" class="btn btn-primary">Simpan</button>
                 <button type="button" class="btn btn-secondary" onclick="document.getElementById('add_modal').style.display='none'">Batal</button>
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

                 <button type="submit" class="btn btn-primary">Update</button>
                 <button type="button" class="btn btn-danger" onclick="deleteEvent()">Hapus</button>
                 <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Batal</button>
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

        // FullCalendar
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            if (calendarEl) {
                var calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    headerToolbar: {
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
                    dayMaxEvents: 3,
                    eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
                    navLinks: true,
eventClick: function(info) {
                        <?php if($is_boss): ?>
                            var eventId = info.event.id;
                            var ext = info.event.extendedProps || {};
                            document.getElementById('edit_event_id').value = eventId;
                            document.getElementById('edit_form_message').innerHTML = '';

                            // Tampilkan modal dulu
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
                                // Tambah option baru
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
                            if (info.event.url) { window.location.href = info.event.url; }
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