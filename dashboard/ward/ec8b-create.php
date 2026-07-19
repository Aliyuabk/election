<?php
// ============================================================
// WARD COORDINATOR - CREATE EC8B FORM
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
// FETCH WARD NAME
// ============================================================
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
    error_log("Error fetching ward name: " . $e->getMessage());
}

// ============================================================
// FETCH ACTIVE ELECTION FOR WARD
// ============================================================
$election_id = null;
$election_name = '';
$elections = [];

try {
    $stmt = $db->prepare("
        SELECT id, name, type, election_date, status 
        FROM elections 
        WHERE tenant_id = ? AND deleted_at IS NULL
        AND JSON_CONTAINS(wards_json, JSON_QUOTE(?))
        AND status IN ('active', 'upcoming')
        ORDER BY election_date DESC
    ");
    $stmt->execute([$tenant_id, $ward_id]);
    $elections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get active election first
    foreach ($elections as $e) {
        if ($e['status'] === 'active') {
            $election_id = $e['id'];
            $election_name = $e['name'];
            break;
        }
    }
    // If no active, use first one
    if (!$election_id && !empty($elections)) {
        $election_id = $elections[0]['id'];
        $election_name = $elections[0]['name'];
    }
} catch (Exception $e) {
    error_log("Error fetching elections: " . $e->getMessage());
}

// ============================================================
// FETCH POLLING UNITS FOR THIS WARD
// ============================================================
$polling_units = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, code, registered_voters, is_active 
        FROM polling_units 
        WHERE ward_id = ? AND is_active = 1
        ORDER BY name ASC
    ");
    $stmt->execute([$ward_id]);
    $polling_units = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching polling units: " . $e->getMessage());
}

// ============================================================
// HANDLE EC8B CREATION
// ============================================================
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $election_id = isset($_POST['election_id']) ? (int)$_POST['election_id'] : 0;
    $pu_id = isset($_POST['pu_id']) ? (int)$_POST['pu_id'] : 0;
    
    // Get party votes from form
    $party_votes = [];
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'party_') === 0) {
            $party_name = str_replace('party_', '', $key);
            $party_votes[$party_name] = (int)$value;
        }
    }
    
    $valid_votes = isset($_POST['valid_votes']) ? (int)$_POST['valid_votes'] : 0;
    $rejected_votes = isset($_POST['rejected_votes']) ? (int)$_POST['rejected_votes'] : 0;
    $total_votes = isset($_POST['total_votes']) ? (int)$_POST['total_votes'] : 0;
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
    $form_photo = isset($_POST['form_photo']) ? trim($_POST['form_photo']) : '';
    
    if ($election_id > 0 && $pu_id > 0 && !empty($party_votes)) {
        try {
            // Calculate totals
            $calculated_valid = array_sum($party_votes);
            $calculated_total = $calculated_valid + $rejected_votes;
            
            // Check for mismatch
            $mismatch_alert = 0;
            $mismatch_details = null;
            
            if ($valid_votes > 0 && $calculated_valid != $valid_votes) {
                $mismatch_alert = 1;
                $mismatch_details = json_encode([
                    'calculated_valid' => $calculated_valid,
                    'entered_valid' => $valid_votes,
                    'difference' => $calculated_valid - $valid_votes
                ]);
            }
            
            // Insert EC8B
            $stmt = $db->prepare("
                INSERT INTO results_ec8b (
                    tenant_id, election_id, ward_id, lga_id, state_id, coordinator_id,
                    party_votes_json, valid_votes, rejected_votes, total_votes,
                    calculated_total_json, mismatch_alert, mismatch_details_json,
                    form_photo_url, status, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, 'pending', NOW()
                )
            ");
            
            $party_votes_json = json_encode($party_votes);
            $calculated_total_json = json_encode([
                'valid_votes' => $calculated_valid,
                'rejected_votes' => $rejected_votes,
                'total_votes' => $calculated_total
            ]);
            
            $stmt->execute([
                $tenant_id,
                $election_id,
                $ward_id,
                $lga_id,
                $state_id,
                $user_id,
                $party_votes_json,
                $valid_votes > 0 ? $valid_votes : $calculated_valid,
                $rejected_votes,
                $total_votes > 0 ? $total_votes : $calculated_total,
                $calculated_total_json,
                $mismatch_alert,
                $mismatch_details,
                $form_photo
            ]);
            
            $ec8b_id = $db->lastInsertId();
            
            // Log activity
            logActivity($user_id, 'ec8b_created', "Created EC8B form ID: $ec8b_id for ward: $ward_id", 'results_ec8b', $ec8b_id);
            
            $success_message = "EC8B form created successfully. ID: " . $ec8b_id;
            
            // Clear form fields after success
            $_POST = [];
            
        } catch (Exception $e) {
            $error_message = "Error creating EC8B: " . $e->getMessage();
            error_log("EC8B creation error: " . $e->getMessage());
        }
    } else {
        $error_message = "Please fill in all required fields.";
    }
}

$page_title = 'Create EC8B';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<style>
.ec8b-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}
.ec8b-header h2 {
    font-size: 1.3rem;
    font-weight: 700;
    margin: 0;
}
.ec8b-header h2 i {
    color: var(--primary);
}

.ec8b-form {
    background: white;
    border-radius: var(--radius);
    border: 1px solid var(--gray-200);
    padding: 24px;
}
.ec8b-form .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.ec8b-form .form-group {
    margin-bottom: 16px;
}
.ec8b-form .form-group label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 4px;
}
.ec8b-form .form-group label .required {
    color: #EF4444;
}
.ec8b-form .form-group select,
.ec8b-form .form-group input,
.ec8b-form .form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    font-size: 0.85rem;
    background: white;
}
.ec8b-form .form-group .helper {
    font-size: 0.7rem;
    color: var(--gray-400);
    margin-top: 4px;
}
.ec8b-form .form-group .error {
    font-size: 0.7rem;
    color: #EF4444;
    margin-top: 4px;
    display: none;
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

.alert {
    padding: 12px 16px;
    border-radius: var(--radius);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.alert-success {
    background: #ECFDF5;
    border: 1px solid #D1FAE5;
    color: #065F46;
}
.alert-danger {
    background: #FEF2F2;
    border: 1px solid #FEE2E2;
    color: #991B1B;
}
.alert-warning {
    background: #FFFBEB;
    border: 1px solid #FEF3C7;
    color: #92400E;
}
.alert i {
    font-size: 1.1rem;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid var(--gray-200);
}

@media (max-width: 768px) {
    .ec8b-form .form-row {
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

@media (max-width: 480px) {
    .party-votes {
        grid-template-columns: 1fr;
    }
    .totals-row {
        grid-template-columns: 1fr 1fr;
    }
}
</style>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="ec8b-header">
            <div>
                <h2><i class="fas fa-upload"></i> Create EC8B Form</h2>
                <p style="color: var(--gray-500); margin: 2px 0 0; font-size: 0.85rem;">
                    <?php echo htmlspecialchars($ward_name); ?> Ward • <?php echo htmlspecialchars($election_name ?: 'No Active Election'); ?>
                </p>
            </div>
            <div>
                <a href="ec8b-history.php" class="btn-secondary-sm">
                    <i class="fas fa-history"></i> View History
                </a>
                <a href="manage-pu-agents.php" class="btn-secondary-sm">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <?php if (empty($elections)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                No active or upcoming elections found for this ward. Please contact your LGA coordinator.
            </div>
        <?php elseif (empty($polling_units)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                No polling units found in this ward. Please add polling units first.
            </div>
        <?php else: ?>

        <!-- Success/Error Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                <a href="ec8b-history.php" style="margin-left:auto;font-weight:600;">View History →</a>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- EC8B Form -->
        <div class="ec8b-form">
            <form method="POST" action="" id="ec8bForm">
                <!-- Basic Information -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Election <span class="required">*</span></label>
                        <select name="election_id" id="election_id" required>
                            <option value="">-- Select Election --</option>
                            <?php foreach ($elections as $e): ?>
                                <option value="<?php echo $e['id']; ?>" <?php echo ($e['id'] == $election_id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($e['name']); ?> 
                                    (<?php echo ucfirst($e['status']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Polling Unit <span class="required">*</span></label>
                        <select name="pu_id" id="pu_id" required>
                            <option value="">-- Select Polling Unit --</option>
                            <?php foreach ($polling_units as $pu): ?>
                                <option value="<?php echo $pu['id']; ?>">
                                    <?php echo htmlspecialchars($pu['name']); ?> 
                                    (<?php echo htmlspecialchars($pu['code']); ?>)
                                    - <?php echo number_format($pu['registered_voters']); ?> voters
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Party Votes -->
                <div class="form-group">
                    <label>Party Votes <span class="required">*</span></label>
                    <div class="party-votes" id="partyVotes">
                        <div class="party-vote-item">
                            <label>APC</label>
                            <input type="number" name="party_APC" id="party_APC" min="0" step="1" value="0" onchange="calculateTotals()">
                        </div>
                        <div class="party-vote-item">
                            <label>PDP</label>
                            <input type="number" name="party_PDP" id="party_PDP" min="0" step="1" value="0" onchange="calculateTotals()">
                        </div>
                        <div class="party-vote-item">
                            <label>LP</label>
                            <input type="number" name="party_LP" id="party_LP" min="0" step="1" value="0" onchange="calculateTotals()">
                        </div>
                        <div class="party-vote-item">
                            <label>NNPP</label>
                            <input type="number" name="party_NNPP" id="party_NNPP" min="0" step="1" value="0" onchange="calculateTotals()">
                        </div>
                        <div class="party-vote-item">
                            <label>APGA</label>
                            <input type="number" name="party_APGA" id="party_APGA" min="0" step="1" value="0" onchange="calculateTotals()">
                        </div>
                        <div class="party-vote-item">
                            <label>SDP</label>
                            <input type="number" name="party_SDP" id="party_SDP" min="0" step="1" value="0" onchange="calculateTotals()">
                        </div>
                        <div class="party-vote-item">
                            <label>ADC</label>
                            <input type="number" name="party_ADC" id="party_ADC" min="0" step="1" value="0" onchange="calculateTotals()">
                        </div>
                        <div class="party-vote-item">
                            <label>YPP</label>
                            <input type="number" name="party_YPP" id="party_YPP" min="0" step="1" value="0" onchange="calculateTotals()">
                        </div>
                        <div class="party-vote-item">
                            <label>Other</label>
                            <input type="number" name="party_Other" id="party_Other" min="0" step="1" value="0" onchange="calculateTotals()">
                        </div>
                    </div>
                    <div class="helper">Enter the number of votes for each party. Leave as 0 for parties with no votes.</div>
                </div>

                <!-- Totals -->
                <div class="form-group">
                    <label>Totals</label>
                    <div class="totals-row">
                        <div class="total-item">
                            <label>Total Valid Votes</label>
                            <div class="value blue" id="totalValid">0</div>
                        </div>
                        <div class="total-item">
                            <label>Rejected Votes</label>
                            <input type="number" name="rejected_votes" id="rejected_votes" min="0" step="1" value="0" style="width:100%;text-align:center;border:1px solid var(--gray-200);border-radius:var(--radius);padding:4px;" onchange="calculateTotals()">
                        </div>
                        <div class="total-item">
                            <label>Total Votes Cast</label>
                            <div class="value orange" id="totalCast">0</div>
                        </div>
                        <div class="total-item">
                            <label>Registered Voters</label>
                            <div class="value green" id="registeredVoters">0</div>
                        </div>
                    </div>
                </div>

                <!-- Remarks & Photo -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Remarks</label>
                        <textarea name="remarks" id="remarks" rows="3" placeholder="Add any remarks about this result..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Form Photo URL</label>
                        <input type="text" name="form_photo" id="form_photo" placeholder="Enter URL or upload photo...">
                        <div class="helper">Upload a photo of the filled EC8B form</div>
                        <div style="margin-top:8px;">
                            <button type="button" class="btn-secondary-sm" onclick="document.getElementById('photoUpload').click();">
                                <i class="fas fa-upload"></i> Upload Photo
                            </button>
                            <input type="file" id="photoUpload" accept="image/*" style="display:none;" onchange="handlePhotoUpload(this)">
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Create EC8B
                    </button>
                    <button type="button" class="btn-secondary" onclick="resetForm()">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                    <button type="button" class="btn-secondary" onclick="window.location.href='ec8b-draft.php'">
                        <i class="fas fa-file-alt"></i> Save as Draft
                    </button>
                </div>
            </form>
        </div>

        <?php endif; ?>
    </div>
</main>

<script>
// Calculate totals
function calculateTotals() {
    // Get all party votes
    const partyInputs = document.querySelectorAll('#partyVotes input[type="number"]');
    let totalValid = 0;
    
    partyInputs.forEach(function(input) {
        const val = parseInt(input.value) || 0;
        if (val >= 0) {
            totalValid += val;
        }
    });
    
    const rejected = parseInt(document.getElementById('rejected_votes').value) || 0;
    const totalCast = totalValid + rejected;
    
    // Update display
    document.getElementById('totalValid').textContent = totalValid.toLocaleString();
    document.getElementById('totalCast').textContent = totalCast.toLocaleString();
    
    // Update hidden fields if they exist
    const validInput = document.querySelector('input[name="valid_votes"]');
    const totalInput = document.querySelector('input[name="total_votes"]');
    if (validInput) validInput.value = totalValid;
    if (totalInput) totalInput.value = totalCast;
}

// Handle photo upload
function handlePhotoUpload(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('form_photo').value = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Reset form
function resetForm() {
    if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
        document.getElementById('ec8bForm').reset();
        calculateTotals();
        document.querySelectorAll('.error').forEach(function(el) {
            el.style.display = 'none';
        });
    }
}

// Validate form
document.getElementById('ec8bForm').addEventListener('submit', function(e) {
    const election = document.getElementById('election_id').value;
    const pu = document.getElementById('pu_id').value;
    
    if (!election || !pu) {
        e.preventDefault();
        alert('Please select both an election and a polling unit.');
        return false;
    }
    
    // Check if any party votes are entered
    const partyInputs = document.querySelectorAll('#partyVotes input[type="number"]');
    let hasVotes = false;
    partyInputs.forEach(function(input) {
        if (parseInt(input.value) > 0) {
            hasVotes = true;
        }
    });
    
    if (!hasVotes) {
        e.preventDefault();
        alert('Please enter votes for at least one party.');
        return false;
    }
    
    return confirm('Are you sure you want to create this EC8B form?');
});

// Update registered voters when PU changes
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

// Initialize on load
document.addEventListener('DOMContentLoaded', function() {
    calculateTotals();
    
    // Trigger PU change to update voters
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