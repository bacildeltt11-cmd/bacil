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

// Cek apakah ada ID Manifest
if (!isset($_SESSION['id_manifest'])) {
    header("Location: input_keberangkatan.php");
    exit;
}

$id_manifest = $_SESSION['id_manifest'];

// Ambil data header kapal dari database
$h_obj = findOneDocument("manifest", ['_id' => new MongoDB\BSON\ObjectId($id_manifest)]);
$h = $h_obj ? (array)$h_obj : [];

// Fungsi format tanggal Indonesia (dari functions.php)
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cargo Manifest - Preview</title>
    <link rel="stylesheet" href="style.css">
<style>
/* ===== HILANGKAN WATERMARK / HEADER / FOOTER BROWSER SAAT CETAK ===== */
@page {
    size: A4 portrait;
    margin: 0;
}
@top-left { content: none; }
@top-center { content: none; }
@top-right { content: none; }
@bottom-left { content: none; }
@bottom-center { content: none; }
@bottom-right { content: none; }

/* CSS PERSIS SEPERTI DESAIN KAMU */
body { font-family: 'Segoe UI', Arial, sans-serif; background: var(--bg-color) !important; margin: 0; padding: 0; }
.kertas { background: #fff; padding: 50px; max-width: 850px; margin: 0 auto 40px auto; border-radius: 8px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); border: 1px solid #ddd; }

/* ===== HEADER MIRIP PDF ===== */
.header-box {
    background: #0a4dbf;
    color: #ffffff;
    padding: 30px;
    text-align: center;
    border-radius: 12px;
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
}
.header-box::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, transparent, #ffcc80, #ffb74d, #ffcc80, transparent);
}
.header-box h1 {
    margin: 0;
    font-size: 26px;
    font-weight: 800;
    letter-spacing: 3px;
}
.header-box h2 {
    margin: 8px 0 0;
    font-size: 14px;
    color: #ffcc80;
    font-weight: normal;
    text-transform: uppercase;
    letter-spacing: 4px;
}

.info{line-height:2; margin-bottom:30px; background:#fff; padding:25px; border-left:6px solid #0a4dbf; border-radius:10px; border: 1px solid #eee;}
table{width:100%;border-collapse:collapse;background:#fff;}
th{background:#0a4dbf;color:#fff; border: 1px solid #000; padding: 12px;}
th,td{border:1px solid #000;padding:10px;text-align:center;}
.total-row td{font-weight:bold;background:#fff3e0;}
.footer{margin-top:60px; display:flex; justify-content:flex-end;}
.footer-content{text-align:center;}
.hormat{margin:15px 0 70px 0;}
.actions {
    margin-top: 30px;
    display: flex;
    justify-content: center;
    gap: 15px;
    padding-bottom: 50px;
}
.btn-print, .btn-back, .btn-next {
    padding: 12px 25px;
    border-radius: 30px;
    font-weight: bold;
    text-decoration: none;
    cursor: pointer;
    border: none;
    transition: all 0.3s;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.btn-print { background: #fff; border: 2px solid var(--primary-blue); color: var(--primary-blue); }
.btn-print:hover { background: var(--light-blue); }
.btn-back { background: #666; color: #fff; }
.btn-back:hover { background: #444; }
.btn-next { 
    background: linear-gradient(135deg, #4caf50, #2e7d32); 
    color: #fff; 
}
.btn-next:hover { 
    opacity: 0.9; 
    transform: translateY(-2px); 
}

@media print{
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .actions, .sidebar, .btn-toggle, .top-nav { display:none !important; }
    body { background: #fff !important; padding: 0; }
    .main-content { padding: 0 !important; }
    .kertas { box-shadow: none; border: none; padding: 0; width: 100%; max-width: 100%; }
    .header-box { background: #0a4dbf !important; color: #fff !important; border-radius: 0; }
    .header-box::after { background: linear-gradient(90deg, transparent, #ffcc80, #ffb74d, #ffcc80, transparent) !important; }
    .header-box h2 { color: #ffcc80 !important; }
    th { background: #0a4dbf !important; color: #fff !important; }
    .total-row td { background: #fff3e0 !important; }
}
</style>
</head>
<body>

<div class="layout-wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <?php include 'top_nav.php'; ?>
        <div style="padding: 40px;">

<!-- HEADER MIRIP PDF -->
<div class="header-box">
    <h1>CV. MANUNGGAL</h1>
    <h2>CARGO MANIFEST</h2>
</div>

<div class="info">
        <div style="display:grid;grid-template-columns:max-content max-content 1fr;gap:5px 10px">
            <div>Nama Kapal</div><div>:</div><div><?= e($h['kapal'] ?? '') ?></div>
            <div>Tanggal</div><div>:</div><div><?= tgl_indo($h['tanggal'] ?? '') ?></div>
            <div>Tujuan</div><div>:</div><div><?= e($h['tujuan'] ?? '') ?></div>
            <div>Jenis</div><div>:</div><div><?= e($h['jenis'] ?? '') ?></div>
            <div>Nopol</div><div>:</div><div><?= e($h['nopol'] ?? '') ?></div>
            <div>Jam</div><div>:</div><div><?= e($h['jam'] ?? '') ?> WIB</div>
        </div>
</div>

<table>
<thead>
<tr>
    <th>NO</th>
    <th>NAMA BARANG</th>
    <th>PCS</th>
    <th>TON</th>
    <th>VOLUME</th>
</tr>
</thead>
<tbody>
    <?php
    $no = 1;
    $total_ton = 0;
    // Ambil data muatan dari database
    $muatan_docs = findDocuments("muatan", ["id_manifest" => $id_manifest]);
    foreach($muatan_docs as $row_obj){
        $row = (array)$row_obj;
        $total_ton += (float)$row['ton'];
    ?>
    <tr>
        <td><?= $no++ ?></td>
        <td style="text-align:left; padding-left:10px;"><?= e($row['nama_barang']) ?></td>
        <td><?= e($row['pcs']) ?></td>
        <td><?= e($row['ton']) ?></td>
        <td><?= isset($row['volume']) && $row['volume'] ? e($row['volume']) : "-" ?></td>
    </tr>
    <?php } ?>
</tbody>
<tfoot>
<tr class="total-row">
    <td colspan="3">TOTAL</td>
    <td>± <?php 
        // LOGIKA PERBAIKAN: Menggunakan total_ton_manual jika ada dan tidak kosong/nol
        $total_ton_display = (isset($h['total_ton_manual']) && $h['total_ton_manual'] != '0' && $h['total_ton_manual'] != '') ? $h['total_ton_manual'] : $total_ton;
        echo $total_ton_display; 
    ?> Ton</td>
    <td></td>
</tr>
</tfoot>
</table>

<div class="footer">
    <div class="footer-content">
        <div>Semarang, <?= tgl_indo($h['tanggal'] ?? '') ?></div>
        <div class="hormat">Hormat Kami,</div>
        <strong>CV. MANUNGGAL</strong>
    </div>
</div>

<div class="actions">
    <a href="input_muatan.php" class="btn-back">← Kembali ke Input</a>
    <div style="display: flex; gap: 10px;">
        <button class="btn-print" onclick="window.print()">🖨️ Cetak (Browser)</button>
        <a href="cetak_pdf.php?id=<?= $id_manifest ?>" class="btn-print" style="text-decoration:none; display:inline-block; line-height:20px;">📄 Download PDF</a>
        <a href="dashboard.php" class="btn-next">Selesai ✓</a>
    </div>
</div>

    </div>
</div>

</body>
</html>