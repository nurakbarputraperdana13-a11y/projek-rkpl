<?php
// pages/settings.php - Halaman pengaturan
// SakuKu - Aplikasi Manajemen Keuangan Mahasiswa

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/navbar.php';

$userId = getCurrentUserId();
$pdo = getPDO();

// Ambil preferensi user (simpan di session atau table terpisah - untuk sekarang default)
$prefs = [
    'currency' => 'IDR',
    'date_format' => 'd/m/Y',
    'month_start' => 1,
    'theme' => 'dark',
    'notifications' => true,
    'budget_alerts' => true,
    'savings_reminders' => true,
];

// Kategori default user
$stmt = $pdo->prepare('SELECT * FROM categories WHERE user_id = ? ORDER BY type, name');
$stmt->execute([$userId]);
$userCategories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan - SakuKu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700&family=Source+Sans+3:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/serius/assets/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <main class="dashboard" role="main">
        <div class="dashboard-container">
            <header class="dashboard-header">
                <h1 class="dashboard-title">Pengaturan</h1>
                <p class="dashboard-subtitle">Kustomisasi aplikasi sesuai preferensi Anda</p>
            </header>

            <div style="display: grid; grid-template-columns: 1fr; gap: var(--space-6);">
                @media (min-width: 768px) { grid-template-columns: 280px 1fr; }

                <!-- Settings Navigation -->
                <nav class="card" style="padding: var(--space-4);" aria-label="Navigasi pengaturan">
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        <li><a href="#general" class="settings-nav-link active" data-section="general"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg> Umum</a></li>
                        <li><a href="#categories" class="settings-nav-link" data-section="categories"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg> Kategori</a></li>
                        <li><a href="#notifications" class="settings-nav-link" data-section="notifications"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg> Notifikasi</a></li>
                        <li><a href="#security" class="settings-nav-link" data-section="security"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Keamanan</a></li>
                        <li><a href="#data" class="settings-nav-link" data-section="data"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg> Data & Ekspor</a></li>
                    </ul>
                </nav>

                <!-- Settings Content -->
                <div>
                    <!-- General Section -->
                    <section id="section-general" class="settings-section card" style="padding: var(--space-6);">
                        <header style="margin-bottom: var(--space-6);">
                            <h2 style="font-size: var(--font-size-xl); font-weight: var(--font-weight-bold); margin: 0 0 var(--space-1);">Umum</h2>
                            <p style="color: var(--color-text-muted); margin: 0;">Preferensi dasar aplikasi</p>
                        </header>

                        <form id="generalForm">
                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                            <input type="hidden" name="action" value="update_general">

                            <div class="form-group">
                                <label>Mata Uang</label>
                                <div class="form-group" style="margin-top: var(--space-2);">
                                    <select name="currency" class="form-select" disabled>
                                        <option value="IDR" selected>Rupiah (Rp) - Indonesia</option>
                                    </select>
                                    <p class="form-hint">Mata uang tetap Rupiah untuk konteks Indonesia</p>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="dateFormat">Format Tanggal</label>
                                <select id="dateFormat" name="date_format" class="form-select">
                                    <option value="d/m/Y" <?= $prefs['date_format'] === 'd/m/Y' ? 'selected' : '' ?>>DD/MM/YYYY (31/12/2024)</option>
                                    <option value="d M Y" <?= $prefs['date_format'] === 'd M Y' ? 'selected' : '' ?>>DD MMM YYYY (31 Des 2024)</option>
                                    <option value="Y-m-d" <?= $prefs['date_format'] === 'Y-m-d' ? 'selected' : '' ?>>YYYY-MM-DD (2024-12-31)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="monthStart">Awal Bulan Keuangan</label>
                                <select id="monthStart" name="month_start" class="form-select">
                                    <?php for ($i = 1; $i <= 28; $i++): ?>
                                        <option value="<?= $i ?>" <?= $prefs['month_start'] == $i ? 'selected' : '' ?>>Tanggal <?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                                <p class="form-hint">Tanggal gajian/bulan baru untuk perhitungan budget bulanan</p>
                            </div>

                            <div class="form-group">
                                <label for="theme">Tema</label>
                                <select id="theme" name="theme" class="form-select">
                                    <option value="dark" selected>Gelap (Dark) - OLED</option>
                                    <option value="light">Terang (Light)</option>
                                    <option value="system">Ikuti Sistem</option>
                                </select>
                                <p class="form-hint">Tema gelap dioptimalkan untuk layar OLED</p>
                            </div>

                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        </form>
                    </section>

                    <!-- Categories Section -->
                    <section id="section-categories" class="settings-section card" style="padding: var(--space-6); display: none;">
                        <header style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-6); flex-wrap: wrap; gap: var(--space-4);">
                            <div>
                                <h2 style="font-size: var(--font-size-xl); font-weight: var(--font-weight-bold); margin: 0 0 var(--space-1);">Kategori Saya</h2>
                                <p style="color: var(--color-text-muted); margin: 0;">Kelola kategori pribadi untuk transaksi</p>
                            </div>
                            <div style="display: flex; gap: var(--space-2);">
                                <button type="button" class="btn btn-primary" onclick="openCategoryModal('income')">+ Pemasukan</button>
                                <button type="button" class="btn btn-primary" onclick="openCategoryModal('expense')">+ Pengeluaran</button>
                            </div>
                        </header>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-6);">
                            <div>
                                <h3 style="font-size: var(--font-size-base); font-weight: var(--font-weight-semibold); margin: 0 0 var(--space-3); color: var(--color-accent); display: flex; align-items: center; gap: var(--space-2);">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                    Pemasukan
                                </h3>
                                <div id="incomeCategories" style="display: flex; flex-direction: column; gap: var(--space-2);">
                                    <?php foreach (array_filter($userCategories, fn($c) => $c['type'] === 'income') as $cat): ?>
                                        <div class="category-item" data-id="<?= $cat['id'] ?>">
                                            <span><?= e($cat['name']) ?></span>
                                            <div class="table-actions">
                                                <button class="btn-icon-sm" onclick="editCategory(<?= htmlspecialchars(json_encode($cat), ENT_QUOTES, 'UTF-8') ?>)" aria-label="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                                                <button class="btn-icon-sm" onclick="deleteCategory(<?= $cat['id'] ?>)" aria-label="Hapus" style="color: var(--color-destructive);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div>
                                <h3 style="font-size: var(--font-size-base); font-weight: var(--font-weight-semibold); margin: 0 0 var(--space-3); color: var(--color-destructive); display: flex; align-items: center; gap: var(--space-2);">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                                    Pengeluaran
                                </h3>
                                <div id="expenseCategories" style="display: flex; flex-direction: column; gap: var(--space-2);">
                                    <?php foreach (array_filter($userCategories, fn($c) => $c['type'] === 'expense') as $cat): ?>
                                        <div class="category-item" data-id="<?= $cat['id'] ?>">
                                            <span><?= e($cat['name']) ?></span>
                                            <div class="table-actions">
                                                <button class="btn-icon-sm" onclick="editCategory(<?= htmlspecialchars(json_encode($cat), ENT_QUOTES, 'UTF-8') ?>)" aria-label="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                                                <button class="btn-icon-sm" onclick="deleteCategory(<?= $cat['id'] ?>)" aria-label="Hapus" style="color: var(--color-destructive);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Notifications Section -->
                    <section id="section-notifications" class="settings-section card" style="padding: var(--space-6); display: none;">
                        <header style="margin-bottom: var(--space-6);">
                            <h2 style="font-size: var(--font-size-xl); font-weight: var(--font-weight-bold); margin: 0 0 var(--space-1);">Notifikasi</h2>
                            <p style="color: var(--color-text-muted); margin: 0;">Kelola preferensi notifikasi</p>
                        </header>

                        <form id="notificationsForm">
                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                            <input type="hidden" name="action" value="update_notifications">

                            <div style="display: flex; flex-direction: column; gap: var(--space-4);">
                                <label class="toggle-label">
                                    <input type="checkbox" name="notifications" <?= $prefs['notifications'] ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                    <div>
                                        <strong>Aktifkan Notifikasi</strong>
                                        <div class="form-hint">Terima notifikasi umum dari aplikasi</div>
                                    </div>
                                </label>

                                <label class="toggle-label">
                                    <input type="checkbox" name="budget_alerts" <?= $prefs['budget_alerts'] ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                    <div>
                                        <strong>Peringatan Budget</strong>
                                        <div class="form-hint">Notifikasi saat pengeluaran mendekati batas budget (80%)</div>
                                    </div>
                                </label>

                                <label class="toggle-label">
                                    <input type="checkbox" name="savings_reminders" <?= $prefs['savings_reminders'] ? 'checked' : '' ?>>
                                    <span class="toggle-slider"></span>
                                    <div>
                                        <strong>Pengingat Tabungan</strong>
                                        <div class="form-hint">Pengingat mingguan untuk menabung ke target aktif</div>
                                    </div>
                                </label>
                            </div>

                            <button type="submit" class="btn btn-primary" style="margin-top: var(--space-4);">Simpan</button>
                        </form>
                    </section>

                    <!-- Security Section -->
                    <section id="section-security" class="settings-section card" style="padding: var(--space-6); display: none;">
                        <header style="margin-bottom: var(--space-6);">
                            <h2 style="font-size: var(--font-size-xl); font-weight: var(--font-weight-bold); margin: 0 0 var(--space-1);">Keamanan</h2>
                            <p style="color: var(--color-text-muted); margin: 0;">Ubah kata sandi & pertanyaan keamanan</p>
                        </header>

                        <div style="display: grid; grid-template-columns: 1fr; gap: var(--space-6);">
                            @media (min-width: 640px) { grid-template-columns: repeat(2, 1fr); }

                            <form id="passwordForm" class="card" style="padding: var(--space-6);">
                                <h3 style="font-size: var(--font-size-lg); font-weight: var(--font-weight-semibold); margin: 0 0 var(--space-4);">Ubah Kata Sandi</h3>
                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                <input type="hidden" name="action" value="change_password">

                                <div class="form-group">
                                    <label for="currentPassword">Kata Sandi Saat Ini <span class="required">*</span></label>
                                    <div class="input-wrapper">
                                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                        <input type="password" id="currentPassword" name="current_password" required autocomplete="current-password">
                                        <button type="button" class="input-toggle" onclick="togglePassword('currentPassword')" aria-label="Tampilkan sembunyikan kata sandi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="newPassword">Kata Sandi Baru <span class="required">*</span></label>
                                    <div class="input-wrapper">
                                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                        <input type="password" id="newPassword" name="new_password" required minlength="8" autocomplete="new-password" aria-describedby="passwordHint">
                                        <button type="button" class="input-toggle" onclick="togglePassword('newPassword')" aria-label="Tampilkan sembunyikan kata sandi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
                                    </div>
                                    <div id="passwordHint" class="form-hint">Minimal 8 karakter</div>
                                </div>

                                <div class="form-group">
                                    <label for="confirmPassword">Konfirmasi Kata Sandi Baru <span class="required">*</span></label>
                                    <div class="input-wrapper">
                                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                        <input type="password" id="confirmPassword" name="confirm_password" required autocomplete="new-password">
                                        <button type="button" class="input-toggle" onclick="togglePassword('confirmPassword')" aria-label="Tampilkan sembunyikan kata sandi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary" style="width: 100%;">Ubah Kata Sandi</button>
                            </form>

                            <form id="securityQuestionForm" class="card" style="padding: var(--space-6);">
                                <h3 style="font-size: var(--font-size-lg); font-weight: var(--font-weight-semibold); margin: 0 0 var(--space-4);">Pertanyaan Keamanan</h3>
                                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                                <input type="hidden" name="action" value="change_security_question">

                                <div class="form-group">
                                    <label for="securityQuestion">Pertanyaan <span class="required">*</span></label>
                                    <select id="securityQuestion" name="security_question" required>
                                        <option value="">-- Pilih Pertanyaan --</option>
                                        <option value="mother_maiden_name">Nama kudul ibu kandung?</option>
                                        <option value="first_pet_name">Nama hewan peliharaan pertama?</option>
                                        <option value="birth_city">Kota kelahiran?</option>
                                        <option value="favorite_teacher">Nama guru favorit SD?</option>
                                        <option value="first_school">Nama sekolah dasar?</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="securityAnswer">Jawaban <span class="required">*</span></label>
                                    <div class="input-wrapper">
                                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                        <input type="text" id="securityAnswer" name="security_answer" required autocomplete="off" aria-describedby="securityHint">
                                        <button type="button" class="input-toggle" onclick="togglePassword('securityAnswer')" aria-label="Tampilkan sembunyikan jawaban"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
                                    </div>
                                    <div id="securityHint" class="form-hint">Jawaban akan di-hash dan tidak bisa dilihat siapa pun</div>
                                </div>

                                <button type="submit" class="btn btn-primary" style="width: 100%;">Perbarui</button>
                            </form>
                        </div>
                    </section>

                    <!-- Data & Export Section -->
                    <section id="section-data" class="settings-section card" style="padding: var(--space-6); display: none;">
                        <header style="margin-bottom: var(--space-6);">
                            <h2 style="font-size: var(--font-size-xl); font-weight: var(--font-weight-bold); margin: 0 0 var(--space-1);">Data & Ekspor</h2>
                            <p style="color: var(--color-text-muted); margin: 0;">Ekspor data atau hapus semua data</p>
                        </header>

                        <div style="display: grid; grid-template-columns: 1fr; gap: var(--space-6);">
                            @media (min-width: 640px) { grid-template-columns: repeat(2, 1fr); }

                            <div class="card" style="padding: var(--space-6);">
                                <h3 style="font-size: var(--font-size-lg); font-weight: var(--font-weight-semibold); margin: 0 0 var(--space-4);">Ekspor Data</h3>
                                <p class="form-hint" style="margin-bottom: var(--space-4);">Unduh semua data keuangan Anda</p>
                                <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                                    <a href="/serius/process/export_process.php?type=transactions&format=csv" class="btn btn-secondary" style="display: flex; align-items: center; justify-content: center; gap: var(--space-2);">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                        Ekspor Transaksi (CSV)
                                    </a>
                                    <a href="/serius/process/export_process.php?type=transactions&format=pdf" class="btn btn-secondary" style="display: flex; align-items: center; justify-content: center; gap: var(--space-2);">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                                        Ekspor Transaksi (PDF)
                                    </a>
                                    <a href="/serius/process/export_process.php?type=all&format=csv" class="btn btn-secondary" style="display: flex; align-items: center; justify-content: center; gap: var(--space-2);">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                        Ekspor Semua Data (CSV ZIP)
                                    </a>
                                </div>
                            </div>

                            <div class="card" style="padding: var(--space-6); border-color: rgba(220, 38, 38, 0.3);">
                                <h3 style="font-size: var(--font-size-lg); font-weight: var(--font-weight-semibold); margin: 0 0 var(--space-4); color: var(--color-destructive);">Zona Bahaya</h3>
                                <p class="form-hint" style="margin-bottom: var(--space-4); color: var(--color-text-muted);">Aksi di bawah ini bersifat permanen dan tidak dapat dibatalkan.</p>
                                
                                <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                                    <button type="button" class="btn btn-destructive" onclick="confirmClearTransactions()">Hapus Semua Transaksi</button>
                                    <button type="button" class="btn btn-destructive" onclick="confirmClearAllData()">Hapus SEMUA Data (Reset Total)</button>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </main>

    <!-- Category Modal -->
    <div class="modal-overlay" id="categoryModal" role="dialog" aria-modal="true" aria-labelledby="categoryModalTitle" hidden>
        <div class="modal">
            <header class="modal-header">
                <h3 id="categoryModalTitle" class="modal-title">Tambah Kategori</h3>
                <button type="button" class="modal-close" onclick="closeCategoryModal()" aria-label="Tutup"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
            </header>
            <form id="categoryForm" class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="action" id="categoryAction" value="create">
                <input type="hidden" name="id" id="categoryId">
                <input type="hidden" name="type" id="categoryType">
                
                <div class="form-group">
                    <label for="categoryName">Nama Kategori <span class="required">*</span></label>
                    <input type="text" id="categoryName" name="name" required maxlength="50" placeholder="Contoh: Makanan / Transport / Gaji">
                </div>

                <div class="form-group">
                    <label for="categoryIcon">Ikon (Emoji)</label>
                    <input type="text" id="categoryIcon" name="icon" maxlength="4" placeholder="🍔 / 🚌 / 💰" style="font-size: var(--font-size-xl);">
                    <p class="form-hint">Opsional, akan ditampilkan di daftar</p>
                </div>
            </form>
            <footer class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCategoryModal()">Batal</button>
                <button type="button" class="btn btn-primary" onclick="submitCategory()">Simpan</button>
            </footer>
        </div>
    </div>

    <script>
        // Section navigation
        document.querySelectorAll('.settings-nav-link').forEach(link => {
            link.addEventListener('click', e => {
                e.preventDefault();
                const section = link.dataset.section;
                
                // Update nav
                document.querySelectorAll('.settings-nav-link').forEach(l => l.classList.remove('active'));
                link.classList.add('active');
                
                // Show section
                document.querySelectorAll('.settings-section').forEach(s => s.style.display = 'none');
                document.getElementById('section-' + section).style.display = 'block';
            });
        });

        // General form
        document.getElementById('generalForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('.btn-primary');
            setButtonLoading(btn, true);
            const formData = new FormData(e.target);
            try {
                const res = await fetch('/serius/process/settings_process.php', { method: 'POST', body: formData });
                const data = await res.json();
                showToast(data.success ? data.message : data.message, data.success ? 'success' : 'error');
            } catch { showToast('Terjadi kesalahan', 'error'); }
            finally { setButtonLoading(btn, false); }
        });

        // Notifications form
        document.getElementById('notificationsForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('.btn-primary');
            setButtonLoading(btn, true);
            const formData = new FormData(e.target);
            try {
                const res = await fetch('/serius/process/settings_process.php', { method: 'POST', body: formData });
                const data = await res.json();
                showToast(data.success ? data.message : data.message, data.success ? 'success' : 'error');
            } catch { showToast('Terjadi kesalahan', 'error'); }
            finally { setButtonLoading(btn, false); }
        });

        // Password form
        document.getElementById('passwordForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const newPass = document.getElementById('newPassword').value;
            const confirmPass = document.getElementById('confirmPassword').value;
            if (newPass !== confirmPass) { showToast('Konfirmasi kata sandi tidak cocok', 'error'); return; }
            if (newPass.length < 8) { showToast('Kata sandi minimal 8 karakter', 'error'); return; }
            
            const btn = e.target.querySelector('.btn-primary');
            setButtonLoading(btn, true);
            const formData = new FormData(e.target);
            try {
                const res = await fetch('/serius/process/settings_process.php', { method: 'POST', body: formData });
                const data = await res.json();
                showToast(data.success ? data.message : data.message, data.success ? 'success' : 'error');
                if (data.success) e.target.reset();
            } catch { showToast('Terjadi kesalahan', 'error'); }
            finally { setButtonLoading(btn, false); }
        });

        // Security question form
        document.getElementById('securityQuestionForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('.btn-primary');
            setButtonLoading(btn, true);
            const formData = new FormData(e.target);
            try {
                const res = await fetch('/serius/process/settings_process.php', { method: 'POST', body: formData });
                const data = await res.json();
                showToast(data.success ? data.message : data.message, data.success ? 'success' : 'error');
            } catch { showToast('Terjadi kesalahan', 'error'); }
            finally { setButtonLoading(btn, false); }
        });

        // Category modal
        const categoryModal = document.getElementById('categoryModal');
        const categoryForm = document.getElementById('categoryForm');

        function openCategoryModal(type, data = null) {
            categoryForm.reset();
            document.getElementById('categoryType').value = type;
            if (data) {
                document.getElementById('categoryModalTitle').textContent = 'Edit Kategori';
                document.getElementById('categoryAction').value = 'update';
                document.getElementById('categoryId').value = data.id;
                document.getElementById('categoryName').value = data.name;
                document.getElementById('categoryIcon').value = data.icon || '';
            } else {
                document.getElementById('categoryModalTitle').textContent = 'Tambah Kategori ' + (type === 'income' ? 'Pemasukan' : 'Pengeluaran');
                document.getElementById('categoryAction').value = 'create';
                document.getElementById('categoryId').value = '';
            }
            categoryModal.hidden = false;
            document.body.style.overflow = 'hidden';
            setTimeout(() => document.getElementById('categoryName').focus(), 100);
        }

        function closeCategoryModal() { categoryModal.hidden = true; document.body.style.overflow = ''; categoryForm.reset(); }

        function editCategory(data) { openCategoryModal(data.type, data); }

        function deleteCategory(id) {
            if (!confirm('Hapus kategori ini? Transaksi yang menggunakan kategori ini tidak terhapus.')) return;
            const formData = new FormData();
            formData.append('action', 'delete_category');
            formData.append('id', id);
            formData.append('csrf_token', categoryForm.querySelector('[name="csrf_token"]').value);
            fetch('/serius/process/settings_process.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(d => { if (d.success) { showToast(d.message, 'success'); location.reload(); } else showToast(d.message, 'error'); });
        }

        function submitCategory() {
            const btn = categoryModal.querySelector('.btn-primary');
            setButtonLoading(btn, true);
            const formData = new FormData(categoryForm);
            fetch('/serius/process/settings_process.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(d => { if (d.success) { showToast(d.message, 'success'); closeCategoryModal(); location.reload(); } else showToast(d.message, 'error'); })
                .finally(() => setButtonLoading(btn, false));
        }

        function confirmClearTransactions() {
            if (!confirm('Hapus SEMUA transaksi? Budget, tabungan, dan grup TIDAK terhapus.')) return;
            if (!confirm('YAKIN? Data transaksi tidak bisa dipulihkan.')) return;
            const formData = new FormData();
            formData.append('action', 'clear_transactions');
            formData.append('csrf_token', document.querySelector('#generalForm [name="csrf_token"]').value);
            fetch('/serius/process/settings_process.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(d => { showToast(d.message, d.success ? 'success' : 'error'); if (d.success) setTimeout(() => location.reload(), 1000); });
        }

        function confirmClearAllData() {
            if (!confirm('PERINGATAN: Ini akan menghapus SEMUA data - transaksi, budget, tabungan, kategori, grup, split bill, settlements. Hanya akun yang tersisa.\n\nLANJUTKAN?')) return;
            if (!confirm('TERAKHIR KALI: Ketik "RESET SEMUA" untuk konfirmasi:')) return;
            const txt = prompt('Ketik "RESET SEMUA":');
            if (txt !== 'RESET SEMUA') { showToast('Batal', 'error'); return; }
            
            const formData = new FormData();
            formData.append('action', 'clear_all_data');
            formData.append('csrf_token', document.querySelector('#generalForm [name="csrf_token"]').value);
            fetch('/serius/process/settings_process.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(d => { showToast(d.message, d.success ? 'success' : 'error'); if (d.success) setTimeout(() => location.reload(), 1000); });
        }

        function togglePassword(id) {
            const inp = document.getElementById(id);
            const btn = inp.nextElementSibling;
            inp.type = inp.type === 'password' ? 'text' : 'password';
            btn.querySelector('svg').innerHTML = inp.type === 'password' 
                ? '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>' 
                : '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';
        }

        function setButtonLoading(btn, loading) {
            if (loading) { btn.disabled = true; btn.dataset.original = btn.textContent; btn.textContent = 'Menyimpan...'; }
            else { btn.disabled = false; btn.textContent = btn.dataset.original || 'Simpan'; }
        }

        function showToast(msg, type) {
            let c = document.querySelector('.toast-container');
            if (!c) { c = document.createElement('div'); c.className = 'toast-container'; document.body.appendChild(c); }
            const t = document.createElement('div');
            t.className = 'toast ' + type;
            t.innerHTML = '<span class="toast-message">' + msg + '</span><button class="toast-close" onclick="this.parentElement.remove()">×</button>';
            c.appendChild(t);
            setTimeout(() => t.remove(), 4000);
        }

        categoryModal.addEventListener('click', e => { if (e.target === categoryModal) closeCategoryModal(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape' && !categoryModal.hidden) closeCategoryModal(); });
    </script>

    <style>
        .settings-nav-link {
            display: flex; align-items: center; gap: var(--space-3);
            padding: var(--space-3) var(--space-4);
            border-radius: var(--radius-md);
            color: var(--color-text-secondary);
            text-decoration: none;
            font-size: var(--font-size-sm);
            font-weight: var(--font-weight-medium);
            transition: var(--transition-fast);
        }
        .settings-nav-link svg { width: 20px; height: 20px; flex-shrink: 0; }
        .settings-nav-link:hover { background: var(--color-background); color: var(--color-text-primary); }
        .settings-nav-link.active { background: var(--color-primary-bg); color: var(--color-primary); }
        
        .category-item {
            display: flex; align-items: center; justify-content: space-between;
            padding: var(--space-3) var(--space-4);
            background: var(--color-background);
            border-radius: var(--radius-md);
            border: 1px solid var(--color-border);
        }
        .category-item span { font-size: var(--font-size-sm); font-weight: var(--font-weight-medium); }
        
        .toggle-label {
            display: flex; align-items: flex-start; gap: var(--space-3); cursor: pointer;
        }
        .toggle-label input { position: absolute; opacity: 0; pointer-events: none; }
        .toggle-slider {
            width: 44px; height: 24px; border-radius: var(--radius-full);
            background: var(--color-border); position: relative; flex-shrink: 0;
            transition: var(--transition-fast);
        }
        .toggle-slider::after {
            content: ''; position: absolute; top: 2px; left: 2px;
            width: 20px; height: 20px; border-radius: 50%;
            background: white; box-shadow: var(--shadow-sm); transition: var(--transition-fast);
        }
        .toggle-label input:checked + .toggle-slider { background: var(--color-primary); }
        .toggle-label input:checked + .toggle-slider::after { left: 22px; }
        .toggle-label div { flex: 1; min-width: 0; }
        .toggle-label strong { display: block; font-size: var(--font-size-sm); color: var(--color-text-primary); }
    </style>
</body>
</html>