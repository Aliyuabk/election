<?php
// ============================================================
// SEARCH HANDLER - AJAX (dashboard/super-admin/search.php)
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Start session
SessionManager::start();

// Check if logged in
if (!SessionManager::isLoggedIn()) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Check if it's an AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    header('HTTP/1.1 403 Forbidden');
    exit();
}

// Get search query
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = [];

if (strlen($query) >= 2) {
    $db = getDB();
    $search_param = "%$query%";
    $user_id = SessionManager::get('user_id');
    $role_level = SessionManager::get('role_level', 'super_admin');
    
    // Search based on role
    if ($role_level === 'super_admin') {
        // Super admin can search everything
        
        // Search Tenants
        try {
            $stmt = $db->prepare("
                SELECT id, name, slug, 'tenant' as type, 'fa-building' as icon 
                FROM tenants 
                WHERE (name LIKE ? OR slug LIKE ? OR contact_email LIKE ?) 
                AND deleted_at IS NULL
                LIMIT 5
            ");
            $stmt->execute([$search_param, $search_param, $search_param]);
            $tenants = $stmt->fetchAll();
            foreach ($tenants as $item) {
                $item['url'] = 'tenants-view.php?id=' . $item['id'];
                $results[] = $item;
            }
        } catch (Exception $e) {}
        
        // Search Users
        try {
            $stmt = $db->prepare("
                SELECT id, full_name as name, email, 'user' as type, 'fa-user' as icon 
                FROM users 
                WHERE (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?) 
                AND deleted_at IS NULL
                LIMIT 5
            ");
            $stmt->execute([$search_param, $search_param, $search_param, $search_param]);
            $users = $stmt->fetchAll();
            foreach ($users as $item) {
                $item['url'] = 'users-view.php?id=' . $item['id'];
                $results[] = $item;
            }
        } catch (Exception $e) {}
        
        // Search Elections
        try {
            $stmt = $db->prepare("
                SELECT id, name, 'election' as type, 'fa-vote-yea' as icon 
                FROM elections 
                WHERE name LIKE ? OR description LIKE ? 
                AND deleted_at IS NULL
                LIMIT 5
            ");
            $stmt->execute([$search_param, $search_param]);
            $elections = $stmt->fetchAll();
            foreach ($elections as $item) {
                $item['url'] = 'elections-view.php?id=' . $item['id'];
                $results[] = $item;
            }
        } catch (Exception $e) {}
        
        // Search Invoices
        try {
            $stmt = $db->prepare("
                SELECT i.id, i.invoice_number as name, 'invoice' as type, 'fa-file-invoice' as icon,
                       t.name as tenant_name
                FROM invoices i
                LEFT JOIN tenants t ON i.tenant_id = t.id
                WHERE i.invoice_number LIKE ? OR t.name LIKE ?
                LIMIT 5
            ");
            $stmt->execute([$search_param, $search_param]);
            $invoices = $stmt->fetchAll();
            foreach ($invoices as $item) {
                $item['name'] = $item['name'] . ' (' . ($item['tenant_name'] ?? 'N/A') . ')';
                $item['url'] = 'billing.php#invoice-' . $item['id'];
                $results[] = $item;
            }
        } catch (Exception $e) {}
        
        // Search Tickets
        try {
            $stmt = $db->prepare("
                SELECT id, ticket_number as name, subject, 'ticket' as type, 'fa-ticket-alt' as icon 
                FROM support_tickets 
                WHERE ticket_number LIKE ? OR subject LIKE ? OR description LIKE ?
                LIMIT 5
            ");
            $stmt->execute([$search_param, $search_param, $search_param]);
            $tickets = $stmt->fetchAll();
            foreach ($tickets as $item) {
                $item['name'] = $item['name'] . ' - ' . ($item['subject'] ?? '');
                $item['url'] = 'tickets.php#ticket-' . $item['id'];
                $results[] = $item;
            }
        } catch (Exception $e) {}
        
        // Search Subscriptions
        try {
            $stmt = $db->prepare("
                SELECT s.id, p.name as name, 'subscription' as type, 'fa-credit-card' as icon,
                       t.name as tenant_name
                FROM subscriptions s
                LEFT JOIN subscription_plans p ON s.plan_id = p.id
                LEFT JOIN tenants t ON s.tenant_id = t.id
                WHERE p.name LIKE ? OR t.name LIKE ?
                LIMIT 5
            ");
            $stmt->execute([$search_param, $search_param]);
            $subscriptions = $stmt->fetchAll();
            foreach ($subscriptions as $item) {
                $item['name'] = $item['name'] . ' (' . ($item['tenant_name'] ?? 'N/A') . ')';
                $item['url'] = 'subscriptions.php#sub-' . $item['id'];
                $results[] = $item;
            }
        } catch (Exception $e) {}
        
        // Search API Keys
        try {
            $stmt = $db->prepare("
                SELECT id, name, 'api_key' as type, 'fa-code' as icon 
                FROM api_keys 
                WHERE name LIKE ? OR key_prefix LIKE ?
                LIMIT 5
            ");
            $stmt->execute([$search_param, $search_param]);
            $api_keys = $stmt->fetchAll();
            foreach ($api_keys as $item) {
                $item['url'] = 'api-management.php#key-' . $item['id'];
                $results[] = $item;
            }
        } catch (Exception $e) {}
        
        // Search Backup files
        try {
            $stmt = $db->prepare("
                SELECT id, backup_type as name, 'backup' as type, 'fa-archive' as icon 
                FROM backups 
                WHERE backup_type LIKE ? OR file_path LIKE ?
                LIMIT 5
            ");
            $stmt->execute([$search_param, $search_param]);
            $backups = $stmt->fetchAll();
            foreach ($backups as $item) {
                $item['name'] = $item['name'] . ' backup';
                $item['url'] = 'backups.php#backup-' . $item['id'];
                $results[] = $item;
            }
        } catch (Exception $e) {}
        
    } else {
        // Client admin or other roles - search within their tenant
        $tenant_id = SessionManager::get('tenant_id');
        
        // Search Users within tenant
        try {
            $stmt = $db->prepare("
                SELECT id, full_name as name, email, 'user' as type, 'fa-user' as icon 
                FROM users 
                WHERE tenant_id = ? AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?) 
                AND deleted_at IS NULL
                LIMIT 5
            ");
            $stmt->execute([$tenant_id, $search_param, $search_param, $search_param]);
            $users = $stmt->fetchAll();
            foreach ($users as $item) {
                $item['url'] = 'users-view.php?id=' . $item['id'];
                $results[] = $item;
            }
        } catch (Exception $e) {}
        
        // Search Elections within tenant
        try {
            $stmt = $db->prepare("
                SELECT id, name, 'election' as type, 'fa-vote-yea' as icon 
                FROM elections 
                WHERE tenant_id = ? AND (name LIKE ? OR description LIKE ?)
                AND deleted_at IS NULL
                LIMIT 5
            ");
            $stmt->execute([$tenant_id, $search_param, $search_param]);
            $elections = $stmt->fetchAll();
            foreach ($elections as $item) {
                $item['url'] = 'elections-view.php?id=' . $item['id'];
                $results[] = $item;
            }
        } catch (Exception $e) {}
        
        // Search Agents within tenant
        try {
            $stmt = $db->prepare("
                SELECT u.id, u.full_name as name, 'agent' as type, 'fa-user-tie' as icon 
                FROM users u
                JOIN agent_assignments a ON u.id = a.user_id
                WHERE u.tenant_id = ? AND (u.first_name LIKE ? OR u.last_name LIKE ?)
                AND u.deleted_at IS NULL
                LIMIT 5
            ");
            $stmt->execute([$tenant_id, $search_param, $search_param]);
            $agents = $stmt->fetchAll();
            foreach ($agents as $item) {
                $item['url'] = 'users-view.php?id=' . $item['id'];
                $results[] = $item;
            }
        } catch (Exception $e) {}
    }
    
    // Limit results to 20
    $results = array_slice($results, 0, 20);
}

// Return JSON results
header('Content-Type: application/json');
echo json_encode($results);
exit();
?>