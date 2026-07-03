<?php
// ============================================================
// INCIDENT EXPORT - CLIENT ADMIN (PROFESSIONAL UI)
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
// FETCH ELECTIONS FOR FILTER
// ============================================================
$elections = [];
try {
    $stmt = $db->prepare("SELECT id, name, type, status, election_date FROM elections WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY election_date DESC");
    $stmt->execute([$tenant_id]);
    $elections = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH STATES FOR FILTER
// ============================================================
$states = [];
try {
    $stmt = $db->query("SELECT id, name, code FROM states WHERE is_active = 1 ORDER BY name");
    $states = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// HANDLE EXPORT ACTION
// ============================================================
$action_result = ['success' => false, 'message' => ''];
$export_data = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'export_incidents':
                $format = $_POST['format'] ?? 'pdf';
                $election_filter = (int)($_POST['election_id'] ?? 0);
                $state_filter = (int)($_POST['state_id'] ?? 0);
                $severity_filter = $_POST['severity'] ?? '';
                $status_filter = $_POST['status'] ?? '';
                $date_from = $_POST['date_from'] ?? '';
                $date_to = $_POST['date_to'] ?? '';
                
                $where_conditions = ["i.tenant_id = ?"];
                $params = [$tenant_id];
                
                if ($election_filter > 0) {
                    $where_conditions[] = "i.election_id = ?";
                    $params[] = $election_filter;
                }
                if ($state_filter > 0) {
                    $where_conditions[] = "i.state_id = ?";
                    $params[] = $state_filter;
                }
                if (!empty($severity_filter)) {
                    $where_conditions[] = "i.severity = ?";
                    $params[] = $severity_filter;
                }
                if (!empty($status_filter)) {
                    $where_conditions[] = "i.status = ?";
                    $params[] = $status_filter;
                }
                if (!empty($date_from)) {
                    $where_conditions[] = "DATE(i.created_at) >= ?";
                    $params[] = $date_from;
                }
                if (!empty($date_to)) {
                    $where_conditions[] = "DATE(i.created_at) <= ?";
                    $params[] = $date_to;
                }
                
                $where_clause = "WHERE " . implode(" AND ", $where_conditions);
                
                $sql = "
                    SELECT i.*, 
                           r.full_name as reporter_name,
                           a.full_name as assigned_to_name,
                           e.name as election_name,
                           s.name as state_name,
                           l.name as lga_name,
                           w.name as ward_name,
                           pu.name as pu_name
                    FROM incidents i
                    LEFT JOIN users r ON i.reporter_id = r.id
                    LEFT JOIN users a ON i.assigned_to = a.id
                    LEFT JOIN elections e ON i.election_id = e.id
                    LEFT JOIN states s ON i.state_id = s.id
                    LEFT JOIN lgas l ON i.lga_id = l.id
                    LEFT JOIN wards w ON i.ward_id = w.id
                    LEFT JOIN polling_units pu ON i.pu_id = pu.id
                    $where_clause
                    ORDER BY i.created_at DESC
                ";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $incidents = $stmt->fetchAll();
                
                if (empty($incidents)) {
                    throw new Exception('No incidents found matching the criteria.');
                }
                
                // Generate filename
                $filename = "incident_report_" . date('Y-m-d_H-i');
                $action_result = ['success' => true, 'message' => 'Export generated successfully.', 'filename' => $filename . '.' . $format, 'format' => $format, 'count' => count($incidents)];
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
       INCIDENT EXPORT - PROFESSIONAL UI STYLES
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
    .btn-secondary {
        padding: 10px 20px;
        background: var(--gray-100);
        color: var(--gray-600);
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
    .btn-secondary:hover {
        background: var(--gray-200);
    }
    
    .export-container {
        max-width: 900px;
        margin: 0 auto;
    }
    
    .export-card {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 28px 32px;
        box-shadow: var(--shadow);
        transition: var(--transition);
        margin-bottom: 20px;
    }
    .export-card:hover {
        box-shadow: var(--shadow-hover);
    }
    .export-card .card-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 2px solid var(--gray-100);
    }
    .export-card .card-header .icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
    }
    .export-card .card-header .icon.primary { background: #EFF6FF; color: var(--primary); }
    .export-card .card-header .icon.success { background: #ECFDF5; color: var(--secondary); }
    .export-card .card-header .icon.warning { background: #FFFBEB; color: #F59E0B; }
    .export-card .card-header h3 {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--gray-800);
    }
    .export-card .card-header p {
        font-size: 0.85rem;
        color: var(--gray-500);
        margin-top: 2px;
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
    .form-group .help-text {
        font-size: 0.7rem;
        color: var(--gray-400);
        margin-top: 2px;
    }
    .form-group select,
    .form-group input {
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
    .form-group select:focus,
    .form-group input:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 4px rgba(var(--primary-rgb), 0.08);
    }
    
    .format-options {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 10px;
        margin-top: 6px;
    }
    .format-option {
        padding: 14px 10px;
        border: 2px solid var(--gray-200);
        border-radius: 10px;
        text-align: center;
        cursor: pointer;
        transition: var(--transition);
        background: var(--gray-50);
    }
    .format-option:hover {
        border-color: var(--gray-300);
        background: white;
        transform: translateY(-2px);
    }
    .format-option.selected {
        border-color: var(--primary);
        background: #EFF6FF;
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
    }
    .format-option i {
        font-size: 1.8rem;
        display: block;
        margin-bottom: 4px;
    }
    .format-option .name {
        font-weight: 600;
        font-size: 0.75rem;
        color: var(--gray-700);
    }
    .format-option .desc {
        font-size: 0.6rem;
        color: var(--gray-400);
    }
    .format-option .pdf i { color: #DC2626; }
    .format-option .excel i { color: #10B981; }
    .format-option .csv i { color: #3B82F6; }
    .format-option .json i { color: #8B5CF6; }
    
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
    
    .toast {
        padding: 12px 18px;
        border-radius: 8px;
        color: white;
        font-size: 0.82rem;
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
    
    .stats-preview {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
        gap: 10px;
        margin-top: 12px;
        padding: 12px 16px;
        background: var(--gray-50);
        border-radius: 8px;
        border: 1px solid var(--gray-200);
    }
    .stats-preview .stat {
        text-align: center;
    }
    .stats-preview .stat .number {
        font-weight: 700;
        font-size: 1.1rem;
        color: var(--primary);
    }
    .stats-preview .stat .label {
        font-size: 0.65rem;
        color: var(--gray-500);
    }
    
    @media (max-width: 768px) {
        .export-card {
            padding: 16px;
        }
        .form-grid {
            grid-template-columns: 1fr;
        }
        .format-options {
            grid-template-columns: 1fr 1fr;
        }
        .form-actions {
            flex-direction: column;
        }
        .form-actions .btn {
            width: 100%;
            justify-content: center;
        }
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .stats-preview {
            grid-template-columns: 1fr 1fr;
        }
    }
    @media (max-width: 480px) {
        .export-card {
            padding: 12px;
        }
        .export-card .card-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .format-options {
            grid-template-columns: 1fr;
        }
        .stats-preview {
            grid-template-columns: 1fr;
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
            <?php if ($action_result['success'] && isset($action_result['count'])): ?>
                <span style="margin-left:auto;font-size:0.7rem;opacity:0.8;">
                    <?php echo number_format($action_result['count']); ?> incidents exported
                </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-file-export" style="color:var(--primary);margin-right:8px;"></i> Export Incidents
                    <small>Export incident reports in various formats</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="incidents.php" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Incidents
                </a>
            </div>
        </div>

        <div class="export-container">
            <!-- Export Configuration -->
            <div class="export-card">
                <div class="card-header">
                    <div class="icon primary">
                        <i class="fas fa-download"></i>
                    </div>
                    <div>
                        <h3>Export Incident Report</h3>
                        <p>Select filters and format to export incident data</p>
                    </div>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="export_incidents">
                    
                    <div class="form-grid">
                        <!-- Format -->
                        <div class="form-group full-width">
                            <label>Export Format <span class="required">*</span></label>
                            <div class="format-options">
                                <div class="format-option pdf selected" onclick="selectFormat(this, 'pdf')">
                                    <i class="fas fa-file-pdf" style="color:#DC2626;"></i>
                                    <div class="name">PDF</div>
                                    <div class="desc">Best for printing</div>
                                </div>
                                <div class="format-option excel" onclick="selectFormat(this, 'excel')">
                                    <i class="fas fa-file-excel" style="color:#10B981;"></i>
                                    <div class="name">Excel</div>
                                    <div class="desc">For data analysis</div>
                                </div>
                                <div class="format-option csv" onclick="selectFormat(this, 'csv')">
                                    <i class="fas fa-file-csv" style="color:#3B82F6;"></i>
                                    <div class="name">CSV</div>
                                    <div class="desc">For import/export</div>
                                </div>
                                <div class="format-option json" onclick="selectFormat(this, 'json')">
                                    <i class="fas fa-code" style="color:#8B5CF6;"></i>
                                    <div class="name">JSON</div>
                                    <div class="desc">For developers</div>
                                </div>
                            </div>
                            <input type="hidden" name="format" id="selectedFormat" value="pdf">
                        </div>

                        <!-- Election Filter -->
                        <div class="form-group">
                            <label>Filter by Election</label>
                            <select name="election_id">
                                <option value="0">All Elections</option>
                                <?php foreach ($elections as $election): ?>
                                    <option value="<?php echo $election['id']; ?>">
                                        <?php echo htmlspecialchars($election['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- State Filter -->
                        <div class="form-group">
                            <label>Filter by State</label>
                            <select name="state_id">
                                <option value="0">All States</option>
                                <?php foreach ($states as $state): ?>
                                    <option value="<?php echo $state['id']; ?>">
                                        <?php echo htmlspecialchars($state['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Severity Filter -->
                        <div class="form-group">
                            <label>Filter by Severity</label>
                            <select name="severity">
                                <option value="">All Severity</option>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>

                        <!-- Status Filter -->
                        <div class="form-group">
                            <label>Filter by Status</label>
                            <select name="status">
                                <option value="">All Status</option>
                                <option value="reported">Reported</option>
                                <option value="acknowledged">Acknowledged</option>
                                <option value="investigating">Investigating</option>
                                <option value="escalated">Escalated</option>
                                <option value="resolved">Resolved</option>
                                <option value="closed">Closed</option>
                                <option value="false_alarm">False Alarm</option>
                            </select>
                        </div>

                        <!-- Date Range -->
                        <div class="form-group">
                            <label>Date From</label>
                            <input type="date" name="date_from">
                        </div>
                        <div class="form-group">
                            <label>Date To</label>
                            <input type="date" name="date_to">
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="incidents.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-download"></i> Export Incidents
                        </button>
                    </div>
                </form>

                <!-- Stats Preview (will be shown after export) -->
                <div class="stats-preview" style="margin-top:16px;">
                    <div class="stat">
                        <div class="number"><?php echo number_format($action_result['count'] ?? 0); ?></div>
                        <div class="label">Incidents</div>
                    </div>
                    <div class="stat">
                        <div class="number" style="color:var(--secondary);"><?php echo number_format($action_result['count'] ?? 0); ?></div>
                        <div class="label">Exported</div>
                    </div>
                    <div class="stat">
                        <div class="number" style="color:var(--primary);"><?php echo date('M j, Y'); ?></div>
                        <div class="label">Date</div>
                    </div>
                    <div class="stat">
                        <div class="number" style="color:var(--warning);">
                            <?php echo isset($action_result['format']) ? strtoupper($action_result['format']) : 'PDF'; ?>
                        </div>
                        <div class="label">Format</div>
                    </div>
                </div>
            </div>

            <!-- Export Tips -->
            <div class="export-card">
                <div class="card-header">
                    <div class="icon warning">
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <div>
                        <h3>Export Tips</h3>
                        <p>Get the most out of your incident reports</p>
                    </div>
                </div>
                <ul style="list-style:none;padding:0;margin:0;font-size:0.85rem;color:var(--gray-600);line-height:2;">
                    <li><i class="fas fa-check-circle" style="color:var(--secondary);"></i> Use <strong>PDF</strong> for official incident reports and printing</li>
                    <li><i class="fas fa-check-circle" style="color:var(--secondary);"></i> Use <strong>Excel</strong> for data analysis and charting</li>
                    <li><i class="fas fa-check-circle" style="color:var(--secondary);"></i> Use <strong>CSV</strong> for importing into other systems</li>
                    <li><i class="fas fa-check-circle" style="color:var(--secondary);"></i> Use <strong>JSON</strong> for API integration</li>
                    <li><i class="fas fa-check-circle" style="color:var(--secondary);"></i> Filter by <strong>severity</strong> to focus on critical incidents</li>
                    <li><i class="fas fa-check-circle" style="color:var(--secondary);"></i> Filter by <strong>date range</strong> for periodic reporting</li>
                </ul>
            </div>
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
// FORMAT SELECTION
// ============================================================
function selectFormat(element, format) {
    document.querySelectorAll('.format-option').forEach(function(opt) {
        opt.classList.remove('selected');
    });
    element.classList.add('selected');
    document.getElementById('selectedFormat').value = format;
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