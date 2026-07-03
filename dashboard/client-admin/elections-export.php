<?php
// ============================================================
// ELECTION EXPORT - CLIENT ADMIN (PROFESSIONAL UI)
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
    $stmt = $db->prepare("SELECT * FROM elections WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL");
    $stmt->execute([$election_id, $tenant_id]);
    $election = $stmt->fetch();
} catch (Exception $e) {}

if (!$election) {
    header('Location: elections.php');
    exit();
}

// ============================================================
// HANDLE EXPORT
// ============================================================
$export_result = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $format = $_POST['format'] ?? 'pdf';
    $data_type = $_POST['data_type'] ?? 'results';
    
    // Simulate export - in production, generate actual file
    $export_result = [
        'success' => true, 
        'message' => "Export started! File will be available shortly.",
        'file' => "election_export_{$election_id}_{$data_type}.{$format}"
    ];
}

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       ELECTION EXPORT - PROFESSIONAL UI STYLES
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
    
    .export-container {
        max-width: 700px;
        margin: 0 auto;
    }
    
    .export-card {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 32px 36px;
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
    
    .form-group {
        margin-bottom: 18px;
    }
    .form-group label {
        font-weight: 600;
        font-size: 0.85rem;
        color: var(--gray-700);
        display: block;
        margin-bottom: 4px;
    }
    .form-group .help-text {
        font-size: 0.75rem;
        color: var(--gray-400);
        margin-top: 4px;
    }
    .form-group select {
        padding: 10px 14px;
        border: 1.5px solid var(--gray-200);
        border-radius: 10px;
        font-family: 'Inter', sans-serif;
        font-size: 0.85rem;
        transition: var(--transition);
        background: var(--gray-50);
        color: var(--gray-700);
        width: 100%;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 12px center;
        padding-right: 36px;
    }
    .form-group select:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 4px rgba(var(--primary-rgb), 0.08);
    }
    
    .format-options {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 10px;
        margin-top: 6px;
    }
    .format-option {
        padding: 14px 12px;
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
    }
    .format-option.selected {
        border-color: var(--primary);
        background: #EFF6FF;
    }
    .format-option i {
        font-size: 1.6rem;
        display: block;
        margin-bottom: 6px;
    }
    .format-option .name {
        font-weight: 600;
        font-size: 0.8rem;
        color: var(--gray-700);
    }
    .format-option .desc {
        font-size: 0.65rem;
        color: var(--gray-400);
    }
    
    .data-options {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 10px;
        margin-top: 6px;
    }
    .data-option {
        padding: 12px 16px;
        border: 2px solid var(--gray-200);
        border-radius: 10px;
        cursor: pointer;
        transition: var(--transition);
        background: var(--gray-50);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .data-option:hover {
        border-color: var(--gray-300);
        background: white;
    }
    .data-option.selected {
        border-color: var(--primary);
        background: #EFF6FF;
    }
    .data-option i {
        font-size: 1.2rem;
        color: var(--primary);
    }
    .data-option .info .name {
        font-weight: 600;
        font-size: 0.8rem;
        color: var(--gray-700);
    }
    .data-option .info .desc {
        font-size: 0.65rem;
        color: var(--gray-400);
    }
    
    .export-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 2px solid var(--gray-100);
    }
    .export-actions .btn {
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
    }
    .toast.success { background: var(--secondary); }
    .toast.error { background: var(--danger); }
    .toast.info { background: #3B82F6; }
    
    @media (max-width: 768px) {
        .export-card {
            padding: 20px;
        }
        .format-options {
            grid-template-columns: 1fr 1fr;
        }
        .data-options {
            grid-template-columns: 1fr;
        }
        .export-actions {
            flex-direction: column;
        }
        .export-actions .btn {
            width: 100%;
            justify-content: center;
        }
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }
    @media (max-width: 480px) {
        .format-options {
            grid-template-columns: 1fr;
        }
        .export-card {
            padding: 14px;
        }
        .export-card .card-header {
            flex-direction: column;
            align-items: flex-start;
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
                    <i class="fas fa-file-export" style="color:var(--primary);margin-right:8px;"></i> Export Election Data
                    <small><?php echo htmlspecialchars($election['name']); ?></small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="elections-view.php?id=<?php echo $election_id; ?>" class="btn-outline">
                    <i class="fas fa-eye"></i> View
                </a>
                <a href="elections.php" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Toast Messages -->
        <?php if (!empty($export_result['message'])): ?>
        <div class="toast <?php echo $export_result['success'] ? 'success' : 'error'; ?>">
            <i class="fas <?php echo $export_result['success'] ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($export_result['message']); ?>
        </div>
        <?php endif; ?>

        <div class="export-container">
            <!-- Export Configuration -->
            <div class="export-card">
                <div class="card-header">
                    <div class="icon primary">
                        <i class="fas fa-cog"></i>
                    </div>
                    <div>
                        <h3>Export Configuration</h3>
                        <p>Select the data and format you want to export</p>
                    </div>
                </div>

                <form method="POST" action="">
                    <!-- Format Selection -->
                    <div class="form-group">
                        <label>Export Format</label>
                        <div class="format-options">
                            <div class="format-option selected" onclick="selectFormat(this, 'pdf')">
                                <i class="fas fa-file-pdf" style="color:#DC2626;"></i>
                                <div class="name">PDF</div>
                                <div class="desc">Best for printing</div>
                            </div>
                            <div class="format-option" onclick="selectFormat(this, 'excel')">
                                <i class="fas fa-file-excel" style="color:#10B981;"></i>
                                <div class="name">Excel</div>
                                <div class="desc">For data analysis</div>
                            </div>
                            <div class="format-option" onclick="selectFormat(this, 'csv')">
                                <i class="fas fa-file-csv" style="color:#3B82F6;"></i>
                                <div class="name">CSV</div>
                                <div class="desc">For import/export</div>
                            </div>
                            <div class="format-option" onclick="selectFormat(this, 'json')">
                                <i class="fas fa-code" style="color:#8B5CF6;"></i>
                                <div class="name">JSON</div>
                                <div class="desc">For developers</div>
                            </div>
                        </div>
                        <input type="hidden" name="format" id="selectedFormat" value="pdf">
                    </div>

                    <!-- Data Type Selection -->
                    <div class="form-group">
                        <label>Data to Export</label>
                        <div class="data-options">
                            <div class="data-option selected" onclick="selectData(this, 'results')">
                                <i class="fas fa-chart-bar"></i>
                                <div class="info">
                                    <div class="name">Results</div>
                                    <div class="desc">All election results data</div>
                                </div>
                            </div>
                            <div class="data-option" onclick="selectData(this, 'candidates')">
                                <i class="fas fa-user-tie"></i>
                                <div class="info">
                                    <div class="name">Candidates</div>
                                    <div class="desc">Candidate information</div>
                                </div>
                            </div>
                            <div class="data-option" onclick="selectData(this, 'polling_units')">
                                <i class="fas fa-map-marker-alt"></i>
                                <div class="info">
                                    <div class="name">Polling Units</div>
                                    <div class="desc">PU assignments</div>
                                </div>
                            </div>
                            <div class="data-option" onclick="selectData(this, 'incidents')">
                                <i class="fas fa-exclamation-triangle"></i>
                                <div class="info">
                                    <div class="name">Incidents</div>
                                    <div class="desc">Incident reports</div>
                                </div>
                            </div>
                            <div class="data-option" onclick="selectData(this, 'full_report')">
                                <i class="fas fa-file-alt"></i>
                                <div class="info">
                                    <div class="name">Full Report</div>
                                    <div class="desc">Complete election report</div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="data_type" id="selectedData" value="results">
                    </div>

                    <!-- Additional Options -->
                    <div class="form-group">
                        <label>Additional Options</label>
                        <div style="display:flex;gap:20px;flex-wrap:wrap;padding:8px 0;">
                            <label style="display:flex;align-items:center;gap:8px;font-weight:400;font-size:0.85rem;cursor:pointer;">
                                <input type="checkbox" name="include_headers" checked>
                                Include headers
                            </label>
                            <label style="display:flex;align-items:center;gap:8px;font-weight:400;font-size:0.85rem;cursor:pointer;">
                                <input type="checkbox" name="include_metadata" checked>
                                Include metadata
                            </label>
                            <label style="display:flex;align-items:center;gap:8px;font-weight:400;font-size:0.85rem;cursor:pointer;">
                                <input type="checkbox" name="compress">
                                Compress file
                            </label>
                        </div>
                        <div class="help-text">
                            <i class="fas fa-info-circle"></i> 
                            Metadata includes election details, date, and creation info
                        </div>
                    </div>

                    <div class="export-actions">
                        <a href="elections-view.php?id=<?php echo $election_id; ?>" class="btn" style="background:var(--gray-100);color:var(--gray-600);">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-download"></i> Export Now
                        </button>
                    </div>
                </form>
            </div>

            <!-- Export History -->
            <div class="export-card">
                <div class="card-header">
                    <div class="icon success">
                        <i class="fas fa-history"></i>
                    </div>
                    <div>
                        <h3>Recent Exports</h3>
                        <p>Your previously exported files</p>
                    </div>
                </div>
                <div style="font-size:0.85rem;">
                    <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--gray-100);">
                        <div>
                            <div style="font-weight:500;">election_results_2027.pdf</div>
                            <div style="font-size:0.7rem;color:var(--gray-400);">Exported Jan 15, 2027 · 2.4 MB</div>
                        </div>
                        <button class="btn-sm info" onclick="alert('Downloading...')">
                            <i class="fas fa-download"></i> Download
                        </button>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--gray-100);">
                        <div>
                            <div style="font-weight:500;">candidates_list_2027.xlsx</div>
                            <div style="font-size:0.7rem;color:var(--gray-400);">Exported Jan 10, 2027 · 856 KB</div>
                        </div>
                        <button class="btn-sm info" onclick="alert('Downloading...')">
                            <i class="fas fa-download"></i> Download
                        </button>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:10px 0;">
                        <div>
                            <div style="font-weight:500;">full_report_2027.pdf</div>
                            <div style="font-size:0.7rem;color:var(--gray-400);">Exported Jan 5, 2027 · 4.7 MB</div>
                        </div>
                        <button class="btn-sm info" onclick="alert('Downloading...')">
                            <i class="fas fa-download"></i> Download
                        </button>
                    </div>
                </div>
            </div>

            <!-- Help Section -->
            <div class="export-card" style="background:#F8FAFC;border-color:var(--gray-200);">
                <div class="card-header" style="border-bottom-color:var(--gray-200);">
                    <div class="icon warning">
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <div>
                        <h3>Export Tips</h3>
                        <p>Get the most out of your exports</p>
                    </div>
                </div>
                <ul style="list-style:none;padding:0;margin:0;font-size:0.85rem;color:var(--gray-600);line-height:1.8;">
                    <li><i class="fas fa-check-circle" style="color:var(--secondary);"></i> Use <strong>PDF</strong> for official reports and printing</li>
                    <li><i class="fas fa-check-circle" style="color:var(--secondary);"></i> Use <strong>Excel</strong> for data analysis and manipulation</li>
                    <li><i class="fas fa-check-circle" style="color:var(--secondary);"></i> Use <strong>CSV</strong> for importing into other systems</li>
                    <li><i class="fas fa-check-circle" style="color:var(--secondary);"></i> Use <strong>JSON</strong> for API integration</li>
                    <li><i class="fas fa-check-circle" style="color:var(--secondary);"></i> The <strong>Full Report</strong> includes all data in one comprehensive file</li>
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
// DATA TYPE SELECTION
// ============================================================
function selectData(element, dataType) {
    document.querySelectorAll('.data-option').forEach(function(opt) {
        opt.classList.remove('selected');
    });
    element.classList.add('selected');
    document.getElementById('selectedData').value = dataType;
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