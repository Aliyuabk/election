<?php
// ============================================================
// CITIZEN PORTAL - PUBLIC HEADER
// ============================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Election Monitoring Portal</title>
    
    <!-- Favicon -->
    <link rel="icon" href="<?php echo APP_URL; ?>/assets/images/favicon.ico" type="image/x-icon">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ============================================================
           CSS VARIABLES
           ============================================================ */
        :root {
            --primary: #0F4C81;
            --primary-dark: #0A3A62;
            --primary-light: #1A6DB5;
            --secondary: #10B981;
            --danger: #EF4444;
            --warning: #F59E0B;
            --gray-50: #F9FAFB;
            --gray-100: #F3F4F6;
            --gray-200: #E5E7EB;
            --gray-300: #D1D5DB;
            --gray-400: #9CA3AF;
            --gray-500: #6B7280;
            --gray-600: #4B5563;
            --gray-700: #374151;
            --gray-800: #1F2937;
            --gray-900: #111827;
            --shadow: 0 4px 20px rgba(0,0,0,0.06);
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.1);
            --radius: 12px;
            --transition: all 0.3s ease;
        }

        /* ============================================================
           RESET & BASE
           ============================================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #F8FAFC;
            color: var(--gray-800);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        a {
            color: var(--primary);
            text-decoration: none;
        }
        a:hover {
            color: var(--primary-dark);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            width: 100%;
        }

        /* ============================================================
           TOP BAR
           ============================================================ */
        .top-bar {
            background: var(--gray-900);
            color: white;
            padding: 6px 0;
            font-size: 0.75rem;
        }
        .top-bar .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        .top-bar a {
            color: rgba(255,255,255,0.7);
            transition: var(--transition);
        }
        .top-bar a:hover {
            color: white;
        }
        .top-bar .top-bar-links {
            display: flex;
            gap: 16px;
            align-items: center;
        }
        .top-bar .top-bar-links .sep {
            color: rgba(255,255,255,0.2);
        }

        /* ============================================================
           HEADER / NAVIGATION
           ============================================================ */
        .public-header {
            background: white;
            border-bottom: 1px solid var(--gray-200);
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        }
        .public-header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 12px;
            padding-bottom: 12px;
            gap: 16px;
            flex-wrap: wrap;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--primary);
            text-decoration: none;
            flex-shrink: 0;
        }
        .logo i {
            font-size: 1.4rem;
        }
        .logo .logo-text {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }
        .logo .logo-text .sub {
            font-size: 0.55rem;
            font-weight: 400;
            color: var(--gray-500);
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .nav-menu {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .nav-menu a {
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 500;
            color: var(--gray-600);
            transition: var(--transition);
            text-decoration: none;
        }
        .nav-menu a:hover {
            background: var(--gray-100);
            color: var(--gray-800);
        }
        .nav-menu a.active {
            background: var(--primary);
            color: white;
        }
        .nav-menu a.active:hover {
            background: var(--primary-dark);
        }
        .nav-menu a .badge {
            background: var(--danger);
            color: white;
            font-size: 0.55rem;
            padding: 1px 8px;
            border-radius: 10px;
            margin-left: 4px;
        }

        .nav-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--gray-700);
            cursor: pointer;
            padding: 4px 8px;
        }

        /* ============================================================
           MOBILE NAV
           ============================================================ */
        @media (max-width: 992px) {
            .nav-toggle {
                display: block;
            }
            .nav-menu {
                display: none;
                flex-direction: column;
                width: 100%;
                padding: 12px 0;
                border-top: 1px solid var(--gray-200);
            }
            .nav-menu.open {
                display: flex;
            }
            .nav-menu a {
                width: 100%;
                padding: 10px 14px;
            }
        }

        /* ============================================================
           MAIN CONTENT
           ============================================================ */
        .main-content {
            flex: 1;
            padding: 30px 0 20px 0;
        }

        /* ============================================================
           FOOTER
           ============================================================ */
        .public-footer {
            background: white;
            border-top: 1px solid var(--gray-200);
            padding: 40px 0 20px 0;
            margin-top: 40px;
        }
        .public-footer .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        .public-footer .footer-grid .footer-col h4 {
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 12px;
            color: var(--gray-800);
        }
        .public-footer .footer-grid .footer-col p,
        .public-footer .footer-grid .footer-col a {
            font-size: 0.82rem;
            color: var(--gray-500);
            line-height: 1.8;
        }
        .public-footer .footer-grid .footer-col a:hover {
            color: var(--primary);
        }
        .public-footer .footer-grid .footer-col .social-links {
            display: flex;
            gap: 12px;
            margin-top: 8px;
        }
        .public-footer .footer-grid .footer-col .social-links a {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--gray-100);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            color: var(--gray-600);
        }
        .public-footer .footer-grid .footer-col .social-links a:hover {
            background: var(--primary);
            color: white;
        }
        .public-footer .footer-bottom {
            border-top: 1px solid var(--gray-200);
            padding-top: 16px;
            text-align: center;
            font-size: 0.78rem;
            color: var(--gray-400);
        }
        .public-footer .footer-bottom .footer-links {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 8px;
        }
        .public-footer .footer-bottom .footer-links a {
            color: var(--gray-400);
            text-decoration: none;
            transition: var(--transition);
        }
        .public-footer .footer-bottom .footer-links a:hover {
            color: var(--primary);
        }

        @media (max-width: 768px) {
            .public-footer .footer-grid {
                grid-template-columns: 1fr 1fr;
                gap: 20px;
            }
            .top-bar .top-bar-links {
                flex-wrap: wrap;
                gap: 8px;
            }
        }
        @media (max-width: 480px) {
            .public-footer .footer-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ============================================================
           UTILITY CLASSES
           ============================================================ */
        .text-center { text-align: center; }
        .text-muted { color: var(--gray-500); }
        .mb-1 { margin-bottom: 8px; }
        .mb-2 { margin-bottom: 16px; }
        .mb-3 { margin-bottom: 24px; }
        .mt-1 { margin-top: 8px; }
        .mt-2 { margin-top: 16px; }
        .mt-3 { margin-top: 24px; }
        .d-flex { display: flex; }
        .gap-1 { gap: 8px; }
        .gap-2 { gap: 16px; }
        .flex-wrap { flex-wrap: wrap; }
        .align-center { align-items: center; }
        .justify-between { justify-content: space-between; }

        /* ============================================================
           BREADCRUMB
           ============================================================ */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.8rem;
            color: var(--gray-400);
            margin-bottom: 16px;
        }
        .breadcrumb a {
            color: var(--gray-500);
            text-decoration: none;
            transition: var(--transition);
        }
        .breadcrumb a:hover {
            color: var(--primary);
        }
        .breadcrumb .sep {
            color: var(--gray-300);
        }
        .breadcrumb .current {
            color: var(--gray-700);
            font-weight: 500;
        }

        /* ============================================================
           ALERT / NOTIFICATION
           ============================================================ */
        .alert {
            padding: 14px 18px;
            border-radius: var(--radius);
            margin-bottom: 16px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            border: 1px solid transparent;
        }
        .alert i {
            margin-top: 2px;
            font-size: 1.1rem;
        }
        .alert-success {
            background: #ECFDF5;
            color: #065F46;
            border-color: #A7F3D0;
        }
        .alert-error {
            background: #FEF2F2;
            color: #DC2626;
            border-color: #FECACA;
        }
        .alert-warning {
            background: #FFFBEB;
            color: #92400E;
            border-color: #FDE68A;
        }
        .alert-info {
            background: #EFF6FF;
            color: #1E40AF;
            border-color: #BFDBFE;
        }

        /* ============================================================
           PRELOADER
           ============================================================ */
        #preloader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.4s ease;
        }
        #preloader.hidden {
            opacity: 0;
            pointer-events: none;
        }
        #preloader .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid var(--gray-200);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: var(--gray-100);
        }
        ::-webkit-scrollbar-thumb {
            background: var(--gray-300);
            border-radius: 3px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: var(--gray-400);
        }
    </style>
</head>
<body>

<!-- ============================================================
PRELOADER
============================================================ -->
<div id="preloader">
    <div class="spinner"></div>
</div>

<!-- ============================================================
TOP BAR
============================================================ -->
<div class="top-bar">
    <div class="container">
        <div>
            <i class="fas fa-flag-checkered"></i> 
            <?php echo APP_NAME; ?> - Transparency &amp; Accountability
        </div>
        <div class="top-bar-links">
            <a href="contact.php"><i class="fas fa-envelope"></i> Contact</a>
            <span class="sep">|</span>
            <a href="<?php echo APP_URL; ?>/auth/login.php"><i class="fas fa-lock"></i> Admin Login</a>
        </div>
    </div>
</div>

<!-- ============================================================
HEADER / NAVIGATION
============================================================ -->
<header class="public-header">
    <div class="container">
        <a href="index.php" class="logo">
            <i class="fas fa-vote-yea"></i>
            <span class="logo-text">
                <?php echo APP_NAME; ?>
                <span class="sub">Election Monitoring</span>
            </span>
        </a>

        <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation">
            <i class="fas fa-bars"></i>
        </button>

        <nav class="nav-menu" id="navMenu">
            <a href="index.php" class="<?php echo ($current_page ?? '') === 'home' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> Home
            </a>
            <a href="published-results.php" class="<?php echo ($current_page ?? '') === 'results' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i> Results
            </a>
            <a href="search-polling-units.php" class="<?php echo ($current_page ?? '') === 'search' ? 'active' : ''; ?>">
                <i class="fas fa-search"></i> Search PU
            </a>
            <a href="candidates.php" class="<?php echo ($current_page ?? '') === 'candidates' ? 'active' : ''; ?>">
                <i class="fas fa-user-tie"></i> Candidates
            </a>
            <a href="maps.php" class="<?php echo ($current_page ?? '') === 'maps' ? 'active' : ''; ?>">
                <i class="fas fa-map"></i> Maps
            </a>
            <a href="statistics.php" class="<?php echo ($current_page ?? '') === 'statistics' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> Stats
            </a>
            <a href="election-information.php" class="<?php echo ($current_page ?? '') === 'info' ? 'active' : ''; ?>">
                <i class="fas fa-info-circle"></i> Info
            </a>
        </nav>
    </div>
</header>

<!-- ============================================================
MAIN CONTENT
============================================================ -->
<main class="main-content">