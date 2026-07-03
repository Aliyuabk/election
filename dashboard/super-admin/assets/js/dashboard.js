// ============================================================
// DASHBOARD MASTER JAVASCRIPT - All Pages
// ============================================================

document.addEventListener('DOMContentLoaded', function() {

    // ============================================================
    // PRELOADER
    // ============================================================
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() {
            preloader.style.display = 'none';
        }, 600);
    }

    // ============================================================
    // SIDEBAR TOGGLE (mobile)
    // ============================================================
    var sidebar = document.getElementById('sidebar');
    var sidebarToggle = document.getElementById('sidebarToggle');
    var sidebarOverlay = document.getElementById('sidebarOverlay');
    var dashboardHeader = document.getElementById('dashboardHeader');

    function toggleSidebar() {
        if (sidebar) {
            sidebar.classList.toggle('open');
        }
        if (sidebarOverlay) {
            sidebarOverlay.classList.toggle('active');
        }
        updateHeaderPosition();
    }

    function updateHeaderPosition() {
        if (!dashboardHeader) return;
        if (window.innerWidth > 768) {
            dashboardHeader.style.left = '260px';
        } else if (sidebar && sidebar.classList.contains('open')) {
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
            if (sidebar) sidebar.classList.remove('open');
            if (sidebarOverlay) sidebarOverlay.classList.remove('active');
            if (dashboardHeader) dashboardHeader.style.left = '260px';
        } else if (sidebar && !sidebar.classList.contains('open')) {
            if (dashboardHeader) dashboardHeader.style.left = '0';
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
    // ACTION DROPDOWN (for table actions)
    // ============================================================
    function toggleDropdown(btn) {
        if (!btn) return;
        var menu = btn.nextElementSibling;
        if (!menu) return;
        
        var isOpen = menu.classList.contains('open');
        
        // Close all dropdowns first
        document.querySelectorAll('.action-dropdown-pro .dropdown-menu').forEach(function(m) {
            m.classList.remove('open');
        });
        
        if (!isOpen) {
            menu.classList.toggle('open');
        }
    }

    // Make toggleDropdown globally accessible
    window.toggleDropdown = toggleDropdown;

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.action-dropdown-pro')) {
            document.querySelectorAll('.action-dropdown-pro .dropdown-menu').forEach(function(m) {
                m.classList.remove('open');
            });
        }
    });

    // ============================================================
    // SEARCH - Live Database Search (header)
    // ============================================================
    var searchInput = document.getElementById('searchInput');
    var searchResults = document.getElementById('searchResults');
    var searchTimeout;

    if (searchInput && searchResults) {
        searchInput.addEventListener('input', function() {
            var query = this.value.trim();
            
            clearTimeout(searchTimeout);
            
            if (query.length < 2) {
                searchResults.classList.remove('active');
                return;
            }
            
            searchTimeout = setTimeout(function() {
                performSearch(query);
            }, 300);
        });

        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                searchResults.classList.remove('active');
                this.blur();
            }
        });

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
            searchResults.innerHTML = '';
            
            if (!data || data.length === 0) {
                searchResults.innerHTML = 
                    '<div style="padding:12px;text-align:center;color:var(--gray-500);font-size:0.8rem;">' +
                        '<i class="fas fa-search" style="display:block;font-size:1.2rem;margin-bottom:4px;"></i>' +
                        'No results found' +
                    '</div>';
                searchResults.classList.add('active');
                return;
            }
            
            data.forEach(function(item) {
                var div = document.createElement('a');
                div.className = 'result-item';
                div.href = item.url || '#';
                
                var icon = item.icon || 'fa-file';
                var type = item.type || '';
                var typeLabel = type.charAt(0).toUpperCase() + type.slice(1);
                
                div.innerHTML = 
                    '<i class="fas ' + icon + '"></i>' +
                    '<span class="text-truncate">' + (item.label || item.name || '') + '</span>' +
                    '<span class="result-type">' + typeLabel + '</span>';
                    
                searchResults.appendChild(div);
            });
            
            searchResults.classList.add('active');
        }

        // Close search results on click outside
        document.addEventListener('click', function(e) {
            var wrapper = document.querySelector('.search-wrapper');
            if (wrapper && !wrapper.contains(e.target)) {
                searchResults.classList.remove('active');
            }
        });
    }

    // ============================================================
    // TOAST AUTO-DISMISS
    // ============================================================
    var toasts = document.querySelectorAll('.toast');
    toasts.forEach(function(toast) {
        setTimeout(function() {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100px)';
            setTimeout(function() {
                toast.style.display = 'none';
            }, 300);
        }, 5000);
    });

    // ============================================================
    // FORM VALIDATION - Generic
    // ============================================================
    var forms = document.querySelectorAll('form[data-validate]');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            var required = form.querySelectorAll('[required]');
            var isValid = true;
            var firstError = null;
            
            required.forEach(function(field) {
                if (!field.value.trim()) {
                    field.classList.add('error');
                    isValid = false;
                    if (!firstError) firstError = field;
                } else {
                    field.classList.remove('error');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                if (firstError) {
                    firstError.focus();
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    });

    // ============================================================
    // DATA-TABLE SORTING (simple)
    // ============================================================
    document.querySelectorAll('.data-table thead th[data-sort]').forEach(function(th) {
        th.style.cursor = 'pointer';
        th.addEventListener('click', function() {
            var table = this.closest('.data-table');
            var tbody = table.querySelector('tbody');
            var rows = Array.from(tbody.querySelectorAll('tr'));
            var index = Array.from(this.parentElement.children).indexOf(this);
            var ascending = !this.dataset.asc || this.dataset.asc === 'false';
            
            rows.sort(function(a, b) {
                var aVal = a.children[index] ? a.children[index].textContent.trim() : '';
                var bVal = b.children[index] ? b.children[index].textContent.trim() : '';
                
                // Check if numeric
                if (!isNaN(aVal) && !isNaN(bVal)) {
                    return ascending ? parseFloat(aVal) - parseFloat(bVal) : parseFloat(bVal) - parseFloat(aVal);
                }
                return ascending ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
            });
            
            rows.forEach(function(row) {
                tbody.appendChild(row);
            });
            
            this.dataset.asc = ascending ? 'false' : 'true';
        });
    });
});