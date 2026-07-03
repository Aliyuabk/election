<?php
// ============================================================
// UNIFIED RESULTS ENTRY - ALL LEVELS (PROFESSIONAL UI)
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Start session and check login
SessionManager::start();

// Redirect if not logged in
if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

// Check role - only client_admin can access this page
if (SessionManager::get('role_level') !== 'client_admin') {
    header('Location: ../client-admin/');
    exit();
}

// Get database connection
$db = getDB();

// Get user info
$user_id = SessionManager::get('user_id');
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');
$tenant_id = SessionManager::get('tenant_id');

// ============================================================
// FETCH ELECTIONS FOR DROPDOWN
// ============================================================
$elections = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, type, status, election_date 
        FROM elections 
        WHERE tenant_id = ? AND deleted_at IS NULL AND status IN ('upcoming', 'active')
        ORDER BY election_date DESC
    ");
    $stmt->execute([$tenant_id]);
    $elections = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH STATES FOR DROPDOWN
// ============================================================
$states = [];
try {
    $stmt = $db->query("SELECT id, name, code FROM states WHERE is_active = 1 ORDER BY name");
    $states = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH LGAS FOR DROPDOWN
// ============================================================
$lgas = [];
try {
    $stmt = $db->query("SELECT l.id, l.name, l.code, s.name as state_name FROM lgas l LEFT JOIN states s ON l.state_id = s.id WHERE l.is_active = 1 ORDER BY s.name, l.name");
    $lgas = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH WARDS FOR DROPDOWN
// ============================================================
$wards = [];
try {
    $stmt = $db->query("SELECT w.id, w.name, w.code, l.name as lga_name FROM wards w LEFT JOIN lgas l ON w.lga_id = l.id WHERE w.is_active = 1 ORDER BY l.name, w.name");
    $wards = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH POLLING UNITS FOR DROPDOWN
// ============================================================
$polling_units = [];
try {
    $stmt = $db->query("
        SELECT pu.id, pu.code, pu.name, w.name as ward_name, l.name as lga_name, s.name as state_name 
        FROM polling_units pu 
        LEFT JOIN wards w ON pu.ward_id = w.id 
        LEFT JOIN lgas l ON w.lga_id = l.id 
        LEFT JOIN states s ON l.state_id = s.id 
        WHERE pu.is_active = 1 
        ORDER BY s.name, l.name, w.name, pu.name
    ");
    $polling_units = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH AGENTS FOR DROPDOWN
// ============================================================
$agents = [];
try {
    $stmt = $db->prepare("
        SELECT u.id, u.first_name, u.last_name, r.name as role_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.tenant_id = ? AND u.status = 'active' 
        AND r.level IN ('pu_agent', 'party_agent', 'volunteer', 'observer')
        ORDER BY u.first_name, u.last_name
    ");
    $stmt->execute([$tenant_id]);
    $agents = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH POLITICAL PARTIES FOR DROPDOWN
// ============================================================
$parties = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, acronym 
        FROM political_parties 
        WHERE tenant_id = ? AND is_active = 1
        ORDER BY name
    ");
    $stmt->execute([$tenant_id]);
    $parties = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
$action_result = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $level = $_POST['level'] ?? 'pu';
    
    try {
        switch ($action) {
            case 'add_pu_result':
                // EC8A - Polling Unit Result
                $election_id = (int)($_POST['election_id'] ?? 0);
                $pu_id = (int)($_POST['pu_id'] ?? 0);
                $agent_id = (int)($_POST['agent_id'] ?? 0);
                $assignment_id = (int)($_POST['assignment_id'] ?? 0);
                $pu_code = trim($_POST['pu_code'] ?? '');
                $pu_name = trim($_POST['pu_name'] ?? '');
                $registered_voters = (int)($_POST['registered_voters'] ?? 0);
                $accredited_voters = (int)($_POST['accredited_voters'] ?? 0);
                $ballot_papers_issued = (int)($_POST['ballot_papers_issued'] ?? 0);
                $unused_ballots = (int)($_POST['unused_ballots'] ?? 0);
                $spoiled_ballots = (int)($_POST['spoiled_ballots'] ?? 0);
                $rejected_votes = (int)($_POST['rejected_votes'] ?? 0);
                $valid_votes = (int)($_POST['valid_votes'] ?? 0);
                $total_votes_cast = (int)($_POST['total_votes_cast'] ?? 0);
                $party_votes = [];
                
                foreach ($parties as $party) {
                    $party_votes[$party['id']] = (int)($_POST['party_votes_' . $party['id']] ?? 0);
                }
                $party_votes_json = json_encode($party_votes);
                
                if ($election_id <= 0 || $pu_id <= 0 || $agent_id <= 0) {
                    throw new Exception('Election, Polling Unit, and Agent are required.');
                }
                
                // Get ward, lga, state from PU
                $stmt = $db->prepare("SELECT ward_id FROM polling_units WHERE id = ?");
                $stmt->execute([$pu_id]);
                $pu_data = $stmt->fetch();
                $ward_id = $pu_data['ward_id'] ?? 0;
                
                $stmt = $db->prepare("SELECT lga_id FROM wards WHERE id = ?");
                $stmt->execute([$ward_id]);
                $ward_data = $stmt->fetch();
                $lga_id = $ward_data['lga_id'] ?? 0;
                
                $stmt = $db->prepare("SELECT state_id FROM lgas WHERE id = ?");
                $stmt->execute([$lga_id]);
                $lga_data = $stmt->fetch();
                $state_id = $lga_data['state_id'] ?? 0;
                
                $stmt = $db->prepare("
                    INSERT INTO results_ec8a (
                        tenant_id, election_id, pu_id, ward_id, lga_id, state_id,
                        agent_id, assignment_id, pu_code, pu_name,
                        registered_voters, accredited_voters, ballot_papers_issued,
                        unused_ballots, spoiled_ballots, rejected_votes,
                        valid_votes, total_votes_cast, party_votes_json,
                        status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([
                    $tenant_id, $election_id, $pu_id, $ward_id, $lga_id, $state_id,
                    $agent_id, $assignment_id, $pu_code, $pu_name,
                    $registered_voters, $accredited_voters, $ballot_papers_issued,
                    $unused_ballots, $spoiled_ballots, $rejected_votes,
                    $valid_votes, $total_votes_cast, $party_votes_json
                ]);
                
                logActivity($user_id, 'ec8a_added', "Added EC8A result for PU: $pu_code");
                $action_result = ['success' => true, 'message' => 'Polling Unit result added successfully.'];
                break;
                
            case 'add_ward_result':
                // EC8B - Ward Result
                $election_id = (int)($_POST['election_id'] ?? 0);
                $ward_id = (int)($_POST['ward_id'] ?? 0);
                $coordinator_id = (int)($_POST['coordinator_id'] ?? 0);
                $valid_votes = (int)($_POST['valid_votes'] ?? 0);
                $rejected_votes = (int)($_POST['rejected_votes'] ?? 0);
                $total_votes = $valid_votes + $rejected_votes;
                $party_votes = [];
                
                foreach ($parties as $party) {
                    $party_votes[$party['id']] = (int)($_POST['ward_party_votes_' . $party['id']] ?? 0);
                }
                $party_votes_json = json_encode($party_votes);
                $calculated_total_json = json_encode(['valid' => $valid_votes, 'rejected' => $rejected_votes]);
                
                if ($election_id <= 0 || $ward_id <= 0) {
                    throw new Exception('Election and Ward are required.');
                }
                
                $stmt = $db->prepare("SELECT lga_id FROM wards WHERE id = ?");
                $stmt->execute([$ward_id]);
                $ward_data = $stmt->fetch();
                $lga_id = $ward_data['lga_id'] ?? 0;
                
                $stmt = $db->prepare("SELECT state_id FROM lgas WHERE id = ?");
                $stmt->execute([$lga_id]);
                $lga_data = $stmt->fetch();
                $state_id = $lga_data['state_id'] ?? 0;
                
                $stmt = $db->prepare("
                    INSERT INTO results_ec8b (
                        tenant_id, election_id, ward_id, lga_id, state_id,
                        coordinator_id, party_votes_json, valid_votes,
                        rejected_votes, total_votes, calculated_total_json,
                        status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([
                    $tenant_id, $election_id, $ward_id, $lga_id, $state_id,
                    $coordinator_id, $party_votes_json, $valid_votes,
                    $rejected_votes, $total_votes, $calculated_total_json
                ]);
                
                logActivity($user_id, 'ec8b_added', "Added EC8B result for Ward ID: $ward_id");
                $action_result = ['success' => true, 'message' => 'Ward result added successfully.'];
                break;
                
            case 'add_lga_result':
                // EC8C - LGA Result
                $election_id = (int)($_POST['election_id'] ?? 0);
                $lga_id = (int)($_POST['lga_id'] ?? 0);
                $coordinator_id = (int)($_POST['coordinator_id'] ?? 0);
                $valid_votes = (int)($_POST['valid_votes'] ?? 0);
                $rejected_votes = (int)($_POST['rejected_votes'] ?? 0);
                $total_votes = $valid_votes + $rejected_votes;
                $party_votes = [];
                
                foreach ($parties as $party) {
                    $party_votes[$party['id']] = (int)($_POST['lga_party_votes_' . $party['id']] ?? 0);
                }
                $party_votes_json = json_encode($party_votes);
                $calculated_total_json = json_encode(['valid' => $valid_votes, 'rejected' => $rejected_votes]);
                
                if ($election_id <= 0 || $lga_id <= 0) {
                    throw new Exception('Election and LGA are required.');
                }
                
                $stmt = $db->prepare("SELECT state_id FROM lgas WHERE id = ?");
                $stmt->execute([$lga_id]);
                $lga_data = $stmt->fetch();
                $state_id = $lga_data['state_id'] ?? 0;
                
                $stmt = $db->prepare("
                    INSERT INTO results_ec8c (
                        tenant_id, election_id, lga_id, state_id,
                        coordinator_id, party_votes_json, valid_votes,
                        rejected_votes, total_votes, calculated_total_json,
                        status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([
                    $tenant_id, $election_id, $lga_id, $state_id,
                    $coordinator_id, $party_votes_json, $valid_votes,
                    $rejected_votes, $total_votes, $calculated_total_json
                ]);
                
                logActivity($user_id, 'ec8c_added', "Added EC8C result for LGA ID: $lga_id");
                $action_result = ['success' => true, 'message' => 'LGA result added successfully.'];
                break;
                
            case 'add_state_result':
                // EC8D - State Result
                $election_id = (int)($_POST['election_id'] ?? 0);
                $state_id = (int)($_POST['state_id'] ?? 0);
                $coordinator_id = (int)($_POST['coordinator_id'] ?? 0);
                $valid_votes = (int)($_POST['valid_votes'] ?? 0);
                $rejected_votes = (int)($_POST['rejected_votes'] ?? 0);
                $total_votes = $valid_votes + $rejected_votes;
                $party_votes = [];
                
                foreach ($parties as $party) {
                    $party_votes[$party['id']] = (int)($_POST['state_party_votes_' . $party['id']] ?? 0);
                }
                $party_votes_json = json_encode($party_votes);
                $calculated_total_json = json_encode(['valid' => $valid_votes, 'rejected' => $rejected_votes]);
                
                if ($election_id <= 0 || $state_id <= 0) {
                    throw new Exception('Election and State are required.');
                }
                
                $stmt = $db->prepare("
                    INSERT INTO results_ec8d (
                        tenant_id, election_id, state_id,
                        coordinator_id, party_votes_json, valid_votes,
                        rejected_votes, total_votes, calculated_total_json,
                        status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([
                    $tenant_id, $election_id, $state_id,
                    $coordinator_id, $party_votes_json, $valid_votes,
                    $rejected_votes, $total_votes, $calculated_total_json
                ]);
                
                logActivity($user_id, 'ec8d_added', "Added EC8D result for State ID: $state_id");
                $action_result = ['success' => true, 'message' => 'State result added successfully.'];
                break;
                
            case 'add_national_result':
                // EC8E - National Result
                $election_id = (int)($_POST['election_id'] ?? 0);
                $coordinator_id = (int)($_POST['coordinator_id'] ?? 0);
                $valid_votes = (int)($_POST['valid_votes'] ?? 0);
                $rejected_votes = (int)($_POST['rejected_votes'] ?? 0);
                $total_votes = $valid_votes + $rejected_votes;
                $party_votes = [];
                
                foreach ($parties as $party) {
                    $party_votes[$party['id']] = (int)($_POST['national_party_votes_' . $party['id']] ?? 0);
                }
                $party_votes_json = json_encode($party_votes);
                $calculated_total_json = json_encode(['valid' => $valid_votes, 'rejected' => $rejected_votes]);
                
                if ($election_id <= 0) {
                    throw new Exception('Election is required.');
                }
                
                $stmt = $db->prepare("
                    INSERT INTO results_ec8e (
                        tenant_id, election_id,
                        coordinator_id, party_votes_json, valid_votes,
                        rejected_votes, total_votes, calculated_total_json,
                        status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([
                    $tenant_id, $election_id,
                    $coordinator_id, $party_votes_json, $valid_votes,
                    $rejected_votes, $total_votes, $calculated_total_json
                ]);
                
                logActivity($user_id, 'ec8e_added', "Added EC8E national result for Election ID: $election_id");
                $action_result = ['success' => true, 'message' => 'National result added successfully.'];
                break;
        }
    } catch (Exception $e) {
        $action_result = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       UNIFIED RESULTS ENTRY - PROFESSIONAL UI STYLES
       ============================================================ */
    
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 20px;
    }
    .page-header h2 {
        font-size: 1.3rem;
        font-weight: 700;
    }
    .page-header h2 small {
        font-size: 0.8rem;
        font-weight: 400;
        color: var(--gray-500);
        display: block;
        margin-top: 2px;
    }
    
    .btn-outline {
        padding: 10px 18px;
        background: transparent;
        color: var(--gray-600);
        border: 1.5px solid var(--gray-200);
        border-radius: 10px;
        font-weight: 500;
        font-size: 0.85rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-outline:hover {
        background: var(--gray-50);
        border-color: var(--primary);
        color: var(--primary);
    }
    .btn-primary {
        padding: 10px 20px;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(var(--primary-rgb), 0.3);
    }
    .btn-success {
        padding: 10px 20px;
        background: var(--secondary);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-success:hover {
        background: #059669;
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(16, 185, 129, 0.3);
    }
    
    .results-nav {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        margin-bottom: 20px;
        background: white;
        padding: 8px 12px;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        box-shadow: var(--shadow);
    }
    .results-nav a {
        padding: 8px 18px;
        border-radius: 10px;
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 500;
        transition: var(--transition);
        background: transparent;
        border: 1px solid transparent;
        color: var(--gray-600);
        display: inline-flex;
        align-items: center;
        gap: 6px;
        position: relative;
    }
    .results-nav a:hover {
        background: var(--gray-50);
        border-color: var(--gray-200);
        color: var(--gray-700);
    }
    .results-nav a.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.25);
    }
    
    .form-container {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 28px 32px;
        box-shadow: var(--shadow);
        max-width: 900px;
        margin: 0 auto;
    }
    .form-container:hover {
        box-shadow: var(--shadow-hover);
    }
    .form-container .form-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 2px solid var(--gray-100);
    }
    .form-container .form-header .icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: #EFF6FF;
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
    }
    .form-container .form-header h3 {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--gray-800);
    }
    .form-container .form-header p {
        font-size: 0.85rem;
        color: var(--gray-500);
        margin-top: 2px;
    }
    
    .level-tabs {
        display: flex;
        gap: 4px;
        margin-bottom: 20px;
        background: var(--gray-50);
        padding: 4px;
        border-radius: 10px;
        border: 1px solid var(--gray-200);
    }
    .level-tab {
        flex: 1;
        padding: 10px 16px;
        border: none;
        border-radius: 8px;
        background: transparent;
        font-family: 'Inter', sans-serif;
        font-size: 0.82rem;
        font-weight: 500;
        color: var(--gray-500);
        cursor: pointer;
        transition: var(--transition);
        text-align: center;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }
    .level-tab:hover {
        color: var(--gray-700);
        background: rgba(255,255,255,0.5);
    }
    .level-tab.active {
        background: white;
        color: var(--primary);
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    .level-tab .badge {
        background: var(--gray-200);
        color: var(--gray-600);
        padding: 0 8px;
        border-radius: 10px;
        font-size: 0.6rem;
        font-weight: 600;
    }
    .level-tab.active .badge {
        background: var(--primary);
        color: white;
    }
    
    .tab-content {
        display: none;
        animation: fadeIn 0.3s ease;
    }
    .tab-content.active {
        display: block;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .form-group.full-width {
        grid-column: 1 / -1;
    }
    .form-group label {
        font-weight: 600;
        font-size: 0.82rem;
        color: var(--gray-700);
    }
    .form-group label .required {
        color: var(--danger);
        margin-left: 2px;
    }
    .form-group .help-text {
        font-size: 0.7rem;
        color: var(--gray-400);
        margin-top: 2px;
    }
    .form-group input,
    .form-group select,
    .form-group textarea {
        padding: 8px 12px;
        border: 1.5px solid var(--gray-200);
        border-radius: 8px;
        font-family: 'Inter', sans-serif;
        font-size: 0.82rem;
        transition: var(--transition);
        background: var(--gray-50);
        color: var(--gray-700);
        width: 100%;
    }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 4px rgba(var(--primary-rgb), 0.08);
    }
    .form-group input[type="number"] {
        -moz-appearance: textfield;
    }
    .form-group input[type="number"]::-webkit-outer-spin-button,
    .form-group input[type="number"]::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    
    .party-votes-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 8px;
        padding: 8px 0;
    }
    .party-vote-item {
        display: flex;
        align-items: center;
        gap: 6px;
        background: var(--gray-50);
        padding: 6px 10px;
        border-radius: 6px;
        border: 1px solid var(--gray-200);
    }
    .party-vote-item .party-name {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--gray-600);
        min-width: 40px;
    }
    .party-vote-item input {
        flex: 1;
        min-width: 50px;
        padding: 4px 6px;
        border: 1px solid var(--gray-200);
        border-radius: 4px;
        font-size: 0.75rem;
        font-family: 'Inter', sans-serif;
        text-align: center;
        background: white;
        transition: var(--transition);
    }
    .party-vote-item input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.08);
    }
    
    .form-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 20px;
        padding-top: 16px;
        border-top: 2px solid var(--gray-100);
    }
    .form-actions .btn {
        padding: 10px 24px;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .form-actions .btn-primary {
        background: var(--primary);
        color: white;
    }
    .form-actions .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.25);
    }
    .form-actions .btn-secondary {
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .form-actions .btn-secondary:hover {
        background: var(--gray-200);
    }
    
    .toast {
        padding: 14px 20px;
        border-radius: 10px;
        color: white;
        font-size: 0.85rem;
        font-weight: 500;
        box-shadow: var(--shadow-hover);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        max-width: 100%;
    }
    .toast.success { background: var(--secondary); }
    .toast.error { background: var(--danger); }
    
    @media (max-width: 768px) {
        .form-container {
            padding: 16px;
        }
        .form-grid {
            grid-template-columns: 1fr;
        }
        .level-tabs {
            flex-wrap: wrap;
        }
        .level-tab {
            flex: 1 1 auto;
            font-size: 0.75rem;
            padding: 8px 12px;
        }
        .form-actions {
            flex-direction: column;
        }
        .form-actions .btn {
            width: 100%;
            justify-content: center;
        }
        .party-votes-grid {
            grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
        }
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }
    @media (max-width: 480px) {
        .form-container {
            padding: 12px;
        }
        .form-container .form-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .level-tab {
            font-size: 0.7rem;
            padding: 6px 10px;
        }
        .party-votes-grid {
            grid-template-columns: 1fr 1fr;
        }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Toast Messages -->
        <?php if (!empty($action_result['message'])): ?>
        <div class="toast <?php echo $action_result['success'] ? 'success' : 'error'; ?>" style="position:static;animation:none;">
            <i class="fas <?php echo $action_result['success'] ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($action_result['message']); ?>
        </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-plus-circle" style="color:var(--primary);margin-right:8px;"></i> Add Results
                    <small>Enter election results for all levels (EC8A - EC8E)</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="results-ec8a.php" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Results
                </a>
            </div>
        </div>

        <!-- Results Navigation -->
        <div class="results-nav">
            <a href="results-ec8a.php"><i class="fas fa-flag-checkered"></i> EC8A (PU)</a>
            <a href="results-ec8b.php"><i class="fas fa-layer-group"></i> EC8B (Ward)</a>
            <a href="results-ec8c.php"><i class="fas fa-map-marker-alt"></i> EC8C (LGA)</a>
            <a href="results-ec8d.php"><i class="fas fa-flag"></i> EC8D (State)</a>
            <a href="results-ec8e.php"><i class="fas fa-globe-africa"></i> EC8E (National)</a>
            <a href="results-add.php" class="active"><i class="fas fa-plus-circle"></i> Add New</a>
        </div>

        <!-- Form Container -->
        <div class="form-container">
            <div class="form-header">
                <div class="icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div>
                    <h3>Enter Election Results</h3>
                    <p>Select the level and fill in the result details. Fields marked with <span style="color:var(--danger);">*</span> are required.</p>
                </div>
            </div>

            <!-- Level Tabs -->
            <div class="level-tabs">
                <button class="level-tab active" onclick="switchLevel('pu')">
                    <i class="fas fa-flag-checkered"></i> EC8A - PU
                    <span class="badge">Polling Unit</span>
                </button>
                <button class="level-tab" onclick="switchLevel('ward')">
                    <i class="fas fa-layer-group"></i> EC8B - Ward
                    <span class="badge">Ward</span>
                </button>
                <button class="level-tab" onclick="switchLevel('lga')">
                    <i class="fas fa-map-marker-alt"></i> EC8C - LGA
                    <span class="badge">LGA</span>
                </button>
                <button class="level-tab" onclick="switchLevel('state')">
                    <i class="fas fa-flag"></i> EC8D - State
                    <span class="badge">State</span>
                </button>
                <button class="level-tab" onclick="switchLevel('national')">
                    <i class="fas fa-globe-africa"></i> EC8E - National
                    <span class="badge">National</span>
                </button>
            </div>

            <!-- ============================================================
            EC8A - POLLING UNIT LEVEL FORM
            ============================================================ -->
            <form method="POST" action="" id="form-pu" class="tab-content active">
                <input type="hidden" name="action" value="add_pu_result">
                <input type="hidden" name="level" value="pu">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Election <span class="required">*</span></label>
                        <select name="election_id" required>
                            <option value="">Select Election</option>
                            <?php foreach ($elections as $election): ?>
                                <option value="<?php echo $election['id']; ?>">
                                    <?php echo htmlspecialchars($election['name']); ?>
                                    (<?php echo date('M j, Y', strtotime($election['election_date'])); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Polling Unit <span class="required">*</span></label>
                        <select name="pu_id" required>
                            <option value="">Select Polling Unit</option>
                            <?php foreach ($polling_units as $pu): ?>
                                <option value="<?php echo $pu['id']; ?>">
                                    <?php echo htmlspecialchars($pu['code']); ?> - <?php echo htmlspecialchars($pu['name']); ?>
                                    (<?php echo htmlspecialchars($pu['ward_name'] ?? 'N/A'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Agent <span class="required">*</span></label>
                        <select name="agent_id" required>
                            <option value="">Select Agent</option>
                            <?php foreach ($agents as $agent): ?>
                                <option value="<?php echo $agent['id']; ?>">
                                    <?php echo htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']); ?>
                                    (<?php echo htmlspecialchars($agent['role_name'] ?? 'N/A'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Assignment ID</label>
                        <input type="number" name="assignment_id" placeholder="Optional" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label>PU Code <span class="required">*</span></label>
                        <input type="text" name="pu_code" placeholder="e.g., PU001" required>
                    </div>
                    
                    <div class="form-group">
                        <label>PU Name <span class="required">*</span></label>
                        <input type="text" name="pu_name" placeholder="e.g., Primary School" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Registered Voters</label>
                        <input type="number" name="registered_voters" placeholder="0" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label>Accredited Voters</label>
                        <input type="number" name="accredited_voters" placeholder="0" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label>Ballot Papers Issued</label>
                        <input type="number" name="ballot_papers_issued" placeholder="0" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label>Unused Ballots</label>
                        <input type="number" name="unused_ballots" placeholder="0" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label>Spoiled Ballots</label>
                        <input type="number" name="spoiled_ballots" placeholder="0" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label>Rejected Votes</label>
                        <input type="number" name="rejected_votes" placeholder="0" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label>Valid Votes</label>
                        <input type="number" name="valid_votes" placeholder="0" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label>Total Votes Cast</label>
                        <input type="number" name="total_votes_cast" placeholder="0" min="0">
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Party Votes</label>
                        <div class="party-votes-grid">
                            <?php foreach ($parties as $party): ?>
                                <div class="party-vote-item">
                                    <span class="party-name"><?php echo htmlspecialchars($party['acronym']); ?></span>
                                    <input type="number" name="party_votes_<?php echo $party['id']; ?>" placeholder="0" min="0">
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($parties)): ?>
                                <div style="grid-column:1/-1;padding:12px;background:#FEF2F2;border-radius:6px;color:#991B1B;font-size:0.8rem;">
                                    <i class="fas fa-exclamation-triangle"></i> No parties available. Please add parties first.
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="help-text">Enter votes received by each party</div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="results-ec8a.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add PU Result</button>
                </div>
            </form>

            <!-- ============================================================
            EC8B - WARD LEVEL FORM
            ============================================================ -->
            <form method="POST" action="" id="form-ward" class="tab-content">
                <input type="hidden" name="action" value="add_ward_result">
                <input type="hidden" name="level" value="ward">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Election <span class="required">*</span></label>
                        <select name="election_id" required>
                            <option value="">Select Election</option>
                            <?php foreach ($elections as $election): ?>
                                <option value="<?php echo $election['id']; ?>">
                                    <?php echo htmlspecialchars($election['name']); ?>
                                    (<?php echo date('M j, Y', strtotime($election['election_date'])); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Ward <span class="required">*</span></label>
                        <select name="ward_id" required>
                            <option value="">Select Ward</option>
                            <?php foreach ($wards as $ward): ?>
                                <option value="<?php echo $ward['id']; ?>">
                                    <?php echo htmlspecialchars($ward['name']); ?>
                                    (<?php echo htmlspecialchars($ward['lga_name'] ?? 'N/A'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Coordinator <span class="required">*</span></label>
                        <select name="coordinator_id" required>
                            <option value="">Select Coordinator</option>
                            <?php foreach ($agents as $agent): ?>
                                <option value="<?php echo $agent['id']; ?>">
                                    <?php echo htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']); ?>
                                    (<?php echo htmlspecialchars($agent['role_name'] ?? 'N/A'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Valid Votes <span class="required">*</span></label>
                        <input type="number" name="valid_votes" placeholder="0" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Rejected Votes</label>
                        <input type="number" name="rejected_votes" placeholder="0" min="0">
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Party Votes</label>
                        <div class="party-votes-grid">
                            <?php foreach ($parties as $party): ?>
                                <div class="party-vote-item">
                                    <span class="party-name"><?php echo htmlspecialchars($party['acronym']); ?></span>
                                    <input type="number" name="ward_party_votes_<?php echo $party['id']; ?>" placeholder="0" min="0">
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($parties)): ?>
                                <div style="grid-column:1/-1;padding:12px;background:#FEF2F2;border-radius:6px;color:#991B1B;font-size:0.8rem;">
                                    <i class="fas fa-exclamation-triangle"></i> No parties available. Please add parties first.
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="help-text">Enter votes received by each party at ward level</div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="results-ec8b.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Ward Result</button>
                </div>
            </form>

            <!-- ============================================================
            EC8C - LGA LEVEL FORM
            ============================================================ -->
            <form method="POST" action="" id="form-lga" class="tab-content">
                <input type="hidden" name="action" value="add_lga_result">
                <input type="hidden" name="level" value="lga">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Election <span class="required">*</span></label>
                        <select name="election_id" required>
                            <option value="">Select Election</option>
                            <?php foreach ($elections as $election): ?>
                                <option value="<?php echo $election['id']; ?>">
                                    <?php echo htmlspecialchars($election['name']); ?>
                                    (<?php echo date('M j, Y', strtotime($election['election_date'])); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>LGA <span class="required">*</span></label>
                        <select name="lga_id" required>
                            <option value="">Select LGA</option>
                            <?php foreach ($lgas as $lga): ?>
                                <option value="<?php echo $lga['id']; ?>">
                                    <?php echo htmlspecialchars($lga['name']); ?>
                                    (<?php echo htmlspecialchars($lga['state_name'] ?? 'N/A'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Coordinator <span class="required">*</span></label>
                        <select name="coordinator_id" required>
                            <option value="">Select Coordinator</option>
                            <?php foreach ($agents as $agent): ?>
                                <option value="<?php echo $agent['id']; ?>">
                                    <?php echo htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']); ?>
                                    (<?php echo htmlspecialchars($agent['role_name'] ?? 'N/A'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Valid Votes <span class="required">*</span></label>
                        <input type="number" name="valid_votes" placeholder="0" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Rejected Votes</label>
                        <input type="number" name="rejected_votes" placeholder="0" min="0">
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Party Votes</label>
                        <div class="party-votes-grid">
                            <?php foreach ($parties as $party): ?>
                                <div class="party-vote-item">
                                    <span class="party-name"><?php echo htmlspecialchars($party['acronym']); ?></span>
                                    <input type="number" name="lga_party_votes_<?php echo $party['id']; ?>" placeholder="0" min="0">
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($parties)): ?>
                                <div style="grid-column:1/-1;padding:12px;background:#FEF2F2;border-radius:6px;color:#991B1B;font-size:0.8rem;">
                                    <i class="fas fa-exclamation-triangle"></i> No parties available. Please add parties first.
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="help-text">Enter votes received by each party at LGA level</div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="results-ec8c.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add LGA Result</button>
                </div>
            </form>

            <!-- ============================================================
            EC8D - STATE LEVEL FORM
            ============================================================ -->
            <form method="POST" action="" id="form-state" class="tab-content">
                <input type="hidden" name="action" value="add_state_result">
                <input type="hidden" name="level" value="state">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Election <span class="required">*</span></label>
                        <select name="election_id" required>
                            <option value="">Select Election</option>
                            <?php foreach ($elections as $election): ?>
                                <option value="<?php echo $election['id']; ?>">
                                    <?php echo htmlspecialchars($election['name']); ?>
                                    (<?php echo date('M j, Y', strtotime($election['election_date'])); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>State <span class="required">*</span></label>
                        <select name="state_id" required>
                            <option value="">Select State</option>
                            <?php foreach ($states as $state): ?>
                                <option value="<?php echo $state['id']; ?>">
                                    <?php echo htmlspecialchars($state['name']); ?>
                                    (<?php echo htmlspecialchars($state['code']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Coordinator <span class="required">*</span></label>
                        <select name="coordinator_id" required>
                            <option value="">Select Coordinator</option>
                            <?php foreach ($agents as $agent): ?>
                                <option value="<?php echo $agent['id']; ?>">
                                    <?php echo htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']); ?>
                                    (<?php echo htmlspecialchars($agent['role_name'] ?? 'N/A'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Valid Votes <span class="required">*</span></label>
                        <input type="number" name="valid_votes" placeholder="0" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Rejected Votes</label>
                        <input type="number" name="rejected_votes" placeholder="0" min="0">
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Party Votes</label>
                        <div class="party-votes-grid">
                            <?php foreach ($parties as $party): ?>
                                <div class="party-vote-item">
                                    <span class="party-name"><?php echo htmlspecialchars($party['acronym']); ?></span>
                                    <input type="number" name="state_party_votes_<?php echo $party['id']; ?>" placeholder="0" min="0">
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($parties)): ?>
                                <div style="grid-column:1/-1;padding:12px;background:#FEF2F2;border-radius:6px;color:#991B1B;font-size:0.8rem;">
                                    <i class="fas fa-exclamation-triangle"></i> No parties available. Please add parties first.
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="help-text">Enter votes received by each party at state level</div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="results-ec8d.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add State Result</button>
                </div>
            </form>

            <!-- ============================================================
            EC8E - NATIONAL LEVEL FORM
            ============================================================ -->
            <form method="POST" action="" id="form-national" class="tab-content">
                <input type="hidden" name="action" value="add_national_result">
                <input type="hidden" name="level" value="national">
                
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>Election <span class="required">*</span></label>
                        <select name="election_id" required>
                            <option value="">Select Election</option>
                            <?php foreach ($elections as $election): ?>
                                <option value="<?php echo $election['id']; ?>">
                                    <?php echo htmlspecialchars($election['name']); ?>
                                    (<?php echo date('M j, Y', strtotime($election['election_date'])); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Coordinator <span class="required">*</span></label>
                        <select name="coordinator_id" required>
                            <option value="">Select Coordinator</option>
                            <?php foreach ($agents as $agent): ?>
                                <option value="<?php echo $agent['id']; ?>">
                                    <?php echo htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']); ?>
                                    (<?php echo htmlspecialchars($agent['role_name'] ?? 'N/A'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Valid Votes <span class="required">*</span></label>
                        <input type="number" name="valid_votes" placeholder="0" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Rejected Votes</label>
                        <input type="number" name="rejected_votes" placeholder="0" min="0">
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Party Votes</label>
                        <div class="party-votes-grid">
                            <?php foreach ($parties as $party): ?>
                                <div class="party-vote-item">
                                    <span class="party-name"><?php echo htmlspecialchars($party['acronym']); ?></span>
                                    <input type="number" name="national_party_votes_<?php echo $party['id']; ?>" placeholder="0" min="0">
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($parties)): ?>
                                <div style="grid-column:1/-1;padding:12px;background:#FEF2F2;border-radius:6px;color:#991B1B;font-size:0.8rem;">
                                    <i class="fas fa-exclamation-triangle"></i> No parties available. Please add parties first.
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="help-text">Enter votes received by each party at national level</div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="results-ec8e.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add National Result</button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
// ============================================================
// PRELOADER
// ============================================================
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});

// ============================================================
// SIDEBAR TOGGLE
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

// ============================================================
// SIDEBAR DROPDOWNS
// ============================================================
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

// ============================================================
// PROFILE DROPDOWN
// ============================================================
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

// ============================================================
// LEVEL SWITCHING
// ============================================================
function switchLevel(level) {
    // Update tabs
    document.querySelectorAll('.level-tab').forEach(function(tab) {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.level-tab').forEach(function(tab) {
        if (tab.textContent.toLowerCase().includes(level)) {
            tab.classList.add('active');
        }
    });
    // Using data attribute instead
    document.querySelectorAll('.level-tab').forEach(function(tab) {
        if (tab.textContent.toLowerCase().includes('ec8a') && level === 'pu') tab.classList.add('active');
        else if (tab.textContent.toLowerCase().includes('ec8b') && level === 'ward') tab.classList.add('active');
        else if (tab.textContent.toLowerCase().includes('ec8c') && level === 'lga') tab.classList.add('active');
        else if (tab.textContent.toLowerCase().includes('ec8d') && level === 'state') tab.classList.add('active');
        else if (tab.textContent.toLowerCase().includes('ec8e') && level === 'national') tab.classList.add('active');
    });
    
    // Update content
    document.querySelectorAll('.tab-content').forEach(function(content) {
        content.classList.remove('active');
    });
    document.getElementById('form-' + level).classList.add('active');
}

// ============================================================
// SEARCH FUNCTIONALITY
// ============================================================
var searchInput = document.querySelector('.search-wrap input');
if (searchInput) {
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            this.closest('form').submit();
        }
    });
}
</script>
</body>
</html>