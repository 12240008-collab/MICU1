<?php
// footer.php - Footer component untuk MICU Laundry
?>

<style>
/* Footer Styles */
.main-footer {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    color: white;
    margin-top: 80px;
    animation: fadeIn 0.8s ease-out;
}

.footer-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 60px 40px 30px;
}

.footer-content {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr;
    gap: 50px;
    margin-bottom: 50px;
}

/* Footer Brand */
.footer-brand {
    animation: fadeInUp 0.8s ease-out 0.2s backwards;
}

.footer-logo {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    text-decoration: none;
}

.footer-logo-icon {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #0066cc 0%, #004999 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 15px rgba(0, 102, 204, 0.3);
}

.footer-logo-icon i {
    font-size: 26px;
    color: white;
}

.footer-logo-text {
    font-size: 32px;
    font-weight: 800;
    color: white;
    letter-spacing: 1px;
}

.footer-description {
    color: rgba(255, 255, 255, 0.8);
    line-height: 1.8;
    margin-bottom: 25px;
    font-size: 15px;
}

.footer-social {
    display: flex;
    gap: 12px;
}

.social-link {
    width: 45px;
    height: 45px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    text-decoration: none;
    transition: all 0.3s ease;
}

.social-link:hover {
    background: #0066cc;
    transform: translateY(-3px);
}

.social-link i {
    font-size: 18px;
}

/* Footer Columns */
.footer-column {
    animation: fadeInUp 0.8s ease-out backwards;
}

.footer-column:nth-child(2) { animation-delay: 0.3s; }
.footer-column:nth-child(3) { animation-delay: 0.4s; }
.footer-column:nth-child(4) { animation-delay: 0.5s; }

.footer-column-title {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 20px;
    color: white;
}

.footer-links {
    list-style: none;
}

.footer-links li {
    margin-bottom: 12px;
}

.footer-link {
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
    font-size: 15px;
    transition: all 0.3s ease;
    display: inline-block;
}

.footer-link:hover {
    color: white;
    transform: translateX(5px);
}

.footer-link i {
    margin-right: 8px;
    color: #0066cc;
    width: 18px;
}

/* Contact Info */
.contact-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 18px;
    color: rgba(255, 255, 255, 0.8);
}

.contact-item i {
    color: #0066cc;
    font-size: 18px;
    margin-top: 2px;
    flex-shrink: 0;
}

.contact-item-content {
    font-size: 15px;
    line-height: 1.6;
}

.contact-item a {
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    transition: color 0.3s ease;
}

.contact-item a:hover {
    color: white;
}

/* Newsletter */
.newsletter-form {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.newsletter-input {
    flex: 1;
    padding: 12px 16px;
    border: 2px solid rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.05);
    color: white;
    font-size: 14px;
    outline: none;
    transition: all 0.3s ease;
}

.newsletter-input:focus {
    border-color: #0066cc;
    background: rgba(255, 255, 255, 0.1);
}

.newsletter-input::placeholder {
    color: rgba(255, 255, 255, 0.5);
}

.newsletter-btn {
    background: linear-gradient(135deg, #0066cc 0%, #004999 100%);
    color: white;
    padding: 12px 24px;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.newsletter-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 102, 204, 0.4);
}

/* Footer Bottom */
.footer-bottom {
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding-top: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
    animation: fadeIn 0.8s ease-out 0.6s backwards;
}

.footer-copyright {
    color: rgba(255, 255, 255, 0.6);
    font-size: 14px;
}

.footer-copyright a {
    color: #0066cc;
    text-decoration: none;
    font-weight: 600;
}

.footer-copyright a:hover {
    color: #3399ff;
}

.footer-links-bottom {
    display: flex;
    gap: 25px;
    list-style: none;
}

.footer-links-bottom a {
    color: rgba(255, 255, 255, 0.6);
    text-decoration: none;
    font-size: 14px;
    transition: color 0.3s ease;
}

.footer-links-bottom a:hover {
    color: white;
}

/* Payment Methods */
.payment-methods {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-top: 20px;
}

.payment-icon {
    width: 50px;
    height: 32px;
    background: white;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 4px;
    transition: transform 0.3s ease;
}

.payment-icon:hover {
    transform: scale(1.05);
}

.payment-icon img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

/* Responsive */
@media (max-width: 1024px) {
    .footer-content {
        grid-template-columns: repeat(2, 1fr);
        gap: 40px;
    }

    .footer-brand {
        grid-column: 1 / -1;
    }
}

@media (max-width: 768px) {
    .footer-container {
        padding: 40px 20px 20px;
    }

    .footer-content {
        grid-template-columns: 1fr;
        gap: 35px;
        margin-bottom: 35px;
    }

    .footer-logo-text {
        font-size: 28px;
    }

    .footer-bottom {
        flex-direction: column;
        align-items: flex-start;
        text-align: center;
    }

    .footer-links-bottom {
        flex-wrap: wrap;
        gap: 15px;
    }

    .newsletter-form {
        flex-direction: column;
    }

    .newsletter-btn {
        width: 100%;
    }
}

/* Animations */
@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

<footer class="main-footer">
    <div class="footer-container">
        <div class="footer-content">
            <!-- Brand Section -->
            <div class="footer-brand">
                <a href="home.php" class="footer-logo">
                    <div class="footer-logo-icon">
                        <i class="fas fa-tint"></i>
                    </div>
                    <div class="footer-logo-text">MICU</div>
                </a>
                <p class="footer-description">
                    MICU adalah platform laundry online terpercaya yang menghubungkan Anda dengan mitra laundry profesional. 
                    Kami memberikan layanan antar jemput, harga terjangkau, dan kualitas terjamin.
                </p>
                <div class="footer-social">
                    <a href="#" class="social-link" title="Facebook">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="#" class="social-link" title="Instagram">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="#" class="social-link" title="Twitter">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="#" class="social-link" title="WhatsApp">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="footer-column">
                <h4 class="footer-column-title">Menu Cepat</h4>
                <ul class="footer-links">
                    <li><a href="home.php" class="footer-link"><i class="fas fa-home"></i>Beranda</a></li>
                    <li><a href="pesanan.php" class="footer-link"><i class="fas fa-shopping-bag"></i>Pesanan Saya</a></li>
                    <li><a href="profile.php" class="footer-link"><i class="fas fa-user"></i>Profil</a></li>
                    <li><a href="../partner/register.php" class="footer-link"><i class="fas fa-store"></i>Daftar Mitra</a></li>
                </ul>
            </div>

            <!-- Layanan -->
            <div class="footer-column">
                <h4 class="footer-column-title">Layanan</h4>
                <ul class="footer-links">
                    <li><a href="#" class="footer-link"><i class="fas fa-tshirt"></i>Cuci Kering</a></li>
                    <li><a href="#" class="footer-link"><i class="fas fa-iron"></i>Cuci Setrika</a></li>
                    <li><a href="#" class="footer-link"><i class="fas fa-bolt"></i>Express</a></li>
                    <li><a href="#" class="footer-link"><i class="fas fa-truck"></i>Antar Jemput</a></li>
                </ul>
            </div>

            <!-- Kontak -->
            <div class="footer-column">
                <h4 class="footer-column-title">Hubungi Kami</h4>
                <div class="contact-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <div class="contact-item-content">
                        Jl. Merdeka No. 123<br>
                        Cikarang, West Java<br>
                        Indonesia
                    </div>
                </div>
                <div class="contact-item">
                    <i class="fas fa-phone"></i>
                    <div class="contact-item-content">
                        <a href="tel:+6281234567890">+62 812-3456-7890</a>
                    </div>
                </div>
                <div class="contact-item">
                    <i class="fas fa-envelope"></i>
                    <div class="contact-item-content">
                        <a href="mailto:info@miculaundry.com">info@miculaundry.com</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Methods -->
        <div class="footer-brand" style="margin-bottom: 30px;">
            <h4 class="footer-column-title">Metode Pembayaran</h4>
            <div class="payment-methods">
                <div class="payment-icon" title="QRIS">
                    <i class="fas fa-qrcode" style="font-size: 20px; color: #ff6b6b;"></i>
                </div>
            </div>
        </div>

        <!-- Footer Bottom -->
        <div class="footer-bottom">
            <div class="footer-copyright">
                &copy; <?php echo date('Y'); ?> <a href="home.php">MICU Laundry</a>. All rights reserved.
            </div>
            <ul class="footer-links-bottom">
                <li><a href="#">Syarat & Ketentuan</a></li>
                <li><a href="#">Kebijakan Privasi</a></li>
                <li><a href="#">Bantuan</a></li>
            </ul>
        </div>
    </div>
</footer>

<script>
function handleNewsletter(event) {
    event.preventDefault();
    const email = event.target.querySelector('input[type="email"]').value;
    
    // Simple notification
    alert('Terima kasih! Email Anda (' + email + ') telah terdaftar untuk newsletter.');
    event.target.reset();
    
    return false;
}

// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            e.preventDefault();
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});
</script>

</body>
</html>