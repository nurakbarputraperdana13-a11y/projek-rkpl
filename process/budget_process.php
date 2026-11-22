<?php
// process/budget_process.php - CRUD Budget
// SakuKu - Aplikasi Manajemen Keuangan Mahasiswa

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/csrf.php';

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
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $amountLimit = (int)($_POST['amount_limit'] ?? 0);
            $month = $_POST['month'] ?? date('Y-m');

            if (!$categoryId || $amountLimit <= 0) {
                throw new Exception('Kategori dan nominal budget wajib diisi');
            }

            // Cek kategori milik user atau global
            $stmt = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND (user_id = ? OR user_id IS NULL) AND type = "expense"');
            $stmt->execute([$categoryId, $userId]);
            if (!$stmt->fetch()) {
                throw new Exception('Kategori tidak valid');
            }

            // Cek sudah ada budget untuk kategori & bulan ini
            $stmt = $pdo->prepare('SELECT id FROM budgets WHERE user_id = ? AND category_id = ? AND month_year = ?');
            $stmt->execute([$userId, $categoryId, $month]);
            if ($stmt->fetch()) {
                throw new Exception('Budget untuk kategori ini pada bulan tersebut sudah ada');
            }

            $stmt = $pdo->prepare('INSERT INTO budgets (user_id, category_id, month_year, amount_limit) VALUES (?, ?, ?, ?)');
            $stmt->execute([$userId, $categoryId, $month, $amountLimit]);

            echo json_encode(['success' => true, 'message' => 'Budget berhasil dibuat']);
            break;

        case 'update':
            $id = (int)($_POST['id'] ?? 0);
            $amountLimit = (int)($_POST['amount_limit'] ?? 0);

            if (!$id || $amountLimit <= 0) {
                throw new Exception('Data tidak valid');
            }

            $stmt = $pdo->prepare('UPDATE budgets SET amount_limit = ? WHERE id = ? AND user_id = ?');
            $stmt->execute([$amountLimit, $id, $userId]);

            if ($stmt->rowCount() === 0) {
                throw new Exception('Budget tidak ditemukan');
            }

            echo json_encode(['success' => true, 'message' => 'Budget berhasil diperbarui']);
            break;

        case 'delete':
            $id = (int)($_POST['id'] ?? 0);

            if (!$id) {
                throw new Exception('ID tidak valid');
            }

            $stmt = $pdo->prepare('DELETE FROM budgets WHERE id = ? AND user_id = ?');
            $stmt->execute([$id, $userId]);

            if ($stmt->rowCount() === 0) {
                throw new Exception('Budget tidak ditemukan');
            }

            echo json_encode(['success' => true, 'message' => 'Budget berhasil dihapus']);
            break;

        default:
            throw new Exception('Aksi tidak dikenal');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}