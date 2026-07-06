<?php
// ============================================================
// NATIONAL COORDINATOR - ELECTION TEMPLATES
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

$db = getDB();

// ============================================================
// FETCH EXISTING ELECTIONS AS TEMPLATES
// ============================================================
$templates = [];
$total_templates = 0;

try {
    $stmt = $db->prepare("
        SELECT 
            e.*,
            u.full_name as created_by_name,
            (SELECT COUNT(*) FROM elections e2 WHERE e2.tenant_id = e.tenant_id AND e2.id != e.id) as usage_count
        FROM elections e
        LEFT JOIN users u ON e.created_by = u.id
        WHERE e.tenant_id = ? AND e.deleted_at IS NULL
        ORDER BY e.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$tenant_id]);
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_templates = count($templates);
    
} catch (Exception $e) {
    error_log("Templates Error: " . $e->getMessage());
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

$page_title = 'Election Templates';
$page_subtitle = 'Create elections faster with templates';
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
                <a href="elections.php" style="text-decoration:none;color:var(--gray-500);">Elections</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="font-weight:600;color:var(--gray-800);">Templates</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        <i class="fas fa-copy" style="color:var(--primary);"></i>
                        Election Templates
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <i class="fas fa-file-alt"></i> 
                        <?php echo number_format($total_templates); ?> templates available
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="elections-create.php" class="btn-primary" style="padding:8px 20px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-plus"></i> New Election
                    </a>
                    <a href="elections.php" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-arrow-left"></i> Back to Elections
                    </a>
                </div>
            </div>
        </div>

        <!-- How to Use -->
        <div style="background:#F0FDF4;border-radius:var(--radius);padding:16px 20px;margin-bottom:20px;border:1px solid #A7F3D0;">
            <div style="display:flex;align-items:flex-start;gap:12px;">
                <i class="fas fa-lightbulb" style="color:#10B981;font-size:1.2rem;margin-top:2px;"></i>
                <div>
                    <h4 style="font-size:0.85rem;font-weight:600;color:#065F46;margin:0 0 4px;">How to Use Templates</h4>
                    <p style="font-size:0.8rem;color:#065F46;margin:0;">
                        Select an existing election to use as a template for a new election. 
                        This will copy all settings including type, locations, and configuration. 
                        You can then modify the copied election as needed.
                    </p>
                </div>
            </div>
        </div>

        <!-- Templates Grid -->
        <?php if (count($templates) > 0): ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:16px;">
                <?php foreach ($templates as $template): 
                    $status_color = $status_colors[$template['status']] ?? '#6B7280';
                    $type_label = $election_types[$template['type']] ?? ucfirst($template['type']);
                    
                    // Get location count
                    $state_count = !empty($template['states_json']) ? count(json_decode($template['states_json'], true) ?: []) : 0;
                    $lga_count = !empty($template['lgas_json']) ? count(json_decode($template['lgas_json'], true) ?: []) : 0;
                    $ward_count = !empty($template['wards_json']) ? count(json_decode($template['wards_json'], true) ?: []) : 0;
                    $pu_count = !empty($template['pus_json']) ? count(json_decode($template['pus_json'], true) ?: []) : 0;
                    $total_locations = $state_count + $lga_count + $ward_count + $pu_count;
                ?>
                    <div style="background:white;border-radius:var(--radius);padding:16px 20px;border:1px solid var(--gray-200);transition:var(--transition);hover:transform:translateY(-4px);hover:box-shadow:var(--shadow-hover);">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
                            <div>
                                <h4 style="font-size:0.95rem;font-weight:600;margin:0;color:var(--gray-800);">
                                    <?php echo htmlspecialchars($template['name']); ?>
                                </h4>
                                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:4px;">
                                    <span style="font-size:0.65rem;padding:1px 10px;border-radius:10px;background:var(--gray-100);color:var(--gray-600);">
                                        <?php echo $type_label; ?>
                                    </span>
                                    <span style="font-size:0.65rem;padding:1px 10px;border-radius:10px;background:<?php echo $status_color; ?>20;color:<?php echo $status_color; ?>;">
                                        <?php echo ucfirst($template['status']); ?>
                                    </span>
                                    <?php if ($template['cycle']): ?>
                                        <span style="font-size:0.65rem;padding:1px 10px;border-radius:10px;background:#EFF6FF;color:#3B82F6;">
                                            <?php echo htmlspecialchars($template['cycle']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div style="font-size:0.65rem;color:var(--gray-400);text-align:right;">
                                <div><?php echo date('M j, Y', strtotime($template['created_at'])); ?></div>
                                <div><?php echo $total_locations; ?> locations</div>
                            </div>
                        </div>
                        
                        <?php if (!empty($template['description'])): ?>
                            <p style="font-size:0.75rem;color:var(--gray-500);margin:0 0 12px;">
                                <?php echo htmlspecialchars(substr($template['description'], 0, 80)) . (strlen($template['description']) > 80 ? '...' : ''); ?>
                            </p>
                        <?php endif; ?>
                        
                        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;padding-top:12px;border-top:1px solid var(--gray-100);">
                            <a href="elections-create.php?template=<?php echo $template['id']; ?>" class="btn-primary" style="padding:6px 16px;background:var(--primary);color:white;border:none;border-radius:6px;text-decoration:none;font-weight:600;font-size:0.7rem;display:inline-flex;align-items:center;gap:4px;transition:var(--transition);">
                                <i class="fas fa-copy"></i> Use Template
                            </a>
                            <a href="election-view.php?id=<?php echo $template['id']; ?>" class="btn-secondary" style="padding:6px 16px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:6px;text-decoration:none;font-weight:500;font-size:0.7rem;display:inline-flex;align-items:center;gap:4px;transition:var(--transition);">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <a href="elections-edit.php?id=<?php echo $template['id']; ?>" class="btn-secondary" style="padding:6px 16px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:6px;text-decoration:none;font-weight:500;font-size:0.7rem;display:inline-flex;align-items:center;gap:4px;transition:var(--transition);">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <?php if ($template['status'] === 'draft'): ?>
                                <a href="election-delete.php?id=<?php echo $template['id']; ?>" class="btn-danger" style="padding:6px 16px;background:#EF4444;color:white;border:none;border-radius:6px;text-decoration:none;font-weight:600;font-size:0.7rem;display:inline-flex;align-items:center;gap:4px;transition:var(--transition);" onclick="return confirm('Delete this template?')">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="background:white;border-radius:var(--radius);padding:60px 20px;text-align:center;border:1px solid var(--gray-200);">
                <i class="fas fa-copy" style="font-size:3rem;display:block;margin-bottom:12px;color:var(--gray-300);"></i>
                <h3 style="font-size:1.1rem;font-weight:600;color:var(--gray-600);margin:0 0 8px;">No Templates Available</h3>
                <p style="font-size:0.85rem;color:var(--gray-400);margin:0 0 16px;">
                    Create your first election to use it as a template for future elections.
                </p>
                <a href="elections-create.php" class="btn-primary" style="padding:10px 28px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.85rem;display:inline-flex;align-items:center;gap:8px;">
                    <i class="fas fa-plus"></i> Create First Election
                </a>
            </div>
        <?php endif; ?>

        <!-- Quick Tips -->
        <div style="background:#F8FAFC;border-radius:var(--radius);padding:16px 20px;margin-top:20px;border:1px solid var(--gray-200);">
            <h4 style="font-size:0.85rem;font-weight:600;color:var(--gray-700);margin:0 0 8px;">
                <i class="fas fa-tips" style="color:var(--primary);margin-right:6px;"></i>
                Template Tips
            </h4>
            <ul style="font-size:0.8rem;color:var(--gray-600);margin:0;padding-left:20px;">
                <li>Any election can be used as a template for creating new elections</li>
                <li>Templates copy all settings including type, cycle, and location selections</li>
                <li>You can edit the copied election before publishing</li>
                <li>Delete draft templates to keep your list organized</li>
                <li>Use templates to standardize elections across different cycles</li>
            </ul>
        </div>
    </div>
</main>

<style>
.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}

.btn-secondary:hover {
    background: var(--gray-200);
    transform: translateY(-1px);
}

.btn-danger:hover {
    background: #DC2626;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

@media (max-width: 768px) {
    div[style*="grid-template-columns:repeat(auto-fill,minmax(340px,1fr))"] {
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