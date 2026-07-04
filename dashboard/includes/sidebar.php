<?php
// ============================================================
// PROFESSIONAL SIDEBAR - Dark Theme with Dropdowns
// ============================================================

$user_name = SessionManager::get('user_name', 'Administrator');
$role_level = SessionManager::get('role_level', 'client_admin');

// Get user's jurisdiction data
$user_state_id = SessionManager::get('state_id', null);
$user_lga_id = SessionManager::get('lga_id', null);
$user_ward_id = SessionManager::get('ward_id', null);
$user_pu_id = SessionManager::get('pu_id', null);
$user_tenant_id = SessionManager::get('tenant_id', null);

// Role display mapping
$role_display = [
    'national' => 'National Coordinator',
    'state' => 'State Coordinator',
    'senatorial' => 'Senatorial Coordinator',
    'federal_constituency' => 'Federal Constituency Coordinator',
    'lga' => 'LGA Coordinator',
    'ward' => 'Ward Coordinator',
    'pu_agent' => 'Polling Unit Agent',
    'client_admin' => 'Client Administrator'
];

$jurisdiction_labels = [
    'national' => 'National',
    'state' => 'State',
    'senatorial' => 'Senatorial District',
    'federal_constituency' => 'Federal Constituency',
    'lga' => 'LGA',
    'ward' => 'Ward',
    'pu_agent' => 'Polling Unit'
];

$jurisdiction_icons = [
    'national' => 'fa-globe-africa',
    'state' => 'fa-flag',
    'senatorial' => 'fa-users',
    'federal_constituency' => 'fa-building',
    'lga' => 'fa-map-marker-alt',
    'ward' => 'fa-layer-group',
    'pu_agent' => 'fa-flag-checkered'
];

$current_role = $role_display[$role_level] ?? 'Coordinator';
$jurisdiction_label = $jurisdiction_labels[$role_level] ?? 'Dashboard';
$jurisdiction_name = getJurisdictionName($role_level, $user_state_id, $user_lga_id, $user_ward_id, $user_pu_id);

function getJurisdictionName($role, $state_id, $lga_id, $ward_id, $pu_id) {
    try {
        $db = getDB();
        
        switch($role) {
            case 'national': return 'Nigeria';
            case 'state':
                if ($state_id) {
                    $stmt = $db->prepare("SELECT name FROM states WHERE id = ?");
                    $stmt->execute([$state_id]);
                    return $stmt->fetchColumn() ?: 'State';
                }
                return 'State';
            case 'senatorial': return 'Senatorial District';
            case 'federal_constituency': return 'Federal Constituency';
            case 'lga':
                if ($lga_id) {
                    $stmt = $db->prepare("SELECT name FROM lgas WHERE id = ?");
                    $stmt->execute([$lga_id]);
                    return $stmt->fetchColumn() ?: 'LGA';
                }
                return 'LGA';
            case 'ward':
                if ($ward_id) {
                    $stmt = $db->prepare("SELECT name FROM wards WHERE id = ?");
                    $stmt->execute([$ward_id]);
                    return $stmt->fetchColumn() ?: 'Ward';
                }
                return 'Ward';
            case 'pu_agent': return 'Polling Unit';
            default: return 'Dashboard';
        }
    } catch (Exception $e) {
        return 'Dashboard';
    }
}

// Menu configuration (keep existing role_menus array)
$role_menus = [
    'national' => [
        'main' => [
            ['label' => 'Dashboard', 'icon' => 'fa-th-large', 'url' => 'index.php', 'active' => 'dashboard'],
            ['label' => 'Monitor States', 'icon' => 'fa-flag', 'url' => 'monitor-states.php', 'badge' => '36'],
        ],
        'elections' => [
            ['label' => 'Elections', 'icon' => 'fa-vote-yea', 'dropdown' => true, 'id' => 'elections-dropdown',
                'items' => [
                    ['label' => 'All Elections', 'icon' => 'fa-list', 'url' => 'elections.php'],
                    ['label' => 'Create Election', 'icon' => 'fa-plus', 'url' => 'elections-create.php'],
                    ['label' => 'Election Templates', 'icon' => 'fa-copy', 'url' => 'elections-templates.php'],
                    ['label' => 'Live Results', 'icon' => 'fa-chart-line', 'url' => 'live-results.php', 'badge' => 'Live']
                ]
            ]
        ],
        'results' => [
            ['label' => 'Result Verification', 'icon' => 'fa-check-double', 'url' => 'result-verification.php', 'badge' => '12'],
            ['label' => 'EC8 Forms', 'icon' => 'fa-file-alt', 'dropdown' => true, 'id' => 'ec8-dropdown',
                'items' => [
                    ['label' => 'EC8A (PU)', 'icon' => 'fa-flag-checkered', 'url' => 'results-ec8a.php'],
                    ['label' => 'EC8B (Ward)', 'icon' => 'fa-layer-group', 'url' => 'results-ec8b.php'],
                    ['label' => 'EC8C (LGA)', 'icon' => 'fa-map', 'url' => 'results-ec8c.php'],
                    ['label' => 'EC8D (State)', 'icon' => 'fa-map-marked-alt', 'url' => 'results-ec8d.php'],
                    ['label' => 'EC8E (National)', 'icon' => 'fa-flag', 'url' => 'results-ec8e.php'],
                ]
            ]
        ],
        'communications' => [
            ['label' => 'Broadcast', 'icon' => 'fa-bullhorn', 'url' => 'broadcasts.php', 'badge' => 'New'],
            ['label' => 'Incident Monitoring', 'icon' => 'fa-exclamation-triangle', 'url' => 'incidents.php', 'badge' => '⚠'],
        ],
        'reports' => [
            ['label' => 'Analytics', 'icon' => 'fa-chart-pie', 'url' => 'analytics.php'],
            ['label' => 'Reports', 'icon' => 'fa-file-alt', 'url' => 'reports.php'],
        ],
        'system' => [
            ['label' => 'Settings', 'icon' => 'fa-cog', 'url' => 'settings.php'],
        ]
    ],
    // Add other roles (state, senatorial, federal_constituency, lga, ward, pu_agent, client_admin)
    // ... (keep existing role configurations)
];

$current_page = basename($_SERVER['PHP_SELF'], '.php');
$menu = $role_menus[$role_level] ?? $role_menus['client_admin'];
$menu_sections = [
    'main' => ['label' => 'Main', 'icon' => 'fa-home'],
    'elections' => ['label' => 'Elections', 'icon' => 'fa-vote-yea'],
    'results' => ['label' => 'Results', 'icon' => 'fa-chart-bar'],
    'communications' => ['label' => 'Communications', 'icon' => 'fa-comments'],
    'structure' => ['label' => 'Structure', 'icon' => 'fa-sitemap'],
    'agents' => ['label' => 'Agents', 'icon' => 'fa-user-tie'],
    'candidates' => ['label' => 'Candidates', 'icon' => 'fa-user-tie'],
    'parties' => ['label' => 'Parties', 'icon' => 'fa-flag'],
    'incidents' => ['label' => 'Incidents', 'icon' => 'fa-exclamation-triangle'],
    'financial' => ['label' => 'Financial', 'icon' => 'fa-money-bill-wave'],
    'reports' => ['label' => 'Reports', 'icon' => 'fa-file-alt'],
    'system' => ['label' => 'System', 'icon' => 'fa-cog']
];

function getBadgeClass($badge) {
    $badgeMap = [
        'Live' => 'badge-live',
        'New' => 'badge-new',
        '⚠' => 'badge-warning',
        '🔴' => 'badge-danger',
        '📤' => 'badge-info',
        '🌐' => 'badge-primary'
    ];
    return $badgeMap[$badge] ?? 'badge-default';
}

function isDropdownActive($items, $current_page) {
    foreach ($items as $item) {
        if (isset($item['url']) && basename($item['url'], '.php') == $current_page) return true;
        if (isset($item['items']) && isDropdownActive($item['items'], $current_page)) return true;
    }
    return false;
}
?>

<!-- ============================================================
SIDEBAR - Dark Professional Theme
============================================================ -->
<aside class="sidebar" id="sidebar">
    <!-- Brand Section -->
    <div class="sidebar-brand">
        <div class="brand-icon">
            <i class="fas fa-bolt"></i>
        </div>
        <div class="brand-text">
            <span><?php echo APP_NAME; ?></span>
            <small><?php echo $current_role; ?></small>
        </div>
    </div>

    <!-- Jurisdiction Info -->
    <div class="sidebar-jurisdiction">
        <div class="jurisdiction-icon">
            <i class="fas <?php echo $jurisdiction_icons[$role_level] ?? 'fa-dashboard'; ?>"></i>
        </div>
        <div class="jurisdiction-info">
            <span class="jurisdiction-label"><?php echo $jurisdiction_label; ?></span>
            <span class="jurisdiction-name"><?php echo htmlspecialchars($jurisdiction_name); ?></span>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <?php foreach ($menu_sections as $section_key => $section_data):
            if (!isset($menu[$section_key]) || empty($menu[$section_key])) continue;
        ?>
            <div class="nav-section">
                <div class="nav-section-header">
                    <i class="fas <?php echo $section_data['icon']; ?>"></i>
                    <span><?php echo $section_data['label']; ?></span>
                </div>
                
                <?php foreach ($menu[$section_key] as $item):
                    $is_active = isset($item['active']) && $item['active'] == $current_page;
                    $has_dropdown = isset($item['dropdown']) && $item['dropdown'] === true;
                    $is_dropdown_active = $has_dropdown && isDropdownActive($item['items'], $current_page);
                ?>
                    <?php if ($has_dropdown): ?>
                        <div class="nav-item nav-dropdown">
                            <a href="#" class="nav-link dropdown-toggle <?php echo ($is_active || $is_dropdown_active) ? 'active' : ''; ?>" 
                               data-dropdown="<?php echo $item['id']; ?>">
                                <i class="fas <?php echo $item['icon']; ?>"></i>
                                <span class="nav-label"><?php echo $item['label']; ?></span>
                                <i class="fas fa-chevron-down chevron <?php echo $is_dropdown_active ? 'open' : ''; ?>"></i>
                            </a>
                            <div class="dropdown-menu <?php echo $is_dropdown_active ? 'open' : ''; ?>" id="<?php echo $item['id']; ?>">
                                <?php foreach ($item['items'] as $sub_item):
                                    $sub_active = isset($sub_item['url']) && basename($sub_item['url'], '.php') == $current_page;
                                ?>
                                    <a href="<?php echo $sub_item['url']; ?>" class="dropdown-item <?php echo $sub_active ? 'active' : ''; ?>">
                                        <i class="fas <?php echo $sub_item['icon']; ?>"></i>
                                        <?php echo $sub_item['label']; ?>
                                        <?php if (isset($sub_item['badge'])): ?>
                                            <span class="badge <?php echo getBadgeClass($sub_item['badge']); ?>">
                                                <?php echo $sub_item['badge']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="nav-item">
                            <a href="<?php echo $item['url']; ?>" class="nav-link <?php echo $is_active ? 'active' : ''; ?>">
                                <i class="fas <?php echo $item['icon']; ?>"></i>
                                <span class="nav-label"><?php echo $item['label']; ?></span>
                                <?php if (isset($item['badge'])): ?>
                                    <span class="badge <?php echo getBadgeClass($item['badge']); ?>">
                                        <?php echo $item['badge']; ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </nav>

    <!-- Footer -->
    <div class="sidebar-footer">
        <div class="user-profile">
            <div class="user-avatar">
                <?php echo strtoupper(substr($user_name, 0, 2)); ?>
                <span class="online-status"></span>
            </div>
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                <div class="user-role"><?php echo $current_role; ?></div>
            </div>
        </div>
        <div class="sidebar-actions">
            <a href="../../auth/logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</aside>