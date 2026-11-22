<?php
// includes/navbar.php - Komponen navigasi reusable
// SakuKu - Aplikasi Manajemen Keuangan Mahasiswa

// Only require auth_check if not already loaded (avoid double session_start)
if (!function_exists('requireAuth')) {
    require_once __DIR__ . '/auth_check.php';
}
if (!function_exists('formatRupiah')) {
    require_once __DIR__ . '/helpers.php';
}
if (!function_exists('csrfField')) {
    require_once __DIR__ . '/csrf.php';
}

$userId = getCurrentUserId();
$userName = getCurrentUserName();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Jika bukan halaman auth, require login
$authPages = ['login', 'register', 'forgot_password'];
if (!in_array($currentPage, $authPages)) {
    requireAuth();
}
?>
<nav class="navbar" role="navigation" aria-label="Navigasi utama">
    <div class="navbar-container">
        <!-- Logo/Brand -->
        <a href="/serius/pages/dashboard.php" class="navbar-brand" aria-label="SakuKu - Beranda">
            <svg class="navbar-logo" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
            </svg>
            <span class="navbar-title">SakuKu</span>
        </a>

        <!-- Navigation Links -->
        <ul class="navbar-nav" role="menubar">
            <li role="none">
                <a href="/serius/pages/dashboard.php" class="navbar-link<?= $currentPage === 'dashboard' ? ' active' : '' ?>" role="menuitem">
                    <svg class="navbar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <rect x="3" y="3" width="7" height="7" rx="1"/>
                        <rect x="14" y="3" width="7" height="7" rx="1"/>
                        <rect x="3" y="14" width="7" height="7" rx="1"/>
                        <rect x="14" y="14" width="7" height="7" rx="1"/>
                    </svg>
                    <span>Dashboard</span>
                </a>
            </li>
            <li role="none">
                <a href="/serius/pages/transactions.php" class="navbar-link<?= $currentPage === 'transactions' ? ' active' : '' ?>" role="menuitem">
                    <svg class="navbar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <line x1="12" y1="1" x2="12" y2="23"/>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                    <span>Transaksi</span>
                </a>
            </li>
            <li role="none">
                <a href="/serius/pages/budgets.php" class="navbar-link<?= $currentPage === 'budgets' ? ' active' : '' ?>" role="menuitem">
                    <svg class="navbar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <line x1="18" y1="20" x2="18" y2="10"/>
                        <line x1="12" y1="20" x2="12" y2="4"/>
                        <line x1="6" y1="20" x2="6" y2="14"/>
                    </svg>
                    <span>Budget</span>
                </a>
            </li>
            <li role="none">
                <a href="/serius/pages/savings.php" class="navbar-link<?= $currentPage === 'savings' ? ' active' : '' ?>" role="menuitem">
                    <svg class="navbar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M12 2a10 10 0 1 0 10 10"/>
                        <path d="M12 6v6l4 2"/>
                    </svg>
                    <span>Tabungan</span>
                </a>
            </li>
            <li role="none">
                <a href="/serius/pages/groups.php" class="navbar-link<?= $currentPage === 'groups' ? ' active' : '' ?>" role="menuitem">
                    <svg class="navbar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    <span>Grup</span>
                </a>
            </li>
        </ul>

        <!-- User Menu -->
        <div class="navbar-user" role="region" aria-label="Menu pengguna">
            <button class="navbar-user-btn" aria-expanded="false" aria-haspopup="true" aria-label="Menu pengguna: <?= e($userName) ?>">
                <div class="navbar-avatar" aria-hidden="true">
                    <?= e(mb_substr($userName, 0, 1)) ?>
                </div>
                <span class="navbar-username"><?= e($userName) ?></span>
                <svg class="navbar-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </button>
            <div class="navbar-dropdown" role="menu" hidden>
                <div class="navbar-dropdown-header">
                    <span class="navbar-dropdown-name"><?= e($userName) ?></span>
                    <span class="navbar-dropdown-email"><?= e($_SESSION['user_email'] ?? '') ?></span>
                </div>
                <a href="/serius/pages/profile.php" class="navbar-dropdown-item" role="menuitem">
                    <svg class="navbar-dropdown-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    Profil
                </a>
                <a href="/serius/pages/settings.php" class="navbar-dropdown-item" role="menuitem">
                    <svg class="navbar-dropdown-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                    </svg>
                    Pengaturan
                </a>
                <hr class="navbar-dropdown-divider">
                <form action="/serius/process/auth_process.php" method="POST" style="margin:0;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="navbar-dropdown-item navbar-dropdown-danger" role="menuitem">
                        <svg class="navbar-dropdown-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                            <polyline points="16 17 21 12 16 7"/>
                            <line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                        Keluar
                    </button>
                </form>
            </div>
        </div>

        <!-- Mobile Menu Toggle -->
        <button class="navbar-toggle" aria-expanded="false" aria-controls="navbar-nav" aria-label="Toggle navigasi">
            <svg class="navbar-toggle-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <line x1="3" y1="12" x2="21" y2="12"/>
                <line x1="3" y1="6" x2="21" y2="6"/>
                <line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>
    </div>
</nav>

<script>
// Navbar dropdown & mobile toggle
document.addEventListener('DOMContentLoaded', function() {
    const userBtn = document.querySelector('.navbar-user-btn');
    const dropdown = document.querySelector('.navbar-dropdown');
    const toggle = document.querySelector('.navbar-toggle');
    const nav = document.querySelector('.navbar-nav');

    // User dropdown
    if (userBtn && dropdown) {
        userBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const expanded = this.getAttribute('aria-expanded') === 'true';
            this.setAttribute('aria-expanded', !expanded);
            dropdown.hidden = expanded;
        });

        document.addEventListener('click', function(e) {
            if (!dropdown.contains(e.target) && !userBtn.contains(e.target)) {
                userBtn.setAttribute('aria-expanded', 'false');
                dropdown.hidden = true;
            }
        });
    }

    // Mobile nav toggle
    if (toggle && nav) {
        toggle.addEventListener('click', function() {
            const expanded = this.getAttribute('aria-expanded') === 'true';
            this.setAttribute('aria-expanded', !expanded);
            nav.classList.toggle('show');
        });

        // Close mobile nav when clicking a link
        nav.querySelectorAll('.navbar-link').forEach(link => {
            link.addEventListener('click', () => {
                nav.classList.remove('show');
                toggle.setAttribute('aria-expanded', 'false');
            });
        });
    }

    // Close dropdown on escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && dropdown && !dropdown.hidden) {
            userBtn.setAttribute('aria-expanded', 'false');
            dropdown.hidden = true;
            userBtn.focus();
        }
    });
});
</script>