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
// 2. AUTHENTICATION FUNCTIONS
// ============================================================

function authenticateUser($email, $password) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT u.*, r.level as role_level, r.permissions_json 
            FROM users u 
            LEFT JOIN roles r ON u.role_id = r.id 
            WHERE u.email = ? AND u.status = 'active'
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && verifyPassword($password, $user['password_hash'])) {
            // Get user's full name (already generated in DB)
            return $user;
        }
        return null;
    } catch (Exception $e) {
        error_log("Authentication failed: " . $e->getMessage());
        return null;
    }
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function generateOTP($length = 6) {
    return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
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
    $hostname = '';
    
    if (isset($_SERVER['SERVER_NAME']) && !empty($_SERVER['SERVER_NAME'])) {
        $hostname = $_SERVER['SERVER_NAME'];
    }
    
    if (empty($hostname) && isset($_SERVER['HTTP_HOST']) && !empty($_SERVER['HTTP_HOST'])) {
        $hostname = $_SERVER['HTTP_HOST'];
    }
    
    if (empty($hostname) && function_exists('gethostname')) {
        $hostname = gethostname() ?: '';
    }
    
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
// 5. LOGGING FUNCTIONS
// ============================================================

function logActivity($userId, $type, $description, $entityType = null, $entityId = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, activity_type, description, entity_type, entity_id, ip_address, device_id, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $type, $description, $entityType, $entityId, getClientIP(), generateDeviceFingerprint()]);
        return true;
    } catch (Exception $e) {
        error_log("Activity logging failed: " . $e->getMessage());
        return false;
    }
}

function logSecurityEvent($userId, $eventType, $description, $riskScore = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO security_events (user_id, event_type, description, ip_address, device_id, risk_score, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $eventType, $description, getClientIP(), generateDeviceFingerprint(), $riskScore]);
        return true;
    } catch (Exception $e) {
        error_log("Security event logging failed: " . $e->getMessage());
        return false;
    }
}

function logLoginAttempt($userId, $email, $success) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO login_attempts (user_id, email, ip_address, user_agent, attempt_type, success, created_at) 
            VALUES (?, ?, ?, ?, 'login', ?, NOW())
        ");
        $stmt->execute([$userId, $email, getClientIP(), getUserAgent(), $success ? 1 : 0]);
        return true;
    } catch (Exception $e) {
        error_log("Login attempt logging failed: " . $e->getMessage());
        return false;
    }
}

// ============================================================
// 6. AUTHENTICATION DATABASE FUNCTIONS
// ============================================================

function getLoginAttempts($email, $ip) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM login_attempts 
            WHERE (email = ? OR ip_address = ?) AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND success = 0
        ");
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
        // LOCKOUT_TIME is in seconds, convert to minutes for DB
        $lockoutMinutes = LOCKOUT_TIME / 60;
        $stmt = $db->prepare("UPDATE users SET locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE email = ?");
        $stmt->execute([$lockoutMinutes, $email]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function saveOTP($userId, $otp, $type = 'login', $channel = 'email') {
    try {
        $db = getDB();
        $expires = date('Y-m-d H:i:s', time() + OTP_EXPIRY);
        
        $stmt = $db->prepare("
            INSERT INTO otp_verifications (user_id, otp_code, type, channel, expires_at, used, attempts) 
            VALUES (?, ?, ?, ?, ?, 0, 0)
        ");
        $stmt->execute([$userId, $otp, $type, $channel, $expires]);
        return true;
    } catch (Exception $e) {
        error_log("OTP save failed: " . $e->getMessage());
        return false;
    }
}

function verifyOTP($userId, $otp, $type = 'login') {
    try {
        $db = getDB();
        
        $stmt = $db->prepare("
            SELECT * FROM otp_verifications 
            WHERE user_id = ? AND otp_code = ? AND type = ? AND used = 0 AND expires_at > NOW() 
            ORDER BY id DESC LIMIT 1
        ");
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
        $db = getDB();
        $token = generateRandomToken();
        $expires = date('Y-m-d H:i:s', time() + ($remember ? 2592000 : SESSION_TIMEOUT));
        $deviceId = generateDeviceFingerprint();
        
        $stmt = $db->prepare("
            INSERT INTO user_sessions (user_id, token, device_id, device_type, ip_address, gps_lat, gps_lng, user_agent, expires_at) 
            VALUES (?, ?, ?, 'web', ?, NULL, NULL, ?, ?)
        ");
        $stmt->execute([$userId, $token, $deviceId, getClientIP(), getUserAgent(), $expires]);
        
        return $token;
    } catch (Exception $e) {
        error_log("Session creation failed: " . $e->getMessage());
        return null;
    }
}

function validateSession($token) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT * FROM user_sessions 
            WHERE token = ? AND is_active = 1 AND expires_at > NOW()
        ");
        $stmt->execute([$token]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return null;
    }
}

function revokeAllSessions($userId) {
    try {
        $db = getDB();
        $stmt = $db->prepare("UPDATE user_sessions SET is_active = 0 WHERE user_id = ?");
        $stmt->execute([$userId]);
        return true;
    } catch (Exception $e) {
        error_log("Revoke all sessions failed: " . $e->getMessage());
        return false;
    }
}

// ============================================================
// 7. USER DATABASE FUNCTIONS
// ============================================================

function getUserById($userId) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT u.*, r.level as role_level, r.permissions_json 
            FROM users u 
            LEFT JOIN roles r ON u.role_id = r.id 
            WHERE u.id = ? AND u.status = 'active'
        ");
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
        $stmt = $db->prepare("
            SELECT u.*, r.level as role_level, r.permissions_json 
            FROM users u 
            LEFT JOIN roles r ON u.role_id = r.id 
            WHERE u.email = ?
        ");
        $stmt->execute([$email]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Get user by email failed: " . $e->getMessage());
        return null;
    }
}

function updateUserLastLogin($userId) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            UPDATE users SET last_login_at = NOW(), last_login_ip = ?, login_attempts = 0 WHERE id = ?
        ");
        $stmt->execute([getClientIP(), $userId]);
        return true;
    } catch (Exception $e) {
        error_log("Update last login failed: " . $e->getMessage());
        return false;
    }
}

function getLoginHistory($userId, $limit = 50) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT * FROM login_attempts WHERE user_id = ? ORDER BY created_at DESC LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Get login history failed: " . $e->getMessage());
        return [];
    }
}

function getTrustedDevices($userId) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT * FROM user_sessions WHERE user_id = ? AND is_active = 1 ORDER BY last_activity_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Get trusted devices failed: " . $e->getMessage());
        return [];
    }
}

function deleteSession($userId, $sessionId) {
    try {
        $db = getDB();
        $stmt = $db->prepare("UPDATE user_sessions SET is_active = 0 WHERE id = ? AND user_id = ?");
        $stmt->execute([$sessionId, $userId]);
        return true;
    } catch (Exception $e) {
        error_log("Delete session failed: " . $e->getMessage());
        return false;
    }
}

// ============================================================
// 8. PASSWORD RESET FUNCTIONS
// ============================================================

function createPasswordReset($userId, $token) {
    try {
        $db = getDB();
        $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
        
        $stmt = $db->prepare("
            INSERT INTO password_resets (user_id, token, expires_at, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $token, $expires]);
        return true;
    } catch (Exception $e) {
        error_log("Create password reset failed: " . $e->getMessage());
        return false;
    }
}

function validatePasswordResetToken($token, $email) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT pr.*, u.id as user_id 
            FROM password_resets pr
            JOIN users u ON pr.user_id = u.id
            WHERE pr.token = ? AND u.email = ? AND pr.used = 0 AND pr.expires_at > NOW()
            ORDER BY pr.id DESC LIMIT 1
        ");
        $stmt->execute([$token, $email]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Validate password reset failed: " . $e->getMessage());
        return null;
    }
}

function markPasswordResetUsed($resetId) {
    try {
        $db = getDB();
        $stmt = $db->prepare("UPDATE password_resets SET used = 1, used_at = NOW() WHERE id = ?");
        $stmt->execute([$resetId]);
        return true;
    } catch (Exception $e) {
        error_log("Mark password reset used failed: " . $e->getMessage());
        return false;
    }
}

function updateUserPassword($userId, $newPassword) {
    try {
        $db = getDB();
        $hashedPassword = hashPassword($newPassword);
        $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$hashedPassword, $userId]);
        return true;
    } catch (Exception $e) {
        error_log("Update user password failed: " . $e->getMessage());
        return false;
    }
}

// ============================================================
// 9. EMAIL TEMPLATE FUNCTIONS
// ============================================================

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
            <p>Hello ' . htmlspecialchars($name ?: 'User') . ',</p>
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
            <p>Hello ' . htmlspecialchars($name ?: 'User') . ',</p>
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

// ============================================================
// 10. BROADCAST FUNCTIONS
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
                AND u.state_id IN ($placeholders)
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
?>