<?php
require_once 'koneksi_mongodb.php';
require_once 'functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pengecekan login
if (!isset($_SESSION['login_rifqy'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu.';
    header("Location: login.php");
    exit;
}

// Cek apakah ada ID Manifest dari URL GET atau Session
if (isset($_GET['id']) && preg_match('/^[a-f0-9]{24}$/', $_GET['id'])) {
    $_SESSION['id_manifest'] = $_GET['id'];
}

if (!isset($_SESSION['id_manifest'])) {
    header("Location: input_keberangkatan.php");
    exit;
}

$id_manifest = $_SESSION['id_manifest'];

// Ambil data header kapal dari database
$h_obj = findOneDocument("manifest", ['_id' => new MongoDB\BSON\ObjectId($id_manifest)]);
$h = $h_obj ? (array)$h_obj : [];

// Handler ketika tombol 'Selesai ✓' diklik
if (isset($_GET['action']) && $_GET['action'] === 'selesai') {
    if ($h && (!isset($h['status']) || $h['status'] !== 'Selesai')) {
        updateDocument("manifest", 
            ['_id' => new MongoDB\BSON\ObjectId($id_manifest)],
            ['$set' => ['status' => 'Selesai']]
        );
    }
    // Hapus id_manifest aktif dari session agar tidak tercampur
    unset($_SESSION['id_manifest']);
    header("Location: dashboard.php");
    exit;
}

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

/* CSS DOKUMEN ASLI */
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

/* ===== TOMBOL AKSI MODERN & PREMIUM ===== */
.actions {
    margin-top: 36px;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 12px;
    padding-bottom: 50px;
    flex-wrap: wrap;
}

.action-buttons-group {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}

.btn-action-modern {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 22px;
    font-size: 14px;
    font-weight: 700;
    border-radius: 12px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 6px rgba(0,0,0,0.06);
    line-height: 1.2;
}

.btn-action-modern:active {
    transform: scale(0.98);
}

/* Tombol Kembali */
.btn-act-back {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #cbd5e1;
}
.btn-act-back:hover {
    background: #e2e8f0;
    color: #0f172a;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

/* Tombol Cetak Browser */
.btn-act-print {
    background: #eff6ff;
    color: #1d4ed8;
    border: 1.5px solid #93c5fd;
}
.btn-act-print:hover {
    background: #dbeafe;
    color: #1e40af;
    transform: translateY(-2px);
    box-shadow: 0 4px 14px rgba(29, 78, 216, 0.15);
}

/* Tombol Download PDF */
.btn-act-pdf {
    background: #fff;
    color: #dc2626;
    border: 1.5px solid #fca5a5;
}
.btn-act-pdf:hover {
    background: #fef2f2;
    color: #b91c1c;
    border-color: #f87171;
    transform: translateY(-2px);
    box-shadow: 0 4px 14px rgba(220, 38, 38, 0.15);
}

/* Tombol Selesai (Setuju / Selesai) */
.btn-act-success {
    background: linear-gradient(135deg, #10b981, #059669);
    color: #ffffff;
    border: 1.5px solid transparent;
    box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
}
.btn-act-success:hover {
    background: linear-gradient(135deg, #059669, #047857);
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(16, 185, 129, 0.45);
}

@media print{
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .actions, .sidebar, .btn-toggle, .top-nav, .mobile-bottom-nav, .table-scroll-hint { display:none !important; }
    body { background: #fff !important; padding: 0; }
    .main-content { padding: 0 !important; }
    .kertas { box-shadow: none; border: none; padding: 0; width: 100%; max-width: 100%; }
    .header-box { background: #0a4dbf !important; color: #fff !important; border-radius: 0; }
    .header-box::after { background: linear-gradient(90deg, transparent, #ffcc80, #ffb74d, #ffcc80, transparent) !important; }
    .header-box h2 { color: #ffcc80 !important; }
    th { background: #0a4dbf !important; color: #fff !important; }
    .total-row td { background: #fff3e0 !important; }
}

@media (max-width: 600px) {
    .actions, .action-buttons-group {
        flex-direction: column;
        width: 100%;
    }
    .btn-action-modern {
        width: 100%;
    }
}
</style>
</head>
<body>

<div class="layout-wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <?php include 'top_nav.php'; ?>
        <div class="content-wrapper">

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

<div class="table-scroll-hint"><span class="hint-icon">👈</span> Geser tabel ke samping untuk melihat data lengkap <span class="hint-icon">👉</span></div>
<div class="table-wrapper">
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
</div>

<div class="footer">
    <div class="footer-content">
        <div>Semarang, <?= tgl_indo($h['tanggal'] ?? '') ?></div>
        <div class="hormat">Hormat Kami,</div>
        <strong>CV. MANUNGGAL</strong>
    </div>
</div>

<div class="actions">
    <a href="input_muatan.php<?= isset($id_manifest) ? '?id=' . urlencode($id_manifest) : '' ?>" class="btn-action-modern btn-act-back">
        ← Kembali ke Input
    </a>
    <div class="action-buttons-group">
        <button type="button" class="btn-action-modern btn-act-print" onclick="window.print()">
            🖨️ Cetak (Browser)
        </button>
        <a href="cetak_pdf.php?id=<?= e($id_manifest) ?>" class="btn-action-modern btn-act-pdf">
            📄 Download PDF
        </a>
        <a href="preview_manifest.php?id=<?= e($id_manifest) ?>&action=selesai" class="btn-action-modern btn-act-success">
            Selesai ✓
        </a>
    </div>
</div>

    </div>
</div>

</body>
</html>