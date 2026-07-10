<?php
// ============================================================
// WARD COORDINATOR - BROADCAST TO OBSERVERS
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

// Get Observers
$observers = [];
try {
    $stmt = $db->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email, u.phone
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE r.level = 'observer' 
        AND u.ward_id = ? 
        AND u.status = 'active' 
        AND u.deleted_at IS NULL
        ORDER BY u.first_name ASC
    ");
    $stmt->execute([$ward_id]);
    $observers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching observers: " . $e->getMessage());
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $message_content = trim($_POST['message'] ?? '');
    $selected_observers = isset($_POST['observers']) ? array_map('intval', $_POST['observers']) : [];
    
    if (empty($title)) {
        $error = 'Please enter a broadcast title.';
    } elseif (empty($message_content)) {
        $error = 'Please enter a message.';
    } elseif (empty($selected_observers)) {
        $error = 'Please select at least one observer to send to.';
    } else {
        try {
            $recipients = [];
            foreach ($observers as $observer) {
                if (in_array($observer['id'], $selected_observers)) {
                    $recipients[] = [
                        'email' => $observer['email'],
                        'full_name' => $observer['first_name'] . ' ' . $observer['last_name']
                    ];
                }
            }
            
            $send_via_json = json_encode(['email']);
            $target_ids_json = json_encode($selected_observers);
            
            $stmt = $db->prepare("
                INSERT INTO broadcasts (
                    tenant_id, sender_id, title, message, 
                    target_audience, target_ids_json, send_via,
                    status, created_at
                ) VALUES (?, ?, ?, ?, 'role_specific', ?, ?, 'sent', NOW())
            ");
            $stmt->execute([$tenant_id, $user_id, $title, $message_content, $target_ids_json, $send_via_json]);
            $broadcast_id = $db->lastInsertId();
            
            $result = sendBroadcastEmails($recipients, $title, $message_content);
            
            $status = $result['success'] ? 'sent' : 'failed';
            $stmt = $db->prepare("
                UPDATE broadcasts 
                SET status = ?, sent_at = NOW(), total_recipients = ?
                WHERE id = ?
            ");
            $stmt->execute([$status, count($recipients), $broadcast_id]);
            
            logActivity($user_id, 'broadcast_observers', 
                "Sent broadcast to Observers: $title (" . count($recipients) . " recipients)",
                'broadcasts', $broadcast_id
            );
            
            if ($result['success']) {
                $message = "Broadcast sent to Observers successfully! ({$result['sent']} recipients)";
            } else {
                $message = "Broadcast sent with some errors. Check logs for details.";
                $error = implode(', ', array_slice($result['errors'], 0, 3));
            }
        } catch (Exception $e) {
            $error = 'Failed to send broadcast: ' . $e->getMessage();
        }
    }
}

$page_title = 'Broadcast to Observers';
include '../includes/base.php';
include '../includes/sidebar.php';
?>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <div class="broadcast-container" style="max-width:700px;margin:0 auto;">
            <!-- Page Header -->
            <div class="welcome-section">
                <div>
                    <h1><i class="fas fa-eye"></i> Broadcast to Observers</h1>
                    <p class="subtitle">
                        <i class="fas fa-layer-group"></i> 
                        <?php echo htmlspecialchars($ward_name); ?> Ward - Send messages to Observers
                    </p>
                </div>
                <div class="actions">
                    <a href="broadcasts.php" class="btn-secondary-sm">
                        <i class="fas fa-arrow-left"></i> Back to Broadcasts
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

            <?php if (empty($observers)): ?>
                <div class="form-card" style="text-align:center;padding:30px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);">
                    <i class="fas fa-eye" style="font-size:2rem;display:block;margin-bottom:8px;color:var(--gray-300);"></i>
                    <h4>No Observers Found</h4>
                    <p style="font-size:0.85rem;color:var(--gray-500);">No observers have been registered in <?php echo htmlspecialchars($ward_name); ?>.</p>
                </div>
            <?php else: ?>
                <form method="POST" action="">
                    <div class="form-card" style="background:white;border-radius:var(--radius);border:1px solid var(--gray-200);padding:20px 24px;margin-bottom:16px;">
                        <div class="card-title" style="font-size:0.85rem;font-weight:600;margin:0 0 10px;padding-bottom:6px;border-bottom:1px solid var(--gray-200);color:var(--gray-700);">
                            <i class="fas fa-info-circle" style="color:var(--primary);margin-right:6px;"></i> Broadcast Details
                        </div>
                        
                        <div class="form-group" style="margin-bottom:14px;">
                            <label style="display:block;font-weight:600;font-size:0.8rem;color:var(--gray-700);margin-bottom:4px;">Title <span class="required" style="color:#EF4444;margin-left:2px;">*</span></label>
                            <input type="text" name="title" required placeholder="Enter broadcast title..." style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:0.85rem;font-family:'Inter',sans-serif;transition:var(--transition);background:white;" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" />
                        </div>

                        <div class="form-group" style="margin-bottom:14px;">
                            <label style="display:block;font-weight:600;font-size:0.8rem;color:var(--gray-700);margin-bottom:4px;">Message <span class="required" style="color:#EF4444;margin-left:2px;">*</span></label>
                            <textarea name="message" id="messageInput" required placeholder="Type your message here..." style="width:100%;padding:8px 12px;border:1px solid var(--gray-200);border-radius:8px;font-size:0.85rem;font-family:'Inter',sans-serif;transition:var(--transition);background:white;resize:vertical;min-height:100px;"><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                            <div class="char-count" style="font-size:0.65rem;color:var(--gray-400);text-align:right;margin-top:2px;"><span id="charCount">0</span> characters</div>
                        </div>
                    </div>

                    <div class="form-card" style="background:white;border-radius:var(--radius);border:1px solid var(--gray-200);padding:20px 24px;margin-bottom:16px;">
                        <div class="card-title" style="font-size:0.85rem;font-weight:600;margin:0 0 10px;padding-bottom:6px;border-bottom:1px solid var(--gray-200);color:var(--gray-700);">
                            <i class="fas fa-users" style="color:var(--primary);margin-right:6px;"></i> Select Observers
                        </div>
                        
                        <div class="recipient-list" style="max-height:350px;overflow-y:auto;border:1px solid var(--gray-200);border-radius:8px;padding:6px 10px;">
                            <div class="select-all" style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid var(--gray-200);margin-bottom:6px;font-weight:500;font-size:0.8rem;">
                                <input type="checkbox" id="selectAll" onchange="toggleAllObservers()" style="width:15px;height:15px;accent-color:var(--primary);" />
                                <label for="selectAll">Select All</label>
                                <span style="margin-left:auto;font-size:0.7rem;color:var(--gray-400);"><?php echo count($observers); ?> observers</span>
                            </div>
                            
                            <?php foreach ($observers as $observer): ?>
                                <div class="recipient-item" style="display:flex;align-items:center;gap:8px;padding:4px 6px;border-bottom:1px solid var(--gray-100);font-size:0.8rem;">
                                    <input type="checkbox" name="observers[]" value="<?php echo $observer['id']; ?>" 
                                           class="observer-checkbox" onchange="updateObserverCount()" style="width:15px;height:15px;accent-color:var(--primary);flex-shrink:0;" />
                                    <span class="name" style="font-weight:500;color:var(--gray-700);"><?php echo htmlspecialchars($observer['first_name'] . ' ' . $observer['last_name']); ?></span>
                                    <span class="email" style="font-size:0.65rem;color:var(--gray-400);margin-left:auto;"><?php echo htmlspecialchars($observer['email']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="recipient-count" style="background:#F0F9FF;border:1px solid #BAE6FD;border-radius:8px;padding:8px 12px;font-size:0.75rem;color:#0369A1;margin-top:8px;">
                            <i class="fas fa-users"></i>
                            Selected: <strong id="observerSelectedCount">0</strong> of <?php echo count($observers); ?> observers
                        </div>
                    </div>

                    <div class="form-card" style="background:white;border-radius:var(--radius);border:1px solid var(--gray-200);padding:20px 24px;margin-bottom:16px;">
                        <div class="btn-group" style="display:flex;gap:8px;flex-wrap:wrap;">
                            <a href="broadcasts.php" class="btn-cancel" style="background:var(--gray-100);color:var(--gray-700);text-decoration:none;padding:8px 20px;border-radius:8px;font-weight:600;font-size:0.82rem;transition:var(--transition);display:inline-flex;align-items:center;gap:6px;">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" class="btn-send" id="observerSendBtn" disabled style="padding:8px 20px;border:none;border-radius:8px;font-weight:600;font-size:0.82rem;cursor:pointer;transition:var(--transition);font-family:'Inter',sans-serif;background:#3B82F6;color:white;">
                                <i class="fas fa-paper-plane"></i> Send to Selected
                            </button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
// Character counter
document.getElementById('messageInput')?.addEventListener('input', function() {
    document.getElementById('charCount').textContent = this.value.length;
});

function toggleAllObservers() {
    var selectAll = document.getElementById('selectAll');
    var checkboxes = document.querySelectorAll('.observer-checkbox');
    checkboxes.forEach(function(cb) {
        cb.checked = selectAll.checked;
    });
    updateObserverCount();
}

function updateObserverCount() {
    var checkboxes = document.querySelectorAll('.observer-checkbox:checked');
    var count = checkboxes.length;
    document.getElementById('observerSelectedCount').textContent = count;
    document.getElementById('observerSendBtn').disabled = count === 0;
}

document.addEventListener('DOMContentLoaded', function() {
    updateObserverCount();
});

// Same sidebar scripts as index.php
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