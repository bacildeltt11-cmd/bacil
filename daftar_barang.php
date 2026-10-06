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

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$is_boss = isset($_SESSION['login_rifqy']) && $_SESSION['login_rifqy'] === 'Boss';
$is_admin = !$is_boss;

// Handle tambah barang (hanya admin)
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_barang'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("CSRF token validation failed");
    }
    $nama = sanitize_string($_POST['nama_barang'] ?? '');
    $pcs = intval($_POST['pcs'] ?? 0);
    if (!empty($nama)) {
        $exist = findOneDocument("master_barang", ['nama' => $nama]);
        if (!$exist) {
            insertDocument("master_barang", ['nama' => $nama, 'pcs' => $pcs]);
            $_SESSION['success_msg'] = 'Daftar barang berhasil ditambahkan';
        } else {
            $_SESSION['error'] = 'Barang sudah terdaftar.';
        }
    } else {
        $_SESSION['error'] = 'Nama barang tidak boleh kosong.';
    }
    header("Location: daftar_barang.php");
    exit;
}

// Handle hapus barang (hanya admin)
if ($is_admin && isset($_GET['hapus'])) {
    $nama = sanitize_string($_GET['hapus']);
    if (!empty($nama)) {
        deleteDocument("master_barang", ['nama' => $nama]);
        $_SESSION['success_msg'] = 'Barang berhasil dihapus dari daftar.';
    }
    header("Location: daftar_barang.php");
    exit;
}

// Handle update pcs (edit) for master barang (admin)
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_barang'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("CSRF token validation failed");
    }
    $nama = sanitize_string($_POST['nama_barang'] ?? '');
    $pcs  = intval($_POST['pcs'] ?? 0);
    if (!empty($nama)) {
        $existing = findOneDocument("master_barang", ['nama' => $nama]);
        if ($existing) {
            updateDocument("master_barang", ['nama' => $nama], ['$set' => ['pcs' => $pcs]]);
            $_SESSION['success_msg'] = "PCS untuk barang '$nama' berhasil diubah.";
        } else {
            $_SESSION['error'] = "Barang tidak ditemukan untuk diedit.";
        }
    } else {
        $_SESSION['error'] = "Nama barang tidak boleh kosong.";
    }
    header("Location: daftar_barang.php");
    exit;
}

// If admin requests edit form, load data
$edit_data = null;
if ($is_admin && isset($_GET['edit'])) {
    $edit_nama = sanitize_string($_GET['edit']);
    $edit_obj = findOneDocument("master_barang", ['nama' => $edit_nama]);
    if ($edit_obj) $edit_data = (array)$edit_obj;
}

// Ambil data master barang
$docs = findDocuments("master_barang", [], ['sort' => ['nama' => 1]]);
$barang_list = [];
foreach ($docs as $d) {
    $arr = (array)$d;
    if (!empty($arr['nama'])) {
        $barang_list[] = ['nama' => $arr['nama'], 'pcs' => $arr['pcs'] ?? 0];
    }
}

if (empty($barang_list)) {
    $default_names = ["Alpukat","Apel","Bawang Putih","Bawang Goreng","Bombay","Brambang","Cabe","Emping","Garam","Gula","Jipan","Kacang Hijau","Kacang Tanah","Kemiri","Kentang","Kertas","Ketan","Kol","Krupuk","Kunir","Mangga","Plastik","Rempah","Salak","Sawi","Telur","Tomat","Terong","Wortel","Trasi","Kurma","Keluak","Kacang kupas"];
    foreach ($default_names as $name) {
        $barang_list[] = ['nama' => $name, 'pcs' => 0];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Barang - CV. MANUNGGAL</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .barang-tag {background: linear-gradient(135deg, #e0f0ff, #c3d9f0);color:#0a4dbf;padding:8px 16px;border-radius:999px;font-size:14px;font-weight:600;display:inline-flex;align-items:center;gap:8px;box-shadow:0 1px 3px rgba(0,0,0,0.1);transition:transform 0.2s;}
        .barang-tag:hover {transform:translateY(-1px);}
        .barang-tag .del {color:#e53935;font-weight:900;font-size:16px;text-decoration:none;line-height:1;margin-left:4px;}
        .barang-tag .del:hover {color:#c62828;}
        .container-barang {background:#fff;padding:24px;border-radius:16px;border:1px solid var(--border-color);margin-top:20px;}
        .success-popup {position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#d4edda;color:#155724;padding:22px 35px;border-radius:14px;box-shadow:0 15px 40px rgba(0,0,0,0.25);z-index:9999;text-align:center;font-size:17px;font-weight:700;border:3px solid #c3e6cb;min-width:280px;}
        .success-popup .check {font-size:28px;display:block;margin-bottom:6px;}
        .del {color:#e53935;font-weight:900;font-size:16px;text-decoration:none;}
    </style>
</head>
<body>
<div class="layout-wrapper">
<?php include 'sidebar.php'; ?>
<div class="main-content">
<?php include 'top_nav.php'; ?>
<div class="content-wrapper">
    <h3 class="header-title">📦 Daftar Barang Master</h3>
    <?php
    if (isset($_SESSION['error'])) {
        echo '<div class="alert alert-danger" style="margin: 15px 0; padding:12px 20px; background:#f8d7da; color:#721c24; border-radius:8px; border:1px solid #f5c6cb;">' . $_SESSION['error'] . '</div>';
        unset($_SESSION['error']);
    }
    if (isset($_SESSION['success_msg'])) {
        echo '<script>window.__barangSuccess = ' . json_encode($_SESSION['success_msg']) . ';</script>';
        unset($_SESSION['success_msg']);
    }
    ?>
    <?php if ($is_admin): ?>
    <div class="add-barang-box" style="background:#fff8e1; border-left:5px solid #ff9800; padding:18px 20px; border-radius:12px; margin-bottom:20px;">
        <strong style="color:#e65100; font-size: 15px;">➕ Tambah Barang Baru</strong>
        <form method="POST" class="add-barang-form" style="margin-top:12px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
            <input type="text" name="nama_barang" class="form-control" placeholder="Contoh: Durian, Jeruk, dll" required autocomplete="off" style="flex:2; min-width:180px;">
            <input type="number" name="pcs" class="form-control" placeholder="PCS (angka)" min="0" required style="flex:1; min-width:110px;">
            <button type="submit" name="add_barang" class="btn btn-primary" style="min-width:140px;">+ Tambah ke Daftar</button>
        </form>
        <small style="color:#856404; display:block; margin-top:8px; font-size:12px;">Admin dapat menambahkan nama barang baru yang akan tersedia untuk dipilih saat input muatan.</small>
    </div>
    <?php else: ?>
    <div style="background:#f0f4f8; padding:14px 18px; border-radius:10px; margin-bottom:20px; font-size:13px; color:#555;">
        <strong>Mode Monitoring (Boss)</strong> — Daftar ini hanya untuk dilihat. Penambahan barang baru hanya dapat dilakukan oleh Admin.
    </div>
    <?php endif; ?>
    <div class="container-barang">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
            <h4 style="margin:0; color:#0a4dbf; font-size: 16px;">Daftar Barang yang Tersedia (<?php echo count($barang_list); ?>)</h4>
            <div style="position:relative; max-width:320px; width:100%;">
                <input type="text" id="search-barang" class="form-control" placeholder="Cari nama barang..." style="padding-left:35px; width:100%; box-sizing:border-box; border-radius:20px;">
                <span style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#888; font-size:14px;">🔍</span>
            </div>
        </div>
        <?php if (!empty($barang_list)): ?>
        <div class="table-scroll-hint"><span class="hint-icon">👈</span> Geser tabel ke samping untuk melihat data lengkap <span class="hint-icon">👉</span></div>
        <div class="table-wrapper">
        <table id="barang-table" class="barang-table" style="width:100%; border-collapse:collapse;">
            <thead>
                <tr style="background:#f0f4f8;">
                    <th style="padding:12px 14px; text-align:left;">Nama Barang</th>
                    <th style="padding:12px 14px; text-align:left;">PCS</th>
                    <?php if ($is_admin): ?><th style="padding:12px 14px; text-align:center;">Aksi</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($barang_list as $item): ?>
                <tr data-name="<?php echo htmlspecialchars(strtolower($item['nama'])); ?>">
                    <td style="padding:12px 14px; border-bottom:1px solid #eee; font-weight:600;"><?php echo e($item['nama']); ?></td>
                    <td style="padding:12px 14px; border-bottom:1px solid #eee;"><span style="background:#f1f5f9; padding:4px 10px; border-radius:6px; font-weight:700;"><?php echo e($item['pcs']); ?></span></td>
                    <?php if ($is_admin): ?>
                    <td style="padding:10px 14px; border-bottom:1px solid #eee; text-align:center;">
                        <div class="action-btn-group">
                            <a href="javascript:void(0);" class="btn-pill btn-pill-edit edit" data-nama="<?php echo e($item['nama']); ?>" data-pcs="<?php echo e($item['pcs']); ?>" title="Edit PCS">✏️ Edit</a>
                            <a href="?hapus=<?php echo urlencode($item['nama']); ?>" class="btn-pill btn-pill-del del" title="Hapus Barang" onclick="return confirm('Hapus \'<?php echo e($item['nama']); ?>\' dari daftar?')">🗑️ Hapus</a>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php else: ?>
        <p style="color:#888; padding:20px; text-align:center;">Belum ada daftar barang.</p>
        <?php endif; ?>
    </div>
    <?php if ($is_admin): ?>
    <div id="edit_modal" class="modal-overlay" style="display:none;" onclick="if (event.target === this) this.style.display='none'">
        <div class="modal" onclick="event.stopImmediatePropagation()">
            <div class="modal-header">
                <h4>✏️ Edit Stok Barang</h4>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                    <div class="form-group">
                        <label>Nama Barang</label>
                        <input type="text" name="nama_barang" class="form-control" placeholder="Nama Barang" value="" required readonly style="background:#f1f5f9;">
                    </div>
                    <div class="form-group">
                        <label>Jumlah PCS</label>
                        <input type="number" name="pcs" class="form-control" placeholder="PCS (angka)" min="0" required value="">
                    </div>
                    <div style="display:flex; gap:10px; margin-top:20px;">
                        <button type="submit" name="update_barang" class="btn btn-primary" style="flex:1;">Simpan Perubahan</button>
                        <button type="button" id="edit_close" class="btn btn-secondary">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
</div>
</div>
<script>
function showSuccessPopup(message) {
    var popup = document.createElement('div');
    popup.className = 'success-popup';
    popup.innerHTML = '<span class="check">✅</span>' + message;
    document.body.appendChild(popup);
    setTimeout(function(){ if(popup && popup.parentNode){ popup.parentNode.removeChild(popup); } }, 2000);
}

// Edit button handler (JS)
document.querySelectorAll('.edit').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        var nama = this.getAttribute('data-nama');
        var pcs = this.getAttribute('data-pcs');
        // Populate modal form fields
        var modal = document.getElementById('edit_modal');
        var form = document.getElementById('edit_form');
        if (!modal || !form) return;
        form.querySelector('input[name="nama_barang"]').value = nama;
        form.querySelector('input[name="pcs"]').value = pcs;
        modal.style.display = 'flex';
    });
});

// Close modal
var closeBtn = document.getElementById('edit_close');
if (closeBtn) {
    closeBtn.addEventListener('click', function(){
        document.getElementById('edit_modal').style.display = 'none';
    });
}

document.getElementById('search-barang')?.addEventListener('input', function(e){
    const searchTerm = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('#barang-table tbody tr');
    let hasVisible = false;
    rows.forEach(row => {
        const name = row.getAttribute('data-name');
        if(name.includes(searchTerm)) { row.style.display = ''; hasVisible = true; }
        else { row.style.display = 'none'; }
    });
    let emptyMsg = document.getElementById('empty-search-msg');
    if(!hasVisible && searchTerm !== '') {
        if(!emptyMsg) {
            emptyMsg = document.createElement('p');
            emptyMsg.id = 'empty-search-msg';
            emptyMsg.style.color = '#888';
            emptyMsg.style.width = '100%';
            emptyMsg.style.textAlign = 'center';
            emptyMsg.style.marginTop = '20px';
            emptyMsg.textContent = 'Barang "' + e.target.value + '" tidak ditemukan.';
            document.getElementById('barang-table').parentNode.appendChild(emptyMsg);
        } else { emptyMsg.textContent = 'Barang "' + e.target.value + '" tidak ditemukan.'; emptyMsg.style.display = 'block'; }
    } else if(emptyMsg) { emptyMsg.style.display = 'none'; }
});

if (window.__barangSuccess) {
    setTimeout(function(){ showSuccessPopup(window.__barangSuccess); }, 350);
}
</script>
</body>
</html>
