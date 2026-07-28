<?php
// ============================================================
// CITIZEN PORTAL - HOME DASHBOARD
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/functions.php';

$page_title = 'Home';
$current_page = 'home';

// Get current/active election
$db = getDB();
$active_election = null;
$election_stats = [];
$latest_news = [];
$candidate_count = 0;
$party_count = 0;
$total_states = 0;
$total_lgas = 0;
$total_wards = 0;
$total_pus = 0;
$published_results = [];

try {
    // Get active election
    $stmt = $db->prepare("
        SELECT * FROM elections 
        WHERE status IN ('active', 'closed')
        ORDER BY election_date DESC 
        LIMIT 1
    ");
    $stmt->execute();
    $active_election = $stmt->fetch();
    
    // Get statistics
    $stmt = $db->prepare("
        SELECT 
            (SELECT COUNT(*) FROM states WHERE is_active = 1) as total_states,
            (SELECT COUNT(*) FROM lgas WHERE is_active = 1) as total_lgas,
            (SELECT COUNT(*) FROM wards WHERE is_active = 1) as total_wards,
            (SELECT COUNT(*) FROM polling_units WHERE is_active = 1) as total_pus,
            (SELECT COUNT(*) FROM candidates) as total_candidates,
            (SELECT COUNT(*) FROM political_parties) as total_parties
    ");
    $stmt->execute();
    $stats = $stmt->fetch();
    $total_states = (int)($stats['total_states'] ?? 0);
    $total_lgas = (int)($stats['total_lgas'] ?? 0);
    $total_wards = (int)($stats['total_wards'] ?? 0);
    $total_pus = (int)($stats['total_pus'] ?? 0);
    $candidate_count = (int)($stats['total_candidates'] ?? 0);
    $party_count = (int)($stats['total_parties'] ?? 0);
    
    // Get published results summary
    if ($active_election) {
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_results,
                COALESCE(SUM(valid_votes), 0) as total_valid,
                COALESCE(SUM(rejected_votes), 0) as total_rejected,
                COALESCE(SUM(total_votes), 0) as total_votes
            FROM public_results 
            WHERE election_id = ? AND is_published = 1
        ");
        $stmt->execute([$active_election['id']]);
        $published_results = $stmt->fetch();
    }
    
    // Get latest announcements
    $stmt = $db->prepare("
        SELECT * FROM broadcasts 
        WHERE status = 'sent' 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $latest_news = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Citizen portal error: " . $e->getMessage());
}

include '../includes/public-header.php';
?>

<style>
/* Hero Section */
.hero-section {
    background: linear-gradient(135deg, #0F4C81 0%, #1a6db5 100%);
    color: white;
    padding: 50px 0 40px 0;
    margin-bottom: 30px;
    border-radius: 0 0 30px 30px;
}
.hero-section .hero-title {
    font-size: 2rem;
    font-weight: 800;
    margin-bottom: 10px;
}
.hero-section .hero-subtitle {
    font-size: 1rem;
    opacity: 0.9;
    max-width: 600px;
}
.hero-section .election-status {
    display: inline-block;
    background: rgba(255,255,255,0.15);
    padding: 6px 20px;
    border-radius: 30px;
    font-size: 0.85rem;
    font-weight: 600;
    margin-top: 10px;
}
.hero-section .election-status .dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    margin-right: 8px;
    animation: pulse 1.5s infinite;
}
.hero-section .election-status .dot.active { background: #10B981; }
.hero-section .election-status .dot.closed { background: #6B7280; }
.hero-section .election-status .dot.upcoming { background: #F59E0B; }

@keyframes pulse {
    0% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(0.9); }
    100% { opacity: 1; transform: scale(1); }
}

/* Search Bar */
.search-section {
    margin-top: -25px;
    margin-bottom: 30px;
}
.search-bar {
    background: white;
    border-radius: 16px;
    padding: 8px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.12);
    display: flex;
    gap: 8px;
    max-width: 700px;
    margin: 0 auto;
}
.search-bar input {
    flex: 1;
    padding: 14px 20px;
    border: none;
    border-radius: 12px;
    font-size: 1rem;
    outline: none;
}
.search-bar input::placeholder {
    color: #9CA3AF;
}
.search-bar button {
    padding: 14px 32px;
    background: #0F4C81;
    color: white;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.3s;
    white-space: nowrap;
}
.search-bar button:hover {
    background: #1a6db5;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 14px;
    margin-bottom: 30px;
}
.stat-card {
    background: white;
    border-radius: 14px;
    padding: 18px 14px;
    text-align: center;
    border: 1px solid #E5E7EB;
    transition: transform 0.2s, box-shadow 0.2s;
}
.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
}
.stat-card .stat-icon {
    font-size: 1.4rem;
    margin-bottom: 4px;
    display: block;
}
.stat-card .stat-number {
    font-size: 1.4rem;
    font-weight: 700;
    color: #0F4C81;
}
.stat-card .stat-label {
    font-size: 0.65rem;
    color: #6B7280;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Quick Actions */
.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 14px;
    margin-bottom: 30px;
}
.quick-action {
    background: white;
    border-radius: 14px;
    padding: 20px 16px;
    text-align: center;
    border: 1px solid #E5E7EB;
    text-decoration: none;
    color: #1F2937;
    transition: all 0.3s;
}
.quick-action:hover {
    border-color: #0F4C81;
    box-shadow: 0 8px 25px rgba(15, 76, 129, 0.1);
    transform: translateY(-4px);
}
.quick-action .action-icon {
    font-size: 1.8rem;
    margin-bottom: 6px;
    display: block;
}
.quick-action .action-title {
    font-weight: 600;
    font-size: 0.85rem;
}
.quick-action .action-desc {
    font-size: 0.7rem;
    color: #6B7280;
    margin-top: 2px;
}

/* News Section */
.news-section {
    background: white;
    border-radius: 14px;
    padding: 20px;
    border: 1px solid #E5E7EB;
}
.news-section .section-title {
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.news-item {
    padding: 12px 0;
    border-bottom: 1px solid #F3F4F6;
}
.news-item:last-child {
    border-bottom: none;
}
.news-item .news-title {
    font-weight: 600;
    font-size: 0.85rem;
}
.news-item .news-meta {
    font-size: 0.65rem;
    color: #9CA3AF;
    margin-top: 4px;
    display: flex;
    gap: 12px;
}
.news-item .news-meta .tag {
    background: #F3F4F6;
    padding: 1px 10px;
    border-radius: 12px;
    font-size: 0.6rem;
    color: #6B7280;
}

@media (max-width: 768px) {
    .hero-section .hero-title {
        font-size: 1.5rem;
    }
    .search-bar {
        flex-direction: column;
    }
    .search-bar button {
        width: 100%;
        justify-content: center;
    }
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .quick-actions {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 480px) {
    .quick-actions {
        grid-template-columns: 1fr 1fr;
    }
    .stats-grid {
        grid-template-columns: 1fr 1fr;
    }
}
</style>

<div class="container">
    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container">
            <div class="hero-title">
                <i class="fas fa-vote-yea"></i> Election Monitoring Portal
            </div>
            <div class="hero-subtitle">
                Transparent access to published election results, polling unit information, and candidate profiles.
            </div>
            <?php if ($active_election): ?>
                <div class="election-status">
                    <span class="dot <?php echo $active_election['status']; ?>"></span>
                    <?php echo htmlspecialchars($active_election['name']); ?>
                    <span style="margin-left:12px;opacity:0.7;">
                        <?php echo date('M d, Y', strtotime($active_election['election_date'])); ?>
                    </span>
                    <span style="margin-left:12px;background:rgba(255,255,255,0.15);padding:2px 12px;border-radius:20px;text-transform:capitalize;">
                        <?php echo $active_election['status']; ?>
                    </span>
                </div>
            <?php else: ?>
                <div class="election-status">
                    <span class="dot upcoming"></span>
                    No active election at this time
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="search-section">
        <form action="search-polling-units.php" method="GET" class="search-bar">
            <input type="text" name="q" placeholder="🔍 Search polling units, states, or LGAs..." required>
            <button type="submit">Search</button>
        </form>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-icon">🗳️</span>
            <div class="stat-number"><?php echo number_format($total_states); ?></div>
            <div class="stat-label">States</div>
        </div>
        <div class="stat-card">
            <span class="stat-icon">🏛️</span>
            <div class="stat-number"><?php echo number_format($total_lgas); ?></div>
            <div class="stat-label">LGAs</div>
        </div>
        <div class="stat-card">
            <span class="stat-icon">📍</span>
            <div class="stat-number"><?php echo number_format($total_pus); ?></div>
            <div class="stat-label">Polling Units</div>
        </div>
        <div class="stat-card">
            <span class="stat-icon">👤</span>
            <div class="stat-number"><?php echo number_format($candidate_count); ?></div>
            <div class="stat-label">Candidates</div>
        </div>
        <div class="stat-card">
            <span class="stat-icon">🎯</span>
            <div class="stat-number"><?php echo number_format($party_count); ?></div>
            <div class="stat-label">Parties</div>
        </div>
        <div class="stat-card">
            <span class="stat-icon">✅</span>
            <div class="stat-number"><?php echo number_format((int)($published_results['total_results'] ?? 0)); ?></div>
            <div class="stat-label">Published Results</div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="published-results.php" class="quick-action">
            <span class="action-icon">📊</span>
            <div class="action-title">View Results</div>
            <div class="action-desc">Published election results</div>
        </a>
        <a href="candidates.php" class="quick-action">
            <span class="action-icon">👤</span>
            <div class="action-title">Candidates</div>
            <div class="action-desc">View candidate profiles</div>
        </a>
        <a href="maps.php" class="quick-action">
            <span class="action-icon">🗺️</span>
            <div class="action-title">Interactive Maps</div>
            <div class="action-desc">Results by location</div>
        </a>
        <a href="statistics.php" class="quick-action">
            <span class="action-icon">📈</span>
            <div class="action-title">Statistics</div>
            <div class="action-desc">Election data charts</div>
        </a>
        <a href="election-information.php" class="quick-action">
            <span class="action-icon">ℹ️</span>
            <div class="action-title">Election Info</div>
            <div class="action-desc">Guidelines and FAQs</div>
        </a>
        <a href="contact.php" class="quick-action">
            <span class="action-icon">📧</span>
            <div class="action-title">Contact Us</div>
            <div class="action-desc">Get in touch</div>
        </a>
    </div>

    <!-- News / Announcements -->
    <?php if (!empty($latest_news)): ?>
        <div class="news-section">
            <div class="section-title">
                <i class="fas fa-newspaper" style="color:#0F4C81;"></i> Latest Announcements
            </div>
            <?php foreach ($latest_news as $news): ?>
                <div class="news-item">
                    <div class="news-title"><?php echo htmlspecialchars($news['title']); ?></div>
                    <div class="news-meta">
                        <span><i class="far fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($news['created_at'])); ?></span>
                        <span class="tag"><?php echo ucfirst($news['target_audience'] ?? 'Public'); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Election Results Summary -->
    <?php if ($active_election && ((int)($published_results['total_results'] ?? 0) > 0)): ?>
        <div class="news-section" style="margin-top:20px;">
            <div class="section-title">
                <i class="fas fa-chart-bar" style="color:#0F4C81;"></i> Published Results Summary
                <span style="font-size:0.7rem;font-weight:400;color:#6B7280;">
                    <?php echo htmlspecialchars($active_election['name']); ?>
                </span>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:14px;">
                <div style="text-align:center;padding:12px;background:#F8FAFC;border-radius:10px;">
                    <div style="font-size:1.2rem;font-weight:700;color:#0F4C81;">
                        <?php echo number_format((int)($published_results['total_votes'] ?? 0)); ?>
                    </div>
                    <div style="font-size:0.65rem;color:#6B7280;">Total Votes</div>
                </div>
                <div style="text-align:center;padding:12px;background:#F8FAFC;border-radius:10px;">
                    <div style="font-size:1.2rem;font-weight:700;color:#10B981;">
                        <?php echo number_format((int)($published_results['total_valid'] ?? 0)); ?>
                    </div>
                    <div style="font-size:0.65rem;color:#6B7280;">Valid Votes</div>
                </div>
                <div style="text-align:center;padding:12px;background:#F8FAFC;border-radius:10px;">
                    <div style="font-size:1.2rem;font-weight:700;color:#EF4444;">
                        <?php echo number_format((int)($published_results['total_rejected'] ?? 0)); ?>
                    </div>
                    <div style="font-size:0.65rem;color:#6B7280;">Rejected Votes</div>
                </div>
                <div style="text-align:center;padding:12px;background:#F8FAFC;border-radius:10px;">
                    <div style="font-size:1.2rem;font-weight:700;color:#7C3AED;">
                        <?php echo number_format((int)($published_results['total_results'] ?? 0)); ?>
                    </div>
                    <div style="font-size:0.65rem;color:#6B7280;">Results Published</div>
                </div>
            </div>
            <div style="text-align:center;margin-top:14px;">
                <a href="published-results.php" style="color:#0F4C81;text-decoration:none;font-weight:600;">
                    View All Results <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/public-footer.php'; ?>