<?php
require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Home';
$produkTerlaris = getProdukTerlaris(4);
$totalProduk = count(getProduk());

require_once __DIR__ . '/includes/header.php';
?>

<!-- ============================================
     HERO SECTION
     ============================================ -->
<section class="hero">
    <div class="container">
        <div class="hero-content">
            <h1>
                Aksesoris HP <span>Terlengkap</span> di Medan
            </h1>
            <p>
                WMBusiness99 Medan menyediakan berbagai aksesoris handphone berkualitas 
                dengan harga terjangkau. Dari casing, charger, kabel data, power bank, 
                hingga earphone - semua tersedia di sini.
            </p>
            <div class="hero-buttons">
                <a href="<?= url('produk.php') ?>" class="btn-primary">
                    <i class="bi bi-bag"></i> Lihat Produk
                </a>
                <a href="https://wa.me/<?= SITE_WHATSAPP ?>" target="_blank" class="btn-secondary">
                    <i class="bi bi-whatsapp"></i> Hubungi Kami
                </a>
            </div>
        </div>
        
        <div class="hero-image">
            <div class="hero-image-wrapper">
                <i class="bi bi-phone-fill"></i>
            </div>
            
            <div class="hero-floating-card hero-card-1">
                <i class="bi bi-star-fill" style="color: #fbbf24;"></i>
                <span>Kualitas Terjamin</span>
            </div>
            
            <div class="hero-floating-card hero-card-2">
                <i class="bi bi-truck"></i>
                <span>Pengiriman Cepat</span>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
     FEATURES SECTION
     ============================================ -->
<section class="section">
    <div class="container">
        <div class="section-header fade-in">
            <span class="section-label">Keunggulan Kami</span>
            <h2 class="section-title">Kenapa Pilih WMBusiness99?</h2>
            <p class="section-desc">
                Kami hadir memberikan pengalaman belanja aksesoris HP terbaik untuk Anda
            </p>
        </div>
        
        <div class="features-grid">
            <div class="feature-card fade-in">
                <div class="feature-icon">
                    <i class="bi bi-patch-check-fill"></i>
                </div>
                <h3>100% Original</h3>
                <p>Semua produk yang kami jual dijamin asli dan berkualitas tinggi</p>
            </div>
            
            <div class="feature-card fade-in">
                <div class="feature-icon">
                    <i class="bi bi-cash-coin"></i>
                </div>
                <h3>Harga Terjangkau</h3>
                <p>Dapatkan harga terbaik dengan kualitas yang tidak kalah dari brand lain</p>
            </div>
            
            <div class="feature-card fade-in">
                <div class="feature-icon">
                    <i class="bi bi-truck"></i>
                </div>
                <h3>Pengiriman Cepat</h3>
                <p>Pesanan Anda akan diproses dan dikirim dengan cepat dan aman</p>
            </div>
            
            <div class="feature-card fade-in">
                <div class="feature-icon">
                    <i class="bi bi-headset"></i>
                </div>
                <h3>Pelayanan Ramah</h3>
                <p>Tim kami siap membantu Anda dengan pelayanan yang ramah dan profesional</p>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
     PRODUK TERLARIS SECTION
     ============================================ -->
<?php if (!empty($produkTerlaris)): ?>
<section class="section" style="background: var(--bg);">
    <div class="container">
        <div class="section-header fade-in">
            <span class="section-label">Produk Unggulan</span>
            <h2 class="section-title">Produk Terlaris Kami</h2>
            <p class="section-desc">
                Produk paling banyak dicari dan diminati oleh pelanggan setia kami
            </p>
        </div>
        
        <div class="produk-grid">
            <?php foreach ($produkTerlaris as $produk): ?>
                
                <?php 
                // LOGIKA FOTO PRODUK
                $nama_file = strtolower(str_replace(' ', '-', $produk['nama_produk'])) . '.jpg';
                $path_gambar = asset('images/' . $nama_file);
                $file_fisik = __DIR__ . '/assets/images/' . $nama_file;
                ?>
                
                <div class="produk-card fade-in">
                    <div class="produk-image">
                        <div class="produk-badge">⭐ Terlaris</div>
                        
                        <?php if (file_exists($file_fisik)): ?>
                            <!-- Tampilkan foto jika file ada di folder assets/images/ -->
                            <img src="<?= $path_gambar ?>" alt="<?= e($produk['nama_produk']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <!-- Tampilkan icon jika foto belum ada -->
                            <i class="bi bi-<?= 
                                stripos($produk['kategori'], 'charger') !== false ? 'plug-fill' :
                                (stripos($produk['kategori'], 'casing') !== false ? 'phone' :
                                (stripos($produk['kategori'], 'kabel') !== false ? 'link-45deg' :
                                (stripos($produk['kategori'], 'power') !== false ? 'battery-charging' :
                                (stripos($produk['kategori'], 'earphone') !== false ? 'headphones' : 'box-seam'))))
                            ?>"></i>
                        <?php endif; ?>
                    </div>
                    <div class="produk-body">
                        <div class="produk-kategori"><?= e($produk['kategori']) ?></div>
                        <h3 class="produk-name"><?= e($produk['nama_produk']) ?></h3>
                        <div class="produk-price">
                            <?= rupiah($produk['harga']) ?>
                        </div>
                        <a href="https://wa.me/<?= SITE_WHATSAPP ?>?text=Halo,%20saya%20tertarik%20dengan%20<?= urlencode($produk['nama_produk']) ?>" 
                           target="_blank"
                           class="produk-btn">
                            <i class="bi bi-whatsapp"></i> Tanya Produk
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div style="text-align: center; margin-top: 40px;">
            <a href="<?= url('produk.php') ?>" class="btn-primary">
                <i class="bi bi-grid"></i> Lihat Semua Produk
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================================
     STATS SECTION
     ============================================ -->
<section class="stats-section">
    <div class="container">
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-number" data-count="<?= $totalProduk ?>">0</div>
                <div class="stat-label">Produk Tersedia</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" data-count="5000">0</div>
                <div class="stat-label">Pelanggan Puas</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" data-count="10">0</div>
                <div class="stat-label">Tahun Pengalaman</div>
            </div>
            <div class="stat-item">
                <div class="stat-number" data-count="100">0</div>
                <div class="stat-label">% Original</div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================
     CTA SECTION
     ============================================ -->
<section class="cta-section">
    <div class="container">
        <div class="cta-card fade-in">
            <h2>Siap Berbelanja?</h2>
            <p>
                Hubungi kami sekarang dan dapatkan penawaran terbaik untuk 
                kebutuhan aksesoris handphone Anda
            </p>
            <a href="https://wa.me/<?= SITE_WHATSAPP ?>" target="_blank" class="btn-primary">
                <i class="bi bi-whatsapp"></i> Chat via WhatsApp
            </a>
        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';
?>