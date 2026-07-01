<?php
$page_title = "Security";
include 'includes/base.php'; ?>
<!-- ===== PRELOADER ===== -->
<div class="preloader" id="preloader">
    <div class="loader-ring"></div>
</div>

<?php include 'includes/navbar.php'; ?>

<!-- ===== PAGE HEADER ===== -->
<section class="page-header">
    <div class="container">
        <span class="badge"><i class="fas fa-shield-alt"></i> Security</span>
        <h1>Enterprise Security</h1>
        <p>Bank-grade security measures to protect your election data and infrastructure.</p>
    </div>
</section>

<!-- ===== SECURITY CONTENT ===== -->
<section class="security-page">
    <div class="container">
        <div class="security-grid">
            <div class="security-item">
                <i class="fas fa-lock"></i>
                <div>
                    <h4>AES-256 Encryption</h4>
                    <p>All data encrypted at rest and in transit using industry-standard AES-256 encryption.</p>
                </div>
            </div>
            <div class="security-item">
                <i class="fas fa-key"></i>
                <div>
                    <h4>Two-Factor Authentication</h4>
                    <p>Multi-factor authentication support including TOTP, SMS, and hardware security keys.</p>
                </div>
            </div>
            <div class="security-item">
                <i class="fas fa-database"></i>
                <div>
                    <h4>Daily Automated Backups</h4>
                    <p>Automated daily backups with point-in-time recovery and geographic redundancy.</p>
                </div>
            </div>
            <div class="security-item">
                <i class="fas fa-clipboard-list"></i>
                <div>
                    <h4>Comprehensive Audit Logs</h4>
                    <p>Complete audit trail of all system actions, user activities, and administrative changes.</p>
                </div>
            </div>
            <div class="security-item">
                <i class="fas fa-mobile-alt"></i>
                <div>
                    <h4>Device Verification</h4>
                    <p>Device fingerprinting and verification to prevent unauthorized access from unknown devices.</p>
                </div>
            </div>
            <div class="security-item">
                <i class="fas fa-gavel"></i>
                <div>
                    <h4>NDPA Compliance</h4>
                    <p>Fully compliant with the Nigerian Data Protection Act and international data protection standards.</p>
                </div>
            </div>
            <div class="security-item">
                <i class="fas fa-cloud"></i>
                <div>
                    <h4>Secure Cloud Infrastructure</h4>
                    <p>Enterprise-grade cloud hosting with DDoS protection, WAF, and SOC 2 compliant facilities.</p>
                </div>
            </div>
            <div class="security-item">
                <i class="fas fa-user-shield"></i>
                <div>
                    <h4>Role-Based Access Control</h4>
                    <p>Granular RBAC with least privilege principle and separation of duties enforcement.</p>
                </div>
            </div>
            <div class="security-item">
                <i class="fas fa-code-branch"></i>
                <div>
                    <h4>Secure Development Lifecycle</h4>
                    <p>Regular security audits, penetration testing, and vulnerability assessments.</p>
                </div>
            </div>
            <div class="security-item">
                <i class="fas fa-shield-virus"></i>
                <div>
                    <h4>Threat Detection & Response</h4>
                    <p>Real-time threat detection, SIEM integration, and incident response procedures.</p>
                </div>
            </div>
            <div class="security-item">
                <i class="fas fa-fingerprint"></i>
                <div>
                    <h4>Biometric Verification</h4>
                    <p>Optional biometric verification support for high-security operations and sensitive actions.</p>
                </div>
            </div>
            <div class="security-item">
                <i class="fas fa-file-signature"></i>
                <div>
                    <h4>Digital Signatures</h4>
                    <p>Cryptographic digital signatures for document authenticity and integrity verification.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>