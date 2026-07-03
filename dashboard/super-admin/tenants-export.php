<?php
// ============================================================
// TENANT EXPORT - SUPER ADMINISTRATOR
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
// GET EXPORT PARAMETERS
// ============================================================
$format = isset($_GET['format']) ? $_GET['format'] : 'csv';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$plan_filter = isset($_GET['plan']) ? $_GET['plan'] : '';

// ============================================================
// BUILD QUERY
// ============================================================
$where_conditions = ["t.deleted_at IS NULL"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(t.name LIKE ? OR t.slug LIKE ? OR t.contact_email LIKE ? OR t.contact_phone LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($status_filter)) {
    if ($status_filter === 'active') {
        $where_conditions[] = "t.is_active = 1";
    } elseif ($status_filter === 'suspended') {
        $where_conditions[] = "t.is_active = 0";
    } elseif ($status_filter === 'trial') {
        $where_conditions[] = "t.subscription_status = 'trial'";
    } elseif ($status_filter === 'expired') {
        $where_conditions[] = "t.subscription_status = 'expired'";
    } elseif ($status_filter === 'active_subscription') {
        $where_conditions[] = "t.subscription_status = 'active'";
    }
}

if (!empty($plan_filter)) {
    $where_conditions[] = "t.subscription_plan = ?";
    $params[] = $plan_filter;
}

$where_clause = implode(" AND ", $where_conditions);

// Fetch all tenants (no pagination limit for export)
$sql = "
    SELECT 
        t.id,
        t.uuid,
        t.name,
        t.slug,
        t.type,
        t.subscription_plan,
        t.subscription_status,
        t.subscription_start,
        t.subscription_end,
        t.max_users,
        t.max_agents,
        t.is_active,
        t.contact_email,
        t.contact_phone,
        t.address,
        t.primary_color,
        t.secondary_color,
        t.created_at,
        u.full_name as created_by_name,
        u.email as created_by_email,
        s.name as state_name,
        l.name as lga_name,
        (SELECT COUNT(*) FROM users WHERE tenant_id = t.id AND deleted_at IS NULL) as total_users,
        (SELECT COUNT(*) FROM elections WHERE tenant_id = t.id AND deleted_at IS NULL) as total_elections,
        (SELECT COUNT(*) FROM agent_assignments WHERE tenant_id = t.id) as total_agents
    FROM tenants t
    LEFT JOIN users u ON t.created_by = u.id
    LEFT JOIN states s ON t.state_id = s.id
    LEFT JOIN lgas l ON t.lga_id = l.id
    WHERE $where_clause
    ORDER BY t.created_at DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$tenants = $stmt->fetchAll();

// ============================================================
// GENERATE EXPORT
// ============================================================
$filename = 'tenants_export_' . date('Y-m-d_H-i-s');

if ($format === 'csv') {
    // CSV Export
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    $headers = [
        'ID', 'UUID', 'Organization Name', 'Slug', 'Type', 
        'Subscription Plan', 'Subscription Status', 'Subscription Start', 'Subscription End',
        'Max Users', 'Max Agents', 'Status', 
        'Contact Email', 'Contact Phone', 'Address',
        'State', 'LGA',
        'Primary Color', 'Secondary Color',
        'Total Users', 'Total Elections', 'Total Agents',
        'Created By', 'Created By Email', 'Created At'
    ];
    fputcsv($output, $headers);
    
    // Data
    foreach ($tenants as $tenant) {
        $row = [
            $tenant['id'],
            $tenant['uuid'],
            $tenant['name'],
            $tenant['slug'],
            $tenant['type'],
            $tenant['subscription_plan'],
            $tenant['subscription_status'],
            $tenant['subscription_start'] ?? '',
            $tenant['subscription_end'] ?? '',
            $tenant['max_users'],
            $tenant['max_agents'],
            $tenant['is_active'] ? 'Active' : 'Suspended',
            $tenant['contact_email'] ?? '',
            $tenant['contact_phone'] ?? '',
            $tenant['address'] ?? '',
            $tenant['state_name'] ?? '',
            $tenant['lga_name'] ?? '',
            $tenant['primary_color'],
            $tenant['secondary_color'],
            $tenant['total_users'] ?? 0,
            $tenant['total_elections'] ?? 0,
            $tenant['total_agents'] ?? 0,
            $tenant['created_by_name'] ?? '',
            $tenant['created_by_email'] ?? '',
            $tenant['created_at']
        ];
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
    
} elseif ($format === 'excel') {
    // Excel Export (using simple HTML table with xls extension)
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    
    ?>
    <html xmlns:o="urn:schemas-microsoft-com:office:office" 
          xmlns:x="urn:schemas-microsoft-com:office:excel" 
          xmlns="http://www.w3.org/TR/REC-html40">
    <head>
        <meta charset="UTF-8">
        <!--[if gte mso 9]>
        <xml>
            <x:ExcelWorkbook>
                <x:ExcelWorksheets>
                    <x:ExcelWorksheet>
                        <x:Name>Tenants</x:Name>
                        <x:WorksheetOptions>
                            <x:DisplayGridlines/>
                        </x:WorksheetOptions>
                    </x:ExcelWorksheet>
                </x:ExcelWorksheets>
            </x:ExcelWorkbook>
        </xml>
        <![endif]-->
        <style>
            th { background-color: #0F4C81; color: #ffffff; font-weight: bold; padding: 8px; border: 1px solid #ccc; }
            td { padding: 6px 8px; border: 1px solid #ccc; }
            .active { color: #10B981; }
            .suspended { color: #EF4444; }
        </style>
    </head>
    <body>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Organization</th>
                    <th>Slug</th>
                    <th>Type</th>
                    <th>Plan</th>
                    <th>Status</th>
                    <th>Subscription Status</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Users</th>
                    <th>Elections</th>
                    <th>Agents</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>State</th>
                    <th>LGA</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tenants as $tenant): ?>
                    <tr>
                        <td><?php echo $tenant['id']; ?></td>
                        <td><?php echo htmlspecialchars($tenant['name']); ?></td>
                        <td><?php echo htmlspecialchars($tenant['slug']); ?></td>
                        <td><?php echo htmlspecialchars($tenant['type']); ?></td>
                        <td><?php echo htmlspecialchars($tenant['subscription_plan']); ?></td>
                        <td class="<?php echo $tenant['is_active'] ? 'active' : 'suspended'; ?>">
                            <?php echo $tenant['is_active'] ? 'Active' : 'Suspended'; ?>
                        </td>
                        <td><?php echo htmlspecialchars($tenant['subscription_status']); ?></td>
                        <td><?php echo $tenant['subscription_start'] ?? ''; ?></td>
                        <td><?php echo $tenant['subscription_end'] ?? ''; ?></td>
                        <td><?php echo $tenant['total_users'] ?? 0; ?></td>
                        <td><?php echo $tenant['total_elections'] ?? 0; ?></td>
                        <td><?php echo $tenant['total_agents'] ?? 0; ?></td>
                        <td><?php echo htmlspecialchars($tenant['contact_email'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($tenant['contact_phone'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($tenant['state_name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($tenant['lga_name'] ?? ''); ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($tenant['created_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </body>
    </html>
    <?php
    exit();
    
} elseif ($format === 'json') {
    // JSON Export
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.json"');
    
    $data = [];
    foreach ($tenants as $tenant) {
        $data[] = [
            'id' => $tenant['id'],
            'uuid' => $tenant['uuid'],
            'name' => $tenant['name'],
            'slug' => $tenant['slug'],
            'type' => $tenant['type'],
            'subscription_plan' => $tenant['subscription_plan'],
            'subscription_status' => $tenant['subscription_status'],
            'subscription_start' => $tenant['subscription_start'],
            'subscription_end' => $tenant['subscription_end'],
            'max_users' => (int)$tenant['max_users'],
            'max_agents' => (int)$tenant['max_agents'],
            'is_active' => (bool)$tenant['is_active'],
            'contact_email' => $tenant['contact_email'],
            'contact_phone' => $tenant['contact_phone'],
            'address' => $tenant['address'],
            'state' => $tenant['state_name'],
            'lga' => $tenant['lga_name'],
            'primary_color' => $tenant['primary_color'],
            'secondary_color' => $tenant['secondary_color'],
            'stats' => [
                'total_users' => (int)($tenant['total_users'] ?? 0),
                'total_elections' => (int)($tenant['total_elections'] ?? 0),
                'total_agents' => (int)($tenant['total_agents'] ?? 0)
            ],
            'created_by' => $tenant['created_by_name'],
            'created_by_email' => $tenant['created_by_email'],
            'created_at' => $tenant['created_at']
        ];
    }
    
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit();
}

// If no format specified, show export options page
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
        <div class="export-container">
            <div class="page-header" style="margin-bottom:16px;">
                <div>
                    <h2>
                        <i class="fas fa-file-export" style="color:var(--primary);margin-right:8px;"></i> Export Tenants
                        <small>Export tenant data in your preferred format</small>
                    </h2>
                </div>
                <a href="tenants.php" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Tenants
                </a>
            </div>

            <div class="export-card">
                <div class="icon">
                    <i class="fas fa-file-export"></i>
                </div>
                <h2>Export Tenant Data</h2>
                <p>Export all tenant information including organization details, subscription, and statistics.</p>

                <div class="export-options">
                    <a href="?format=csv<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($plan_filter) ? '&plan=' . urlencode($plan_filter) : ''; ?>" class="export-option">
                        <i class="fas fa-file-csv"></i>
                        <span class="label">CSV</span>
                        <span class="desc">Comma Separated Values</span>
                    </a>
                    <a href="?format=excel<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($plan_filter) ? '&plan=' . urlencode($plan_filter) : ''; ?>" class="export-option">
                        <i class="fas fa-file-excel"></i>
                        <span class="label">Excel</span>
                        <span class="desc">Microsoft Excel (.xls)</span>
                    </a>
                    <a href="?format=json<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($plan_filter) ? '&plan=' . urlencode($plan_filter) : ''; ?>" class="export-option">
                        <i class="fas fa-file-code"></i>
                        <span class="label">JSON</span>
                        <span class="desc">JavaScript Object Notation</span>
                    </a>
                </div>

                <div class="export-filters">
                    <p style="font-size:0.85rem;color:var(--gray-500);margin-bottom:10px;">Apply filters before exporting:</p>
                    <form method="GET" action="" class="filter-row">
                        <input type="text" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                        <select name="status">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                            <option value="trial" <?php echo $status_filter === 'trial' ? 'selected' : ''; ?>>Trial</option>
                            <option value="expired" <?php echo $status_filter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                        </select>
                        <select name="plan">
                            <option value="">All Plans</option>
                            <option value="free" <?php echo $plan_filter === 'free' ? 'selected' : ''; ?>>Free</option>
                            <option value="basic" <?php echo $plan_filter === 'basic' ? 'selected' : ''; ?>>Basic</option>
                            <option value="standard" <?php echo $plan_filter === 'standard' ? 'selected' : ''; ?>>Standard</option>
                            <option value="premium" <?php echo $plan_filter === 'premium' ? 'selected' : ''; ?>>Premium</option>
                            <option value="enterprise" <?php echo $plan_filter === 'enterprise' ? 'selected' : ''; ?>>Enterprise</option>
                        </select>
                        <button type="submit" class="btn-primary" style="padding:8px 16px;font-size:0.82rem;">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <?php if (!empty($search) || !empty($status_filter) || !empty($plan_filter)): ?>
                            <a href="tenants-export.php" class="btn-outline" style="padding:8px 14px;font-size:0.82rem;">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        <?php endif; ?>
                    </form>
                    <div style="margin-top:10px;font-size:0.8rem;color:var(--gray-400);">
                        <i class="fas fa-info-circle"></i> 
                        Exporting <?php echo count($tenants); ?> tenants
                        <?php if (!empty($search) || !empty($status_filter) || !empty($plan_filter)): ?>
                            (filtered)
                        <?php endif; ?>
                    </div>
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