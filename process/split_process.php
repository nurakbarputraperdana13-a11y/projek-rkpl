<?php
// process/split_process.php - Split Bill Logic (Group Expenses + Shares + Settlement)
// SakuKu - Aplikasi Manajemen Keuangan Mahasiswa

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$userId = getCurrentUserId();
$pdo = getPDO();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireCsrfToken();
    }

    switch ($action) {
        case 'create':
            $groupId = (int)($_POST['group_id'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $totalAmount = (int)($_POST['total_amount'] ?? 0);
            $paidBy = (int)($_POST['paid_by'] ?? 0);
            $categoryId = (int)($_POST['category_id'] ?? 0) ?: null;
            $expenseDate = $_POST['expense_date'] ?? date('Y-m-d');
            $splitMethod = $_POST['split_method'] ?? 'equal';
            $customShares = $_POST['custom_shares'] ? json_decode($_POST['custom_shares'], true) : [];

            if (!$groupId || !$description || $totalAmount <= 0 || !$paidBy) {
                throw new Exception('Data pengeluaran tidak lengkap');
            }

            // Verifikasi user adalah member grup
            $stmt = $pdo->prepare('SELECT role FROM group_members WHERE group_id = ? AND user_id = ?');
            $stmt->execute([$groupId, $userId]);
            if (!$stmt->fetch()) {
                throw new Exception('Anda bukan anggota grup ini');
            }

            // Verifikasi paid_by adalah member
            $stmt = $pdo->prepare('SELECT user_id FROM group_members WHERE group_id = ? AND user_id = ?');
            $stmt->execute([$groupId, $paidBy]);
            if (!$stmt->fetch()) {
                throw new Exception('Pembayar bukan anggota grup');
            }

            // Ambil semua member untuk split
            $stmt = $pdo->prepare('SELECT user_id FROM group_members WHERE group_id = ?');
            $stmt->execute([$groupId]);
            $members = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($members)) {
                throw new Exception('Grup tidak memiliki anggota');
            }

            // Hitung share per member
            $shares = [];
            if ($splitMethod === 'custom' && $customShares) {
                // Validasi total persentase = 100
                $totalPct = array_sum($customShares);
                if (abs($totalPct - 100) > 0.01) {
                    throw new Exception('Total persentase custom harus 100%');
                }
                foreach ($members as $memberId) {
                    $pct = $customShares[$memberId] ?? 0;
                    $shares[$memberId] = splitBillCalculate($totalAmount, [$pct => 1])[0]; // Hack: hitung per orang
                    // Better: use helper
                }
                // Proper calculation
                $amounts = [];
                $remainder = $totalAmount;
                $count = count($members);
                foreach ($members as $i => $memberId) {
                    $pct = ($customShares[$memberId] ?? 0) / 100;
                    $share = (int)floor($totalAmount * $pct);
                    if ($i === $count - 1) {
                        $share = $remainder; // Last gets remainder
                    }
                    $amounts[$memberId] = $share;
                    $remainder -= $share;
                }
                $shares = $amounts;
            } else {
                // Equal split - divider gets remainder
                $shareResult = splitBillCalculate($totalAmount, $members);
                $shares = array_combine($members, $shareResult);
            }

            $pdo->beginTransaction();

            // Insert group_expense
            $stmt = $pdo->prepare('
                INSERT INTO group_expenses (group_id, description, total_amount, paid_by, category_id, expense_date, split_method)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([$groupId, $description, $totalAmount, $paidBy, $categoryId, $expenseDate, $splitMethod]);
            $expenseId = $pdo->lastInsertId();

            // Insert expense_shares
            $stmt = $pdo->prepare('INSERT INTO expense_shares (expense_id, user_id, share_amount) VALUES (?, ?, ?)');
            foreach ($shares as $memberId => $shareAmount) {
                $stmt->execute([$expenseId, $memberId, $shareAmount]);
            }

            $pdo->commit();

            echo json_encode(['success' => true, 'message' => 'Pengeluaran grup ditambahkan', 'expense_id' => $expenseId]);
            break;

        case 'update':
            $id = (int)($_POST['id'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $totalAmount = (int)($_POST['total_amount'] ?? 0);
            $paidBy = (int)($_POST['paid_by'] ?? 0);
            $categoryId = (int)($_POST['category_id'] ?? 0) ?: null;
            $expenseDate = $_POST['expense_date'] ?? date('Y-m-d');
            $splitMethod = $_POST['split_method'] ?? 'equal';
            $customShares = $_POST['custom_shares'] ? json_decode($_POST['custom_shares'], true) : [];

            if (!$id || !$description || $totalAmount <= 0 || !$paidBy) {
                throw new Exception('Data tidak valid');
            }

            // Cek ownership (admin grup atau paid_by)
            $stmt = $pdo->prepare('
                SELECT ge.group_id, gm.role 
                FROM group_expenses ge
                JOIN group_members gm ON ge.group_id = gm.group_id
                WHERE ge.id = ? AND gm.user_id = ?
            ');
            $stmt->execute([$id, $userId]);
            $expense = $stmt->fetch();
            if (!$expense) throw new Exception('Pengeluaran tidak ditemukan');
            if ($expense['role'] !== 'admin' && (int)$expense['paid_by'] !== $userId) {
                throw new Exception('Hanya admin atau pembayar yang bisa edit');
            }

            // Ambil member
            $stmt = $pdo->prepare('SELECT user_id FROM group_members WHERE group_id = ?');
            $stmt->execute([$expense['group_id']]);
            $members = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Hitung shares baru
            if ($splitMethod === 'custom' && $customShares) {
                $totalPct = array_sum($customShares);
                if (abs($totalPct - 100) > 0.01) throw new Exception('Total persentase harus 100%');
                $amounts = [];
                $remainder = $totalAmount;
                $count = count($members);
                foreach ($members as $i => $memberId) {
                    $pct = ($customShares[$memberId] ?? 0) / 100;
                    $share = (int)floor($totalAmount * $pct);
                    if ($i === $count - 1) $share = $remainder;
                    $amounts[$memberId] = $share;
                    $remainder -= $share;
                }
                $shares = $amounts;
            } else {
                $shareResult = splitBillCalculate($totalAmount, $members);
                $shares = array_combine($members, $shareResult);
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare('
                UPDATE group_expenses 
                SET description = ?, total_amount = ?, paid_by = ?, category_id = ?, expense_date = ?, split_method = ?
                WHERE id = ?
            ');
            $stmt->execute([$description, $totalAmount, $paidBy, $categoryId, $expenseDate, $splitMethod, $id]);

            // Hapus shares lama
            $stmt = $pdo->prepare('DELETE FROM expense_shares WHERE expense_id = ?');
            $stmt->execute([$id]);

            // Insert shares baru
            $stmt = $pdo->prepare('INSERT INTO expense_shares (expense_id, user_id, share_amount) VALUES (?, ?, ?)');
            foreach ($shares as $memberId => $shareAmount) {
                $stmt->execute([$id, $memberId, $shareAmount]);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Pengeluaran diperbarui']);
            break;

        case 'delete_expense':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception('ID tidak valid');

            // Cek admin
            $stmt = $pdo->prepare('
                SELECT ge.group_id, gm.role 
                FROM group_expenses ge
                JOIN group_members gm ON ge.group_id = gm.group_id
                WHERE ge.id = ? AND gm.user_id = ? AND gm.role = "admin"
            ');
            $stmt->execute([$id, $userId]);
            if (!$stmt->fetch()) throw new Exception('Hanya admin yang bisa hapus pengeluaran');

            $pdo->beginTransaction();
            $stmt = $pdo->prepare('DELETE FROM expense_shares WHERE expense_id = ?');
            $stmt->execute([$id]);
            $stmt = $pdo->prepare('DELETE FROM group_expenses WHERE id = ?');
            $stmt->execute([$id]);
            $pdo->commit();

            echo json_encode(['success' => true, 'message' => 'Pengeluaran dihapus']);
            break;

        case 'detail':
            $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
            if (!$id) throw new Exception('ID tidak valid');

            $stmt = $pdo->prepare('
                SELECT ge.*, u.name as paid_by_name, c.name as category_name
                FROM group_expenses ge
                JOIN users u ON ge.paid_by = u.id_user
                LEFT JOIN categories c ON ge.category_id = c.id
                WHERE ge.id = ?
            ');
            $stmt->execute([$id]);
            $expense = $stmt->fetch();
            if (!$expense) throw new Exception('Pengeluaran tidak ditemukan');

            // Cek akses
            $stmt = $pdo->prepare('SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?');
            $stmt->execute([$expense['group_id'], $userId]);
            if (!$stmt->fetch()) throw new Exception('Akses ditolak');

            // Ambil shares
            $stmt = $pdo->prepare('
                SELECT es.*, u.name 
                FROM expense_shares es
                JOIN users u ON es.user_id = u.id_user
                WHERE es.expense_id = ?
                ORDER BY u.name
            ');
            $stmt->execute([$id]);
            $shares = $stmt->fetchAll();

            $html = '
                <div style="display: grid; gap: var(--space-4);">
                    <div><strong>Deskripsi:</strong> ' . e($expense['description']) . '</div>
                    <div><strong>Tanggal:</strong> ' . e(date('d M Y', strtotime($expense['expense_date']))) . '</div>
                    <div><strong>Total:</strong> <span style="color: var(--color-destructive); font-weight: bold;">' . e(formatRupiah($expense['total_amount'])) . '</span></div>
                    <div><strong>Dibayar oleh:</strong> ' . e($expense['paid_by_name']) . '</div>
                    <div><strong>Kategori:</strong> ' . e($expense['category_name'] ?: '-') . '</div>
                    <div><strong>Metode Split:</strong> <span class="badge" style="background: var(--color-accent-bg); color: var(--color-accent);">' . ($expense['split_method'] === 'equal' ? 'Rata' : 'Custom') . '</span></div>
                    <hr>
                    <h4 style="margin: var(--space-3) 0;">Rincian Bagi</h4>
                    <table class="table" style="margin: 0;">
                        <thead><tr><th>Anggota</th><th>Bagian</th></tr></thead>
                        <tbody>';
            foreach ($shares as $s) {
                $html .= '<tr><td>' . e($s['name']) . '</td><td style="font-weight: 600;">' . e(formatRupiah($s['share_amount'])) . '</td></tr>';
            }
            $html .= '</tbody></table></div>';

            echo json_encode(['success' => true, 'html' => $html]);
            break;

        case 'settle':
            $groupId = (int)($_POST['group_id'] ?? 0);
            $fromUser = (int)($_POST['from_user'] ?? 0);
            $toUser = (int)($_POST['to_user'] ?? 0);
            $amount = (int)($_POST['amount'] ?? 0);

            if (!$groupId || !$fromUser || !$toUser || $amount <= 0) {
                throw new Exception('Data pelunasan tidak valid');
            }
            if ($fromUser === $toUser) throw new Exception('Tidak bisa lunas ke diri sendiri');

            // Verifikasi member grup
            $stmt = $pdo->prepare('SELECT 1 FROM group_members WHERE group_id = ? AND user_id IN (?, ?)');
            $stmt->execute([$groupId, $fromUser, $toUser]);
            if ($stmt->rowCount() !== 2) throw new Exception('Kedua user harus anggota grup');

            // Cek net balance
            $stmt = $pdo->prepare('
                SELECT 
                    (SELECT COALESCE(SUM(amount), 0) FROM group_expenses WHERE group_id = ? AND paid_by = ?) as paid_from,
                    (SELECT COALESCE(SUM(es.share_amount), 0) FROM expense_shares es JOIN group_expenses ge ON es.expense_id = ge.id WHERE ge.group_id = ? AND es.user_id = ?) as share_from,
                    (SELECT COALESCE(SUM(amount), 0) FROM group_expenses WHERE group_id = ? AND paid_by = ?) as paid_to,
                    (SELECT COALESCE(SUM(es.share_amount), 0) FROM expense_shares es JOIN group_expenses ge ON es.expense_id = ge.id WHERE ge.group_id = ? AND es.user_id = ?) as share_to
            ');
            $stmt->execute([$groupId, $fromUser, $groupId, $fromUser, $groupId, $toUser, $groupId, $toUser]);
            $bal = $stmt->fetch();

            $netFrom = (int)$bal['paid_from'] - (int)$bal['share_from'];
            $netTo = (int)$bal['paid_to'] - (int)$bal['share_to'];

            if ($netFrom >= 0) throw new Exception('User pengirim tidak punya hutang (net balance >= 0)');
            if ($netTo <= 0) throw new Exception('User penerima tidak berhak terima (net balance <= 0)');
            if ($amount > abs($netFrom)) throw new Exception('Jumlah melebihi hutang pengirim');
            if ($amount > $netTo) throw new Exception('Jumlah melebihi hak penerima');

            $stmt = $pdo->prepare('
                INSERT INTO settlements (group_id, from_user, to_user, amount)
                VALUES (?, ?, ?, ?)
            ');
            $stmt->execute([$groupId, $fromUser, $toUser, $amount]);

            echo json_encode(['success' => true, 'message' => 'Pelunasan dicatat']);
            break;

        default:
            throw new Exception('Aksi tidak dikenal');
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}