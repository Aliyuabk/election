<?php
// ============================================================
// TENANT VIEW - SUPER ADMINISTRATOR
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

// Check role - only super_admin can access this page
if (SessionManager::get('role_level') !== 'super_admin') {
    header('Location: ../client-admin/');
    exit();
}

// Get database connection
$db = getDB();

// ============================================================
// GET TENANT ID
// ============================================================
$tenant_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($tenant_id <= 0) {
    header('Location: tenants.php');
    exit();
}

// ============================================================
// FETCH TENANT DETAILS
// ============================================================
$tenant = null;
try {
    $stmt = $db->prepare("
        SELECT 
            t.*,
            u.full_name as created_by_name,
            u.email as created_by_email,
            s.name as state_name,
            l.name as lga_name,
            (SELECT COUNT(*) FROM users WHERE tenant_id = t.id AND deleted_at IS NULL) as total_users,
            (SELECT COUNT(*) FROM elections WHERE tenant_id = t.id AND deleted_at IS NULL) as total_elections,
            (SELECT COUNT(*) FROM agent_assignments WHERE tenant_id = t.id) as total_agents,
            (SELECT COUNT(*) FROM activity_logs WHERE tenant_id = t.id) as total_activities
        FROM tenants t
        LEFT JOIN users u ON t.created_by = u.id
        LEFT JOIN states s ON t.state_id = s.id
        LEFT JOIN lgas l ON t.lga_id = l.id
        WHERE t.id = ? AND t.deleted_at IS NULL
    ");
    $stmt->execute([$tenant_id]);
    $tenant = $stmt->fetch();
} catch (Exception $e) {
    // Continue
}

if (!$tenant) {
    header('Location: tenants.php');
    exit();
}

// ============================================================
// FETCH RECENT ACTIVITIES FOR THIS TENANT
// ============================================================
$recent_activities = [];
try {
    $stmt = $db->prepare("
        SELECT a.*, u.full_name as user_name
        FROM activity_logs a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE a.tenant_id = ?
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$tenant_id]);
    $recent_activities = $stmt->fetchAll();
} catch (Exception $e) {
    // Continue
}

// ============================================================
// FETCH TENANT USERS
// ============================================================
$tenant_users = [];
try {
    $stmt = $db->prepare("
        SELECT id, first_name, last_name, email, phone, status, role_id, created_at
        FROM users
        WHERE tenant_id = ? AND deleted_at IS NULL
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$tenant_id]);
    $tenant_users = $stmt->fetchAll();
} catch (Exception $e) {
    // Continue
}

// ============================================================
// FETCH TENANT ELECTIONS
// ============================================================
$tenant_elections = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, type, status, election_date, created_at
        FROM elections
        WHERE tenant_id = ? AND deleted_at IS NULL
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$tenant_id]);
    $tenant_elections = $stmt->fetchAll();
} catch (Exception $e) {
    // Continue
}

// Get user info
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');

include 'includes/base.php';
include 'includes/sidebar.php';
?> 
<main class="main-content">
    <!-- Fixed Header -->
    <?php include 'includes/header.php'; ?>
    
    <!-- Main Content Inner -->
    <div class="main-content-inner">
        <!-- Tenant Header -->
        <div class="tenant-header">
            <div class="logo">
                <?php if (!empty($tenant['logo_url'])): ?>
                    <img src="<?php echo htmlspecialchars($tenant['logo_url']); ?>" alt="<?php echo htmlspecialchars($tenant['name']); ?>">
                <?php else: ?>
                    <?php echo strtoupper(substr($tenant['name'], 0, 2)); ?>
                <?php endif; ?>
            </div>
            <div class="info">
                <h2><?php echo htmlspecialchars($tenant['name']); ?></h2>
                <div class="meta">
                    <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($tenant['slug']); ?></span>
                    <span><i class="fas fa-calendar-alt"></i> Created: <?php echo date('M j, Y', strtotime($tenant['created_at'])); ?></span>
                    <span>
                        <i class="fas fa-circle" style="color:<?php echo $tenant['is_active'] ? '#10B981' : '#EF4444'; ?>;font-size:8px;"></i>
                        <?php echo $tenant['is_active'] ? 'Active' : 'Suspended'; ?>
                    </span>
                </div>
            </div>
            <div class="actions">
                <a href="tenants-edit.php?id=<?php echo $tenant['id']; ?>" class="btn-primary" style="padding:6px 14px;font-size:0.8rem;">
                    <i class="fas fa-edit"></i> Edit
                </a>
                <a href="tenants.php" class="btn-outline" style="padding:6px 14px;font-size:0.8rem;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Detail Grid -->
        <div class="detail-grid">
            <!-- Left Column: Details -->
            <div>
                <!-- Organization Details -->
                <div class="detail-card">
                    <div class="card-title">
                        <i class="fas fa-building" style="color:var(--primary);"></i> Organization Details
                    </div>
                    <div class="detail-row">
                        <span class="label">Organization Name</span>
                        <span class="value"><?php echo htmlspecialchars($tenant['name']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Slug</span>
                        <span class="value"><?php echo htmlspecialchars($tenant['slug']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Type</span>
                        <span class="value"><?php echo ucfirst(str_replace('_', ' ', $tenant['type'])); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Status</span>
                        <span class="value">
                            <span class="status-badge <?php echo $tenant['is_active'] ? 'active' : 'suspended'; ?>">
                                <i class="fas fa-circle" style="font-size:6px;"></i>
                                <?php echo $tenant['is_active'] ? 'Active' : 'Suspended'; ?>
                            </span>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Address</span>
                        <span class="value"><?php echo htmlspecialchars($tenant['address'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Location</span>
                        <span class="value">
                            <?php echo htmlspecialchars($tenant['state_name'] ?? 'N/A'); ?>
                            <?php if ($tenant['lga_name']): ?>
                                , <?php echo htmlspecialchars($tenant['lga_name']); ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Contact Email</span>
                        <span class="value"><?php echo htmlspecialchars($tenant['contact_email'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Contact Phone</span>
                        <span class="value"><?php echo htmlspecialchars($tenant['contact_phone'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Created By</span>
                        <span class="value">
                            <?php echo htmlspecialchars($tenant['created_by_name'] ?? 'System'); ?>
                            <span style="font-size:0.75rem;color:var(--gray-400);">
                                (<?php echo htmlspecialchars($tenant['created_by_email'] ?? ''); ?>)
                            </span>
                        </span>
                    </div>
                </div>

                <!-- Subscription Details -->
                <div class="detail-card" style="margin-top:16px;">
                    <div class="card-title">
                        <i class="fas fa-credit-card" style="color:var(--primary);"></i> Subscription Details
                    </div>
                    <div class="detail-row">
                        <span class="label">Plan</span>
                        <span class="value">
                            <span class="status-badge <?php echo $tenant['subscription_plan']; ?>">
                                <?php echo ucfirst($tenant['subscription_plan']); ?>
                            </span>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Status</span>
                        <span class="value">
                            <span class="status-badge <?php echo $tenant['subscription_status']; ?>">
                                <?php echo ucfirst($tenant['subscription_status']); ?>
                            </span>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Start Date</span>
                        <span class="value"><?php echo !empty($tenant['subscription_start']) ? date('M j, Y', strtotime($tenant['subscription_start'])) : 'N/A'; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">End Date</span>
                        <span class="value"><?php echo !empty($tenant['subscription_end']) ? date('M j, Y', strtotime($tenant['subscription_end'])) : 'N/A'; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Max Users</span>
                        <span class="value"><?php echo number_format($tenant['max_users']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Max Agents</span>
                        <span class="value"><?php echo number_format($tenant['max_agents']); ?></span>
                    </div>
                </div>

                <!-- Branding -->
                <div class="detail-card" style="margin-top:16px;">
                    <div class="card-title">
                        <i class="fas fa-palette" style="color:var(--primary);"></i> Branding
                    </div>
                    <div class="detail-row">
                        <span class="label">Primary Color</span>
                        <span class="value">
                            <span style="display:inline-block;width:20px;height:20px;border-radius:4px;background:<?php echo htmlspecialchars($tenant['primary_color']); ?>;border:1px solid var(--gray-200);vertical-align:middle;"></span>
                            <?php echo htmlspecialchars($tenant['primary_color']); ?>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Secondary Color</span>
                        <span class="value">
                            <span style="display:inline-block;width:20px;height:20px;border-radius:4px;background:<?php echo htmlspecialchars($tenant['secondary_color']); ?>;border:1px solid var(--gray-200);vertical-align:middle;"></span>
                            <?php echo htmlspecialchars($tenant['secondary_color']); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Right Column: Stats & Lists -->
            <div>
                <!-- Quick Stats -->
                <div class="detail-card">
                    <div class="card-title">
                        <i class="fas fa-chart-simple" style="color:var(--primary);"></i> Quick Stats
                    </div>
                    <div class="mini-stats">
                        <div class="mini-stat">
                            <div class="number"><?php echo number_format($tenant['total_users'] ?? 0); ?></div>
                            <div class="label">Total Users</div>
                        </div>
                        <div class="mini-stat">
                            <div class="number"><?php echo number_format($tenant['total_elections'] ?? 0); ?></div>
                            <div class="label">Elections</div>
                        </div>
                        <div class="mini-stat">
                            <div class="number"><?php echo number_format($tenant['total_agents'] ?? 0); ?></div>
                            <div class="label">Agents</div>
                        </div>
                        <div class="mini-stat">
                            <div class="number"><?php echo number_format($tenant['total_activities'] ?? 0); ?></div>
                            <div class="label">Activities</div>
                        </div>
                    </div>
                </div>

                <!-- Recent Users -->
                <div class="detail-card" style="margin-top:16px;">
                    <div class="card-title">
                        <i class="fas fa-users" style="color:var(--primary);"></i> Recent Users
                        <a href="tenants-users.php?id=<?php echo $tenant['id']; ?>" style="margin-left:auto;font-size:0.75rem;color:var(--primary);text-decoration:none;">View All →</a>
                    </div>
                    <?php if (count($tenant_users) > 0): ?>
                        <?php foreach ($tenant_users as $user): ?>
                            <div class="list-item">
                                <div>
                                    <div class="name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                                    <div class="sub"><?php echo htmlspecialchars($user['email']); ?></div>
                                </div>
                                <span class="status-badge <?php echo $user['status']; ?>" style="font-size:0.6rem;padding:2px 8px;">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state-small">
                            <i class="fas fa-users"></i>
                            No users found for this tenant.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Elections -->
                <div class="detail-card" style="margin-top:16px;">
                    <div class="card-title">
                        <i class="fas fa-vote-yea" style="color:var(--primary);"></i> Recent Elections
                        <a href="tenants-elections.php?id=<?php echo $tenant['id']; ?>" style="margin-left:auto;font-size:0.75rem;color:var(--primary);text-decoration:none;">View All →</a>
                    </div>
                    <?php if (count($tenant_elections) > 0): ?>
                        <?php foreach ($tenant_elections as $election): ?>
                            <div class="list-item">
                                <div>
                                    <div class="name"><?php echo htmlspecialchars($election['name']); ?></div>
                                    <div class="sub"><?php echo ucfirst(str_replace('_', ' ', $election['type'])); ?> · <?php echo date('M j, Y', strtotime($election['election_date'])); ?></div>
                                </div>
                                <span class="status-badge <?php echo $election['status']; ?>" style="font-size:0.6rem;padding:2px 8px;">
                                    <?php echo ucfirst($election['status']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state-small">
                            <i class="fas fa-vote-yea"></i>
                            No elections found for this tenant.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Activities -->
                <div class="detail-card" style="margin-top:16px;">
                    <div class="card-title">
                        <i class="fas fa-clock" style="color:var(--primary);"></i> Recent Activities
                        <a href="tenants-activities.php?id=<?php echo $tenant['id']; ?>" style="margin-left:auto;font-size:0.75rem;color:var(--primary);text-decoration:none;">View All →</a>
                    </div>
                    <?php if (count($recent_activities) > 0): ?>
                        <?php foreach (array_slice($recent_activities, 0, 5) as $activity): ?>
                            <div class="list-item" style="flex-direction:column;align-items:flex-start;padding:6px 0;">
                                <div class="name" style="font-size:0.8rem;"><?php echo htmlspecialchars($activity['description'] ?? 'Activity'); ?></div>
                                <div class="sub">
                                    <?php echo htmlspecialchars($activity['user_name'] ?? 'System'); ?>
                                    · <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state-small">
                            <i class="fas fa-clock"></i>
                            No recent activities.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// ============================================================
// PRELOADER
// ============================================================
window.addEventListener('load', function() {
    const preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(() => {
            preloader.style.display = 'none';
        }, 600);
    }
});

// ============================================================
// SIDEBAR TOGGLE (mobile)
// ============================================================
const sidebar = document.getElementById('sidebar');
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebarOverlay = document.getElementById('sidebarOverlay');
const dashboardHeader = document.getElementById('dashboardHeader');

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

window.addEventListener('resize', () => {
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
document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
    toggle.addEventListener('click', function(e) {
        e.preventDefault();
        const dropdownId = this.dataset.dropdown;
        const dropdown = document.getElementById(dropdownId);
        const chevron = this.querySelector('.chevron');
        
        if (dropdown) {
            dropdown.classList.toggle('open');
            if (chevron) chevron.classList.toggle('open');
        }
    });
});

// ============================================================
// PROFILE DROPDOWN
// ============================================================
const profileBtn = document.getElementById('profileBtn');
const profileMenu = document.getElementById('profileMenu');

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
// SEARCH (header)
// ============================================================
const searchInput = document.getElementById('searchInput');
const searchResults = document.getElementById('searchResults');
let searchTimeout;

if (searchInput) {
    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        clearTimeout(searchTimeout);
        
        if (query.length < 2) {
            if (searchResults) searchResults.classList.remove('active');
            return;
        }
        
        searchTimeout = setTimeout(() => {
            fetch(`search.php?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    if (searchResults) {
                        searchResults.innerHTML = '';
                        if (data && data.length > 0) {
                            data.forEach(item => {
                                const div = document.createElement('a');
                                div.className = 'result-item';
                                div.href = item.url || '#';
                                div.innerHTML = `
                                    <i class="fas ${item.icon || 'fa-file'}"></i>
                                    <span class="text-truncate">${item.label || item.name || ''}</span>
                                    <span class="result-type">${(item.type || '').charAt(0).toUpperCase() + (item.type || '').slice(1)}</span>
                                `;
                                searchResults.appendChild(div);
                            });
                            searchResults.classList.add('active');
                        } else {
                            searchResults.innerHTML = `
                                <div style="padding:12px;text-align:center;color:var(--gray-500);font-size:0.8rem;">
                                    <i class="fas fa-search" style="display:block;font-size:1.2rem;margin-bottom:4px;"></i>
                                    No results found
                                </div>
                            `;
                            searchResults.classList.add('active');
                        }
                    }
                })
                .catch(() => {});
        }, 300);
    });

    document.addEventListener('click', function(e) {
        const wrapper = document.querySelector('.search-wrapper');
        if (wrapper && !wrapper.contains(e.target) && searchResults) {
            searchResults.classList.remove('active');
        }
    });
}
</script>
</body>
</html>