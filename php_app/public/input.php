<?php
/**
 * Halaman Input Dataset (Gambar 5.3).
 * Saat "Simpan ke Database": HAPUS transaksi + riset lama, lalu insert baru.
 *
 * CATATAN: ALTER TABLE berada di LUAR transaksi karena menyebabkan implicit commit.
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
$info    = '';

$preview          = $_SESSION['parsed_rows'] ?? [];
$preview_filename = $_SESSION['parsed_filename'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Sesi tidak valid. Muat ulang halaman.';
    } else {
        $action = $_POST['action'] ?? '';

        // ---- Aksi 1: Unggah file ----
        if ($action === 'upload') {
            if (empty($_FILES['file']['tmp_name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Tidak ada berkas yang diunggah atau terjadi kesalahan.';
            } else {
                $file = $_FILES['file'];
                $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $size = (int) $file['size'];

                if (!in_array($ext, UPLOAD_ALLOWED_EXT, true)) {
                    $error = 'Format berkas harus CSV atau XLSX.';
                } elseif ($size > UPLOAD_MAX_BYTES) {
                    $error = 'Ukuran berkas melebihi 5 MB.';
                } else {
                    $client = new MlClient();
                    $result = $client->parseUpload($file['tmp_name'], $file['name']);

                    if (empty($result['ok'])) {
                        $error = $result['error'] ?? 'Gagal memproses berkas.';
                        if (strpos($error, 'Layanan ML') !== false) {
                            $_SESSION['ml_down'] = true;
                        }
                    } else {
                        $_SESSION['parsed_rows']     = $result['rows'];
                        $_SESSION['parsed_filename'] = $result['filename'];
                        $preview          = $result['rows'];
                        $preview_filename = $result['filename'];
                        $success = 'Berkas berhasil diproses: '
                                 . number_format($result['n_baris'], 0, ',', '.')
                                 . ' baris siap disimpan.';
                        log_activity('Unggah Dataset',
                            'File: ' . $result['filename'] . ', ' . $result['n_baris'] . ' baris');
                    }
                }
            }
        }

        // ---- Aksi 2: Tambah manual ----
        elseif ($action === 'manual') {
            $tanggal  = trim($_POST['tanggal'] ?? '');
            $produk   = trim($_POST['nama_produk'] ?? '');
            $kategori = trim($_POST['kategori'] ?? '');
            $jumlah   = $_POST['jumlah_terjual'] ?? '';
            $harga    = $_POST['harga'] ?? '';

            if ($tanggal === '' || $produk === '' || $kategori === '' || $jumlah === '' || $harga === '') {
                $error = 'Semua kolom wajib diisi.';
            } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
                $error = 'Format tanggal harus YYYY-MM-DD.';
            } elseif (!is_numeric($jumlah) || (int) $jumlah <= 0) {
                $error = 'Jumlah terjual harus angka positif.';
            } elseif (!is_numeric($harga) || (float) $harga <= 0) {
                $error = 'Harga harus angka positif.';
            } else {
                $preview[] = [
                    'tanggal'        => $tanggal,
                    'id_produk'      => null,
                    'nama_produk'    => $produk,
                    'kategori'       => $kategori,
                    'jumlah_terjual' => (int) $jumlah,
                    'harga'          => (float) $harga,
                    'label_tren'     => null,
                ];
                $_SESSION['parsed_rows'] = $preview;
                $success = 'Data manual ditambahkan ke pratinjau.';
            }
        }

        // ---- Aksi 3: Simpan ke database ----
        elseif ($action === 'save_db') {
            $rows = $_SESSION['parsed_rows'] ?? [];
            if (empty($rows)) {
                $error = 'Tidak ada data untuk disimpan.';
            } else {
                try {
                    // ============================================================
                    // LANGKAH 0: Reset AUTO_INCREMENT — DI LUAR TRANSAKSI
                    // ALTER TABLE menyebabkan implicit commit di MySQL/MariaDB.
                    // Kalau dijalankan di dalam transaksi, transaksi otomatis
                    // di-commit dan PDO::commit() berikutnya akan error:
                    // "There is no active transaction".
                    // ============================================================
                    Db::execute('ALTER TABLE tb_hasil_prediksi AUTO_INCREMENT = 1');
                    Db::execute('ALTER TABLE tb_transaksi AUTO_INCREMENT = 1');
                    Db::execute('ALTER TABLE tb_evaluasi AUTO_INCREMENT = 1');
                    Db::execute('ALTER TABLE tb_koefisien_glm AUTO_INCREMENT = 1');
                    Db::execute('ALTER TABLE tb_riset AUTO_INCREMENT = 1');

                    // ============================================================
                    // LANGKAH 1: Mulai transaksi untuk DELETE + INSERT
                    // ============================================================
                    $pdo = Db::conn();
                    $pdo->beginTransaction();

                    // Hapus data lama
                    Db::execute('DELETE FROM tb_hasil_prediksi');
                    Db::execute('DELETE FROM tb_transaksi');
                    Db::execute('DELETE FROM tb_evaluasi');
                    Db::execute('DELETE FROM tb_koefisien_glm');
                    Db::execute('DELETE FROM tb_riset');

                    // Hapus cache session
                    unset($_SESSION['dashboard_summary_cache']);
                    unset($_SESSION['dashboard_summary_cache_time']);
                    unset($_SESSION['summary_cache']);
                    unset($_SESSION['summary_cache_time']);
                    unset($_SESSION['latest_cache']);
                    unset($_SESSION['latest_cache_time']);
                    unset($_SESSION['glm_result'], $_SESSION['glm_skenario'], $_SESSION['glm_produk']);
                    unset($_SESSION['knn_result'], $_SESSION['knn_skenario'], $_SESSION['knn_k_pilihan']);
                    unset($_SESSION['last_preprocess']);

                    // ============================================================
                    // LANGKAH 2: Insert data baru
                    // ============================================================
                    $n_produk_baru = 0;
                    $n_transaksi   = 0;
                    $produk_by_id  = [];
                    $produk_by_name= [];

                    $existing = Db::fetchAll('SELECT id_produk, nama_produk, kategori, harga FROM tb_produk');
                    foreach ($existing as $p) {
                        $produk_by_id[(int) $p['id_produk']]                 = $p;
                        $produk_by_name[strtolower(trim($p['nama_produk']))] = $p;
                    }

                    foreach ($rows as $row) {
                        $nama     = trim($row['nama_produk']);
                        $harga    = (float) $row['harga'];
                        $jumlah   = (int) $row['jumlah_terjual'];
                        $tanggal  = $row['tanggal'];
                        $id_csv   = isset($row['id_produk']) ? (int) $row['id_produk'] : null;
                        $kategori = trim($row['kategori'] ?? '');
                        $label    = $row['label_tren'] ?? null;
                        if (!in_array($label, ['Naik', 'Turun'], true)) {
                            $label = null;
                        }

                        $produk = null;
                        if ($id_csv && isset($produk_by_id[$id_csv])) {
                            $produk = $produk_by_id[$id_csv];
                        }
                        if (!$produk && isset($produk_by_name[strtolower($nama)])) {
                            $produk = $produk_by_name[strtolower($nama)];
                        }

                        if ($produk) {
                            $id_produk = (int) $produk['id_produk'];
                            if ($kategori === '') {
                                $kategori = $produk['kategori'];
                            }
                            Db::execute(
                                'UPDATE tb_produk SET harga = ?, kategori = ? WHERE id_produk = ?',
                                [$harga, $kategori ?: $produk['kategori'], $id_produk]
                            );
                        } else {
                            $kategori_final = $kategori !== '' ? $kategori : '-';
                            $id_produk = Db::insert(
                                'INSERT INTO tb_produk (nama_produk, kategori, harga, stok, created_by)
                                 VALUES (?, ?, ?, ?, ?)',
                                [$nama, $kategori_final, $harga, 0, $_SESSION['id_user'] ?? null]
                            );
                            $produk_by_id[$id_produk] = [
                                'id_produk' => $id_produk, 'nama_produk' => $nama,
                                'kategori'  => $kategori_final, 'harga' => $harga,
                            ];
                            $produk_by_name[strtolower($nama)] = $produk_by_id[$id_produk];
                            $n_produk_baru++;
                        }

                        $total = $jumlah * $harga;
                        Db::execute(
                            'INSERT INTO tb_transaksi
                             (tanggal_transaksi, id_produk, jumlah_terjual, harga_satuan, total_harga, label_tren)
                             VALUES (?, ?, ?, ?, ?, ?)',
                            [$tanggal, $id_produk, $jumlah, $harga, $total, $label]
                        );
                        $n_transaksi++;
                    }

                    $pdo->commit();
                    log_activity('Simpan Dataset',
                        "Produk baru: $n_produk_baru, Transaksi baru: $n_transaksi (data lama dihapus)");

                    unset($_SESSION['parsed_rows'], $_SESSION['parsed_filename']);
                    $preview = [];
                    $preview_filename = '';
                    $success = "Berhasil menyimpan $n_transaksi transaksi dan $n_produk_baru produk baru. "
                             . "Data lama telah dihapus. Silakan buka Dashboard untuk melihat hasil.";
                } catch (Exception $e) {
                    if (Db::conn()->inTransaction()) Db::conn()->rollBack();
                    $error = 'Gagal menyimpan: ' . $e->getMessage();
                }
            }
        }

        // ---- Aksi 4: Bersihkan pratinjau ----
        elseif ($action === 'clear') {
            unset($_SESSION['parsed_rows'], $_SESSION['parsed_filename']);
            $preview = [];
            $preview_filename = '';
            $info = 'Pratinjau dibersihkan.';
        }
    }
}

$total_produk_db    = (int) Db::fetchScalar('SELECT COUNT(*) FROM tb_produk');
$total_transaksi_db = (int) Db::fetchScalar('SELECT COUNT(*) FROM tb_transaksi');

$contoh_data = [];
if ($total_transaksi_db > 0) {
    $contoh_data = Db::fetchAll(
        "SELECT t.tanggal_transaksi, p.nama_produk, p.kategori,
                t.jumlah_terjual, t.harga_satuan
         FROM tb_transaksi t
         JOIN tb_produk p ON p.id_produk = t.id_produk
         ORDER BY t.tanggal_transaksi ASC, t.id_transaksi ASC
         LIMIT 5"
    );
    $last = Db::fetchOne(
        "SELECT t.tanggal_transaksi, p.nama_produk, p.kategori,
                t.jumlah_terjual, t.harga_satuan
         FROM tb_transaksi t
         JOIN tb_produk p ON p.id_produk = t.id_produk
         ORDER BY t.tanggal_transaksi DESC, t.id_transaksi DESC LIMIT 1"
    );
    if ($last) $contoh_data[] = $last;
}

$page_title  = 'Input Dataset';
$active_menu = 'input';
require __DIR__ . '/../src/layout/top.php';
?>

<?php if ($error): ?><div class="alert alert-danger mb-3"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success mb-3"><?= e($success) ?></div><?php endif; ?>
<?php if ($info): ?><div class="alert alert-info mb-3"><?= e($info) ?></div><?php endif; ?>

<div class="card mb-3">
  <div class="card-title" style="margin-bottom:2px;">Unggah Data Historis</div>
  <div style="font-size:11px; color:var(--text-muted); margin-bottom:14px;">
    Unggah file CSV atau Excel, atau tambahkan data transaksi secara manual.
    <strong style="color:#B45309;">Data lama akan otomatis dihapus saat simpan.</strong>
  </div>

  <form method="post" enctype="multipart/form-data" id="form-upload">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="upload">

    <div class="row g-3 align-items-center">
      <div class="col-lg-7">
        <label for="file-input" class="upload-area" id="drop-zone">
          <div class="upload-icon">⬆</div>
          <div class="upload-title">Seret dan lepas berkas di sini</div>
          <div class="upload-sub">atau klik untuk memilih file · CSV, XLSX</div>
        </label>
        <input type="file" id="file-input" name="file" accept=".csv,.xlsx,.xls" style="display:none;">
      </div>

      <div class="col-lg-5">
        <div id="file-info-default" class="file-info-box-v2">
          <div class="file-icon-v2">📄</div>
          <div class="file-detail-v2">
            <div class="file-name-v2">Belum ada berkas dipilih</div>
            <div class="file-rows-v2">Pilih file CSV/XLSX untuk diunggah</div>
          </div>
        </div>

        <div id="file-info" class="file-info-box-v2" style="display:none;">
          <div class="file-icon-v2">📄</div>
          <div class="file-detail-v2">
            <div class="file-name-v2" id="file-name">-</div>
            <div class="file-rows-v2" id="file-rows">-</div>
            <div class="file-ok-v2">✓ Validasi format berhasil</div>
          </div>
        </div>

        <div class="d-flex gap-2 mt-3">
          <button type="submit" class="btn btn-primary flex-fill" id="btn-upload" disabled
                  style="justify-content:center;">
            Unggah &amp; Simpan
          </button>
          <button type="button" class="btn btn-outline" data-bs-toggle="modal"
                  data-bs-target="#modalManual">
            Tambah Manual
          </button>
        </div>
      </div>
    </div>
  </form>
</div>

<div class="card">
  <div class="card-title" style="margin-bottom:2px;">Pratinjau Data Transaksi</div>
  <div style="font-size:11px; color:var(--text-muted); margin-bottom:14px;">
    Contoh data dari <?= number_format($total_transaksi_db, 0, ',', '.') ?> record yang tersimpan
  </div>

  <?php if ($total_transaksi_db === 0 && empty($preview)): ?>
    <div class="alert alert-info mb-0">
      Belum ada data transaksi di database. Unggah berkas CSV/XLSX atau tambah data manual.
    </div>
  <?php endif; ?>

  <?php if ($total_transaksi_db > 0): ?>
    <div style="overflow-x:auto;">
      <table class="table-clean">
        <thead>
          <tr>
            <th style="width:60px;">NO</th>
            <th>TANGGAL</th>
            <th>NAMA PRODUK</th>
            <th style="text-align:right;">JUMLAH TERJUAL</th>
            <th style="text-align:right;">HARGA (RP)</th>
            <th>KATEGORI</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($contoh_data as $i => $row): ?>
            <?php if ($i === 5): ?>
              <tr><td colspan="6" style="text-align:center; color:#94A3B8; font-size:11px;">. . .</td></tr>
            <?php endif; ?>
            <tr>
              <td><?= ($i === 5) ? number_format($total_transaksi_db, 0, ',', '.') : ($i + 1) ?></td>
              <td><?= e(format_tanggal($row['tanggal_transaksi'])) ?></td>
              <td><?= e($row['nama_produk']) ?></td>
              <td style="text-align:right;"><?= number_format((int) $row['jumlah_terjual'], 0, ',', '.') ?></td>
              <td style="text-align:right;"><?= e(format_rupiah($row['harga_satuan'])) ?></td>
              <td><?= e($row['kategori']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div style="font-size:11px; color:var(--text-muted); margin-top:8px;">
      Menampilkan contoh data dari <?= number_format($total_transaksi_db, 0, ',', '.') ?> record.
    </div>
  <?php endif; ?>

  <?php if (!empty($preview)): ?>
    <div style="margin-top:20px; padding-top:16px; border-top:1px solid var(--border-soft);">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div style="font-size:12px; font-weight:600;">
          Pratinjau data baru (<?= count($preview) ?> baris)
          <?php if ($preview_filename): ?>
            <span style="font-weight:400; color:var(--text-muted);">· <?= e($preview_filename) ?></span>
          <?php endif; ?>
        </div>
      </div>
      <div style="overflow-x:auto; max-height:400px; overflow-y:auto;">
        <table class="table-clean">
          <thead>
            <tr>
              <th>NO</th><th>TANGGAL</th><th>NAMA PRODUK</th>
              <th style="text-align:right;">JUMLAH TERJUAL</th>
              <th style="text-align:right;">HARGA (RP)</th><th>KATEGORI</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($preview as $i => $row): ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td><?= e(format_tanggal($row['tanggal'])) ?></td>
                <td><?= e($row['nama_produk']) ?></td>
                <td style="text-align:right;"><?= number_format((int) $row['jumlah_terjual'], 0, ',', '.') ?></td>
                <td style="text-align:right;"><?= e(format_rupiah($row['harga'])) ?></td>
                <td><?= e($row['kategori'] ?: '-') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="alert alert-warning mt-3 mb-0" style="font-size:12px;">
        <strong>⚠ Perhatian:</strong> Menyimpan akan <strong>menghapus semua transaksi lama</strong> dan
        semua riwayat riset. Pastikan data baru sudah benar.
      </div>

      <div class="d-flex justify-content-end gap-2 mt-3">
        <form method="post" style="display:inline;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="clear">
          <button type="submit" class="btn btn-outline"
                  style="padding:10px 20px; font-size:13px;">Bersihkan</button>
        </form>
        <form method="post" style="display:inline;"
              onsubmit="return confirm('Simpan data baru? Data lama (transaksi + riset) akan dihapus.');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save_db">
          <button type="submit" class="btn btn-primary"
                  style="padding:10px 24px; font-size:13px;">
            💾 Simpan ke Database
          </button>
        </form>
      </div>
    </div>
  <?php endif; ?>
</div>

<div class="modal fade" id="modalManual" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="manual">
        <div class="modal-header">
          <h5 class="modal-title">Tambah Data Manual</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2"><label class="form-label">Tanggal</label>
            <input type="date" name="tanggal" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Nama Produk</label>
            <input type="text" name="nama_produk" class="form-control"
                   placeholder="Charger iPhone 20W" required></div>
          <div class="mb-2"><label class="form-label">Kategori</label>
            <input type="text" name="kategori" class="form-control"
                   placeholder="Charger" required></div>
          <div class="mb-2"><label class="form-label">Jumlah Terjual</label>
            <input type="number" name="jumlah_terjual" class="form-control"
                   min="1" placeholder="11" required></div>
          <div class="mb-2"><label class="form-label">Harga Satuan (Rp)</label>
            <input type="number" name="harga" class="form-control"
                   min="1" step="0.01" placeholder="15500" required></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Tambahkan ke Pratinjau</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
  const dropZone  = document.getElementById('drop-zone');
  const fileInput = document.getElementById('file-input');
  const fileInfo  = document.getElementById('file-info');
  const fileDef   = document.getElementById('file-info-default');
  const fileName  = document.getElementById('file-name');
  const fileRows  = document.getElementById('file-rows');
  const btnUpload = document.getElementById('btn-upload');

  function showFile(file) {
    fileName.textContent = file.name;
    const sizeKB = (file.size / 1024).toFixed(1);
    fileRows.textContent = sizeKB + ' KB · siap diunggah';
    fileInfo.style.display = 'flex';
    fileDef.style.display  = 'none';
    btnUpload.disabled = false;
  }

  dropZone.addEventListener('click', () => fileInput.click());
  fileInput.addEventListener('change', () => {
    if (fileInput.files[0]) showFile(fileInput.files[0]);
  });

  ['dragenter', 'dragover'].forEach(evt => {
    dropZone.addEventListener(evt, e => {
      e.preventDefault(); dropZone.classList.add('dragover');
    });
  });
  ['dragleave', 'drop'].forEach(evt => {
    dropZone.addEventListener(evt, e => {
      e.preventDefault(); dropZone.classList.remove('dragover');
    });
  });
  dropZone.addEventListener('drop', e => {
    const file = e.dataTransfer.files[0];
    if (file) { fileInput.files = e.dataTransfer.files; showFile(file); }
  });
})();
</script>

<?php require __DIR__ . '/../src/layout/bottom.php'; ?>