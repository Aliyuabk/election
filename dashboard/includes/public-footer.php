<?php
// ============================================================
// CITIZEN PORTAL - PUBLIC FOOTER
// ============================================================
?>

</main>

<!-- ============================================================
FOOTER
============================================================ -->
<footer class="public-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-col">
                <h4><i class="fas fa-vote-yea" style="color:var(--primary);"></i> <?php echo APP_NAME; ?></h4>
                <p>
                    Providing transparent access to published election results, 
                    polling unit information, and candidate profiles. 
                    Promoting transparency and accountability in the electoral process.
                </p>
                <div class="social-links">
                    <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                    <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                </div>
            </div>
            <div class="footer-col">
                <h4>Quick Links</h4>
                <a href="published-results.php">📊 Published Results</a><br>
                <a href="search-polling-units.php">🔍 Search Polling Units</a><br>
                <a href="candidates.php">👤 Candidates</a><br>
                <a href="maps.php">🗺️ Interactive Maps</a><br>
                <a href="statistics.php">📈 Statistics</a>
            </div>
            <div class="footer-col">
                <h4>Information</h4>
                <a href="election-information.php">📋 Election Guidelines</a><br>
                <a href="election-information.php#faq">❓ FAQ</a><br>
                <a href="election-information.php#calendar">📅 Election Calendar</a><br>
                <a href="contact.php">📧 Contact Us</a>
            </div>
            <div class="footer-col">
                <h4>Contact</h4>
                <p><i class="fas fa-envelope"></i> <?php echo SMTP_FROM_EMAIL ?? 'info@example.com'; ?></p>
                <p><i class="fas fa-phone"></i> <?php echo SMTP_FROM_PHONE ?? '+234 800 555 5555'; ?></p>
                <p><i class="fas fa-map-marker-alt"></i> <?php echo APP_ADDRESS ?? 'Nigeria'; ?></p>
                <p style="font-size:0.75rem;color:var(--gray-400);margin-top:4px;">
                    <i class="far fa-clock"></i> Mon-Fri: 8:00 AM - 6:00 PM
                </p>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="footer-links">
                <a href="privacy-policy.php">Privacy Policy</a>
                <a href="terms-of-use.php">Terms of Use</a>
                <a href="contact.php">Contact</a>
                <a href="<?php echo APP_URL; ?>/auth/login.php">Admin Login</a>
            </div>
            <p>
                &copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. 
                All rights reserved.
                <br>
                <span style="font-size:0.7rem;color:var(--gray-400);">
                    Empowering transparency through technology.
                </span>
            </p>
        </div>
    </div>
</footer>

<!-- ============================================================
SCRIPTS
============================================================ -->
<script>
// ============================================================
// NAV TOGGLE
// ============================================================
document.getElementById('navToggle').addEventListener('click', function() {
    var nav = document.getElementById('navMenu');
    nav.classList.toggle('open');
    var icon = this.querySelector('i');
    if (nav.classList.contains('open')) {
        icon.className = 'fas fa-times';
    } else {
        icon.className = 'fas fa-bars';
    }
});

// ============================================================
// CLOSE NAV ON LINK CLICK (mobile)
// ============================================================
document.querySelectorAll('#navMenu a').forEach(function(link) {
    link.addEventListener('click', function() {
        var nav = document.getElementById('navMenu');
        if (window.innerWidth <= 992 && nav.classList.contains('open')) {
            nav.classList.remove('open');
            document.getElementById('navToggle').querySelector('i').className = 'fas fa-bars';
        }
    });
});

// ============================================================
// PRELOADER
// ============================================================
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() {
            preloader.style.display = 'none';
        }, 500);
    }
});

// ============================================================
// DROPDOWN MENU CLOSE ON OUTSIDE CLICK
// ============================================================
document.addEventListener('click', function(event) {
    var nav = document.getElementById('navMenu');
    var toggle = document.getElementById('navToggle');
    if (window.innerWidth <= 992 && nav.classList.contains('open')) {
        if (!toggle.contains(event.target) && !nav.contains(event.target)) {
            nav.classList.remove('open');
            toggle.querySelector('i').className = 'fas fa-bars';
        }
    }
});

// ============================================================
// SMOOTH SCROLL FOR ANCHOR LINKS
// ============================================================
document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
    anchor.addEventListener('click', function(e) {
        var target = document.querySelector(this.getAttribute('href'));
        if (target) {
            e.preventDefault();
            var headerHeight = document.querySelector('.public-header').offsetHeight;
            var targetPosition = target.getBoundingClientRect().top + window.pageYOffset - headerHeight - 20;
            window.scrollTo({
                top: targetPosition,
                behavior: 'smooth'
            });
        }
    });
});

// ============================================================
// ACTIVE NAV LINK HIGHLIGHTING
// ============================================================
(function() {
    var currentPath = window.location.pathname;
    var filename = currentPath.substring(currentPath.lastIndexOf('/') + 1);
    
    document.querySelectorAll('#navMenu a').forEach(function(link) {
        var href = link.getAttribute('href');
        if (href === filename || (filename === '' && href === 'index.php')) {
            link.classList.add('active');
        }
    });
})();
</script>

</body>
</html>