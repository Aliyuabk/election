<?php
// ============================================================
// NATIONAL COORDINATOR - REPORTS
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

// Get parameters
$report_type = isset($_GET['type']) ? $_GET['type'] : '';
$state_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$selected_states = isset($_GET['states']) ? $_GET['states'] : '';

$db = getDB();

// ============================================================
// FETCH STATES FOR DROPDOWN
// ============================================================
$states = [];
try {
    $stmt = $db->prepare("SELECT id, name FROM states WHERE is_active = 1 ORDER BY name ASC");
    $stmt->execute();
    $states = $stmt->fetchAll();
} catch (Exception $e) {
    $states = [];
}

// ============================================================
// FETCH STATE NAME IF PROVIDED
// ============================================================
$state_name = '';
if ($state_id > 0) {
    try {
        $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
        $stmt->execute([$state_id]);
        $state_name = $stmt->fetchColumn() ?: '';
    } catch (Exception $e) {
        $state_name = '';
    }
}

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'Reports';
$page_subtitle = 'Generate and export reports';
?>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Breadcrumb -->
        <div class="welcome-section">
            <div class="breadcrumb">
                <i class="fas fa-home"></i>
                <a href="../national/index.php" style="text-decoration:none;color:var(--gray-500);">Dashboard</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="font-weight:600;color:var(--gray-800);">Reports</span>
            </div>
            
            <h2 style="font-size:1.5rem;font-weight:700;margin:8px 0 0;">Generate Reports</h2>
            <p style="color:var(--gray-500);margin:2px 0 0;">Create and export reports for analysis and documentation</p>
        </div>

        <!-- Report Types Grid -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;margin-bottom:24px;">
            <!-- National Election Report -->
            <div style="background:white;border-radius:var(--radius);padding:20px;border:1px solid var(--gray-200);transition:var(--transition);hover:transform:translateY(-4px);hover:box-shadow:var(--shadow-hover);">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                    <div style="width:48px;height:48px;border-radius:12px;background:#EFF6FF;display:flex;align-items:center;justify-content:center;color:#3B82F6;font-size:1.2rem;">
                        <i class="fas fa-flag"></i>
                    </div>
                    <div>
                        <h3 style="font-size:0.9rem;font-weight:600;margin:0;">National Election Report</h3>
                        <p style="font-size:0.7rem;color:var(--gray-500);margin:0;">Overview of all states</p>
                    </div>
                </div>
                <p style="font-size:0.8rem;color:var(--gray-600);margin-bottom:12px;">Comprehensive report covering all states with election results, incidents, and agent activity.</p>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="reports-generate.php?type=national&format=pdf" class="btn-sm" style="padding:6px 16px;border-radius:8px;background:#EF4444;color:white;text-decoration:none;font-size:0.7rem;display:inline-flex;align-items:center;gap:4px;">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                    <a href="reports-generate.php?type=national&format=excel" class="btn-sm" style="padding:6px 16px;border-radius:8px;background:#10B981;color:white;text-decoration:none;font-size:0.7rem;display:inline-flex;align-items:center;gap:4px;">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                    <a href="reports-generate.php?type=national&format=csv" class="btn-sm" style="padding:6px 16px;border-radius:8px;background:#8B5CF6;color:white;text-decoration:none;font-size:0.7rem;display:inline-flex;align-items:center;gap:4px;">
                        <i class="fas fa-file-csv"></i> CSV
                    </a>
                </div>
            </div>

            <!-- State Performance Report -->
            <div style="background:white;border-radius:var(--radius);padding:20px;border:1px solid var(--gray-200);transition:var(--transition);hover:transform:translateY(-4px);hover:box-shadow:var(--shadow-hover);">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                    <div style="width:48px;height:48px;border-radius:12px;background:#ECFDF5;display:flex;align-items:center;justify-content:center;color:#10B981;font-size:1.2rem;">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div>
                        <h3 style="font-size:0.9rem;font-weight:600;margin:0;">State Performance Report</h3>
                        <p style="font-size:0.7rem;color:var(--gray-500);margin:0;">Detailed state analysis</p>
                    </div>
                </div>
                <form method="GET" action="reports-generate.php" style="display:flex;flex-direction:column;gap:8px;">
                    <input type="hidden" name="type" value="state">
                    <select name="id" class="form-control" style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:0.8rem;background:white;">
                        <option value="">Select State...</option>
                        <?php foreach ($states as $state): ?>
                            <option value="<?php echo $state['id']; ?>" <?php echo $state_id == $state['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($state['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <button type="submit" name="format" value="pdf" class="btn-sm" style="padding:6px 16px;border-radius:8px;background:#EF4444;color:white;border:none;font-size:0.7rem;cursor:pointer;display:inline-flex;align-items:center;gap:4px;">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                        <button type="submit" name="format" value="excel" class="btn-sm" style="padding:6px 16px;border-radius:8px;background:#10B981;color:white;border:none;font-size:0.7rem;cursor:pointer;display:inline-flex;align-items:center;gap:4px;">
                            <i class="fas fa-file-excel"></i> Excel
                        </button>
                        <button type="submit" name="format" value="csv" class="btn-sm" style="padding:6px 16px;border-radius:8px;background:#8B5CF6;color:white;border:none;font-size:0.7rem;cursor:pointer;display:inline-flex;align-items:center;gap:4px;">
                            <i class="fas fa-file-csv"></i> CSV
                        </button>
                    </div>
                </form>
            </div>

            <!-- Result Submission Report -->
            <div style="background:white;border-radius:var(--radius);padding:20px;border:1px solid var(--gray-200);transition:var(--transition);hover:transform:translateY(-4px);hover:box-shadow:var(--shadow-hover);">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                    <div style="width:48px;height:48px;border-radius:12px;background:#FEF3C7;display:flex;align-items:center;justify-content:center;color:#F59E0B;font-size:1.2rem;">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div>
                        <h3 style="font-size:0.9rem;font-weight:600;margin:0;">Result Submission Report</h3>
                        <p style="font-size:0.7rem;color:var(--gray-500);margin:0;">EC8 forms status</p>
                    </div>
                </div>
                <p style="font-size:0.8rem;color:var(--gray-600);margin-bottom:12px;">Report on EC8A, EC8B, EC8C, EC8D, EC8E submission status across all states.</p>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="reports-generate.php?type=results&format=pdf" class="btn-sm" style="padding:6px 16px;border-radius:8px;background:#EF4444;color:white;text-decoration:none;font-size:0.7rem;display:inline-flex;align-items:center;gap:4px;">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                    <a href="reports-generate.php?type=results&format=excel" class="btn-sm" style="padding:6px 16px;border-radius:8px;background:#10B981;color:white;text-decoration:none;font-size:0.7rem;display:inline-flex;align-items:center;gap:4px;">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                </div>
            </div>

            <!-- Incident Report -->
            <div style="background:white;border-radius:var(--radius);padding:20px;border:1px solid var(--gray-200);transition:var(--transition);hover:transform:translateY(-4px);hover:box-shadow:var(--shadow-hover);">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                    <div style="width:48px;height:48px;border-radius:12px;background:#FEE2E2;display:flex;align-items:center;justify-content:center;color:#EF4444;font-size:1.2rem;">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div>
                        <h3 style="font-size:0.9rem;font-weight:600;margin:0;">Incident Report</h3>
                        <p style="font-size:0.7rem;color:var(--gray-500);margin:0;">All incidents recorded</p>
                    </div>
                </div>
                <p style="font-size:0.8rem;color:var(--gray-600);margin-bottom:12px;">Comprehensive report of all incidents including severity, status, and resolution details.</p>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="reports-generate.php?type=incident&format=pdf" class="btn-sm" style="padding:6px 16px;border-radius:8px;background:#EF4444;color:white;text-decoration:none;font-size:0.7rem;display:inline-flex;align-items:center;gap:4px;">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                    <a href="reports-generate.php?type=incident&format=excel" class="btn-sm" style="padding:6px 16px;border-radius:8px;background:#10B981;color:white;text-decoration:none;font-size:0.7rem;display:inline-flex;align-items:center;gap:4px;">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                </div>
            </div>

            <!-- User Activity Report -->
            <div style="background:white;border-radius:var(--radius);padding:20px;border:1px solid var(--gray-200);transition:var(--transition);hover:transform:translateY(-4px);hover:box-shadow:var(--shadow-hover);">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                    <div style="width:48px;height:48px;border-radius:12px;background:#F5F3FF;display:flex;align-items:center;justify-content:center;color:#8B5CF6;font-size:1.2rem;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <h3 style="font-size:0.9rem;font-weight:600;margin:0;">User Activity Report</h3>
                        <p style="font-size:0.7rem;color:var(--gray-500);margin:0;">Coordinator & agent activity</p>
                    </div>
                </div>
                <p style="font-size:0.8rem;color:var(--gray-600);margin-bottom:12px;">Track user logins, actions, and engagement across the platform.</p>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="reports-generate.php?type=activity&format=pdf" class="btn-sm" style="padding:6px 16px;border-radius:8px;background:#EF4444;color:white;text-decoration:none;font-size:0.7rem;display:inline-flex;align-items:center;gap:4px;">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                    <a href="reports-generate.php?type=activity&format=excel" class="btn-sm" style="padding:6px 16px;border-radius:8px;background:#10B981;color:white;text-decoration:none;font-size:0.7rem;display:inline-flex;align-items:center;gap:4px;">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                </div>
            </div>

            <!-- State Comparison Report -->
            <div style="background:white;border-radius:var(--radius);padding:20px;border:1px solid var(--gray-200);transition:var(--transition);hover:transform:translateY(-4px);hover:box-shadow:var(--shadow-hover);">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                    <div style="width:48px;height:48px;border-radius:12px;background:#CCFBF1;display:flex;align-items:center;justify-content:center;color:#0D9488;font-size:1.2rem;">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div>
                        <h3 style="font-size:0.9rem;font-weight:600;margin:0;">State Comparison Report</h3>
                        <p style="font-size:0.7rem;color:var(--gray-500);margin:0;">Compare multiple states</p>
                    </div>
                </div>
                <form method="GET" action="reports-generate.php" style="display:flex;flex-direction:column;gap:8px;">
                    <input type="hidden" name="type" value="comparison">
                    <select name="states[]" multiple class="form-control" style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:0.8rem;background:white;min-height:80px;">
                        <?php foreach ($states as $state): ?>
                            <option value="<?php echo $state['id']; ?>">
                                <?php echo htmlspecialchars($state['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <button type="submit" name="format" value="pdf" class="btn-sm" style="padding:6px 16px;border-radius:8px;background:#EF4444;color:white;border:none;font-size:0.7rem;cursor:pointer;display:inline-flex;align-items:center;gap:4px;">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                        <button type="submit" name="format" value="excel" class="btn-sm" style="padding:6px 16px;border-radius:8px;background:#10B981;color:white;border:none;font-size:0.7rem;cursor:pointer;display:inline-flex;align-items:center;gap:4px;">
                            <i class="fas fa-file-excel"></i> Excel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Recent Reports -->
        <div style="background:white;border-radius:var(--radius);padding:20px;border:1px solid var(--gray-200);margin-bottom:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <h4 style="font-size:0.9rem;font-weight:600;margin:0;">
                    <i class="fas fa-history" style="color:var(--primary);margin-right:6px;"></i>
                    Recently Generated Reports
                </h4>
                <a href="reports-history.php" style="font-size:0.7rem;color:var(--primary);text-decoration:none;">View All →</a>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px;">
                <?php
                // Fetch recent reports
                $recent_reports = [];
                try {
                    $stmt = $db->prepare("
                        SELECT * FROM reports 
                        WHERE tenant_id = ? 
                        ORDER BY generated_at DESC 
                        LIMIT 6
                    ");
                    $stmt->execute([$tenant_id]);
                    $recent_reports = $stmt->fetchAll();
                } catch (Exception $e) {
                    $recent_reports = [];
                }
                
                if (count($recent_reports) > 0):
                    foreach ($recent_reports as $report):
                ?>
                    <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;background:var(--gray-50);border-radius:8px;border:1px solid var(--gray-100);">
                        <div style="width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;background:#EFF6FF;color:#3B82F6;font-size:0.9rem;">
                            <i class="fas fa-<?php echo $report['format'] === 'pdf' ? 'file-pdf' : ($report['format'] === 'excel' ? 'file-excel' : 'file'); ?>"></i>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:500;font-size:0.8rem;text-truncate:ellipsis;"><?php echo htmlspecialchars($report['name']); ?></div>
                            <div style="font-size:0.65rem;color:var(--gray-400);">
                                <?php echo date('M j, Y g:i A', strtotime($report['generated_at'])); ?>
                                <?php if ($report['file_size']): ?>
                                    • <?php echo round($report['file_size'] / 1024, 1); ?> KB
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($report['file_url']): ?>
                            <a href="<?php echo htmlspecialchars($report['file_url']); ?>" class="btn-sm" style="padding:4px 10px;border-radius:6px;background:var(--primary);color:white;text-decoration:none;font-size:0.6rem;" download>
                                <i class="fas fa-download"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach;
                else: ?>
                    <p style="color:var(--gray-500);font-size:0.8rem;padding:12px 0;text-align:center;grid-column:1/-1;">
                        <i class="fas fa-file-alt" style="display:block;font-size:1.2rem;margin-bottom:4px;color:var(--gray-300);"></i>
                        No recent reports generated
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Export -->
        <div style="background:#F8FAFC;border-radius:var(--radius);padding:16px 20px;border:1px solid var(--gray-200);">
            <h4 style="font-size:0.85rem;font-weight:600;margin:0 0 8px;">
                <i class="fas fa-download" style="color:var(--primary);margin-right:6px;"></i>
                Quick Export Options
            </h4>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                <a href="reports-generate.php?type=national&format=pdf" class="btn-sm" style="padding:6px 16px;border-radius:8px;background:var(--primary);color:white;text-decoration:none;font-size:0.75rem;display:inline-flex;align-items:center;gap:4px;">
                    <i class="fas fa-file-pdf"></i> Full Report (PDF)
                </a>
                <a href="reports-generate.php?type=national&format=excel" class="btn-sm" style="padding:6px 16px;border-radius:8px;background:#10B981;color:white;text-decoration:none;font-size:0.75rem;display:inline-flex;align-items:center;gap:4px;">
                    <i class="fas fa-file-excel"></i> Full Report (Excel)
                </a>
                <a href="reports-generate.php?type=summary&format=pdf" class="btn-sm" style="padding:6px 16px;border-radius:8px;background:#8B5CF6;color:white;text-decoration:none;font-size:0.75rem;display:inline-flex;align-items:center;gap:4px;">
                    <i class="fas fa-file-pdf"></i> Executive Summary
                </a>
                <a href="reports-generate.php?type=incident&format=pdf" class="btn-sm" style="padding:6px 16px;border-radius:8px;background:#EF4444;color:white;text-decoration:none;font-size:0.75rem;display:inline-flex;align-items:center;gap:4px;">
                    <i class="fas fa-file-pdf"></i> Incident Summary
                </a>
            </div>
        </div>
    </div>
</main>

<style>
.btn-sm:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1); }
div[style*="transition:var(--transition);"]:hover { transform: translateY(-4px); box-shadow: var(--shadow-hover); }

@media (max-width: 768px) {
    div[style*="grid-template-columns:repeat(auto-fit,minmax(280px,1fr))"] {
        grid-template-columns: 1fr !important;
    }
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