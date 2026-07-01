<?php
$page_title = "Documentation";
include 'includes/base.php'; ?>
<!-- ===== PRELOADER ===== -->
<div class="preloader" id="preloader">
    <div class="loader-ring"></div>
</div>

<?php include 'includes/navbar.php'; ?>

<!-- ===== PAGE HEADER ===== -->
<section class="page-header">
    <div class="container">
        <span class="badge"><i class="fas fa-file-alt"></i> Resources</span>
        <h1>Documentation</h1>
        <p>Comprehensive guides, API references, and resources to help you get started.</p>
    </div>
</section>

<!-- ===== DOCUMENTATION CONTENT ===== -->
<section class="docs-page">
    <div class="container">
        <div class="docs-grid">
            <div class="doc-card">
                <i class="fas fa-book"></i>
                <h4>Getting Started</h4>
                <p>Quick start guide to set up your organization, create elections, and deploy agents.</p>
                <a href="#" class="btn btn-primary btn-sm">Read Guide</a>
            </div>
            <div class="doc-card">
                <i class="fas fa-code"></i>
                <h4>API Reference</h4>
                <p>Complete API documentation for integration with your existing systems and applications.</p>
                <a href="#" class="btn btn-primary btn-sm">View API</a>
            </div>
            <div class="doc-card">
                <i class="fas fa-user-cog"></i>
                <h4>Admin Guide</h4>
                <p>Administrative guide covering user management, role configuration, and system settings.</p>
                <a href="#" class="btn btn-primary btn-sm">Read Guide</a>
            </div>
            <div class="doc-card">
                <i class="fas fa-mobile-alt"></i>
                <h4>Mobile App Guide</h4>
                <p>Setup and usage guide for the mobile agent application for offline data collection.</p>
                <a href="#" class="btn btn-primary btn-sm">Read Guide</a>
            </div>
            <div class="doc-card">
                <i class="fas fa-chart-pie"></i>
                <h4>Reports & Analytics</h4>
                <p>Guide to creating custom reports, dashboards, and data visualizations.</p>
                <a href="#" class="btn btn-primary btn-sm">Read Guide</a>
            </div>
            <div class="doc-card">
                <i class="fas fa-shield-alt"></i>
                <h4>Security Compliance</h4>
                <p>Security best practices, compliance guidelines, and data protection policies.</p>
                <a href="#" class="btn btn-primary btn-sm">Read Guide</a>
            </div>
            <div class="doc-card">
                <i class="fas fa-plug"></i>
                <h4>Integration Guides</h4>
                <p>Guides for integrating with third-party systems, data import/export, and workflows.</p>
                <a href="#" class="btn btn-primary btn-sm">Read Guide</a>
            </div>
            <div class="doc-card">
                <i class="fas fa-question-circle"></i>
                <h4>FAQ</h4>
                <p>Frequently asked questions about installation, usage, and troubleshooting.</p>
                <a href="#" class="btn btn-primary btn-sm">View FAQ</a>
            </div>
            <div class="doc-card">
                <i class="fas fa-video"></i>
                <h4>Video Tutorials</h4>
                <p>Step-by-step video tutorials covering all platform features and capabilities.</p>
                <a href="#" class="btn btn-primary btn-sm">Watch Videos</a>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>