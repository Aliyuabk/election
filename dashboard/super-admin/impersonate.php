<?php
// ============================================================
// STOP IMPERSONATION
// ============================================================
// Returns to original Super Admin session
// ============================================================

session_start();

require_once 'includes/db.php';

// Get database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Check if impersonating
if (!isset($_SESSION['is_impersonating']) || $_SESSION['is_impersonating'] !== true) {
    header("Location: dashboard.php");
    exit;
}

// Log stop impersonation
if (isset($_SESSION['original_user_id']) && isset($_SESSION['impersonated_tenant_id'])) {
    $logQuery = "INSERT INTO activity_logs (user_id, tenant_id, activity_type, description, ip_address, created_at) 
                 VALUES (?, ?, 'stop_impersonate', 'Super Admin stopped impersonating tenant: ' . ?, NOW())";
    $logStmt = $conn->prepare($logQuery);
    $logStmt->execute([
        $_SESSION['original_user_id'],
        $_SESSION['impersonated_tenant_id'],
        $_SESSION['impersonated_tenant_name'] ?? 'Unknown Tenant',
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);
}

// Restore original session
$original_user_id = $_SESSION['original_user_id'] ?? null;
$original_user_role = $_SESSION['original_user_role'] ?? 'super_admin';

// Clear impersonation session variables
unset($_SESSION['is_impersonating']);
unset($_SESSION['impersonating']);
unset($_SESSION['impersonated_tenant_id']);
unset($_SESSION['impersonated_tenant_name']);
unset($_SESSION['impersonated_user_id']);
unset($_SESSION['tenant_id']);
unset($_SESSION['tenant_name']);
unset($_SESSION['role_id']);
unset($_SESSION['role']);
unset($_SESSION['role_slug']);
unset($_SESSION['role_level']);
unset($_SESSION['user_name']);
unset($_SESSION['user_email']);

// Restore original user
if ($original_user_id) {
    // Get original user details
    $userQuery = "SELECT id, first_name, last_name, email, role_id FROM users WHERE id = ? AND deleted_at IS NULL";
    $userStmt = $conn->prepare($userQuery);
    $userStmt->execute([$original_user_id]);
    $user = $userStmt->fetch();
    
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['user_email'] = $user['email'] ?? '';
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['role'] = 'Super Administrator';
        $_SESSION['is_super_admin'] = true;
        
        // Get role details
        $roleQuery = "SELECT name, slug FROM roles WHERE id = ?";
        $roleStmt = $conn->prepare($roleQuery);
        $roleStmt->execute([$user['role_id']]);
        $role = $roleStmt->fetch();
        if ($role) {
            $_SESSION['role'] = $role['name'];
            $_SESSION['role_slug'] = $role['slug'];
        }
    }
}

// Set flash message
$_SESSION['flash_message'] = "You have been returned to your original session";
$_SESSION['flash_type'] = 'success';

header("Location: dashboard.php");
exit;
?>