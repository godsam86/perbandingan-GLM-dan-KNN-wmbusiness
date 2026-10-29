<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Kontak';
require_once __DIR__ . '/includes/header.php';
?>

<!-- HERO -->
<section class="hero" style="padding: 140px 0 60px;">
    <div class="container">
        <div class="hero-content" style="text-align: center; max-width: 800px; margin: 0 auto;">
            <span class="section-label">Kontak</span>
            <h1 style="font-size: 48px;">
                Hubungi <span>Kami</span>
            </h1>
            <p>Kami siap membantu Anda - hubungi kami melalui channel di bawah ini</p>
        </div>
    </div>
</section>

<!-- KONTAK INFO -->
<section class="section">
    <div class="container">
        
        <div class="features-grid" style="margin-bottom: 60px;">
            <div class="feature-card fade-in">
                <div class="feature-icon"><i class="bi bi-geo-alt-fill"></i></div>
                <h3>Alamat</h3>
                <p><?= SITE_ADDRESS ?></p>
            </div>
            
            <div class="feature-card fade-in">
                <div class="feature-icon"><i class="bi bi-telephone-fill"></i></div>
                <h3>Telepon</h3>
                <p><a href="tel:<?= SITE_PHONE ?>" style="color: var(--primary); font-weight: 600;"><?= SITE_PHONE ?></a></p>
            </div>
            
            <div class="feature-card fade-in">
                <div class="feature-icon" style="background: linear-gradient(135deg, #25d366, #128c7e);"><i class="bi bi-whatsapp"></i></div>
                <h3>WhatsApp</h3>
                <p><a href="https://wa.me/<?= SITE_WHATSAPP ?>" target="_blank" style="color: var(--primary); font-weight: 600;">Chat Sekarang</a></p>
            </div>
            
            <div class="feature-card fade-in">
                <div class="feature-icon"><i class="bi bi-envelope-fill"></i></div>
                <h3>Email</h3>
                <p><a href="mailto:<?= SITE_EMAIL ?>" style="color: var(--primary); font-weight: 600;"><?= SITE_EMAIL ?></a></p>
            </div>
        </div>
        
        <!-- Maps & Info -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px;">
            
            <!-- Maps -->
            <div class="fade-in">
                <h2 style="font-size: 24px; font-weight: 800; margin-bottom: 20px; color: var(--dark);">
                    <i class="bi bi-map"></i> Lokasi Kami
                </h2>
                <div style="border-radius: 16px; overflow: hidden; box-shadow: var(--shadow-lg);">
                    <iframe 
                        src="<?= SITE_MAPS_EMBED ?>"
                        width="100%" 
                        height="400" 
                        style="border: 0; display: block;" 
                        allowfullscreen="" 
                        loading="lazy">
                    </iframe>
                </div>
            </div>
            
            <!-- Info -->
            <div class="fade-in">
                <h2 style="font-size: 24px; font-weight: 800; margin-bottom: 20px; color: var(--dark);">
                    <i class="bi bi-info-circle"></i> Informasi
                </h2>
                
                <div style="background: var(--bg); border-radius: 16px; padding: 30px;">
                    
                    <div style="margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid var(--border);">
                        <h4 style="font-size: 14px; color: var(--gray); margin-bottom: 6px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                            <i class="bi bi-clock"></i> Jam Operasional
                        </h4>
                        <p style="font-size: 16px; font-weight: 700; color: var(--dark); margin: 0;">
                            <?= SITE_OPERASIONAL ?>
                        </p>
                        <p style="font-size: 13px; color: var(--gray); margin: 4px 0 0;">
                            Minggu & hari besar: Tutup
                        </p>
                    </div>
                    
                    <div style="margin-bottom: 24px; padding-bottom: 24px; border-bottom: 1px solid var(--border);">
                        <h4 style="font-size: 14px; color: var(--gray); margin-bottom: 6px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                            <i class="bi bi-geo-alt"></i> Alamat Lengkap
                        </h4>
                        <p style="font-size: 15px; color: var(--dark); margin: 0; line-height: 1.6;">
                            <?= SITE_ADDRESS ?>
                        </p>
                    </div>
                    
                    <div>
                        <h4 style="font-size: 14px; color: var(--gray); margin-bottom: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                            <i class="bi bi-share"></i> Ikuti Kami
                        </h4>
                        <div class="footer-social">
                            <a href="https://instagram.com/<?= SITE_INSTAGRAM ?>" target="_blank">
                                <i class="bi bi-instagram"></i>
                            </a>
                            <a href="https://facebook.com/<?= SITE_FACEBOOK ?>" target="_blank">
                                <i class="bi bi-facebook"></i>
                            </a>
                            <a href="https://tiktok.com/@<?= SITE_TIKTOK ?>" target="_blank">
                                <i class="bi bi-tiktok"></i>
                            </a>
                            <a href="https://wa.me/<?= SITE_WHATSAPP ?>" target="_blank">
                                <i class="bi bi-whatsapp"></i>
                            </a>
                        </div>
                    </div>
                    
                </div>
                
                <div style="margin-top: 24px; text-align: center;">
                    <a href="https://wa.me/<?= SITE_WHATSAPP ?>" target="_blank" class="btn-primary" style="width: 100%; justify-content: center;">
                        <i class="bi bi-whatsapp"></i> Chat via WhatsApp
                    </a>
                </div>
            </div>
            
        </div>
        
    </div>
</section>

<!-- Responsive -->
<style>
@media (max-width: 768px) {
    section .container > div[style*="grid-template-columns: 1fr 1fr"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<?php
require_once __DIR__ . '/includes/footer.php';
?>