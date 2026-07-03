<!-- ============================================================
SIDEBAR OVERLAY (mobile)
============================================================ -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ============================================================
SIDEBAR
============================================================ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <i class="fas fa-bolt"></i>
        <div>
            <span><?php echo APP_NAME; ?></span>
            <small>Super Admin</small>
        </div>
    </div>

    <ul class="sidebar-nav">
        <li class="nav-label">Main</li>
        <li><a href="index.php" class="active"><i class="fas fa-th-large"></i> Dashboard</a></li>
        <li><a href="tenants.php"><i class="fas fa-building"></i> Tenants</a></li>
        <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>

        <li class="nav-label">Elections</li>
        <li>
            <a href="#" class="dropdown-toggle" data-dropdown="elections-dropdown">
                <i class="fas fa-vote-yea"></i> Elections
                <i class="fas fa-chevron-down chevron"></i>
            </a>
            <div class="dropdown-menu" id="elections-dropdown">
                <a href="elections.php"><i class="fas fa-list"></i> All Elections</a>
                <a href="elections-create.php"><i class="fas fa-plus"></i> Create Election</a>
                <a href="elections-templates.php"><i class="fas fa-copy"></i> Templates</a>
            </div>
        </li>

        <li class="nav-label">Management</li>
        <li>
            <a href="#" class="dropdown-toggle" data-dropdown="management-dropdown">
                <i class="fas fa-cogs"></i> Management
                <i class="fas fa-chevron-down chevron"></i>
            </a>
            <div class="dropdown-menu" id="management-dropdown">
                <a href="subscriptions.php"><i class="fas fa-credit-card"></i> Subscriptions</a>
                <a href="billing.php"><i class="fas fa-file-invoice"></i> Billing</a>
                <a href="roles.php"><i class="fas fa-user-shield"></i> Roles</a>
                <a href="inec-data.php"><i class="fas fa-database"></i> INEC Data</a>
            </div>
        </li>

        <li class="nav-label">System</li>
        <li>
            <a href="#" class="dropdown-toggle" data-dropdown="system-dropdown">
                <i class="fas fa-server"></i> System
                <i class="fas fa-chevron-down chevron"></i>
            </a>
            <div class="dropdown-menu" id="system-dropdown">
                <a href="audit-logs.php"><i class="fas fa-clipboard-list"></i> Audit Logs</a>
                <a href="backups.php"><i class="fas fa-archive"></i> Backups</a>
                <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                <a href="api-management.php"><i class="fas fa-code"></i> API Management</a>
            </div>
        </li>

        <li class="nav-label">Support</li>
        <li><a href="tickets.php"><i class="fas fa-ticket-alt"></i> Support Tickets</a></li>
        <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
    </ul>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar"><?php echo strtoupper(substr($user_name, 0, 2)); ?></div>
            <div>
                <div class="user-name text-truncate"><?php echo htmlspecialchars($user_name); ?></div>
                <div class="user-role">Super Admin</div>
            </div>
        </div>
        <a href="../../auth/logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</aside>