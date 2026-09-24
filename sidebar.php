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

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const isMobile = window.innerWidth <= 1024;

    if (isMobile) {
        // Mobile: slide in/out from left
        sidebar.classList.toggle('collapsed');
        if (overlay) overlay.classList.toggle('active');
    } else {
        // Desktop: collapse width
        const isCollapsed = sidebar.classList.toggle('collapsed');
        localStorage.setItem('sidebarCollapsed', isCollapsed);
    }
}

function closeSidebarMobile() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.add('collapsed');
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
})();
</script>