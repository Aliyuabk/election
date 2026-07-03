<?php
// ============================================================
// ELECTION CANDIDATES - SUPER ADMINISTRATOR
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

// Check role - only super_admin can access this page
if (SessionManager::get('role_level') !== 'super_admin') {
    header('Location: ../client-admin/');
    exit();
}

// Get database connection
$db = getDB();

// ============================================================
// GET ELECTION ID
// ============================================================
$election_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($election_id <= 0) {
    header('Location: elections.php');
    exit();
}

// ============================================================
// FETCH ELECTION DETAILS
// ============================================================
$election = null;
try {
    $stmt = $db->prepare("SELECT id, name, type, tenant_id FROM elections WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$election_id]);
    $election = $stmt->fetch();
} catch (Exception $e) {
    // Continue
}

if (!$election) {
    header('Location: elections.php');
    exit();
}

// ============================================================
// FETCH CANDIDATES
// ============================================================
$candidates = [];
try {
    $stmt = $db->prepare("
        SELECT c.*, p.name as party_name, p.acronym as party_acronym, p.logo_url as party_logo
        FROM candidates c
        LEFT JOIN political_parties p ON c.party_id = p.id
        WHERE c.election_id = ?
        ORDER BY c.position, c.last_name
    ");
    $stmt->execute([$election_id]);
    $candidates = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH POLITICAL PARTIES
// ============================================================
$parties = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, acronym, logo_url 
        FROM political_parties 
        WHERE tenant_id = ? AND is_active = 1 
        ORDER BY name
    ");
    $stmt->execute([$election['tenant_id']]);
    $parties = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// HANDLE ACTIONS
// ============================================================
$action_result = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add_candidate':
                $first_name = trim($_POST['first_name'] ?? '');
                $last_name = trim($_POST['last_name'] ?? '');
                $position = trim($_POST['position'] ?? '');
                $party_id = !empty($_POST['party_id']) ? (int)$_POST['party_id'] : null;
                $contact_email = trim($_POST['contact_email'] ?? '');
                $contact_phone = trim($_POST['contact_phone'] ?? '');
                $biography = trim($_POST['biography'] ?? '');
                
                if (empty($first_name) || empty($last_name) || empty($position)) {
                    throw new Exception('First name, last name, and position are required.');
                }
                
                $stmt = $db->prepare("
                    INSERT INTO candidates (
                        tenant_id, election_id, party_id, first_name, last_name,
                        position, contact_email, contact_phone, biography
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $election['tenant_id'],
                    $election_id,
                    $party_id,
                    $first_name,
                    $last_name,
                    $position,
                    $contact_email,
                    $contact_phone,
                    $biography
                ]);
                
                logActivity(
                    SessionManager::get('user_id'),
                    'candidate_added',
                    "Added candidate: $first_name $last_name to election ID: $election_id"
                );
                
                $action_result = ['success' => true, 'message' => 'Candidate added successfully.'];
                break;
                
            case 'edit_candidate':
                $id = (int)($_POST['id'] ?? 0);
                $first_name = trim($_POST['first_name'] ?? '');
                $last_name = trim($_POST['last_name'] ?? '');
                $position = trim($_POST['position'] ?? '');
                $party_id = !empty($_POST['party_id']) ? (int)$_POST['party_id'] : null;
                $contact_email = trim($_POST['contact_email'] ?? '');
                $contact_phone = trim($_POST['contact_phone'] ?? '');
                $biography = trim($_POST['biography'] ?? '');
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if ($id <= 0 || empty($first_name) || empty($last_name) || empty($position)) {
                    throw new Exception('Invalid data provided.');
                }
                
                $stmt = $db->prepare("
                    UPDATE candidates SET
                        first_name = ?,
                        last_name = ?,
                        position = ?,
                        party_id = ?,
                        contact_email = ?,
                        contact_phone = ?,
                        biography = ?,
                        is_active = ?
                    WHERE id = ? AND election_id = ?
                ");
                $stmt->execute([
                    $first_name,
                    $last_name,
                    $position,
                    $party_id,
                    $contact_email,
                    $contact_phone,
                    $biography,
                    $is_active,
                    $id,
                    $election_id
                ]);
                
                logActivity(
                    SessionManager::get('user_id'),
                    'candidate_updated',
                    "Updated candidate ID: $id"
                );
                
                $action_result = ['success' => true, 'message' => 'Candidate updated successfully.'];
                break;
                
            case 'delete_candidate':
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid candidate ID.');
                
                $stmt = $db->prepare("DELETE FROM candidates WHERE id = ? AND election_id = ?");
                $stmt->execute([$id, $election_id]);
                
                logActivity(
                    SessionManager::get('user_id'),
                    'candidate_deleted',
                    "Deleted candidate ID: $id"
                );
                
                $action_result = ['success' => true, 'message' => 'Candidate deleted successfully.'];
                break;
        }
    } catch (Exception $e) {
        $action_result = ['success' => false, 'message' => $e->getMessage()];
    }
    
    // Refresh candidates list
    $stmt = $db->prepare("
        SELECT c.*, p.name as party_name, p.acronym as party_acronym, p.logo_url as party_logo
        FROM candidates c
        LEFT JOIN political_parties p ON c.party_id = p.id
        WHERE c.election_id = ?
        ORDER BY c.position, c.last_name
    ");
    $stmt->execute([$election_id]);
    $candidates = $stmt->fetchAll();
}

// Get user info
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       ELECTION CANDIDATES - PRO STYLES
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
    
    .btn-primary {
        padding: 8px 18px;
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
        transform: translateY(-1px);
        box-shadow: 0 4px 16px rgba(var(--primary-rgb), 0.25);
    }
    .btn-outline {
        padding: 8px 16px;
        background: transparent;
        color: var(--gray-600);
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        font-weight: 500;
        font-size: 0.82rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-outline:hover {
        background: var(--gray-50);
        border-color: var(--gray-300);
    }
    .btn-sm {
        padding: 4px 10px;
        font-size: 0.7rem;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .btn-sm.success { background: #ECFDF5; color: #065F46; }
    .btn-sm.success:hover { background: #D1FAE5; }
    .btn-sm.danger { background: #FEF2F2; color: #991B1B; }
    .btn-sm.danger:hover { background: #FEE2E2; }
    .btn-sm.info { background: #EFF6FF; color: #1E40AF; }
    .btn-sm.info:hover { background: #DBEAFE; }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }
    .stat-item {
        background: white;
        border-radius: 12px;
        padding: 14px 18px;
        border: 1px solid var(--gray-200);
        text-align: center;
        transition: var(--transition);
    }
    .stat-item:hover {
        box-shadow: var(--shadow-hover);
        transform: translateY(-2px);
    }
    .stat-item .number {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--primary);
    }
    .stat-item .label {
        font-size: 0.7rem;
        color: var(--gray-500);
        margin-top: 2px;
    }
    
    .filter-bar {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 12px 16px;
        margin-bottom: 20px;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
        box-shadow: var(--shadow);
    }
    .filter-bar .search-wrap {
        flex: 1;
        min-width: 180px;
        display: flex;
        align-items: center;
        background: var(--gray-50);
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        padding: 4px 12px;
        transition: var(--transition);
    }
    .filter-bar .search-wrap:focus-within {
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
    }
    .filter-bar .search-wrap i {
        color: var(--gray-400);
        font-size: 0.8rem;
    }
    .filter-bar .search-wrap input {
        border: none;
        outline: none;
        background: transparent;
        padding: 4px 8px;
        font-family: 'Inter', sans-serif;
        font-size: 0.85rem;
        width: 100%;
        color: var(--gray-700);
    }
    .filter-bar select {
        padding: 6px 12px;
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        font-family: 'Inter', sans-serif;
        font-size: 0.82rem;
        background: var(--gray-50);
        color: var(--gray-700);
        cursor: pointer;
        transition: var(--transition);
        min-width: 130px;
    }
    .filter-bar select:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
    }
    
    .table-container {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        overflow: hidden;
        box-shadow: var(--shadow);
    }
    .table-container .table-header {
        padding: 16px 20px;
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        background: var(--gray-50);
    }
    .table-container .table-header .table-title {
        font-weight: 600;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .table-container .table-header .table-title .count {
        background: var(--primary);
        color: white;
        padding: 0 10px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }
    .data-table thead {
        background: var(--gray-50);
    }
    .data-table thead th {
        padding: 10px 14px;
        text-align: left;
        font-weight: 600;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--gray-500);
        border-bottom: 1px solid var(--gray-200);
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 2;
        background: var(--gray-50);
    }
    .data-table tbody td {
        padding: 8px 14px;
        border-bottom: 1px solid var(--gray-100);
        vertical-align: middle;
    }
    .data-table tbody tr:last-child td {
        border-bottom: none;
    }
    .data-table tbody tr:hover {
        background: var(--gray-50);
    }
    
    .candidate-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.85rem;
        color: white;
        flex-shrink: 0;
    }
    .candidate-avatar.blue { background: #3B82F6; }
    .candidate-avatar.green { background: #10B981; }
    .candidate-avatar.purple { background: #8B5CF6; }
    .candidate-avatar.orange { background: #F59E0B; }
    .candidate-avatar.red { background: #EF4444; }
    .candidate-avatar.pink { background: #EC4899; }
    .candidate-avatar.teal { background: #14B8A6; }
    
    .party-tag {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 500;
        background: var(--gray-100);
        color: var(--gray-600);
    }
    
    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 600;
    }
    .badge-status .dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        display: inline-block;
    }
    .badge-status.active { background: #ECFDF5; color: #065F46; }
    .badge-status.active .dot { background: #10B981; }
    .badge-status.inactive { background: #FEF2F2; color: #991B1B; }
    .badge-status.inactive .dot { background: #EF4444; }
    
    .action-dropdown {
        position: relative;
        display: inline-block;
    }
    .action-dropdown .dropdown-btn {
        background: none;
        border: none;
        padding: 4px 8px;
        cursor: pointer;
        color: var(--gray-400);
        font-size: 1.1rem;
        transition: var(--transition);
        border-radius: 6px;
    }
    .action-dropdown .dropdown-btn:hover {
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .action-dropdown .dropdown-menu {
        position: absolute;
        right: 0;
        top: 100%;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.12);
        border: 1px solid var(--gray-200);
        min-width: 180px;
        padding: 6px;
        display: none;
        z-index: 50;
        animation: dropdownFade 0.2s ease;
    }
    @keyframes dropdownFade {
        from { opacity: 0; transform: translateY(-8px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .action-dropdown .dropdown-menu.open { display: block; }
    .action-dropdown .dropdown-menu a,
    .action-dropdown .dropdown-menu button {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 14px;
        width: 100%;
        border: none;
        background: none;
        font-family: 'Inter', sans-serif;
        font-size: 0.8rem;
        color: var(--gray-600);
        cursor: pointer;
        border-radius: 8px;
        transition: var(--transition);
        text-decoration: none;
    }
    .action-dropdown .dropdown-menu a:hover,
    .action-dropdown .dropdown-menu button:hover {
        background: var(--gray-50);
        color: var(--primary);
    }
    .action-dropdown .dropdown-menu .danger:hover {
        background: #FEF2F2;
        color: var(--danger);
    }
    .action-dropdown .dropdown-menu i {
        width: 16px;
        color: var(--gray-400);
        font-size: 0.85rem;
    }
    .action-dropdown .dropdown-menu .divider {
        height: 1px;
        background: var(--gray-100);
        margin: 4px 8px;
    }
    
    .empty-state {
        text-align: center;
        padding: 48px 20px;
        color: var(--gray-500);
    }
    .empty-state i {
        font-size: 3rem;
        color: var(--gray-300);
        display: block;
        margin-bottom: 12px;
    }
    .empty-state h4 {
        color: var(--gray-700);
        margin-bottom: 4px;
        font-size: 1rem;
    }
    
    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.4);
        z-index: 300;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .modal-overlay.active { display: flex; }
    .modal {
        background: white;
        border-radius: var(--radius);
        max-width: 560px;
        width: 100%;
        padding: 28px 32px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        animation: modalIn 0.25s ease;
        max-height: 90vh;
        overflow-y: auto;
    }
    @keyframes modalIn {
        from { transform: scale(0.95) translateY(10px); opacity: 0; }
        to { transform: scale(1) translateY(0); opacity: 1; }
    }
    .modal .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    }
    .modal .modal-header h3 {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--gray-800);
    }
    .modal .modal-header .close-btn {
        background: none;
        border: none;
        font-size: 1.4rem;
        color: var(--gray-400);
        cursor: pointer;
        transition: var(--transition);
        padding: 0 4px;
    }
    .modal .modal-header .close-btn:hover {
        color: var(--gray-600);
    }
    .modal .form-group {
        margin-bottom: 14px;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .modal .form-group label {
        font-weight: 600;
        font-size: 0.82rem;
        color: var(--gray-700);
    }
    .modal .form-group label .required {
        color: var(--danger);
        margin-left: 2px;
    }
    .modal .form-group .help-text {
        font-size: 0.7rem;
        color: var(--gray-400);
        margin-top: 2px;
    }
    .modal .form-group input,
    .modal .form-group select,
    .modal .form-group textarea {
        padding: 10px 14px;
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        font-family: 'Inter', sans-serif;
        font-size: 0.85rem;
        transition: var(--transition);
        background: var(--gray-50);
        color: var(--gray-700);
        width: 100%;
    }
    .modal .form-group input:focus,
    .modal .form-group select:focus,
    .modal .form-group textarea:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
    }
    .modal .form-group textarea {
        resize: vertical;
        min-height: 60px;
    }
    .modal .form-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 20px;
        padding-top: 16px;
        border-top: 1px solid var(--gray-200);
    }
    .modal .form-actions .btn {
        padding: 8px 20px;
        border-radius: 8px;
        border: none;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .modal .form-actions .btn-primary {
        background: var(--primary);
        color: white;
    }
    .modal .form-actions .btn-primary:hover {
        background: var(--primary-dark);
    }
    .modal .form-actions .btn-secondary {
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .modal .form-actions .btn-secondary:hover {
        background: var(--gray-200);
    }
    
    .toast-container {
        position: fixed;
        top: 80px;
        right: 20px;
        z-index: 999;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .toast {
        padding: 14px 20px;
        border-radius: 10px;
        color: white;
        font-size: 0.85rem;
        font-weight: 500;
        box-shadow: var(--shadow-hover);
        animation: slideIn 0.3s ease;
        min-width: 280px;
        max-width: 400px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .toast.success { background: var(--secondary); }
    .toast.error { background: var(--danger); }
    @keyframes slideIn {
        from { opacity: 0; transform: translateX(100px); }
        to { opacity: 1; transform: translateX(0); }
    }
    
    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .filter-bar { flex-direction: column; align-items: stretch; }
        .filter-bar .search-wrap { min-width: auto; }
        .filter-bar select { width: 100%; }
        .table-container { overflow-x: auto; }
        .data-table { font-size: 0.78rem; }
        .data-table th, .data-table td { padding: 6px 10px; }
        .page-header { flex-direction: column; align-items: flex-start; }
        .modal { padding: 20px; margin: 10px; }
    }
    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
        .stat-item { padding: 10px 12px; }
        .stat-item .number { font-size: 1.2rem; }
        .data-table th, .data-table td { padding: 4px 8px; font-size: 0.7rem; }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Toast Messages -->
        <?php if (!empty($action_result['message'])): ?>
        <div style="margin-bottom:16px;">
            <div class="toast <?php echo $action_result['success'] ? 'success' : 'error'; ?>" style="position:static;animation:none;max-width:100%;">
                <i class="fas <?php echo $action_result['success'] ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($action_result['message']); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-user-tie" style="color:var(--primary);margin-right:8px;"></i> Manage Candidates
                    <small><?php echo htmlspecialchars($election['name']); ?></small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button onclick="openModal('addCandidateModal')" class="btn-primary">
                    <i class="fas fa-plus-circle"></i> Add Candidate
                </button>
                <a href="elections-view.php?id=<?php echo $election_id; ?>" class="btn-outline">
                    <i class="fas fa-eye"></i> View Election
                </a>
                <a href="elections.php" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-item"><div class="number"><?php echo count($candidates); ?></div><div class="label">Total Candidates</div></div>
            <div class="stat-item"><div class="number">
                <?php 
                    $active = array_filter($candidates, function($c) { return $c['is_active'] == 1; });
                    echo count($active);
                ?>
            </div><div class="label">Active</div></div>
            <div class="stat-item"><div class="number">
                <?php 
                    $inactive = array_filter($candidates, function($c) { return $c['is_active'] == 0; });
                    echo count($inactive);
                ?>
            </div><div class="label">Inactive</div></div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:10px;flex:1;align-items:center;width:100%;">
                <input type="hidden" name="id" value="<?php echo $election_id; ?>">
                <div class="search-wrap" style="flex:1;">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search candidates..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                </div>
                <button type="submit" class="btn-primary" style="padding:6px 16px;font-size:0.8rem;">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <?php if (!empty($_GET['search'])): ?>
                    <a href="elections-candidates.php?id=<?php echo $election_id; ?>" class="btn-outline" style="padding:6px 14px;font-size:0.8rem;">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Candidates Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-user-tie" style="color:var(--primary);"></i> Candidates
                    <span class="count"><?php echo count($candidates); ?></span>
                </div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Candidate</th>
                        <th>Position</th>
                        <th>Party</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($candidates) > 0): ?>
                        <?php 
                        $avatar_colors = ['blue', 'green', 'purple', 'orange', 'red', 'pink', 'teal'];
                        foreach ($candidates as $index => $candidate):
                            $color_idx = $index % count($avatar_colors);
                            $avatar_color = $avatar_colors[$color_idx];
                        ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <div class="candidate-avatar <?php echo $avatar_color; ?>">
                                            <?php echo strtoupper(substr($candidate['first_name'], 0, 1) . substr($candidate['last_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div style="font-weight:500;font-size:0.85rem;">
                                                <?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']); ?>
                                            </div>
                                            <div style="font-size:0.65rem;color:var(--gray-400);">
                                                <?php echo htmlspecialchars($candidate['position']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-size:0.82rem;font-weight:500;">
                                        <?php echo htmlspecialchars($candidate['position']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($candidate['party_name'])): ?>
                                        <span class="party-tag">
                                            <?php echo htmlspecialchars($candidate['party_acronym'] ?? $candidate['party_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="font-size:0.75rem;color:var(--gray-400);">Independent</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($candidate['contact_email'])): ?>
                                        <div style="font-size:0.78rem;"><?php echo htmlspecialchars($candidate['contact_email']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($candidate['contact_phone'])): ?>
                                        <div style="font-size:0.7rem;color:var(--gray-400);"><?php echo htmlspecialchars($candidate['contact_phone']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-status <?php echo $candidate['is_active'] ? 'active' : 'inactive'; ?>">
                                        <span class="dot"></span>
                                        <?php echo $candidate['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-dropdown">
                                        <button class="dropdown-btn" onclick="toggleDropdown(this)"><i class="fas fa-ellipsis-v"></i></button>
                                        <div class="dropdown-menu">
                                            <button onclick="editCandidate(<?php echo $candidate['id']; ?>)"><i class="fas fa-edit"></i> Edit</button>
                                            <button class="danger" onclick="deleteCandidate(<?php echo $candidate['id']; ?>)"><i class="fas fa-trash"></i> Delete</button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fas fa-user-tie"></i>
                                    <h4>No candidates found</h4>
                                    <p>Add candidates to this election by clicking the "Add Candidate" button.</p>
                                    <button onclick="openModal('addCandidateModal')" class="btn-primary" style="margin-top:12px;">
                                        <i class="fas fa-plus-circle"></i> Add Candidate
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Add Candidate Modal -->
<div class="modal-overlay" id="addCandidateModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus" style="color:var(--primary);"></i> Add Candidate</h3>
            <button class="close-btn" onclick="closeModal('addCandidateModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add_candidate">
            <div class="form-group">
                <label>First Name <span class="required">*</span></label>
                <input type="text" name="first_name" placeholder="John" required>
            </div>
            <div class="form-group">
                <label>Last Name <span class="required">*</span></label>
                <input type="text" name="last_name" placeholder="Doe" required>
            </div>
            <div class="form-group">
                <label>Position <span class="required">*</span></label>
                <input type="text" name="position" placeholder="e.g., President, Governor" required>
            </div>
            <div class="form-group">
                <label>Political Party</label>
                <select name="party_id">
                    <option value="">Independent (No Party)</option>
                    <?php foreach ($parties as $party): ?>
                        <option value="<?php echo $party['id']; ?>">
                            <?php echo htmlspecialchars($party['acronym'] ?? $party['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Contact Email</label>
                <input type="email" name="contact_email" placeholder="candidate@example.com">
            </div>
            <div class="form-group">
                <label>Contact Phone</label>
                <input type="tel" name="contact_phone" placeholder="+234 800 555 5555">
            </div>
            <div class="form-group">
                <label>Biography</label>
                <textarea name="biography" placeholder="Brief biography of the candidate..."></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addCandidateModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Candidate</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Candidate Modal -->
<div class="modal-overlay" id="editCandidateModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-edit" style="color:var(--primary);"></i> Edit Candidate</h3>
            <button class="close-btn" onclick="closeModal('editCandidateModal')">&times;</button>
        </div>
        <form method="POST" action="" id="editCandidateForm">
            <input type="hidden" name="action" value="edit_candidate">
            <input type="hidden" name="id" id="editCandidateId">
            <div class="form-group">
                <label>First Name <span class="required">*</span></label>
                <input type="text" name="first_name" id="editFirstName" required>
            </div>
            <div class="form-group">
                <label>Last Name <span class="required">*</span></label>
                <input type="text" name="last_name" id="editLastName" required>
            </div>
            <div class="form-group">
                <label>Position <span class="required">*</span></label>
                <input type="text" name="position" id="editPosition" required>
            </div>
            <div class="form-group">
                <label>Political Party</label>
                <select name="party_id" id="editPartyId">
                    <option value="">Independent (No Party)</option>
                    <?php foreach ($parties as $party): ?>
                        <option value="<?php echo $party['id']; ?>">
                            <?php echo htmlspecialchars($party['acronym'] ?? $party['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Contact Email</label>
                <input type="email" name="contact_email" id="editContactEmail" placeholder="candidate@example.com">
            </div>
            <div class="form-group">
                <label>Contact Phone</label>
                <input type="tel" name="contact_phone" id="editContactPhone" placeholder="+234 800 555 5555">
            </div>
            <div class="form-group">
                <label>Biography</label>
                <textarea name="biography" id="editBiography" placeholder="Brief biography of the candidate..."></textarea>
            </div>
            <div class="form-group">
                <div style="display:flex;align-items:center;gap:10px;">
                    <input type="checkbox" name="is_active" id="editIsActive" value="1">
                    <label for="editIsActive" style="font-weight:400;cursor:pointer;">Active</label>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editCandidateModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Candidate</button>
            </div>
        </form>
    </div>
</div>

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
// MODAL FUNCTIONS
// ============================================================
function openModal(id) {
    document.getElementById(id).classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});

// ============================================================
// DROPDOWN FUNCTIONS
// ============================================================
function toggleDropdown(btn) {
    var menu = btn.nextElementSibling;
    var isOpen = menu.classList.contains('open');
    document.querySelectorAll('.action-dropdown .dropdown-menu').forEach(function(m) {
        m.classList.remove('open');
    });
    if (!isOpen) {
        menu.classList.toggle('open');
    }
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.action-dropdown')) {
        document.querySelectorAll('.action-dropdown .dropdown-menu').forEach(function(m) {
            m.classList.remove('open');
        });
    }
});

// ============================================================
// CANDIDATE FUNCTIONS
// ============================================================
function editCandidate(id) {
    // Fetch candidate data via AJAX
    fetch('elections-candidates-ajax.php?action=get_candidate&id=' + id, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            var c = data.data;
            document.getElementById('editCandidateId').value = c.id;
            document.getElementById('editFirstName').value = c.first_name;
            document.getElementById('editLastName').value = c.last_name;
            document.getElementById('editPosition').value = c.position;
            document.getElementById('editPartyId').value = c.party_id || '';
            document.getElementById('editContactEmail').value = c.contact_email || '';
            document.getElementById('editContactPhone').value = c.contact_phone || '';
            document.getElementById('editBiography').value = c.biography || '';
            document.getElementById('editIsActive').checked = c.is_active == 1;
            openModal('editCandidateModal');
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(function() {
        alert('An error occurred. Please try again.');
    });
}

function deleteCandidate(id) {
    if (!confirm('Delete this candidate?')) return;
    
    var form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = '<input type="hidden" name="action" value="delete_candidate"><input type="hidden" name="id" value="' + id + '">';
    document.body.appendChild(form);
    form.submit();
}

// ============================================================
// SEARCH
// ============================================================
var searchInput = document.getElementById('searchInput');
var searchResults = document.getElementById('searchResults');
var searchTimeout;

if (searchInput) {
    searchInput.addEventListener('input', function() {
        var query = this.value.trim();
        clearTimeout(searchTimeout);
        if (query.length < 2) {
            if (searchResults) searchResults.classList.remove('active');
            return;
        }
        searchTimeout = setTimeout(function() {
            fetch('search.php?q=' + encodeURIComponent(query))
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (searchResults) {
                        searchResults.innerHTML = '';
                        if (data && data.length > 0) {
                            data.forEach(function(item) {
                                var div = document.createElement('a');
                                div.className = 'result-item';
                                div.href = item.url || '#';
                                div.innerHTML = '<i class="fas ' + (item.icon || 'fa-file') + '"></i><span class="text-truncate">' + (item.label || item.name || '') + '</span><span class="result-type">' + ((item.type || '').charAt(0).toUpperCase() + (item.type || '').slice(1)) + '</span>';
                                searchResults.appendChild(div);
                            });
                            searchResults.classList.add('active');
                        } else {
                            searchResults.innerHTML = '<div style="padding:12px;text-align:center;color:var(--gray-500);font-size:0.8rem;"><i class="fas fa-search" style="display:block;font-size:1.2rem;margin-bottom:4px;"></i>No results found</div>';
                            searchResults.classList.add('active');
                        }
                    }
                })
                .catch(function() {});
        }, 300);
    });

    document.addEventListener('click', function(e) {
        var wrapper = document.querySelector('.search-wrapper');
        if (wrapper && !wrapper.contains(e.target) && searchResults) {
            searchResults.classList.remove('active');
        }
    });
}
</script>
</body>
</html>