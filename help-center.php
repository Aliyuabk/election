<?php
$page_title = "Help Center";
include 'includes/base.php'; ?>
<!-- ===== PRELOADER ===== -->
<div class="preloader" id="preloader">
    <div class="loader-ring"></div>
</div>

<?php include 'includes/navbar.php'; ?>

<!-- ===== PAGE HEADER ===== -->
<section class="page-header">
    <div class="container">
        <span class="badge"><i class="fas fa-life-ring"></i> Support</span>
        <h1>Help Center</h1>
        <p>Find answers to common questions and get the support you need.</p>
    </div>
</section>

<!-- ===== HELP CENTER CONTENT ===== -->
<section class="help-page">
    <div class="container">
        <!-- Search -->
        <div class="help-search">
            <div class="search-wrapper">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search for help articles..." />
                <button class="btn btn-primary">Search</button>
            </div>
        </div>

        <!-- Quick Help Categories -->
        <div class="help-categories">
            <h2>Browse Help Topics</h2>
            <div class="categories-grid">
                <a href="#" class="category-card">
                    <i class="fas fa-rocket"></i>
                    <h4>Getting Started</h4>
                    <p>Setup your organization and first election</p>
                </a>
                <a href="#" class="category-card">
                    <i class="fas fa-user-cog"></i>
                    <h4>Administration</h4>
                    <p>User management, roles, and settings</p>
                </a>
                <a href="#" class="category-card">
                    <i class="fas fa-vote-yea"></i>
                    <h4>Election Management</h4>
                    <p>Create and manage elections</p>
                </a>
                <a href="#" class="category-card">
                    <i class="fas fa-mobile-alt"></i>
                    <h4>Mobile Agent App</h4>
                    <p>Setup and use the mobile application</p>
                </a>
                <a href="#" class="category-card">
                    <i class="fas fa-chart-pie"></i>
                    <h4>Reports & Analytics</h4>
                    <p>Generate and interpret reports</p>
                </a>
                <a href="#" class="category-card">
                    <i class="fas fa-shield-alt"></i>
                    <h4>Security & Privacy</h4>
                    <p>Security best practices and compliance</p>
                </a>
            </div>
        </div>

        <!-- FAQ Section -->
        <div class="faq-section">
            <h2>Frequently Asked Questions</h2>
            
            <div class="faq-item">
                <button class="faq-question" onclick="toggleFaq(this)">
                    <span>How do I create a new election?</span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="faq-answer">
                    <p>To create a new election:</p>
                    <ol>
                        <li>Log in to your dashboard</li>
                        <li>Navigate to "Elections" in the main menu</li>
                        <li>Click "Create New Election"</li>
                        <li>Fill in the election details (name, type, date, etc.)</li>
                        <li>Define polling units and assign agents</li>
                        <li>Review and publish the election</li>
                    </ol>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleFaq(this)">
                    <span>How do I add polling units?</span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="faq-answer">
                    <p>You can add polling units in two ways:</p>
                    <ul>
                        <li><strong>Bulk Import:</strong> Upload a CSV/Excel file with polling unit data</li>
                        <li><strong>Manual Entry:</strong> Add polling units individually through the interface</li>
                    </ul>
                    <p>Both options are available under "Polling Units" in the main menu.</p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleFaq(this)">
                    <span>How does offline data collection work?</span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="faq-answer">
                    <p>Our offline data collection feature allows agents to:</p>
                    <ul>
                        <li>Download election data before going offline</li>
                        <li>Collect results and observations without internet</li>
                        <li>Store data locally on their device</li>
                        <li>Automatically sync when back online</li>
                        <li>Resolve conflicts automatically</li>
                    </ul>
                    <p>All data is encrypted on the device for security.</p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleFaq(this)">
                    <span>How do I generate reports?</span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="faq-answer">
                    <p>To generate reports:</p>
                    <ol>
                        <li>Go to "Reports & Analytics" in the dashboard</li>
                        <li>Select the report type (summary, detailed, graphical, etc.)</li>
                        <li>Choose your filters (date range, regions, etc.)</li>
                        <li>Click "Generate Report"</li>
                        <li>Export in PDF, Excel, or CSV format</li>
                    </ol>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleFaq(this)">
                    <span>How do I add new users or agents?</span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="faq-answer">
                    <p>To add new users:</p>
                    <ol>
                        <li>Navigate to "User Management"</li>
                        <li>Click "Add New User"</li>
                        <li>Fill in user details (name, email, role, etc.)</li>
                        <li>Assign permissions based on role</li>
                        <li>Send an invitation email</li>
                        <li>User will receive login credentials</li>
                    </ol>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleFaq(this)">
                    <span>What security measures are in place?</span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="faq-answer">
                    <p>5G Election Guru implements enterprise-grade security including:</p>
                    <ul>
                        <li>AES-256 encryption for all data</li>
                        <li>Two-Factor Authentication (2FA)</li>
                        <li>Role-Based Access Control (RBAC)</li>
                        <li>Regular security audits</li>
                        <li>NDPA compliance</li>
                        <li>Secure cloud infrastructure</li>
                    </ul>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-question" onclick="toggleFaq(this)">
                    <span>How do I contact support?</span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="faq-answer">
                    <p>You can reach our support team through:</p>
                    <ul>
                        <li><strong>Email:</strong> support@5gguru.ng</li>
                        <li><strong>Phone:</strong> +234 800 555 5G55</li>
                        <li><strong>Live Chat:</strong> Available on the platform</li>
                        <li><strong>Help Center:</strong> Browse articles and guides</li>
                    </ul>
                    <p>We typically respond within 2-4 hours during business hours.</p>
                </div>
            </div>
        </div>

        <!-- Contact Support -->
        <div class="contact-support">
            <div class="support-card">
                <i class="fas fa-headset"></i>
                <h3>Still need help?</h3>
                <p>Our support team is ready to assist you with any questions or issues.</p>
                <div style="display:flex; gap:16px; flex-wrap:wrap; justify-content:center; margin-top:16px;">
                    <a href="#" class="btn btn-primary">Live Chat</a>
                    <a href="#" class="btn btn-outline">Email Support</a>
                    <a href="#" class="btn btn-outline">Schedule Call</a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>