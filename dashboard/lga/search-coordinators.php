<?php
// ============================================================
// LGA COORDINATOR - SEARCH COORDINATORS
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'lga') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'LGA Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$lga_id = SessionManager::get('lga_id');

if (empty($lga_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT lga_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['lga_id'])) {
            $lga_id = $user['lga_id'];
            SessionManager::set('lga_id', $lga_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching lga_id: " . $e->getMessage());
    }
}

$db = getDB();

// Get search query
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$search_results = [];

if (!empty($query) && strlen($query) >= 2) {
    try {
        $searchTerm = '%' . $query . '%';
        
        // Search in users (coordinators and agents) within the LGA
        $stmt = $db->prepare("
            SELECT 
                u.id,
                u.user_code,
                u.first_name,
                u.last_name,
                u.email,
                u.phone,
                u.status,
                u.created_at,
                r.name as role_name,
                r.level as role_level,
                w.name as ward_name,
                pu.name as pu_name,
                l.name as lga_name,
                CASE 
                    WHEN r.level = 'lga' THEN 'LGA Coordinator'
                    WHEN r.level = 'ward' THEN 'Ward Coordinator'
                    WHEN r.level = 'pu_agent' THEN 'PU Agent'
                    WHEN r.level = 'party_agent' THEN 'Party Agent'
                    ELSE r.name
                END as display_role,
                (SELECT COUNT(*) FROM user_sessions us WHERE us.user_id = u.id AND us.is_active = 1 AND us.last_activity_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)) as is_online
            FROM users u
            JOIN roles r ON u.role_id = r.id
            LEFT JOIN wards w ON u.ward_id = w.id
            LEFT JOIN polling_units pu ON u.pu_id = pu.id
            LEFT JOIN lgas l ON u.lga_id = l.id
            WHERE u.tenant_id = ?
            AND u.lga_id = ?
            AND u.deleted_at IS NULL
            AND (
                u.first_name LIKE ? 
                OR u.last_name LIKE ? 
                OR u.email LIKE ?
                OR u.phone LIKE ?
                OR u.user_code LIKE ?
                OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?
            )
            AND r.level IN ('ward', 'pu_agent', 'party_agent')
            ORDER BY 
                CASE 
                    WHEN u.first_name LIKE ? THEN 1
                    WHEN u.last_name LIKE ? THEN 2
                    ELSE 3
                END,
                u.first_name ASC
            LIMIT 50
        ");
        $stmt->execute([
            $tenant_id, 
            $lga_id,
            $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm,
            $query . '%', $query . '%'
        ]);
        $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Search error: " . $e->getMessage());
    }
}

// If AJAX request, return JSON
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode($search_results);
    exit;
}

$page_title = 'Search Coordinators';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.search-container {
    max-width: 800px;
    margin: 0 auto;
}

.search-box {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px 28px;
    margin-bottom: 20px;
}

.search-box .search-input-wrapper {
    display: flex;
    gap: 12px;
    align-items: center;
}

.search-box input[type="text"] {
    flex: 1;
    padding: 12px 18px;
    border: 2px solid var(--gray-200);
    border-radius: 10px;
    font-size: 1rem;
    font-family: 'Inter', sans-serif;
    transition: var(--transition);
}

.search-box input[type="text"]:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.search-box .btn-search {
    padding: 12px 32px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
    white-space: nowrap;
}

.search-box .btn-search:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}

.search-box .search-hint {
    font-size: 0.75rem;
    color: var(--gray-400);
    margin-top: 8px;
}

.results-count {
    font-size: 0.85rem;
    color: var(--gray-500);
    margin-bottom: 16px;
}

.results-count strong {
    color: var(--gray-700);
}

.result-item {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 16px 20px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: var(--transition);
}

.result-item:hover {
    transform: translateX(4px);
    border-color: var(--primary);
    box-shadow: var(--shadow-hover);
}

.result-item .avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1rem;
    flex-shrink: 0;
}

.result-item .info {
    flex: 1;
}

.result-item .info .name {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--gray-800);
}

.result-item .info .role {
    font-size: 0.7rem;
    color: var(--primary);
    font-weight: 500;
}

.result-item .info .details {
    font-size: 0.7rem;
    color: var(--gray-500);
    margin-top: 2px;
}

.result-item .info .details i {
    margin-right: 4px;
}

.result-item .badges {
    display: flex;
    gap: 6px;
    align-items: center;
    flex-wrap: wrap;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.55rem;
    padding: 2px 10px;
    border-radius: 10px;
    font-weight: 600;
}

.status-badge .dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    display: inline-block;
}

.status-badge.active { background: #ECFDF5; color: #065F46; }
.status-badge.active .dot { background: #10B981; }
.status-badge.suspended { background: #FEF2F2; color: #991B1B; }
.status-badge.suspended .dot { background: #EF4444; }
.status-badge.pending { background: #FFFBEB; color: #92400E; }
.status-badge.pending .dot { background: #F59E0B; }

.status-badge.online { background: #ECFDF5; color: #065F46; }
.status-badge.online .dot { background: #10B981; animation: pulse-dot 1.5s ease-in-out infinite; }
.status-badge.offline { background: #F3F4F6; color: #6B7280; }
.status-badge.offline .dot { background: #9CA3AF; }

@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(0.8); }
}

.result-item .actions {
    display: flex;
    gap: 6px;
}

.result-item .actions a {
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 0.65rem;
    font-weight: 500;
    text-decoration: none;
    transition: var(--transition);
}

.result-item .actions .btn-view {
    background: var(--primary);
    color: white;
}

.result-item .actions .btn-view:hover {
    background: var(--primary-dark);
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}

.empty-state i {
    font-size: 3rem;
    color: var(--gray-300);
    display: block;
    margin-bottom: 12px;
}

.empty-state h3 {
    color: var(--gray-600);
    margin: 0;
}

.empty-state p {
    color: var(--gray-400);
    margin-top: 6px;
}

@media (max-width: 768px) {
    .search-box {
        padding: 16px 18px;
    }
    .search-box .search-input-wrapper {
        flex-direction: column;
    }
    .search-box input[type="text"] {
        width: 100%;
    }
    .search-box .btn-search {
        width: 100%;
        justify-content: center;
    }
    .result-item {
        flex-direction: column;
        text-align: center;
    }
    .result-item .badges {
        justify-content: center;
    }
    .result-item .actions {
        width: 100%;
        justify-content: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="search-container">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-search"></i> Search Coordinators</h1>
                    <p class="subtitle">
                        <i class="fas fa-map-marker-alt"></i> 
                        Search for Ward Coordinators, PU Agents, and Party Agents
                    </p>
                </div>
                <div class="actions">
                    <a href="manage-wards.php" class="btn-secondary-sm">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>

            <!-- Search Box -->
            <div class="search-box">
                <form method="GET" action="">
                    <div class="search-input-wrapper">
                        <input type="text" name="q" placeholder="Search by name, email, phone, or code..." 
                               value="<?php echo htmlspecialchars($query); ?>" autofocus />
                        <button type="submit" class="btn-search">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                    <div class="search-hint">
                        <i class="fas fa-info-circle"></i> 
                        Search for coordinators and agents by name, email, phone number, or user code
                    </div>
                </form>
            </div>

            <!-- Results -->
            <?php if (!empty($query)): ?>
                <div class="results-count">
                    <strong><?php echo count($search_results); ?></strong> result<?php echo count($search_results) !== 1 ? 's' : ''; ?> found for 
                    "<strong><?php echo htmlspecialchars($query); ?></strong>"
                </div>

                <?php if (!empty($search_results)): ?>
                    <?php foreach ($search_results as $result): 
                        $full_name = $result['first_name'] . ' ' . $result['last_name'];
                        $initials = strtoupper(substr($result['first_name'], 0, 1) . substr($result['last_name'], 0, 1));
                        $online_status = $result['is_online'] > 0 ? 'online' : 'offline';
                        $online_label = $result['is_online'] > 0 ? 'Online' : 'Offline';
                        $location = $result['pu_name'] ?? $result['ward_name'] ?? $result['lga_name'] ?? 'N/A';
                    ?>
                        <div class="result-item">
                            <div class="avatar"><?php echo $initials; ?></div>
                            
                            <div class="info">
                                <div class="name"><?php echo htmlspecialchars($full_name); ?></div>
                                <div class="role">
                                    <i class="fas fa-user-tag"></i> 
                                    <?php echo htmlspecialchars($result['display_role']); ?>
                                </div>
                                <div class="details">
                                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($location); ?>
                                    <?php if (!empty($result['email'])): ?>
                                        <span style="margin:0 6px;">•</span>
                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($result['email']); ?>
                                    <?php endif; ?>
                                    <?php if (!empty($result['phone'])): ?>
                                        <span style="margin:0 6px;">•</span>
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($result['phone']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="badges">
                                <span class="status-badge <?php echo $result['status']; ?>">
                                    <span class="dot"></span> <?php echo ucfirst($result['status']); ?>
                                </span>
                                <span class="status-badge <?php echo $online_status; ?>">
                                    <span class="dot"></span> <?php echo $online_label; ?>
                                </span>
                                <span style="font-size:0.6rem;color:var(--gray-400);">
                                    <?php echo htmlspecialchars($result['user_code']); ?>
                                </span>
                            </div>
                            
                            <div class="actions">
                                <a href="coordinator-profile.php?id=<?php echo $result['id']; ?>" class="btn-view">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-search"></i>
                        <h3>No Results Found</h3>
                        <p>No coordinators or agents found matching "<strong><?php echo htmlspecialchars($query); ?></strong>".</p>
                        <p style="font-size:0.75rem;color:var(--gray-400);">Try searching by name, email, phone number, or user code</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <h3>Search for Coordinators</h3>
                    <p>Enter a search term above to find coordinators and agents in your LGA.</p>
                    <p style="font-size:0.75rem;color:var(--gray-400);">
                        You can search by name, email, phone number, or user code
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
// Real-time search (optional)
var searchInput = document.querySelector('input[name="q"]');
var searchForm = document.querySelector('form');

searchInput?.addEventListener('keyup', function(e) {
    if (e.key === 'Enter') {
        searchForm.submit();
    }
});

// Same sidebar scripts as index.php
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