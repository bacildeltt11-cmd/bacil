<?php
// Kode di bawah digunakan untuk menentukan label nama role pengguna yang sedang login (Boss atau User/Operator)
$user_role_label = (isset($_SESSION['login_rifqy']) && $_SESSION['login_rifqy'] === 'Boss') ? 'Boss' : (isset($_SESSION['login_rifqy']) ? $_SESSION['login_rifqy'] : 'User');

// Kode di bawah digunakan untuk memeriksa apakah user yang sedang aktif memiliki level hak akses tertinggi (Boss)
$user_role_is_boss = (isset($_SESSION['login_rifqy']) && $_SESSION['login_rifqy'] === 'Boss');
?>
<!-- Kode di bawah adalah kontainer navigasi bagian atas (Top Navigation Bar) -->
<div class="top-nav">
    <!-- Kode di bawah adalah tombol hamburger untuk membuka atau menutup menu sidebar (responsif desktop & mobile) -->
    <button class="btn-toggle" onclick="toggleSidebar()" title="Buka/Tutup Menu" aria-label="Menu">
        <span class="hamburger-icon">☰</span>
    </button>

    <!-- Kode di bawah digunakan untuk menampilkan identitas nama perusahaan dan nama sistem cargo -->
    <div class="nav-title">
        <span class="nav-brand">CV. MANUNGGAL</span>
        <span class="nav-sub">Cargo Manifest</span>
    </div>

    <!-- Kode di bawah digunakan untuk menampilkan lencana (badge) status hak akses user yang sedang login -->
    <div class="nav-user-badge <?= $user_role_is_boss ? 'badge-boss' : 'badge-staff' ?>">
        <?= $user_role_is_boss ? '👑 Boss' : '👤 ' . htmlspecialchars($user_role_label, ENT_QUOTES, 'UTF-8') ?>
    </div>
</div>
