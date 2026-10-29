<?php
/**
 * Halaman Perbandingan Model (Gambar 5.7)
 */
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/Db.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/MlClient.php';
require_once __DIR__ . '/../src/helpers.php';

Auth::requireLogin();
$user = Auth::user();

$error = '';

// Skenario aktif
$skenario_aktif = $_GET['skenario'] ?? 'semua';
if (!in_array($skenario_aktif, ['semua', 'latih', 'uji'], true)) {
    $skenario_aktif = 'semua';
}

// ============================================================
// AMBIL HASIL SUMMARY DARI PYTHON
// ============================================================
$summary = null;
$total_labeled = (int) Db::fetchScalar("SELECT COUNT(*) FROM tb_transaksi WHERE label_tren IS NOT NULL");

if ($total_labeled >= 5) {
    $cache_key = 'summary_cache';
    $cache_time_key = 'summary_cache_time';

    if (!empty($_SESSION[$cache_key]) && !empty($_SESSION[$cache_time_key])
        && (time() - $_SESSION[$cache_time_key]) < 300) {
        $summary = $_SESSION[$cache_key];
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
        $summary = $client->summary($rows);

        if (!empty($summary['ok'])) {
            $_SESSION[$cache_key] = $summary;
            $_SESSION[$cache_time_key] = time();
        } else {
            $error = $summary['error'] ?? 'Gagal mengambil ringkasan dari ML service.';
            if (strpos($error, 'ML') !== false) {
                $_SESSION['ml_down'] = true;
            }
            $summary = null;
        }
    }
}

// Label skenario aktual
$n_latih_aktual = (int) round($total_labeled * 0.8);
$n_uji_aktual = $total_labeled - $n_latih_aktual;

$skenario_label = [
    'semua' => $total_labeled . ' data',
    'latih' => $n_latih_aktual . ' data',
    'uji'   => $n_uji_aktual . ' data',
];

// Data skenario aktif
$sk = null;
if ($summary && !empty($summary['skenario'][$skenario_aktif])) {
    $sk = $summary['skenario'][$skenario_aktif];
}

// Render
$page_title  = 'Perbandingan Model';
$active_menu = 'perbandingan';
require __DIR__ . '/../src/layout/top.php';
?>

<?php if ($error): ?><div class="alert alert-danger mb-3"><?= e($error) ?></div><?php endif; ?>

<!-- ============ TAB SKENARIO ============ -->
<div class="card mb-3">
  <div class="card-title" style="margin-bottom:12px;">Pilih Skenario Data</div>
  <div class="tabs">
    <?php foreach ($skenario_label as $key => $lbl): ?>
      <a href="?skenario=<?= e($key) ?>"
         class="tab-item <?= $skenario_aktif === $key ? 'active' : '' ?>">
        <?= e($lbl) ?>
      </a>
    <?php endforeach; ?>
  </div>
  <?php if ($skenario_aktif !== 'uji'): ?>
    <div class="alert alert-info mt-3 mb-0" style="font-size:11px; padding:8px 12px;">
      <strong>Catatan:</strong> Skenario "<?= e($skenario_label[$skenario_aktif]) ?>"
      adalah evaluasi <em>in-sample</em> (data latih dipakai juga untuk uji).
      Untuk perbandingan yang adil, gunakan skenario <strong><?= e($skenario_label['uji']) ?> (uji)</strong>.
    </div>
  <?php endif; ?>
</div>

<?php if ($sk): ?>
  <?php
    $glm = $sk['glm'];
    $knn = $sk['knn'];
    $knn_k = $sk['knn_k_terbaik'];
    $best = $sk['model_terbaik'];
  ?>

  <!-- ============ GRAFIK + TABEL METRIK ============ -->
  <div class="row g-3 mb-3">
    <div class="col-lg-7">
      <div class="card">
        <div class="card-title" style="margin-bottom:2px;">Perbandingan Metrik</div>
        <div style="font-size:11px; color:var(--text-muted); margin-bottom:12px;">
          Skenario <?= e($skenario_label[$skenario_aktif]) ?>
        </div>
        <div style="position:relative; height:280px;">
          <canvas id="chartPerbandingan"></canvas>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card">
        <div class="card-title" style="margin-bottom:2px;">Tabel Metrik</div>
        <div style="font-size:11px; color:var(--text-muted); margin-bottom:12px;">
          Skenario <?= e($skenario_label[$skenario_aktif]) ?>
        </div>
        <table class="table-clean">
          <thead>
            <tr>
              <th>METRIK</th>
              <th style="text-align:right;">GLM</th>
              <th style="text-align:right;">KNN (K=<?= (int) $knn_k ?>)</th>
            </tr>
          </thead>
          <tbody>
            <?php
              $metrics = [
                  'accuracy'  => 'Accuracy',
                  'precision' => 'Precision',
                  'recall'    => 'Recall',
                  'f1'        => 'F1-Score',
              ];
              foreach ($metrics as $key => $label):
                $glm_val = $glm[$key] * 100;
                $knn_val = $knn[$key] * 100;
                $glm_bold = $glm_val > $knn_val;
                $knn_bold = $knn_val > $glm_val;
            ?>
              <tr>
                <td><?= e($label) ?></td>
                <td style="text-align:right; <?= $glm_bold ? 'font-weight:700; color:var(--indigo);' : '' ?>">
                  <?= e(number_format($glm_val, 2, ',', '.')) ?>%
                </td>
                <td style="text-align:right; <?= $knn_bold ? 'font-weight:700; color:#0EA5E9;' : '' ?>">
                  <?= e(number_format($knn_val, 2, ',', '.')) ?>%
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div style="font-size:11px; color:var(--text-muted); margin-top:10px;">
          Cetak tebal menandai nilai tertinggi pada metrik tersebut.
        </div>
      </div>
    </div>
  </div>

  <!-- ============ RINGKASAN SEMUA SKENARIO + KESIMPULAN ============ -->
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-title">Ringkasan F1-Score Semua Skenario</div>
        <table class="table-clean">
          <thead>
            <tr>
              <th>SKENARIO</th>
              <th style="text-align:right;">GLM</th>
              <th style="text-align:right;">KNN TERBAIK</th>
              <th>MODEL TERBAIK</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (['semua', 'latih', 'uji'] as $sk_key): ?>
              <?php
                $s = $summary['skenario'][$sk_key] ?? null;
                if (!$s) continue;
              ?>
              <tr class="<?= $skenario_aktif === $sk_key ? 'row-highlight' : '' ?>">
                <td><?= e($skenario_label[$sk_key]) ?></td>
                <td style="text-align:right;">
                  <?= e(number_format($s['glm']['f1'] * 100, 2, ',', '.')) ?>%
                </td>
                <td style="text-align:right;">
                  <?= e(number_format($s['knn']['f1'] * 100, 2, ',', '.')) ?>%
                  <span style="color:#94A3B8;">(K=<?= (int) $s['knn_k_terbaik'] ?>)</span>
                </td>
                <td>
                  <?php if ($s['model_terbaik']): ?>
                    <span class="badge-tren badge-promo"><?= e($s['model_terbaik']) ?></span>
                  <?php else: ?>
                    —
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="col-lg-4">
      <?php
        // Model terbaik global — berdasarkan SKENARIO UJI (out-of-sample)
        $model_unggul_global = $summary['model_terbaik_global'] ?? 'GLM';
        $f1_global = $summary['f1_terbaik_global'] ?? null;

        // Info jumlah skenario menang (untuk keterangan tambahan)
        $n_glm_unggul = (int) ($summary['n_glm_unggul'] ?? 0);
        $n_knn_unggul = (int) ($summary['n_knn_unggul'] ?? 0);
        $n_total = $n_glm_unggul + $n_knn_unggul;
      ?>
      <div class="card text-center" style="padding:24px 18px;">
        <div class="card-title" style="text-align:left;">Kesimpulan</div>
        <div class="trophy-big">🏆</div>
        <div style="font-size:22px; font-weight:700; color:var(--indigo); margin-top:6px;">
          <?= e($model_unggul_global) ?>
        </div>
        <div style="font-size:12px; color:var(--text-muted); margin-top:6px;">
          Model terbaik pada <strong>skenario uji</strong>
        </div>
        <?php if ($f1_global !== null): ?>
          <div style="font-size:13px; color:var(--text-dark); margin-top:6px; font-weight:600;">
            F1-Score <?= e(number_format($f1_global * 100, 2, ',', '.')) ?>%
          </div>
        <?php endif; ?>

        <?php if ($n_total > 0): ?>
          <div style="margin-top:14px; padding-top:12px; border-top:1px solid var(--border-soft);
                      font-size:11px; color:var(--text-muted); line-height:1.6;">
            <div><strong>Catatan in-sample:</strong></div>
            <div>GLM menang di <?= (int) $n_glm_unggul ?> skenario</div>
            <div>KNN menang di <?= (int) $n_knn_unggul ?> skenario</div>
            <div style="margin-top:6px; font-style:italic;">
              Perbandingan final tetap mengacu ke skenario uji.
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php elseif ($total_labeled >= 5): ?>
  <div class="card">
    <div class="alert alert-info mb-0">
      Data ringkasan belum tersedia. Pastikan layanan ML aktif.
    </div>
  </div>
<?php else: ?>
  <div class="card">
    <div class="alert alert-info mb-0">
      Belum cukup data untuk perbandingan. Unggah dan praproses dataset terlebih dahulu.
    </div>
  </div>
<?php endif; ?>

<?php if ($sk): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const ctx = document.getElementById('chartPerbandingan');
  if (!ctx) return;

  const glmData = [
    <?= json_encode(round($sk['glm']['accuracy'] * 100, 2)) ?>,
    <?= json_encode(round($sk['glm']['precision'] * 100, 2)) ?>,
    <?= json_encode(round($sk['glm']['recall'] * 100, 2)) ?>,
    <?= json_encode(round($sk['glm']['f1'] * 100, 2)) ?>,
  ];
  const knnData = [
    <?= json_encode(round($sk['knn']['accuracy'] * 100, 2)) ?>,
    <?= json_encode(round($sk['knn']['precision'] * 100, 2)) ?>,
    <?= json_encode(round($sk['knn']['recall'] * 100, 2)) ?>,
    <?= json_encode(round($sk['knn']['f1'] * 100, 2)) ?>,
  ];

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: ['Accuracy', 'Precision', 'Recall', 'F1-Score'],
      datasets: [
        {
          label: 'GLM',
          data: glmData,
          backgroundColor: '#4F46E5',
          borderRadius: 6,
          barPercentage: 0.7,
        },
        {
          label: 'KNN (K=<?= (int) $sk['knn_k_terbaik'] ?>)',
          data: knnData,
          backgroundColor: '#0EA5E9',
          borderRadius: 6,
          barPercentage: 0.7,
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'top', align: 'end' },
        tooltip: {
          callbacks: {
            label: function (ctx) {
              return ctx.dataset.label + ': ' + ctx.parsed.y.toFixed(2).replace('.', ',') + '%';
            }
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          max: 100,
          grid: { color: '#F1F5F9' },
          ticks: {
            callback: function (v) { return v + '%'; }
          }
        },
        x: { grid: { display: false } }
      }
    }
  });
});
</script>
<?php endif; ?>

<?php require __DIR__ . '/../src/layout/bottom.php'; ?>