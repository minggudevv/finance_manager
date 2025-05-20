<?php
require_once __DIR__ . '/../helpers/environment_helper.php';

// Load environment configuration
Environment::load();

// Pilih database berdasarkan environment
$dbname = Environment::isDevelopment() ? 
    Environment::get('DB_DATABASE_DEV', 'keuangan_dev') : 
    Environment::get('DB_DATABASE_PROD', 'keuangan');

$host = Environment::get('DB_HOST', 'localhost');
$username = Environment::get('DB_USERNAME', 'root');
$password = Environment::get('DB_PASSWORD', '');

try {
    $conn = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    );
    
    if (Environment::isDevelopment()) {
        echo "Mode: Development (Database: {$dbname})\n";
    }
} catch(PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}
