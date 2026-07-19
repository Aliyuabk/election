<?php
// ============================================================
// PROFESSIONAL SIDEBAR - Dark Theme with Role-Based Access
// ============================================================

$user_name = SessionManager::get('user_name', 'Administrator');
$role_level = SessionManager::get('role_level', 'client_admin');

// Get user's jurisdiction data
$user_state_id = SessionManager::get('state_id', null);
$user_lga_id = SessionManager::get('lga_id', null);
$user_ward_id = SessionManager::get('ward_id', null);
$user_pu_id = SessionManager::get('pu_id', null);
$user_tenant_id = SessionManager::get('tenant_id', null);
$user_senatorial_id = SessionManager::get('senatorial_id', null);
$user_constituency_id = SessionManager::get('federal_constituency_id', null);

// Role display mapping
$role_display = [
    'national' => 'National Coordinator',
    'state' => 'State Coordinator',
    'senatorial' => 'Senatorial Coordinator',
    'federal_constituency' => 'Federal Constituency Coordinator',
    'lga' => 'LGA Coordinator',
    'ward' => 'Ward Coordinator',
    'pu_agent' => 'Polling Unit Agent',
    'client_admin' => 'Client Administrator',
    'party_agent' => 'Party Agent',
    'observer' => 'Observer',
    'situation_room' => 'Situation Room',
    'finance_officer' => 'Finance Officer',
    'citizen' => 'Citizen',
    'volunteer' => 'Volunteer'
];

$jurisdiction_labels = [
    'national' => 'National',
    'state' => 'State',
    'senatorial' => 'Senatorial District',
    'federal_constituency' => 'Federal Constituency',
    'lga' => 'LGA',
    'ward' => 'Ward',
    'pu_agent' => 'Polling Unit',
    'client_admin' => 'Organization',
    'volunteer' => 'Volunteer Area'
];

$jurisdiction_icons = [
    'national' => 'fa-globe-africa',
    'state' => 'fa-flag',
    'senatorial' => 'fa-users',
    'federal_constituency' => 'fa-building',
    'lga' => 'fa-map-marker-alt',
    'ward' => 'fa-layer-group',
    'pu_agent' => 'fa-flag-checkered',
    'client_admin' => 'fa-user-cog',
    'volunteer' => 'fa-hands-helping'
];

$current_role = $role_display[$role_level] ?? 'Coordinator';
$jurisdiction_label = $jurisdiction_labels[$role_level] ?? 'Dashboard';
$jurisdiction_name = getJurisdictionName($role_level, $user_state_id, $user_lga_id, $user_ward_id, $user_pu_id, $user_senatorial_id, $user_constituency_id);

function getJurisdictionName($role, $state_id, $lga_id, $ward_id, $pu_id, $senatorial_id, $constituency_id) {
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
            case 'senatorial':
                if ($senatorial_id) {
                    $stmt = $db->prepare("SELECT name FROM senatorial_districts WHERE id = ?");
                    $stmt->execute([$senatorial_id]);
                    return $stmt->fetchColumn() ?: 'Senatorial District';
                }
                return 'Senatorial District';
            case 'federal_constituency':
                if ($constituency_id) {
                    $stmt = $db->prepare("SELECT name FROM federal_constituencies WHERE id = ?");
                    $stmt->execute([$constituency_id]);
                    return $stmt->fetchColumn() ?: 'Federal Constituency';
                }
                return 'Federal Constituency';
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
            case 'client_admin': return 'Organization';
            case 'volunteer': return 'Volunteer Area';
            default: return 'Dashboard';
        }
    } catch (Exception $e) {
        error_log("Error fetching jurisdiction name: " . $e->getMessage());
        return 'Dashboard';
    }
}

// ============================================================
// ROLE-BASED MENU CONFIGURATION
// ============================================================

$role_menus = [];

// ============================================================
// 1. NATIONAL COORDINATOR MENU
// ============================================================
$role_menus['national'] = [
    'main' => [
        ['label' => 'Dashboard', 'icon' => 'fa-th-large', 'url' => 'index.php', 'active' => 'dashboard'],
        ['label' => 'Monitor States', 'icon' => 'fa-flag', 'url' => 'monitor-states.php', 'badge' => '36'],
    ],
    'elections' => [
        ['label' => 'Elections', 'icon' => 'fa-vote-yea', 'dropdown' => true, 'id' => 'elections-dropdown',
            'items' => [
                ['label' => 'All Elections', 'icon' => 'fa-list', 'url' => 'elections.php'],
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
];

// ============================================================
// 2. STATE COORDINATOR MENU
// ============================================================
$role_menus['state'] = [
    'main' => [
        ['label' => 'Dashboard', 'icon' => 'fa-th-large', 'url' => 'index.php', 'active' => 'dashboard'],
        ['label' => 'Monitor LGAs', 'icon' => 'fa-map-marker-alt', 'url' => 'monitor-lgas.php', 'badge' => '24'],
    ],
    'structure' => [
        ['label' => 'Manage LGA Coordinators', 'icon' => 'fa-user-tie', 'dropdown' => true, 'id' => 'lga-coordinators-dropdown',
            'items' => [
                ['label' => 'All Coordinators', 'icon' => 'fa-list', 'url' => 'lga-coordinators.php'],
                ['label' => 'Assign Coordinator', 'icon' => 'fa-user-plus', 'url' => 'lga-coordinators-assign.php'],
                ['label' => 'Reassign Coordinator', 'icon' => 'fa-exchange-alt', 'url' => 'lga-coordinators-reassign.php'],
                ['label' => 'View Profiles', 'icon' => 'fa-id-card', 'url' => 'lga-coordinators-profiles.php'],
                ['label' => 'Suspend Coordinator', 'icon' => 'fa-pause', 'url' => 'lga-coordinators-suspend.php'],
                ['label' => 'Reset Password', 'icon' => 'fa-key', 'url' => 'lga-coordinators-reset-password.php'],
                ['label' => 'View Activity', 'icon' => 'fa-clock', 'url' => 'lga-coordinators-activity.php'],
            ]
        ]
    ],
    'elections' => [
        ['label' => 'Elections', 'icon' => 'fa-vote-yea', 'dropdown' => true, 'id' => 'elections-dropdown',
            'items' => [
                ['label' => 'All Elections', 'icon' => 'fa-list', 'url' => 'elections.php'],
                ['label' => 'Election Progress', 'icon' => 'fa-chart-line', 'url' => 'election-progress.php'],
            ]
        ]
    ],
    'results' => [
        ['label' => 'Result Verification', 'icon' => 'fa-check-double', 'dropdown' => true, 'id' => 'result-verification-dropdown',
            'items' => [
                ['label' => 'Result Verification Dashboard', 'icon' => 'fa-dashboard', 'url' => 'result-verification.php'],
                ['label' => 'Verify EC8A', 'icon' => 'fa-file-alt', 'url' => 'verify-ec8a.php'],
                ['label' => 'Verify EC8B', 'icon' => 'fa-file-alt', 'url' => 'verify-ec8b.php'],
                ['label' => 'Compare Results', 'icon' => 'fa-balance-scale', 'url' => 'compare-results.php'],
                ['label' => 'Approve Results', 'icon' => 'fa-check-circle', 'url' => 'approve-results.php'],
                ['label' => 'Reject Results', 'icon' => 'fa-times-circle', 'url' => 'reject-results.php'],
                ['label' => 'Request Correction', 'icon' => 'fa-edit', 'url' => 'request-correction.php'],
            ]
        ]
    ],
    'communications' => [
        ['label' => 'Broadcast', 'icon' => 'fa-bullhorn', 'dropdown' => true, 'id' => 'broadcast-dropdown',
            'items' => [
                ['label' => 'Send to LGA Coordinators', 'icon' => 'fa-user-tie', 'url' => 'broadcast-lga-coordinators.php'],
                ['label' => 'Send to Ward Coordinators', 'icon' => 'fa-user', 'url' => 'broadcast-ward-coordinators.php'],
                ['label' => 'Send to PU Agents', 'icon' => 'fa-user-check', 'url' => 'broadcast-pu-agents.php'],
                ['label' => 'Create Broadcast', 'icon' => 'fa-plus', 'url' => 'broadcasts-create.php'],
                ['label' => 'Schedule Broadcast', 'icon' => 'fa-calendar-plus', 'url' => 'broadcasts-schedule.php'],
                ['label' => 'Edit Broadcast', 'icon' => 'fa-edit', 'url' => 'broadcasts-edit.php'],
                ['label' => 'Delete Broadcast', 'icon' => 'fa-trash', 'url' => 'broadcasts-delete.php'],
                ['label' => 'Send Broadcast', 'icon' => 'fa-paper-plane', 'url' => 'broadcasts-send.php'],
                ['label' => 'View Broadcasts', 'icon' => 'fa-list', 'url' => 'broadcasts.php'],
            ]
        ],
        ['label' => 'Incident Management', 'icon' => 'fa-exclamation-triangle', 'dropdown' => true, 'id' => 'incident-dropdown',
            'items' => [
                ['label' => 'View Incidents', 'icon' => 'fa-list', 'url' => 'incidents.php'],
                ['label' => 'Update Status', 'icon' => 'fa-edit', 'url' => 'incident-update.php'],
                ['label' => 'Escalate Incident', 'icon' => 'fa-arrow-up', 'url' => 'incident-escalate.php'],
                ['label' => 'Resolve Incident', 'icon' => 'fa-check-circle', 'url' => 'incident-resolve.php'],
                ['label' => 'Close Incident', 'icon' => 'fa-times-circle', 'url' => 'incident-close.php'],
                ['label' => 'Add Notes', 'icon' => 'fa-sticky-note', 'url' => 'incident-add-notes.php'],
            ]
        ]
    ],
    'reports' => [
        ['label' => 'Reports', 'icon' => 'fa-file-alt', 'dropdown' => true, 'id' => 'reports-dropdown',
            'items' => [
                ['label' => 'State Report', 'icon' => 'fa-file-pdf', 'url' => 'reports-state.php'],
                ['label' => 'LGA Performance', 'icon' => 'fa-chart-bar', 'url' => 'reports-lga-performance.php'],
                ['label' => 'Election Report', 'icon' => 'fa-file-alt', 'url' => 'reports-election.php'],
                ['label' => 'Incident Report', 'icon' => 'fa-exclamation-triangle', 'url' => 'reports-incident.php'],
                ['label' => 'Coordinator Report', 'icon' => 'fa-user-tie', 'url' => 'reports-coordinators.php'],
            ]
        ]
    ],
    'exports' => [
        ['label' => 'Export', 'icon' => 'fa-download', 'dropdown' => true, 'id' => 'export-dropdown',
            'items' => [
                ['label' => 'Export as PDF', 'icon' => 'fa-file-pdf', 'url' => 'export-pdf.php'],
                ['label' => 'Export as Excel', 'icon' => 'fa-file-excel', 'url' => 'export-excel.php'],
                ['label' => 'Export as CSV', 'icon' => 'fa-file-csv', 'url' => 'export-csv.php'],
            ]
        ]
    ],
    'system' => [
        ['label' => 'Settings', 'icon' => 'fa-cog', 'url' => 'settings.php'],
    ]
];

// ============================================================
// 3. SENATORIAL COORDINATOR MENU
// ============================================================
$role_menus['senatorial'] = [
    'main' => [
        ['label' => 'Dashboard', 'icon' => 'fa-th-large', 'url' => 'index.php', 'active' => 'dashboard'],
        ['label' => 'Monitor Senatorial District', 'icon' => 'fa-university', 'url' => 'monitor-district.php'],
    ],
    'structure' => [
        ['label' => 'View LGAs', 'icon' => 'fa-map-marker-alt', 'url' => 'view-lgas.php'],
        ['label' => 'View Coordinators', 'icon' => 'fa-user-tie', 'url' => 'view-coordinators.php'],
        ['label' => 'Monitor Agents', 'icon' => 'fa-user-clock', 'url' => 'monitor-agents.php'],
    ],
    'elections' => [
        ['label' => 'View Elections', 'icon' => 'fa-vote-yea', 'url' => 'elections.php'],
        ['label' => 'Upload Progress', 'icon' => 'fa-upload', 'url' => 'upload-progress.php'],
    ],
    'communications' => [
        ['label' => 'Broadcast', 'icon' => 'fa-bullhorn', 'dropdown' => true, 'id' => 'broadcast-dropdown',
            'items' => [
                ['label' => 'Create Broadcast', 'icon' => 'fa-plus', 'url' => 'broadcasts-create.php'],
                ['label' => 'Schedule Broadcast', 'icon' => 'fa-calendar-plus', 'url' => 'broadcasts-schedule.php'],
                ['label' => 'View Broadcasts', 'icon' => 'fa-list', 'url' => 'broadcasts.php'],
                ['label' => 'Delivery Status', 'icon' => 'fa-check-circle', 'url' => 'broadcasts-delivery.php'],
            ]
        ]
    ],
    'reports' => [
        ['label' => 'Analytics', 'icon' => 'fa-chart-pie', 'dropdown' => true, 'id' => 'analytics-dropdown',
            'items' => [
                ['label' => 'District Performance', 'icon' => 'fa-chart-line', 'url' => 'analytics-district.php'],
                ['label' => 'Upload Statistics', 'icon' => 'fa-upload', 'url' => 'analytics-uploads.php'],
                ['label' => 'Incident Statistics', 'icon' => 'fa-exclamation-triangle', 'url' => 'analytics-incidents.php'],
                ['label' => 'Agent Performance', 'icon' => 'fa-user-chart', 'url' => 'analytics-agents.php'],
            ]
        ],
        ['label' => 'Reports', 'icon' => 'fa-file-alt', 'dropdown' => true, 'id' => 'reports-dropdown',
            'items' => [
                ['label' => 'District Report', 'icon' => 'fa-file-pdf', 'url' => 'reports-district.php'],
                ['label' => 'Election Summary', 'icon' => 'fa-file-alt', 'url' => 'reports-election-summary.php'],
                ['label' => 'Agent Report', 'icon' => 'fa-file-alt', 'url' => 'reports-agents.php'],
            ]
        ]
    ]
];

// ============================================================
// 4. FEDERAL CONSTITUENCY COORDINATOR MENU
// ============================================================
$role_menus['federal_constituency'] = [
    'main' => [
        ['label' => 'Dashboard', 'icon' => 'fa-th-large', 'url' => 'index.php', 'active' => 'dashboard'],
        ['label' => 'Monitor Constituency', 'icon' => 'fa-building', 'url' => 'monitor-constituency.php'],
    ],
    'structure' => [
        ['label' => 'View Wards', 'icon' => 'fa-layer-group', 'url' => 'view-wards.php'],
        ['label' => 'Monitor Polling Units', 'icon' => 'fa-flag-checkered', 'url' => 'monitor-pus.php'],
        ['label' => 'View Agents', 'icon' => 'fa-user-tie', 'url' => 'view-agents.php'],
        ['label' => 'Election Progress', 'icon' => 'fa-chart-line', 'url' => 'election-progress.php'],
    ],
    'communications' => [
        ['label' => 'Broadcast', 'icon' => 'fa-bullhorn', 'dropdown' => true, 'id' => 'broadcast-dropdown',
            'items' => [
                ['label' => 'Create Broadcast', 'icon' => 'fa-plus', 'url' => 'broadcasts-create.php'],
                ['label' => 'Edit Broadcast', 'icon' => 'fa-edit', 'url' => 'broadcasts-edit.php'],
                ['label' => 'Schedule Broadcast', 'icon' => 'fa-calendar-plus', 'url' => 'broadcasts-schedule.php'],
                ['label' => 'Send Broadcast', 'icon' => 'fa-paper-plane', 'url' => 'broadcasts-send.php'],
                ['label' => 'View Broadcasts', 'icon' => 'fa-list', 'url' => 'broadcasts.php'],
            ]
        ]
    ],
    'results' => [
        ['label' => 'Result Verification', 'icon' => 'fa-check-double', 'dropdown' => true, 'id' => 'verify-dropdown',
            'items' => [
                ['label' => 'Verify EC8A', 'icon' => 'fa-file-alt', 'url' => 'verify-ec8a.php'],
                ['label' => 'Verify EC8B', 'icon' => 'fa-file-alt', 'url' => 'verify-ec8b.php'],
                ['label' => 'Compare Results', 'icon' => 'fa-balance-scale', 'url' => 'compare-results.php'],
                ['label' => 'Flag Issues', 'icon' => 'fa-flag', 'url' => 'flag-issues.php'],
            ]
        ]
    ],
    'reports' => [
        ['label' => 'Reports', 'icon' => 'fa-file-alt', 'dropdown' => true, 'id' => 'reports-dropdown',
            'items' => [
                ['label' => 'Constituency Report', 'icon' => 'fa-file-pdf', 'url' => 'reports-constituency.php'],
                ['label' => 'Polling Unit Report', 'icon' => 'fa-file-alt', 'url' => 'reports-pu.php'],
                ['label' => 'Incident Report', 'icon' => 'fa-file-alt', 'url' => 'reports-incident.php'],
            ]
        ]
    ]
];

// ============================================================
// 5. LGA COORDINATOR MENU
// ============================================================
$role_menus['lga'] = [
    'main' => [
        ['label' => 'Dashboard', 'icon' => 'fa-th-large', 'url' => 'index.php', 'active' => 'dashboard'],
        ['label' => 'Manage Wards', 'icon' => 'fa-layer-group', 'url' => 'manage-wards.php'],
    ],
    'structure' => [
        ['label' => 'Ward Coordinators', 'icon' => 'fa-user-tie', 'dropdown' => true, 'id' => 'ward-coordinators-dropdown',
            'items' => [
                ['label' => 'View Ward Coordinators', 'icon' => 'fa-list', 'url' => 'ward-coordinators.php'],
                ['label' => 'View Polling Units', 'icon' => 'fa-flag-checkered', 'url' => 'polling-units.php'],
                ['label' => 'Search Coordinators', 'icon' => 'fa-search', 'url' => 'search-coordinators.php'],
                ['label' => 'Filter by Ward', 'icon' => 'fa-filter', 'url' => 'filter-wards.php'],
            ]
        ]
    ],
    'results' => [
        ['label' => 'Approve Results', 'icon' => 'fa-check-double', 'dropdown' => true, 'id' => 'approve-dropdown',
            'items' => [
                ['label' => 'View Submitted Results', 'icon' => 'fa-list', 'url' => 'submitted-results.php'],
                ['label' => 'Review EC8B', 'icon' => 'fa-file-alt', 'url' => 'review-ec8b.php'],
                ['label' => 'Approve Results', 'icon' => 'fa-check-circle', 'url' => 'approve-results.php'],
                ['label' => 'Request Correction', 'icon' => 'fa-edit', 'url' => 'request-correction.php'],
                ['label' => 'Approval History', 'icon' => 'fa-history', 'url' => 'approval-history.php'],
            ]
        ]
    ],
    'communications' => [
        ['label' => 'Broadcast', 'icon' => 'fa-bullhorn', 'dropdown' => true, 'id' => 'broadcast-dropdown',
            'items' => [
                ['label' => 'Send to Ward Coordinators', 'icon' => 'fa-user-tie', 'url' => 'broadcast-ward-coordinators.php'],
                ['label' => 'Send to PU Agents', 'icon' => 'fa-user', 'url' => 'broadcast-pu-agents.php'],
                ['label' => 'Create Broadcast', 'icon' => 'fa-plus', 'url' => 'broadcasts-create.php'],
                ['label' => 'Edit Broadcast', 'icon' => 'fa-edit', 'url' => 'broadcasts-edit.php'],
            ]
        ],
        ['label' => 'Incident Monitoring', 'icon' => 'fa-exclamation-triangle', 'dropdown' => true, 'id' => 'incident-dropdown',
            'items' => [
                ['label' => 'View Incidents', 'icon' => 'fa-list', 'url' => 'incidents.php'],
                ['label' => 'Update Status', 'icon' => 'fa-edit', 'url' => 'incident-update.php'],
                ['label' => 'Resolve Incident', 'icon' => 'fa-check-circle', 'url' => 'incident-resolve.php'],
                ['label' => 'Escalate Incident', 'icon' => 'fa-arrow-up', 'url' => 'incident-escalate.php'],
                ['label' => 'Close Incident', 'icon' => 'fa-times-circle', 'url' => 'incident-close.php'],
            ]
        ]
    ],
    'reports' => [
        ['label' => 'Reports', 'icon' => 'fa-file-alt', 'dropdown' => true, 'id' => 'reports-dropdown',
            'items' => [
                ['label' => 'LGA Report', 'icon' => 'fa-file-pdf', 'url' => 'reports-lga.php'],
                ['label' => 'Ward Report', 'icon' => 'fa-file-alt', 'url' => 'reports-ward.php'],
                ['label' => 'Agent Report', 'icon' => 'fa-file-alt', 'url' => 'reports-agents.php'],
            ]
        ]
    ]
];

// ============================================================
// 6. WARD COORDINATOR MENU - COMPLETE WITH ALL FEATURES
// ============================================================
$role_menus['ward'] = [
    'main' => [
        ['label' => 'Dashboard', 'icon' => 'fa-th-large', 'url' => 'index.php', 'active' => 'dashboard'],
        ['label' => 'Manage PU Agents', 'icon' => 'fa-user-tie', 'url' => 'manage-pu-agents.php'],
    ],
    'structure' => [
        ['label' => 'Polling Units', 'icon' => 'fa-flag-checkered', 'dropdown' => true, 'id' => 'pu-dropdown',
            'items' => [
                ['label' => 'View Polling Units', 'icon' => 'fa-list', 'url' => 'polling-units.php'],
                ['label' => 'View PU Details', 'icon' => 'fa-info-circle', 'url' => 'pu-details.php'],
                ['label' => 'View PU Status', 'icon' => 'fa-circle', 'url' => 'pu-status.php'],
            ]
        ]
    ],
    'personnel' => [
        ['label' => 'PU Data Agents', 'icon' => 'fa-user-check', 'dropdown' => true, 'id' => 'data-agents-dropdown',
            'items' => [
                ['label' => 'Assign Agent', 'icon' => 'fa-user-plus', 'url' => 'assign-agents.php'],
                ['label' => 'Change Assignment', 'icon' => 'fa-exchange-alt', 'url' => 'reassign-agent.php'],
                ['label' => 'Remove Assignment', 'icon' => 'fa-user-minus', 'url' => 'remove-agent.php'],
                ['label' => 'View Profile', 'icon' => 'fa-id-card', 'url' => 'agent-profile.php'],
                ['label' => 'View Performance', 'icon' => 'fa-chart-bar', 'url' => 'agent-performance.php'],
            ]
        ],
        ['label' => 'Party Agents', 'icon' => 'fa-flag', 'dropdown' => true, 'id' => 'party-agents-dropdown',
            'items' => [
                ['label' => 'Assign Agent', 'icon' => 'fa-user-plus', 'url' => 'assign-party-agents.php'],
                ['label' => 'Change Assignment', 'icon' => 'fa-exchange-alt', 'url' => 'reassign-party-agent.php'],
                ['label' => 'Remove Assignment', 'icon' => 'fa-user-minus', 'url' => 'remove-party-agent.php'],
                ['label' => 'View Reports', 'icon' => 'fa-file-alt', 'url' => 'party-agent-reports.php'],
            ]
        ],
        ['label' => 'Observers', 'icon' => 'fa-eye', 'dropdown' => true, 'id' => 'observers-dropdown',
            'items' => [
                ['label' => 'Assign Observer', 'icon' => 'fa-user-plus', 'url' => 'assign-observers.php'],
                ['label' => 'Change Assignment', 'icon' => 'fa-exchange-alt', 'url' => 'reassign-observer.php'],
                ['label' => 'Remove Assignment', 'icon' => 'fa-user-minus', 'url' => 'remove-observer.php'],
                ['label' => 'View Reports', 'icon' => 'fa-file-alt', 'url' => 'observer-reports.php'],
            ]
        ],
        ['label' => 'Volunteers', 'icon' => 'fa-hands-helping', 'dropdown' => true, 'id' => 'volunteers-dropdown',
            'items' => [
                ['label' => 'Assign Volunteer', 'icon' => 'fa-user-plus', 'url' => 'assign-volunteers.php'],
                ['label' => 'Assign Tasks', 'icon' => 'fa-tasks', 'url' => 'assign-tasks.php'],
                ['label' => 'View Progress', 'icon' => 'fa-chart-line', 'url' => 'volunteer-progress.php'],
                ['label' => 'Remove Assignment', 'icon' => 'fa-user-minus', 'url' => 'remove-volunteer.php'],
            ]
        ]
    ],
    'monitoring' => [
        ['label' => 'Election Monitoring', 'icon' => 'fa-tv', 'dropdown' => true, 'id' => 'monitoring-dropdown',
            'items' => [
                ['label' => 'Monitor Check-ins', 'icon' => 'fa-clock', 'url' => 'monitor-checkins.php'],
                ['label' => 'Monitor Polling Units', 'icon' => 'fa-flag-checkered', 'url' => 'monitor-pus.php'],
                ['label' => 'View Upload Status', 'icon' => 'fa-upload', 'url' => 'upload-status.php'],
                ['label' => 'View Election Progress', 'icon' => 'fa-chart-line', 'url' => 'election-progress.php'],
            ]
        ]
    ],
    'results' => [
        ['label' => 'Result Verification', 'icon' => 'fa-check-double', 'dropdown' => true, 'id' => 'verify-dropdown',
            'items' => [
                ['label' => 'Review EC8A Uploads', 'icon' => 'fa-file-alt', 'url' => 'review-ec8a.php'],
                ['label' => 'Approve Submission', 'icon' => 'fa-check-circle', 'url' => 'approve-submission.php'],
                ['label' => 'Reject Submission', 'icon' => 'fa-times-circle', 'url' => 'reject-submission.php'],
                ['label' => 'Request Correction', 'icon' => 'fa-edit', 'url' => 'request-correction.php'],
                ['label' => 'View Approval History', 'icon' => 'fa-history', 'url' => 'approval-history.php'],
            ]
        ],
        ['label' => 'Upload EC8B', 'icon' => 'fa-upload', 'dropdown' => true, 'id' => 'ec8b-dropdown',
            'items' => [
                ['label' => 'Create EC8B', 'icon' => 'fa-plus', 'url' => 'ec8b-create.php'],
                ['label' => 'Edit Draft', 'icon' => 'fa-edit', 'url' => 'ec8b-edit.php'],
                ['label' => 'Upload EC8B Form', 'icon' => 'fa-file-upload', 'url' => 'ec8b-upload.php'],
                ['label' => 'Attach Image', 'icon' => 'fa-image', 'url' => 'ec8b-attach.php'],
                ['label' => 'Add Remarks', 'icon' => 'fa-comment', 'url' => 'ec8b-remarks.php'],
                ['label' => 'Submit EC8B', 'icon' => 'fa-paper-plane', 'url' => 'ec8b-submit.php'],
                ['label' => 'View Submission History', 'icon' => 'fa-history', 'url' => 'ec8b-history.php'],
                ['label' => 'Supporting Documents', 'icon' => 'fa-folder-open', 'url' => 'ec8b-documents.php'],
            ]
        ]
    ],
    'incidents' => [
        ['label' => 'Incident Management', 'icon' => 'fa-exclamation-triangle', 'dropdown' => true, 'id' => 'incident-dropdown',
            'items' => [
                ['label' => 'View Incidents', 'icon' => 'fa-list', 'url' => 'incidents.php'],
                ['label' => 'Create Incident', 'icon' => 'fa-plus', 'url' => 'incident-create.php'],
                ['label' => 'Update Status', 'icon' => 'fa-edit', 'url' => 'incident-update.php'],
                ['label' => 'Resolve Incident', 'icon' => 'fa-check-circle', 'url' => 'incident-resolve.php'],
                ['label' => 'Escalate Incident', 'icon' => 'fa-arrow-up', 'url' => 'incident-escalate.php'],
                ['label' => 'Close Incident', 'icon' => 'fa-times-circle', 'url' => 'incident-close.php'],
                ['label' => 'Add Attachments', 'icon' => 'fa-paperclip', 'url' => 'incident-attachments.php'],
            ]
        ]
    ],
    'communications' => [
        ['label' => 'Broadcast Messages', 'icon' => 'fa-bullhorn', 'dropdown' => true, 'id' => 'broadcast-dropdown',
            'items' => [
                ['label' => 'Create Broadcast', 'icon' => 'fa-plus', 'url' => 'broadcasts-create.php'],
                ['label' => 'Edit Draft', 'icon' => 'fa-edit', 'url' => 'broadcasts-edit.php'],
                ['label' => 'Delete Draft', 'icon' => 'fa-trash', 'url' => 'broadcasts-delete.php'],
                ['label' => 'Send to All', 'icon' => 'fa-users', 'url' => 'broadcasts-send-all.php'],
                ['label' => 'Send to Selected Users', 'icon' => 'fa-user-check', 'url' => 'broadcasts-send-selected.php'],
                ['label' => 'Schedule Broadcast', 'icon' => 'fa-calendar-plus', 'url' => 'broadcasts-schedule.php'],
            ]
        ],
        ['label' => 'Chat', 'icon' => 'fa-comment-dots', 'dropdown' => true, 'id' => 'chat-dropdown',
            'items' => [
                ['label' => 'PU Data Agents', 'icon' => 'fa-user-check', 'url' => 'chat-agents.php'],
                ['label' => 'Party Agents', 'icon' => 'fa-flag', 'url' => 'chat-party-agents.php'],
                ['label' => 'Observers', 'icon' => 'fa-eye', 'url' => 'chat-observers.php'],
                ['label' => 'Volunteers', 'icon' => 'fa-hands-helping', 'url' => 'chat-volunteers.php'],
                ['label' => 'Send Text', 'icon' => 'fa-comment', 'url' => 'chat-send-text.php'],
                ['label' => 'Receive Messages', 'icon' => 'fa-inbox', 'url' => 'chat-inbox.php'],
                ['label' => 'Voice Messages', 'icon' => 'fa-microphone', 'url' => 'chat-voice.php'],
                ['label' => 'Send Images', 'icon' => 'fa-image', 'url' => 'chat-images.php'],
                ['label' => 'Send Videos', 'icon' => 'fa-video', 'url' => 'chat-videos.php'],
                ['label' => 'Send Documents', 'icon' => 'fa-file-alt', 'url' => 'chat-documents.php'],
                ['label' => 'Share Location', 'icon' => 'fa-location-dot', 'url' => 'chat-location.php'],
                ['label' => 'Download Files', 'icon' => 'fa-download', 'url' => 'chat-download.php'],
                ['label' => 'Search Messages', 'icon' => 'fa-search', 'url' => 'chat-search.php'],
                ['label' => 'View Online Status', 'icon' => 'fa-circle', 'url' => 'chat-online.php'],
            ]
        ]
    ],
    'notifications' => [
        ['label' => 'Notifications', 'icon' => 'fa-bell', 'dropdown' => true, 'id' => 'notifications-dropdown',
            'items' => [
                ['label' => 'View Notifications', 'icon' => 'fa-list', 'url' => 'notifications.php'],
                ['label' => 'Mark as Read', 'icon' => 'fa-check-double', 'url' => 'notifications-mark-read.php'],
                ['label' => 'Delete Notification', 'icon' => 'fa-trash', 'url' => 'notifications-delete.php'],
            ]
        ]
    ],
    'reports' => [
        ['label' => 'Reports', 'icon' => 'fa-file-alt', 'dropdown' => true, 'id' => 'reports-dropdown',
            'items' => [
                ['label' => 'Ward Report', 'icon' => 'fa-file-pdf', 'url' => 'reports-ward.php'],
                ['label' => 'Polling Unit Report', 'icon' => 'fa-file-alt', 'url' => 'reports-pu.php'],
                ['label' => 'Agent Report', 'icon' => 'fa-file-alt', 'url' => 'reports-agent.php'],
                ['label' => 'Incident Report', 'icon' => 'fa-file-alt', 'url' => 'reports-incident.php'],
                ['label' => 'Export PDF', 'icon' => 'fa-file-pdf', 'url' => 'export-pdf.php'],
                ['label' => 'Export Excel', 'icon' => 'fa-file-excel', 'url' => 'export-excel.php'],
            ]
        ]
    ],
    'profile' => [
        ['label' => 'Profile', 'icon' => 'fa-user', 'dropdown' => true, 'id' => 'profile-dropdown',
            'items' => [
                ['label' => 'View Profile', 'icon' => 'fa-id-card', 'url' => 'profile.php'],
                ['label' => 'Update Information', 'icon' => 'fa-edit', 'url' => 'profile-edit.php'],
                ['label' => 'Change Password', 'icon' => 'fa-key', 'url' => 'change-password.php'],
                ['label' => 'Logout', 'icon' => 'fa-sign-out-alt', 'url' => '../../auth/logout.php'],
            ]
        ]
    ]
];

// ============================================================
// 7. PU AGENT MENU
// ============================================================
$role_menus['pu_agent'] = [
    'main' => [
        ['label' => 'Dashboard', 'icon' => 'fa-th-large', 'url' => 'index.php', 'active' => 'dashboard'],
        ['label' => 'My Polling Unit', 'icon' => 'fa-flag-checkered', 'url' => 'my-pu.php'],
    ],
    'results' => [
        ['label' => 'Submit Results', 'icon' => 'fa-upload', 'dropdown' => true, 'id' => 'submit-dropdown',
            'items' => [
                ['label' => 'Submit EC8A', 'icon' => 'fa-file-alt', 'url' => 'submit-ec8a.php'],
                ['label' => 'View My Results', 'icon' => 'fa-list', 'url' => 'my-results.php'],
                ['label' => 'Result History', 'icon' => 'fa-history', 'url' => 'result-history.php'],
            ]
        ]
    ],
    'communications' => [
        ['label' => 'Report Incident', 'icon' => 'fa-exclamation-triangle', 'url' => 'report-incident.php'],
        ['label' => 'Broadcast', 'icon' => 'fa-bullhorn', 'url' => 'broadcasts.php'],
    ],
    'profile' => [
        ['label' => 'My Profile', 'icon' => 'fa-user', 'url' => 'profile.php'],
        ['label' => 'Check-in', 'icon' => 'fa-sign-in-alt', 'url' => 'checkin.php'],
    ]
];

// ============================================================
// 8. PARTY AGENT MENU
// ============================================================
$role_menus['party_agent'] = [
    'main' => [
        ['label' => 'Dashboard', 'icon' => 'fa-th-large', 'url' => 'index.php', 'active' => 'dashboard'],
    ],
    'results' => [
        ['label' => 'Monitor Results', 'icon' => 'fa-chart-line', 'url' => 'monitor-results.php'],
        ['label' => 'View Party Results', 'icon' => 'fa-flag', 'url' => 'party-results.php'],
    ],
    'communications' => [
        ['label' => 'Report Incident', 'icon' => 'fa-exclamation-triangle', 'url' => 'report-incident.php'],
        ['label' => 'Broadcast', 'icon' => 'fa-bullhorn', 'url' => 'broadcasts.php'],
    ]
];

// ============================================================
// 9. OBSERVER MENU
// ============================================================
$role_menus['observer'] = [
    'main' => [
        ['label' => 'Dashboard', 'icon' => 'fa-th-large', 'url' => 'index.php', 'active' => 'dashboard'],
    ],
    'results' => [
        ['label' => 'View Results', 'icon' => 'fa-chart-bar', 'url' => 'view-results.php'],
    ],
    'communications' => [
        ['label' => 'Report Incident', 'icon' => 'fa-exclamation-triangle', 'url' => 'report-incident.php'],
    ]
];

// ============================================================
// 10. SITUATION ROOM MENU
// ============================================================
$role_menus['situation_room'] = [
    'main' => [
        ['label' => 'Dashboard', 'icon' => 'fa-th-large', 'url' => 'index.php', 'active' => 'dashboard'],
        ['label' => 'Live Monitoring', 'icon' => 'fa-tv', 'url' => 'live-monitoring.php', 'badge' => 'Live'],
    ],
    'results' => [
        ['label' => 'All Results', 'icon' => 'fa-chart-bar', 'url' => 'all-results.php'],
        ['label' => 'Result Dashboard', 'icon' => 'fa-chart-pie', 'url' => 'result-dashboard.php'],
    ],
    'incidents' => [
        ['label' => 'Manage Incidents', 'icon' => 'fa-exclamation-triangle', 'dropdown' => true, 'id' => 'incident-dropdown',
            'items' => [
                ['label' => 'View Incidents', 'icon' => 'fa-list', 'url' => 'incidents.php'],
                ['label' => 'Assign Incident', 'icon' => 'fa-user-plus', 'url' => 'incident-assign.php'],
                ['label' => 'Escalate Incident', 'icon' => 'fa-arrow-up', 'url' => 'incident-escalate.php'],
                ['label' => 'Resolve Incident', 'icon' => 'fa-check-circle', 'url' => 'incident-resolve.php'],
                ['label' => 'Incident Dashboard', 'icon' => 'fa-chart-pie', 'url' => 'incident-dashboard.php'],
            ]
        ]
    ],
    'communications' => [
        ['label' => 'Broadcast', 'icon' => 'fa-bullhorn', 'url' => 'broadcasts.php'],
    ],
    'reports' => [
        ['label' => 'Reports', 'icon' => 'fa-file-alt', 'dropdown' => true, 'id' => 'reports-dropdown',
            'items' => [
                ['label' => 'Situation Report', 'icon' => 'fa-file-pdf', 'url' => 'reports-situation.php'],
                ['label' => 'Incident Report', 'icon' => 'fa-file-alt', 'url' => 'reports-incident.php'],
                ['label' => 'Election Report', 'icon' => 'fa-file-alt', 'url' => 'reports-election.php'],
            ]
        ]
    ]
];

// ============================================================
// 11. FINANCE OFFICER MENU
// ============================================================
$role_menus['finance_officer'] = [
    'main' => [
        ['label' => 'Dashboard', 'icon' => 'fa-th-large', 'url' => 'index.php', 'active' => 'dashboard'],
    ],
    'financial' => [
        ['label' => 'Budgets', 'icon' => 'fa-wallet', 'dropdown' => true, 'id' => 'budget-dropdown',
            'items' => [
                ['label' => 'View Budgets', 'icon' => 'fa-list', 'url' => 'budgets.php'],
                ['label' => 'Create Budget', 'icon' => 'fa-plus', 'url' => 'budgets-create.php'],
                ['label' => 'Budget Report', 'icon' => 'fa-file-alt', 'url' => 'budgets-report.php'],
            ]
        ],
        ['label' => 'Expenses', 'icon' => 'fa-money-bill-wave', 'dropdown' => true, 'id' => 'expense-dropdown',
            'items' => [
                ['label' => 'View Expenses', 'icon' => 'fa-list', 'url' => 'expenses.php'],
                ['label' => 'Create Expense', 'icon' => 'fa-plus', 'url' => 'expenses-create.php'],
                ['label' => 'Expense Report', 'icon' => 'fa-file-alt', 'url' => 'expenses-report.php'],
            ]
        ],
        ['label' => 'Agent Payments', 'icon' => 'fa-user-pay', 'dropdown' => true, 'id' => 'payment-dropdown',
            'items' => [
                ['label' => 'View Payments', 'icon' => 'fa-list', 'url' => 'agent-payments.php'],
                ['label' => 'Create Payment', 'icon' => 'fa-plus', 'url' => 'agent-payments-create.php'],
                ['label' => 'Payment Report', 'icon' => 'fa-file-alt', 'url' => 'agent-payments-report.php'],
            ]
        ]
    ],
    'reports' => [
        ['label' => 'Financial Reports', 'icon' => 'fa-file-invoice-dollar', 'dropdown' => true, 'id' => 'finance-reports',
            'items' => [
                ['label' => 'Financial Summary', 'icon' => 'fa-file-pdf', 'url' => 'reports-financial-summary.php'],
                ['label' => 'Budget vs Actual', 'icon' => 'fa-chart-bar', 'url' => 'reports-budget-vs-actual.php'],
                ['label' => 'Payment History', 'icon' => 'fa-history', 'url' => 'reports-payment-history.php'],
            ]
        ]
    ]
];

// ============================================================
// 12. CITIZEN MENU
// ============================================================
$role_menus['citizen'] = [
    'main' => [
        ['label' => 'Dashboard', 'icon' => 'fa-th-large', 'url' => 'index.php', 'active' => 'dashboard'],
    ],
    'results' => [
        ['label' => 'View Public Results', 'icon' => 'fa-chart-bar', 'url' => 'public-results.php'],
    ],
    'communications' => [
        ['label' => 'Report Incident', 'icon' => 'fa-exclamation-triangle', 'url' => 'report-incident.php'],
    ]
];

// ============================================================
// 13. VOLUNTEER MENU
// ============================================================
$role_menus['volunteer'] = [
    'main' => [
        ['label' => 'Dashboard', 'icon' => 'fa-th-large', 'url' => 'index.php', 'active' => 'dashboard'],
        ['label' => 'My Tasks', 'icon' => 'fa-tasks', 'url' => 'my-tasks.php', 'badge' => 'New'],
    ],
    'tasks' => [
        ['label' => 'Tasks', 'icon' => 'fa-clipboard-list', 'dropdown' => true, 'id' => 'tasks-dropdown',
            'items' => [
                ['label' => 'View Tasks', 'icon' => 'fa-list', 'url' => 'tasks.php'],
                ['label' => 'Update Progress', 'icon' => 'fa-chart-line', 'url' => 'task-progress.php'],
                ['label' => 'Task History', 'icon' => 'fa-history', 'url' => 'task-history.php'],
            ]
        ]
    ],
    'reports' => [
        ['label' => 'Submit Report', 'icon' => 'fa-file-alt', 'url' => 'submit-report.php'],
        ['label' => 'Report History', 'icon' => 'fa-history', 'url' => 'report-history.php'],
    ],
    'communications' => [
        ['label' => 'Chat', 'icon' => 'fa-comment-dots', 'url' => 'chat.php'],
        ['label' => 'Broadcasts', 'icon' => 'fa-bullhorn', 'url' => 'broadcasts.php'],
        ['label' => 'Report Incident', 'icon' => 'fa-exclamation-triangle', 'url' => 'report-incident.php'],
    ]
];

// ============================================================
// 14. CLIENT ADMINISTRATOR MENU (Full Access)
// ============================================================
$role_menus['client_admin'] = [
    'main' => [
        ['label' => 'Dashboard', 'icon' => 'fa-th-large', 'url' => 'index.php', 'active' => 'dashboard'],
        ['label' => 'Organization', 'icon' => 'fa-building', 'url' => 'organization.php'],
    ],
    'users' => [
        ['label' => 'Users', 'icon' => 'fa-users', 'dropdown' => true, 'id' => 'users-dropdown',
            'items' => [
                ['label' => 'All Users', 'icon' => 'fa-list', 'url' => 'users.php'],
                ['label' => 'Create User', 'icon' => 'fa-user-plus', 'url' => 'users-create.php'],
                ['label' => 'User Roles', 'icon' => 'fa-user-tag', 'url' => 'user-roles.php'],
                ['label' => 'User Permissions', 'icon' => 'fa-shield-alt', 'url' => 'user-permissions.php'],
            ]
        ],
        ['label' => 'Agents', 'icon' => 'fa-user-tie', 'dropdown' => true, 'id' => 'agents-dropdown',
            'items' => [
                ['label' => 'All Agents', 'icon' => 'fa-list', 'url' => 'agents.php'],
                ['label' => 'Create Agent', 'icon' => 'fa-user-plus', 'url' => 'agents-create.php'],
                ['label' => 'Agent Assignments', 'icon' => 'fa-clipboard-list', 'url' => 'agent-assignments.php'],
                ['label' => 'Agent Payments', 'icon' => 'fa-money-bill-wave', 'url' => 'agent-payments.php'],
            ]
        ]
    ],
    'elections' => [
        ['label' => 'Elections', 'icon' => 'fa-vote-yea', 'dropdown' => true, 'id' => 'elections-dropdown',
            'items' => [
                ['label' => 'All Elections', 'icon' => 'fa-list', 'url' => 'elections.php'],
                ['label' => 'Create Election', 'icon' => 'fa-plus', 'url' => 'elections-create.php'],
                ['label' => 'Election Templates', 'icon' => 'fa-copy', 'url' => 'elections-templates.php'],
                ['label' => 'Live Results', 'icon' => 'fa-chart-line', 'url' => 'live-results.php', 'badge' => 'Live'],
            ]
        ]
    ],
    'structure' => [
        ['label' => 'Structure', 'icon' => 'fa-sitemap', 'dropdown' => true, 'id' => 'structure-dropdown',
            'items' => [
                ['label' => 'States', 'icon' => 'fa-flag', 'url' => 'states.php'],
                ['label' => 'LGAs', 'icon' => 'fa-map-marker-alt', 'url' => 'lgas.php'],
                ['label' => 'Wards', 'icon' => 'fa-layer-group', 'url' => 'wards.php'],
                ['label' => 'Polling Units', 'icon' => 'fa-flag-checkered', 'url' => 'polling-units.php'],
                ['label' => 'Senatorial Districts', 'icon' => 'fa-users', 'url' => 'senatorial-districts.php'],
                ['label' => 'Federal Constituencies', 'icon' => 'fa-building', 'url' => 'federal-constituencies.php'],
            ]
        ],
        ['label' => 'Candidates', 'icon' => 'fa-user-tie', 'url' => 'candidates.php'],
        ['label' => 'Parties', 'icon' => 'fa-flag', 'url' => 'parties.php'],
    ],
    'results' => [
        ['label' => 'Results', 'icon' => 'fa-chart-bar', 'dropdown' => true, 'id' => 'results-dropdown',
            'items' => [
                ['label' => 'EC8A (PU)', 'icon' => 'fa-flag-checkered', 'url' => 'results-ec8a.php'],
                ['label' => 'EC8B (Ward)', 'icon' => 'fa-layer-group', 'url' => 'results-ec8b.php'],
                ['label' => 'EC8C (LGA)', 'icon' => 'fa-map', 'url' => 'results-ec8c.php'],
                ['label' => 'EC8D (State)', 'icon' => 'fa-map-marked-alt', 'url' => 'results-ec8d.php'],
                ['label' => 'EC8E (National)', 'icon' => 'fa-flag', 'url' => 'results-ec8e.php'],
                ['label' => 'Result Verification', 'icon' => 'fa-check-double', 'url' => 'result-verification.php'],
            ]
        ]
    ],
    'communications' => [
        ['label' => 'Broadcast', 'icon' => 'fa-bullhorn', 'url' => 'broadcasts.php'],
        ['label' => 'Incidents', 'icon' => 'fa-exclamation-triangle', 'url' => 'incidents.php'],
    ],
    'financial' => [
        ['label' => 'Financial', 'icon' => 'fa-money-bill-wave', 'dropdown' => true, 'id' => 'financial-dropdown',
            'items' => [
                ['label' => 'Budgets', 'icon' => 'fa-wallet', 'url' => 'budgets.php'],
                ['label' => 'Expenses', 'icon' => 'fa-receipt', 'url' => 'expenses.php'],
                ['label' => 'Invoices', 'icon' => 'fa-file-invoice', 'url' => 'invoices.php'],
                ['label' => 'Subscriptions', 'icon' => 'fa-credit-card', 'url' => 'subscriptions.php'],
            ]
        ]
    ],
    'reports' => [
        ['label' => 'Reports', 'icon' => 'fa-file-alt', 'url' => 'reports.php'],
    ],
    'system' => [
        ['label' => 'Settings', 'icon' => 'fa-cog', 'url' => 'settings.php'],
        ['label' => 'Backups', 'icon' => 'fa-database', 'url' => 'backups.php'],
        ['label' => 'Audit Logs', 'icon' => 'fa-clipboard-list', 'url' => 'audit-logs.php'],
        ['label' => 'Security', 'icon' => 'fa-shield-alt', 'url' => 'security.php'],
    ]
];

// ============================================================
// MENU RENDER HELPERS
// ============================================================

$current_page = basename($_SERVER['PHP_SELF'], '.php');

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
    'system' => ['label' => 'System', 'icon' => 'fa-cog'],
    'users' => ['label' => 'Users', 'icon' => 'fa-users'],
    'profile' => ['label' => 'Profile', 'icon' => 'fa-user'],
    'personnel' => ['label' => 'Personnel', 'icon' => 'fa-users-cog'],
    'monitoring' => ['label' => 'Monitoring', 'icon' => 'fa-tv'],
    'exports' => ['label' => 'Export', 'icon' => 'fa-download'],
    'tasks' => ['label' => 'Tasks', 'icon' => 'fa-tasks'],
    'notifications' => ['label' => 'Notifications', 'icon' => 'fa-bell']
];

function getBadgeClass($badge) {
    $badgeMap = [
        'Live' => 'badge-live',
        'New' => 'badge-new',
        '⚠' => 'badge-warning',
        '🔴' => 'badge-danger',
        '📤' => 'badge-info',
        '🌐' => 'badge-primary',
        '24' => 'badge-primary',
        '36' => 'badge-primary',
        '12' => 'badge-primary'
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

// Get menu for current role
$menu = $role_menus[$role_level] ?? $role_menus['client_admin'];
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