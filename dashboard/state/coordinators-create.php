<?php
// ============================================================
// STATE COORDINATOR - CREATE COORDINATOR
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

// Only state coordinator can access
if (SessionManager::get('role_level') !== 'state') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$user_email = SessionManager::get('user_email');
$state_id = SessionManager::get('state_id');
$tenant_id = SessionManager::get('tenant_id');

// Get parameters - support both state and lga
$state_id_param = isset($_GET['state']) ? intval($_GET['state']) : $state_id;
$lga_id = isset($_GET['lga']) ? intval($_GET['lga']) : 0;
$ward_id = isset($_GET['ward']) ? intval($_GET['ward']) : 0;
$pu_id = isset($_GET['pu']) ? intval($_GET['pu']) : 0;
$level = isset($_GET['level']) ? $_GET['level'] : 'lga';

// Also check POST for parameters
if ($state_id_param <= 0 && isset($_POST['state_id'])) {
    $state_id_param = intval($_POST['state_id']);
}
if ($lga_id <= 0 && isset($_POST['lga_id'])) {
    $lga_id = intval($_POST['lga_id']);
}
if ($ward_id <= 0 && isset($_POST['ward_id'])) {
    $ward_id = intval($_POST['ward_id']);
}
if ($pu_id <= 0 && isset($_POST['pu_id'])) {
    $pu_id = intval($_POST['pu_id']);
}
if (isset($_POST['level'])) {
    $level = $_POST['level'];
}

$db = getDB();

// ============================================================
// FETCH LOCATION DATA
// ============================================================
$state_name = '';
$lga_name = '';
$ward_name = '';
$pu_name = '';
$location_label = '';

// Get state name
try {
    $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
    $stmt->execute([$state_id_param]);
    $state_name = $stmt->fetchColumn() ?: 'State';
} catch (Exception $e) {
    $state_name = 'State';
}

// Determine which level we're working with
if ($level === 'state' && $state_id_param > 0) {
    $location_label = $state_name;
} elseif ($level === 'lga' && $lga_id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT l.name as lga_name, s.name as state_name 
            FROM lgas l 
            JOIN states s ON l.state_id = s.id 
            WHERE l.id = ?
        ");
        $stmt->execute([$lga_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $lga_name = $result['lga_name'];
            $state_name = $result['state_name'];
            $location_label = "$lga_name ($state_name)";
        }
    } catch (Exception $e) {
        error_log("LGA fetch error: " . $e->getMessage());
    }
} elseif ($level === 'ward' && $ward_id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT w.name as ward_name, l.name as lga_name, s.name as state_name 
            FROM wards w
            JOIN lgas l ON w.lga_id = l.id
            JOIN states s ON l.state_id = s.id
            WHERE w.id = ?
        ");
        $stmt->execute([$ward_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $ward_name = $result['ward_name'];
            $lga_name = $result['lga_name'];
            $state_name = $result['state_name'];
            $location_label = "$ward_name ($lga_name, $state_name)";
        }
    } catch (Exception $e) {
        error_log("Ward fetch error: " . $e->getMessage());
    }
} elseif ($level === 'pu_agent' && $pu_id > 0) {
    try {
        $stmt = $db->prepare("
            SELECT pu.name as pu_name, w.name as ward_name, l.name as lga_name, s.name as state_name 
            FROM polling_units pu
            JOIN wards w ON pu.ward_id = w.id
            JOIN lgas l ON w.lga_id = l.id
            JOIN states s ON l.state_id = s.id
            WHERE pu.id = ?
        ");
        $stmt->execute([$pu_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $pu_name = $result['pu_name'];
            $ward_name = $result['ward_name'];
            $lga_name = $result['lga_name'];
            $state_name = $result['state_name'];
            $location_label = "$pu_name ($ward_name, $lga_name, $state_name)";
        }
    } catch (Exception $e) {
        error_log("PU fetch error: " . $e->getMessage());
    }
}

// ============================================================
// FETCH ROLES
// ============================================================
$roles = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, level 
        FROM roles 
        WHERE level IN ('lga', 'ward', 'pu_agent') 
        AND is_active = 1 
        ORDER BY FIELD(level, 'lga', 'ward', 'pu_agent'), name
    ");
    $stmt->execute();
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $roles = [];
}

// ============================================================
// FETCH LGAS FOR SELECTION
// ============================================================
$lgas = [];
if ($state_id_param > 0) {
    try {
        $stmt = $db->prepare("SELECT id, name FROM lgas WHERE state_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$state_id_param]);
        $lgas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $lgas = [];
    }
}

// ============================================================
// FETCH WARDS FOR SELECTION
// ============================================================
$wards = [];
if ($lga_id > 0) {
    try {
        $stmt = $db->prepare("SELECT id, name FROM wards WHERE lga_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$lga_id]);
        $wards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $wards = [];
    }
}

// ============================================================
// FETCH POLLING UNITS FOR SELECTION
// ============================================================
$polling_units = [];
if ($ward_id > 0) {
    try {
        $stmt = $db->prepare("SELECT id, name, code FROM polling_units WHERE ward_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$ward_id]);
        $polling_units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $polling_units = [];
    }
}

// ============================================================
// PROCESS FORM SUBMISSION
// ============================================================
$message = '';
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role_id = intval($_POST['role_id'] ?? 0);
    $jurisdiction_id = intval($_POST['jurisdiction_id'] ?? 0);
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $send_welcome_email = isset($_POST['send_welcome_email']) ? true : false;
    
    // Get state_id from POST if not set
    if ($state_id_param <= 0 && isset($_POST['state_id'])) {
        $state_id_param = intval($_POST['state_id']);
    }
    
    // Validation
    if (empty($first_name)) {
        $error = 'First name is required';
    } elseif (empty($last_name)) {
        $error = 'Last name is required';
    } elseif (empty($email)) {
        $error = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } elseif (empty($phone)) {
        $error = 'Phone number is required';
    } elseif ($role_id <= 0) {
        $error = 'Please select a role';
    } elseif ($jurisdiction_id <= 0) {
        $error = 'Please select a jurisdiction';
    } elseif (empty($password)) {
        $error = 'Password is required';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        try {
            // Check if email already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email already registered';
            } else {
                // Get role level
                $stmt = $db->prepare("SELECT level FROM roles WHERE id = ?");
                $stmt->execute([$role_id]);
                $role_level = $stmt->fetchColumn();
                
                // Generate user code
                $user_code = 'USR' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
                
                // Hash password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // Determine jurisdiction type based on role
                $jurisdiction_type = $role_level;
                if ($role_level === 'pu_agent') {
                    $jurisdiction_type = 'pu';
                }
                
                // Insert user
                $stmt = $db->prepare("
                    INSERT INTO users (
                        tenant_id, user_code, role_id, first_name, last_name,
                        email, phone, password_hash, jurisdiction_type, jurisdiction_id,
                        status, created_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())
                ");
                
                $stmt->execute([
                    $tenant_id,
                    $user_code,
                    $role_id,
                    $first_name,
                    $last_name,
                    $email,
                    $phone,
                    $password_hash,
                    $jurisdiction_type,
                    $jurisdiction_id,
                    $user_id
                ]);
                
                $new_user_id = $db->lastInsertId();
                error_log("User created with ID: " . $new_user_id);
                
                // Send welcome email if requested
                if ($send_welcome_email && !empty($email)) {
                    $login_url = APP_URL . '/auth/login.php';
                    $email_subject = 'Welcome to ' . APP_NAME . ' - Your Coordinator Account';
                    $email_body = '
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; background: #f4f6fa; padding: 20px; }
                            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; padding: 40px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
                            .header { text-align: center; margin-bottom: 30px; }
                            .header h1 { color: #0F4C81; margin: 0; }
                            .credentials { background: #F8FAFC; padding: 20px; border-radius: 12px; margin: 20px 0; border-left: 4px solid #0F4C81; }
                            .btn { display: inline-block; padding: 12px 32px; background: #0F4C81; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; }
                            .footer { text-align: center; color: #64748B; font-size: 12px; margin-top: 30px; border-top: 1px solid #E2E8F0; padding-top: 20px; }
                        </style>
                    </head>
                    <body>
                        <div class="container">
                            <div class="header">
                                <h1>🎯 ' . APP_NAME . '</h1>
                                <p style="color: #64748B;">Welcome to the Team!</p>
                            </div>
                            <p>Dear ' . htmlspecialchars($first_name) . ' ' . htmlspecialchars($last_name) . ',</p>
                            <p>Your coordinator account has been created successfully. You can now access the platform using the credentials below:</p>
                            <div class="credentials">
                                <p><strong>User Code:</strong> ' . $user_code . '</p>
                                <p><strong>Email:</strong> ' . htmlspecialchars($email) . '</p>
                                <p><strong>Password:</strong> [The password you set]</p>
                                <p><strong>Role:</strong> ' . ucfirst($role_level) . ' Coordinator</p>
                                <p><strong>Jurisdiction:</strong> ' . htmlspecialchars($location_label) . '</p>
                            </div>
                            <div style="text-align: center; margin: 30px 0;">
                                <a href="' . $login_url . '" class="btn">Login Now</a>
                            </div>
                            <p style="color: #64748B; font-size: 14px;">
                                Please keep your credentials secure and do not share them with anyone.
                            </p>
                            <div class="footer">
                                &copy; ' . date('Y') . ' ' . APP_NAME . '. All rights reserved.
                            </div>
                        </div>
                    </body>
                    </html>
                    ';
                    
                    sendEmail($email, $email_subject, $email_body);
                }
                
                // Log activity
                $log_stmt = $db->prepare("
                    INSERT INTO activity_logs (user_id, tenant_id, activity_type, description, entity_type, entity_id, created_at)
                    VALUES (?, ?, 'user_created', ?, 'user', ?, NOW())
                ");
                $log_stmt->execute([
                    $user_id,
                    $tenant_id,
                    "Created coordinator: $first_name $last_name as " . ucfirst($role_level) . " for $location_label",
                    $new_user_id
                ]);
                
                $success = true;
                $message = "Coordinator created successfully! User Code: $user_code";
                
                // Redirect after success
                if ($level === 'state' && $state_id_param > 0) {
                    header("Location: state-coordinators.php?id=$state_id_param&success=1");
                } elseif ($lga_id > 0) {
                    header("Location: lga-coordinators.php?id=$lga_id&success=1");
                } elseif ($ward_id > 0) {
                    header("Location: ward-dashboard.php?id=$ward_id&success=1");
                } elseif ($pu_id > 0) {
                    header("Location: pu-agents.php?pu=$pu_id&success=1");
                } else {
                    header("Location: monitor-lgas.php?success=1");
                }
                exit();
            }
        } catch (Exception $e) {
            $error = 'Failed to create coordinator: ' . $e->getMessage();
            error_log("Create Coordinator Error: " . $e->getMessage());
        }
    }
}

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'Create Coordinator';
$page_subtitle = ucfirst($level) . ' Coordinator';
?>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Breadcrumb -->
        <div class="welcome-section">
            <div class="breadcrumb">
                <i class="fas fa-home"></i>
                <a href="index.php" style="text-decoration:none;color:var(--gray-500);">Dashboard</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <?php if ($level === 'state' && $state_id_param > 0): ?>
                    <a href="state-coordinators.php?id=<?php echo $state_id_param; ?>" style="text-decoration:none;color:var(--gray-500);"><?php echo htmlspecialchars($state_name); ?></a>
                <?php elseif ($lga_id > 0): ?>
                    <a href="lga-coordinators.php?id=<?php echo $lga_id; ?>" style="text-decoration:none;color:var(--gray-500);"><?php echo htmlspecialchars($lga_name); ?></a>
                <?php elseif ($ward_id > 0): ?>
                    <a href="ward-dashboard.php?id=<?php echo $ward_id; ?>" style="text-decoration:none;color:var(--gray-500);"><?php echo htmlspecialchars($ward_name); ?></a>
                <?php elseif ($pu_id > 0): ?>
                    <a href="pu-dashboard.php?id=<?php echo $pu_id; ?>" style="text-decoration:none;color:var(--gray-500);"><?php echo htmlspecialchars($pu_name); ?></a>
                <?php else: ?>
                    <a href="monitor-lgas.php" style="text-decoration:none;color:var(--gray-500);">Monitor LGAs</a>
                <?php endif; ?>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="font-weight:600;color:var(--gray-800);">Create Coordinator</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        Create <?php echo ucfirst($level); ?> Coordinator
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <?php echo htmlspecialchars($location_label); ?> • 
                        <?php echo ucfirst($level); ?> Level
                    </p>
                </div>
                <?php if ($level === 'state' && $state_id_param > 0): ?>
                    <a href="state-coordinators.php?id=<?php echo $state_id_param; ?>" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                <?php elseif ($lga_id > 0): ?>
                    <a href="lga-coordinators.php?id=<?php echo $lga_id; ?>" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                <?php elseif ($ward_id > 0): ?>
                    <a href="ward-dashboard.php?id=<?php echo $ward_id; ?>" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                <?php elseif ($pu_id > 0): ?>
                    <a href="pu-dashboard.php?id=<?php echo $pu_id; ?>" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($message && $success): ?>
            <div style="background:#D1FAE5;color:#065F46;padding:12px 20px;border-radius:10px;margin-bottom:20px;border:1px solid #A7F3D0;display:flex;align-items:center;gap:10px;">
                <i class="fas fa-check-circle" style="font-size:1.2rem;"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="background:#FEE2E2;color:#991B1B;padding:12px 20px;border-radius:10px;margin-bottom:20px;border:1px solid #FECACA;display:flex;align-items:center;gap:10px;">
                <i class="fas fa-exclamation-circle" style="font-size:1.2rem;"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Create Coordinator Form -->
        <form method="POST" action="" style="background:white;border-radius:var(--radius);padding:24px;border:1px solid var(--gray-200);">
            <input type="hidden" name="state_id" value="<?php echo $state_id_param; ?>">
            <input type="hidden" name="lga_id" value="<?php echo $lga_id; ?>">
            <input type="hidden" name="ward_id" value="<?php echo $ward_id; ?>">
            <input type="hidden" name="pu_id" value="<?php echo $pu_id; ?>">
            <input type="hidden" name="level" value="<?php echo $level; ?>">
            
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                <!-- Left Column -->
                <div>
                    <!-- First Name -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            First Name <span style="color:#EF4444;">*</span>
                        </label>
                        <input type="text" name="first_name" class="form-control" required
                               value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                               placeholder="Enter first name"
                               style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                    
                    <!-- Last Name -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Last Name <span style="color:#EF4444;">*</span>
                        </label>
                        <input type="text" name="last_name" class="form-control" required
                               value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                               placeholder="Enter last name"
                               style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                    
                    <!-- Email -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Email Address <span style="color:#EF4444;">*</span>
                        </label>
                        <input type="email" name="email" class="form-control" required
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                               placeholder="Enter email address"
                               style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                    
                    <!-- Phone -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Phone Number <span style="color:#EF4444;">*</span>
                        </label>
                        <input type="tel" name="phone" class="form-control" required
                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                               placeholder="Enter phone number (e.g., +234XXXXXXXXXX)"
                               style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                </div>
                
                <!-- Right Column -->
                <div>
                    <!-- Role -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Role <span style="color:#EF4444;">*</span>
                        </label>
                        <select name="role_id" class="form-control" required id="roleSelect"
                                style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;background:white;transition:var(--transition);">
                            <option value="">Select Role...</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role['id']; ?>"
                                    <?php echo ($_POST['role_id'] ?? '') == $role['id'] ? 'selected' : ''; ?>
                                    <?php echo $role['level'] === $level ? 'selected' : ''; ?>
                                    data-level="<?php echo $role['level']; ?>">
                                    <?php echo htmlspecialchars($role['name']); ?> (<?php echo ucfirst($role['level']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Jurisdiction -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Jurisdiction <span style="color:#EF4444;">*</span>
                        </label>
                        <div id="jurisdictionContainer">
                            <?php if ($level === 'lga'): ?>
                                <select name="jurisdiction_id" class="form-control" required
                                        style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;background:white;transition:var(--transition);">
                                    <option value="">Select LGA...</option>
                                    <?php foreach ($lgas as $lga): ?>
                                        <option value="<?php echo $lga['id']; ?>"
                                            <?php echo ($_POST['jurisdiction_id'] ?? '') == $lga['id'] ? 'selected' : ''; ?>
                                            <?php echo $lga['id'] == $lga_id ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($lga['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($level === 'ward'): ?>
                                <select name="jurisdiction_id" class="form-control" required
                                        style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;background:white;transition:var(--transition);">
                                    <option value="">Select Ward...</option>
                                    <?php foreach ($wards as $ward): ?>
                                        <option value="<?php echo $ward['id']; ?>"
                                            <?php echo ($_POST['jurisdiction_id'] ?? '') == $ward['id'] ? 'selected' : ''; ?>
                                            <?php echo $ward['id'] == $ward_id ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($ward['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($level === 'pu_agent'): ?>
                                <select name="jurisdiction_id" class="form-control" required
                                        style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;background:white;transition:var(--transition);">
                                    <option value="">Select Polling Unit...</option>
                                    <?php foreach ($polling_units as $pu): ?>
                                        <option value="<?php echo $pu['id']; ?>"
                                            <?php echo ($_POST['jurisdiction_id'] ?? '') == $pu['id'] ? 'selected' : ''; ?>
                                            <?php echo $pu['id'] == $pu_id ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($pu['name']); ?> (<?php echo htmlspecialchars($pu['code']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <select name="jurisdiction_id" class="form-control" required
                                        style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;background:white;transition:var(--transition);">
                                    <option value="">Select Jurisdiction...</option>
                                </select>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Password -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Password <span style="color:#EF4444;">*</span>
                        </label>
                        <input type="password" name="password" class="form-control" required
                               placeholder="Min 8 characters"
                               style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                        <div style="font-size:0.65rem;color:var(--gray-400);margin-top:4px;">
                            <i class="fas fa-info-circle"></i> Must be at least 8 characters
                        </div>
                    </div>
                    
                    <!-- Confirm Password -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-weight:600;font-size:0.85rem;color:var(--gray-700);margin-bottom:4px;">
                            Confirm Password <span style="color:#EF4444;">*</span>
                        </label>
                        <input type="password" name="confirm_password" class="form-control" required
                               placeholder="Confirm password"
                               style="width:100%;padding:10px 14px;border:1px solid var(--gray-200);border-radius:10px;font-family:'Inter',sans-serif;font-size:0.85rem;transition:var(--transition);">
                    </div>
                </div>
            </div>
            
            <!-- Options -->
            <div style="padding-top:16px;border-top:1px solid var(--gray-200);margin-top:8px;">
                <label style="display:flex;align-items:center;gap:8px;font-size:0.85rem;color:var(--gray-600);cursor:pointer;">
                    <input type="checkbox" name="send_welcome_email" value="1" checked>
                    <i class="fas fa-envelope" style="color:#3B82F6;"></i>
                    Send welcome email with credentials
                </label>
            </div>
            
            <!-- Form Actions -->
            <div style="display:flex;gap:12px;margin-top:20px;padding-top:16px;border-top:1px solid var(--gray-200);flex-wrap:wrap;">
                <button type="submit" class="btn-primary" style="padding:10px 32px;background:var(--primary);color:white;border:none;border-radius:10px;font-family:'Inter',sans-serif;font-weight:600;font-size:0.85rem;cursor:pointer;transition:var(--transition);display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-user-plus"></i> Create Coordinator
                </button>
                <?php if ($level === 'state' && $state_id_param > 0): ?>
                    <a href="state-coordinators.php?id=<?php echo $state_id_param; ?>" class="btn-secondary" style="padding:10px 32px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-family:'Inter',sans-serif;font-weight:500;font-size:0.85rem;cursor:pointer;transition:var(--transition);display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                <?php elseif ($lga_id > 0): ?>
                    <a href="lga-coordinators.php?id=<?php echo $lga_id; ?>" class="btn-secondary" style="padding:10px 32px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-family:'Inter',sans-serif;font-weight:500;font-size:0.85rem;cursor:pointer;transition:var(--transition);display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                <?php elseif ($ward_id > 0): ?>
                    <a href="ward-dashboard.php?id=<?php echo $ward_id; ?>" class="btn-secondary" style="padding:10px 32px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-family:'Inter',sans-serif;font-weight:500;font-size:0.85rem;cursor:pointer;transition:var(--transition);display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                <?php elseif ($pu_id > 0): ?>
                    <a href="pu-dashboard.php?id=<?php echo $pu_id; ?>" class="btn-secondary" style="padding:10px 32px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-family:'Inter',sans-serif;font-weight:500;font-size:0.85rem;cursor:pointer;transition:var(--transition);display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Quick Tips -->
        <div style="background:#F0FDF4;border-radius:var(--radius);padding:16px 20px;margin-top:20px;border:1px solid #A7F3D0;">
            <h4 style="font-size:0.85rem;font-weight:600;color:#065F46;margin:0 0 8px;">
                <i class="fas fa-lightbulb"></i> Coordinator Creation Tips
            </h4>
            <ul style="font-size:0.8rem;color:#065F46;margin:0;padding-left:20px;">
                <li>Use official email addresses for coordinators</li>
                <li>Assign the correct role based on jurisdiction level</li>
                <li>Enable "Send welcome email" to notify the coordinator</li>
                <li>Ensure the jurisdiction matches the selected role</li>
                <li>Passwords must be at least 8 characters with a mix of letters and numbers</li>
            </ul>
        </div>
    </div>
</main>

<style>
.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
}

.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3);
}

.btn-secondary:hover {
    background: var(--gray-200);
    transform: translateY(-2px);
}

@media (max-width: 768px) {
    div[style*="grid-template-columns:1fr 1fr"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<script>
// ============================================================
// ROLE CHANGE HANDLER
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    var roleSelect = document.getElementById('roleSelect');
    
    if (roleSelect) {
        roleSelect.addEventListener('change', function() {
            var selectedOption = this.options[this.selectedIndex];
            var level = selectedOption.getAttribute('data-level');
            
            if (level) {
                var currentUrl = new URL(window.location.href);
                var params = new URLSearchParams(currentUrl.search);
                params.set('level', level);
                currentUrl.search = params.toString();
                window.location.href = currentUrl.toString();
            }
        });
    }
});

// ============================================================
// SIDEBAR TOGGLE, DROPDOWNS, PROFILE, SEARCH
// ============================================================
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