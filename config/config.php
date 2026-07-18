<?php
// ============================================================
// 5G ELECTION GURU - CONFIGURATION
// ============================================================

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'utgoohwm_election');
define('DB_USER', 'utgoohwm_election');
define('DB_PASS', 'Jiddahhh@1');

// Application Configuration
define('APP_NAME', '5G Election Guru');
define('APP_URL', 'https://eguruelction.kowagurutech.ng/');
define('APP_TIMEZONE', 'Africa/Lagos');

// Session Configuration
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes
define('OTP_EXPIRY', 300); // 5 minutes

// Email Configuration (SMTP)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'aliyuabubakarjdh@gmail.com');
define('SMTP_PASSWORD', 'crhebdkjibmmwyqs');
define('SMTP_FROM_EMAIL', 'aliyuabubakarjdh@gmail.com');
define('SMTP_FROM_NAME', '5G Election Guru');
define('SMTP_ENCRYPTION', 'tls');

// Google OAuth Configuration
define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');
define('GOOGLE_REDIRECT_URI', APP_URL . '/auth/google-callback.php');

// Security
define('ENCRYPTION_KEY', 'your-32-character-encryption-key-here');

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// ============================================================
// CRITICAL FIX: Set Timezone BEFORE any database operations
// ============================================================
date_default_timezone_set(APP_TIMEZONE);

// Also set MySQL timezone to match
// This will be executed when the database connection is created
?>