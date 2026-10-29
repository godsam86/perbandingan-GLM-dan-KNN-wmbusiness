<?php
// ============================================
// Company Profile WMBusiness99 - Config
// ============================================

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// KONFIGURASI WEBSITE
// ============================================

// Info Bisnis
define('SITE_NAME', 'WMBusiness99 Medan');
define('SITE_TAGLINE', 'Aksesoris Handphone Terlengkap di Medan');
define('SITE_DESCRIPTION', 'Toko aksesoris handphone terlengkap dan terpercaya di Medan. Menyediakan casing, charger, kabel data, power bank, earphone, dan aksesoris lainnya.');

// Kontak (SILAKAN GANTI DENGAN DATA ASLI)
define('SITE_PHONE', '0856-6306-232');
define('SITE_WHATSAPP', '628566306232'); // Format: 62 di depan
define('SITE_EMAIL', 'info@wmbusiness99.com');
define('SITE_ADDRESS', 'Jl. Ngumban Surbakti, Simpang Sedap Malam 1, Medan');
define('SITE_INSTAGRAM', 'wmbusiness99');
define('SITE_FACEBOOK', 'wmbusiness99');
define('SITE_TIKTOK', 'wmbusiness99');
define('SITE_OPERASIONAL', 'Senin - Sabtu: 09.00 - 21.00 WIB');

// Maps (embed URL dari Google Maps)
define('SITE_MAPS_EMBED', 'https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d7964.359547064556!2d98.6468618!3d3.5459757!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x30312f0039bd4e61%3A0x70f72cb840fb2a81!2syunna%20salon%203%20sp%20pos!5e0!3m2!1sid!2sid!4v1793208935645!5m2!1sid!2sid');

// Base URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$path = '/wm99/company_profile';
define('BASE_URL', $protocol . '://' . $host . $path);

// ============================================
// KONEKSI DATABASE (Untuk ambil produk)
// ============================================
try {
    // Baca .env dari root project
    $envFile = __DIR__ . '/../../.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') === false) continue;
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
        }
    }
    
    $dbHost = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $dbPort = $_ENV['DB_PORT'] ?? '3306';
    $dbName = $_ENV['DB_NAME'] ?? 'db_wmbusiness99';
    $dbUser = $_ENV['DB_USER'] ?? 'root';
    $dbPass = $_ENV['DB_PASSWORD'] ?? '';
    
    $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    // Kalau error, pakai data default kosong
    $pdo = null;
}

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Ambil semua produk
 */
function getProduk($limit = null) {
    global $pdo;
    if (!$pdo) return [];
    
    $sql = "SELECT * FROM tb_produk ORDER BY nama_produk ASC";
    if ($limit) $sql .= " LIMIT $limit";
    
    try {
        return $pdo->query($sql)->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Ambil produk terlaris
 */
function getProdukTerlaris($limit = 4) {
    global $pdo;
    if (!$pdo) return [];
    
    $sql = "SELECT p.*, 
                   COALESCE(SUM(t.jumlah_terjual), 0) as total_terjual
            FROM tb_produk p
            LEFT JOIN tb_transaksi t ON p.id_produk = t.id_produk
            GROUP BY p.id_produk
            ORDER BY total_terjual DESC
            LIMIT $limit";
    
    try {
        return $pdo->query($sql)->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Ambil kategori unik
 */
function getKategori() {
    global $pdo;
    if (!$pdo) return [];
    
    try {
        return $pdo->query("SELECT DISTINCT kategori FROM tb_produk ORDER BY kategori")->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Format Rupiah
 */
function rupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

/**
 * Escape HTML
 */
function e($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Generate URL
 */
function url($path = '') {
    return BASE_URL . '/' . ltrim($path, '/');
}

/**
 * Asset URL
 */
function asset($path = '') {
    return BASE_URL . '/assets/' . ltrim($path, '/');
}

/**
 * Ambil icon kategori (emoji)
 */
function kategoriIcon($kategori) {
    $icons = [
        'Charger' => '🔌',
        'Casing' => '📱',
        'Kabel data' => '🔗',
        'Power bank' => '🔋',
        'Earphone/Headset' => '🎧',
        'Pelindung' => '🛡️',
        'Audio' => '🎵'
    ];
    
    foreach ($icons as $key => $icon) {
        if (stripos($kategori, $key) !== false) {
            return $icon;
        }
    }
    
    return '📦';
}
?>