<?php
// ============================================================
// SENATORIAL COORDINATOR - POLLING UNIT RESULTS
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
// GET SELECTED PU
// ============================================================
$pu_id = isset($_GET['pu_id']) ? (int)$_GET['pu_id'] : 0;

// ============================================================
// GET PU DATA WITH RESULTS
// ============================================================
$pu_data = null;
$result_data = null;

if ($pu_id > 0) {
    try {
        // Get PU details
        $stmt = $db->prepare("
            SELECT pu.id, pu.name, pu.code, pu.registered_voters,
                   w.id as ward_id, w.name as ward_name,
                   l.id as lga_id, l.name as lga_name
            FROM polling_units pu
            JOIN wards w ON pu.ward_id = w.id
            JOIN lgas l ON w.lga_id = l.id
            WHERE pu.id = ? AND pu.is_active = 1
        ");
        $stmt->execute([$pu_id]);
        $pu_data = $stmt->fetch();

        // Get result for this PU
        $stmt = $db->prepare("
            SELECT r.*, u.full_name as agent_name, v.full_name as verified_by_name,
                   e.name as election_name
            FROM results_ec8a r
            LEFT JOIN users u ON r.agent_id = u.id
            LEFT JOIN users v ON r.verified_by = v.id
            JOIN elections e ON r.election_id = e.id
            WHERE r.pu_id = ? AND r.tenant_id = ?
            ORDER BY r.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$pu_id, $tenant_id]);
        $result_data = $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error fetching PU data: " . $e->getMessage());
    }
}

// ============================================================
// GET ALL PUS FOR SELECTION
// ============================================================
$pus = [];
try {
    if ($lga_list !== '0') {
        $stmt = $db->prepare("
            SELECT pu.id, pu.name, pu.code, w.name as ward_name, l.name as lga_name
            FROM polling_units pu
            JOIN wards w ON pu.ward_id = w.id
            JOIN lgas l ON w.lga_id = l.id
            WHERE l.id IN ($lga_list) AND pu.is_active = 1
            ORDER BY l.name ASC, w.name ASC, pu.name ASC
        ");
        $stmt->execute();
        $pus = $stmt->fetchAll();
    }
} catch (Exception $e) {
    error_log("Error fetching PUs: " . $e->getMessage());
}

// ============================================================
// PARSE PARTY VOTES
// ============================================================
$party_votes = [];
if ($result_data && $result_data['party_votes_json']) {
    $party_votes = json_decode($result_data['party_votes_json'], true) ?: [];
}

$page_title = 'Polling Unit Results';
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

.filter-section {
    display: flex;
    gap: 12px;
    margin-bottom: 24px;
    flex-wrap: wrap;
    background: white;
    padding: 16px 20px;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
}
.filter-section select {
    padding: 8px 14px;
    border: 1.5px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.8rem;
    background: white;
    min-width: 200px;
}
.filter-section select:focus {
    outline: none;
    border-color: var(--primary);
}
.filter-section .btn-filter {
    padding: 8px 20px;
    border: none;
    border-radius: 8px;
    background: var(--primary);
    color: white;
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
    transition: var(--transition);
}
.filter-section .btn-filter:hover {
    background: var(--primary-dark);
}

.pu-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px;
    margin-bottom: 20px;
}
.pu-card .pu-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 16px;
}
.pu-card .pu-header h3 {
    font-size: 1.1rem;
    font-weight: 700;
    margin: 0;
}
.pu-card .pu-header .code {
    font-size: 0.8rem;
    color: var(--gray-400);
    background: var(--gray-100);
    padding: 2px 12px;
    border-radius: 20px;
}
.pu-card .pu-header .location {
    font-size: 0.8rem;
    color: var(--gray-500);
}

.result-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin: 20px 0;
}
.result-item {
    background: var(--gray-50);
    border-radius: 10px;
    padding: 14px 18px;
    text-align: center;
}
.result-item .result-number {
    font-size: 1.5rem;
    font-weight: 700;
}
.result-item .result-label {
    font-size: 0.7rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.result-item.blue .result-number { color: #2563EB; }
.result-item.green .result-number { color: #059669; }
.result-item.orange .result-number { color: #EA580C; }
.result-item.purple .result-number { color: #7C3AED; }
.result-item.teal .result-number { color: #0D9488; }
.result-item.yellow .result-number { color: #D97706; }

.status-badge {
    display: inline-block;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
}
.status-badge.verified { background: #D1FAE5; color: #059669; }
.status-badge.pending { background: #FEF3C7; color: #D97706; }
.status-badge.flagged { background: #FEE2E2; color: #DC2626; }
.status-badge.rejected { background: #FEE2E2; color: #DC2626; }

.party-votes {
    margin-top: 20px;
}
.party-votes .party-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    border-bottom: 1px solid var(--gray-100);
}
.party-votes .party-item:last-child {
    border-bottom: none;
}
.party-votes .party-item .party-name {
    font-weight: 500;
}
.party-votes .party-item .party-votes {
    font-weight: 600;
    color: var(--gray-700);
}

.btn-sm {
    padding: 4px 12px;
    border-radius: 6px;
    background: var(--primary);
    color: white;
    text-decoration: none;
    font-size: 0.7rem;
    border: none;
}
.btn-sm:hover {
    background: var(--primary-dark);
    color: white;
}
.btn-sm.outline {
    background: transparent;
    border: 1px solid var(--gray-300);
    color: var(--gray-600);
}
.btn-sm.outline:hover {
    background: var(--gray-50);
}

.empty-state {
    text-align: center;
    padding: 40px;
    color: var(--gray-500);
}
.empty-state i {
    font-size: 3rem;
    display: block;
    margin-bottom: 12px;
    color: var(--gray-300);
}

@media (max-width: 768px) {
    .result-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .filter-section {
        flex-direction: column;
    }
    .filter-section select {
        min-width: unset;
        width: 100%;
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
                    <i class="fas fa-flag-checkered" style="color:var(--primary);margin-right:8px;"></i> 
                    Polling Unit Results
                    <small><?php echo htmlspecialchars($district_name); ?> - <?php echo htmlspecialchars($state_name); ?></small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="view-results.php" class="btn btn-secondary" style="padding:8px 18px;border-radius:10px;font-weight:600;font-size:0.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:8px;background:var(--gray-100);color:var(--gray-600);border:none;">
                    <i class="fas fa-arrow-left"></i> Back to Results
                </a>
            </div>
        </div>

        <!-- Filter -->
        <div class="filter-section">
            <form method="GET" action="" style="display:flex;gap:12px;flex-wrap:wrap;flex:1;">
                <select name="pu_id" required>
                    <option value="">Select Polling Unit...</option>
                    <?php foreach ($pus as $pu): ?>
                        <option value="<?php echo $pu['id']; ?>" <?php echo ($pu_id == $pu['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($pu['name']); ?> (<?php echo htmlspecialchars($pu['code']); ?>)
                            - <?php echo htmlspecialchars($pu['ward_name']); ?>, <?php echo htmlspecialchars($pu['lga_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> View Results</button>
            </form>
        </div>

        <?php if ($pu_id > 0 && $pu_data): ?>
            <!-- PU Card -->
            <div class="pu-card">
                <div class="pu-header">
                    <div>
                        <h3><?php echo htmlspecialchars($pu_data['name']); ?></h3>
                        <div class="location">
                            <i class="fas fa-map-marker-alt"></i> 
                            <?php echo htmlspecialchars($pu_data['ward_name']); ?>, 
                            <?php echo htmlspecialchars($pu_data['lga_name']); ?>
                            <span class="code" style="margin-left:12px;"><?php echo htmlspecialchars($pu_data['code']); ?></span>
                        </div>
                    </div>
                    <?php if ($result_data): ?>
                        <div>
                            <span class="status-badge <?php echo $result_data['status'] ?? 'pending'; ?>">
                                <?php echo ucfirst($result_data['status'] ?? 'Pending'); ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($result_data): ?>
                    <!-- Result Summary -->
                    <div class="result-grid">
                        <div class="result-item blue">
                            <div class="result-number"><?php echo number_format($result_data['registered_voters'] ?? 0); ?></div>
                            <div class="result-label">Registered Voters</div>
                        </div>
                        <div class="result-item green">
                            <div class="result-number"><?php echo number_format($result_data['accredited_voters'] ?? 0); ?></div>
                            <div class="result-label">Accredited Voters</div>
                        </div>
                        <div class="result-item orange">
                            <div class="result-number"><?php echo number_format($result_data['total_votes_cast'] ?? 0); ?></div>
                            <div class="result-label">Total Votes Cast</div>
                        </div>
                        <div class="result-item purple">
                            <div class="result-number"><?php echo number_format($result_data['valid_votes'] ?? 0); ?></div>
                            <div class="result-label">Valid Votes</div>
                        </div>
                        <div class="result-item yellow">
                            <div class="result-number"><?php echo number_format($result_data['rejected_votes'] ?? 0); ?></div>
                            <div class="result-label">Rejected Votes</div>
                        </div>
                        <div class="result-item teal">
                            <div class="result-number"><?php echo number_format($result_data['ballot_papers_issued'] ?? 0); ?></div>
                            <div class="result-label">Ballot Papers Issued</div>
                        </div>
                    </div>

                    <!-- Party Votes -->
                    <?php if (!empty($party_votes)): ?>
                        <div class="party-votes">
                            <h4 style="font-size:0.9rem;font-weight:600;margin-bottom:12px;">
                                <i class="fas fa-flag"></i> Party Votes
                            </h4>
                            <?php foreach ($party_votes as $party => $votes): ?>
                                <div class="party-item">
                                    <span class="party-name"><?php echo htmlspecialchars($party); ?></span>
                                    <span class="party-votes"><?php echo number_format($votes); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Result Details -->
                    <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--gray-200);">
                        <div style="display:flex;flex-wrap:wrap;gap:20px;font-size:0.85rem;color:var(--gray-600);">
                            <div>
                                <strong>Election:</strong> <?php echo htmlspecialchars($result_data['election_name'] ?? 'N/A'); ?>
                            </div>
                            <div>
                                <strong>Submitted:</strong> <?php echo date('M j, Y g:i A', strtotime($result_data['created_at'] ?? 'now')); ?>
                            </div>
                            <?php if ($result_data['verified_at']): ?>
                                <div>
                                    <strong>Verified:</strong> <?php echo date('M j, Y g:i A', strtotime($result_data['verified_at'])); ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <strong>Agent:</strong> <?php echo htmlspecialchars($result_data['agent_name'] ?? 'N/A'); ?>
                            </div>
                            <?php if ($result_data['verified_by_name']): ?>
                                <div>
                                    <strong>Verified By:</strong> <?php echo htmlspecialchars($result_data['verified_by_name']); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($result_data['remarks']): ?>
                                <div>
                                    <strong>Remarks:</strong> <?php echo htmlspecialchars($result_data['remarks']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;">
                        <?php if ($result_data['photo_url']): ?>
                            <a href="<?php echo $result_data['photo_url']; ?>" target="_blank" class="btn-sm">
                                <i class="fas fa-image"></i> View Photo
                            </a>
                        <?php endif; ?>
                        <a href="download-results.php?pu_id=<?php echo $pu_id; ?>" class="btn-sm outline">
                            <i class="fas fa-download"></i> Download
                        </a>
                        <?php if ($result_data['status'] === 'pending'): ?>
                            <a href="verify-ec8a.php?id=<?php echo $result_data['id']; ?>" class="btn-sm" style="background:#10B981;">
                                <i class="fas fa-check"></i> Verify
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state" style="padding:20px;">
                        <i class="fas fa-file-alt"></i>
                        <p>No results have been submitted for this polling unit yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif ($pu_id > 0): ?>
            <div class="empty-state">
                <i class="fas fa-flag-checkered"></i>
                <p>Polling Unit not found.</p>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-flag-checkered"></i>
                <h3>Select a Polling Unit</h3>
                <p>Choose a polling unit from the dropdown above to view its results.</p>
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