<?php
// ============================================================
// SENATORIAL COORDINATOR - SEARCH CONSTITUENCIES
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'senatorial') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$senatorial_id = SessionManager::get('senatorial_id');
$state_id = SessionManager::get('state_id');
$tenant_id = SessionManager::get('tenant_id');

$db = getDB();

// ============================================================
// GET SENATORIAL DISTRICT NAME
// ============================================================
$district_name = 'Senatorial District';
$state_name = 'State';
try {
    if ($senatorial_id) {
        $stmt = $db->prepare("
            SELECT s.name as state_name, sd.name as district_name 
            FROM senatorial_districts sd 
            JOIN states s ON sd.state_id = s.id 
            WHERE sd.id = ?
        ");
        $stmt->execute([$senatorial_id]);
        $result = $stmt->fetch();
        if ($result) {
            $district_name = $result['district_name'];
            $state_name = $result['state_name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching district: " . $e->getMessage());
}

// ============================================================
// GET LGA IDs FROM SENATORIAL DISTRICT
// ============================================================
$lga_ids = [];
try {
    if ($senatorial_id) {
        $stmt = $db->prepare("SELECT lgas_json FROM senatorial_districts WHERE id = ?");
        $stmt->execute([$senatorial_id]);
        $lgas_json = $stmt->fetchColumn();
        
        if ($lgas_json) {
            $lga_names = json_decode($lgas_json, true) ?: [];
            
            if (!empty($lga_names)) {
                $placeholders = implode(',', array_fill(0, count($lga_names), '?'));
                $stmt = $db->prepare("SELECT id FROM lgas WHERE name IN ($placeholders) AND state_id = ? AND is_active = 1");
                $stmt->execute(array_merge($lga_names, [$state_id]));
                $lga_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
        }
    }
} catch (Exception $e) {
    error_log("Error getting LGA IDs: " . $e->getMessage());
    $lga_ids = [];
}

$lga_list = !empty($lga_ids) ? implode(',', array_map('intval', $lga_ids)) : '0';

// ============================================================
// SEARCH PARAMETERS
// ============================================================
$search_term = isset($_GET['q']) ? trim($_GET['q']) : '';
$search_type = isset($_GET['type']) ? $_GET['type'] : 'all'; // all, constituency, lga, ward, pu, agent
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// ============================================================
// SEARCH RESULTS
// ============================================================
$results = [];
$total_results = 0;

if (!empty($search_term) && strlen($search_term) >= 2) {
    $search_like = '%' . $search_term . '%';
    
    // Search Federal Constituencies
    if ($search_type === 'all' || $search_type === 'constituency') {
        try {
            $stmt = $db->prepare("
                SELECT 
                    fc.id, 
                    fc.name, 
                    fc.code,
                    'federal_constituency' as type,
                    'Federal Constituency' as type_label,
                    s.name as state_name,
                    NULL as parent_name,
                    NULL as parent_type
                FROM federal_constituencies fc
                JOIN states s ON fc.state_id = s.id
                WHERE fc.state_id = ? 
                AND (fc.name LIKE ? OR fc.code LIKE ?)
                AND fc.is_active = 1
                ORDER BY fc.name ASC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$state_id, $search_like, $search_like, $per_page, $offset]);
            $fc_results = $stmt->fetchAll();
            
            $stmt = $db->prepare("
                SELECT COUNT(*) as total
                FROM federal_constituencies fc
                WHERE fc.state_id = ? 
                AND (fc.name LIKE ? OR fc.code LIKE ?)
                AND fc.is_active = 1
            ");
            $stmt->execute([$state_id, $search_like, $search_like]);
            $fc_total = $stmt->fetchColumn();
            
            $results = array_merge($results, $fc_results);
            $total_results += $fc_total;
        } catch (Exception $e) {
            error_log("Error searching constituencies: " . $e->getMessage());
        }
    }
    
    // Search LGAs
    if (($search_type === 'all' || $search_type === 'lga') && $lga_list !== '0') {
        try {
            $stmt = $db->prepare("
                SELECT 
                    l.id, 
                    l.name, 
                    l.code,
                    'lga' as type,
                    'LGA' as type_label,
                    s.name as state_name,
                    NULL as parent_name,
                    NULL as parent_type
                FROM lgas l
                JOIN states s ON l.state_id = s.id
                WHERE l.id IN ($lga_list)
                AND (l.name LIKE ? OR l.code LIKE ?)
                AND l.is_active = 1
                ORDER BY l.name ASC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$search_like, $search_like, $per_page, $offset]);
            $lga_results = $stmt->fetchAll();
            
            $stmt = $db->prepare("
                SELECT COUNT(*) as total
                FROM lgas l
                WHERE l.id IN ($lga_list)
                AND (l.name LIKE ? OR l.code LIKE ?)
                AND l.is_active = 1
            ");
            $stmt->execute([$search_like, $search_like]);
            $lga_total = $stmt->fetchColumn();
            
            $results = array_merge($results, $lga_results);
            $total_results += $lga_total;
        } catch (Exception $e) {
            error_log("Error searching LGAs: " . $e->getMessage());
        }
    }
    
    // Search Wards
    if (($search_type === 'all' || $search_type === 'ward') && $lga_list !== '0') {
        try {
            $stmt = $db->prepare("
                SELECT 
                    w.id, 
                    w.name, 
                    w.code,
                    'ward' as type,
                    'Ward' as type_label,
                    l.name as parent_name,
                    'LGA' as parent_type
                FROM wards w
                JOIN lgas l ON w.lga_id = l.id
                WHERE l.id IN ($lga_list)
                AND (w.name LIKE ? OR w.code LIKE ?)
                AND w.is_active = 1
                ORDER BY l.name ASC, w.name ASC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$search_like, $search_like, $per_page, $offset]);
            $ward_results = $stmt->fetchAll();
            
            $stmt = $db->prepare("
                SELECT COUNT(*) as total
                FROM wards w
                JOIN lgas l ON w.lga_id = l.id
                WHERE l.id IN ($lga_list)
                AND (w.name LIKE ? OR w.code LIKE ?)
                AND w.is_active = 1
            ");
            $stmt->execute([$search_like, $search_like]);
            $ward_total = $stmt->fetchColumn();
            
            $results = array_merge($results, $ward_results);
            $total_results += $ward_total;
        } catch (Exception $e) {
            error_log("Error searching wards: " . $e->getMessage());
        }
    }
    
    // Search Polling Units
    if (($search_type === 'all' || $search_type === 'pu') && $lga_list !== '0') {
        try {
            $stmt = $db->prepare("
                SELECT 
                    pu.id, 
                    pu.name, 
                    pu.code,
                    'polling_unit' as type,
                    'Polling Unit' as type_label,
                    w.name as parent_name,
                    'Ward' as parent_type
                FROM polling_units pu
                JOIN wards w ON pu.ward_id = w.id
                JOIN lgas l ON w.lga_id = l.id
                WHERE l.id IN ($lga_list)
                AND (pu.name LIKE ? OR pu.code LIKE ?)
                AND pu.is_active = 1
                ORDER BY l.name ASC, w.name ASC, pu.name ASC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$search_like, $search_like, $per_page, $offset]);
            $pu_results = $stmt->fetchAll();
            
            $stmt = $db->prepare("
                SELECT COUNT(*) as total
                FROM polling_units pu
                JOIN wards w ON pu.ward_id = w.id
                JOIN lgas l ON w.lga_id = l.id
                WHERE l.id IN ($lga_list)
                AND (pu.name LIKE ? OR pu.code LIKE ?)
                AND pu.is_active = 1
            ");
            $stmt->execute([$search_like, $search_like]);
            $pu_total = $stmt->fetchColumn();
            
            $results = array_merge($results, $pu_results);
            $total_results += $pu_total;
        } catch (Exception $e) {
            error_log("Error searching polling units: " . $e->getMessage());
        }
    }
    
    // Search Agents
    if (($search_type === 'all' || $search_type === 'agent') && $lga_list !== '0') {
        try {
            $stmt = $db->prepare("
                SELECT 
                    u.id, 
                    u.full_name as name,
                    'agent' as type,
                    'Agent' as type_label,
                    u.email,
                    u.phone,
                    r.name as role_name,
                    pu.name as parent_name,
                    'Polling Unit' as parent_type
                FROM users u
                JOIN roles r ON u.role_id = r.id
                LEFT JOIN polling_units pu ON u.pu_id = pu.id
                LEFT JOIN wards w ON pu.ward_id = w.id
                LEFT JOIN lgas l ON w.lga_id = l.id
                WHERE u.tenant_id = ? 
                AND u.status = 'active'
                AND r.level IN ('pu_agent', 'party_agent', 'volunteer', 'observer')
                AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)
                AND (l.id IN ($lga_list) OR l.id IS NULL)
                ORDER BY u.full_name ASC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$tenant_id, $search_like, $search_like, $search_like, $per_page, $offset]);
            $agent_results = $stmt->fetchAll();
            
            $stmt = $db->prepare("
                SELECT COUNT(*) as total
                FROM users u
                JOIN roles r ON u.role_id = r.id
                LEFT JOIN polling_units pu ON u.pu_id = pu.id
                LEFT JOIN wards w ON pu.ward_id = w.id
                LEFT JOIN lgas l ON w.lga_id = l.id
                WHERE u.tenant_id = ? 
                AND u.status = 'active'
                AND r.level IN ('pu_agent', 'party_agent', 'volunteer', 'observer')
                AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)
                AND (l.id IN ($lga_list) OR l.id IS NULL)
            ");
            $stmt->execute([$tenant_id, $search_like, $search_like, $search_like]);
            $agent_total = $stmt->fetchColumn();
            
            $results = array_merge($results, $agent_results);
            $total_results += $agent_total;
        } catch (Exception $e) {
            error_log("Error searching agents: " . $e->getMessage());
        }
    }
}

$total_pages = ceil($total_results / $per_page);

$page_title = 'Search Constituencies';
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
}
.page-header h2 small {
    font-size: 0.8rem;
    font-weight: 400;
    color: var(--gray-500);
    display: block;
    margin-top: 2px;
}

.search-box {
    display: flex;
    gap: 12px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}
.search-box input[type="text"] {
    flex: 1;
    min-width: 200px;
    padding: 12px 16px;
    border: 1.5px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.95rem;
    font-family: 'Inter', sans-serif;
    transition: var(--transition);
}
.search-box input[type="text"]:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}
.search-box select {
    padding: 12px 16px;
    border: 1.5px solid var(--gray-200);
    border-radius: 10px;
    font-size: 0.9rem;
    font-family: 'Inter', sans-serif;
    background: white;
}
.search-box .btn-search {
    padding: 12px 28px;
    border: none;
    border-radius: 10px;
    background: var(--primary);
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
    font-family: 'Inter', sans-serif;
    cursor: pointer;
    transition: var(--transition);
}
.search-box .btn-search:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
}

.results-stats {
    color: var(--gray-500);
    font-size: 0.85rem;
    margin-bottom: 16px;
}

.result-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 18px;
    background: white;
    border-radius: 10px;
    border: 1px solid var(--gray-200);
    margin-bottom: 10px;
    transition: var(--transition);
}
.result-item:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow-sm);
}
.result-item .result-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.result-item .result-icon.constituency { background: #EDE9FE; color: #7C3AED; }
.result-item .result-icon.lga { background: #DBEAFE; color: #2563EB; }
.result-item .result-icon.ward { background: #D1FAE5; color: #059669; }
.result-item .result-icon.polling_unit { background: #FEF3C7; color: #D97706; }
.result-item .result-icon.agent { background: #FFEDD5; color: #EA580C; }
.result-item .result-info {
    flex: 1;
    min-width: 0;
}
.result-item .result-info .name {
    font-weight: 600;
    font-size: 0.9rem;
}
.result-item .result-info .details {
    font-size: 0.78rem;
    color: var(--gray-500);
}
.result-item .result-info .details i {
    width: 16px;
    margin-right: 4px;
}
.result-item .result-badge {
    padding: 2px 12px;
    border-radius: 20px;
    font-size: 0.65rem;
    font-weight: 600;
    white-space: nowrap;
}
.result-item .result-badge.constituency { background: #EDE9FE; color: #7C3AED; }
.result-item .result-badge.lga { background: #DBEAFE; color: #2563EB; }
.result-item .result-badge.ward { background: #D1FAE5; color: #059669; }
.result-item .result-badge.polling_unit { background: #FEF3C7; color: #D97706; }
.result-item .result-badge.agent { background: #FFEDD5; color: #EA580C; }
.result-item .result-link {
    color: var(--primary);
    text-decoration: none;
    font-size: 0.8rem;
    font-weight: 500;
}
.result-item .result-link:hover {
    text-decoration: underline;
}

.pagination {
    display: flex;
    gap: 6px;
    justify-content: center;
    margin-top: 20px;
    flex-wrap: wrap;
}
.pagination a, .pagination span {
    padding: 8px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 6px;
    text-decoration: none;
    color: var(--gray-600);
    font-size: 0.85rem;
    transition: var(--transition);
}
.pagination a:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}
.pagination .active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}
.pagination .disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--gray-500);
}
.empty-state i {
    font-size: 3rem;
    display: block;
    margin-bottom: 16px;
    color: var(--gray-300);
}
.empty-state h3 {
    font-size: 1.2rem;
    color: var(--gray-700);
    margin-bottom: 8px;
}
.empty-state p {
    font-size: 0.9rem;
}

@media (max-width: 768px) {
    .search-box {
        flex-direction: column;
    }
    .search-box input[type="text"] {
        min-width: unset;
    }
    .result-item {
        flex-wrap: wrap;
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
                    <i class="fas fa-search" style="color:var(--primary);margin-right:8px;"></i> 
                    Search Constituencies
                    <small><?php echo htmlspecialchars($district_name); ?> - <?php echo htmlspecialchars($state_name); ?></small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="monitor-district.php" class="btn btn-secondary" style="padding:8px 18px;border-radius:10px;font-weight:600;font-size:0.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;background:var(--gray-100);color:var(--gray-600);border:none;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Search Box -->
        <div class="search-box">
            <form method="GET" action="" style="display:flex;gap:12px;flex:1;flex-wrap:wrap;">
                <input type="text" name="q" placeholder="Search by name, code, email, or phone..." value="<?php echo htmlspecialchars($search_term); ?>" autofocus>
                <select name="type">
                    <option value="all" <?php echo $search_type === 'all' ? 'selected' : ''; ?>>All Types</option>
                    <option value="constituency" <?php echo $search_type === 'constituency' ? 'selected' : ''; ?>>Federal Constituencies</option>
                    <option value="lga" <?php echo $search_type === 'lga' ? 'selected' : ''; ?>>LGAs</option>
                    <option value="ward" <?php echo $search_type === 'ward' ? 'selected' : ''; ?>>Wards</option>
                    <option value="pu" <?php echo $search_type === 'pu' ? 'selected' : ''; ?>>Polling Units</option>
                    <option value="agent" <?php echo $search_type === 'agent' ? 'selected' : ''; ?>>Agents</option>
                </select>
                <button type="submit" class="btn-search">
                    <i class="fas fa-search"></i> Search
                </button>
            </form>
        </div>

        <?php if (!empty($search_term)): ?>
            <!-- Results Stats -->
            <div class="results-stats">
                Found <?php echo number_format($total_results); ?> result(s) for "<?php echo htmlspecialchars($search_term); ?>"
                <?php if ($search_type !== 'all'): ?>
                    in <?php echo ucfirst(str_replace('_', ' ', $search_type)); ?>
                <?php endif; ?>
            </div>

            <!-- Results -->
            <?php if (count($results) > 0): ?>
                <?php foreach ($results as $result): ?>
                    <div class="result-item">
                        <div class="result-icon <?php echo $result['type']; ?>">
                            <?php if ($result['type'] === 'federal_constituency'): ?>
                                <i class="fas fa-building"></i>
                            <?php elseif ($result['type'] === 'lga'): ?>
                                <i class="fas fa-map-marker-alt"></i>
                            <?php elseif ($result['type'] === 'ward'): ?>
                                <i class="fas fa-layer-group"></i>
                            <?php elseif ($result['type'] === 'polling_unit'): ?>
                                <i class="fas fa-flag-checkered"></i>
                            <?php elseif ($result['type'] === 'agent'): ?>
                                <i class="fas fa-user-tie"></i>
                            <?php endif; ?>
                        </div>
                        <div class="result-info">
                            <div class="name"><?php echo htmlspecialchars($result['name']); ?></div>
                            <div class="details">
                                <?php if (isset($result['code']) && $result['code']): ?>
                                    <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($result['code']); ?>
                                <?php endif; ?>
                                <?php if (isset($result['parent_name']) && $result['parent_name']): ?>
                                    <i class="fas fa-level-up-alt"></i> <?php echo htmlspecialchars($result['parent_name']); ?>
                                    <?php if (isset($result['parent_type']) && $result['parent_type']): ?>
                                        (<?php echo htmlspecialchars($result['parent_type']); ?>)
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if (isset($result['state_name']) && $result['state_name']): ?>
                                    <i class="fas fa-flag"></i> <?php echo htmlspecialchars($result['state_name']); ?>
                                <?php endif; ?>
                                <?php if (isset($result['email']) && $result['email']): ?>
                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($result['email']); ?>
                                <?php endif; ?>
                                <?php if (isset($result['phone']) && $result['phone']): ?>
                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($result['phone']); ?>
                                <?php endif; ?>
                                <?php if (isset($result['role_name']) && $result['role_name']): ?>
                                    <i class="fas fa-user-tag"></i> <?php echo htmlspecialchars($result['role_name']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="result-badge <?php echo $result['type']; ?>">
                            <?php echo $result['type_label']; ?>
                        </span>
                        <a href="<?php 
                            if ($result['type'] === 'federal_constituency') echo 'view-constituency-details.php?id=' . $result['id'];
                            elseif ($result['type'] === 'lga') echo 'view-lga-details.php?id=' . $result['id'];
                            elseif ($result['type'] === 'ward') echo 'view-ward-details.php?id=' . $result['id'];
                            elseif ($result['type'] === 'polling_unit') echo 'pu-details.php?id=' . $result['id'];
                            elseif ($result['type'] === 'agent') echo 'agent-details.php?id=' . $result['id'];
                        ?>" class="result-link">
                            View <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                <?php endforeach; ?>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?q=<?php echo urlencode($search_term); ?>&type=<?php echo $search_type; ?>&page=<?php echo $page - 1; ?>">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php else: ?>
                            <span class="disabled"><i class="fas fa-chevron-left"></i> Previous</span>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= min($total_pages, 10); $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?q=<?php echo urlencode($search_term); ?>&type=<?php echo $search_type; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?q=<?php echo urlencode($search_term); ?>&type=<?php echo $search_type; ?>&page=<?php echo $page + 1; ?>">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="disabled">Next <i class="fas fa-chevron-right"></i></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <h3>No Results Found</h3>
                    <p>We couldn't find any matches for "<strong><?php echo htmlspecialchars($search_term); ?></strong>".</p>
                    <p style="font-size:0.85rem;margin-top:8px;">Try adjusting your search term or filter type.</p>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <h3>Search for Constituencies</h3>
                <p>Enter a search term above to find federal constituencies, LGAs, wards, polling units, or agents.</p>
                <p style="font-size:0.85rem;margin-top:8px;color:var(--gray-400);">
                    <i class="fas fa-lightbulb"></i> Tip: Search by name, code, email, or phone number
                </p>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
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

window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});
</script>
</body>
</html>