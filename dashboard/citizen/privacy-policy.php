<?php
// ============================================================
// CITIZEN PORTAL - PRIVACY POLICY
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/functions.php';

$page_title = 'Privacy Policy';
$current_page = '';

include '../includes/public-header.php';
?>

<style>
.policy-section {
    background: white;
    border-radius: 14px;
    padding: 30px;
    border: 1px solid #E5E7EB;
    max-width: 900px;
    margin: 0 auto;
}
.policy-section h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin-bottom: 16px;
}
.policy-section h3 {
    font-size: 1rem;
    font-weight: 600;
    margin-top: 24px;
    margin-bottom: 8px;
    color: #0F4C81;
}
.policy-section p {
    color: #4B5563;
    line-height: 1.8;
    font-size: 0.95rem;
    margin-bottom: 12px;
}
.policy-section ul {
    padding-left: 24px;
    color: #4B5563;
    line-height: 1.8;
    font-size: 0.95rem;
    margin-bottom: 12px;
}
.policy-section ul li {
    margin-bottom: 4px;
}
.policy-section .last-updated {
    color: #9CA3AF;
    font-size: 0.8rem;
    margin-top: 30px;
    padding-top: 16px;
    border-top: 1px solid #E5E7EB;
    text-align: center;
}
</style>

<div class="container">
    <h1 style="font-size:1.4rem;font-weight:700;margin-bottom:24px;text-align:center;">
        <i class="fas fa-shield-alt" style="color:#0F4C81;"></i> Privacy Policy
    </h1>

    <div class="policy-section">
        <h2>Privacy Policy for <?php echo APP_NAME; ?></h2>
        <p>
            At <?php echo APP_NAME; ?>, we are committed to protecting your privacy and ensuring 
            the security of your personal information. This Privacy Policy explains how we collect, 
            use, and safeguard your information when you use our website and services.
        </p>

        <h3>1. Information We Collect</h3>
        <p>We collect information that you voluntarily provide to us:</p>
        <ul>
            <li><strong>Contact Information:</strong> Name, email address, phone number when you contact us.</li>
            <li><strong>Usage Data:</strong> Information about how you use our website, including pages visited and time spent.</li>
            <li><strong>Device Information:</strong> Browser type, IP address, and device information for analytics.</li>
        </ul>

        <h3>2. How We Use Your Information</h3>
        <p>We use the information we collect to:</p>
        <ul>
            <li>Provide and maintain our services</li>
            <li>Respond to your inquiries and requests</li>
            <li>Improve our website and user experience</li>
            <li>Analyze usage patterns and trends</li>
            <li>Comply with legal obligations</li>
        </ul>

        <h3>3. Information Sharing</h3>
        <p>
            We do not sell, trade, or rent your personal information to third parties. We may share 
            your information with:
        </p>
        <ul>
            <li>Service providers who assist us in operating our website</li>
            <li>Law enforcement when required by law</li>
            <li>Third parties with your consent</li>
        </ul>

        <h3>4. Data Security</h3>
        <p>
            We implement appropriate technical and organizational measures to protect your personal 
            information against unauthorized access, alteration, disclosure, or destruction.
        </p>

        <h3>5. Cookies</h3>
        <p>
            Our website uses cookies to enhance your experience. Cookies are small text files stored 
            on your device. You can control cookie settings through your browser preferences.
        </p>

        <h3>6. Your Rights</h3>
        <p>You have the right to:</p>
        <ul>
            <li>Access your personal information</li>
            <li>Rectify inaccurate information</li>
            <li>Request deletion of your information</li>
            <li>Withdraw consent at any time</li>
        </ul>

        <h3>7. Third-Party Links</h3>
        <p>
            Our website may contain links to third-party websites. We are not responsible for the 
            privacy practices or content of these external sites.
        </p>

        <h3>8. Changes to This Policy</h3>
        <p>
            We may update this Privacy Policy from time to time. We will notify you of any changes 
            by posting the new policy on this page with an updated date.
        </p>

        <h3>9. Contact Us</h3>
        <p>
            If you have any questions about this Privacy Policy, please contact us at:
            <br>
            <strong>Email:</strong> <?php echo defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'info@example.com'; ?>
            <br>
            <strong>Phone:</strong> <?php echo defined('SMTP_FROM_PHONE') ? SMTP_FROM_PHONE : '+234 800 555 5555'; ?>
        </p>

        <div class="last-updated">
            Last Updated: <?php echo date('F d, Y'); ?>
        </div>
    </div>
</div>

<?php include '../includes/public-footer.php'; ?>