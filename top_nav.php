
<?php
$user_role_label = (isset($_SESSION['login_rifqy']) && $_SESSION['login_rifqy'] === 'Boss') ? 'Boss' : (isset($_SESSION['login_rifqy']) ? $_SESSION['login_rifqy'] : 'User');
$user_role_is_boss = (isset($_SESSION['login_rifqy']) && $_SESSION['login_rifqy'] === 'Boss');
?>
<div class="top-nav">
    <button class="btn-toggle" onclick="toggleSidebar()" title="Buka/Tutup Menu" aria-label="Menu">
        <span class="hamburger-icon">☰</span>
    </button>
    <div class="nav-title">
        <span class="nav-brand">CV. MANUNGGAL</span>
        <span class="nav-sub">Cargo Manifest</span>
    </div>
    <div class="nav-user-badge <?= $user_role_is_boss ? 'badge-boss' : 'badge-staff' ?>">
        <?= $user_role_is_boss ? '👑 Boss' : '👤 ' . htmlspecialchars($user_role_label, ENT_QUOTES, 'UTF-8') ?>
    </div>
</div>
