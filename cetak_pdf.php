<?php
ob_start();
session_start();

// Pengecekan login
if (!isset($_SESSION['login_rifqy'])) {
    ob_end_clean();
    $_SESSION['error'] = 'Silakan login terlebih dahulu.';
    header("Location: login.php");
    exit;
}

require 'vendor/autoload.php';
include 'koneksi_mongodb.php';
include 'functions.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$id = $_GET['id'] ?? '';
if (!$id || !preg_match('/^[a-f0-9]{24}$/', $id)) {
    die("ID Manifest tidak valid.");
}

try {
    // Data Manifest
    $h_obj = findOneDocument("manifest", ['_id' => new MongoDB\BSON\ObjectId($id)]);
    if (!$h_obj) die("Data manifest tidak ditemukan.");
    $h = (array)$h_obj;

    // Data Muatan
    $muatan = findDocuments("muatan", ['id_manifest' => $id]);

    // HTML Content with proper escaping
    $html = '
    <html>
    <head>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #333; padding: 20px; }
        .header-box { background: #0a4dbf; color: #ffffff; padding: 25px; text-align: center; border-radius: 12px; margin-bottom: 30px; }
        .header-box h1 { margin: 0; font-size: 22px; font-weight: bold; }
        .header-box h2 { margin: 8px 0 0 0; font-size: 14px; color: #ffcc80; font-weight: normal; text-transform: uppercase; letter-spacing: 2px; }
        
        .info-card { background: #ffffff; border-left: 6px solid #0a4dbf; padding: 20px; border-radius: 10px; margin-bottom: 30px; border: 1px solid #eee; line-height: 2; }
        .info-table { width: 100%; border-collapse: collapse; }
        .info-label { width: 100px; font-weight: normal; color: #555; }
        .info-sep { width: 20px; text-align: center; }
        
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; border: 2px solid #000; }
        .data-table th { background: #0a4dbf; color: #ffffff; padding: 12px; text-align: center; border: 1px solid #000; font-weight: bold; font-size: 12px; }
        .data-table td { padding: 10px; border: 1px solid #000; text-align: center; background: #fff; }
        .total-row { background: #fff3e0 !important; font-weight: bold; }
        
        .signature-section { margin-top: 50px; float: right; text-align: center; width: 250px; }
        .signature-date { margin-bottom: 10px; }
        .signature-name { margin-top: 60px; font-weight: bold; font-size: 13px; }
    </style>
    </head>
    <body>
        <div class="header-box">
            <h1>CV. MANUNGGAL</h1>
            <h2>CARGO MANIFEST</h2>
        </div>

        <div class="info-card">
            <table class="info-table">
                <tr><td class="info-label">Nama Kapal</td><td class="info-sep">:</td><td>' . e($h['kapal'] ?? '-') . '</td></tr>
                <tr><td class="info-label">Tanggal</td><td class="info-sep">:</td><td>' . e(tgl_indo($h['tanggal'] ?? '')) . '</td></tr>
                <tr><td class="info-label">Tujuan</td><td class="info-sep">:</td><td>' . e($h['tujuan'] ?? '-') . '</td></tr>
                <tr><td class="info-label">Jenis</td><td class="info-sep">:</td><td>' . e($h['jenis'] ?? '-') . '</td></tr>
                <tr><td class="info-label">Nopol</td><td class="info-sep">:</td><td>' . e($h['nopol'] ?? '-') . '</td></tr>
                <tr><td class="info-label">Jam</td><td class="info-sep">:</td><td>' . e($h['jam'] ?? '-') . ' WIB</td></tr>
            </table>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th width="40">NO</th>
                    <th>NAMA BARANG</th>
                    <th width="80">PCS</th>
                    <th width="100">TON</th>
                    <th width="120">VOLUME</th>
                </tr>
            </thead>
            <tbody>';

    $no = 1;
    $total_ton = 0;
    foreach ($muatan as $m) {
        $m_arr = (array)$m;
        $html .= '
            <tr>
                <td>' . $no++ . '</td>
                <td>' . e($m_arr['nama_barang'] ?? '-') . '</td>
                <td>' . e($m_arr['pcs'] ?? '0') . '</td>
                <td>' . e($m_arr['ton'] ?? '0') . '</td>
                <td>' . e($m_arr['volume'] ?? '-') . '</td>
            </tr>';
        $total_ton += (float)($m_arr['ton'] ?? 0);
    }

    $final_ton = (isset($h['total_ton_manual']) && $h['total_ton_manual'] != '0' && $h['total_ton_manual'] != '') ? $h['total_ton_manual'] : $total_ton;

    $html .= '
            <tr class="total-row">
                <td colspan="3">TOTAL</td>
                <td>± ' . $final_ton . ' Ton</td>
                <td></td>
            </tr>
            </tbody>
        </table>

        <div class="signature-section">
            <div class="signature-date">Semarang,</div>
            <div>Hormat Kami,</div>
            <div class="signature-name">CV. MANUNGGAL</div>
        </div>
    </body>
    </html>';

    // Dompdf configuration
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Pastikan tidak ada output sampah sebelum stream
    ob_end_clean();
    
    // Stream PDF with safe filename
    $safe_kapal = preg_replace('/[^a-zA-Z0-9_-]/', '_', $h['kapal'] ?? 'Data');
    $safe_tanggal = preg_replace('/[^0-9_-]/', '_', $h['tanggal'] ?? date('Y-m-d'));
    $filename = "Manifest_{$safe_kapal}_{$safe_tanggal}.pdf";
    $dompdf->stream($filename, ["Attachment" => 1]);

} catch (Exception $e) {
    ob_end_clean();
    die("Kesalahan Generate PDF: " . $e->getMessage());
}
?>
