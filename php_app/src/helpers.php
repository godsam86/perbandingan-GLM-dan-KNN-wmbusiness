<?php
/**
 * Fungsi bantu: format angka/tanggal Indonesia, escape, CSRF, log.
 */

// ---------- Format ----------

function format_rupiah($n)
{
    if ($n === null || $n === '') return '-';
    return number_format((float) $n, 0, ',', '.');
}

function format_persen($n, $desimal = 2)
{
    if ($n === null || $n === '') return '-';
    return number_format((float) $n * 100, $desimal, ',', '.') . '%';
}

function format_tanggal($iso)
{
    if (!$iso) return '-';
    $ts = strtotime($iso);
    if ($ts === false) return $iso;
    return date('d-m-Y', $ts);
}

function e($str)
{
    return htmlspecialchars((string) ($str ?? ''), ENT_QUOTES, 'UTF-8');
}

// ---------- CSRF ----------

function csrf_token()
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify($token)
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    return !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], (string) $token);
}

// ---------- Log aktivitas ----------

function log_activity($aksi, $keterangan = null)
{
    try {
        $id_user = $_SESSION['id_user'] ?? null;
        Db::execute(
            'INSERT INTO tb_log_activity (id_user, aksi, keterangan) VALUES (?, ?, ?)',
            [$id_user, $aksi, $keterangan]
        );
    } catch (Exception $e) {
        // Diamkan
    }
}

// ---------- JSON response ----------

function json_ok($data = [])
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['ok' => true], $data));
    exit;
}

function json_err($msg, $code = 400)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

// ---------- Banner ML ----------

function ml_unavailable_banner()
{
    return '<div class="alert alert-warning mb-3" role="alert">'
         . '<strong>Layanan ML tidak aktif.</strong> '
         . 'Jalankan <code>python app.py</code> di folder <code>python_ml_service</code>, '
         . 'lalu muat ulang halaman.'
         . '</div>';
}