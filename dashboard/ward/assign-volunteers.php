<?php
// ============================================================
// WARD COORDINATOR - ASSIGN VOLUNTEERS (COMPLETE REWRITE)
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
            
            // Update session
            SessionManager::set('ward_id', $ward_id);
            SessionManager::set('lga_id', $lga_id);
            SessionManager::set('state_id', $state_id);
        } else {
            // No ward assigned - redirect to error
            $_SESSION['flash_error'] = 'You have not been assigned to any ward. Please contact your administrator.';
            header('Location: ../client-admin/dashboard.php');
            exit();
        }
    } catch (Exception $e) {
        error_log("Error fetching ward_id: " . $e->getMessage());
        $_SESSION['flash_error'] = 'Database error occurred. Please try again.';
        header('Location: ../client-admin/dashboard.php');
        exit();
    }
}

// ============================================================
// FIX: Function to ensure active election exists
// ============================================================
function ensureActiveElection($db, $tenant_id, $ward_id, $user_id) {
    try {
        // First, check if there's an active election that includes this ward
        $stmt = $db->prepare("
            SELECT id, name FROM elections 
            WHERE tenant_id = ? 
            AND status = 'active' 
            AND JSON_CONTAINS(wards_json, JSON_QUOTE(?))
            LIMIT 1
        ");
        $stmt->execute([$tenant_id, $ward_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return ['id' => $result['id'], 'name' => $result['name'], 'created' => false];
        }
        
        // No active election found - create a default one
        $db->beginTransaction();
        
        $election_name = 'Default Active Election - ' . date('Y-m-d H:i:s');
        $election_date = date('Y-m-d', strtotime('+1 year'));
        
        $stmt = $db->prepare("
            INSERT INTO elections (
                tenant_id, 
                name, 
                type, 
                cycle, 
                election_date, 
                start_time,
                status, 
                wards_json, 
                created_by,
                created_at,
                updated_at
            ) VALUES (?, ?, 'governorship', '2031', ?, '08:00:00', 'active', JSON_ARRAY(?), ?, NOW(), NOW())
        ");
        $stmt->execute([$tenant_id, $election_name, $election_date, $ward_id, $user_id]);
        $election_id = $db->lastInsertId();
        
        $db->commit();
        
        error_log("Created default active election ID: $election_id for tenant: $tenant_id, ward: $ward_id");
        return ['id' => $election_id, 'name' => $election_name, 'created' => true];
        
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Error ensuring active election: " . $e->getMessage());
        throw $e;
    }
}

// ============================================================
// FIX: Improved function to get polling unit details with debugging
// ============================================================
function getPollingUnitDetails($db, $pu_id, $ward_id = null) {
    try {
        // First, just check if the PU exists
        $stmt = $db->prepare("
            SELECT 
                id, 
                name, 
                code, 
                ward_id, 
                lga_id, 
                state_id, 
                registered_voters,
                is_active,
                description,
                address
            FROM polling_units 
            WHERE id = ?
        ");
        $stmt->execute([$pu_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            error_log("Polling Unit ID $pu_id not found in database");
            return null;
        }
        
        // Check if active
        if ($result['is_active'] != 1) {
            error_log("Polling Unit ID $pu_id is not active (is_active = {$result['is_active']})");
            // Still return it but with a warning
            $result['warning'] = 'Polling unit is not active';
        }
        
        // Check ward match if provided
        if ($ward_id && $result['ward_id'] != $ward_id) {
            error_log("Polling Unit ID $pu_id belongs to ward {$result['ward_id']} but current ward is $ward_id");
            // Still return it but with a warning
            $result['warning'] = 'Polling unit belongs to a different ward';
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Error fetching polling unit: " . $e->getMessage());
        return null;
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
// FETCH VOLUNTEERS AND POLLING UNITS
// ============================================================
$unassigned_volunteers = [];
$assigned_volunteers = [];
$polling_units = [];

try {
    // Get unassigned volunteers (role_id = 15 for Volunteer)
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
        AND u.role_id = 15
        AND (u.pu_id IS NULL OR u.pu_id = 0)
        AND u.status = 'active'
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $unassigned_volunteers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get assigned volunteers (role_id = 15 for Volunteer)
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
            pu.code as pu_code,
            pu.registered_voters
        FROM users u
        LEFT JOIN polling_units pu ON u.pu_id = pu.id
        WHERE u.tenant_id = ? 
        AND u.ward_id = ?
        AND u.deleted_at IS NULL
        AND u.role_id = 15
        AND u.pu_id IS NOT NULL
        AND u.pu_id > 0
        AND u.status = 'active'
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $assigned_volunteers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get polling units in this ward
    $stmt = $db->prepare("
        SELECT 
            pu.id,
            pu.name,
            pu.code,
            pu.registered_voters,
            pu.is_active,
            pu.ward_id,
            pu.description,
            (SELECT COUNT(*) FROM users u 
             WHERE u.pu_id = pu.id AND u.role_id = 15 AND u.status = 'active' AND u.deleted_at IS NULL) as assigned_count
        FROM polling_units pu
        WHERE pu.ward_id = ? AND pu.is_active = 1
        ORDER BY pu.name ASC
    ");
    $stmt->execute([$ward_id]);
    $polling_units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // DEBUG: Log the polling units found
    error_log("Found " . count($polling_units) . " polling units for ward $ward_id");
    
} catch (Exception $e) {
    error_log("Error fetching data: " . $e->getMessage());
    $_SESSION['flash_error'] = 'Error loading data. Please refresh the page.';
}

// ============================================================
// HANDLE ASSIGNMENT FORM SUBMISSION
// ============================================================
$success_message = '';
$error_message = '';
$show_success = false;
$debug_info = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_volunteer') {
    $volunteer_id = isset($_POST['volunteer_id']) ? (int)$_POST['volunteer_id'] : 0;
    $pu_id = isset($_POST['pu_id']) ? (int)$_POST['pu_id'] : 0;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $assignment_type = isset($_POST['assignment_type']) ? $_POST['assignment_type'] : 'volunteer';
    
    // CSRF Protection
    $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    $session_token = SessionManager::get('csrf_token');
    
    if (empty($csrf_token) || $csrf_token !== $session_token) {
        $error_message = 'Security validation failed. Please try again.';
    } elseif ($volunteer_id <= 0 || $pu_id <= 0) {
        $error_message = 'Please select both a volunteer and a polling unit.';
    } else {
        try {
            $db->beginTransaction();
            
            // Verify the volunteer exists
            $stmt = $db->prepare("
                SELECT id, full_name, pu_id, status, role_id, tenant_id, ward_id 
                FROM users 
                WHERE id = ? AND tenant_id = ? AND ward_id = ? AND deleted_at IS NULL
            ");
            $stmt->execute([$volunteer_id, $tenant_id, $ward_id]);
            $volunteer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$volunteer) {
                throw new Exception('Volunteer not found or does not belong to your ward.');
            }
            
            // Debug: Log volunteer data
            error_log("Volunteer found: ID={$volunteer['id']}, Name={$volunteer['full_name']}, Role={$volunteer['role_id']}, PU={$volunteer['pu_id']}");
            
            if ($volunteer['role_id'] != 15) {
                throw new Exception('Selected user is not a volunteer (role_id = ' . $volunteer['role_id'] . ').');
            }
            
            if ($volunteer['status'] !== 'active') {
                throw new Exception('Volunteer is not active. Please activate them first.');
            }
            
            // If volunteer is already assigned, confirm reassignment
            if (!empty($volunteer['pu_id']) && $volunteer['pu_id'] > 0) {
                if (!isset($_POST['confirm_reassign']) || $_POST['confirm_reassign'] !== '1') {
                    throw new Exception('reassign_required');
                }
            }
            
            // DEBUG: Verify the polling unit exists
            error_log("Looking for polling unit ID: $pu_id in ward: $ward_id");
            
            // Get polling unit details with debugging
            $pu_details = getPollingUnitDetails($db, $pu_id, $ward_id);
            
            if (!$pu_details) {
                // Check if the PU exists at all
                $stmt = $db->prepare("SELECT id, name, ward_id, is_active FROM polling_units WHERE id = ?");
                $stmt->execute([$pu_id]);
                $check_pu = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($check_pu) {
                    error_log("PU exists but getPollingUnitDetails returned null. PU data: " . print_r($check_pu, true));
                    throw new Exception('Polling unit exists but could not be loaded. Ward mismatch or inactive.');
                } else {
                    error_log("PU ID $pu_id does not exist in database");
                    throw new Exception('Polling unit not found. Please select a valid polling unit.');
                }
            }
            
            // Debug: Log PU details
            error_log("Polling Unit found: " . print_r($pu_details, true));
            
            // Check if PU belongs to the same ward
            if ($pu_details['ward_id'] != $ward_id) {
                error_log("PU ward_id: {$pu_details['ward_id']} != current ward_id: $ward_id");
                throw new Exception('Polling unit does not belong to your ward. Please select a polling unit from your ward.');
            }
            
            // Check if PU is active
            if ($pu_details['is_active'] != 1) {
                throw new Exception('Polling unit is not active. Please select an active polling unit.');
            }
            
            // Get or create active election
            $election_data = ensureActiveElection($db, $tenant_id, $ward_id, $user_id);
            $election_id = $election_data['id'];
            
            // If election was just created, log it
            if ($election_data['created']) {
                logActivity($user_id, 'election_created', "Created default election: {$election_data['name']} (ID: $election_id) for tenant ID: $tenant_id", 'elections', $election_id);
            }
            
            // Update user's PU assignment
            $stmt = $db->prepare("UPDATE users SET pu_id = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$pu_id, $volunteer_id, $tenant_id]);
            
            // Check if an assignment already exists
            $stmt = $db->prepare("
                SELECT id FROM agent_assignments 
                WHERE user_id = ? AND election_id = ? AND pu_id = ? AND status != 'completed'
            ");
            $stmt->execute([$volunteer_id, $election_id, $pu_id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Update existing assignment
                $stmt = $db->prepare("
                    UPDATE agent_assignments 
                    SET status = 'active', assigned_by = ?, assigned_at = NOW(), notes = CONCAT(notes, '\nReassigned: ', ?)
                    WHERE id = ?
                ");
                $stmt->execute([$user_id, $notes, $existing['id']]);
                $assignment_id = $existing['id'];
            } else {
                // Create new assignment record
                $stmt = $db->prepare("
                    INSERT INTO agent_assignments (
                        tenant_id, 
                        election_id, 
                        user_id, 
                        pu_id, 
                        ward_id, 
                        lga_id, 
                        state_id,
                        assignment_type, 
                        status, 
                        assigned_by, 
                        notes, 
                        assigned_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $tenant_id,
                    $election_id,
                    $volunteer_id,
                    $pu_id,
                    $ward_id,
                    $lga_id,
                    $state_id,
                    $assignment_type,
                    $user_id,
                    $notes
                ]);
                $assignment_id = $db->lastInsertId();
            }
            
            // Log the activity
            logActivity($user_id, 'volunteer_assigned', "Assigned volunteer: {$volunteer['full_name']} (ID: $volunteer_id) to PU: {$pu_details['name']} (ID: $pu_id)", 'user', $volunteer_id);
            
            $db->commit();
            $success_message = "Volunteer assigned successfully!";
            $show_success = true;
            
            // Refresh data after assignment
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
                WHERE u.tenant_id = ? AND u.ward_id = ? AND u.deleted_at IS NULL
                AND u.role_id = 15 AND (u.pu_id IS NULL OR u.pu_id = 0) AND u.status = 'active'
                ORDER BY u.full_name ASC
            ");
            $stmt->execute([$tenant_id, $ward_id]);
            $unassigned_volunteers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
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
                    pu.code as pu_code,
                    pu.registered_voters
                FROM users u
                LEFT JOIN polling_units pu ON u.pu_id = pu.id
                WHERE u.tenant_id = ? AND u.ward_id = ? AND u.deleted_at IS NULL
                AND u.role_id = 15 AND u.pu_id IS NOT NULL AND u.pu_id > 0 AND u.status = 'active'
                ORDER BY u.full_name ASC
            ");
            $stmt->execute([$tenant_id, $ward_id]);
            $assigned_volunteers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Refresh polling units
            $stmt = $db->prepare("
                SELECT 
                    pu.id,
                    pu.name,
                    pu.code,
                    pu.registered_voters,
                    pu.is_active,
                    pu.ward_id,
                    (SELECT COUNT(*) FROM users u 
                     WHERE u.pu_id = pu.id AND u.role_id = 15 AND u.status = 'active' AND u.deleted_at IS NULL) as assigned_count
                FROM polling_units pu
                WHERE pu.ward_id = ? AND pu.is_active = 1
                ORDER BY pu.name ASC
            ");
            $stmt->execute([$ward_id]);
            $polling_units = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $db->rollBack();
            
            if ($e->getMessage() === 'reassign_required') {
                // Special case: Need confirmation for reassignment
                $error_message = 'reassign_required';
                $reassign_volunteer_id = $volunteer_id;
                $reassign_pu_id = $pu_id;
            } else {
                $error_message = "Error assigning volunteer: " . $e->getMessage();
                error_log("Volunteer assignment error: " . $e->getMessage());
                
                // Add debug info for polling unit issues
                if (strpos($e->getMessage(), 'Polling unit') !== false) {
                    $debug_info = "Debug: Please check that the selected polling unit exists and is active in your ward.";
                }
            }
        }
    }
}

// Generate CSRF token
$csrf_token = bin2hex(random_bytes(32));
SessionManager::set('csrf_token', $csrf_token);

// Page title and includes
$page_title = 'Assign Volunteers';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
/* ============================================================
   MAIN STYLES (Same as before)
   ============================================================ */
:root {
    --primary: #0F4C81;
    --primary-light: #1a6bb5;
    --primary-dark: #0a3a62;
    --success: #10B981;
    --success-light: #ECFDF5;
    --danger: #EF4444;
    --danger-light: #FEF2F2;
    --warning: #F59E0B;
    --warning-light: #FEF3C7;
    --gray-50: #F9FAFB;
    --gray-100: #F3F4F6;
    --gray-200: #E5E7EB;
    --gray-300: #D1D5DB;
    --gray-400: #9CA3AF;
    --gray-500: #6B7280;
    --gray-600: #4B5563;
    --gray-700: #374151;
    --gray-800: #1F2937;
    --gray-900: #111827;
    --radius: 8px;
    --shadow: 0 1px 3px rgba(0,0,0,0.1);
    --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
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
.alert .debug-info {
    font-size: 0.75rem;
    color: var(--gray-500);
    margin-top: 4px;
    font-family: monospace;
    background: var(--gray-100);
    padding: 4px 8px;
    border-radius: 4px;
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
.alert-info {
    background: #EFF6FF;
    border-color: #BFDBFE;
    color: #1E40AF;
}

.stats-bar {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}
.stat-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 14px 18px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.stat-card .stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.stat-card .stat-icon.blue { background: #EFF6FF; color: #3B82F6; }
.stat-card .stat-icon.green { background: #ECFDF5; color: #10B981; }
.stat-card .stat-icon.yellow { background: #FEF3C7; color: #F59E0B; }
.stat-card .stat-icon.purple { background: #F3E8FF; color: #8B5CF6; }
.stat-card .stat-info .stat-number {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--gray-800);
    line-height: 1.2;
}
.stat-card .stat-info .stat-label {
    font-size: 0.7rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
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
.assign-form .form-group select:disabled {
    background: var(--gray-100);
    cursor: not-allowed;
}
.assign-form .form-group textarea {
    resize: vertical;
    min-height: 38px;
    max-height: 80px;
}
.assign-form .form-group .helper-text {
    font-size: 0.7rem;
    color: var(--gray-400);
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
    transform: none;
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

.volunteers-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.volunteer-list {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow);
}
.volunteer-list .list-header {
    background: var(--gray-50);
    padding: 10px 16px;
    font-weight: 600;
    font-size: 0.8rem;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.volunteer-list .list-header .count {
    background: var(--gray-200);
    padding: 1px 10px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    color: var(--gray-600);
}
.volunteer-list .list-body {
    max-height: 350px;
    overflow-y: auto;
}
.volunteer-list .list-body::-webkit-scrollbar {
    width: 4px;
}
.volunteer-list .list-body::-webkit-scrollbar-track {
    background: var(--gray-100);
}
.volunteer-list .list-body::-webkit-scrollbar-thumb {
    background: var(--gray-300);
    border-radius: 4px;
}
.volunteer-list .list-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 16px;
    border-bottom: 1px solid var(--gray-100);
    font-size: 0.82rem;
    cursor: pointer;
    transition: var(--transition);
}
.volunteer-list .list-item:hover {
    background: var(--gray-50);
}
.volunteer-list .list-item:last-child {
    border-bottom: none;
}
.volunteer-list .list-item .info {
    flex: 1;
    min-width: 0;
}
.volunteer-list .list-item .info .name {
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
}
.volunteer-list .list-item .info .name .avatar {
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
.volunteer-list .list-item .info .name .avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}
.volunteer-list .list-item .info .sub {
    font-size: 0.7rem;
    color: var(--gray-500);
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.volunteer-list .list-item .info .sub .code {
    color: var(--gray-400);
    font-family: monospace;
}
.volunteer-list .list-item .badge {
    font-size: 0.6rem;
    padding: 2px 10px;
    border-radius: 20px;
    font-weight: 500;
    flex-shrink: 0;
}
.volunteer-list .list-item .badge.unassigned { 
    background: var(--warning-light); 
    color: #92400E; 
}
.volunteer-list .list-item .badge.assigned { 
    background: var(--success-light); 
    color: #065F46; 
}
.volunteer-list .list-item .badge.pu-info {
    background: #EFF6FF;
    color: #1E40AF;
    font-size: 0.55rem;
}
.volunteer-list .empty-state {
    text-align: center;
    padding: 30px 20px;
    color: var(--gray-400);
}
.volunteer-list .empty-state i {
    font-size: 2rem;
    display: block;
    margin-bottom: 8px;
    color: var(--gray-300);
}
.volunteer-list .empty-state p {
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
    .volunteers-grid {
        grid-template-columns: 1fr;
    }
    .stats-bar {
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
}
@media (max-width: 480px) {
    .stats-bar {
        grid-template-columns: 1fr;
    }
    .assign-header {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="assign-header">
            <div>
                <h2><i class="fas fa-hands-helping"></i> Assign Volunteers</h2>
                <p class="subtitle">
                    <i class="fas fa-map-marker-alt" style="color:var(--gray-400);"></i> 
                    <?php echo htmlspecialchars($ward_name); ?> Ward
                    <?php if ($ward_id): ?>
                        • Ward ID: <?php echo htmlspecialchars($ward_id); ?>
                    <?php endif; ?>
                    <?php if ($lga_id): ?>
                        • LGA ID: <?php echo htmlspecialchars($lga_id); ?>
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <a href="manage-volunteers.php" class="btn-secondary-sm" style="padding:6px 14px;border:1px solid var(--gray-200);border-radius:var(--radius);color:var(--gray-600);text-decoration:none;font-size:0.8rem;transition:var(--transition);">
                    <i class="fas fa-arrow-left"></i> Back to Volunteers
                </a>
            </div>
        </div>

        <!-- Stats Bar -->
        <div class="stats-bar">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo count($unassigned_volunteers) + count($assigned_volunteers); ?></div>
                    <div class="stat-label">Total Volunteers</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-user-plus"></i></div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo count($unassigned_volunteers); ?></div>
                    <div class="stat-label">Unassigned</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-user-check"></i></div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo count($assigned_volunteers); ?></div>
                    <div class="stat-label">Assigned</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-flag-checkered"></i></div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo count($polling_units); ?></div>
                    <div class="stat-label">Polling Units</div>
                </div>
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
                    <?php if (!empty($debug_info)): ?>
                        <div class="debug-info"><?php echo htmlspecialchars($debug_info); ?></div>
                    <?php endif; ?>
                </div>
                <button type="button" onclick="this.parentElement.style.display='none'" style="background:none;border:none;color:inherit;cursor:pointer;font-size:1.2rem;opacity:0.7;">&times;</button>
            </div>
        <?php endif; ?>

        <!-- Assignment Form -->
        <div class="assign-form">
            <div class="form-title"><i class="fas fa-user-plus" style="color:var(--primary);"></i> Assign Volunteer to Polling Unit</div>
            <form method="POST" action="" id="assignForm">
                <input type="hidden" name="action" value="assign_volunteer">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="volunteer_id"><i class="fas fa-user"></i> Select Volunteer <span class="required">*</span></label>
                        <select name="volunteer_id" id="volunteer_id" required>
                            <option value="">-- Select Volunteer --</option>
                            <?php if (count($unassigned_volunteers) > 0): ?>
                                <optgroup label="Unassigned Volunteers (<?php echo count($unassigned_volunteers); ?>)">
                                    <?php foreach ($unassigned_volunteers as $volunteer): ?>
                                        <option value="<?php echo $volunteer['id']; ?>">
                                            <?php echo htmlspecialchars($volunteer['full_name']); ?> (<?php echo htmlspecialchars($volunteer['user_code']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                            <?php if (count($assigned_volunteers) > 0): ?>
                                <optgroup label="Assigned Volunteers (Reassign - <?php echo count($assigned_volunteers); ?>)">
                                    <?php foreach ($assigned_volunteers as $volunteer): ?>
                                        <option value="<?php echo $volunteer['id']; ?>" data-assigned="1" data-pu="<?php echo htmlspecialchars($volunteer['pu_name'] ?? 'N/A'); ?>">
                                            <?php echo htmlspecialchars($volunteer['full_name']); ?> → <?php echo htmlspecialchars($volunteer['pu_name'] ?? 'N/A'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        </select>
                        <div class="helper-text" id="volunteerStatus"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="pu_id"><i class="fas fa-flag-checkered"></i> Select Polling Unit <span class="required">*</span></label>
                        <select name="pu_id" id="pu_id" required>
                            <option value="">-- Select PU --</option>
                            <?php if (count($polling_units) > 0): ?>
                                <?php foreach ($polling_units as $pu): ?>
                                    <option value="<?php echo $pu['id']; ?>" data-assigned="<?php echo $pu['assigned_count'] ?? 0; ?>" data-ward="<?php echo $pu['ward_id']; ?>">
                                        <?php echo htmlspecialchars($pu['name']); ?> (<?php echo htmlspecialchars($pu['code']); ?>)
                                        <?php if (($pu['assigned_count'] ?? 0) > 0): ?>
                                            - <?php echo $pu['assigned_count']; ?> assigned
                                        <?php endif; ?>
                                        <?php if (isset($pu['description']) && !empty($pu['description'])): ?>
                                            - <?php echo htmlspecialchars($pu['description']); ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>No polling units available in this ward</option>
                            <?php endif; ?>
                        </select>
                        <div class="helper-text" id="puStatus">
                            <?php if (count($polling_units) === 0): ?>
                                <span style="color:var(--danger);"><i class="fas fa-exclamation-circle"></i> No polling units found. Please add polling units to this ward first.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="assignment_type"><i class="fas fa-tag"></i> Assignment Type</label>
                        <select name="assignment_type" id="assignment_type">
                            <option value="volunteer">Volunteer</option>
                            <option value="data_agent">Data Agent</option>
                            <option value="party_agent">Party Agent</option>
                            <option value="observer">Observer</option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-primary" id="assignBtn" <?php echo count($polling_units) === 0 ? 'disabled' : ''; ?>>
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

        <!-- Volunteers Lists -->
        <div class="volunteers-grid">
            <!-- Unassigned Volunteers -->
            <div class="volunteer-list">
                <div class="list-header">
                    <span><i class="fas fa-user-plus" style="color:var(--warning);"></i> Unassigned Volunteers</span>
                    <span class="count"><?php echo count($unassigned_volunteers); ?></span>
                </div>
                <div class="list-body">
                    <?php if (count($unassigned_volunteers) > 0): ?>
                        <?php foreach ($unassigned_volunteers as $volunteer): ?>
                            <div class="list-item" onclick="selectVolunteer(<?php echo $volunteer['id']; ?>)">
                                <div class="info">
                                    <div class="name">
                                        <span class="avatar">
                                            <?php if (!empty($volunteer['photograph_url'])): ?>
                                                <img src="<?php echo htmlspecialchars($volunteer['photograph_url']); ?>" alt="">
                                            <?php else: ?>
                                                <?php echo strtoupper(substr($volunteer['full_name'] ?? 'U', 0, 2)); ?>
                                            <?php endif; ?>
                                        </span>
                                        <?php echo htmlspecialchars($volunteer['full_name']); ?>
                                    </div>
                                    <div class="sub">
                                        <span class="code"><?php echo htmlspecialchars($volunteer['user_code']); ?></span>
                                        <?php if (!empty($volunteer['phone'])): ?>
                                            • <?php echo htmlspecialchars($volunteer['phone']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="badge unassigned">Unassigned</span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-user-check"></i>
                            <p>All volunteers are assigned.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Assigned Volunteers -->
            <div class="volunteer-list">
                <div class="list-header">
                    <span><i class="fas fa-user-check" style="color:var(--success);"></i> Assigned Volunteers</span>
                    <span class="count"><?php echo count($assigned_volunteers); ?></span>
                </div>
                <div class="list-body">
                    <?php if (count($assigned_volunteers) > 0): ?>
                        <?php foreach ($assigned_volunteers as $volunteer): ?>
                            <div class="list-item" onclick="selectVolunteer(<?php echo $volunteer['id']; ?>)">
                                <div class="info">
                                    <div class="name">
                                        <span class="avatar">
                                            <?php if (!empty($volunteer['photograph_url'])): ?>
                                                <img src="<?php echo htmlspecialchars($volunteer['photograph_url']); ?>" alt="">
                                            <?php else: ?>
                                                <?php echo strtoupper(substr($volunteer['full_name'] ?? 'U', 0, 2)); ?>
                                            <?php endif; ?>
                                        </span>
                                        <?php echo htmlspecialchars($volunteer['full_name']); ?>
                                    </div>
                                    <div class="sub">
                                        <span class="code"><?php echo htmlspecialchars($volunteer['user_code']); ?></span>
                                        <span class="badge pu-info">
                                            <i class="fas fa-map-pin"></i> <?php echo htmlspecialchars($volunteer['pu_name'] ?? 'N/A'); ?>
                                        </span>
                                    </div>
                                </div>
                                <span class="badge assigned">Assigned</span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-user-plus"></i>
                            <p>No volunteers assigned yet.</p>
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
        <div class="modal-title">Reassign Volunteer?</div>
        <div class="modal-message">
            This volunteer is already assigned to a polling unit. 
            Reassigning will move them to the new polling unit.
            <br><br>
            <strong id="reassignDetails"></strong>
        </div>
        <div class="modal-actions">
            <form method="POST" action="" id="reassignForm">
                <input type="hidden" name="action" value="assign_volunteer">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="volunteer_id" id="reassignVolunteerId" value="">
                <input type="hidden" name="pu_id" id="reassignPuId" value="">
                <input type="hidden" name="confirm_reassign" value="1">
                <input type="hidden" name="assignment_type" id="reassignAssignmentType" value="volunteer">
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

function selectVolunteer(volunteerId) {
    const select = document.getElementById('volunteer_id');
    select.value = volunteerId;
    select.style.borderColor = '#0F4C81';
    select.style.boxShadow = '0 0 0 3px rgba(15, 76, 129, 0.1)';
    setTimeout(() => {
        select.style.borderColor = '';
        select.style.boxShadow = '';
    }, 2000);
    updateVolunteerStatus();
}

function updateVolunteerStatus() {
    const select = document.getElementById('volunteer_id');
    const statusDiv = document.getElementById('volunteerStatus');
    const selectedOption = select.options[select.selectedIndex];
    
    if (selectedOption && selectedOption.value) {
        if (selectedOption.dataset.assigned === '1') {
            statusDiv.innerHTML = '<span style="color:var(--warning);"><i class="fas fa-info-circle"></i> This volunteer is already assigned to: ' + 
                (selectedOption.dataset.pu || 'Unknown') + '. Reassignment will be required.</span>';
        } else {
            statusDiv.innerHTML = '<span style="color:var(--success);"><i class="fas fa-check-circle"></i> Volunteer is available for assignment.</span>';
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
        const wardId = selectedOption.dataset.ward || '';
        if (assigned > 0) {
            statusDiv.innerHTML = '<span style="color:var(--warning);"><i class="fas fa-info-circle"></i> This PU already has ' + 
                assigned + ' volunteer(s) assigned.</span>';
        } else {
            statusDiv.innerHTML = '<span style="color:var(--success);"><i class="fas fa-check-circle"></i> This PU is available.</span>';
        }
    } else {
        statusDiv.innerHTML = '';
    }
}

function openReassignModal(volunteerId, puId) {
    document.getElementById('reassignVolunteerId').value = volunteerId;
    document.getElementById('reassignPuId').value = puId;
    document.getElementById('reassignAssignmentType').value = document.getElementById('assignment_type').value;
    document.getElementById('reassignNotes').value = document.getElementById('notes').value;
    
    const volunteerSelect = document.getElementById('volunteer_id');
    const puSelect = document.getElementById('pu_id');
    const volunteerName = volunteerSelect.options[volunteerSelect.selectedIndex]?.text || 'Volunteer';
    const puName = puSelect.options[puSelect.selectedIndex]?.text || 'Polling Unit';
    
    document.getElementById('reassignDetails').textContent = 
        volunteerName + ' → ' + puName;
    
    document.getElementById('reassignModal').classList.add('active');
}

function closeReassignModal() {
    document.getElementById('reassignModal').classList.remove('active');
}

document.getElementById('assignForm').addEventListener('submit', function(e) {
    const volunteerId = document.getElementById('volunteer_id').value;
    const puId = document.getElementById('pu_id').value;
    const volunteerSelect = document.getElementById('volunteer_id');
    const selectedOption = volunteerSelect.options[volunteerSelect.selectedIndex];
    
    if (!volunteerId || !puId) {
        e.preventDefault();
        alert('Please select both a volunteer and a polling unit.');
        return false;
    }
    
    if (selectedOption && selectedOption.dataset.assigned === '1') {
        e.preventDefault();
        openReassignModal(volunteerId, puId);
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
    
    document.getElementById('volunteer_id').addEventListener('change', updateVolunteerStatus);
    document.getElementById('pu_id').addEventListener('change', updatePuStatus);
    
    updateVolunteerStatus();
    updatePuStatus();
});

// Sidebar toggle
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