<?php
// ============================================================
// SEARCH HANDLER - AJAX (dashboard/client-admin/search.php)
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
    $role_level = SessionManager::get('role_level', 'client_admin');
    $tenant_id = SessionManager::get('tenant_id');
    
    // ============================================================
    // CLIENT ADMIN SEARCH (Within Tenant)
    // ============================================================
    if ($role_level === 'client_admin' && $tenant_id) {
        
        // 1. Search Elections
        try {
            $stmt = $db->prepare("
                SELECT id, name, type, status, election_date, 'election' as type_name, 'fa-vote-yea' as icon 
                FROM elections 
                WHERE tenant_id = ? AND (name LIKE ? OR description LIKE ?)
                AND deleted_at IS NULL
                ORDER BY election_date DESC
                LIMIT 5
            ");
            $stmt->execute([$tenant_id, $search_param, $search_param]);
            $items = $stmt->fetchAll();
            foreach ($items as $item) {
                $results[] = [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'label' => $item['name'] . ' (' . ucfirst($item['status']) . ')',
                    'type' => 'Election',
                    'icon' => $item['icon'],
                    'url' => 'elections-view.php?id=' . $item['id']
                ];
            }
        } catch (Exception $e) {}
        
        // 2. Search Candidates
        try {
            $stmt = $db->prepare("
                SELECT c.id, c.full_name as name, c.position, e.name as election_name, 'candidate' as type_name, 'fa-user-tie' as icon 
                FROM candidates c
                LEFT JOIN elections e ON c.election_id = e.id
                WHERE c.tenant_id = ? AND (c.first_name LIKE ? OR c.last_name LIKE ? OR c.full_name LIKE ?)
                ORDER BY c.full_name
                LIMIT 5
            ");
            $stmt->execute([$tenant_id, $search_param, $search_param, $search_param]);
            $items = $stmt->fetchAll();
            foreach ($items as $item) {
                $results[] = [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'label' => $item['name'] . ' - ' . ucfirst(str_replace('_', ' ', $item['position'])) . ($item['election_name'] ? ' (' . $item['election_name'] . ')' : ''),
                    'type' => 'Candidate',
                    'icon' => $item['icon'],
                    'url' => 'candidates-details.php?id=' . $item['id']
                ];
            }
        } catch (Exception $e) {}
        
        // 3. Search Political Parties
        try {
            $stmt = $db->prepare("
                SELECT id, name, acronym, 'party' as type_name, 'fa-flag' as icon 
                FROM political_parties 
                WHERE tenant_id = ? AND (name LIKE ? OR acronym LIKE ?)
                ORDER BY name
                LIMIT 5
            ");
            $stmt->execute([$tenant_id, $search_param, $search_param]);
            $items = $stmt->fetchAll();
            foreach ($items as $item) {
                $results[] = [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'label' => $item['name'] . ' (' . $item['acronym'] . ')',
                    'type' => 'Party',
                    'icon' => $item['icon'],
                    'url' => 'parties-details.php?id=' . $item['id']
                ];
            }
        } catch (Exception $e) {}
        
        // 4. Search Polling Units
        try {
            $stmt = $db->prepare("
                SELECT pu.id, pu.code, pu.name, w.name as ward_name, l.name as lga_name, 'polling_unit' as type_name, 'fa-flag-checkered' as icon 
                FROM polling_units pu
                LEFT JOIN wards w ON pu.ward_id = w.id
                LEFT JOIN lgas l ON w.lga_id = l.id
                WHERE pu.name LIKE ? OR pu.code LIKE ? OR w.name LIKE ? OR l.name LIKE ?
                ORDER BY pu.name
                LIMIT 5
            ");
            $stmt->execute([$search_param, $search_param, $search_param, $search_param]);
            $items = $stmt->fetchAll();
            foreach ($items as $item) {
                $results[] = [
                    'id' => $item['id'],
                    'name' => $item['code'] . ' - ' . $item['name'],
                    'label' => $item['code'] . ' - ' . $item['name'] . ' (' . ($item['ward_name'] ?? 'N/A') . ')',
                    'type' => 'Polling Unit',
                    'icon' => $item['icon'],
                    'url' => 'polling-units-details.php?id=' . $item['id']
                ];
            }
        } catch (Exception $e) {}
        
        // 5. Search Users/Agents
        try {
            $stmt = $db->prepare("
                SELECT u.id, u.full_name as name, u.email, u.phone, r.name as role_name, 'user' as type_name, 'fa-user' as icon 
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.tenant_id = ? AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)
                AND u.deleted_at IS NULL
                ORDER BY u.full_name
                LIMIT 5
            ");
            $stmt->execute([$tenant_id, $search_param, $search_param, $search_param, $search_param]);
            $items = $stmt->fetchAll();
            foreach ($items as $item) {
                $results[] = [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'label' => $item['name'] . ' (' . ($item['role_name'] ?? 'User') . ')',
                    'type' => 'User',
                    'icon' => $item['icon'],
                    'url' => 'users-view.php?id=' . $item['id']
                ];
            }
        } catch (Exception $e) {}
        
        // 6. Search Incidents
        try {
            $stmt = $db->prepare("
                SELECT i.id, i.title, i.incident_type, i.status, 'incident' as type_name, 'fa-exclamation-triangle' as icon 
                FROM incidents i
                WHERE i.tenant_id = ? AND (i.title LIKE ? OR i.description LIKE ? OR i.incident_type LIKE ?)
                ORDER BY i.created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$tenant_id, $search_param, $search_param, $search_param]);
            $items = $stmt->fetchAll();
            foreach ($items as $item) {
                $results[] = [
                    'id' => $item['id'],
                    'name' => $item['title'],
                    'label' => $item['title'] . ' (' . ucfirst($item['status']) . ')',
                    'type' => 'Incident',
                    'icon' => $item['icon'],
                    'url' => 'incidents-view.php?id=' . $item['id']
                ];
            }
        } catch (Exception $e) {}
        
        // 7. Search Budgets
        try {
            $stmt = $db->prepare("
                SELECT b.id, b.name, b.total_amount, b.status, 'budget' as type_name, 'fa-wallet' as icon 
                FROM budgets b
                WHERE b.tenant_id = ? AND b.name LIKE ?
                ORDER BY b.created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$tenant_id, $search_param]);
            $items = $stmt->fetchAll();
            foreach ($items as $item) {
                $results[] = [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'label' => $item['name'] . ' (₦' . number_format($item['total_amount']) . ')',
                    'type' => 'Budget',
                    'icon' => $item['icon'],
                    'url' => 'budgets-details.php?id=' . $item['id']
                ];
            }
        } catch (Exception $e) {}
        
        // 8. Search Broadcasts
        try {
            $stmt = $db->prepare("
                SELECT b.id, b.title, b.status, 'broadcast' as type_name, 'fa-bullhorn' as icon 
                FROM broadcasts b
                WHERE b.tenant_id = ? AND (b.title LIKE ? OR b.message LIKE ?)
                ORDER BY b.created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$tenant_id, $search_param, $search_param]);
            $items = $stmt->fetchAll();
            foreach ($items as $item) {
                $results[] = [
                    'id' => $item['id'],
                    'name' => $item['title'],
                    'label' => $item['title'] . ' (' . ucfirst($item['status']) . ')',
                    'type' => 'Broadcast',
                    'icon' => $item['icon'],
                    'url' => 'broadcasts-view.php?id=' . $item['id']
                ];
            }
        } catch (Exception $e) {}
        
        // 9. Search Results (EC8A)
        try {
            $stmt = $db->prepare("
                SELECT r.id, r.pu_code, r.pu_name, r.status, 'result' as type_name, 'fa-file-alt' as icon 
                FROM results_ec8a r
                WHERE r.tenant_id = ? AND (r.pu_code LIKE ? OR r.pu_name LIKE ?)
                ORDER BY r.created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$tenant_id, $search_param, $search_param]);
            $items = $stmt->fetchAll();
            foreach ($items as $item) {
                $results[] = [
                    'id' => $item['id'],
                    'name' => $item['pu_code'] . ' - ' . $item['pu_name'],
                    'label' => $item['pu_code'] . ' - ' . $item['pu_name'] . ' (' . ucfirst($item['status']) . ')',
                    'type' => 'Result (EC8A)',
                    'icon' => $item['icon'],
                    'url' => 'results-ec8a-view.php?id=' . $item['id']
                ];
            }
        } catch (Exception $e) {}
        
        // 10. Search Agent Assignments
        try {
            $stmt = $db->prepare("
                SELECT a.id, u.full_name as agent_name, pu.name as pu_name, a.status, 'assignment' as type_name, 'fa-tasks' as icon 
                FROM agent_assignments a
                LEFT JOIN users u ON a.user_id = u.id
                LEFT JOIN polling_units pu ON a.pu_id = pu.id
                WHERE a.tenant_id = ? AND (u.full_name LIKE ? OR pu.name LIKE ?)
                ORDER BY a.assigned_at DESC
                LIMIT 5
            ");
            $stmt->execute([$tenant_id, $search_param, $search_param]);
            $items = $stmt->fetchAll();
            foreach ($items as $item) {
                $results[] = [
                    'id' => $item['id'],
                    'name' => $item['agent_name'] . ' → ' . $item['pu_name'],
                    'label' => $item['agent_name'] . ' assigned to ' . $item['pu_name'] . ' (' . ucfirst($item['status']) . ')',
                    'type' => 'Assignment',
                    'icon' => $item['icon'],
                    'url' => 'agent-assign-view.php?id=' . $item['id']
                ];
            }
        } catch (Exception $e) {}
    }
    
    // ============================================================
    // SUPER ADMIN SEARCH (All Tenants)
    // ============================================================
    if ($role_level === 'super_admin') {
        // Search Tenants
        try {
            $stmt = $db->prepare("
                SELECT id, name, slug, type, subscription_status, 'tenant' as type_name, 'fa-building' as icon 
                FROM tenants 
                WHERE (name LIKE ? OR slug LIKE ? OR contact_email LIKE ?) 
                AND deleted_at IS NULL
                ORDER BY name
                LIMIT 5
            ");
            $stmt->execute([$search_param, $search_param, $search_param]);
            $items = $stmt->fetchAll();
            foreach ($items as $item) {
                $results[] = [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'label' => $item['name'] . ' (' . ucfirst($item['type']) . ' - ' . ucfirst($item['subscription_status']) . ')',
                    'type' => 'Tenant',
                    'icon' => $item['icon'],
                    'url' => 'tenants-view.php?id=' . $item['id']
                ];
            }
        } catch (Exception $e) {}
        
        // Search All Users
        try {
            $stmt = $db->prepare("
                SELECT u.id, u.full_name as name, u.email, t.name as tenant_name, 'user' as type_name, 'fa-user' as icon 
                FROM users u
                LEFT JOIN tenants t ON u.tenant_id = t.id
                WHERE (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)
                AND u.deleted_at IS NULL
                ORDER BY u.full_name
                LIMIT 5
            ");
            $stmt->execute([$search_param, $search_param, $search_param, $search_param]);
            $items = $stmt->fetchAll();
            foreach ($items as $item) {
                $results[] = [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'label' => $item['name'] . ' (' . ($item['tenant_name'] ?? 'No Tenant') . ')',
                    'type' => 'User',
                    'icon' => $item['icon'],
                    'url' => 'users-view.php?id=' . $item['id']
                ];
            }
        } catch (Exception $e) {}
        
        // Search Subscriptions
        try {
            $stmt = $db->prepare("
                SELECT s.id, s.plan, t.name as tenant_name, s.status, 'subscription' as type_name, 'fa-credit-card' as icon 
                FROM subscriptions s
                LEFT JOIN tenants t ON s.tenant_id = t.id
                WHERE s.plan LIKE ? OR t.name LIKE ? OR s.status LIKE ?
                ORDER BY s.created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$search_param, $search_param, $search_param]);
            $items = $stmt->fetchAll();
            foreach ($items as $item) {
                $results[] = [
                    'id' => $item['id'],
                    'name' => $item['plan'] . ' plan',
                    'label' => ucfirst($item['plan']) . ' - ' . ($item['tenant_name'] ?? 'N/A') . ' (' . ucfirst($item['status']) . ')',
                    'type' => 'Subscription',
                    'icon' => $item['icon'],
                    'url' => 'subscriptions-view.php?id=' . $item['id']
                ];
            }
        } catch (Exception $e) {}
        
        // Search Invoices
        try {
            $stmt = $db->prepare("
                SELECT i.id, i.invoice_number, i.total_amount, i.status, t.name as tenant_name, 'invoice' as type_name, 'fa-file-invoice' as icon 
                FROM invoices i
                LEFT JOIN tenants t ON i.tenant_id = t.id
                WHERE i.invoice_number LIKE ? OR t.name LIKE ? OR i.status LIKE ?
                ORDER BY i.created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$search_param, $search_param, $search_param]);
            $items = $stmt->fetchAll();
            foreach ($items as $item) {
                $results[] = [
                    'id' => $item['id'],
                    'name' => $item['invoice_number'],
                    'label' => $item['invoice_number'] . ' - ' . ($item['tenant_name'] ?? 'N/A') . ' (₦' . number_format($item['total_amount']) . ')',
                    'type' => 'Invoice',
                    'icon' => $item['icon'],
                    'url' => 'invoices-view.php?id=' . $item['id']
                ];
            }
        } catch (Exception $e) {}
    }
    
    // Sort results by relevance (type priority)
    $priority = [
        'Election' => 1,
        'Candidate' => 2,
        'Party' => 3,
        'Polling Unit' => 4,
        'User' => 5,
        'Incident' => 6,
        'Budget' => 7,
        'Broadcast' => 8,
        'Result' => 9,
        'Assignment' => 10,
        'Tenant' => 11,
        'Subscription' => 12,
        'Invoice' => 13
    ];
    
    usort($results, function($a, $b) use ($priority) {
        $pa = $priority[$a['type']] ?? 99;
        $pb = $priority[$b['type']] ?? 99;
        return $pa - $pb;
    });
    
    // Limit results to 20
    $results = array_slice($results, 0, 20);
}

// Return JSON results
header('Content-Type: application/json');
echo json_encode($results);
exit();
?>