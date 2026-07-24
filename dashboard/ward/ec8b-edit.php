<?php
// ============================================================
// WARD COORDINATOR - EDIT EC8B
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
$lga_id = SessionManager::get('lga_id');
$state_id = SessionManager::get('state_id');
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
// GET EC8B ID
// ============================================================
$ec8b_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($ec8b_id <= 0) {
    header('Location: ec8b-history.php');
    exit();
}

// ============================================================
// FETCH EC8B DETAILS
// ============================================================
$ec8b = null;
$error_message = '';

try {
    $stmt = $db->prepare("
        SELECT * FROM results_ec8b 
        WHERE id = ? AND tenant_id = ? AND ward_id = ? AND status = 'pending'
    ");
    $stmt->execute([$ec8b_id, $tenant_id, $ward_id]);
    $ec8b = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ec8b) {
        header('Location: ec8b-history.php?error=notfound');
        exit();
    }
    
} catch (Exception $e) {
    error_log("Error fetching EC8B: " . $e->getMessage());
    header('Location: ec8b-history.php?error=db');
    exit();
}

// ============================================================
// FETCH ELECTIONS AND POLLING UNITS
// ============================================================
$elections = [];
$polling_units = [];

try {
    // Get elections
    $stmt = $db->prepare("
        SELECT id, name, type, status, election_date 
        FROM elections 
        WHERE tenant_id = ? AND deleted_at IS NULL
        AND JSON_CONTAINS(wards_json, JSON_QUOTE(?))
        ORDER BY election_date DESC
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $elections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get polling units
    $stmt = $db->prepare("
        SELECT id, name, code, registered_voters 
        FROM polling_units 
        WHERE ward_id = ? AND is_active = 1
        ORDER BY name ASC
    ");
    $stmt->execute([$ward_id]);
    $polling_units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error fetching data: " . $e->getMessage());
}

// ============================================================
// PARSE PARTY VOTES
// ============================================================
$party_votes = json_decode($ec8b['party_votes_json'] ?? '{}', true);
$calculated_total = json_decode($ec8b['calculated_total_json'] ?? '{}', true);

// ============================================================
// HANDLE UPDATE
// ============================================================
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pu_id = isset($_POST['pu_id']) ? (int)$_POST['pu_id'] : 0;
    $election_id = isset($_POST['election_id']) ? (int)$_POST['election_id'] : 0;
    $rejected_votes = isset($_POST['rejected_votes']) ? (int)$_POST['rejected_votes'] : 0;
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
    
    // Get party votes
    $new_party_votes = [];
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'party_') === 0) {
            $party_name = str_replace('party_', '', $key);
            $new_party_votes[$party_name] = (int)$value;
        }
    }
    
    $valid_votes = array_sum($new_party_votes);
    $total_votes = $valid_votes + $rejected_votes;
    
    // Check for mismatch
    $mismatch_alert = 0;
    $mismatch_details = null;
    
    if ($valid_votes != ($ec8b['valid_votes'] ?? 0)) {
        $mismatch_alert = 1;
        $mismatch_details = json_encode([
            'calculated_valid' => $valid_votes,
            'previous_valid' => $ec8b['valid_votes'] ?? 0,
            'difference' => $valid_votes - ($ec8b['valid_votes'] ?? 0)
        ]);
    }
    
    try {
        $stmt = $db->prepare("
            UPDATE results_ec8b 
            SET pu_id = ?, election_id = ?, party_votes_json = ?, 
                valid_votes = ?, rejected_votes = ?, total_votes = ?,
                calculated_total_json = ?, mismatch_alert = ?, mismatch_details_json = ?
            WHERE id = ? AND tenant_id = ? AND ward_id = ?
        ");
        
        $calculated_total_json = json_encode([
            'valid_votes' => $valid_votes,
            'rejected_votes' => $rejected_votes,
            'total_votes' => $total_votes
        ]);
        
        $stmt->execute([
            $pu_id,
            $election_id,
            json_encode($new_party_votes),
            $valid_votes,
            $rejected_votes,
            $total_votes,
            $calculated_total_json,
            $mismatch_alert,
            $mismatch_details,
            $ec8b_id,
            $tenant_id,
            $ward_id
        ]);
        
        logActivity($user_id, 'ec8b_updated', "Updated EC8B ID: $ec8b_id", 'results_ec8b', $ec8b_id);
        
        $success_message = "EC8B updated successfully!";
        header('Location: ec8b-details.php?id=' . $ec8b_id . '&success=' . urlencode($success_message));
        exit();
        
    } catch (Exception $e) {
        $error_message = "Error updating EC8B: " . $e->getMessage();
        error_log("EC8B update error: " . $e->getMessage());
    }
}

$page_title = 'Edit EC8B';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.edit-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.edit-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.edit-header h2 i {
    color: var(--primary);
}

.edit-form {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px;
}
.edit-form .form-group {
    margin-bottom: 16px;
}
.edit-form .form-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 4px;
}
.edit-form .form-group select,
.edit-form .form-group input,
.edit-form .form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    background: white;
}
.edit-form .form-group textarea {
    resize: vertical;
    min-height: 80px;
    font-family: inherit;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.party-votes {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 12px;
    margin: 12px 0;
}
.party-vote-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.party-vote-item label {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--gray-600);
}
.party-vote-item input {
    padding: 8px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    text-align: center;
}

.totals-row {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr 1fr;
    gap: 12px;
    margin: 12px 0;
}
.totals-row .total-item {
    background: var(--gray-50);
    padding: 10px 12px;
    border-radius: var(--radius);
    text-align: center;
}
.totals-row .total-item label {
    font-size: 0.7rem;
    color: var(--gray-500);
    font-weight: 500;
    display: block;
}
.totals-row .total-item .value {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--gray-800);
}
.totals-row .total-item .value.green { color: #10B981; }
.totals-row .total-item .value.blue { color: #3B82F6; }
.totals-row .total-item .value.orange { color: #F59E0B; }

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
.alert-danger {
    background: #FEF2F2;
    border: 1px solid #FEE2E2;
    color: #991B1B;
}
.alert i {
    font-size: 1.1rem;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    .party-votes {
        grid-template-columns: 1fr 1fr;
    }
    .totals-row {
        grid-template-columns: 1fr 1fr;
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
        <div class="edit-header">
            <div>
                <h2><i class="fas fa-edit"></i> Edit EC8B</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward • Form #<?php echo $ec8b_id; ?>
                </p>
            </div>
            <div>
                <a href="ec8b-history.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($ec8b): ?>
            <!-- Edit Form -->
            <div class="edit-form">
                <form method="POST" action="" id="ec8bForm">
                    <!-- Basic Information -->
                    <div class="form-row">
                        <div class="form-group">
                            <label>Election</label>
                            <select name="election_id" id="election_id">
                                <option value="">-- Select Election --</option>
                                <?php foreach ($elections as $e): ?>
                                    <option value="<?php echo $e['id']; ?>" <?php echo ($e['id'] == $ec8b['election_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($e['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Polling Unit</label>
                            <select name="pu_id" id="pu_id">
                                <option value="">-- Select Polling Unit --</option>
                                <?php foreach ($polling_units as $pu): ?>
                                    <option value="<?php echo $pu['id']; ?>" <?php echo ($pu['id'] == $ec8b['pu_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($pu['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Party Votes -->
                    <div class="form-group">
                        <label>Party Votes</label>
                        <div class="party-votes">
                            <?php 
                            $parties = ['APC', 'PDP', 'LP', 'NNPP', 'APGA', 'SDP', 'ADC', 'YPP', 'Other'];
                            foreach ($parties as $party): 
                                $value = $party_votes[$party] ?? 0;
                            ?>
                                <div class="party-vote-item">
                                    <label><?php echo $party; ?></label>
                                    <input type="number" name="party_<?php echo $party; ?>" 
                                           min="0" step="1" value="<?php echo $value; ?>" 
                                           onchange="calculateTotals()">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Totals -->
                    <div class="form-group">
                        <label>Totals</label>
                        <div class="totals-row">
                            <div class="total-item">
                                <label>Total Valid Votes</label>
                                <div class="value blue" id="totalValid"><?php echo array_sum($party_votes); ?></div>
                            </div>
                            <div class="total-item">
                                <label>Rejected Votes</label>
                                <input type="number" name="rejected_votes" id="rejected_votes" 
                                       min="0" step="1" value="<?php echo $ec8b['rejected_votes'] ?? 0; ?>" 
                                       style="width:100%;text-align:center;border:1px solid var(--gray-200);border-radius:var(--radius);padding:4px;" 
                                       onchange="calculateTotals()">
                            </div>
                            <div class="total-item">
                                <label>Total Votes Cast</label>
                                <div class="value orange" id="totalCast"><?php echo ($ec8b['total_votes'] ?? 0); ?></div>
                            </div>
                            <div class="total-item">
                                <label>Registered Voters</label>
                                <div class="value green" id="registeredVoters">0</div>
                            </div>
                        </div>
                    </div>

                    <!-- Remarks -->
                    <div class="form-group">
                        <label>Remarks</label>
                        <textarea name="remarks" rows="3" placeholder="Add remarks..."><?php echo htmlspecialchars($ec8b['remarks'] ?? ''); ?></textarea>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Update EC8B
                        </button>
                        <a href="ec8b-details.php?id=<?php echo $ec8b_id; ?>" class="btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
// Calculate totals
function calculateTotals() {
    const partyInputs = document.querySelectorAll('.party-vote-item input[type="number"]');
    let totalValid = 0;
    
    partyInputs.forEach(function(input) {
        const val = parseInt(input.value) || 0;
        if (val >= 0) {
            totalValid += val;
        }
    });
    
    const rejected = parseInt(document.getElementById('rejected_votes').value) || 0;
    const totalCast = totalValid + rejected;
    
    document.getElementById('totalValid').textContent = totalValid.toLocaleString();
    document.getElementById('totalCast').textContent = totalCast.toLocaleString();
}

// Update registered voters
document.getElementById('pu_id').addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    const text = selected.textContent;
    const match = text.match(/(\d+[\d,]*)\s*voters/);
    if (match) {
        const voters = parseInt(match[1].replace(/,/g, ''));
        document.getElementById('registeredVoters').textContent = voters.toLocaleString();
    } else {
        document.getElementById('registeredVoters').textContent = '0';
    }
});

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    calculateTotals();
    const puSelect = document.getElementById('pu_id');
    if (puSelect.value) {
        const event = new Event('change');
        puSelect.dispatchEvent(event);
    }
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