<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= SITE_DESCRIPTION ?>">
    <meta name="keywords" content="aksesoris hp medan, toko aksesoris handphone, casing hp, charger, kabel data, power bank, earphone">
    
    <title><?= isset($pageTitle) ? $pageTitle . ' - ' : '' ?><?= SITE_NAME ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E📱%3C/text%3E%3C/svg%3E">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- CSS -->
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body>

<!-- ============================================
     NAVBAR
     ============================================ -->
<nav class="navbar" id="navbar">
    <div class="container">
        
        <!-- Logo -->
        <a href="<?= url('index.php') ?>" class="navbar-brand">
            <div class="brand-icon">
                <i class="bi bi-phone"></i>
            </div>
            <div class="brand-text">
                <span class="brand-name">WMBusiness99</span>
                <span class="brand-tagline">Medan</span>
            </div>
        </a>
        
        <!-- Menu Desktop -->
        <ul class="navbar-menu" id="navMenu">
            <li><a href="<?= url('index.php') ?>" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">Home</a></li>
            <li><a href="<?= url('tentang.php') ?>" class="<?= $currentPage === 'tentang.php' ? 'active' : '' ?>">Tentang</a></li>
            <li><a href="<?= url('produk.php') ?>" class="<?= $currentPage === 'produk.php' ? 'active' : '' ?>">Produk</a></li>
            <li><a href="<?= url('galeri.php') ?>" class="<?= $currentPage === 'galeri.php' ? 'active' : '' ?>">Galeri</a></li>
            <li><a href="<?= url('kontak.php') ?>" class="<?= $currentPage === 'kontak.php' ? 'active' : '' ?>">Kontak</a></li>
        </ul>
        
        <!-- CTA Button -->
        <a href="https://wa.me/<?= SITE_WHATSAPP ?>" target="_blank" class="btn-wa-nav">
            <i class="bi bi-whatsapp"></i>
            <span>Hubungi Kami</span>
        </a>
        
        <!-- Mobile Toggle -->
        <button class="nav-toggle" id="navToggle">
            <i class="bi bi-list"></i>
        </button>
        
    </div>
</nav>