<?php
// ============================================================
// INCIDENT VIEW - CLIENT ADMIN (PROFESSIONAL UI)
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
$user_id = SessionManager::get('user_id');
$user_name = SessionManager::get('user_name', 'Administrator');
$user_email = SessionManager::get('user_email', 'admin@example.com');
$tenant_id = SessionManager::get('tenant_id');

// ============================================================
// GET INCIDENT ID
// ============================================================
$incident_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($incident_id <= 0) {
    header('Location: incidents.php');
    exit();
}

// ============================================================
// FETCH INCIDENT DETAILS
// ============================================================
$incident = null;
try {
    $stmt = $db->prepare("
        SELECT i.*, 
               r.full_name as reporter_name, r.email as reporter_email, r.phone as reporter_phone,
               a.full_name as assigned_to_name, a.email as assigned_to_email,
               res.full_name as resolved_by_name,
               e.name as election_name,
               s.name as state_name, s.code as state_code,
               l.name as lga_name, l.code as lga_code,
               w.name as ward_name, w.code as ward_code,
               pu.name as pu_name, pu.code as pu_code
        FROM incidents i
        LEFT JOIN users r ON i.reporter_id = r.id
        LEFT JOIN users a ON i.assigned_to = a.id
        LEFT JOIN users res ON i.resolved_by = res.id
        LEFT JOIN elections e ON i.election_id = e.id
        LEFT JOIN states s ON i.state_id = s.id
        LEFT JOIN lgas l ON i.lga_id = l.id
        LEFT JOIN wards w ON i.ward_id = w.id
        LEFT JOIN polling_units pu ON i.pu_id = pu.id
        WHERE i.id = ? AND i.tenant_id = ?
    ");
    $stmt->execute([$incident_id, $tenant_id]);
    $incident = $stmt->fetch();
    
    if (!$incident) {
        header('Location: incidents.php');
        exit();
    }
} catch (Exception $e) {
    header('Location: incidents.php');
    exit();
}

// ============================================================
// DECODE JSON FIELDS
// ============================================================
$photo_urls = json_decode($incident['photo_urls_json'] ?? '[]', true);
$has_attachments = !empty($photo_urls) || !empty($incident['video_url']) || !empty($incident['audio_url']);

// ============================================================
// FETCH INVESTIGATORS FOR DROPDOWN
// ============================================================
$investigators = [];
try {
    $stmt = $db->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email, r.name as role_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.tenant_id = ? AND u.status = 'active' 
        AND r.level IN ('state', 'lga', 'ward', 'pu_agent')
        ORDER BY u.first_name, u.last_name
    ");
    $stmt->execute([$tenant_id]);
    $investigators = $stmt->fetchAll();
} catch (Exception $e) {}

include 'includes/base.php';
include 'includes/sidebar.php';
?>
<style>
    /* ============================================================
       INCIDENT VIEW - PROFESSIONAL UI STYLES
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
        padding: 10px 20px;
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
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(var(--primary-rgb), 0.3);
    }
    .btn-success {
        padding: 10px 20px;
        background: var(--secondary);
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
    .btn-success:hover {
        background: #059669;
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(16, 185, 129, 0.3);
    }
    .btn-outline {
        padding: 10px 18px;
        background: transparent;
        color: var(--gray-600);
        border: 1.5px solid var(--gray-200);
        border-radius: 10px;
        font-weight: 500;
        font-size: 0.85rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
    }
    .btn-outline:hover {
        background: var(--gray-50);
        border-color: var(--primary);
        color: var(--primary);
    }
    .btn-danger {
        padding: 10px 20px;
        background: var(--danger);
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
    .btn-danger:hover {
        background: #DC2626;
        transform: translateY(-2px);
        box-shadow: 0 4px 16px rgba(239, 68, 68, 0.3);
    }
    .btn-sm {
        padding: 4px 12px;
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
    .btn-sm.info { background: #EFF6FF; color: #1E40AF; }
    .btn-sm.info:hover { background: #DBEAFE; }
    .btn-sm.danger { background: #FEF2F2; color: #991B1B; }
    .btn-sm.danger:hover { background: #FEE2E2; }
    
    .incident-hero {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 28px 32px;
        box-shadow: var(--shadow);
        margin-bottom: 24px;
        position: relative;
        overflow: hidden;
    }
    .incident-hero::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--primary), var(--secondary));
    }
    .incident-hero .hero-content {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 16px;
    }
    .incident-hero .hero-info h1 {
        font-size: 1.4rem;
        font-weight: 700;
        margin-bottom: 6px;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .incident-hero .hero-info .meta {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        font-size: 0.85rem;
        color: var(--gray-500);
    }
    .incident-hero .hero-info .meta span {
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .incident-hero .hero-status {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    
    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 600;
        transition: var(--transition);
    }
    .badge-status .dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        display: inline-block;
        animation: pulse-dot 2s ease-in-out infinite;
    }
    @keyframes pulse-dot {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.5; transform: scale(0.8); }
    }
    .badge-status.reported { background: #FEF2F2; color: #991B1B; }
    .badge-status.reported .dot { background: #EF4444; }
    .badge-status.acknowledged { background: #FFFBEB; color: #92400E; }
    .badge-status.acknowledged .dot { background: #F59E0B; }
    .badge-status.investigating { background: #EFF6FF; color: #1E40AF; }
    .badge-status.investigating .dot { background: #3B82F6; }
    .badge-status.escalated { background: #F5F3FF; color: #5B21B6; }
    .badge-status.escalated .dot { background: #8B5CF6; }
    .badge-status.resolved { background: #ECFDF5; color: #065F46; }
    .badge-status.resolved .dot { background: #10B981; }
    .badge-status.closed { background: var(--gray-100); color: var(--gray-500); }
    .badge-status.closed .dot { background: var(--gray-400); }
    .badge-status.false_alarm { background: var(--gray-100); color: var(--gray-500); }
    .badge-status.false_alarm .dot { background: var(--gray-400); }
    
    .badge-severity {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 12px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 600;
    }
    .badge-severity.low { background: #ECFDF5; color: #065F46; }
    .badge-severity.medium { background: #FFFBEB; color: #92400E; }
    .badge-severity.high { background: #FEF2F2; color: #991B1B; }
    .badge-severity.critical { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; }
    
    .badge-panic {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 700;
        background: #FEF2F2;
        color: #991B1B;
        border: 2px solid #FECACA;
        animation: pulse-panic 2s ease-in-out infinite;
    }
    @keyframes pulse-panic {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.6; }
    }
    
    .detail-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 20px;
        margin-bottom: 24px;
    }
    .detail-card {
        background: white;
        border-radius: var(--radius);
        border: 1px solid var(--gray-200);
        padding: 24px 28px;
        box-shadow: var(--shadow);
        transition: var(--transition);
    }
    .detail-card:hover {
        box-shadow: var(--shadow-hover);
    }
    .detail-card .card-title {
        font-weight: 600;
        font-size: 1rem;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 2px solid var(--gray-100);
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--gray-700);
    }
    .detail-card .card-title i {
        color: var(--primary);
        font-size: 1.1rem;
    }
    
    .detail-row {
        display: flex;
        padding: 8px 0;
        border-bottom: 1px solid var(--gray-50);
        font-size: 0.85rem;
        transition: var(--transition);
    }
    .detail-row:hover {
        background: var(--gray-50);
        margin: 0 -8px;
        padding: 8px 8px;
        border-radius: 6px;
    }
    .detail-row:last-child {
        border-bottom: none;
    }
    .detail-row .label {
        font-weight: 500;
        color: var(--gray-500);
        min-width: 120px;
        flex-shrink: 0;
    }
    .detail-row .value {
        color: var(--gray-700);
        word-break: break-word;
    }
    
    .description-content {
        background: var(--gray-50);
        border-radius: 8px;
        padding: 16px 20px;
        font-size: 0.9rem;
        line-height: 1.7;
        color: var(--gray-700);
        border: 1px solid var(--gray-200);
        white-space: pre-wrap;
        max-height: 300px;
        overflow-y: auto;
    }
    
    .attachment-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 12px;
        margin-top: 8px;
    }
    .attachment-item {
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid var(--gray-200);
        transition: var(--transition);
        cursor: pointer;
        position: relative;
        aspect-ratio: 1;
    }
    .attachment-item:hover {
        border-color: var(--primary);
        transform: scale(1.02);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .attachment-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .attachment-item .overlay {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(transparent, rgba(0,0,0,0.6));
        padding: 8px 12px;
        color: white;
        font-size: 0.65rem;
        font-weight: 500;
        opacity: 0;
        transition: var(--transition);
    }
    .attachment-item:hover .overlay {
        opacity: 1;
    }
    .attachment-item.video {
        background: #1a1a2e;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 2rem;
    }
    .attachment-item.audio {
        background: #16213e;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 2rem;
    }
    .attachment-item.document {
        background: var(--gray-50);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 16px;
        text-align: center;
        aspect-ratio: auto;
        min-height: 100px;
    }
    .attachment-item.document i {
        font-size: 2rem;
        color: var(--gray-400);
        margin-bottom: 4px;
    }
    .attachment-item.document .name {
        font-size: 0.65rem;
        color: var(--gray-600);
        word-break: break-all;
    }
    
    .gps-coordinates {
        background: var(--gray-50);
        border-radius: 8px;
        padding: 12px 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        border: 1px solid var(--gray-200);
    }
    .gps-coordinates i {
        font-size: 1.2rem;
        color: var(--primary);
    }
    .gps-coordinates .coords {
        flex: 1;
        font-family: monospace;
        font-size: 0.85rem;
        color: var(--gray-700);
    }
    .gps-coordinates .map-link {
        color: var(--primary);
        text-decoration: none;
        font-size: 0.75rem;
        font-weight: 500;
    }
    .gps-coordinates .map-link:hover {
        text-decoration: underline;
    }
    
    .timeline {
        position: relative;
        padding-left: 24px;
    }
    .timeline::before {
        content: '';
        position: absolute;
        left: 6px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: var(--gray-200);
    }
    .timeline-item {
        position: relative;
        padding: 8px 0 8px 16px;
        border-bottom: 1px solid var(--gray-50);
    }
    .timeline-item:last-child {
        border-bottom: none;
    }
    .timeline-item::before {
        content: '';
        position: absolute;
        left: -22px;
        top: 14px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: var(--primary);
        border: 2px solid white;
        box-shadow: 0 0 0 2px var(--primary);
    }
    .timeline-item .time {
        font-size: 0.7rem;
        color: var(--gray-400);
        margin-bottom: 2px;
    }
    .timeline-item .event {
        font-size: 0.82rem;
        color: var(--gray-700);
    }
    .timeline-item .event strong {
        color: var(--gray-800);
    }
    
    .action-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 4px;
    }
    .action-buttons .btn {
        padding: 6px 14px;
        border-radius: 6px;
        border: none;
        font-weight: 500;
        font-size: 0.78rem;
        cursor: pointer;
        transition: var(--transition);
        font-family: 'Inter', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        text-decoration: none;
    }
    
    @media (max-width: 768px) {
        .detail-grid {
            grid-template-columns: 1fr;
        }
        .incident-hero {
            padding: 20px;
        }
        .incident-hero .hero-content {
            flex-direction: column;
        }
        .incident-hero .hero-status {
            width: 100%;
        }
        .detail-row {
            flex-direction: column;
            padding: 6px 0;
        }
        .detail-row .label {
            min-width: auto;
            font-size: 0.75rem;
        }
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .attachment-grid {
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
        }
        .action-buttons {
            width: 100%;
        }
        .action-buttons .btn {
            flex: 1;
            justify-content: center;
        }
    }
    @media (max-width: 480px) {
        .incident-hero {
            padding: 16px;
        }
        .incident-hero .hero-info h1 {
            font-size: 1.1rem;
        }
        .detail-card {
            padding: 16px 18px;
        }
        .action-buttons {
            flex-direction: column;
        }
        .action-buttons .btn {
            width: 100%;
            justify-content: center;
        }
        .attachment-grid {
            grid-template-columns: repeat(auto-fill, minmax(60px, 1fr));
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
                    <i class="fas fa-exclamation-triangle" style="color:var(--danger);margin-right:8px;"></i> Incident Details
                    <small>Complete incident information and management</small>
                </h2>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="incidents-edit.php?id=<?php echo $incident_id; ?>" class="btn-primary">
                    <i class="fas fa-edit"></i> Edit
                </a>
                <a href="incidents.php" class="btn-outline">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <!-- Incident Hero -->
        <div class="incident-hero">
            <div class="hero-content">
                <div class="hero-info">
                    <h1>
                        <?php echo htmlspecialchars($incident['title']); ?>
                        <?php if ($incident['is_panic']): ?>
                            <span class="badge-panic"><i class="fas fa-exclamation-triangle"></i> PANIC ALERT</span>
                        <?php endif; ?>
                    </h1>
                    <div class="meta">
                        <span><i class="fas fa-tag"></i> <?php echo ucfirst(str_replace('_', ' ', $incident['incident_type'])); ?></span>
                        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($incident['reporter_name'] ?? 'Unknown'); ?></span>
                        <span><i class="fas fa-clock"></i> <?php echo date('M j, Y g:i A', strtotime($incident['created_at'])); ?></span>
                        <?php if (!empty($incident['election_name'])): ?>
                            <span><i class="fas fa-vote-yea"></i> <?php echo htmlspecialchars($incident['election_name']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="hero-status">
                    <span class="badge-status <?php echo $incident['status']; ?>">
                        <span class="dot"></span>
                        <?php echo ucfirst($incident['status']); ?>
                    </span>
                    <span class="badge-severity <?php echo $incident['severity']; ?>">
                        <i class="fas <?php 
                            echo $incident['severity'] == 'low' ? 'fa-circle' : 
                                 ($incident['severity'] == 'medium' ? 'fa-circle' : 
                                 ($incident['severity'] == 'high' ? 'fa-exclamation-circle' : 'fa-exclamation-triangle'));
                        ?>"></i>
                        <?php echo ucfirst($incident['severity']); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Detail Grid -->
        <div class="detail-grid">
            <!-- Left Column -->
            <div>
                <!-- Description -->
                <div class="detail-card">
                    <div class="card-title">
                        <i class="fas fa-align-left" style="color:var(--primary);"></i> Description
                    </div>
                    <div class="description-content">
                        <?php echo nl2br(htmlspecialchars($incident['description'])); ?>
                    </div>
                </div>

                <!-- Location -->
                <div class="detail-card" style="margin-top:16px;">
                    <div class="card-title">
                        <i class="fas fa-map-marker-alt" style="color:var(--primary);"></i> Location Details
                    </div>
                    <div class="detail-row">
                        <span class="label">State</span>
                        <span class="value">
                            <?php if (!empty($incident['state_name'])): ?>
                                <?php echo htmlspecialchars($incident['state_name']); ?>
                                <?php if (!empty($incident['state_code'])): ?>
                                    (<?php echo htmlspecialchars($incident['state_code']); ?>)
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color:var(--gray-400);">Not specified</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="label">LGA</span>
                        <span class="value"><?php echo htmlspecialchars($incident['lga_name'] ?? 'Not specified'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Ward</span>
                        <span class="value"><?php echo htmlspecialchars($incident['ward_name'] ?? 'Not specified'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Polling Unit</span>
                        <span class="value">
                            <?php if (!empty($incident['pu_name'])): ?>
                                <?php echo htmlspecialchars($incident['pu_name']); ?>
                                <?php if (!empty($incident['pu_code'])): ?>
                                    (<?php echo htmlspecialchars($incident['pu_code']); ?>)
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color:var(--gray-400);">Not specified</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    
                    <!-- GPS Coordinates -->
                    <?php if (!empty($incident['gps_lat']) && !empty($incident['gps_lng'])): ?>
                        <div class="gps-coordinates" style="margin-top:12px;">
                            <i class="fas fa-map-pin"></i>
                            <div class="coords">
                                <?php echo number_format($incident['gps_lat'], 6) . ', ' . number_format($incident['gps_lng'], 6); ?>
                                <?php if (!empty($incident['gps_accuracy'])): ?>
                                    <span style="font-size:0.7rem;color:var(--gray-400);">(±<?php echo $incident['gps_accuracy']; ?>m)</span>
                                <?php endif; ?>
                            </div>
                            <a href="https://www.google.com/maps?q=<?php echo $incident['gps_lat']; ?>,<?php echo $incident['gps_lng']; ?>" target="_blank" class="map-link">
                                <i class="fas fa-external-link-alt"></i> View Map
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Attachments -->
                <?php if ($has_attachments): ?>
                <div class="detail-card" style="margin-top:16px;">
                    <div class="card-title">
                        <i class="fas fa-paperclip" style="color:var(--primary);"></i> Attachments
                        <span style="margin-left:auto;font-size:0.7rem;color:var(--gray-400);">
                            <?php 
                            $count = count($photo_urls) + (!empty($incident['video_url']) ? 1 : 0) + (!empty($incident['audio_url']) ? 1 : 0);
                            echo $count . ' files';
                            ?>
                        </span>
                    </div>
                    <div class="attachment-grid">
                        <?php foreach ($photo_urls as $photo): ?>
                            <div class="attachment-item" onclick="window.open('<?php echo htmlspecialchars($photo); ?>', '_blank')">
                                <img src="<?php echo htmlspecialchars($photo); ?>" alt="Incident photo">
                                <div class="overlay"><i class="fas fa-search-plus"></i> View</div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!empty($incident['video_url'])): ?>
                            <div class="attachment-item video" onclick="window.open('<?php echo htmlspecialchars($incident['video_url']); ?>', '_blank')">
                                <i class="fas fa-play-circle"></i>
                                <div style="font-size:0.6rem;margin-top:4px;">Video</div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($incident['audio_url'])): ?>
                            <div class="attachment-item audio" onclick="window.open('<?php echo htmlspecialchars($incident['audio_url']); ?>', '_blank')">
                                <i class="fas fa-microphone"></i>
                                <div style="font-size:0.6rem;margin-top:4px;">Audio</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Status & Assignment -->
                <div class="detail-card">
                    <div class="card-title">
                        <i class="fas fa-tasks" style="color:var(--primary);"></i> Status &amp; Assignment
                    </div>
                    <div class="detail-row">
                        <span class="label">Status</span>
                        <span class="value">
                            <span class="badge-status <?php echo $incident['status']; ?>">
                                <span class="dot"></span>
                                <?php echo ucfirst($incident['status']); ?>
                            </span>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Severity</span>
                        <span class="value">
                            <span class="badge-severity <?php echo $incident['severity']; ?>">
                                <i class="fas <?php 
                                    echo $incident['severity'] == 'low' ? 'fa-circle' : 
                                         ($incident['severity'] == 'medium' ? 'fa-circle' : 
                                         ($incident['severity'] == 'high' ? 'fa-exclamation-circle' : 'fa-exclamation-triangle'));
                                ?>"></i>
                                <?php echo ucfirst($incident['severity']); ?>
                            </span>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Assigned To</span>
                        <span class="value">
                            <?php if (!empty($incident['assigned_to_name'])): ?>
                                <strong><?php echo htmlspecialchars($incident['assigned_to_name']); ?></strong>
                                <?php if (!empty($incident['assigned_to_email'])): ?>
                                    <span style="font-size:0.7rem;color:var(--gray-400);display:block;">
                                        <?php echo htmlspecialchars($incident['assigned_to_email']); ?>
                                    </span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color:var(--gray-400);">Unassigned</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php if (!empty($incident['resolved_by_name'])): ?>
                    <div class="detail-row">
                        <span class="label">Resolved By</span>
                        <span class="value">
                            <strong><?php echo htmlspecialchars($incident['resolved_by_name']); ?></strong>
                            <?php if (!empty($incident['resolved_at'])): ?>
                                <span style="font-size:0.7rem;color:var(--gray-400);display:block;">
                                    <?php echo date('M j, Y g:i A', strtotime($incident['resolved_at'])); ?>
                                </span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($incident['resolution_notes'])): ?>
                    <div class="detail-row" style="flex-direction:column;align-items:flex-start;">
                        <span class="label">Resolution Notes</span>
                        <span class="value" style="margin-top:4px;font-size:0.82rem;color:var(--gray-600);">
                            <?php echo nl2br(htmlspecialchars($incident['resolution_notes'])); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="detail-card" style="margin-top:16px;">
                    <div class="card-title">
                        <i class="fas fa-bolt" style="color:var(--primary);"></i> Quick Actions
                    </div>
                    <div class="action-buttons">
                        <a href="incidents-edit.php?id=<?php echo $incident_id; ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <?php if ($incident['status'] != 'resolved' && $incident['status'] != 'closed' && $incident['status'] != 'false_alarm'): ?>
                            <button onclick="resolveIncident(<?php echo $incident_id; ?>)" class="btn btn-success">
                                <i class="fas fa-check-circle"></i> Resolve
                            </button>
                        <?php endif; ?>
                        <?php if ($incident['status'] == 'resolved' || $incident['status'] == 'false_alarm'): ?>
                            <button onclick="closeIncident(<?php echo $incident_id; ?>)" class="btn btn-danger">
                                <i class="fas fa-times-circle"></i> Close
                            </button>
                        <?php endif; ?>
                        <button class="btn btn-outline" onclick="window.print();">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </div>

                <!-- Reporter Information -->
                <div class="detail-card" style="margin-top:16px;">
                    <div class="card-title">
                        <i class="fas fa-user" style="color:var(--primary);"></i> Reporter Information
                    </div>
                    <div class="detail-row">
                        <span class="label">Name</span>
                        <span class="value"><?php echo htmlspecialchars($incident['reporter_name'] ?? 'Unknown'); ?></span>
                    </div>
                    <?php if (!empty($incident['reporter_email'])): ?>
                    <div class="detail-row">
                        <span class="label">Email</span>
                        <span class="value">
                            <a href="mailto:<?php echo htmlspecialchars($incident['reporter_email']); ?>" style="color:var(--primary);text-decoration:none;">
                                <?php echo htmlspecialchars($incident['reporter_email']); ?>
                            </a>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($incident['reporter_phone'])): ?>
                    <div class="detail-row">
                        <span class="label">Phone</span>
                        <span class="value">
                            <a href="tel:<?php echo htmlspecialchars($incident['reporter_phone']); ?>" style="color:var(--primary);text-decoration:none;">
                                <?php echo htmlspecialchars($incident['reporter_phone']); ?>
                            </a>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($incident['device_id'])): ?>
                    <div class="detail-row">
                        <span class="label">Device ID</span>
                        <span class="value" style="font-family:monospace;font-size:0.7rem;color:var(--gray-500);">
                            <?php echo htmlspecialchars($incident['device_id']); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Timeline -->
                <div class="detail-card" style="margin-top:16px;">
                    <div class="card-title">
                        <i class="fas fa-clock" style="color:var(--primary);"></i> Timeline
                    </div>
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="time"><?php echo date('M j, Y g:i A', strtotime($incident['created_at'])); ?></div>
                            <div class="event"><strong>Reported</strong> - Incident created by <?php echo htmlspecialchars($incident['reporter_name'] ?? 'Unknown'); ?></div>
                        </div>
                        <?php if (!empty($incident['assigned_to_name'])): ?>
                        <div class="timeline-item">
                            <div class="time"><?php echo date('M j, Y g:i A', strtotime($incident['updated_at'] ?? $incident['created_at'])); ?></div>
                            <div class="event"><strong>Assigned</strong> - Assigned to <?php echo htmlspecialchars($incident['assigned_to_name']); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($incident['resolved_at'])): ?>
                        <div class="timeline-item">
                            <div class="time"><?php echo date('M j, Y g:i A', strtotime($incident['resolved_at'])); ?></div>
                            <div class="event"><strong>Resolved</strong> - Resolved by <?php echo htmlspecialchars($incident['resolved_by_name'] ?? 'Unknown'); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if ($incident['status'] == 'closed'): ?>
                        <div class="timeline-item">
                            <div class="time"><?php echo date('M j, Y g:i A', strtotime($incident['updated_at'] ?? $incident['created_at'])); ?></div>
                            <div class="event"><strong>Closed</strong> - Incident closed</div>
                        </div>
                        <?php endif; ?>
                    </div>
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
// INCIDENT FUNCTIONS
// ============================================================
function resolveIncident(id) {
    if (confirm('Mark this incident as resolved?')) {
        var notes = prompt('Please provide resolution notes:');
        if (notes !== null) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = 'incidents.php';
            form.innerHTML = '<input type="hidden" name="action" value="resolve_incident"><input type="hidden" name="id" value="' + id + '"><input type="hidden" name="resolution_notes" value="' + encodeURIComponent(notes) + '">';
            document.body.appendChild(form);
            form.submit();
        }
    }
}

function closeIncident(id) {
    if (confirm('Close this incident?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = 'incidents.php';
        form.innerHTML = '<input type="hidden" name="action" value="close_incident"><input type="hidden" name="id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

// ============================================================
// SEARCH FUNCTIONALITY
// ============================================================
var searchInput = document.querySelector('.search-wrap input');
if (searchInput) {
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            this.closest('form').submit();
        }
    });
}
</script>
</body>
</html>