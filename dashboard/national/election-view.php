<?php
// ============================================================
// NATIONAL COORDINATOR - VIEW ELECTION DETAILS (FIXED)
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Start session
SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

// Only national coordinator can access
if (SessionManager::get('role_level') !== 'national') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');

// Get election ID
$election_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($election_id <= 0) {
    header('Location: elections.php?error=invalid_election');
    exit();
}

$db = getDB();

// ============================================================
// FETCH ELECTION DATA
// ============================================================
$election = null;
$stats = [
    'total_pus' => 0,
    'total_results' => 0,
    'verified_results' => 0,
    'pending_results' => 0,
    'flagged_results' => 0,
    'total_incidents' => 0,
    'reporting_percent' => 0
];

try {
    // Fix: Check if tenant_id is set, if not, show all elections for national
    if (!empty($tenant_id)) {
        $stmt = $db->prepare("
            SELECT 
                e.*,
                u.full_name as created_by_name,
                u2.full_name as updated_by_name
            FROM elections e
            LEFT JOIN users u ON e.created_by = u.id
            LEFT JOIN users u2 ON e.updated_by = u2.id
            WHERE e.id = ? AND e.tenant_id = ?
        ");
        $stmt->execute([$election_id, $tenant_id]);
    } else {
        // National coordinator with no tenant - show all elections
        $stmt = $db->prepare("
            SELECT 
                e.*,
                u.full_name as created_by_name,
                u2.full_name as updated_by_name
            FROM elections e
            LEFT JOIN users u ON e.created_by = u.id
            LEFT JOIN users u2 ON e.updated_by = u2.id
            WHERE e.id = ?
        ");
        $stmt->execute([$election_id]);
    }
    $election = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$election) {
        header('Location: elections.php?error=election_not_found');
        exit();
    }
    
    // Get location IDs
    $state_ids = json_decode($election['states_json'] ?? '[]', true);
    $lga_ids = json_decode($election['lgas_json'] ?? '[]', true);
    $ward_ids = json_decode($election['wards_json'] ?? '[]', true);
    $pu_ids = json_decode($election['pus_json'] ?? '[]', true);
    
    // Get total PUs
    $query = "SELECT COUNT(*) as count FROM polling_units pu JOIN wards w ON pu.ward_id = w.id JOIN lgas l ON w.lga_id = l.id WHERE 1=1";
    $params = [];
    
    if (!empty($pu_ids)) {
        $placeholders = implode(',', array_fill(0, count($pu_ids), '?'));
        $query .= " AND pu.id IN ($placeholders)";
        $params = $pu_ids;
    } elseif (!empty($ward_ids)) {
        $placeholders = implode(',', array_fill(0, count($ward_ids), '?'));
        $query .= " AND pu.ward_id IN ($placeholders)";
        $params = $ward_ids;
    } elseif (!empty($lga_ids)) {
        $placeholders = implode(',', array_fill(0, count($lga_ids), '?'));
        $query .= " AND w.lga_id IN ($placeholders)";
        $params = $lga_ids;
    } elseif (!empty($state_ids)) {
        $placeholders = implode(',', array_fill(0, count($state_ids), '?'));
        $query .= " AND l.state_id IN ($placeholders)";
        $params = $state_ids;
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $stats['total_pus'] = $stmt->fetchColumn() ?: 0;
    
    // Get results stats - Check if table exists first
    try {
        $table_check = $db->query("SHOW TABLES LIKE 'results_ec8a'");
        if ($table_check->rowCount() > 0) {
            $query = "SELECT COUNT(*) as total, 
                             SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified, 
                             SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending, 
                             SUM(CASE WHEN status = 'flagged' THEN 1 ELSE 0 END) as flagged 
                      FROM results_ec8a 
                      WHERE tenant_id = ? AND election_id = ?";
            $params = [$tenant_id, $election_id];
            
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stats['total_results'] = $result['total'] ?? 0;
            $stats['verified_results'] = $result['verified'] ?? 0;
            $stats['pending_results'] = $result['pending'] ?? 0;
            $stats['flagged_results'] = $result['flagged'] ?? 0;
        }
    } catch (Exception $e) {
        // Table doesn't exist or error - continue with default values
        error_log("Results table error: " . $e->getMessage());
    }
    
    // Get incidents - Check if table exists first
    try {
        $table_check = $db->query("SHOW TABLES LIKE 'incidents'");
        if ($table_check->rowCount() > 0) {
            $query = "SELECT COUNT(*) as count FROM incidents WHERE tenant_id = ?";
            $params = [$tenant_id];
            
            if (!empty($state_ids)) {
                $placeholders = implode(',', array_fill(0, count($state_ids), '?'));
                $query .= " AND state_id IN ($placeholders)";
                $params = array_merge($params, $state_ids);
            }
            
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $stats['total_incidents'] = $stmt->fetchColumn() ?: 0;
        }
    } catch (Exception $e) {
        // Table doesn't exist or error - continue with default values
        error_log("Incidents table error: " . $e->getMessage());
    }
    
    // Calculate reporting percentage
    $stats['reporting_percent'] = $stats['total_pus'] > 0 ? round(($stats['total_results'] / $stats['total_pus']) * 100) : 0;
    
} catch (PDOException $e) {
    error_log("Election View PDO Error: " . $e->getMessage());
    header('Location: elections.php?error=database_error');
    exit();
} catch (Exception $e) {
    error_log("Election View Error: " . $e->getMessage());
    header('Location: elections.php?error=database_error');
    exit();
}

// ============================================================
// ELECTION TYPES AND STATUSES
// ============================================================
$election_types = [
    'presidential' => 'Presidential',
    'governorship' => 'Governorship',
    'senatorial' => 'Senatorial',
    'house_of_reps' => 'House of Reps',
    'house_of_assembly' => 'House of Assembly',
    'lga_chairman' => 'LGA Chairman',
    'councillorship' => 'Councillorship',
    'party_primary' => 'Party Primary',
    'internal_party' => 'Internal Party'
];

$status_colors = [
    'draft' => '#6B7280',
    'upcoming' => '#3B82F6',
    'active' => '#10B981',
    'closed' => '#6B7280',
    'cancelled' => '#EF4444',
    'archived' => '#6B7280'
];

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'Election Details';
$page_subtitle = $election['name'] ?? 'Election';
?>

<!-- HTML remains the same as your original file -->
<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Breadcrumb -->
        <div class="welcome-section">
            <div class="breadcrumb">
                <i class="fas fa-home"></i>
                <a href="../national/index.php" style="text-decoration:none;color:var(--gray-500);">Dashboard</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <a href="elections.php" style="text-decoration:none;color:var(--gray-500);">Elections</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="font-weight:600;color:var(--gray-800);"><?php echo htmlspecialchars($election['name']); ?></span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        <?php echo htmlspecialchars($election['name']); ?>
                        <span style="font-size:0.7rem;background:<?php echo $status_colors[$election['status']] ?? '#6B7280'; ?>;color:white;padding:2px 12px;border-radius:20px;font-weight:500;margin-left:8px;">
                            <?php echo ucfirst($election['status']); ?>
                        </span>
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <i class="fas fa-tag"></i> 
                        <?php echo $election_types[$election['type']] ?? ucfirst($election['type']); ?>
                        <?php if ($election['cycle']): ?>
                            • <i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($election['cycle']); ?>
                        <?php endif; ?>
                        <?php if ($election['election_date']): ?>
                            • <i class="fas fa-calendar-check"></i> <?php echo date('F j, Y', strtotime($election['election_date'])); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="elections-edit.php?id=<?php echo $election_id; ?>" class="btn-primary" style="padding:8px 20px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <a href="election-progress.php?id=<?php echo $election_id; ?>" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-chart-line"></i> Progress
                    </a>
                    <a href="elections.php" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-flag-checkered"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total_pus']); ?></div>
                <div class="stat-label">Polling Units</div>
                <div class="stat-change">Total coverage</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['verified_results']); ?></div>
                <div class="stat-label">Verified Results</div>
                <div class="stat-change up"><i class="fas fa-check"></i> Approved</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo number_format($stats['pending_results']); ?></div>
                <div class="stat-label">Pending Results</div>
                <div class="stat-change down"><i class="fas fa-hourglass-half"></i> Awaiting</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-flag"></i></div>
                <div class="stat-number"><?php echo number_format($stats['flagged_results']); ?></div>
                <div class="stat-label">Flagged Results</div>
                <div class="stat-change down"><i class="fas fa-exclamation-triangle"></i> Review</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total_incidents']); ?></div>
                <div class="stat-label">Incidents</div>
                <div class="stat-change down"><i class="fas fa-clock"></i> Total</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon teal"><i class="fas fa-chart-line"></i></div>
                <div class="stat-number"><?php echo $stats['reporting_percent']; ?>%</div>
                <div class="stat-label">Reporting Rate</div>
                <div class="stat-change <?php echo $stats['reporting_percent'] >= 80 ? 'up' : 'down'; ?>">
                    <?php echo $stats['reporting_percent'] >= 80 ? 'Good' : 'Needs improvement'; ?>
                </div>
            </div>
        </div>

        <!-- Election Details -->
        <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px;">
            <!-- Left Column -->
            <div style="background:white;border-radius:var(--radius);padding:20px;border:1px solid var(--gray-200);">
                <h4 style="font-size:0.9rem;font-weight:600;margin:0 0 16px;padding-bottom:12px;border-bottom:1px solid var(--gray-200);">
                    <i class="fas fa-info-circle" style="color:var(--primary);margin-right:6px;"></i>
                    Election Information
                </h4>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <label style="display:block;font-size:0.65rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.03em;">Election Name</label>
                        <div style="font-weight:500;"><?php echo htmlspecialchars($election['name']); ?></div>
                    </div>
                    <div>
                        <label style="display:block;font-size:0.65rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.03em;">Type</label>
                        <div style="font-weight:500;"><?php echo $election_types[$election['type']] ?? ucfirst($election['type']); ?></div>
                    </div>
                    <div>
                        <label style="display:block;font-size:0.65rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.03em;">Cycle</label>
                        <div style="font-weight:500;"><?php echo htmlspecialchars($election['cycle'] ?? 'N/A'); ?></div>
                    </div>
                    <div>
                        <label style="display:block;font-size:0.65rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.03em;">Status</label>
                        <div>
                            <span style="display:inline-block;padding:2px 12px;border-radius:12px;font-size:0.7rem;font-weight:600;background:<?php echo $status_colors[$election['status']] ?? '#6B7280'; ?>20;color:<?php echo $status_colors[$election['status']] ?? '#6B7280'; ?>;">
                                <?php echo ucfirst($election['status']); ?>
                            </span>
                        </div>
                    </div>
                    <div>
                        <label style="display:block;font-size:0.65rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.03em;">Election Date</label>
                        <div style="font-weight:500;"><?php echo $election['election_date'] ? date('F j, Y', strtotime($election['election_date'])) : 'Not set'; ?></div>
                    </div>
                    <div>
                        <label style="display:block;font-size:0.65rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.03em;">Time</label>
                        <div style="font-weight:500;">
                            <?php if ($election['start_time'] && $election['end_time']): ?>
                                <?php echo date('g:i A', strtotime($election['start_time'])); ?> - <?php echo date('g:i A', strtotime($election['end_time'])); ?>
                            <?php else: ?>
                                Not set
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="grid-column:1/-1;">
                        <label style="display:block;font-size:0.65rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.03em;">Description</label>
                        <div style="font-weight:500;font-size:0.85rem;color:var(--gray-600);">
                            <?php echo !empty($election['description']) ? htmlspecialchars($election['description']) : 'No description provided'; ?>
                        </div>
                    </div>
                    <div>
                        <label style="display:block;font-size:0.65rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.03em;">Created By</label>
                        <div style="font-weight:500;font-size:0.85rem;"><?php echo htmlspecialchars($election['created_by_name'] ?? 'System'); ?></div>
                        <div style="font-size:0.6rem;color:var(--gray-400);"><?php echo date('M j, Y g:i A', strtotime($election['created_at'])); ?></div>
                    </div>
                    <div>
                        <label style="display:block;font-size:0.65rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.03em;">Last Updated</label>
                        <div style="font-weight:500;font-size:0.85rem;"><?php echo $election['updated_by_name'] ? htmlspecialchars($election['updated_by_name']) : '—'; ?></div>
                        <div style="font-size:0.6rem;color:var(--gray-400);"><?php echo date('M j, Y g:i A', strtotime($election['updated_at'])); ?></div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div style="display:flex;flex-direction:column;gap:20px;">
                <!-- Quick Actions -->
                <div style="background:white;border-radius:var(--radius);padding:20px;border:1px solid var(--gray-200);">
                    <h4 style="font-size:0.9rem;font-weight:600;margin:0 0 12px;padding-bottom:12px;border-bottom:1px solid var(--gray-200);">
                        <i class="fas fa-bolt" style="color:var(--warning);margin-right:6px;"></i>
                        Quick Actions
                    </h4>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                        <a href="election-progress.php?id=<?php echo $election_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:6px;padding:8px 12px;background:var(--gray-50);border-radius:6px;border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;font-size:0.75rem;transition:var(--transition);">
                            <i class="fas fa-chart-line" style="color:var(--primary);"></i>
                            Progress
                        </a>
                        <a href="live-results.php?id=<?php echo $election_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:6px;padding:8px 12px;background:var(--gray-50);border-radius:6px;border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;font-size:0.75rem;transition:var(--transition);">
                            <i class="fas fa-broadcast-tower" style="color:var(--danger);"></i>
                            Live Results
                        </a>
                        <a href="result-verification.php?election=<?php echo $election_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:6px;padding:8px 12px;background:var(--gray-50);border-radius:6px;border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;font-size:0.75rem;transition:var(--transition);">
                            <i class="fas fa-check-double" style="color:var(--secondary);"></i>
                            Verify Results
                        </a>
                        <a href="reports.php?type=election&id=<?php echo $election_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:6px;padding:8px 12px;background:var(--gray-50);border-radius:6px;border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;font-size:0.75rem;transition:var(--transition);">
                            <i class="fas fa-file-pdf" style="color:var(--danger);"></i>
                            Report
                        </a>
                        <a href="elections-create.php?template=<?php echo $election_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:6px;padding:8px 12px;background:var(--gray-50);border-radius:6px;border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;font-size:0.75rem;transition:var(--transition);">
                            <i class="fas fa-copy" style="color:var(--purple);"></i>
                            Use as Template
                        </a>
                        <a href="analytics.php?election=<?php echo $election_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:6px;padding:8px 12px;background:var(--gray-50);border-radius:6px;border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;font-size:0.75rem;transition:var(--transition);">
                            <i class="fas fa-chart-pie" style="color:var(--primary);"></i>
                            Analytics
                        </a>
                    </div>
                </div>

                <!-- Locations Summary -->
                <div style="background:white;border-radius:var(--radius);padding:20px;border:1px solid var(--gray-200);">
                    <h4 style="font-size:0.9rem;font-weight:600;margin:0 0 12px;padding-bottom:12px;border-bottom:1px solid var(--gray-200);">
                        <i class="fas fa-map-marker-alt" style="color:var(--secondary);margin-right:6px;"></i>
                        Locations
                    </h4>
                    <?php
                    $state_count = !empty($election['states_json']) ? count(json_decode($election['states_json'], true) ?: []) : 0;
                    $lga_count = !empty($election['lgas_json']) ? count(json_decode($election['lgas_json'], true) ?: []) : 0;
                    $ward_count = !empty($election['wards_json']) ? count(json_decode($election['wards_json'], true) ?: []) : 0;
                    $pu_count = !empty($election['pus_json']) ? count(json_decode($election['pus_json'], true) ?: []) : 0;
                    ?>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                        <div style="text-align:center;padding:8px;background:var(--gray-50);border-radius:6px;">
                            <div style="font-size:1.2rem;font-weight:700;color:var(--primary);"><?php echo $state_count; ?></div>
                            <div style="font-size:0.65rem;color:var(--gray-500);">States</div>
                        </div>
                        <div style="text-align:center;padding:8px;background:var(--gray-50);border-radius:6px;">
                            <div style="font-size:1.2rem;font-weight:700;color:var(--secondary);"><?php echo $lga_count; ?></div>
                            <div style="font-size:0.65rem;color:var(--gray-500);">LGAs</div>
                        </div>
                        <div style="text-align:center;padding:8px;background:var(--gray-50);border-radius:6px;">
                            <div style="font-size:1.2rem;font-weight:700;color:var(--warning);"><?php echo $ward_count; ?></div>
                            <div style="font-size:0.65rem;color:var(--gray-500);">Wards</div>
                        </div>
                        <div style="text-align:center;padding:8px;background:var(--gray-50);border-radius:6px;">
                            <div style="font-size:1.2rem;font-weight:700;color:var(--danger);"><?php echo $pu_count; ?></div>
                            <div style="font-size:0.65rem;color:var(--gray-500);">Polling Units</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
.quick-action-btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-hover);
    border-color: var(--primary);
}
.stat-icon.teal { background: #CCFBF1; color: #0D9488; }
.btn-secondary:hover { background: var(--gray-200); transform: translateY(-1px); }
.btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3); }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    div[style*="grid-template-columns:2fr 1fr;gap:20px;"] { grid-template-columns: 1fr !important; }
}
</style>

<script>
// ============================================================
// SIDEBAR TOGGLE, DROPDOWNS, PROFILE, SEARCH
// ============================================================
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});

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
</script>
</body>
</html>