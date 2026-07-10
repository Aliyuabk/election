<?php
// ============================================================
// 5G ELECTION GURU - FUNCTIONS
// ============================================================

require_once __DIR__ . '/../email/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// ============================================================
// 1. EMAIL FUNCTIONS
// ============================================================

function sendEmail($to, $subject, $body, $altBody = '') {
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);
        
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = $altBody ?: strip_tags($body);
        
        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully'];
    } catch (Exception $e) {
        error_log("Email send failed to $to: " . $mail->ErrorInfo);
        return ['success' => false, 'message' => $mail->ErrorInfo];
    }
}

// ============================================================
// 2. SEND BROADCAST EMAILS
// ============================================================

function sendBroadcastEmails($recipients, $subject, $message, $sender_name = '') {
    $sent_count = 0;
    $failed_count = 0;
    $errors = [];
    
    if (empty($sender_name)) {
        $sender_name = APP_NAME;
    }
    
    foreach ($recipients as $recipient) {
        $email = is_array($recipient) ? ($recipient['email'] ?? '') : $recipient;
        $name = is_array($recipient) ? ($recipient['full_name'] ?? $recipient['name'] ?? 'User') : 'User';
        
        if (empty($email)) {
            continue;
        }
        
        $email_body = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background: #f4f6fa; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; padding: 40px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
                .header { text-align: center; margin-bottom: 30px; }
                .header h1 { color: #0F4C81; margin: 0; }
                .message-box { background: #F8FAFC; padding: 20px; border-radius: 12px; margin: 20px 0; border-left: 4px solid #0F4C81; }
                .footer { text-align: center; color: #64748B; font-size: 12px; margin-top: 30px; border-top: 1px solid #E2E8F0; padding-top: 20px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>📢 ' . APP_NAME . '</h1>
                    <p style="color: #64748B;">Broadcast Message</p>
                </div>
                <p>Hello ' . htmlspecialchars($name) . ',</p>
                <div class="message-box">
                    <p>' . nl2br(htmlspecialchars($message)) . '</p>
                </div>
                <p style="color: #64748B; font-size: 14px;">
                    This is an automated message from ' . APP_NAME . '.
                    Please do not reply to this email.
                </p>
                <div class="footer">
                    &copy; ' . date('Y') . ' ' . APP_NAME . '. All rights reserved.
                </div>
            </div>
        </body>
        </html>
        ';
        
        $result = sendEmail($email, $subject, $email_body, strip_tags($message));
        
        if ($result['success']) {
            $sent_count++;
        } else {
            $failed_count++;
            $errors[] = $email . ': ' . $result['message'];
        }
    }
    
    return [
        'success' => $sent_count > 0,
        'sent' => $sent_count,
        'failed' => $failed_count,
        'errors' => $errors,
        'total' => count($recipients)
    ];
}

// ============================================================
// 3. GET BROADCAST RECIPIENTS
// ============================================================

function getBroadcastRecipients($tenant_id, $target_audience, $target_ids = []) {
    $db = getDB();
    $recipients = [];
    
    try {
        if ($target_audience === 'all') {
            $stmt = $db->prepare("
                SELECT email, full_name FROM users 
                WHERE tenant_id = ? AND status = 'active' 
                AND email IS NOT NULL AND email != ''
                AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
            ");
            $stmt->execute([$tenant_id]);
            $recipients = $stmt->fetchAll();
        } elseif ($target_audience === 'state' && !empty($target_ids)) {
            $placeholders = implode(',', array_fill(0, count($target_ids), '?'));
            $stmt = $db->prepare("
                SELECT u.email, u.full_name FROM users u
                WHERE u.tenant_id = ? AND u.status = 'active'
                AND u.jurisdiction_id IN ($placeholders)
                AND u.email IS NOT NULL AND u.email != ''
                AND (u.deleted_at IS NULL OR u.deleted_at = '0000-00-00 00:00:00')
            ");
            $stmt->execute(array_merge([$tenant_id], $target_ids));
            $recipients = $stmt->fetchAll();
        } elseif ($target_audience === 'role_specific' && !empty($target_ids)) {
            $placeholders = implode(',', array_fill(0, count($target_ids), '?'));
            $stmt = $db->prepare("
                SELECT u.email, u.full_name FROM users u
                WHERE u.tenant_id = ? AND u.status = 'active'
                AND u.role_id IN ($placeholders)
                AND u.email IS NOT NULL AND u.email != ''
                AND (u.deleted_at IS NULL OR u.deleted_at = '0000-00-00 00:00:00')
            ");
            $stmt->execute(array_merge([$tenant_id], $target_ids));
            $recipients = $stmt->fetchAll();
        }
    } catch (Exception $e) {
        error_log("Get recipients error: " . $e->getMessage());
    }
    
    return $recipients;
}

function sendOTPEmail($email, $otp, $name = '') {
    $subject = 'Your OTP Code - ' . APP_NAME;
    
    $body = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; background: #f4f6fa; padding: 20px; }
            .container { max-width: 500px; margin: 0 auto; background: white; border-radius: 12px; padding: 40px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
            .header { text-align: center; margin-bottom: 30px; }
            .header h1 { color: #0F4C81; margin: 0; }
            .otp-box { background: #F8FAFC; padding: 20px; border-radius: 12px; text-align: center; margin: 20px 0; border: 2px dashed #2563EB; }
            .otp-code { font-size: 36px; font-weight: 700; color: #0F4C81; letter-spacing: 8px; font-family: monospace; }
            .footer { text-align: center; color: #64748B; font-size: 12px; margin-top: 30px; border-top: 1px solid #E2E8F0; padding-top: 20px; }
            .expiry { color: #EF4444; font-weight: 600; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🔐 ' . APP_NAME . '</h1>
                <p style="color: #64748B;">One-Time Password Verification</p>
            </div>
            <p>Hello ' . htmlspecialchars($name) . ',</p>
            <p>You requested a one-time password (OTP) for authentication. Please use the code below:</p>
            <div class="otp-box">
                <div class="otp-code">' . $otp . '</div>
            </div>
            <p style="color: #64748B;">This OTP will expire in <span class="expiry">5 minutes</span>.</p>
            <p style="color: #64748B; font-size: 14px;">If you did not request this, please ignore this email.</p>
            <div class="footer">
                &copy; ' . date('Y') . ' ' . APP_NAME . '. All rights reserved.
            </div>
        </div>
    </body>
    </html>
    ';
    
    return sendEmail($email, $subject, $body);
}

function sendPasswordResetEmail($email, $resetLink, $name = '') {
    $subject = 'Password Reset - ' . APP_NAME;
    
    $body = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; background: #f4f6fa; padding: 20px; }
            .container { max-width: 500px; margin: 0 auto; background: white; border-radius: 12px; padding: 40px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
            .header { text-align: center; margin-bottom: 30px; }
            .header h1 { color: #0F4C81; margin: 0; }
            .btn { display: inline-block; padding: 12px 32px; background: #0F4C81; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; }
            .footer { text-align: center; color: #64748B; font-size: 12px; margin-top: 30px; border-top: 1px solid #E2E8F0; padding-top: 20px; }
            .warning { background: #FEF2F2; padding: 12px; border-radius: 8px; color: #DC2626; font-size: 14px; margin: 16px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🔐 ' . APP_NAME . '</h1>
                <p style="color: #64748B;">Password Reset Request</p>
            </div>
            <p>Hello ' . htmlspecialchars($name) . ',</p>
            <p>We received a request to reset your password. Click the button below to reset it:</p>
            <div style="text-align: center; margin: 30px 0;">
                <a href="' . $resetLink . '" class="btn">Reset Password</a>
            </div>
            <div class="warning">
                <strong>⚠️ Security Notice:</strong> This link will expire in 1 hour. 
                If you did not request this, please ignore this email.
            </div>
            <div class="footer">
                &copy; ' . date('Y') . ' ' . APP_NAME . '. All rights reserved.
            </div>
        </div>
    </body>
    </html>
    ';
    
    return sendEmail($email, $subject, $body);
}

// ============================================================
// 2. AUTHENTICATION & SECURITY FUNCTIONS
// ============================================================

function generateOTP($length = 6) {
    return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function generateRandomToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function generateCaptcha($length = 6) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $captcha = '';
    for ($i = 0; $i < $length; $i++) {
        $captcha .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $captcha;
}

// ============================================================
// 3. DEVICE & REQUEST FUNCTIONS
// ============================================================

function getClientIP() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

function getUserAgent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
}

function generateDeviceFingerprint() {
    // Safe hostname detection without php_uname
    $hostname = '';
    
    // Method 1: Server variables
    if (isset($_SERVER['SERVER_NAME']) && !empty($_SERVER['SERVER_NAME'])) {
        $hostname = $_SERVER['SERVER_NAME'];
    }
    
    // Method 2: HTTP Host
    if (empty($hostname) && isset($_SERVER['HTTP_HOST']) && !empty($_SERVER['HTTP_HOST'])) {
        $hostname = $_SERVER['HTTP_HOST'];
    }
    
    // Method 3: gethostname if available
    if (empty($hostname) && function_exists('gethostname')) {
        $hostname = gethostname() ?: '';
    }
    
    // Method 4: Fallback
    if (empty($hostname)) {
        $hostname = 'localhost';
    }
    
    $data = getClientIP() . getUserAgent() . $hostname;
    return hash('sha256', $data);
}

// ============================================================
// 4. DATABASE FUNCTIONS
// ============================================================

function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    return $pdo;
}

// ============================================================
// 5. TABLE MANAGEMENT FUNCTIONS
// ============================================================

function ensureTablesExist() {
    $db = getDB();
    
    // Fix activity_logs table - change ENUM to VARCHAR
    try {
        $db->query("SELECT 1 FROM activity_logs LIMIT 1");
        try {
            $db->exec("ALTER TABLE activity_logs MODIFY COLUMN activity_type VARCHAR(50) NOT NULL");
        } catch (Exception $e) {
            // Column might already be correct
        }
    } catch (PDOException $e) {
        $db->exec("CREATE TABLE IF NOT EXISTS `activity_logs` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `activity_type` VARCHAR(50) NOT NULL,
            `description` VARCHAR(500) NOT NULL,
            `entity_type` VARCHAR(50) NULL,
            `entity_id` BIGINT UNSIGNED NULL,
            `ip_address` VARCHAR(45) NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_activity_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    
    // Fix security_events table - change ENUM to VARCHAR
    try {
        $db->query("SELECT 1 FROM security_events LIMIT 1");
        try {
            $db->exec("ALTER TABLE security_events MODIFY COLUMN event_type VARCHAR(50) NOT NULL");
        } catch (Exception $e) {
            // Column might already be correct
        }
    } catch (PDOException $e) {
        $db->exec("CREATE TABLE IF NOT EXISTS `security_events` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` BIGINT UNSIGNED NULL,
            `event_type` VARCHAR(50) NOT NULL,
            `description` TEXT NOT NULL,
            `ip_address` VARCHAR(45) NULL,
            `device_id` VARCHAR(255) NULL,
            `risk_score` TINYINT UNSIGNED NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_security_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    
    // Fix login_attempts table
    try {
        $db->query("SELECT 1 FROM login_attempts LIMIT 1");
        try {
            $db->exec("ALTER TABLE login_attempts MODIFY COLUMN attempt_type VARCHAR(50) NOT NULL DEFAULT 'login'");
        } catch (Exception $e) {
            // Column might not exist or already correct
        }
    } catch (PDOException $e) {
        $db->exec("CREATE TABLE IF NOT EXISTS `login_attempts` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` BIGINT UNSIGNED NULL,
            `email` VARCHAR(255) NULL,
            `ip_address` VARCHAR(45) NOT NULL,
            `user_agent` TEXT NULL,
            `attempt_type` VARCHAR(50) NOT NULL DEFAULT 'login',
            `success` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_login_attempts_ip` (`ip_address`),
            INDEX `idx_login_attempts_email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    
    // Check password_resets table
    try {
        $db->query("SELECT 1 FROM password_resets LIMIT 1");
    } catch (PDOException $e) {
        $db->exec("CREATE TABLE IF NOT EXISTS `password_resets` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `token` VARCHAR(255) NOT NULL,
            `expires_at` TIMESTAMP NOT NULL,
            `used` TINYINT(1) NOT NULL DEFAULT 0,
            `used_at` TIMESTAMP NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_pw_resets_user` (`user_id`),
            INDEX `idx_pw_resets_token` (`token`(255))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    
    // Check otp_verifications table
    try {
        $db->query("SELECT 1 FROM otp_verifications LIMIT 1");
    } catch (PDOException $e) {
        $db->exec("CREATE TABLE IF NOT EXISTS `otp_verifications` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `otp_code` VARCHAR(10) NOT NULL,
            `type` VARCHAR(50) NOT NULL DEFAULT 'login',
            `channel` VARCHAR(20) NOT NULL DEFAULT 'email',
            `expires_at` TIMESTAMP NOT NULL,
            `used` TINYINT(1) NOT NULL DEFAULT 0,
            `used_at` TIMESTAMP NULL,
            `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_otp_user` (`user_id`),
            INDEX `idx_otp_code` (`otp_code`),
            INDEX `idx_otp_expires` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    
    // Check user_sessions table
    try {
        $db->query("SELECT 1 FROM user_sessions LIMIT 1");
    } catch (PDOException $e) {
        $db->exec("CREATE TABLE IF NOT EXISTS `user_sessions` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `token` VARCHAR(500) NOT NULL,
            `device_id` VARCHAR(255) NULL,
            `device_type` VARCHAR(20) NOT NULL DEFAULT 'web',
            `device_name` VARCHAR(255) NULL,
            `ip_address` VARCHAR(45) NULL,
            `user_agent` TEXT NULL,
            `expires_at` TIMESTAMP NOT NULL,
            `last_activity_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_sessions_user` (`user_id`),
            INDEX `idx_sessions_token` (`token`(255))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

// ============================================================
// 6. LOGGING FUNCTIONS
// ============================================================

function logActivity($userId, $type, $description, $entityType = null, $entityId = null) {
    try {
        ensureTablesExist();
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, activity_type, description, entity_type, entity_id, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $type, $description, $entityType, $entityId, getClientIP()]);
        return true;
    } catch (Exception $e) {
        error_log("Activity logging failed: " . $e->getMessage());
        return false;
    }
}

function logSecurityEvent($userId, $eventType, $description, $riskScore = null) {
    try {
        ensureTablesExist();
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO security_events (user_id, event_type, description, ip_address, device_id, risk_score, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $eventType, $description, getClientIP(), generateDeviceFingerprint(), $riskScore]);
        return true;
    } catch (Exception $e) {
        error_log("Security event logging failed: " . $e->getMessage());
        return false;
    }
}

function logLoginAttempt($userId, $email, $success) {
    try {
        ensureTablesExist();
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO login_attempts (user_id, email, ip_address, user_agent, attempt_type, success, created_at) VALUES (?, ?, ?, ?, 'login', ?, NOW())");
        $stmt->execute([$userId, $email, getClientIP(), getUserAgent(), $success ? 1 : 0]);
        return true;
    } catch (Exception $e) {
        error_log("Login attempt logging failed: " . $e->getMessage());
        return false;
    }
}

// ============================================================
// 7. AUTHENTICATION DATABASE FUNCTIONS
// ============================================================

function getLoginAttempts($email, $ip) {
    try {
        ensureTablesExist();
        $db = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM login_attempts WHERE (email = ? OR ip_address = ?) AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND success = 0");
        $stmt->execute([$email, $ip]);
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}

function isAccountLocked($email) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT locked_until FROM users WHERE email = ? AND locked_until > NOW()");
        $stmt->execute([$email]);
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        return false;
    }
}

function lockAccount($email) {
    try {
        $db = getDB();
        $stmt = $db->prepare("UPDATE users SET locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE email = ?");
        $stmt->execute([LOCKOUT_TIME / 60, $email]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function saveOTP($userId, $otp, $type = 'login', $channel = 'email') {
    try {
        ensureTablesExist();
        $db = getDB();
        $expires = date('Y-m-d H:i:s', time() + OTP_EXPIRY);
        
        $stmt = $db->prepare("INSERT INTO otp_verifications (user_id, otp_code, type, channel, expires_at, used, attempts) VALUES (?, ?, ?, ?, ?, 0, 0)");
        $stmt->execute([$userId, $otp, $type, $channel, $expires]);
        return true;
    } catch (Exception $e) {
        error_log("OTP save failed: " . $e->getMessage());
        return false;
    }
}

function verifyOTP($userId, $otp, $type = 'login') {
    try {
        ensureTablesExist();
        $db = getDB();
        
        $stmt = $db->prepare("SELECT * FROM otp_verifications WHERE user_id = ? AND otp_code = ? AND type = ? AND used = 0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
        $stmt->execute([$userId, $otp, $type]);
        $record = $stmt->fetch();
        
        if ($record) {
            $stmt = $db->prepare("UPDATE otp_verifications SET used = 1, used_at = NOW() WHERE id = ?");
            $stmt->execute([$record['id']]);
            return true;
        }
        return false;
    } catch (Exception $e) {
        error_log("OTP verification failed: " . $e->getMessage());
        return false;
    }
}

function createSession($userId, $remember = false) {
    try {
        ensureTablesExist();
        $db = getDB();
        $token = generateRandomToken();
        $expires = date('Y-m-d H:i:s', time() + ($remember ? 2592000 : SESSION_TIMEOUT));
        
        $stmt = $db->prepare("INSERT INTO user_sessions (user_id, token, device_id, device_type, ip_address, user_agent, expires_at) VALUES (?, ?, ?, 'web', ?, ?, ?)");
        $stmt->execute([$userId, $token, generateDeviceFingerprint(), getClientIP(), getUserAgent(), $expires]);
        
        return $token;
    } catch (Exception $e) {
        error_log("Session creation failed: " . $e->getMessage());
        return null;
    }
}

function validateSession($token) {
    try {
        ensureTablesExist();
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM user_sessions WHERE token = ? AND is_active = 1 AND expires_at > NOW()");
        $stmt->execute([$token]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return null;
    }
}

// ============================================================
// 8. USER DATABASE FUNCTIONS
// ============================================================

function getUserById($userId) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT u.*, r.level as role_level, r.permissions_json FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ? AND u.status = 'active'");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Get user by ID failed: " . $e->getMessage());
        return null;
    }
}

function getUserByEmail($email) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Get user by email failed: " . $e->getMessage());
        return null;
    }
}

function createUser($data) {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO users (user_code, role_id, first_name, last_name, email, phone, password_hash, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            'USR' . time() . rand(100, 999),
            $data['role_id'] ?? 1,
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $data['phone'] ?? '',
            hashPassword($data['password']),
            'active'
        ]);
        return $db->lastInsertId();
    } catch (Exception $e) {
        error_log("User creation failed: " . $e->getMessage());
        return false;
    }
}

function updateUserLastLogin($userId) {
    try {
        $db = getDB();
        $stmt = $db->prepare("UPDATE users SET last_login_at = NOW(), last_login_ip = ?, login_attempts = 0 WHERE id = ?");
        $stmt->execute([getClientIP(), $userId]);
        return true;
    } catch (Exception $e) {
        error_log("Update last login failed: " . $e->getMessage());
        return false;
    }
}

function getLoginHistory($userId, $limit = 50) {
    try {
        ensureTablesExist();
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM login_attempts WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Get login history failed: " . $e->getMessage());
        return [];
    }
}

function getTrustedDevices($userId) {
    try {
        ensureTablesExist();
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM user_sessions WHERE user_id = ? AND is_active = 1 ORDER BY last_activity_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Get trusted devices failed: " . $e->getMessage());
        return [];
    }
}

function deleteSession($userId, $sessionId) {
    try {
        ensureTablesExist();
        $db = getDB();
        $stmt = $db->prepare("UPDATE user_sessions SET is_active = 0 WHERE id = ? AND user_id = ?");
        $stmt->execute([$sessionId, $userId]);
        return true;
    } catch (Exception $e) {
        error_log("Delete session failed: " . $e->getMessage());
        return false;
    }
}

function revokeAllSessions($userId) {
    try {
        ensureTablesExist();
        $db = getDB();
        $stmt = $db->prepare("UPDATE user_sessions SET is_active = 0 WHERE user_id = ?");
        $stmt->execute([$userId]);
        return true;
    } catch (Exception $e) {
        error_log("Revoke all sessions failed: " . $e->getMessage());
        return false;
    }
}
?>