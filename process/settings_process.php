<?php
// process/settings_process.php - Settings management
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
        case 'update_general':
            // Simpan preferensi di session (atau table user_preferences jika ada)
            $_SESSION['prefs'] = [
                'date_format' => $_POST['date_format'] ?? 'd/m/Y',
                'month_start' => (int)($_POST['month_start'] ?? 1),
                'theme' => $_POST['theme'] ?? 'dark',
            ];
            echo json_encode(['success' => true, 'message' => 'Pengaturan umum disimpan']);
            break;

        case 'update_notifications':
            $_SESSION['prefs']['notifications'] = isset($_POST['notifications']);
            $_SESSION['prefs']['budget_alerts'] = isset($_POST['budget_alerts']);
            $_SESSION['prefs']['savings_reminders'] = isset($_POST['savings_reminders']);
            echo json_encode(['success' => true, 'message' => 'Preferensi notifikasi disimpan']);
            break;

        case 'change_password':
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (!$currentPassword || !$newPassword || !$confirmPassword) {
                throw new Exception('Semua field wajib diisi');
            }

            if ($newPassword !== $confirmPassword) {
                throw new Exception('Konfirmasi kata sandi tidak cocok');
            }

            if (strlen($newPassword) < 8) {
                throw new Exception('Kata sandi minimal 8 karakter');
            }

            // Verifikasi current password
            $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id_user = ?');
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
                throw new Exception('Kata sandi saat ini salah');
            }

            // Update password
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id_user = ?');
            $stmt->execute([$newHash, $userId]);

            echo json_encode(['success' => true, 'message' => 'Kata sandi berhasil diubah']);
            break;

        case 'change_security_question':
            $question = $_POST['security_question'] ?? '';
            $answer = trim($_POST['security_answer'] ?? '');

            if (!$question || !$answer) {
                throw new Exception('Pertanyaan dan jawaban wajib diisi');
            }

            $answerHash = password_hash($answer, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET security_question = ?, security_answer_hash = ? WHERE id_user = ?');
            $stmt->execute([$question, $answerHash, $userId]);

            echo json_encode(['success' => true, 'message' => 'Pertanyaan keamanan diperbarui']);
            break;

        case 'create_category':
        case 'update_category':
            $name = trim($_POST['name'] ?? '');
            $type = $_POST['type'] ?? '';
            $icon = trim($_POST['icon'] ?? '');
            $id = (int)($_POST['id'] ?? 0);

            if (!$name || !in_array($type, ['income', 'expense'])) {
                throw new Exception('Nama dan tipe kategori wajib diisi');
            }

            if ($action === 'create') {
                $stmt = $pdo->prepare('INSERT INTO categories (user_id, name, type, icon) VALUES (?, ?, ?, ?)');
                $stmt->execute([$userId, $name, $type, $icon]);
                echo json_encode(['success' => true, 'message' => 'Kategori dibuat']);
            } else {
                if (!$id) throw new Exception('ID tidak valid');
                $stmt = $pdo->prepare('UPDATE categories SET name = ?, icon = ? WHERE id = ? AND user_id = ?');
                $stmt->execute([$name, $icon, $id, $userId]);
                if ($stmt->rowCount() === 0) throw new Exception('Kategori tidak ditemukan');
                echo json_encode(['success' => true, 'message' => 'Kategori diperbarui']);
            }
            break;

        case 'delete_category':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception('ID tidak valid');

            // Cek kepemilikan
            $stmt = $pdo->prepare('DELETE FROM categories WHERE id = ? AND user_id = ?');
            $stmt->execute([$id, $userId]);
            if ($stmt->rowCount() === 0) throw new Exception('Kategori tidak ditemukan');

            echo json_encode(['success' => true, 'message' => 'Kategori dihapus']);
            break;

        case 'clear_transactions':
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('DELETE FROM transactions WHERE user_id = ?');
            $stmt->execute([$userId]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Semua transaksi dihapus']);
            break;

        case 'clear_all_data':
            $pdo->beginTransaction();
            // Hapus semua data user kecuali users table
            $tables = ['transactions', 'budgets', 'savings_goals', 'categories', 'group_members', 'settlements'];
            foreach ($tables as $table) {
                // Cek kolom user_id atau group_id via join
                if ($table === 'group_members') {
                    // Hapus membership user, lalu grup yang kosong
                    $stmt = $pdo->prepare('DELETE FROM group_members WHERE user_id = ?');
                    $stmt->execute([$userId]);
                    // Hapus grup yang dibuat user tapi tidak punya member lain
                    $stmt = $pdo->prepare('DELETE g FROM groups g LEFT JOIN group_members gm ON g.id = gm.group_id WHERE g.created_by = ? AND gm.group_id IS NULL');
                    $stmt->execute([$userId]);
                } elseif ($table === 'settlements') {
                    $stmt = $pdo->prepare('DELETE s FROM settlements s JOIN group_members gm ON s.group_id = gm.group_id WHERE gm.user_id = ?');
                    $stmt->execute([$userId]);
                } else {
                    $stmt = $pdo->prepare("DELETE FROM $table WHERE user_id = ?");
                    $stmt->execute([$userId]);
                }
            }
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Semua data direset (akun tetap ada)']);
            break;

        default:
            throw new Exception('Aksi tidak dikenal');
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}