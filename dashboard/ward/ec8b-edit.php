<?php
// ============================================================
// WARD COORDINATOR - EDIT EC8B DRAFT
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

if (SessionManager::get('role_level') !== 'ward') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Ward Coordinator');
$user_id = SessionManager::get('user_id');
$tenant_id = SessionManager::get('tenant_id');
$ward_id = SessionManager::get('ward_id');
$lga_id = SessionManager::get('lga_id');
$state_id = SessionManager::get('state_id');

if (empty($ward_id)) {
    $db = getDB();
    try {
        $stmt = $db->prepare("SELECT ward_id, lga_id, state_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $ward_id = $user['ward_id'];
            $lga_id = $user['lga_id'];
            $state_id = $user['state_id'];
            SessionManager::set('ward_id', $ward_id);
            SessionManager::set('lga_id', $lga_id);
            SessionManager::set('state_id', $state_id);
        }
    } catch (Exception $e) {
        error_log("Error fetching location data: " . $e->getMessage());
    }
}

$db = getDB();
$ec8b_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($ec8b_id <= 0) {
    header('Location: ec8b-history.php');
    exit();
}

// Get EC8B details
$ec8b = null;
try {
    $stmt = $db->prepare("
        SELECT r.*, e.name as election_name, w.name as ward_name
        FROM results_ec8b r
        JOIN elections e ON r.election_id = e.id
        JOIN wards w ON r.ward_id = w.id
        WHERE r.id = ? AND r.tenant_id = ? AND r.ward_id = ? AND r.status = 'pending'
    ");
    $stmt->execute([$ec8b_id, $tenant_id, $ward_id]);
    $ec8b = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching EC8B: " . $e->getMessage());
}

if (!$ec8b) {
    header('Location: ec8b-history.php');
    exit();
}

// Get ward name
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
    error_log("Error fetching ward: " . $e->getMessage());
}

// Get parties from tenant
$parties = [];
try {
    $stmt = $db->prepare("SELECT id, name, acronym FROM political_parties WHERE tenant_id = ? AND is_active = 1 ORDER BY name ASC");
    $stmt->execute([$tenant_id]);
    $parties = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching parties: " . $e->getMessage());
}

// Get party votes
$party_votes = json_decode($ec8b['party_votes_json'], true) ?: [];

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update';
    
    // Collect party votes
    $party_votes = [];
    foreach ($parties as $party) {
        $party_votes[$party['acronym']] = (int)($_POST['votes_' . $party['id']] ?? 0);
    }
    $party_votes_json = json_encode($party_votes);
    
    $valid_votes = (int)($_POST['valid_votes'] ?? 0);
    $rejected_votes = (int)($_POST['rejected_votes'] ?? 0);
    $total_votes = $valid_votes + $rejected_votes;
    
    $sum_party_votes = array_sum($party_votes);
    $mismatch_alert = ($sum_party_votes != $valid_votes) ? 1 : 0;
    $mismatch_details = $mismatch_alert ? json_encode([
        'party_sum' => $sum_party_votes,
        'valid_votes' => $valid_votes,
        'difference' => abs($sum_party_votes - $valid_votes),
        'message' => 'Sum of party votes (' . $sum_party_votes . ') does not match valid votes (' . $valid_votes . ')'
    ]) : null;
    
    try {
        $status = ($action === 'submit') ? 'pending' : 'pending';
        
        $stmt = $db->prepare("
            UPDATE results_ec8b 
            SET party_votes_json = ?, valid_votes = ?, 
                rejected_votes = ?, total_votes = ?, 
                mismatch_alert = ?, mismatch_details_json = ?
            WHERE id = ? AND tenant_id = ?
        ");
        
        $stmt->execute([
            $party_votes_json,
            $valid_votes,
            $rejected_votes,
            $total_votes,
            $mismatch_alert,
            $mismatch_details,
            $ec8b_id,
            $tenant_id
        ]);
        
        logActivity($user_id, 'ec8b_updated', 
            "Updated EC8B form ID: $ec8b_id",
            'results_ec8b', $ec8b_id
        );
        
        if ($action === 'submit') {
            $message = "EC8B form updated and submitted successfully!";
        } else {
            $message = "EC8B draft updated successfully!";
        }
        
        // Refresh data
        $stmt = $db->prepare("SELECT * FROM results_ec8b WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$ec8b_id, $tenant_id]);
        $ec8b = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $error = 'Failed to update EC8B: ' . $e->getMessage();
        error_log("EC8B update error: " . $e->getMessage());
    }
}

$page_title = 'Edit EC8B';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.ec8b-container {
    max-width: 800px;
    margin: 0 auto;
}

.form-card {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px 28px;
    margin-bottom: 16px;
}

.form-card .card-title {
    font-size: 0.85rem;
    font-weight: 600;
    margin: 0 0 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--gray-200);
    color: var(--gray-700);
}

.form-card .card-title i {
    color: var(--primary);
    margin-right: 6px;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.55rem;
    padding: 2px 10px;
    border-radius: 8px;
    font-weight: 600;
}

.status-badge .dot {
    width: 4px;
    height: 4px;
    border-radius: 50%;
    display: inline-block;
}

.status-badge.pending { background: #FFFBEB; color: #92400E; }
.status-badge.pending .dot { background: #F59E0B; }

.info-box {
    background: #F0F9FF;
    border: 1px solid #BAE6FD;
    border-radius: 8px;
    padding: 10px 14px;
    margin-bottom: 16px;
    font-size: 0.75rem;
    color: #0369A1;
}

.info-box i {
    margin-right: 6px;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    font-weight: 600;
    font-size: 0.8rem;
    color: var(--gray-700);
    margin-bottom: 4px;
}

.form-group input[type="number"] {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--gray-200);
    border-radius: 8px;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
    transition: var(--transition);
    background: white;
}

.form-group input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.party-votes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 8px;
}

.party-vote-item {
    background: var(--gray-50);
    border-radius: 8px;
    padding: 10px 12px;
    border: 1px solid var(--gray-200);
}

.party-vote-item .party-label {
    font-weight: 600;
    font-size: 0.75rem;
    color: var(--gray-700);
    margin-bottom: 4px;
}

.party-vote-item input[type="number"] {
    width: 100%;
    padding: 6px 8px;
    border: 1px solid var(--gray-200);
    border-radius: 6px;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
    transition: var(--transition);
}

.party-vote-item input[type="number"]:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

.vote-summary {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 12px;
    margin: 12px 0;
}

.vote-summary .summary-item {
    text-align: center;
    padding: 10px;
    background: var(--gray-50);
    border-radius: 8px;
}

.vote-summary .summary-item .number {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--gray-800);
}

.vote-summary .summary-item .label {
    font-size: 0.6rem;
    color: var(--gray-500);
}

.alert {
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 0.85rem;
    margin-bottom: 16px;
}

.alert-success {
    background: #ECFDF5;
    color: #065F46;
    border: 1px solid #A7F3D0;
}

.alert-error {
    background: #FEF2F2;
    color: #991B1B;
    border: 1px solid #FECACA;
}

.alert i {
    margin-right: 6px;
}

.btn-group {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-group button {
    padding: 10px 24px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
}

.btn-group .btn-draft {
    background: var(--gray-100);
    color: var(--gray-700);
}

.btn-group .btn-draft:hover {
    background: var(--gray-200);
}

.btn-group .btn-submit {
    background: #10B981;
    color: white;
}

.btn-group .btn-submit:hover {
    background: #059669;
}

.btn-group .btn-cancel {
    background: var(--gray-100);
    color: var(--gray-700);
    text-decoration: none;
    padding: 10px 24px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-group .btn-cancel:hover {
    background: var(--gray-200);
}

@media (max-width: 768px) {
    .form-card {
        padding: 16px 18px;
    }
    .party-votes-grid {
        grid-template-columns: 1fr 1fr;
    }
    .vote-summary {
        grid-template-columns: 1fr 1fr 1fr;
    }
    .btn-group {
        flex-direction: column;
    }
    .btn-group button,
    .btn-group .btn-cancel {
        width: 100%;
        justify-content: center;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="ec8b-container">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-edit"></i> Edit EC8B</h1>
                    <p class="subtitle">
                        <i class="fas fa-layer-group"></i> 
                        <?php echo htmlspecialchars($ward_name); ?> Ward - Edit EC8B Form
                    </p>
                </div>
                <div>
                    <span class="status-badge <?php echo $ec8b['status']; ?>">
                        <span class="dot"></span>
                        <?php echo ucfirst($ec8b['status']); ?>
                    </span>
                </div>
                <div class="actions">
                    <a href="ec8b-history.php" class="btn-secondary-sm">
                        <i class="fas fa-history"></i> History
                    </a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="form-card">
                <div class="card-title"><i class="fas fa-info-circle"></i> EC8B Form - Ward Collation</div>

                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    Edit the EC8B form by updating the aggregated results from all polling units in this ward.
                </div>

                <div style="margin:12px 0;padding:10px 14px;background:var(--gray-50);border-radius:8px;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;font-size:0.8rem;">
                        <div><span style="color:var(--gray-500);">Election:</span> <?php echo htmlspecialchars($ec8b['election_name']); ?></div>
                        <div><span style="color:var(--gray-500);">Ward:</span> <?php echo htmlspecialchars($ec8b['ward_name']); ?></div>
                        <div><span style="color:var(--gray-500);">Created:</span> <?php echo date('M j, Y g:i A', strtotime($ec8b['created_at'])); ?></div>
                        <div><span style="color:var(--gray-500);">Status:</span> <?php echo ucfirst($ec8b['status']); ?></div>
                    </div>
                </div>

                <form method="POST" action="" id="ec8bForm">
                    <!-- Party Votes -->
                    <div class="card-title" style="margin-top:16px;padding-top:12px;border-top:1px solid var(--gray-200);">
                        <i class="fas fa-vote-yea"></i> Party Votes
                    </div>
                    
                    <div class="party-votes-grid">
                        <?php foreach ($parties as $party): 
                            $value = $party_votes[$party['acronym']] ?? 0;
                        ?>
                            <div class="party-vote-item">
                                <div class="party-label">
                                    <?php echo htmlspecialchars($party['name']); ?>
                                    <span style="font-weight:400;font-size:0.65rem;color:var(--gray-400);">
                                        (<?php echo htmlspecialchars($party['acronym']); ?>)
                                    </span>
                                </div>
                                <input type="number" name="votes_<?php echo $party['id']; ?>" 
                                       min="0" step="1" value="<?php echo $value; ?>" 
                                       onchange="updateTotals()" />
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Vote Totals -->
                    <div class="vote-summary">
                        <div class="summary-item">
                            <div class="number" id="partySum"><?php echo array_sum($party_votes); ?></div>
                            <div class="label">Sum of Party Votes</div>
                        </div>
                        <div class="summary-item">
                            <div class="number" id="validVotes"><?php echo $ec8b['valid_votes']; ?></div>
                            <div class="label">Valid Votes</div>
                        </div>
                        <div class="summary-item">
                            <div class="number" id="totalVotes"><?php echo $ec8b['total_votes']; ?></div>
                            <div class="label">Total Votes</div>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;">
                        <div class="form-group">
                            <label>Rejected Votes</label>
                            <input type="number" name="rejected_votes" id="rejectedVotes" 
                                   min="0" step="1" value="<?php echo $ec8b['rejected_votes']; ?>" 
                                   onchange="updateTotals()" />
                        </div>
                        <div class="form-group">
                            <label>Valid Votes</label>
                            <input type="number" name="valid_votes" id="validVotesInput" 
                                   min="0" step="1" value="<?php echo $ec8b['valid_votes']; ?>" 
                                   onchange="updateTotals()" />
                        </div>
                    </div>

                    <div id="mismatchAlert" style="display:<?php echo $ec8b['mismatch_alert'] ? 'block' : 'none'; ?>;margin:12px 0;padding:10px 14px;background:#FEF2F2;border:1px solid #FECACA;border-radius:8px;color:#991B1B;font-size:0.8rem;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Mismatch Alert:</strong> The sum of party votes (<span id="mismatchPartySum"><?php echo array_sum($party_votes); ?></span>) 
                        does not match the valid votes (<span id="mismatchValidVotes"><?php echo $ec8b['valid_votes']; ?></span>). 
                        Difference: <span id="mismatchDiff"><?php echo abs(array_sum($party_votes) - $ec8b['valid_votes']); ?></span>
                    </div>

                    <div class="btn-group" style="margin-top:16px;">
                        <a href="ec8b-history.php" class="btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" name="action" value="draft" class="btn-draft">
                            <i class="fas fa-save"></i> Update Draft
                        </button>
                        <button type="submit" name="action" value="submit" class="btn-submit" onclick="return confirm('Submit this EC8B form? This will send it for verification.')">
                            <i class="fas fa-paper-plane"></i> Submit EC8B
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script>
function updateTotals() {
    var partyInputs = document.querySelectorAll('.party-vote-item input[type="number"]');
    var partySum = 0;
    partyInputs.forEach(function(input) {
        partySum += parseInt(input.value) || 0;
    });
    
    var validVotes = parseInt(document.getElementById('validVotesInput').value) || 0;
    var rejectedVotes = parseInt(document.getElementById('rejectedVotes').value) || 0;
    var totalVotes = validVotes + rejectedVotes;
    
    document.getElementById('partySum').textContent = partySum;
    document.getElementById('validVotes').textContent = validVotes;
    document.getElementById('totalVotes').textContent = totalVotes;
    
    var mismatchAlert = document.getElementById('mismatchAlert');
    if (partySum !== validVotes) {
        mismatchAlert.style.display = 'block';
        document.getElementById('mismatchPartySum').textContent = partySum;
        document.getElementById('mismatchValidVotes').textContent = validVotes;
        document.getElementById('mismatchDiff').textContent = Math.abs(partySum - validVotes);
    } else {
        mismatchAlert.style.display = 'none';
    }
}

// Same sidebar scripts as index.php
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
    updateTotals();
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