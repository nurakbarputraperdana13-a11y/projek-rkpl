<?php
// pages/dashboard.php - Dashboard utama
// SakuKu - Aplikasi Manajemen Keuangan Mahasiswa

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/navbar.php';

$userId = getCurrentUserId();
$userName = getCurrentUserName();

// Hitung statistik
$totalIncome = getTotalIncome($userId);
$totalExpense = getTotalExpense($userId);
$totalSavings = getTotalActiveSavings($userId);
$availableBalance = getAvailableBalance($userId);

// Data untuk chart (6 bulan terakhir)
$months = [];
$incomeData = [];
$expenseData = [];
for ($i = 5; $i >= 0; $i--) {
    $date = date('Y-m', strtotime("-$i months"));
    $months[] = date('M Y', strtotime($date . '-01'));
    $incomeData[] = getTotalIncome($userId, $date . '-01', date('Y-m-t', strtotime($date . '-01')));
    $expenseData[] = getTotalExpense($userId, $date . '-01', date('Y-m-t', strtotime($date . '-01')));
}

// Budget alerts
$budgetAlerts = getBudgetAlerts($userId);

// Transaksi terbaru (5 terakhir)
$pdo = getPDO();
$stmt = $pdo->prepare('
    SELECT t.*, c.name as category_name, c.icon as category_icon
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ?
    ORDER BY t.transaction_date DESC, t.created_at DESC
    LIMIT 5
');
$stmt->execute([$userId]);
$recentTransactions = $stmt->fetchAll();

// Savings goals (aktif, 3 teratas)
$stmt = $pdo->prepare('
    SELECT * FROM savings_goals
    WHERE user_id = ? AND status = "active"
    ORDER BY deadline ASC, created_at ASC
    LIMIT 3
');
$stmt->execute([$userId]);
$savingsGoals = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SakuKu</title>
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
            <!-- Header -->
            <header class="dashboard-header">
                <h1 class="dashboard-title">Halo, <?= e($userName) ?>! 👋</h1>
                <p class="dashboard-subtitle">Berikut ringkasan keuanganmu hari ini</p>
            </header>

            <!-- Budget Alerts -->
            <?php if (!empty($budgetAlerts)): ?>
                <div class="budget-alerts" role="region" aria-label="Peringatan budget">
                    <?php foreach ($budgetAlerts as $alert): ?>
                        <div class="budget-alert <?= $alert['status'] ?>" role="alert">
                            <svg class="budget-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <?php if ($alert['status'] === 'overbudget'): ?>
                                    <circle cx="12" cy="12" r="10"/>
                                    <line x1="12" y1="8" x2="12" y2="12"/>
                                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                                <?php else: ?>
                                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                    <line x1="12" y1="9" x2="12" y2="13"/>
                                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                                <?php endif; ?>
                            </svg>
                            <div class="budget-alert-content">
                                <div class="budget-alert-title">
                                    <?= $alert['status'] === 'overbudget' ? 'Budget Terlampaui' : 'Budget Hampir Habis' ?>: <?= e($alert['category']) ?>
                                </div>
                                <div class="budget-alert-details">
                                    Terpakai <?= e(formatRupiah($alert['spent'])) ?> dari <?= e(formatRupiah($alert['limit'])) ?> (<?= $alert['percentage'] ?>%)
                                </div>
                                <div class="budget-alert-progress">
                                    <div class="budget-alert-progress-bar" style="width: <?= min(100, $alert['percentage']) ?>%"></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="dashboard-grid" role="region" aria-label="Ringkasan keuangan">
                <article class="stat-card income">
                    <header class="stat-header">
                        <div class="stat-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="19" x2="12" y2="5"/>
                                <polyline points="5 12 12 5 19 12"/>
                            </svg>
                        </div>
                        <span class="stat-trend positive">+12% dari bulan lalu</span>
                    </header>
                    <div class="stat-amount"><?= e(formatRupiah($totalIncome)) ?></div>
                    <div class="stat-label">Total Pemasukan</div>
                </article>

                <article class="stat-card expense">
                    <header class="stat-header">
                        <div class="stat-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"/>
                                <polyline points="19 12 12 19 5 12"/>
                            </svg>
                        </div>
                        <span class="stat-trend negative">-8% dari bulan lalu</span>
                    </header>
                    <div class="stat-amount"><?= e(formatRupiah($totalExpense)) ?></div>
                    <div class="stat-label">Total Pengeluaran</div>
                </article>

                <article class="stat-card balance">
                    <header class="stat-header">
                        <div class="stat-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="1" x2="12" y2="23"/>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            </svg>
                        </div>
                    </header>
                    <div class="stat-amount" style="color: <?= $availableBalance >= 0 ? 'var(--color-accent)' : 'var(--color-destructive)' ?>">
                        <?= e(formatRupiah($availableBalance)) ?>
                    </div>
                    <div class="stat-label">Saldo Tersedia <small>(Setelah Tabungan)</small></div>
                    <div class="stat-label" style="margin-top: var(--space-2); font-size: var(--font-size-xs); color: var(--color-text-muted)">
                        Tabungan aktif: <?= e(formatRupiah($totalSavings)) ?>
                    </div>
                </article>

                <!-- Chart Section -->
                <section class="chart-section chart-card" aria-labelledby="chartTitle">
                    <header class="chart-header">
                        <h2 id="chartTitle" class="chart-title">Arus Kas 6 Bulan Terakhir</h2>
                        <div class="chart-tabs" role="tablist" aria-label="Pilih periode">
                            <button class="chart-tab active" role="tab" aria-selected="true" data-period="6m">6 Bulan</button>
                            <button class="chart-tab" role="tab" aria-selected="false" data-period="12m">12 Bulan</button>
                            <button class="chart-tab" role="tab" aria-selected="false" data-period="1y">1 Tahun</button>
                        </div>
                    </header>
                    <div class="chart-container">
                        <canvas id="cashflowChart" aria-label="Grafik arus kas bulanan pemasukan dan pengeluaran"></canvas>
                    </div>
                </section>

                <!-- Quick Actions -->
                <section class="quick-actions chart-card" aria-labelledby="actionsTitle">
                    <header class="chart-header">
                        <h2 id="actionsTitle" class="chart-title">Aksi Cepat</h2>
                    </header>
                    <div class="chart-container" style="padding: var(--space-5); height: auto;">
                        <div class="quick-actions-grid">
                            <a href="/serius/pages/transactions.php?type=income" class="action-btn income" role="button">
                                <div class="action-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="12" y1="19" x2="12" y2="5"/>
                                        <polyline points="5 12 12 5 19 12"/>
                                    </svg>
                                </div>
                                <span class="action-label">Catat Pemasukan</span>
                            </a>
                            <a href="/serius/pages/transactions.php?type=expense" class="action-btn expense" role="button">
                                <div class="action-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="12" y1="5" x2="12" y2="19"/>
                                        <polyline points="19 12 12 19 5 12"/>
                                    </svg>
                                </div>
                                <span class="action-label">Catat Pengeluaran</span>
                            </a>
                            <a href="/serius/pages/savings.php" class="action-btn savings" role="button">
                                <div class="action-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M12 2a10 10 0 1 0 10 10"/>
                                        <path d="M12 6v6l4 2"/>
                                    </svg>
                                </div>
                                <span class="action-label">Target Tabungan</span>
                            </a>
                            <a href="/serius/pages/groups.php" class="action-btn group" role="button">
                                <div class="action-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                        <circle cx="9" cy="7" r="4"/>
                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                    </svg>
                                </div>
                                <span class="action-label">Split Bill Grup</span>
                            </a>
                        </div>
                    </div>
                </section>

                <!-- Recent Transactions -->
                <section class="chart-card" style="grid-column: 1 / -1;" aria-labelledby="recentTitle">
                    <header class="chart-header">
                        <h2 id="recentTitle" class="chart-title">Transaksi Terbaru</h2>
                        <a href="/serius/pages/transactions.php" class="btn btn-ghost btn-sm">Lihat Semua</a>
                    </header>
                    <div class="chart-container" style="padding: 0; height: auto;">
                        <?php if (empty($recentTransactions)): ?>
                            <div class="empty-state" style="padding: var(--space-8);">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                    <line x1="12" y1="1" x2="12" y2="23"/>
                                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                </svg>
                                <h3>Belum ada transaksi</h3>
                                <p>Mulai catat pemasukan atau pengeluaran pertamamu</p>
                                <a href="/serius/pages/transactions.php?type=expense" class="btn btn-primary">Tambah Transaksi</a>
                            </div>
                        <?php else: ?>
                            <div class="table-container">
                                <table>
                                    <thead>
                                        <tr>
                                            <th scope="col">Tanggal</th>
                                            <th scope="col">Kategori</th>
                                            <th scope="col">Catatan</th>
                                            <th scope="col" style="text-align: right;">Jumlah</th>
                                            <th scope="col" style="width: 48px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentTransactions as $tx): ?>
                                            <tr>
                                                <td><?= e(date('d M Y', strtotime($tx['transaction_date']))) ?></td>
                                                <td>
                                                    <span style="display: inline-flex; align-items: center; gap: var(--space-2);">
                                                        <?php if ($tx['category_icon']): ?>
                                                            <svg class="action-icon" style="width: 18px; height: 18px; background: var(--color-primary);" aria-hidden="true">
                                                                <use href="#icon-<?= e($tx['category_icon']) ?>"></use>
                                                            </svg>
                                                        <?php endif; ?>
                                                        <?= e($tx['category_name']) ?>
                                                    </span>
                                                </td>
                                                <td><?= e($tx['note'] ?? '-') ?></td>
                                                <td style="text-align: right; font-family: var(--font-mono); color: <?= $tx['type'] === 'income' ? 'var(--color-accent)' : 'var(--color-destructive)' ?>">
                                                    <?= $tx['type'] === 'income' ? '+' : '-' ?><?= e(formatRupiah($tx['amount'])) ?>
                                                </td>
                                                <td>
                                                    <a href="/serius/pages/transactions.php?edit=<?= $tx['id'] ?>" class="btn-icon-sm" aria-label="Edit transaksi">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                                        </svg>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Savings Goals -->
                <?php if (!empty($savingsGoals)): ?>
                <section class="chart-card" style="grid-column: 1 / -1;" aria-labelledby="savingsTitle">
                    <header class="chart-header">
                        <h2 id="savingsTitle" class="chart-title">Target Tabungan Aktif</h2>
                        <a href="/serius/pages/savings.php" class="btn btn-ghost btn-sm">Lihat Semua</a>
                    </header>
                    <div class="chart-container" style="padding: 0; height: auto;">
                        <div class="savings-grid">
                            <?php foreach ($savingsGoals as $goal): 
                                $progress = $goal['target_amount'] > 0 ? ($goal['current_amount'] / $goal['target_amount']) * 100 : 0;
                                $isCompleted = $goal['status'] === 'completed' || $progress >= 100;
                            ?>
                                <article class="savings-card<?= $isCompleted ? ' completed' : '' ?>">
                                    <header class="savings-header">
                                        <h3 class="savings-title"><?= e($goal['title']) ?></h3>
                                        <span class="savings-status <?= $goal['status'] ?>"><?= $goal['status'] === 'completed' ? 'Selesai' : 'Aktif' ?></span>
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
                                        <div class="savings-amount"><?= e(formatRupiah($goal['current_amount'])) ?> / <?= e(formatRupiah($goal['target_amount'])) ?></div>
                                        <?php if ($goal['deadline']): ?>
                                            <div class="savings-deadline">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" style="width: 14px; height: 14px;">
                                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                                    <line x1="16" y1="2" x2="16" y2="6"/>
                                                    <line x1="8" y1="2" x2="8" y2="6"/>
                                                    <line x1="3" y1="10" x2="21" y2="10"/>
                                                </svg>
                                                Target: <?= e(date('d M Y', strtotime($goal['deadline']))) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="savings-actions">
                                        <a href="/serius/pages/savings.php?topup=<?= $goal['id'] ?>" class="btn btn-accent btn-sm" style="flex: 1; text-align: center;">Top Up</a>
                                        <a href="/serius/pages/savings.php?withdraw=<?= $goal['id'] ?>" class="btn btn-secondary btn-sm" style="flex: 1; text-align: center;">Tarik</a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // Chart.js Cashflow Chart
        const ctx = document.getElementById('cashflowChart');
        if (ctx) {
            const months = <?= json_encode($months) ?>;
            const incomeData = <?= json_encode($incomeData) ?>;
            const expenseData = <?= json_encode($expenseData) ?>;

            const formatRupiah = (amount) => {
                return 'Rp' + amount.toLocaleString('id-ID');
            };

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: months,
                    datasets: [
                        {
                            label: 'Pemasukan',
                            data: incomeData,
                            backgroundColor: 'rgba(5, 150, 105, 0.8)',
                            borderColor: '#059669',
                            borderWidth: 1,
                            borderRadius: 6,
                            borderSkipped: false,
                        },
                        {
                            label: 'Pengeluaran',
                            data: expenseData,
                            backgroundColor: 'rgba(220, 38, 38, 0.8)',
                            borderColor: '#DC2626',
                            borderWidth: 1,
                            borderRadius: 6,
                            borderSkipped: false,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                color: '#94A3B8',
                                font: { family: 'Source Sans 3', size: 12 },
                                padding: 20,
                                usePointStyle: true,
                                pointStyle: 'rectRounded',
                            }
                        },
                        tooltip: {
                            backgroundColor: '#192134',
                            titleColor: '#F8FAFC',
                            bodyColor: '#E2E8F0',
                            borderColor: 'rgba(255,255,255,0.08)',
                            borderWidth: 1,
                            padding: 12,
                            displayColors: true,
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + formatRupiah(context.raw);
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false,
                            },
                            ticks: {
                                color: '#64748B',
                                font: { family: 'Source Sans 3', size: 11 },
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(255,255,255,0.04)',
                            },
                            ticks: {
                                color: '#64748B',
                                font: { family: 'Source Sans 3', size: 11 },
                                callback: function(value) {
                                    return formatRupiah(value);
                                }
                            }
                        }
                    },
                }
            });
        }

        // Chart tab switching
        document.querySelectorAll('.chart-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.chart-tab').forEach(t => {
                    t.classList.remove('active');
                    t.setAttribute('aria-selected', 'false');
                });
                this.classList.add('active');
                this.setAttribute('aria-selected', 'true');
                // TODO: Load different period data via AJAX
            });
        });
    </script>
</body>
</html>