<?php
// user-details.php - User Details View
require_once 'includes/db.php';

$db = Database::getInstance();
$conn = $db->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$format = isset($_GET['format']) ? $_GET['format'] : 'html';

if (!$id) {
    if ($format === 'json') {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    } else {
        echo '<div style="text-align:center; padding:40px; color:#ef4444;">Invalid user ID</div>';
    }
    exit;
}

// Get user data
$stmt = $conn->prepare("
    SELECT 
        u.*,
        t.name as tenant_name,
        t.slug as tenant_slug,
        r.name as role_name,
        r.level as role_level,
        r.slug as role_slug,
        (SELECT COUNT(*) FROM user_sessions WHERE user_id = u.id AND is_active = 1 AND last_activity_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)) as is_online
    FROM users u
    LEFT JOIN tenants t ON u.tenant_id = t.id
    LEFT JOIN roles r ON u.role_id = r.id
    WHERE u.id = ? AND u.deleted_at IS NULL
");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    if ($format === 'json') {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    } else {
        echo '<div style="text-align:center; padding:40px; color:#ef4444;">User not found</div>';
    }
    exit;
}

// If JSON format requested (for edit)
if ($format === 'json') {
    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'tenant_id' => $user['tenant_id'],
            'role_id' => $user['role_id'],
            'status' => $user['status'],
            'user_code' => $user['user_code'],
            'photograph_url' => $user['photograph_url']
        ]
    ]);
    exit;
}

// HTML format (for view modal)
?>
<div class="user-detail">
    <!-- Profile Header -->
    <div class="detail-header">
        <div class="detail-avatar">
            <?php if ($user['photograph_url']): ?>
            <img src="<?php echo htmlspecialchars($user['photograph_url']); ?>" alt="">
            <?php else: ?>
            <div class="avatar-placeholder" style="background: <?php echo $user['is_online'] ? '#10b981' : '#8b9bb5'; ?>;">
                <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
            </div>
            <?php endif; ?>
            <?php if ($user['is_online']): ?>
            <span class="online-dot"></span>
            <?php endif; ?>
        </div>
        <div class="detail-title">
            <h3><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
            <div class="detail-meta">
                <span class="user-code"><?php echo htmlspecialchars($user['user_code']); ?></span>
                <span class="role-badge <?php echo $user['role_level'] ?? 'user'; ?>">
                    <?php echo htmlspecialchars($user['role_name'] ?? 'Unknown'); ?>
                </span>
                <span class="status-badge <?php echo $user['status']; ?>">
                    <?php echo ucfirst($user['status']); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Contact Information -->
    <div class="detail-section">
        <h4><i class="fas fa-address-card"></i> Contact Information</h4>
        <div class="detail-grid">
            <div class="detail-item">
                <span class="detail-label">Email</span>
                <span class="detail-value"><?php echo $user['email'] ? htmlspecialchars($user['email']) : '<span style="color:#8b9bb5;">Not set</span>'; ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Phone</span>
                <span class="detail-value"><?php echo $user['phone'] ? htmlspecialchars($user['phone']) : '<span style="color:#8b9bb5;">Not set</span>'; ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Tenant</span>
                <span class="detail-value"><?php echo $user['tenant_name'] ? htmlspecialchars($user['tenant_name']) : '<span style="color:#8b9bb5;">System</span>'; ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Role</span>
                <span class="detail-value"><?php echo htmlspecialchars($user['role_name'] ?? 'Unknown'); ?></span>
            </div>
        </div>
    </div>

    <!-- Account Information -->
    <div class="detail-section">
        <h4><i class="fas fa-user-cog"></i> Account Information</h4>
        <div class="detail-grid">
            <div class="detail-item">
                <span class="detail-label">User Code</span>
                <span class="detail-value"><?php echo htmlspecialchars($user['user_code']); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Status</span>
                <span class="detail-value">
                    <span class="status-badge <?php echo $user['status']; ?>">
                        <?php echo ucfirst($user['status']); ?>
                    </span>
                </span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Created</span>
                <span class="detail-value"><?php echo date('F d, Y H:i:s', strtotime($user['created_at'])); ?></span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Last Login</span>
                <span class="detail-value">
                    <?php if ($user['last_login_at']): ?>
                    <?php echo date('F d, Y H:i:s', strtotime($user['last_login_at'])); ?>
                    <span style="font-size:0.7rem; color:#8b9bb5; display:block;">
                        IP: <?php echo htmlspecialchars($user['last_login_ip'] ?? 'Unknown'); ?>
                    </span>
                    <?php else: ?>
                    <span style="color:#8b9bb5;">Never</span>
                    <?php endif; ?>
                </span>
            </div>
            <?php if ($user['last_login_device']): ?>
            <div class="detail-item">
                <span class="detail-label">Last Device</span>
                <span class="detail-value"><?php echo htmlspecialchars($user['last_login_device']); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($user['device_bound']): ?>
            <div class="detail-item">
                <span class="detail-label">Device Binding</span>
                <span class="detail-value"><span style="color:#10b981;"><i class="fas fa-check-circle"></i> Enabled</span></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Personal Information -->
    <?php if ($user['gender'] || $user['date_of_birth'] || $user['nin'] || $user['bvn']): ?>
    <div class="detail-section">
        <h4><i class="fas fa-id-card"></i> Personal Information</h4>
        <div class="detail-grid">
            <?php if ($user['gender']): ?>
            <div class="detail-item">
                <span class="detail-label">Gender</span>
                <span class="detail-value"><?php echo ucfirst($user['gender']); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($user['date_of_birth']): ?>
            <div class="detail-item">
                <span class="detail-label">Date of Birth</span>
                <span class="detail-value"><?php echo date('F d, Y', strtotime($user['date_of_birth'])); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($user['nin']): ?>
            <div class="detail-item">
                <span class="detail-label">NIN</span>
                <span class="detail-value"><?php echo htmlspecialchars($user['nin']); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($user['bvn']): ?>
            <div class="detail-item">
                <span class="detail-label">BVN</span>
                <span class="detail-value"><?php echo htmlspecialchars($user['bvn']); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Bank Information -->
    <?php if ($user['bank_name'] || $user['account_number'] || $user['account_name']): ?>
    <div class="detail-section">
        <h4><i class="fas fa-university"></i> Bank Information</h4>
        <div class="detail-grid">
            <?php if ($user['bank_name']): ?>
            <div class="detail-item">
                <span class="detail-label">Bank</span>
                <span class="detail-value"><?php echo htmlspecialchars($user['bank_name']); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($user['account_number']): ?>
            <div class="detail-item">
                <span class="detail-label">Account Number</span>
                <span class="detail-value"><?php echo htmlspecialchars($user['account_number']); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($user['account_name']): ?>
            <div class="detail-item">
                <span class="detail-label">Account Name</span>
                <span class="detail-value"><?php echo htmlspecialchars($user['account_name']); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Emergency Contact -->
    <?php if ($user['emergency_contact_name'] || $user['emergency_contact_phone']): ?>
    <div class="detail-section">
        <h4><i class="fas fa-ambulance"></i> Emergency Contact</h4>
        <div class="detail-grid">
            <?php if ($user['emergency_contact_name']): ?>
            <div class="detail-item">
                <span class="detail-label">Name</span>
                <span class="detail-value"><?php echo htmlspecialchars($user['emergency_contact_name']); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($user['emergency_contact_phone']): ?>
            <div class="detail-item">
                <span class="detail-label">Phone</span>
                <span class="detail-value"><?php echo htmlspecialchars($user['emergency_contact_phone']); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Address -->
    <?php if ($user['residential_address']): ?>
    <div class="detail-section">
        <h4><i class="fas fa-map-marker-alt"></i> Address</h4>
        <div class="detail-grid">
            <div class="detail-item" style="grid-column:1/3;">
                <span class="detail-label">Residential Address</span>
                <span class="detail-value"><?php echo nl2br(htmlspecialchars($user['residential_address'])); ?></span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Two-Factor Authentication -->
    <div class="detail-section">
        <h4><i class="fas fa-shield-alt"></i> Security</h4>
        <div class="detail-grid">
            <div class="detail-item">
                <span class="detail-label">Two-Factor Authentication</span>
                <span class="detail-value">
                    <?php if ($user['two_factor_enabled']): ?>
                    <span style="color:#10b981;"><i class="fas fa-check-circle"></i> Enabled</span>
                    <?php else: ?>
                    <span style="color:#8b9bb5;"><i class="fas fa-times-circle"></i> Disabled</span>
                    <?php endif; ?>
                </span>
            </div>
            <?php if ($user['two_factor_verified_at']): ?>
            <div class="detail-item">
                <span class="detail-label">Verified</span>
                <span class="detail-value"><?php echo date('F d, Y H:i:s', strtotime($user['two_factor_verified_at'])); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($user['phone_verified_at']): ?>
            <div class="detail-item">
                <span class="detail-label">Phone Verified</span>
                <span class="detail-value"><?php echo date('F d, Y H:i:s', strtotime($user['phone_verified_at'])); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($user['email_verified_at']): ?>
            <div class="detail-item">
                <span class="detail-label">Email Verified</span>
                <span class="detail-value"><?php echo date('F d, Y H:i:s', strtotime($user['email_verified_at'])); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.user-detail .detail-header {
    display: flex;
    align-items: center;
    gap: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eef3f8;
    margin-bottom: 20px;
}

.user-detail .detail-avatar {
    position: relative;
    width: 64px;
    height: 64px;
    flex-shrink: 0;
}

.user-detail .detail-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #eef3f8;
}

.user-detail .detail-avatar .avatar-placeholder {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 1.2rem;
    border: 3px solid #eef3f8;
}

.user-detail .detail-avatar .online-dot {
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    background: #10b981;
    border: 2.5px solid white;
    box-shadow: 0 0 10px rgba(16, 185, 129, 0.5);
    animation: pulse-dot 2s infinite;
}

.user-detail .detail-title h3 {
    font-size: 1.2rem;
    font-weight: 600;
    color: #0b1a33;
    margin: 0;
}

.user-detail .detail-meta {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 6px;
    flex-wrap: wrap;
}

.user-detail .detail-meta .user-code {
    font-size: 0.75rem;
    color: #8b9bb5;
    background: #f0f4fa;
    padding: 2px 12px;
    border-radius: 30px;
}

.user-detail .detail-section {
    margin-bottom: 20px;
}

.user-detail .detail-section h4 {
    font-size: 0.9rem;
    font-weight: 600;
    color: #1f3149;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.user-detail .detail-section h4 i {
    color: #4f9cf7;
}

.user-detail .detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.user-detail .detail-item {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 10px 14px;
    background: #f8faff;
    border-radius: 10px;
    border: 1px solid #f0f4fa;
}

.user-detail .detail-item .detail-label {
    font-size: 0.65rem;
    color: #8b9bb5;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.user-detail .detail-item .detail-value {
    font-size: 0.9rem;
    color: #1f3149;
    font-weight: 500;
}

.user-detail .status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.7rem;
    padding: 2px 12px;
    border-radius: 30px;
    font-weight: 500;
}

.user-detail .status-badge.active { background: #d1fae5; color: #065f46; }
.user-detail .status-badge.suspended { background: #fef3c7; color: #92400e; }
.user-detail .status-badge.pending { background: #dbeafe; color: #1e40af; }
.user-detail .status-badge.archived { background: #f3f4f6; color: #4b5563; }

.user-detail .role-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.7rem;
    padding: 2px 12px;
    border-radius: 30px;
    font-weight: 500;
}

.user-detail .role-badge.super_admin { background: #ede9fe; color: #5b21b6; }
.user-detail .role-badge.client_admin { background: #dbeafe; color: #1e40af; }
.user-detail .role-badge.national { background: #d1fae5; color: #065f46; }
.user-detail .role-badge.state { background: #fef3c7; color: #92400e; }
.user-detail .role-badge.lga { background: #fce4ec; color: #c62828; }
.user-detail .role-badge.ward { background: #e8f0fe; color: #4f9cf7; }
.user-detail .role-badge.pu_agent { background: #f3e8ff; color: #7c3aed; }
.user-detail .role-badge.default { background: #f5f5f5; color: #616161; }

@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.6; transform: scale(0.85); }
}

@media (max-width: 600px) {
    .user-detail .detail-grid {
        grid-template-columns: 1fr;
    }
    .user-detail .detail-header {
        flex-direction: column;
        text-align: center;
    }
    .user-detail .detail-meta {
        justify-content: center;
    }
}
</style>