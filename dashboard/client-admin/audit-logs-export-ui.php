<?php
// ============================================================
// AUDIT LOGS EXPORT UI - CLIENT ADMIN (PROFESSIONAL UI)
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
// FETCH STATISTICS
// ============================================================
$stats = [
    'total' => 0,
    'today' => 0,
    'this_week' => 0,
    'this_month' => 0
];

try {
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM audit_logs WHERE tenant_id = ?");
    $stmt->execute([$tenant_id]);
    $stats['total'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM audit_logs WHERE tenant_id = ? AND DATE(created_at) = CURDATE()");
    $stmt->execute([$tenant_id]);
    $stats['today'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM audit_logs WHERE tenant_id = ? AND YEARWEEK(created_at) = YEARWEEK(CURDATE())");
    $stmt->execute([$tenant_id]);
    $stats['this_week'] = $stmt->fetch()['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM audit_logs WHERE tenant_id = ? AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
    $stmt->execute([$tenant_id]);
    $stats['this_month'] = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {}

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       AUDIT LOGS EXPORT UI - PROFESSIONAL STYLES
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
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
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
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--primary);
        line-height: 1.2;
    }
    .stat-item .number.green { color: var(--secondary); }
    .stat-item .number.blue { color: #3B82F6; }
    .stat-item .number.purple { color: #8B5CF6; }
    .stat-item .label {
        font-size: 0.7rem;
        color: var(--gray-500);
        margin-top: 4px;
        font-weight: 500;
    }
    
    .export-container {
        max-width: 800px;
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
    .export-card .card-header .icon.purple { background: #F5F3FF; color: #8B5CF6; }
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
    .toast.info { background: #3B82F6; }
    
    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .export-card { padding: 16px; }
        .form-grid { grid-template-columns: 1fr; }
        .format-options { grid-template-columns: 1fr 1fr; }
        .form-actions { flex-direction: column; }
        .form-actions .btn { width: 100%; justify-content: center; }
        .page-header { flex-direction: column; align-items: flex-start; }
    }
    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
        .stat-item { padding: 10px 12px; }
        .stat-item .number { font-size: 1.1rem; }
        .export-card { padding: 12px; }
        .export-card .card-header { flex-direction: column; align-items: flex-start; }
        .format-options { grid-template-columns: 1fr; }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Toast Messages -->
        <?php if (!empty($_SESSION['export_error'])): ?>
        <div class="toast error" style="position:static;animation:none;">
            <i class="fas fa-exclamation-circle"></i>
            <?php 
            echo htmlspecialchars($_SESSION['export_error']);
            unset($_SESSION['export_error']);
            ?>
        </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-file-export" style="color:var(--primary);margin-right:8px;"></i> Export Audit Logs
                    <small>Export audit logs in various formats</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="audit-logs.php" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-item">
                <div class="number"><?php echo number_format($stats['total']); ?></div>
                <div class="label">Total Logs</div>
            </div>
            <div class="stat-item">
                <div class="number blue"><?php echo number_format($stats['today']); ?></div>
                <div class="label">Today</div>
            </div>
            <div class="stat-item">
                <div class="number green"><?php echo number_format($stats['this_week']); ?></div>
                <div class="label">This Week</div>
            </div>
            <div class="stat-item">
                <div class="number purple"><?php echo number_format($stats['this_month']); ?></div>
                <div class="label">This Month</div>
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
                        <h3>Export Configuration</h3>
                        <p>Select filters and format for your audit log export</p>
                    </div>
                </div>

                <form method="GET" action="audit-logs-export.php">
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

                        <!-- Action Filter -->
                        <div class="form-group">
                            <label>Filter by Action</label>
                            <select name="action">
                                <option value="">All Actions</option>
                                <option value="login">Login</option>
                                <option value="logout">Logout</option>
                                <option value="create">Create</option>
                                <option value="update">Update</option>
                                <option value="delete">Delete</option>
                                <option value="view">View</option>
                                <option value="export">Export</option>
                                <option value="import">Import</option>
                            </select>
                        </div>

                        <!-- Severity Filter -->
                        <div class="form-group">
                            <label>Filter by Severity</label>
                            <select name="severity">
                                <option value="">All Severity</option>
                                <option value="info">Info</option>
                                <option value="warning">Warning</option>
                                <option value="error">Error</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>

                        <!-- Date From -->
                        <div class="form-group">
                            <label>Date From</label>
                            <input type="date" name="date_from">
                        </div>

                        <!-- Date To -->
                        <div class="form-group">
                            <label>Date To</label>
                            <input type="date" name="date_to">
                        </div>

                        <!-- Search -->
                        <div class="form-group full-width">
                            <label>Search</label>
                            <input type="text" name="search" placeholder="Search in actions, entities, descriptions...">
                            <div class="help-text">Search will look in action, entity type, and description fields</div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="audit-logs.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-download"></i> Export Logs
                        </button>
                    </div>
                </form>
            </div>

            <!-- Export Tips -->
            <div class="export-card">
                <div class="card-header">
                    <div class="icon warning">
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <div>
                        <h3>Export Tips</h3>
                        <p>Get the most out of your audit log exports</p>
                    </div>
                </div>
                <ul style="list-style:none;padding:0;margin:0;font-size:0.85rem;color:var(--gray-600);line-height:2;">
                    <li><i class="fas fa-check-circle" style="color:var(--secondary);"></i> Use <strong>PDF</strong> for official audit reports and printing</li>
                    <li><i class="fas fa-check-circle" style="color:var(--secondary);"></i> Use <strong>Excel</strong> for data analysis and filtering</li>
                    <li><i class="fas fa-check-circle" style="color:var(--secondary);"></i> Use <strong>CSV</strong> for importing into other systems</li>
                    <li><i class="fas fa-check-circle" style="color:var(--secondary);"></i> Use <strong>JSON</strong> for API integration</li>
                    <li><i class="fas fa-check-circle" style="color:var(--secondary);"></i> Filter by <strong>severity</strong> to focus on critical events</li>
                    <li><i class="fas fa-check-circle" style="color:var(--secondary);"></i> Filter by <strong>date range</strong> for periodic auditing</li>
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