<?php
Auth::requireLogin();
$user = Auth::user();
$page_title  = $page_title  ?? 'Dashboard';
$active_menu = $active_menu ?? 'dashboard';

$menu = [
    'dashboard'    => ['label' => 'Dashboard',          'href' => 'dashboard.php',    'icon' => '▤'],
    'input'        => ['label' => 'Input Dataset',      'href' => 'input.php',        'icon' => '⬆'],
    'praproses'    => ['label' => 'Praproses Data',     'href' => 'praproses.php',    'icon' => '⚙'],
    'glm'          => ['label' => 'Prediksi GLM',       'href' => 'glm.php',          'icon' => '∑'],
    'knn'          => ['label' => 'Prediksi KNN',       'href' => 'knn.php',          'icon' => '◎'],
    'perbandingan' => ['label' => 'Perbandingan Model', 'href' => 'perbandingan.php', 'icon' => '⇄'],
    'hasil'        => ['label' => 'Hasil Prediksi',     'href' => 'hasil.php',        'icon' => '✓'],
    'rekomendasi'  => ['label' => 'Rekomendasi Bisnis', 'href' => 'rekomendasi.php',  'icon' => '★'],
];
?><!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($page_title) ?> · <?= e(APP_NAME) ?></title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(ASSETS_URL) ?>/css/app.css">
</head>
<body>
<div class="app-wrapper">

  <aside class="app-sidebar">
    <div class="sidebar-brand">
      <div class="sidebar-logo">PT</div>
      <div class="sidebar-brand-text">
        <div class="brand-title">Prediksi Tren</div>
        <div class="brand-sub"><?= e(APP_SUB) ?></div>
      </div>
    </div>

    <nav class="sidebar-menu">
      <?php foreach ($menu as $key => $item): ?>
        <a class="menu-item <?= $active_menu === $key ? 'active' : '' ?>"
           href="<?= e($item['href']) ?>">
          <span class="menu-icon"><?= $item['icon'] ?></span>
          <span><?= e($item['label']) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
      <div class="user-box">
        <div class="user-avatar"><?= strtoupper(substr($user['nama_lengkap'] ?? 'A', 0, 1)) ?></div>
        <div class="user-info">
          <div class="user-name"><?= e($user['nama_lengkap'] ?? 'Admin') ?></div>
          <div class="user-role">Admin / Pemilik Usaha</div>
        </div>
      </div>
      <a href="logout.php" class="logout-link" title="Logout">⎋</a>
    </div>
  </aside>

  <main class="app-main">
    <header class="app-header">
      <div>
        <h1 class="page-title"><?= e($page_title) ?></h1>
        <div class="breadcrumb-line">
          Beranda / <span><?= e($page_title) ?></span>
        </div>
      </div>
      <div class="header-right">
        <span class="chip chip-admin"><?= e($user['nama_lengkap'] ?? 'Admin') ?></span>
      </div>
    </header>

    <div class="app-content">
      <?php
      if (!empty($_SESSION['ml_down'])) {
          echo ml_unavailable_banner();
          unset($_SESSION['ml_down']);
      }
      ?>