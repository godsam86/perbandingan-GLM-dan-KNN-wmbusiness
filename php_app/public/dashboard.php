<?php
/**
 * Dashboard (Gambar 4.6 & 5.2)
 * - 4 kartu statistik
 * - Grafik tren penjualan (line chart)
 * - Donut chart distribusi tren (Naik/Turun)
 * - Tabel ringkasan F1 3 skenario (GLM vs KNN)
 * - Kartu "Metode Terbaik" (netral jika data kosong)
 */
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/Db.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/MlClient.php';
require_once __DIR__ . '/../src/helpers.php';

Auth::requireLogin();
$user = Auth::user();

// ============================================================
// 1. AMBIL DATA DARI DATABASE
// ============================================================
$total_transaksi_raw = (int) Db::fetchScalar('SELECT COUNT(*) FROM tb_transaksi');
$total_produk        = (int) Db::fetchScalar('SELECT COUNT(*) FROM tb_produk');

$total_produk_pakai  = (int) Db::fetchScalar(
    "SELECT COUNT(DISTINCT p.id_produk)
     FROM tb_produk p
     JOIN tb_transaksi t ON t.id_produk = p.id_produk
     WHERE t.label_tren IS NOT NULL"
);

$total_transaksi_labeled = (int) Db::fetchScalar(
    "SELECT COUNT(*) FROM tb_transaksi WHERE label_tren IS NOT NULL"
);

$periode = Db::fetchOne(
    'SELECT MIN(tanggal_transaksi) AS tgl_min, MAX(tanggal_transaksi) AS tgl_max FROM tb_transaksi'
);
$periode_min = $periode['tgl_min'] ?? null;
$periode_max = $periode['tgl_max'] ?? null;

$n_naik  = (int) Db::fetchScalar("SELECT COUNT(*) FROM tb_transaksi WHERE label_tren = 'Naik'");
$n_turun = (int) Db::fetchScalar("SELECT COUNT(*) FROM tb_transaksi WHERE label_tren = 'Turun'");

$monthly_rows = Db::fetchAll(
    "SELECT DATE_FORMAT(tanggal_transaksi, '%b %y') AS bulan,
            DATE_FORMAT(tanggal_transaksi, '%Y-%m') AS bulan_sort,
            SUM(jumlah_terjual) AS total
     FROM tb_transaksi
     WHERE label_tren IS NOT NULL
     GROUP BY bulan_sort, bulan
     ORDER BY bulan_sort ASC"
);
$monthly_labels = array_column($monthly_rows, 'bulan');
$monthly_values = array_map('intval', array_column($monthly_rows, 'total'));

// ============================================================
// 2. RINGKASAN F1 3 SKENARIO (opsional, hanya jika ada cukup data)
// ============================================================
$skenario_data = [
    'semua' => ['glm' => null, 'knn' => null, 'knn_k' => null, 'best' => null, 'best_f1' => null],
    'latih' => ['glm' => null, 'knn' => null, 'knn_k' => null, 'best' => null, 'best_f1' => null],
    'uji'   => ['glm' => null, 'knn' => null, 'knn_k' => null, 'best' => null, 'best_f1' => null],
];
$model_terbaik_global = null;
$f1_terbaik_global    = null;
$n_skenario_unggul    = 0;

if ($total_transaksi_labeled >= 5) {
    $cache_key      = 'dashboard_summary_cache';
    $cache_time_key = 'dashboard_summary_cache_time';

    $summary = null;
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

        $client  = new MlClient();
        $summary = $client->summary($rows);

        if (!empty($summary['ok'])) {
            $_SESSION[$cache_key]      = $summary;
            $_SESSION[$cache_time_key] = time();
        } else {
            $_SESSION['ml_down'] = true;
            $summary = null;
        }
    }

    if ($summary && !empty($summary['skenario'])) {
        $sk = $summary['skenario'];
        foreach (['semua', 'latih', 'uji'] as $key) {
            if (isset($sk[$key])) {
                $skenario_data[$key] = [
                    'glm'     => $sk[$key]['glm']['f1'] ?? null,
                    'knn'     => $sk[$key]['knn']['f1'] ?? null,
                    'knn_k'   => $sk[$key]['knn_k_terbaik'] ?? null,
                    'best'    => $sk[$key]['model_terbaik'] ?? null,
                    'best_f1' => $sk[$key]['f1_terbaik'] ?? null,
                ];
            }
        }
        // Model terbaik GLOBAL — dari skenario uji (out-of-sample)
        $model_terbaik_global = $summary['model_terbaik_global'] ?? null;
        $f1_terbaik_global    = $summary['f1_terbaik_global'] ?? null;

        foreach ($skenario_data as $s) {
            if ($s['best'] === 'GLM') $n_skenario_unggul++;
        }
    }
}

// ============================================================
// 3. RENDER
// ============================================================
$page_title  = 'Dashboard';
$active_menu = 'dashboard';
require __DIR__ . '/../src/layout/top.php';
?>

<!-- ============ BARIS 1: 4 KARTU STATISTIK ============ -->
<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3">
    <div class="metric-card">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="metric-label">Total Transaksi</div>
          <div class="metric-value"><?= number_format($total_transaksi_raw, 0, ',', '.') ?></div>
          <div class="metric-sub">record mentah</div>
        </div>
        <div class="metric-icon">🛒</div>
      </div>
    </div>
  </div>

  <div class="col-6 col-lg-3">
    <div class="metric-card">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="metric-label">Total Produk</div>
          <div class="metric-value"><?= number_format($total_produk, 0, ',', '.') ?></div>
          <div class="metric-sub">
            <?= $total_produk_pakai > 0
                ? $total_produk_pakai . ' produk dipakai pemodelan'
                : 'produk terdaftar' ?>
          </div>
        </div>
        <div class="metric-icon">📦</div>
      </div>
    </div>
  </div>

  <div class="col-6 col-lg-3">
    <div class="metric-card">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="metric-label">Periode Data</div>
          <div class="metric-value" style="font-size:16px;">
            <?php if ($periode_min && $periode_max): ?>
              <?= e(date('M Y', strtotime($periode_min))) ?>–<?= e(date('M Y', strtotime($periode_max))) ?>
            <?php else: ?>
              Belum ada data
            <?php endif; ?>
          </div>
          <div class="metric-sub">data historis penjualan</div>
        </div>
        <div class="metric-icon">📅</div>
      </div>
    </div>
  </div>

  <div class="col-6 col-lg-3">
    <div class="metric-card">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="metric-label">Data Setelah Preprocessing</div>
          <div class="metric-value"><?= number_format($total_transaksi_labeled, 0, ',', '.') ?></div>
          <div class="metric-sub">siap untuk pemodelan</div>
        </div>
        <div class="metric-icon">🗄</div>
      </div>
    </div>
  </div>
</div>

<!-- ============ BARIS 2: GRAFIK TREN + DISTRIBUSI ============ -->
<div class="row g-3 mb-3">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-title" style="margin-bottom:2px;">Grafik Tren Penjualan</div>
      <div style="font-size:11px; color:var(--text-muted); margin-bottom:12px;">
        Total jumlah terjual per bulan
      </div>
      <div style="position:relative; height:260px;">
        <canvas id="chartTren"></canvas>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card">
      <div class="card-title" style="margin-bottom:2px;">Distribusi Tren</div>
      <div style="font-size:11px; color:var(--text-muted); margin-bottom:12px;">
        <?= number_format($total_transaksi_labeled, 0, ',', '.') ?> data berlabel
      </div>
      <div style="position:relative; height:180px;">
        <canvas id="chartDistribusi"></canvas>
        <div class="donut-center">
          <div class="donut-center-value"><?= number_format($total_transaksi_labeled, 0, ',', '.') ?></div>
          <div class="donut-center-label">data</div>
        </div>
      </div>
      <div class="distribusi-legend mt-3">
        <div class="legend-row">
          <span class="legend-dot" style="background:#22C55E;"></span>
          <span class="legend-name">Naik</span>
          <span class="legend-value"><?= number_format($n_naik, 0, ',', '.') ?>
            (<?= $total_transaksi_labeled > 0 ? number_format($n_naik / $total_transaksi_labeled * 100, 1, ',', '.') : '0' ?>%)</span>
        </div>
        <div class="legend-row">
          <span class="legend-dot" style="background:#EF4444;"></span>
          <span class="legend-name">Turun</span>
          <span class="legend-value"><?= number_format($n_turun, 0, ',', '.') ?>
            (<?= $total_transaksi_labeled > 0 ? number_format($n_turun / $total_transaksi_labeled * 100, 1, ',', '.') : '0' ?>%)</span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ============ BARIS 3: RINGKASAN F1 + METODE TERBAIK ============ -->
<div class="row g-3 mb-3">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-title" style="margin-bottom:2px;">Ringkasan Hasil Prediksi Tren</div>
      <div style="font-size:11px; color:var(--text-muted); margin-bottom:12px;">
        F1-Score GLM dibandingkan KNN pada tiga skenario data
      </div>

      <?php if ($total_transaksi_labeled < 5): ?>
        <div class="alert alert-info mb-0">
          Belum cukup data untuk menampilkan ringkasan. Unggah dataset dan jalankan praproses terlebih dahulu.
        </div>
      <?php else: ?>
        <div style="overflow-x:auto;">
          <table class="table-clean">
            <thead>
              <tr>
                <th>SKENARIO</th>
                <th>GLM</th>
                <th>KNN TERBAIK</th>
                <th>MODEL TERBAIK</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (['semua' => '800 data', 'latih' => '640 data', 'uji' => '160 data'] as $key => $label): ?>
                <?php $s = $skenario_data[$key]; ?>
                <tr>
                  <td><?= e($label) ?></td>
                  <td><?= $s['glm'] !== null ? e(number_format($s['glm'] * 100, 2, ',', '.') . '%') : '—' ?></td>
                  <td>
                    <?php if ($s['knn'] !== null): ?>
                      <?= e(number_format($s['knn'] * 100, 2, ',', '.') . '%') ?>
                      <span style="color:#94A3B8;">(K=<?= (int) $s['knn_k'] ?>)</span>
                    <?php else: ?>—<?php endif; ?>
                  </td>
                  <td>
                    <?php if ($s['best']): ?>
                      <span class="badge-tren badge-promo"><?= e($s['best']) ?></span>
                    <?php else: ?>—<?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- METODE TERBAIK — netral saat kosong -->
  <div class="col-lg-4">
    <div class="card text-center" style="padding:24px 18px;">
      <div class="card-title" style="text-align:left; margin-bottom:6px;">Metode Terbaik</div>
      <div class="trophy-big">🏆</div>

      <?php if ($model_terbaik_global !== null): ?>
        <div style="font-size:22px; font-weight:700; color:var(--indigo); margin-top:6px;">
          <?= e($model_terbaik_global) ?>
        </div>
        <div style="font-size:12px; color:var(--text-muted); margin-top:6px;">
          <?php if ($f1_terbaik_global !== null): ?>
            F1-Score <?= e(number_format($f1_terbaik_global * 100, 2, ',', '.')) ?>%
          <?php endif; ?>
        </div>
        <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">
          pada skenario uji (out-of-sample)
        </div>
      <?php else: ?>
        <div style="font-size:22px; font-weight:700; color:#94A3B8; margin-top:6px;">
          —
        </div>
        <div style="font-size:12px; color:var(--text-muted); margin-top:6px;">
          Belum ada data
        </div>
        <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">
          Unggah dataset terlebih dahulu
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ============ CHART JS ============ -->
<script>
document.addEventListener('DOMContentLoaded', function () {

  // ----- Line chart tren penjualan -----
  const ctxTren = document.getElementById('chartTren');
  if (ctxTren) {
    new Chart(ctxTren, {
      type: 'line',
      data: {
        labels: <?= json_encode($monthly_labels) ?>,
        datasets: [{
          data: <?= json_encode($monthly_values) ?>,
          borderColor: '#4F46E5',
          backgroundColor: 'rgba(79,70,229,0.08)',
          borderWidth: 2,
          tension: 0.35,
          fill: true,
          pointRadius: 3,
          pointBackgroundColor: '#4F46E5',
          pointBorderColor: '#fff',
          pointBorderWidth: 2,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function (ctx) {
                return 'Total: ' + new Intl.NumberFormat('id-ID').format(ctx.parsed.y);
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: { color: '#F1F5F9' },
            ticks: {
              callback: function (v) { return new Intl.NumberFormat('id-ID').format(v); }
            }
          },
          x: { grid: { display: false } }
        }
      }
    });
  }

  // ----- Donut chart distribusi -----
  const ctxDist = document.getElementById('chartDistribusi');
  if (ctxDist) {
    const nNaik  = <?= (int) $n_naik ?>;
    const nTurun = <?= (int) $n_turun ?>;

    // Kalau data kosong, tampilkan placeholder abu
    const isEmpty = (nNaik + nTurun) === 0;
    const dataDist    = isEmpty ? [1] : [nNaik, nTurun];
    const colorsDist  = isEmpty ? ['#E5E7EB'] : ['#22C55E', '#EF4444'];
    const labelsDist  = isEmpty ? ['Belum ada data'] : ['Naik', 'Turun'];

    new Chart(ctxDist, {
      type: 'doughnut',
      data: {
        labels: labelsDist,
        datasets: [{
          data: dataDist,
          backgroundColor: colorsDist,
          borderWidth: 0,
          hoverOffset: isEmpty ? 0 : 6,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '72%',
        plugins: {
          legend: { display: false },
          tooltip: {
            enabled: !isEmpty,
            callbacks: {
              label: function (ctx) {
                const total = nNaik + nTurun;
                const pct = total > 0 ? (ctx.parsed / total * 100).toFixed(1) : 0;
                return ctx.label + ': ' + new Intl.NumberFormat('id-ID').format(ctx.parsed) + ' (' + pct + '%)';
              }
            }
          }
        }
      }
    });
  }

});
</script>

<?php require __DIR__ . '/../src/layout/bottom.php'; ?>