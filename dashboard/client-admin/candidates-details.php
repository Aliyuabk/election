<?php
// ============================================================
// CANDIDATE DETAILS - CLIENT ADMIN (PROFESSIONAL UI)
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
// GET CANDIDATE ID
// ============================================================
$candidate_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($candidate_id <= 0) {
    header('Location: candidates.php');
    exit();
}

// ============================================================
// FETCH CANDIDATE DETAILS
// ============================================================
$candidate = null;
$stats = [
    'total_results' => 0,
    'votes_received' => 0,
    'percentage' => 0,
    'rank' => 0
];

try {
    $stmt = $db->prepare("
        SELECT c.*, 
               e.name as election_name, e.type as election_type, e.election_date, e.status as election_status,
               p.name as party_name, p.acronym as party_acronym, p.logo_url as party_logo,
               u.full_name as created_by_name
        FROM candidates c
        LEFT JOIN elections e ON c.election_id = e.id
        LEFT JOIN political_parties p ON c.party_id = p.id
        LEFT JOIN users u ON c.created_by = u.id
        WHERE c.id = ? AND c.tenant_id = ?
    ");
    $stmt->execute([$candidate_id, $tenant_id]);
    $candidate = $stmt->fetch();
    
    if (!$candidate) {
        header('Location: candidates.php');
        exit();
    }
    
    // Get candidate stats (votes, results)
    // In production, you would query the results table
    $stats['total_results'] = rand(50, 200); // Placeholder
    $stats['votes_received'] = rand(500, 5000); // Placeholder
    $stats['percentage'] = round(($stats['votes_received'] / 10000) * 100, 1);
    $stats['rank'] = rand(1, 5);
    
} catch (Exception $e) {
    header('Location: candidates.php');
    exit();
}

// ============================================================
// FETCH RELATED RESULTS (if any)
// ============================================================
$results = [];
try {
    $stmt = $db->prepare("
        SELECT r.*, pu.name as pu_name, pu.code as pu_code,
               w.name as ward_name, l.name as lga_name, s.name as state_name
        FROM results_ec8a r
        LEFT JOIN polling_units pu ON r.pu_id = pu.id
        LEFT JOIN wards w ON pu.ward_id = w.id
        LEFT JOIN lgas l ON w.lga_id = l.id
        LEFT JOIN states s ON l.state_id = s.id
        WHERE r.election_id = ? AND r.tenant_id = ?
        ORDER BY r.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$candidate['election_id'], $tenant_id]);
    $results = $stmt->fetchAll();
} catch (Exception $e) {}

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       CANDIDATE DETAILS - PROFESSIONAL UI STYLES
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
    
    /* Candidate Profile Header */
    .profile-header {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 32px 36px;
        box-shadow: var(--shadow);
        margin-bottom: 24px;
        position: relative;
        overflow: hidden;
    }
    .profile-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary), var(--secondary), #8B5CF6);
    }
    .profile-header .profile-content {
        display: flex;
        gap: 28px;
        flex-wrap: wrap;
        align-items: center;
    }
    .profile-header .profile-avatar {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        overflow: hidden;
        flex-shrink: 0;
        border: 4px solid var(--gray-200);
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        transition: var(--transition);
        position: relative;
    }
    .profile-header .profile-avatar:hover {
        border-color: var(--primary);
        transform: scale(1.02);
    }
    .profile-header .profile-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .profile-header .profile-avatar .no-photo {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--gray-100);
        font-size: 2.5rem;
        color: var(--gray-400);
    }
    .profile-header .profile-avatar .status-badge {
        position: absolute;
        bottom: 4px;
        right: 4px;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        border: 3px solid white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.6rem;
        color: white;
    }
    .profile-header .profile-avatar .status-badge.active { background: var(--secondary); }
    .profile-header .profile-avatar .status-badge.inactive { background: var(--danger); }
    
    .profile-header .profile-info {
        flex: 1;
    }
    .profile-header .profile-info h1 {
        font-size: 1.6rem;
        font-weight: 700;
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .profile-header .profile-info .position {
        font-size: 1rem;
        color: var(--gray-500);
        font-weight: 400;
    }
    .profile-header .profile-info .meta {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        margin-top: 8px;
        font-size: 0.85rem;
        color: var(--gray-500);
    }
    .profile-header .profile-info .meta span {
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .profile-header .profile-info .meta span i {
        color: var(--primary);
        width: 16px;
    }
    .profile-header .profile-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-left: auto;
    }
    
    /* Stats Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 12px;
        margin-bottom: 24px;
    }
    .stat-item {
        background: white;
        border-radius: 12px;
        padding: 16px 20px;
        border: 1px solid var(--gray-200);
        text-align: center;
        transition: var(--transition);
        cursor: default;
        position: relative;
        overflow: hidden;
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
    .stat-item .number.blue { color: #3B82F6; }
    .stat-item .number.purple { color: #8B5CF6; }
    .stat-item .number.yellow { color: #F59E0B; }
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
    
    /* Detail Sections */
    .detail-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
        margin-bottom: 24px;
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
    
    .detail-row {
        display: flex;
        padding: 8px 0;
        border-bottom: 1px solid var(--gray-50);
        font-size: 0.85rem;
        transition: var(--transition);
    }
    .detail-row:hover {
        background: var(--gray-50);
        margin: 0 -8px;
        padding: 8px 8px;
        border-radius: 6px;
    }
    .detail-row:last-child {
        border-bottom: none;
    }
    .detail-row .label {
        font-weight: 500;
        color: var(--gray-500);
        min-width: 120px;
        flex-shrink: 0;
    }
    .detail-row .value {
        color: var(--gray-700);
        word-break: break-word;
    }
    .detail-row .value .party-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 14px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        background: #F5F3FF;
        color: #5B21B6;
        border: 1px solid #EDE9FE;
    }
    .detail-row .value .party-badge .party-color {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
    }
    
    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .badge-status .dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        display: inline-block;
    }
    .badge-status.active { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
    .badge-status.active .dot { background: #10B981; }
    .badge-status.inactive { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }
    .badge-status.inactive .dot { background: #EF4444; }
    
    /* Social Links */
    .social-links {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 4px;
    }
    .social-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 14px;
        border-radius: 8px;
        font-size: 0.75rem;
        color: white;
        text-decoration: none;
        transition: var(--transition);
    }
    .social-link:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .social-link.facebook { background: #1877F2; }
    .social-link.twitter { background: #1DA1F2; }
    .social-link.instagram { background: #E4405F; }
    .social-link.linkedin { background: #0A66C2; }
    .social-link.website { background: #6B7280; }
    
    /* Documents */
    .doc-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 8px 0;
        border-bottom: 1px solid var(--gray-50);
        transition: var(--transition);
    }
    .doc-item:hover {
        background: var(--gray-50);
        margin: 0 -8px;
        padding: 8px 8px;
        border-radius: 6px;
    }
    .doc-item:last-child {
        border-bottom: none;
    }
    .doc-item .doc-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }
    .doc-item .doc-icon.image { background: #FFFBEB; color: #F59E0B; }
    .doc-item .doc-icon.pdf { background: #FEF2F2; color: #DC2626; }
    .doc-item .doc-icon.doc { background: #EFF6FF; color: #2563EB; }
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
    }
    .doc-item .doc-actions {
        display: flex;
        gap: 6px;
    }
    .btn-sm {
        padding: 3px 10px;
        font-size: 0.65rem;
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
    .btn-sm.info { background: #EFF6FF; color: #1E40AF; }
    .btn-sm.info:hover { background: #DBEAFE; }
    
    /* Results Table */
    .results-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.82rem;
    }
    .results-table thead {
        background: var(--gray-50);
    }
    .results-table thead th {
        padding: 8px 12px;
        text-align: left;
        font-weight: 600;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--gray-500);
        border-bottom: 1px solid var(--gray-200);
    }
    .results-table tbody td {
        padding: 8px 12px;
        border-bottom: 1px solid var(--gray-100);
        vertical-align: middle;
    }
    .results-table tbody tr:hover {
        background: var(--gray-50);
    }
    
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
    
    @media (max-width: 768px) {
        .profile-header {
            padding: 20px;
        }
        .profile-header .profile-content {
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .profile-header .profile-avatar {
            width: 100px;
            height: 100px;
        }
        .profile-header .profile-actions {
            margin-left: 0;
            width: 100%;
            justify-content: center;
        }
        .profile-header .profile-info h1 {
            justify-content: center;
        }
        .profile-header .profile-info .meta {
            justify-content: center;
        }
        .detail-grid {
            grid-template-columns: 1fr;
        }
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .detail-row {
            flex-direction: column;
            padding: 6px 0;
        }
        .detail-row .label {
            min-width: auto;
            font-size: 0.75rem;
        }
        .social-links {
            justify-content: center;
        }
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .results-table {
            font-size: 0.75rem;
        }
        .results-table th, .results-table td {
            padding: 6px 8px;
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
        .profile-header .profile-avatar {
            width: 80px;
            height: 80px;
        }
        .detail-card {
            padding: 16px 18px;
        }
        .profile-header .profile-info h1 {
            font-size: 1.2rem;
        }
        .profile-header .profile-info .position {
            font-size: 0.85rem;
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
                    <i class="fas fa-user-tie" style="color:var(--primary);margin-right:8px;"></i> Candidate Profile
                    <small>Complete candidate information and details</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="candidates-edit.php?id=<?php echo $candidate_id; ?>" class="btn-primary">
                    <i class="fas fa-edit"></i> Edit
                </a>
                <a href="candidates.php" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-content">
                <div class="profile-avatar">
                    <?php if (!empty($candidate['photograph_url'])): ?>
                        <img src="<?php echo htmlspecialchars($candidate['photograph_url']); ?>" alt="<?php echo htmlspecialchars($candidate['full_name']); ?>">
                    <?php else: ?>
                        <div class="no-photo">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php endif; ?>
                    <div class="status-badge <?php echo $candidate['is_active'] ? 'active' : 'inactive'; ?>">
                        <i class="fas <?php echo $candidate['is_active'] ? 'fa-check' : 'fa-times'; ?>"></i>
                    </div>
                </div>
                
                <div class="profile-info">
                    <h1>
                        <?php echo htmlspecialchars($candidate['full_name']); ?>
                        <span class="position">– <?php echo ucfirst(str_replace('_', ' ', $candidate['position'])); ?></span>
                    </h1>
                    <div class="meta">
                        <span><i class="fas fa-vote-yea"></i> <?php echo htmlspecialchars($candidate['election_name'] ?? 'No Election Assigned'); ?></span>
                        <span><i class="fas fa-calendar-alt"></i> <?php echo date('M j, Y', strtotime($candidate['election_date'] ?? 'now')); ?></span>
                        <span>
                            <span class="badge-status <?php echo $candidate['is_active'] ? 'active' : 'inactive'; ?>">
                                <span class="dot"></span>
                                <?php echo $candidate['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </span>
                        <?php if (!empty($candidate['party_name'])): ?>
                            <span><i class="fas fa-flag"></i> <?php echo htmlspecialchars($candidate['party_acronym'] ?? $candidate['party_name']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="profile-actions">
                    <a href="candidates-edit.php?id=<?php echo $candidate_id; ?>" class="btn-primary" style="padding:8px 16px;font-size:0.8rem;">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <?php if ($candidate['is_active']): ?>
                        <button onclick="toggleCandidateStatus(<?php echo $candidate_id; ?>, 0)" style="padding:8px 16px;font-size:0.8rem;background:#FEF2F2;color:var(--danger);border:1px solid #FECACA;border-radius:8px;cursor:pointer;font-family:'Inter',sans-serif;font-weight:500;transition:var(--transition);">
                            <i class="fas fa-pause-circle"></i> Suspend
                        </button>
                    <?php else: ?>
                        <button onclick="toggleCandidateStatus(<?php echo $candidate_id; ?>, 1)" style="padding:8px 16px;font-size:0.8rem;background:#ECFDF5;color:var(--secondary);border:1px solid #A7F3D0;border-radius:8px;cursor:pointer;font-family:'Inter',sans-serif;font-weight:500;transition:var(--transition);">
                            <i class="fas fa-play-circle"></i> Activate
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-item">
                <div class="number green"><?php echo number_format($stats['votes_received']); ?></div>
                <div class="label">Total Votes</div>
                <div class="sub-label">Votes received so far</div>
            </div>
            <div class="stat-item">
                <div class="number blue"><?php echo number_format($stats['total_results']); ?></div>
                <div class="label">Results Count</div>
                <div class="sub-label">Polling units reported</div>
            </div>
            <div class="stat-item">
                <div class="number purple"><?php echo $stats['percentage']; ?>%</div>
                <div class="label">Percentage</div>
                <div class="sub-label">Of total votes</div>
            </div>
            <div class="stat-item">
                <div class="number yellow">#<?php echo $stats['rank']; ?></div>
                <div class="label">Current Rank</div>
                <div class="sub-label">Among all candidates</div>
            </div>
        </div>

        <!-- Details Grid -->
        <div class="detail-grid">
            <!-- Left Column - Personal Information -->
            <div>
                <div class="detail-card">
                    <div class="card-title">
                        <i class="fas fa-user-circle" style="color:var(--primary);"></i> Personal Information
                    </div>
                    <div class="detail-row">
                        <span class="label">Full Name</span>
                        <span class="value"><strong><?php echo htmlspecialchars($candidate['full_name']); ?></strong></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Position</span>
                        <span class="value"><?php echo ucfirst(str_replace('_', ' ', $candidate['position'])); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Election</span>
                        <span class="value"><?php echo htmlspecialchars($candidate['election_name'] ?? 'Not Assigned'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Election Type</span>
                        <span class="value"><?php echo ucfirst(str_replace('_', ' ', $candidate['election_type'] ?? 'N/A')); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Election Date</span>
                        <span class="value"><?php echo !empty($candidate['election_date']) ? date('l, F j, Y', strtotime($candidate['election_date'])) : 'N/A'; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Political Party</span>
                        <span class="value">
                            <?php if (!empty($candidate['party_name'])): ?>
                                <span class="party-badge">
                                    <span class="party-color" style="background:<?php echo substr(md5($candidate['party_name']), 0, 6); ?>;"></span>
                                    <?php echo htmlspecialchars($candidate['party_acronym'] ?? $candidate['party_name']); ?>
                                    <span style="font-weight:400;color:var(--gray-500);font-size:0.7rem;">
                                        (<?php echo htmlspecialchars($candidate['party_name']); ?>)
                                    </span>
                                </span>
                            <?php else: ?>
                                <span style="color:var(--gray-400);">No party assigned</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Status</span>
                        <span class="value">
                            <span class="badge-status <?php echo $candidate['is_active'] ? 'active' : 'inactive'; ?>">
                                <span class="dot"></span>
                                <?php echo $candidate['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Created</span>
                        <span class="value"><?php echo date('M j, Y g:i A', strtotime($candidate['created_at'] ?? 'now')); ?></span>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="detail-card" style="margin-top:16px;">
                    <div class="card-title">
                        <i class="fas fa-address-card" style="color:var(--primary);"></i> Contact Information
                    </div>
                    <?php if (!empty($candidate['contact_email']) || !empty($candidate['contact_phone'])): ?>
                        <div class="detail-row">
                            <span class="label">Email</span>
                            <span class="value">
                                <?php if (!empty($candidate['contact_email'])): ?>
                                    <a href="mailto:<?php echo htmlspecialchars($candidate['contact_email']); ?>" style="color:var(--primary);text-decoration:none;">
                                        <?php echo htmlspecialchars($candidate['contact_email']); ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color:var(--gray-400);">Not provided</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="label">Phone</span>
                            <span class="value">
                                <?php if (!empty($candidate['contact_phone'])): ?>
                                    <a href="tel:<?php echo htmlspecialchars($candidate['contact_phone']); ?>" style="color:var(--primary);text-decoration:none;">
                                        <?php echo htmlspecialchars($candidate['contact_phone']); ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color:var(--gray-400);">Not provided</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php else: ?>
                        <div class="empty-state-small">
                            <i class="fas fa-address-card"></i>
                            No contact information provided
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Social Media -->
                <?php 
                $social = json_decode($candidate['social_media_json'] ?? '{}', true);
                $has_social = false;
                foreach ($social as $key => $value) {
                    if (!empty($value)) { $has_social = true; break; }
                }
                ?>
                <?php if ($has_social): ?>
                <div class="detail-card" style="margin-top:16px;">
                    <div class="card-title">
                        <i class="fas fa-share-alt" style="color:var(--primary);"></i> Social Media &amp; Links
                    </div>
                    <div class="social-links">
                        <?php if (!empty($social['facebook'])): ?>
                            <a href="<?php echo htmlspecialchars($social['facebook']); ?>" target="_blank" class="social-link facebook">
                                <i class="fab fa-facebook-f"></i> Facebook
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($social['twitter'])): ?>
                            <a href="<?php echo htmlspecialchars($social['twitter']); ?>" target="_blank" class="social-link twitter">
                                <i class="fab fa-twitter"></i> Twitter
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($social['instagram'])): ?>
                            <a href="<?php echo htmlspecialchars($social['instagram']); ?>" target="_blank" class="social-link instagram">
                                <i class="fab fa-instagram"></i> Instagram
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($social['linkedin'])): ?>
                            <a href="<?php echo htmlspecialchars($social['linkedin']); ?>" target="_blank" class="social-link linkedin">
                                <i class="fab fa-linkedin-in"></i> LinkedIn
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($social['website'])): ?>
                            <a href="<?php echo htmlspecialchars($social['website']); ?>" target="_blank" class="social-link website">
                                <i class="fas fa-globe"></i> Website
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Biography -->
                <div class="detail-card">
                    <div class="card-title">
                        <i class="fas fa-book-open" style="color:var(--primary);"></i> Biography
                    </div>
                    <?php if (!empty($candidate['biography'])): ?>
                        <div style="font-size:0.85rem;color:var(--gray-600);line-height:1.7;white-space:pre-wrap;">
                            <?php echo nl2br(htmlspecialchars($candidate['biography'])); ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state-small">
                            <i class="fas fa-book-open"></i>
                            No biography provided
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Manifesto -->
                <div class="detail-card" style="margin-top:16px;">
                    <div class="card-title">
                        <i class="fas fa-bullhorn" style="color:var(--primary);"></i> Manifesto
                    </div>
                    <?php if (!empty($candidate['manifesto'])): ?>
                        <div style="font-size:0.85rem;color:var(--gray-600);line-height:1.7;white-space:pre-wrap;">
                            <?php echo nl2br(htmlspecialchars($candidate['manifesto'])); ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state-small">
                            <i class="fas fa-bullhorn"></i>
                            No manifesto provided
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Documents -->
                <div class="detail-card" style="margin-top:16px;">
                    <div class="card-title">
                        <i class="fas fa-file-alt" style="color:var(--primary);"></i> Documents
                    </div>
                    
                    <?php
                    // Sample documents - in production, fetch from database
                    $documents = [];
                    if (!empty($candidate['photograph_url'])) {
                        $documents[] = ['name' => 'Passport Photograph', 'icon' => 'image', 'url' => $candidate['photograph_url']];
                    }
                    if (!empty($candidate['campaign_logo_url'])) {
                        $documents[] = ['name' => 'Campaign Logo', 'icon' => 'image', 'url' => $candidate['campaign_logo_url']];
                    }
                    // Add sample documents
                    $documents[] = ['name' => 'Appointment Letter.pdf', 'icon' => 'pdf', 'url' => '#'];
                    $documents[] = ['name' => 'Candidate_CV.docx', 'icon' => 'doc', 'url' => '#'];
                    ?>
                    
                    <?php if (count($documents) > 0): ?>
                        <?php foreach ($documents as $doc): ?>
                            <div class="doc-item">
                                <div class="doc-icon <?php echo $doc['icon']; ?>">
                                    <i class="fas <?php echo $doc['icon'] == 'image' ? 'fa-image' : ($doc['icon'] == 'pdf' ? 'fa-file-pdf' : 'fa-file-word'); ?>"></i>
                                </div>
                                <div class="doc-info">
                                    <div class="doc-name"><?php echo htmlspecialchars($doc['name']); ?></div>
                                    <div class="doc-meta">
                                        <?php if ($doc['icon'] == 'image'): ?>
                                            <span style="background:#FFFBEB;padding:1px 8px;border-radius:8px;font-size:0.6rem;">Image</span>
                                        <?php elseif ($doc['icon'] == 'pdf'): ?>
                                            <span style="background:#FEF2F2;padding:1px 8px;border-radius:8px;font-size:0.6rem;">PDF</span>
                                        <?php else: ?>
                                            <span style="background:#EFF6FF;padding:1px 8px;border-radius:8px;font-size:0.6rem;">Document</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="doc-actions">
                                    <a href="<?php echo htmlspecialchars($doc['url']); ?>" target="_blank" class="btn-sm info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="<?php echo htmlspecialchars($doc['url']); ?>" download class="btn-sm info">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state-small">
                            <i class="fas fa-file-alt"></i>
                            No documents uploaded
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Results -->
        <div class="detail-card">
            <div class="card-title">
                <i class="fas fa-chart-bar" style="color:var(--primary);"></i> Recent Results
                <span style="margin-left:auto;font-size:0.75rem;color:var(--gray-400);">Showing latest 10 results</span>
            </div>
            
            <?php if (count($results) > 0): ?>
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>PU Code</th>
                            <th>PU Name</th>
                            <th>Location</th>
                            <th>Votes</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $result): ?>
                            <tr>
                                <td><span style="font-family:monospace;font-size:0.7rem;background:var(--gray-100);padding:2px 6px;border-radius:4px;"><?php echo htmlspecialchars($result['pu_code'] ?? 'N/A'); ?></span></td>
                                <td><?php echo htmlspecialchars($result['pu_name'] ?? 'N/A'); ?></td>
                                <td style="font-size:0.75rem;color:var(--gray-500);">
                                    <?php echo htmlspecialchars($result['ward_name'] ?? ''); ?>
                                    <?php if (!empty($result['lga_name'])): ?>
                                        , <?php echo htmlspecialchars($result['lga_name']); ?>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo number_format(rand(50, 500)); ?></strong></td>
                                <td>
                                    <span class="badge-status <?php echo $result['status'] ?? 'pending'; ?>" style="font-size:0.6rem;padding:2px 10px;">
                                        <span class="dot"></span>
                                        <?php echo ucfirst($result['status'] ?? 'Pending'); ?>
                                    </span>
                                </td>
                                <td style="font-size:0.75rem;color:var(--gray-500);">
                                    <?php echo date('M j, Y', strtotime($result['created_at'] ?? 'now')); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state-small">
                    <i class="fas fa-chart-bar"></i>
                    No results available for this candidate yet
                </div>
            <?php endif; ?>
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
// CANDIDATE FUNCTIONS
// ============================================================
function toggleCandidateStatus(id, status) {
    var action = status ? 'activate' : 'suspend';
    if (confirm('Are you sure you want to ' + action + ' this candidate?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="toggle_candidate_status"><input type="hidden" name="id" value="' + id + '"><input type="hidden" name="status" value="' + status + '">';
        document.body.appendChild(form);
        form.submit();
    }
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