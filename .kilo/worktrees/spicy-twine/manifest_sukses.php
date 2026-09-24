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
        body { font-family: 'Segoe UI', Arial, sans-serif; background: linear-gradient(135deg, #e6f0ff, #ffffff); height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; }
        .card { background: #fff; padding: 40px; border-radius: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); text-align: center; max-width: 400px; }
        .icon { font-size: 60px; color: #4caf50; margin-bottom: 20px; }
        h2 { color: #0a4dbf; margin-bottom: 10px; }
        p { color: #666; margin-bottom: 30px; line-height: 1.6; }
        .btn-group { display: flex; flex-direction: column; gap: 10px; }
        .btn { padding: 12px; border-radius: 30px; text-decoration: none; font-weight: bold; transition: 0.3s; }
        .btn-primary { background: linear-gradient(135deg, #0a4dbf, #003b8e); color: #fff; }
        .btn-secondary { background: #eee; color: #333; border: 1px solid #ccc; }
        .btn:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
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