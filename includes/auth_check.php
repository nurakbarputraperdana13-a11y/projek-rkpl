<?php
// includes/auth_check.php - Middleware proteksi halaman (cek $_SESSION)
// SakuKu - Aplikasi Manajemen Keuangan Mahasiswa

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Cek apakah user sudah login
 * Jika belum, redirect ke login.php
 */
function requireAuth(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: /serius/pages/login.php');
        exit;
    }
}

/**
 * Get current logged in user ID
 */
function getCurrentUserId(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current logged in user name
 */
function getCurrentUserName(): ?string
{
    return $_SESSION['user_name'] ?? null;
}

/**
 * Cek apakah user adalah admin grup
 */
function isGroupAdmin(int $groupId, int $userId): bool
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT role FROM group_members WHERE group_id = ? AND user_id = ?');
    $stmt->execute([$groupId, $userId]);
    $member = $stmt->fetch();
    return $member && $member['role'] === 'admin';
}

/**
 * Cek apakah user adalah member grup
 */
function isGroupMember(int $groupId, int $userId): bool
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT id FROM group_members WHERE group_id = ? AND user_id = ?');
    $stmt->execute([$groupId, $userId]);
    return (bool)$stmt->fetch();
}