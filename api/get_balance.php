<?php
// api/get_balance.php - Get available balance for AJAX
// SakuKu - Aplikasi Manajemen Keuangan Mahasiswa

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

$userId = getCurrentUserId();
$balance = getAvailableBalance($userId);

echo json_encode([
    'balance' => $balance,
    'formatted' => formatRupiah($balance)
]);