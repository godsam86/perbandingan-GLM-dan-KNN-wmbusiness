<?php
/**
 * Halaman Riwayat Riset — daftar semua proses yang tersimpan di tb_riset.
 * Setiap praproses, GLM, dan KNN yang dijalankan akan muncul di sini.
 */
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/Db.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/helpers.php';

Auth::requireLogin();
$user = Auth::user();

$error   = '';
$success = '';
$info    = '';

// ============================================================
// PROSES POST — Hapus riset
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Sesi tidak valid. Muat ulang halaman.';
    } else {
        $action = $_POST['action'] ?? '';

        // Hapus satu riset
        if ($action === 'delete_one') {
            $id_riset = (int) ($_POST['id_riset'] ?? 0);
            if ($id_riset > 0) {
                Db::execute('DELETE FROM tb_riset WHERE id_riset = ?', [$id_riset]);
                log_activity('Hapus Riset', 'id_riset=' . $id_riset);
                $success = 'Riset berhasil dihapus.';
            } else {
                $error = 'ID riset tidak valid.';
            }
        }

        // Hapus semua riset
        elseif ($action === 'delete_all') {
            $n = Db::execute('DELETE FROM tb_riset');
            log_activity('Hapus Semua Riset', 'Total: ' . $n);
            $success = 'Berhasil menghapus ' . $n . ' riwayat riset.';
        }
    }
}

// ============================================================
// FILTER
// ============================================================
$filter_tipe = $_GET['tipe'] ?? 'semua';
$valid_tipe = ['semua', 'preprocess', 'glm_semua', 'glm_latih', 'glm_uji',
               'knn_semua', 'knn_latih', 'knn_uji'];

if (!in_array($filter_tipe, $valid_tipe, true)) {
    $filter_tipe = 'semua';
}

// Ambil data
if ($filter_tipe === 'semua') {
    $riset_list = Db::fetchAll(
        "SELECT r.*, u.nama_lengkap AS user_nama
         FROM tb_riset r
         LEFT JOIN users u ON u.id_user = r.created_by
         ORDER BY r.created_at DESC
         LIMIT 100"
    );
} else {
    $riset_list = Db::fetchAll(
        "SELECT r.*, u.nama_lengkap AS user_nama
         FROM tb_riset r
         LEFT JOIN users u ON u.id_user = r.created_by
         WHERE r.tipe_riset = ?
         ORDER BY r.created_at DESC
         LIMIT 100"
    , [$filter_tipe]);
}

// Statistik
$total_riset    = (int) Db::fetchScalar('SELECT COUNT(*) FROM tb_riset');
$total_preprocess = (int) Db::fetchScalar("SELECT COUNT(*) FROM tb_riset WHERE tipe_riset = 'preprocess'");
$total_glm      = (int) Db::fetchScalar("SELECT COUNT(*) FROM tb_riset WHERE tipe_riset LIKE 'glm_%'");
$total_knn      = (int) Db::fetchScalar("SELECT COUNT(*) FROM tb_riset WHERE tipe_riset LIKE 'knn_%'");

// Render
$page_title  = 'Riwayat Riset';
$active_menu = 'riset';
require __DIR__ . '/../src/layout/top.php';
?>

<?php if ($error): ?><div class="alert alert-danger mb-3"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success mb-3"><?= e($success) ?></div><?php endif; ?>
<?php if ($info): ?><div class="alert alert-info mb-3"><?= e($info) ?></div><?php endif; ?>

<!-- ============ HEADER + TOMBOL HAPUS SEMUA ============ -->
<div class="card mb-3" style="padding:14px 20px;">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
      <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase; letter-spacing:.04em;">
        Pusat Riwayat
      </div>
      <div style="font-size:18px; font-weight:700; color:var(--indigo);">
        Riwayat Riset
      </div>
      <div style="font-size:11px; color:var(--text-muted); margin-top:2px;">
        Semua proses praproses, GLM, dan KNN yang pernah dijalankan
      </div>
    </div>
    <?php if ($total_riset > 0): ?>
      <form method="post" onsubmit="return confirm('Hapus SEMUA riwayat riset? Tindakan ini tidak bisa dibatalkan.');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_all">
        <button type="submit" class="btn btn-outline" style="color:#B91C1C; border-color:#FECACA;">
          🗑 Hapus Semua
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>

<!-- ============ 4 KARTU STATISTIK ============ -->
<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3">
    <div class="metric-card">
      <div class="metric-label">Total Riset</div>
      <div class="metric-value"><?= number_format($total_riset, 0, ',', '.') ?></div>
      <div class="metric-sub">semua riwayat</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="metric-card">
      <div class="metric-label">Praproses</div>
      <div class="metric-value" style="color:#0EA5E9;">
        <?= number_format($total_preprocess, 0, ',', '.') ?>
      </div>
      <div class="metric-sub">riwayat</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="metric-card">
      <div class="metric-label">GLM</div>
      <div class="metric-value" style="color:var(--indigo);">
        <?= number_format($total_glm, 0, ',', '.') ?>
      </div>
      <div class="metric-sub">riwayat</div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="metric-card">
      <div class="metric-label">KNN</div>
      <div class="metric-value" style="color:#15803D;">
        <?= number_format($total_knn, 0, ',', '.') ?>
      </div>
      <div class="metric-sub">riwayat</div>
    </div>
  </div>
</div>

<!-- ============ FILTER TAB ============ -->
<div class="card mb-3">
  <div class="card-title" style="margin-bottom:12px;">Filter Tipe Riset</div>
  <div class="tabs">
    <?php
      $filter_labels = [
        'semua'      => 'Semua (' . $total_riset . ')',
        'preprocess' => 'Praproses (' . $total_preprocess . ')',
        'glm_semua'  => 'GLM Semua',
        'glm_latih'  => 'GLM Latih',
        'glm_uji'    => 'GLM Uji',
        'knn_semua'  => 'KNN Semua',
        'knn_latih'  => 'KNN Latih',
        'knn_uji'    => 'KNN Uji',
      ];
    ?>
    <?php foreach ($filter_labels as $key => $label): ?>
      <a href="?tipe=<?= e($key) ?>"
         class="tab-item <?= $filter_tipe === $key ? 'active' : '' ?>">
        <?= e($label) ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- ============ TABEL RIWAYAT ============ -->
<div class="card">
  <div class="card-title" style="margin-bottom:12px;">
    Daftar Riwayat <?= $total_riset > 100 ? '(100 terbaru)' : '' ?>
  </div>

  <?php if (empty($riset_list)): ?>
    <div class="alert alert-info mb-0">
      Belum ada riwayat riset. Jalankan <strong>Praproses Data</strong>, <strong>Prediksi GLM</strong>,
      atau <strong>Prediksi KNN</strong> untuk mulai mencatat riwayat.
    </div>
  <?php else: ?>
    <div style="overflow-x:auto;">
      <table class="table-clean">
        <thead>
          <tr>
            <th style="width:40px;">NO</th>
            <th style="width:120px;">TANGGAL</th>
            <th>TIPE RISET</th>
            <th>JUDUL</th>
            <th style="text-align:right;">JUMLAH DATA</th>
            <th>STATUS</th>
            <th>USER</th>
            <th style="width:150px; text-align:right;">AKSI</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($riset_list as $i => $r): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><?= e(date('d-m-Y H:i', strtotime($r['created_at']))) ?></td>
              <td>
                <?php
                  $tipe = $r['tipe_riset'];
                  $badge = 'badge-promo';
                  if (strpos($tipe, 'preprocess') !== false) $badge = 'badge-promo';
                  elseif (strpos($tipe, 'glm') !== false) $badge = 'badge-naik';
                  elseif (strpos($tipe, 'knn') !== false) $badge = 'badge-tahan';
                ?>
                <span class="badge-tren <?= e($badge) ?>">
                  <?= e(strtoupper($tipe)) ?>
                </span>
              </td>
              <td><?= e($r['judul']) ?></td>
              <td style="text-align:right;">
                <?= number_format((int) $r['jumlah_data'], 0, ',', '.') ?>
              </td>
              <td>
                <?php if ($r['status'] === 'completed'): ?>
                  <span style="color:#15803D; font-weight:600;">✓ Selesai</span>
                <?php elseif ($r['status'] === 'processing'): ?>
                  <span style="color:#B45309; font-weight:600;">⏳ Proses</span>
                <?php else: ?>
                  <span style="color:#B91C1C; font-weight:600;">✗ Gagal</span>
                <?php endif; ?>
              </td>
              <td><?= e($r['user_nama'] ?? '-') ?></td>
              <td style="text-align:right;">
                <a href="riset_detail.php?id=<?= (int) $r['id_riset'] ?>"
                   class="btn btn-outline"
                   style="padding:4px 10px; font-size:11px;">
                  Lihat
                </a>
                <form method="post" style="display:inline;"
                      onsubmit="return confirm('Hapus riset ini?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete_one">
                  <input type="hidden" name="id_riset" value="<?= (int) $r['id_riset'] ?>">
                  <button type="submit" class="btn btn-outline"
                          style="padding:4px 10px; font-size:11px; color:#B91C1C;">
                    Hapus
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../src/layout/bottom.php'; ?>