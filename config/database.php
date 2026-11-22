<?php
// config/database.php - Konfigurasi koneksi PDO ke MySQL
// SakuKu - Aplikasi Manajemen Keuangan Mahasiswa

$host = '127.0.0.1';
$dbname = 'saku_kampus';
$username = 'root';
$password = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES $charset",
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    // Di production: log error, jangan tampilkan detail ke user
    die('Koneksi database gagal: ' . $e->getMessage());
}

// Helper function untuk mendapatkan instance PDO (singleton pattern sederhana)
function getPDO(): PDO
{
    global $pdo;
    return $pdo;
}