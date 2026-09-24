<?php
session_start();
include 'koneksi_mongodb.php';
include 'functions.php';

// Pengecekan login
if (!isset($_SESSION['login_rifqy'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu.';
    header("Location: login.php");
    exit;
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$is_boss = isset($_SESSION['login_rifqy']) && $_SESSION['login_rifqy'] === 'Boss';

$edit_id = $_GET['id'] ?? null;
$edit_data = null;

// Admin TIDAK BOLEH buka halaman ini langsung (tanpa ?id=).
// Satu-satunya cara Admin masuk: klik event di kalender → ?id=xxx
if (!$is_boss && !$edit_id) {
    $_SESSION['error'] = 'Akses ditolak. Silakan klik event dari Kalender terlebih dahulu.';
    header("Location: dashboard.php");
    exit;
}

if ($edit_id) {
     $obj = findOneDocument("manifest", ['_id' => new MongoDB\BSON\ObjectId($edit_id)]);
     if ($obj) $edit_data = (array)$obj;
}

// Logika lock:
// - Jika Admin klik dari kalender (ada edit_id) → boleh jika manifest dibuat oleh Boss
// - Jika Admin buat baru (tanpa edit_id) → TIDAK BOLEH (sudah diblok di atas)
// - Boss selalu boleh
if (!$is_boss && $edit_id) {
     $is_locked = !$edit_data || ($edit_data['created_by'] ?? '') !== 'Boss';
} else {
     $is_locked = false;
}

// Jika tombol "Simpan" diklik
if (isset($_POST['simpan'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("CSRF token validation failed");
    }

    // Cek apakah admin dilarang simpan karena Boss belum input jadwal
    if ($is_locked) {
        $_SESSION['error'] = 'Anda tidak bisa menyimpan data karena Boss belum menginputkan jadwal keberangkatan hari ini.';
        header("Location: " . $_SERVER['PHP_SELF'] . ($edit_id ? "?id=$edit_id" : ''));
        exit;
    }

    $kapal   = sanitize_string($_POST['kapal'] ?? '');
    $tanggal = $_POST['tanggal'] ?? '';
    $tujuan  = sanitize_string($_POST['tujuan'] ?? '');
    $jenis   = sanitize_string($_POST['jenis'] ?? '');
    $nopol   = sanitize_string($_POST['nopol'] ?? '');
    $jam     = $_POST['jam'] ?? '';

    $errors = [];
    if (empty($kapal)) $errors[] = "Nama kapal harus diisi";
    if (empty($tanggal) || !validate_date($tanggal)) $errors[] = "Format tanggal tidak valid";
    if (empty($tujuan)) $errors[] = "Tujuan harus diisi";
    if (empty($jenis)) $errors[] = "Jenis kendaraan harus diisi";
    if (empty($nopol)) $errors[] = "Nomor polisi harus diisi";
    if (empty($jam)) $errors[] = "Jam berangkat harus diisi";

    if (!empty($errors)) {
        $_SESSION['error'] = implode('<br>', $errors);
        header("Location: " . $_SERVER['PHP_SELF'] . ($edit_id ? "?id=$edit_id" : ''));
        exit;
    }

    $data = [
        "kapal" => $kapal,
        "tanggal" => $tanggal,
        "tujuan" => $tujuan,
        "jenis" => $jenis,
        "nopol" => $nopol,
        "jam" => $jam,
        "created_by" => $_SESSION['login_rifqy']
    ];

    $cek_nopol = findOneDocument("master_nopol", ["nopol" => $nopol]);
    if (!$cek_nopol) {
        insertDocument("master_nopol", ["nopol" => $nopol]);
    }

    if ($edit_id) {
        $existing = findOneDocument("manifest", ['_id' => new MongoDB\BSON\ObjectId($edit_id)]);
        $data['created_by'] = $existing->created_by ?? $_SESSION['login_rifqy'];
        updateDocument("manifest", ['_id' => new MongoDB\BSON\ObjectId($edit_id)], $data);
        $id_manifest = $edit_id;
    } else {
        $id_manifest = insertDocument("manifest", $data);
    }

    if ($id_manifest) {
        if ($is_boss) {
            $_SESSION['success'] = 'Manifest berhasil dibuat';
            header("Location: dashboard.php");
            exit;
        } else {
            $_SESSION['id_manifest'] = $id_manifest;
            header("Location: input_muatan.php");
            exit;
        }
    } else {
        echo "<script>alert('Gagal menyimpan data!');</script>";
    }
}

// Handle tambah master dari modal (?modal=kapal/jenis/nopol)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_kapal'])) {
        $nama = sanitize_string($_POST['nama'] ?? '');
        if ($nama) {
            insertDocument("master_kapal", ["nama" => $nama]);
            $_SESSION['success'] = 'Kapal baru berhasil ditambahkan';
        }
        $url = 'input_keberangkatan.php' . ($edit_id ? '?id=' . urlencode($edit_id) : '');
        header("Location: $url");
        exit;
    }
    if (isset($_POST['add_jenis'])) {
        $kode = sanitize_string($_POST['kode'] ?? '');
        if ($kode) {
            insertDocument("master_jenis", ["kode" => $kode]);
            $_SESSION['success'] = 'Jenis kendaraan baru berhasil ditambahkan';
        }
        $url = 'input_keberangkatan.php' . ($edit_id ? '?id=' . urlencode($edit_id) : '');
        header("Location: $url");
        exit;
    }
    if (isset($_POST['add_nopol'])) {
        $nopol = sanitize_string($_POST['nopol'] ?? '');
        if ($nopol) {
            insertDocument("master_nopol", ["nopol" => $nopol]);
            $_SESSION['success'] = 'Nomor polisi baru berhasil ditambahkan';
        }
        $url = 'input_keberangkatan.php' . ($edit_id ? '?id=' . urlencode($edit_id) : '');
        header("Location: $url");
        exit;
    }
}

$master_kapal = findDocuments("master_kapal", []);
$kapals = [];
foreach($master_kapal as $k) $kapals[] = $k->nama;
if(empty($kapals)) { $kapals = ['KM. DHARMA FERRY II', 'KM. DHARMA FERRY III']; }

$master_jenis = findDocuments("master_jenis", []);
$jeniss = [];
foreach($master_jenis as $j) $jeniss[] = $j->kode;
if(empty($jeniss)) { $jeniss = ['TB', 'TS']; }

$master_nopol = findDocuments("master_nopol", []);
$nopols = [];
foreach($master_nopol as $n) $nopols[] = $n->nopol;
if(empty($nopols)) { $nopols = ['H 8454 QQ', 'H 1316 PH', 'H 1370 TA', 'H 8470 QQ', 'H 9773 BQ', 'H 8211 BA', 'AA 8519 OF', 'BA 9937 FU']; }

$button_label = $edit_id ? "Simpan Perubahan" : ($is_boss ? "Simpan Jadwal" : "Selanjutnya →");

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cargo Manifest - Keberangkatan</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .container { display: flex; gap: 30px; }
        .form-container { width: 60%; position: relative; }
        .header-right { width: 40%; background: linear-gradient(160deg, var(--primary-blue), var(--dark-blue)); border-radius: 14px; padding: 30px; color: var(--white); }
        .info-box { margin-top: 22px; background: rgba(255,255,255,0.18); padding: 15px; border-radius: 10px; font-size: 14px; line-height: 1.6; }

        .lock-notice {
            background: rgba(229, 57, 53, 0.1);
            border: 1px solid rgba(229, 57, 53, 0.3);
            border-left: 4px solid var(--danger);
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: var(--danger);
            font-weight: 600;
        }
        .lock-notice .lock-emoji { font-size: 22px; }

        /* ===== MODAL - MODERN ===== */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.55);
            display: flex; align-items: center; justify-content: center; z-index: 1000;
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            animation: modalFadeIn 0.25s ease;
        }
        @keyframes modalFadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes modalSlideUp {
            from { opacity: 0; transform: translateY(30px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .modal {
            background: #fff; padding: 0; border-radius: 20px; width: 440px;
            max-width: 92vw; box-shadow: 0 24px 80px rgba(0,0,0,0.25), 0 0 0 1px rgba(255,255,255,0.1) inset;
            animation: modalSlideUp 0.3s ease; overflow: hidden;
        }
        .modal-header {
            background: linear-gradient(135deg, #0a4dbf, #003b8e);
            padding: 22px 28px; text-align: center;
        }
        .modal-header h4 { margin: 0; font-size: 17px; font-weight: 700; color: #fff; letter-spacing: 0.5px; }
        .modal-body { padding: 28px; }
        .modal-form input {
            width: 100%; padding: 13px 18px; border: 2px solid #e2e8f0;
            border-radius: 12px; margin-bottom: 14px; font-size: 15px;
            color: #2d3436; transition: border-color 0.3s, box-shadow 0.3s, background 0.3s;
            background: #f8fafc; box-sizing: border-box;
        }
        .modal-form input::placeholder { color: #a0aec0; }
        .modal-form input:focus { border-color: #0a4dbf; outline: none; background: #fff; box-shadow: 0 0 0 4px rgba(10,77,191,0.12); }
        .modal-footer { display: flex; gap: 10px; padding: 0 28px 22px; flex-direction: column; }
        .modal-footer .btn-modal-submit {
            width: 100%; padding: 13px; border: none; border-radius: 12px;
            font-size: 15px; font-weight: 700; color: #fff;
            background: linear-gradient(135deg, #0a4dbf 0%, #1565c0 50%, #1e88e5 100%);
            cursor: pointer; transition: all 0.3s ease; letter-spacing: 0.5px;
        }
        .modal-footer .btn-modal-submit:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(10,77,191,0.35); }
        .modal-footer .btn-modal-submit:active { transform: translateY(0); }
        .modal-footer a.modal-close {
            display: block; text-align: center; padding: 10px; color: #718096;
            text-decoration: none; font-size: 14px; font-weight: 600;
            border-radius: 10px; transition: all 0.3s ease; background: #f1f5f9;
        }
        .modal-footer a.modal-close:hover { background: #e2e8f0; color: #4a5568; }
        .btn-tambah {
            background: linear-gradient(135deg, #0a4dbf, #1e88e5); color: #fff;
            padding: 6px 14px; border-radius: 8px; font-size: 18px; font-weight: 700;
            border: none; cursor: pointer; transition: all 0.3s ease;
            line-height: 1.2; min-width: 40px; text-align: center; box-shadow: 0 2px 8px rgba(10,77,191,0.25);
        }
        .btn-tambah:hover { background: linear-gradient(135deg, #003b8e, #0a4dbf); transform: translateY(-2px); box-shadow: 0 4px 14px rgba(10,77,191,0.35); }
        .btn-tambah:active { transform: translateY(0); }
        .select-group { display: flex; align-items: center; gap: 8px; }
        .select-group select { flex: 1; }

        @media (max-width: 900px) {
            .container { flex-direction: column; }
            .form-container, .header-right { width: 100%; }
        }
    </style>
</head>
<body>
<div class="layout-wrapper">
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        <?php include 'top_nav.php'; ?>
        <?php if(isset($_SESSION['success'])){ echo '<div class="alert alert-success" style="padding:12px 20px; background:#d4edda; color:#155724; border-radius:8px; margin-bottom:20px; border:1px solid #c3e6cb;">'.$_SESSION['success'].'</div>'; unset($_SESSION['success']); } if(isset($_SESSION['error'])){ echo '<div class="alert alert-danger" style="padding:12px 20px; background:#f8d7da; color:#721c24; border-radius:8px; margin-bottom:20px; border:1px solid #f5c6cb;">'.$_SESSION['error'].'</div>'; unset($_SESSION['error']); } ?>
        <div style="padding: 40px;">
            <div class="container">
                <!-- FORM INPUT DI SEBELAH KIRI -->
                <div class="form-container">
                    <h3 class="header-title"><?= $edit_id ? "Edit Data Keberangkatan" : "Data Keberangkatan" ?></h3>

                    <?php if ($is_locked): ?>
                    <div class="lock-notice">
                        <span class="lock-emoji">🔒</span>
                        <span>Anda harus menunggu jadwal keberangkatan dari Boss sebelum bisa menyimpan data.</span>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                        <?php if ($edit_id): ?>
                            <input type="hidden" name="id" value="<?= e($edit_id) ?>">
                        <?php endif; ?>
                        <div class="form-group">
                            <label>Nama Kapal</label>
                            <div class="select-group">
                                <select name="kapal" class="form-control" required>
                                    <?php foreach($kapals as $k):
                                        $selected = ($edit_data && $edit_data['kapal'] == $k) ? 'selected' : '';
                                    ?>
                                        <option value="<?= e($k) ?>" <?= $selected ?>><?= e($k) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <a href="?modal=kapal<?= $edit_id ? "&id=" . e($edit_id) : '' ?>" class="btn-tambah" title="Tambah Kapal Baru">+</a>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Tanggal Keberangkatan</label>
                            <input type="date" name="tanggal" class="form-control" value="<?= e($edit_data['tanggal'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Tujuan Keberangkatan</label>
                            <input type="text" name="tujuan" class="form-control" value="<?= $edit_data ? e($edit_data['tujuan']) : 'Semarang - Ketapang' ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Jenis Kendaraan</label>
                            <div class="select-group">
                                <select name="jenis" class="form-control" required>
                                    <?php foreach($jeniss as $j):
                                        $selected = ($edit_data && $edit_data['jenis'] == $j) ? 'selected' : '';
                                    ?>
                                        <option value="<?= e($j) ?>" <?= $selected ?>><?= e($j) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <a href="?modal=jenis<?= $edit_id ? "&id=" . e($edit_id) : '' ?>" class="btn-tambah" title="Tambah Jenis Baru">+</a>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Nomor Polisi (Nopol)</label>
                            <div class="select-group">
                                <select name="nopol" class="form-control" required>
                                    <?php foreach($nopols as $n):
                                        $selected = ($edit_data && $edit_data['nopol'] == $n) ? 'selected' : '';
                                    ?>
                                        <option value="<?= e($n) ?>" <?= $selected ?>><?= e($n) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <a href="?modal=nopol<?= $edit_id ? "&id=" . e($edit_id) : '' ?>" class="btn-tambah" title="Tambah Nopol Baru">+</a>
                            </div>
                            <small style="color: var(--gray);">Pilih nomor polisi dari daftar atau tambah yang baru.</small>
                        </div>
                        <div class="form-group">
                            <label>Jam Berangkat</label>
                            <input type="time" name="jam" class="form-control" value="<?= $edit_data ? $edit_data['jam'] : '' ?>" required>
                        </div>
                        <button type="submit" name="simpan" class="btn btn-primary" style="margin-top: 15px;" <?= $is_locked ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : '' ?>>
                            <?= $button_label ?>
                        </button>
                        <?php if($edit_id && !$is_locked): ?>
                            <a href="input_muatan.php" class="btn btn-secondary" style="margin-top: 15px; margin-left: 10px;">Batal</a>
                        <?php endif; ?>
                    </form>
                </div>
                <!-- KOTAK BIRU INFO DI SEBELAH KANAN -->
                <div class="header-right">
                    <h1>CARGO MANIFEST</h1>
                    <h2>Data Keberangkatan</h2>
                    <div class="info-box">
                        <strong>Petunjuk:</strong><br>
                        • Pastikan data keberangkatan diisi lengkap<br>
                        • Nomor polisi harus sesuai STNK<br>
                        • Jam berangkat sesuai jadwal pelabuhan<br>
                        • Pastikan kembali agar Data tidak salah saat menginput<br> 
                        • Manifest CV.Manunggal Pelabuhan
                    </div>
                </div>
            </div>



        </div>
    </div>
</div>

<script>
function closeModal(e, overlay) {
    if (e.target === overlay) {
        overlay.style.opacity = '0';
        setTimeout(() => { window.location.href = window.location.href.split('?')[0]; }, 200);
    }
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const overlays = document.querySelectorAll('.modal-overlay');
        overlays.forEach(o => {
            o.style.opacity = '0';
            setTimeout(() => { window.location.href = window.location.href.split('?')[0]; }, 200);
        });
    }
});

// Auto open modal berdasarkan ?modal=kapal / jenis / nopol
document.addEventListener('DOMContentLoaded', function() {
    const params = new URLSearchParams(window.location.search);
    const m = params.get('modal');
    if (m === 'kapal') {
        document.getElementById('modal-kapal').style.display = 'flex';
    } else if (m === 'jenis') {
        document.getElementById('modal-jenis').style.display = 'flex';
    } else if (m === 'nopol') {
        document.getElementById('modal-nopol').style.display = 'flex';
    }
});
</script>

<!-- MODAL TAMBAH MASTER DATA (Improved UI) -->
<!-- Modal Kapal -->
<div id="modal-kapal" class="modal-overlay" style="display:none;" onclick="if (event.target === this) this.style.display='none'">
    <div class="modal" onclick="event.stopImmediatePropagation()">
        <div class="modal-header">
            <h4>🚢 Tambah Kapal Baru</h4>
        </div>
        <div class="modal-body">
            <form method="POST" action="">
                <div class="form-group">
                    <label>Nama Kapal</label>
                    <input type="text" name="nama" class="form-control" placeholder="Contoh: KM. DHARMA FERRY IV" required>
                </div>
                <div class="modal-footer" style="padding-top: 10px; margin-top: 10px;">
                    <button type="submit" name="add_kapal" class="btn-modal-submit">Simpan Kapal</button>
                    <a href="javascript:void(0)" onclick="document.getElementById('modal-kapal').style.display='none'" class="modal-close">Batal</a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Jenis -->
<div id="modal-jenis" class="modal-overlay" style="display:none;" onclick="if (event.target === this) this.style.display='none'">
    <div class="modal" onclick="event.stopImmediatePropagation()">
        <div class="modal-header">
            <h4>🚚 Tambah Jenis Kendaraan Baru</h4>
        </div>
        <div class="modal-body">
            <form method="POST" action="">
                <div class="form-group">
                    <label>Kode Jenis</label>
                    <input type="text" name="kode" class="form-control" placeholder="Contoh: TB / TS / LCT" required>
                </div>
                <div class="modal-footer" style="padding-top: 10px; margin-top: 10px;">
                    <button type="submit" name="add_jenis" class="btn-modal-submit">Simpan Jenis</button>
                    <a href="javascript:void(0)" onclick="document.getElementById('modal-jenis').style.display='none'" class="modal-close">Batal</a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Nopol -->
<div id="modal-nopol" class="modal-overlay" style="display:none;" onclick="if (event.target === this) this.style.display='none'">
    <div class="modal" onclick="event.stopImmediatePropagation()">
        <div class="modal-header">
            <h4>🚛 Tambah Nomor Polisi Baru</h4>
        </div>
        <div class="modal-body">
            <form method="POST" action="">
                <div class="form-group">
                    <label>Nomor Polisi (Nopol)</label>
                    <input type="text" name="nopol" class="form-control" placeholder="Contoh: H 1234 AB" required>
                </div>
                <div class="modal-footer" style="padding-top: 10px; margin-top: 10px;">
                    <button type="submit" name="add_nopol" class="btn-modal-submit">Simpan Nopol</button>
                    <a href="javascript:void(0)" onclick="document.getElementById('modal-nopol').style.display='none'" class="modal-close">Batal</a>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>