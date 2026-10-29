<?php
/**
 * Ekspor hasil prediksi ke CSV.
 */
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/Db.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/MlClient.php';
require_once __DIR__ . '/../src/helpers.php';

Auth::requireLogin();

// Ambil data terbaru dari Python
$rows = Db::fetchAll(
    "SELECT t.id_transaksi AS id,
            t.tanggal_transaksi AS tanggal,
            p.nama_produk AS produk,
            p.kategori AS kategori,
            t.jumlah_terjual AS jumlah,
            t.harga_satuan AS harga,
            t.label_tren AS label
     FROM tb_transaksi t
     JOIN tb_produk p ON p.id_produk = t.id_produk
     WHERE t.label_tren IS NOT NULL
     ORDER BY t.tanggal_transaksi ASC, t.id_transaksi ASC"
);

if (empty($rows)) {
    http_response_code(400);
    die('Tidak ada data untuk diekspor.');
}

$client = new MlClient();
$latest = $client->latest($rows);

if (empty($latest['ok']) || empty($latest['items'])) {
    http_response_code(500);
    die('Gagal mengambil data prediksi: ' . ($latest['error'] ?? 'unknown'));
}

// Header CSV
$filename = 'prediksi_tren_wmbusiness99_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// BOM UTF-8 agar Excel bisa baca dengan benar
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Header kolom
fputcsv($out, [
    'No',
    'Tanggal',
    'Nama Produk',
    'Kategori',
    'Prediksi Tren',
    'Rekomendasi Stok',
    'Model',
    'F1-Score',
]);

// Baris data
foreach ($latest['items'] as $i => $item) {
    $is_naik = $item['tren'] === 'Naik';
    $rekomendasi = $is_naik
        ? 'Tambah stok atau pertahankan ketersediaan produk'
        : 'Tahan pembelian stok baru dan kurangi persediaan';

    fputcsv($out, [
        $i + 1,
        date('d-m-Y', strtotime($item['tanggal'])),
        $item['produk'],
        $item['kategori'],
        $item['tren'],
        $rekomendasi,
        $latest['model'],
        number_format($latest['f1'] * 100, 2, ',', '.') . '%',
    ]);
}

fclose($out);

// Log aktivitas
log_activity('Ekspor CSV', 'File: ' . $filename);
exit;