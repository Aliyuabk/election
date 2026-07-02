<?php
// Save this as create-user.php in your project root and run it once
require_once 'config/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

$db = getDB();

// Get super admin role
$stmt = $db->prepare("SELECT id FROM roles WHERE slug = 'super_admin' LIMIT 1");
$stmt->execute();
$role = $stmt->fetch();

if (!$role) {
    // Create super admin role if it doesn't exist
    $stmt = $db->prepare("INSERT INTO roles (name, slug, level, permissions_json, is_system) VALUES ('Super Administrator', 'super_admin', 'super_admin', '{\"all\": true}', 1)");
    $stmt->execute();
    $role_id = $db->lastInsertId();
} else {
    $role_id = $role['id'];
}

// Check if user already exists
$stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute(['aliyuabubakar11117@gmail.com']);
$existing = $stmt->fetch();

if ($existing) {
    echo "User already exists!\n";
    echo "ID: " . $existing['id'] . "\n";
} else {
    // Create user with password: Admin@123
    $password_hash = password_hash('Admin@123', PASSWORD_BCRYPT, ['cost' => 12]);
    
    $stmt = $db->prepare("INSERT INTO users (
        user_code, role_id, first_name, last_name, email, phone, 
        password_hash, status, email_verified_at, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    
    $stmt->execute([
        'ADMIN001',
        $role_id,
        'Aliyu',
        'Abubakar',
        'aliyuabubakar11117@gmail.com',
        '+2348005555555',
        $password_hash,
        'active',
        date('Y-m-d H:i:s')
    ]);
    
    $user_id = $db->lastInsertId();
    echo "Super Admin user created successfully!\n";
    echo "ID: " . $user_id . "\n";
    echo "Email: aliyuabubakar11117@gmail.com\n";
    echo "Password: Admin@123\n";
}
?>