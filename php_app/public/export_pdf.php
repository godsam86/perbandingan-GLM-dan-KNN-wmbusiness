<?php
/**
 * Ekspor Rekomendasi Bisnis ke PDF 1 halaman.
 * Layout sesuai acuan, angka aktual dari sistem (DB + Python ML).
 */
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/Db.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/MlClient.php';
require_once __DIR__ . '/../src/helpers.php';

Auth::requireLogin();
$user = Auth::user();

// Autoload Composer
$vendor_autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($vendor_autoload)) {
    die('Composer belum diinstall. Jalankan: composer install');
}
require_once $vendor_autoload;

if (!class_exists('Dompdf\Dompdf')) {
    die('Class Dompdf\Dompdf tidak ditemukan.');
}

// ============================================================
// 1. AMBIL DATA BERLABEL DARI DB
// ============================================================
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
    die('Tidak ada data berlabel untuk diekspor.');
}

// ============================================================
// 2. PANGGIL PYTHON ML SERVICE — /api/latest
//    Semua angka prediksi berasal dari sini.
// ============================================================
$client = new MlClient();
$latest = $client->latest($rows);

if (empty($latest['ok']) || empty($latest['items'])) {
    die('Gagal mengambil data prediksi: ' . htmlspecialchars($latest['error'] ?? 'unknown'));
}

$items        = $latest['items'];          // list produk + tren + p_naik
$model_terbaik = $latest['model'];         // "GLM" atau "KNN (K=n)"
$f1_terbaik    = $latest['f1'];
$accuracy      = $latest['accuracy'];
$metrik_glm    = $latest['metrik_glm'];
$metrik_knn    = $latest['metrik_knn'];
$knn_k         = $latest['k'] ?? 5;

// Hitung Naik / Turun
$n_naik = 0; $n_turun = 0;
foreach ($items as $it) {
    if ($it['tren'] === 'Naik') $n_naik++;
    else $n_turun++;
}
$total_produk = count($items);

// ============================================================
// 3. STATISTIK AKTUAL DARI DATABASE
// ============================================================
$periode = Db::fetchOne(
    'SELECT MIN(tanggal_transaksi) AS tgl_min, MAX(tanggal_transaksi) AS tgl_max FROM tb_transaksi'
);
$periode_str = '-';
if (!empty($periode['tgl_min']) && !empty($periode['tgl_max'])) {
    $periode_str = date('d-m-Y', strtotime($periode['tgl_min']))
                 . ' - ' . date('d-m-Y', strtotime($periode['tgl_max']));
}

$n_data_total   = (int) Db::fetchScalar('SELECT COUNT(*) FROM tb_transaksi');
$n_produk_total = (int) Db::fetchScalar('SELECT COUNT(*) FROM tb_produk');
$n_kategori      = (int) Db::fetchScalar('SELECT COUNT(DISTINCT kategori) FROM tb_produk WHERE kategori IS NOT NULL');

$jumlah_produk_str = $n_produk_total . ' produk, ' . $n_kategori . ' kategori';
$akurasi_str       = number_format($accuracy * 100, 2, ',', '.') . '%';

// Pembagian data (kronologis 80:20) — hitung dari jumlah data aktual
$n_latih_aktual = (int) round($n_data_total * 0.8);
$n_uji_aktual   = $n_data_total - $n_latih_aktual;

// ============================================================
// 4. HITUNG STOK AKTUAL + P(TURUN) PER PRODUK
// ============================================================
// Ambil stok per produk dari DB
$produk_stok = [];
$produk_rows = Db::fetchAll('SELECT id_produk, nama_produk, stok FROM tb_produk');
foreach ($produk_rows as $p) {
    $produk_stok[$p['nama_produk']] = (int) $p['stok'];
}

// Lengkapi setiap item dengan stok & p_turun aktual
foreach ($items as &$it) {
    $it['stok'] = $produk_stok[$it['produk']] ?? 0;
    $it['p_turun'] = 1 - $it['p_naik'];
}
unset($it);

// ============================================================
// 5. PRIORITAS TINDAKAN (berdasarkan tren + p_naik aktual)
// ============================================================
// Urutkan salinan untuk prioritas (p_naik tertinggi = paling prioritas)
$items_sorted = $items;
usort($items_sorted, function($a, $b) { return $b['p_naik'] <=> $a['p_naik']; });

$prioritas_restok = [];
$pertahankan_stok = [];
$tahan_restok     = [];

// Aturan:
// - 3 produk Naik dengan p_naik tertinggi → Prioritas restok
// - Produk Naik lainnya                  → Pertahankan stok
// - Produk Turun                         → Tahan restok
$counter_naik = 0;
foreach ($items_sorted as $it) {
    if ($it['tren'] === 'Naik') {
        if ($counter_naik < 3) {
            $prioritas_restok[] = $it['produk'];
        } else {
            $pertahankan_stok[] = $it['produk'];
        }
        $counter_naik++;
    } else {
        $tahan_restok[] = $it['produk'];
    }
}

// ============================================================
// 6. SUSUN HTML UNTUK PDF
// ============================================================
$tgl_cetak = date('d-m-Y');
$user_name = $user['nama_lengkap'] ?? 'Admin';

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  @page { margin: 30px 32px 32px 32px; }
  * { font-family: DejaVu Sans, Arial, sans-serif; box-sizing: border-box; }
  body { font-size: 9px; color: #0F172A; margin: 0; padding: 0; }

  /* HEADER */
  .header { padding-bottom: 8px; margin-bottom: 12px; }
  .header-table { width: 100%; border-collapse: collapse; }
  .header-table td { vertical-align: middle; padding: 0; }
  .logo-box {
    width: 38px; height: 38px;
    background: #4F46E5;
    border-radius: 8px;
    text-align: center;
    color: #fff;
    font-weight: bold;
    font-size: 18px;
    line-height: 38px;
  }
  .header-text { padding-left: 12px; }
  .header-title { font-size: 16px; font-weight: bold; color: #0F172A; margin: 0; }
  .header-sub { font-size: 9px; color: #64748B; margin-top: 2px; }
  .header-meta { text-align: right; font-size: 8px; color: #64748B; line-height: 1.6; }
  .header-meta strong { color: #0F172A; }

  /* 4 KOTAK */
  .boxes { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin-bottom: 12px; }
  .boxes td {
    width: 25%;
    vertical-align: top;
    padding: 8px 10px;
    border: 1px solid #E5E7EB;
    background: #F8FAFC;
    border-radius: 6px;
  }
  .box-label { font-size: 7px; color: #64748B; text-transform: uppercase; letter-spacing: .5px; }
  .box-value { font-size: 11px; font-weight: bold; color: #0F172A; margin-top: 4px; }
  .box-sub { font-size: 7px; color: #94A3B8; margin-top: 2px; }

  /* SECTION */
  h2 {
    font-size: 10px;
    margin: 12px 0 6px 0;
    padding-bottom: 3px;
    color: #0F172A;
    border-bottom: 1px solid #E5E7EB;
  }

  /* TABEL DATA */
  table.data { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
  table.data th {
    background: #F1F5F9;
    color: #475569;
    font-size: 7px;
    font-weight: bold;
    padding: 5px 6px;
    text-align: left;
    text-transform: uppercase;
    letter-spacing: .3px;
    border-bottom: 1px solid #E5E7EB;
  }
  table.data td {
    font-size: 8px;
    padding: 5px 6px;
    border-bottom: 1px solid #F1F5F9;
    vertical-align: top;
  }
  table.data tr:nth-child(even) td { background: #FAFBFC; }
  table.data tr.highlight td {
    background: #EEF2FF;
    font-weight: bold;
  }

  /* BADGE */
  .badge { display: inline-block; padding: 2px 6px; font-size: 7px; font-weight: bold; border-radius: 8px; }
  .badge-naik  { background: #DCFCE7; color: #15803D; }
  .badge-turun { background: #FEE2E2; color: #B91C1C; }
  .badge-tahan { background: #FEF3C7; color: #B45309; }

  /* PRIORITAS */
  .priority-row { margin-bottom: 6px; font-size: 8px; line-height: 1.5; }
  .priority-badge {
    display: inline-block;
    padding: 3px 8px;
    font-size: 7px;
    font-weight: bold;
    border-radius: 8px;
    margin-right: 8px;
  }
  .priority-restok  { background: #FEE2E2; color: #B91C1C; }
  .priority-hold    { background: #DCFCE7; color: #15803D; }
  .priority-slow    { background: #FEF3C7; color: #B45309; }

  /* CATATAN */
  .notes { font-size: 7.5px; color: #475569; line-height: 1.6; margin-top: 4px; }
  .notes ul { margin: 0; padding-left: 14px; }
  .notes li { margin-bottom: 3px; }

  /* FOOTER */
  .footer {
    margin-top: 14px;
    padding-top: 6px;
    border-top: 1px solid #E5E7EB;
  }
  .footer-table { width: 100%; }
  .footer-table td { font-size: 7px; color: #94A3B8; }
</style>
</head>
<body>

<!-- HEADER -->
<div class="header">
  <table class="header-table">
    <tr>
      <td style="width:50px;">
        <div class="logo-box">L</div>
      </td>
      <td class="header-text">
        <div class="header-title">Laporan Rekomendasi Bisnis</div>
        <div class="header-sub">WMBusiness99 Medan &middot; Prediksi Tren Penjualan dengan <?= e($model_terbaik) ?></div>
      </td>
      <td class="header-meta">
        Dicetak: <?= e($tgl_cetak) ?><br>
        Pengguna: <strong><?= e($user_name) ?></strong>
      </td>
    </tr>
  </table>
</div>

<!-- 4 KOTAK -->
<table class="boxes">
  <tr>
    <td>
      <div class="box-label">Periode Data</div>
      <div class="box-value" style="font-size:9px;"><?= e($periode_str) ?></div>
    </td>
    <td>
      <div class="box-label">Jumlah Produk</div>
      <div class="box-value" style="font-size:9px;"><?= e($jumlah_produk_str) ?></div>
    </td>
    <td>
      <div class="box-label">Model</div>
      <div class="box-value" style="font-size:9px;">
        <?= e($model_terbaik) ?> &middot; Accuracy <?= e($akurasi_str) ?>
      </div>
    </td>
    <td>
      <div class="box-label">Prediksi Tren</div>
      <div class="box-value" style="font-size:9px;">
        Naik <?= (int) $n_naik ?> &middot; Turun <?= (int) $n_turun ?>
      </div>
    </td>
  </tr>
</table>

<!-- SECTION 1 -->
<h2>1. Ringkasan Kinerja Model pada Data Uji</h2>
<table class="data">
  <thead>
    <tr>
      <th style="width:25%;">Model</th>
      <th>Accuracy</th>
      <th>Precision</th>
      <th>Recall</th>
      <th>F1-Score</th>
      <th>Error Rate</th>
    </tr>
  </thead>
  <tbody>
    <?php
      // Tentukan model unggul untuk highlight
      $glm_unggul = ($metrik_glm['f1'] ?? 0) >= ($metrik_knn['f1'] ?? 0);
    ?>
    <tr class="<?= $glm_unggul ? 'highlight' : '' ?>">
      <td>GLM <?= $glm_unggul ? '(model terpilih)' : '' ?></td>
      <td><?= e(number_format(($metrik_glm['accuracy'] ?? 0) * 100, 2, ',', '.')) ?>%</td>
      <td><?= e(number_format(($metrik_glm['precision'] ?? 0) * 100, 2, ',', '.')) ?>%</td>
      <td><?= e(number_format(($metrik_glm['recall'] ?? 0) * 100, 2, ',', '.')) ?>%</td>
      <td><?= e(number_format(($metrik_glm['f1'] ?? 0) * 100, 2, ',', '.')) ?>%</td>
      <td><?= e(number_format(($metrik_glm['error_rate'] ?? 0) * 100, 2, ',', '.')) ?>%</td>
    </tr>
    <tr class="<?= !$glm_unggul ? 'highlight' : '' ?>">
      <td>KNN (K = <?= (int) $knn_k ?>) <?= !$glm_unggul ? '(model terpilih)' : '' ?></td>
      <td><?= e(number_format(($metrik_knn['accuracy'] ?? 0) * 100, 2, ',', '.')) ?>%</td>
      <td><?= e(number_format(($metrik_knn['precision'] ?? 0) * 100, 2, ',', '.')) ?>%</td>
      <td><?= e(number_format(($metrik_knn['recall'] ?? 0) * 100, 2, ',', '.')) ?>%</td>
      <td><?= e(number_format(($metrik_knn['f1'] ?? 0) * 100, 2, ',', '.')) ?>%</td>
      <td><?= e(number_format(($metrik_knn['error_rate'] ?? 0) * 100, 2, ',', '.')) ?>%</td>
    </tr>
  </tbody>
</table>
<div style="font-size:7px; color:#94A3B8; margin-bottom:6px;">
  Pembagian data kronologis 80:20 (<?= number_format($n_latih_aktual, 0, ',', '.') ?> data latih,
  <?= number_format($n_uji_aktual, 0, ',', '.') ?> data uji).
  Kelas positif: Naik.
</div>

<!-- SECTION 2 -->
<h2>2. Rekomendasi per Produk</h2>
<table class="data">
  <thead>
    <tr>
      <th style="width:20px;">No</th>
      <th style="width:17%;">Produk</th>
      <th style="width:45px;">Tren</th>
      <th style="width:50px;">P(Turun)</th>
      <th style="width:35px;">Stok</th>
      <th>Pengelolaan Stok</th>
      <th>Strategi Penjualan</th>
      <th>Perencanaan Persediaan</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($items as $i => $it): ?>
      <?php
        $is_naik = $it['tren'] === 'Naik';
        $tren_badge = $is_naik ? 'badge-naik' : 'badge-turun';

        if ($is_naik) {
            $pengelolaan = 'Pertahankan stok';
            $strategi    = 'Perkuat promosi';
            $perencanaan = 'Jadwalkan restok lebih awal';
        } else {
            $pengelolaan = 'Tahan restok, pertimbangkan promo';
            $strategi    = 'Promo atau bundling';
            $perencanaan = 'Tunda restok hingga tren membaik';
        }
      ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td><?= e($it['produk']) ?></td>
        <td><span class="badge <?= $tren_badge ?>"><?= e($it['tren']) ?></span></td>
        <td><?= e(number_format($it['p_turun'], 3, ',', '.')) ?></td>
        <td><?= (int) $it['stok'] ?></td>
        <td><?= e($pengelolaan) ?></td>
        <td><?= e($strategi) ?></td>
        <td><?= e($perencanaan) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<!-- SECTION 3 -->
<h2>3. Prioritas Tindakan</h2>

<?php if (!empty($prioritas_restok)): ?>
  <div class="priority-row">
    <span class="priority-badge priority-restok">Prioritas restok</span>
    <?= e(implode(', ', $prioritas_restok)) ?>
  </div>
<?php endif; ?>

<?php if (!empty($pertahankan_stok)): ?>
  <div class="priority-row">
    <span class="priority-badge priority-hold">Pertahankan stok</span>
    <?= e(implode(', ', $pertahankan_stok)) ?>
  </div>
<?php endif; ?>

<?php if (!empty($tahan_restok)): ?>
  <div class="priority-row">
    <span class="priority-badge priority-slow">Tahan restok</span>
    <?= e(implode(', ', $tahan_restok)) ?>
  </div>
<?php endif; ?>

<!-- CATATAN -->
<h2>Catatan</h2>
<div class="notes">
  <ul>
    <li>Prediksi memakai <?= e($model_terbaik) ?> yang dilatih pada <?= number_format($n_latih_aktual, 0, ',', '.') ?>
        data transaksi dengan jumlah terjual serta harga satuan sebagai masukan.</li>
    <li>Prediksi bersifat probabilistik (Accuracy <?= e($akurasi_str) ?>),
        sehingga rekomendasi perlu ditinjau pemilik usaha sebelum dijadikan keputusan.</li>
    <li>Ambang stok aktual dapat disesuaikan sesuai kapasitas gudang dan kondisi permintaan pasar.</li>
  </ul>
</div>

<!-- FOOTER -->
<div class="footer">
  <table class="footer-table">
    <tr>
      <td style="text-align:left;">
        Dihasilkan otomatis oleh Sistem Prediksi Tren Penjualan WMBusiness99 Medan
      </td>
      <td style="text-align:right;">
        Halaman 1 dari 1
      </td>
    </tr>
  </table>
</div>

</body>
</html>
<?php
$html = ob_get_clean();

// ============================================================
// 7. RENDER PDF
// ============================================================
$options = new \Dompdf\Options();
$options->set('isRemoteEnabled', false);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new \Dompdf\Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'Laporan_Rekomendasi_Bisnis_WMBusiness99_' . date('Ymd_His') . '.pdf';

log_activity('Ekspor PDF', 'File: ' . $filename);

$dompdf->stream($filename, ['Attachment' => true]);
exit;