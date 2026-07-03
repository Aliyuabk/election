<?php
// ============================================================
// CLIENT ADMIN SIDEBAR
// ============================================================

$user_name = SessionManager::get('user_name', 'Administrator');
?>

<!-- ============================================================
SIDEBAR
============================================================ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <i class="fas fa-bolt"></i>
        <div>
            <span><?php echo APP_NAME; ?></span>
            <small>Client Admin</small>
        </div>
    </div>

    <ul class="sidebar-nav">
        <li class="nav-label">Main</li>
        <li><a href="index.php" class="active"><i class="fas fa-th-large"></i> Dashboard</a></li>
        <li><a href="profile.php"><i class="fas fa-building"></i> Organization</a></li>
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

        <li class="nav-label">Political Structure</li>
        <li>
            <a href="#" class="dropdown-toggle" data-dropdown="structure-dropdown">
                <i class="fas fa-sitemap"></i> Structure
                <i class="fas fa-chevron-down chevron"></i>
            </a>
            <div class="dropdown-menu" id="structure-dropdown">
                <a href="states.php"><i class="fas fa-flag"></i> States</a>
                <a href="lgas.php"><i class="fas fa-map-marker-alt"></i> LGAs</a>
                <a href="wards.php"><i class="fas fa-layer-group"></i> Wards</a>
                <a href="polling-units.php"><i class="fas fa-flag-checkered"></i> Polling Units</a>
            </div>
        </li>

        <li class="nav-label">Agents</li>
        <li>
            <a href="#" class="dropdown-toggle" data-dropdown="agents-dropdown">
                <i class="fas fa-user-tie"></i> Agents
                <i class="fas fa-chevron-down chevron"></i>
            </a>
            <div class="dropdown-menu" id="agents-dropdown">
                <a href="agents.php"><i class="fas fa-list"></i> All Agents</a>
                <a href="agents-assign.php"><i class="fas fa-user-plus"></i> Assign Agent</a>
                <a href="agents-payments.php"><i class="fas fa-money-bill-wave"></i> Payments</a>
            </div>
        </li>

        <li class="nav-label">Candidates</li>
        <li>
            <a href="#" class="dropdown-toggle" data-dropdown="candidates-dropdown">
                <i class="fas fa-user-tie"></i> Candidates
                <i class="fas fa-chevron-down chevron"></i>
            </a>
            <div class="dropdown-menu" id="candidates-dropdown">
                <a href="candidates.php"><i class="fas fa-list"></i> All Candidates</a>
                <a href="candidates-add.php"><i class="fas fa-user-plus"></i> Add Candidate</a>
            </div>
        </li>

        <li class="nav-label">Parties</li>
        <li>
            <a href="#" class="dropdown-toggle" data-dropdown="parties-dropdown">
                <i class="fas fa-flag"></i> Political Parties
                <i class="fas fa-chevron-down chevron"></i>
            </a>
            <div class="dropdown-menu" id="parties-dropdown">
                <a href="parties.php"><i class="fas fa-list"></i> All Parties</a>
                <a href="parties-add.php"><i class="fas fa-plus"></i> Add Party</a>
            </div>
        </li>

        <li class="nav-label">Results</li>
        <li>
            <a href="#" class="dropdown-toggle" data-dropdown="results-dropdown">
                <i class="fas fa-chart-bar"></i> Results
                <i class="fas fa-chevron-down chevron"></i>
            </a>
            <div class="dropdown-menu" id="results-dropdown">
                <a href="results-ec8a.php"><i class="fas fa-flag-checkered"></i> EC8A (PU)</a>
                <a href="results-ec8b.php"><i class="fas fa-layer-group"></i> EC8B (Ward)</a>
                <a href="results-ec8c.php"><i class="fas fa-map"></i> EC8C (LGA)</a>
                <a href="results-ec8d.php"><i class="fas fa-map-marked-alt"></i> EC8D (State)</a>
                <a href="results-ec8e.php"><i class="fas fa-flag"></i> EC8E (National)</a>
            </div>
        </li>

        <li class="nav-label">Broadcast</li>
        <li>
            <a href="#" class="dropdown-toggle" data-dropdown="broadcast-dropdown">
                <i class="fas fa-bullhorn"></i> Broadcast
                <i class="fas fa-chevron-down chevron"></i>
            </a>
            <div class="dropdown-menu" id="broadcast-dropdown">
                <a href="broadcasts.php"><i class="fas fa-list"></i> All Broadcasts</a>
                <a href="broadcasts-create.php"><i class="fas fa-plus"></i> New Broadcast</a>
            </div>
        </li>

        <li class="nav-label">Incidents</li>
        <li><a href="incidents.php"><i class="fas fa-exclamation-triangle"></i> Incidents</a></li>

        <li class="nav-label">Financial</li>
        <li>
            <a href="#" class="dropdown-toggle" data-dropdown="financial-dropdown">
                <i class="fas fa-money-bill-wave"></i> Financial
                <i class="fas fa-chevron-down chevron"></i>
            </a>
            <div class="dropdown-menu" id="financial-dropdown">
                <a href="budgets.php"><i class="fas fa-coins"></i> Budgets</a>
                <a href="expenses.php"><i class="fas fa-receipt"></i> Expenses</a>
                <a href="agent-payments.php"><i class="fas fa-user-check"></i> Agent Payments</a>
            </div>
        </li>

        <li class="nav-label">Reports</li>
        <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>

        <li class="nav-label">System</li>
        <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
        <li><a href="audit-logs.php"><i class="fas fa-clipboard-list"></i> Audit Logs</a></li>
    </ul>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar"><?php echo strtoupper(substr($user_name, 0, 2)); ?></div>
            <div>
                <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                <div class="user-role">Client Administrator</div>
            </div>
        </div>
        <a href="../../auth/logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</aside>
