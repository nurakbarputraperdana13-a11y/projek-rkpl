<?php
// process/transaction_process.php - CRUD transaksi
// SakuKu - Aplikasi Manajemen Keuangan Mahasiswa

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

requireAuth();
requireCsrfToken($_POST['csrf_token'] ?? '');

$action = $_POST['action'] ?? '';
$userId = getCurrentUserId();

try {
    switch ($action) {
        case 'create':
            handleCreate($userId);
            break;
        case 'update':
            handleUpdate($userId);
            break;
        case 'delete':
            handleDelete($userId);
            break;
        default:
            throw new Exception('Action tidak valid');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handleCreate(int $userId): void
{
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $type = $_POST['type'] ?? '';
    $amount = (int)($_POST['amount'] ?? 0);
    $date = $_POST['transaction_date'] ?? '';
    $note = trim($_POST['note'] ?? '');

    // Validasi
    if (!$categoryId || !$type || !$amount || !$date) {
        throw new Exception('Semua field wajib diisi');
    }

    if (!in_array($type, ['income', 'expense'])) {
        throw new Exception('Tipe transaksi tidak valid');
    }

    if (!validateAmount($amount)) {
        throw new Exception('Nominal harus lebih dari 0');
    }

    if (!validateLength($note, 255)) {
        throw new Exception('Catatan maksimal 255 karakter');
    }

    // Validasi kategori milik user atau sistem
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND (user_id = ? OR user_id IS NULL)');
    $stmt->execute([$categoryId, $userId]);
    if (!$stmt->fetch()) {
        throw new Exception('Kategori tidak valid');
    }

    // Validasi saldo untuk pengeluaran & topup tabungan
    if ($type === 'expense') {
        $available = getAvailableBalance($userId);
        if ($amount > $available) {
            throw new Exception('Saldo tidak mencukupi. Saldo tersedia: ' . formatRupiah($available));
        }
    }

    // Insert
    $stmt = $pdo->prepare('
        INSERT INTO transactions (user_id, category_id, type, amount, transaction_date, note)
        VALUES (?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([$userId, $categoryId, $type, $amount, $date, $note]);

    echo json_encode([
        'success' => true,
        'message' => 'Transaksi berhasil ditambahkan',
        'redirect' => '/serius/pages/transactions.php'
    ]);
}

function handleUpdate(int $userId): void
{
    $id = (int)($_POST['id'] ?? 0);
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $type = $_POST['type'] ?? '';
    $amount = (int)($_POST['amount'] ?? 0);
    $date = $_POST['transaction_date'] ?? '';
    $note = trim($_POST['note'] ?? '');

    if (!$id) {
        throw new Exception('ID transaksi tidak valid');
    }

    // Validasi ownership
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT id, type, amount FROM transactions WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);
    $existing = $stmt->fetch();

    if (!$existing) {
        throw new Exception('Transaksi tidak ditemukan');
    }

    // Validasi input
    if (!$categoryId || !$type || !$amount || !$date) {
        throw new Exception('Semua field wajib diisi');
    }

    if (!in_array($type, ['income', 'expense'])) {
        throw new Exception('Tipe transaksi tidak valid');
    }

    if (!validateAmount($amount)) {
        throw new Exception('Nominal harus lebih dari 0');
    }

    if (!validateLength($note, 255)) {
        throw new Exception('Catatan maksimal 255 karakter');
    }

    // Validasi kategori
    $stmt = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND (user_id = ? OR user_id IS NULL)');
    $stmt->execute([$categoryId, $userId]);
    if (!$stmt->fetch()) {
        throw new Exception('Kategori tidak valid');
    }

    // Validasi saldo untuk pengeluaran (hitung selisih)
    if ($type === 'expense') {
        $diff = $amount - (int)$existing['amount'];
        if ($diff > 0) {
            $available = getAvailableBalance($userId);
            if ($diff > $available) {
                throw new Exception('Saldo tidak mencukupi untuk penambahan. Saldo tersedia: ' . formatRupiah($available));
            }
        }
    }

    // Update
    $stmt = $pdo->prepare('
        UPDATE transactions 
        SET category_id = ?, type = ?, amount = ?, transaction_date = ?, note = ?
        WHERE id = ? AND user_id = ?
    ');
    $stmt->execute([$categoryId, $type, $amount, $date, $note, $id, $userId]);

    echo json_encode([
        'success' => true,
        'message' => 'Transaksi berhasil diperbarui',
        'redirect' => '/serius/pages/transactions.php'
    ]);
}

function handleDelete(int $userId): void
{
    $id = (int)($_POST['id'] ?? 0);

    if (!$id) {
        throw new Exception('ID transaksi tidak valid');
    }

    $pdo = getPDO();
    $stmt = $pdo->prepare('DELETE FROM transactions WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $userId]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Transaksi tidak ditemukan');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Transaksi berhasil dihapus'
    ]);
}