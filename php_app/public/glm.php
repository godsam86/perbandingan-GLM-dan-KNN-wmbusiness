<?php
/**
 * Halaman Prediksi GLM (Gambar 5.5)
 */
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/Db.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/MlClient.php';
require_once __DIR__ . '/../src/helpers.php';

Auth::requireLogin();
$user = Auth::user();

$error   = '';
$success = '';

// Skenario & produk dari request
$skenario = $_POST['skenario'] ?? ($_GET['skenario'] ?? 'semua');
if (!in_array($skenario, ['semua', 'latih', 'uji'], true)) {
    $skenario = 'semua';
}
$produk_pilihan = $_POST['produk'] ?? ($_GET['produk'] ?? '');
if ($produk_pilihan === '') $produk_pilihan = null;

// ============================================================
// PROSES POST — Jalankan Prediksi GLM
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'run') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Sesi tidak valid. Muat ulang halaman.';
    } else {
        $total = (int) Db::fetchScalar("SELECT COUNT(*) FROM tb_transaksi WHERE label_tren IS NOT NULL");
        if ($total < 5) {
            $error = 'Belum cukup data berlabel. Jalankan praproses terlebih dahulu.';
        } else {
            // Ambil semua transaksi berlabel
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
            $result = $client->evaluate($rows, $skenario, null, $produk_pilihan);

            if (empty($result['ok'])) {
                $error = $result['error'] ?? 'Gagal menjalankan prediksi GLM.';
                if (strpos($error, 'ML') !== false) {
                    $_SESSION['ml_down'] = true;
                }
            } else {
                // ============================================================
                // SIMPAN HASIL KE DATABASE
                // ============================================================
                try {
                    $pdo = Db::conn();
                    $pdo->beginTransaction();

                    $glm = $result['glm'];
                    $b0 = $glm['koefisien']['b0'];
                    $b1 = $glm['koefisien']['b1'];
                    $b2 = $glm['koefisien']['b2'];

                    // 1. Simpan koefisien GLM
                    $id_koefisien = Db::insert(
                        'INSERT INTO tb_koefisien_glm (beta0, beta1, beta2) VALUES (?, ?, ?)',
                        [$b0, $b1, $b2]
                    );

                    // 2. Simpan evaluasi
                    $m = $glm['metrik'];
                    $id_evaluasi = Db::insert(
                        'INSERT INTO tb_evaluasi
                         (algoritma, accuracy, precision_score, recall_score, f1_score, confusion_matrix)
                         VALUES (?, ?, ?, ?, ?, ?)',
                        [
                            'GLM',
                            $m['accuracy'],
                            $m['precision'],
                            $m['recall'],
                            $m['f1'],
                            json_encode($glm['confusion']),
                        ]
                    );

                    // 3. Simpan hasil prediksi per produk (dari sampel)
                    foreach ($glm['sampel'] as $s) {
                        $produk_row = Db::fetchOne(
                            'SELECT id_produk FROM tb_produk WHERE nama_produk = ? LIMIT 1',
                            [$s['produk']]
                        );
                        if ($produk_row) {
                            Db::execute(
                                'INSERT INTO tb_hasil_prediksi
                                 (id_produk, id_evaluasi, id_koefisien, algoritma, periode,
                                  hasil_prediksi, probabilitas)
                                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                                [
                                    (int) $produk_row['id_produk'],
                                    $id_evaluasi,
                                    $id_koefisien,
                                    'GLM',
                                    $skenario,
                                    $s['prediksi'],
                                    $s['p_naik'],
                                ]
                            );
                        }
                    }

                    // 4. Simpan ringkasan riset
                    Db::execute(
                        'INSERT INTO tb_riset
                         (judul, deskripsi, tipe_riset, data_json, jumlah_data, status, created_by)
                         VALUES (?, ?, ?, ?, ?, ?, ?)',
                        [
                            'GLM ' . $result['n_latih'] . ' latih / ' . $result['n_uji'] . ' uji',
                            'Accuracy: ' . $m['accuracy'] . ', F1: ' . $m['f1'],
                            'glm_' . $skenario,
                            json_encode($result, JSON_UNESCAPED_UNICODE),
                            $result['n_latih'] + $result['n_uji'],
                            'completed',
                            $_SESSION['id_user'] ?? null,
                        ]
                    );

                    $pdo->commit();
                    log_activity('Hitung GLM',
                        'Skenario: ' . $skenario . ', Accuracy: ' . $m['accuracy'] . ', F1: ' . $m['f1']);

                    $success = 'Prediksi GLM berhasil dijalankan.';
                } catch (Exception $e) {
                    if (Db::conn()->inTransaction()) Db::conn()->rollBack();
                    $error = 'Gagal menyimpan hasil: ' . $e->getMessage();
                }

                // Simpan hasil di session untuk ditampilkan
                $_SESSION['glm_result'] = $result;
                $_SESSION['glm_skenario'] = $skenario;
                $_SESSION['glm_produk'] = $produk_pilihan;
            }
        }
    }
}

// Ambil hasil dari session
$result = $_SESSION['glm_result'] ?? null;
$saved_skenario = $_SESSION['glm_skenario'] ?? 'semua';

// ============================================================
// DATA UNTUK DROPDOWN
// ============================================================
$produk_list = Db::fetchAll(
    'SELECT id_produk, nama_produk, kategori FROM tb_produk ORDER BY nama_produk ASC'
);

$total_labeled = (int) Db::fetchScalar("SELECT COUNT(*) FROM tb_transaksi WHERE label_tren IS NOT NULL");

// Label skenario (pakai jumlah data aktual)
$n_semua = $total_labeled;
$n_latih_aktual = (int) round($total_labeled * 0.8);
$n_uji_aktual = $total_labeled - $n_latih_aktual;

$skenario_label = [
    'semua' => $n_semua . ' data',
    'latih' => $n_latih_aktual . ' data',
    'uji'   => $n_uji_aktual . ' data',
];

$page_title  = 'Prediksi GLM';
$active_menu = 'glm';
require __DIR__ . '/../src/layout/top.php';
?>

<?php if ($error): ?><div class="alert alert-danger mb-3"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success mb-3"><?= e($success) ?></div><?php endif; ?>

<!-- ============ FILTER ============ -->
<div class="card mb-3">
  <form method="post" class="row g-3 align-items-end">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="run">

    <div class="col-md-4">
      <label class="form-label">Skenario Data</label>
      <select name="skenario" class="form-select">
        <?php foreach ($skenario_label as $key => $lbl): ?>
          <option value="<?= e($key) ?>" <?= $skenario === $key ? 'selected' : '' ?>>
            <?= e($lbl) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-4">
      <label class="form-label">Produk</label>
      <select name="produk" class="form-select">
        <option value="">Semua produk</option>
        <?php foreach ($produk_list as $p): ?>
          <option value="<?= e($p['nama_produk']) ?>"
            <?= $produk_pilihan === $p['nama_produk'] ? 'selected' : '' ?>>
            <?= e($p['nama_produk']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-4">
      <button type="submit" class="btn btn-primary w-100" style="justify-content:center;">
        🔄 Jalankan Prediksi GLM
      </button>
    </div>
  </form>
</div>

<?php if ($result): ?>
  <?php
    $glm = $result['glm'];
    $m = $glm['metrik'];
    $cm = $glm['confusion'];
    $koef = $glm['koefisien'];
  ?>

  <!-- ============ 4 KARTU METRIK ============ -->
  <div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
      <div class="metric-card">
        <div class="metric-label">Accuracy</div>
        <div class="metric-value"><?= e(number_format($m['accuracy'] * 100, 2, ',', '.')) ?>%</div>
        <div class="metric-sub"><?= number_format($result['n_uji'], 0, ',', '.') ?> data uji</div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="metric-card">
        <div class="metric-label">Precision</div>
        <div class="metric-value"><?= e(number_format($m['precision'] * 100, 2, ',', '.')) ?>%</div>
        <div class="metric-sub">dari prediksi Naik</div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="metric-card">
        <div class="metric-label">Recall</div>
        <div class="metric-value"><?= e(number_format($m['recall'] * 100, 2, ',', '.')) ?>%</div>
        <div class="metric-sub">dari aktual Naik</div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="metric-card">
        <div class="metric-label">F1-Score</div>
        <div class="metric-value"><?= e(number_format($m['f1'] * 100, 2, ',', '.')) ?>%</div>
        <div class="metric-sub">harmonic mean</div>
      </div>
    </div>
  </div>

  <!-- ============ KOEFISIEN + CONFUSION ============ -->
  <div class="row g-3 mb-3">
    <div class="col-lg-5">
      <div class="card">
        <div class="card-title">Koefisien Model GLM (Binomial, logit)</div>
        <table class="table-clean">
          <thead>
            <tr><th>VARIABEL</th><th style="text-align:right;">KOEFISIEN</th></tr>
          </thead>
          <tbody>
            <tr>
              <td>Konstanta (β₀)</td>
              <td style="text-align:right; font-family:monospace;">
                <?= e(number_format($koef['b0'], 8, ',', '.')) ?>
              </td>
            </tr>
            <tr>
              <td>Jumlah Terjual (β₁)</td>
              <td style="text-align:right; font-family:monospace;">
                <?= e(number_format($koef['b1'], 8, ',', '.')) ?>
              </td>
            </tr>
            <tr>
              <td>Harga (β₂)</td>
              <td style="text-align:right; font-family:monospace;">
                <?= e(number_format($koef['b2'], 12, ',', '.')) ?>
              </td>
            </tr>
          </tbody>
        </table>
        <div style="font-size:11px; color:var(--text-muted); margin-top:10px;">
          Iterasi: <?= (int) $glm['n_iter'] ?> · Konvergen: 
          <?= $glm['converged'] ? '✓' : '✗' ?>
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="card">
        <div class="card-title">Confusion Matrix</div>
        <div class="cm-wrap">
          <div class="cm-corner"></div>
          <div class="cm-header">Prediksi Naik</div>
          <div class="cm-header">Prediksi Turun</div>

          <div class="cm-row-label">Aktual Naik</div>
          <div class="cm-cell cm-tp"><?= (int) $cm['tp'] ?></div>
          <div class="cm-cell cm-fn"><?= (int) $cm['fn'] ?></div>

          <div class="cm-row-label">Aktual Turun</div>
          <div class="cm-cell cm-fp"><?= (int) $cm['fp'] ?></div>
          <div class="cm-cell cm-tn"><?= (int) $cm['tn'] ?></div>
        </div>
        <div style="font-size:11px; color:var(--text-muted); margin-top:10px;">
          TP: True Positive · TN: True Negative · FP: False Positive · FN: False Negative
        </div>
      </div>
    </div>
  </div>

  <!-- ============ TABEL HASIL PREDIKSI SAMPEL ============ -->
  <div class="card">
    <div class="card-title" style="margin-bottom:12px;">Hasil Prediksi Data Sampel</div>
    <?php if (empty($glm['sampel'])): ?>
      <div class="alert alert-info mb-0">Tidak ada sampel.</div>
    <?php else: ?>
      <div style="overflow-x:auto;">
        <table class="table-clean">
          <thead>
            <tr>
              <th>NO</th>
              <th>NAMA PRODUK</th>
              <th style="text-align:right;">X₁ (Qty)</th>
              <th style="text-align:right;">X₂ (Rp)</th>
              <th style="text-align:right;">P(NAIK)</th>
              <th>PREDIKSI</th>
              <th>CONFIDENCE</th>
              <th>AKTUAL</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($glm['sampel'] as $s): ?>
              <tr>
                <td><?= (int) $s['no'] ?></td>
                <td><?= e($s['produk']) ?></td>
                <td style="text-align:right;"><?= number_format((int) $s['x1'], 0, ',', '.') ?></td>
                <td style="text-align:right;"><?= e(format_rupiah($s['x2'])) ?></td>
                <td style="text-align:right; font-family:monospace;">
                  <?= e(number_format($s['p_naik'], 4, ',', '.')) ?>
                </td>
                <td>
                  <?php if ($s['prediksi'] === 'Naik'): ?>
                    <span class="badge-tren badge-naik">Naik</span>
                  <?php else: ?>
                    <span class="badge-tren badge-turun">Turun</span>
                  <?php endif; ?>
                </td>
                <td style="min-width:120px;">
                  <div style="font-size:11px; color:var(--text-muted);">
                    <?= e(number_format($s['confidence'] * 100, 2, ',', '.')) ?>%
                  </div>
                  <div class="conf-bar">
                    <div class="conf-fill" style="width:<?= $s['confidence'] * 100 ?>%;"></div>
                  </div>
                </td>
                <td>
                  <?php if ($s['aktual'] === 'Naik'): ?>
                    <span class="badge-tren badge-naik">Naik</span>
                  <?php else: ?>
                    <span class="badge-tren badge-turun">Turun</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="card">
    <div class="alert alert-info mb-0">
      Pilih skenario dan klik <strong>Jalankan Prediksi GLM</strong> untuk melihat hasil.
    </div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../src/layout/bottom.php'; ?>