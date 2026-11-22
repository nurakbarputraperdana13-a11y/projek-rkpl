<?php
// pages/budgets.php - Halaman manajemen budget
// SakuKu - Aplikasi Manajemen Keuangan Mahasiswa

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/navbar.php';

$userId = getCurrentUserId();
$currentMonth = $_GET['month'] ?? date('Y-m');

// Ambil kategori expense untuk form
$pdo = getPDO();
$stmt = $pdo->prepare('SELECT * FROM categories WHERE type = "expense" AND (user_id = ? OR user_id IS NULL) ORDER BY name');
$stmt->execute([$userId]);
$expenseCategories = $stmt->fetchAll();

// Ambil budget bulan ini
$stmt = $pdo->prepare('
    SELECT b.*, c.name as category_name
    FROM budgets b
    JOIN categories c ON b.category_id = c.id
    WHERE b.user_id = ? AND b.month_year = ?
    ORDER BY c.name
');
$stmt->execute([$userId, $currentMonth]);
$budgets = $stmt->fetchAll();

// Hitung pengeluaran per kategori bulan ini
$monthlySpending = [];
$startDate = $currentMonth . '-01';
$endDate = date('Y-m-t', strtotime($startDate));
$stmt = $pdo->prepare('
    SELECT category_id, COALESCE(SUM(amount), 0) as total
    FROM transactions
    WHERE user_id = ? AND type = "expense" AND transaction_date BETWEEN ? AND ?
    GROUP BY category_id
');
$stmt->execute([$userId, $startDate, $endDate]);
foreach ($stmt->fetchAll() as $row) {
    $monthlySpending[$row['category_id']] = (int)$row['total'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget - SakuKu</title>
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
                    <h1 class="dashboard-title">Budget Bulanan</h1>
                    <p class="dashboard-subtitle">Kelola batas pengeluaran per kategori</p>
                </div>
                <div style="display: flex; gap: var(--space-2); align-items: center;">
                    <label for="monthSelect" class="sr-only">Pilih Bulan</label>
                    <input type="month" id="monthSelect" value="<?= e($currentMonth) ?>" onchange="window.location.href='?month='+this.value" style="padding: var(--space-2) var(--space-3); border: 1px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface); color: var(--color-text-primary);">
                    <button class="btn btn-primary" onclick="openBudgetModal()">+ Tambah Budget</button>
                </div>
            </header>

            <!-- Budget Cards -->
            <section class="chart-card" aria-labelledby="budgetTitle">
                <header class="chart-header">
                    <h2 id="budgetTitle" class="chart-title">Daftar Budget <?= date('F Y', strtotime($currentMonth . '-01')) ?></h2>
                </header>
                <div class="chart-container" style="padding: 0; height: auto;">
                    <?php if (empty($budgets)): ?>
                        <div class="empty-state" style="padding: var(--space-12);">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <line x1="18" y1="20" x2="18" y2="10"/>
                                <line x1="12" y1="20" x2="12" y2="4"/>
                                <line x1="6" y1="20" x2="6" y2="14"/>
                            </svg>
                            <h3>Belum ada budget</h3>
                            <p>Atur batas pengeluaran per kategori untuk mendapatkan peringatan otomatis</p>
                            <button class="btn btn-primary" onclick="openBudgetModal()">Buat Budget Pertama</button>
                        </div>
                    <?php else: ?>
                        <div style="display: grid; grid-template-columns: 1fr; gap: var(--space-3);">
                            @media (min-width: 640px) {
                                grid-template-columns: repeat(2, 1fr);
                            }
                            @media (min-width: 1024px) {
                                grid-template-columns: repeat(3, 1fr);
                            }
                            <?php foreach ($budgets as $budget): 
                                $spent = $monthlySpending[$budget['category_id']] ?? 0;
                                $percentage = $budget['amount_limit'] > 0 ? ($spent / $budget['amount_limit']) * 100 : 0;
                                $status = $percentage >= 100 ? 'overbudget' : ($percentage >= 80 ? 'warning' : 'safe');
                            ?>
                                <div class="card" style="padding: var(--space-4);">
                                    <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: var(--space-3); margin-bottom: var(--space-3);">
                                        <div style="flex: 1; min-width: 0;">
                                            <h3 style="font-size: var(--font-size-base); font-weight: var(--font-weight-semibold); margin: 0 0 var(--space-1);"><?= e($budget['category_name']) ?></h3>
                                            <p style="font-size: var(--font-size-sm); color: var(--color-text-muted); margin: 0;">
                                                <?= e(formatRupiah($spent)) ?> / <?= e(formatRupiah($budget['amount_limit'])) ?>
                                            </p>
                                        </div>
                                        <span class="badge" style="
                                            padding: var(--space-1) var(--space-2);
                                            border-radius: var(--radius-full);
                                            font-size: var(--font-size-xs);
                                            font-weight: var(--font-weight-medium);
                                            background: <?= $status === 'overbudget' ? 'rgba(220, 38, 38, 0.15)' : ($status === 'warning' ? 'rgba(217, 119, 6, 0.15)' : 'rgba(5, 150, 105, 0.15)') ?>;
                                            color: <?= $status === 'overbudget' ? 'var(--color-destructive)' : ($status === 'warning' ? 'var(--color-warning)' : 'var(--color-accent)') ?>;
                                        ">
                                            <?= $status === 'overbudget' ? 'Overbudget' : ($status === 'warning' ? 'Peringatan' : 'Aman') ?>
                                        </span>
                                    </div>
                                    <div class="savings-progress-bar">
                                        <div class="savings-progress-fill" style="width: <?= min(100, $percentage) ?>%; background: <?= $status === 'overbudget' ? 'var(--color-destructive)' : ($status === 'warning' ? 'var(--color-warning)' : 'var(--color-accent)') ?>"></div>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; margin-top: var(--space-2); font-size: var(--font-size-xs); color: var(--color-text-muted);">
                                        <span><?= round($percentage) ?>% terpakai</span>
                                        <span>Sisa: <?= e(formatRupiah(max(0, $budget['amount_limit'] - $spent))) ?></span>
                                    </div>
                                    <div class="table-actions" style="margin-top: var(--space-3);">
                                        <button class="btn-icon-sm" onclick="editBudget(<?= htmlspecialchars(json_encode($budget), ENT_QUOTES, 'UTF-8') ?>)" aria-label="Edit budget">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                            </svg>
                                        </button>
                                        <button class="btn-icon-sm" onclick="deleteBudget(<?= $budget['id'] ?>)" aria-label="Hapus budget" style="color: var(--color-destructive);">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <polyline points="3 6 5 6 21 6"/>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Budget Overview Chart -->
            <?php if (!empty($budgets)): ?>
            <section class="chart-card" aria-labelledby="chartTitle">
                <header class="chart-header">
                    <h2 id="chartTitle" class="chart-title">Visualisasi Budget vs Realisasi</h2>
                </header>
                <div class="chart-container">
                    <canvas id="budgetChart" aria-label="Grafik batas budget vs pengeluaran real per kategori"></canvas>
                </div>
            </section>
            <?php endif; ?>
        </div>
    </main>

    <!-- Budget Modal -->
    <div class="modal-overlay" id="budgetModal" role="dialog" aria-modal="true" aria-labelledby="budgetModalTitle" hidden>
        <div class="modal">
            <header class="modal-header">
                <h3 id="budgetModalTitle" class="modal-title">Tambah Budget</h3>
                <button type="button" class="modal-close" onclick="closeBudgetModal()" aria-label="Tutup">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </header>
            <form id="budgetForm" class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" id="budgetAction" value="create">
                <input type="hidden" name="id" id="budgetId">
                <input type="hidden" name="month" value="<?= e($currentMonth) ?>">
                
                <div class="form-group">
                    <label for="budgetCategory">Kategori <span class="required">*</span></label>
                    <select id="budgetCategory" name="category_id" required>
                        <option value="">-- Pilih Kategori Pengeluaran --</option>
                        <?php foreach ($expenseCategories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="budgetAmount">Batas Budget (Rp) <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <line x1="18" y1="20" x2="18" y2="10"/>
                            <line x1="12" y1="20" x2="12" y2="4"/>
                            <line x1="6" y1="20" x2="6" y2="14"/>
                        </svg>
                        <input type="number" id="budgetAmount" name="amount_limit" min="1" step="1000" required placeholder="Contoh: 500000" aria-describedby="budgetAmountError">
                    </div>
                    <div id="budgetAmountError" class="form-error" aria-live="polite"></div>
                </div>
            </form>
            <footer class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeBudgetModal()">Batal</button>
                <button type="button" class="btn btn-primary" onclick="submitBudget()">Simpan</button>
            </footer>
        </div>
    </div>

    <script>
        // Budget Chart
        const ctx = document.getElementById('budgetChart');
        if (ctx) {
            const labels = <?= json_encode(array_column($budgets, 'category_name')) ?>;
            const budgets = <?= json_encode(array_column($budgets, 'amount_limit')) ?>;
            const spent = labels.map((_, i) => <?= json_encode($monthlySpending) ?>[<?= json_encode(array_column($budgets, 'category_id')) ?>[i]] || 0);

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Budget',
                            data: budgets,
                            backgroundColor: 'rgba(30, 64, 175, 0.6)',
                            borderColor: '#1E40AF',
                            borderWidth: 1,
                            borderRadius: 6,
                        },
                        {
                            label: 'Terpakai',
                            data: spent,
                            backgroundColor: 'rgba(220, 38, 38, 0.8)',
                            borderColor: '#DC2626',
                            borderWidth: 1,
                            borderRadius: 6,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top', labels: { color: '#94A3B8', font: { family: 'Source Sans 3', size: 12 }, padding: 20, usePointStyle: true, pointStyle: 'rectRounded' } },
                        tooltip: { backgroundColor: '#192134', titleColor: '#F8FAFC', bodyColor: '#E2E8F0', borderColor: 'rgba(255,255,255,0.08)', borderWidth: 1, padding: 12, callbacks: { label: (ctx) => ctx.dataset.label + ': Rp' + ctx.raw.toLocaleString('id-ID') } }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: '#64748B', font: { family: 'Source Sans 3', size: 11 } } },
                        y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748B', font: { family: 'Source Sans 3', size: 11 }, callback: (v) => 'Rp' + v.toLocaleString('id-ID') } }
                    }
                }
            });
        }

        // Modal handlers
        const modal = document.getElementById('budgetModal');
        const form = document.getElementById('budgetForm');

        function openBudgetModal(data = null) {
            form.reset();
            if (data) {
                document.getElementById('budgetModalTitle').textContent = 'Edit Budget';
                document.getElementById('budgetAction').value = 'update';
                document.getElementById('budgetId').value = data.id;
                document.getElementById('budgetCategory').value = data.category_id;
                document.getElementById('budgetAmount').value = data.amount_limit;
            } else {
                document.getElementById('budgetModalTitle').textContent = 'Tambah Budget';
                document.getElementById('budgetAction').value = 'create';
                document.getElementById('budgetId').value = '';
            }
            modal.hidden = false;
            document.body.style.overflow = 'hidden';
            setTimeout(() => document.getElementById('budgetCategory').focus(), 100);
        }

        function closeBudgetModal() {
            modal.hidden = true;
            document.body.style.overflow = '';
            form.reset();
        }

        function editBudget(data) { openBudgetModal(data); }

        function deleteBudget(id) {
            if (!confirm('Hapus budget ini?')) return;
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);
            formData.append('csrf_token', form.querySelector('[name="csrf_token"]').value);
            fetch('/serius/process/budget_process.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(d => { if (d.success) { showToast(d.message, 'success'); setTimeout(() => location.reload(), 800); } else showToast(d.message, 'error'); });
        }

        function submitBudget() {
            const btn = modal.querySelector('.btn-primary');
            setButtonLoading(btn, true);
            const formData = new FormData(form);
            fetch('/serius/process/budget_process.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(d => { if (d.success) { showToast(d.message, 'success'); closeBudgetModal(); setTimeout(() => location.reload(), 800); } else showToast(d.message, 'error'); })
                .finally(() => setButtonLoading(btn, false));
        }

        function setButtonLoading(btn, loading) {
            const text = btn.querySelector('.btn-text') || btn;
            if (loading) { btn.disabled = true; text.textContent = 'Menyimpan...'; }
            else { btn.disabled = false; text.textContent = 'Simpan'; }
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

        modal.addEventListener('click', e => { if (e.target === modal) closeBudgetModal(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape' && !modal.hidden) closeBudgetModal(); });
    </script>
</body>
</html>