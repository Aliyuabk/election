<?php
// ============================================================
// BASE LAYOUT - Optimized for All Dashboards
// ============================================================

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define app constants if not defined
if (!defined('APP_NAME')) {
    define('APP_NAME', 'Election Guru');
}

// Get user data from session
$user_name = SessionManager::get('user_name', 'Coordinator');
$user_email = SessionManager::get('user_email', 'coordinator@example.com');
$user_role = SessionManager::get('role_level', 'national');
$user_level = SessionManager::get('role_level', 'national');
$jurisdiction_id = SessionManager::get('jurisdiction_id', null);
$tenant_id = SessionManager::get('tenant_id', null);

// Role configuration for coordinators
$role_configs = [
    'national' => [
        'label' => 'National Coordinator',
        'icon' => 'fa-flag',
        'color' => '#0F4C81',
        'badge_color' => 'bg-blue-600',
        'features' => [
            'dashboard' => 'National Dashboard',
            'monitor_states' => 'Monitor States',
            'reports' => 'Reports',
            'broadcast' => 'Broadcast',
            'incident_monitoring' => 'Incident Monitoring',
            'result_verification' => 'Result Verification',
            'analytics' => 'Analytics'
        ]
    ],
    'state' => [
        'label' => 'State Coordinator',
        'icon' => 'fa-map',
        'color' => '#2563EB',
        'badge_color' => 'bg-blue-500',
        'features' => [
            'dashboard' => 'State Dashboard',
            'monitor_lgas' => 'Monitor LGAs',
            'manage_lga_coordinators' => 'Manage LGA Coordinators',
            'reports' => 'Reports',
            'broadcast' => 'Broadcast',
            'incident_management' => 'Incident Management',
            'result_verification' => 'Result Verification'
        ]
    ],
    'senatorial' => [
        'label' => 'Senatorial Coordinator',
        'icon' => 'fa-university',
        'color' => '#7C3AED',
        'badge_color' => 'bg-purple-600',
        'features' => [
            'dashboard' => 'Dashboard',
            'monitor_senatorial' => 'Monitor Senatorial District',
            'reports' => 'Reports',
            'broadcast' => 'Broadcast',
            'analytics' => 'Analytics'
        ]
    ],
    'federal_constituency' => [
        'label' => 'Federal Constituency Coordinator',
        'icon' => 'fa-building',
        'color' => '#059669',
        'badge_color' => 'bg-green-600',
        'features' => [
            'dashboard' => 'Dashboard',
            'monitor_constituency' => 'Monitor Constituency',
            'reports' => 'Reports',
            'broadcast' => 'Broadcast',
            'result_verification' => 'Result Verification'
        ]
    ],
    'lga' => [
        'label' => 'LGA Coordinator',
        'icon' => 'fa-map-marker-alt',
        'color' => '#D97706',
        'badge_color' => 'bg-yellow-600',
        'features' => [
            'dashboard' => 'Dashboard',
            'manage_wards' => 'Manage Wards',
            'reports' => 'Reports',
            'approve_results' => 'Approve Results',
            'broadcast' => 'Broadcast',
            'incident_monitoring' => 'Incident Monitoring'
        ]
    ],
    'ward' => [
        'label' => 'Ward Coordinator',
        'icon' => 'fa-layer-group',
        'color' => '#DC2626',
        'badge_color' => 'bg-red-600',
        'features' => [
            'dashboard' => 'Dashboard',
            'manage_pu_agents' => 'Manage Polling Unit Agents',
            'upload_ec8b' => 'Upload EC8B',
            'reports' => 'Reports',
            'broadcast' => 'Broadcast',
            'incident_management' => 'Incident Management'
        ]
    ],
    'pu_agent' => [
        'label' => 'Polling Unit Agent',
        'icon' => 'fa-flag-checkered',
        'color' => '#8B5CF6',
        'badge_color' => 'bg-purple-500',
        'features' => [
            'dashboard' => 'Dashboard',
            'submit_results' => 'Submit Results',
            'report_incident' => 'Report Incident',
            'broadcast' => 'Broadcast'
        ]
    ],
    'client_admin' => [
        'label' => 'Client Administrator',
        'icon' => 'fa-user-cog',
        'color' => '#0F4C81',
        'badge_color' => 'bg-blue-700',
        'features' => [
            'dashboard' => 'Dashboard',
            'organization' => 'Organization',
            'users' => 'Users',
            'elections' => 'Elections',
            'structure' => 'Structure',
            'agents' => 'Agents',
            'candidates' => 'Candidates',
            'parties' => 'Parties',
            'results' => 'Results',
            'broadcast' => 'Broadcast',
            'incidents' => 'Incidents',
            'financial' => 'Financial',
            'reports' => 'Reports',
            'settings' => 'Settings'
        ]
    ]
];

// Get current role config
$current_role = isset($role_configs[$user_level]) ? $user_level : 'national';
$role_config = $role_configs[$current_role];
$page_title = isset($page_title) ? $page_title : 'Dashboard';

// Set primary color for CSS variables
$primary_color = $role_config['color'];
$primary_rgb = implode(', ', sscanf($primary_color, '#%02x%02x%02x'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title><?php echo htmlspecialchars($page_title); ?> - <?php echo APP_NAME; ?></title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz@14..32&family=Poppins:wght@600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../../assets/favicon.ico" />
    
    <!-- ============================================================
    MASTER CSS - All styles consolidated
    ============================================================ -->
    <style>
        /* ============================================================
           ROOT VARIABLES
           ============================================================ */
        :root {
            --primary: <?php echo $primary_color; ?>;
            --primary-light: <?php echo $primary_color; ?>44;
            --primary-dark: <?php echo $primary_color; ?>cc;
            --primary-rgb: <?php echo $primary_rgb; ?>;
            --secondary: #10B981;
            --secondary-light: #34D399;
            --danger: #EF4444;
            --warning: #F59E0B;
            --info: #3B82F6;
            --gray-50: #F8FAFC;
            --gray-100: #F1F5F9;
            --gray-200: #E2E8F0;
            --gray-300: #CBD5E1;
            --gray-400: #94A3B8;
            --gray-500: #64748B;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1E293B;
            --gray-900: #0F172A;
            --radius: 14px;
            --shadow: 0 4px 20px rgba(0,0,0,0.05);
            --shadow-hover: 0 8px 30px rgba(0,0,0,0.10);
            --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            --header-height: 64px;
            --sidebar-width: 280px;
            --sidebar-width-collapsed: 80px;
        }

        /* ============================================================
           BASE RESET & TYPOGRAPHY
           ============================================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { font-size: 16px; }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray-50);
            color: var(--gray-900);
            line-height: 1.5;
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--gray-100); }
        ::-webkit-scrollbar-thumb { background: var(--gray-300); border-radius: 8px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--gray-400); }

        /* Utility */
        .text-truncate {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* ============================================================
           PRELOADER
           ============================================================ */
        #preloader {
            position: fixed;
            inset: 0;
            background: #F8FAFC;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.6s ease, visibility 0.6s ease;
        }
        #preloader.hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }
        .preloader-spinner {
            width: 48px;
            height: 48px;
            border: 4px solid #E2E8F0;
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .preloader-text {
            margin-top: 16px;
            font-weight: 600;
            color: var(--primary);
            font-size: 1rem;
            letter-spacing: 0.05em;
        }
        .preloader-dots::after {
            content: '...';
            animation: dots 1.2s steps(4, end) infinite;
        }
        @keyframes dots {
            0% { content: ''; }
            25% { content: '.'; }
            50% { content: '..'; }
            75% { content: '...'; }
        }

        /* ============================================================
           SIDEBAR - Professional Dark Theme
           ============================================================ */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #0F172A 0%, #1E293B 100%);
            color: #94A3B8;
            height: 100vh;
            position: sticky;
            top: 0;
            overflow-y: auto;
            padding: 0;
            flex-shrink: 0;
            transition: transform 0.3s ease, width 0.3s ease;
            z-index: 100;
            display: flex;
            flex-direction: column;
            border-right: 1px solid rgba(255, 255, 255, 0.05);
        }
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-track { background: transparent; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.15); border-radius: 4px; }

        /* Brand */
        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 20px 20px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }
        .sidebar-brand .brand-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
            flex-shrink: 0;
        }
        .sidebar-brand .brand-text span {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.1rem;
            color: white;
            display: block;
            line-height: 1.2;
        }
        .sidebar-brand .brand-text small {
            font-size: 0.6rem;
            color: #64748B;
            font-weight: 400;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        /* Jurisdiction */
        .sidebar-jurisdiction {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 20px;
            margin: 12px 16px;
            background: rgba(255, 255, 255, 0.04);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.06);
        }
        .sidebar-jurisdiction .jurisdiction-icon {
            width: 32px;
            height: 32px;
            background: rgba(37, 99, 235, 0.15);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #60A5FA;
            font-size: 0.9rem;
            flex-shrink: 0;
        }
        .sidebar-jurisdiction .jurisdiction-info {
            flex: 1;
            min-width: 0;
        }
        .sidebar-jurisdiction .jurisdiction-label {
            display: block;
            font-size: 0.55rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748B;
            font-weight: 600;
        }
        .sidebar-jurisdiction .jurisdiction-name {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #E2E8F0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Navigation */
        .sidebar-nav {
            flex: 1;
            padding: 8px 12px 20px;
            overflow-y: auto;
        }
        .nav-section { margin-bottom: 6px; }
        .nav-section-header {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px 4px;
            font-size: 0.6rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #475569;
            font-weight: 600;
        }
        .nav-section-header i {
            font-size: 0.6rem;
            color: #334155;
        }

        /* Nav Items */
        .nav-item { margin-bottom: 1px; }
        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 14px;
            border-radius: 10px;
            color: #94A3B8;
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 500;
            transition: all 0.2s ease;
            position: relative;
            cursor: pointer;
        }
        .nav-link:hover {
            background: rgba(255, 255, 255, 0.06);
            color: #E2E8F0;
        }
        .nav-link:hover i { color: #60A5FA; }
        .nav-link i {
            width: 20px;
            text-align: center;
            font-size: 0.95rem;
            color: #475569;
            transition: color 0.2s ease;
            flex-shrink: 0;
        }
        .nav-link .nav-label { flex: 1; }
        .nav-link .chevron {
            font-size: 0.6rem;
            transition: transform 0.3s ease;
            margin-left: auto;
            color: #475569;
        }
        .nav-link .chevron.open { transform: rotate(180deg); }
        .nav-link.active {
            background: rgba(37, 99, 235, 0.15);
            color: #60A5FA;
        }
        .nav-link.active i { color: #60A5FA; }
        .nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 24px;
            background: var(--primary);
            border-radius: 0 4px 4px 0;
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 8px;
            height: 20px;
            border-radius: 6px;
            font-size: 0.6rem;
            font-weight: 600;
            line-height: 20px;
            flex-shrink: 0;
        }
        .badge-default { background: rgba(255, 255, 255, 0.08); color: #94A3B8; }
        .badge-live {
            background: rgba(239, 68, 68, 0.15);
            color: #F87171;
            animation: pulse-badge 1.5s ease-in-out infinite;
        }
        .badge-new { background: rgba(16, 185, 129, 0.15); color: #34D399; }
        .badge-warning { background: rgba(245, 158, 11, 0.15); color: #FBBF24; }
        .badge-danger { background: rgba(239, 68, 68, 0.15); color: #F87171; }
        .badge-primary { background: rgba(37, 99, 235, 0.15); color: #60A5FA; }
        .badge-info { background: rgba(59, 130, 246, 0.15); color: #60A5FA; }

        @keyframes pulse-badge {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* Dropdown */
        .nav-dropdown .dropdown-menu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease, opacity 0.3s ease, margin 0.3s ease;
            opacity: 0;
            margin: 0;
            padding-left: 12px;
        }
        .nav-dropdown .dropdown-menu.open {
            max-height: 500px;
            opacity: 1;
            margin: 4px 0;
        }
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 14px 6px 44px;
            border-radius: 8px;
            color: #94A3B8;
            text-decoration: none;
            font-size: 0.78rem;
            font-weight: 400;
            transition: all 0.2s ease;
        }
        .dropdown-item:hover {
            background: rgba(255, 255, 255, 0.05);
            color: #E2E8F0;
        }
        .dropdown-item i {
            width: 16px;
            text-align: center;
            font-size: 0.7rem;
            color: #475569;
            flex-shrink: 0;
        }
        .dropdown-item:hover i { color: #60A5FA; }
        .dropdown-item.active { color: #60A5FA; }
        .dropdown-item.active i { color: #60A5FA; }
        .dropdown-item .badge { margin-left: auto; }

        /* Sidebar Footer */
        .sidebar-footer {
            padding: 16px 16px 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            margin-top: auto;
        }
        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.03);
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.85rem;
            flex-shrink: 0;
            position: relative;
        }
        .user-avatar .online-status {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 10px;
            height: 10px;
            background: #10B981;
            border-radius: 50%;
            border: 2px solid #1E293B;
        }
        .user-details { flex: 1; min-width: 0; }
        .user-details .user-name {
            font-weight: 600;
            font-size: 0.82rem;
            color: #E2E8F0;
            line-height: 1.2;
        }
        .user-details .user-role {
            font-size: 0.65rem;
            color: #64748B;
            line-height: 1.2;
        }
        .sidebar-actions { margin-top: 8px; }
        .logout-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 10px;
            background: rgba(239, 68, 68, 0.1);
            color: #F87171;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.8rem;
            transition: all 0.2s ease;
            width: 100%;
        }
        .logout-btn:hover {
            background: rgba(239, 68, 68, 0.2);
            color: #FCA5A5;
        }
        .logout-btn i { font-size: 0.9rem; }

        /* ============================================================
           MAIN CONTENT
           ============================================================ */
        .main-content {
            flex: 1;
            min-width: 0;
            padding-top: var(--header-height);
            position: relative;
        }
        .main-content-inner {
            padding: 16px 20px 24px;
        }

        /* ============================================================
           HEADER
           ============================================================ */
        .dashboard-header {
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            height: var(--header-height);
            background: white;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
            z-index: 90;
            transition: left 0.3s ease;
        }
        .dashboard-header .header-left h1 {
            font-size: 1.15rem;
            font-weight: 700;
        }
        .dashboard-header .header-left h1 small {
            font-size: 0.7rem;
            font-weight: 400;
            color: var(--gray-500);
            display: block;
        }
        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Search */
        .search-wrapper { position: relative; }
        .search-box {
            display: flex;
            align-items: center;
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            padding: 4px 12px;
            gap: 8px;
            transition: var(--transition);
            min-width: 160px;
        }
        .search-box:focus-within {
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
        }
        .search-box i {
            color: var(--gray-400);
            font-size: 0.8rem;
        }
        .search-box input {
            border: none;
            outline: none;
            background: transparent;
            font-family: 'Inter', sans-serif;
            font-size: 0.8rem;
            width: 100%;
            color: var(--gray-700);
        }
        .search-box input::placeholder {
            color: var(--gray-400);
            font-size: 0.75rem;
        }
        .search-results {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.12);
            border: 1px solid var(--gray-200);
            max-height: 350px;
            overflow-y: auto;
            display: none;
            z-index: 50;
        }
        .search-results.active { display: block; }
        .search-results .result-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 14px;
            text-decoration: none;
            color: var(--gray-700);
            transition: var(--transition);
            border-bottom: 1px solid var(--gray-50);
            font-size: 0.82rem;
        }
        .search-results .result-item:hover { background: var(--gray-50); }
        .search-results .result-item .result-type {
            font-size: 0.55rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: 1px 8px;
            border-radius: 10px;
            background: var(--gray-100);
            color: var(--gray-500);
            flex-shrink: 0;
        }

        /* Notifications */
        .notification-btn {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: 1px solid var(--gray-200);
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-600);
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            text-decoration: none;
        }
        .notification-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        .notification-btn .badge {
            position: absolute;
            top: -3px;
            right: -3px;
            background: var(--danger);
            color: white;
            font-size: 0.5rem;
            font-weight: 700;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Profile */
        .profile-dropdown { position: relative; }
        .profile-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 2px solid var(--gray-200);
            background: var(--primary);
            color: white;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .profile-btn:hover {
            border-color: var(--primary);
            transform: scale(1.05);
        }
        .profile-menu {
            position: absolute;
            right: 0;
            top: calc(100% + 6px);
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.12);
            border: 1px solid var(--gray-200);
            min-width: 200px;
            padding: 6px;
            display: none;
            z-index: 50;
        }
        .profile-menu.active { display: block; }
        .profile-menu .profile-header {
            padding: 10px 14px;
            border-bottom: 1px solid var(--gray-100);
            margin-bottom: 4px;
        }
        .profile-menu .profile-header .name {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--gray-800);
        }
        .profile-menu .profile-header .email {
            font-size: 0.75rem;
            color: var(--gray-500);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .profile-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 14px;
            border-radius: 8px;
            color: var(--gray-600);
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 500;
            transition: var(--transition);
        }
        .profile-menu a:hover {
            background: var(--gray-50);
            color: var(--primary);
        }
        .profile-menu a i {
            width: 16px;
            color: var(--gray-400);
            font-size: 0.9rem;
        }
        .profile-menu .divider {
            height: 1px;
            background: var(--gray-100);
            margin: 4px 0;
        }
        .profile-menu .logout-link { color: var(--danger); }
        .profile-menu .logout-link i { color: var(--danger); }
        .profile-menu .logout-link:hover { background: #FEF2F2; }

        /* Sidebar Toggle */
        .sidebar-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.2rem;
            color: var(--gray-600);
            cursor: pointer;
            padding: 4px;
        }

        /* Overlay */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.3);
            z-index: 150;
        }
        .sidebar-overlay.active { display: block; }

        /* ============================================================
           DASHBOARD COMPONENTS
           ============================================================ */

        /* Welcome Section */
        .welcome-section { margin-bottom: 20px; }
        .welcome-section h2 {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--gray-800);
        }
        .welcome-section p {
            color: var(--gray-500);
            font-size: 0.9rem;
            margin-top: 2px;
        }
        .welcome-section .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 4px;
            font-size: 0.8rem;
            color: var(--gray-500);
        }
        .welcome-section .breadcrumb span {
            background: var(--gray-100);
            padding: 2px 12px;
            border-radius: 20px;
            font-weight: 500;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            border-radius: var(--radius);
            padding: 16px 18px;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
            transition: var(--transition);
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }
        .stat-card .stat-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        .stat-card .stat-icon.blue { background: #EFF6FF; color: #3B82F6; }
        .stat-card .stat-icon.green { background: #ECFDF5; color: #10B981; }
        .stat-card .stat-icon.purple { background: #F5F3FF; color: #8B5CF6; }
        .stat-card .stat-icon.yellow { background: #FFFBEB; color: #F59E0B; }
        .stat-card .stat-icon.red { background: #FEF2F2; color: #EF4444; }
        .stat-card .stat-icon.orange { background: #FFF7ED; color: #F97316; }
        .stat-card .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-800);
        }
        .stat-card .stat-label {
            font-size: 0.75rem;
            color: var(--gray-500);
            font-weight: 500;
        }
        .stat-card .stat-change {
            font-size: 0.7rem;
            margin-top: 6px;
            font-weight: 500;
        }
        .stat-card .stat-change.up { color: var(--secondary); }
        .stat-card .stat-change.down { color: var(--danger); }

        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 20px;
        }
        .chart-card {
            background: white;
            border-radius: var(--radius);
            padding: 16px;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
        }
        .chart-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .chart-card .card-header h3 {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--gray-700);
            margin: 0;
        }
        .chart-card .card-header .period {
            font-size: 0.7rem;
            color: var(--gray-400);
            font-weight: 500;
        }
        .chart-container { height: 220px; position: relative; }

        /* Activities Grid */
        .activities-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }
        .activity-card {
            background: white;
            border-radius: var(--radius);
            padding: 16px;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
        }
        .activity-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .activity-card .card-header h3 {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--gray-700);
            margin: 0;
        }
        .activity-card .card-header a {
            font-size: 0.7rem;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
        .activity-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 0;
            border-bottom: 1px solid var(--gray-100);
        }
        .activity-item:last-child { border-bottom: none; }
        .activity-item .activity-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            flex-shrink: 0;
        }
        .activity-item .activity-icon.system { background: #F1F5F9; color: #64748B; }
        .activity-item .activity-icon.login { background: #EFF6FF; color: #3B82F6; }
        .activity-item .activity-icon.tenant { background: #F5F3FF; color: #8B5CF6; }
        .activity-item .activity-icon.user { background: #ECFDF5; color: #10B981; }
        .activity-item .activity-icon.backup { background: #FFFBEB; color: #F59E0B; }
        .activity-item .activity-content { flex: 1; min-width: 0; }
        .activity-item .activity-content .title {
            font-weight: 500;
            font-size: 0.8rem;
            color: var(--gray-700);
        }
        .activity-item .activity-content .desc {
            font-size: 0.7rem;
            color: var(--gray-500);
        }
        .activity-item .activity-content .time {
            font-size: 0.6rem;
            color: var(--gray-400);
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-top: 4px;
        }
        .quick-action-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            background: var(--gray-50);
            border-radius: 10px;
            color: var(--gray-700);
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
            transition: var(--transition);
            border: 1px solid var(--gray-200);
        }
        .quick-action-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.2);
        }
        .quick-action-btn i {
            color: var(--primary);
            transition: var(--transition);
        }
        .quick-action-btn:hover i { color: white; }

        /* Incident Summary */
        .incident-summary {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
            padding: 4px 0;
        }
        .incident-stat {
            text-align: center;
            padding: 8px;
            background: var(--gray-50);
            border-radius: 8px;
        }
        .incident-stat .label {
            display: block;
            font-size: 0.65rem;
            color: var(--gray-500);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .incident-stat .value {
            display: block;
            font-size: 1.2rem;
            font-weight: 700;
            margin-top: 2px;
        }

        /* Quick Stats */
        .quick-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            padding: 4px 0;
        }
        .quick-stat-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 12px;
            background: var(--gray-50);
            border-radius: 8px;
            font-size: 0.8rem;
        }
        .quick-stat-item .label { color: var(--gray-500); font-weight: 500; }
        .quick-stat-item .value { font-weight: 700; color: var(--gray-800); }

        /* Subscription Stats */
        .subscription-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 4px 0;
        }
        .subscription-stats .sub-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            color: var(--gray-600);
            background: var(--gray-50);
            padding: 4px 12px;
            border-radius: 20px;
        }
        .subscription-stats .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }
        .subscription-stats .dot.enterprise { background: #7C3AED; }
        .subscription-stats .dot.premium { background: #D97706; }
        .subscription-stats .dot.standard { background: #3B82F6; }
        .subscription-stats .dot.basic { background: #10B981; }
        .subscription-stats .dot.free { background: #94A3B8; }

        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 1024px) {
            .charts-grid { grid-template-columns: 1fr; }
            .activities-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                transform: translateX(-100%);
                width: 300px;
                z-index: 200;
                height: 100vh;
                top: 0;
                box-shadow: 0 0 40px rgba(0,0,0,0.3);
            }
            .sidebar.open { transform: translateX(0); }
            .sidebar-overlay.active { display: block; }
            .sidebar-toggle { display: block; }
            
            .dashboard-header {
                left: 0;
                padding: 0 14px;
                height: 56px;
            }
            .main-content { padding-top: 56px; }
            .main-content-inner { padding: 12px 14px 20px; }
            .dashboard-header .header-left h1 { font-size: 1rem; }
            .dashboard-header .header-left h1 small { font-size: 0.6rem; }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
            .stat-card { padding: 12px 14px; }
            .stat-card .stat-number { font-size: 1.2rem; }
            
            .quick-actions { grid-template-columns: 1fr 1fr; }
            .chart-container { height: 180px; }
            .incident-summary { grid-template-columns: 1fr 1fr 1fr; }
            
            .search-box {
                min-width: 120px;
                padding: 3px 10px;
            }
            .search-box input { font-size: 0.75rem; }
            .profile-btn {
                width: 32px;
                height: 32px;
                font-size: 0.7rem;
            }
            .notification-btn {
                width: 32px;
                height: 32px;
            }
        }

        @media (max-width: 480px) {
            .sidebar { width: 280px; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .quick-actions { grid-template-columns: 1fr; }
            .stat-card .stat-number { font-size: 1rem; }
            .stat-card .stat-label { font-size: 0.65rem; }
            
            .search-box {
                min-width: 80px;
                padding: 2px 8px;
            }
            .search-box input { width: 50px; }
            .profile-menu { right: -10px; }
            .dashboard-header {
                padding: 0 10px;
                height: 50px;
            }
            .main-content { padding-top: 50px; }
            .main-content-inner { padding: 10px 10px 16px; }
        }
        /* ============================================================
   PROFILE DROPDOWN - Enhanced Styles
   ============================================================ */

.profile-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    width: auto;
    padding: 4px 12px 4px 4px;
    border-radius: 50px;
    background: var(--gray-50);
    border: 1px solid var(--gray-200);
    color: var(--gray-700);
    font-weight: 600;
    font-size: 0.8rem;
    cursor: pointer;
    transition: var(--transition);
}

.profile-btn .profile-chevron {
    font-size: 0.6rem;
    transition: transform 0.3s ease;
    color: var(--gray-400);
}

.profile-btn .profile-chevron.open {
    transform: rotate(180deg);
}

.profile-btn:hover {
    border-color: var(--primary);
    background: var(--gray-100);
}

.profile-btn .user-initials {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 700;
}

/* Profile Menu - Enhanced */
.profile-menu {
    min-width: 260px;
    padding: 8px;
    border-radius: 14px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
    animation: profileFadeIn 0.25s ease;
}

@keyframes profileFadeIn {
    from {
        opacity: 0;
        transform: translateY(-12px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.profile-menu .profile-header {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 14px 16px;
    border-bottom: 1px solid var(--gray-100);
    margin-bottom: 4px;
}

.profile-menu .profile-avatar-large {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-light));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    font-weight: 700;
    flex-shrink: 0;
}

.profile-menu .profile-info {
    flex: 1;
    min-width: 0;
}

.profile-menu .profile-info .name {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--gray-800);
}

.profile-menu .profile-info .email {
    font-size: 0.75rem;
    color: var(--gray-500);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.profile-menu .profile-menu-items {
    padding: 4px 0;
}

.profile-menu .profile-menu-items a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 14px;
    border-radius: 8px;
    color: var(--gray-600);
    text-decoration: none;
    font-size: 0.82rem;
    font-weight: 500;
    transition: var(--transition);
}

.profile-menu .profile-menu-items a:hover {
    background: var(--gray-50);
    color: var(--primary);
}

.profile-menu .profile-menu-items a i {
    width: 18px;
    color: var(--gray-400);
    font-size: 0.9rem;
    text-align: center;
}

.profile-menu .profile-menu-items .divider {
    height: 1px;
    background: var(--gray-100);
    margin: 4px 12px;
}

.profile-menu .profile-menu-items .logout-link {
    color: var(--danger);
}

.profile-menu .profile-menu-items .logout-link i {
    color: var(--danger);
}

.profile-menu .profile-menu-items .logout-link:hover {
    background: #FEF2F2;
}

/* Responsive */
@media (max-width: 768px) {
    .profile-btn {
        padding: 2px 10px 2px 2px;
        font-size: 0.7rem;
    }
    
    .profile-btn .user-initials {
        width: 28px;
        height: 28px;
        font-size: 0.65rem;
    }
    
    .profile-menu {
        min-width: 220px;
        right: -10px;
    }
}

@media (max-width: 480px) {
    .profile-btn .profile-chevron {
        display: none;
    }
    
    .profile-menu {
        min-width: 200px;
        right: -15px;
    }
    
    .profile-menu .profile-avatar-large {
        width: 40px;
        height: 40px;
        font-size: 0.9rem;
    }
}
    </style>
    
    <?php if (isset($extra_styles)): ?>
    <style><?php echo $extra_styles; ?></style>
    <?php endif; ?>
</head>
<body>

<!-- ============================================================
PRELOADER
============================================================ -->
<!-- <div id="preloader">
    <div class="preloader-spinner"></div>
    <div class="preloader-text">Loading <span class="preloader-dots"></span></div>
</div> -->

<!-- ============================================================
SIDEBAR OVERLAY (mobile)
============================================================ -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>