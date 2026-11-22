<?php
// process/savings_process.php - CRUD Savings Goals + Top Up / Withdraw
// SakuKu - Aplikasi Manajemen Keuangan Mahasiswa

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

requireCsrfToken();

$userId = getCurrentUserId();
$pdo = getPDO();
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            $title = trim($_POST['title'] ?? '');
            $targetAmount = (int)($_POST['target_amount'] ?? 0);
            $deadline = $_POST['deadline'] ?: null;

            if (!$title || $targetAmount < 1000) {
                throw new Exception('Judul dan target minimal Rp1.000 wajib diisi');
            }

            // Validasi saldo tersedia untuk target awal (opsional, bisa 0)
            $stmt = $pdo->prepare('INSERT INTO savings_goals (user_id, title, target_amount, current_amount, deadline, status) VALUES (?, ?, ?, 0, ?, "active")');
            $stmt->execute([$userId, $title, $targetAmount, $deadline]);

            echo json_encode(['success' => true, 'message' => 'Target tabungan berhasil dibuat']);
            break;

        case 'update':
            $id = (int)($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $targetAmount = (int)($_POST['target_amount'] ?? 0);
            $deadline = $_POST['deadline'] ?: null;

            if (!$id || !$title || $targetAmount < 1000) {
                throw new Exception('Data tidak valid');
            }

            // Cek kepemilikan
            $stmt = $pdo->prepare('SELECT current_amount FROM savings_goals WHERE id = ? AND user_id = ?');
            $stmt->execute([$id, $userId]);
            $goal = $stmt->fetch();
            if (!$goal) {
                throw new Exception('Target tabungan tidak ditemukan');
            }

            // Validasi target tidak boleh kurang dari current_amount
            if ($targetAmount < (int)$goal['current_amount']) {
                throw new Exception('Target tidak boleh kurang dari jumlah terkumpul saat ini');
            }

            $stmt = $pdo->prepare('UPDATE savings_goals SET title = ?, target_amount = ?, deadline = ? WHERE id = ? AND user_id = ?');
            $stmt->execute([$title, $targetAmount, $deadline, $id, $userId]);

            // Auto update status
            $stmt = $pdo->prepare('UPDATE savings_goals SET status = CASE WHEN current_amount >= target_amount THEN "completed" ELSE "active" END WHERE id = ?');
            $stmt->execute([$id]);

            echo json_encode(['success' => true, 'message' => 'Target tabungan diperbarui']);
            break;

        case 'topup':
        case 'withdraw':
            $id = (int)($_POST['goal_id'] ?? 0);
            $amount = (int)($_POST['amount'] ?? 0);

            if (!$id || $amount < 1000) {
                throw new Exception('Target dan nominal wajib diisi');
            }

            $pdo->beginTransaction();

            // Lock row
            $stmt = $pdo->prepare('SELECT * FROM savings_goals WHERE id = ? AND user_id = ? FOR UPDATE');
            $stmt->execute([$id, $userId]);
            $goal = $stmt->fetch();

            if (!$goal) {
                throw new Exception('Target tabungan tidak ditemukan');
            }

            if ($goal['status'] === 'completed' && $action === 'topup') {
                throw new Exception('Target sudah selesai, tidak bisa top up');
            }

            $currentAmount = (int)$goal['current_amount'];
            $newAmount = $action === 'topup' ? $currentAmount + $amount : $currentAmount - $amount;

            if ($newAmount < 0) {
                throw new Exception('Saldo tabungan tidak boleh negatif');
            }

            if ($action === 'withdraw') {
                // Validasi saldo tersedia di kas (income - expense - active_savings_except_this)
                $availableBalance = getAvailableBalance($userId) + $currentAmount; // +currentAmount karena uang ini ada di tabungan
                if ($amount > $availableBalance) {
                    throw new Exception('Saldo kas tidak mencukupi untuk penarikan');
                }
            }

            // Update savings goal
            $newStatus = $newAmount >= (int)$goal['target_amount'] ? 'completed' : 'active';
            $stmt = $pdo->prepare('UPDATE savings_goals SET current_amount = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->execute([$newAmount, $newStatus, $id]);

            // Catat sebagai transaksi khusus (opsional, untuk riwayat)
            $type = $action === 'topup' ? 'income' : 'expense';
            $desc = $action === 'topup' ? "Top up: {$goal['title']}" : "Tarik: {$goal['title']}";
            $stmt = $pdo->prepare('
                INSERT INTO transactions (user_id, type, category_id, amount, description, transaction_date)
                VALUES (?, ?, NULL, ?, ?, CURDATE())
            ');
            $stmt->execute([$userId, $type, $amount, $desc]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => $action === 'topup' ? 'Top up berhasil' : 'Penarikan berhasil']);
            break;

        case 'reopen':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception('ID tidak valid');

            $stmt = $pdo->prepare('UPDATE savings_goals SET status = "active" WHERE id = ? AND user_id = ?');
            $stmt->execute([$id, $userId]);
            if ($stmt->rowCount() === 0) throw new Exception('Target tidak ditemukan');

            echo json_encode(['success' => true, 'message' => 'Target dibuka kembali']);
            break;

        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception('ID tidak valid');

            $stmt = $pdo->prepare('DELETE FROM savings_goals WHERE id = ? AND user_id = ?');
            $stmt->execute([$id, $userId]);
            if ($stmt->rowCount() === 0) throw new Exception('Target tidak ditemukan');

            echo json_encode(['success' => true, 'message' => 'Target tabungan dihapus']);
            break;

        default:
            throw new Exception('Aksi tidak dikenal');
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}