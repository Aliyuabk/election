  (function() {
            // PRELOADER
            const preloader = document.getElementById('preloader');
            window.addEventListener('load', function() {
                preloader.classList.add('hidden');
            });
            setTimeout(() => { preloader.classList.add('hidden'); }, 2000);

            // SIDEBAR TOGGLE (mobile)
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

            // SIDEBAR DROPDOWN
            const dropdownHeader = document.getElementById('sidebarDropdownToggle');
            const dropdownMenu = document.getElementById('sidebarDropdownMenu');
            dropdownHeader.addEventListener('click', function(e) {
                e.stopPropagation();
                this.classList.toggle('open');
                dropdownMenu.classList.toggle('open');
            });
            document.addEventListener('click', function(e) {
                if (!dropdownHeader.contains(e.target) && !dropdownMenu.contains(e.target)) {
                    dropdownHeader.classList.remove('open');
                    dropdownMenu.classList.remove('open');
                }
            });

            // SEARCH: toggle form on icon click
            const searchToggle = document.getElementById('searchToggle');
            const searchForm = document.getElementById('searchForm');
            const searchWrapper = document.getElementById('searchWrapper');

            searchToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                searchForm.classList.toggle('open');
                // close other panels when search opens
                if (searchForm.classList.contains('open')) {
                    closeAllPanelsExcept('search');
                }
            });

            // close search when clicking outside
            document.addEventListener('click', function(e) {
                if (!searchWrapper.contains(e.target)) {
                    searchForm.classList.remove('open');
                }
            });

            // PROFILE DROPDOWN
            const profileTrigger = document.getElementById('profileTrigger');
            const profileDropdown = document.getElementById('profileDropdown');
            const profileWrapper = document.getElementById('profileWrapper');

            function toggleProfile(e) {
                e.stopPropagation();
                profileTrigger.classList.toggle('open');
                profileDropdown.classList.toggle('open');
                closeAllPanelsExcept('profile');
            }
            profileTrigger.addEventListener('click', toggleProfile);
            document.addEventListener('click', function(e) {
                if (!profileWrapper.contains(e.target)) {
                    profileTrigger.classList.remove('open');
                    profileDropdown.classList.remove('open');
                }
            });

            // HEADER PANELS: Notifications & Messages
            const notificationTrigger = document.getElementById('notificationTrigger');
            const notificationPanel = document.getElementById('notificationPanel');
            const notificationWrapper = document.getElementById('notificationWrapper');

            const messageTrigger = document.getElementById('messageTrigger');
            const messagePanel = document.getElementById('messagePanel');
            const messageWrapper = document.getElementById('messageWrapper');

            function closeAllPanelsExcept(except) {
                if (except !== 'search') {
                    searchForm.classList.remove('open');
                }
                if (except !== 'notification') {
                    notificationPanel.classList.remove('open');
                }
                if (except !== 'message') {
                    messagePanel.classList.remove('open');
                }
                if (except !== 'profile') {
                    profileTrigger.classList.remove('open');
                    profileDropdown.classList.remove('open');
                }
            }

            function togglePanel(trigger, panel, wrapper, name) {
                return function(e) {
                    e.stopPropagation();
                    const isOpen = panel.classList.contains('open');
                    closeAllPanelsExcept(name);
                    if (!isOpen) {
                        panel.classList.add('open');
                    }
                    if (isOpen) {
                        panel.classList.remove('open');
                    }
                };
            }

            notificationTrigger.addEventListener('click', togglePanel(notificationTrigger, notificationPanel, notificationWrapper, 'notification'));
            messageTrigger.addEventListener('click', togglePanel(messageTrigger, messagePanel, messageWrapper, 'message'));

            // close panels when clicking outside
            document.addEventListener('click', function(e) {
                if (!notificationWrapper.contains(e.target)) {
                    notificationPanel.classList.remove('open');
                }
                if (!messageWrapper.contains(e.target)) {
                    messagePanel.classList.remove('open');
                }
            });

            // close on escape
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