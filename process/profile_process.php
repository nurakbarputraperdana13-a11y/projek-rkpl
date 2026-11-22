<?php
// process/profile_process.php - Profile management
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
        case 'update_profile':
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');

            if (!$name || !$email) {
                throw new Exception('Nama dan email wajib diisi');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Format email tidak valid');
            }

            // Cek email unik
            $stmt = $pdo->prepare('SELECT id_user FROM users WHERE email = ? AND id_user != ?');
            $stmt->execute([$email, $userId]);
            if ($stmt->fetch()) {
                throw new Exception('Email sudah digunakan akun lain');
            }

            $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ? WHERE id_user = ?');
            $stmt->execute([$name, $email, $userId]);

            // Update session
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;

            echo json_encode(['success' => true, 'message' => 'Profil diperbarui']);
            break;

        case 'delete_account':
            // Hapus semua data user (cascade via FK)
            $stmt = $pdo->prepare('DELETE FROM users WHERE id_user = ?');
            $stmt->execute([$userId]);

            // Destroy session
            session_destroy();

            echo json_encode(['success' => true, 'message' => 'Akun dan semua data dihapus permanen']);
            break;

        default:
            throw new Exception('Aksi tidak dikenal');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}