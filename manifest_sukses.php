<?php
session_start();
// Hapus session id_manifest biar kalau input baru nggak tercampur
unset($_SESSION['id_manifest']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selesai - CV. MANUNGGAL</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: linear-gradient(135deg, #e6f0ff, #ffffff); min-height: 100vh; min-height: 100dvh; display: flex; align-items: center; justify-content: center; margin: 0; padding: 20px 16px; box-sizing: border-box; }
        .card { background: #fff; padding: 36px 26px; border-radius: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.08); text-align: center; width: 100%; max-width: 400px; box-sizing: border-box; }
        .icon { font-size: 56px; color: #4caf50; margin-bottom: 16px; line-height: 1; }
        h2 { color: #0a4dbf; margin-bottom: 10px; font-size: 22px; font-weight: 800; }
        p { color: #666; margin-bottom: 26px; line-height: 1.6; font-size: 14px; }
        .btn-group { display: flex; flex-direction: column; gap: 12px; }
        .btn { padding: 14px; border-radius: 12px; text-decoration: none; font-weight: 700; transition: 0.3s; font-size: 15px; display: inline-flex; align-items: center; justify-content: center; min-height: 48px; }
        .btn-primary { background: linear-gradient(135deg, #0a4dbf, #003b8e); color: #fff; }
        .btn-secondary { background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        @media (max-width: 480px) {
            .card { padding: 28px 18px; border-radius: 16px; }
        }
    </style>
</head>
<body>

<div class="card">
    <div class="icon">✔</div>
    <h2>Data Tersimpan!</h2>
    <p>Manifest Cargo telah berhasil diproses dan disimpan ke dalam sistem database CV. MANUNGGAL.</p>
    
    <div class="btn-group">
        <a href="input_keberangkatan.php" class="btn btn-primary">Input Manifest Baru</a>
        <a href="data.php" class="btn btn-secondary">Lihat Semua Data</a>
    </div>
</div>

</body>
</html>