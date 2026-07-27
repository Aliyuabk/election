<?php
// ============================================================
// AGENT ACCESS FUNCTIONS
// ============================================================

/**
 * Get agents accessible to a coordinator based on their role
 */
function getAccessibleAgents($coordinator_id, $role_level, $senatorial_id = null, $federal_constituency_id = null, $lga_id = null, $ward_id = null) {
    $db = getDB();
    $agents = [];
    
    try {
        $sql = "
            SELECT DISTINCT u.id, u.first_name, u.last_name, u.email, u.phone, 
                   u.pu_id, u.ward_id, u.lga_id, u.state_id,
                   u.senatorial_id, u.federal_constituency_id,
                   r.name as role_name, r.level as role_level,
                   pu.name as pu_name, w.name as ward_name, l.name as lga_name,
                   s.name as state_name,
                   aa.id as assignment_id, aa.status as assignment_status,
                   aa.assignment_type
            FROM users u
            JOIN roles r ON u.role_id = r.id
            LEFT JOIN polling_units pu ON u.pu_id = pu.id
            LEFT JOIN wards w ON u.ward_id = w.id
            LEFT JOIN lgas l ON u.lga_id = l.id
            LEFT JOIN states s ON u.state_id = s.id
            LEFT JOIN agent_assignments aa ON u.id = aa.user_id
            WHERE u.tenant_id = (SELECT tenant_id FROM users WHERE id = ?)
            AND u.status = 'active'
            AND r.level IN ('party_agent', 'pu_agent', 'volunteer', 'observer')
        ";
        
        $params = [$coordinator_id];
        
        // Apply jurisdiction filter based on coordinator role
        if ($role_level === 'senatorial' && $senatorial_id) {
            $sql .= " AND u.senatorial_id = ?";
            $params[] = $senatorial_id;
        } elseif ($role_level === 'federal_constituency' && $federal_constituency_id) {
            $sql .= " AND u.federal_constituency_id = ?";
            $params[] = $federal_constituency_id;
        } elseif ($role_level === 'lga' && $lga_id) {
            $sql .= " AND u.lga_id = ?";
            $params[] = $lga_id;
        } elseif ($role_level === 'ward' && $ward_id) {
            $sql .= " AND u.ward_id = ?";
            $params[] = $ward_id;
        }
        
        $sql .= " ORDER BY u.last_name, u.first_name ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $agents = $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Error fetching accessible agents: " . $e->getMessage());
    }
    
    return $agents;
}

/**
 * Get party agents count by jurisdiction
 */
function getPartyAgentsByJurisdiction($coordinator_id, $role_level, $senatorial_id = null, $federal_constituency_id = null) {
    $db = getDB();
    $result = [];
    
    try {
        $sql = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN aa.status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN aa.status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN aa.status = 'suspended' THEN 1 ELSE 0 END) as suspended
            FROM users u
            JOIN roles r ON u.role_id = r.id
            LEFT JOIN agent_assignments aa ON u.id = aa.user_id
            WHERE u.tenant_id = (SELECT tenant_id FROM users WHERE id = ?)
            AND u.status = 'active'
            AND r.level = 'party_agent'
        ";
        
        $params = [$coordinator_id];
        
        if ($role_level === 'senatorial' && $senatorial_id) {
            $sql .= " AND u.senatorial_id = ?";
            $params[] = $senatorial_id;
        } elseif ($role_level === 'federal_constituency' && $federal_constituency_id) {
            $sql .= " AND u.federal_constituency_id = ?";
            $params[] = $federal_constituency_id;
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        
    } catch (Exception $e) {
        error_log("Error fetching party agents: " . $e->getMessage());
    }
    
    return $result;
}

/**
 * Get agents by specific jurisdiction level
 */
function getAgentsBySenatorialDistrict($senatorial_id) {
    $db = getDB();
    $agents = [];
    
    try {
        $stmt = $db->prepare("
            SELECT u.*, r.name as role_name, r.level as role_level,
                   pu.name as pu_name, w.name as ward_name, l.name as lga_name
            FROM users u
            JOIN roles r ON u.role_id = r.id
            LEFT JOIN polling_units pu ON u.pu_id = pu.id
            LEFT JOIN wards w ON u.ward_id = w.id
            LEFT JOIN lgas l ON u.lga_id = l.id
            WHERE u.senatorial_id = ?
            AND u.status = 'active'
            AND r.level IN ('party_agent', 'pu_agent', 'volunteer', 'observer')
            ORDER BY r.level, u.last_name, u.first_name
        ");
        $stmt->execute([$senatorial_id]);
        $agents = $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Error fetching agents by senatorial district: " . $e->getMessage());
    }
    
    return $agents;
}

/**
 * Get agents by federal constituency
 */
function getAgentsByFederalConstituency($federal_constituency_id) {
    $db = getDB();
    $agents = [];
    
    try {
        $stmt = $db->prepare("
            SELECT u.*, r.name as role_name, r.level as role_level,
                   pu.name as pu_name, w.name as ward_name, l.name as lga_name
            FROM users u
            JOIN roles r ON u.role_id = r.id
            LEFT JOIN polling_units pu ON u.pu_id = pu.id
            LEFT JOIN wards w ON u.ward_id = w.id
            LEFT JOIN lgas l ON u.lga_id = l.id
            WHERE u.federal_constituency_id = ?
            AND u.status = 'active'
            AND r.level IN ('party_agent', 'pu_agent', 'volunteer', 'observer')
            ORDER BY r.level, u.last_name, u.first_name
        ");
        $stmt->execute([$federal_constituency_id]);
        $agents = $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Error fetching agents by federal constituency: " . $e->getMessage());
    }
    
    return $agents;
}

/**
 * Check if a coordinator has access to a specific agent
 */
function hasAgentAccess($coordinator_id, $agent_id) {
    $db = getDB();
    
    try {
        // Get coordinator role and jurisdiction
        $stmt = $db->prepare("
            SELECT role_id, senatorial_id, federal_constituency_id, lga_id, ward_id
            FROM users WHERE id = ?
        ");
        $stmt->execute([$coordinator_id]);
        $coordinator = $stmt->fetch();
        
        if (!$coordinator) return false;
        
        // Get coordinator role level
        $stmt = $db->prepare("SELECT level FROM roles WHERE id = ?");
        $stmt->execute([$coordinator['role_id']]);
        $role = $stmt->fetch();
        $role_level = $role['level'] ?? '';
        
        // Get agent jurisdiction
        $stmt = $db->prepare("
            SELECT senatorial_id, federal_constituency_id, lga_id, ward_id
            FROM users WHERE id = ?
        ");
        $stmt->execute([$agent_id]);
        $agent = $stmt->fetch();
        
        if (!$agent) return false;
        
        // Check access based on role
        switch ($role_level) {
            case 'senatorial':
                return $coordinator['senatorial_id'] == $agent['senatorial_id'];
            case 'federal_constituency':
                return $coordinator['federal_constituency_id'] == $agent['federal_constituency_id'];
            case 'lga':
                return $coordinator['lga_id'] == $agent['lga_id'];
            case 'ward':
                return $coordinator['ward_id'] == $agent['ward_id'];
            default:
                return false;
        }
        
    } catch (Exception $e) {
        error_log("Error checking agent access: " . $e->getMessage());
        return false;
    }
}