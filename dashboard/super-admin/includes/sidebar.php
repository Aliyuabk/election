<aside class="sidebar" id="sidebar">
    <!-- ============================================================
    SIDEBAR BRAND
    ============================================================ -->
    <div class="sidebar-brand">
        <div class="brand-logo">
            <i class="fas fa-cube"></i>
        </div>
        <div class="brand-text">
            <span>5G Election Guru</span>
            <small>Super Admin</small>
        </div>
        <button class="sidebar-collapse-btn" id="sidebarCollapse" title="Toggle Sidebar">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>

    <!-- ============================================================
    SIDEBAR NAVIGATION
    ============================================================ -->
    <nav class="nav-section">
        <!-- Dashboard -->
        <a href="index.php" class="nav-item <?php echo $page_title === 'Dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-th-large"></i>
            <span class="nav-text">Dashboard</span>
            <span class="nav-badge hot">Live</span>
        </a>

        <!-- ============================================================
        MANAGEMENT GROUP
        ============================================================ -->
        <div class="nav-group-label">
            <span>Management</span>
        </div>

        <!-- Tenants Dropdown -->
        <div class="nav-dropdown <?php echo strpos($page_title, 'Tenant') !== false ? 'open' : ''; ?>">
            <div class="nav-dropdown-header" data-target="tenantMenu">
                <i class="fas fa-building"></i>
                <span class="nav-text">Tenants</span>
                <span class="nav-badge"><?php echo $tenantCount ?? 0; ?></span>
                <i class="fas fa-chevron-down dropdown-arrow"></i>
            </div>
            <div class="nav-dropdown-menu" id="tenantMenu">
                <a href="tenants.php" class="nav-item <?php echo $page_title === 'Manage Tenants' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i>
                    <span class="nav-text">All Tenants</span>
                </a>
                <a href="tenant-edit.php" class="nav-item">
                    <i class="fas fa-plus-circle"></i>
                    <span class="nav-text">Create Tenant</span>
                </a>
                <a href="tenant-subscriptions.php" class="nav-item">
                    <i class="fas fa-crown"></i>
                    <span class="nav-text">Subscriptions</span>
                </a>
            </div>
        </div>

        <!-- Users -->
        <a href="users.php" class="nav-item <?php echo $page_title === 'Manage Users' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span class="nav-text">All Users</span>
            <span class="nav-badge"><?php echo $userCount ?? 0; ?></span>
        </a>

        <!-- Roles Dropdown -->
        <div class="nav-dropdown <?php echo strpos($page_title, 'Role') !== false ? 'open' : ''; ?>">
            <div class="nav-dropdown-header" data-target="rolesMenu">
                <i class="fas fa-user-shield"></i>
                <span class="nav-text">System Roles</span>
                <i class="fas fa-chevron-down dropdown-arrow"></i>
            </div>
            <div class="nav-dropdown-menu" id="rolesMenu">
                <a href="roles.php" class="nav-item <?php echo $page_title === 'Manage Roles' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i>
                    <span class="nav-text">All Roles</span>
                </a>
                <a href="role-edit.php" class="nav-item">
                    <i class="fas fa-plus-circle"></i>
                    <span class="nav-text">Create Role</span>
                </a>
                <a href="permissions.php" class="nav-item">
                    <i class="fas fa-key"></i>
                    <span class="nav-text">Permissions</span>
                </a>
            </div>
        </div>

        <!-- ============================================================
        ELECTIONS GROUP
        ============================================================ -->
        <div class="nav-group-label">
            <span>Elections</span>
        </div>

        <!-- Elections Dropdown -->
        <div class="nav-dropdown <?php echo strpos($page_title, 'Election') !== false ? 'open' : ''; ?>">
            <div class="nav-dropdown-header" data-target="electionMenu">
                <i class="fas fa-vote-yea"></i>
                <span class="nav-text">Elections</span>
                <i class="fas fa-chevron-down dropdown-arrow"></i>
            </div>
            <div class="nav-dropdown-menu" id="electionMenu">
                <a href="elections.php" class="nav-item <?php echo $page_title === 'Elections' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i>
                    <span class="nav-text">All Elections</span>
                </a>
                <a href="election-edit.php" class="nav-item">
                    <i class="fas fa-plus-circle"></i>
                    <span class="nav-text">Create Election</span>
                </a>
                <a href="election-results.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i>
                    <span class="nav-text">Results</span>
                </a>
                <a href="election-templates.php" class="nav-item">
                    <i class="fas fa-file-template"></i>
                    <span class="nav-text">Templates</span>
                </a>
            </div>
        </div>

        <!-- INEC Data -->
        <a href="inec-upload.php" class="nav-item <?php echo $page_title === 'INEC Data Upload' ? 'active' : ''; ?>">
            <i class="fas fa-upload"></i>
            <span class="nav-text">INEC Master Data</span>
            <span class="nav-badge">Import</span>
        </a>

        <!-- ============================================================
        SYSTEM GROUP
        ============================================================ -->
        <div class="nav-group-label">
            <span>System</span>
        </div>

        <!-- Audit Logs -->
        <a href="audit-logs.php" class="nav-item <?php echo $page_title === 'Audit Logs' ? 'active' : ''; ?>">
            <i class="fas fa-history"></i>
            <span class="nav-text">Audit Logs</span>
            <span class="nav-badge audit">Live</span>
        </a>

        <!-- System Settings Dropdown -->
        <div class="nav-dropdown <?php echo strpos($page_title, 'Setting') !== false ? 'open' : ''; ?>">
            <div class="nav-dropdown-header" data-target="settingsMenu">
                <i class="fas fa-cog"></i>
                <span class="nav-text">System Settings</span>
                <i class="fas fa-chevron-down dropdown-arrow"></i>
            </div>
            <div class="nav-dropdown-menu" id="settingsMenu">
                <a href="system-settings.php" class="nav-item <?php echo $page_title === 'System Settings' ? 'active' : ''; ?>">
                    <i class="fas fa-sliders-h"></i>
                    <span class="nav-text">General Settings</span>
                </a>
                <a href="email-settings.php" class="nav-item">
                    <i class="fas fa-envelope"></i>
                    <span class="nav-text">Email Settings</span>
                </a>
                <a href="sms-settings.php" class="nav-item">
                    <i class="fas fa-sms"></i>
                    <span class="nav-text">SMS Settings</span>
                </a>
            </div>
        </div>

        <!-- Backup & Restore -->
        <a href="backups.php" class="nav-item <?php echo $page_title === 'Backups' ? 'active' : ''; ?>">
            <i class="fas fa-database"></i>
            <span class="nav-text">Backup & Restore</span>
        </a>

        <!-- Security Monitoring -->
        <a href="security.php" class="nav-item <?php echo $page_title === 'Security Monitoring' ? 'active' : ''; ?>">
            <i class="fas fa-shield-alt"></i>
            <span class="nav-text">Security Monitoring</span>
            <span class="nav-badge security"><?php echo $securityAlerts ?? 0; ?></span>
        </a>

        <!-- ============================================================
        OPERATIONS GROUP
        ============================================================ -->
        <div class="nav-group-label">
            <span>Operations</span>
        </div>

        <!-- API Management -->
        <a href="api-management.php" class="nav-item <?php echo $page_title === 'API Management' ? 'active' : ''; ?>">
            <i class="fas fa-code"></i>
            <span class="nav-text">API Management</span>
        </a>

        <!-- Notifications Dropdown -->
        <div class="nav-dropdown <?php echo strpos($page_title, 'Notification') !== false ? 'open' : ''; ?>">
            <div class="nav-dropdown-header" data-target="notificationMenu">
                <i class="fas fa-bell"></i>
                <span class="nav-text">Notifications</span>
                <span class="nav-badge notification">3</span>
                <i class="fas fa-chevron-down dropdown-arrow"></i>
            </div>
            <div class="nav-dropdown-menu" id="notificationMenu">
                <a href="notifications.php" class="nav-item <?php echo $page_title === 'Notifications' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i>
                    <span class="nav-text">All Notifications</span>
                </a>
                <a href="broadcast.php" class="nav-item">
                    <i class="fas fa-bullhorn"></i>
                    <span class="nav-text">Send Broadcast</span>
                </a>
                <a href="notification-templates.php" class="nav-item">
                    <i class="fas fa-file-alt"></i>
                    <span class="nav-text">Templates</span>
                </a>
            </div>
        </div>

        <!-- Support Tickets Dropdown -->
        <div class="nav-dropdown <?php echo strpos($page_title, 'Support') !== false ? 'open' : ''; ?>">
            <div class="nav-dropdown-header" data-target="supportMenu">
                <i class="fas fa-life-ring"></i>
                <span class="nav-text">Support Tickets</span>
                <span class="nav-badge support"><?php echo $pendingTickets ?? 0; ?></span>
                <i class="fas fa-chevron-down dropdown-arrow"></i>
            </div>
            <div class="nav-dropdown-menu" id="supportMenu">
                <a href="support-tickets.php" class="nav-item <?php echo $page_title === 'Support Tickets' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i>
                    <span class="nav-text">All Tickets</span>
                </a>
                <a href="support-tickets.php?status=open" class="nav-item">
                    <i class="fas fa-clock"></i>
                    <span class="nav-text">Open Tickets</span>
                    <span class="nav-badge"><?php echo $pendingTickets ?? 0; ?></span>
                </a>
                <a href="support-tickets.php?status=resolved" class="nav-item">
                    <i class="fas fa-check-circle"></i>
                    <span class="nav-text">Resolved</span>
                </a>
                <a href="support-tickets.php?status=escalated" class="nav-item">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span class="nav-text">Escalated</span>
                </a>
            </div>
        </div>

        <!-- ============================================================
        ANALYTICS GROUP
        ============================================================ -->
        <div class="nav-group-label">
            <span>Analytics</span>
        </div>

        <!-- Reports & Analytics Dropdown -->
        <div class="nav-dropdown <?php echo strpos($page_title, 'Report') !== false ? 'open' : ''; ?>">
            <div class="nav-dropdown-header" data-target="reportMenu">
                <i class="fas fa-chart-bar"></i>
                <span class="nav-text">Reports & Analytics</span>
                <i class="fas fa-chevron-down dropdown-arrow"></i>
            </div>
            <div class="nav-dropdown-menu" id="reportMenu">
                <a href="reports.php" class="nav-item <?php echo $page_title === 'Reports' ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt"></i>
                    <span class="nav-text">All Reports</span>
                </a>
                <a href="reports.php?type=financial" class="nav-item">
                    <i class="fas fa-money-bill-wave"></i>
                    <span class="nav-text">Financial Reports</span>
                </a>
                <a href="reports.php?type=election" class="nav-item">
                    <i class="fas fa-vote-yea"></i>
                    <span class="nav-text">Election Reports</span>
                </a>
                <a href="reports.php?type=user" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span class="nav-text">User Analytics</span>
                </a>
                <a href="reports.php?type=tenant" class="nav-item">
                    <i class="fas fa-building"></i>
                    <span class="nav-text">Tenant Analytics</span>
                </a>
            </div>
        </div>

        <!-- Billing & Invoices Dropdown -->
        <div class="nav-dropdown <?php echo strpos($page_title, 'Billing') !== false ? 'open' : ''; ?>">
            <div class="nav-dropdown-header" data-target="billingMenu">
                <i class="fas fa-receipt"></i>
                <span class="nav-text">Billing & Invoices</span>
                <i class="fas fa-chevron-down dropdown-arrow"></i>
            </div>
            <div class="nav-dropdown-menu" id="billingMenu">
                <a href="billing.php" class="nav-item <?php echo $page_title === 'Billing' ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i>
                    <span class="nav-text">All Invoices</span>
                </a>
                <a href="billing.php?status=unpaid" class="nav-item">
                    <i class="fas fa-exclamation-circle"></i>
                    <span class="nav-text">Unpaid Invoices</span>
                    <span class="nav-badge warning">5</span>
                </a>
                <a href="billing.php?status=paid" class="nav-item">
                    <i class="fas fa-check-circle"></i>
                    <span class="nav-text">Paid Invoices</span>
                </a>
                <a href="subscription-plans.php" class="nav-item">
                    <i class="fas fa-crown"></i>
                    <span class="nav-text">Subscription Plans</span>
                </a>
            </div>
        </div>

        <!-- ============================================================
        DIVIDER & LOGOUT
        ============================================================ -->
        <hr class="nav-divider">

        <a href="#" class="nav-item logout-item" onclick="confirmLogout()">
            <i class="fas fa-sign-out-alt"></i>
            <span class="nav-text">Logout</span>
        </a>
    </nav>

    <!-- ============================================================
    SIDEBAR FOOTER
    ============================================================ -->
    <div class="sidebar-footer">
        <div class="user-profile">
            <div class="user-avatar">
                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='40' height='40' viewBox='0 0 40 40'%3E%3Ccircle cx='20' cy='20' r='18' fill='%23b3cef0'/%3E%3Ccircle cx='20' cy='14' r='6' fill='%23577a9e'/%3E%3Cpath d='M8 32c0-6 5-10 12-10s12 4 12 10' fill='%23577a9e'/%3E%3C/svg%3E" alt="User Avatar">
            </div>
            <div class="user-info">
                <div class="user-name">Super Admin</div>
                <div class="user-role">KowaGuru Tech</div>
            </div>
        </div>
        <div class="sidebar-status">
            <span class="status-dot online"></span>
            <span class="status-text">Online</span>
            <span class="version">v3.1.0</span>
        </div>
    </div>
</aside>