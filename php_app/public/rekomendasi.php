<?php
/**
 * Halaman Rekomendasi Bisnis (Gambar 5.9)
 */
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/Db.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/MlClient.php';
require_once __DIR__ . '/../src/helpers.php';

Auth::requireLogin();
$user = Auth::user();

$error = '';

$total_labeled = (int) Db::fetchScalar("SELECT COUNT(*) FROM tb_transaksi WHERE label_tren IS NOT NULL");
$latest = null;

if ($total_labeled >= 5) {
    $cache_key      = 'latest_cache';
    $cache_time_key = 'latest_cache_time';

    if (!empty($_SESSION[$cache_key]) && !empty($_SESSION[$cache_time_key])
        && (time() - $_SESSION[$cache_time_key]) < 300) {
        $latest = $_SESSION[$cache_key];
    } else {
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

        $client = new MlClient();
        $latest = $client->latest($rows);

        if (!empty($latest['ok'])) {
            $_SESSION[$cache_key] = $latest;
            $_SESSION[$cache_time_key] = time();
        } else {
            $error = $latest['error'] ?? 'Gagal mengambil hasil prediksi.';
            if (strpos($error, 'ML') !== false) {
                $_SESSION['ml_down'] = true;
            }
            $latest = null;
        }
    }
}

// Hitung statistik
$n_naik = 0; $n_turun = 0;
$items = $latest['items'] ?? [];
foreach ($items as $it) {
    if ($it['tren'] === 'Naik') $n_naik++;
    else $n_turun++;
}
$total_items = $n_naik + $n_turun;
$pct_naik  = $total_items > 0 ? round($n_naik  / $total_items * 100, 1) : 0;
$pct_turun = $total_items > 0 ? round($n_turun / $total_items * 100, 1) : 0;

$page_title  = 'Rekomendasi Bisnis';
$active_menu = 'rekomendasi';
require __DIR__ . '/../src/layout/top.php';
?>

<?php if ($error): ?><div class="alert alert-danger mb-3"><?= e($error) ?></div><?php endif; ?>

<?php if ($latest && !empty($latest['items'])): ?>

  <!-- ============ HEADER ============ -->
  <div class="card mb-3" style="padding:14px 20px;">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
      <div class="d-flex align-items-center gap-3">
        <div style="font-size:32px;">🏆</div>
        <div>
          <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; letter-spacing:.04em;">
            Rekomendasi berdasarkan model terbaik
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
        <a href="export_pdf.php" class="btn btn-primary" target="_blank">
          📄 Ekspor PDF
        </a>
      </div>
    </div>
  </div>

  <!-- ============ 4 KARTU RINGKASAN ============ -->
  <div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
      <div class="metric-card">
        <div class="metric-label">Prediksi Naik</div>
        <div class="metric-value" style="color:#15803D;">
          <?= number_format($n_naik, 0, ',', '.') ?>
        </div>
        <div class="metric-sub"><?= $pct_naik ?>% dari <?= $total_items ?> produk</div>
      </div>
    </div>

    <div class="col-6 col-lg-3">
      <div class="metric-card">
        <div class="metric-label">Prediksi Turun</div>
        <div class="metric-value" style="color:#B91C1C;">
          <?= number_format($n_turun, 0, ',', '.') ?>
        </div>
        <div class="metric-sub"><?= $pct_turun ?>% dari <?= $total_items ?> produk</div>
      </div>
    </div>

    <div class="col-6 col-lg-3">
      <div class="metric-card">
        <div class="metric-label">Model Terbaik</div>
        <div class="metric-value" style="font-size:16px;">
          <?= e($latest['model']) ?>
        </div>
        <div class="metric-sub">unggul pada skenario uji</div>
      </div>
    </div>

    <div class="col-6 col-lg-3">
      <div class="metric-card">
        <div class="metric-label">Accuracy Model</div>
        <div class="metric-value">
          <?= e(number_format($latest['accuracy'] * 100, 2, ',', '.')) ?>%
        </div>
        <div class="metric-sub"><?= $total_items ?> produk diprediksi</div>
      </div>
    </div>
  </div>

  <!-- ============ 3 KARTU KATEGORI ============ -->
  <div class="row g-3 mb-3">
    <div class="col-lg-4">
      <div class="card">
        <div class="card-title">Pengelolaan Stok Barang</div>
        <div style="font-size:13px; line-height:1.7;">
          <div style="margin-bottom:8px;">
            <span class="badge-tren badge-naik">Naik</span>
            <span style="margin-left:6px;">Tambah stok dan jaga ketersediaan produk yang sedang naik</span>
          </div>
          <div>
            <span class="badge-tren badge-turun">Turun</span>
            <span style="margin-left:6px;">Tahan pembelian stok baru dan kurangi persediaan produk</span>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card">
        <div class="card-title">Strategi Penjualan</div>
        <div style="font-size:13px; line-height:1.7;">
          <div style="margin-bottom:8px;">
            <span class="badge-tren badge-naik">Naik</span>
            <span style="margin-left:6px;">Perkuat promosi pada produk yang sedang diminati</span>
          </div>
          <div>
            <span class="badge-tren badge-turun">Turun</span>
            <span style="margin-left:6px;">Pertimbangkan promo atau bundling untuk mendorong penjualan</span>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card">
        <div class="card-title">Perencanaan Persediaan</div>
        <div style="font-size:13px; line-height:1.7;">
          <div style="margin-bottom:8px;">
            <span class="badge-tren badge-naik">Naik</span>
            <span style="margin-left:6px;">Jadwalkan restok lebih awal pada periode berikutnya</span>
          </div>
          <div>
            <span class="badge-tren badge-turun">Turun</span>
            <span style="margin-left:6px;">Tunda restok hingga tren membaik pada prediksi berikutnya</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ============ TABEL REKOMENDASI PER PRODUK ============ -->
  <div class="card">
    <div class="card-title" style="margin-bottom:12px;">Rekomendasi per Produk</div>
    <table class="table-clean">
      <thead>
        <tr>
          <th>NAMA PRODUK</th>
          <th>KATEGORI</th>
          <th>PREDIKSI TREN</th>
          <th>PENGELOLAAN STOK</th>
          <th>STRATEGI PENJUALAN</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $item): ?>
          <?php
            $is_naik = $item['tren'] === 'Naik';

            if ($is_naik) {
                $stok_badge    = 'badge-naik';
                $stok_label    = 'Tambah stok';
                $strategi_badge = 'badge-promo';
                $strategi_label = 'Perkuat promosi';
            } else {
                $stok_badge    = 'badge-tahan';
                $stok_label    = 'Tahan pembelian stok';
                $strategi_badge = 'badge-promo';
                $strategi_label = 'Promo atau bundling';
            }
          ?>
          <tr>
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
              <span class="badge-tren <?= e($stok_badge) ?>">
                <?= e($stok_label) ?>
              </span>
            </td>
            <td>
              <span class="badge-tren <?= e($strategi_badge) ?>">
                <?= e($strategi_label) ?>
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

<?php elseif ($total_labeled >= 5): ?>
  <div class="card">
    <div class="alert alert-info mb-0">
      Gagal mengambil hasil prediksi. Pastikan layanan ML aktif.
    </div>
  </div>
<?php else: ?>
  <div class="card">
    <div class="alert alert-info mb-0">
      Belum cukup data. Unggah dataset dan jalankan praproses terlebih dahulu.
    </div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../src/layout/bottom.php'; ?>