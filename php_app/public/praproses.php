<?php
/**
 * Halaman Praproses Data (Gambar 5.4).
 * Baca hasil dari tb_riset (preprocess terakhir).
 */
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/Db.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/MlClient.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/Riset.php';

Auth::requireLogin();
$user = Auth::user();

$error   = '';
$success = '';

// Jalankan praproses (tombol)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'run') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Sesi tidak valid. Muat ulang halaman.';
    } else {
        $n_transaksi = (int) Db::fetchScalar('SELECT COUNT(*) FROM tb_transaksi');
        if ($n_transaksi < 5) {
            $error = 'Belum ada cukup data. Unggah dataset terlebih dahulu.';
        } else {
            $rows = Db::fetchAll(
                "SELECT t.id_transaksi AS id, t.tanggal_transaksi AS tanggal,
                        p.nama_produk AS produk, p.kategori AS kategori,
                        t.jumlah_terjual AS jumlah, t.harga_satuan AS harga,
                        t.label_tren AS label
                 FROM tb_transaksi t
                 JOIN tb_produk p ON p.id_produk = t.id_produk
                 ORDER BY t.tanggal_transaksi ASC, t.id_transaksi ASC"
            );

            $client = new MlClient();
            $result = $client->preprocess($rows);

            if (empty($result['ok'])) {
                $error = $result['error'] ?? 'Gagal menjalankan praproses.';
                if (strpos($error, 'Layanan ML') !== false) $_SESSION['ml_down'] = true;
            } else {
                // Simpan ke tb_riset (replace yang lama untuk tipe preprocess)
                Riset::simpan(
                    'preprocess',
                    'Praproses ' . number_format($result['n_final'], 0, ',', '.') . ' data',
                    $result,
                    'Pembersihan: ' . $result['n_kosong'] . ' kosong, '
                    . $result['n_duplikat'] . ' duplikat. Split 80:20 → '
                    . $result['n_latih'] . ' latih, ' . $result['n_uji'] . ' uji.',
                    (int) $result['n_final']
                );

                log_activity('Praproses Data',
                    'Kosong: ' . $result['n_kosong'] . ', Duplikat: ' . $result['n_duplikat']
                    . ', Split: ' . $result['n_latih'] . ':' . $result['n_uji']);

                $success = 'Praproses berhasil. '
                         . number_format($result['n_final'], 0, ',', '.') . ' data siap digunakan '
                         . '(' . $result['n_latih'] . ' latih, ' . $result['n_uji'] . ' uji).';
            }
        }
    }
}

// Ambil dari tb_riset (preprocess terakhir)
$riset_preprocess = Riset::terbaru('preprocess');
$result = $riset_preprocess ? $riset_preprocess['data'] : null;

// Statistik
$total_raw     = (int) Db::fetchScalar('SELECT COUNT(*) FROM tb_transaksi');
$total_labeled = (int) Db::fetchScalar("SELECT COUNT(*) FROM tb_transaksi WHERE label_tren IS NOT NULL");
$n_produk      = (int) Db::fetchScalar('SELECT COUNT(DISTINCT id_produk) FROM tb_transaksi');
$n_naik        = (int) Db::fetchScalar("SELECT COUNT(*) FROM tb_transaksi WHERE label_tren = 'Naik'");
$n_turun       = (int) Db::fetchScalar("SELECT COUNT(*) FROM tb_transaksi WHERE label_tren = 'Turun'");

$contoh = Db::fetchAll(
    "SELECT t.tanggal_transaksi, p.nama_produk, t.jumlah_terjual,
            t.harga_satuan, p.kategori, t.label_tren
     FROM tb_transaksi t
     JOIN tb_produk p ON p.id_produk = t.id_produk
     WHERE t.label_tren IS NOT NULL
     ORDER BY t.tanggal_transaksi ASC, t.id_transaksi ASC LIMIT 10"
);

$page_title  = 'Praproses Data';
$active_menu = 'praproses';
require __DIR__ . '/../src/layout/top.php';
?>

<?php if ($error): ?><div class="alert alert-danger mb-3"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success mb-3"><?= e($success) ?></div><?php endif; ?>

<div class="card mb-3">
  <div class="card-title" style="margin-bottom:16px;">Tahapan Praproses</div>
  <div class="stepper">
    <div class="stepper-item done">
      <div class="stepper-num">✓</div>
      <div class="stepper-body">
        <div class="stepper-title">Pembersihan Data</div>
        <div class="stepper-sub">Kosong dan duplikat</div>
      </div>
    </div>
    <div class="stepper-line done"></div>
    <div class="stepper-item done">
      <div class="stepper-num">✓</div>
      <div class="stepper-body">
        <div class="stepper-title">Seleksi Produk</div>
        <div class="stepper-sub"><?= $n_produk ?> produk aktif</div>
      </div>
    </div>
    <div class="stepper-line done"></div>
    <div class="stepper-item done">
      <div class="stepper-num">✓</div>
      <div class="stepper-body">
        <div class="stepper-title">Transformasi Harga</div>
        <div class="stepper-sub">Format numerik</div>
      </div>
    </div>
    <div class="stepper-line done"></div>
    <div class="stepper-item done">
      <div class="stepper-num">✓</div>
      <div class="stepper-body">
        <div class="stepper-title">Pelabelan Tren</div>
        <div class="stepper-sub">Naik atau Turun</div>
      </div>
    </div>
  </div>
  <form method="post" class="mt-3">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="run">
    <button type="submit" class="btn btn-primary">🔄 Jalankan Praproses</button>
  </form>
  <?php if ($riset_preprocess): ?>
    <div style="font-size:11px; color:var(--text-muted); margin-top:12px;">
      Hasil dari riset #<?= (int) $riset_preprocess['id_riset'] ?> ·
      <?= e(date('d-m-Y H:i', strtotime($riset_preprocess['created_at']))) ?>
    </div>
  <?php endif; ?>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3">
    <div class="metric-card">
      <div class="metric-label">Data Kosong</div>
      <div class="metric-value"><?= $result ? (int) $result['n_kosong'] : '—' ?></div>
      <div class="metric-sub">record terbuang</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="metric-card">
      <div class="metric-label">Data Duplikat</div>
      <div class="metric-value"><?= $result ? (int) $result['n_duplikat'] : '—' ?></div>
      <div class="metric-sub">record terbuang</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="metric-card">
      <div class="metric-label">Produk Terpilih</div>
      <div class="metric-value"><?= number_format($n_produk, 0, ',', '.') ?></div>
      <div class="metric-sub">produk dipakai pemodelan</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="metric-card">
      <div class="metric-label">Dataset Akhir</div>
      <div class="metric-value"><?= number_format($total_labeled, 0, ',', '.') ?></div>
      <div class="metric-sub">record siap model</div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-6">
    <div class="card">
      <div class="card-title">Distribusi Label Tren</div>
      <?php
        $total_for_pct = $n_naik + $n_turun;
        $pct_naik  = $total_for_pct > 0 ? round($n_naik  / $total_for_pct * 100, 1) : 0;
        $pct_turun = $total_for_pct > 0 ? round($n_turun / $total_for_pct * 100, 1) : 0;
      ?>
      <div class="dist-bar-wrap">
        <div class="dist-bar-label">
          <span>Naik</span>
          <strong><?= number_format($n_naik, 0, ',', '.') ?> · <?= $pct_naik ?>%</strong>
        </div>
        <div class="dist-bar-bg">
          <div class="dist-bar-fill" style="width:<?= $pct_naik ?>%; background:#22C55E;"></div>
        </div>
      </div>
      <div class="dist-bar-wrap" style="margin-top:14px;">
        <div class="dist-bar-label">
          <span>Turun</span>
          <strong><?= number_format($n_turun, 0, ',', '.') ?> · <?= $pct_turun ?>%</strong>
        </div>
        <div class="dist-bar-bg">
          <div class="dist-bar-fill" style="width:<?= $pct_turun ?>%; background:#EF4444;"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card">
      <div class="card-title">Pembagian Data 80:20</div>
      <?php
        $n_latih = $result ? (int) $result['n_latih'] : (int) round($total_labeled * 0.8);
        $n_uji   = $result ? (int) $result['n_uji']   : ($total_labeled - $n_latih);
        $pct_latih = $total_labeled > 0 ? round($n_latih / $total_labeled * 100, 1) : 0;
        $pct_uji   = $total_labeled > 0 ? round($n_uji   / $total_labeled * 100, 1) : 0;
      ?>
      <div class="split-bar-wrap">
        <div class="split-bar">
          <div class="split-fill split-latih" style="width:<?= $pct_latih ?>%;"><?= $pct_latih ?>%</div>
          <div class="split-fill split-uji" style="width:<?= $pct_uji ?>%;"><?= $pct_uji ?>%</div>
        </div>
      </div>
      <div class="d-flex justify-content-between mt-3" style="font-size:12px;">
        <div>
          <div style="color:var(--text-muted);">Data Latih</div>
          <div style="font-weight:700; color:var(--indigo);">
            <?= number_format($n_latih, 0, ',', '.') ?> record
          </div>
        </div>
        <div style="text-align:right;">
          <div style="color:var(--text-muted);">Data Uji</div>
          <div style="font-weight:700; color:#0EA5E9;">
            <?= number_format($n_uji, 0, ',', '.') ?> record
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-title" style="margin-bottom:12px;">Contoh Data Berlabel Tren</div>
  <?php if (empty($contoh)): ?>
    <div class="alert alert-info mb-0">
      Belum ada data berlabel. Unggah dataset terlebih dahulu di halaman Input Dataset.
    </div>
  <?php else: ?>
    <div style="overflow-x:auto;">
      <table class="table-clean">
        <thead>
          <tr>
            <th>NO</th><th>TANGGAL</th><th>NAMA PRODUK</th><th>KATEGORI</th>
            <th style="text-align:right;">JUMLAH</th>
            <th style="text-align:right;">HARGA (RP)</th>
            <th>TREN</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($contoh as $i => $row): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><?= e(format_tanggal($row['tanggal_transaksi'])) ?></td>
              <td><?= e($row['nama_produk']) ?></td>
              <td><?= e($row['kategori']) ?></td>
              <td style="text-align:right;"><?= number_format((int) $row['jumlah_terjual'], 0, ',', '.') ?></td>
              <td style="text-align:right;"><?= e(format_rupiah($row['harga_satuan'])) ?></td>
              <td>
                <?php if ($row['label_tren'] === 'Naik'): ?>
                  <span class="badge-tren badge-naik">Naik</span>
                <?php elseif ($row['label_tren'] === 'Turun'): ?>
                  <span class="badge-tren badge-turun">Turun</span>
                <?php else: ?>
                  <span style="color:#94A3B8;">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div style="font-size:11px; color:var(--text-muted); margin-top:8px;">
      Menampilkan 10 contoh dari <?= number_format($total_labeled, 0, ',', '.') ?> record berlabel.
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../src/layout/bottom.php'; ?>