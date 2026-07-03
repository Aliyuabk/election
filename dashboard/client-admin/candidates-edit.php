<?php
// ============================================================
// ADD/EDIT CANDIDATE - CLIENT ADMIN (PROFESSIONAL UI)
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

// Check role - only client_admin can access this page
if (SessionManager::get('role_level') !== 'client_admin') {
    header('Location: ../client-admin/');
    exit();
}

// Get database connection
$db = getDB();

// Get user info
$user_id = SessionManager::get('user_id');
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');
$tenant_id = SessionManager::get('tenant_id');

// ============================================================
// GET CANDIDATE ID FOR EDIT
// ============================================================
$candidate_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $candidate_id > 0;

// ============================================================
// FETCH CANDIDATE DATA FOR EDIT
// ============================================================
$candidate = null;
if ($is_edit) {
    try {
        $stmt = $db->prepare("
            SELECT c.*, e.name as election_name, p.name as party_name, p.acronym as party_acronym
            FROM candidates c
            LEFT JOIN elections e ON c.election_id = e.id
            LEFT JOIN political_parties p ON c.party_id = p.id
            WHERE c.id = ? AND c.tenant_id = ?
        ");
        $stmt->execute([$candidate_id, $tenant_id]);
        $candidate = $stmt->fetch();
        
        if (!$candidate) {
            header('Location: candidates.php');
            exit();
        }
    } catch (Exception $e) {
        header('Location: candidates.php');
        exit();
    }
}

// ============================================================
// FETCH ELECTIONS FOR DROPDOWN
// ============================================================
$elections = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, type, election_date, status 
        FROM elections 
        WHERE tenant_id = ? AND deleted_at IS NULL AND status IN ('upcoming', 'active')
        ORDER BY election_date DESC
    ");
    $stmt->execute([$tenant_id]);
    $elections = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// FETCH PARTIES FOR DROPDOWN
// ============================================================
$parties = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, acronym, logo_url 
        FROM political_parties 
        WHERE tenant_id = ? AND is_active = 1
        ORDER BY name
    ");
    $stmt->execute([$tenant_id]);
    $parties = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
$action_result = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add_candidate':
                $election_id = (int)($_POST['election_id'] ?? 0);
                $party_id = (int)($_POST['party_id'] ?? 0);
                $first_name = trim($_POST['first_name'] ?? '');
                $last_name = trim($_POST['last_name'] ?? '');
                $position = trim($_POST['position'] ?? '');
                $biography = trim($_POST['biography'] ?? '');
                $manifesto = trim($_POST['manifesto'] ?? '');
                $contact_email = trim($_POST['contact_email'] ?? '');
                $contact_phone = trim($_POST['contact_phone'] ?? '');
                $social_media_json = json_encode([
                    'facebook' => trim($_POST['facebook'] ?? ''),
                    'twitter' => trim($_POST['twitter'] ?? ''),
                    'instagram' => trim($_POST['instagram'] ?? ''),
                    'linkedin' => trim($_POST['linkedin'] ?? ''),
                    'website' => trim($_POST['website'] ?? '')
                ]);
                
                if (empty($first_name) || empty($last_name) || empty($position) || $election_id <= 0) {
                    throw new Exception('First name, last name, position, and election are required.');
                }
                
                // Handle file uploads
                $photograph_url = '';
                $campaign_logo_url = '';
                $manifesto_file = '';
                
                // In production, handle file uploads here
                // For now, use placeholder values
                if (!empty($_FILES['photograph']['name'])) {
                    $photograph_url = '/uploads/candidates/' . uniqid() . '_' . basename($_FILES['photograph']['name']);
                }
                if (!empty($_FILES['campaign_logo']['name'])) {
                    $campaign_logo_url = '/uploads/candidates/' . uniqid() . '_' . basename($_FILES['campaign_logo']['name']);
                }
                if (!empty($_FILES['manifesto_file']['name'])) {
                    $manifesto_file = '/uploads/candidates/' . uniqid() . '_' . basename($_FILES['manifesto_file']['name']);
                }
                
                $stmt = $db->prepare("
                    INSERT INTO candidates (
                        tenant_id, election_id, party_id, first_name, last_name,
                        photograph_url, position, biography, manifesto,
                        contact_email, contact_phone, social_media_json, campaign_logo_url,
                        is_active
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([
                    $tenant_id, $election_id, $party_id, $first_name, $last_name,
                    $photograph_url, $position, $biography, $manifesto,
                    $contact_email, $contact_phone, $social_media_json, $campaign_logo_url
                ]);
                
                logActivity($user_id, 'candidate_added', "Added candidate: $first_name $last_name");
                $action_result = ['success' => true, 'message' => "Candidate '$first_name $last_name' added successfully."];
                break;
                
            case 'edit_candidate':
                $id = (int)($_POST['id'] ?? 0);
                $election_id = (int)($_POST['election_id'] ?? 0);
                $party_id = (int)($_POST['party_id'] ?? 0);
                $first_name = trim($_POST['first_name'] ?? '');
                $last_name = trim($_POST['last_name'] ?? '');
                $position = trim($_POST['position'] ?? '');
                $biography = trim($_POST['biography'] ?? '');
                $manifesto = trim($_POST['manifesto'] ?? '');
                $contact_email = trim($_POST['contact_email'] ?? '');
                $contact_phone = trim($_POST['contact_phone'] ?? '');
                $social_media_json = json_encode([
                    'facebook' => trim($_POST['facebook'] ?? ''),
                    'twitter' => trim($_POST['twitter'] ?? ''),
                    'instagram' => trim($_POST['instagram'] ?? ''),
                    'linkedin' => trim($_POST['linkedin'] ?? ''),
                    'website' => trim($_POST['website'] ?? '')
                ]);
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if ($id <= 0 || empty($first_name) || empty($last_name) || empty($position) || $election_id <= 0) {
                    throw new Exception('Invalid data provided.');
                }
                
                $stmt = $db->prepare("
                    UPDATE candidates SET 
                        election_id = ?, party_id = ?, first_name = ?, last_name = ?,
                        position = ?, biography = ?, manifesto = ?,
                        contact_email = ?, contact_phone = ?, social_media_json = ?,
                        is_active = ?
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmt->execute([
                    $election_id, $party_id, $first_name, $last_name,
                    $position, $biography, $manifesto,
                    $contact_email, $contact_phone, $social_media_json,
                    $is_active, $id, $tenant_id
                ]);
                
                logActivity($user_id, 'candidate_updated', "Updated candidate ID: $id");
                $action_result = ['success' => true, 'message' => 'Candidate updated successfully.'];
                break;
        }
    } catch (Exception $e) {
        $action_result = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       ADD/EDIT CANDIDATE - PROFESSIONAL UI STYLES
       ============================================================ */
    
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
    
    .btn-outline {
        padding: 10px 18px;
        background: transparent;
        color: var(--gray-600);
        border: 1.5px solid var(--gray-200);
        border-radius: 10px;
        font-weight: 500;
        font-size: 0.85rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-outline:hover {
        background: var(--gray-50);
        border-color: var(--primary);
        color: var(--primary);
    }
    .btn-primary {
        padding: 10px 20px;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(var(--primary-rgb), 0.3);
    }
    .btn-secondary {
        padding: 10px 20px;
        background: var(--gray-100);
        color: var(--gray-600);
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-secondary:hover {
        background: var(--gray-200);
    }
    
    .form-container {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 32px 36px;
        box-shadow: var(--shadow);
        max-width: 900px;
        margin: 0 auto;
    }
    .form-container:hover {
        box-shadow: var(--shadow-hover);
    }
    .form-container .form-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 2px solid var(--gray-100);
    }
    .form-container .form-header .icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: #EFF6FF;
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
    }
    .form-container .form-header h3 {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--gray-800);
    }
    .form-container .form-header p {
        font-size: 0.85rem;
        color: var(--gray-500);
        margin-top: 2px;
    }
    
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .form-group.full-width {
        grid-column: 1 / -1;
    }
    .form-group label {
        font-weight: 600;
        font-size: 0.85rem;
        color: var(--gray-700);
    }
    .form-group label .required {
        color: var(--danger);
        margin-left: 2px;
    }
    .form-group .help-text {
        font-size: 0.75rem;
        color: var(--gray-400);
        margin-top: 2px;
    }
    .form-group input,
    .form-group select,
    .form-group textarea {
        padding: 10px 14px;
        border: 1.5px solid var(--gray-200);
        border-radius: 10px;
        font-family: 'Inter', sans-serif;
        font-size: 0.85rem;
        transition: var(--transition);
        background: var(--gray-50);
        color: var(--gray-700);
        width: 100%;
    }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 4px rgba(var(--primary-rgb), 0.08);
    }
    .form-group textarea {
        resize: vertical;
        min-height: 80px;
    }
    
    .file-upload-area {
        border: 2px dashed var(--gray-200);
        border-radius: 10px;
        padding: 20px;
        text-align: center;
        cursor: pointer;
        transition: var(--transition);
        background: var(--gray-50);
        position: relative;
    }
    .file-upload-area:hover {
        border-color: var(--primary);
        background: #EFF6FF;
    }
    .file-upload-area.dragover {
        border-color: var(--primary);
        background: #EFF6FF;
        transform: scale(1.01);
    }
    .file-upload-area i {
        font-size: 2rem;
        color: var(--gray-400);
        display: block;
        margin-bottom: 8px;
        transition: var(--transition);
    }
    .file-upload-area:hover i {
        color: var(--primary);
    }
    .file-upload-area p {
        font-size: 0.85rem;
        color: var(--gray-500);
        margin-bottom: 2px;
    }
    .file-upload-area .file-types {
        font-size: 0.7rem;
        color: var(--gray-400);
    }
    .file-upload-area input[type="file"] {
        display: none;
    }
    .file-preview {
        display: none;
        margin-top: 12px;
        padding: 12px 16px;
        background: var(--gray-50);
        border-radius: 8px;
        border: 1px solid var(--gray-200);
        text-align: left;
        animation: fadeIn 0.3s ease;
    }
    .file-preview.show {
        display: block;
    }
    .file-preview .file-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .file-preview .file-info .file-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }
    .file-preview .file-info .file-icon.image { background: #FFFBEB; color: #F59E0B; }
    .file-preview .file-info .file-icon.pdf { background: #FEF2F2; color: #DC2626; }
    .file-preview .file-info .file-icon.doc { background: #EFF6FF; color: #2563EB; }
    .file-preview .file-info .file-details {
        flex: 1;
    }
    .file-preview .file-info .file-details .file-name {
        font-weight: 500;
        font-size: 0.85rem;
        color: var(--gray-700);
    }
    .file-preview .file-info .file-details .file-size {
        font-size: 0.7rem;
        color: var(--gray-400);
    }
    .file-preview .file-info .file-remove {
        background: none;
        border: none;
        color: var(--gray-400);
        cursor: pointer;
        transition: var(--transition);
        padding: 4px;
        border-radius: 4px;
    }
    .file-preview .file-info .file-remove:hover {
        background: #FEF2F2;
        color: var(--danger);
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-8px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .form-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 24px;
        padding-top: 20px;
        border-top: 2px solid var(--gray-100);
    }
    .form-actions .btn {
        padding: 10px 24px;
        border-radius: 10px;
        border: none;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .form-actions .btn-primary {
        background: var(--primary);
        color: white;
    }
    .form-actions .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.25);
    }
    .form-actions .btn-secondary {
        background: var(--gray-100);
        color: var(--gray-600);
    }
    .form-actions .btn-secondary:hover {
        background: var(--gray-200);
    }
    
    .toast {
        padding: 14px 20px;
        border-radius: 10px;
        color: white;
        font-size: 0.85rem;
        font-weight: 500;
        box-shadow: var(--shadow-hover);
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .toast.success { background: var(--secondary); }
    .toast.error { background: var(--danger); }
    
    /* Social media input group */
    .social-input {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .social-input .social-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        flex-shrink: 0;
        font-size: 0.9rem;
    }
    .social-input .social-icon.facebook { background: #1877F2; }
    .social-input .social-icon.twitter { background: #1DA1F2; }
    .social-input .social-icon.instagram { background: #E4405F; }
    .social-input .social-icon.linkedin { background: #0A66C2; }
    .social-input .social-icon.website { background: #6B7280; }
    .social-input input {
        flex: 1;
    }
    
    @media (max-width: 768px) {
        .form-container {
            padding: 20px;
        }
        .form-grid {
            grid-template-columns: 1fr;
        }
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .form-actions {
            flex-direction: column;
        }
        .form-actions .btn {
            width: 100%;
            justify-content: center;
        }
        .social-input {
            flex-wrap: wrap;
        }
    }
    @media (max-width: 480px) {
        .form-container {
            padding: 14px;
        }
        .form-container .form-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas <?php echo $is_edit ? 'fa-edit' : 'fa-plus-circle'; ?>" style="color:var(--primary);margin-right:8px;"></i>
                    <?php echo $is_edit ? 'Edit Candidate' : 'Add New Candidate'; ?>
                    <small><?php echo $is_edit ? 'Update candidate information' : 'Register a new candidate for an election'; ?></small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="candidates.php" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Candidates
                </a>
            </div>
        </div>

        <!-- Toast Messages -->
        <?php if (!empty($action_result['message'])): ?>
        <div class="toast <?php echo $action_result['success'] ? 'success' : 'error'; ?>" style="position:static;animation:none;max-width:100%;margin-bottom:16px;">
            <i class="fas <?php echo $action_result['success'] ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($action_result['message']); ?>
        </div>
        <?php endif; ?>

        <!-- Form -->
        <div class="form-container">
            <div class="form-header">
                <div class="icon">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div>
                    <h3><?php echo $is_edit ? 'Edit Candidate' : 'New Candidate Registration'; ?></h3>
                    <p>Fill in the candidate details below. Fields marked with <span style="color:var(--danger);">*</span> are required.</p>
                </div>
            </div>

            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="<?php echo $is_edit ? 'edit_candidate' : 'add_candidate'; ?>">
                <?php if ($is_edit): ?>
                    <input type="hidden" name="id" value="<?php echo $candidate_id; ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <!-- Election -->
                    <div class="form-group">
                        <label>Election <span class="required">*</span></label>
                        <select name="election_id" required>
                            <option value="">Select Election</option>
                            <?php foreach ($elections as $election): ?>
                                <option value="<?php echo $election['id']; ?>" 
                                    <?php echo ($is_edit && $candidate['election_id'] == $election['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($election['name']); ?>
                                    (<?php echo date('M j, Y', strtotime($election['election_date'])); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Party -->
                    <div class="form-group">
                        <label>Political Party</label>
                        <select name="party_id">
                            <option value="">Select Party</option>
                            <?php foreach ($parties as $party): ?>
                                <option value="<?php echo $party['id']; ?>" 
                                    <?php echo ($is_edit && $candidate['party_id'] == $party['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($party['name']); ?>
                                    (<?php echo htmlspecialchars($party['acronym']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="help-text">Select the political party the candidate belongs to</div>
                    </div>

                    <!-- First Name -->
                    <div class="form-group">
                        <label>First Name <span class="required">*</span></label>
                        <input type="text" name="first_name" placeholder="Enter first name" required
                               value="<?php echo $is_edit ? htmlspecialchars($candidate['first_name']) : ''; ?>">
                    </div>

                    <!-- Last Name -->
                    <div class="form-group">
                        <label>Last Name <span class="required">*</span></label>
                        <input type="text" name="last_name" placeholder="Enter last name" required
                               value="<?php echo $is_edit ? htmlspecialchars($candidate['last_name']) : ''; ?>">
                    </div>

                    <!-- Position -->
                    <div class="form-group full-width">
                        <label>Position <span class="required">*</span></label>
                        <select name="position" required>
                            <option value="">Select Position</option>
                            <option value="presidential" <?php echo ($is_edit && $candidate['position'] == 'presidential') ? 'selected' : ''; ?>>Presidential</option>
                            <option value="vice_presidential" <?php echo ($is_edit && $candidate['position'] == 'vice_presidential') ? 'selected' : ''; ?>>Vice Presidential</option>
                            <option value="governorship" <?php echo ($is_edit && $candidate['position'] == 'governorship') ? 'selected' : ''; ?>>Governorship</option>
                            <option value="deputy_governorship" <?php echo ($is_edit && $candidate['position'] == 'deputy_governorship') ? 'selected' : ''; ?>>Deputy Governorship</option>
                            <option value="senatorial" <?php echo ($is_edit && $candidate['position'] == 'senatorial') ? 'selected' : ''; ?>>Senatorial</option>
                            <option value="house_of_reps" <?php echo ($is_edit && $candidate['position'] == 'house_of_reps') ? 'selected' : ''; ?>>House of Representatives</option>
                            <option value="house_of_assembly" <?php echo ($is_edit && $candidate['position'] == 'house_of_assembly') ? 'selected' : ''; ?>>House of Assembly</option>
                            <option value="lga_chairman" <?php echo ($is_edit && $candidate['position'] == 'lga_chairman') ? 'selected' : ''; ?>>LGA Chairman</option>
                            <option value="councillorship" <?php echo ($is_edit && $candidate['position'] == 'councillorship') ? 'selected' : ''; ?>>Councillorship</option>
                            <option value="party_primary" <?php echo ($is_edit && $candidate['position'] == 'party_primary') ? 'selected' : ''; ?>>Party Primary</option>
                            <option value="other" <?php echo ($is_edit && $candidate['position'] == 'other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                        <div class="help-text">The position the candidate is running for</div>
                    </div>

                    <!-- Biography -->
                    <div class="form-group full-width">
                        <label>Biography</label>
                        <textarea name="biography" placeholder="Enter candidate's biography, background, and experience..." rows="3"><?php echo $is_edit ? htmlspecialchars($candidate['biography']) : ''; ?></textarea>
                        <div class="help-text">Detailed background information about the candidate</div>
                    </div>

                    <!-- Manifesto -->
                    <div class="form-group full-width">
                        <label>Manifesto</label>
                        <textarea name="manifesto" placeholder="Enter candidate's manifesto, goals, and promises..." rows="3"><?php echo $is_edit ? htmlspecialchars($candidate['manifesto']) : ''; ?></textarea>
                        <div class="help-text">The candidate's campaign manifesto and key promises</div>
                    </div>

                    <!-- Contact Information -->
                    <div class="form-group">
                        <label>Contact Email</label>
                        <input type="email" name="contact_email" placeholder="candidate@example.com"
                               value="<?php echo $is_edit ? htmlspecialchars($candidate['contact_email']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Contact Phone</label>
                        <input type="tel" name="contact_phone" placeholder="+234 800 000 0000"
                               value="<?php echo $is_edit ? htmlspecialchars($candidate['contact_phone']) : ''; ?>">
                    </div>

                    <!-- Social Media -->
                    <div class="form-group full-width">
                        <label>Social Media &amp; Links</label>
                        <?php 
                        $social = $is_edit ? json_decode($candidate['social_media_json'] ?? '{}', true) : [];
                        ?>
                        <div class="social-input">
                            <span class="social-icon facebook"><i class="fab fa-facebook-f"></i></span>
                            <input type="url" name="facebook" placeholder="Facebook URL" 
                                   value="<?php echo htmlspecialchars($social['facebook'] ?? ''); ?>">
                        </div>
                        <div class="social-input" style="margin-top:6px;">
                            <span class="social-icon twitter"><i class="fab fa-twitter"></i></span>
                            <input type="url" name="twitter" placeholder="Twitter URL"
                                   value="<?php echo htmlspecialchars($social['twitter'] ?? ''); ?>">
                        </div>
                        <div class="social-input" style="margin-top:6px;">
                            <span class="social-icon instagram"><i class="fab fa-instagram"></i></span>
                            <input type="url" name="instagram" placeholder="Instagram URL"
                                   value="<?php echo htmlspecialchars($social['instagram'] ?? ''); ?>">
                        </div>
                        <div class="social-input" style="margin-top:6px;">
                            <span class="social-icon linkedin"><i class="fab fa-linkedin-in"></i></span>
                            <input type="url" name="linkedin" placeholder="LinkedIn URL"
                                   value="<?php echo htmlspecialchars($social['linkedin'] ?? ''); ?>">
                        </div>
                        <div class="social-input" style="margin-top:6px;">
                            <span class="social-icon website"><i class="fas fa-globe"></i></span>
                            <input type="url" name="website" placeholder="Website URL"
                                   value="<?php echo htmlspecialchars($social['website'] ?? ''); ?>">
                        </div>
                        <div class="help-text">Enter the full URL for each social media profile</div>
                    </div>

                    <!-- Photograph Upload -->
                    <div class="form-group full-width">
                        <label>Passport Photograph</label>
                        <div class="file-upload-area" onclick="document.getElementById('photograph').click()">
                            <i class="fas fa-camera"></i>
                            <p>Click to upload passport photograph</p>
                            <div class="file-types">Supported: JPG, PNG (Max 2MB)</div>
                            <input type="file" name="photograph" id="photograph" accept=".jpg,.jpeg,.png">
                        </div>
                        <div class="file-preview" id="photographPreview">
                            <div class="file-info">
                                <div class="file-icon image"><i class="fas fa-image"></i></div>
                                <div class="file-details">
                                    <div class="file-name" id="photographName">photo.jpg</div>
                                    <div class="file-size" id="photographSize">0 KB</div>
                                </div>
                                <button type="button" class="file-remove" onclick="removeFile('photograph')">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <?php if ($is_edit && !empty($candidate['photograph_url'])): ?>
                            <div style="margin-top:8px;padding:8px 12px;background:#F3F4F6;border-radius:6px;display:flex;align-items:center;gap:8px;">
                                <i class="fas fa-check-circle" style="color:var(--secondary);"></i>
                                <span style="font-size:0.8rem;color:var(--gray-600);">Current photo uploaded</span>
                                <a href="<?php echo htmlspecialchars($candidate['photograph_url']); ?>" target="_blank" style="margin-left:auto;font-size:0.7rem;color:var(--primary);text-decoration:none;">
                                    View <i class="fas fa-external-link-alt"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Campaign Logo Upload -->
                    <div class="form-group full-width">
                        <label>Campaign Logo</label>
                        <div class="file-upload-area" onclick="document.getElementById('campaign_logo').click()">
                            <i class="fas fa-flag"></i>
                            <p>Click to upload campaign logo</p>
                            <div class="file-types">Supported: JPG, PNG (Max 2MB)</div>
                            <input type="file" name="campaign_logo" id="campaign_logo" accept=".jpg,.jpeg,.png">
                        </div>
                        <div class="file-preview" id="logoPreview">
                            <div class="file-info">
                                <div class="file-icon image"><i class="fas fa-image"></i></div>
                                <div class="file-details">
                                    <div class="file-name" id="logoName">logo.png</div>
                                    <div class="file-size" id="logoSize">0 KB</div>
                                </div>
                                <button type="button" class="file-remove" onclick="removeFile('campaign_logo')">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <?php if ($is_edit && !empty($candidate['campaign_logo_url'])): ?>
                            <div style="margin-top:8px;padding:8px 12px;background:#F3F4F6;border-radius:6px;display:flex;align-items:center;gap:8px;">
                                <i class="fas fa-check-circle" style="color:var(--secondary);"></i>
                                <span style="font-size:0.8rem;color:var(--gray-600);">Current logo uploaded</span>
                                <a href="<?php echo htmlspecialchars($candidate['campaign_logo_url']); ?>" target="_blank" style="margin-left:auto;font-size:0.7rem;color:var(--primary);text-decoration:none;">
                                    View <i class="fas fa-external-link-alt"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Manifesto PDF Upload -->
                    <div class="form-group full-width">
                        <label>Manifesto Document (PDF)</label>
                        <div class="file-upload-area" onclick="document.getElementById('manifesto_file').click()">
                            <i class="fas fa-file-pdf"></i>
                            <p>Click to upload manifesto PDF</p>
                            <div class="file-types">Supported: PDF (Max 5MB)</div>
                            <input type="file" name="manifesto_file" id="manifesto_file" accept=".pdf">
                        </div>
                        <div class="file-preview" id="manifestoPreview">
                            <div class="file-info">
                                <div class="file-icon pdf"><i class="fas fa-file-pdf"></i></div>
                                <div class="file-details">
                                    <div class="file-name" id="manifestoName">manifesto.pdf</div>
                                    <div class="file-size" id="manifestoSize">0 KB</div>
                                </div>
                                <button type="button" class="file-remove" onclick="removeFile('manifesto_file')">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Supporting Documents -->
                    <div class="form-group full-width">
                        <label>Supporting Documents</label>
                        <div class="file-upload-area" onclick="document.getElementById('supporting_docs').click()">
                            <i class="fas fa-folder-open"></i>
                            <p>Click to upload supporting documents</p>
                            <div class="file-types">Supported: PDF, DOC, DOCX (Max 10MB)</div>
                            <input type="file" name="supporting_docs[]" id="supporting_docs" multiple accept=".pdf,.doc,.docx">
                        </div>
                        <div style="margin-top:8px;font-size:0.75rem;color:var(--gray-400);">
                            <i class="fas fa-info-circle"></i> You can upload multiple documents (CV, credentials, etc.)
                        </div>
                    </div>

                    <!-- Status (Edit only) -->
                    <?php if ($is_edit): ?>
                    <div class="form-group full-width">
                        <div style="display:flex;align-items:center;gap:12px;padding:8px 0;">
                            <input type="checkbox" name="is_active" id="is_active" value="1" 
                                   <?php echo ($is_edit && $candidate['is_active']) ? 'checked' : ''; ?>>
                            <label for="is_active" style="font-weight:400;cursor:pointer;">Active</label>
                            <span style="font-size:0.7rem;color:var(--gray-400);">Uncheck to suspend this candidate</span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <a href="candidates.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo $is_edit ? 'Update Candidate' : 'Add Candidate'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
// ============================================================
// PRELOADER
// ============================================================
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});

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

// ============================================================
// SIDEBAR DROPDOWNS
// ============================================================
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

// ============================================================
// PROFILE DROPDOWN
// ============================================================
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

// ============================================================
// FILE UPLOAD HANDLERS
// ============================================================
function setupFileUpload(inputId, previewId, nameId, sizeId) {
    var input = document.getElementById(inputId);
    var preview = document.getElementById(previewId);
    var nameEl = document.getElementById(nameId);
    var sizeEl = document.getElementById(sizeId);
    
    if (input) {
        input.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                var file = this.files[0];
                nameEl.textContent = file.name;
                sizeEl.textContent = (file.size / 1024).toFixed(1) + ' KB';
                preview.classList.add('show');
            } else {
                preview.classList.remove('show');
            }
        });
    }
}

function removeFile(inputId) {
    var input = document.getElementById(inputId);
    var preview = input.closest('.form-group').querySelector('.file-preview');
    if (input) {
        input.value = '';
        if (preview) {
            preview.classList.remove('show');
        }
    }
}

// Setup all file uploads
document.addEventListener('DOMContentLoaded', function() {
    setupFileUpload('photograph', 'photographPreview', 'photographName', 'photographSize');
    setupFileUpload('campaign_logo', 'logoPreview', 'logoName', 'logoSize');
    setupFileUpload('manifesto_file', 'manifestoPreview', 'manifestoName', 'manifestoSize');
});

// ============================================================
// SEARCH FUNCTIONALITY
// ============================================================
var searchInput = document.querySelector('.search-wrap input');
if (searchInput) {
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            this.closest('form').submit();
        }
    });
}
</script>
</body>
</html>