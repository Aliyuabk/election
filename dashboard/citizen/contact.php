<?php
// ============================================================
// CITIZEN PORTAL - CONTACT
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/functions.php';

$page_title = 'Contact Us';
$current_page = 'contact';

// Handle form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    if (empty($name)) {
        $error = 'Please enter your name.';
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (empty($subject)) {
        $error = 'Please enter a subject.';
    } elseif (empty($message)) {
        $error = 'Please enter your message.';
    } else {
        // Send email
        $to = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'info@example.com';
        $email_subject = "Contact Form: $subject";
        $email_body = "
            <h2>Contact Form Message</h2>
            <p><strong>Name:</strong> $name</p>
            <p><strong>Email:</strong> $email</p>
            <p><strong>Subject:</strong> $subject</p>
            <p><strong>Message:</strong></p>
            <p>" . nl2br(htmlspecialchars($message)) . "</p>
        ";
        
        $result = sendEmail($to, $email_subject, $email_body);
        
        if ($result['success']) {
            $success = 'Your message has been sent successfully. We will get back to you soon.';
        } else {
            $error = 'Failed to send your message. Please try again later.';
            error_log("Contact form error: " . ($result['message'] ?? 'Unknown error'));
        }
    }
}

include '../includes/public-header.php';
?>

<style>
.contact-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
}
.contact-card {
    background: white;
    border-radius: 14px;
    padding: 24px;
    border: 1px solid #E5E7EB;
}
.contact-card .card-title {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.contact-card .card-title i {
    color: #0F4C81;
}

.contact-info-item {
    display: flex;
    gap: 14px;
    padding: 12px 0;
    border-bottom: 1px solid #F3F4F6;
}
.contact-info-item:last-child {
    border-bottom: none;
}
.contact-info-item .icon {
    width: 40px;
    height: 40px;
    background: #F3F4F6;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #0F4C81;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.contact-info-item .info .label {
    font-size: 0.7rem;
    color: #6B7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.contact-info-item .info .value {
    font-weight: 500;
    font-size: 0.95rem;
}
.contact-info-item .info .value a {
    color: #0F4C81;
    text-decoration: none;
}
.contact-info-item .info .value a:hover {
    text-decoration: underline;
}

.contact-form .form-group {
    margin-bottom: 16px;
}
.contact-form .form-group label {
    display: block;
    font-weight: 600;
    font-size: 0.82rem;
    color: #374151;
    margin-bottom: 4px;
}
.contact-form .form-group label .required {
    color: #EF4444;
}
.contact-form .form-group input,
.contact-form .form-group textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid #E5E7EB;
    border-radius: 10px;
    font-family: 'Inter', sans-serif;
    font-size: 0.85rem;
    transition: border-color 0.2s;
}
.contact-form .form-group input:focus,
.contact-form .form-group textarea:focus {
    outline: none;
    border-color: #0F4C81;
}
.contact-form .form-group textarea {
    min-height: 120px;
    resize: vertical;
}
.contact-form .btn-submit {
    padding: 12px 32px;
    background: #0F4C81;
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: background 0.3s;
}
.contact-form .btn-submit:hover {
    background: #1a6db5;
}
.contact-form .btn-submit:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.social-links-large {
    display: flex;
    gap: 12px;
    margin-top: 12px;
}
.social-links-large a {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #F3F4F6;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    color: #6B7280;
    transition: all 0.2s;
    text-decoration: none;
}
.social-links-large a:hover {
    background: #0F4C81;
    color: white;
}

.map-container {
    margin-top: 16px;
    border-radius: 10px;
    overflow: hidden;
    height: 200px;
    background: #F3F4F6;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #9CA3AF;
}

.alert {
    padding: 12px 16px;
    border-radius: 10px;
    font-size: 0.85rem;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-success {
    background: #ECFDF5;
    color: #065F46;
    border: 1px solid #A7F3D0;
}
.alert-error {
    background: #FEF2F2;
    color: #DC2626;
    border: 1px solid #FECACA;
}

@media (max-width: 768px) {
    .contact-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="container">
    <h1 style="font-size:1.4rem;font-weight:700;margin-bottom:6px;">
        <i class="fas fa-envelope" style="color:#0F4C81;"></i> Contact Us
    </h1>
    <p style="color:#6B7280;margin-bottom:24px;font-size:0.9rem;">
        Have questions or feedback? Reach out to us using the form below.
    </p>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <div><?php echo htmlspecialchars($error); ?></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <div><?php echo htmlspecialchars($success); ?></div>
        </div>
    <?php endif; ?>

    <div class="contact-grid">
        <!-- Contact Form -->
        <div class="contact-card">
            <div class="card-title">
                <i class="fas fa-paper-plane"></i> Send a Message
            </div>
            <form method="POST" class="contact-form" id="contactForm">
                <div class="form-group">
                    <label>Full Name <span class="required">*</span></label>
                    <input type="text" name="name" placeholder="Your full name" 
                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Email Address <span class="required">*</span></label>
                    <input type="email" name="email" placeholder="your@email.com" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Subject <span class="required">*</span></label>
                    <input type="text" name="subject" placeholder="Message subject" 
                           value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Message <span class="required">*</span></label>
                    <textarea name="message" placeholder="Your message..." required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                </div>
                <button type="submit" class="btn-submit" id="submitBtn">
                    <i class="fas fa-paper-plane"></i> Send Message
                </button>
            </form>
        </div>

        <!-- Contact Info -->
        <div>
            <div class="contact-card">
                <div class="card-title">
                    <i class="fas fa-address-card"></i> Contact Information
                </div>

                <div class="contact-info-item">
                    <div class="icon"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="info">
                        <div class="label">Office Address</div>
                        <div class="value">Nigeria</div>
                    </div>
                </div>

                <div class="contact-info-item">
                    <div class="icon"><i class="fas fa-envelope"></i></div>
                    <div class="info">
                        <div class="label">Email</div>
                        <div class="value">
                            <a href="mailto:<?php echo defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'info@example.com'; ?>">
                                <?php echo defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'info@example.com'; ?>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="contact-info-item">
                    <div class="icon"><i class="fas fa-phone"></i></div>
                    <div class="info">
                        <div class="label">Phone</div>
                        <div class="value">
                            <a href="tel:<?php echo defined('SMTP_FROM_PHONE') ? SMTP_FROM_PHONE : '+2348005555555'; ?>">
                                <?php echo defined('SMTP_FROM_PHONE') ? SMTP_FROM_PHONE : '+234 800 555 5555'; ?>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="contact-info-item">
                    <div class="icon"><i class="fas fa-clock"></i></div>
                    <div class="info">
                        <div class="label">Working Hours</div>
                        <div class="value">Monday - Friday: 8:00 AM - 6:00 PM</div>
                    </div>
                </div>

                <div style="margin-top:12px;">
                    <div class="label" style="font-size:0.7rem;color:#6B7280;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">
                        Connect With Us
                    </div>
                    <div class="social-links-large">
                        <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>

                <div class="map-container">
                    <i class="fas fa-map" style="font-size:2rem;"></i>
                    <span style="margin-left:8px;">Google Map Location</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('contactForm').addEventListener('submit', function(e) {
    var btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
});

// Remove spinner if form submission fails (backup)
window.addEventListener('load', function() {
    var btn = document.getElementById('submitBtn');
    if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Message';
    }
});
</script>

<?php include '../includes/public-footer.php'; ?>