<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$current_page = basename($_SERVER['PHP_SELF']);

// Tentukan role
$is_boss = isset($_SESSION['login_rifqy']) && $_SESSION['login_rifqy'] === 'Boss';
$is_admin = !$is_boss;
?>
<div class="sidebar" id="sidebar">
    <div class="sidebar-inner">
        <div class="sidebar-header">
            <div class="sidebar-brand">
                <h2>CV. MANUNGGAL</h2>
                <span class="sidebar-role-badge <?= $is_boss ? 'role-boss' : 'role-staff' ?>">
                    <?= $is_boss ? '👑 Boss Mode' : '👤 Operator Mode' ?>
                </span>
            </div>
            <button class="sidebar-close-btn" onclick="closeSidebarMobile()" aria-label="Tutup Menu" title="Tutup Menu">✕</button>
        </div>

        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                <span class="nav-icon">🏠</span> <span class="nav-text">Dashboard</span>
            </a>

            <?php if ($is_boss): ?>
            <a href="input_keberangkatan.php" class="nav-link <?= $current_page == 'input_keberangkatan.php' ? 'active' : '' ?>">
                <span class="nav-icon">📅</span> <span class="nav-text">Keberangkatan</span>
            </a>
            <?php endif; ?>

            <a href="daftar_barang.php" class="nav-link <?= $current_page == 'daftar_barang.php' ? 'active' : '' ?>">
                <span class="nav-icon">📦</span> <span class="nav-text">Daftar Barang</span>
            </a>

            <a href="data.php" class="nav-link <?= $current_page == 'data.php' ? 'active' : '' ?>">
                <span class="nav-icon">📜</span> <span class="nav-text">Riwayat Arsip</span>
            </a>
        </nav>

        <a href="logout.php" class="logout-btn" onclick="return confirm('Yakin ingin keluar?');">
            <span class="nav-icon">🚪</span> <span class="nav-text">Keluar</span>
        </a>
    </div>
</div>

<!-- Sidebar Overlay for Mobile -->
<div id="sidebarOverlay" class="sidebar-overlay" onclick="closeSidebarMobile()"></div>

<!-- Mobile Bottom Navigation Bar (Thumb-Friendly Ergonomics for Mobile Screens) -->
<nav class="mobile-bottom-nav" id="mobileBottomNav" aria-label="Navigasi Bawah">
    <a href="dashboard.php" class="bottom-nav-item <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
        <span class="bottom-nav-icon">🏠</span>
        <span class="bottom-nav-label">Home</span>
    </a>

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

    <button type="button" class="bottom-nav-item btn-bottom-menu" onclick="toggleSidebar()" aria-label="Menu Lengkap">
        <span class="bottom-nav-icon">☰</span>
        <span class="bottom-nav-label">Menu</span>
    </button>
</nav>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const isMobile = window.innerWidth <= 1024;

    if (isMobile) {
        // Mobile: slide in/out from left
        const isCollapsed = sidebar.classList.toggle('collapsed');
        if (overlay) {
            if (isCollapsed) {
                overlay.classList.remove('active');
            } else {
                overlay.classList.add('active');
            }
        }
    } else {
        // Desktop: collapse width
        const isCollapsed = sidebar.classList.toggle('collapsed');
        localStorage.setItem('sidebarCollapsed', isCollapsed);
    }
}

function closeSidebarMobile() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar) sidebar.classList.add('collapsed');
    if (overlay) overlay.classList.remove('active');
}

(function() {
    const isMobile = window.innerWidth <= 1024;
    const sidebar = document.getElementById('sidebar');
    if (isMobile) {
        // Always start collapsed on mobile/tablet so sidebar doesn't obstruct the view
        if (sidebar) sidebar.classList.add('collapsed');
    } else {
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            if (sidebar) sidebar.classList.add('collapsed');
        }
    }

    // Auto-close sidebar on mobile when window resizes to desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth > 1024) {
            const overlay = document.getElementById('sidebarOverlay');
            if (overlay) overlay.classList.remove('active');
        }
    });
})();
</script>