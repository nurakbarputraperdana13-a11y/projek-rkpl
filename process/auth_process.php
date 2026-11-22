<?php
// process/auth_process.php - Proses login, register, reset password, logout
// SakuKu - Aplikasi Manajemen Keuangan Mahasiswa

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'login':
            handleLogin();
            break;
        case 'register':
            handleRegister();
            break;
        case 'forgot_password':
            handleForgotPassword();
            break;
        case 'reset_password':
            handleResetPassword();
            break;
        case 'logout':
            handleLogout();
            break;
        default:
            throw new Exception('Action tidak valid');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handleLogin(): void
{
    requireCsrfToken($_POST['csrf_token'] ?? '');
    
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!$email || !$password) {
        throw new Exception('Email dan password wajib diisi');
    }
    
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT id, name, email, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password_hash'])) {
        throw new Exception('Email atau password salah');
    }
    
    // Regenerate session ID untuk keamanan
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    regenerateCsrfToken();
    
    echo json_encode([
        'success' => true,
        'message' => 'Login berhasil',
        'redirect' => '/serius/pages/dashboard.php'
    ]);
}

function handleRegister(): void
{
    requireCsrfToken($_POST['csrf_token'] ?? '');
    
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $securityQuestion = trim($_POST['security_question'] ?? '');
    $securityAnswer = trim($_POST['security_answer'] ?? '');
    
    // Validasi
    if (!$name || !$email || !$password || !$confirmPassword || !$securityQuestion || !$securityAnswer) {
        throw new Exception('Semua field wajib diisi');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Format email tidak valid');
    }
    
    if ($password !== $confirmPassword) {
        throw new Exception('Konfirmasi password tidak cocok');
    }
    
    if (strlen($password) < 8) {
        throw new Exception('Password minimal 8 karakter');
    }
    
    if (!validateLength($name, 100) || !validateLength($securityQuestion, 255) || !validateLength($securityAnswer, 255)) {
        throw new Exception('Input terlalu panjang');
    }
    
    $pdo = getPDO();
    
    // Cek email sudah terdaftar
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        throw new Exception('Email sudah terdaftar');
    }
    
    // Hash password dan security answer
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $securityAnswerHash = password_hash(strtolower($securityAnswer), PASSWORD_DEFAULT);
    
    // Insert user
    $stmt = $pdo->prepare('
        INSERT INTO users (name, email, password_hash, security_question, security_answer_hash)
        VALUES (?, ?, ?, ?, ?)
    ');
    $stmt->execute([$name, $email, $passwordHash, $securityQuestion, $securityAnswerHash]);
    
    $userId = $pdo->lastInsertId();
    
    // Auto login after register
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_name'] = $name;
    $_SESSION['user_email'] = $email;
    regenerateCsrfToken();
    
    echo json_encode([
        'success' => true,
        'message' => 'Registrasi berhasil',
        'redirect' => '/serius/pages/dashboard.php'
    ]);
}

function handleForgotPassword(): void
{
    requireCsrfToken($_POST['csrf_token'] ?? '');
    
    $email = trim($_POST['email'] ?? '');
    
    if (!$email) {
        throw new Exception('Email wajib diisi');
    }
    
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT id, security_question FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // Untuk keamanan, jangan reveal apakah email exist
        echo json_encode([
            'success' => true,
            'message' => 'Jika email terdaftar, pertanyaan keamanan akan ditampilkan',
            'show_question' => false
        ]);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Pertanyaan keamanan ditemukan',
        'show_question' => true,
        'security_question' => $user['security_question'],
        'user_id' => $user['id']
    ]);
}

function handleResetPassword(): void
{
    requireCsrfToken($_POST['csrf_token'] ?? '');
    
    $userId = (int)($_POST['user_id'] ?? 0);
    $securityAnswer = trim($_POST['security_answer'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (!$userId || !$securityAnswer || !$newPassword || !$confirmPassword) {
        throw new Exception('Semua field wajib diisi');
    }
    
    if ($newPassword !== $confirmPassword) {
        throw new Exception('Konfirmasi password tidak cocok');
    }
    
    if (strlen($newPassword) < 8) {
        throw new Exception('Password minimal 8 karakter');
    }
    
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT security_answer_hash FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify(strtolower($securityAnswer), $user['security_answer_hash'])) {
        throw new Exception('Jawaban keamanan salah');
    }
    
    // Update password
    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $stmt->execute([$newPasswordHash, $userId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Password berhasil direset. Silakan login.',
        'redirect' => '/serius/pages/login.php'
    ]);
}

function handleLogout(): void
{
    requireCsrfToken($_POST['csrf_token'] ?? '');
    
    session_destroy();
    session_start();
    regenerateCsrfToken();
    
    echo json_encode([
        'success' => true,
        'message' => 'Logout berhasil',
        'redirect' => '/serius/pages/login.php'
    ]);
}