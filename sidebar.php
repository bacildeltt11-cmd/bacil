<?php
// Kode di bawah digunakan untuk memulai session jika belum berjalan
if (session_status() === PHP_SESSION_NONE) session_start();

// Kode di bawah digunakan untuk mendeteksi nama file halaman aktif saat ini untuk penandaan menu aktif
$current_page = basename($_SERVER['PHP_SELF']);

// Kode di bawah digunakan untuk menentukan hak akses (role) pengguna aktif (Boss atau Operator/Admin)
$is_boss = isset($_SESSION['login_rifqy']) && $_SESSION['login_rifqy'] === 'Boss';
$is_admin = !$is_boss;
?>
<!-- Kontainer Menu Navigasi Samping (Sidebar) untuk Desktop & Tablet -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-inner">
        <!-- Header Sidebar: Menampilkan brand dan lencana role -->
        <div class="sidebar-header">
            <div class="sidebar-brand">
                <h2>CV. MANUNGGAL</h2>
                <span class="sidebar-role-badge <?= $is_boss ? 'role-boss' : 'role-staff' ?>">
                    <?= $is_boss ? '👑 Boss Mode' : '👤 Operator Mode' ?>
                </span>
            </div>
            <!-- Tombol silang untuk menutup sidebar pada tampilan layar kecil/smartphone -->
            <button class="sidebar-close-btn" onclick="closeSidebarMobile()" aria-label="Tutup Menu" title="Tutup Menu">✕</button>
        </div>

        <!-- Daftar Menu Navigasi Utama -->
        <nav class="sidebar-nav">
            <!-- Menu Dashboard -->
            <a href="dashboard.php" class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                <span class="nav-icon">🏠</span> <span class="nav-text">Dashboard</span>
            </a>

            <!-- Menu Keberangkatan khusus untuk level hak akses Boss -->
            <?php if ($is_boss): ?>
            <a href="input_keberangkatan.php" class="nav-link <?= $current_page == 'input_keberangkatan.php' ? 'active' : '' ?>">
                <span class="nav-icon">📅</span> <span class="nav-text">Keberangkatan</span>
            </a>
            <?php endif; ?>

            <!-- Menu Manajemen Master Barang -->
            <a href="daftar_barang.php" class="nav-link <?= $current_page == 'daftar_barang.php' ? 'active' : '' ?>">
                <span class="nav-icon">📦</span> <span class="nav-text">Daftar Barang</span>
            </a>

            <!-- Menu Riwayat Arsip Manifest -->
            <a href="data.php" class="nav-link <?= $current_page == 'data.php' ? 'active' : '' ?>">
                <span class="nav-icon">📜</span> <span class="nav-text">Riwayat Arsip</span>
            </a>
        </nav>

        <!-- Tombol Logout untuk keluar dari sistem -->
        <a href="logout.php" class="logout-btn" onclick="return confirm('Yakin ingin keluar?');">
            <span class="nav-icon">🚪</span> <span class="nav-text">Keluar</span>
        </a>
    </div>
</div>

<!-- Lapisan transparan (Overlay) saat sidebar terbuka di layar smartphone -->
<div id="sidebarOverlay" class="sidebar-overlay" onclick="closeSidebarMobile()"></div>

<!-- Bar Navigasi Bawah Khusus Tampilan Layar Smartphone (Mobile Bottom Navigation) -->
<nav class="mobile-bottom-nav" id="mobileBottomNav" aria-label="Navigasi Bawah">
    <a href="dashboard.php" class="bottom-nav-item <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
        <span class="bottom-nav-icon">🏠</span>
        <span class="bottom-nav-label">Home</span>
    </a>

    <!-- Menu dinamis berdasarkan role user -->
    <?php if ($is_boss): ?>
    <a href="input_keberangkatan.php" class="bottom-nav-item <?= $current_page == 'input_keberangkatan.php' ? 'active' : '' ?>">
        <span class="bottom-nav-icon">📅</span>
        <span class="bottom-nav-label">Jadwal</span>
    </a>
    <?php else: ?>
    <?php $has_active_manifest = !empty($_SESSION['id_manifest']); ?>
    <a href="<?= $has_active_manifest ? 'input_muatan.php' : 'dashboard.php' ?>" class="bottom-nav-item <?= $current_page == 'input_muatan.php' ? 'active' : '' ?>">
        <span class="bottom-nav-icon">📝</span>
        <span class="bottom-nav-label">Muatan</span>
    </a>
    <?php endif; ?>

    <a href="daftar_barang.php" class="bottom-nav-item <?= $current_page == 'daftar_barang.php' ? 'active' : '' ?>">
        <span class="bottom-nav-icon">📦</span>
        <span class="bottom-nav-label">Barang</span>
    </a>

    <a href="data.php" class="bottom-nav-item <?= ($current_page == 'data.php' || $current_page == 'preview_manifest_lama.php') ? 'active' : '' ?>">
        <span class="bottom-nav-icon">📜</span>
        <span class="bottom-nav-label">Riwayat</span>
    </a>

    <a href="logout.php" class="bottom-nav-item nav-item-logout" onclick="return confirm('Yakin ingin keluar?');" aria-label="Keluar">
        <span class="bottom-nav-icon">🚪</span>
        <span class="bottom-nav-label">Keluar</span>
    </a>
</nav>

<script>
// Kode JavaScript di bawah digunakan untuk membuka atau menutup sidebar responsif
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const isMobile = window.innerWidth <= 1024;

    if (isMobile) {
        // Tampilan Mobile: slide geser dari sisi kiri
        const isCollapsed = sidebar.classList.toggle('collapsed');
        if (overlay) {
            if (isCollapsed) {
                overlay.classList.remove('active');
            } else {
                overlay.classList.add('active');
            }
        }
    } else {
        // Tampilan Desktop: ciutkan lebar sidebar dan simpan preferensi di localStorage
        const isCollapsed = sidebar.classList.toggle('collapsed');
        localStorage.setItem('sidebarCollapsed', isCollapsed);
    }
}

// Kode JavaScript di bawah digunakan untuk menutup sidebar secara paksa pada mode mobile
function closeSidebarMobile() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar) sidebar.classList.add('collapsed');
    if (overlay) overlay.classList.remove('active');
}

// Inisialisasi otomatis saat halaman dimuat
(function() {
    const isMobile = window.innerWidth <= 1024;
    const sidebar = document.getElementById('sidebar');
    if (isMobile) {
        // Secara default tutup sidebar di layar kecil agar tidak menutupi konten
        if (sidebar) sidebar.classList.add('collapsed');
    } else {
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            if (sidebar) sidebar.classList.add('collapsed');
        }
    }

    // Menutup overlay otomatis ketika ukuran browser di-resize ke ukuran desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth > 1024) {
            const overlay = document.getElementById('sidebarOverlay');
            if (overlay) overlay.classList.remove('active');
        }
    });
})();
</script>
