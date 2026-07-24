<?php
// ============================================================
// WARD COORDINATOR - ASSIGN AGENTS TO POLLING UNITS (COMPLETE UPDATE)
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Start session
SessionManager::start();

// Check if user is logged in
if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

// Only Ward coordinator can access
$user_role_level = SessionManager::get('role_level');
if ($user_role_level !== 'ward') {
    header('Location: ../client-admin/');
    exit();
}

// Get user data from session
$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$ward_id = SessionManager::get('ward_id');
$lga_id = SessionManager::get('lga_id');
$state_id = SessionManager::get('state_id');

// Get database connection
$db = getDB();

// ============================================================
// FIX: Ensure ward_id is properly set
// ============================================================
if (empty($ward_id)) {
    try {
        $stmt = $db->prepare("SELECT ward_id, lga_id, state_id FROM users WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$user_id, $tenant_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && !empty($user['ward_id'])) {
            $ward_id = $user['ward_id'];
            $lga_id = $user['lga_id'] ?? $lga_id;
            $state_id = $user['state_id'] ?? $state_id;
            
            SessionManager::set('ward_id', $ward_id);
            SessionManager::set('lga_id', $lga_id);
            SessionManager::set('state_id', $state_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching ward_id: " . $e->getMessage());
    }
}

// ============================================================
// FETCH WARD NAME
// ============================================================
$ward_name = 'Unknown Ward';
try {
    if ($ward_id) {
        $stmt = $db->prepare("SELECT name FROM wards WHERE id = ?");
        $stmt->execute([$ward_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $ward_name = $result['name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching ward name: " . $e->getMessage());
}

// ============================================================
// FUNCTION: Ensure active election exists
// ============================================================
function ensureActiveElection($db, $tenant_id, $ward_id, $user_id) {
    try {
        // Check for active election with this ward
        $stmt = $db->prepare("
            SELECT id FROM elections 
            WHERE tenant_id = ? AND status = 'active' 
            AND JSON_CONTAINS(wards_json, JSON_QUOTE(?))
            LIMIT 1
        ");
        $stmt->execute([$tenant_id, $ward_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return $result['id'];
        }
        
        // Create default election
        $db->beginTransaction();
        
        $stmt = $db->prepare("
            INSERT INTO elections (
                tenant_id, name, type, cycle, election_date, 
                status, wards_json, created_by, created_at, updated_at
            ) VALUES (?, 'Default Active Election', 'governorship', '2031', 
                DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'active', JSON_ARRAY(?), ?, NOW(), NOW()
            )
        ");
        $stmt->execute([$tenant_id, $ward_id, $user_id]);
        $election_id = $db->lastInsertId();
        
        $db->commit();
        error_log("Created default election ID: $election_id for tenant: $tenant_id, ward: $ward_id");
        return $election_id;
        
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Error ensuring active election: " . $e->getMessage());
        throw $e;
    }
}

// ============================================================
// HANDLE ASSIGNMENT
// ============================================================
$success_message = '';
$error_message = '';
$show_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_agent') {
    $agent_id = isset($_POST['agent_id']) ? (int)$_POST['agent_id'] : 0;
    $pu_id = isset($_POST['pu_id']) ? (int)$_POST['pu_id'] : 0;
    $assignment_type = isset($_POST['assignment_type']) ? $_POST['assignment_type'] : 'data_agent';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    // CSRF Protection
    $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    $session_token = SessionManager::get('csrf_token');
    
    if (empty($csrf_token) || $csrf_token !== $session_token) {
        $error_message = 'Security validation failed. Please try again.';
    } elseif ($agent_id <= 0 || $pu_id <= 0) {
        $error_message = 'Please select both an agent and a polling unit.';
    } else {
        try {
            $db->beginTransaction();
            
            // Verify agent exists and belongs to this ward
            $stmt = $db->prepare("
                SELECT u.id, u.full_name, u.pu_id, u.status, u.role_id, u.ward_id
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE u.id = ? AND u.tenant_id = ? AND u.ward_id = ? AND u.deleted_at IS NULL
            ");
            $stmt->execute([$agent_id, $tenant_id, $ward_id]);
            $agent = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$agent) {
                throw new Exception('Agent not found or does not belong to your ward.');
            }
            
            if ($agent['status'] !== 'active') {
                throw new Exception('Agent is not active. Please activate them first.');
            }
            
            // Check if agent is already assigned
            if (!empty($agent['pu_id']) && $agent['pu_id'] > 0) {
                if (!isset($_POST['confirm_reassign']) || $_POST['confirm_reassign'] !== '1') {
                    throw new Exception('reassign_required');
                }
            }
            
            // Verify polling unit exists and belongs to this ward
            $stmt = $db->prepare("
                SELECT id, name, ward_id, is_active 
                FROM polling_units 
                WHERE id = ? AND is_active = 1
            ");
            $stmt->execute([$pu_id]);
            $pu = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$pu) {
                throw new Exception('Polling unit not found or inactive.');
            }
            
            if ($pu['ward_id'] != $ward_id) {
                throw new Exception("Polling unit belongs to ward {$pu['ward_id']}, but you are assigned to ward $ward_id.");
            }
            
            // Ensure active election exists
            $election_id = ensureActiveElection($db, $tenant_id, $ward_id, $user_id);
            
            // Update user's PU assignment
            $stmt = $db->prepare("UPDATE users SET pu_id = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$pu_id, $agent_id, $tenant_id]);
            
            // Mark old assignments as reassigned
            $stmt = $db->prepare("
                UPDATE agent_assignments 
                SET status = 'reassigned' 
                WHERE user_id = ? AND status = 'active'
            ");
            $stmt->execute([$agent_id]);
            
            // Create new assignment
            $stmt = $db->prepare("
                INSERT INTO agent_assignments (
                    tenant_id, election_id, user_id, pu_id, ward_id, lga_id, state_id,
                    assignment_type, status, assigned_by, notes, assigned_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, NOW())
            ");
            
            $stmt->execute([
                $tenant_id,
                $election_id,
                $agent_id,
                $pu_id,
                $ward_id,
                $lga_id,
                $state_id,
                $assignment_type,
                $user_id,
                $notes
            ]);
            
            logActivity($user_id, 'agent_assigned', "Assigned agent: {$agent['full_name']} (ID: $agent_id) to PU: {$pu['name']} (ID: $pu_id)", 'user', $agent_id);
            
            $db->commit();
            $success_message = "Agent assigned successfully to {$pu['name']}!";
            $show_success = true;
            
        } catch (Exception $e) {
            $db->rollBack();
            
            if ($e->getMessage() === 'reassign_required') {
                $error_message = 'reassign_required';
                $reassign_agent_id = $agent_id;
                $reassign_pu_id = $pu_id;
            } else {
                $error_message = "Error: " . $e->getMessage();
                error_log("Agent assignment error: " . $e->getMessage());
            }
        }
    }
}

// ============================================================
// FETCH UNASSIGNED AGENTS
// ============================================================
$unassigned_agents = [];
$assigned_agents = [];
$polling_units = [];
$agent_stats = ['total' => 0, 'assigned' => 0, 'unassigned' => 0];

try {
    // Get unassigned agents (PU Agents without PU assignment - role_id = 9)
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.user_code,
            u.full_name,
            u.email,
            u.phone,
            u.status,
            u.created_at,
            u.photograph_url
        FROM users u
        WHERE u.tenant_id = ? 
        AND u.ward_id = ?
        AND u.deleted_at IS NULL
        AND u.role_id = 9
        AND (u.pu_id IS NULL OR u.pu_id = 0)
        AND u.status = 'active'
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $unassigned_agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get assigned agents
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.user_code,
            u.full_name,
            u.email,
            u.phone,
            u.status,
            u.pu_id,
            u.photograph_url,
            pu.name as pu_name,
            pu.code as pu_code
        FROM users u
        LEFT JOIN polling_units pu ON u.pu_id = pu.id
        WHERE u.tenant_id = ? 
        AND u.ward_id = ?
        AND u.deleted_at IS NULL
        AND u.role_id = 9
        AND u.pu_id IS NOT NULL
        AND u.pu_id > 0
        AND u.status = 'active'
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $assigned_agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get polling units
    $stmt = $db->prepare("
        SELECT 
            pu.id,
            pu.name,
            pu.code,
            pu.registered_voters,
            pu.is_active,
            pu.ward_id,
            (SELECT COUNT(*) FROM users u 
             WHERE u.pu_id = pu.id AND u.role_id = 9 AND u.status = 'active' AND u.deleted_at IS NULL) as assigned_agents
        FROM polling_units pu
        WHERE pu.ward_id = ? AND pu.is_active = 1
        ORDER BY pu.name ASC
    ");
    $stmt->execute([$ward_id]);
    $polling_units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistics
    $agent_stats['total'] = count($unassigned_agents) + count($assigned_agents);
    $agent_stats['assigned'] = count($assigned_agents);
    $agent_stats['unassigned'] = count($unassigned_agents);
    
} catch (Exception $e) {
    error_log("Error fetching data: " . $e->getMessage());
}

// Generate CSRF token
$csrf_token = bin2hex(random_bytes(32));
SessionManager::set('csrf_token', $csrf_token);

$page_title = 'Assign Agents';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
:root {
    --primary: #0F4C81;
    --primary-dark: #0a3a62;
    --success: #10B981;
    --success-light: #ECFDF5;
    --danger: #EF4444;
    --danger-light: #FEF2F2;
    --warning: #F59E0B;
    --warning-light: #FEF3C7;
    --info: #3B82F6;
    --info-light: #EFF6FF;
    --gray-50: #F9FAFB;
    --gray-100: #F3F4F6;
    --gray-200: #E5E7EB;
    --gray-300: #D1D5DB;
    --gray-400: #9CA3AF;
    --gray-500: #6B7280;
    --gray-600: #4B5563;
    --gray-700: #374151;
    --gray-800: #1F2937;
    --radius: 8px;
    --shadow: 0 1px 3px rgba(0,0,0,0.1);
    --transition: all 0.2s ease;
}

.assign-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.assign-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.assign-header h2 i {
    color: var(--primary);
}
.assign-header .subtitle {
    color: var(--gray-500);
    font-size: 0.85rem;
    margin: 2px 0 0;
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}
.stat-mini {
    background: white;
    padding: 14px 18px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    text-align: center;
    box-shadow: var(--shadow);
}
.stat-mini .number {
    font-size: 1.3rem;
    font-weight: 700;
}
.stat-mini .number.green { color: #10B981; }
.stat-mini .number.blue { color: #3B82F6; }
.stat-mini .number.orange { color: #F59E0B; }
.stat-mini .number.purple { color: #8B5CF6; }
.stat-mini .label {
    font-size: 0.65rem;
    color: var(--gray-500);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.alert {
    padding: 14px 18px;
    border-radius: var(--radius);
    margin-bottom: 16px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    border: 1px solid transparent;
}
.alert i {
    font-size: 1.1rem;
    margin-top: 2px;
}
.alert .alert-content {
    flex: 1;
}
.alert .alert-title {
    font-weight: 600;
    font-size: 0.9rem;
}
.alert .alert-message {
    font-size: 0.85rem;
    opacity: 0.9;
}
.alert-success {
    background: var(--success-light);
    border-color: #D1FAE5;
    color: #065F46;
}
.alert-danger {
    background: var(--danger-light);
    border-color: #FEE2E2;
    color: #991B1B;
}
.alert-warning {
    background: var(--warning-light);
    border-color: #FDE68A;
    color: #92400E;
}

.assign-form {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: var(--shadow);
}
.assign-form .form-title {
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 16px;
    color: var(--gray-700);
}
.assign-form .form-title i {
    color: var(--primary);
}
.assign-form .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr 0.8fr 0.8fr;
    gap: 16px;
    align-items: end;
}
.assign-form .form-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.assign-form .form-group label {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--gray-700);
}
.assign-form .form-group label .required {
    color: var(--danger);
}
.assign-form .form-group select,
.assign-form .form-group textarea {
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    background: white;
    width: 100%;
    transition: var(--transition);
}
.assign-form .form-group select:focus,
.assign-form .form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(15, 76, 129, 0.1);
}
.assign-form .form-group .helper-text {
    font-size: 0.7rem;
    color: var(--gray-400);
}
.assign-form .form-group textarea {
    resize: vertical;
    min-height: 38px;
    max-height: 80px;
}
.assign-form .form-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}
.assign-form .form-actions .btn-primary {
    padding: 8px 24px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: var(--radius);
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
}
.assign-form .form-actions .btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(15, 76, 129, 0.3);
}
.assign-form .form-actions .btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.assign-form .form-actions .btn-secondary {
    padding: 8px 16px;
    background: var(--gray-100);
    color: var(--gray-600);
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-weight: 500;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
}
.assign-form .form-actions .btn-secondary:hover {
    background: var(--gray-200);
}

.agents-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.agent-list {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow);
}
.agent-list .list-header {
    background: var(--gray-50);
    padding: 10px 16px;
    font-weight: 600;
    font-size: 0.8rem;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.agent-list .list-header .count {
    background: var(--gray-200);
    padding: 1px 10px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--gray-600);
}
.agent-list .list-body {
    max-height: 350px;
    overflow-y: auto;
}
.agent-list .list-body::-webkit-scrollbar {
    width: 4px;
}
.agent-list .list-body::-webkit-scrollbar-track {
    background: var(--gray-100);
}
.agent-list .list-body::-webkit-scrollbar-thumb {
    background: var(--gray-300);
    border-radius: 4px;
}
.agent-list .list-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 16px;
    border-bottom: 1px solid var(--gray-100);
    font-size: 0.82rem;
    cursor: pointer;
    transition: var(--transition);
}
.agent-list .list-item:hover {
    background: var(--gray-50);
}
.agent-list .list-item:last-child {
    border-bottom: none;
}
.agent-list .list-item .info {
    flex: 1;
    min-width: 0;
}
.agent-list .list-item .info .name {
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
}
.agent-list .list-item .info .name .avatar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--gray-200);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.6rem;
    font-weight: 600;
    color: var(--gray-600);
    flex-shrink: 0;
}
.agent-list .list-item .info .name .avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}
.agent-list .list-item .info .sub {
    font-size: 0.7rem;
    color: var(--gray-500);
}
.agent-list .list-item .badge {
    font-size: 0.6rem;
    padding: 2px 10px;
    border-radius: 20px;
    font-weight: 500;
    flex-shrink: 0;
}
.agent-list .list-item .badge.unassigned { 
    background: var(--warning-light); 
    color: #92400E; 
}
.agent-list .list-item .badge.assigned { 
    background: var(--success-light); 
    color: #065F46; 
}
.agent-list .empty-state {
    text-align: center;
    padding: 30px 20px;
    color: var(--gray-400);
}
.agent-list .empty-state i {
    font-size: 2rem;
    display: block;
    margin-bottom: 8px;
    color: var(--gray-300);
}
.agent-list .empty-state p {
    margin: 0;
    font-size: 0.85rem;
}

.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}
.modal-overlay.active {
    display: flex;
}
.modal-box {
    background: white;
    border-radius: 12px;
    max-width: 450px;
    width: 90%;
    padding: 32px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    animation: modalSlideIn 0.3s ease;
}
@keyframes modalSlideIn {
    from { transform: translateY(-20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
.modal-box .modal-icon {
    text-align: center;
    font-size: 3rem;
    margin-bottom: 12px;
    color: var(--warning);
}
.modal-box .modal-title {
    text-align: center;
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 8px;
}
.modal-box .modal-message {
    text-align: center;
    color: var(--gray-500);
    font-size: 0.9rem;
    margin-bottom: 20px;
    line-height: 1.6;
}
.modal-box .modal-actions {
    display: flex;
    gap: 10px;
    justify-content: center;
}
.modal-box .modal-actions .btn {
    padding: 8px 24px;
    border-radius: var(--radius);
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    border: none;
}
.modal-box .modal-actions .btn-confirm {
    background: var(--warning);
    color: white;
}
.modal-box .modal-actions .btn-confirm:hover {
    background: #D97706;
}
.modal-box .modal-actions .btn-cancel {
    background: var(--gray-100);
    color: var(--gray-600);
}
.modal-box .modal-actions .btn-cancel:hover {
    background: var(--gray-200);
}

@media (max-width: 1200px) {
    .assign-form .form-row {
        grid-template-columns: 1fr 1fr 1fr;
    }
}
@media (max-width: 992px) {
    .assign-form .form-row {
        grid-template-columns: 1fr 1fr;
    }
}
@media (max-width: 768px) {
    .assign-form .form-row {
        grid-template-columns: 1fr;
    }
    .agents-grid {
        grid-template-columns: 1fr;
    }
    .stats-row {
        grid-template-columns: 1fr 1fr;
    }
    .assign-form .form-actions {
        flex-direction: column;
        width: 100%;
    }
    .assign-form .form-actions .btn-primary {
        width: 100%;
        justify-content: center;
    }
    .assign-header {
        flex-direction: column;
        align-items: flex-start;
    }
}
@media (max-width: 480px) {
    .stats-row {
        grid-template-columns: 1fr;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="assign-header">
            <div>
                <h2><i class="fas fa-user-plus"></i> Assign Agents to Polling Units</h2>
                <p class="subtitle">
                    <i class="fas fa-map-marker-alt" style="color:var(--gray-400);"></i> 
                    <?php echo htmlspecialchars($ward_name); ?> Ward
                    <?php if ($ward_id): ?>
                        • Ward ID: <?php echo htmlspecialchars($ward_id); ?>
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <a href="manage-pu-agents.php" class="btn-secondary-sm" style="padding:6px 14px;border:1px solid var(--gray-200);border-radius:var(--radius);color:var(--gray-600);text-decoration:none;font-size:0.8rem;transition:var(--transition);">
                    <i class="fas fa-arrow-left"></i> Back to Agents
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-mini">
                <div class="number blue"><?php echo number_format($agent_stats['total']); ?></div>
                <div class="label">Total Agents</div>
            </div>
            <div class="stat-mini">
                <div class="number green"><?php echo number_format($agent_stats['assigned']); ?></div>
                <div class="label">Assigned</div>
            </div>
            <div class="stat-mini">
                <div class="number orange"><?php echo number_format($agent_stats['unassigned']); ?></div>
                <div class="label">Unassigned</div>
            </div>
            <div class="stat-mini">
                <div class="number purple"><?php echo number_format(count($polling_units)); ?></div>
                <div class="label">Polling Units</div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (!empty($success_message) && $show_success): ?>
            <div class="alert alert-success" id="successAlert">
                <i class="fas fa-check-circle"></i>
                <div class="alert-content">
                    <div class="alert-title">Success!</div>
                    <div class="alert-message"><?php echo htmlspecialchars($success_message); ?></div>
                </div>
                <button type="button" onclick="this.parentElement.style.display='none'" style="background:none;border:none;color:inherit;cursor:pointer;font-size:1.2rem;opacity:0.7;">&times;</button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message) && $error_message !== 'reassign_required'): ?>
            <div class="alert alert-danger" id="errorAlert">
                <i class="fas fa-exclamation-circle"></i>
                <div class="alert-content">
                    <div class="alert-title">Error</div>
                    <div class="alert-message"><?php echo htmlspecialchars($error_message); ?></div>
                </div>
                <button type="button" onclick="this.parentElement.style.display='none'" style="background:none;border:none;color:inherit;cursor:pointer;font-size:1.2rem;opacity:0.7;">&times;</button>
            </div>
        <?php endif; ?>

        <!-- Assignment Form -->
        <div class="assign-form">
            <div class="form-title"><i class="fas fa-user-plus" style="color:var(--primary);"></i> Assign Agent to Polling Unit</div>
            <form method="POST" action="" id="assignForm">
                <input type="hidden" name="action" value="assign_agent">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="agent_id"><i class="fas fa-user"></i> Select Agent <span class="required">*</span></label>
                        <select name="agent_id" id="agent_id" required>
                            <option value="">-- Select Agent --</option>
                            <?php if (count($unassigned_agents) > 0): ?>
                                <optgroup label="Unassigned Agents (<?php echo count($unassigned_agents); ?>)">
                                    <?php foreach ($unassigned_agents as $agent): ?>
                                        <option value="<?php echo $agent['id']; ?>">
                                            <?php echo htmlspecialchars($agent['full_name']); ?> (<?php echo htmlspecialchars($agent['user_code']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                            <?php if (count($assigned_agents) > 0): ?>
                                <optgroup label="Assigned Agents (Reassign - <?php echo count($assigned_agents); ?>)">
                                    <?php foreach ($assigned_agents as $agent): ?>
                                        <option value="<?php echo $agent['id']; ?>" data-assigned="1" data-pu="<?php echo htmlspecialchars($agent['pu_name'] ?? 'N/A'); ?>">
                                            <?php echo htmlspecialchars($agent['full_name']); ?> → <?php echo htmlspecialchars($agent['pu_name'] ?? 'N/A'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        </select>
                        <div class="helper-text" id="agentStatus"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="pu_id"><i class="fas fa-flag-checkered"></i> Select Polling Unit <span class="required">*</span></label>
                        <select name="pu_id" id="pu_id" required>
                            <option value="">-- Select PU --</option>
                            <?php foreach ($polling_units as $pu): ?>
                                <option value="<?php echo $pu['id']; ?>" data-assigned="<?php echo $pu['assigned_agents'] ?? 0; ?>">
                                    <?php echo htmlspecialchars($pu['name']); ?> (<?php echo htmlspecialchars($pu['code']); ?>)
                                    <?php if (($pu['assigned_agents'] ?? 0) > 0): ?>
                                        - <?php echo $pu['assigned_agents']; ?> agent(s)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="helper-text" id="puStatus"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="assignment_type"><i class="fas fa-tag"></i> Assignment Type</label>
                        <select name="assignment_type" id="assignment_type">
                            <option value="data_agent">Data Agent</option>
                            <option value="party_agent">Party Agent</option>
                            <option value="volunteer">Volunteer</option>
                            <option value="observer">Observer</option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-primary" id="assignBtn">
                            <i class="fas fa-check"></i> Assign
                        </button>
                        <button type="reset" class="btn-secondary">
                            <i class="fas fa-undo"></i> Reset
                        </button>
                    </div>
                </div>
                
                <div class="form-group" style="margin-top:12px;">
                    <label for="notes"><i class="fas fa-sticky-note"></i> Notes (Optional)</label>
                    <textarea name="notes" id="notes" placeholder="Add any notes about this assignment..." rows="2"></textarea>
                </div>
            </form>
        </div>

        <!-- Agents Lists -->
        <div class="agents-grid">
            <!-- Unassigned Agents -->
            <div class="agent-list">
                <div class="list-header">
                    <span><i class="fas fa-user-plus" style="color:var(--warning);"></i> Unassigned Agents</span>
                    <span class="count"><?php echo count($unassigned_agents); ?></span>
                </div>
                <div class="list-body">
                    <?php if (count($unassigned_agents) > 0): ?>
                        <?php foreach ($unassigned_agents as $agent): ?>
                            <div class="list-item" onclick="selectAgent(<?php echo $agent['id']; ?>)">
                                <div class="info">
                                    <div class="name">
                                        <span class="avatar">
                                            <?php if (!empty($agent['photograph_url'])): ?>
                                                <img src="<?php echo htmlspecialchars($agent['photograph_url']); ?>" alt="">
                                            <?php else: ?>
                                                <?php echo strtoupper(substr($agent['full_name'] ?? 'U', 0, 2)); ?>
                                            <?php endif; ?>
                                        </span>
                                        <?php echo htmlspecialchars($agent['full_name']); ?>
                                    </div>
                                    <div class="sub">
                                        <?php echo htmlspecialchars($agent['user_code']); ?>
                                        <?php if (!empty($agent['email'])): ?>
                                            • <?php echo htmlspecialchars($agent['email']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="badge unassigned">Unassigned</span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-user-check"></i>
                            <p>All agents are assigned.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Assigned Agents -->
            <div class="agent-list">
                <div class="list-header">
                    <span><i class="fas fa-user-check" style="color:var(--success);"></i> Assigned Agents</span>
                    <span class="count"><?php echo count($assigned_agents); ?></span>
                </div>
                <div class="list-body">
                    <?php if (count($assigned_agents) > 0): ?>
                        <?php foreach ($assigned_agents as $agent): ?>
                            <div class="list-item" onclick="selectAgent(<?php echo $agent['id']; ?>)">
                                <div class="info">
                                    <div class="name">
                                        <span class="avatar">
                                            <?php if (!empty($agent['photograph_url'])): ?>
                                                <img src="<?php echo htmlspecialchars($agent['photograph_url']); ?>" alt="">
                                            <?php else: ?>
                                                <?php echo strtoupper(substr($agent['full_name'] ?? 'U', 0, 2)); ?>
                                            <?php endif; ?>
                                        </span>
                                        <?php echo htmlspecialchars($agent['full_name']); ?>
                                    </div>
                                    <div class="sub">
                                        <?php echo htmlspecialchars($agent['user_code']); ?>
                                        <strong><?php echo htmlspecialchars($agent['pu_name'] ?? 'N/A'); ?></strong>
                                    </div>
                                </div>
                                <span class="badge assigned">Assigned</span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-user-plus"></i>
                            <p>No agents assigned yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Reassign Confirmation Modal -->
<div class="modal-overlay" id="reassignModal">
    <div class="modal-box">
        <div class="modal-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="modal-title">Reassign Agent?</div>
        <div class="modal-message">
            This agent is already assigned to a polling unit. 
            Reassigning will move them to the new polling unit.
            <br><br>
            <strong id="reassignDetails"></strong>
        </div>
        <div class="modal-actions">
            <form method="POST" action="" id="reassignForm">
                <input type="hidden" name="action" value="assign_agent">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="agent_id" id="reassignAgentId" value="">
                <input type="hidden" name="pu_id" id="reassignPuId" value="">
                <input type="hidden" name="confirm_reassign" value="1">
                <input type="hidden" name="assignment_type" id="reassignAssignmentType" value="data_agent">
                <input type="hidden" name="notes" id="reassignNotes" value="">
                <button type="submit" class="btn btn-confirm">
                    <i class="fas fa-check"></i> Yes, Reassign
                </button>
                <button type="button" class="btn btn-cancel" onclick="closeReassignModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// ============================================================
// JAVASCRIPT FUNCTIONS
// ============================================================

function selectAgent(agentId) {
    const select = document.getElementById('agent_id');
    select.value = agentId;
    select.style.borderColor = '#0F4C81';
    select.style.boxShadow = '0 0 0 3px rgba(15, 76, 129, 0.1)';
    setTimeout(() => {
        select.style.borderColor = '';
        select.style.boxShadow = '';
    }, 2000);
    updateAgentStatus();
}

function updateAgentStatus() {
    const select = document.getElementById('agent_id');
    const statusDiv = document.getElementById('agentStatus');
    const selectedOption = select.options[select.selectedIndex];
    
    if (selectedOption && selectedOption.value) {
        if (selectedOption.dataset.assigned === '1') {
            statusDiv.innerHTML = '<span style="color:var(--warning);"><i class="fas fa-info-circle"></i> This agent is already assigned to: ' + 
                (selectedOption.dataset.pu || 'Unknown') + '. Reassignment will be required.</span>';
        } else {
            statusDiv.innerHTML = '<span style="color:var(--success);"><i class="fas fa-check-circle"></i> Agent is available for assignment.</span>';
        }
    } else {
        statusDiv.innerHTML = '';
    }
}

function updatePuStatus() {
    const select = document.getElementById('pu_id');
    const statusDiv = document.getElementById('puStatus');
    const selectedOption = select.options[select.selectedIndex];
    
    if (selectedOption && selectedOption.value) {
        const assigned = parseInt(selectedOption.dataset.assigned) || 0;
        if (assigned > 0) {
            statusDiv.innerHTML = '<span style="color:var(--warning);"><i class="fas fa-info-circle"></i> This PU already has ' + 
                assigned + ' agent(s) assigned.</span>';
        } else {
            statusDiv.innerHTML = '<span style="color:var(--success);"><i class="fas fa-check-circle"></i> This PU is available.</span>';
        }
    } else {
        statusDiv.innerHTML = '';
    }
}

function openReassignModal(agentId, puId) {
    document.getElementById('reassignAgentId').value = agentId;
    document.getElementById('reassignPuId').value = puId;
    document.getElementById('reassignAssignmentType').value = document.getElementById('assignment_type').value;
    document.getElementById('reassignNotes').value = document.getElementById('notes').value;
    
    const agentSelect = document.getElementById('agent_id');
    const puSelect = document.getElementById('pu_id');
    const agentName = agentSelect.options[agentSelect.selectedIndex]?.text || 'Agent';
    const puName = puSelect.options[puSelect.selectedIndex]?.text || 'Polling Unit';
    
    document.getElementById('reassignDetails').textContent = 
        agentName + ' → ' + puName;
    
    document.getElementById('reassignModal').classList.add('active');
}

function closeReassignModal() {
    document.getElementById('reassignModal').classList.remove('active');
}

document.getElementById('assignForm').addEventListener('submit', function(e) {
    const agentId = document.getElementById('agent_id').value;
    const puId = document.getElementById('pu_id').value;
    const agentSelect = document.getElementById('agent_id');
    const selectedOption = agentSelect.options[agentSelect.selectedIndex];
    
    if (!agentId || !puId) {
        e.preventDefault();
        alert('Please select both an agent and a polling unit.');
        return false;
    }
    
    if (selectedOption && selectedOption.dataset.assigned === '1') {
        e.preventDefault();
        openReassignModal(agentId, puId);
        return false;
    }
    
    return true;
});

document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.style.display = 'none';
            }, 500);
        }, 5000);
    });
    
    document.getElementById('agent_id').addEventListener('change', updateAgentStatus);
    document.getElementById('pu_id').addEventListener('change', updatePuStatus);
    
    updateAgentStatus();
    updatePuStatus();
});

// ============================================================
// SIDEBAR TOGGLE (from parent)
// ============================================================
var sidebar = document.getElementById('sidebar');
var sidebarToggle = document.getElementById('sidebarToggle');
var sidebarOverlay = document.getElementById('sidebarOverlay');
var dashboardHeader = document.getElementById('dashboardHeader');

function toggleSidebar() {
    sidebar.classList.toggle('open');
    sidebarOverlay.classList.toggle('active');
    updateHeaderPosition();
}

function updateHeaderPosition() {
    if (window.innerWidth > 768) {
        dashboardHeader.style.left = '260px';
    } else if (sidebar.classList.contains('open')) {
        dashboardHeader.style.left = '280px';
    } else {
        dashboardHeader.style.left = '0';
    }
}

if (sidebarToggle) {
    sidebarToggle.addEventListener('click', toggleSidebar);
}
if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', toggleSidebar);
}

window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
        sidebar.classList.remove('open');
        sidebarOverlay.classList.remove('active');
        dashboardHeader.style.left = '260px';
    } else if (!sidebar.classList.contains('open')) {
        dashboardHeader.style.left = '0';
    }
});

document.querySelectorAll('.dropdown-toggle').forEach(function(toggle) {
    toggle.addEventListener('click', function(e) {
        e.preventDefault();
        var dropdownId = this.dataset.dropdown;
        var dropdown = document.getElementById(dropdownId);
        var chevron = this.querySelector('.chevron');
        if (dropdown) {
            dropdown.classList.toggle('open');
            if (chevron) chevron.classList.toggle('open');
        }
    });
});

var profileBtn = document.getElementById('profileBtn');
var profileMenu = document.getElementById('profileMenu');

if (profileBtn && profileMenu) {
    profileBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        profileMenu.classList.toggle('active');
    });
    document.addEventListener('click', function(e) {
        if (!profileBtn.contains(e.target) && !profileMenu.contains(e.target)) {
            profileMenu.classList.remove('active');
        }
    });
}

window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});
</script>
</body>
</html>