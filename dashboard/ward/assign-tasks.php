<?php
// ============================================================
// WARD COORDINATOR - ASSIGN TASKS TO VOLUNTEERS
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

// Only Ward coordinator can access
if (SessionManager::get('role_level') !== 'ward') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$ward_id = SessionManager::get('ward_id');
$tenant_id = SessionManager::get('tenant_id');

// If ward_id is not set in session, try to get it from user record
if (empty($ward_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT ward_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['ward_id'])) {
            $ward_id = $user['ward_id'];
            SessionManager::set('ward_id', $ward_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching ward_id: " . $e->getMessage());
    }
}

$db = getDB();

// ============================================================
// FETCH WARD NAME
// ============================================================
$ward_name = 'Ward';
try {
    if ($ward_id) {
        $stmt = $db->prepare("SELECT name FROM wards WHERE id = ?");
        $stmt->execute([$ward_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $ward_name = $result['name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching ward name: " . $e->getMessage());
}

// ============================================================
// FETCH VOLUNTEERS
// ============================================================
$volunteers = [];
try {
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.user_code,
            u.email,
            u.phone,
            pu.name as pu_name
        FROM users u
        LEFT JOIN polling_units pu ON u.pu_id = pu.id
        WHERE u.tenant_id = ? AND u.ward_id = ? AND u.deleted_at IS NULL
        AND u.status = 'active'
        AND EXISTS (SELECT 1 FROM roles r WHERE r.id = u.role_id AND r.level = 'volunteer')
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $volunteers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching volunteers: " . $e->getMessage());
}

// ============================================================
// HANDLE TASK ASSIGNMENT
// ============================================================
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $volunteer_id = isset($_POST['volunteer_id']) ? (int)$_POST['volunteer_id'] : 0;
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $location = isset($_POST['location']) ? trim($_POST['location']) : '';
    $due_date = isset($_POST['due_date']) ? trim($_POST['due_date']) : '';
    $due_time = isset($_POST['due_time']) ? trim($_POST['due_time']) : '';
    
    if ($volunteer_id <= 0) {
        $error_message = "Please select a volunteer.";
    } elseif (empty($title)) {
        $error_message = "Please enter a task title.";
    } elseif (empty($description)) {
        $error_message = "Please enter a task description.";
    } else {
        try {
            $due_datetime = null;
            if (!empty($due_date) && !empty($due_time)) {
                $due_datetime = $due_date . ' ' . $due_time . ':00';
            }
            
            $stmt = $db->prepare("
                INSERT INTO volunteer_tasks (
                    volunteer_id, assigned_by, title, description, 
                    assigned_date, due_date, location, status, created_at
                ) VALUES (?, ?, ?, ?, NOW(), ?, ?, 'pending', NOW())
            ");
            $stmt->execute([
                $volunteer_id,
                $user_id,
                $title,
                $description,
                $due_datetime,
                $location
            ]);
            
            $task_id = $db->lastInsertId();
            
            logActivity($user_id, 'task_assigned', "Assigned task to volunteer ID: $volunteer_id - $title", 'volunteer_tasks', $task_id);
            
            $success_message = "Task assigned successfully!";
            
        } catch (Exception $e) {
            $error_message = "Error assigning task: " . $e->getMessage();
            error_log("Task assignment error: " . $e->getMessage());
        }
    }
}

$page_title = 'Assign Tasks';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.assign-task-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.assign-task-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.assign-task-header h2 i {
    color: var(--primary);
}

.task-form {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px;
    max-width: 700px;
}
.task-form .form-group {
    margin-bottom: 16px;
}
.task-form .form-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 4px;
}
.task-form .form-group label .required {
    color: #EF4444;
}
.task-form .form-group input[type="text"],
.task-form .form-group textarea,
.task-form .form-group select,
.task-form .form-group input[type="date"],
.task-form .form-group input[type="time"] {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    background: white;
}
.task-form .form-group textarea {
    resize: vertical;
    min-height: 100px;
    font-family: inherit;
}
.task-form .form-group .helper {
    font-size: 0.7rem;
    color: var(--gray-400);
    margin-top: 4px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid var(--gray-200);
}

.alert {
    padding: 12px 16px;
    border-radius: var(--radius);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-success {
    background: #ECFDF5;
    border: 1px solid #D1FAE5;
    color: #065F46;
}
.alert-danger {
    background: #FEF2F2;
    border: 1px solid #FEE2E2;
    color: #991B1B;
}
.alert i {
    font-size: 1.1rem;
}

@media (max-width: 768px) {
    .task-form {
        max-width: 100%;
    }
    .form-row {
        grid-template-columns: 1fr;
    }
    .form-actions {
        flex-direction: column;
    }
    .form-actions button,
    .form-actions a {
        width: 100%;
        text-align: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="assign-task-header">
            <div>
                <h2><i class="fas fa-tasks"></i> Assign Tasks to Volunteers</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward
                </p>
            </div>
            <div>
                <a href="volunteer-progress.php" class="btn-secondary-sm">
                    <i class="fas fa-chart-line"></i> View Progress
                </a>
                <a href="manage-pu-agents.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Task Form -->
        <div class="task-form">
            <form method="POST" action="" id="taskForm">
                <div class="form-group">
                    <label>Select Volunteer <span class="required">*</span></label>
                    <select name="volunteer_id" id="volunteer_id" required>
                        <option value="">-- Select Volunteer --</option>
                        <?php foreach ($volunteers as $volunteer): ?>
                            <option value="<?php echo $volunteer['id']; ?>">
                                <?php echo htmlspecialchars($volunteer['full_name']); ?> 
                                (<?php echo htmlspecialchars($volunteer['user_code']); ?>)
                                <?php if (!empty($volunteer['pu_name'])): ?>
                                    - <?php echo htmlspecialchars($volunteer['pu_name']); ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($volunteers)): ?>
                        <div class="helper" style="color:#EF4444;">No volunteers available. Please assign volunteers first.</div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Task Title <span class="required">*</span></label>
                    <input type="text" name="title" id="title" placeholder="Enter task title..." required>
                </div>

                <div class="form-group">
                    <label>Task Description <span class="required">*</span></label>
                    <textarea name="description" id="description" placeholder="Describe the task in detail..." required></textarea>
                </div>

                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" id="location" placeholder="Enter location (optional)">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Due Date</label>
                        <input type="date" name="due_date" id="due_date" min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Due Time</label>
                        <input type="time" name="due_time" id="due_time">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-plus"></i> Assign Task
                    </button>
                    <button type="reset" class="btn-secondary">
                        <i class="fas fa-undo"></i> Clear
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
// Validate form
document.getElementById('taskForm').addEventListener('submit', function(e) {
    const volunteer = document.getElementById('volunteer_id').value;
    const title = document.getElementById('title').value.trim();
    const description = document.getElementById('description').value.trim();
    
    if (!volunteer) {
        e.preventDefault();
        alert('Please select a volunteer.');
        return false;
    }
    if (!title) {
        e.preventDefault();
        alert('Please enter a task title.');
        return false;
    }
    if (!description) {
        e.preventDefault();
        alert('Please enter a task description.');
        return false;
    }
    return true;
});

// Preloader
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});

// Sidebar toggle
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

// Sidebar dropdowns
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

// Profile dropdown
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