<?php
// pages/profile.php - Halaman profil user
// SakuKu - Aplikasi Manajemen Keuangan Mahasiswa

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/navbar.php';

$userId = getCurrentUserId();
$pdo = getPDO();

// Ambil data user
$stmt = $pdo->prepare('SELECT * FROM users WHERE id_user = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Stats user
$stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM transactions WHERE user_id = ?');
$stmt->execute([$userId]);
$txnCount = (int)$stmt->fetch()['cnt'];

$stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM savings_goals WHERE user_id = ?');
$stmt->execute([$userId]);
$savingsCount = (int)$stmt->fetch()['cnt'];

$stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM groups g JOIN group_members gm ON g.id = gm.group_id WHERE gm.user_id = ?');
$stmt->execute([$userId]);
$groupsCount = (int)$stmt->fetch()['cnt'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - SakuKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700&family=Source+Sans+3:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/serius/assets/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <main class="dashboard" role="main">
        <div class="dashboard-container">
            <header class="dashboard-header">
                <h1 class="dashboard-title">Profil Saya</h1>
                <p class="dashboard-subtitle">Kelola informasi akun & preferensi</p>
            </header>

            <div style="display: grid; grid-template-columns: 1fr; gap: var(--space-6);">
                @media (min-width: 768px) { grid-template-columns: 320px 1fr; }

                <!-- Profile Sidebar -->
                <aside class="card" style="padding: var(--space-6); text-align: center;">
                    <div class="avatar-lg" style="margin: 0 auto var(--space-4); width: 96px; height: 96px; font-size: 36px;">
                        <?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?>
                    </div>
                    <h2 style="font-size: var(--font-size-xl); font-weight: var(--font-weight-bold); margin: 0 0 var(--space-1);"><?= e($user['name']) ?></h2>
                    <p style="color: var(--color-text-muted); margin: 0 0 var(--space-4);"><?= e($user['email']) ?></p>
                    
                    <div style="display: flex; justify-content: center; gap: var(--space-4); padding-top: var(--space-4); border-top: 1px solid var(--color-border);">
                        <div>
                            <div style="font-size: var(--font-size-xl); font-weight: var(--font-weight-bold);"><?= $txnCount ?></div>
                            <div style="font-size: var(--font-size-xs); color: var(--color-text-muted);">Transaksi</div>
                        </div>
                        <div>
                            <div style="font-size: var(--font-size-xl); font-weight: var(--font-weight-bold);"><?= $savingsCount ?></div>
                            <div style="font-size: var(--font-size-xs); color: var(--color-text-muted);">Target Tabungan</div>
                        </div>
                        <div>
                            <div style="font-size: var(--font-size-xl); font-weight: var(--font-weight-bold);"><?= $groupsCount ?></div>
                            <div style="font-size: var(--font-size-xs); color: var(--color-text-muted);">Grup</div>
                        </div>
                    </div>

                    <div style="margin-top: var(--space-6); padding: var(--space-4); background: var(--color-background); border-radius: var(--radius-md);">
                        <div style="font-size: var(--font-size-sm); color: var(--color-text-secondary);">
                            Bergabung sejak<br>
                            <strong style="color: var(--color-text-primary);"><?= e(date('d M Y', strtotime($user['created_at']))) ?></strong>
                        </div>
                    </div>
                </aside>

                <!-- Profile Content -->
                <section class="card" style="padding: var(--space-6);">
                    <h3 style="font-size: var(--font-size-lg); font-weight: var(--font-weight-semibold); margin: 0 0 var(--space-6);">Informasi Akun</h3>
                    
                    <form id="profileForm">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-group">
                            <label for="profileName">Nama Lengkap <span class="required">*</span></label>
                            <input type="text" id="profileName" name="name" required value="<?= e($user['name']) ?>" maxlength="100">
                        </div>

                        <div class="form-group">
                            <label for="profileEmail">Email <span class="required">*</span></label>
                            <input type="email" id="profileEmail" name="email" required value="<?= e($user['email']) ?>" maxlength="100">
                        </div>

                        <hr class="navbar-dropdown-divider" style="margin: var(--space-6) 0;">

                        <h4 style="font-size: var(--font-size-base); font-weight: var(--font-weight-semibold); margin: 0 0 var(--space-4); color: var(--color-destructive);">Zona Bahaya</h4>
                        <p class="form-hint" style="margin-bottom: var(--space-4);">Aksi ini tidak dapat dibatalkan. Semua data akan dihapus permanen.</p>
                        <button type="button" class="btn btn-destructive" onclick="confirmDeleteAccount()">Hapus Akun Permanen</button>
                    </form>
                </section>
            </div>
        </div>
    </main>

    <script>
        document.getElementById('profileForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button[type="submit"]') || document.querySelector('#profileForm .btn-primary');
            if (!btn) return;
            
            btn.disabled = true;
            btn.textContent = 'Menyimpan...';
            
            const formData = new FormData(e.target);
            try {
                const res = await fetch('/serius/process/profile_process.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, 'success');
                    // Update navbar name
                    document.querySelector('.nav-user-name')?.textContent = formData.get('name');
                    document.querySelector('.nav-user-menu .nav-user-name')?.textContent = formData.get('name');
                } else {
                    showToast(data.message, 'error');
                }
            } catch (err) {
                showToast('Terjadi kesalahan', 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Simpan';
            }
        });

        function confirmDeleteAccount() {
            if (!confirm('PERINGATAN: Ini akan menghapus SEMUA data Anda (transaksi, tabungan, grup, dll). Tindakan ini TIDAK DAPAT DIBATALKAN.\n\nKetik "HAPUS AKUN" untuk konfirmasi:')) return;
            const confirmText = prompt('Ketik "HAPUS AKUN" untuk konfirmasi:');
            if (confirmText !== 'HAPUS AKUN') {
                showToast('Konfirmasi tidak cocok. Akun tidak dihapus.', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'delete_account');
            formData.append('csrf_token', document.querySelector('#profileForm [name="csrf_token"]').value);
            
            fetch('/serius/process/profile_process.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        showToast(d.message, 'success');
                        setTimeout(() => window.location.href = '/serius/process/auth_process.php?action=logout', 1500);
                    } else {
                        showToast(d.message, 'error');
                    }
                });
        }

        function showToast(msg, type) {
            let c = document.querySelector('.toast-container');
            if (!c) { c = document.createElement('div'); c.className = 'toast-container'; document.body.appendChild(c); }
            const t = document.createElement('div');
            t.className = 'toast ' + type;
            t.innerHTML = '<span class="toast-message">' + msg + '</span><button class="toast-close" onclick="this.parentElement.remove()">×</button>';
            c.appendChild(t);
            setTimeout(() => t.remove(), 4000);
        }
    </script>
</body>
</html>