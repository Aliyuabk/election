<header class="dashboard-header" id="dashboardHeader">
        <div class="header-left">
            <h1>
                Dashboard
            </h1>
        </div>
        <div class="header-actions">
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="search-wrapper">
                <div class="search-box" id="searchBox">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search..." autocomplete="off" />
                </div>
                <div class="search-results" id="searchResults"></div>
            </div>
            <a href="notifications.php" class="notification-btn">
                <i class="fas fa-bell"></i> 
                
            </a>
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
 <script>
        // ============================================================
// SEARCH FUNCTIONALITY
// ============================================================
var searchInput = document.getElementById('searchInput');
var searchResults = document.getElementById('searchResults');
var searchTimeout;

if (searchInput) {
    searchInput.addEventListener('input', function() {
        var query = this.value.trim();
        clearTimeout(searchTimeout);
        
        if (query.length < 2) {
            if (searchResults) {
                searchResults.classList.remove('active');
                searchResults.innerHTML = '';
            }
            return;
        }
        
        searchTimeout = setTimeout(function() {
            // Show loading
            searchResults.innerHTML = `
                <div style="padding:12px;text-align:center;color:var(--gray-500);font-size:0.8rem;">
                    <i class="fas fa-spinner fa-spin" style="display:block;font-size:1.2rem;margin-bottom:4px;"></i>
                    Searching...
                </div>
            `;
            searchResults.classList.add('active');
            
            fetch('search.php?q=' + encodeURIComponent(query))
                .then(function(response) { 
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json(); 
                })
                .then(function(data) {
                    if (searchResults) {
                        searchResults.innerHTML = '';
                        if (data && data.length > 0) {
                            // Group results by type
                            var grouped = {};
                            data.forEach(function(item) {
                                if (!grouped[item.type]) {
                                    grouped[item.type] = [];
                                }
                                grouped[item.type].push(item);
                            });
                            
                            var typeColors = {
                                'Election': '#3B82F6',
                                'Candidate': '#8B5CF6',
                                'Party': '#F59E0B',
                                'Polling Unit': '#10B981',
                                'User': '#6B7280',
                                'Incident': '#EF4444',
                                'Budget': '#0D9488',
                                'Broadcast': '#EC4899',
                                'Result': '#14B8A6',
                                'Assignment': '#F59E0B',
                                'Tenant': '#3B82F6',
                                'Subscription': '#8B5CF6',
                                'Invoice': '#10B981'
                            };
                            
                            // Build grouped results
                            Object.keys(grouped).forEach(function(type) {
                                var color = typeColors[type] || '#6B7280';
                                var items = grouped[type];
                                
                                var header = document.createElement('div');
                                header.style.cssText = `
                                    padding: 6px 14px;
                                    font-size: 0.65rem;
                                    font-weight: 700;
                                    text-transform: uppercase;
                                    letter-spacing: 0.05em;
                                    color: ${color};
                                    background: var(--gray-50);
                                    border-bottom: 1px solid var(--gray-200);
                                `;
                                header.textContent = type + ' (' + items.length + ')';
                                searchResults.appendChild(header);
                                
                                items.forEach(function(item) {
                                    var div = document.createElement('a');
                                    div.className = 'result-item';
                                    div.href = item.url || '#';
                                    div.style.cssText = `
                                        display: flex;
                                        align-items: center;
                                        gap: 10px;
                                        padding: 8px 14px;
                                        text-decoration: none;
                                        color: var(--gray-700);
                                        transition: var(--transition);
                                        border-bottom: 1px solid var(--gray-50);
                                        font-size: 0.82rem;
                                    `;
                                    div.innerHTML = `
                                        <i class="fas ${item.icon || 'fa-file'}" style="color:${color};width:16px;text-align:center;font-size:0.85rem;"></i>
                                        <span style="flex:1;font-weight:500;">${item.label || item.name}</span>
                                        <span style="font-size:0.6rem;color:var(--gray-400);">
                                            <i class="fas fa-chevron-right"></i>
                                        </span>
                                    `;
                                    div.addEventListener('mouseenter', function() {
                                        this.style.background = 'var(--gray-50)';
                                    });
                                    div.addEventListener('mouseleave', function() {
                                        this.style.background = 'transparent';
                                    });
                                    searchResults.appendChild(div);
                                });
                            });
                            
                            searchResults.classList.add('active');
                        } else {
                            searchResults.innerHTML = `
                                <div style="padding:12px;text-align:center;color:var(--gray-500);font-size:0.8rem;">
                                    <i class="fas fa-search" style="display:block;font-size:1.2rem;margin-bottom:4px;"></i>
                                    No results found for "${query}"
                                </div>
                            `;
                            searchResults.classList.add('active');
                        }
                    }
                })
                .catch(function(error) {
                    console.error('Search error:', error);
                    if (searchResults) {
                        searchResults.innerHTML = `
                            <div style="padding:12px;text-align:center;color:var(--gray-500);font-size:0.8rem;">
                                <i class="fas fa-exclamation-circle" style="display:block;font-size:1.2rem;margin-bottom:4px;color:var(--danger);"></i>
                                Error searching. Please try again.
                            </div>
                        `;
                        searchResults.classList.add('active');
                    }
                });
        }, 300);
    });

    // Close search results when clicking outside
    document.addEventListener('click', function(e) {
        var wrapper = document.querySelector('.search-wrapper');
        if (wrapper && !wrapper.contains(e.target) && searchResults) {
            searchResults.classList.remove('active');
        }
    });
    
    // Close on Escape key
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && searchResults) {
            searchResults.classList.remove('active');
            this.blur();
        }
    });
}
    </script>