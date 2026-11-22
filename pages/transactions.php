<?php
// pages/transactions.php - Halaman manajemen transaksi
// SakuKu - Aplikasi Manajemen Keuangan Mahasiswa

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/navbar.php';

$userId = getCurrentUserId();

// Filter
$filterType = $_GET['type'] ?? '';
$filterCategory = $_GET['category'] ?? '';
$filterMonth = $_GET['month'] ?? date('Y-m');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// Ambil kategori untuk filter & form
$pdo = getPDO();
$stmt = $pdo->prepare('SELECT * FROM categories WHERE (user_id = ? OR user_id IS NULL) ORDER BY type, name');
$stmt->execute([$userId]);
$categories = $stmt->fetchAll();

// Build query
$where = ['t.user_id = ?'];
$params = [$userId];

if ($filterType && in_array($filterType, ['income', 'expense'])) {
    $where[] = 't.type = ?';
    $params[] = $filterType;
}

if ($filterCategory) {
    $where[] = 't.category_id = ?';
    $params[] = (int)$filterCategory;
}

if ($filterMonth) {
    $startDate = $filterMonth . '-01';
    $endDate = date('Y-m-t', strtotime($startDate));
    $where[] = 't.transaction_date BETWEEN ? AND ?';
    $params[] = $startDate;
    $params[] = $endDate;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM transactions t $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

// Get data
$offset = ($page - 1) * $perPage;
$dataParams = array_merge($params, [$perPage, $offset]);
$stmt = $pdo->prepare("
    SELECT t.*, c.name as category_name, c.icon as category_icon, c.type as category_type
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    $whereSql
    ORDER BY t.transaction_date DESC, t.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($dataParams);
$transactions = $stmt->fetchAll();

// Stats untuk bulan ini
$currentMonth = date('Y-m');
$monthlyIncome = getTotalIncome($userId, $currentMonth . '-01', date('Y-m-t'));
$monthlyExpense = getTotalExpense($userId, $currentMonth . '-01', date('Y-m-t'));
$monthlyBalance = $monthlyIncome - $monthlyExpense;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi - SakuKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700&family=Source+Sans+3:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/serius/assets/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <main class="dashboard" role="main">
        <div class="dashboard-container">
            <!-- Header -->
            <header class="dashboard-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: var(--space-4);">
                <div>
                    <h1 class="dashboard-title">Transaksi</h1>
                    <p class="dashboard-subtitle">Kelola pemasukan & pengeluaran harian</p>
                </div>
                <div style="display: flex; gap: var(--space-2); flex-wrap: wrap;">
                    <a href="?type=income" class="btn btn-accent">+ Pemasukan</a>
                    <a href="?type=expense" class="btn btn-destructive">- Pengeluaran</a>
                </div>
            </header>

            <!-- Monthly Stats -->
            <div class="dashboard-grid" style="margin-bottom: var(--space-6);">
                <article class="stat-card income">
                    <header class="stat-header">
                        <div class="stat-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="19" x2="12" y2="5"/>
                                <polyline points="5 12 12 5 19 12"/>
                            </svg>
                        </div>
                    </header>
                    <div class="stat-amount"><?= e(formatRupiah($monthlyIncome)) ?></div>
                    <div class="stat-label">Pemasukan Bulan Ini</div>
                </article>

                <article class="stat-card expense">
                    <header class="stat-header">
                        <div class="stat-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"/>
                                <polyline points="19 12 12 19 5 12"/>
                            </svg>
                        </div>
                    </header>
                    <div class="stat-amount"><?= e(formatRupiah($monthlyExpense)) ?></div>
                    <div class="stat-label">Pengeluaran Bulan Ini</div>
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
                    <div class="stat-amount" style="color: <?= $monthlyBalance >= 0 ? 'var(--color-accent)' : 'var(--color-destructive)' ?>">
                        <?= e(formatRupiah($monthlyBalance)) ?>
                    </div>
                    <div class="stat-label">Saldo Bulan Ini</div>
                </article>
            </div>

            <!-- Filter Bar -->
            <div class="card" style="padding: var(--space-4); margin-bottom: var(--space-4);">
                <form method="GET" class="d-flex flex-wrap gap-3 align-center" style="max-width: none;">
                    <div class="form-group" style="margin: 0; flex: 1; min-width: 200px;">
                        <label for="filterType" class="sr-only">Tipe</label>
                        <select id="filterType" name="type" class="d-block w-full">
                            <option value="">Semua Tipe</option>
                            <option value="income" <?= $filterType === 'income' ? 'selected' : '' ?>>Pemasukan</option>
                            <option value="expense" <?= $filterType === 'expense' ? 'selected' : '' ?>>Pengeluaran</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin: 0; flex: 1; min-width: 200px;">
                        <label for="filterCategory" class="sr-only">Kategori</label>
                        <select id="filterCategory" name="category" class="d-block w-full">
                            <option value="">Semua Kategori</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $filterCategory == $cat['id'] ? 'selected' : '' ?>>
                                    <?= e($cat['name']) ?> (<?= $cat['type'] === 'income' ? 'Masuk' : 'Keluar' ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin: 0; min-width: 180px;">
                        <label for="filterMonth" class="sr-only">Bulan</label>
                        <input type="month" id="filterMonth" name="month" value="<?= e($filterMonth) ?>" class="d-block w-full" style="padding-left: var(--space-4);">
                    </div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="/serius/pages/transactions.php" class="btn btn-secondary">Reset</a>
                </form>
            </div>

            <!-- Transactions Table -->
            <section class="chart-card" aria-labelledby="transactionsTitle">
                <header class="chart-header">
                    <h2 id="transactionsTitle" class="chart-title">Daftar Transaksi</h2>
                    <span class="fs-sm" style="color: var(--color-text-muted);">
                        <?= $total ?> transaksi <?= $totalPages > 1 ? "(Halaman $page dari $totalPages)" : '' ?>
                    </span>
                </header>
                <div class="chart-container" style="padding: 0; height: auto;">
                    <?php if (empty($transactions)): ?>
                        <div class="empty-state" style="padding: var(--space-12);">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <line x1="12" y1="1" x2="12" y2="23"/>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            </svg>
                            <h3>Belum ada transaksi</h3>
                            <p>Mulai catat pemasukan atau pengeluaran pertamamu</p>
                            <div style="display: flex; gap: var(--space-2); justify-content: center; flex-wrap: wrap;">
                                <a href="?type=income" class="btn btn-accent">+ Pemasukan</a>
                                <a href="?type=expense" class="btn btn-destructive">- Pengeluaran</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th scope="col">Tanggal</th>
                                        <th scope="col">Tipe</th>
                                        <th scope="col">Kategori</th>
                                        <th scope="col">Catatan</th>
                                        <th scope="col" style="text-align: right;">Jumlah</th>
                                        <th scope="col" style="width: 80px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $tx): ?>
                                        <tr>
                                            <td><?= e(date('d M Y', strtotime($tx['transaction_date']))) ?></td>
                                            <td>
                                                <span class="badge" style="
                                                    padding: var(--space-1) var(--space-2);
                                                    border-radius: var(--radius-full);
                                                    font-size: var(--font-size-xs);
                                                    font-weight: var(--font-weight-medium);
                                                    background: <?= $tx['type'] === 'income' ? 'rgba(5, 150, 105, 0.15)' : 'rgba(220, 38, 38, 0.15)' ?>;
                                                    color: <?= $tx['type'] === 'income' ? 'var(--color-accent)' : 'var(--color-destructive)' ?>;
                                                ">
                                                    <?= $tx['type'] === 'income' ? 'Masuk' : 'Keluar' ?>
                                                </span>
                                            </td>
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
                                                <div class="table-actions">
                                                    <button type="button" class="btn-icon-sm" onclick="openEditModal(<?= htmlspecialchars(json_encode($tx), ENT_QUOTES, 'UTF-8') ?>)" aria-label="Edit transaksi">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                                        </svg>
                                                    </button>
                                                    <button type="button" class="btn-icon-sm" onclick="confirmDelete(<?= $tx['id'] ?>)" aria-label="Hapus transaksi">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" style="color: var(--color-destructive);">
                                                            <polyline points="3 6 5 6 21 6"/>
                                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav class="d-flex justify-center gap-1 mt-4" aria-label="Pagination">
                                <?php
                                $baseUrl = '/serius/pages/transactions.php?' . http_build_query(array_filter([
                                    'type' => $filterType,
                                    'category' => $filterCategory,
                                    'month' => $filterMonth,
                                ]));
                                ?>
                                <?php if ($page > 1): ?>
                                    <a href="<?= $baseUrl . '&page=' . ($page - 1) ?>" class="btn btn-secondary btn-sm" aria-label="Halaman sebelumnya">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" style="width: 16px; height: 16px;">
                                            <polyline points="15 18 9 12 15 6"/>
                                        </svg>
                                    </a>
                                <?php endif; ?>

                                <?php
                                $start = max(1, $page - 2);
                                $end = min($totalPages, $page + 2);
                                for ($i = $start; $i <= $end; $i++):
                                ?>
                                    <a href="<?= $baseUrl . '&page=' . $i ?>" class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?>" aria-label="Halaman <?= $i ?>" <?= $i === $page ? 'aria-current="page"' : '' ?>>
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="<?= $baseUrl . '&page=' . ($page + 1) ?>" class="btn btn-secondary btn-sm" aria-label="Halaman selanjutnya">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" style="width: 16px; height: 16px;">
                                            <polyline points="9 18 15 12 9 6"/>
                                        </svg>
                                    </a>
                                <?php endif; ?>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>

    <!-- Add/Edit Modal -->
    <div class="modal-overlay" id="transactionModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle" hidden>
        <div class="modal">
            <header class="modal-header">
                <h3 id="modalTitle" class="modal-title">Tambah Transaksi</h3>
                <button type="button" class="modal-close" onclick="closeModal()" aria-label="Tutup modal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </header>
            <form id="transactionForm" class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="formId">
                
                <div class="form-group">
                    <label for="formType">Tipe <span class="required">*</span></label>
                    <select id="formType" name="type" required>
                        <option value="income">Pemasukan</option>
                        <option value="expense">Pengeluaran</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="formCategory">Kategori <span class="required">*</span></label>
                    <select id="formCategory" name="category_id" required>
                        <option value="">-- Pilih Kategori --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" data-type="<?= $cat['type'] ?>">
                                <?= e($cat['name']) ?> (<?= $cat['type'] === 'income' ? 'Masuk' : 'Keluar' ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="formAmount">Nominal (Rp) <span class="required">*</span></label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <line x1="12" y1="1" x2="12" y2="23"/>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                        <input type="number" id="formAmount" name="amount" min="1" step="1" required placeholder="Contoh: 50000" aria-describedby="amountError">
                    </div>
                    <div id="amountError" class="form-error" aria-live="polite"></div>
                </div>

                <div class="form-group">
                    <label for="formDate">Tanggal <span class="required">*</span></label>
                    <input type="date" id="formDate" name="transaction_date" required value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                </div>

                <div class="form-group">
                    <label for="formNote">Catatan</label>
                    <textarea id="formNote" name="note" maxlength="255" placeholder="Opsional" rows="3"></textarea>
                    <div class="form-hint">Maksimal 255 karakter</div>
                </div>
            </form>
            <footer class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                <button type="button" class="btn btn-primary" onclick="submitForm()">Simpan</button>
            </footer>
        </div>
    </div>

    <!-- Delete Confirm Modal -->
    <div class="modal-overlay" id="deleteModal" role="dialog" aria-modal="true" aria-labelledby="deleteTitle" hidden>
        <div class="modal" style="max-width: 400px;">
            <div class="modal-body" style="text-align: center; padding: var(--space-6);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" style="width: 48px; height: 48px; color: var(--color-warning); margin-bottom: var(--space-4);">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
                <h3 id="deleteTitle" class="modal-title" style="margin-bottom: var(--space-2);">Hapus Transaksi?</h3>
                <p style="color: var(--color-text-secondary); margin: 0;">Transaksi yang dihapus tidak bisa dikembalikan.</p>
            </div>
            <footer class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Batal</button>
                <button type="button" class="btn btn-destructive" id="confirmDeleteBtn">Hapus</button>
            </footer>
        </div>
    </div>

    <script src="/serius/assets/js/transactions.js"></script>
</body>
</html>