<?php
// ============================================================
// ELECTION VIEW - CLIENT ADMIN (PROFESSIONAL UI)
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
    $stmt = $db->prepare("
        SELECT e.*, u.full_name as created_by_name
        FROM elections e
        LEFT JOIN users u ON e.created_by = u.id
        WHERE e.id = ? AND e.tenant_id = ? AND e.deleted_at IS NULL
    ");
    $stmt->execute([$election_id, $tenant_id]);
    $election = $stmt->fetch();
} catch (Exception $e) {
    // Continue
}

if (!$election) {
    header('Location: elections.php');
    exit();
}

// ============================================================
// FETCH STATISTICS
// ============================================================
$stats = [
    'total_candidates' => 0,
    'total_polling_units' => 0,
    'total_results' => 0,
    'verified_results' => 0,
    'pending_results' => 0,
    'rejected_results' => 0,
    'total_incidents' => 0
];

try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM candidates WHERE election_id = ?");
    $stmt->execute([$election_id]);
    $stats['total_candidates'] = $stmt->fetch()['total'] ?? 0;
    
    $pus_json = json_decode($election['pus_json'] ?? '[]', true);
    $stats['total_polling_units'] = count($pus_json);
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM results_ec8a WHERE election_id = ?");
    $stmt->execute([$election_id]);
    $stats['total_results'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM results_ec8a WHERE election_id = ? AND status = 'verified'");
    $stmt->execute([$election_id]);
    $stats['verified_results'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM results_ec8a WHERE election_id = ? AND status = 'pending'");
    $stmt->execute([$election_id]);
    $stats['pending_results'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM results_ec8a WHERE election_id = ? AND status = 'rejected'");
    $stmt->execute([$election_id]);
    $stats['rejected_results'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM incidents WHERE election_id = ?");
    $stmt->execute([$election_id]);
    $stats['total_incidents'] = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {
    // Continue
}

// ============================================================
// FETCH CANDIDATES
// ============================================================
$candidates = [];
try {
    $stmt = $db->prepare("
        SELECT c.*, p.name as party_name, p.acronym as party_acronym
        FROM candidates c
        LEFT JOIN political_parties p ON c.party_id = p.id
        WHERE c.election_id = ?
        ORDER BY c.position, c.last_name
        LIMIT 10
    ");
    $stmt->execute([$election_id]);
    $candidates = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH POLLING UNITS
// ============================================================
$pus = [];
$pus_ids = json_decode($election['pus_json'] ?? '[]', true);
if (!empty($pus_ids)) {
    $placeholders = implode(',', array_fill(0, count($pus_ids), '?'));
    try {
        $stmt = $db->prepare("
            SELECT pu.*, w.name as ward_name, l.name as lga_name, s.name as state_name
            FROM polling_units pu
            LEFT JOIN wards w ON pu.ward_id = w.id
            LEFT JOIN lgas l ON w.lga_id = l.id
            LEFT JOIN states s ON l.state_id = s.id
            WHERE pu.id IN ($placeholders)
            ORDER BY s.name, l.name, w.name, pu.name
            LIMIT 10
        ");
        $stmt->execute($pus_ids);
        $pus = $stmt->fetchAll();
    } catch (Exception $e) {}
}

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       ELECTION VIEW - PROFESSIONAL UI STYLES
       ============================================================ */
    
    :root {
        --status-draft: #94A3B8;
        --status-upcoming: #F59E0B;
        --status-active: #10B981;
        --status-completed: #3B82F6;
        --status-cancelled: #EF4444;
        --status-archived: #8B5CF6;
    }
    
    /* Page Header */
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
    
    /* Buttons */
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
    .btn-danger {
        padding: 10px 20px;
        background: var(--danger);
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
    .btn-danger:hover {
        background: #DC2626;
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(239, 68, 68, 0.3);
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
    .btn-outline-primary {
        padding: 10px 18px;
        background: transparent;
        color: var(--primary);
        border: 1.5px solid var(--primary);
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
    .btn-outline-primary:hover {
        background: var(--primary);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(var(--primary-rgb), 0.2);
    }
    .btn-sm {
        padding: 4px 12px;
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
    .btn-sm.warning { background: #FFFBEB; color: #92400E; }
    .btn-sm.warning:hover { background: #FEF3C7; }
    .btn-sm.info { background: #EFF6FF; color: #1E40AF; }
    .btn-sm.info:hover { background: #DBEAFE; }
    .btn-sm.purple { background: #F5F3FF; color: #5B21B6; }
    .btn-sm.purple:hover { background: #EDE9FE; }
    
    /* Election Hero */
    .election-hero {
        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
        border-radius: var(--radius);
        padding: 32px 40px;
        box-shadow: var(--shadow-hover);
        margin-bottom: 24px;
        position: relative;
        overflow: hidden;
        color: white;
    }
    .election-hero::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 40%;
        height: 200%;
        background: linear-gradient(135deg, rgba(255,255,255,0.05) 0%, transparent 100%);
        transform: rotate(15deg);
        pointer-events: none;
    }
    .election-hero::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary), var(--secondary), #F59E0B, var(--primary));
        background-size: 300% 100%;
        animation: gradientMove 3s linear infinite;
    }
    @keyframes gradientMove {
        0% { background-position: 0% 0%; }
        100% { background-position: 300% 0%; }
    }
    .election-hero .hero-content {
        display: flex;
        align-items: center;
        gap: 24px;
        flex-wrap: wrap;
        position: relative;
        z-index: 1;
    }
    .election-hero .hero-icon {
        width: 72px;
        height: 72px;
        border-radius: 18px;
        background: rgba(255,255,255,0.1);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.15);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        flex-shrink: 0;
        color: #FCD34D;
    }
    .election-hero .hero-info h1 {
        font-size: 1.6rem;
        font-weight: 700;
        margin-bottom: 6px;
        color: white;
    }
    .election-hero .hero-info .meta {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        font-size: 0.85rem;
        color: rgba(255,255,255,0.7);
    }
    .election-hero .hero-info .meta span {
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .election-hero .hero-info .meta span i {
        color: rgba(255,255,255,0.5);
    }
    .election-hero .hero-actions {
        margin-left: auto;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    .election-hero .hero-actions .btn-outline {
        background: rgba(255,255,255,0.1);
        border-color: rgba(255,255,255,0.2);
        color: white;
    }
    .election-hero .hero-actions .btn-outline:hover {
        background: rgba(255,255,255,0.2);
        border-color: rgba(255,255,255,0.3);
    }
    .election-hero .hero-actions .btn-primary {
        background: white;
        color: var(--primary);
    }
    .election-hero .hero-actions .btn-primary:hover {
        background: rgba(255,255,255,0.9);
        color: var(--primary-dark);
    }
    
    /* Badge Status */
    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    .badge-status .dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        display: inline-block;
        animation: pulse-dot 2s ease-in-out infinite;
    }
    @keyframes pulse-dot {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.4; }
    }
    .badge-status.draft { background: rgba(148, 163, 184, 0.2); color: var(--gray-500); }
    .badge-status.draft .dot { background: var(--gray-400); }
    .badge-status.upcoming { background: rgba(245, 158, 11, 0.2); color: #92400E; }
    .badge-status.upcoming .dot { background: #F59E0B; }
    .badge-status.active { background: rgba(16, 185, 129, 0.2); color: #065F46; }
    .badge-status.active .dot { background: #10B981; }
    .badge-status.completed { background: rgba(59, 130, 246, 0.2); color: #1E40AF; }
    .badge-status.completed .dot { background: #3B82F6; }
    .badge-status.cancelled { background: rgba(239, 68, 68, 0.2); color: #991B1B; }
    .badge-status.cancelled .dot { background: #EF4444; }
    .badge-status.archived { background: rgba(139, 92, 246, 0.2); color: #5B21B6; }
    .badge-status.archived .dot { background: #8B5CF6; }
    
    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 14px;
        margin-bottom: 24px;
    }
    .stat-item {
        background: white;
        border-radius: 14px;
        padding: 18px 20px;
        border: 1px solid var(--gray-200);
        text-align: center;
        transition: var(--transition);
        position: relative;
        overflow: hidden;
        cursor: default;
    }
    .stat-item::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
        opacity: 0;
        transition: var(--transition);
    }
    .stat-item:hover::before {
        opacity: 1;
    }
    .stat-item:hover {
        box-shadow: var(--shadow-hover);
        transform: translateY(-3px);
    }
    .stat-item .number {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--primary);
        line-height: 1.2;
    }
    .stat-item .number.green { color: var(--secondary); }
    .stat-item .number.yellow { color: var(--warning); }
    .stat-item .number.purple { color: #8B5CF6; }
    .stat-item .number.blue { color: #3B82F6; }
    .stat-item .number.red { color: var(--danger); }
    .stat-item .number.orange { color: #F59E0B; }
    .stat-item .label {
        font-size: 0.75rem;
        color: var(--gray-500);
        margin-top: 4px;
        font-weight: 500;
    }
    .stat-item .sub-label {
        font-size: 0.65rem;
        color: var(--gray-400);
        margin-top: 2px;
    }
    .stat-item .trend {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 0.6rem;
        font-weight: 600;
        margin-top: 4px;
        padding: 2px 10px;
        border-radius: 12px;
    }
    .stat-item .trend.up { background: #ECFDF5; color: var(--secondary); }
    .stat-item .trend.down { background: #FEF2F2; color: var(--danger); }
    .stat-item .trend.neutral { background: var(--gray-100); color: var(--gray-500); }
    
    /* Detail Grid */
    .detail-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
    }
    .detail-card {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 24px 28px;
        box-shadow: var(--shadow);
        transition: var(--transition);
    }
    .detail-card:hover {
        box-shadow: var(--shadow-hover);
    }
    .detail-card .card-title {
        font-weight: 600;
        font-size: 1rem;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 2px solid var(--gray-100);
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--gray-700);
    }
    .detail-card .card-title i {
        color: var(--primary);
        font-size: 1.1rem;
    }
    .detail-card .card-title .badge-count {
        background: var(--primary);
        color: white;
        padding: 0 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
        margin-left: auto;
    }
    
    /* Detail Rows */
    .detail-row {
        display: flex;
        padding: 10px 0;
        border-bottom: 1px solid var(--gray-50);
        font-size: 0.85rem;
        transition: var(--transition);
    }
    .detail-row:hover {
        background: var(--gray-50);
        margin: 0 -8px;
        padding: 10px 8px;
        border-radius: 6px;
    }
    .detail-row:last-child {
        border-bottom: none;
    }
    .detail-row .label {
        font-weight: 500;
        color: var(--gray-500);
        min-width: 140px;
        flex-shrink: 0;
    }
    .detail-row .value {
        color: var(--gray-700);
        word-break: break-word;
    }
    .detail-row .value .highlight {
        background: #FEF3C7;
        padding: 2px 8px;
        border-radius: 4px;
        font-weight: 600;
    }
    
    /* List Items */
    .list-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid var(--gray-50);
        font-size: 0.85rem;
        transition: var(--transition);
    }
    .list-item:hover {
        background: var(--gray-50);
        margin: 0 -8px;
        padding: 10px 8px;
        border-radius: 6px;
    }
    .list-item:last-child {
        border-bottom: none;
    }
    .list-item .name {
        font-weight: 500;
        color: var(--gray-700);
    }
    .list-item .sub {
        font-size: 0.75rem;
        color: var(--gray-500);
        margin-top: 2px;
    }
    .list-item .party-badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
        background: #F5F3FF;
        color: #5B21B6;
    }
    .list-item .pu-code {
        font-family: monospace;
        font-size: 0.7rem;
        background: var(--gray-100);
        padding: 2px 8px;
        border-radius: 4px;
        color: var(--gray-600);
    }
    
    /* Document Items */
    .doc-item {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 10px 0;
        border-bottom: 1px solid var(--gray-50);
        transition: var(--transition);
    }
    .doc-item:hover {
        background: var(--gray-50);
        margin: 0 -8px;
        padding: 10px 8px;
        border-radius: 6px;
    }
    .doc-item:last-child {
        border-bottom: none;
    }
    .doc-item .doc-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }
    .doc-item .doc-icon.pdf { background: #FEF2F2; color: #DC2626; }
    .doc-item .doc-icon.word { background: #EFF6FF; color: #2563EB; }
    .doc-item .doc-icon.excel { background: #ECFDF5; color: #10B981; }
    .doc-item .doc-icon.text { background: #F5F3FF; color: #7C3AED; }
    .doc-item .doc-icon.image { background: #FFFBEB; color: #F59E0B; }
    .doc-item .doc-icon.other { background: var(--gray-100); color: var(--gray-500); }
    .doc-item .doc-info {
        flex: 1;
    }
    .doc-item .doc-info .doc-name {
        font-weight: 500;
        font-size: 0.85rem;
        color: var(--gray-700);
    }
    .doc-item .doc-info .doc-meta {
        font-size: 0.7rem;
        color: var(--gray-400);
        margin-top: 2px;
    }
    .doc-item .doc-info .doc-meta .doc-type {
        background: var(--gray-100);
        padding: 1px 10px;
        border-radius: 10px;
        font-size: 0.6rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    
    /* Empty State */
    .empty-state-small {
        text-align: center;
        padding: 30px 20px;
        color: var(--gray-400);
        font-size: 0.85rem;
    }
    .empty-state-small i {
        font-size: 2rem;
        display: block;
        margin-bottom: 8px;
        color: var(--gray-300);
    }
    
    /* Tabs */
    .tab-container {
        margin-top: 16px;
    }
    .tab-buttons {
        display: flex;
        gap: 4px;
        border-bottom: 2px solid var(--gray-200);
        margin-bottom: 20px;
        padding: 0 4px;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .tab-btn {
        padding: 12px 24px;
        border: none;
        background: none;
        font-family: 'Inter', sans-serif;
        font-size: 0.85rem;
        font-weight: 500;
        color: var(--gray-500);
        cursor: pointer;
        transition: var(--transition);
        border-bottom: 2px solid transparent;
        margin-bottom: -2px;
        white-space: nowrap;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .tab-btn:hover {
        color: var(--gray-700);
        background: var(--gray-50);
        border-radius: 8px 8px 0 0;
    }
    .tab-btn.active {
        color: var(--primary);
        border-bottom-color: var(--primary);
        font-weight: 600;
    }
    .tab-btn .tab-count {
        background: var(--gray-100);
        color: var(--gray-500);
        padding: 0 8px;
        border-radius: 10px;
        font-size: 0.6rem;
        font-weight: 600;
    }
    .tab-btn.active .tab-count {
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
    
    /* Quick Actions */
    .quick-actions {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .quick-actions .action-btn {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        border-radius: 10px;
        border: 1px solid var(--gray-200);
        background: white;
        text-decoration: none;
        color: var(--gray-700);
        transition: var(--transition);
        font-size: 0.85rem;
        font-weight: 500;
    }
    .quick-actions .action-btn:hover {
        border-color: var(--primary);
        background: #EFF6FF;
        transform: translateX(4px);
    }
    .quick-actions .action-btn i {
        width: 20px;
        color: var(--primary);
        font-size: 1rem;
    }
    .quick-actions .action-btn .arrow {
        margin-left: auto;
        color: var(--gray-300);
        font-size: 0.7rem;
        transition: var(--transition);
    }
    .quick-actions .action-btn:hover .arrow {
        transform: translateX(4px);
        color: var(--primary);
    }
    .quick-actions .action-btn.danger i { color: var(--danger); }
    .quick-actions .action-btn.danger:hover { border-color: var(--danger); background: #FEF2F2; }
    .quick-actions .action-btn.success i { color: var(--secondary); }
    .quick-actions .action-btn.success:hover { border-color: var(--secondary); background: #ECFDF5; }
    .quick-actions .action-btn.warning i { color: #F59E0B; }
    .quick-actions .action-btn.warning:hover { border-color: #F59E0B; background: #FFFBEB; }
    
    /* Modal */
    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        backdrop-filter: blur(8px);
        z-index: 300;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .modal-overlay.active { display: flex; }
    .modal {
        background: white;
        border-radius: var(--radius);
        max-width: 540px;
        width: 100%;
        padding: 28px 32px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        animation: modalIn 0.3s ease;
        max-height: 90vh;
        overflow-y: auto;
    }
    @keyframes modalIn {
        from { opacity: 0; transform: scale(0.95) translateY(20px); }
        to { opacity: 1; transform: scale(1) translateY(0); }
    }
    .modal .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 14px;
        border-bottom: 2px solid var(--gray-100);
    }
    .modal .modal-header h3 {
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--gray-800);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .modal .modal-header h3 i {
        color: var(--primary);
    }
    .modal .modal-header .close-btn {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: var(--gray-400);
        cursor: pointer;
        transition: var(--transition);
        padding: 4px 8px;
        border-radius: 8px;
    }
    .modal .modal-header .close-btn:hover {
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .modal .form-group {
        margin-bottom: 16px;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .modal .form-group label {
        font-weight: 600;
        font-size: 0.85rem;
        color: var(--gray-700);
    }
    .modal .form-group label .required {
        color: var(--danger);
        margin-left: 2px;
    }
    .modal .form-group .help-text {
        font-size: 0.75rem;
        color: var(--gray-400);
        margin-top: 2px;
    }
    .modal .form-group select,
    .modal .form-group input {
        padding: 10px 14px;
        border: 1.5px solid var(--gray-200);
        border-radius: 10px;
        font-family: 'Inter', sans-serif;
        font-size: 0.85rem;
        transition: var(--transition);
        background: var(--gray-50);
        color: var(--gray-700);
        width: 100%;
    }
    .modal .form-group select:focus,
    .modal .form-group input:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 4px rgba(var(--primary-rgb), 0.08);
    }
    .modal .file-upload-area {
        border: 2px dashed var(--gray-200);
        border-radius: 10px;
        padding: 30px 20px;
        text-align: center;
        cursor: pointer;
        transition: var(--transition);
        background: var(--gray-50);
    }
    .modal .file-upload-area:hover {
        border-color: var(--primary);
        background: #EFF6FF;
    }
    .modal .file-upload-area i {
        font-size: 2.5rem;
        color: var(--gray-400);
        display: block;
        margin-bottom: 10px;
    }
    .modal .file-upload-area p {
        font-size: 0.9rem;
        color: var(--gray-500);
        margin-bottom: 4px;
    }
    .modal .file-upload-area .file-types {
        font-size: 0.7rem;
        color: var(--gray-400);
    }
    .modal .file-upload-area input[type="file"] {
        display: none;
    }
    .modal .file-preview {
        display: none;
        margin-top: 12px;
        padding: 12px 16px;
        background: var(--gray-50);
        border-radius: 8px;
        border: 1px solid var(--gray-200);
        text-align: left;
    }
    .modal .file-preview.show {
        display: block;
    }
    .modal .file-preview .file-name {
        font-weight: 500;
        font-size: 0.85rem;
        color: var(--gray-700);
    }
    .modal .file-preview .file-size {
        font-size: 0.7rem;
        color: var(--gray-400);
        margin-top: 2px;
    }
    .modal .modal-footer {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 20px;
        padding-top: 16px;
        border-top: 2px solid var(--gray-100);
    }
    .modal .modal-footer .btn {
        padding: 10px 24px;
        border-radius: 10px;
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
    .modal .modal-footer .btn-primary {
        background: var(--primary);
        color: white;
    }
    .modal .modal-footer .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.25);
    }
    .modal .modal-footer .btn-secondary {
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .modal .modal-footer .btn-secondary:hover {
        background: var(--gray-200);
    }
    
    /* Responsive */
    @media (max-width: 992px) {
        .detail-grid {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 768px) {
        .election-hero {
            padding: 20px;
        }
        .election-hero .hero-content {
            flex-direction: column;
            align-items: flex-start;
        }
        .election-hero .hero-actions {
            margin-left: 0;
            width: 100%;
        }
        .election-hero .hero-actions .btn {
            flex: 1;
            justify-content: center;
        }
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .detail-row {
            flex-direction: column;
            padding: 8px 0;
        }
        .detail-row .label {
            min-width: auto;
            font-size: 0.75rem;
        }
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .tab-buttons {
            flex-wrap: nowrap;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .tab-btn {
            padding: 10px 16px;
            font-size: 0.8rem;
        }
        .modal {
            padding: 20px;
            margin: 10px;
        }
        .modal .modal-footer {
            flex-direction: column;
        }
        .modal .modal-footer .btn {
            width: 100%;
            justify-content: center;
        }
    }
    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .stat-item {
            padding: 12px 14px;
        }
        .stat-item .number {
            font-size: 1.3rem;
        }
        .election-hero .hero-icon {
            width: 56px;
            height: 56px;
            font-size: 1.4rem;
        }
        .election-hero .hero-info h1 {
            font-size: 1.2rem;
        }
        .detail-card {
            padding: 16px 18px;
        }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-vote-yea" style="color:var(--primary);margin-right:8px;"></i> Election Details
                    <small>Complete overview of election data and performance</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="elections-edit.php?id=<?php echo $election_id; ?>" class="btn-primary">
                    <i class="fas fa-edit"></i> Edit Election
                </a>
                <a href="elections.php" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Election Hero -->
        <div class="election-hero">
            <div class="hero-content">
                <div class="hero-icon">
                    <i class="fas fa-vote-yea"></i>
                </div>
                <div class="hero-info">
                    <h1><?php echo htmlspecialchars($election['name']); ?></h1>
                    <div class="meta">
                        <span><i class="fas fa-tag"></i> <?php echo ucfirst(str_replace('_', ' ', $election['type'])); ?></span>
                        <span><i class="fas fa-calendar-alt"></i> <?php echo date('M j, Y', strtotime($election['election_date'])); ?></span>
                        <span><i class="fas fa-code-branch"></i> Cycle: <?php echo htmlspecialchars($election['cycle']); ?></span>
                        <span>
                            <span class="badge-status <?php echo $election['status']; ?>">
                                <span class="dot"></span>
                                <?php echo ucfirst($election['status']); ?>
                            </span>
                        </span>
                        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($election['created_by_name'] ?? 'System'); ?></span>
                    </div>
                </div>
                <div class="hero-actions">
                    <a href="elections-results.php?id=<?php echo $election_id; ?>" class="btn-outline">
                        <i class="fas fa-chart-bar"></i> Results
                    </a>
                    <a href="elections-export.php?id=<?php echo $election_id; ?>" class="btn-outline">
                        <i class="fas fa-file-export"></i> Export
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-item">
                <div class="number"><?php echo number_format($stats['total_candidates']); ?></div>
                <div class="label">Total Candidates</div>
                <div class="sub-label">Running for office</div>
            </div>
            <div class="stat-item">
                <div class="number purple"><?php echo number_format($stats['total_polling_units']); ?></div>
                <div class="label">Polling Units</div>
                <div class="sub-label">Across all locations</div>
            </div>
            <div class="stat-item">
                <div class="number blue"><?php echo number_format($stats['total_results']); ?></div>
                <div class="label">Total Results</div>
                <div class="sub-label">Submitted so far</div>
            </div>
            <div class="stat-item">
                <div class="number green"><?php echo number_format($stats['verified_results']); ?></div>
                <div class="label">Verified</div>
                <div class="sub-label"><?php echo $stats['total_results'] > 0 ? round(($stats['verified_results'] / $stats['total_results']) * 100, 1) : 0; ?>% verified</div>
                <div class="trend up"><i class="fas fa-check-circle"></i> Confirmed</div>
            </div>
            <div class="stat-item">
                <div class="number yellow"><?php echo number_format($stats['pending_results']); ?></div>
                <div class="label">Pending</div>
                <div class="sub-label">Awaiting verification</div>
                <div class="trend neutral"><i class="fas fa-clock"></i> In progress</div>
            </div>
            <div class="stat-item">
                <div class="number red"><?php echo number_format($stats['total_incidents']); ?></div>
                <div class="label">Incidents</div>
                <div class="sub-label">Reports filed</div>
                <div class="trend down"><i class="fas fa-exclamation-triangle"></i> Needs attention</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tab-container">
            <div class="tab-buttons">
                <button class="tab-btn active" data-tab="overview" onclick="switchTab('overview')">
                    <i class="fas fa-th-large"></i> Overview
                </button>
                <button class="tab-btn" data-tab="candidates" onclick="switchTab('candidates')">
                    <i class="fas fa-user-tie"></i> Candidates
                    <span class="tab-count"><?php echo $stats['total_candidates']; ?></span>
                </button>
                <button class="tab-btn" data-tab="polling_units" onclick="switchTab('polling_units')">
                    <i class="fas fa-map-marker-alt"></i> Polling Units
                    <span class="tab-count"><?php echo $stats['total_polling_units']; ?></span>
                </button>
                <button class="tab-btn" data-tab="documents" onclick="switchTab('documents')">
                    <i class="fas fa-file-alt"></i> Documents
                </button>
            </div>

            <!-- Overview Tab -->
            <div id="tab-overview" class="tab-content active">
                <div class="detail-grid">
                    <!-- Left Column - Election Details -->
                    <div>
                        <div class="detail-card">
                            <div class="card-title">
                                <i class="fas fa-info-circle" style="color:var(--primary);"></i> Election Information
                            </div>
                            <div class="detail-row">
                                <span class="label">Election Name</span>
                                <span class="value"><strong><?php echo htmlspecialchars($election['name']); ?></strong></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Type</span>
                                <span class="value"><?php echo ucfirst(str_replace('_', ' ', $election['type'])); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Cycle</span>
                                <span class="value"><span class="highlight"><?php echo htmlspecialchars($election['cycle']); ?></span></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Status</span>
                                <span class="value">
                                    <span class="badge-status <?php echo $election['status']; ?>">
                                        <span class="dot"></span>
                                        <?php echo ucfirst($election['status']); ?>
                                    </span>
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Election Date</span>
                                <span class="value">
                                    <i class="fas fa-calendar-day" style="color:var(--primary);"></i>
                                    <?php echo date('l, F j, Y', strtotime($election['election_date'])); ?>
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Time</span>
                                <span class="value">
                                    <?php 
                                    if (!empty($election['start_time']) && !empty($election['end_time'])) {
                                        echo date('g:i A', strtotime($election['start_time'])) . ' - ' . date('g:i A', strtotime($election['end_time']));
                                    } else {
                                        echo 'Not set';
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Created By</span>
                                <span class="value">
                                    <i class="fas fa-user" style="color:var(--gray-400);"></i>
                                    <?php echo htmlspecialchars($election['created_by_name'] ?? 'System'); ?>
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Created</span>
                                <span class="value"><?php echo date('M j, Y g:i A', strtotime($election['created_at'])); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="label">Last Updated</span>
                                <span class="value"><?php echo date('M j, Y g:i A', strtotime($election['updated_at'])); ?></span>
                            </div>
                            <?php if (!empty($election['description'])): ?>
                                <div class="detail-row" style="flex-direction:column;align-items:flex-start;">
                                    <span class="label">Description</span>
                                    <span class="value" style="margin-top:6px;line-height:1.6;color:var(--gray-600);">
                                        <?php echo nl2br(htmlspecialchars($election['description'])); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Right Column - Quick Actions & Jurisdiction -->
                    <div>
                        <!-- Quick Actions -->
                        <div class="detail-card">
                            <div class="card-title">
                                <i class="fas fa-bolt" style="color:var(--primary);"></i> Quick Actions
                            </div>
                            <div class="quick-actions">
                                <a href="elections-edit.php?id=<?php echo $election_id; ?>" class="action-btn">
                                    <i class="fas fa-edit"></i> Edit Election
                                    <span class="arrow"><i class="fas fa-chevron-right"></i></span>
                                </a>
                                <a href="elections-candidates.php?id=<?php echo $election_id; ?>" class="action-btn">
                                    <i class="fas fa-user-tie"></i> Manage Candidates
                                    <span class="arrow"><i class="fas fa-chevron-right"></i></span>
                                </a>
                                <a href="elections-pus.php?id=<?php echo $election_id; ?>" class="action-btn">
                                    <i class="fas fa-map-marker-alt"></i> Polling Units
                                    <span class="arrow"><i class="fas fa-chevron-right"></i></span>
                                </a>
                                <a href="elections-results.php?id=<?php echo $election_id; ?>" class="action-btn success">
                                    <i class="fas fa-chart-bar"></i> View Results
                                    <span class="arrow"><i class="fas fa-chevron-right"></i></span>
                                </a>
                                <a href="elections-export.php?id=<?php echo $election_id; ?>" class="action-btn warning">
                                    <i class="fas fa-file-export"></i> Export Data
                                    <span class="arrow"><i class="fas fa-chevron-right"></i></span>
                                </a>
                                <a href="#" class="action-btn danger" onclick="if(confirm('Are you sure you want to cancel this election?')){alert('Election cancelled');}">
                                    <i class="fas fa-times-circle"></i> Cancel Election
                                    <span class="arrow"><i class="fas fa-chevron-right"></i></span>
                                </a>
                            </div>
                        </div>

                        <!-- Jurisdiction -->
                        <div class="detail-card" style="margin-top:16px;">
                            <div class="card-title">
                                <i class="fas fa-map-marked-alt" style="color:var(--primary);"></i> Jurisdiction
                            </div>
                            <?php
                            $states_list = json_decode($election['states_json'] ?? '[]', true);
                            if (!empty($states_list)):
                            ?>
                                <div style="display:flex;flex-wrap:wrap;gap:6px;">
                                    <?php 
                                    foreach ($states_list as $state_id) {
                                        // Fetch state name from database or use ID
                                        try {
                                            $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
                                            $stmt->execute([$state_id]);
                                            $state_name = $stmt->fetch()['name'] ?? "State #$state_id";
                                            echo '<span style="background:#EFF6FF;padding:4px 12px;border-radius:6px;font-size:0.75rem;font-weight:500;color:#1E40AF;">' . htmlspecialchars($state_name) . '</span>';
                                        } catch (Exception $e) {
                                            echo '<span style="background:#EFF6FF;padding:4px 12px;border-radius:6px;font-size:0.75rem;font-weight:500;color:#1E40AF;">State #' . $state_id . '</span>';
                                        }
                                    }
                                    ?>
                                </div>
                                <div style="margin-top:8px;font-size:0.75rem;color:var(--gray-400);">
                                    <i class="fas fa-info-circle"></i> 
                                    <?php echo count($states_list); ?> state(s) included
                                </div>
                            <?php else: ?>
                                <div style="display:flex;align-items:center;gap:8px;padding:8px 0;font-size:0.85rem;color:var(--gray-500);">
                                    <i class="fas fa-globe-africa" style="font-size:1.2rem;color:var(--primary);"></i>
                                    All States (National Election)
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Progress -->
                        <div class="detail-card" style="margin-top:16px;">
                            <div class="card-title">
                                <i class="fas fa-chart-pie" style="color:var(--primary);"></i> Progress
                            </div>
                            <?php 
                            $progress = $stats['total_results'] > 0 ? round(($stats['verified_results'] / $stats['total_results']) * 100, 1) : 0;
                            $progress_color = $progress >= 80 ? '#10B981' : ($progress >= 50 ? '#F59E0B' : '#3B82F6');
                            ?>
                            <div style="margin-bottom:12px;">
                                <div style="display:flex;justify-content:space-between;font-size:0.8rem;color:var(--gray-500);">
                                    <span>Verification Progress</span>
                                    <span style="font-weight:600;color:<?php echo $progress_color; ?>;"><?php echo $progress; ?>%</span>
                                </div>
                                <div style="width:100%;height:8px;background:var(--gray-100);border-radius:4px;overflow:hidden;margin-top:4px;">
                                    <div style="height:100%;width:<?php echo $progress; ?>%;background:<?php echo $progress_color; ?>;border-radius:4px;transition:width 1s ease;"></div>
                                </div>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:0.75rem;">
                                <div style="background:#ECFDF5;padding:8px 12px;border-radius:6px;text-align:center;color:#065F46;">
                                    <div style="font-weight:700;font-size:1rem;"><?php echo number_format($stats['verified_results']); ?></div>
                                    <div>Verified</div>
                                </div>
                                <div style="background:#FFFBEB;padding:8px 12px;border-radius:6px;text-align:center;color:#92400E;">
                                    <div style="font-weight:700;font-size:1rem;"><?php echo number_format($stats['pending_results']); ?></div>
                                    <div>Pending</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Candidates Tab -->
            <div id="tab-candidates" class="tab-content">
                <div class="detail-card">
                    <div class="card-title">
                        <i class="fas fa-user-tie" style="color:var(--primary);"></i> Candidates
                        <a href="elections-candidates.php?id=<?php echo $election_id; ?>" style="margin-left:auto;font-size:0.8rem;color:var(--primary);text-decoration:none;font-weight:500;">
                            View All →
                        </a>
                    </div>
                    <?php if (count($candidates) > 0): ?>
                        <?php foreach ($candidates as $candidate): ?>
                            <div class="list-item">
                                <div>
                                    <div class="name">
                                        <?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']); ?>
                                    </div>
                                    <div class="sub">
                                        <strong><?php echo htmlspecialchars($candidate['position']); ?></strong>
                                        <?php if (!empty($candidate['party_acronym'])): ?>
                                            <span class="party-badge"><?php echo htmlspecialchars($candidate['party_acronym']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($candidate['party_name'])): ?>
                                            · <?php echo htmlspecialchars($candidate['party_name']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="badge-status <?php echo $candidate['is_active'] ? 'active' : 'inactive'; ?>">
                                    <span class="dot"></span>
                                    <?php echo $candidate['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($candidates) >= 10): ?>
                            <div style="text-align:center;padding-top:12px;border-top:1px solid var(--gray-100);">
                                <a href="elections-candidates.php?id=<?php echo $election_id; ?>" style="color:var(--primary);text-decoration:none;font-weight:500;font-size:0.85rem;">
                                    View all <?php echo number_format($stats['total_candidates']); ?> candidates →
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state-small">
                            <i class="fas fa-user-tie"></i>
                            No candidates added yet
                            <div style="margin-top:8px;">
                                <a href="elections-candidates.php?id=<?php echo $election_id; ?>" class="btn-primary" style="padding:6px 16px;font-size:0.8rem;">
                                    <i class="fas fa-plus"></i> Add Candidates
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Polling Units Tab -->
            <div id="tab-polling_units" class="tab-content">
                <div class="detail-card">
                    <div class="card-title">
                        <i class="fas fa-map-marker-alt" style="color:var(--primary);"></i> Polling Units
                        <a href="elections-pus.php?id=<?php echo $election_id; ?>" style="margin-left:auto;font-size:0.8rem;color:var(--primary);text-decoration:none;font-weight:500;">
                            Manage All →
                        </a>
                    </div>
                    <?php if (count($pus) > 0): ?>
                        <?php foreach ($pus as $pu): ?>
                            <div class="list-item">
                                <div>
                                    <div class="name">
                                        <span class="pu-code"><?php echo htmlspecialchars($pu['code']); ?></span>
                                        <?php echo htmlspecialchars($pu['name']); ?>
                                    </div>
                                    <div class="sub">
                                        <i class="fas fa-layer-group" style="font-size:0.6rem;"></i>
                                        <?php echo htmlspecialchars($pu['ward_name'] ?? 'N/A'); ?>
                                        <i class="fas fa-map-marker-alt" style="font-size:0.6rem;margin-left:8px;"></i>
                                        <?php echo htmlspecialchars($pu['lga_name'] ?? 'N/A'); ?>
                                        <?php if (!empty($pu['state_name'])): ?>
                                            <i class="fas fa-flag" style="font-size:0.6rem;margin-left:8px;"></i>
                                            <?php echo htmlspecialchars($pu['state_name']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div style="text-align:right;">
                                    <div style="font-weight:600;font-size:0.85rem;">
                                        <?php echo number_format($pu['registered_voters'] ?? 0); ?>
                                    </div>
                                    <div style="font-size:0.6rem;color:var(--gray-400);">voters</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($pus) >= 10): ?>
                            <div style="text-align:center;padding-top:12px;border-top:1px solid var(--gray-100);">
                                <a href="elections-pus.php?id=<?php echo $election_id; ?>" style="color:var(--primary);text-decoration:none;font-weight:500;font-size:0.85rem;">
                                    View all <?php echo number_format($stats['total_polling_units']); ?> polling units →
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state-small">
                            <i class="fas fa-map-marker-alt"></i>
                            No polling units assigned
                            <div style="margin-top:8px;">
                                <a href="elections-pus.php?id=<?php echo $election_id; ?>" class="btn-primary" style="padding:6px 16px;font-size:0.8rem;">
                                    <i class="fas fa-plus"></i> Assign Polling Units
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Documents Tab -->
            <div id="tab-documents" class="tab-content">
                <div class="detail-card">
                    <div class="card-title">
                        <i class="fas fa-file-alt" style="color:var(--primary);"></i> Documents
                        <button onclick="openModal('uploadDocModal')" style="margin-left:auto;padding:6px 16px;background:var(--primary);color:white;border:none;border-radius:8px;font-size:0.75rem;cursor:pointer;font-family:'Inter',sans-serif;font-weight:500;display:flex;align-items:center;gap:6px;transition:var(--transition);">
                            <i class="fas fa-upload"></i> Upload
                        </button>
                    </div>
                    
                    <?php
                    // Sample documents (in production, fetch from database)
                    $sample_docs = [
                        ['name' => 'Election_Guidelines_2027.pdf', 'size' => '1.2 MB', 'date' => '2024-01-15', 'type' => 'guidelines', 'icon' => 'pdf'],
                        ['name' => 'Candidate_List_Official.xlsx', 'size' => '856 KB', 'date' => '2024-01-20', 'type' => 'candidate_list', 'icon' => 'excel'],
                        ['name' => 'Election_Rules_Regulations.docx', 'size' => '2.4 MB', 'date' => '2024-01-10', 'type' => 'rules', 'icon' => 'word'],
                        ['name' => 'Official_Notice_Election_Date.pdf', 'size' => '345 KB', 'date' => '2024-01-25', 'type' => 'notice', 'icon' => 'pdf'],
                        ['name' => 'Voter_Registration_Guide.pdf', 'size' => '678 KB', 'date' => '2024-02-01', 'type' => 'voter_guide', 'icon' => 'pdf'],
                    ];
                    ?>
                    
                    <?php if (count($sample_docs) > 0): ?>
                        <?php foreach ($sample_docs as $doc): ?>
                            <div class="doc-item">
                                <div class="doc-icon <?php echo $doc['icon']; ?>">
                                    <i class="fas fa-file-<?php echo $doc['icon'] == 'pdf' ? 'pdf' : ($doc['icon'] == 'word' ? 'word' : ($doc['icon'] == 'excel' ? 'excel' : 'alt')); ?>"></i>
                                </div>
                                <div class="doc-info">
                                    <div class="doc-name"><?php echo htmlspecialchars($doc['name']); ?></div>
                                    <div class="doc-meta">
                                        <?php echo $doc['size']; ?> · <?php echo date('M j, Y', strtotime($doc['date'])); ?>
                                        <span class="doc-type"><?php echo ucfirst(str_replace('_', ' ', $doc['type'])); ?></span>
                                    </div>
                                </div>
                                <div class="doc-actions" style="display:flex;gap:6px;">
                                    <button class="btn-sm info" onclick="alert('Downloading <?php echo $doc['name']; ?>')">
                                        <i class="fas fa-download"></i>
                                    </button>
                                    <button class="btn-sm danger" onclick="if(confirm('Delete this document?')){alert('Deleted');}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div style="text-align:center;padding-top:12px;border-top:1px solid var(--gray-100);margin-top:4px;">
                            <span style="font-size:0.75rem;color:var(--gray-400);">
                                <i class="fas fa-info-circle"></i> Showing 5 of 5 documents
                            </span>
                        </div>
                    <?php else: ?>
                        <div class="empty-state-small">
                            <i class="fas fa-file-alt"></i>
                            No documents uploaded
                            <div style="margin-top:8px;">
                                <button onclick="openModal('uploadDocModal')" class="btn-primary" style="padding:6px 16px;font-size:0.8rem;">
                                    <i class="fas fa-upload"></i> Upload First Document
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div style="margin-top:16px;padding:12px 16px;background:#F5F3FF;border-radius:8px;border:1px solid #EDE9FE;color:#5B21B6;font-size:0.8rem;display:flex;align-items:center;gap:10px;">
                        <i class="fas fa-info-circle" style="font-size:1.1rem;"></i>
                        <div>
                            <strong>Supported file types:</strong> PDF, DOC, DOCX, XLS, XLSX, TXT
                            <span style="color:var(--gray-400);margin-left:8px;">| Max size: 10MB</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Upload Document Modal -->
<div class="modal-overlay" id="uploadDocModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-upload" style="color:var(--primary);"></i> Upload Document</h3>
            <button class="close-btn" onclick="closeModal('uploadDocModal')">&times;</button>
        </div>
        <form method="POST" action="elections-documents.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_document">
            <input type="hidden" name="election_id" value="<?php echo $election_id; ?>">
            <div class="form-group">
                <label>Document Type <span class="required">*</span></label>
                <select name="doc_type" required>
                    <option value="">Select Document Type</option>
                    <option value="guidelines">Election Guidelines</option>
                    <option value="candidate_list">Candidate List</option>
                    <option value="rules">Rules &amp; Regulations</option>
                    <option value="notice">Official Notice</option>
                    <option value="voter_guide">Voter Guide</option>
                    <option value="result_sheet">Result Sheet</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label>File <span class="required">*</span></label>
                <div class="file-upload-area" onclick="document.getElementById('docFile').click()">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p>Click to upload or drag &amp; drop</p>
                    <div class="file-types">Supported: PDF, DOC, DOCX, XLS, XLSX, TXT (Max 10MB)</div>
                    <input type="file" name="document" id="docFile" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt" required>
                </div>
                <div class="file-preview" id="docPreview">
                    <div class="file-name" id="docFileName">file.pdf</div>
                    <div class="file-size" id="docFileSize">0 KB</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('uploadDocModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-upload"></i> Upload Document
                </button>
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
// TAB SWITCHING
// ============================================================
function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(function(btn) {
        btn.classList.remove('active');
        if (btn.dataset.tab === tab) {
            btn.classList.add('active');
        }
    });
    
    document.querySelectorAll('.tab-content').forEach(function(content) {
        content.classList.remove('active');
    });
    document.getElementById('tab-' + tab).classList.add('active');
}

// ============================================================
// MODAL FUNCTIONS
// ============================================================
function openModal(id) {
    document.getElementById(id).classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
    document.body.style.overflow = '';
}

document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
});

// ============================================================
// FILE UPLOAD PREVIEW
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    var fileInput = document.getElementById('docFile');
    var preview = document.getElementById('docPreview');
    var fileName = document.getElementById('docFileName');
    var fileSize = document.getElementById('docFileSize');
    
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                var file = this.files[0];
                fileName.textContent = file.name;
                fileSize.textContent = (file.size / 1024).toFixed(1) + ' KB';
                preview.classList.add('show');
            } else {
                preview.classList.remove('show');
            }
        });
    }
});

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