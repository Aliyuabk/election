<?php
// ============================================================
// ADD/EDIT PARTY - CLIENT ADMIN (PROFESSIONAL UI)
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
// GET PARTY ID FOR EDIT
// ============================================================
$party_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $party_id > 0;

// ============================================================
// FETCH PARTY DATA FOR EDIT
// ============================================================
$party = null;
if ($is_edit) {
    try {
        $stmt = $db->prepare("SELECT * FROM political_parties WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$party_id, $tenant_id]);
        $party = $stmt->fetch();
        
        if (!$party) {
            header('Location: parties.php');
            exit();
        }
    } catch (Exception $e) {
        header('Location: parties.php');
        exit();
    }
}

// ============================================================
// FETCH STATES FOR DROPDOWN
// ============================================================
$states = [];
try {
    $stmt = $db->query("SELECT id, name, code FROM states WHERE is_active = 1 ORDER BY name");
    $states = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================
$action_result = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add_party':
                $name = trim($_POST['name'] ?? '');
                $acronym = trim($_POST['acronym'] ?? '');
                $chairman_name = trim($_POST['chairman_name'] ?? '');
                $secretary_name = trim($_POST['secretary_name'] ?? '');
                $contact_email = trim($_POST['contact_email'] ?? '');
                $contact_phone = trim($_POST['contact_phone'] ?? '');
                $website = trim($_POST['website'] ?? '');
                $state_offices_json = json_encode($_POST['state_offices'] ?? []);
                $social_media_json = json_encode([
                    'facebook' => trim($_POST['facebook'] ?? ''),
                    'twitter' => trim($_POST['twitter'] ?? ''),
                    'instagram' => trim($_POST['instagram'] ?? ''),
                    'linkedin' => trim($_POST['linkedin'] ?? ''),
                    'youtube' => trim($_POST['youtube'] ?? '')
                ]);
                
                if (empty($name) || empty($acronym)) {
                    throw new Exception('Party name and acronym are required.');
                }
                
                // Check if acronym exists
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM political_parties WHERE acronym = ? AND tenant_id = ?");
                $stmt->execute([$acronym, $tenant_id]);
                if ($stmt->fetch()['count'] > 0) {
                    throw new Exception('Party acronym already exists.');
                }
                
                // Handle logo upload
                $logo_url = '';
                if (!empty($_FILES['logo']['name'])) {
                    $logo_url = '/uploads/parties/' . uniqid() . '_' . basename($_FILES['logo']['name']);
                    // In production, move uploaded file
                }
                
                $stmt = $db->prepare("
                    INSERT INTO political_parties (
                        tenant_id, name, acronym, logo_url,
                        chairman_name, secretary_name,
                        contact_email, contact_phone, website,
                        state_offices_json, social_media_json,
                        is_active
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([
                    $tenant_id, $name, $acronym, $logo_url,
                    $chairman_name, $secretary_name,
                    $contact_email, $contact_phone, $website,
                    $state_offices_json, $social_media_json
                ]);
                
                logActivity($user_id, 'party_added', "Added party: $name");
                $action_result = ['success' => true, 'message' => "Party '$name' created successfully."];
                break;
                
            case 'edit_party':
                $id = (int)($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $acronym = trim($_POST['acronym'] ?? '');
                $chairman_name = trim($_POST['chairman_name'] ?? '');
                $secretary_name = trim($_POST['secretary_name'] ?? '');
                $contact_email = trim($_POST['contact_email'] ?? '');
                $contact_phone = trim($_POST['contact_phone'] ?? '');
                $website = trim($_POST['website'] ?? '');
                $state_offices_json = json_encode($_POST['state_offices'] ?? []);
                $social_media_json = json_encode([
                    'facebook' => trim($_POST['facebook'] ?? ''),
                    'twitter' => trim($_POST['twitter'] ?? ''),
                    'instagram' => trim($_POST['instagram'] ?? ''),
                    'linkedin' => trim($_POST['linkedin'] ?? ''),
                    'youtube' => trim($_POST['youtube'] ?? '')
                ]);
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if ($id <= 0 || empty($name) || empty($acronym)) {
                    throw new Exception('Invalid data provided.');
                }
                
                $stmt = $db->prepare("
                    UPDATE political_parties SET 
                        name = ?, acronym = ?,
                        chairman_name = ?, secretary_name = ?,
                        contact_email = ?, contact_phone = ?, website = ?,
                        state_offices_json = ?, social_media_json = ?,
                        is_active = ?
                    WHERE id = ? AND tenant_id = ?
                ");
                $stmt->execute([
                    $name, $acronym,
                    $chairman_name, $secretary_name,
                    $contact_email, $contact_phone, $website,
                    $state_offices_json, $social_media_json,
                    $is_active, $id, $tenant_id
                ]);
                
                logActivity($user_id, 'party_updated', "Updated party ID: $id");
                $action_result = ['success' => true, 'message' => 'Party updated successfully.'];
                break;
        }
    } catch (Exception $e) {
        $action_result = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<!-- The HTML remains the same as before, just remove the slogan field -->
<style>
    /* ============================================================
       ADD/EDIT PARTY - PROFESSIONAL UI STYLES
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
        min-height: 60px;
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
    .social-input .social-icon.youtube { background: #FF0000; }
    .social-input input {
        flex: 1;
    }
    
    .checkbox-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 8px;
        padding: 8px 0;
    }
    .checkbox-grid label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 400;
        font-size: 0.82rem;
        color: var(--gray-600);
        cursor: pointer;
        padding: 4px 8px;
        border-radius: 6px;
        transition: var(--transition);
    }
    .checkbox-grid label:hover {
        background: var(--gray-50);
    }
    .checkbox-grid input[type="checkbox"] {
        width: 16px;
        height: 16px;
        accent-color: var(--primary);
        cursor: pointer;
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
        .checkbox-grid {
            grid-template-columns: 1fr 1fr;
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
        .checkbox-grid {
            grid-template-columns: 1fr;
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
                    <?php echo $is_edit ? 'Edit Party' : 'Create New Party'; ?>
                    <small><?php echo $is_edit ? 'Update party information' : 'Register a new political party'; ?></small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="parties.php" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Parties
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
                    <i class="fas fa-flag"></i>
                </div>
                <div>
                    <h3><?php echo $is_edit ? 'Edit Party' : 'New Party Registration'; ?></h3>
                    <p>Fill in the party details below. Fields marked with <span style="color:var(--danger);">*</span> are required.</p>
                </div>
            </div>

            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="<?php echo $is_edit ? 'edit_party' : 'add_party'; ?>">
                <?php if ($is_edit): ?>
                    <input type="hidden" name="id" value="<?php echo $party_id; ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <!-- Party Name -->
                    <div class="form-group">
                        <label>Party Name <span class="required">*</span></label>
                        <input type="text" name="name" placeholder="e.g., All Progressives Congress" required
                               value="<?php echo $is_edit ? htmlspecialchars($party['name']) : ''; ?>">
                    </div>

                    <!-- Acronym -->
                    <div class="form-group">
                        <label>Acronym <span class="required">*</span></label>
                        <input type="text" name="acronym" placeholder="e.g., APC" maxlength="10" required
                               value="<?php echo $is_edit ? htmlspecialchars($party['acronym']) : ''; ?>">
                        <div class="help-text">Unique abbreviation for the party</div>
                    </div>

                    <!-- Slogan -->
                    <div class="form-group full-width">
                        <label>Slogan</label>
                        <input type="text" name="slogan" placeholder="e.g., Change is Coming" 
                               value="<?php echo $is_edit ? htmlspecialchars($party['slogan'] ?? '') : ''; ?>">
                        <div class="help-text">Party slogan or motto</div>
                    </div>

                    <!-- Chairman -->
                    <div class="form-group">
                        <label>National Chairman</label>
                        <input type="text" name="chairman_name" placeholder="Full name of the party chairman"
                               value="<?php echo $is_edit ? htmlspecialchars($party['chairman_name'] ?? '') : ''; ?>">
                    </div>

                    <!-- Secretary -->
                    <div class="form-group">
                        <label>National Secretary</label>
                        <input type="text" name="secretary_name" placeholder="Full name of the party secretary"
                               value="<?php echo $is_edit ? htmlspecialchars($party['secretary_name'] ?? '') : ''; ?>">
                    </div>

                    <!-- Contact Email -->
                    <div class="form-group">
                        <label>Contact Email</label>
                        <input type="email" name="contact_email" placeholder="party@example.com"
                               value="<?php echo $is_edit ? htmlspecialchars($party['contact_email'] ?? '') : ''; ?>">
                    </div>

                    <!-- Contact Phone -->
                    <div class="form-group">
                        <label>Contact Phone</label>
                        <input type="tel" name="contact_phone" placeholder="+234 800 000 0000"
                               value="<?php echo $is_edit ? htmlspecialchars($party['contact_phone'] ?? '') : ''; ?>">
                    </div>

                    <!-- Website -->
                    <div class="form-group full-width">
                        <label>Website</label>
                        <input type="url" name="website" placeholder="https://www.partywebsite.com"
                               value="<?php echo $is_edit ? htmlspecialchars($party['website'] ?? '') : ''; ?>">
                    </div>

                    <!-- Logo Upload -->
                    <div class="form-group full-width">
                        <label>Party Logo</label>
                        <div class="file-upload-area" onclick="document.getElementById('logo').click()">
                            <i class="fas fa-image"></i>
                            <p>Click to upload party logo</p>
                            <div class="file-types">Supported: JPG, PNG (Max 2MB)</div>
                            <input type="file" name="logo" id="logo" accept=".jpg,.jpeg,.png">
                        </div>
                        <div class="file-preview" id="logoPreview">
                            <div class="file-info">
                                <div class="file-icon image"><i class="fas fa-image"></i></div>
                                <div class="file-details">
                                    <div class="file-name" id="logoName">logo.png</div>
                                    <div class="file-size" id="logoSize">0 KB</div>
                                </div>
                                <button type="button" class="file-remove" onclick="removeFile('logo')">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <?php if ($is_edit && !empty($party['logo_url'])): ?>
                            <div style="margin-top:8px;padding:8px 12px;background:#F3F4F6;border-radius:6px;display:flex;align-items:center;gap:8px;">
                                <i class="fas fa-check-circle" style="color:var(--secondary);"></i>
                                <span style="font-size:0.8rem;color:var(--gray-600);">Current logo uploaded</span>
                                <a href="<?php echo htmlspecialchars($party['logo_url']); ?>" target="_blank" style="margin-left:auto;font-size:0.7rem;color:var(--primary);text-decoration:none;">
                                    View <i class="fas fa-external-link-alt"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- State Offices -->
                    <div class="form-group full-width">
                        <label>State Offices</label>
                        <div class="help-text">Select the states where the party has offices</div>
                        <?php 
                        $selected_states = $is_edit ? json_decode($party['state_offices_json'] ?? '[]', true) : [];
                        ?>
                        <div class="checkbox-grid">
                            <?php foreach ($states as $state): ?>
                                <label>
                                    <input type="checkbox" name="state_offices[]" value="<?php echo $state['id']; ?>" 
                                        <?php echo in_array($state['id'], $selected_states) ? 'checked' : ''; ?>>
                                    <?php echo htmlspecialchars($state['name']); ?>
                                    <span style="font-family:monospace;font-size:0.6rem;color:var(--gray-400);"><?php echo htmlspecialchars($state['code']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Social Media -->
                    <div class="form-group full-width">
                        <label>Social Media</label>
                        <?php 
                        $social = $is_edit ? json_decode($party['social_media_json'] ?? '{}', true) : [];
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
                            <span class="social-icon youtube"><i class="fab fa-youtube"></i></span>
                            <input type="url" name="youtube" placeholder="YouTube URL"
                                   value="<?php echo htmlspecialchars($social['youtube'] ?? ''); ?>">
                        </div>
                    </div>

                    <!-- Status (Edit only) -->
                    <?php if ($is_edit): ?>
                    <div class="form-group full-width">
                        <div style="display:flex;align-items:center;gap:12px;padding:8px 0;">
                            <input type="checkbox" name="is_active" id="is_active" value="1" 
                                   <?php echo ($is_edit && $party['is_active']) ? 'checked' : ''; ?>>
                            <label for="is_active" style="font-weight:400;cursor:pointer;">Active</label>
                            <span style="font-size:0.7rem;color:var(--gray-400);">Uncheck to suspend this party</span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <a href="parties.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo $is_edit ? 'Update Party' : 'Create Party'; ?>
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

// Setup file upload
document.addEventListener('DOMContentLoaded', function() {
    setupFileUpload('logo', 'logoPreview', 'logoName', 'logoSize');
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