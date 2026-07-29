<?php
// ============================================================
// CITIZEN PORTAL - TERMS OF USE
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/functions.php';

$page_title = 'Terms of Use';
$current_page = '';

include '../includes/public-header.php';
?>

<style>
.terms-section {
    background: white;
    border-radius: 14px;
    padding: 30px;
    border: 1px solid #E5E7EB;
    max-width: 900px;
    margin: 0 auto;
}
.terms-section h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin-bottom: 16px;
}
.terms-section h3 {
    font-size: 1rem;
    font-weight: 600;
    margin-top: 24px;
    margin-bottom: 8px;
    color: #0F4C81;
}
.terms-section p {
    color: #4B5563;
    line-height: 1.8;
    font-size: 0.95rem;
    margin-bottom: 12px;
}
.terms-section ul {
    padding-left: 24px;
    color: #4B5563;
    line-height: 1.8;
    font-size: 0.95rem;
    margin-bottom: 12px;
}
.terms-section ul li {
    margin-bottom: 4px;
}
.terms-section .last-updated {
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
        <i class="fas fa-file-contract" style="color:#0F4C81;"></i> Terms of Use
    </h1>

    <div class="terms-section">
        <h2>Terms of Use for <?php echo APP_NAME; ?></h2>
        <p>
            By using <?php echo APP_NAME; ?>, you agree to comply with and be bound by the following 
            Terms of Use. Please read these terms carefully before using our website and services.
        </p>

        <h3>1. Acceptance of Terms</h3>
        <p>
            By accessing and using this website, you accept and agree to be bound by these Terms of Use. 
            If you do not agree to these terms, please do not use our website.
        </p>

        <h3>2. Description of Service</h3>
        <p>
            <?php echo APP_NAME; ?> provides a platform for publishing and viewing election results, 
            polling unit information, candidate profiles, and other election-related information. 
            All information is provided for informational purposes only.
        </p>

        <h3>3. User Responsibilities</h3>
        <p>Users of this website agree to:</p>
        <ul>
            <li>Use the website for lawful purposes only</li>
            <li>Not attempt to gain unauthorized access to any part of the website</li>
            <li>Not interfere with the proper functioning of the website</li>
            <li>Not misuse any information obtained from the website</li>
            <li>Respect the intellectual property rights of others</li>
        </ul>

        <h3>4. Intellectual Property</h3>
        <p>
            All content on this website, including text, graphics, logos, and software, is the property 
            of <?php echo APP_NAME; ?> or its content suppliers and is protected by copyright and other 
            intellectual property laws.
        </p>

        <h3>5. Disclaimer of Warranties</h3>
        <p>
            The information on this website is provided "as is" without any warranties of any kind, 
            express or implied. While we strive to provide accurate and up-to-date information, we 
            do not warrant the completeness, accuracy, or reliability of any information on this website.
        </p>

        <h3>6. Limitation of Liability</h3>
        <p>
            <?php echo APP_NAME; ?> shall not be liable for any damages arising from the use of this 
            website, including but not limited to direct, indirect, incidental, consequential, or 
            punitive damages.
        </p>

        <h3>7. Third-Party Links</h3>
        <p>
            This website may contain links to third-party websites. These links are provided for 
            convenience and do not imply endorsement. We are not responsible for the content or 
            practices of external sites.
        </p>

        <h3>8. Modifications</h3>
        <p>
            We reserve the right to modify these Terms of Use at any time. Changes will be effective 
            immediately upon posting. Your continued use of the website constitutes acceptance of 
            the modified terms.
        </p>

        <h3>9. Termination</h3>
        <p>
            We reserve the right to terminate or suspend access to our website at any time, with or 
            without cause, and without prior notice.
        </p>

        <h3>10. Governing Law</h3>
        <p>
            These Terms of Use shall be governed by and construed in accordance with the laws of 
            the Federal Republic of Nigeria.
        </p>

        <h3>11. Contact Us</h3>
        <p>
            If you have any questions about these Terms of Use, please contact us at:
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