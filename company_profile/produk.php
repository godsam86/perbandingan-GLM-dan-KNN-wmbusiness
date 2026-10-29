<?php
require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Katalog Produk';

// Filter kategori & search
$filterKategori = $_GET['kategori'] ?? '';
$searchKeyword = trim($_GET['search'] ?? '');

if ($filterKategori || $searchKeyword) {
    global $pdo;
    $sql = "SELECT * FROM tb_produk WHERE 1=1";
    $params = [];
    
    if ($filterKategori) {
        $sql .= " AND kategori = ?";
        $params[] = $filterKategori;
    }
    
    if ($searchKeyword) {
        $sql .= " AND nama_produk LIKE ?";
        $params[] = "%$searchKeyword%";
    }
    
    $sql .= " ORDER BY nama_produk ASC";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $produkList = $stmt->fetchAll();
    } catch (Exception $e) {
        $produkList = [];
    }
} else {
    $produkList = getProduk();
}

$kategoriList = getKategori();

require_once __DIR__ . '/includes/header.php';
?>

<!-- ============================================
     HERO
     ============================================ -->
<section class="hero" style="padding: 140px 0 60px;">
    <div class="container">
        <div class="hero-content" style="text-align: center; max-width: 800px; margin: 0 auto;">
            <span class="section-label">Katalog Produk</span>
            <h1 style="font-size: 48px;">
                Produk <span>Unggulan</span> Kami
            </h1>
            <p>
                Temukan berbagai aksesoris handphone berkualitas dengan harga terjangkau
            </p>
        </div>
    </div>
</section>

<!-- ============================================
     FILTER & PRODUK
     ============================================ -->
<section class="section" style="padding-top: 40px;">
    <div class="container">
        
        <!-- Filter & Search -->
        <div class="fade-in" style="background: var(--bg); border-radius: 16px; padding: 24px; margin-bottom: 40px;">
            <form method="GET" style="display: flex; gap: 12px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 200px;">
                    <input type="text" 
                           name="search" 
                           placeholder="🔍 Cari produk..."
                           value="<?= e($searchKeyword) ?>"
                           style="width: 100%; padding: 12px 16px; border: 1px solid var(--border); border-radius: 10px; font-size: 14px; outline: none;">
                </div>
                <div style="min-width: 180px;">
                    <select name="kategori" style="width: 100%; padding: 12px 16px; border: 1px solid var(--border); border-radius: 10px; font-size: 14px; outline: none; background: white;">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($kategoriList as $kat): ?>
                            <option value="<?= e($kat['kategori']) ?>" <?= $filterKategori === $kat['kategori'] ? 'selected' : '' ?>>
                                <?= e($kat['kategori']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-primary" style="padding: 12px 24px;">
                    <i class="bi bi-search"></i> Cari
                </button>
                <?php if ($filterKategori || $searchKeyword): ?>
                    <a href="<?= url('produk.php') ?>" class="btn-secondary" style="padding: 12px 24px;">
                        <i class="bi bi-x-circle"></i> Reset
                    </a>
                <?php endif; ?>
            </form>
        </div>
        
        <!-- Info -->
        <div class="fade-in" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px;">
            <p style="color: var(--gray); margin: 0; font-size: 14px;">
                Menampilkan <strong style="color: var(--primary);"><?= count($produkList) ?></strong> produk
                <?php if ($filterKategori): ?> dalam kategori <strong><?= e($filterKategori) ?></strong><?php endif; ?>
            </p>
        </div>
        
        <!-- Produk Grid -->
        <?php if (empty($produkList)): ?>
            <div style="text-align: center; padding: 80px 20px;">
                <div style="font-size: 80px; margin-bottom: 20px;">📦</div>
                <h3 style="font-size: 24px; color: var(--dark); margin-bottom: 12px;">Produk Tidak Ditemukan</h3>
                <p style="color: var(--gray); margin-bottom: 24px;">Coba kata kunci atau kategori lain</p>
                <a href="<?= url('produk.php') ?>" class="btn-primary">
                    <i class="bi bi-grid"></i> Lihat Semua Produk
                </a>
            </div>
        <?php else: ?>
            <div class="produk-grid">
                <?php foreach ($produkList as $produk): ?>
                    
                    <?php 
                    // LOGIKA FOTO PRODUK
                    $nama_file = strtolower(str_replace(' ', '-', $produk['nama_produk'])) . '.jpg';
                    $path_gambar = asset('images/' . $nama_file);
                    $file_fisik = __DIR__ . '/assets/images/' . $nama_file;
                    ?>
                    
                    <div class="produk-card fade-in">
                        <div class="produk-image">
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
                                <span>/ unit</span>
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
        <?php endif; ?>
        
    </div>
</section>

<!-- CTA -->
<section class="cta-section">
    <div class="container">
        <div class="cta-card fade-in">
            <h2>Tidak Menemukan yang Dicari?</h2>
            <p>Hubungi kami langsung, tim kami siap membantu Anda</p>
            <a href="https://wa.me/<?= SITE_WHATSAPP ?>" target="_blank" class="btn-primary">
                <i class="bi bi-whatsapp"></i> Chat WhatsApp
            </a>
        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';
?>