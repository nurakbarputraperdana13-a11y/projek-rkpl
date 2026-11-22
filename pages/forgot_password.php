<?php
// pages/forgot_password.php - Halaman lupa password (2 langkah: email -> pertanyaan keamanan -> reset)
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

$step = $_GET['step'] ?? 1; // 1 = email, 2 = pertanyaan, 3 = reset
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - SakuKu</title>
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
                <p class="auth-subtitle"><?= $step === 1 ? 'Lupa Password' : ($step === 2 ? 'Verifikasi Keamanan' : 'Atur Password Baru') ?></p>
            </div>

            <!-- Step Indicator -->
            <div class="step-indicator" role="progressbar" aria-valuenow="<?= $step ?>" aria-valuemin="1" aria-valuemax="3" aria-label="Langkah reset password">
                <div class="step-item<?= $step >= 1 ? ' active' : '' ?>">
                    <span class="step-number">1</span>
                    <span class="step-label">Email</span>
                </div>
                <div class="step-connector"></div>
                <div class="step-item<?= $step >= 2 ? ' active' : '' ?>">
                    <span class="step-number">2</span>
                    <span class="step-label">Verifikasi</span>
                </div>
                <div class="step-connector"></div>
                <div class="step-item<?= $step >= 3 ? ' active' : '' ?>">
                    <span class="step-number">3</span>
                    <span class="step-label">Password Baru</span>
                </div>
            </div>

            <div id="alertContainer"></div>

            <!-- Step 1: Email -->
            <form id="step1Form" class="auth-form<?= $step !== 1 ? ' hidden' : '' ?>" novalidate data-step="1">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="forgot_password">
                
                <div class="form-group">
                    <label for="email">Email terdaftar</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                        <input type="email" id="email" name="email" autocomplete="email" required placeholder="mahasiswa@kampus.ac.id" aria-describedby="emailError">
                    </div>
                    <div id="emailError" class="form-error" aria-live="polite"></div>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <span class="btn-text">Lanjutkan</span>
                    <span class="btn-loader" hidden aria-hidden="true">
                        <svg class="spinner" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
                            <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/>
                        </svg>
                    </span>
                </button>
            </form>

            <!-- Step 2: Security Question -->
            <form id="step2Form" class="auth-form<?= $step !== 2 ? ' hidden' : '' ?>" novalidate data-step="2" hidden>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="userId">
                
                <div class="form-group">
                    <label>Pertanyaan Keamanan</label>
                    <div class="security-question-display" id="securityQuestionDisplay" aria-live="polite"></div>
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

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="backToStep1">Kembali</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="btn-text">Verifikasi</span>
                        <span class="btn-loader" hidden aria-hidden="true">
                            <svg class="spinner" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
                                <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/>
                            </svg>
                        </span>
                    </button>
                </div>
            </form>

            <!-- Step 3: New Password -->
            <form id="step3Form" class="auth-form<?= $step !== 3 ? ' hidden' : '' ?>" novalidate data-step="3" hidden>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="userId2">
                <input type="hidden" name="security_answer" id="securityAnswerHidden">
                
                <div class="form-group">
                    <label for="new_password">Password Baru</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        <input type="password" id="new_password" name="new_password" autocomplete="new-password" required placeholder="Minimal 8 karakter" minlength="8" aria-describedby="newPasswordError newPasswordHint">
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
                    <div id="newPasswordHint" class="form-hint">Minimal 8 karakter</div>
                    <div id="newPasswordError" class="form-error" aria-live="polite"></div>
                </div>

                <div class="form-group">
                    <label for="confirm_new_password">Konfirmasi Password Baru</label>
                    <div class="input-wrapper">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        <input type="password" id="confirm_new_password" name="confirm_password" autocomplete="new-password" required placeholder="Ulangi password baru" aria-describedby="confirmNewError">
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
                    <div id="confirmNewError" class="form-error" aria-live="polite"></div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="backToStep2">Kembali</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="btn-text">Reset Password</span>
                        <span class="btn-loader" hidden aria-hidden="true">
                            <svg class="spinner" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
                                <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/>
                            </svg>
                        </span>
                    </button>
                </div>
            </form>

            <div class="auth-footer">
                <p>Ingat password? <a href="/serius/pages/login.php">Kembali ke Login</a></p>
            </div>
        </div>
    </main>

    <script src="/serius/assets/js/auth.js"></script>
</body>
</html>