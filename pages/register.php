<?php
// pages/register.php - Halaman registrasi
// SakuKu - Aplikasi Manajemen Keuangan Mahasiswa

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect jika sudah login
if (!empty($_SESSION['user_id'])) {
    header('Location: /serius/pages/dashboard.php');
    exit;
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi - SakuKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700&family=Source+Sans+3:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/serius/assets/css/style.css">
</head>
<body class="auth-page">
    <main class="auth-container" role="main">
        <div class="auth-card">
            <div class="auth-header">
                <svg class="auth-logo" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                </svg>
                <h1>SakuKu</h1>
                <p class="auth-subtitle">Buat akun baru</p>
            </div>

            <form id="registerForm" class="auth-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="register">
                
                <div class="form-group">
                    <label for="name">Nama Lengkap</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                        <input type="text" id="name" name="name" autocomplete="name" required placeholder="Nama lengkap" maxlength="100" aria-describedby="nameError">
                    </div>
                    <div id="nameError" class="form-error" aria-live="polite"></div>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                        <input type="email" id="email" name="email" autocomplete="email" required placeholder="mahasiswa@kampus.ac.id" aria-describedby="emailError">
                    </div>
                    <div id="emailError" class="form-error" aria-live="polite"></div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        <input type="password" id="password" name="password" autocomplete="new-password" required placeholder="Minimal 8 karakter" minlength="8" aria-describedby="passwordError passwordHint">
                        <button type="button" class="toggle-password" aria-label="Tampilkan/sembunyikan password" aria-pressed="false">
                            <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" style="display:none;">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                                <line x1="1" y1="1" x2="23" y2="23"/>
                            </svg>
                        </button>
                    </div>
                    <div id="passwordHint" class="form-hint">Minimal 8 karakter</div>
                    <div id="passwordError" class="form-error" aria-live="polite"></div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Konfirmasi Password</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required placeholder="Ulangi password" aria-describedby="confirmError">
                        <button type="button" class="toggle-password" aria-label="Tampilkan/sembunyikan password" aria-pressed="false">
                            <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" style="display:none;">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                                <line x1="1" y1="1" x2="23" y2="23"/>
                            </svg>
                        </button>
                    </div>
                    <div id="confirmError" class="form-error" aria-live="polite"></div>
                </div>

                <fieldset class="form-group">
                    <legend>Pertanyaan Keamanan (untuk reset password)</legend>
                    
                    <div class="form-group">
                        <label for="security_question">Pertanyaan</label>
                        <div class="input-wrapper">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <circle cx="12" cy="12" r="10"/>
                                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                                <line x1="12" y1="17" x2="12.01" y2="17"/>
                            </svg>
                            <select id="security_question" name="security_question" required aria-describedby="questionError">
                                <option value="">-- Pilih pertanyaan --</option>
                                <option value="Nama hewan peliharaan pertama Anda?">Nama hewan peliharaan pertama Anda?</option>
                                <option value="Nama ibu kandung Anda?">Nama ibu kandung Anda?</option>
                                <option value="Sekolah dasar pertama Anda?">Sekolah dasar pertama Anda?</option>
                                <option value="Kota kelahiran Anda?">Kota kelahiran Anda?</option>
                                <option value="Makanan favorit Anda saat kecil?">Makanan favorit Anda saat kecil?</option>
                                <option value="Nama panggilan Anda saat kecil?">Nama panggilan Anda saat kecil?</option>
                            </select>
                        </div>
                        <div id="questionError" class="form-error" aria-live="polite"></div>
                    </div>

                    <div class="form-group">
                        <label for="security_answer">Jawaban</label>
                        <div class="input-wrapper">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                            </svg>
                            <input type="text" id="security_answer" name="security_answer" autocomplete="off" required placeholder="Jawaban Anda" maxlength="255" aria-describedby="answerError">
                        </div>
                        <div id="answerError" class="form-error" aria-live="polite"></div>
                        <div class="form-hint">Jawaban bersifat case-insensitive</div>
                    </div>
                </fieldset>

                <button type="submit" class="btn btn-primary btn-block" id="registerBtn">
                    <span class="btn-text">Daftar</span>
                    <span class="btn-loader" hidden aria-hidden="true">
                        <svg class="spinner" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
                            <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/>
                        </svg>
                    </span>
                </button>
            </form>

            <div class="auth-footer">
                <p>Sudah punya akun? <a href="/serius/pages/login.php">Masuk disini</a></p>
            </div>
        </div>
    </main>

    <script src="/serius/assets/js/auth.js"></script>
</body>
</html>