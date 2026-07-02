(function() {
    // PRELOADER
    const preloader = document.getElementById('preloader');
    window.addEventListener('load', function() {
        preloader.classList.add('hidden');
    });
    setTimeout(() => { preloader.classList.add('hidden'); }, 2000);

    // SIDEBAR TOGGLE
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const toggleBtn = document.getElementById('menuToggle');

    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
    }
    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('active');
    }
    toggleBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        if (sidebar.classList.contains('open')) closeSidebar();
        else openSidebar();
    });
    overlay.addEventListener('click', closeSidebar);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && sidebar.classList.contains('open')) closeSidebar();
    });
    window.addEventListener('resize', () => {
        if (window.innerWidth > 820) {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        } else {
            if (sidebar.classList.contains('open')) overlay.classList.add('active');
            else overlay.classList.remove('active');
        }
    });

    // SIDEBAR DROPDOWNS
    document.querySelectorAll('.nav-dropdown-header').forEach(header => {
        header.addEventListener('click', function(e) {
            e.stopPropagation();
            const menu = this.nextElementSibling;
            this.classList.toggle('open');
            menu.classList.toggle('open');
        });
    });

    // SEARCH TOGGLE
    const searchToggle = document.getElementById('searchToggle');
    const searchForm = document.getElementById('searchForm');
    const searchWrapper = document.getElementById('searchWrapper');

    searchToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        searchForm.classList.toggle('open');
        if (searchForm.classList.contains('open')) {
            closeAllPanelsExcept('search');
        }
    });
    document.addEventListener('click', function(e) {
        if (!searchWrapper.contains(e.target)) {
            searchForm.classList.remove('open');
        }
    });

    // PROFILE DROPDOWN
    const profileTrigger = document.getElementById('profileTrigger');
    const profileDropdown = document.getElementById('profileDropdown');
    const profileWrapper = document.getElementById('profileWrapper');

    profileTrigger.addEventListener('click', function(e) {
        e.stopPropagation();
        this.classList.toggle('open');
        profileDropdown.classList.toggle('open');
        closeAllPanelsExcept('profile');
    });
    document.addEventListener('click', function(e) {
        if (!profileWrapper.contains(e.target)) {
            profileTrigger.classList.remove('open');
            profileDropdown.classList.remove('open');
        }
    });

    // NOTIFICATIONS & MESSAGES
    const notificationTrigger = document.getElementById('notificationTrigger');
    const notificationPanel = document.getElementById('notificationPanel');
    const notificationWrapper = document.getElementById('notificationWrapper');

    const messageTrigger = document.getElementById('messageTrigger');
    const messagePanel = document.getElementById('messagePanel');
    const messageWrapper = document.getElementById('messageWrapper');

    function closeAllPanelsExcept(except) {
        if (except !== 'search') searchForm.classList.remove('open');
        if (except !== 'notification') notificationPanel.classList.remove('open');
        if (except !== 'message') messagePanel.classList.remove('open');
        if (except !== 'profile') {
            profileTrigger.classList.remove('open');
            profileDropdown.classList.remove('open');
        }
    }

    notificationTrigger.addEventListener('click', function(e) {
        e.stopPropagation();
        const isOpen = notificationPanel.classList.contains('open');
        closeAllPanelsExcept('notification');
        if (!isOpen) notificationPanel.classList.add('open');
    });

    messageTrigger.addEventListener('click', function(e) {
        e.stopPropagation();
        const isOpen = messagePanel.classList.contains('open');
        closeAllPanelsExcept('message');
        if (!isOpen) messagePanel.classList.add('open');
    });

    document.addEventListener('click', function(e) {
        if (!notificationWrapper.contains(e.target)) notificationPanel.classList.remove('open');
        if (!messageWrapper.contains(e.target)) messagePanel.classList.remove('open');
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            searchForm.classList.remove('open');
            notificationPanel.classList.remove('open');
            messagePanel.classList.remove('open');
            profileTrigger.classList.remove('open');
            profileDropdown.classList.remove('open');
        }
    });
})();

// ============================================================
// SIDEBAR FUNCTIONALITY
// ============================================================

document.addEventListener('DOMContentLoaded', function() {
    // ============================================================
    // SIDEBAR COLLAPSE
    // ============================================================
    const sidebar = document.getElementById('sidebar');
    const collapseBtn = document.getElementById('sidebarCollapse');
    
    if (collapseBtn) {
        collapseBtn.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            // Save state to localStorage
            const isCollapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed);
        });
        
        // Restore state from localStorage
        const savedState = localStorage.getItem('sidebarCollapsed');
        if (savedState === 'true') {
            sidebar.classList.add('collapsed');
        }
    }

    // ============================================================
    // SIDEBAR DROPDOWNS
    // ============================================================
    const dropdownHeaders = document.querySelectorAll('.nav-dropdown-header');
    
    dropdownHeaders.forEach(header => {
        header.addEventListener('click', function(e) {
            e.stopPropagation();
            const parent = this.closest('.nav-dropdown');
            const isOpen = parent.classList.contains('open');
            
            // Close all other dropdowns in the same group
            const group = parent.closest('.nav-section');
            if (group) {
                const allDropdowns = group.querySelectorAll('.nav-dropdown');
                allDropdowns.forEach(dd => {
                    if (dd !== parent) {
                        dd.classList.remove('open');
                    }
                });
            }
            
            // Toggle current dropdown
            parent.classList.toggle('open');
        });
    });

    // ============================================================
    // CLOSE DROPDOWNS ON OUTSIDE CLICK
    // ============================================================
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.nav-dropdown')) {
            document.querySelectorAll('.nav-dropdown.open').forEach(dd => {
                dd.classList.remove('open');
            });
        }
    });

    // ============================================================
    // MOBILE SIDEBAR TOGGLE
    // ============================================================
    const menuToggle = document.getElementById('menuToggle');
    const overlay = document.getElementById('overlay');
    
    if (menuToggle && overlay) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
        });
        
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        });
    }

    // ============================================================
    // KEYBOARD SHORTCUTS
    // ============================================================
    document.addEventListener('keydown', function(e) {
        // Ctrl+B to toggle sidebar
        if (e.ctrlKey && e.key === 'b') {
            e.preventDefault();
            if (collapseBtn) {
                collapseBtn.click();
            }
        }
        
        // Escape to close mobile sidebar
        if (e.key === 'Escape' && sidebar.classList.contains('open')) {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    });

    // ============================================================
    // LOGOUT CONFIRMATION
    // ============================================================
    window.confirmLogout = function() {
        if (confirm('Are you sure you want to logout?')) {
            window.location.href = 'logout.php';
        }
    };
});

// ============================================================
// HOVER EXPAND FOR COLLAPSED SIDEBAR
// ============================================================
document.addEventListener('mouseenter', function(e) {
    const sidebar = document.getElementById('sidebar');
    if (sidebar && sidebar.classList.contains('collapsed')) {
        const target = e.target.closest('.nav-dropdown');
        if (target) {
            const menu = target.querySelector('.nav-dropdown-menu');
            if (menu) {
                // Position the dropdown menu
                const rect = target.getBoundingClientRect();
                menu.style.top = rect.top + 'px';
                menu.style.left = (rect.right) + 'px';
            }
        }
    }
}, true);

// ============================================================
// USER MODAL FUNCTIONS
// ============================================================

function showAddUser() {
    const modal = document.getElementById('userModal');
    modal.classList.add('active');
    document.getElementById('modalTitleText').textContent = 'Add User';
    document.getElementById('formAction').value = 'add_user';
    document.getElementById('userId').value = '0';
    document.getElementById('submitText').textContent = 'Add User';
    document.getElementById('userForm').reset();
    document.getElementById('status').value = 'active';
    document.body.style.overflow = 'hidden';
}

function closeUserModal() {
    document.getElementById('userModal').classList.remove('active');
    document.body.style.overflow = '';
}

// ============================================================
// SUBSCRIPTION MODAL FUNCTIONS
// ============================================================

function showAddSubscription(tenantId = null) {
    const modal = document.getElementById('subscriptionModal');
    modal.classList.add('active');
    document.getElementById('subModalTitle').textContent = 'Add Subscription';
    document.getElementById('subFormAction').value = 'add_subscription';
    document.getElementById('subscriptionId').value = '0';
    document.getElementById('subSubmitText').textContent = 'Add Subscription';
    document.getElementById('subscriptionForm').reset();
    document.getElementById('sub_auto_renew').checked = true;
    document.getElementById('sub_amount').value = '';
    document.getElementById('sub_payment_status').value = 'pending';
    
    // Set default dates
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('sub_start_date').value = today;
    const endDate = new Date();
    endDate.setMonth(endDate.getMonth() + 1);
    document.getElementById('sub_end_date').value = endDate.toISOString().split('T')[0];
    
    if (tenantId) {
        document.getElementById('sub_tenant_id').value = tenantId;
        document.getElementById('sub_tenant_id').disabled = true;
    } else {
        document.getElementById('sub_tenant_id').disabled = false;
    }
    
    document.body.style.overflow = 'hidden';
}

function closeSubscriptionModal() {
    document.getElementById('subscriptionModal').classList.remove('active');
    document.getElementById('sub_tenant_id').disabled = false;
    document.body.style.overflow = '';
}

function updateSubscriptionAmount() {
    const plan = document.getElementById('sub_plan').value;
    const cycle = document.getElementById('sub_billing_cycle').value;
    const amountField = document.getElementById('sub_amount');
    
    const planPrices = {
        'free': 0,
        'basic': 50,
        'standard': 150,
        'premium': 350,
        'enterprise': 750
    };
    
    const cycleMultipliers = {
        'monthly': 1,
        'quarterly': 3,
        'yearly': 12
    };
    
    if (plan && cycle) {
        const price = planPrices[plan] || 0;
        const multiplier = cycleMultipliers[cycle] || 1;
        amountField.value = (price * multiplier).toFixed(2);
    } else {
        amountField.value = '';
    }
}

// ============================================================
// CLOSE MODALS ON ESCAPE
// ============================================================
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeUserModal();
        closeSubscriptionModal();
        closeViewUserModal();
    }
});

// ============================================================
// CLOSE MODALS ON OVERLAY CLICK
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    // User modal overlay
    document.querySelector('#userModal .modal-overlay')?.addEventListener('click', closeUserModal);
    
    // Subscription modal overlay
    document.querySelector('#subscriptionModal .modal-overlay')?.addEventListener('click', closeSubscriptionModal);
    
    // View user modal overlay
    document.querySelector('#viewUserModal .modal-overlay')?.addEventListener('click', closeViewUserModal);
});

// ============================================================
// PREVENT BODY SCROLL WHEN MODAL IS OPEN
// ============================================================
// This is handled in the show/close functions above

// ============================================================
// HEADER/SIDEBAR INTERACTIONS
// ============================================================

document.addEventListener('DOMContentLoaded', function() {
    // ============================================================
    // NOTIFICATION PANEL
    // ============================================================
    const notificationTrigger = document.getElementById('notificationTrigger');
    const notificationPanel = document.getElementById('notificationPanel');
    const notificationWrapper = document.getElementById('notificationWrapper');

    if (notificationTrigger) {
        notificationTrigger.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationPanel.classList.toggle('open');
            // Close other panels
            if (notificationPanel.classList.contains('open')) {
                document.getElementById('messagePanel')?.classList.remove('open');
                document.getElementById('profileDropdown')?.classList.remove('open');
            }
        });
    }

    // ============================================================
    // MESSAGE PANEL
    // ============================================================
    const messageTrigger = document.getElementById('messageTrigger');
    const messagePanel = document.getElementById('messagePanel');
    const messageWrapper = document.getElementById('messageWrapper');

    if (messageTrigger) {
        messageTrigger.addEventListener('click', function(e) {
            e.stopPropagation();
            messagePanel.classList.toggle('open');
            // Close other panels
            if (messagePanel.classList.contains('open')) {
                document.getElementById('notificationPanel')?.classList.remove('open');
                document.getElementById('profileDropdown')?.classList.remove('open');
            }
        });
    }

    // ============================================================
    // PROFILE DROPDOWN
    // ============================================================
    const profileTrigger = document.getElementById('profileTrigger');
    const profileDropdown = document.getElementById('profileDropdown');
    const profileWrapper = document.getElementById('profileWrapper');

    if (profileTrigger) {
        profileTrigger.addEventListener('click', function(e) {
            e.stopPropagation();
            profileTrigger.classList.toggle('open');
            profileDropdown.classList.toggle('open');
            // Close other panels
            if (profileDropdown.classList.contains('open')) {
                document.getElementById('notificationPanel')?.classList.remove('open');
                document.getElementById('messagePanel')?.classList.remove('open');
            }
        });
    }

    // ============================================================
    // CLOSE PANELS ON OUTSIDE CLICK
    // ============================================================
    document.addEventListener('click', function(e) {
        // Close notification panel
        if (notificationWrapper && !notificationWrapper.contains(e.target)) {
            notificationPanel?.classList.remove('open');
        }
        // Close message panel
        if (messageWrapper && !messageWrapper.contains(e.target)) {
            messagePanel?.classList.remove('open');
        }
        // Close profile dropdown
        if (profileWrapper && !profileWrapper.contains(e.target)) {
            profileTrigger?.classList.remove('open');
            profileDropdown?.classList.remove('open');
        }
    });

    // ============================================================
    // CLOSE PANELS ON ESCAPE
    // ============================================================
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            notificationPanel?.classList.remove('open');
            messagePanel?.classList.remove('open');
            profileTrigger?.classList.remove('open');
            profileDropdown?.classList.remove('open');
        }
    });

    // ============================================================
    // SEARCH TOGGLE
    // ============================================================
    const searchToggle = document.getElementById('searchToggle');
    const searchForm = document.getElementById('searchForm');
    const searchWrapper = document.getElementById('searchWrapper');

    if (searchToggle) {
        searchToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            searchForm.classList.toggle('open');
        });
    }

    document.addEventListener('click', function(e) {
        if (searchWrapper && !searchWrapper.contains(e.target)) {
            searchForm?.classList.remove('open');
        }
    });

    // ============================================================
    // LOGOUT CONFIRMATION
    // ============================================================
    window.confirmLogout = function() {
        if (confirm('Are you sure you want to logout?')) {
            window.location.href = 'logout.php';
        }
    };

    // ============================================================
    // SIDEBAR TOGGLE (Mobile)
    // ============================================================
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');

    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
            if (overlay) {
                overlay.classList.toggle('active');
            }
            document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
        });

        // Close sidebar on overlay click
        if (overlay) {
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            });
        }
    }

    // ============================================================
    // SIDEBAR COLLAPSE
    // ============================================================
    const collapseBtn = document.getElementById('sidebarCollapse');
    if (collapseBtn) {
        collapseBtn.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            // Save state to localStorage
            const isCollapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed);
        });

        // Restore state from localStorage
        const savedState = localStorage.getItem('sidebarCollapsed');
        if (savedState === 'true') {
            sidebar.classList.add('collapsed');
        }
    }

    // ============================================================
    // SIDEBAR DROPDOWNS
    // ============================================================
    const dropdownHeaders = document.querySelectorAll('.nav-dropdown-header');
    dropdownHeaders.forEach(header => {
        header.addEventListener('click', function(e) {
            e.stopPropagation();
            const parent = this.closest('.nav-dropdown');
            const isOpen = parent.classList.contains('open');
            
            // Close all other dropdowns
            document.querySelectorAll('.nav-dropdown.open').forEach(dd => {
                if (dd !== parent) {
                    dd.classList.remove('open');
                }
            });
            
            parent.classList.toggle('open');
        });
    });

    // Close dropdowns on outside click
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.nav-dropdown')) {
            document.querySelectorAll('.nav-dropdown.open').forEach(dd => {
                dd.classList.remove('open');
            });
        }
    });

    // ============================================================
    // KEYBOARD SHORTCUT
    // ============================================================
    document.addEventListener('keydown', function(e) {
        // Ctrl+B to toggle sidebar collapse
        if (e.ctrlKey && e.key === 'b') {
            e.preventDefault();
            if (collapseBtn) {
                collapseBtn.click();
            }
        }
        
        // Ctrl+K to focus search
        if (e.ctrlKey && e.key === 'k') {
            e.preventDefault();
            const searchInput = document.querySelector('#searchForm input');
            if (searchInput) {
                searchInput.focus();
                searchForm?.classList.add('open');
            }
        }
    });
});