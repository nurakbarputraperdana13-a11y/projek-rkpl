<?php
// process/group_process.php - CRUD Groups & Membership
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
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (!$name) {
                throw new Exception('Nama grup wajib diisi');
            }

            // Generate invite code (8 chars alphanumeric)
            $inviteCode = '';
            do {
                $inviteCode = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8);
                $stmt = $pdo->prepare('SELECT id FROM groups WHERE invite_code = ?');
                $stmt->execute([$inviteCode]);
            } while ($stmt->fetch());

            $pdo->beginTransaction();

            $stmt = $pdo->prepare('INSERT INTO groups (name, description, invite_code, created_by) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $description, $inviteCode, $userId]);
            $groupId = $pdo->lastInsertId();

            // Add creator as admin
            $stmt = $pdo->prepare('INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, "admin")');
            $stmt->execute([$groupId, $userId]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Grup berhasil dibuat', 'invite_code' => $inviteCode]);
            break;

        case 'update':
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (!$id || !$name) {
                throw new Exception('Data tidak valid');
            }

            // Cek admin
            $stmt = $pdo->prepare('SELECT id FROM group_members WHERE group_id = ? AND user_id = ? AND role = "admin"');
            $stmt->execute([$id, $userId]);
            if (!$stmt->fetch()) {
                throw new Exception('Hanya admin yang bisa edit grup');
            }

            $stmt = $pdo->prepare('UPDATE groups SET name = ?, description = ? WHERE id = ?');
            $stmt->execute([$name, $description, $id]);

            echo json_encode(['success' => true, 'message' => 'Grup diperbarui']);
            break;

        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception('ID tidak valid');

            // Cek admin
            $stmt = $pdo->prepare('SELECT id FROM group_members WHERE group_id = ? AND user_id = ? AND role = "admin"');
            $stmt->execute([$id, $userId]);
            if (!$stmt->fetch()) {
                throw new Exception('Hanya admin yang bisa hapus grup');
            }

            $pdo->beginTransaction();
            // Cascade akan hapus members, expenses, shares, settlements (FK ON DELETE CASCADE)
            $stmt = $pdo->prepare('DELETE FROM groups WHERE id = ?');
            $stmt->execute([$id]);
            $pdo->commit();

            echo json_encode(['success' => true, 'message' => 'Grup dan semua datanya dihapus']);
            break;

        case 'join':
            $inviteCode = strtoupper(trim($_POST['invite_code'] ?? ''));
            if (!$inviteCode) throw new Exception('Kode undangan wajib diisi');

            $stmt = $pdo->prepare('SELECT id FROM groups WHERE invite_code = ?');
            $stmt->execute([$inviteCode]);
            $group = $stmt->fetch();
            if (!$group) throw new Exception('Kode undangan tidak valid');

            $groupId = $group['id'];

            // Cek sudah member
            $stmt = $pdo->prepare('SELECT id FROM group_members WHERE group_id = ? AND user_id = ?');
            $stmt->execute([$groupId, $userId]);
            if ($stmt->fetch()) throw new Exception('Anda sudah anggota grup ini');

            $stmt = $pdo->prepare('INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, "member")');
            $stmt->execute([$groupId, $userId]);

            echo json_encode(['success' => true, 'message' => 'Berhasil bergabung ke grup']);
            break;

        case 'leave':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception('ID tidak valid');

            // Cek net balance = 0
            $stmt = $pdo->prepare('
                SELECT 
                    (SELECT COALESCE(SUM(amount), 0) FROM group_expenses WHERE group_id = ? AND paid_by = ?) as paid,
                    (SELECT COALESCE(SUM(es.share_amount), 0) FROM expense_shares es JOIN group_expenses ge ON es.expense_id = ge.id WHERE ge.group_id = ? AND es.user_id = ?) as share
            ');
            $stmt->execute([$id, $userId, $id, $userId]);
            $balance = $stmt->fetch();
            if ((int)$balance['paid'] !== (int)$balance['share']) {
                throw new Exception('Tidak bisa keluar: net balance belum 0. Lakukan pelunasan dulu.');
            }

            // Cek bukan admin terakhir
            $stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM group_members WHERE group_id = ? AND role = "admin"');
            $stmt->execute([$id]);
            $adminCount = (int)$stmt->fetch()['cnt'];

            $stmt = $pdo->prepare('SELECT role FROM group_members WHERE group_id = ? AND user_id = ?');
            $stmt->execute([$id, $userId]);
            $myRole = $stmt->fetch();

            if ($myRole && $myRole['role'] === 'admin' && $adminCount === 1) {
                throw new Exception('Admin terakhir tidak bisa keluar. Serahkan admin ke anggota lain atau hapus grup.');
            }

            $stmt = $pdo->prepare('DELETE FROM group_members WHERE group_id = ? AND user_id = ?');
            $stmt->execute([$id, $userId]);

            echo json_encode(['success' => true, 'message' => 'Berhasil keluar dari grup']);
            break;

        case 'kick':
            $groupId = (int)($_POST['group_id'] ?? 0);
            $targetUserId = (int)($_POST['user_id'] ?? 0);
            if (!$groupId || !$targetUserId) throw new Exception('Data tidak valid');
            if ($targetUserId === $userId) throw new Exception('Tidak bisa kick diri sendiri');

            // Cek admin
            $stmt = $pdo->prepare('SELECT id FROM group_members WHERE group_id = ? AND user_id = ? AND role = "admin"');
            $stmt->execute([$groupId, $userId]);
            if (!$stmt->fetch()) throw new Exception('Hanya admin yang bisa kick anggota');

            // Cek net balance target = 0
            $stmt = $pdo->prepare('
                SELECT 
                    (SELECT COALESCE(SUM(amount), 0) FROM group_expenses WHERE group_id = ? AND paid_by = ?) as paid,
                    (SELECT COALESCE(SUM(es.share_amount), 0) FROM expense_shares es JOIN group_expenses ge ON es.expense_id = ge.id WHERE ge.group_id = ? AND es.user_id = ?) as share
            ');
            $stmt->execute([$groupId, $targetUserId, $groupId, $targetUserId]);
            $balance = $stmt->fetch();
            if ((int)$balance['paid'] !== (int)$balance['share']) {
                throw new Exception('Anggota tidak bisa di-kick: net balance belum 0');
            }

            // Cek target bukan admin terakhir
            $stmt = $pdo->prepare('SELECT role FROM group_members WHERE group_id = ? AND user_id = ?');
            $stmt->execute([$groupId, $targetUserId]);
            $targetRole = $stmt->fetch();
            if ($targetRole && $targetRole['role'] === 'admin') {
                $stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM group_members WHERE group_id = ? AND role = "admin"');
                $stmt->execute([$groupId]);
                if ((int)$stmt->fetch()['cnt'] <= 1) {
                    throw new Exception('Admin terakhir tidak bisa di-kick');
                }
            }

            $stmt = $pdo->prepare('DELETE FROM group_members WHERE group_id = ? AND user_id = ?');
            $stmt->execute([$groupId, $targetUserId]);

            echo json_encode(['success' => true, 'message' => 'Anggota di-kick dari grup']);
            break;

        case 'transfer_admin':
            $groupId = (int)($_POST['group_id'] ?? 0);
            $targetUserId = (int)($_POST['user_id'] ?? 0);
            if (!$groupId || !$targetUserId) throw new Exception('Data tidak valid');
            if ($targetUserId === $userId) throw new Exception('Anda sudah admin');

            // Cek user adalah admin
            $stmt = $pdo->prepare('SELECT id FROM group_members WHERE group_id = ? AND user_id = ? AND role = "admin"');
            $stmt->execute([$groupId, $userId]);
            if (!$stmt->fetch()) throw new Exception('Hanya admin yang bisa serahkan jabatan');

            // Cek target adalah member
            $stmt = $pdo->prepare('SELECT id FROM group_members WHERE group_id = ? AND user_id = ? AND role = "member"');
            $stmt->execute([$groupId, $targetUserId]);
            if (!$stmt->fetch()) throw new Exception('Target bukan anggota biasa');

            $pdo->beginTransaction();
            $stmt = $pdo->prepare('UPDATE group_members SET role = "member" WHERE group_id = ? AND user_id = ?');
            $stmt->execute([$groupId, $userId]);
            $stmt = $pdo->prepare('UPDATE group_members SET role = "admin" WHERE group_id = ? AND user_id = ?');
            $stmt->execute([$groupId, $targetUserId]);
            $pdo->commit();

            echo json_encode(['success' => true, 'message' => 'Jabatan admin diserahkan']);
            break;

        default:
            throw new Exception('Aksi tidak dikenal');
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}