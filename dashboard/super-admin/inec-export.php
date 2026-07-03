<?php
// ============================================================
// INEC DATA EXPORT - SUPER ADMINISTRATOR
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
$format = isset($_GET['format']) ? $_GET['format'] : 'csv';

$tables = [
    'states' => ['table' => 'states', 'label' => 'States'],
    'lgas' => ['table' => 'lgas', 'label' => 'LGAs'],
    'wards' => ['table' => 'wards', 'label' => 'Wards'],
    'polling_units' => ['table' => 'polling_units', 'label' => 'Polling Units'],
    'senatorial_districts' => ['table' => 'senatorial_districts', 'label' => 'Senatorial Districts'],
    'federal_constituencies' => ['table' => 'federal_constituencies', 'label' => 'Federal Constituencies']
];

if (!isset($tables[$data_type])) {
    $data_type = 'states';
}

$table = $tables[$data_type]['table'];
$label = $tables[$data_type]['label'];

// Fetch all data
$stmt = $db->query("SELECT * FROM $table ORDER BY name");
$items = $stmt->fetchAll();

$filename = strtolower(str_replace(' ', '_', $label)) . '_export_' . date('Y-m-d');

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    if (!empty($items)) {
        fputcsv($output, array_keys($items[0]));
    }
    
    // Data
    foreach ($items as $item) {
        fputcsv($output, $item);
    }
    
    fclose($output);
    exit();
    
} elseif ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.json"');
    echo json_encode($items, JSON_PRETTY_PRINT);
    exit();
}

// Show export options
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    .export-container { max-width: 600px; margin: 0 auto; }
    .export-card { background: white; border-radius: var(--radius); border: 1px solid var(--gray-200); padding: 32px; box-shadow: var(--shadow); text-align: center; }
    .export-card .icon { font-size: 4rem; color: #8B5CF6; margin-bottom: 16px; }
    .export-card h2 { font-size: 1.4rem; margin-bottom: 8px; }
    .export-card p { color: var(--gray-500); margin-bottom: 24px; }
    .export-options { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; max-width: 400px; margin: 0 auto; }
    .export-option { padding: 20px 16px; border: 2px solid var(--gray-200); border-radius: 12px; text-decoration: none; color: var(--gray-700); transition: var(--transition); display: flex; flex-direction: column; align-items: center; gap: 8px; }
    .export-option:hover { border-color: #8B5CF6; background: #F5F3FF; transform: translateY(-2px); }
    .export-option i { font-size: 2rem; color: #8B5CF6; }
    .export-option .label { font-weight: 600; font-size: 0.9rem; }
    .export-option .desc { font-size: 0.75rem; color: var(--gray-400); }
    .back-link { display: inline-block; margin-top: 16px; color: var(--gray-500); text-decoration: none; }
    .back-link:hover { color: var(--primary); }
    @media (max-width: 480px) {
        .export-options { grid-template-columns: 1fr; }
        .export-card { padding: 20px; }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="export-container">
            <div class="page-header" style="margin-bottom:16px;">
                <div>
                    <h2>
                        <i class="fas fa-file-export" style="color:#8B5CF6;margin-right:8px;"></i> Export <?php echo $label; ?>
                        <small>Export <?php echo strtolower($label); ?> data</small>
                    </h2>
                </div>
                <a href="inec-view.php?type=<?php echo $data_type; ?>" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>

            <div class="export-card">
                <div class="icon"><i class="fas fa-file-export"></i></div>
                <h2>Export <?php echo $label; ?></h2>
                <p><?php echo number_format(count($items)); ?> records available for export.</p>

                <div class="export-options">
                    <a href="?type=<?php echo $data_type; ?>&format=csv" class="export-option">
                        <i class="fas fa-file-csv"></i>
                        <span class="label">CSV</span>
                        <span class="desc">Comma Separated Values</span>
                    </a>
                    <a href="?type=<?php echo $data_type; ?>&format=json" class="export-option">
                        <i class="fas fa-file-code"></i>
                        <span class="label">JSON</span>
                        <span class="desc">JavaScript Object Notation</span>
                    </a>
                </div>
                
                <a href="inec-view.php?type=<?php echo $data_type; ?>" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to view
                </a>
            </div>
        </div>
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