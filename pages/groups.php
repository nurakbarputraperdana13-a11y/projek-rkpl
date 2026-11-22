<?php
// pages/groups.php - Halaman manajemen grup & split bill
// SakuKu - Aplikasi Manajemen Keuangan Mahasiswa

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/navbar.php';

$userId = getCurrentUserId();
$pdo = getPDO();

// Ambil grup user
$stmt = $pdo->prepare('
    SELECT g.*, gm.role,
           (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count
    FROM groups g
    JOIN group_members gm ON g.id = gm.group_id
    WHERE gm.user_id = ?
    ORDER BY g.created_at DESC
');
$stmt->execute([$userId]);
$groups = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grup & Split Bill - SakuKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700&family=Source+Sans+3:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/serius/assets/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <main class="dashboard" role="main">
        <div class="dashboard-container">
            <header class="dashboard-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: var(--space-4);">
                <div>
                    <h1 class="dashboard-title">Grup & Split Bill</h1>
                    <p class="dashboard-subtitle">Kelola patungan dengan teman/kelompok kos</p>
                </div>
                <div style="display: flex; gap: var(--space-2);">
                    <button class="btn btn-primary" onclick="openGroupModal()">+ Buat Grup</button>
                    <button class="btn btn-secondary" onclick="openJoinModal()">Gabung Grup</button>
                </div>
            </header>

            <!-- Groups Grid -->
            <section class="chart-card" aria-labelledby="groupsTitle">
                <header class="chart-header">
                    <h2 id="groupsTitle" class="chart-title">Grupmu</h2>
                </header>
                <div class="chart-container" style="padding: 0; height: auto;">
                    <?php if (empty($groups)): ?>
                        <div class="empty-state" style="padding: var(--space-12);">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                            <h3>Belum ada grup</h3>
                            <p>Buat grup baru atau gabung grup teman untuk mulai split bill</p>
                            <div style="display: flex; gap: var(--space-2); justify-content: center; flex-wrap: wrap;">
                                <button class="btn btn-primary" onclick="openGroupModal()">Buat Grup</button>
                                <button class="btn btn-secondary" onclick="openJoinModal()">Gabung Grup</button>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="display: grid; grid-template-columns: 1fr; gap: var(--space-4);">
                            @media (min-width: 640px) { grid-template-columns: repeat(2, 1fr); }
                            @media (min-width: 1024px) { grid-template-columns: repeat(3, 1fr); }
                            <?php foreach ($groups as $group): 
                                $isAdmin = $group['role'] === 'admin';
                            ?>
                                <article class="group-card">
                                    <header class="group-header">
                                        <div class="group-avatar" aria-hidden="true">
                                            <?= e(mb_substr($group['name'], 0, 1)) ?>
                                        </div>
                                        <div class="group-info">
                                            <h3 class="group-name"><?= e($group['name']) ?></h3>
                                            <?php if ($group['description']): ?>
                                                <p class="group-desc"><?= e($group['description']) ?></p>
                                            <?php endif; ?>
                                            <div class="group-meta">
                                                <span class="group-meta-item">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                                                    <?= $group['member_count'] ?> anggota
                                                </span>
                                                <span class="group-meta-item">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/></svg>
                                                    Kode: <code style="font-family: var(--font-mono); background: var(--color-background); padding: 1px 4px; border-radius: var(--radius-sm);"><?= e($group['invite_code']) ?></code>
                                                </span>
                                                <span class="group-meta-item">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/></svg>
                                                    <?= $isAdmin ? 'Admin' : 'Anggota' ?>
                                                </span>
                                            </div>
                                        </div>
                                    </header>
                                    <footer style="display: flex; gap: var(--space-2); padding-top: var(--space-4); border-top: 1px solid var(--color-border);">
                                        <a href="/serius/pages/group_detail.php?id=<?= $group['id'] ?>" class="btn btn-primary" style="flex: 1; text-align: center;">Buka</a>
                                        <?php if ($isAdmin): ?>
                                            <button class="btn btn-secondary" style="flex: 1;" onclick="openInviteModal(<?= htmlspecialchars(json_encode($group), ENT_QUOTES, 'UTF-8') ?>)">Undang</button>
                                            <button class="btn btn-secondary" style="flex: 1;" onclick="openEditGroupModal(<?= htmlspecialchars(json_encode($group), ENT_QUOTES, 'UTF-8') ?>)">Edit</button>
                                        <?php endif; ?>
                                    </footer>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>

    <!-- Create Group Modal -->
    <div class="modal-overlay" id="groupModal" role="dialog" aria-modal="true" aria-labelledby="groupModalTitle" hidden>
        <div class="modal">
            <header class="modal-header">
                <h3 id="groupModalTitle" class="modal-title">Buat Grup Baru</h3>
                <button type="button" class="modal-close" onclick="closeGroupModal()" aria-label="Tutup">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </header>
            <form id="groupForm" class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" id="groupAction" value="create">
                <input type="hidden" name="id" id="groupId">
                
                <div class="form-group">
                    <label for="groupName">Nama Grup <span class="required">*</span></label>
                    <input type="text" id="groupName" name="name" required placeholder="Contoh: Kos Block A / Kelompok Tugas" maxlength="100">
                </div>

                <div class="form-group">
                    <label for="groupDesc">Deskripsi (Opsional)</label>
                    <textarea id="groupDesc" name="description" placeholder="Contoh: Grup untuk patungan sewa kos & listrik" maxlength="255" rows="3"></textarea>
                </div>
            </form>
            <footer class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeGroupModal()">Batal</button>
                <button type="button" class="btn btn-primary" onclick="submitGroup()">Buat Grup</button>
            </footer>
        </div>
    </div>

    <!-- Join Group Modal -->
    <div class="modal-overlay" id="joinModal" role="dialog" aria-modal="true" aria-labelledby="joinModalTitle" hidden>
        <div class="modal" style="max-width: 400px;">
            <header class="modal-header">
                <h3 id="joinModalTitle" class="modal-title">Gabung Grup</h3>
                <button type="button" class="modal-close" onclick="closeJoinModal()" aria-label="Tutup">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </header>
            <form id="joinForm" class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" value="join">
                
                <div class="form-group">
                    <label for="joinCode">Kode Undangan <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        <input type="text" id="joinCode" name="invite_code" required placeholder="Contoh: A1B2C3D4" maxlength="8" style="text-transform: uppercase;" aria-describedby="joinHint">
                    </div>
                    <div id="joinHint" class="form-hint">Kode undangan 8 karakter dari admin grup</div>
                </div>
            </form>
            <footer class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeJoinModal()">Batal</button>
                <button type="button" class="btn btn-primary" onclick="submitJoin()">Gabung</button>
            </footer>
        </div>
    </div>

    <!-- Invite Modal -->
    <div class="modal-overlay" id="inviteModal" role="dialog" aria-modal="true" aria-labelledby="inviteModalTitle" hidden>
        <div class="modal" style="max-width: 400px;">
            <header class="modal-header">
                <h3 id="inviteModalTitle" class="modal-title">Undang Anggota</h3>
                <button type="button" class="modal-close" onclick="closeInviteModal()" aria-label="Tutup">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </header>
            <div class="modal-body" style="text-align: center;">
                <p style="color: var(--color-text-secondary); margin-bottom: var(--space-4)">Bagikan kode undangan ini ke teman:</p>
                <div style="display: flex; gap: var(--space-2); justify-content: center; margin-bottom: var(--space-4);">
                    <code id="inviteCodeDisplay" style="flex: 1; background: var(--color-background); padding: var(--space-3); border-radius: var(--radius-md); font-size: var(--font-size-xl); font-weight: var(--font-weight-bold); letter-spacing: 0.1em; color: var(--color-accent); font-family: var(--font-mono);"></code>
                    <button class="btn btn-secondary" onclick="copyInviteCode()" aria-label="Salin kode">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" style="width: 20px; height: 20px;">
                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                        </svg>
                    </button>
                </div>
                <p class="form-hint">Kode ini bersifat rahasia, hanya bagikan ke orang yang diundang</p>
            </div>
            <footer class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="closeInviteModal()">Tutup</button>
            </footer>
        </div>
    </div>

    <!-- Edit Group Modal -->
    <div class="modal-overlay" id="editGroupModal" role="dialog" aria-modal="true" aria-labelledby="editGroupModalTitle" hidden>
        <div class="modal">
            <header class="modal-header">
                <h3 id="editGroupModalTitle" class="modal-title">Edit Grup</h3>
                <button type="button" class="modal-close" onclick="closeEditGroupModal()" aria-label="Tutup">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </header>
            <form id="editGroupForm" class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="editGroupId">
                
                <div class="form-group">
                    <label for="editGroupName">Nama Grup <span class="required">*</span></label>
                    <input type="text" id="editGroupName" name="name" required maxlength="100">
                </div>

                <div class="form-group">
                    <label for="editGroupDesc">Deskripsi</label>
                    <textarea id="editGroupDesc" name="description" maxlength="255" rows="3"></textarea>
                </div>
                
                <hr class="navbar-dropdown-divider" style="margin: var(--space-4) 0;">
                <p class="form-hint" style="color: var(--color-destructive);">Zona Bahaya</p>
                <button type="button" class="btn btn-destructive" style="width: 100%;" onclick="confirmDeleteGroup()">Hapus Grup</button>
            </form>
            <footer class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditGroupModal()">Batal</button>
                <button type="button" class="btn btn-primary" onclick="submitEditGroup()">Simpan</button>
            </footer>
        </div>
    </div>

    <script>
        // Modal handlers
        const groupModal = document.getElementById('groupModal');
        const groupForm = document.getElementById('groupForm');
        const joinModal = document.getElementById('joinModal');
        const joinForm = document.getElementById('joinForm');
        const inviteModal = document.getElementById('inviteModal');
        const editGroupModal = document.getElementById('editGroupModal');
        const editGroupForm = document.getElementById('editGroupForm');

        function openGroupModal(data = null) {
            groupForm.reset();
            if (data) {
                document.getElementById('groupModalTitle').textContent = 'Edit Grup';
                document.getElementById('groupAction').value = 'update';
                document.getElementById('groupId').value = data.id;
                document.getElementById('groupName').value = data.name;
                document.getElementById('groupDesc').value = data.description || '';
            } else {
                document.getElementById('groupModalTitle').textContent = 'Buat Grup Baru';
                document.getElementById('groupAction').value = 'create';
                document.getElementById('groupId').value = '';
            }
            groupModal.hidden = false;
            document.body.style.overflow = 'hidden';
            setTimeout(() => document.getElementById('groupName').focus(), 100);
        }

        function closeGroupModal() { groupModal.hidden = true; document.body.style.overflow = ''; groupForm.reset(); }

        function submitGroup() {
            const btn = groupModal.querySelector('.btn-primary');
            setButtonLoading(btn, true);
            const formData = new FormData(groupForm);
            fetch('/serius/process/group_process.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(d => { if (d.success) { showToast(d.message, 'success'); closeGroupModal(); setTimeout(() => location.reload(), 800); } else showToast(d.message, 'error'); })
                .finally(() => setButtonLoading(btn, false));
        }

        function openJoinModal() { joinModal.hidden = false; document.body.style.overflow = 'hidden'; joinForm.reset(); setTimeout(() => document.getElementById('joinCode').focus(), 100); }
        function closeJoinModal() { joinModal.hidden = true; document.body.style.overflow = ''; joinForm.reset(); }
        function submitJoin() {
            const btn = joinModal.querySelector('.btn-primary');
            setButtonLoading(btn, true);
            const formData = new FormData(joinForm);
            fetch('/serius/process/group_process.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(d => { if (d.success) { showToast(d.message, 'success'); closeJoinModal(); setTimeout(() => location.reload(), 800); } else showToast(d.message, 'error'); })
                .finally(() => setButtonLoading(btn, false));
        }

        function openInviteModal(group) {
            document.getElementById('inviteCodeDisplay').textContent = group.invite_code;
            inviteModal.hidden = false;
            document.body.style.overflow = 'hidden';
        }
        function closeInviteModal() { inviteModal.hidden = true; document.body.style.overflow = ''; }
        function copyInviteCode() {
            const code = document.getElementById('inviteCodeDisplay').textContent;
            navigator.clipboard.writeText(code);
            showToast('Kode disalin!', 'success');
        }

        function openEditGroupModal(group) {
            document.getElementById('editGroupModalTitle').textContent = 'Edit Grup: ' + group.name;
            document.getElementById('editGroupId').value = group.id;
            document.getElementById('editGroupName').value = group.name;
            document.getElementById('editGroupDesc').value = group.description || '';
            editGroupModal.hidden = false;
            document.body.style.overflow = 'hidden';
        }
        function closeEditGroupModal() { editGroupModal.hidden = true; document.body.style.overflow = ''; editGroupForm.reset(); }
        function submitEditGroup() {
            const btn = editGroupModal.querySelector('.btn-primary');
            setButtonLoading(btn, true);
            const formData = new FormData(editGroupForm);
            fetch('/serius/process/group_process.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(d => { if (d.success) { showToast(d.message, 'success'); closeEditGroupModal(); setTimeout(() => location.reload(), 800); } else showToast(d.message, 'error'); })
                .finally(() => setButtonLoading(btn, false));
        }
        function confirmDeleteGroup() {
            if (!confirm('PERINGATAN: Menghapus grup akan menghapus SEMUA data split bill, anggota, dan riwayat. Lanjutkan?')) return;
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', document.getElementById('editGroupId').value);
            formData.append('csrf_token', editGroupForm.querySelector('[name="csrf_token"]').value);
            fetch('/serius/process/group_process.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(d => { if (d.success) { showToast(d.message, 'success'); closeEditGroupModal(); setTimeout(() => location.reload(), 800); } else showToast(d.message, 'error'); });
        }

        function setButtonLoading(btn, loading) {
            if (loading) { btn.disabled = true; btn.dataset.original = btn.textContent; btn.textContent = 'Menyimpan...'; }
            else { btn.disabled = false; btn.textContent = btn.dataset.original || 'Simpan'; }
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

        [groupModal, joinModal, inviteModal, editGroupModal].forEach(m => m.addEventListener('click', e => { if (e.target === m) { if (m === groupModal) closeGroupModal(); else if (m === joinModal) closeJoinModal(); else if (m === inviteModal) closeInviteModal(); else closeEditGroupModal(); } }));
        document.addEventListener('keydown', e => { if (e.key === 'Escape') { if (!groupModal.hidden) closeGroupModal(); if (!joinModal.hidden) closeJoinModal(); if (!inviteModal.hidden) closeInviteModal(); if (!editGroupModal.hidden) closeEditGroupModal(); } });
    </script>
</body>
</html>