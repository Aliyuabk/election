<?php
// ============================================================
// STATE COORDINATOR - VIEW LGA COORDINATORS
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

// Start session
SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: ../../auth/login.php');
    exit();
}

// Only state coordinator can access
if (SessionManager::get('role_level') !== 'state') {
    header('Location: ../client-admin/');
    exit();
}

$user_name = SessionManager::get('user_name', 'Coordinator');
$user_id = SessionManager::get('user_id');
$user_email = SessionManager::get('user_email');
$state_id = SessionManager::get('state_id');
$tenant_id = SessionManager::get('tenant_id');

// Get LGA ID from URL
$lga_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($lga_id <= 0) {
    header('Location: monitor-lgas.php?error=invalid_lga');
    exit();
}

$db = getDB();

// ============================================================
// FETCH LGA AND STATE DATA
// ============================================================
$lga_name = '';
$state_name = '';
$lga_data = null;

try {
    $stmt = $db->prepare("
        SELECT l.*, s.name as state_name 
        FROM lgas l 
        JOIN states s ON l.state_id = s.id 
        WHERE l.id = ? AND l.state_id = ?
    ");
    $stmt->execute([$lga_id, $state_id]);
    $lga_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lga_data) {
        header('Location: monitor-lgas.php?error=lga_not_found');
        exit();
    }
    
    $lga_name = $lga_data['name'];
    $state_name = $lga_data['state_name'];
    
} catch (Exception $e) {
    error_log("LGA Coordinators Error: " . $e->getMessage());
    header('Location: monitor-lgas.php?error=database_error');
    exit();
}

// ============================================================
// FETCH LGA COORDINATORS
// ============================================================
$coordinators = [];
$total_coordinators = 0;

try {
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.user_code,
            u.first_name,
            u.last_name,
            u.full_name,
            u.email,
            u.phone,
            u.status,
            u.last_login_at,
            u.created_at,
            r.name as role_name,
            r.level as role_level
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE u.tenant_id = ? 
        AND r.level = 'lga'
        AND u.jurisdiction_id = ?
        AND (u.deleted_at IS NULL OR u.deleted_at = '0000-00-00 00:00:00')
        ORDER BY u.full_name ASC
    ");
    $stmt->execute([$tenant_id, $lga_id]);
    $coordinators = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_coordinators = count($coordinators);
    
} catch (Exception $e) {
    error_log("LGA Coordinators Fetch Error: " . $e->getMessage());
}

// ============================================================
// FETCH WARD COORDINATORS FOR THIS LGA
// ============================================================
$ward_coordinators = [];
$total_ward_coordinators = 0;

try {
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.user_code,
            u.first_name,
            u.last_name,
            u.full_name,
            u.email,
            u.phone,
            u.status,
            u.last_login_at,
            u.created_at,
            r.name as role_name,
            r.level as role_level,
            w.name as ward_name,
            w.code as ward_code
        FROM users u
        JOIN roles r ON u.role_id = r.id
        JOIN wards w ON u.jurisdiction_id = w.id
        WHERE u.tenant_id = ? 
        AND r.level = 'ward'
        AND u.jurisdiction_id IN (SELECT id FROM wards WHERE lga_id = ?)
        AND (u.deleted_at IS NULL OR u.deleted_at = '0000-00-00 00:00:00')
        ORDER BY w.name ASC, u.full_name ASC
    ");
    $stmt->execute([$tenant_id, $lga_id]);
    $ward_coordinators = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_ward_coordinators = count($ward_coordinators);
    
} catch (Exception $e) {
    error_log("Ward Coordinators Fetch Error: " . $e->getMessage());
}

// ============================================================
// FETCH PU AGENTS FOR THIS LGA
// ============================================================
$pu_agents = [];
$total_pu_agents = 0;

try {
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.user_code,
            u.first_name,
            u.last_name,
            u.full_name,
            u.email,
            u.phone,
            u.status,
            u.last_login_at,
            u.created_at,
            r.name as role_name,
            r.level as role_level,
            pu.name as pu_name,
            pu.code as pu_code,
            w.name as ward_name
        FROM users u
        JOIN roles r ON u.role_id = r.id
        JOIN polling_units pu ON u.jurisdiction_id = pu.id
        JOIN wards w ON pu.ward_id = w.id
        WHERE u.tenant_id = ? 
        AND r.level = 'pu_agent'
        AND pu.ward_id IN (SELECT id FROM wards WHERE lga_id = ?)
        AND (u.deleted_at IS NULL OR u.deleted_at = '0000-00-00 00:00:00')
        ORDER BY w.name ASC, pu.name ASC, u.full_name ASC
        LIMIT 50
    ");
    $stmt->execute([$tenant_id, $lga_id]);
    $pu_agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_pu_agents = count($pu_agents);
    
} catch (Exception $e) {
    error_log("PU Agents Fetch Error: " . $e->getMessage());
}

include '../includes/base.php';
include '../includes/sidebar.php';

$page_title = 'LGA Coordinators';
$page_subtitle = $lga_name;
?>

<main class="main-content">
    <?php include '../includes/header.php'; ?>
    
    <div class="main-content-inner">
        <!-- Breadcrumb -->
        <div class="welcome-section">
            <div class="breadcrumb">
                <i class="fas fa-home"></i>
                <a href="index.php" style="text-decoration:none;color:var(--gray-500);">Dashboard</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <a href="monitor-lgas.php" style="text-decoration:none;color:var(--gray-500);">Monitor LGAs</a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <a href="lga-dashboard.php?id=<?php echo $lga_id; ?>" style="text-decoration:none;color:var(--gray-500);"><?php echo htmlspecialchars($lga_name); ?></a>
                <i class="fas fa-chevron-right" style="font-size:0.6rem;color:var(--gray-400);"></i>
                <span style="font-weight:600;color:var(--gray-800);">Coordinators</span>
            </div>
            
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-top:8px;">
                <div>
                    <h2 style="font-size:1.5rem;font-weight:700;margin:0;">
                        <?php echo htmlspecialchars($lga_name); ?> Coordinators
                    </h2>
                    <p style="color:var(--gray-500);margin:2px 0 0;">
                        <i class="fas fa-user-tie"></i> 
                        <?php echo $total_coordinators; ?> LGA Coordinators • 
                        <?php echo $total_ward_coordinators; ?> Ward Coordinators • 
                        <?php echo $total_pu_agents; ?> PU Agents
                    </p>
                    <p style="color:var(--gray-400);font-size:0.75rem;margin:2px 0 0;">
                        <?php echo htmlspecialchars($state_name); ?> • <?php echo htmlspecialchars($lga_name); ?>
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="lga-dashboard.php?id=<?php echo $lga_id; ?>" class="btn-secondary" style="padding:8px 20px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:10px;text-decoration:none;font-weight:500;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                    <a href="coordinators-create.php?lga=<?php echo $lga_id; ?>&level=lga" class="btn-primary" style="padding:8px 20px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-user-plus"></i> Add Coordinator
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-user-tie"></i></div>
                <div class="stat-number"><?php echo number_format($total_coordinators); ?></div>
                <div class="stat-label">LGA Coordinators</div>
                <div class="stat-change">Active staff</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo number_format($total_ward_coordinators); ?></div>
                <div class="stat-label">Ward Coordinators</div>
                <div class="stat-change">Supervisors</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-user"></i></div>
                <div class="stat-number"><?php echo number_format($total_pu_agents); ?></div>
                <div class="stat-label">PU Agents</div>
                <div class="stat-change">Field staff</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-flag-checkered"></i></div>
                <div class="stat-number"><?php echo number_format($total_coordinators + $total_ward_coordinators + $total_pu_agents); ?></div>
                <div class="stat-label">Total Personnel</div>
                <div class="stat-change">All staff</div>
            </div>
        </div>

        <!-- LGA Coordinators Section -->
        <div style="background:white;border-radius:var(--radius);overflow:hidden;border:1px solid var(--gray-200);margin-bottom:20px;">
            <div style="padding:12px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <h4 style="font-size:0.9rem;font-weight:600;margin:0;">
                    <i class="fas fa-user-tie" style="color:var(--primary);margin-right:6px;"></i>
                    LGA Coordinators
                </h4>
                <span style="font-size:0.75rem;color:var(--gray-500);"><?php echo $total_coordinators; ?> coordinators</span>
            </div>
            
            <?php if (count($coordinators) > 0): ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;padding:16px;">
                    <?php foreach ($coordinators as $coord): ?>
                        <div style="background:var(--gray-50);border-radius:12px;padding:16px;border:1px solid var(--gray-200);transition:var(--transition);hover:transform:translateY(-2px);hover:box-shadow:var(--shadow-hover);">
                            <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;">
                                <div style="width:48px;height:48px;border-radius:50%;background:var(--primary);color:white;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1rem;flex-shrink:0;">
                                    <?php echo strtoupper(substr($coord['first_name'] ?? 'U', 0, 1) . substr($coord['last_name'] ?? 'N', 0, 1)); ?>
                                </div>
                                <div style="flex:1;min-width:0;">
                                    <div style="font-weight:600;font-size:0.9rem;"><?php echo htmlspecialchars($coord['full_name'] ?? 'Unknown'); ?></div>
                                    <div style="font-size:0.7rem;color:var(--gray-500);">
                                        <span class="badge <?php echo ($coord['status'] ?? '') === 'active' ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo ucfirst($coord['status'] ?? 'Unknown'); ?>
                                        </span>
                                        • <?php echo htmlspecialchars($coord['role_name'] ?? 'Unknown'); ?>
                                    </div>
                                    <div style="font-size:0.65rem;color:var(--gray-400);">
                                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($lga_name); ?>
                                    </div>
                                </div>
                            </div>
                            <div style="font-size:0.75rem;color:var(--gray-500);">
                                <div><i class="fas fa-envelope" style="width:16px;"></i> <?php echo htmlspecialchars($coord['email'] ?? 'N/A'); ?></div>
                                <div><i class="fas fa-phone" style="width:16px;"></i> <?php echo htmlspecialchars($coord['phone'] ?? 'N/A'); ?></div>
                                <div><i class="fas fa-clock" style="width:16px;"></i> Last login: <?php echo ($coord['last_login_at'] ?? null) ? date('M j, Y g:i A', strtotime($coord['last_login_at'])) : 'Never'; ?></div>
                            </div>
                            <div style="display:flex;gap:6px;margin-top:10px;flex-wrap:wrap;">
                                <a href="coordinator-view.php?id=<?php echo $coord['id']; ?>" class="btn-sm" style="padding:4px 12px;border-radius:6px;background:var(--primary);color:white;text-decoration:none;font-size:0.7rem;">View</a>
                                <a href="coordinator-edit.php?id=<?php echo $coord['id']; ?>" class="btn-sm" style="padding:4px 12px;border-radius:6px;background:var(--gray-200);color:var(--gray-700);text-decoration:none;font-size:0.7rem;">Edit</a>
                                <?php if ($coord['status'] === 'active'): ?>
                                    <a href="coordinator-suspend.php?id=<?php echo $coord['id']; ?>" class="btn-sm" style="padding:4px 12px;border-radius:6px;background:#FEE2E2;color:#991B1B;text-decoration:none;font-size:0.7rem;" onclick="return confirm('Suspend this coordinator?')">Suspend</a>
                                <?php endif; ?>
                                <a href="coordinator-reset-password.php?id=<?php echo $coord['id']; ?>" class="btn-sm" style="padding:4px 12px;border-radius:6px;background:#FEF3C7;color:#92400E;text-decoration:none;font-size:0.7rem;">Reset Password</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="padding:30px;text-align:center;color:var(--gray-500);">
                    <i class="fas fa-user-tie" style="font-size:1.5rem;display:block;margin-bottom:8px;color:var(--gray-300);"></i>
                    No LGA coordinators assigned yet
                    <div style="margin-top:12px;">
                        <a href="coordinators-create.php?lga=<?php echo $lga_id; ?>&level=lga" class="btn-primary" style="padding:8px 20px;background:var(--primary);color:white;border:none;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.8rem;">
                            <i class="fas fa-user-plus"></i> Add LGA Coordinator
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Ward Coordinators Section -->
        <div style="background:white;border-radius:var(--radius);overflow:hidden;border:1px solid var(--gray-200);margin-bottom:20px;">
            <div style="padding:12px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <h4 style="font-size:0.9rem;font-weight:600;margin:0;">
                    <i class="fas fa-users" style="color:var(--secondary);margin-right:6px;"></i>
                    Ward Coordinators
                </h4>
                <span style="font-size:0.75rem;color:var(--gray-500);"><?php echo $total_ward_coordinators; ?> coordinators</span>
            </div>
            
            <?php if (count($ward_coordinators) > 0): ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;padding:16px;">
                    <?php foreach ($ward_coordinators as $coord): ?>
                        <div style="background:var(--gray-50);border-radius:12px;padding:16px;border:1px solid var(--gray-200);transition:var(--transition);hover:transform:translateY(-2px);hover:box-shadow:var(--shadow-hover);">
                            <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px;">
                                <div style="width:48px;height:48px;border-radius:50%;background:var(--secondary);color:white;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1rem;flex-shrink:0;">
                                    <?php echo strtoupper(substr($coord['first_name'] ?? 'U', 0, 1) . substr($coord['last_name'] ?? 'N', 0, 1)); ?>
                                </div>
                                <div style="flex:1;min-width:0;">
                                    <div style="font-weight:600;font-size:0.9rem;"><?php echo htmlspecialchars($coord['full_name'] ?? 'Unknown'); ?></div>
                                    <div style="font-size:0.7rem;color:var(--gray-500);">
                                        <span class="badge <?php echo ($coord['status'] ?? '') === 'active' ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo ucfirst($coord['status'] ?? 'Unknown'); ?>
                                        </span>
                                        • <?php echo htmlspecialchars($coord['role_name'] ?? 'Unknown'); ?>
                                    </div>
                                    <div style="font-size:0.65rem;color:var(--gray-400);">
                                        <i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($coord['ward_name'] ?? 'Unknown Ward'); ?>
                                        <?php if (!empty($coord['ward_code'])): ?>
                                            (<?php echo htmlspecialchars($coord['ward_code']); ?>)
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div style="font-size:0.75rem;color:var(--gray-500);">
                                <div><i class="fas fa-envelope" style="width:16px;"></i> <?php echo htmlspecialchars($coord['email'] ?? 'N/A'); ?></div>
                                <div><i class="fas fa-phone" style="width:16px;"></i> <?php echo htmlspecialchars($coord['phone'] ?? 'N/A'); ?></div>
                                <div><i class="fas fa-clock" style="width:16px;"></i> Last login: <?php echo ($coord['last_login_at'] ?? null) ? date('M j, Y g:i A', strtotime($coord['last_login_at'])) : 'Never'; ?></div>
                            </div>
                            <div style="display:flex;gap:6px;margin-top:10px;flex-wrap:wrap;">
                                <a href="coordinator-view.php?id=<?php echo $coord['id']; ?>" class="btn-sm" style="padding:4px 12px;border-radius:6px;background:var(--primary);color:white;text-decoration:none;font-size:0.7rem;">View</a>
                                <a href="coordinator-edit.php?id=<?php echo $coord['id']; ?>" class="btn-sm" style="padding:4px 12px;border-radius:6px;background:var(--gray-200);color:var(--gray-700);text-decoration:none;font-size:0.7rem;">Edit</a>
                                <?php if ($coord['status'] === 'active'): ?>
                                    <a href="coordinator-suspend.php?id=<?php echo $coord['id']; ?>" class="btn-sm" style="padding:4px 12px;border-radius:6px;background:#FEE2E2;color:#991B1B;text-decoration:none;font-size:0.7rem;" onclick="return confirm('Suspend this coordinator?')">Suspend</a>
                                <?php endif; ?>
                                <a href="coordinator-reset-password.php?id=<?php echo $coord['id']; ?>" class="btn-sm" style="padding:4px 12px;border-radius:6px;background:#FEF3C7;color:#92400E;text-decoration:none;font-size:0.7rem;">Reset Password</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="padding:30px;text-align:center;color:var(--gray-500);">
                    <i class="fas fa-users" style="font-size:1.5rem;display:block;margin-bottom:8px;color:var(--gray-300);"></i>
                    No ward coordinators assigned yet
                </div>
            <?php endif; ?>
        </div>

        <!-- PU Agents Section -->
        <div style="background:white;border-radius:var(--radius);overflow:hidden;border:1px solid var(--gray-200);margin-bottom:20px;">
            <div style="padding:12px 20px;border-bottom:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <h4 style="font-size:0.9rem;font-weight:600;margin:0;">
                    <i class="fas fa-user" style="color:var(--warning);margin-right:6px;"></i>
                    PU Agents (Recent)
                </h4>
                <span style="font-size:0.75rem;color:var(--gray-500);">Showing <?php echo min($total_pu_agents, 50); ?> of <?php echo $total_pu_agents; ?> agents</span>
            </div>
            
            <?php if (count($pu_agents) > 0): ?>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:0.75rem;">
                        <thead style="background:var(--gray-50);border-bottom:2px solid var(--gray-200);">
                            <tr>
                                <th style="padding:8px 12px;text-align:left;font-weight:600;color:var(--gray-600);">Agent</th>
                                <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--gray-600);">PU / Ward</th>
                                <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--gray-600);">Status</th>
                                <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--gray-600);">Last Login</th>
                                <th style="padding:8px 12px;text-align:center;font-weight:600;color:var(--gray-600);">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($pu_agents, 0, 50) as $agent): ?>
                                <tr style="border-bottom:1px solid var(--gray-100);transition:var(--transition);hover:background:var(--gray-50);">
                                    <td style="padding:8px 12px;">
                                        <div style="font-weight:500;font-size:0.8rem;"><?php echo htmlspecialchars($agent['full_name'] ?? 'Unknown'); ?></div>
                                        <div style="font-size:0.6rem;color:var(--gray-400);"><?php echo htmlspecialchars($agent['email'] ?? 'N/A'); ?></div>
                                    </td>
                                    <td style="padding:8px 12px;text-align:center;font-size:0.7rem;">
                                        <div><?php echo htmlspecialchars($agent['pu_name'] ?? 'Unknown PU'); ?></div>
                                        <div style="font-size:0.55rem;color:var(--gray-400);"><?php echo htmlspecialchars($agent['ward_name'] ?? ''); ?></div>
                                    </td>
                                    <td style="padding:8px 12px;text-align:center;">
                                        <span style="display:inline-block;padding:2px 8px;border-radius:8px;font-size:0.6rem;font-weight:600;background:<?php echo ($agent['status'] ?? '') === 'active' ? '#D1FAE5' : '#FEE2E2'; ?>;color:<?php echo ($agent['status'] ?? '') === 'active' ? '#065F46' : '#991B1B'; ?>;">
                                            <?php echo ucfirst($agent['status'] ?? 'Unknown'); ?>
                                        </span>
                                    </td>
                                    <td style="padding:8px 12px;text-align:center;font-size:0.65rem;color:var(--gray-500);">
                                        <?php if ($agent['last_login_at']): ?>
                                            <?php echo date('M j, Y', strtotime($agent['last_login_at'])); ?>
                                        <?php else: ?>
                                            <span style="color:var(--gray-400);">Never</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:8px 12px;text-align:center;">
                                        <div style="display:flex;gap:3px;justify-content:center;flex-wrap:wrap;">
                                            <a href="coordinator-view.php?id=<?php echo $agent['id']; ?>" class="btn-sm" style="padding:2px 8px;border-radius:4px;background:var(--primary);color:white;text-decoration:none;font-size:0.6rem;" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="coordinator-edit.php?id=<?php echo $agent['id']; ?>" class="btn-sm" style="padding:2px 8px;border-radius:4px;background:var(--gray-200);color:var(--gray-700);text-decoration:none;font-size:0.6rem;" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pu_agents > 50): ?>
                    <div style="padding:10px 20px;text-align:center;background:var(--gray-50);border-top:1px solid var(--gray-200);">
                        <span style="font-size:0.75rem;color:var(--gray-500);">
                            Showing 50 of <?php echo number_format($total_pu_agents); ?> agents. 
                            <a href="pu-agents.php?lga=<?php echo $lga_id; ?>" style="color:var(--primary);text-decoration:none;">View All →</a>
                        </span>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div style="padding:30px;text-align:center;color:var(--gray-500);">
                    <i class="fas fa-user" style="font-size:1.5rem;display:block;margin-bottom:8px;color:var(--gray-300);"></i>
                    No PU agents assigned yet
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
            <a href="coordinator-activity.php?lga=<?php echo $lga_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-clock" style="color:var(--primary);"></i>
                <span>View Activity Log</span>
            </a>
            <a href="coordinator-performance.php?lga=<?php echo $lga_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-chart-bar" style="color:var(--secondary);"></i>
                <span>Performance Report</span>
            </a>
            <a href="broadcasts-create.php?lga=<?php echo $lga_id; ?>&target=coordinators" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-bullhorn" style="color:var(--warning);"></i>
                <span>Broadcast to Coordinators</span>
            </a>
            <a href="pu-agents.php?lga=<?php echo $lga_id; ?>" class="quick-action-btn" style="display:flex;align-items:center;gap:10px;padding:12px 16px;background:white;border-radius:var(--radius);border:1px solid var(--gray-200);text-decoration:none;color:var(--gray-700);font-weight:500;transition:var(--transition);">
                <i class="fas fa-user" style="color:var(--primary);"></i>
                <span>View All PU Agents</span>
            </a>
        </div>
    </div>
</main>

<style>
.badge-success { background: #D1FAE5; color: #065F46; padding: 2px 10px; border-radius: 12px; font-size: 0.65rem; font-weight: 600; }
.badge-danger { background: #FEE2E2; color: #991B1B; padding: 2px 10px; border-radius: 12px; font-size: 0.65rem; font-weight: 600; }
.btn-sm:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.quick-action-btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-hover); border-color: var(--primary); }
.btn-secondary:hover { background: var(--gray-200); transform: translateY(-1px); }
.btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.3); }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .stats-grid { grid-template-columns: 1fr 1fr; }
    div[style*="grid-template-columns:repeat(auto-fill,minmax(300px,1fr))"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<script>
// ============================================================
// SIDEBAR TOGGLE, DROPDOWNS, PROFILE, SEARCH
// ============================================================
window.addEventListener('load', function() {
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('hidden');
        setTimeout(function() { preloader.style.display = 'none'; }, 600);
    }
});

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
</script>
</body>
</html>