<?php
// Kode di bawah digunakan untuk memuat helper koneksi database MongoDB
require_once 'koneksi_mongodb.php';

// Kode di bawah digunakan untuk memuat fungsi utilitas seperti sanitasi dan format tanggal
require_once 'functions.php';

// Kode di bawah digunakan untuk memulai session jika belum berjalan
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kode di bawah digunakan untuk memvalidasi status login user, mengarahkan ke login.php jika belum login
if (!isset($_SESSION['login_rifqy'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu.';
    header("Location: login.php");
    exit;
}

// Kode di bawah digunakan untuk menginisialisasi array query filter MongoDB
$filter = [];

// Kode di bawah digunakan untuk menyaring data manifest berdasarkan pencarian nama kapal (case-insensitive regex)
if (!empty($_GET['kapal'])) {
    $kapal = sanitize_string($_GET['kapal']);
    $filter['kapal'] = new MongoDB\BSON\Regex($kapal, 'i');
}

// Kode di bawah digunakan untuk menyaring data manifest berdasarkan nomor polisi kendaraan
if (!empty($_GET['nopol'])) {
    $nopol = sanitize_string($_GET['nopol']);
    $nopol_escaped = preg_quote($nopol, '/');
    $filter['nopol'] = new MongoDB\BSON\Regex($nopol_escaped, 'i');
}

// Kode di bawah digunakan untuk menyaring data manifest berdasarkan rentang tanggal mulai dan tanggal selesai
if (!empty($_GET['tgl_mulai']) && !empty($_GET['tgl_selesai'])) {
    if (validate_date($_GET['tgl_mulai']) && validate_date($_GET['tgl_selesai'])) {
        $filter['tanggal'] = ['$gte' => $_GET['tgl_mulai'], '$lte' => $_GET['tgl_selesai']];
    }
} elseif (!empty($_GET['tgl_mulai'])) {
    if (validate_date($_GET['tgl_mulai'])) {
        $filter['tanggal'] = ['$gte' => $_GET['tgl_mulai']];
    }
} elseif (!empty($_GET['tgl_selesai'])) {
    if (validate_date($_GET['tgl_selesai'])) {
        $filter['tanggal'] = ['$lte' => $_GET['tgl_selesai']];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Manifest - CV. MANUNGGAL</title>
    <!-- Memuat file stylesheet utama aplikasi -->
    <link rel="stylesheet" href="style.css">
    <style>
        .no-data { padding: 40px; color: #999; text-align: center; }
    </style>
</head>
<body>

<!-- Kode di bawah adalah pembungkus layout utama sistem dengan sidebar dan navigasi -->
<div class="layout-wrapper">
    <!-- Memuat menu navigasi samping (sidebar) -->
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Memuat bar navigasi atas -->
        <?php include 'top_nav.php'; ?>
        
        <div class="content-wrapper">
            <div class="container">
                <h3 class="header-title">📜 Riwayat Manifest Cargo</h3>
                
                <!-- Kode di bawah adalah form filter pencarian data manifest berdasarkan kriteria tertentu -->
                <div class="filter-card" style="background: #f8fafc; padding: 18px; border-radius: 12px; margin-bottom: 20px; border: 1px solid var(--border-color);">
                    <form method="GET" class="filter-form" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
                        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
                            <label>Nama Kapal</label>
                            <input type="text" name="kapal" class="form-control" placeholder="Cari kapal..." value="<?= e($_GET['kapal'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
                            <label>Nomor Polisi</label>
                            <input type="text" name="nopol" class="form-control" placeholder="Cari nopol..." value="<?= e($_GET['nopol'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 140px;">
                            <label>Dari Tanggal</label>
                            <input type="date" name="tgl_mulai" class="form-control" value="<?= e($_GET['tgl_mulai'] ?? '') ?>">
                        </div>
                        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 140px;">
                            <label>Sampai Tanggal</label>
                            <input type="date" name="tgl_selesai" class="form-control" value="<?= e($_GET['tgl_selesai'] ?? '') ?>">
                        </div>
                        <div class="filter-actions" style="display: flex; gap: 8px;">
                            <button type="submit" class="btn btn-primary" style="min-width: 90px;">🔍 Filter</button>
                            <a href="data.php" class="btn btn-secondary" style="min-width: 70px;">Reset</a>
                        </div>
                    </form>
                </div>
                
                <!-- Indikator petunjuk scroll horizontal untuk layar mobile -->
                <div class="table-scroll-hint"><span class="hint-icon">👈</span> Geser tabel ke samping untuk melihat data lengkap <span class="hint-icon">👉</span></div>
                
                <!-- Tabel daftar arsip riwayat manifest -->
                <div class="table-wrapper">
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>NO</th>
                                <th>TANGGAL</th>
                                <th>NAMA KAPAL</th>
                                <th>NOPOL</th>
                                <th>TUJUAN</th>
                                <th>JAM</th>
                                <th>AKSI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            // Kode di bawah digunakan untuk mengambil dokumen manifest dari MongoDB terurut dari yang paling baru
                            $docs = findDocuments("manifest", $filter, ['sort' => ['_id' => -1]]);
                            
                            // Menampilkan baris data jika hasil query ditemukan
                            if(count($docs) > 0) {
                                foreach($docs as $row_obj) {
                                    $row = (array)$row_obj;
                                    $id_str = (string)$row['_id'];
                            ?>
                            <tr>
                                <td><?= $no++; ?></td>
                                <!-- Menampilkan format tanggal Indonesia -->
                                <td><?= tgl_indo($row['tanggal']); ?></td>
                                <td><strong><?= e($row['kapal']); ?></strong></td>
                                <td><span style="background:#f1f5f9; padding:3px 8px; border-radius:6px; font-weight:600;"><?= e($row['nopol']); ?></span></td>
                                <td><?= e($row['tujuan']); ?></td>
                                <td><?= e($row['jam']); ?></td>
                                <td>
                                    <!-- Tombol untuk membuka pratinjau dan mencetak arsip manifest -->
                                    <a href="preview_manifest_lama.php?id=<?= e($id_str); ?>" class="btn-pill btn-pill-view">📄 Lihat / Cetak</a>
                                </td>
                            </tr>
                            <?php 
                                }
                            } else {
                                echo "<tr><td colspan='7' class='no-data' style='padding:30px; color:#64748b; text-align:center;'>Belum ada data manifest tersimpan.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
