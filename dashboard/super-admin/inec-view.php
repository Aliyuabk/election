<?php
// ============================================================
// INEC DATA VIEW - SUPER ADMINISTRATOR
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'super_admin') {
    header('Location: ../client-admin/');
    exit();
}

$db = getDB();

$data_type = isset($_GET['type']) ? $_GET['type'] : 'states';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 25;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Define table columns based on data type
$tables = [
    'states' => ['table' => 'states', 'columns' => ['id', 'code', 'name', 'capital', 'registered_voters', 'is_active'], 'label' => 'States'],
    'lgas' => ['table' => 'lgas', 'columns' => ['id', 'state_id', 'code', 'name', 'registered_voters', 'is_active'], 'label' => 'LGAs'],
    'wards' => ['table' => 'wards', 'columns' => ['id', 'lga_id', 'code', 'name', 'registered_voters', 'is_active'], 'label' => 'Wards'],
    'polling_units' => ['table' => 'polling_units', 'columns' => ['id', 'ward_id', 'code', 'name', 'registered_voters', 'is_active'], 'label' => 'Polling Units'],
    'senatorial_districts' => ['table' => 'senatorial_districts', 'columns' => ['id', 'state_id', 'code', 'name', 'is_active'], 'label' => 'Senatorial Districts'],
    'federal_constituencies' => ['table' => 'federal_constituencies', 'columns' => ['id', 'state_id', 'code', 'name', 'is_active'], 'label' => 'Federal Constituencies']
];

if (!isset($tables[$data_type])) {
    $data_type = 'states';
}

$table_info = $tables[$data_type];
$table = $table_info['table'];
$label = $table_info['label'];
$columns = $table_info['columns'];

// Build query
$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(name LIKE ? OR code LIKE ?)";
    $search_param = "%$search%";
    $params = [$search_param, $search_param];
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Count total
$count_sql = "SELECT COUNT(*) as total FROM $table $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_items = $stmt->fetch()['total'] ?? 0;
$total_pages = ceil($total_items / $limit);

// Fetch data
$sql = "SELECT * FROM $table $where_clause ORDER BY name LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

// Get counts for all types
$counts = [];
foreach ($tables as $key => $info) {
    try {
        $stmt = $db->query("SELECT COUNT(*) as total FROM {$info['table']}");
        $counts[$key] = $stmt->fetch()['total'] ?? 0;
    } catch (Exception $e) {
        $counts[$key] = 0;
    }
}

$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    .page-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; }
    .page-header h2 { font-size: 1.3rem; font-weight: 700; }
    .page-header h2 small { font-size: 0.8rem; font-weight: 400; color: var(--gray-500); display: block; }
    .btn-outline { padding: 8px 16px; background: transparent; color: var(--gray-600); border: 1px solid var(--gray-200); border-radius: 10px; font-weight: 500; font-size: 0.82rem; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: var(--transition); font-family: 'Inter', sans-serif; }
    .btn-outline:hover { background: var(--gray-50); border-color: var(--gray-300); }
    .btn-primary { padding: 8px 18px; background: #8B5CF6; color: white; border: none; border-radius: 10px; font-weight: 600; font-size: 0.85rem; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: var(--transition); font-family: 'Inter', sans-serif; }
    .btn-primary:hover { background: #7C3AED; transform: translateY(-1px); box-shadow: 0 4px 16px rgba(139, 92, 246, 0.3); }
    
    .filter-bar-pro { background: white; border-radius: var(--radius); border: 1px solid var(--gray-200); padding: 14px 20px; margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; box-shadow: var(--shadow); }
    .filter-bar-pro .search-wrap { flex: 1; min-width: 200px; display: flex; align-items: center; background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: 10px; padding: 6px 14px; transition: var(--transition); }
    .filter-bar-pro .search-wrap:focus-within { border-color: #8B5CF6; background: white; box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1); }
    .filter-bar-pro .search-wrap i { color: var(--gray-400); font-size: 0.85rem; }
    .filter-bar-pro .search-wrap input { border: none; outline: none; background: transparent; padding: 6px 10px; font-family: 'Inter', sans-serif; font-size: 0.85rem; width: 100%; color: var(--gray-700); }
    .filter-bar-pro select { padding: 8px 14px; border: 1px solid var(--gray-200); border-radius: 10px; font-family: 'Inter', sans-serif; font-size: 0.82rem; background: var(--gray-50); color: var(--gray-700); cursor: pointer; transition: var(--transition); min-width: 120px; }
    .filter-bar-pro select:focus { outline: none; border-color: #8B5CF6; background: white; }
    .filter-bar-pro .btn-filter { padding: 8px 18px; background: #8B5CF6; color: white; border: none; border-radius: 10px; font-weight: 600; font-size: 0.8rem; cursor: pointer; transition: var(--transition); font-family: 'Inter', sans-serif; display: inline-flex; align-items: center; gap: 6px; }
    
    .table-container { background: white; border-radius: var(--radius); border: 1px solid var(--gray-200); overflow: hidden; box-shadow: var(--shadow); }
    .table-container .table-header { padding: 16px 20px; border-bottom: 1px solid var(--gray-200); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; background: var(--gray-50); }
    .table-container .table-header .table-title { font-weight: 600; font-size: 0.95rem; display: flex; align-items: center; gap: 8px; }
    .table-container .table-header .table-title .count { background: #8B5CF6; color: white; padding: 0 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
    
    .data-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    .data-table thead { background: var(--gray-50); }
    .data-table thead th { padding: 10px 14px; text-align: left; font-weight: 600; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--gray-500); border-bottom: 1px solid var(--gray-200); white-space: nowrap; }
    .data-table tbody td { padding: 8px 14px; border-bottom: 1px solid var(--gray-100); vertical-align: middle; }
    .data-table tbody tr:last-child td { border-bottom: none; }
    .data-table tbody tr:hover { background: var(--gray-50); }
    
    .status-badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 10px; border-radius: 20px; font-size: 0.65rem; font-weight: 600; }
    .status-badge.active { background: #ECFDF5; color: #065F46; }
    .status-badge.inactive { background: #FEF2F2; color: #991B1B; }
    
    .pagination-pro { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding: 14px 20px; background: white; border-radius: var(--radius); border: 1px solid var(--gray-200); margin-top: 16px; box-shadow: var(--shadow); }
    .pagination-pro .info { font-size: 0.82rem; color: var(--gray-500); }
    .pagination-pro .pages { display: flex; gap: 4px; align-items: center; }
    .pagination-pro .pages a, .pagination-pro .pages span { padding: 6px 14px; border-radius: 8px; font-size: 0.82rem; text-decoration: none; color: var(--gray-600); transition: var(--transition); min-width: 36px; text-align: center; border: 1px solid transparent; }
    .pagination-pro .pages a:hover { background: var(--gray-100); border-color: var(--gray-200); }
    .pagination-pro .pages .active { background: #8B5CF6; color: white; border-color: #8B5CF6; }
    .pagination-pro .pages .disabled { opacity: 0.4; cursor: not-allowed; }
    
    .empty-state-pro { text-align: center; padding: 48px 20px; color: var(--gray-500); }
    .empty-state-pro i { font-size: 3rem; color: var(--gray-300); display: block; margin-bottom: 12px; }
    
    .type-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
    .type-tab { padding: 8px 18px; border-radius: 10px; border: 1px solid var(--gray-200); background: white; color: var(--gray-600); text-decoration: none; font-size: 0.82rem; font-weight: 500; transition: var(--transition); }
    .type-tab:hover { background: var(--gray-50); border-color: var(--gray-300); }
    .type-tab.active { background: #8B5CF6; color: white; border-color: #8B5CF6; }
    .type-tab .badge { background: var(--gray-200); color: var(--gray-600); padding: 1px 8px; border-radius: 12px; font-size: 0.6rem; font-weight: 600; margin-left: 4px; }
    .type-tab.active .badge { background: rgba(255,255,255,0.3); color: white; }
    
    @media (max-width: 768px) {
        .filter-bar-pro { flex-direction: column; align-items: stretch; }
        .filter-bar-pro .search-wrap { min-width: auto; }
        .filter-bar-pro select { width: 100%; }
        .table-container { overflow-x: auto; }
        .data-table { font-size: 0.78rem; }
        .data-table th, .data-table td { padding: 6px 10px; }
        .pagination-pro { flex-direction: column; align-items: center; }
        .type-tabs { flex-wrap: wrap; }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-database" style="color:#8B5CF6;margin-right:8px;"></i> INEC Data Viewer
                    <small>View and manage <?php echo strtolower($label); ?></small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="inec-data.php" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <a href="inec-export.php?type=<?php echo $data_type; ?>" class="btn-outline">
                    <i class="fas fa-file-export"></i> Export
                </a>
            </div>
        </div>

        <!-- Type Tabs -->
        <div class="type-tabs">
            <?php foreach ($tables as $key => $info): ?>
                <a href="inec-view.php?type=<?php echo $key; ?>" class="type-tab <?php echo $data_type === $key ? 'active' : ''; ?>">
                    <?php echo $info['label']; ?>
                    <span class="badge"><?php echo number_format($counts[$key] ?? 0); ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Filter -->
        <div class="filter-bar-pro">
            <form method="GET" action="" style="display:flex;flex-wrap:wrap;gap:10px;flex:1;align-items:center;width:100%;">
                <input type="hidden" name="type" value="<?php echo $data_type; ?>">
                <div class="search-wrap" style="flex:1;">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search by name or code..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
                <?php if (!empty($search)): ?>
                    <a href="inec-view.php?type=<?php echo $data_type; ?>" class="btn-outline" style="padding:8px 14px;font-size:0.8rem;">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">
                    <i class="fas fa-list" style="color:#8B5CF6;"></i> <?php echo $label; ?>
                    <span class="count"><?php echo number_format($total_items); ?></span>
                </div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <?php foreach ($columns as $col): ?>
                            <th><?php echo ucfirst(str_replace('_', ' ', $col)); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($items) > 0): ?>
                        <?php foreach ($items as $index => $item): ?>
                            <tr>
                                <td><?php echo $offset + $index + 1; ?></td>
                                <?php foreach ($columns as $col): ?>
                                    <td>
                                        <?php
                                        $value = $item[$col] ?? '';
                                        if ($col === 'is_active') {
                                            echo '<span class="status-badge ' . ($value ? 'active' : 'inactive') . '">' . ($value ? 'Active' : 'Inactive') . '</span>';
                                        } elseif ($col === 'registered_voters') {
                                            echo number_format($value);
                                        } elseif (in_array($col, ['state_id', 'lga_id', 'ward_id'])) {
                                            // Try to get name
                                            $related_table = str_replace('_id', '', $col);
                                            try {
                                                $stmt = $db->prepare("SELECT name FROM $related_table WHERE id = ?");
                                                $stmt->execute([$value]);
                                                $related = $stmt->fetch();
                                                echo htmlspecialchars($related['name'] ?? $value);
                                            } catch (Exception $e) {
                                                echo htmlspecialchars($value);
                                            }
                                        } else {
                                            echo htmlspecialchars($value);
                                        }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo count($columns) + 1; ?>">
                                <div class="empty-state-pro">
                                    <i class="fas fa-database"></i>
                                    <h4>No data found</h4>
                                    <p>Try adjusting your search or upload data from the INEC Data page.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="pagination-pro">
            <div class="info">Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $limit, $total_items); ?></strong> of <strong><?php echo number_format($total_items); ?></strong></div>
            <div class="pages">
                <?php if ($page > 1): ?>
                    <a href="?type=<?php echo $data_type; ?>&page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>"><i class="fas fa-chevron-left"></i></a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1) { echo '<a href="?type=' . $data_type . '&page=1&search=' . urlencode($search) . '">1</a>'; if ($start_page > 2) echo '<span>…</span>'; }
                for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?type=<?php echo $data_type; ?>&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor;
                if ($end_page < $total_pages) { if ($end_page < $total_pages - 1) echo '<span>…</span>'; echo '<a href="?type=' . $data_type . '&page=' . $total_pages . '&search=' . urlencode($search) . '">' . $total_pages . '</a>'; } ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?type=<?php echo $data_type; ?>&page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>"><i class="fas fa-chevron-right"></i></a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<script>
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