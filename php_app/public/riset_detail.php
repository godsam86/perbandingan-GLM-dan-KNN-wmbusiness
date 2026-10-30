<?php
/**
 * Halaman Detail Riset — menampilkan JSON lengkap dari satu riset.
 */
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/Db.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/helpers.php';

Auth::requireLogin();
$user = Auth::user();

$id_riset = (int) ($_GET['id'] ?? 0);
if ($id_riset <= 0) {
    header('Location: riset.php');
    exit;
}

$riset = Db::fetchOne(
    "SELECT r.*, u.nama_lengkap AS user_nama
     FROM tb_riset r
     LEFT JOIN users u ON u.id_user = r.created_by
     WHERE r.id_riset = ? LIMIT 1",
    [$id_riset]
);

if (!$riset) {
    header('Location: riset.php');
    exit;
}

$data = null;
if (!empty($riset['data_json'])) {
    $data = json_decode($riset['data_json'], true);
}

$page_title  = 'Detail Riset';
$active_menu = 'riset';
require __DIR__ . '/../src/layout/top.php';
?>

<div class="card mb-3">
  <div class="d-flex justify-content-between align-items-start mb-3">
    <div>
      <div style="font-size:11px; color:var(--text-muted); text-transform:uppercase;">
        Riset #<?= (int) $riset['id_riset'] ?>
      </div>
      <div style="font-size:18px; font-weight:700; color:var(--indigo);">
        <?= e($riset['judul']) ?>
      </div>
      <div style="font-size:12px; color:var(--text-muted); margin-top:4px;">
        <?= e($riset['deskripsi'] ?? '-') ?>
      </div>
    </div>
    <a href="riset.php" class="btn btn-outline">← Kembali</a>
  </div>

  <table class="table-clean">
    <tbody>
      <tr><td style="width:180px;">Tipe</td><td><span class="badge-tren badge-promo"><?= e(strtoupper($riset['tipe_riset'])) ?></span></td></tr>
      <tr><td>Jumlah Data</td><td><?= number_format((int) $riset['jumlah_data'], 0, ',', '.') ?> record</td></tr>
      <tr><td>Status</td><td><?= e($riset['status']) ?></td></tr>
      <tr><td>User</td><td><?= e($riset['user_nama'] ?? '-') ?></td></tr>
      <tr><td>Dibuat</td><td><?= e(date('d-m-Y H:i:s', strtotime($riset['created_at']))) ?></td></tr>
    </tbody>
  </table>
</div>

<?php if ($data): ?>
  <div class="card mb-3">
    <div class="card-title">Ringkasan Data JSON</div>
    <pre style="background:#F8FAFC; padding:16px; border-radius:8px; overflow-x:auto; font-size:11px; line-height:1.5; max-height:500px;">
<?= e(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?>
    </pre>
  </div>

  <?php if (!empty($data['glm']['metrik']) || !empty($data['knn']['per_k'])): ?>
    <div class="card mb-3">
      <div class="card-title">Metrik Evaluasi</div>

      <?php if (!empty($data['glm']['metrik'])): ?>
        <?php $m = $data['glm']['metrik']; ?>
        <div style="margin-bottom:16px;">
          <div style="font-size:13px; font-weight:600; margin-bottom:8px;">GLM</div>
          <table class="table-clean">
            <tbody>
              <tr><td style="width:180px;">Accuracy</td><td><?= number_format($m['accuracy'] * 100, 2, ',', '.') ?>%</td></tr>
              <tr><td>Precision</td><td><?= number_format($m['precision'] * 100, 2, ',', '.') ?>%</td></tr>
              <tr><td>Recall</td><td><?= number_format($m['recall'] * 100, 2, ',', '.') ?>%</td></tr>
              <tr><td>F1-Score</td><td><?= number_format($m['f1'] * 100, 2, ',', '.') ?>%</td></tr>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <?php if (!empty($data['knn']['per_k'])): ?>
        <div>
          <div style="font-size:13px; font-weight:600; margin-bottom:8px;">KNN</div>
          <table class="table-clean">
            <thead>
              <tr><th>K</th><th>Accuracy</th><th>Precision</th><th>Recall</th><th>F1</th></tr>
            </thead>
            <tbody>
              <?php foreach ($data['knn']['per_k'] as $k => $v): ?>
                <tr>
                  <td>K = <?= e($k) ?></td>
                  <td><?= number_format($v['metrik']['accuracy'] * 100, 2, ',', '.') ?>%</td>
                  <td><?= number_format($v['metrik']['precision'] * 100, 2, ',', '.') ?>%</td>
                  <td><?= number_format($v['metrik']['recall'] * 100, 2, ',', '.') ?>%</td>
                  <td><?= number_format($v['metrik']['f1'] * 100, 2, ',', '.') ?>%</td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../src/layout/bottom.php'; ?>