<?php
/**
 * Halaman Login (Gambar 5.1)
 */
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/Db.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/helpers.php';

Auth::start();

if (Auth::check()) {
    header('Location: dashboard.php');
    exit;
}

$error   = '';
$username_input = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Sesi tidak valid. Muat ulang halaman.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $username_input = $username;

        if ($username === '' && $password === '') {
            $error = 'Username dan password wajib diisi.';
        } elseif ($username === '') {
            $error = 'Username wajib diisi.';
        } elseif ($password === '') {
            $error = 'Password wajib diisi.';
        } else {
            if (Auth::attempt($username, $password)) {
                log_activity('Login', 'User ' . $username . ' berhasil login');
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Username atau password salah.';
            }
        }
    }
}
?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Masuk · <?= e(APP_NAME) ?></title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(ASSETS_URL) ?>/css/app.css">
</head>
<body>
<div class="login-wrapper">

  <!-- KIRI: BRANDING -->
  <div class="login-left">
    <div class="logo-badge">PT</div>

    <h1>Sistem Prediksi<br>Tren Penjualan</h1>
    <p class="sub">
      Perbandingan Generalized Linear Model dan K-Nearest Neighbor
      pada WMBusiness99 Medan
    </p>

    <div class="login-feature">
      <span class="check">✓</span>
      <span>Klasifikasi tren penjualan naik atau turun</span>
    </div>
    <div class="login-feature">
      <span class="check">✓</span>
      <span>Perbandingan Accuracy, Precision, Recall, F1-Score</span>
    </div>
    <div class="login-feature">
      <span class="check">✓</span>
      <span>Rekomendasi pengelolaan stok per produk</span>
    </div>
  </div>

  <!-- KANAN: FORM -->
  <div class="login-right">
    <div class="login-card">
      <h2>Masuk ke Sistem</h2>
      <p class="sub">Gunakan akun admin untuk melanjutkan</p>

      <?php if ($error !== ''): ?>
        <div class="alert alert-danger mb-3"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" action="login.php" autocomplete="off" novalidate>
        <?= csrf_field() ?>

        <div class="mb-3">
          <label class="form-label" for="username">Username</label>
          <input type="text" class="form-control" id="username" name="username"
                 placeholder="admin" value="<?= e($username_input) ?>" autofocus>
        </div>

        <div class="mb-3">
          <label class="form-label" for="password">Password</label>
          <input type="password" class="form-control" id="password" name="password"
                 placeholder="••••••••">
        </div>

        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="remember"
                 name="remember" value="1">
          <label class="form-check-label" for="remember">Ingat saya</label>
        </div>

        <button type="submit" class="btn btn-primary w-100" style="justify-content:center;">
          Masuk
        </button>
      </form>

      <div class="login-footer">
        © <?= e(APP_YEAR) ?> <?= e(APP_SUB) ?>
      </div>
    </div>
  </div>

</div>
</body>
</html>