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

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_SESSION['login_rifqy']) && $_SESSION['login_rifqy'] === 'Boss') {
    $_SESSION['error'] = 'Akses ditolak untuk pengguna Boss';
    header("Location: dashboard.php");
    exit;
}

if (isset($_GET['id'])) {
     $_SESSION['id_manifest'] = $_GET['id'];
 } elseif (!isset($_SESSION['id_manifest'])) {
     header("Location: input_keberangkatan.php");
     exit;
 }
 $id_manifest = $_SESSION['id_manifest'];

 // Cek apakah manifest ini dibuat oleh Boss (per-manifest check, bukan per-hari)
 $manifest_data = findOneDocument("manifest", ["_id" => new MongoDB\BSON\ObjectId($id_manifest)]);
 $manifest_is_boss = $manifest_data && ($manifest_data->created_by ?? '') === 'Boss';
 $is_admin = isset($_SESSION['login_rifqy']) && $_SESSION['login_rifqy'] !== 'Boss';
 $is_locked = $is_admin && !$manifest_is_boss;

// 1. MESIN UPDATE TOTAL MANUAL
if (isset($_POST['total_manual'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("CSRF token validation failed");
    }

    $total_baru = $_POST['total_manual'];
    if (!is_numeric($total_baru) || $total_baru < 0) {
        $_SESSION['error'] = 'Total ton harus berupa angka positif';
        header("Location: input_muatan.php");
        exit;
    }

    updateDocument("manifest",
        ['_id' => new MongoDB\BSON\ObjectId($id_manifest)],
        ['$set' => ['total_ton_manual' => $total_baru]]
    );
    header("Location: input_muatan.php");
    exit;
}

// 2. MESIN SIMPAN BARANG
if (isset($_POST['simpan_database'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("CSRF token validation failed");
    }

    // Server-side block jika terkunci
    if ($is_locked) {
        $_SESSION['error'] = 'Anda tidak bisa menyimpan data karena Boss belum menginputkan jadwal keberangkatan hari ini.';
        header("Location: input_muatan.php");
        exit;
    }

    $nama   = sanitize_string($_POST['nama_barang'] ?? '');
    $pcs    = filter_var($_POST['pcs_barang'] ?? 0, FILTER_VALIDATE_INT);
    $ton    = filter_var($_POST['ton_barang'] ?? 0, FILTER_VALIDATE_FLOAT);
    $vol    = sanitize_string($_POST['vol_barang'] ?? '');

    $errors = [];
    if (empty($nama)) {
        $errors[] = "Nama barang harus diisi";
    }
    if ($ton === false || $ton < 0) {
        $errors[] = "Ton harus berupa angka positif yang valid";
    }

    if (!empty($errors)) {
        $_SESSION['error'] = implode('<br>', $errors);
        header("Location: input_muatan.php");
        exit;
    }

    // Validate barang exists in master list and fetch current pcs
    // Validate barang exists in master list and fetch current pcs
    $masterObj = findOneDocument("master_barang", ["nama" => $nama]);
    if (!$masterObj) {
        $_SESSION['error'] = "MAAF! Barang [ $nama ] tidak terdaftar. Silakan pilih dari daftar yang ada atau daftarkan barang baru di kotak oranye di bawah.";
        header("Location: input_muatan.php");
        exit;
    }
    // Cast to array to access pcs
    $master = (array)$masterObj;
    // Check available pcs in storage
    $available_pcs = $master['pcs'] ?? 0;
    if ($pcs > $available_pcs) {
        $_SESSION['error'] = "Jumlah PCS yang dimasukkan ($pcs) melebihi stok tersedia ($available_pcs).";
        header("Location: input_muatan.php");
        exit;
    }

    // Insert muatan record
    insertDocument("muatan", [
        "id_manifest" => $id_manifest,
        "nama_barang" => $nama,
        "pcs" => $pcs,
        "ton" => $ton,
        "volume" => $vol
    ]);

    // Reduce pcs in master_barang storage
    updateDocument("master_barang", ["nama" => $nama], [
        '$set' => ["pcs" => $available_pcs - $pcs]
    ]);
}

// 2.5 MESIN UPDATE BARANG
if (isset($_POST['update_database'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("CSRF token validation failed");
    }

    // Server-side block jika terkunci
    if ($is_locked) {
        $_SESSION['error'] = 'Anda tidak bisa mengupdate data karena Boss belum menginputkan jadwal keberangkatan hari ini.';
        header("Location: input_muatan.php");
        exit;
    }

    $id_edit = $_POST['id_edit'];
    $nama   = sanitize_string($_POST['nama_barang'] ?? '');
    $pcs    = filter_var($_POST['pcs_barang'] ?? 0, FILTER_VALIDATE_INT);
    $ton    = filter_var($_POST['ton_barang'] ?? 0, FILTER_VALIDATE_FLOAT);
    $vol    = sanitize_string($_POST['vol_barang'] ?? '');

    $errors = [];
    if (empty($nama)) {
        $errors[] = "Nama barang harus diisi";
    }
    if ($ton === false || $ton < 0) {
        $errors[] = "Ton harus berupa angka positif yang valid";
    }

    if (!empty($errors)) {
        $_SESSION['error'] = implode('<br>', $errors);
        header("Location: input_muatan.php");
        exit;
    }

    // Validate barang exists in master list and fetch current pcs
    $masterObj = findOneDocument("master_barang", ["nama" => $nama]);
    if (!$masterObj) {
        $_SESSION['error'] = "MAAF! Update gagal. Nama barang [ $nama ] tidak terdaftar.";
        header("Location: input_muatan.php");
        exit;
    }
    // Cast to array to access pcs
    $master = (array)$masterObj;

    // Fetch existing muatan record to get previous pcs
    $oldMuatan = findOneDocument("muatan", ['_id' => new MongoDB\BSON\ObjectId($id_edit)]);
    $oldPcs = $oldMuatan ? ($oldMuatan->pcs ?? 0) : 0;

    // Calculate stock after reverting old pcs and applying new pcs
    $available_pcs = ($master['pcs'] ?? 0) + $oldPcs; // add back old pcs
    if ($pcs > $available_pcs) {
        $_SESSION['error'] = "Jumlah PCS yang dimasukkan ($pcs) melebihi stok tersedia ($available_pcs).";
        header("Location: input_muatan.php");
        exit;
    }

    // Update muatan record
    updateDocument("muatan",
        ['_id' => new MongoDB\BSON\ObjectId($id_edit)],
        ['$set' => [
            "nama_barang" => $nama,
            "pcs" => $pcs,
            "ton" => $ton,
            "volume" => $vol
        ]]
    );

    // Adjust master_barang pcs (subtract new pcs, add back old pcs difference)
    $new_stock = $available_pcs - $pcs; // resulting stock after update
    updateDocument("master_barang", ["nama" => $nama], [
        '$set' => ["pcs" => $new_stock]
    ]);

    header("Location: input_muatan.php");
    exit;
}

// 3. MESIN HAPUS BARANG
if (isset($_GET['hapus'])) {
    // Server-side block jika terkunci
    if ($is_locked) {
        $_SESSION['error'] = 'Tidak bisa menghapus karena Boss belum menginputkan jadwal keberangkatan hari ini.';
        header("Location: input_muatan.php");
        exit;
    }

    if (!isset($_GET['token']) || !verify_csrf_token($_GET['token'])) {
        die("CSRF token validation failed");
    }

    $id_hapus = $_GET['hapus'];
    // Retrieve the muatan record to restore stock
    $muatanObj = findOneDocument("muatan", ['_id' => new MongoDB\BSON\ObjectId($id_hapus)]);
    if ($muatanObj) {
        $muatan = (array)$muatanObj;
        $nama = $muatan['nama_barang'] ?? '';
        $pcs  = $muatan['pcs'] ?? 0;
        // Update master stock by adding back the pcs
        $masterObj = findOneDocument("master_barang", ['nama' => $nama]);
        if ($masterObj) {
            $master = (array)$masterObj;
            $new_stock = ($master['pcs'] ?? 0) + $pcs;
            updateDocument("master_barang", ['nama' => $nama], ['$set' => ['pcs' => $new_stock]]);
        }
    }
    // Delete the muatan entry
    deleteDocument("muatan", ['_id' => new MongoDB\BSON\ObjectId($id_hapus)]);
    header("Location: input_muatan.php");
    exit;
}

// Ambil data edit jika ada parameter ?edit
$edit_data = null;
if (isset($_GET['edit'])) {
    $id_edit = $_GET['edit'];
    $edit_obj = findOneDocument("muatan", ['_id' => new MongoDB\BSON\ObjectId($id_edit)]);
    if ($edit_obj) $edit_data = (array)$edit_obj;
}

// Ambil data header untuk ditampilkan
$h_obj = findOneDocument("manifest", ['_id' => new MongoDB\BSON\ObjectId($id_manifest)]);
$h = $h_obj ? (array)$h_obj : [];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Muatan Lengkap - CV. MANUNGGAL</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .header-info { background: var(--light-blue); border-left: 5px solid var(--primary-blue); padding: 18px; border-radius: 8px; margin-bottom: 25px; }
        .header-info-inner { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
        .header-row { display: grid; grid-template-columns: 140px 10px auto; gap: 5px; line-height: 1.6; font-size: 14px; }
        .header-row span:first-child { font-weight: 600; color: var(--text-color); }
        .master-box { margin-bottom: 25px; padding: 20px; background: #fff8e1; border-radius: 10px; border-left: 5px solid #ff9800; display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
        .master-box strong { font-size: 16px; color: #e65100; }
        .master-box input { padding: 12px; font-size: 16px; border-radius: 8px; border: 2px solid #ddd; width: 300px; }
        .master-box button { padding: 12px 20px; font-size: 16px; border-radius: 8px; }
        .row-input { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr; gap: 15px; background: #f9fbff; padding: 20px; border-radius: 12px; align-items: end; }
        .field { display: flex; flex-direction: column; position: relative; }
        .field label { font-size: 16px; font-weight: bold; color: var(--text-color); margin-bottom: 5px; }
        .form-control, #search-box {
            border-radius: 8px;
            border: 2px solid var(--border-color);
            padding: 12px;
            font-size: 16px;
            width: 100%;
            box-sizing: border-box;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .form-control:focus, #search-box:focus {
            border-color: var(--primary-blue);
            outline: none;
            box-shadow: 0 0 5px rgba(10, 77, 191, 0.3);
        }
        .dropdown-list { position: absolute; top: 100%; left: 0; right: 0; background: var(--white); border: 1px solid var(--border-color); max-height: 200px; overflow-y: auto; z-index: 1000; display: none; border-radius: 0 0 8px 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .dropdown-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 15px; border-bottom: 1px solid #eee; cursor: pointer; font-size: 14px; }
        .dropdown-item:hover { background: var(--light-blue); }
        .btn-del-list { color: var(--danger); font-weight: bold; border: 1px solid var(--danger); padding: 0 6px; border-radius: 4px; font-size: 11px; }
        .nav-actions { margin-top: 35px; display: flex; justify-content: space-between; align-items: center; gap: 12px; }
        .table-custom th:nth-child(1) { width: 10%; }
        .table-custom th:nth-child(2) { width: 25%; }
        .table-custom th:nth-child(3) { width: 15%; }
        .table-custom th:nth-child(4) { width: 15%; }
        .table-custom th:nth-child(5) { width: 15%; }
        .table-custom th:nth-child(6) { width: 20%; }
        .table-wrapper { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 8px; }

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

        /* ============================
           RESPONSIVE PORTRAIT MOBILE
        ============================ */
        @media (max-width: 768px) {
            /* Reduce page padding */
            div[style*="padding: 40px"] {
                padding: 12px !important;
            }

            /* Header info: stack info + button vertically */
            .header-info-inner {
                flex-direction: column;
                align-items: flex-start;
            }
            .header-info-inner > div {
                width: 100%;
            }
            .header-info-inner > div a {
                display: block;
                width: 100%;
                text-align: center;
                margin-top: 8px;
            }
            .header-row {
                grid-template-columns: 110px 10px auto;
                font-size: 13px;
            }

            /* Master box: stack vertically */
            .master-box {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
                padding: 15px;
            }
            .master-box input {
                width: 100%;
                box-sizing: border-box;
            }
            .master-box button {
                width: 100%;
            }

            /* Row input: Nama barang full width, 4 items on next row */
            .form-scroll-wrapper {
                width: 100%;
                margin-bottom: 15px;
            }
            .row-input {
                min-width: 0;
                grid-template-columns: 1fr 1fr 1fr auto !important; /* Override style.css */
                gap: 8px !important;
                padding: 12px !important;
                align-items: end;
            }
            /* Nama Barang spans all columns */
            .row-input .field:first-child {
                grid-column: 1 / -1 !important;
            }
            .row-input .field label {
                font-size: 11px !important;
                margin-bottom: 3px !important;
                white-space: nowrap !important;
            }
            .row-input .field .form-control,
            .row-input .field #search-box {
                padding: 6px !important;
                font-size: 12px !important;
                min-width: 0 !important;
                width: 100% !important;
            }
            .row-input > div[style*="display: flex"] {
                flex-direction: row !important;
                gap: 4px !important;
            }
            .row-input > div[style*="display: flex"] .btn {
                white-space: nowrap !important;
                padding: 6px 10px !important;
                font-size: 12px !important;
                height: 100% !important;
            }

            /* Table: full width card with scroll */
            .table-wrapper {
                margin-bottom: 12px;
                overflow-x: auto !important;
                width: 100% !important;
                -webkit-overflow-scrolling: touch;
            }
            .table-custom {
                display: table !important; /* Override display: block from style.css */
                width: 100% !important;
                min-width: 550px !important; 
                font-size: 12px !important;
            }
            .table-custom th, .table-custom td {
                padding: 8px 6px !important;
                white-space: nowrap !important;
            }

            /* Nav actions: stack vertically */
            .nav-actions {
                flex-direction: column;
                gap: 10px;
            }
            .nav-actions a {
                width: 100%;
                text-align: center;
                display: block;
            }

            /* Lock notice */
            .lock-notice {
                font-size: 13px;
                padding: 10px 12px;
            }
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

                <div class="header-info">
                    <div class="header-info-inner">
                        <div class="header-row">
                            <span>Nama Kapal</span><span>:</span><span><?= e($h['kapal'] ?? '') ?></span>
                            <span>Tanggal</span><span>:</span><span><?= e($h['tanggal'] ?? '') ?></span>
                            <span>Tujuan</span><span>:</span><span><?= e($h['tujuan'] ?? '') ?></span>
                            <span>Jenis</span><span>:</span><span><?= e($h['jenis'] ?? '') ?></span>
                            <span>Nopol</span><span>:</span><span><?= e($h['nopol'] ?? '') ?></span>
                            <span>Jam</span><span>:</span><span><?= e($h['jam'] ?? '') ?> WIB</span>
                        </div>
                        <div>
                            <a href="input_keberangkatan.php?id=<?= e($id_manifest) ?>" class="btn btn-sm btn-secondary" style="background: var(--primary-blue); color: white;">✏️ Edit Keberangkatan</a>
                        </div>
                    </div>
                </div>

                <div class="master-box">
                    <strong>Daftarkan Barang Baru:</strong>
                    <input id="inputBaru" placeholder="Ketik nama barang..." style="max-width: 300px; width: 100%;">
                    <button onclick="tambahKeMaster()" style="background: orange; color: white; border: none; cursor: pointer; padding: 0 15px; border-radius: 8px;">+ Masukkan History</button>
                </div>

                <?php if ($is_locked): ?>
                <div class="lock-notice">
                    <span class="lock-emoji">🔒</span>
                    <span>Anda harus menunggu jadwal keberangkatan dari Boss sebelum bisa menyimpan data.</span>
                </div>
                <?php endif; ?>

                <div class="form-scroll-wrapper">
                <form method="POST" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <?php if ($edit_data): ?>
                        <input type="hidden" name="id_edit" value="<?= e($_GET['edit']) ?>">
                    <?php endif; ?>
                    <div class="row-input">
                        <div class="field">
                            <label>Nama Barang (Ketik/Pilih)</label>
                            <input type="text" id="search-box" name="nama_barang" placeholder="🔍 Ketik nama barang untuk mencari atau pilih dari daftar..."
                                   onfocus="tampilkanSemua()" onkeyup="filterBarang()"
                                   value="<?= $edit_data ? e($edit_data['nama_barang']) : '' ?>" required>
                            <div id="dropdownBarang" class="dropdown-list"></div>
                        </div>
                         <div class="field"><label>PCS</label><input type="number" name="pcs_barang" class="form-control" value="<?= $edit_data ? e($edit_data['pcs']) : '' ?>"></div>
                         <div class="field"><label>Ton</label><input type="number" step="0.01" name="ton_barang" class="form-control" value="<?= $edit_data ? e($edit_data['ton']) : '' ?>" required></div>
                         <div class="field"><label>Volume</label><input type="text" name="vol_barang" class="form-control" value="<?= $edit_data ? (isset($edit_data['volume']) ? e($edit_data['volume']) : '') : '' ?>"></div>

                         <div style="display: flex; gap: 5px; align-items: flex-end;">
                             <?php if ($edit_data): ?>
                                 <button type="submit" name="update_database" class="btn btn-primary" style="width: 100%;" <?= $is_locked ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : '' ?>>Update</button>
                                 <a href="input_muatan.php" class="btn btn-secondary">Batal</a>
                             <?php else: ?>
                                 <button type="submit" name="simpan_database" class="btn btn-primary" style="width: 100%;" <?= $is_locked ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : '' ?>>Tambah</button>
                             <?php endif; ?>
                         </div>
                    </div>
                </form>
                </div>

                <div class="table-wrapper">
                <table class="table-custom">
                    <thead><tr><th>NO</th><th>NAMA BARANG</th><th>PCS</th><th>TON</th><th>VOLUME</th><th>AKSI</th></tr></thead>
                    <tbody>
                        <?php
                        $no = 1; $total_sist = 0;
                        $res = findDocuments("muatan", ["id_manifest" => $id_manifest]);
                        foreach($res as $m_obj){
                            $m = (array)$m_obj;
                            $total_sist += (float)$m['ton'];
                            $id_muatan = (string)$m['_id'];
                            $vol_tampil = isset($m['volume']) ? $m['volume'] : '';
                            $edit_link = $is_locked ? '#' : "?edit=" . e($id_muatan);
                            $hapus_link = $is_locked ? '#' : "?hapus=" . e($id_muatan) . "&token=" . e($_SESSION['csrf_token']);
                            echo "<tr><td>$no</td><td style='text-align:left; padding-left:15px;'>" . e($m['nama_barang']) . "</td><td>" . e($m['pcs']) . "</td><td>" . e($m['ton']) . "</td><td>$vol_tampil</td><td><a href='$edit_link' style='color:" . ($is_locked ? '#999' : 'orange') . "; text-decoration:none; font-weight:bold; margin-right: 10px; " . ($is_locked ? 'cursor:not-allowed;' : '') . "'>Edit</a> <a href='$hapus_link' style='color:" . ($is_locked ? '#999' : 'red') . "; text-decoration:none; font-weight:bold; " . ($is_locked ? 'cursor:not-allowed;' : '') . "'" . ($is_locked ? "" : " onclick='return confirm(\"Hapus item ini?\")'" ) . ">Hapus</a></td></tr>";
                            $no++;
                        }
                        ?>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="3" style="text-align: center;">TOTAL TON</td>
                            <td colspan="3">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                    <input type="text" name="total_manual"
                            style="width: 100px; text-align: center; font-weight: bold; border: 2px solid #0a4dbf; border-radius: 10px;"
                     value="<?= (isset($h['total_ton_manual']) && $h['total_ton_manual'] != '0' && $h['total_ton_manual'] != '') ? e($h['total_ton_manual']) : e($total_sist); ?>"
                     onchange="this.form.submit()" <?= $is_locked ? 'disabled style="opacity:0.5; cursor:not-allowed; border-color: #ccc;"' : '' ?>>
                                </form>
                            </td>
                        </tr>
                    </tfoot>
                </table>
                </div>

                <div class="nav-actions">
                    <a href="dashboard.php" style="color:var(--gray); text-decoration:none; font-weight: bold;">← Kembali</a>
                    <a href="preview_manifest.php?id=<?= e($id_manifest) ?>" class="btn btn-primary" <?= $is_locked ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : '' ?>>Lihat Manifest (Cetak) →</a>
                </div>
            </div>

<script>
let masterBrg = [];
const csrfToken = '<?= e($_SESSION['csrf_token']) ?>';

async function loadMaster() {
    try {
        let resp = await fetch('api_master_barang.php?action=get');
        masterBrg = await resp.json();
    } catch (e) {
        console.error("Gagal load master barang", e);
    }
}

function renderDropdown(data) {
    let list = document.getElementById("dropdownBarang");
    list.innerHTML = "";
    if(data.length > 0) {
        list.style.display = "block";
        data.sort().forEach((item) => {
            list.innerHTML += `
                <div class="dropdown-item">
                    <span onclick="setPilihan('${item}')" style="flex:1">${item}</span>
                    <span class="btn-del-list" onclick="hapusHistory(event, '${item}')">X</span>
                </div>`;
        });
    } else {
        list.style.display = "none";
    }
}

function filterBarang() {
    let key = document.getElementById("search-box").value.toLowerCase();
    let filtered = masterBrg.filter(i => i.toLowerCase().includes(key));
    renderDropdown(filtered);
}

function tampilkanSemua() { renderDropdown(masterBrg); }

function setPilihan(val) {
    document.getElementById("search-box").value = val;
    document.getElementById("dropdownBarang").style.display = "none";
}

async function tambahKeMaster() {
    let baru = document.getElementById("inputBaru").value.trim();
    if(baru) {
        let formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('nama', baru);
        formData.append('action', 'add');

        let resp = await fetch('api_master_barang.php', {
            method: 'POST',
            body: formData
        });
        let res = await resp.json();
        if(res.status == 'success') {
            await loadMaster();
            document.getElementById("inputBaru").value = "";
            alert("Berhasil disimpan ke Database!");
            tampilkanSemua();
        } else {
            alert("Gagal menyimpan atau barang sudah ada.");
        }
    }
}

async function hapusHistory(e, item) {
    e.stopPropagation();
    if(confirm(`Hapus "${item}" dari history Database?`)) {
        let formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('nama', item);
        formData.append('action', 'delete');

        let resp = await fetch('api_master_barang.php', {
            method: 'POST',
            body: formData
        });
        let res = await resp.json();
        if(res.status == 'success') {
            await loadMaster();
            filterBarang();
        } else {
            alert('Gagal menghapus: ' + (res.message || 'unknown error'));
        }
    }
}

loadMaster();

window.onclick = function(e) {
    if (!e.target.matches('#search-box')) {
        document.getElementById("dropdownBarang").style.display = "none";
    }
}
</script>
        </div>
    </div>
</div>

</body>
</html>