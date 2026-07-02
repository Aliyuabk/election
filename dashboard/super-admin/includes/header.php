<?php
// includes/header.php
// Get dynamic data for header
$db = Database::getInstance();
$conn = $db->getConnection();

// Get notification count
$notificationCount = 0;
$notifications = [];
try {
    // Get unread notifications
    $stmt = $conn->query("
        SELECT * FROM notifications 
        WHERE is_read = 0 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $notifications = $stmt->fetchAll();
    $notificationCount = count($notifications);
    
    // Get total unread count
    $stmt = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE is_read = 0");
    $notificationCount = $stmt->fetch()['count'] ?? 0;
    
    // Get unread messages (using notifications with message type)
    $messageCount = 0;
    $messages = [];
    $stmt = $conn->query("
        SELECT * FROM notifications 
        WHERE type = 'system' AND is_read = 0 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $messages = $stmt->fetchAll();
    $messageCount = count($messages);
    
} catch (Exception $e) {
    // Use default values if queries fail
}

// Get user info
$userName = 'Super Admin';
$userRole = 'System Administrator';
$userAvatar = '';
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $conn->prepare("SELECT first_name, last_name, email, photograph_url, role_id FROM users WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user) {
            $userName = $user['first_name'] . ' ' . $user['last_name'];
            $userAvatar = $user['photograph_url'] ?? '';
            
            // Get role name
            if ($user['role_id']) {
                $stmt = $conn->prepare("SELECT name FROM roles WHERE id = ?");
                $stmt->execute([$user['role_id']]);
                $role = $stmt->fetch();
                if ($role) {
                    $userRole = $role['name'];
                }
            }
        }
    } catch (Exception $e) {
        // Use defaults
    }
}
?>

<header class="header">
    <div class="header-left">
        <button class="menu-toggle" id="menuToggle" aria-label="Toggle sidebar">
            <i class="fas fa-bars"></i>
        </button>
        
        <!-- SEARCH -->
        <div class="search-wrapper" id="searchWrapper">
            <button class="search-icon-btn" id="searchToggle" aria-label="Search">
                <i class="fas fa-search"></i>
            </button>
            <div class="search-form-container" id="searchForm">
                <i class="fas fa-search" style="color:#889fc0;"></i>
                <input type="text" placeholder="Search anything..." aria-label="Search">
                <span class="search-shortcut">⌘K</span>
            </div>
        </div>
    </div>

    <div class="header-right">
        <!-- NOTIFICATIONS -->
        <div class="icon-wrapper" id="notificationWrapper">
            <button class="header-icon" id="notificationTrigger" aria-label="Notifications">
                <i class="far fa-bell"></i>
                <?php if ($notificationCount > 0): ?>
                <span class="badge"><?php echo $notificationCount > 99 ? '99+' : $notificationCount; ?></span>
                <?php endif; ?>
            </button>
            <div class="header-dropdown-panel" id="notificationPanel">
                <div class="panel-header">
                    Notifications
                    <small><?php echo $notificationCount; ?> unread</small>
                </div>
                <?php if (!empty($notifications)): ?>
                    <?php foreach ($notifications as $notif): ?>
                    <div class="panel-item">
                        <div class="icon-circle">
                            <?php if ($notif['type'] === 'system'): ?>
                            <i class="fas fa-cog"></i>
                            <?php elseif ($notif['type'] === 'election'): ?>
                            <i class="fas fa-vote-yea"></i>
                            <?php elseif ($notif['type'] === 'incident'): ?>
                            <i class="fas fa-exclamation-triangle" style="color:#f59e0b;"></i>
                            <?php elseif ($notif['type'] === 'payment'): ?>
                            <i class="fas fa-credit-card" style="color:#10b981;"></i>
                            <?php else: ?>
                            <i class="fas fa-bell"></i>
                            <?php endif; ?>
                        </div>
                        <div class="content">
                            <div class="title"><?php echo htmlspecialchars($notif['title']); ?></div>
                            <div class="desc"><?php echo htmlspecialchars(substr($notif['message'], 0, 60)); ?><?php echo strlen($notif['message']) > 60 ? '...' : ''; ?></div>
                        </div>
                        <div class="time">
                            <?php 
                            $time = strtotime($notif['created_at']);
                            $diff = time() - $time;
                            if ($diff < 60) echo 'Just now';
                            elseif ($diff < 3600) echo floor($diff / 60) . 'm';
                            elseif ($diff < 86400) echo floor($diff / 3600) . 'h';
                            else echo date('M d', $time);
                            ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                <div style="text-align:center; padding:20px; color:#8b9bb5;">
                    <i class="fas fa-inbox" style="font-size:1.5rem; display:block; margin-bottom:8px;"></i>
                    No new notifications
                </div>
                <?php endif; ?>
                <div class="panel-footer">View all notifications</div>
            </div>
        </div>

        <!-- MESSAGES -->
        <div class="icon-wrapper" id="messageWrapper">
            <button class="header-icon" id="messageTrigger" aria-label="Messages">
                <i class="far fa-comment-dots"></i>
                <?php if ($messageCount > 0): ?>
                <span class="badge"><?php echo $messageCount > 99 ? '99+' : $messageCount; ?></span>
                <?php endif; ?>
            </button>
            <div class="header-dropdown-panel" id="messagePanel">
                <div class="panel-header">
                    Messages
                    <small><?php echo $messageCount; ?> unread</small>
                </div>
                <?php if (!empty($messages)): ?>
                    <?php foreach ($messages as $msg): ?>
                    <div class="panel-item">
                        <div class="icon-circle"><i class="fas fa-user-circle"></i></div>
                        <div class="content">
                            <div class="title"><?php echo htmlspecialchars($msg['title']); ?></div>
                            <div class="desc"><?php echo htmlspecialchars(substr($msg['message'], 0, 50)); ?><?php echo strlen($msg['message']) > 50 ? '...' : ''; ?></div>
                        </div>
                        <div class="time">
                            <?php 
                            $time = strtotime($msg['created_at']);
                            $diff = time() - $time;
                            if ($diff < 60) echo 'Just now';
                            elseif ($diff < 3600) echo floor($diff / 60) . 'm';
                            elseif ($diff < 86400) echo floor($diff / 3600) . 'h';
                            else echo date('M d', $time);
                            ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                <div style="text-align:center; padding:20px; color:#8b9bb5;">
                    <i class="fas fa-inbox" style="font-size:1.5rem; display:block; margin-bottom:8px;"></i>
                    No messages
                </div>
                <?php endif; ?>
                <div class="panel-footer">View all messages</div>
            </div>
        </div>

        <!-- PROFILE -->
        <div class="profile-wrapper" id="profileWrapper">
            <button class="profile-trigger" id="profileTrigger" aria-label="Profile menu">
                <?php if (!empty($userAvatar)): ?>
                <img src="<?php echo htmlspecialchars($userAvatar); ?>" alt="avatar">
                <?php else: ?>
                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='40' height='40' viewBox='0 0 40 40'%3E%3Ccircle cx='20' cy='20' r='18' fill='%23b3cef0'/%3E%3Ccircle cx='20' cy='14' r='6' fill='%23577a9e'/%3E%3Cpath d='M8 32c0-6 5-10 12-10s12 4 12 10' fill='%23577a9e'/%3E%3C/svg%3E" alt="avatar">
                <?php endif; ?>
                <div class="avatar-info">
                    <div class="name"><?php echo htmlspecialchars($userName); ?></div>
                    <div class="role"><?php echo htmlspecialchars($userRole); ?></div>
                </div>
                <i class="fas fa-chevron-down chevron"></i>
            </button>
            <div class="profile-dropdown" id="profileDropdown">
                <a href="profile.php" class="dd-item"><i class="fas fa-user-circle"></i> My Profile</a>
                <a href="system-settings.php" class="dd-item"><i class="fas fa-cog"></i> Settings</a>
                <a href="security.php" class="dd-item"><i class="fas fa-shield-alt"></i> Security</a>
                <hr class="dd-divider">
                <a href="help.php" class="dd-item"><i class="fas fa-life-ring"></i> Help Center</a>
                <a href="#" class="dd-item logout" onclick="confirmLogout()"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>
</header>