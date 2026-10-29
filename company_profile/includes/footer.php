<!-- ============================================
     FOOTER
     ============================================ -->
<footer class="footer">
    <div class="container">
        
        <div class="footer-grid">
            
            <!-- Brand -->
            <div class="footer-col">
                <div class="footer-brand">
                    <div class="brand-icon">
                        <i class="bi bi-phone"></i>
                    </div>
                    <h4>WMBusiness99</h4>
                </div>
                <p class="footer-desc">
                    Toko aksesoris handphone terlengkap dan terpercaya di Medan. 
                    Menyediakan berbagai aksesoris berkualitas dengan harga terjangkau.
                </p>
                <div class="footer-social">
                    <a href="https://instagram.com/<?= SITE_INSTAGRAM ?>" target="_blank" aria-label="Instagram">
                        <i class="bi bi-instagram"></i>
                    </a>
                    <a href="https://facebook.com/<?= SITE_FACEBOOK ?>" target="_blank" aria-label="Facebook">
                        <i class="bi bi-facebook"></i>
                    </a>
                    <a href="https://tiktok.com/@<?= SITE_TIKTOK ?>" target="_blank" aria-label="TikTok">
                        <i class="bi bi-tiktok"></i>
                    </a>
                    <a href="https://wa.me/<?= SITE_WHATSAPP ?>" target="_blank" aria-label="WhatsApp">
                        <i class="bi bi-whatsapp"></i>
                    </a>
                </div>
            </div>
            
            <!-- Menu -->
            <div class="footer-col">
                <h5>Menu</h5>
                <ul class="footer-links">
                    <li><a href="<?= url('index.php') ?>">Home</a></li>
                    <li><a href="<?= url('tentang.php') ?>">Tentang Kami</a></li>
                    <li><a href="<?= url('produk.php') ?>">Produk</a></li>
                    <li><a href="<?= url('galeri.php') ?>">Galeri</a></li>
                    <li><a href="<?= url('kontak.php') ?>">Kontak</a></li>
                </ul>
            </div>
            
            <!-- Kontak -->
            <div class="footer-col">
                <h5>Kontak</h5>
                <ul class="footer-contact">
                    <li>
                        <i class="bi bi-geo-alt-fill"></i>
                        <span><?= SITE_ADDRESS ?></span>
                    </li>
                    <li>
                        <i class="bi bi-telephone-fill"></i>
                        <a href="tel:<?= SITE_PHONE ?>"><?= SITE_PHONE ?></a>
                    </li>
                    <li>
                        <i class="bi bi-envelope-fill"></i>
                        <a href="mailto:<?= SITE_EMAIL ?>"><?= SITE_EMAIL ?></a>
                    </li>
                    <li>
                        <i class="bi bi-clock-fill"></i>
                        <span><?= SITE_OPERASIONAL ?></span>
                    </li>
                </ul>
            </div>
            
            <!-- Layanan -->
            <div class="footer-col">
                <h5>Kategori Produk</h5>
                <ul class="footer-links">
                    <li><a href="<?= url('produk.php?kategori=Charger') ?>">Charger</a></li>
                    <li><a href="<?= url('produk.php?kategori=Casing') ?>">Casing</a></li>
                    <li><a href="<?= url('produk.php?kategori=Kabel data') ?>">Kabel Data</a></li>
                    <li><a href="<?= url('produk.php?kategori=Power bank') ?>">Power Bank</a></li>
                    <li><a href="<?= url('produk.php?kategori=Earphone/Headset') ?>">Earphone</a></li>
                </ul>
            </div>
            
        </div>
        
        <!-- Copyright -->
        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> <strong>WMBusiness99 Medan</strong>. All rights reserved.</p>
            <p class="footer-tagline">Dibuat dengan <i class="bi bi-heart-fill" style="color: #ef4444;"></i> untuk UMKM Medan</p>
        </div>
        
    </div>
</footer>

<!-- ============================================
     FLOATING WHATSAPP
     ============================================ -->
<a href="https://wa.me/<?= SITE_WHATSAPP ?>?text=Halo%20WMBusiness99,%20saya%20tertarik%20dengan%20produk%20Anda" 
   target="_blank" 
   class="wa-float" 
   aria-label="Chat WhatsApp">
    <i class="bi bi-whatsapp"></i>
    <span class="wa-tooltip">Chat dengan kami</span>
</a>

<!-- ============================================
     SCROLL TO TOP
     ============================================ -->
<button class="scroll-top" id="scrollTop" aria-label="Scroll ke atas">
    <i class="bi bi-arrow-up"></i>
</button>

<script src="<?= asset('js/script.js') ?>"></script>

</body>
</html>