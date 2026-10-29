<?php
/**
 * Halaman Prediksi KNN (Gambar 5.6)
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

// Skenario & K dari request
$skenario = $_POST['skenario'] ?? ($_GET['skenario'] ?? 'uji');
if (!in_array($skenario, ['semua', 'latih', 'uji'], true)) {
    $skenario = 'uji';
}
$k_pilihan = (int) ($_POST['k'] ?? ($_GET['k'] ?? 5));
if (!in_array($k_pilihan, [3, 5, 7], true)) {
    $k_pilihan = 5;
}

// ============================================================
// PROSES POST — Jalankan Prediksi KNN
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
            $result = $client->evaluate($rows, $skenario, $k_pilihan, null);

            if (empty($result['ok'])) {
                $error = $result['error'] ?? 'Gagal menjalankan prediksi KNN.';
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

                    $knn = $result['knn'];
                    $k_terbaik = (int) $knn['k_terbaik'];
                    $per_k = $knn['per_k'];
                    $metrik_k = $per_k[(string) $k_pilihan]['metrik'];
                    $confusion_k = $per_k[(string) $k_pilihan]['confusion'];

                    // 1. Simpan evaluasi KNN (K terpilih)
                    $id_evaluasi = Db::insert(
                        'INSERT INTO tb_evaluasi
                         (algoritma, accuracy, precision_score, recall_score, f1_score, confusion_matrix)
                         VALUES (?, ?, ?, ?, ?, ?)',
                        [
                            'KNN (K=' . $k_pilihan . ')',
                            $metrik_k['accuracy'],
                            $metrik_k['precision'],
                            $metrik_k['recall'],
                            $metrik_k['f1'],
                            json_encode($confusion_k),
                        ]
                    );

                    // 2. Simpan hasil prediksi per produk dari contoh (data uji terakhir)
                    if (!empty($knn['contoh'])) {
                        $contoh = $knn['contoh'];
                        $produk_row = Db::fetchOne(
                            'SELECT id_produk FROM tb_produk WHERE nama_produk = ? LIMIT 1',
                            [$contoh['produk']]
                        );
                        if ($produk_row) {
                            // Simpan probabilitas naik berdasarkan voting
                            $total_suara = $contoh['suara']['naik'] + $contoh['suara']['turun'];
                            $p_naik = $total_suara > 0 ? $contoh['suara']['naik'] / $total_suara : 0;

                            Db::execute(
                                'INSERT INTO tb_hasil_prediksi
                                 (id_produk, id_evaluasi, id_koefisien, algoritma, periode,
                                  hasil_prediksi, probabilitas)
                                 VALUES (?, ?, NULL, ?, ?, ?, ?)',
                                [
                                    (int) $produk_row['id_produk'],
                                    $id_evaluasi,
                                    'KNN (K=' . $k_pilihan . ')',
                                    $skenario,
                                    $contoh['prediksi'],
                                    $p_naik,
                                ]
                            );
                        }
                    }

                    // 3. Simpan ringkasan riset
                    Db::execute(
                        'INSERT INTO tb_riset
                         (judul, deskripsi, tipe_riset, data_json, jumlah_data, status, created_by)
                         VALUES (?, ?, ?, ?, ?, ?, ?)',
                        [
                            'KNN K=' . $k_pilihan . ' ' . $result['n_latih'] . ' latih / ' . $result['n_uji'] . ' uji',
                            'Accuracy: ' . $metrik_k['accuracy'] . ', F1: ' . $metrik_k['f1']
                                . ', K terbaik: ' . $k_terbaik,
                            'knn_' . $skenario,
                            json_encode($result, JSON_UNESCAPED_UNICODE),
                            $result['n_latih'] + $result['n_uji'],
                            'completed',
                            $_SESSION['id_user'] ?? null,
                        ]
                    );

                    $pdo->commit();
                    log_activity('Hitung KNN',
                        'Skenario: ' . $skenario . ', K=' . $k_pilihan
                        . ', Accuracy: ' . $metrik_k['accuracy']
                        . ', F1: ' . $metrik_k['f1']);

                    $success = 'Prediksi KNN berhasil dijalankan (K=' . $k_pilihan . ').';
                } catch (Exception $e) {
                    if (Db::conn()->inTransaction()) Db::conn()->rollBack();
                    $error = 'Gagal menyimpan hasil: ' . $e->getMessage();
                }

                // Simpan hasil di session
                $_SESSION['knn_result']    = $result;
                $_SESSION['knn_skenario']  = $skenario;
                $_SESSION['knn_k_pilihan'] = $k_pilihan;
            }
        }
    }
}

// Ambil hasil dari session
$result = $_SESSION['knn_result'] ?? null;

// Label skenario (pakai jumlah data aktual)
$total_labeled = (int) Db::fetchScalar("SELECT COUNT(*) FROM tb_transaksi WHERE label_tren IS NOT NULL");
$n_latih_aktual = (int) round($total_labeled * 0.8);
$n_uji_aktual = $total_labeled - $n_latih_aktual;

$skenario_label = [
    'semua' => $total_labeled . ' data',
    'latih' => $n_latih_aktual . ' data',
    'uji'   => $n_uji_aktual . ' data',
];

$page_title  = 'Prediksi KNN';
$active_menu = 'knn';
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
      <label class="form-label">Nilai K</label>
      <div class="segmented">
        <?php foreach ([3, 5, 7] as $k): ?>
          <label class="segmented-item <?= $k_pilihan === $k ? 'active' : '' ?>">
            <input type="radio" name="k" value="<?= $k ?>"
                   <?= $k_pilihan === $k ? 'checked' : '' ?>>
            K = <?= $k ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="col-md-4">
      <button type="submit" class="btn btn-primary w-100" style="justify-content:center;">
        🔄 Jalankan Prediksi KNN
      </button>
    </div>
  </form>
</div>

<?php if ($result): ?>
  <?php
    $knn = $result['knn'];
    $per_k = $knn['per_k'];
    $k_terbaik = (int) $knn['k_terbaik'];
    $metrik_k = $per_k[(string) $k_pilihan]['metrik'];
    $confusion_k = $per_k[(string) $k_pilihan]['confusion'];
    $contoh = $knn['contoh'];
  ?>

  <!-- ============ PERBANDINGAN NILAI K + CONFUSION ============ -->
  <div class="row g-3 mb-3">
    <div class="col-lg-7">
      <div class="card">
        <div class="card-title">Perbandingan Nilai K</div>
        <table class="table-clean">
          <thead>
            <tr>
              <th>K</th>
              <th style="text-align:right;">ACCURACY</th>
              <th style="text-align:right;">PRECISION</th>
              <th style="text-align:right;">RECALL</th>
              <th style="text-align:right;">F1-SCORE</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ([3, 5, 7] as $k): ?>
              <?php $mk = $per_k[(string) $k]['metrik']; ?>
              <tr class="<?= $k === $k_pilihan ? 'row-highlight' : '' ?>">
                <td>
                  <strong>K = <?= $k ?></strong>
                  <?php if ($k === $k_terbaik): ?>
                    <span class="badge-tren badge-promo" style="margin-left:6px;">Terbaik</span>
                  <?php endif; ?>
                </td>
                <td style="text-align:right;"><?= e(number_format($mk['accuracy'] * 100, 2, ',', '.')) ?>%</td>
                <td style="text-align:right;"><?= e(number_format($mk['precision'] * 100, 2, ',', '.')) ?>%</td>
                <td style="text-align:right;"><?= e(number_format($mk['recall'] * 100, 2, ',', '.')) ?>%</td>
                <td style="text-align:right;"><?= e(number_format($mk['f1'] * 100, 2, ',', '.')) ?>%</td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card">
        <div class="card-title">Confusion Matrix (K = <?= $k_pilihan ?>)</div>
        <div class="cm-wrap">
          <div class="cm-corner"></div>
          <div class="cm-header">Pred. Naik</div>
          <div class="cm-header">Pred. Turun</div>

          <div class="cm-row-label">Aktual Naik</div>
          <div class="cm-cell cm-tp"><?= (int) $confusion_k['tp'] ?></div>
          <div class="cm-cell cm-fn"><?= (int) $confusion_k['fn'] ?></div>

          <div class="cm-row-label">Aktual Turun</div>
          <div class="cm-cell cm-fp"><?= (int) $confusion_k['fp'] ?></div>
          <div class="cm-cell cm-tn"><?= (int) $confusion_k['tn'] ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- ============ CONTOH TETANGGA TERDEKAT ============ -->
  <?php if ($contoh): ?>
    <div class="card">
      <div class="card-title" style="margin-bottom:12px;">
        Contoh Prediksi Data Uji No. <?= (int) $contoh['no'] ?>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <div class="info-box">
            <div class="info-label">Produk</div>
            <div style="font-weight:600; font-size:14px; margin-top:4px;">
              <?= e($contoh['produk']) ?>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="info-box">
            <div class="info-label">X₁ (Jumlah)</div>
            <div style="font-weight:700; color:var(--indigo); font-size:18px; margin-top:4px;">
              <?= number_format((int) $contoh['x1'], 0, ',', '.') ?>
            </div>
            <div style="font-size:10px; color:#94A3B8;">
              scaled: <?= e(number_format($contoh['x1_scaled'], 4, ',', '.')) ?>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="info-box">
            <div class="info-label">X₂ (Harga)</div>
            <div style="font-weight:700; color:var(--indigo); font-size:18px; margin-top:4px;">
              <?= e(format_rupiah($contoh['x2'])) ?>
            </div>
            <div style="font-size:10px; color:#94A3B8;">
              scaled: <?= e(number_format($contoh['x2_scaled'], 4, ',', '.')) ?>
            </div>
          </div>
        </div>
      </div>

      <div style="font-size:11px; color:var(--text-muted); margin-bottom:8px;">
        <strong>Jarak Manhattan</strong> dihitung pada data yang dinormalisasi min–max dari data latih.
        Nilai K = <?= (int) $contoh['k'] ?>.
      </div>

      <table class="table-clean">
        <thead>
          <tr>
            <th>URUTAN</th>
            <th>NO</th>
            <th>NAMA PRODUK</th>
            <th style="text-align:right;">JARAK MANHATTAN</th>
            <th>LABEL</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($contoh['tetangga'] as $t): ?>
            <tr>
              <td><?= (int) $t['urutan'] ?></td>
              <td><?= (int) $t['no'] ?></td>
              <td><?= e($t['produk']) ?></td>
              <td style="text-align:right; font-family:monospace;">
                <?= e(number_format($t['jarak'], 4, ',', '.')) ?>
              </td>
              <td>
                <?php if ($t['label'] === 'Naik'): ?>
                  <span class="badge-tren badge-naik">Naik</span>
                <?php else: ?>
                  <span class="badge-tren badge-turun">Turun</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <!-- Ringkasan Voting -->
      <div class="voting-box">
        <div class="voting-item">
          <div class="voting-label">Hasil prediksi</div>
          <div style="margin-top:4px;">
            <?php if ($contoh['prediksi'] === 'Naik'): ?>
              <span class="badge-tren badge-naik">Naik</span>
            <?php else: ?>
              <span class="badge-tren badge-turun">Turun</span>
            <?php endif; ?>
          </div>
        </div>
        <div class="voting-item">
          <div class="voting-label">Suara</div>
          <div style="font-weight:700; font-size:16px; margin-top:4px;">
            <?= (int) $contoh['suara']['naik'] ?> Naik
            ·
            <?= (int) $contoh['suara']['turun'] ?> Turun
          </div>
        </div>
        <div class="voting-item">
          <div class="voting-label">Data aktual</div>
          <div style="margin-top:4px;">
            <?php if ($contoh['aktual'] === 'Naik'): ?>
              <span class="badge-tren badge-naik">Naik</span>
            <?php else: ?>
              <span class="badge-tren badge-turun">Turun</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>
<?php else: ?>
  <div class="card">
    <div class="alert alert-info mb-0">
      Pilih skenario dan nilai K, lalu klik <strong>Jalankan Prediksi KNN</strong> untuk melihat hasil.
    </div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../src/layout/bottom.php'; ?>