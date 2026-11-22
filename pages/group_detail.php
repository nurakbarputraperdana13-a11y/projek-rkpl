<?php
// pages/group_detail.php - Detail grup & split bill
// SakuKu - Aplikasi Manajemen Keuangan Mahasiswa

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/navbar.php';

$userId = getCurrentUserId();
$groupId = (int)($_GET['id'] ?? 0);

if (!$groupId) {
    header('Location: /serius/pages/groups.php');
    exit;
}

$pdo = getPDO();

// Verifikasi keanggotaan
$stmt = $pdo->prepare('SELECT g.*, gm.role FROM groups g JOIN group_members gm ON g.id = gm.group_id WHERE g.id = ? AND gm.user_id = ?');
$stmt->execute([$groupId, $userId]);
$group = $stmt->fetch();

if (!$group) {
    header('Location: /serius/pages/groups.php');
    exit;
}

$isAdmin = $group['role'] === 'admin';

// Ambil anggota
$stmt = $pdo->prepare('
    SELECT gm.*, u.name, u.email,
           (SELECT COALESCE(SUM(amount), 0) FROM group_expenses WHERE group_id = ? AND paid_by = u.id_user) as total_paid,
           (SELECT COALESCE(SUM(es.share_amount), 0) FROM expense_shares es JOIN group_expenses ge ON es.expense_id = ge.id WHERE ge.group_id = ? AND es.user_id = u.id_user) as total_share
    FROM group_members gm
    JOIN users u ON gm.user_id = u.id_user
    WHERE gm.group_id = ?
    ORDER BY gm.role DESC, u.name
');
$stmt->execute([$groupId, $groupId, $groupId]);
$members = $stmt->fetchAll();

// Hitung net balance per anggota
foreach ($members as &$m) {
    $m['net_balance'] = (int)$m['total_paid'] - (int)$m['total_share'];
}

// Ambil expenses
$stmt = $pdo->prepare('
    SELECT ge.*, u.name as paid_by_name, c.name as category_name
    FROM group_expenses ge
    JOIN users u ON ge.paid_by = u.id_user
    LEFT JOIN categories c ON ge.category_id = c.id
    WHERE ge.group_id = ?
    ORDER BY ge.expense_date DESC, ge.created_at DESC
');
$stmt->execute([$groupId]);
$expenses = $stmt->fetchAll();

// Ambil kategori untuk dropdown
$stmt = $pdo->prepare('SELECT * FROM categories WHERE type = "expense" AND (user_id = ? OR user_id IS NULL) ORDER BY name');
$stmt->execute([$userId]);
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($group['name']) ?> - SakuKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700&family=Source+Sans+3:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/serius/assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <main class="dashboard" role="main">
        <div class="dashboard-container">
            <header class="dashboard-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: var(--space-4);">
                <div>
                    <a href="/serius/pages/groups.php" class="btn btn-secondary btn-sm" style="margin-bottom: var(--space-2); display: inline-flex; align-items: center; gap: var(--space-1);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                        Kembali
                    </a>
                    <h1 class="dashboard-title"><?= e($group['name']) ?></h1>
                    <p class="dashboard-subtitle"><?= e($group['description'] ?: 'Grup split bill') ?></p>
                </div>
                <div style="display: flex; gap: var(--space-2);">
                    <?php if ($isAdmin): ?>
                        <button class="btn btn-primary" onclick="openExpenseModal()">+ Tambah Pengeluaran</button>
                        <button class="btn btn-secondary" onclick="openInviteModal()">Undang</button>
                    <?php endif; ?>
                    <button class="btn btn-secondary" onclick="openSettleModal()">Lunas</button>
                </div>
            </header>

            <!-- Balance Summary Cards -->
            <div class="dashboard-grid" style="margin-bottom: var(--space-6);">
                <article class="stat-card income">
                    <header class="stat-header">
                        <div class="stat-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a10 10 0 1 0 10 10"/><path d="M12 6v6l4 2"/></svg>
                        </div>
                    </header>
                    <div class="stat-amount"><?= e(formatRupiah(array_sum(array_column($members, 'total_paid')))) ?></div>
                    <div class="stat-label">Total Dibayar</div>
                </article>

                <article class="stat-card expense">
                    <header class="stat-header">
                        <div class="stat-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                        </div>
                    </header>
                    <div class="stat-amount"><?= e(formatRupiah(array_sum(array_column($members, 'total_share')))) ?></div>
                    <div class="stat-label">Total Bagi</div>
                </article>

                <article class="stat-card balance">
                    <header class="stat-header">
                        <div class="stat-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </div>
                    </header>
                    <div class="stat-amount" style="color: var(--color-text-primary);"><?= e(formatRupiah(array_sum(array_column($members, 'net_balance')))) ?></div>
                    <div class="stat-label">Net Balance (Harus 0)</div>
                </article>
            </div>

            <!-- Member Balance Cards -->
            <section class="chart-card" aria-labelledby="balanceTitle">
                <header class="chart-header">
                    <h2 id="balanceTitle" class="chart-title">Saldo Anggota</h2>
                    <div class="form-hint">Positif = dikasih uang, Negatif = bayar uang</div>
                </header>
                <div class="chart-container" style="padding: 0; height: auto;">
                    <div style="display: grid; grid-template-columns: 1fr; gap: var(--space-3);">
                        @media (min-width: 640px) { grid-template-columns: repeat(2, 1fr); }
                        @media (min-width: 1024px) { grid-template-columns: repeat(3, 1fr); }
                        <?php foreach ($members as $m): 
                            $balance = (int)$m['net_balance'];
                            $status = $balance > 0 ? 'receive' : ($balance < 0 ? 'pay' : 'settled');
                        ?>
                            <div class="balance-card <?= $status ?>">
                                <div class="balance-avatar" aria-hidden="true"><?= e(mb_substr($m['name'], 0, 1)) ?></div>
                                <div class="balance-info">
                                    <h4 class="balance-name"><?= e($m['name']) ?></h4>
                                    <p class="balance-role"><?= $m['role'] === 'admin' ? 'Admin' : 'Anggota' ?></p>
                                </div>
                                <div class="balance-amount" style="color: <?= $balance > 0 ? 'var(--color-accent)' : ($balance < 0 ? 'var(--color-destructive)' : 'var(--color-text-muted)') ?>">
                                    <?= $balance > 0 ? '+' : '' ?><?= e(formatRupiah($balance)) ?>
                                </div>
                                <div class="balance-detail">
                                    <span>Dibayar: <?= e(formatRupiah($m['total_paid'])) ?></span>
                                    <span>Bagi: <?= e(formatRupiah($m['total_share'])) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <!-- Expenses List -->
            <section class="chart-card" aria-labelledby="expenseTitle">
                <header class="chart-header">
                    <h2 id="expenseTitle" class="chart-title">Daftar Pengeluaran</h2>
                </header>
                <div class="chart-container" style="padding: 0; height: auto;">
                    <?php if (empty($expenses)): ?>
                        <div class="empty-state" style="padding: var(--space-12);">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                            <h3>Belum ada pengeluaran</h3>
                            <p>Tambah pengeluaran pertama untuk mulai split bill</p>
                            <?php if ($isAdmin): ?>
                                <button class="btn btn-primary" onclick="openExpenseModal()">Tambah Pengeluaran</button>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Tanggal</th>
                                        <th>Deskripsi</th>
                                        <th>Kategori</th>
                                        <th>Dibayar Oleh</th>
                                        <th>Jumlah</th>
                                        <th>Split</th>
                                        <?php if ($isAdmin): ?><th>Aksi</th><?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($expenses as $e): ?>
                                        <tr>
                                            <td><?= e(date('d M Y', strtotime($e['expense_date']))) ?></td>
                                            <td><?= e($e['description']) ?></td>
                                            <td><span class="category-badge"><?= e($e['category_name'] ?: '-') ?></span></td>
                                            <td><?= e($e['paid_by_name']) ?></td>
                                            <td style="font-weight: var(--font-weight-semibold);"><?= e(formatRupiah($e['total_amount'])) ?></td>
                                            <td>
                                                <span class="badge" style="background: var(--color-accent-bg); color: var(--color-accent);"><?= $e['split_method'] === 'equal' ? 'Rata' : 'Custom' ?></span>
                                            </td>
                                            <?php if ($isAdmin): ?>
                                                <td>
                                                    <div class="table-actions">
                                                        <button class="btn-icon-sm" onclick="viewExpenseDetail(<?= $e['id'] ?>)" aria-label="Lihat detail"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
                                                        <button class="btn-icon-sm" onclick="editExpense(<?= htmlspecialchars(json_encode($e), ENT_QUOTES, 'UTF-8') ?>)" aria-label="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                                                        <button class="btn-icon-sm" onclick="deleteExpense(<?= $e['id'] ?>)" aria-label="Hapus" style="color: var(--color-destructive);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                                                    </div>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Settlement Flow Chart -->
            <?php if (count($members) >= 2 && array_sum(array_column($members, 'net_balance')) == 0): ?>
            <section class="chart-card" aria-labelledby="settlementTitle">
                <header class="chart-header">
                    <h2 id="settlementTitle" class="chart-title">Alur Pelunasan (Settlement)</h2>
                    <div class="form-hint">Tarik dari yang negatif ke yang positif</div>
                </header>
                <div class="chart-container" style="height: 300px;">
                    <canvas id="settlementChart"></canvas>
                </div>
            </section>
            <?php endif; ?>
        </div>
    </main>

    <!-- Expense Modal -->
    <div class="modal-overlay" id="expenseModal" role="dialog" aria-modal="true" aria-labelledby="expenseModalTitle" hidden>
        <div class="modal" style="max-width: 600px;">
            <header class="modal-header">
                <h3 id="expenseModalTitle" class="modal-title">Tambah Pengeluaran</h3>
                <button type="button" class="modal-close" onclick="closeExpenseModal()" aria-label="Tutup">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </header>
            <form id="expenseForm" class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" id="expenseAction" value="create">
                <input type="hidden" name="group_id" value="<?= $groupId ?>">
                <input type="hidden" name="id" id="expenseId">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="expenseDate">Tanggal <span class="required">*</span></label>
                        <input type="date" id="expenseDate" name="expense_date" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label for="expenseCategory">Kategori</label>
                        <select id="expenseCategory" name="category_id">
                            <option value="">-- Pilih Kategori --</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="expenseDesc">Deskripsi <span class="required">*</span></label>
                    <input type="text" id="expenseDesc" name="description" required placeholder="Contoh: Belanja bulanan / Listrik bulan ini" maxlength="255">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="expenseAmount">Total Jumlah (Rp) <span class="required">*</span></label>
                        <div class="input-wrapper">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                            <input type="number" id="expenseAmount" name="total_amount" min="1" step="1000" required placeholder="Contoh: 150000" aria-describedby="expenseAmountError">
                        </div>
                        <div id="expenseAmountError" class="form-error" aria-live="polite"></div>
                    </div>
                    <div class="form-group">
                        <label for="expensePaidBy">Dibayar Oleh <span class="required">*</span></label>
                        <select id="expensePaidBy" name="paid_by" required>
                            <?php foreach ($members as $m): ?>
                                <option value="<?= $m['user_id'] ?>"><?= e($m['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Metode Split <span class="required">*</span></label>
                    <div style="display: flex; gap: var(--space-4); flex-wrap: wrap;">
                        <label style="display: flex; align-items: center; gap: var(--space-2); cursor: pointer;">
                            <input type="radio" name="split_method" value="equal" checked>
                            <span>Rata (Equal)</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: var(--space-2); cursor: pointer;">
                            <input type="radio" name="split_method" value="custom">
                            <span>Custom (Persentase)</span>
                        </label>
                    </div>
                </div>

                <div id="customSplitSection" style="display: none; margin-top: var(--space-4);">
                    <p class="form-hint">Total persentase harus 100%</p>
                    <div id="customSplitInputs"></div>
                </div>
            </form>
            <footer class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeExpenseModal()">Batal</button>
                <button type="button" class="btn btn-primary" onclick="submitExpense()">Simpan</button>
            </footer>
        </div>
    </div>

    <!-- Invite Modal -->
    <div class="modal-overlay" id="inviteModal" role="dialog" aria-modal="true" aria-labelledby="inviteModalTitle" hidden>
        <div class="modal" style="max-width: 400px;">
            <header class="modal-header">
                <h3 id="inviteModalTitle" class="modal-title">Undang Anggota</h3>
                <button type="button" class="modal-close" onclick="closeInviteModal()" aria-label="Tutup"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
            </header>
            <div class="modal-body" style="text-align: center;">
                <p style="color: var(--color-text-secondary); margin-bottom: var(--space-4)">Bagikan kode:</p>
                <code id="inviteCodeDisplay" style="flex: 1; background: var(--color-background); padding: var(--space-3); border-radius: var(--radius-md); font-size: var(--font-size-xl); font-weight: var(--font-weight-bold); letter-spacing: 0.1em; color: var(--color-accent); font-family: var(--font-mono); display: block;"></code>
                <button class="btn btn-secondary" onclick="copyInviteCode()" style="margin-top: var(--space-3);">Salin Kode</button>
            </div>
            <footer class="modal-footer"><button type="button" class="btn btn-primary" onclick="closeInviteModal()">Tutup</button></footer>
        </div>
    </div>

    <!-- Settle Modal -->
    <div class="modal-overlay" id="settleModal" role="dialog" aria-modal="true" aria-labelledby="settleModalTitle" hidden>
        <div class="modal" style="max-width: 500px;">
            <header class="modal-header">
                <h3 id="settleModalTitle" class="modal-title">Konfirmasi Pelunasan</h3>
                <button type="button" class="modal-close" onclick="closeSettleModal()" aria-label="Tutup"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
            </header>
            <div class="modal-body">
                <p style="color: var(--color-text-secondary); margin-bottom: var(--space-4);">Net balance saat ini:</p>
                <ul id="settleSummary" style="list-style: none; padding: 0; margin: 0 0 var(--space-4);"></ul>
                <div class="form-group">
                    <label for="settleFrom">Dari (yang bayar) <span class="required">*</span></label>
                    <select id="settleFrom" name="from_user" required>
                        <option value="">-- Pilih --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="settleTo">Ke (yang terima) <span class="required">*</span></label>
                    <select id="settleTo" name="to_user" required>
                        <option value="">-- Pilih --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="settleAmount">Jumlah (Rp) <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                        <input type="number" id="settleAmount" name="amount" min="1" step="1000" required placeholder="Contoh: 50000">
                    </div>
                </div>
            </div>
            <footer class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeSettleModal()">Batal</button>
                <button type="button" class="btn btn-primary" onclick="submitSettle()">Catat Lunas</button>
            </footer>
        </div>
    </div>

    <!-- Expense Detail Modal -->
    <div class="modal-overlay" id="detailModal" role="dialog" aria-modal="true" aria-labelledby="detailModalTitle" hidden>
        <div class="modal" style="max-width: 600px;">
            <header class="modal-header">
                <h3 id="detailModalTitle" class="modal-title">Detail Pengeluaran</h3>
                <button type="button" class="modal-close" onclick="closeDetailModal()" aria-label="Tutup"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
            </header>
            <div class="modal-body" id="detailContent"></div>
            <footer class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeDetailModal()">Tutup</button></footer>
        </div>
    </div>

    <script>
        // Chart: Settlement Flow
        const settlementData = <?= json_encode(array_filter($members, fn($m) => $m['net_balance'] !== 0)) ?>;
        if (settlementData.length >= 2) {
            const ctx = document.getElementById('settlementChart');
            const debtors = settlementData.filter(m => m.net_balance < 0).sort((a,b) => a.net_balance - b.net_balance);
            const creditors = settlementData.filter(m => m.net_balance > 0).sort((a,b) => b.net_balance - a.net_balance);
            
            const labels = [...debtors.map(m => m.name + ' (bayar)'), ...creditors.map(m => m.name + ' (terima)')];
            const values = [...debtors.map(m => m.net_balance), ...creditors.map(m => m.net_balance)];
            const colors = [...debtors.map(() => 'rgba(220, 38, 38, 0.8)'), ...creditors.map(() => 'rgba(5, 150, 105, 0.8)')];

            new Chart(ctx, {
                type: 'bar',
                data: { labels, datasets: [{ label: 'Net Balance', data: values, backgroundColor: colors, borderRadius: 6 }] },
                options: {
                    indexAxis: 'y',
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip: { backgroundColor: '#192134', titleColor: '#F8FAFC', bodyColor: '#E2E8F0', borderColor: 'rgba(255,255,255,0.08)', borderWidth: 1, padding: 12, callbacks: { label: (ctx) => ctx.raw > 0 ? 'Terima: Rp' + ctx.raw.toLocaleString('id-ID') : 'Bayar: Rp' + Math.abs(ctx.raw).toLocaleString('id-ID') } } },
                    scales: { x: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748B', font: { family: 'Source Sans 3', size: 11 }, callback: (v) => 'Rp' + Math.abs(v).toLocaleString('id-ID') } }, y: { grid: { display: false }, ticks: { color: '#E2E8F0', font: { family: 'Source Sans 3', size: 11 } } } }
                }
            });
        }

        // Modals
        const expenseModal = document.getElementById('expenseModal');
        const expenseForm = document.getElementById('expenseForm');
        const inviteModal = document.getElementById('inviteModal');
        const settleModal = document.getElementById('settleModal');
        const detailModal = document.getElementById('detailModal');

        function openExpenseModal(data = null) {
            expenseForm.reset();
            document.getElementById('expenseDate').value = '<?= date('Y-m-d') ?>';
            if (data) {
                document.getElementById('expenseModalTitle').textContent = 'Edit Pengeluaran';
                document.getElementById('expenseAction').value = 'update';
                document.getElementById('expenseId').value = data.id;
                document.getElementById('expenseDate').value = data.expense_date;
                document.getElementById('expenseCategory').value = data.category_id || '';
                document.getElementById('expenseDesc').value = data.description;
                document.getElementById('expenseAmount').value = data.total_amount;
                document.getElementById('expensePaidBy').value = data.paid_by;
                document.querySelector('input[name="split_method"][value="' + data.split_method + '"]').checked = true;
                toggleCustomSplit();
                if (data.split_method === 'custom') {
                    // Custom shares would need to be loaded separately
                }
            } else {
                document.getElementById('expenseModalTitle').textContent = 'Tambah Pengeluaran';
                document.getElementById('expenseAction').value = 'create';
                document.getElementById('expenseId').value = '';
            }
            // Setup custom split inputs
            setupCustomSplitInputs();
            expenseModal.hidden = false;
            document.body.style.overflow = 'hidden';
        }

        function closeExpenseModal() { expenseModal.hidden = true; document.body.style.overflow = ''; expenseForm.reset(); }

        function submitExpense() {
            const btn = expenseModal.querySelector('.btn-primary');
            setButtonLoading(btn, true);
            const formData = new FormData(expenseForm);
            // Add custom shares if needed
            if (document.querySelector('input[name="split_method"]:checked').value === 'custom') {
                const shares = {};
                document.querySelectorAll('#customSplitInputs input[type="number"]').forEach(inp => {
                    shares[inp.dataset.userId] = inp.value;
                });
                formData.append('custom_shares', JSON.stringify(shares));
            }
            fetch('/serius/process/split_process.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(d => { if (d.success) { showToast(d.message, 'success'); closeExpenseModal(); setTimeout(() => location.reload(), 800); } else showToast(d.message, 'error'); })
                .finally(() => setButtonLoading(btn, false));
        }

        function toggleCustomSplit() {
            const show = document.querySelector('input[name="split_method"]:checked').value === 'custom';
            document.getElementById('customSplitSection').style.display = show ? 'block' : 'none';
        }

        function setupCustomSplitInputs() {
            const container = document.getElementById('customSplitInputs');
            container.innerHTML = '';
            const method = document.querySelector('input[name="split_method"]:checked').value;
            if (method !== 'custom') return;
            
            const members = <?= json_encode($members) ?>;
            members.forEach(m => {
                const div = document.createElement('div');
                div.className = 'form-group';
                div.innerHTML = `
                    <label for="share_${m.user_id}">${m.name}</label>
                    <div class="input-wrapper">
                        <input type="number" id="share_${m.user_id}" name="share_${m.user_id}" min="0" max="100" step="0.01" data-user-id="${m.user_id}" placeholder="%">%
                    </div>
                `;
                container.appendChild(div);
            });
        }

        document.querySelectorAll('input[name="split_method"]').forEach(r => r.addEventListener('change', () => {
            toggleCustomSplit();
            setupCustomSplitInputs();
        }));

        function openInviteModal() {
            document.getElementById('inviteCodeDisplay').textContent = '<?= e($group['invite_code']) ?>';
            inviteModal.hidden = false;
            document.body.style.overflow = 'hidden';
        }
        function closeInviteModal() { inviteModal.hidden = true; document.body.style.overflow = ''; }
        function copyInviteCode() {
            const code = document.getElementById('inviteCodeDisplay').textContent;
            navigator.clipboard.writeText(code);
            showToast('Kode disalin!', 'success');
        }

        function openSettleModal() {
            // Populate settle summary
            const members = <?= json_encode($members) ?>;
            const summary = document.getElementById('settleSummary');
            summary.innerHTML = members.map(m => {
                const bal = m.net_balance;
                if (bal === 0) return '';
                return `<li style="display: flex; justify-content: space-between; padding: var(--space-2) 0; border-bottom: 1px solid var(--color-border);">
                    <span>${m.name}</span>
                    <strong style="color: ${bal > 0 ? 'var(--color-accent)' : 'var(--color-destructive)'}">${bal > 0 ? '+' : ''}${bal.toLocaleString('id-ID')}</strong>
                </li>`;
            }).join('');
            
            // Populate dropdowns
            const fromSelect = document.getElementById('settleFrom');
            const toSelect = document.getElementById('settleTo');
            fromSelect.innerHTML = '<option value="">-- Pilih --</option>';
            toSelect.innerHTML = '<option value="">-- Pilih --</option>';
            members.forEach(m => {
                if (m.net_balance < 0) {
                    fromSelect.innerHTML += `<option value="${m.user_id}">${m.name} (bayar ${Math.abs(m.net_balance).toLocaleString('id-ID')})</option>`;
                }
                if (m.net_balance > 0) {
                    toSelect.innerHTML += `<option value="${m.user_id}">${m.name} (terima ${m.net_balance.toLocaleString('id-ID')})</option>`;
                }
            });
            
            settleModal.hidden = false;
            document.body.style.overflow = 'hidden';
        }
        function closeSettleModal() { settleModal.hidden = true; document.body.style.overflow = ''; }
        function submitSettle() {
            const btn = settleModal.querySelector('.btn-primary');
            setButtonLoading(btn, true);
            const formData = new FormData();
            formData.append('action', 'settle');
            formData.append('group_id', '<?= $groupId ?>');
            formData.append('from_user', document.getElementById('settleFrom').value);
            formData.append('to_user', document.getElementById('settleTo').value);
            formData.append('amount', document.getElementById('settleAmount').value);
            formData.append('csrf_token', expenseForm.querySelector('[name="csrf_token"]').value);
            fetch('/serius/process/split_process.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(d => { if (d.success) { showToast(d.message, 'success'); closeSettleModal(); setTimeout(() => location.reload(), 800); } else showToast(d.message, 'error'); })
                .finally(() => setButtonLoading(btn, false));
        }

        function viewExpenseDetail(id) {
            fetch('/serius/process/split_process.php?action=detail&id=' + id)
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        document.getElementById('detailContent').innerHTML = d.html;
                        detailModal.hidden = false;
                        document.body.style.overflow = 'hidden';
                    } else showToast(d.message, 'error');
                });
        }
        function closeDetailModal() { detailModal.hidden = true; document.body.style.overflow = ''; }

        function editExpense(data) { openExpenseModal(data); }
        function deleteExpense(id) {
            if (!confirm('Hapus pengeluaran ini? Semua split terkait akan terhapus.')) return;
            const formData = new FormData();
            formData.append('action', 'delete_expense');
            formData.append('id', id);
            formData.append('csrf_token', expenseForm.querySelector('[name="csrf_token"]').value);
            fetch('/serius/process/split_process.php', { method: 'POST', body: formData })
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

        [expenseModal, inviteModal, settleModal, detailModal].forEach(m => m.addEventListener('click', e => { if (e.target === m) { if (m === expenseModal) closeExpenseModal(); else if (m === inviteModal) closeInviteModal(); else if (m === settleModal) closeSettleModal(); else closeDetailModal(); } }));
        document.addEventListener('keydown', e => { if (e.key === 'Escape') { if (!expenseModal.hidden) closeExpenseModal(); if (!inviteModal.hidden) closeInviteModal(); if (!settleModal.hidden) closeSettleModal(); if (!detailModal.hidden) closeDetailModal(); } });
    </script>
</body>
</html>