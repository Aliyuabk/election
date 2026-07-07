<?php
// ============================================================
// STATE COORDINATOR - ACTIVATE COORDINATOR
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

// Only state coordinator can access
if (SessionManager::get('role_level') !== 'state') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$user_email = SessionManager::get('user_email');
$state_id = SessionManager::get('state_id');
$tenant_id = SessionManager::get('tenant_id');

// Get coordinator ID
$coordinator_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($coordinator_id <= 0) {
    header('Location: monitor-lgas.php?error=invalid_coordinator');
    exit();
}

$db = getDB();

// ============================================================
// FETCH COORDINATOR DATA
// ============================================================
$coordinator = null;
$back_url = 'state-coordinators.php';

try {
    $stmt = $db->prepare("
        SELECT 
            u.*,
            r.name as role_name,
            r.level as role_level,
            CASE 
                WHEN u.jurisdiction_type = 'state' THEN (SELECT name FROM states WHERE id = u.jurisdiction_id)
                WHEN u.jurisdiction_type = 'lga' THEN (SELECT name FROM lgas WHERE id = u.jurisdiction_id)
                WHEN u.jurisdiction_type = 'ward' THEN (SELECT name FROM wards WHERE id = u.jurisdiction_id)
                WHEN u.jurisdiction_type = 'pu' THEN (SELECT name FROM polling_units WHERE id = u.jurisdiction_id)
                ELSE 'Unknown'
            END as jurisdiction_name
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE u.id = ? AND u.tenant_id = ?
    ");
    $stmt->execute([$coordinator_id, $tenant_id]);
    $coordinator = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$coordinator) {
        header('Location: monitor-lgas.php?error=coordinator_not_found');
        exit();
    }
    
    // Determine back URL based on role level
    if ($coordinator['role_level'] === 'state') {
        $back_url = "state-coordinators.php";
    } elseif ($coordinator['role_level'] === 'lga') {
        $back_url = "lga-coordinators.php?id=" . $coordinator['jurisdiction_id'];
    } elseif ($coordinator['role_level'] === 'ward') {
        $back_url = "ward-dashboard.php?id=" . $coordinator['jurisdiction_id'];
    } elseif ($coordinator['role_level'] === 'pu_agent') {
        $back_url = "pu-agents.php?pu=" . $coordinator['jurisdiction_id'];
    } else {
        $back_url = "state-coordinators.php";
    }
    
} catch (Exception $e) {
    error_log("Coordinator Activate Error: " . $e->getMessage());
    header('Location: monitor-lgas.php?error=database_error');
    exit();
}

// ============================================================
// CHECK IF COORDINATOR IS ACTIVE
// ============================================================
if ($coordinator['status'] === 'active') {
    header("Location: " . $back_url . "&already_active=1");
    exit();
}

// ============================================================
// PROCESS ACTIVATION
// ============================================================
$message = '';
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $activate_reason = trim($_POST['activate_reason'] ?? '');
    $confirm = isset($_POST['confirm']) ? true : false;
    
    if (!$confirm) {
        $error = 'Please confirm that you want to activate this coordinator';
    } elseif (empty($activate_reason)) {
        $error = 'Please provide a reason for activation';
    } else {
        try {
            // Update user status
            $stmt = $db->prepare("
                UPDATE users 
                SET status = 'active', 
                    updated_at = NOW() 
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$coordinator_id, $tenant_id]);
            
            // Log activity
            $log_stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, tenant_id, activity_type, description, entity_type, entity_id, created_at)
                VALUES (?, ?, 'user_activated', ?, 'user', ?, NOW())
            ");
            $log_stmt->execute([
                $user_id,
                $tenant_id,
                "Activated coordinator: " . $coordinator['full_name'] . " - Reason: $activate_reason",
                $coordinator_id
            ]);
            
            // Log security event
            $security_stmt = $db->prepare("
                INSERT INTO security_events (tenant_id, user_id, event_type, description, ip_address, created_at)
                VALUES (?, ?, 'user_activated', ?, ?, NOW())
            ");
            $security_stmt->execute([
                $tenant_id,
                $coordinator_id,
                "Coordinator activated by " . $user_name . " - Reason: $activate_reason",
                getClientIP()
            ]);
            
            $success = true;
            $message = "Coordinator " . $coordinator['full_name'] . " has been activated successfully!";
            
            // Redirect after success
            header("Location: " . $back_url . "&activated=1");
            exit();
            
        } catch (Exception $e) {
            $error = 'Failed to activate coordinator: ' . $e->getMessage();
            error_log("Activate Coordinator Error: " . $e->getMessage());
        }
    }
}

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'Activate Coordinator';
$page_subtitle = $coordinator['full_name'] ?? 'Coordinator';
?>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Breadcrumb -->
        <div class="welcome-section">
            <div class="breadcrumb">
                <i class="fas fa-home"></i>
                <a href="index.php" style="text-decoration:none;color:var(--gray-500);">Dashboard</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <a href="<?php echo $back_url; ?>" style="text-decoration:none;color:var(--gray-500);">Coordinators</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="font-weight:600;color:var(--gray-800);">Activate Coordinator</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;color:#10B981;">
                        <i class="fas fa-user-check"></i>
                        Activate Coordinator
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <i class="fas fa-user"></i> 
                        <?php echo htmlspecialchars($coordinator['full_name']); ?> • 
                        <?php echo htmlspecialchars($coordinator['role_name']); ?>
                    </p>
                </div>
                <a href="<?php echo $back_url; ?>" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Info Box -->
        <div style="background:#F0FDF4;border-radius:var(--radius);padding:16px 20px;margin-bottom:20px;border:1px solid #A7F3D0;display:flex;align-items:flex-start;gap:12px;">
            <i class="fas fa-info-circle" style="color:#10B981;font-size:1.2rem;margin-top:2px;"></i>
            <div>
                <h4 style="font-size:0.9rem;font-weight:600;color:#065F46;margin:0 0 4px;">Activation Notice</h4>
                <p style="font-size:0.8rem;color:#065F46;margin:0;">
                    Activating this coordinator will:
                </p>
                <ul style="font-size:0.8rem;color:#065F46;margin:4px 0 0;padding-left:20px;">
                    <li>Restore full access to the platform</li>
                    <li>Allow them to submit results and report incidents</li>
                    <li>Reinstate all active assignments</li>
                    <li>This action can be reversed by suspending the account</li>
                </ul>
            </div>
        </div>

        <!-- Coordinator Info -->
        <div style="background:white;border-radius:var(--radius);padding:16px 20px;border:1px solid var(--gray-200);margin-bottom:20px;">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
                <div>
                    <label style="display:block;font-size:0.65rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.03em;">Name</label>
                    <div style="font-weight:500;"><?php echo htmlspecialchars($coordinator['full_name']); ?></div>
                </div>
                <div>
                    <label style="display:block;font-size:0.65rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.03em;">Role</label>
                    <div style="font-weight:500;"><?php echo htmlspecialchars($coordinator['role_name']); ?></div>
                </div>
                <div>
                    <label style="display:block;font-size:0.65rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.03em;">Email</label>
                    <div style="font-weight:500;"><?php echo htmlspecialchars($coordinator['email']); ?></div>
                </div>
                <div>
                    <label style="display:block;font-size:0.65rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.03em;">Jurisdiction</label>
                    <div style="font-weight:500;"><?php echo htmlspecialchars($coordinator['jurisdiction_name']); ?></div>
                </div>
                <div>
                    <label style="display:block;font-size:0.65rem;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.03em;">Current Status</label>
                    <div>
                        <span style="display:inline-block;padding:2px 12px;border-radius:12px;font-size:0.7rem;font-weight:600;background:#FEE2E2;color:#991B1B;">
                            <?php echo ucfirst($coordinator['status'] ?? 'Unknown'); ?>
                        </span>
                        <i class="fas fa-arrow-right" style="margin:0 8px;color:var(--gray-400);"></i>
                        <span style="display:inline-block;padding:2px 12px;border-radius:12px;font-size:0.7rem;font-weight:600;background:#D1FAE5;color:#065F46;">
                            Active
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($message && $success): ?>
            <div style="background:#D1FAE5;color:#065F46;padding:12px 20px;border-radius:10px;margin-bottom:20px;border:1px solid #A7F3D0;display:flex;align-items:center;gap:10px;">
                <i class="fas fa-check-circle" style="font-size:1.2rem;"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="background:#FEE2E2;color:#991B1B;padding:12px 20px;border-radius:10px;margin-bottom:20px;border:1px solid #FECACA;display:flex;align-items:center;gap:10px;">
                <i class="fas fa-exclamation-circle" style="font-size:1.2rem;"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Activate Form -->
        <form method="POST" action="" style="background:white;border-radius:var(--radius);padding:24px;border:1px solid var(--gray-200);">
            <!-- Reason -->
            <div style="margin-bottom:16px;">
                <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                    Reason for Activation <span style="color:#EF4444;">*</span>
                </label>
                <textarea name="activate_reason" class="form-control" required rows="4"
                          placeholder="Provide detailed reason for activation..."
                          style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;resize:vertical;transition:var(--transition);"><?php echo htmlspecialchars($_POST['activate_reason'] ?? ''); ?></textarea>
                <div style="font-size:0.65rem;color:var(--gray-400);margin-top:4px;">
                    <i class="fas fa-info-circle"></i> This reason will be logged for audit purposes
                </div>
            </div>

            <!-- Confirmation -->
            <div style="margin-bottom:16px;padding:12px 16px;background:#F0FDF4;border-radius:8px;border:1px solid #A7F3D0;">
                <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;">
                    <input type="checkbox" name="confirm" value="1" style="margin-top:2px;" <?php echo isset($_POST['confirm']) ? 'checked' : ''; ?>>
                    <span style="font-size:0.85rem;color:#065F46;">
                        <strong>I confirm</strong> that I want to activate this coordinator. I understand that this action will restore full access and allow the coordinator to perform all actions.
                    </span>
                </label>
            </div>

            <!-- Form Actions -->
            <div style="display:flex;gap:12px;margin-top:24px;padding-top:20px;border-top:1px solid var(--gray-200);flex-wrap:wrap;">
                <button type="submit" class="btn-success" style="padding:10px 32px;background:#10B981;color:white;border:none;border-radius:10px;font-family:'Inter',sans-serif;font-weight:600;font-size:0.85rem;cursor:pointer;transition:var(--transition);display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-user-check"></i> Activate Coordinator
                </button>
                <a href="<?php echo $back_url; ?>" class="btn-secondary" style="padding:10px 32px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-family:'Inter',sans-serif;font-weight:500;font-size:0.85rem;cursor:pointer;transition:var(--transition);display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>

        <!-- Quick Actions -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-top:16px;">
            <a href="coordinator-view.php?id=<?php echo $coordinator_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-eye" style="color:var(--primary);"></i>
                <span>View Profile</span>
            </a>
            <a href="coordinator-edit.php?id=<?php echo $coordinator_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-edit" style="color:var(--secondary);"></i>
                <span>Edit Profile</span>
            </a>
            <a href="coordinator-activity.php?id=<?php echo $coordinator_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-clock" style="color:var(--warning);"></i>
                <span>View Activity</span>
            </a>
        </div>
    </div>
</main>

<style>
.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.btn-success:hover {
    background: #059669;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.btn-secondary:hover {
    background: var(--gray-200);
    transform: translateY(-2px);
}

.quick-action-btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-hover);
    border-color: var(--primary);
}

@media (max-width: 768px) {
    div[style*="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))"] {
        grid-template-columns: 1fr 1fr !important;
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