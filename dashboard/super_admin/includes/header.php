 <header class="header">
            <div class="header-left">
                <button class="menu-toggle" id="menuToggle" aria-label="Toggle sidebar">
                    <i class="fas fa-bars"></i>
                </button>
                
                <!-- SEARCH: icon + expandable form -->
                <div class="search-wrapper" id="searchWrapper">
                    <button class="search-icon-btn" id="searchToggle" aria-label="Search">
                        <i class="fas fa-search"></i>
                    </button>
                    <div class="search-form-container" id="searchForm">
                        <i class="fas fa-search" style="color:#889fc0;"></i>
                        <input type="text" placeholder="Search anything..." aria-label="Search">
                        <span class="search-shortcut">⌘K</span>
                    </div>
                </div>
            </div>

            <div class="header-right">
                <!-- NOTIFICATIONS -->
                <div class="icon-wrapper" id="notificationWrapper">
                    <button class="header-icon" id="notificationTrigger" aria-label="Notifications">
                        <i class="far fa-bell"></i>
                        <span class="badge">4</span>
                    </button>
                    <div class="header-dropdown-panel" id="notificationPanel">
                        <div class="panel-header">
                            Notifications
                            <small>3 new</small>
                        </div>
                        <div class="panel-item">
                            <div class="icon-circle"><i class="fas fa-user-plus"></i></div>
                            <div class="content">
                                <div class="title">New user registered</div>
                                <div class="desc">Sarah Chen created an account</div>
                            </div>
                            <div class="time">2m</div>
                        </div>
                        <div class="panel-item">
                            <div class="icon-circle"><i class="fas fa-chart-line"></i></div>
                            <div class="content">
                                <div class="title">Weekly report ready</div>
                                <div class="desc">Q2 performance summary</div>
                            </div>
                            <div class="time">1h</div>
                        </div>
                        <div class="panel-item">
                            <div class="icon-circle"><i class="fas fa-check-circle" style="color:#2e9c5a;"></i></div>
                            <div class="content">
                                <div class="title">Deployment successful</div>
                                <div class="desc">v3.1.0 live on production</div>
                            </div>
                            <div class="time">3h</div>
                        </div>
                        <div class="panel-footer">View all notifications</div>
                    </div>
                </div>

                <!-- MESSAGES -->
                <div class="icon-wrapper" id="messageWrapper">
                    <button class="header-icon" id="messageTrigger" aria-label="Messages">
                        <i class="far fa-comment-dots"></i>
                        <span class="badge">5</span>
                    </button>
                    <div class="header-dropdown-panel" id="messagePanel">
                        <div class="panel-header">
                            Messages
                            <small>2 unread</small>
                        </div>
                        <div class="panel-item">
                            <div class="icon-circle"><i class="fas fa-user-circle"></i></div>
                            <div class="content">
                                <div class="title">Elena V.</div>
                                <div class="desc">Hey, can we schedule a call for tomorrow?</div>
                            </div>
                            <div class="time">5m</div>
                        </div>
                        <div class="panel-item">
                            <div class="icon-circle"><i class="fas fa-user-circle"></i></div>
                            <div class="content">
                                <div class="title">Marcus T.</div>
                                <div class="desc">I've updated the project files, please review.</div>
                            </div>
                            <div class="time">1h</div>
                        </div>
                        <div class="panel-item">
                            <div class="icon-circle"><i class="fas fa-user-circle"></i></div>
                            <div class="content">
                                <div class="title">Design Team</div>
                                <div class="desc">New mockups are ready for feedback.</div>
                            </div>
                            <div class="time">3h</div>
                        </div>
                        <div class="panel-footer">View all messages</div>
                    </div>
                </div>

                <!-- PROFILE -->
                <div class="profile-wrapper" id="profileWrapper">
                    <button class="profile-trigger" id="profileTrigger" aria-label="Profile menu">
                        <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='40' height='40' viewBox='0 0 40 40'%3E%3Ccircle cx='20' cy='20' r='18' fill='%23b3cef0'/%3E%3Ccircle cx='20' cy='14' r='6' fill='%23577a9e'/%3E%3Cpath d='M8 32c0-6 5-10 12-10s12 4 12 10' fill='%23577a9e'/%3E%3C/svg%3E" alt="avatar">
                        <div class="avatar-info">
                            <div class="name">Alex R.</div>
                            <div class="role">Administrator</div>
                        </div>
                        <i class="fas fa-chevron-down chevron"></i>
                    </button>
                    <div class="profile-dropdown" id="profileDropdown">
                        <div class="dd-item"><i class="fas fa-user-circle"></i> My Profile</div>
                        <div class="dd-item"><i class="fas fa-cog"></i> Settings</div>
                        <div class="dd-item"><i class="fas fa-shield-alt"></i> Privacy</div>
                        <hr class="dd-divider">
                        <div class="dd-item logout"><i class="fas fa-sign-out-alt"></i> Logout</div>
                    </div>
                </div>
            </div>
        </header>