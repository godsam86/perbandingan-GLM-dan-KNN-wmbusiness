<?php
/**
 * Konfigurasi global aplikasi.
 */

// ---------- Database ----------
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'wm99');
define('DB_USER', 'root');
define('DB_PASS', '');           // XAMPP default: kosong
define('DB_CHARSET', 'utf8mb4');

// ---------- ML Service ----------
define('ML_BASE_URL', 'http://127.0.0.1:5001');
define('ML_API_KEY',  'wm99-secret-key-change-me');  // HARUS sama dengan .env Python
define('ML_TIMEOUT',  30);

// ---------- Aplikasi ----------
define('APP_NAME', 'Sistem Prediksi Tren Penjualan');
define('APP_SUB',  'WMBusiness99 Medan');
define('APP_YEAR', '2026');
define('UPLOAD_MAX_BYTES', 5 * 1024 * 1024);
define('UPLOAD_ALLOWED_EXT', ['csv', 'xlsx', 'xls']);

// ---------- Session ----------
define('SESSION_NAME', 'wm99_session');
define('SESSION_LIFETIME', 60 * 60 * 8);

// ---------- Timezone & error ----------
date_default_timezone_set('Asia/Jakarta');
ini_set('display_errors', '0');
error_reporting(E_ALL);

// ---------- Base URL ----------
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
define('BASE_URL', $scheme . '://' . $host);

// ---------- Path ----------
define('ROOT_PATH', dirname(__DIR__));
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('ASSETS_URL', BASE_URL . '/assets');