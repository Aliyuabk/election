<?php
// ============================================================
// HEADER - Super Administrator (With Search & Notifications)
// ============================================================

// Get user info from session
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');
?>

<!-- ============================================================
HEADER
============================================================ -->
<header class="dashboard-header" id="dashboardHeader">
    <div class="header-left">
        <h1>
            Dashboard
            <small>Welcome back, <?php echo htmlspecialchars($user_name); ?></small>
        </h1>
    </div>
    <div class="header-actions">
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
        
        <!-- Search -->
        <div class="search-wrapper">
            <div class="search-box" id="searchBox">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search tenants, users, elections..." autocomplete="off" />
            </div>
            <div class="search-results" id="searchResults"></div>
        </div>
        
        <!-- Notifications -->
        <div class="notification-dropdown">
            <button class="notification-btn" id="notificationBtn">
                <i class="fas fa-bell"></i>
                <span class="badge" id="notificationBadge">0</span>
            </button>
            <div class="notification-menu" id="notificationMenu">
                <div class="notification-header">
                    <span>Notifications</span>
                    <button class="mark-all-read-btn" id="markAllRead">Mark all as read</button>
                </div>
                <div class="notification-list" id="notificationList">
                    <div class="notification-empty">
                        <i class="fas fa-bell-slash"></i>
                        <p>No notifications</p>
                    </div>
                </div>
                <div class="notification-footer">
                    <a href="notifications.php">View all notifications</a>
                </div>
            </div>
        </div>
        
        <!-- Profile -->
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

<!-- Notification Toast -->
<div class="notification-toast" id="notificationToast">
    <button class="toast-close" onclick="closeNotificationToast()">&times;</button>
    <div class="toast-title" id="toastTitle">New Notification</div>
    <div class="toast-message" id="toastMessage">You have a new notification.</div>
</div>

<!-- Styles for header components -->
<style>
    /* ============================================================
       HEADER - Enhanced Styles
       ============================================================ */
    
    /* Header */
    .dashboard-header {
        position: fixed;
        top: 0;
        left: 260px;
        right: 0;
        height: var(--header-height, 64px);
        background: white;
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 24px;
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
        margin-top: -2px;
    }
    
    /* Header Actions */
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
        padding: 6px 14px;
        gap: 8px;
        transition: var(--transition);
        min-width: 200px;
    }
    .search-box:focus-within {
        border-color: var(--primary);
        background: white;
        box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.06);
    }
    .search-box i {
        color: var(--gray-400);
        font-size: 0.85rem;
    }
    .search-box input {
        border: none;
        outline: none;
        background: transparent;
        font-family: 'Inter', sans-serif;
        font-size: 0.85rem;
        width: 100%;
        color: var(--gray-700);
    }
    .search-box input::placeholder {
        color: var(--gray-400);
        font-size: 0.78rem;
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
    .search-results.active {
        display: block;
    }
    .search-results::-webkit-scrollbar {
        width: 4px;
    }
    .search-results::-webkit-scrollbar-thumb {
        background: var(--gray-300);
        border-radius: 8px;
    }
    
    /* Notifications */
    .notification-dropdown {
        position: relative;
    }
    .notification-btn {
        width: 40px;
        height: 40px;
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
        top: -4px;
        right: -4px;
        background: var(--danger);
        color: white;
        font-size: 0.55rem;
        font-weight: 700;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .notification-menu {
        position: absolute;
        right: 0;
        top: calc(100% + 8px);
        background: white;
        border-radius: 14px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.12);
        border: 1px solid var(--gray-200);
        width: 380px;
        max-height: 460px;
        display: none;
        z-index: 50;
        overflow: hidden;
        flex-direction: column;
    }
    .notification-menu.active {
        display: flex;
    }
    .notification-menu .notification-header {
        padding: 12px 18px;
        border-bottom: 1px solid var(--gray-100);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-shrink: 0;
    }
    .notification-menu .notification-header span {
        font-weight: 600;
        font-size: 0.9rem;
    }
    .notification-menu .notification-header .mark-all-read-btn {
        background: none;
        border: none;
        color: var(--primary);
        font-size: 0.75rem;
        font-weight: 500;
        cursor: pointer;
        font-family: 'Inter', sans-serif;
    }
    .notification-menu .notification-header .mark-all-read-btn:hover {
        text-decoration: underline;
    }
    
    .notification-list {
        overflow-y: auto;
        flex: 1;
        padding: 4px 0;
    }
    .notification-list::-webkit-scrollbar {
        width: 4px;
    }
    .notification-list::-webkit-scrollbar-thumb {
        background: var(--gray-300);
        border-radius: 8px;
    }
    
    .notification-item {
        padding: 10px 16px;
        border-bottom: 1px solid var(--gray-50);
        cursor: pointer;
        transition: var(--transition);
        display: flex;
        gap: 10px;
        align-items: flex-start;
        text-decoration: none;
        color: var(--gray-700);
    }
    .notification-item:hover {
        background: var(--gray-50);
    }
    .notification-item.unread {
        background: #F0F7FF;
        border-left: 3px solid var(--primary);
    }
    .notification-item.unread:hover {
        background: #E8F0FE;
    }
    .notification-item .notif-icon {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        flex-shrink: 0;
    }
    .notification-item .notif-icon.system { background: #EFF6FF; color: #3B82F6; }
    .notification-item .notif-icon.election { background: #ECFDF5; color: #10B981; }
    .notification-item .notif-icon.result { background: #F5F3FF; color: #8B5CF6; }
    .notification-item .notif-icon.incident { background: #FEF2F2; color: #EF4444; }
    .notification-item .notif-icon.chat { background: #FFFBEB; color: #F59E0B; }
    .notification-item .notif-icon.payment { background: #ECFDF5; color: #10B981; }
    .notification-item .notif-icon.security { background: #FEF2F2; color: #EF4444; }
    .notification-item .notif-icon.broadcast { background: #F5F3FF; color: #8B5CF6; }
    .notification-item .notif-icon.tenant { background: #EFF6FF; color: #3B82F6; }
    .notification-item .notif-icon.user { background: #F5F3FF; color: #8B5CF6; }
    
    .notification-item .notif-content {
        flex: 1;
        min-width: 0;
    }
    .notification-item .notif-content .notif-title {
        font-weight: 500;
        font-size: 0.85rem;
    }
    .notification-item .notif-content .notif-message {
        font-size: 0.78rem;
        color: var(--gray-500);
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .notification-item .notif-content .notif-time {
        font-size: 0.65rem;
        color: var(--gray-400);
        margin-top: 2px;
    }
    
    .notification-empty {
        padding: 30px 20px;
        text-align: center;
        color: var(--gray-400);
    }
    .notification-empty i {
        font-size: 2rem;
        display: block;
        margin-bottom: 8px;
        color: var(--gray-300);
    }
    .notification-empty p {
        font-size: 0.85rem;
    }
    
    .notification-footer {
        padding: 10px 18px;
        border-top: 1px solid var(--gray-100);
        text-align: center;
        flex-shrink: 0;
    }
    .notification-footer a {
        color: var(--primary);
        text-decoration: none;
        font-size: 0.8rem;
        font-weight: 500;
    }
    .notification-footer a:hover {
        text-decoration: underline;
    }
    
    /* Notification Toast */
    .notification-toast {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        border: 1px solid var(--gray-200);
        padding: 14px 20px;
        max-width: 380px;
        display: none;
        z-index: 999;
        animation: slideUp 0.3s ease;
        border-left: 4px solid var(--primary);
    }
    .notification-toast.show {
        display: block;
    }
    @keyframes slideUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .notification-toast .toast-title {
        font-weight: 600;
        font-size: 0.9rem;
    }
    .notification-toast .toast-message {
        font-size: 0.82rem;
        color: var(--gray-500);
    }
    .notification-toast .toast-close {
        position: absolute;
        top: 6px;
        right: 10px;
        background: none;
        border: none;
        color: var(--gray-400);
        cursor: pointer;
        font-size: 1.1rem;
    }
    
    /* Profile */
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
        box-shadow: 0 10px 40px rgba(0,0,0,0.12);
        border: 1px solid var(--gray-200);
        min-width: 220px;
        padding: 6px;
        display: none;
        z-index: 50;
    }
    .profile-menu.active {
        display: block;
    }
    .profile-menu .profile-header {
        padding: 10px 14px;
        border-bottom: 1px solid var(--gray-100);
        margin-bottom: 4px;
    }
    .profile-menu .profile-header .name {
        font-weight: 600;
        font-size: 0.9rem;
    }
    .profile-menu .profile-header .email {
        font-size: 0.75rem;
        color: var(--gray-500);
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
    
    /* Search Results Items */
    .search-section-header {
        padding: 8px 16px;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--gray-400);
        font-weight: 600;
        background: var(--gray-50);
        border-top: 1px solid var(--gray-100);
        border-bottom: 1px solid var(--gray-100);
    }
    .search-section-header:first-child {
        border-top: none;
    }
    .search-divider {
        height: 1px;
        background: var(--gray-100);
        margin: 4px 0;
    }
    .search-results .result-item {
        padding: 10px 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        border-bottom: 1px solid var(--gray-50);
        cursor: pointer;
        transition: var(--transition);
        text-decoration: none;
        color: var(--gray-700);
        font-size: 0.85rem;
    }
    .search-results .result-item:hover {
        background: var(--gray-50);
    }
    .search-results .result-item:last-child {
        border-bottom: none;
    }
    .search-results .result-item i {
        color: var(--gray-400);
        width: 18px;
        font-size: 0.9rem;
    }
    .search-results .result-item .result-type {
        font-size: 0.6rem;
        color: var(--gray-400);
        margin-left: auto;
        background: var(--gray-100);
        padding: 2px 10px;
        border-radius: 12px;
        white-space: nowrap;
    }
    
    /* Responsive */
    @media (max-width: 1024px) {
        .search-box {
            min-width: 150px;
        }
    }
    
    @media (max-width: 768px) {
        .dashboard-header {
            left: 0;
            padding: 0 14px;
            height: 56px;
        }
        .sidebar-toggle {
            display: block;
        }
        .dashboard-header .header-left h1 {
            font-size: 1rem;
        }
        .dashboard-header .header-left h1 small {
            font-size: 0.6rem;
        }
        .search-box {
            min-width: 120px;
            padding: 4px 10px;
        }
        .search-box input {
            font-size: 0.78rem;
        }
        .search-box input::placeholder {
            font-size: 0.7rem;
        }
        .notification-menu {
            width: 320px;
            right: -60px;
        }
        .profile-btn {
            width: 34px;
            height: 34px;
            font-size: 0.75rem;
        }
        .notification-btn {
            width: 34px;
            height: 34px;
        }
    }
    
    @media (max-width: 480px) {
        .search-box {
            min-width: 80px;
            padding: 3px 8px;
        }
        .search-box input {
            width: 50px;
        }
        .notification-menu {
            width: 290px;
            right: -40px;
        }
        .notification-item {
            padding: 8px 12px;
        }
    }
</style>

<script>
// ============================================================
// HEADER - JavaScript
// ============================================================

// ============================================================
// SIDEBAR TOGGLE
// ============================================================
var sidebar = document.getElementById('sidebar');
var sidebarToggle = document.getElementById('sidebarToggle');
var sidebarOverlay = document.getElementById('sidebarOverlay');
var dashboardHeader = document.getElementById('dashboardHeader');

function toggleSidebar() {
    sidebar.classList.toggle('open');
    sidebarOverlay.classList.toggle('active');
    updateHeaderPosition();
}

function updateHeaderPosition() {
    if (window.innerWidth > 768) {
        dashboardHeader.style.left = '260px';
    } else if (sidebar.classList.contains('open')) {
        dashboardHeader.style.left = '280px';
    } else {
        dashboardHeader.style.left = '0';
    }
}

if (sidebarToggle) {
    sidebarToggle.addEventListener('click', toggleSidebar);
}
if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', toggleSidebar);
}

window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
        sidebar.classList.remove('open');
        sidebarOverlay.classList.remove('active');
        dashboardHeader.style.left = '260px';
    } else if (!sidebar.classList.contains('open')) {
        dashboardHeader.style.left = '0';
    }
});

// ============================================================
// PROFILE DROPDOWN
// ============================================================
var profileBtn = document.getElementById('profileBtn');
var profileMenu = document.getElementById('profileMenu');

if (profileBtn && profileMenu) {
    profileBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        profileMenu.classList.toggle('active');
    });
    document.addEventListener('click', function(e) {
        if (!profileBtn.contains(e.target) && !profileMenu.contains(e.target)) {
            profileMenu.classList.remove('active');
        }
    });
}

// ============================================================
// SEARCH - JavaScript
// ============================================================
var searchInput = document.getElementById('searchInput');
var searchResults = document.getElementById('searchResults');
var searchTimeout;
var searchActive = false;

if (searchInput) {
    searchInput.addEventListener('input', function() {
        var query = this.value.trim();
        clearTimeout(searchTimeout);
        
        if (query.length < 2) {
            searchResults.classList.remove('active');
            searchActive = false;
            return;
        }
        
        searchActive = true;
        searchTimeout = setTimeout(function() {
            performSearch(query);
        }, 300);
    });
    
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            searchResults.classList.remove('active');
            searchActive = false;
            this.blur();
        }
    });
    
    searchInput.addEventListener('focus', function() {
        if (this.value.trim().length >= 2 && searchActive) {
            searchResults.classList.add('active');
        }
    });
}

function performSearch(query) {
    fetch('search.php?q=' + encodeURIComponent(query), {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        renderSearchResults(data);
    })
    .catch(function() {
        // Silently fail
    });
}

function renderSearchResults(data) {
    if (!searchResults) return;
    searchResults.innerHTML = '';
    
    if (!data || data.length === 0) {
        searchResults.innerHTML = `
            <div style="padding:16px;text-align:center;color:var(--gray-500);font-size:0.85rem;">
                <i class="fas fa-search" style="display:block;font-size:1.4rem;margin-bottom:6px;color:var(--gray-300);"></i>
                No results found for "<strong>${searchInput.value}</strong>"
            </div>
        `;
        searchResults.classList.add('active');
        return;
    }
    
    var html = '';
    var currentType = '';
    
    data.forEach(function(item) {
        var typeLabel = (item.type || '').charAt(0).toUpperCase() + (item.type || '').slice(1);
        var icon = item.icon || 'fa-file';
        
        // Add section header when type changes
        if (item.type !== currentType) {
            if (currentType !== '') {
                html += '<div class="search-divider"></div>';
            }
            html += `<div class="search-section-header">${typeLabel}</div>`;
            currentType = item.type;
        }
        
        html += `
            <a href="${item.url || '#'}" class="result-item" onclick="closeSearch()">
                <i class="fas ${icon}"></i>
                <span class="text-truncate">${item.name || item.label || ''}</span>
                <span class="result-type">${typeLabel}</span>
            </a>
        `;
    });
    
    searchResults.innerHTML = html;
    searchResults.classList.add('active');
}

function closeSearch() {
    searchResults.classList.remove('active');
    searchActive = false;
}

// Close search results on click outside
document.addEventListener('click', function(e) {
    var searchWrapper = document.querySelector('.search-wrapper');
    if (searchWrapper && !searchWrapper.contains(e.target)) {
        searchResults.classList.remove('active');
        searchActive = false;
    }
});

// ============================================================
// NOTIFICATIONS - JavaScript
// ============================================================
var notificationList = document.getElementById('notificationList');
var notificationMenu = document.getElementById('notificationMenu');
var notificationBtn = document.getElementById('notificationBtn');
var notificationBadge = document.getElementById('notificationBadge');

// Load notifications
function loadNotifications() {
    fetch('notification.php?action=get_notifications&limit=10', {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            renderNotifications(data.data);
            updateBadge(data.unread_count);
        }
    })
    .catch(function() {});
}

// Render notifications
function renderNotifications(notifications) {
    if (!notificationList) return;
    
    if (!notifications || notifications.length === 0) {
        notificationList.innerHTML = `
            <div class="notification-empty">
                <i class="fas fa-bell-slash"></i>
                <p>No notifications</p>
            </div>
        `;
        return;
    }
    
    var html = '';
    notifications.forEach(function(notif) {
        var iconClass = notif.type || 'system';
        var iconMap = {
            'system': 'fa-cog',
            'election': 'fa-vote-yea',
            'result': 'fa-chart-bar',
            'incident': 'fa-exclamation-triangle',
            'chat': 'fa-comment',
            'payment': 'fa-credit-card',
            'security': 'fa-shield-alt',
            'broadcast': 'fa-bullhorn',
            'tenant': 'fa-building',
            'user': 'fa-user'
        };
        var icon = iconMap[iconClass] || 'fa-bell';
        var unreadClass = notif.is_read ? '' : 'unread';
        var time = new Date(notif.created_at).toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
        
        html += `
            <a href="${notif.action_url || '#'}" class="notification-item ${unreadClass}" data-id="${notif.id}" onclick="markNotificationRead(${notif.id})">
                <div class="notif-icon ${iconClass}">
                    <i class="fas ${icon}"></i>
                </div>
                <div class="notif-content">
                    <div class="notif-title">${notif.title}</div>
                    <div class="notif-message">${notif.message}</div>
                    <div class="notif-time">${time}</div>
                </div>
            </a>
        `;
    });
    
    notificationList.innerHTML = html;
}

// Update notification badge
function updateBadge(count) {
    if (notificationBadge) {
        notificationBadge.textContent = count > 0 ? count : 0;
        notificationBadge.style.display = count > 0 ? 'flex' : 'none';
    }
}

// Mark notification as read
function markNotificationRead(id) {
    fetch('notification.php?action=mark_read', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'id=' + id
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            loadNotifications();
        }
    })
    .catch(function() {});
}

// Mark all as read
var markAllReadBtn = document.getElementById('markAllRead');
if (markAllReadBtn) {
    markAllReadBtn.addEventListener('click', function() {
        fetch('notification.php?action=mark_all_read', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                loadNotifications();
            }
        })
        .catch(function() {});
    });
}

// Toggle notification menu
if (notificationBtn) {
    notificationBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        notificationMenu.classList.toggle('active');
        if (notificationMenu.classList.contains('active')) {
            loadNotifications();
        }
    });
}

// Close notification menu on outside click
document.addEventListener('click', function(e) {
    if (!notificationBtn.contains(e.target) && !notificationMenu.contains(e.target)) {
        notificationMenu.classList.remove('active');
    }
});

// Show notification toast
function showNotificationToast(title, message) {
    var toast = document.getElementById('notificationToast');
    if (!toast) return;
    document.getElementById('toastTitle').textContent = title || 'New Notification';
    document.getElementById('toastMessage').textContent = message || 'You have a new notification.';
    toast.classList.add('show');
    
    clearTimeout(window.toastTimeout);
    window.toastTimeout = setTimeout(function() {
        closeNotificationToast();
    }, 5000);
}

function closeNotificationToast() {
    var toast = document.getElementById('notificationToast');
    if (toast) toast.classList.remove('show');
}

// Load initial notification count
document.addEventListener('DOMContentLoaded', function() {
    fetch('notification.php?action=get_unread_count', {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            updateBadge(data.unread_count);
        }
    })
    .catch(function() {});
});

// ============================================================
// SIMULATE REAL-TIME NOTIFICATIONS (Optional)
// ============================================================
// Uncomment below to simulate real-time notifications
/*
setInterval(function() {
    fetch('notification.php?action=get_unread_count', {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success && data.unread_count > parseInt(notificationBadge.textContent || 0)) {
            showNotificationToast('New notification', 'You have a new notification.');
            updateBadge(data.unread_count);
        }
    })
    .catch(function() {});
}, 30000); // Check every 30 seconds
*/
</script>