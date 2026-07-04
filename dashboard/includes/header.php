<?php
// ============================================================
// HEADER - Dashboard Header with Profile Dropdown
// ============================================================

$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');
$notification_count = isset($notification_count) ? $notification_count : 0;
$page_title = isset($page_title) ? $page_title : 'Dashboard';
$page_subtitle = isset($page_subtitle) ? $page_subtitle : '';
?>
<header class="dashboard-header" id="dashboardHeader">
    <div class="header-left">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
            <i class="fas fa-bars"></i>
        </button>
        <div class="header-title">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <?php if (!empty($page_subtitle)): ?>
            <small><?php echo htmlspecialchars($page_subtitle); ?></small>
            <?php endif; ?>
        </div>
    </div>
    <div class="header-actions">
        <!-- Search -->
        <div class="search-wrapper">
            <div class="search-box" id="searchBox">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search..." autocomplete="off" />
            </div>
            <div class="search-results" id="searchResults"></div>
        </div>

        <!-- Notifications -->
        <a href="notifications.php" class="notification-btn" aria-label="Notifications">
            <i class="fas fa-bell"></i>
            <?php if ($notification_count > 0): ?>
            <span class="badge"><?php echo min($notification_count, 99); ?></span>
            <?php endif; ?>
        </a>

        <!-- Profile Dropdown -->
        <div class="profile-dropdown">
            <button class="profile-btn" id="profileBtn" aria-label="Profile Menu">
                <?php echo strtoupper(substr($user_name, 0, 2)); ?>
                <i class="fas fa-chevron-down profile-chevron"></i>
            </button>
            <div class="profile-menu" id="profileMenu">
                <div class="profile-header">
                    <div class="profile-avatar-large">
                        <?php echo strtoupper(substr($user_name, 0, 2)); ?>
                    </div>
                    <div class="profile-info">
                        <div class="name"><?php echo htmlspecialchars($user_name); ?></div>
                        <div class="email text-truncate"><?php echo htmlspecialchars($user_email); ?></div>
                    </div>
                </div>
                <div class="profile-menu-items">
                    <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                    <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                    <a href="security.php"><i class="fas fa-shield-alt"></i> Security</a>
                    <div class="divider"></div>
                    <a href="../../auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </div>
</header>