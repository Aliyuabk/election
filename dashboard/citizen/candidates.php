<?php
// ============================================================
// CITIZEN PORTAL - CANDIDATES
// ============================================================
require_once '../../config/config.php';
require_once '../../includes/functions.php';

$page_title = 'Candidates';
$current_page = 'candidates';

$db = getDB();

// Get filters
$election_id = isset($_GET['election']) ? (int)$_GET['election'] : 0;
$party_filter = isset($_GET['party']) ? trim($_GET['party']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get elections
$elections = [];
try {
    $stmt = $db->prepare("
        SELECT id, name, type, election_date, status 
        FROM elections 
        WHERE status IN ('active', 'closed')
        ORDER BY election_date DESC
    ");
    $stmt->execute();
    $elections = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching elections: " . $e->getMessage());
}

// Get parties
$parties = [];
try {
    $stmt = $db->prepare("
        SELECT DISTINCT party_id, p.name, p.acronym, p.logo_url
        FROM candidates c
        LEFT JOIN political_parties p ON c.party_id = p.id
        WHERE c.is_active = 1
        ORDER BY p.name ASC
    ");
    $stmt->execute();
    $parties = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching parties: " . $e->getMessage());
}

// Build query
$candidates = [];
$total_results = 0;

try {
    $where = ["c.is_active = 1"];
    $params = [];
    
    if ($election_id > 0) {
        $where[] = "c.election_id = ?";
        $params[] = $election_id;
    }
    if (!empty($party_filter)) {
        $where[] = "(p.name LIKE ? OR p.acronym LIKE ?)";
        $params[] = "%$party_filter%";
        $params[] = "%$party_filter%";
    }
    if (!empty($search)) {
        $where[] = "(c.first_name LIKE ? OR c.last_name LIKE ? OR c.full_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_clause = implode(" AND ", $where);
    
    // Get count
    $count_sql = "SELECT COUNT(*) FROM candidates c 
                  LEFT JOIN political_parties p ON c.party_id = p.id 
                  WHERE " . $where_clause;
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_results = (int)$stmt->fetchColumn();
    
    // Get candidates
    $params[] = $limit;
    $params[] = $offset;
    
    $sql = "
        SELECT 
            c.*,
            p.name as party_name,
            p.acronym as party_acronym,
            p.logo_url as party_logo,
            e.name as election_name,
            e.type as election_type
        FROM candidates c
        LEFT JOIN political_parties p ON c.party_id = p.id
        LEFT JOIN elections e ON c.election_id = e.id
        WHERE " . $where_clause . "
        ORDER BY c.full_name ASC
        LIMIT ? OFFSET ?
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $candidates = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Error fetching candidates: " . $e->getMessage());
}

include '../includes/public-header.php';
?>

<style>
.filter-section {
    background: white;
    border-radius: 14px;
    padding: 20px;
    border: 1px solid #E5E7EB;
    margin-bottom: 24px;
}
.filter-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 12px;
}
.filter-grid select,
.filter-grid input {
    padding: 10px 14px;
    border: 1.5px solid #E5E7EB;
    border-radius: 10px;
    font-size: 0.85rem;
    width: 100%;
    background: white;
}
.filter-grid select:focus,
.filter-grid input:focus {
    outline: none;
    border-color: #0F4C81;
}
.filter-actions {
    display: flex;
    gap: 10px;
    margin-top: 12px;
    flex-wrap: wrap;
}
.btn-filter {
    padding: 10px 24px;
    background: #0F4C81;
    color: white;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.3s;
}
.btn-filter:hover {
    background: #1a6db5;
}
.btn-reset {
    padding: 10px 24px;
    background: #F3F4F6;
    color: #6B7280;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.3s;
    text-decoration: none;
}
.btn-reset:hover {
    background: #E5E7EB;
}

.candidate-card {
    background: white;
    border-radius: 14px;
    padding: 20px;
    border: 1px solid #E5E7EB;
    margin-bottom: 16px;
    display: flex;
    gap: 20px;
    align-items: flex-start;
    transition: box-shadow 0.2s;
}
.candidate-card:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,0.06);
}
.candidate-card .candidate-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: #0F4C81;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: 700;
    flex-shrink: 0;
    overflow: hidden;
}
.candidate-card .candidate-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.candidate-card .candidate-info {
    flex: 1;
}
.candidate-card .candidate-info .name {
    font-size: 1.1rem;
    font-weight: 700;
}
.candidate-card .candidate-info .party {
    display: inline-block;
    background: #F3F4F6;
    padding: 2px 14px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-top: 2px;
}
.candidate-card .candidate-info .party .party-logo {
    width: 20px;
    height: 20px;
    vertical-align: middle;
    margin-right: 4px;
}
.candidate-card .candidate-info .position {
    color: #6B7280;
    font-size: 0.85rem;
    margin-top: 4px;
}
.candidate-card .candidate-info .election {
    font-size: 0.75rem;
    color: #9CA3AF;
}
.candidate-card .candidate-info .biography {
    margin-top: 8px;
    font-size: 0.85rem;
    color: #4B5563;
    line-height: 1.5;
}
.candidate-card .candidate-info .biography .read-more {
    color: #0F4C81;
    cursor: pointer;
    font-weight: 500;
}
.candidate-card .candidate-info .manifesto {
    margin-top: 6px;
    font-size: 0.8rem;
    color: #6B7280;
    background: #F8FAFC;
    padding: 8px 12px;
    border-radius: 8px;
    border-left: 3px solid #0F4C81;
}

.results-stats {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 16px;
    padding: 12px 0;
    border-bottom: 1px solid #E5E7EB;
}
.results-stats .count {
    font-weight: 600;
    color: #0F4C81;
}
.results-stats .count span {
    font-weight: 700;
    font-size: 1.1rem;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 6px;
    margin-top: 20px;
    flex-wrap: wrap;
}
.pagination a,
.pagination span {
    padding: 8px 14px;
    border: 1px solid #E5E7EB;
    border-radius: 8px;
    text-decoration: none;
    color: #6B7280;
    font-size: 0.8rem;
    transition: all 0.2s;
}
.pagination a:hover {
    background: #0F4C81;
    color: white;
    border-color: #0F4C81;
}
.pagination .active {
    background: #0F4C81;
    color: white;
    border-color: #0F4C81;
}
.pagination .disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6B7280;
}
.empty-state i {
    font-size: 3rem;
    color: #D1D5DB;
    margin-bottom: 12px;
}
.empty-state h3 {
    font-size: 1.1rem;
    color: #1F2937;
    margin-bottom: 6px;
}

@media (max-width: 768px) {
    .filter-grid {
        grid-template-columns: 1fr;
    }
    .candidate-card {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    .candidate-card .candidate-info .biography {
        text-align: left;
    }
}
</style>

<div class="container">
    <h1 style="font-size:1.4rem;font-weight:700;margin-bottom:6px;">
        <i class="fas fa-user-tie" style="color:#0F4C81;"></i> Candidates
    </h1>
    <p style="color:#6B7280;margin-bottom:20px;font-size:0.9rem;">
        View all candidates participating in elections.
    </p>

    <!-- Filters -->
    <div class="filter-section">
        <form method="GET" action="">
            <div class="filter-grid">
                <select name="election">
                    <option value="">All Elections</option>
                    <?php foreach ($elections as $e): ?>
                        <option value="<?php echo $e['id']; ?>" <?php echo ($election_id == $e['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($e['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="party" placeholder="Search by party..." value="<?php echo htmlspecialchars($party_filter); ?>">
                <input type="text" name="search" placeholder="Search by name..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Apply Filters</button>
                <a href="candidates.php" class="btn-reset"><i class="fas fa-times"></i> Reset</a>
            </div>
        </form>
    </div>

    <!-- Results -->
    <?php if (!empty($candidates)): ?>
        <div class="results-stats">
            <div class="count">
                <i class="fas fa-users"></i> 
                <span><?php echo number_format($total_results); ?></span> candidates found
            </div>
        </div>

        <?php foreach ($candidates as $candidate): ?>
            <div class="candidate-card">
                <div class="candidate-avatar">
                    <?php if (!empty($candidate['photograph_url'])): ?>
                        <img src="<?php echo htmlspecialchars($candidate['photograph_url']); ?>" 
                             alt="<?php echo htmlspecialchars($candidate['full_name']); ?>">
                    <?php else: ?>
                        <?php 
                        $initials = '';
                        if (!empty($candidate['first_name'])) $initials .= $candidate['first_name'][0];
                        if (!empty($candidate['last_name'])) $initials .= $candidate['last_name'][0];
                        echo strtoupper($initials ?: '?');
                        ?>
                    <?php endif; ?>
                </div>
                <div class="candidate-info">
                    <div class="name"><?php echo htmlspecialchars($candidate['full_name']); ?></div>
                    <div class="party">
                        <?php if (!empty($candidate['party_logo'])): ?>
                            <img src="<?php echo htmlspecialchars($candidate['party_logo']); ?>" 
                                 alt="<?php echo htmlspecialchars($candidate['party_name']); ?>" 
                                 class="party-logo">
                        <?php endif; ?>
                        <?php echo htmlspecialchars($candidate['party_name'] ?? 'Independent'); ?>
                        <?php if (!empty($candidate['party_acronym'])): ?>
                            (<?php echo htmlspecialchars($candidate['party_acronym']); ?>)
                        <?php endif; ?>
                    </div>
                    <div class="position">
                        <i class="fas fa-bullseye"></i> 
                        <?php echo htmlspecialchars($candidate['position']); ?>
                    </div>
                    <div class="election">
                        <i class="fas fa-vote-yea"></i> 
                        <?php echo htmlspecialchars($candidate['election_name'] ?? 'Election'); ?>
                        <span style="text-transform:capitalize;margin-left:6px;background:#F3F4F6;padding:1px 8px;border-radius:12px;font-size:0.65rem;">
                            <?php echo str_replace('_', ' ', $candidate['election_type'] ?? ''); ?>
                        </span>
                    </div>
                    
                    <?php if (!empty($candidate['biography'])): ?>
                        <div class="biography">
                            <?php 
                            $bio = htmlspecialchars($candidate['biography']);
                            if (strlen($bio) > 200) {
                                echo substr($bio, 0, 200) . '...';
                            } else {
                                echo $bio;
                            }
                            ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($candidate['manifesto'])): ?>
                        <div class="manifesto">
                            <strong><i class="fas fa-bullhorn"></i> Manifesto:</strong>
                            <?php 
                            $manifesto = htmlspecialchars($candidate['manifesto']);
                            if (strlen($manifesto) > 150) {
                                echo substr($manifesto, 0, 150) . '...';
                            } else {
                                echo $manifesto;
                            }
                            ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($candidate['contact_email']) || !empty($candidate['contact_phone'])): ?>
                        <div style="margin-top:6px;font-size:0.75rem;color:#9CA3AF;">
                            <?php if (!empty($candidate['contact_email'])): ?>
                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($candidate['contact_email']); ?>
                            <?php endif; ?>
                            <?php if (!empty($candidate['contact_phone'])): ?>
                                <span style="margin-left:10px;"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($candidate['contact_phone']); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Pagination -->
        <?php if ($total_results > $limit): ?>
            <div class="pagination">
                <?php
                $total_pages = ceil($total_results / $limit);
                $query_params = $_GET;
                unset($query_params['page']);
                $base_url = '?' . http_build_query($query_params);
                
                if ($page > 1): ?>
                    <a href="<?php echo $base_url; ?>&page=<?php echo $page - 1; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="<?php echo $base_url; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="<?php echo $base_url; ?>&page=<?php echo $page + 1; ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-user-tie"></i>
            <h3>No Candidates Found</h3>
            <p>Try adjusting your filters or search terms.</p>
            <?php if (!empty($search) || !empty($party_filter) || $election_id > 0): ?>
                <a href="candidates.php" style="display:inline-block;margin-top:10px;padding:10px 24px;background:#0F4C81;color:white;border-radius:10px;text-decoration:none;font-weight:600;">
                    <i class="fas fa-undo"></i> Reset Filters
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/public-footer.php'; ?>