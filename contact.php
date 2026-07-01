<?php
$page_title = "Contact";
include 'includes/base.php'; ?>
<!-- ===== PRELOADER ===== -->
<div class="preloader" id="preloader">
    <div class="loader-ring"></div>
</div>

<?php include 'includes/navbar.php'; ?>

<!-- ===== PAGE HEADER ===== -->
<section class="page-header">
    <div class="container">
        <span class="badge"><i class="fas fa-envelope"></i> Get in Touch</span>
        <h1>Contact Us</h1>
        <p>Have questions? Reach out to our team and we'll get back to you promptly.</p>
    </div>
</section>

<!-- ===== CONTACT CONTENT ===== -->
<section class="contact-page">
    <div class="container">
        <div class="contact-grid">
            <div class="contact-info">
                <h3>Let's Talk</h3>
                <p>Our team is ready to help you with any questions about 5G Election Guru. Reach out to us via any of the channels below.</p>
                
                <div class="contact-detail">
                    <i class="fas fa-envelope"></i>
                    <div>
                        <strong>Email</strong>
                        <p style="margin:0; color:#475569;">support@5gguru.ng</p>
                    </div>
                </div>
                <div class="contact-detail">
                    <i class="fas fa-phone"></i>
                    <div>
                        <strong>Phone</strong>
                        <p style="margin:0; color:#475569;">+234 800 555 5G55</p>
                    </div>
                </div>
                <div class="contact-detail">
                    <i class="fas fa-map-marker-alt"></i>
                    <div>
                        <strong>Address</strong>
                        <p style="margin:0; color:#475569;">Lagos, Nigeria</p>
                    </div>
                </div>
                <div class="contact-detail">
                    <i class="fas fa-clock"></i>
                    <div>
                        <strong>Working Hours</strong>
                        <p style="margin:0; color:#475569;">Monday - Friday: 8:00 AM - 6:00 PM WAT</p>
                    </div>
                </div>
                
                <div style="margin-top: 32px; display:flex; gap:16px;">
                    <a href="#" style="color:#0F4C81; font-size:1.6rem;"><i class="fab fa-twitter"></i></a>
                    <a href="#" style="color:#0F4C81; font-size:1.6rem;"><i class="fab fa-linkedin"></i></a>
                    <a href="#" style="color:#0F4C81; font-size:1.6rem;"><i class="fab fa-github"></i></a>
                    <a href="#" style="color:#0F4C81; font-size:1.6rem;"><i class="fab fa-youtube"></i></a>
                </div>
            </div>
            
            <div class="contact-form">
                <form>
                    <input type="text" placeholder="Your Full Name" required>
                    <input type="email" placeholder="Your Email Address" required>
                    <input type="text" placeholder="Subject">
                    <textarea placeholder="Your Message" required></textarea>
                    <button type="submit" class="btn btn-primary" style="width:100%;">Send Message</button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>