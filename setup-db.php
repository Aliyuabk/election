<?php
// ============================================================
// DATABASE SETUP SCRIPT
// Run this once to create all required tables
// ============================================================

require_once 'config/config.php';
require_once 'includes/functions.php';

echo "<h1>5G Election Guru - Database Setup</h1>";
echo "<pre>";

try {
    $db = getDB();
    echo "✅ Database connected successfully\n\n";
    
    // Create password_resets table
    echo "Creating password_resets table...\n";
    $db->exec("DROP TABLE IF EXISTS `password_resets`");
    $db->exec("CREATE TABLE `password_resets` (
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
    echo "✅ password_resets table created\n";
    
    // Create otp_verifications table
    echo "Creating otp_verifications table...\n";
    $db->exec("DROP TABLE IF EXISTS `otp_verifications`");
    $db->exec("CREATE TABLE `otp_verifications` (
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
        INDEX `idx_otp_code` (`otp_code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ otp_verifications table created\n";
    
    // Create login_attempts table
    echo "Creating login_attempts table...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS `login_attempts` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` BIGINT UNSIGNED NULL,
        `email` VARCHAR(255) NULL,
        `ip_address` VARCHAR(45) NOT NULL,
        `user_agent` TEXT NULL,
        `success` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_login_attempts_ip` (`ip_address`),
        INDEX `idx_login_attempts_email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ login_attempts table created\n";
    
    // Create activity_logs table
    echo "Creating activity_logs table...\n";
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
    echo "✅ activity_logs table created\n";
    
    // Create security_events table
    echo "Creating security_events table...\n";
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
    echo "✅ security_events table created\n";
    
    // Create user_sessions table
    echo "Creating user_sessions table...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS `user_sessions` (
        `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id` BIGINT UNSIGNED NOT NULL,
        `token` VARCHAR(500) NOT NULL,
        `device_id` VARCHAR(255) NULL,
        `device_type` VARCHAR(20) NOT NULL DEFAULT 'web',
        `ip_address` VARCHAR(45) NULL,
        `user_agent` TEXT NULL,
        `expires_at` TIMESTAMP NOT NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_sessions_user` (`user_id`),
        INDEX `idx_sessions_token` (`token`(255))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ user_sessions table created\n";
    
    echo "\n🎉 All tables created successfully!\n";
    echo "\nTest Credentials:\n";
    echo "Email: aliyuabubakar11117@gmail.com\n";
    echo "Password: Admin@123\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "</pre>";
?>