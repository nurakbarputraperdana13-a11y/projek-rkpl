<?php
// pages/savings.php - Halaman manajemen tabungan
// SakuKu - Aplikasi Manajemen Keuangan Mahasiswa

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/navbar.php';

$userId = getCurrentUserId();

// Proses topup/withdraw dari GET
if (isset($_GET['topup']) || isset($_GET['withdraw'])) {
    $goalId = (int)($_GET['topup'] ?? $_GET['withdraw']);
    $action = isset($_GET['topup']) ? 'topup' : 'withdraw';
    
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT * FROM savings_goals WHERE id = ? AND user_id = ?');
    $stmt->execute([$goalId, $userId]);
    $goal = $stmt->fetch();
    
    if ($goal) {
        // Store in session for modal
        $_SESSION['savings_action'] = ['action' => $action, 'goal' => $goal];
        header('Location: /serius/pages/savings.php');
        exit;
    }
}

// Ambil semua tabungan
$pdo = getPDO();
$stmt = $pdo->prepare('SELECT * FROM savings_goals WHERE user_id = ? ORDER BY status, deadline ASC, created_at ASC');
$stmt->execute([$userId]);
$savingsGoals = $stmt->fetchAll();

// Total tabungan aktif
$totalSavings = 0;
foreach ($savingsGoals as $g) {
    if ($g['status'] === 'active') $totalSavings += (int)$g['current_amount'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tabungan - SakuKu</title>
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
                    <h1 class="dashboard-title">Target Tabungan</h1>
                    <p class="dashboard-subtitle">Atur target nabung & pantau progresnya</p>
                </div>
                <div style="display: flex; gap: var(--space-2);">
                    <button class="btn btn-primary" onclick="openSavingsModal()">+ Target Baru</button>
                </div>
            </header>

            <!-- Summary Card -->
            <div class="dashboard-grid" style="margin-bottom: var(--space-6);">
                <article class="stat-card savings">
                    <header class="stat-header">
                        <div class="stat-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 2a10 10 0 1 0 10 10"/>
                                <path d="M12 6v6l4 2"/>
                            </svg>
                        </div>
                    </header>
                    <div class="stat-amount"><?= e(formatRupiah($totalSavings)) ?></div>
                    <div class="stat-label">Total Tabungan Aktif</div>
                </article>

                <article class="stat-card income">
                    <header class="stat-header">
                        <div class="stat-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/>
                                <path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/>
                                <path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/>
                            </svg>
                        </div>
                    </header>
                    <div class="stat-amount"><?= count(array_filter($savingsGoals, fn($g) => $g['status'] === 'active')) ?></div>
                    <div class="stat-label">Target Aktif</div>
                </article>

                <article class="stat-card balance">
                    <header class="stat-header">
                        <div class="stat-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                        </div>
                    </header>
                    <div class="stat-amount"><?= count(array_filter($savingsGoals, fn($g) => $g['status'] === 'completed')) ?></div>
                    <div class="stat-label">Target Selesai</div>
                </article>
            </div>

            <!-- Savings Goals Grid -->
            <section class="chart-card" aria-labelledby="goalsTitle">
                <header class="chart-header">
                    <h2 id="goalsTitle" class="chart-title">Daftar Target Tabungan</h2>
                </header>
                <div class="chart-container" style="padding: 0; height: auto;">
                    <?php if (empty($savingsGoals)): ?>
                        <div class="empty-state" style="padding: var(--space-12);">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path d="M12 2a10 10 0 1 0 10 10"/>
                                <path d="M12 6v6l4 2"/>
                            </svg>
                            <h3>Belum ada target tabungan</h3>
                            <p>Buat target nabung pertama untuk memulai kebiasaan menabung</p>
                            <button class="btn btn-primary" onclick="openSavingsModal()">Buat Target Baru</button>
                        </div>
                    <?php else: ?>
                        <div class="savings-grid">
                            <?php foreach ($savingsGoals as $goal): 
                                $progress = $goal['target_amount'] > 0 ? ($goal['current_amount'] / $goal['target_amount']) * 100 : 0;
                                $isCompleted = $goal['status'] === 'completed' || $progress >= 100;
                                $daysLeft = $goal['deadline'] ? ceil((strtotime($goal['deadline']) - time()) / 86400) : null;
                            ?>
                                <article class="savings-card<?= $isCompleted ? ' completed' : '' ?>">
                                    <header class="savings-header">
                                        <h3 class="savings-title"><?= e($goal['title']) ?></h3>
                                        <span class="savings-status <?= $goal['status'] ?>"><?= $isCompleted ? 'Selesai' : 'Aktif' ?></span>
                                    </header>
                                    <div class="savings-body">
                                        <div class="savings-progress">
                                            <div class="savings-progress-bar">
                                                <div class="savings-progress-fill" style="width: <?= min(100, $progress) ?>%"></div>
                                            </div>
                                            <div class="savings-progress-labels">
                                                <span><?= e(formatRupiah($goal['current_amount'])) ?></span>
                                                <span><?= e(formatRupiah($goal['target_amount'])) ?></span>
                                            </div>
                                        </div>
                                        <div class="savings-amount"><?= e(formatRupiah($goal['current_amount'])) ?> / <?= e(formatRupiah($goal['target_amount'])) ?> (<?= round($progress) ?>%)</div>
                                        <?php if ($goal['deadline']): ?>
                                            <div class="savings-deadline">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" style="width: 14px; height: 14px;">
                                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                                    <line x1="16" y1="2" x2="16" y2="6"/>
                                                    <line x1="8" y1="2" x2="8" y2="6"/>
                                                    <line x1="3" y1="10" x2="21" y2="10"/>
                                                </svg>
                                                Target: <?= e(date('d M Y', strtotime($goal['deadline']))) ?>
                                                <?php if ($daysLeft !== null && !$isCompleted): ?>
                                                    <span style="margin-left: var(--space-2); color: <?= $daysLeft <= 7 ? 'var(--color-warning)' : 'var(--color-text-muted)' ?>">
                                                        (<?= $daysLeft > 0 ? $daysLeft . ' hari lagi' : 'Lewat ' . abs($daysLeft) . ' hari' ?>)
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="savings-actions">
                                        <?php if (!$isCompleted): ?>
                                            <button class="btn btn-accent btn-sm" style="flex: 1;" onclick="openActionModal('topup', <?= htmlspecialchars(json_encode($goal), ENT_QUOTES, 'UTF-8') ?>)">Top Up</button>
                                            <button class="btn btn-secondary btn-sm" style="flex: 1;" onclick="openActionModal('withdraw', <?= htmlspecialchars(json_encode($goal), ENT_QUOTES, 'UTF-8') ?>)">Tarik</button>
                                        <?php else: ?>
                                            <button class="btn btn-secondary btn-sm" style="flex: 1;" disabled>Target Tercapai</button>
                                            <button class="btn btn-secondary btn-sm" style="flex: 1;" onclick="reopenGoal(<?= $goal['id'] ?>)">Buka Lagi</button>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>

    <!-- Savings Goal Modal -->
    <div class="modal-overlay" id="savingsModal" role="dialog" aria-modal="true" aria-labelledby="savingsModalTitle" hidden>
        <div class="modal">
            <header class="modal-header">
                <h3 id="savingsModalTitle" class="modal-title">Target Tabungan Baru</h3>
                <button type="button" class="modal-close" onclick="closeSavingsModal()" aria-label="Tutup">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </header>
            <form id="savingsForm" class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" id="savingsAction" value="create">
                <input type="hidden" name="id" id="savingsId">
                
                <div class="form-group">
                    <label for="savingsTitle">Judul Target <span class="required">*</span></label>
                    <input type="text" id="savingsTitle" name="title" required placeholder="Contoh: Liburan Akhir Tahun" maxlength="100">
                </div>

                <div class="form-group">
                    <label for="savingsTarget">Target Nominal (Rp) <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M12 2a10 10 0 1 0 10 10"/>
                            <path d="M12 6v6l4 2"/>
                        </svg>
                        <input type="number" id="savingsTarget" name="target_amount" min="1000" step="1000" required placeholder="Contoh: 5000000" aria-describedby="targetError">
                    </div>
                    <div id="targetError" class="form-error" aria-live="polite"></div>
                </div>

                <div class="form-group">
                    <label for="savingsDeadline">Tanggal Target (Opsional)</label>
                    <input type="date" id="savingsDeadline" name="deadline" min="<?= date('Y-m-d') ?>">
                    <div class="form-hint">Kosongkan jika tidak ada batas waktu</div>
                </div>
            </form>
            <footer class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeSavingsModal()">Batal</button>
                <button type="button" class="btn btn-primary" onclick="submitSavings()">Simpan</button>
            </footer>
        </div>
    </div>

    <!-- Top Up / Withdraw Modal -->
    <div class="modal-overlay" id="actionModal" role="dialog" aria-modal="true" aria-labelledby="actionModalTitle" hidden>
        <div class="modal" style="max-width: 400px;">
            <header class="modal-header">
                <h3 id="actionModalTitle" class="modal-title">Top Up Tabungan</h3>
                <button type="button" class="modal-close" onclick="closeActionModal()" aria-label="Tutup">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </header>
            <form id="actionForm" class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" id="actionType" value="topup">
                <input type="hidden" name="goal_id" id="actionGoalId">
                
                <div class="form-group">
                    <label>Target</label>
                    <p id="actionGoalTitle" style="font-weight: var(--font-weight-semibold); color: var(--color-text-primary);"></p>
                </div>
                
                <div class="form-group">
                    <label id="actionAmountLabel" for="actionAmount">Nominal Top Up (Rp) <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <line x1="12" y1="1" x2="12" y2="23"/>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                        <input type="number" id="actionAmount" name="amount" min="1000" step="1000" required placeholder="Contoh: 100000" aria-describedby="actionAmountError">
                    </div>
                    <div id="actionAmountError" class="form-error" aria-live="polite"></div>
                    <div class="form-hint" id="actionHint">Saldo tersedia: Rp0</div>
                </div>
            </form>
            <footer class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeActionModal()">Batal</button>
                <button type="button" class="btn btn-primary" id="actionSubmitBtn" onclick="submitAction()">Top Up</button>
            </footer>
        </div>
    </div>

    <?php if (isset($_SESSION['savings_action'])): 
        $action = $_SESSION['savings_action'];
        unset($_SESSION['savings_action']);
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            openActionModal('<?= $action['action'] ?>', <?= json_encode($action['goal']) ?>);
        });
    </script>
    <?php endif; ?>

    <script>
        // Modal handlers
        const savingsModal = document.getElementById('savingsModal');
        const savingsForm = document.getElementById('savingsForm');
        const actionModal = document.getElementById('actionModal');
        const actionForm = document.getElementById('actionForm');

        function openSavingsModal(data = null) {
            savingsForm.reset();
            if (data) {
                document.getElementById('savingsModalTitle').textContent = 'Edit Target';
                document.getElementById('savingsAction').value = 'update';
                document.getElementById('savingsId').value = data.id;
                document.getElementById('savingsTitle').value = data.title;
                document.getElementById('savingsTarget').value = data.target_amount;
                document.getElementById('savingsDeadline').value = data.deadline || '';
            } else {
                document.getElementById('savingsModalTitle').textContent = 'Target Tabungan Baru';
                document.getElementById('savingsAction').value = 'create';
                document.getElementById('savingsId').value = '';
            }
            savingsModal.hidden = false;
            document.body.style.overflow = 'hidden';
            setTimeout(() => document.getElementById('savingsTitle').focus(), 100);
        }

        function closeSavingsModal() {
            savingsModal.hidden = true;
            document.body.style.overflow = '';
            savingsForm.reset();
        }

        function submitSavings() {
            const btn = savingsModal.querySelector('.btn-primary');
            setButtonLoading(btn, true);
            const formData = new FormData(savingsForm);
            fetch('/serius/process/savings_process.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(d => { if (d.success) { showToast(d.message, 'success'); closeSavingsModal(); setTimeout(() => location.reload(), 800); } else showToast(d.message, 'error'); })
                .finally(() => setButtonLoading(btn, false));
        }

        window.openActionModal = function(type, goal) {
            const isTopup = type === 'topup';
            document.getElementById('actionModalTitle').textContent = isTopup ? 'Top Up Tabungan' : 'Tarik Tabungan';
            document.getElementById('actionType').value = type;
            document.getElementById('actionGoalId').value = goal.id;
            document.getElementById('actionGoalTitle').textContent = goal.title;
            document.getElementById('actionAmountLabel').textContent = (isTopup ? 'Nominal Top Up' : 'Nominal Tarik') + ' (Rp) *';
            document.getElementById('actionSubmitBtn').textContent = isTopup ? 'Top Up' : 'Tarik';
            
            // Update hint with available balance
            fetch('/serius/api/get_balance.php')
                .then(r => r.json())
                .then(d => {
                    document.getElementById('actionHint').textContent = 'Saldo tersedia: ' + d.formatted;
                    document.getElementById('actionAmount').max = d.balance;
                });
            
            actionModal.hidden = false;
            document.body.style.overflow = 'hidden';
            setTimeout(() => document.getElementById('actionAmount').focus(), 100);
        }

        function closeActionModal() {
            actionModal.hidden = true;
            document.body.style.overflow = '';
            actionForm.reset();
        }

        function submitAction() {
            const btn = document.getElementById('actionSubmitBtn');
            setButtonLoading(btn, true);
            const formData = new FormData(actionForm);
            fetch('/serius/process/savings_process.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(d => { if (d.success) { showToast(d.message, 'success'); closeActionModal(); setTimeout(() => location.reload(), 800); } else showToast(d.message, 'error'); })
                .finally(() => setButtonLoading(btn, false));
        }

        function reopenGoal(id) {
            const formData = new FormData();
            formData.append('action', 'reopen');
            formData.append('id', id);
            formData.append('csrf_token', savingsForm.querySelector('[name="csrf_token"]').value);
            fetch('/serius/process/savings_process.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(d => { if (d.success) { showToast(d.message, 'success'); setTimeout(() => location.reload(), 800); } else showToast(d.message, 'error'); });
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

        savingsModal.addEventListener('click', e => { if (e.target === savingsModal) closeSavingsModal(); });
        actionModal.addEventListener('click', e => { if (e.target === actionModal) closeActionModal(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') { if (!savingsModal.hidden) closeSavingsModal(); if (!actionModal.hidden) closeActionModal(); } });
    </script>
</body>
</html>