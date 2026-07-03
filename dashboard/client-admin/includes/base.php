<?php
// ============================================================
// CLIENT ADMIN BASE - Super Administrator
// ============================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz@14..32&family=Poppins:wght@600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico" />
   
    
    <!-- ============================================================
    BASE STYLES
    ============================================================ -->
    <style>
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
            border-top-color: #0F4C81;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .preloader-text {
            margin-top: 16px;
            font-weight: 600;
            color: #0F4C81;
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
           BASE STYLES
           ============================================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary: #0F4C81;
            --primary-light: #1a5fa0;
            --primary-dark: #0a3a63;
            --primary-rgb: 15, 76, 129;
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
        }
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

        /* ============================================================
           SIDEBAR
           ============================================================ */
        .sidebar {
            width: 260px;
            background: white;
            border-right: 1px solid var(--gray-200);
            height: 100vh;
            position: sticky;
            top: 0;
            overflow-y: auto;
            padding: 16px 12px 20px;
            flex-shrink: 0;
            transition: transform 0.3s ease;
            z-index: 100;
        }
        .sidebar::-webkit-scrollbar { width: 3px; }
        .sidebar::-webkit-scrollbar-thumb { background: var(--gray-300); border-radius: 8px; }
        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 10px 16px;
            border-bottom: 1px solid var(--gray-200);
            margin-bottom: 16px;
        }
        .sidebar-brand i {
            font-size: 24px;
            color: var(--primary);
        }
        .sidebar-brand span {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--primary);
        }
        .sidebar-brand small {
            display: block;
            font-size: 0.6rem;
            font-weight: 400;
            color: var(--gray-500);
            font-family: 'Inter', sans-serif;
            margin-top: -2px;
        }
        .sidebar-nav { list-style: none; }
        .sidebar-nav li { margin-bottom: 1px; }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            border-radius: 10px;
            color: var(--gray-600);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: var(--transition);
            position: relative;
        }
        .sidebar-nav a i {
            width: 18px;
            text-align: center;
            font-size: 0.95rem;
            color: var(--gray-400);
            transition: var(--transition);
        }
        .sidebar-nav a .chevron {
            margin-left: auto;
            font-size: 0.65rem;
            transition: transform 0.3s ease;
        }
        .sidebar-nav a .chevron.open { transform: rotate(180deg); }
        .sidebar-nav a:hover {
            background: var(--gray-100);
            color: var(--primary);
        }
        .sidebar-nav a:hover i { color: var(--primary); }
        .sidebar-nav a.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.25);
        }
        .sidebar-nav a.active i { color: white; }
        .sidebar-nav .nav-label {
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--gray-400);
            padding: 12px 12px 4px;
            font-weight: 600;
        }
        .sidebar-nav .dropdown-menu {
            padding-left: 16px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        .sidebar-nav .dropdown-menu.open { max-height: 300px; }
        .sidebar-nav .dropdown-menu a {
            padding: 6px 12px 6px 38px;
            font-size: 0.78rem;
            font-weight: 400;
            color: var(--gray-500);
        }
        .sidebar-nav .dropdown-menu a:hover { color: var(--primary); background: var(--gray-50); }
        .sidebar-nav .dropdown-menu a i {
            width: 16px;
            font-size: 0.8rem;
        }

        .sidebar-footer {
            margin-top: auto;
            padding-top: 12px;
            border-top: 1px solid var(--gray-200);
        }
        .sidebar-footer .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 10px;
            border-radius: 10px;
            background: var(--gray-50);
        }
        .sidebar-footer .user-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
            flex-shrink: 0;
        }
        .sidebar-footer .user-name {
            font-weight: 600;
            font-size: 0.82rem;
        }
        .sidebar-footer .user-role {
            font-size: 0.7rem;
            color: var(--gray-500);
        }
        .sidebar-footer .logout-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
            padding: 8px 14px;
            border-radius: 10px;
            background: #FEF2F2;
            color: var(--danger);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.82rem;
            transition: var(--transition);
        }
        .sidebar-footer .logout-btn:hover {
            background: #FEE2E2;
        }

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
            left: 260px;
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
        .search-wrapper {
            position: relative;
        }
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

        /* Notifications */
        .notification-dropdown {
            position: relative;
        }
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
        .notification-menu {
            position: absolute;
            right: 0;
            top: calc(100% + 6px);
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.12);
            border: 1px solid var(--gray-200);
            width: 380px;
            max-height: 460px;
            display: none;
            z-index: 50;
            overflow: hidden;
            flex-direction: column;
        }
        .notification-menu.active { display: flex; }

        /* Profile */
        .profile-dropdown {
            position: relative;
        }
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

        /* ============================================================
           OVERLAY
           ============================================================ */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.3);
            z-index: 150;
        }
        .sidebar-overlay.active { display: block; }

        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                transform: translateX(-100%);
                width: 280px;
                z-index: 200;
                box-shadow: 0 0 40px rgba(0,0,0,0.1);
                height: 100vh;
                top: 0;
                padding: 16px 14px 20px;
            }
            .sidebar.open { transform: translateX(0); }
            .sidebar-toggle { display: block; }
            
            .dashboard-header {
                left: 0;
                padding: 0 14px;
                height: 56px;
            }
            .main-content {
                padding-top: 56px;
            }
            .main-content-inner { 
                padding: 12px 14px 20px; 
            }
            .dashboard-header .header-left h1 { 
                font-size: 1rem; 
            }
            .dashboard-header .header-left h1 small { 
                font-size: 0.6rem; 
            }
            .search-box {
                min-width: 120px;
                padding: 3px 10px;
            }
            .search-box input {
                font-size: 0.75rem;
            }
            .notification-menu {
                width: 320px;
                right: -60px;
            }
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
            .search-box {
                min-width: 80px;
                padding: 2px 8px;
            }
            .search-box input {
                width: 50px;
            }
            .notification-menu {
                width: 290px;
                right: -40px;
            }
            .profile-menu {
                right: -10px;
            }
            .dashboard-header {
                padding: 0 10px;
                height: 50px;
            }
            .main-content {
                padding-top: 50px;
            }
            .main-content-inner {
                padding: 10px 10px 16px;
            }
        }

        /* ============================================================
           SCROLLBAR
           ============================================================ */
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: var(--gray-100); }
        ::-webkit-scrollbar-thumb { background: var(--gray-300); border-radius: 8px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--gray-400); }

        /* ============================================================
           UTILITY
           ============================================================ */
        .text-truncate {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        /* ============================================================
   PROFILE DROPDOWN - Complete Styles
   ============================================================ */

.profile-dropdown {
    position: relative;
}

.profile-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: 2px solid var(--gray-200);
    background: var(--primary);
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
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
    top: calc(100% + 8px);
    background: white;
    border-radius: 14px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.12);
    border: 1px solid var(--gray-200);
    min-width: 220px;
    padding: 6px;
    display: none;
    z-index: 50;
    animation: dropdownFade 0.2s ease;
}

.profile-menu.active {
    display: block;
}

@keyframes dropdownFade {
    from {
        opacity: 0;
        transform: translateY(-8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

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

.profile-menu .logout-link {
    color: var(--danger);
}

.profile-menu .logout-link i {
    color: var(--danger);
}

.profile-menu .logout-link:hover {
    background: #FEF2F2;
}

/* Responsive */
@media (max-width: 768px) {
    .profile-btn {
        width: 34px;
        height: 34px;
        font-size: 0.75rem;
    }
    
    .profile-menu {
        min-width: 180px;
        right: -10px;
    }
}

@media (max-width: 480px) {
    .profile-btn {
        width: 30px;
        height: 30px;
        font-size: 0.65rem;
    }
    
    .profile-menu {
        min-width: 160px;
        right: -15px;
    }
    
    .profile-menu .profile-header .name {
        font-size: 0.8rem;
    }
    
    .profile-menu .profile-header .email {
        font-size: 0.65rem;
    }
    
    .profile-menu a {
        font-size: 0.75rem;
        padding: 6px 12px;
    }
}
/* ============================================================
   DOCUMENT UPLOAD MODAL - ADDITIONAL STYLES
   ============================================================ */

/* File Upload Area */
.file-upload-area {
    border: 2px dashed var(--gray-200);
    border-radius: 10px;
    padding: 30px 20px;
    text-align: center;
    cursor: pointer;
    transition: var(--transition);
    background: var(--gray-50);
    position: relative;
}
.file-upload-area:hover {
    border-color: var(--primary);
    background: #EFF6FF;
}
.file-upload-area.dragover {
    border-color: var(--primary);
    background: #EFF6FF;
    transform: scale(1.01);
}
.file-upload-area i {
    font-size: 2.5rem;
    color: var(--gray-400);
    display: block;
    margin-bottom: 10px;
    transition: var(--transition);
}
.file-upload-area:hover i {
    color: var(--primary);
}
.file-upload-area p {
    font-size: 0.9rem;
    color: var(--gray-500);
    margin-bottom: 4px;
}
.file-upload-area .file-types {
    font-size: 0.7rem;
    color: var(--gray-400);
}
.file-upload-area input[type="file"] {
    display: none;
}

/* File Preview */
.file-preview {
    display: none;
    margin-top: 12px;
    padding: 12px 16px;
    background: var(--gray-50);
    border-radius: 8px;
    border: 1px solid var(--gray-200);
    text-align: left;
    animation: fadeIn 0.3s ease;
}
.file-preview.show {
    display: block;
}
.file-preview .file-info {
    display: flex;
    align-items: center;
    gap: 12px;
}
.file-preview .file-info .file-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}
.file-preview .file-info .file-icon.pdf { background: #FEF2F2; color: #DC2626; }
.file-preview .file-info .file-icon.doc { background: #EFF6FF; color: #3B82F6; }
.file-preview .file-info .file-icon.xls { background: #ECFDF5; color: #10B981; }
.file-preview .file-info .file-icon.txt { background: #F5F3FF; color: #8B5CF6; }
.file-preview .file-info .file-icon.image { background: #FFFBEB; color: #F59E0B; }
.file-preview .file-info .file-details {
    flex: 1;
}
.file-preview .file-info .file-details .file-name {
    font-weight: 500;
    font-size: 0.85rem;
    color: var(--gray-700);
}
.file-preview .file-info .file-details .file-size {
    font-size: 0.7rem;
    color: var(--gray-400);
}
.file-preview .file-info .file-remove {
    background: none;
    border: none;
    color: var(--gray-400);
    cursor: pointer;
    transition: var(--transition);
    padding: 4px;
    border-radius: 4px;
}
.file-preview .file-info .file-remove:hover {
    background: #FEF2F2;
    color: var(--danger);
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-8px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Modal Form Group Enhancements */
.modal .form-group {
    margin-bottom: 16px;
}
.modal .form-group label {
    font-weight: 600;
    font-size: 0.85rem;
    color: var(--gray-700);
    display: block;
    margin-bottom: 4px;
}
.modal .form-group label .required {
    color: var(--danger);
    margin-left: 2px;
}
.modal .form-group .help-text {
    font-size: 0.75rem;
    color: var(--gray-400);
    margin-top: 4px;
}
.modal .form-group select,
.modal .form-group input,
.modal .form-group textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid var(--gray-200);
    border-radius: 10px;
    font-family: 'Inter', sans-serif;
    font-size: 0.85rem;
    transition: var(--transition);
    background: var(--gray-50);
    color: var(--gray-700);
}
.modal .form-group select:focus,
.modal .form-group input:focus,
.modal .form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
}

/* Document Type Select */
.modal .form-group select option {
    padding: 8px;
}

/* Modal Footer Buttons */
.modal .modal-footer .btn {
    padding: 10px 24px;
    border-radius: 10px;
    border: none;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    transition: var(--transition);
    font-family: 'Inter', sans-serif;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.modal .modal-footer .btn-primary {
    background: var(--primary);
    color: white;
}
.modal .modal-footer .btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: 0 4px 16px rgba(var(--primary-rgb), 0.25);
}
.modal .modal-footer .btn-secondary {
    background: var(--gray-100);
    color: var(--gray-600);
}
.modal .modal-footer .btn-secondary:hover {
    background: var(--gray-200);
}

/* Modal Responsive */
@media (max-width: 768px) {
    .file-upload-area {
        padding: 20px 16px;
    }
    .file-upload-area i {
        font-size: 2rem;
    }
    .file-preview .file-info {
        flex-wrap: wrap;
    }
    .modal .modal-footer {
        flex-direction: column;
    }
    .modal .modal-footer .btn {
        width: 100%;
        justify-content: center;
    }
}
/* ============================================================
   SEARCH RESULTS STYLES
   ============================================================ */
.search-wrapper {
    position: relative;
}

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
    max-height: 400px;
    overflow-y: auto;
    display: none;
    z-index: 50;
    min-width: 320px;
}
.search-results.active {
    display: block;
}
.search-results::-webkit-scrollbar {
    width: 4px;
}
.search-results::-webkit-scrollbar-thumb {
    background: var(--gray-300);
    border-radius: 4px;
}
.search-results::-webkit-scrollbar-track {
    background: transparent;
}

.result-item {
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
.result-item:hover {
    background: var(--gray-50);
}
.result-item:last-child {
    border-bottom: none;
}
.result-item .text-truncate {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.result-item .result-type {
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

@media (max-width: 768px) {
    .search-results {
        min-width: auto;
        width: calc(100vw - 40px);
        right: -60px;
        left: auto;
    }
}
@media (max-width: 480px) {
    .search-box {
        min-width: 80px;
        padding: 2px 8px;
    }
    .search-box input {
        width: 50px;
    }
    .search-results {
        right: -40px;
        width: calc(100vw - 30px);
    }
}
    </style>
</head>
<body>

<!-- ============================================================
PRELOADER
============================================================ -->
<div id="preloader">
    <div class="preloader-spinner"></div>
    <div class="preloader-text">Loading <span class="preloader-dots"></span></div>
</div>

<!-- ============================================================
SIDEBAR OVERLAY (mobile)
============================================================ -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>