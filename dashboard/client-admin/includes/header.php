<header class="dashboard-header" id="dashboardHeader">
        <div class="header-left">
            <h1>
                Dashboard
            </h1>
        </div>
        <div class="header-actions">
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="search-wrapper">
                <div class="search-box" id="searchBox">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search..." autocomplete="off" />
                </div>
                <div class="search-results" id="searchResults"></div>
            </div>
            <a href="notifications.php" class="notification-btn">
                <i class="fas fa-bell"></i> 
                
            </a>
            <div class="profile-dropdown">
                <button class="profile-btn" id="profileBtn">
                    <?php echo strtoupper(substr($user_name, 0, 2)); ?>
                </button>
                <div class="profile-menu" id="profileMenu">
                    <div class="profile-header">
                        <div class="name"><?php echo htmlspecialchars($user_name); ?></div>
                        <div class="email text-truncate"><?php echo htmlspecialchars($user_email); ?></div>
                    </div>
                    <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                    <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                    <a href="security.php"><i class="fas fa-shield-alt"></i> Security</a>
                    <div class="divider"></div>
                    <a href="../../auth/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>
    