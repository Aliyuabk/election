<?php
// ============================================================
// FOOTER - Dashboard Pages
// ============================================================
?>

<!-- ============================================================
JAVASCRIPT - Master Dashboard
============================================================ -->
<script src="../../assets/js/dashboard.js"></script>

<!-- ============================================================
JAVASCRIPT - Role Specific
============================================================ -->
<?php 
$role_level = SessionManager::get('role_level', 'client_admin');
$js_file = '../../assets/js/' . $role_level . '-dashboard.js';

// Check if role-specific JS exists
if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $js_file)): ?>
<script src="<?php echo $js_file; ?>"></script>
<?php endif; ?>

<!-- ============================================================
JAVASCRIPT - Data Injection
============================================================ -->
<script>
// Inject PHP data into JavaScript
window.DASHBOARD_DATA = <?php 
    $data = [
        'verified' => $result_stats['verified'] ?? 0,
        'pending' => $result_stats['pending'] ?? 0,
        'flagged' => $result_stats['flagged'] ?? 0,
        'total' => $result_stats['total_results'] ?? 0,
        'reported' => $incident_stats['reported'] ?? 0,
        'investigating' => $incident_stats['investigating'] ?? 0,
        'resolved' => $incident_stats['resolved'] ?? 0,
    ];
    
    // Add role-specific data
    if (isset($top_states)) {
        $data['topStatesLabels'] = array_column($top_states, 'state_name');
        $data['topStatesData'] = array_column($top_states, 'verified_count');
    }
    if (isset($top_lgas)) {
        $data['topLgasLabels'] = array_column($top_lgas, 'lga_name');
        $data['topLgasData'] = array_column($top_lgas, 'verified_count');
    }
    if (isset($top_wards)) {
        $data['topWardsLabels'] = array_column($top_wards, 'ward_name');
        $data['topWardsData'] = array_column($top_wards, 'verified_count');
    }
    if (isset($top_pus)) {
        $data['topPUsLabels'] = array_column($top_pus, 'pu_name');
        $data['topPUsData'] = array_column($top_pus, 'verified_count');
    }
    if (isset($monthly_revenue)) {
        $data['months'] = array_column($monthly_revenue, 'month');
        $data['revenue'] = array_column($monthly_revenue, 'revenue');
    }
    if (isset($tenant_growth)) {
        $data['tenantGrowth'] = array_column($tenant_growth, 'count');
        $data['userGrowth'] = array_column($user_growth, 'count');
    }
    if (isset($assignment_stats)) {
        $data['assignmentStats'] = $assignment_stats;
    }
    
    echo json_encode($data);
?>;
</script>

</body>
</html>