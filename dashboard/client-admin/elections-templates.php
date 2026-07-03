<?php
// ============================================================
// ELECTION TEMPLATES - CLIENT ADMIN
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Start session and check login
SessionManager::start();

// Redirect if not logged in
if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

// Check role - only client_admin can access this page
if (SessionManager::get('role_level') !== 'client_admin') {
    header('Location: ../client-admin/');
    exit();
}

// Get database connection
$db = getDB();

// Get user info
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       ELECTION TEMPLATES - CLIENT ADMIN STYLES
       ============================================================ */
    
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 20px;
    }
    .page-header h2 {
        font-size: 1.3rem;
        font-weight: 700;
    }
    .page-header h2 small {
        font-size: 0.8rem;
        font-weight: 400;
        color: var(--gray-500);
        display: block;
        margin-top: 2px;
    }
    
    .btn-primary {
        padding: 8px 18px;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-primary:hover {
        background: var(--primary-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 16px rgba(var(--primary-rgb), 0.25);
    }
    .btn-outline {
        padding: 8px 16px;
        background: transparent;
        color: var(--gray-600);
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        font-weight: 500;
        font-size: 0.82rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-outline:hover {
        background: var(--gray-50);
        border-color: var(--gray-300);
    }
    .btn-sm {
        padding: 4px 10px;
        font-size: 0.7rem;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .btn-sm.purple { background: #F5F3FF; color: #5B21B6; }
    .btn-sm.purple:hover { background: #EDE9FE; }
    .btn-sm.blue { background: #EFF6FF; color: #1E40AF; }
    .btn-sm.blue:hover { background: #DBEAFE; }
    .btn-sm.green { background: #ECFDF5; color: #065F46; }
    .btn-sm.green:hover { background: #D1FAE5; }
    .btn-sm.orange { background: #FFFBEB; color: #92400E; }
    .btn-sm.orange:hover { background: #FEF3C7; }
    .btn-sm.red { background: #FEF2F2; color: #991B1B; }
    .btn-sm.red:hover { background: #FEE2E2; }
    
    .templates-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    
    .template-card {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 24px;
        box-shadow: var(--shadow);
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }
    .template-card:hover {
        box-shadow: var(--shadow-hover);
        transform: translateY(-4px);
    }
    .template-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
    }
    .template-card .card-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 12px;
    }
    .template-card .card-header .icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        flex-shrink: 0;
    }
    .template-card .card-header .icon.purple { background: #F5F3FF; color: #8B5CF6; }
    .template-card .card-header .icon.blue { background: #EFF6FF; color: #3B82F6; }
    .template-card .card-header .icon.green { background: #ECFDF5; color: #10B981; }
    .template-card .card-header .icon.orange { background: #FFFBEB; color: #F59E0B; }
    .template-card .card-header .icon.red { background: #FEF2F2; color: #EF4444; }
    
    .template-card .card-header .template-badge {
        font-size: 0.65rem;
        font-weight: 600;
        padding: 3px 10px;
        border-radius: 12px;
        background: var(--gray-100);
        color: var(--gray-500);
    }
    .template-card .template-title {
        font-size: 1.05rem;
        font-weight: 700;
        margin-bottom: 4px;
    }
    .template-card .template-desc {
        font-size: 0.85rem;
        color: var(--gray-500);
        margin-bottom: 16px;
        line-height: 1.5;
    }
    .template-card .template-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        padding: 12px 0;
        border-top: 1px solid var(--gray-100);
        border-bottom: 1px solid var(--gray-100);
        margin-bottom: 16px;
        font-size: 0.78rem;
        color: var(--gray-500);
    }
    .template-card .template-meta span {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .template-card .template-meta i {
        color: var(--gray-400);
    }
    .template-card .card-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .template-card .card-actions .btn-sm {
        padding: 6px 14px;
        border-radius: 8px;
        border: none;
        font-weight: 500;
        font-size: 0.78rem;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--gray-500);
    }
    .empty-state i {
        font-size: 4rem;
        color: var(--gray-300);
        display: block;
        margin-bottom: 16px;
    }
    .empty-state h3 {
        color: var(--gray-700);
        margin-bottom: 8px;
        font-size: 1.2rem;
    }
    
    @media (max-width: 768px) {
        .templates-grid {
            grid-template-columns: 1fr;
        }
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .template-card {
            padding: 18px;
        }
    }
    @media (max-width: 480px) {
        .template-card .template-meta {
            flex-direction: column;
            gap: 6px;
        }
        .template-card .card-actions {
            flex-direction: column;
        }
        .template-card .card-actions .btn-sm {
            justify-content: center;
        }
    }
</style>

<main class="main-content">
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>
                    <i class="fas fa-copy" style="color:var(--primary);margin-right:8px;"></i> Election Templates
                    <small>Pre-configured templates for common election types</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="elections-create.php" class="btn-primary">
                    <i class="fas fa-plus-circle"></i> Create Custom
                </a>
                <a href="elections.php" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Elections
                </a>
            </div>
        </div>

        <!-- Description -->
        <div style="background:white;border-radius:var(--radius);border:1px solid var(--gray-200);padding:16px 20px;margin-bottom:20px;box-shadow:var(--shadow);">
            <p style="color:var(--gray-500);font-size:0.9rem;display:flex;align-items:center;gap:10px;">
                <i class="fas fa-info-circle" style="color:var(--primary);font-size:1.2rem;"></i>
                Use these templates to quickly create elections with pre-configured settings. Templates include common election types with recommended settings.
            </p>
        </div>

        <!-- Templates Grid -->
        <div class="templates-grid">
            <!-- Template 1: Presidential Election -->
            <div class="template-card">
                <div class="card-header">
                    <div class="icon purple">
                        <i class="fas fa-flag"></i>
                    </div>
                    <span class="template-badge">National</span>
                </div>
                <div class="template-title">Presidential Election</div>
                <div class="template-desc">
                    Full national election with presidential candidates, nationwide polling units, and comprehensive result tracking.
                </div>
                <div class="template-meta">
                    <span><i class="fas fa-globe-africa"></i> National</span>
                    <span><i class="fas fa-users"></i> 37 States</span>
                    <span><i class="fas fa-map-marker-alt"></i> All PUs</span>
                </div>
                <div class="card-actions">
                    <a href="elections-create.php?template=presidential" class="btn-sm purple">
                        <i class="fas fa-plus"></i> Use Template
                    </a>
                    <a href="#" class="btn-sm outline" style="background:transparent;color:var(--gray-500);border:1px solid var(--gray-200);">
                        <i class="fas fa-eye"></i> Preview
                    </a>
                </div>
            </div>

            <!-- Template 2: Governorship Election -->
            <div class="template-card">
                <div class="card-header">
                    <div class="icon blue">
                        <i class="fas fa-building"></i>
                    </div>
                    <span class="template-badge">State</span>
                </div>
                <div class="template-title">Governorship Election</div>
                <div class="template-desc">
                    State-level election for governor with LGA-level result aggregation and state-specific candidate tracking.
                </div>
                <div class="template-meta">
                    <span><i class="fas fa-map"></i> State Level</span>
                    <span><i class="fas fa-users"></i> 1 State</span>
                    <span><i class="fas fa-map-marker-alt"></i> All LGAs</span>
                </div>
                <div class="card-actions">
                    <a href="elections-create.php?template=governorship" class="btn-sm blue">
                        <i class="fas fa-plus"></i> Use Template
                    </a>
                    <a href="#" class="btn-sm outline" style="background:transparent;color:var(--gray-500);border:1px solid var(--gray-200);">
                        <i class="fas fa-eye"></i> Preview
                    </a>
                </div>
            </div>

            <!-- Template 3: Senatorial Election -->
            <div class="template-card">
                <div class="card-header">
                    <div class="icon green">
                        <i class="fas fa-gavel"></i>
                    </div>
                    <span class="template-badge">Senatorial</span>
                </div>
                <div class="template-title">Senatorial Election</div>
                <div class="template-desc">
                    Senatorial district election with focus on senatorial zones, LGA-level reporting, and district-wide results.
                </div>
                <div class="template-meta">
                    <span><i class="fas fa-layer-group"></i> Senatorial Zone</span>
                    <span><i class="fas fa-users"></i> 109 Districts</span>
                    <span><i class="fas fa-map-marker-alt"></i> Multiple LGAs</span>
                </div>
                <div class="card-actions">
                    <a href="elections-create.php?template=senatorial" class="btn-sm green">
                        <i class="fas fa-plus"></i> Use Template
                    </a>
                    <a href="#" class="btn-sm outline" style="background:transparent;color:var(--gray-500);border:1px solid var(--gray-200);">
                        <i class="fas fa-eye"></i> Preview
                    </a>
                </div>
            </div>

            <!-- Template 4: House of Reps Election -->
            <div class="template-card">
                <div class="card-header">
                    <div class="icon orange">
                        <i class="fas fa-university"></i>
                    </div>
                    <span class="template-badge">Federal</span>
                </div>
                <div class="template-title">House of Representatives</div>
                <div class="template-desc">
                    Federal constituency election with ward-level reporting, candidate tracking, and constituency results.
                </div>
                <div class="template-meta">
                    <span><i class="fas fa-layer-group"></i> Federal Constituency</span>
                    <span><i class="fas fa-users"></i> 360 Constituencies</span>
                    <span><i class="fas fa-map-marker-alt"></i> Ward Level</span>
                </div>
                <div class="card-actions">
                    <a href="elections-create.php?template=house_of_reps" class="btn-sm orange">
                        <i class="fas fa-plus"></i> Use Template
                    </a>
                    <a href="#" class="btn-sm outline" style="background:transparent;color:var(--gray-500);border:1px solid var(--gray-200);">
                        <i class="fas fa-eye"></i> Preview
                    </a>
                </div>
            </div>

            <!-- Template 5: LGA Chairman Election -->
            <div class="template-card">
                <div class="card-header">
                    <div class="icon red">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <span class="template-badge">Local</span>
                </div>
                <div class="template-title">LGA Chairman Election</div>
                <div class="template-desc">
                    Local Government Area election with ward-level results, LGA-wide aggregation, and local candidate management.
                </div>
                <div class="template-meta">
                    <span><i class="fas fa-map"></i> LGA Level</span>
                    <span><i class="fas fa-users"></i> 1 LGA</span>
                    <span><i class="fas fa-map-marker-alt"></i> All Wards</span>
                </div>
                <div class="card-actions">
                    <a href="elections-create.php?template=lga_chairman" class="btn-sm red">
                        <i class="fas fa-plus"></i> Use Template
                    </a>
                    <a href="#" class="btn-sm outline" style="background:transparent;color:var(--gray-500);border:1px solid var(--gray-200);">
                        <i class="fas fa-eye"></i> Preview
                    </a>
                </div>
            </div>

            <!-- Template 6: Party Primary Election -->
            <div class="template-card">
                <div class="card-header">
                    <div class="icon purple">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <span class="template-badge">Internal</span>
                </div>
                <div class="template-title">Party Primary Election</div>
                <div class="template-desc">
                    Internal party primary election with delegate management, candidate selection, and party-specific tracking.
                </div>
                <div class="template-meta">
                    <span><i class="fas fa-users"></i> Party Delegates</span>
                    <span><i class="fas fa-building"></i> Internal Party</span>
                    <span><i class="fas fa-user-tie"></i> Candidate Selection</span>
                </div>
                <div class="card-actions">
                    <a href="elections-create.php?template=party_primary" class="btn-sm purple">
                        <i class="fas fa-plus"></i> Use Template
                    </a>
                    <a href="#" class="btn-sm outline" style="background:transparent;color:var(--gray-500);border:1px solid var(--gray-200);">
                        <i class="fas fa-eye"></i> Preview
                    </a>
                </div>
            </div>

            <!-- Template 7: Councillorship Election -->
            <div class="template-card">
                <div class="card-header">
                    <div class="icon green">
                        <i class="fas fa-city"></i>
                    </div>
                    <span class="template-badge">Ward</span>
                </div>
                <div class="template-title">Councillorship Election</div>
                <div class="template-desc">
                    Ward-level councillorship election with ward-based results, local candidate management, and community engagement.
                </div>
                <div class="template-meta">
                    <span><i class="fas fa-map"></i> Ward Level</span>
                    <span><i class="fas fa-users"></i> 1 Ward</span>
                    <span><i class="fas fa-map-marker-alt"></i> PUs in Ward</span>
                </div>
                <div class="card-actions">
                    <a href="elections-create.php?template=councillorship" class="btn-sm green">
                        <i class="fas fa-plus"></i> Use Template
                    </a>
                    <a href="#" class="btn-sm outline" style="background:transparent;color:var(--gray-500);border:1px solid var(--gray-200);">
                        <i class="fas fa-eye"></i> Preview
                    </a>
                </div>
            </div>

            <!-- Template 8: Internal Party Election -->
            <div class="template-card">
                <div class="card-header">
                    <div class="icon blue">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <span class="template-badge">Internal</span>
                </div>
                <div class="template-title">Internal Party Election</div>
                <div class="template-desc">
                    Internal party elections for leadership positions, executive committees, and party delegate selections.
                </div>
                <div class="template-meta">
                    <span><i class="fas fa-users"></i> Party Members</span>
                    <span><i class="fas fa-building"></i> Internal</span>
                    <span><i class="fas fa-user-tie"></i> Leadership</span>
                </div>
                <div class="card-actions">
                    <a href="elections-create.php?template=internal_party" class="btn-sm blue">
                        <i class="fas fa-plus"></i> Use Template
                    </a>
                    <a href="#" class="btn-sm outline" style="background:transparent;color:var(--gray-500);border:1px solid var(--gray-200);">
                        <i class="fas fa-eye"></i> Preview
                    </a>
                </div>
            </div>
        </div>

        <!-- Note about templates -->
        <div style="margin-top:24px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);padding:16px 20px;box-shadow:var(--shadow);">
            <div style="display:flex;align-items:flex-start;gap:12px;">
                <i class="fas fa-lightbulb" style="color:#F59E0B;font-size:1.2rem;margin-top:2px;"></i>
                <div>
                    <h4 style="font-size:0.9rem;font-weight:600;margin-bottom:4px;">Customize Your Election</h4>
                    <p style="color:var(--gray-500);font-size:0.85rem;">
                        Templates provide a starting point. You can customize all settings including dates, locations, candidates, and polling units after creation.
                        <a href="elections-create.php" style="color:var(--primary);text-decoration:none;font-weight:500;">Create from scratch →</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// ============================================================
// PRELOADER
// ============================================================
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});

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
// SIDEBAR DROPDOWNS
// ============================================================
document.querySelectorAll('.dropdown-toggle').forEach(function(toggle) {
    toggle.addEventListener('click', function(e) {
        e.preventDefault();
        var dropdownId = this.dataset.dropdown;
        var dropdown = document.getElementById(dropdownId);
        var chevron = this.querySelector('.chevron');
        if (dropdown) {
            dropdown.classList.toggle('open');
            if (chevron) chevron.classList.toggle('open');
        }
    });
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
// SEARCH
// ============================================================
var searchInput = document.getElementById('searchInput');
var searchResults = document.getElementById('searchResults');
var searchTimeout;

if (searchInput) {
    searchInput.addEventListener('input', function() {
        var query = this.value.trim();
        clearTimeout(searchTimeout);
        if (query.length < 2) {
            if (searchResults) searchResults.classList.remove('active');
            return;
        }
        searchTimeout = setTimeout(function() {
            fetch('search.php?q=' + encodeURIComponent(query))
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (searchResults) {
                        searchResults.innerHTML = '';
                        if (data && data.length > 0) {
                            data.forEach(function(item) {
                                var div = document.createElement('a');
                                div.className = 'result-item';
                                div.href = item.url || '#';
                                div.innerHTML = '<i class="fas ' + (item.icon || 'fa-file') + '"></i><span class="text-truncate">' + (item.label || item.name || '') + '</span><span class="result-type">' + ((item.type || '').charAt(0).toUpperCase() + (item.type || '').slice(1)) + '</span>';
                                searchResults.appendChild(div);
                            });
                            searchResults.classList.add('active');
                        } else {
                            searchResults.innerHTML = '<div style="padding:12px;text-align:center;color:var(--gray-500);font-size:0.8rem;"><i class="fas fa-search" style="display:block;font-size:1.2rem;margin-bottom:4px;"></i>No results found</div>';
                            searchResults.classList.add('active');
                        }
                    }
                })
                .catch(function() {});
        }, 300);
    });

    document.addEventListener('click', function(e) {
        var wrapper = document.querySelector('.search-wrapper');
        if (wrapper && !wrapper.contains(e.target) && searchResults) {
            searchResults.classList.remove('active');
        }
    });
}
</script>
</body>
</html>