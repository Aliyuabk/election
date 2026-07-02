<?php
// test-email.php - Test email configuration
require_once 'includes/db.php';
require_once '../config/config.php';

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Get email settings
$settings = [];
$stmt = $conn->query("SELECT * FROM system_settings");
while ($row = $stmt->fetch()) {
    $settings[$row['key']] = $row['value'];
}

function getSetting($key, $default = '') {
    global $settings;
    return isset($settings[$key]) ? $settings[$key] : $default;
}

// Check if SMTP is configured
$smtp_host = getSetting('smtp_host', '');
$smtp_port = getSetting('smtp_port', '587');
$smtp_username = getSetting('smtp_username', '');
$smtp_password = getSetting('smtp_password', '');
$sender_email = getSetting('sender_email', '');
$sender_name = getSetting('sender_name', '5G Election Guru');

$error = '';
$success = '';
$test_sent = false;

// Handle test email send
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test'])) {
    $test_email = $_POST['test_email'] ?? '';
    
    if (empty($test_email)) {
        $error = "Please enter a test email address.";
    } elseif (!filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Send test email
        $result = sendTestEmail($test_email, $sender_email, $sender_name, $smtp_host, $smtp_port, $smtp_username, $smtp_password);
        
        if ($result['success']) {
            $success = "Test email sent successfully to " . htmlspecialchars($test_email);
            $test_sent = true;
        } else {
            $error = "Failed to send test email: " . $result['message'];
        }
    }
}

// ============================================================
// SEND TEST EMAIL FUNCTION
// ============================================================
function sendTestEmail($to, $from_email, $from_name, $smtp_host, $smtp_port, $smtp_username, $smtp_password) {
    // Check if required settings are available
    if (empty($smtp_host) || empty($smtp_username) || empty($smtp_password)) {
        return ['success' => false, 'message' => 'SMTP settings are not configured. Please configure your email settings first.'];
    }
    
    if (empty($from_email)) {
        return ['success' => false, 'message' => 'Sender email is not configured.'];
    }
    
    // Email subject and body
    $subject = "Test Email from " . getSetting('site_name', '5G Election Guru');
    
    $html_body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Test Email</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f4f6fa; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .header { text-align: center; padding-bottom: 20px; border-bottom: 2px solid #4f9cf7; }
            .header h1 { color: #0b1a33; margin: 0; }
            .content { padding: 20px 0; }
            .content p { color: #1f3149; line-height: 1.6; }
            .footer { text-align: center; padding-top: 20px; border-top: 1px solid #eef3f8; color: #8b9bb5; font-size: 0.85rem; }
            .badge { display: inline-block; background: #4f9cf7; color: white; padding: 4px 16px; border-radius: 30px; font-size: 0.8rem; }
            .info-box { background: #f8faff; border-radius: 8px; padding: 12px 16px; margin: 12px 0; }
            .info-box .label { color: #6d83a5; font-size: 0.8rem; }
            .info-box .value { color: #0b1a33; font-weight: 500; }
            .success { color: #10b981; font-weight: 600; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>📧 Test Email</h1>
                <p><span class="badge">SMTP Test</span></p>
            </div>
            <div class="content">
                <p>Hello,</p>
                <p>This is a test email from <strong>' . htmlspecialchars(getSetting('site_name', '5G Election Guru')) . '</strong>.</p>
                <p>Your email configuration is working correctly!</p>
                
                <div class="info-box">
                    <div><span class="label">Sent From:</span> <span class="value">' . htmlspecialchars($from_name . ' <' . $from_email . '>') . '</span></div>
                    <div><span class="label">SMTP Host:</span> <span class="value">' . htmlspecialchars($smtp_host) . ':' . htmlspecialchars($smtp_port) . '</span></div>
                    <div><span class="label">Time:</span> <span class="value">' . date('Y-m-d H:i:s') . '</span></div>
                </div>
                
                <p style="text-align: center; margin-top: 20px;">
                    <span style="background: #d1fae5; color: #065f46; padding: 8px 20px; border-radius: 30px; font-weight: 500;">
                        ✅ Test Successful
                    </span>
                </p>
            </div>
            <div class="footer">
                <p>This is an automated test email from ' . htmlspecialchars(getSetting('site_name', '5G Election Guru')) . '.</p>
                <p>© ' . date('Y') . ' All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    $plain_body = "Test Email from " . getSetting('site_name', '5G Election Guru') . "\n\n";
    $plain_body .= "This is a test email to verify your SMTP configuration.\n\n";
    $plain_body .= "SMTP Host: " . $smtp_host . "\n";
    $plain_body .= "SMTP Port: " . $smtp_port . "\n";
    $plain_body .= "Sent From: " . $from_name . " <" . $from_email . ">\n";
    $plain_body .= "Time: " . date('Y-m-d H:i:s') . "\n\n";
    $plain_body .= "✅ Test Successful!";
    
    // Headers
    $headers = [];
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-type: text/html; charset=UTF-8";
    $headers[] = "From: " . $from_name . " <" . $from_email . ">";
    $headers[] = "Reply-To: " . $from_email;
    $headers[] = "X-Mailer: PHP/" . phpversion();
    $headers[] = "X-Priority: 3";
    
    // Try to send using PHP mail function with SMTP settings
    // Note: For SMTP, you need to use PHPMailer or SwiftMailer for proper SMTP support
    // This uses the built-in mail function which uses the system's sendmail
    
    // Check if PHPMailer is available
    $use_phpmailer = false;
    if (file_exists(__DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php')) {
        $use_phpmailer = true;
    }
    
    if ($use_phpmailer) {
        // Use PHPMailer for SMTP
        require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
        require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
        require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = $smtp_host;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtp_username;
            $mail->Password   = $smtp_password;
            $mail->SMTPSecure = getSetting('smtp_encryption', 'tls');
            $mail->Port       = $smtp_port;
            
            // Recipients
            $mail->setFrom($from_email, $from_name);
            $mail->addAddress($to);
            $mail->addReplyTo($from_email, $from_name);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html_body;
            $mail->AltBody = $plain_body;
            
            $mail->send();
            return ['success' => true, 'message' => 'Email sent successfully using PHPMailer'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'PHPMailer error: ' . $mail->ErrorInfo];
        }
    } else {
        // Fallback to mail() function
        $headers_string = implode("\r\n", $headers);
        
        // For SMTP, you might need to configure sendmail.ini or use a mail library.
        // This is a simple test using mail()
        if (mail($to, $subject, $html_body, $headers_string)) {
            return ['success' => true, 'message' => 'Email sent successfully using mail()'];
        } else {
            return ['success' => false, 'message' => 'Failed to send email using mail(). Check sendmail configuration.'];
        }
    }
}

include 'includes/base.php';
?>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/header.php'; ?>

<style>
/* ============================================================
   TEST EMAIL STYLES
   ============================================================ */

.test-email-container {
    max-width: 700px;
    margin: 0 auto;
}

.test-email-card {
    background: white;
    border-radius: 14px;
    padding: 30px;
    border: 1px solid #eef3f8;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
}

.test-email-card .card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding-bottom: 16px;
    border-bottom: 1px solid #eef3f8;
    margin-bottom: 20px;
}

.test-email-card .card-header i {
    font-size: 2rem;
    color: #4f9cf7;
}

.test-email-card .card-header h2 {
    font-size: 1.2rem;
    font-weight: 600;
    color: #0b1a33;
    margin: 0;
}

.test-email-card .card-header .status-badge {
    margin-left: auto;
    font-size: 0.7rem;
    padding: 4px 14px;
    border-radius: 30px;
    font-weight: 500;
}

.test-email-card .card-header .status-badge.configured {
    background: #d1fae5;
    color: #065f46;
}

.test-email-card .card-header .status-badge.not-configured {
    background: #fee2e2;
    color: #991b1b;
}

.smtp-info {
    background: #f8faff;
    border-radius: 10px;
    padding: 16px 20px;
    margin-bottom: 20px;
    border: 1px solid #eef3f8;
}

.smtp-info .info-row {
    display: flex;
    padding: 4px 0;
}

.smtp-info .info-row .label {
    min-width: 120px;
    color: #6d83a5;
    font-size: 0.8rem;
}

.smtp-info .info-row .value {
    color: #0b1a33;
    font-weight: 500;
    font-size: 0.85rem;
}

.test-form {
    margin-top: 20px;
}

.test-form .form-group {
    margin-bottom: 16px;
}

.test-form .form-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 500;
    color: #1f3149;
    margin-bottom: 6px;
}

.test-form .form-group .form-control {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid #dce6f0;
    border-radius: 10px;
    font-size: 0.9rem;
    background: white;
    color: #1f3149;
    transition: all 0.2s ease;
}

.test-form .form-group .form-control:focus {
    outline: none;
    border-color: #4f9cf7;
    box-shadow: 0 0 0 3px rgba(79, 156, 247, 0.1);
}

.test-form .form-actions {
    display: flex;
    gap: 12px;
    margin-top: 20px;
}

.test-form .form-actions .btn-primary {
    padding: 10px 28px;
    font-size: 0.9rem;
    border-radius: 10px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
    background: #4f9cf7;
    color: white;
}

.test-form .form-actions .btn-primary:hover {
    background: #3b82d6;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(79, 156, 247, 0.3);
}

.test-form .form-actions .btn-secondary {
    padding: 10px 28px;
    font-size: 0.9rem;
    border-radius: 10px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    border: 1px solid #dce6f0;
    background: #f0f5fe;
    color: #1f3d6b;
    text-decoration: none;
}

.test-form .form-actions .btn-secondary:hover {
    background: #e5edf9;
}

.alert {
    padding: 14px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 12px;
}

.alert i {
    font-size: 1.2rem;
    flex-shrink: 0;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.alert-success i { color: #10b981; }

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.alert-error i { color: #ef4444; }

.alert-info {
    background: #dbeafe;
    color: #1e40af;
    border: 1px solid #bfdbfe;
}

.alert-info i { color: #4f9cf7; }

/* Responsive */
@media (max-width: 768px) {
    .test-email-card {
        padding: 20px;
    }
    
    .smtp-info .info-row {
        flex-direction: column;
        gap: 2px;
        padding: 6px 0;
    }
    
    .smtp-info .info-row .label {
        min-width: auto;
        font-size: 0.7rem;
    }
    
    .test-form .form-actions {
        flex-direction: column;
    }
    
    .test-form .form-actions .btn-primary,
    .test-form .form-actions .btn-secondary {
        width: 100%;
        justify-content: center;
    }
    
    .test-email-card .card-header .status-badge {
        font-size: 0.6rem;
        padding: 2px 10px;
    }
}
</style>

<main class="main-content">
    <div class="test-email-container">
        <!-- ============================================================
        PAGE HEADER
        ============================================================ -->
        <div class="page-header">
            <div class="header-left">
                <h1>
                    <i class="fas fa-envelope" style="color:#4f9cf7;"></i>
                    Test Email Configuration
                    <span class="page-badge">SMTP Test</span>
                </h1>
                <p class="subtitle">Verify your email server settings by sending a test email</p>
            </div>
        </div>

        <!-- ============================================================
        ALERTS
        ============================================================ -->
        <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo $success; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <!-- ============================================================
        TEST EMAIL CARD
        ============================================================ -->
        <div class="test-email-card">
            <div class="card-header">
                <i class="fas fa-envelope"></i>
                <h2>SMTP Test</h2>
                <?php if (!empty($smtp_host) && !empty($smtp_username) && !empty($smtp_password)): ?>
                <span class="status-badge configured">
                    <i class="fas fa-check-circle"></i> Configured
                </span>
                <?php else: ?>
                <span class="status-badge not-configured">
                    <i class="fas fa-exclamation-triangle"></i> Not Configured
                </span>
                <?php endif; ?>
            </div>

            <!-- SMTP Information -->
            <div class="smtp-info">
                <div class="info-row">
                    <span class="label">SMTP Host</span>
                    <span class="value"><?php echo htmlspecialchars($smtp_host ?: 'Not configured'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">SMTP Port</span>
                    <span class="value"><?php echo htmlspecialchars($smtp_port ?: 'Not configured'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Username</span>
                    <span class="value"><?php echo htmlspecialchars($smtp_username ?: 'Not configured'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Sender Email</span>
                    <span class="value"><?php echo htmlspecialchars($sender_email ?: 'Not configured'); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Sender Name</span>
                    <span class="value"><?php echo htmlspecialchars($sender_name ?: 'Not configured'); ?></span>
                </div>
            </div>

            <!-- Test Form -->
            <form method="POST" class="test-form">
                <div class="form-group">
                    <label for="test_email">Send Test Email To <span class="required">*</span></label>
                    <input type="email" name="test_email" id="test_email" class="form-control" 
                           placeholder="Enter email address to send test to"
                           value="<?php echo isset($_POST['test_email']) ? htmlspecialchars($_POST['test_email']) : ''; ?>"
                           required>
                    <small>Enter a valid email address to receive the test email</small>
                </div>

                <div class="form-actions">
                    <button type="submit" name="send_test" class="btn-primary" 
                            <?php echo (empty($smtp_host) || empty($smtp_username) || empty($smtp_password)) ? 'disabled' : ''; ?>>
                        <i class="fas fa-paper-plane"></i> Send Test Email
                    </button>
                    <a href="system-settings.php?section=email" class="btn-secondary">
                        <i class="fas fa-cog"></i> Configure Email Settings
                    </a>
                </div>

                <?php if (empty($smtp_host) || empty($smtp_username) || empty($smtp_password)): ?>
                <div class="alert alert-info" style="margin-top:16px;">
                    <i class="fas fa-info-circle"></i>
                    Please configure your email settings before sending a test email.
                </div>
                <?php endif; ?>
            </form>

            <!-- Test Result -->
            <?php if ($test_sent): ?>
            <div style="margin-top:20px; padding:16px 20px; background:#f8faff; border-radius:10px; border:1px solid #d1fae5;">
                <div style="display:flex; align-items:center; gap:12px;">
                    <span style="font-size:2rem;">✅</span>
                    <div>
                        <div style="font-weight:600; color:#065f46;">Test Email Sent Successfully</div>
                        <div style="font-size:0.85rem; color:#6d83a5;">
                            The test email was sent to <strong><?php echo htmlspecialchars($_POST['test_email']); ?></strong>
                        </div>
                        <div style="font-size:0.75rem; color:#8b9bb5; margin-top:4px;">
                            Sent at: <?php echo date('Y-m-d H:i:s'); ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>