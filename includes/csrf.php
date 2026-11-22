<?php
// includes/csrf.php - Helper generate & validasi CSRF Token
// SakuKu - Aplikasi Manajemen Keuangan Mahasiswa

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate CSRF token baru dan simpan di session
 */
function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validasi CSRF token dari form/input
 * Return true jika valid, false jika tidak
 */
function validateCsrfToken(string $token): bool
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Validasi CSRF token dan kill script jika invalid
 * Dipakai di process/*.php
 */
function requireCsrfToken(string $token): void
{
    if (!validateCsrfToken($token)) {
        http_response_code(403);
        die('CSRF token invalid. Silakan refresh halaman dan coba lagi.');
    }
}

/**
 * Render hidden input CSRF token untuk form
 */
function csrfField(): string
{
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Regenerate CSRF token (misal setelah login/logout)
 */
function regenerateCsrfToken(): string
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}