<?php
session_start();

// Pengecekan login
if (!isset($_SESSION['login_rifqy'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu.';
    header("Location: login.php");
    exit;
}

include 'koneksi_mongodb.php';
include 'functions.php';

// Initialize filter array
$filter = [];

// Sanitize and apply filters
if (!empty($_GET['kapal'])) {
    $kapal = sanitize_string($_GET['kapal']);
    $filter['kapal'] = new MongoDB\BSON\Regex($kapal, 'i');
}
if (!empty($_GET['nopol'])) {
    $nopol = sanitize_string($_GET['nopol']);
    // Escape regex special characters
    $nopol_escaped = preg_quote($nopol, '/');
    $filter['nopol'] = new MongoDB\BSON\Regex($nopol_escaped, 'i');
}
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
if (!empty($_GET['nopol'])) {
    $filter['nopol'] = new MongoDB\BSON\Regex($_GET['nopol'], 'i');
}
if (!empty($_GET['tgl_mulai']) && !empty($_GET['tgl_selesai'])) {
    $filter['tanggal'] = ['$gte' => $_GET['tgl_mulai'], '$lte' => $_GET['tgl_selesai']];
} elseif (!empty($_GET['tgl_mulai'])) {
    $filter['tanggal'] = ['$gte' => $_GET['tgl_mulai']];
} elseif (!empty($_GET['tgl_selesai'])) {
    $filter['tanggal'] = ['$lte' => $_GET['tgl_selesai']];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Manifest - CV. MANUNGGAL</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .no-data { padding: 40px; color: #999; text-align: center; }
    </style>
</head>
<body>

<div class="layout-wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <?php include 'top_nav.php'; ?>
        <div style="padding: 40px;">

<div class="container">
    <h3 class="header-title">Riwayat Manifest Cargo</h3>
    
    <div style="background: #f9fbff; padding: 20px; border-radius: 12px; margin-bottom: 25px; border: 1px solid var(--border-color);">
        <form method="GET" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
            <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
                <label>Nama Kapal</label>
                <input type="text" name="kapal" class="form-control" placeholder="Cari kapal..." value="<?= e($_GET['kapal'] ?? '') ?>">
            </div>
            <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
                <label>Nomor Polisi</label>
                <input type="text" name="nopol" class="form-control" placeholder="Cari nopol..." value="<?= e($_GET['nopol'] ?? '') ?>">
            </div>
            <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
                <label>Dari Tanggal</label>
                <input type="date" name="tgl_mulai" class="form-control" value="<?= e($_GET['tgl_mulai'] ?? '') ?>">
            </div>
            <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
                <label>Sampai Tanggal</label>
                <input type="date" name="tgl_selesai" class="form-control" value="<?= e($_GET['tgl_selesai'] ?? '') ?>">
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary">🔍 Filter</button>
                <a href="data.php" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>
    
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
            // Ambil data manifest diurutkan dari yang terbaru dengan filter
            $docs = findDocuments("manifest", $filter, ['sort' => ['_id' => -1]]);
            
            if(count($docs) > 0) {
                foreach($docs as $row_obj) {
                    $row = (array)$row_obj;
                    $id_str = (string)$row['_id'];
            ?>
            <tr>
                <td><?= $no++; ?></td>
                <td><?= tgl_indo($row['tanggal']); ?></td>
                <td><strong><?= e($row['kapal']); ?></strong></td>
                <td><?= e($row['nopol']); ?></td>
                <td><?= e($row['tujuan']); ?></td>
                <td><?= e($row['jam']); ?></td>
                <td>
                    <a href="preview_manifest_lama.php?id=<?= e($id_str); ?>" class="btn btn-primary btn-sm">Lihat / Cetak</a>
                </td>
            </tr>
            <?php 
                }
            } else {
                echo "<tr><td colspan='7' class='no-data'>Belum ada data manifest tersimpan.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>

        </div>
    </div>
</div>

</body>
</html>