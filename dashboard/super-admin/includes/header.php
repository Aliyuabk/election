<header class="header">
    <div class="header-left">
        <button class="menu-toggle" id="menuToggle" aria-label="Toggle sidebar">
            <i class="fas fa-bars"></i>
        </button>
         
    </div>

    <div class="header-right">
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

        <!-- NOTIFICATIONS -->
        <div class="icon-wrapper" id="notificationWrapper">
            <button class="header-icon" id="notificationTrigger" aria-label="Notifications">
                <i class="far fa-bell"></i>
                <span class="badge">4</span>
            </button>
            <div class="header-dropdown-panel" id="notificationPanel">
                <div class="panel-header">
                    Notifications
                    <small>3 new</small>
                </div>
                <div class="panel-item">
                    <div class="icon-circle"><i class="fas fa-user-plus"></i></div>
                    <div class="content">
                        <div class="title">New tenant registered</div>
                        <div class="desc">INEC Nigeria created an account</div>
                    </div>
                    <div class="time">2m</div>
                </div>
                <div class="panel-item">
                    <div class="icon-circle"><i class="fas fa-exclamation-triangle" style="color:#f59e0b;"></i></div>
                    <div class="content">
                        <div class="title">System alert</div>
                        <div class="desc">High CPU usage detected</div>
                    </div>
                    <div class="time">1h</div>
                </div>
                <div class="panel-item">
                    <div class="icon-circle"><i class="fas fa-check-circle" style="color:#2e9c5a;"></i></div>
                    <div class="content">
                        <div class="title">Backup completed</div>
                        <div class="desc">Daily backup successful</div>
                    </div>
                    <div class="time">3h</div>
                </div>
                <div class="panel-footer">View all notifications</div>
            </div>
        </div>

        <!-- MESSAGES -->
        <div class="icon-wrapper" id="messageWrapper">
            <button class="header-icon" id="messageTrigger" aria-label="Messages">
                <i class="far fa-comment-dots"></i>
                <span class="badge">5</span>
            </button>
            <div class="header-dropdown-panel" id="messagePanel">
                <div class="panel-header">
                    Messages
                    <small>2 unread</small>
                </div>
                <div class="panel-item">
                    <div class="icon-circle"><i class="fas fa-user-circle"></i></div>
                    <div class="content">
                        <div class="title">Support Team</div>
                        <div class="desc">New ticket #SUP-2024-001</div>
                    </div>
                    <div class="time">5m</div>
                </div>
                <div class="panel-item">
                    <div class="icon-circle"><i class="fas fa-user-circle"></i></div>
                    <div class="content">
                        <div class="title">Tenant Manager</div>
                        <div class="desc">Tenant subscription expiring soon</div>
                    </div>
                    <div class="time">1h</div>
                </div>
                <div class="panel-footer">View all messages</div>
            </div>
        </div>

        <!-- PROFILE -->
        <div class="profile-wrapper" id="profileWrapper">
            <button class="profile-trigger" id="profileTrigger" aria-label="Profile menu">
                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='40' height='40' viewBox='0 0 40 40'%3E%3Ccircle cx='20' cy='20' r='18' fill='%23b3cef0'/%3E%3Ccircle cx='20' cy='14' r='6' fill='%23577a9e'/%3E%3Cpath d='M8 32c0-6 5-10 12-10s12 4 12 10' fill='%23577a9e'/%3E%3C/svg%3E" alt="avatar">
                <div class="avatar-info">
                    <div class="name">Super Admin</div>
                    <div class="role">System Administrator</div>
                </div>
                <i class="fas fa-chevron-down chevron"></i>
            </button>
            <div class="profile-dropdown" id="profileDropdown">
                <div class="dd-item"><i class="fas fa-user-circle"></i> My Profile</div>
                <div class="dd-item"><i class="fas fa-cog"></i> Settings</div>
                <div class="dd-item"><i class="fas fa-shield-alt"></i> Security</div>
                <hr class="dd-divider">
                <div class="dd-item"><i class="fas fa-life-ring"></i> Help Center</div>
                <div class="dd-item logout"><i class="fas fa-sign-out-alt"></i> Logout</div>
            </div>
        </div>
    </div>
</header>