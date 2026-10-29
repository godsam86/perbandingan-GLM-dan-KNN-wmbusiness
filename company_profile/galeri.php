<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Galeri';
require_once __DIR__ . '/includes/header.php';
?>

<!-- HERO -->
<section class="hero" style="padding: 140px 0 60px;">
    <div class="container">
        <div class="hero-content" style="text-align: center; max-width: 800px; margin: 0 auto;">
            <span class="section-label">Galeri</span>
            <h1 style="font-size: 48px;">
                Galeri <span>Kami</span>
            </h1>
            <p>Intip suasana toko dan koleksi produk unggulan kami</p>
        </div>
    </div>
</section>

<!-- GALERI GRID -->
<section class="section">
    <div class="container">
        
        <div class="section-header fade-in">
            <span class="section-label">Koleksi Foto</span>
            <h2 class="section-title">Suasana Toko</h2>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
            
            <?php 
            $galeriItems = [
                ['icon' => 'bi-shop', 'title' => 'Tampak Depan Toko', 'color' => '#4f46e5'],
                ['icon' => 'bi-phone', 'title' => 'Display Produk', 'color' => '#7c3aed'],
                ['icon' => 'bi-headset', 'title' => 'Koleksi Earphone', 'color' => '#06b6d4'],
                ['icon' => 'bi-battery-charging', 'title' => 'Power Bank', 'color' => '#10b981'],
                ['icon' => 'bi-plug', 'title' => 'Charger Original', 'color' => '#f59e0b'],
                ['icon' => 'bi-box-seam', 'title' => 'Kemasan Rapi', 'color' => '#ef4444'],
            ];
            
            foreach ($galeriItems as $item): 
            ?>
                <div class="fade-in" style="
                    background: linear-gradient(135deg, <?= $item['color'] ?>15, <?= $item['color'] ?>05);
                    border: 2px dashed <?= $item['color'] ?>40;
                    border-radius: 16px;
                    padding: 60px 20px;
                    text-align: center;
                    transition: all 0.3s;
                    cursor: pointer;
                "
                onmouseover="this.style.transform='translateY(-6px)'; this.style.borderColor='<?= $item['color'] ?>';"
                onmouseout="this.style.transform='translateY(0)'; this.style.borderColor='<?= $item['color'] ?>40';">
                    <i class="bi <?= $item['icon'] ?>" style="font-size: 64px; color: <?= $item['color'] ?>; margin-bottom: 16px;"></i>
                    <h4 style="font-size: 16px; font-weight: 700; color: var(--dark);"><?= $item['title'] ?></h4>
                    <p style="font-size: 13px; color: var(--gray); margin-top: 8px;">Foto akan ditampilkan di sini</p>
                </div>
            <?php endforeach; ?>
            
        </div>
        
    </div>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';
?>