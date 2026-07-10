<?php
// ============================================================
// STATE COORDINATOR - MANAGE LGA COORDINATORS
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

$user_name = SessionManager::get('user_name', 'State Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$state_id = SessionManager::get('state_id');

// If state_id is not set in session, try to get it from user record
if (empty($state_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT state_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['state_id'])) {
            $state_id = $user['state_id'];
            SessionManager::set('state_id', $state_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching state_id: " . $e->getMessage());
    }
}

$db = getDB();

// ============================================================
// GET FILTERS
// ============================================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$lga_filter = isset($_GET['lga_id']) ? (int)$_GET['lga_id'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// ============================================================
// FETCH STATE NAME
// ============================================================
$state_name = 'Unknown State';
try {
    if (!empty($state_id)) {
        $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
        $stmt->execute([$state_id]);
        $state = $stmt->fetch(PDO::FETCH_ASSOC);
        $state_name = $state['name'] ?? 'Unknown State';
    }
} catch (Exception $e) {
    error_log("Error fetching state: " . $e->getMessage());
}

// ============================================================
// FETCH LGAS FOR FILTER
// ============================================================
$lgas = [];
try {
    if (!empty($state_id)) {
        $stmt = $db->prepare("SELECT id, name FROM lgas WHERE state_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$state_id]);
        $lgas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("Error fetching LGAs: " . $e->getMessage());
}

// ============================================================
// FETCH LGA COORDINATORS
// ============================================================
$coordinators = [];
$total_coordinators = 0;

try {
    $sql = "
        SELECT 
            u.id,
            u.user_code,
            u.first_name,
            u.last_name,
            u.full_name,
            u.email,
            u.phone,
            u.status,
            u.last_login_at,
            u.created_at,
            r.name as role_name,
            l.id as lga_id,
            l.name as lga_name,
            (SELECT COUNT(*) FROM users u2 WHERE u2.lga_id = u.lga_id AND u2.deleted_at IS NULL AND u2.status = 'active') as total_agents
        FROM users u
        JOIN roles r ON u.role_id = r.id
        LEFT JOIN lgas l ON u.lga_id = l.id
        WHERE u.tenant_id = ?
        AND u.state_id = ?
        AND r.level = 'lga'
        AND u.deleted_at IS NULL
    ";
    
    $params = [$tenant_id, $state_id];
    
    if (!empty($search)) {
        $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($lga_filter)) {
        $sql .= " AND u.lga_id = ?";
        $params[] = $lga_filter;
    }
    
    if (!empty($status_filter)) {
        $sql .= " AND u.status = ?";
        $params[] = $status_filter;
    }
    
    $sql .= " ORDER BY u.last_name ASC, u.first_name ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $coordinators = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_coordinators = count($coordinators);
    
} catch (Exception $e) {
    error_log("Error fetching coordinators: " . $e->getMessage());
}

include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
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
    margin: 0;
}
.page-header h2 small {
    font-size: 0.8rem;
    font-weight: 400;
    color: var(--gray-500);
    display: block;
    margin-top: 2px;
}

.btn-primary-sm {
    padding: 8px 20px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}
.btn-primary-sm:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}

.btn-secondary-sm {
    padding: 8px 20px;
    background: var(--gray-100);
    color: var(--gray-700);
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}
.btn-secondary-sm:hover {
    background: var(--gray-200);
}

.filter-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 16px;
}
.filter-bar .search-box {
    flex: 1;
    min-width: 200px;
    display: flex;
    gap: 8px;
}
.filter-bar .search-box input {
    flex: 1;
    padding: 8px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
}
.filter-bar .search-box input:focus {
    outline: none;
    border-color: var(--primary);
}
.filter-bar select {
    padding: 8px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
    background: white;
}
.filter-bar .btn-filter {
    padding: 8px 20px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    font-family: 'Inter', sans-serif;
}
.filter-bar .btn-filter:hover {
    background: var(--primary-dark);
}
.filter-bar .btn-reset {
    padding: 8px 20px;
    background: var(--gray-100);
    color: var(--gray-600);
    border: 1px solid var(--gray-200);
    border-radius: 10px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    font-family: 'Inter', sans-serif;
}
.filter-bar .btn-reset:hover {
    background: var(--gray-200);
}

.table-wrapper {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
}
.table-wrapper table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}
.table-wrapper table th {
    background: var(--gray-50);
    padding: 12px 16px;
    text-align: left;
    font-weight: 600;
    font-size: 0.75rem;
    color: var(--gray-600);
    border-bottom: 1px solid var(--gray-200);
    white-space: nowrap;
}
.table-wrapper table td {
    padding: 12px 16px;
    border-bottom: 1px solid var(--gray-100);
    vertical-align: middle;
}
.table-wrapper table tr:hover td {
    background: var(--gray-50);
}
.table-wrapper table tr:last-child td {
    border-bottom: none;
}

.badge-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 600;
}
.badge-status .dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
}
.badge-status.active { background: #ECFDF5; color: #065F46; }
.badge-status.active .dot { background: #10B981; }
.badge-status.suspended { background: #FEF2F2; color: #991B1B; }
.badge-status.suspended .dot { background: #EF4444; }
.badge-status.pending { background: #FFFBEB; color: #92400E; }
.badge-status.pending .dot { background: #F59E0B; }

.btn-action {
    padding: 4px 10px;
    border-radius: 6px;
    border: none;
    font-size: 0.7rem;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-family: 'Inter', sans-serif;
}
.btn-action.btn-view {
    background: #EFF6FF;
    color: #3B82F6;
}
.btn-action.btn-view:hover {
    background: #DBEAFE;
}
.btn-action.btn-edit {
    background: #F5F3FF;
    color: #8B5CF6;
}
.btn-action.btn-edit:hover {
    background: #EDE9FE;
}
.btn-action.btn-suspend {
    background: #FEF2F2;
    color: #EF4444;
}
.btn-action.btn-suspend:hover {
    background: #FEE2E2;
}
.btn-action.btn-activate {
    background: #ECFDF5;
    color: #10B981;
}
.btn-action.btn-activate:hover {
    background: #D1FAE5;
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--gray-400);
}
.empty-state i {
    font-size: 3rem;
    display: block;
    margin-bottom: 12px;
    color: var(--gray-300);
}

.coordinator-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.8rem;
    color: white;
    flex-shrink: 0;
}
.coordinator-avatar.blue { background: #3B82F6; }
.coordinator-avatar.green { background: #10B981; }
.coordinator-avatar.purple { background: #8B5CF6; }
.coordinator-avatar.orange { background: #F59E0B; }
.coordinator-avatar.red { background: #EF4444; }
.coordinator-avatar.teal { background: #0D9488; }
.coordinator-avatar.pink { background: #EC4899; }

.user-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}
.user-cell .user-info .name {
    font-weight: 600;
    color: var(--gray-800);
}
.user-cell .user-info .email {
    font-size: 0.7rem;
    color: var(--gray-400);
}

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .table-wrapper {
        overflow-x: auto;
    }
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    .filter-bar .search-box {
        flex-direction: column;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-user-tie" style="color:var(--primary);margin-right:8px;"></i>
                    LGA Coordinators
                    <small><?php echo htmlspecialchars($state_name); ?> - Manage LGA Coordinators</small>
                </h2>
            </div>
            <div>
                <a href="lga-coordinators-assign.php" class="btn-primary-sm">
                    <i class="fas fa-user-plus"></i> Assign Coordinator
                </a>
                <a href="index.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:20px;">
            <div style="background:white;border-radius:10px;padding:14px 18px;border:1px solid var(--gray-200);text-align:center;">
                <div style="font-size:1.4rem;font-weight:700;color:var(--gray-800);"><?php echo number_format($total_coordinators); ?></div>
                <div style="font-size:0.7rem;color:var(--gray-500);">Total Coordinators</div>
            </div>
            <div style="background:white;border-radius:10px;padding:14px 18px;border:1px solid var(--gray-200);text-align:center;">
                <div style="font-size:1.4rem;font-weight:700;color:#10B981;">
                    <?php 
                    $active = array_filter($coordinators, function($c) { return $c['status'] === 'active'; });
                    echo number_format(count($active));
                    ?>
                </div>
                <div style="font-size:0.7rem;color:var(--gray-500);">Active</div>
            </div>
            <div style="background:white;border-radius:10px;padding:14px 18px;border:1px solid var(--gray-200);text-align:center;">
                <div style="font-size:1.4rem;font-weight:700;color:#F59E0B;">
                    <?php 
                    $pending = array_filter($coordinators, function($c) { return $c['status'] === 'pending'; });
                    echo number_format(count($pending));
                    ?>
                </div>
                <div style="font-size:0.7rem;color:var(--gray-500);">Pending</div>
            </div>
            <div style="background:white;border-radius:10px;padding:14px 18px;border:1px solid var(--gray-200);text-align:center;">
                <div style="font-size:1.4rem;font-weight:700;color:#EF4444;">
                    <?php 
                    $suspended = array_filter($coordinators, function($c) { return $c['status'] === 'suspended'; });
                    echo number_format(count($suspended));
                    ?>
                </div>
                <div style="font-size:0.7rem;color:var(--gray-500);">Suspended</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <form method="GET" action="" class="filter-bar">
            <div class="search-box">
                <input type="text" name="search" placeholder="Search coordinators..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
            </div>
            <select name="lga_id">
                <option value="">All LGAs</option>
                <?php foreach ($lgas as $lga): ?>
                    <option value="<?php echo $lga['id']; ?>" <?php echo $lga_filter == $lga['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($lga['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="status">
                <option value="">All Status</option>
                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
            </select>
            <a href="lga-coordinators.php" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
        </form>

        <!-- Table -->
        <div class="table-wrapper">
            <?php if (count($coordinators) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>Coordinator</th>
                            <th>LGA</th>
                            <th>Phone</th>
                            <th>Agents</th>
                            <th>Last Login</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $sn = 1;
                        $avatar_colors = ['blue', 'green', 'purple', 'orange', 'red', 'teal', 'pink'];
                        ?>
                        <?php foreach ($coordinators as $coordinator): ?>
                            <?php 
                            $color_idx = ($coordinator['id'] ?? 0) % count($avatar_colors);
                            $avatar_color = $avatar_colors[$color_idx];
                            $initials = strtoupper(substr($coordinator['first_name'] ?? '', 0, 1) . substr($coordinator['last_name'] ?? '', 0, 1));
                            ?>
                            <tr>
                                <td><?php echo $sn++; ?></td>
                                <td>
                                    <div class="user-cell">
                                        <div class="coordinator-avatar <?php echo $avatar_color; ?>">
                                            <?php echo $initials ?: '?'; ?>
                                        </div>
                                        <div class="user-info">
                                            <div class="name"><?php echo htmlspecialchars($coordinator['full_name'] ?? $coordinator['first_name'] . ' ' . $coordinator['last_name']); ?></div>
                                            <div class="email"><?php echo htmlspecialchars($coordinator['email'] ?? ''); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($coordinator['lga_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($coordinator['phone'] ?? 'N/A'); ?></td>
                                <td><?php echo number_format($coordinator['total_agents'] ?? 0); ?></td>
                                <td>
                                    <?php if (!empty($coordinator['last_login_at'])): ?>
                                        <?php echo date('M j, Y', strtotime($coordinator['last_login_at'])); ?>
                                    <?php else: ?>
                                        <span style="color:var(--gray-400);">Never</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-status <?php echo $coordinator['status']; ?>">
                                        <span class="dot"></span>
                                        <?php echo ucfirst($coordinator['status'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="coordinator-view.php?id=<?php echo $coordinator['id']; ?>" class="btn-action btn-view">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="coordinator-edit.php?id=<?php echo $coordinator['id']; ?>" class="btn-action btn-edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($coordinator['status'] === 'active'): ?>
                                        <button onclick="confirmAction('suspend', <?php echo $coordinator['id']; ?>)" class="btn-action btn-suspend">
                                            <i class="fas fa-pause"></i>
                                        </button>
                                    <?php elseif ($coordinator['status'] === 'suspended'): ?>
                                        <button onclick="confirmAction('activate', <?php echo $coordinator['id']; ?>)" class="btn-action btn-activate">
                                            <i class="fas fa-play"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-user-tie"></i>
                    <p>No LGA coordinators found.</p>
                    <?php if (!empty($search) || !empty($lga_filter) || !empty($status_filter)): ?>
                        <p style="font-size:0.8rem;">Try adjusting your filters.</p>
                    <?php else: ?>
                        <p style="font-size:0.8rem;">
                            <a href="lga-coordinators-assign.php" style="color:var(--primary);text-decoration:none;font-weight:600;">
                                Assign a coordinator
                            </a> to get started.
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- ============================================================
CONFIRMATION MODAL
============================================================ -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="confirmTitle">Confirm Action</h3>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="confirmBody">
            <p>Are you sure you want to perform this action?</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            <form method="POST" action="" id="confirmForm">
                <input type="hidden" name="action" id="confirmAction" value="">
                <input type="hidden" name="user_id" id="confirmUserId" value="">
                <button type="submit" class="btn btn-danger" id="confirmBtn">Confirm</button>
            </form>
        </div>
    </div>
</div>

<style>
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.4);
    z-index: 300;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.modal-overlay.active { display: flex; }
.modal {
    background: white;
    border-radius: var(--radius);
    max-width: 440px;
    width: 100%;
    padding: 28px 32px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.15);
    animation: modalIn 0.25s ease;
}
@keyframes modalIn {
    from { transform: scale(0.95) translateY(10px); opacity: 0; }
    to { transform: scale(1) translateY(0); opacity: 1; }
}
.modal .modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}
.modal .modal-header h3 {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--gray-800);
}
.modal .modal-header .close-btn {
    background: none;
    border: none;
    font-size: 1.4rem;
    color: var(--gray-400);
    cursor: pointer;
    transition: var(--transition);
    padding: 0 4px;
}
.modal .modal-header .close-btn:hover {
    color: var(--gray-600);
}
.modal .modal-body {
    margin-bottom: 20px;
    color: var(--gray-600);
    font-size: 0.9rem;
    line-height: 1.6;
}
.modal .modal-body strong {
    color: var(--gray-800);
}
.modal .modal-footer {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}
.modal .modal-footer .btn {
    padding: 8px 20px;
    border-radius: 8px;
    border: none;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}
.modal .modal-footer .btn-secondary {
    background: var(--gray-100);
    color: var(--gray-600);
}
.modal .modal-footer .btn-secondary:hover {
    background: var(--gray-200);
}
.modal .modal-footer .btn-danger {
    background: var(--danger);
    color: white;
}
.modal .modal-footer .btn-danger:hover {
    background: #DC2626;
}
.modal .modal-footer .btn-primary {
    background: var(--primary);
    color: white;
}
.modal .modal-footer .btn-primary:hover {
    background: var(--primary-dark);
}

@media (max-width: 480px) {
    .modal { padding: 20px; margin: 10px; }
    .modal .modal-footer { flex-direction: column; }
    .modal .modal-footer .btn { width: 100%; justify-content: center; }
}
</style>

<script>
// ============================================================
// PRELOADER
// ============================================================
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() {
            preloader.style.display = 'none';
        }, 600);
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
// CONFIRMATION MODAL
// ============================================================
function confirmAction(action, userId) {
    var modal = document.getElementById('confirmModal');
    
    if (action === 'suspend') {
        document.getElementById('confirmTitle').textContent = 'Suspend Coordinator';
        document.getElementById('confirmBody').innerHTML = 'Are you sure you want to suspend this LGA coordinator? The user will lose access to the platform.';
        document.getElementById('confirmAction').value = 'suspend';
        document.getElementById('confirmBtn').className = 'btn btn-danger';
        document.getElementById('confirmBtn').textContent = 'Suspend';
    } else if (action === 'activate') {
        document.getElementById('confirmTitle').textContent = 'Activate Coordinator';
        document.getElementById('confirmBody').innerHTML = 'Are you sure you want to activate this LGA coordinator? The user will regain full access.';
        document.getElementById('confirmAction').value = 'activate';
        document.getElementById('confirmBtn').className = 'btn btn-primary';
        document.getElementById('confirmBtn').textContent = 'Activate';
    }
    
    document.getElementById('confirmUserId').value = userId;
    modal.classList.add('active');
}

function closeModal() {
    document.getElementById('confirmModal').classList.remove('active');
}
</script>
</body>
</html>