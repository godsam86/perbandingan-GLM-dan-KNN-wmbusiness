<?php
/**
 * Halaman Hasil Prediksi (Gambar 5.8).
 * Baca dari tb_riset (latest terakhir).
 */
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/Db.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/MlClient.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/Riset.php';

Auth::requireLogin();
$user = Auth::user();

$error = '';
$total_labeled = (int) Db::fetchScalar("SELECT COUNT(*) FROM tb_transaksi WHERE label_tren IS NOT NULL");
$latest = null;

// Baca latest dari tb_riset
$latest = Riset::getLatest();

// Kalau belum ada dan data cukup, panggil Python sekali
if (!$latest && $total_labeled >= 5) {
    $rows = Db::fetchAll(
        "SELECT t.id_transaksi AS id, t.tanggal_transaksi AS tanggal,
                p.nama_produk AS produk, p.kategori AS kategori,
                t.jumlah_terjual AS jumlah, t.harga_satuan AS harga,
                t.label_tren AS label
         FROM tb_transaksi t
         JOIN tb_produk p ON p.id_produk = t.id_produk
         WHERE t.label_tren IS NOT NULL
         ORDER BY t.tanggal_transaksi ASC, t.id_transaksi ASC"
    );

    $client = new MlClient();
    $latest = $client->latest($rows);

    if (!empty($latest['ok'])) {
        Riset::simpanLatest($latest);
    } else {
        $error = $latest['error'] ?? 'Gagal mengambil hasil prediksi.';
        if (strpos($error, 'ML') !== false) $_SESSION['ml_down'] = true;
        $latest = null;
    }
}

$n_naik = 0; $n_turun = 0;
if ($latest && !empty($latest['items'])) {
    foreach ($latest['items'] as $it) {
        if ($it['tren'] === 'Naik') $n_naik++;
        else $n_turun++;
    }
}

$page_title  = 'Hasil Prediksi';
$active_menu = 'hasil';
require __DIR__ . '/../src/layout/top.php';
?>

<?php if ($error): ?><div class="alert alert-danger mb-3"><?= e($error) ?></div><?php endif; ?>

<?php if ($latest && !empty($latest['items'])): ?>
  <div class="card mb-3" style="padding:14px 20px;">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
      <div class="d-flex align-items-center gap-3">
        <div style="font-size:32px;">🏆</div>
        <div>
          <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; letter-spacing:.04em;">
            Model terbaik
          </div>
          <div style="font-size:18px; font-weight:700; color:var(--indigo);">
            <?= e($latest['model']) ?>
          </div>
          <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">
            F1-Score <?= e(number_format($latest['f1'] * 100, 2, ',', '.')) ?>%
            · skenario uji (out-of-sample)
          </div>
        </div>
      </div>
      <div>
        <a href="export_csv.php" class="btn btn-primary" target="_blank">⬇ Ekspor CSV</a>
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-title" style="margin-bottom:12px;">Rekapitulasi Prediksi Tren per Produk</div>
    <table class="table-clean">
      <thead>
        <tr>
          <th style="width:60px;">NO</th>
          <th>TANGGAL</th>
          <th>NAMA PRODUK</th>
          <th>KATEGORI</th>
          <th>PREDIKSI TREN</th>
          <th>REKOMENDASI STOK</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($latest['items'] as $i => $item): ?>
          <?php
            $is_naik = $item['tren'] === 'Naik';
            $rekomendasi = $is_naik
                ? 'Tambah stok atau pertahankan ketersediaan produk'
                : 'Tahan pembelian stok baru dan kurangi persediaan';
            $badge_rekom = $is_naik ? 'badge-naik' : 'badge-tahan';
          ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td><?= e(format_tanggal($item['tanggal'])) ?></td>
            <td><?= e($item['produk']) ?></td>
            <td><?= e($item['kategori']) ?></td>
            <td>
              <?php if ($is_naik): ?>
                <span class="badge-tren badge-naik">Naik</span>
              <?php else: ?>
                <span class="badge-tren badge-turun">Turun</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge-tren <?= e($badge_rekom) ?>">
                <?= e($rekomendasi) ?>
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <div class="card-title">Keterangan Rekomendasi</div>
    <div class="row g-3">
      <div class="col-md-6">
        <div class="d-flex align-items-start gap-2">
          <span class="badge-tren badge-naik" style="flex-shrink:0;">Naik</span>
          <div style="font-size:13px;">Tambah stok atau pertahankan ketersediaan produk</div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="d-flex align-items-start gap-2">
          <span class="badge-tren badge-turun" style="flex-shrink:0;">Turun</span>
          <div style="font-size:13px;">Tahan pembelian stok baru dan kurangi persediaan</div>
        </div>
      </div>
    </div>
  </div>
<?php elseif ($total_labeled >= 5): ?>
  <div class="card"><div class="alert alert-info mb-0">
    Gagal mengambil hasil prediksi. Pastikan layanan ML aktif.</div></div>
<?php else: ?>
  <div class="card"><div class="alert alert-info mb-0">
    Belum cukup data berlabel. Unggah dataset dan jalankan praproses terlebih dahulu.</div></div>
<?php endif; ?>

<?php require __DIR__ . '/../src/layout/bottom.php'; ?>