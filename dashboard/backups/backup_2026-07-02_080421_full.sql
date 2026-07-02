-- ============================================================
-- DATABASE BACKUP
-- Generated: 2026-07-02 08:04:21
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- Table: `activity_logs`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `activity_type` varchar(50) NOT NULL,
  `description` varchar(500) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `device_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_activity_user` (`user_id`),
  KEY `idx_activity_tenant` (`tenant_id`),
  KEY `idx_activity_type` (`activity_type`),
  KEY `idx_activity_created` (`created_at`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `activity_logs_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `activity_logs` (`id`, `user_id`, `tenant_id`, `activity_type`, `description`, `entity_type`, `entity_id`, `ip_address`, `device_id`, `created_at`) VALUES 
('1', '2', NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 00:01:44'),
('2', '2', NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-02 00:06:54'),
('3', '2', NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 00:24:33'),
('4', '2', NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-02 00:28:55'),
('5', '2', NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 00:36:06'),
('6', '2', NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-02 00:40:33'),
('7', '2', NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 00:41:13'),
('8', '2', NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 00:49:09'),
('9', '2', NULL, 'login', 'User logged in successfully', NULL, NULL, '10.180.98.13', NULL, '2026-07-02 01:30:15'),
('18', '2', NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 02:24:48'),
('19', '2', NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 05:52:20'),
('20', '2', '5', 'tenant_created', 'New tenant created: APC', NULL, NULL, '::1', NULL, '2026-07-02 06:06:43'),
('26', '2', NULL, 'password_reset', 'Password reset requested', NULL, NULL, '::1', NULL, '2026-07-02 06:52:52'),
('27', '2', NULL, 'password_change', 'Password reset successfully', NULL, NULL, '::1', NULL, '2026-07-02 06:53:15'),
('28', '2', NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 06:53:53'),
('29', '2', NULL, 'inec_clear', 'Cleared INEC data: polling_units', NULL, NULL, '::1', NULL, '2026-07-02 07:39:04'),
('30', '2', NULL, 'settings_changed', 'Email settings updated', NULL, NULL, '::1', NULL, '2026-07-02 07:54:08'),
('31', '2', NULL, 'settings_changed', 'Email settings updated', NULL, NULL, '::1', NULL, '2026-07-02 07:54:25');

-- ------------------------------------------------------------
-- Table: `agent_assignments`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `agent_assignments`;
CREATE TABLE `agent_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `election_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `pu_id` int(10) unsigned NOT NULL,
  `ward_id` int(10) unsigned NOT NULL,
  `lga_id` int(10) unsigned NOT NULL,
  `state_id` int(10) unsigned NOT NULL,
  `assignment_type` enum('data_agent','party_agent','volunteer','observer') NOT NULL,
  `status` enum('pending','active','completed','suspended','reassigned') NOT NULL DEFAULT 'pending',
  `assigned_by` bigint(20) unsigned NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_assignments_tenant` (`tenant_id`),
  KEY `idx_assignments_election` (`election_id`),
  KEY `idx_assignments_user` (`user_id`),
  KEY `idx_assignments_pu` (`pu_id`),
  KEY `idx_assignments_status` (`status`),
  KEY `assigned_by` (`assigned_by`),
  CONSTRAINT `agent_assignments_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `agent_assignments_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `agent_assignments_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `agent_assignments_ibfk_4` FOREIGN KEY (`pu_id`) REFERENCES `polling_units` (`id`),
  CONSTRAINT `agent_assignments_ibfk_5` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `agent_assignments` is empty

-- ------------------------------------------------------------
-- Table: `agent_checkins`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `agent_checkins`;
CREATE TABLE `agent_checkins` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `election_id` bigint(20) unsigned NOT NULL,
  `agent_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `pu_id` int(10) unsigned NOT NULL,
  `checkin_type` enum('arrival','departure','material_received','accreditation_started','voting_started','voting_ended','counting_started','counting_ended') NOT NULL,
  `gps_lat` decimal(10,8) NOT NULL,
  `gps_lng` decimal(11,8) NOT NULL,
  `gps_accuracy` decimal(6,2) DEFAULT NULL,
  `gps_distance_from_pu` decimal(8,2) DEFAULT NULL,
  `photo_url` varchar(500) DEFAULT NULL,
  `device_id` varchar(255) DEFAULT NULL,
  `device_battery` tinyint(3) unsigned DEFAULT NULL,
  `network_type` enum('2g','3g','4g','5g','wifi','none') DEFAULT NULL,
  `is_offline_sync` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_checkins_tenant` (`tenant_id`),
  KEY `idx_checkins_election` (`election_id`),
  KEY `idx_checkins_agent` (`agent_id`),
  KEY `idx_checkins_pu` (`pu_id`),
  KEY `idx_checkins_type` (`checkin_type`),
  CONSTRAINT `agent_checkins_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `agent_checkins_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `agent_checkins_ibfk_3` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`),
  CONSTRAINT `agent_checkins_ibfk_4` FOREIGN KEY (`pu_id`) REFERENCES `polling_units` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `agent_checkins` is empty

-- ------------------------------------------------------------
-- Table: `agent_payments`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `agent_payments`;
CREATE TABLE `agent_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `election_id` bigint(20) unsigned NOT NULL,
  `agent_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_type` enum('advance','daily_allowance','completion_bonus','transport','other') NOT NULL,
  `payment_method` enum('cash','bank_transfer','mobile_money') NOT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `account_number` varchar(20) DEFAULT NULL,
  `account_name` varchar(200) DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `status` enum('pending','processing','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  `paid_at` timestamp NULL DEFAULT NULL,
  `paid_by` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_agent_payments_tenant` (`tenant_id`),
  KEY `idx_agent_payments_election` (`election_id`),
  KEY `idx_agent_payments_agent` (`agent_id`),
  KEY `paid_by` (`paid_by`),
  CONSTRAINT `agent_payments_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `agent_payments_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `agent_payments_ibfk_3` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`),
  CONSTRAINT `agent_payments_ibfk_4` FOREIGN KEY (`paid_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `agent_payments` is empty

-- ------------------------------------------------------------
-- Table: `api_keys`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `api_keys`;
CREATE TABLE `api_keys` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `key_hash` varchar(255) NOT NULL,
  `key_prefix` varchar(20) NOT NULL,
  `permissions_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`permissions_json`)),
  `rate_limit` int(10) unsigned NOT NULL DEFAULT 1000,
  `rate_limit_window` int(10) unsigned NOT NULL DEFAULT 3600,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_api_keys_tenant` (`tenant_id`),
  KEY `idx_api_keys_prefix` (`key_prefix`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `api_keys_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `api_keys_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `api_keys` is empty

-- ------------------------------------------------------------
-- Table: `api_logs`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `api_logs`;
CREATE TABLE `api_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `api_key_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `method` varchar(10) NOT NULL,
  `endpoint` varchar(500) NOT NULL,
  `request_body` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_body`)),
  `response_status` smallint(5) unsigned DEFAULT NULL,
  `response_body` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_body`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `duration_ms` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_api_logs_key` (`api_key_id`),
  KEY `idx_api_logs_user` (`user_id`),
  KEY `idx_api_logs_endpoint` (`endpoint`(100)),
  KEY `idx_api_logs_created` (`created_at`),
  CONSTRAINT `api_logs_ibfk_1` FOREIGN KEY (`api_key_id`) REFERENCES `api_keys` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `api_logs` is empty

-- ------------------------------------------------------------
-- Table: `audit_logs`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` bigint(20) unsigned DEFAULT NULL,
  `old_values_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values_json`)),
  `new_values_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values_json`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `device_id` varchar(255) DEFAULT NULL,
  `gps_lat` decimal(10,8) DEFAULT NULL,
  `gps_lng` decimal(11,8) DEFAULT NULL,
  `severity` enum('info','warning','error','critical') NOT NULL DEFAULT 'info',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_tenant` (`tenant_id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_entity` (`entity_type`,`entity_id`),
  KEY `idx_audit_created` (`created_at`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `audit_logs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `audit_logs` is empty

-- ------------------------------------------------------------
-- Table: `backups`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `backups`;
CREATE TABLE `backups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `backup_type` enum('full','database','files','tenant_data') NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` bigint(20) unsigned NOT NULL,
  `file_sha256` varchar(64) NOT NULL,
  `status` enum('pending','in_progress','completed','failed','restored') NOT NULL DEFAULT 'pending',
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `restored_at` timestamp NULL DEFAULT NULL,
  `restored_by` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_backups_tenant` (`tenant_id`),
  KEY `idx_backups_status` (`status`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `backups_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `backups_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `backups` is empty

-- ------------------------------------------------------------
-- Table: `broadcasts`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `broadcasts`;
CREATE TABLE `broadcasts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `election_id` bigint(20) unsigned DEFAULT NULL,
  `sender_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `target_audience` enum('all','national','state','senatorial','federal_constituency','lga','ward','pu','role_specific') NOT NULL,
  `target_ids_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`target_ids_json`)),
  `target_role_id` bigint(20) unsigned DEFAULT NULL,
  `send_via` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`send_via`)),
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `status` enum('draft','scheduled','sending','sent','failed','cancelled') NOT NULL DEFAULT 'draft',
  `read_count` int(10) unsigned NOT NULL DEFAULT 0,
  `total_recipients` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_broadcasts_tenant` (`tenant_id`),
  KEY `idx_broadcasts_election` (`election_id`),
  KEY `idx_broadcasts_status` (`status`),
  KEY `sender_id` (`sender_id`),
  CONSTRAINT `broadcasts_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `broadcasts_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE SET NULL,
  CONSTRAINT `broadcasts_ibfk_3` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `broadcasts` is empty

-- ------------------------------------------------------------
-- Table: `budgets`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `budgets`;
CREATE TABLE `budgets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `election_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `spent_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `remaining_amount` decimal(15,2) GENERATED ALWAYS AS (`total_amount` - `spent_amount`) STORED,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('draft','active','closed','cancelled') NOT NULL DEFAULT 'draft',
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_budgets_tenant` (`tenant_id`),
  KEY `idx_budgets_election` (`election_id`),
  CONSTRAINT `budgets_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `budgets_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `budgets` is empty

-- ------------------------------------------------------------
-- Table: `candidates`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `candidates`;
CREATE TABLE `candidates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `election_id` bigint(20) unsigned NOT NULL,
  `party_id` bigint(20) unsigned DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `full_name` varchar(200) GENERATED ALWAYS AS (concat(`first_name`,' ',`last_name`)) STORED,
  `photograph_url` varchar(500) DEFAULT NULL,
  `position` varchar(100) NOT NULL,
  `biography` text DEFAULT NULL,
  `manifesto` text DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `social_media_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`social_media_json`)),
  `campaign_logo_url` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_candidates_tenant` (`tenant_id`),
  KEY `idx_candidates_election` (`election_id`),
  KEY `idx_candidates_party` (`party_id`),
  CONSTRAINT `candidates_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `candidates_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `candidates_ibfk_3` FOREIGN KEY (`party_id`) REFERENCES `political_parties` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `candidates` is empty

-- ------------------------------------------------------------
-- Table: `chat_messages`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `chat_messages`;
CREATE TABLE `chat_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `room_id` bigint(20) unsigned NOT NULL,
  `sender_id` bigint(20) unsigned NOT NULL,
  `message_type` enum('text','image','video','audio','file','location','system') NOT NULL DEFAULT 'text',
  `content` text NOT NULL,
  `media_url` varchar(500) DEFAULT NULL,
  `media_size` bigint(20) unsigned DEFAULT NULL,
  `media_sha256` varchar(64) DEFAULT NULL,
  `gps_lat` decimal(10,8) DEFAULT NULL,
  `gps_lng` decimal(11,8) DEFAULT NULL,
  `is_offline_sync` tinyint(1) NOT NULL DEFAULT 0,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_chat_messages_room` (`room_id`),
  KEY `idx_chat_messages_sender` (`sender_id`),
  KEY `idx_chat_messages_created` (`created_at`),
  CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `chat_rooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chat_messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `chat_messages` is empty

-- ------------------------------------------------------------
-- Table: `chat_room_members`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `chat_room_members`;
CREATE TABLE `chat_room_members` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `room_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `role` enum('admin','member') NOT NULL DEFAULT 'member',
  `last_read_message_id` bigint(20) unsigned DEFAULT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_chat_members` (`room_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `chat_room_members_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `chat_rooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chat_room_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `chat_room_members` is empty

-- ------------------------------------------------------------
-- Table: `chat_rooms`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `chat_rooms`;
CREATE TABLE `chat_rooms` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('direct','group','broadcast') NOT NULL DEFAULT 'group',
  `election_id` bigint(20) unsigned DEFAULT NULL,
  `jurisdiction_type` enum('national','state','senatorial','federal_constituency','lga','ward','pu') DEFAULT NULL,
  `jurisdiction_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_chat_rooms_tenant` (`tenant_id`),
  KEY `idx_chat_rooms_election` (`election_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `chat_rooms_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chat_rooms_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chat_rooms_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `chat_rooms` is empty

-- ------------------------------------------------------------
-- Table: `election_materials`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `election_materials`;
CREATE TABLE `election_materials` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `election_id` bigint(20) unsigned NOT NULL,
  `pu_id` int(10) unsigned NOT NULL,
  `agent_id` bigint(20) unsigned NOT NULL,
  `material_type` enum('ballot_papers','result_sheets','stamp','ink','bvas','generator','tent','chairs','tables','other') NOT NULL,
  `quantity_received` int(10) unsigned NOT NULL DEFAULT 0,
  `quantity_used` int(10) unsigned NOT NULL DEFAULT 0,
  `quantity_damaged` int(10) unsigned NOT NULL DEFAULT 0,
  `quantity_returned` int(10) unsigned NOT NULL DEFAULT 0,
  `condition` enum('excellent','good','fair','poor','missing') DEFAULT NULL,
  `photo_url` varchar(500) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_materials_tenant` (`tenant_id`),
  KEY `idx_materials_election` (`election_id`),
  KEY `idx_materials_pu` (`pu_id`),
  KEY `agent_id` (`agent_id`),
  CONSTRAINT `election_materials_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `election_materials_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `election_materials_ibfk_3` FOREIGN KEY (`pu_id`) REFERENCES `polling_units` (`id`),
  CONSTRAINT `election_materials_ibfk_4` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `election_materials` is empty

-- ------------------------------------------------------------
-- Table: `elections`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `elections`;
CREATE TABLE `elections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('presidential','governorship','senatorial','house_of_reps','house_of_assembly','lga_chairman','councillorship','party_primary','internal_party') NOT NULL,
  `cycle` varchar(20) NOT NULL,
  `election_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `states_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`states_json`)),
  `lgas_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`lgas_json`)),
  `wards_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`wards_json`)),
  `pus_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`pus_json`)),
  `status` enum('draft','upcoming','active','closed','cancelled','archived') NOT NULL DEFAULT 'draft',
  `description` text DEFAULT NULL,
  `logo_url` varchar(500) DEFAULT NULL,
  `settings_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings_json`)),
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_elections_tenant` (`tenant_id`),
  KEY `idx_elections_type` (`type`),
  KEY `idx_elections_status` (`status`),
  KEY `idx_elections_date` (`election_date`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `elections_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `elections_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `elections` is empty

-- ------------------------------------------------------------
-- Table: `expenses`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `budget_id` bigint(20) unsigned NOT NULL,
  `election_id` bigint(20) unsigned DEFAULT NULL,
  `category` enum('agent_payment','transport','materials','logistics','security','communication','media','other') NOT NULL,
  `description` text NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `receipt_url` varchar(500) DEFAULT NULL,
  `paid_to_user_id` bigint(20) unsigned DEFAULT NULL,
  `paid_by_user_id` bigint(20) unsigned NOT NULL,
  `payment_method` enum('cash','bank_transfer','mobile_money','cheque','other') NOT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `status` enum('pending','approved','rejected','paid') NOT NULL DEFAULT 'pending',
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `gps_lat` decimal(10,8) DEFAULT NULL,
  `gps_lng` decimal(11,8) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_expenses_tenant` (`tenant_id`),
  KEY `idx_expenses_budget` (`budget_id`),
  KEY `idx_expenses_status` (`status`),
  KEY `paid_to_user_id` (`paid_to_user_id`),
  KEY `paid_by_user_id` (`paid_by_user_id`),
  KEY `approved_by` (`approved_by`),
  CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`budget_id`) REFERENCES `budgets` (`id`),
  CONSTRAINT `expenses_ibfk_3` FOREIGN KEY (`paid_to_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_ibfk_4` FOREIGN KEY (`paid_by_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `expenses_ibfk_5` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `expenses` is empty

-- ------------------------------------------------------------
-- Table: `federal_constituencies`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `federal_constituencies`;
CREATE TABLE `federal_constituencies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `state_id` int(10) unsigned NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `lgas_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`lgas_json`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_fc_state_code` (`state_id`,`code`),
  CONSTRAINT `federal_constituencies_ibfk_1` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `federal_constituencies` is empty

-- ------------------------------------------------------------
-- Table: `incidents`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `incidents`;
CREATE TABLE `incidents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `election_id` bigint(20) unsigned DEFAULT NULL,
  `reporter_id` bigint(20) unsigned NOT NULL,
  `pu_id` int(10) unsigned DEFAULT NULL,
  `ward_id` int(10) unsigned DEFAULT NULL,
  `lga_id` int(10) unsigned DEFAULT NULL,
  `state_id` int(10) unsigned DEFAULT NULL,
  `incident_type` enum('violence','intimidation','ballot_stuffing','vote_buying','voter_suppression','material_shortage','delay','technical_issue','other','panic_button') NOT NULL,
  `severity` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `is_panic` tinyint(1) NOT NULL DEFAULT 0,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `gps_lat` decimal(10,8) DEFAULT NULL,
  `gps_lng` decimal(11,8) DEFAULT NULL,
  `gps_accuracy` decimal(6,2) DEFAULT NULL,
  `photo_urls_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`photo_urls_json`)),
  `video_url` varchar(500) DEFAULT NULL,
  `audio_url` varchar(500) DEFAULT NULL,
  `device_id` varchar(255) DEFAULT NULL,
  `status` enum('reported','acknowledged','investigating','resolved','escalated','false_alarm') NOT NULL DEFAULT 'reported',
  `assigned_to` bigint(20) unsigned DEFAULT NULL,
  `resolved_by` bigint(20) unsigned DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `is_offline_sync` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_incidents_tenant` (`tenant_id`),
  KEY `idx_incidents_election` (`election_id`),
  KEY `idx_incidents_reporter` (`reporter_id`),
  KEY `idx_incidents_pu` (`pu_id`),
  KEY `idx_incidents_type` (`incident_type`),
  KEY `idx_incidents_severity` (`severity`),
  KEY `idx_incidents_status` (`status`),
  KEY `idx_incidents_panic` (`is_panic`),
  KEY `idx_incidents_created` (`created_at`),
  KEY `assigned_to` (`assigned_to`),
  KEY `resolved_by` (`resolved_by`),
  CONSTRAINT `incidents_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `incidents_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incidents_ibfk_3` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`),
  CONSTRAINT `incidents_ibfk_4` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incidents_ibfk_5` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `incidents` is empty

-- ------------------------------------------------------------
-- Table: `invoices`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `invoices`;
CREATE TABLE `invoices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `subscription_id` bigint(20) unsigned DEFAULT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `tax_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL,
  `status` enum('draft','sent','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
  `due_date` date NOT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `paid_by` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `idx_invoices_tenant` (`tenant_id`),
  KEY `idx_invoices_status` (`status`),
  KEY `subscription_id` (`subscription_id`),
  CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `invoices` is empty

-- ------------------------------------------------------------
-- Table: `lgas`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `lgas`;
CREATE TABLE `lgas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `state_id` int(10) unsigned NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `gps_lat` decimal(10,8) DEFAULT NULL,
  `gps_lng` decimal(11,8) DEFAULT NULL,
  `registered_voters` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_lgas_state_code` (`state_id`,`code`),
  KEY `idx_lgas_state` (`state_id`),
  CONSTRAINT `lgas_ibfk_1` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=792 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `lgas` (`id`, `state_id`, `code`, `name`, `gps_lat`, `gps_lng`, `registered_voters`, `is_active`) VALUES 
('18', '1', 'AB001', 'Aba North', '5.10660000', '7.36670000', '128213', '1'),
('19', '1', 'AB002', 'Aba South', '5.11670000', '7.35000000', '210714', '1'),
('20', '1', 'AB003', 'Arochukwu', '5.38330000', '7.91670000', '68612', '1'),
('21', '1', 'AB004', 'Bende', '5.55860000', '7.63390000', '113301', '1'),
('22', '1', 'AB005', 'Ikwuano', '5.41670000', '7.56670000', '70313', '1'),
('23', '1', 'AB006', 'Isiala Ngwa North', '5.36670000', '7.40000000', '120890', '1'),
('24', '1', 'AB007', 'Isiala Ngwa South', '5.30000000', '7.45000000', '117492', '1'),
('25', '1', 'AB008', 'Isuikwuato', '5.70000000', '7.46670000', '58628', '1'),
('26', '1', 'AB009', 'Obi Ngwa', '5.05000000', '7.36670000', '113474', '1'),
('27', '1', 'AB010', 'Ohafia', '5.61670000', '7.81670000', '143940', '1'),
('28', '1', 'AB011', 'Osisioma', '5.16670000', '7.31670000', '117169', '1'),
('29', '1', 'AB012', 'Ugwunagbo', '5.01670000', '7.38330000', '71187', '1'),
('30', '1', 'AB013', 'Ukwa East', '4.75000000', '7.40000000', '65856', '1'),
('31', '1', 'AB014', 'Ukwa West', '4.85000000', '7.35000000', '71184', '1'),
('32', '1', 'AB015', 'Umuahia North', '5.53330000', '7.48330000', '134028', '1'),
('33', '1', 'AB016', 'Umuahia South', '5.50000000', '7.45000000', '102092', '1'),
('34', '1', 'AB017', 'Umu Nneochi', '5.70000000', '7.36670000', '75158', '1'),
('35', '2', 'AD001', 'Demsa', '9.45000000', '12.15000000', '54455', '1'),
('36', '2', 'AD002', 'Fufure', '9.35000000', '12.25000000', '47169', '1'),
('37', '2', 'AD003', 'Ganye', '8.43330000', '12.05000000', '54655', '1'),
('38', '2', 'AD004', 'Gayuk', '9.86670000', '12.00000000', '47969', '1'),
('39', '2', 'AD005', 'Gombi', '10.16670000', '12.73330000', '62271', '1'),
('40', '2', 'AD006', 'Grie', '10.15000000', '13.23330000', '48835', '1'),
('41', '2', 'AD007', 'Hong', '10.23330000', '12.95000000', '86226', '1'),
('42', '2', 'AD008', 'Jada', '8.73330000', '12.15000000', '59470', '1'),
('43', '2', 'AD009', 'Lamurde', '9.60000000', '11.80000000', '64121', '1'),
('44', '2', 'AD010', 'Madagali', '10.86670000', '13.63330000', '60183', '1'),
('45', '2', 'AD011', 'Maiha', '9.98330000', '13.21670000', '60854', '1'),
('46', '2', 'AD012', 'Mayo-Belwa', '9.05000000', '12.05000000', '71038', '1'),
('47', '2', 'AD013', 'Michika', '10.61670000', '13.38330000', '87608', '1'),
('48', '2', 'AD014', 'Mubi North', '10.26670000', '13.26670000', '108961', '1'),
('49', '2', 'AD015', 'Mubi South', '10.06670000', '13.28330000', '64739', '1'),
('50', '2', 'AD016', 'Numan', '9.46670000', '12.03330000', '67145', '1'),
('51', '2', 'AD017', 'Shelleng', '9.90000000', '11.80000000', '49920', '1'),
('52', '2', 'AD018', 'Song', '9.81670000', '12.61670000', '85716', '1'),
('53', '2', 'AD019', 'Toungo', '8.11670000', '12.05000000', '40181', '1'),
('54', '2', 'AD020', 'Yola North', '9.23330000', '12.46670000', '127650', '1'),
('55', '2', 'AD021', 'Yola South', '9.16670000', '12.41670000', '93024', '1'),
('56', '3', 'AK001', 'Abak', '4.98330000', '7.78330000', '74715', '1'),
('57', '3', 'AK002', 'Eastern Obolo', '4.53330000', '7.71670000', '41582', '1'),
('58', '3', 'AK003', 'Eket', '4.63330000', '7.93330000', '112149', '1'),
('59', '3', 'AK004', 'Esit Eket', '4.63330000', '7.86670000', '30229', '1'),
('60', '3', 'AK005', 'Essien Udim', '5.21670000', '7.70000000', '71812', '1'),
('61', '3', 'AK006', 'Etim Ekpo', '5.08330000', '7.65000000', '34227', '1'),
('62', '3', 'AK007', 'Etinan', '4.85000000', '7.85000000', '48704', '1'),
('63', '3', 'AK008', 'Ibeno', '4.56670000', '7.96670000', '32410', '1'),
('64', '3', 'AK009', 'Ibesikpo Asutan', '4.80000000', '7.95000000', '54942', '1'),
('65', '3', 'AK010', 'Ibiono-Ibom', '5.11670000', '7.96670000', '54836', '1'),
('66', '3', 'AK011', 'Ika', '5.10000000', '7.66670000', '40018', '1'),
('67', '3', 'AK012', 'Ikono', '5.33330000', '7.71670000', '50981', '1'),
('68', '3', 'AK013', 'Ikot Abasi', '4.58330000', '7.60000000', '51161', '1'),
('69', '3', 'AK014', 'Ikot Ekpene', '5.18330000', '7.71670000', '77515', '1'),
('70', '3', 'AK015', 'Ini', '5.21670000', '7.86670000', '43201', '1'),
('71', '3', 'AK016', 'Itu', '5.20000000', '7.98330000', '48264', '1'),
('72', '3', 'AK017', 'Mbo', '4.65000000', '8.00000000', '61695', '1'),
('73', '3', 'AK018', 'Mkpat-Enin', '4.73330000', '7.75000000', '59472', '1'),
('74', '3', 'AK019', 'Nsit-Atai', '4.85000000', '7.90000000', '34097', '1'),
('75', '3', 'AK020', 'Nsit-Ibom', '4.90000000', '7.93330000', '42357', '1'),
('76', '3', 'AK021', 'Nsit-Ubium', '4.90000000', '7.96670000', '43045', '1'),
('77', '3', 'AK022', 'Obot Akara', '5.16670000', '7.65000000', '37822', '1'),
('78', '3', 'AK023', 'Okobo', '4.85000000', '8.03330000', '51095', '1'),
('79', '3', 'AK024', 'Onna', '4.70000000', '7.86670000', '48875', '1'),
('80', '3', 'AK025', 'Oron', '4.81670000', '8.23330000', '84951', '1'),
('81', '3', 'AK026', 'Oruk Anam', '5.10000000', '7.56670000', '61392', '1'),
('82', '3', 'AK027', 'Udung-Uko', '4.75000000', '8.06670000', '30353', '1'),
('83', '3', 'AK028', 'Ukanafun', '5.01670000', '7.60000000', '45745', '1'),
('84', '3', 'AK029', 'Uruan', '5.03330000', '8.01670000', '38367', '1'),
('85', '3', 'AK030', 'Urue-Offong/Oruko', '4.75000000', '8.08330000', '31184', '1'),
('86', '3', 'AK031', 'Uyo', '5.05000000', '7.93330000', '121537', '1'),
('87', '4', 'AN001', 'Aguata', '6.01670000', '7.08330000', '105545', '1'),
('88', '4', 'AN002', 'Anambra East', '6.28330000', '6.83330000', '93237', '1'),
('89', '4', 'AN003', 'Anambra West', '6.35000000', '6.73330000', '58231', '1'),
('90', '4', 'AN004', 'Anaocha', '6.08330000', '7.01670000', '89934', '1'),
('91', '4', 'AN005', 'Awka North', '6.26670000', '7.06670000', '83527', '1'),
('92', '4', 'AN006', 'Awka South', '6.21670000', '7.06670000', '134647', '1'),
('93', '4', 'AN007', 'Ayamelum', '6.23330000', '6.96670000', '62145', '1'),
('94', '4', 'AN008', 'Dunukofia', '6.26670000', '6.91670000', '82352', '1'),
('95', '4', 'AN009', 'Ekwusigo', '6.11670000', '6.83330000', '68916', '1'),
('96', '4', 'AN010', 'Idemili North', '6.18330000', '6.91670000', '141561', '1'),
('97', '4', 'AN011', 'Idemili South', '6.06670000', '6.90000000', '85777', '1'),
('98', '4', 'AN012', 'Ihiala', '5.85000000', '6.85000000', '127700', '1'),
('99', '4', 'AN013', 'Njikoka', '6.21670000', '7.01670000', '96969', '1'),
('100', '4', 'AN014', 'Nnewi North', '6.01670000', '6.91670000', '104382', '1'),
('101', '4', 'AN015', 'Nnewi South', '5.98330000', '6.86670000', '87134', '1'),
('102', '4', 'AN016', 'Ogbaru', '6.13330000', '6.66670000', '79862', '1'),
('103', '4', 'AN017', 'Onitsha North', '6.16670000', '6.78330000', '124025', '1'),
('104', '4', 'AN018', 'Onitsha South', '6.15000000', '6.78330000', '78935', '1'),
('105', '4', 'AN019', 'Orumba North', '6.06670000', '7.13330000', '70973', '1'),
('106', '4', 'AN020', 'Orumba South', '6.00000000', '7.08330000', '68378', '1'),
('107', '4', 'AN021', 'Oyi', '6.26670000', '6.86670000', '85352', '1'),
('108', '5', 'BA001', 'Alkaleri', '10.26670000', '10.21670000', '81339', '1'),
('109', '5', 'BA002', 'Bauchi', '10.31570000', '9.84420000', '294888', '1'),
('110', '5', 'BA003', 'Bogoro', '9.66670000', '9.60000000', '43884', '1'),
('111', '5', 'BA004', 'Damban', '11.63330000', '10.70000000', '54318', '1'),
('112', '5', 'BA005', 'Darazo', '10.98330000', '10.41670000', '91623', '1'),
('113', '5', 'BA006', 'Dass', '10.00000000', '9.51670000', '52644', '1'),
('114', '5', 'BA007', 'Gamawa', '12.13330000', '10.53330000', '141975', '1'),
('115', '5', 'BA008', 'Ganjuwa', '10.86670000', '10.20000000', '96583', '1'),
('116', '5', 'BA009', 'Giade', '11.40000000', '10.16670000', '76149', '1'),
('117', '5', 'BA010', 'Itas/Gadau', '11.83330000', '9.96670000', '85680', '1'),
('118', '5', 'BA011', 'Jama\'are', '11.66670000', '9.91670000', '99532', '1'),
('119', '5', 'BA012', 'Katagum', '12.28330000', '10.35000000', '178716', '1'),
('120', '5', 'BA013', 'Kirfi', '10.40000000', '10.35000000', '53081', '1'),
('121', '5', 'BA014', 'Misau', '11.31670000', '10.46670000', '122542', '1'),
('122', '5', 'BA015', 'Ningi', '11.08330000', '9.56670000', '170356', '1'),
('123', '5', 'BA016', 'Shira', '11.50000000', '10.20000000', '87426', '1'),
('124', '5', 'BA017', 'Tafawa Balewa', '9.75000000', '9.55000000', '106305', '1'),
('125', '5', 'BA018', 'Toro', '10.06670000', '9.06670000', '142447', '1'),
('126', '5', 'BA019', 'Warji', '11.18330000', '9.75000000', '51927', '1'),
('127', '5', 'BA020', 'Zaki', '12.38330000', '10.06670000', '116219', '1'),
('128', '6', 'BY001', 'Brass', '4.31670000', '6.23330000', '111841', '1'),
('129', '6', 'BY002', 'Ekeremor', '5.05000000', '5.75000000', '88298', '1'),
('130', '6', 'BY003', 'Kolokuma/Opokuma', '5.06670000', '6.15000000', '58479', '1'),
('131', '6', 'BY004', 'Nembe', '4.53330000', '6.40000000', '82718', '1'),
('132', '6', 'BY005', 'Ogbia', '4.68330000', '6.31670000', '141451', '1'),
('133', '6', 'BY006', 'Sagbama', '5.16670000', '6.18330000', '105237', '1'),
('134', '6', 'BY007', 'Southern Ijaw', '4.46670000', '5.70000000', '177691', '1'),
('135', '6', 'BY008', 'Yenagoa', '4.91670000', '6.26670000', '156376', '1'),
('136', '7', 'BE001', 'Ado', '7.46670000', '8.01670000', '32728', '1'),
('137', '7', 'BE002', 'Agatu', '7.93330000', '7.91670000', '45706', '1'),
('138', '7', 'BE003', 'Apa', '7.63330000', '7.86670000', '39970', '1'),
('139', '7', 'BE004', 'Buruku', '7.48330000', '8.93330000', '96836', '1'),
('140', '7', 'BE005', 'Gboko', '7.33330000', '9.00000000', '169211', '1'),
('141', '7', 'BE006', 'Guma', '7.61670000', '8.36670000', '80717', '1'),
('142', '7', 'BE007', 'Gwer East', '7.90000000', '8.50000000', '98113', '1'),
('143', '7', 'BE008', 'Gwer West', '7.36670000', '8.15000000', '59405', '1'),
('144', '7', 'BE009', 'Katsina-Ala', '7.16670000', '9.28330000', '118718', '1'),
('145', '7', 'BE010', 'Konshisha', '7.06670000', '8.56670000', '82796', '1'),
('146', '7', 'BE011', 'Kwande', '6.71670000', '9.30000000', '118745', '1'),
('147', '7', 'BE012', 'Logo', '7.28330000', '8.90000000', '72969', '1'),
('148', '7', 'BE013', 'Makurdi', '7.73330000', '8.53330000', '194716', '1'),
('149', '7', 'BE014', 'Obi', '7.01670000', '8.20000000', '54566', '1'),
('150', '7', 'BE015', 'Ogbadibo', '7.00000000', '7.86670000', '51848', '1'),
('151', '7', 'BE016', 'Ohimini', '7.48330000', '7.68330000', '36230', '1'),
('152', '7', 'BE017', 'Oju', '6.85000000', '8.41670000', '77750', '1'),
('153', '7', 'BE018', 'Okpokwu', '7.01670000', '7.73330000', '77767', '1'),
('154', '7', 'BE019', 'Otukpo', '7.20000000', '8.13330000', '117813', '1'),
('155', '7', 'BE020', 'Tarka', '7.51670000', '8.75000000', '42950', '1'),
('156', '7', 'BE021', 'Ukum', '7.00000000', '9.16670000', '97900', '1'),
('157', '7', 'BE022', 'Ushongo', '7.16670000', '8.68330000', '68197', '1'),
('158', '7', 'BE023', 'Vandeikya', '6.78330000', '8.96670000', '74397', '1'),
('159', '8', 'BO001', 'Abadam', '13.61670000', '13.26670000', '36660', '1'),
('160', '8', 'BO002', 'Askira/Uba', '10.65000000', '12.91670000', '65668', '1'),
('161', '8', 'BO003', 'Bama', '11.51670000', '13.68330000', '64652', '1'),
('162', '8', 'BO004', 'Bayo', '10.45000000', '12.16670000', '50276', '1'),
('163', '8', 'BO005', 'Biu', '10.60000000', '12.20000000', '103194', '1'),
('164', '8', 'BO006', 'Chibok', '10.86670000', '12.85000000', '26858', '1'),
('165', '8', 'BO007', 'Damboa', '11.15000000', '12.75000000', '54577', '1'),
('166', '8', 'BO008', 'Dikwa', '12.03330000', '13.91670000', '43873', '1'),
('167', '8', 'BO009', 'Gubio', '12.48330000', '12.78330000', '57515', '1'),
('168', '8', 'BO010', 'Guzamala', '13.01670000', '13.16670000', '32721', '1'),
('169', '8', 'BO011', 'Gwoza', '11.08330000', '13.70000000', '79891', '1'),
('170', '8', 'BO012', 'Hawul', '10.46670000', '12.25000000', '75298', '1'),
('171', '8', 'BO013', 'Jere', '11.80000000', '13.15000000', '208447', '1'),
('172', '8', 'BO014', 'Kaga', '12.03330000', '12.90000000', '50005', '1'),
('173', '8', 'BO015', 'Kala/Balge', '12.33330000', '14.66670000', '34470', '1'),
('174', '8', 'BO016', 'Konduga', '11.65000000', '13.41670000', '56714', '1'),
('175', '8', 'BO017', 'Kukawa', '12.91670000', '13.56670000', '42958', '1'),
('176', '8', 'BO018', 'Kwaya Kusar', '10.46670000', '12.05000000', '29341', '1'),
('177', '8', 'BO019', 'Mafa', '11.91670000', '13.60000000', '61302', '1'),
('178', '8', 'BO020', 'Magumeri', '12.11670000', '12.75000000', '54083', '1'),
('179', '8', 'BO021', 'Maiduguri', '11.83330000', '13.15000000', '414619', '1'),
('180', '8', 'BO022', 'Marte', '12.36670000', '13.83330000', '54001', '1'),
('181', '8', 'BO023', 'Mobbar', '12.26670000', '13.33330000', '37760', '1'),
('182', '8', 'BO024', 'Monguno', '12.66670000', '13.61670000', '60596', '1'),
('183', '8', 'BO025', 'Ngala', '12.33330000', '14.18330000', '36525', '1'),
('184', '8', 'BO026', 'Nganzai', '12.86670000', '13.20000000', '33548', '1'),
('185', '8', 'BO027', 'Shani', '10.21670000', '12.06670000', '51846', '1'),
('186', '9', 'CR001', 'Abi', '5.85000000', '8.10000000', '65178', '1'),
('187', '9', 'CR002', 'Akamkpa', '5.31670000', '8.35000000', '73876', '1'),
('188', '9', 'CR003', 'Akpabuyo', '4.98330000', '8.36670000', '39892', '1'),
('189', '9', 'CR004', 'Bakassi', '4.70000000', '8.56670000', '17444', '1'),
('190', '9', 'CR005', 'Bekwarra', '6.73330000', '8.80000000', '44335', '1'),
('191', '9', 'CR006', 'Biase', '5.50000000', '8.05000000', '44043', '1'),
('192', '9', 'CR007', 'Boki', '6.00000000', '8.93330000', '85206', '1'),
('193', '9', 'CR008', 'Calabar Municipal', '4.95000000', '8.33330000', '192944', '1'),
('194', '9', 'CR009', 'Calabar South', '4.91670000', '8.31670000', '200456', '1'),
('195', '9', 'CR010', 'Etung', '5.90000000', '8.65000000', '47212', '1'),
('196', '9', 'CR011', 'Ikom', '6.08330000', '8.61670000', '96221', '1'),
('197', '9', 'CR012', 'Obanliku', '6.45000000', '9.20000000', '41010', '1'),
('198', '9', 'CR013', 'Obubra', '6.08330000', '8.31670000', '65269', '1'),
('199', '9', 'CR014', 'Obudu', '6.66670000', '9.16670000', '77152', '1'),
('200', '9', 'CR015', 'Odukpani', '5.11670000', '8.40000000', '90336', '1'),
('201', '9', 'CR016', 'Ogoja', '6.65000000', '8.80000000', '101466', '1'),
('202', '9', 'CR017', 'Yakuur', '5.73330000', '8.13330000', '51372', '1'),
('203', '9', 'CR018', 'Yala', '6.70000000', '8.46670000', '86319', '1'),
('204', '10', 'DE001', 'Aniocha North', '6.20000000', '6.70000000', '79056', '1'),
('205', '10', 'DE002', 'Aniocha South', '6.15000000', '6.71670000', '60062', '1'),
('206', '10', 'DE003', 'Bomadi', '5.16670000', '5.93330000', '60322', '1'),
('207', '10', 'DE004', 'Burutu', '5.35000000', '5.50000000', '111278', '1'),
('208', '10', 'DE005', 'Ethiope East', '5.61670000', '6.06670000', '112347', '1'),
('209', '10', 'DE006', 'Ethiope West', '5.71670000', '5.98330000', '70840', '1'),
('210', '10', 'DE007', 'Ika North East', '6.20000000', '6.31670000', '85204', '1'),
('211', '10', 'DE008', 'Ika South', '6.16670000', '6.28330000', '105851', '1'),
('212', '10', 'DE009', 'Isoko North', '5.48330000', '6.33330000', '97968', '1'),
('213', '10', 'DE010', 'Isoko South', '5.45000000', '6.30000000', '115337', '1'),
('214', '10', 'DE011', 'Ndokwa East', '5.70000000', '6.41670000', '98925', '1'),
('215', '10', 'DE012', 'Ndokwa West', '5.73330000', '6.21670000', '95716', '1'),
('216', '10', 'DE013', 'Okpe', '5.86670000', '5.98330000', '82674', '1'),
('217', '10', 'DE014', 'Oshimili North', '6.26670000', '6.68330000', '105114', '1'),
('218', '10', 'DE015', 'Oshimili South', '6.21670000', '6.73330000', '117005', '1'),
('219', '10', 'DE016', 'Patani', '5.23330000', '6.08330000', '61579', '1'),
('220', '10', 'DE017', 'Sapele', '5.90000000', '5.66670000', '118393', '1'),
('221', '10', 'DE018', 'Udu', '5.51670000', '5.78330000', '93654', '1'),
('222', '10', 'DE019', 'Ughelli North', '5.50000000', '6.00000000', '180774', '1'),
('223', '10', 'DE020', 'Ughelli South', '5.38330000', '5.95000000', '119220', '1'),
('224', '10', 'DE021', 'Ukwuani', '5.90000000', '6.20000000', '54307', '1'),
('225', '10', 'DE022', 'Uvwie', '5.56670000', '5.76670000', '142102', '1'),
('226', '10', 'DE023', 'Warri North', '5.70000000', '5.45000000', '92267', '1'),
('227', '10', 'DE024', 'Warri South', '5.51670000', '5.75000000', '241607', '1'),
('228', '10', 'DE025', 'Warri South West', '5.56670000', '5.55000000', '100436', '1'),
('229', '11', 'EB001', 'Abakaliki', '6.33330000', '8.10000000', '165303', '1'),
('230', '11', 'EB002', 'Afikpo North', '5.90000000', '7.93330000', '84180', '1'),
('231', '11', 'EB003', 'Afikpo South', '5.83330000', '7.96670000', '56119', '1'),
('232', '11', 'EB004', 'Ebonyi', '6.20000000', '8.10000000', '60506', '1'),
('233', '11', 'EB005', 'Ezza North', '6.21670000', '8.03330000', '73183', '1'),
('234', '11', 'EB006', 'Ezza South', '6.16670000', '8.01670000', '60145', '1'),
('235', '11', 'EB007', 'Ikwo', '6.10000000', '8.10000000', '97704', '1'),
('236', '11', 'EB008', 'Ishielu', '6.08330000', '7.86670000', '77250', '1'),
('237', '11', 'EB009', 'Ivo', '5.96670000', '7.86670000', '51992', '1'),
('238', '11', 'EB010', 'Izzi', '6.30000000', '8.15000000', '107465', '1'),
('239', '11', 'EB011', 'Ohaozara', '6.01670000', '7.75000000', '64049', '1'),
('240', '11', 'EB012', 'Ohaukwu', '6.43330000', '8.06670000', '112539', '1'),
('241', '11', 'EB013', 'Onicha', '6.06670000', '7.80000000', '64353', '1'),
('242', '12', 'ED001', 'Akoko-Edo', '7.05000000', '6.11670000', '76989', '1'),
('243', '12', 'ED002', 'Egor', '6.35000000', '5.60000000', '120287', '1'),
('244', '12', 'ED003', 'Esan Central', '6.66670000', '6.31670000', '50114', '1'),
('245', '12', 'ED004', 'Esan North-East', '6.75000000', '6.38330000', '52758', '1'),
('246', '12', 'ED005', 'Esan South-East', '6.60000000', '6.35000000', '50297', '1'),
('247', '12', 'ED006', 'Esan West', '6.61670000', '6.25000000', '62479', '1'),
('248', '12', 'ED007', 'Etsako Central', '7.06670000', '6.40000000', '45797', '1'),
('249', '12', 'ED008', 'Etsako East', '7.08330000', '6.50000000', '64365', '1'),
('250', '12', 'ED009', 'Etsako West', '7.15000000', '6.53330000', '126878', '1'),
('251', '12', 'ED010', 'Igueben', '6.60000000', '6.25000000', '35837', '1'),
('252', '12', 'ED011', 'Ikpoba-Okha', '6.38330000', '5.60000000', '163170', '1'),
('253', '12', 'ED012', 'Oredo', '6.33330000', '5.61670000', '298103', '1'),
('254', '12', 'ED013', 'Orhionmwon', '6.20000000', '5.85000000', '78330', '1'),
('255', '12', 'ED014', 'Ovia North-East', '6.45000000', '5.13330000', '82518', '1'),
('256', '12', 'ED015', 'Ovia South-West', '6.28330000', '5.16670000', '65307', '1'),
('257', '12', 'ED016', 'Owan East', '7.03330000', '6.05000000', '56638', '1'),
('258', '12', 'ED017', 'Owan West', '7.10000000', '5.96670000', '64052', '1'),
('259', '12', 'ED018', 'Uhunmwonde', '6.40000000', '5.81670000', '55640', '1'),
('260', '13', 'EK001', 'Ado Ekiti', '7.63330000', '5.21670000', '127389', '1'),
('261', '13', 'EK002', 'Efon', '7.50000000', '5.10000000', '31232', '1'),
('262', '13', 'EK003', 'Ekiti East', '7.70000000', '5.33330000', '55256', '1'),
('263', '13', 'EK004', 'Ekiti South-West', '7.53330000', '5.08330000', '48323', '1'),
('264', '13', 'EK005', 'Ekiti West', '7.68330000', '5.11670000', '48369', '1'),
('265', '13', 'EK006', 'Emure', '7.43330000', '5.46670000', '29889', '1'),
('266', '13', 'EK007', 'Gbonyin', '7.60000000', '5.30000000', '39945', '1'),
('267', '13', 'EK008', 'Ido Osi', '7.75000000', '5.20000000', '61603', '1'),
('268', '13', 'EK009', 'Ijero', '7.81670000', '5.06670000', '57169', '1'),
('269', '13', 'EK010', 'Ikere', '7.50000000', '5.23330000', '72637', '1'),
('270', '13', 'EK011', 'Ikole', '7.80000000', '5.50000000', '84356', '1'),
('271', '13', 'EK012', 'Ilejemeje', '7.56670000', '5.23330000', '19198', '1'),
('272', '13', 'EK013', 'Irepodun/Ifelodun', '7.90000000', '5.01670000', '48809', '1'),
('273', '13', 'EK014', 'Ise/Orun', '7.46670000', '5.41670000', '40104', '1'),
('274', '13', 'EK015', 'Moba', '8.06670000', '5.21670000', '47637', '1'),
('275', '13', 'EK016', 'Oye', '7.70000000', '5.33330000', '62715', '1'),
('276', '14', 'EN001', 'Aninri', '5.90000000', '7.63330000', '63706', '1'),
('277', '14', 'EN002', 'Awgu', '6.06670000', '7.46670000', '74876', '1'),
('278', '14', 'EN003', 'Enugu East', '6.45000000', '7.50000000', '133717', '1'),
('279', '14', 'EN004', 'Enugu North', '6.45000000', '7.48330000', '142253', '1'),
('280', '14', 'EN005', 'Enugu South', '6.41670000', '7.46670000', '115063', '1'),
('281', '14', 'EN006', 'Ezeagu', '6.23330000', '7.41670000', '72961', '1'),
('282', '14', 'EN007', 'Igbo Etiti', '6.38330000', '7.30000000', '89409', '1'),
('283', '14', 'EN008', 'Igbo Eze North', '6.61670000', '7.60000000', '96294', '1'),
('284', '14', 'EN009', 'Igbo Eze South', '6.53330000', '7.55000000', '66642', '1'),
('285', '14', 'EN010', 'Isi Uzo', '6.20000000', '7.60000000', '55273', '1'),
('286', '14', 'EN011', 'Nkanu East', '6.35000000', '7.70000000', '75591', '1'),
('287', '14', 'EN012', 'Nkanu West', '6.36670000', '7.56670000', '92886', '1'),
('288', '14', 'EN013', 'Nsukka', '6.86670000', '7.38330000', '166777', '1'),
('289', '14', 'EN014', 'Oji River', '6.26670000', '7.26670000', '60978', '1'),
('290', '14', 'EN015', 'Udenu', '6.70000000', '7.40000000', '92831', '1'),
('291', '14', 'EN016', 'Udi', '6.31670000', '7.40000000', '105756', '1'),
('292', '14', 'EN017', 'Uzo Uwani', '6.61670000', '7.01670000', '65948', '1'),
('293', '15', 'FC001', 'Abaji', '8.38330000', '6.95000000', '42088', '1'),
('294', '15', 'FC002', 'Bwari', '9.16670000', '7.33330000', '180187', '1'),
('295', '15', 'FC003', 'Gwagwalada', '8.93330000', '7.08330000', '159933', '1'),
('296', '15', 'FC004', 'Kuje', '8.88330000', '7.21670000', '117071', '1'),
('297', '15', 'FC005', 'Kwali', '8.85000000', '6.83330000', '95226', '1'),
('298', '15', 'FC006', 'Municipal Area Council', '9.06670000', '7.48330000', '1007197', '1'),
('299', '16', 'GO001', 'Akko', '10.28330000', '10.96670000', '141369', '1'),
('300', '16', 'GO002', 'Balanga', '9.96670000', '11.68330000', '66215', '1'),
('301', '16', 'GO003', 'Billiri', '9.86670000', '11.21670000', '89358', '1'),
('302', '16', 'GO004', 'Dukku', '10.81670000', '10.76670000', '86306', '1'),
('303', '16', 'GO005', 'Funakaye', '10.50000000', '11.40000000', '87055', '1'),
('304', '16', 'GO006', 'Gombe', '10.28330000', '11.16670000', '198635', '1'),
('305', '16', 'GO007', 'Kaltungo', '9.81670000', '11.30000000', '100843', '1'),
('306', '16', 'GO008', 'Kwami', '10.48330000', '11.23330000', '96685', '1'),
('307', '16', 'GO009', 'Nafada', '11.10000000', '11.33330000', '71727', '1'),
('308', '16', 'GO010', 'Shongom', '9.95000000', '11.11670000', '70072', '1'),
('309', '16', 'GO011', 'Yamaltu/Deba', '10.16670000', '11.38330000', '113911', '1'),
('310', '17', 'IM001', 'Aboh Mbaise', '5.48330000', '7.21670000', '76019', '1'),
('311', '17', 'IM002', 'Ahiazu Mbaise', '5.46670000', '7.31670000', '63178', '1'),
('312', '17', 'IM003', 'Ehime Mbano', '5.73330000', '7.36670000', '53627', '1'),
('313', '17', 'IM004', 'Ezinihitte', '5.66670000', '7.26670000', '60206', '1'),
('314', '17', 'IM005', 'Ideato North', '5.85000000', '7.08330000', '70075', '1'),
('315', '17', 'IM006', 'Ideato South', '5.80000000', '7.01670000', '59018', '1'),
('316', '17', 'IM007', 'Ihitte/Uboma', '5.70000000', '7.33330000', '49277', '1'),
('317', '17', 'IM008', 'Ikeduru', '5.65000000', '7.13330000', '67111', '1'),
('318', '17', 'IM009', 'Isiala Mbano', '5.68330000', '7.28330000', '57184', '1'),
('319', '17', 'IM010', 'Isu', '5.71670000', '6.98330000', '42522', '1'),
('320', '17', 'IM011', 'Mbaitoli', '5.50000000', '6.98330000', '116455', '1'),
('321', '17', 'IM012', 'Ngor Okpala', '5.36670000', '7.16670000', '80321', '1'),
('322', '17', 'IM013', 'Njaba', '5.76670000', '7.06670000', '51947', '1'),
('323', '17', 'IM014', 'Nkwerre', '5.71670000', '7.13330000', '54953', '1'),
('324', '17', 'IM015', 'Nwangele', '5.76670000', '7.13330000', '52107', '1'),
('325', '17', 'IM016', 'Obowo', '5.61670000', '7.26670000', '55338', '1'),
('326', '17', 'IM017', 'Oguta', '5.40000000', '6.96670000', '51841', '1'),
('327', '17', 'IM018', 'Ohaji/Egbema', '5.46670000', '6.80000000', '58606', '1'),
('328', '17', 'IM019', 'Okigwe', '5.83330000', '7.33330000', '108352', '1'),
('329', '17', 'IM020', 'Orlu', '5.80000000', '7.03330000', '135142', '1'),
('330', '17', 'IM021', 'Orsu', '5.71670000', '6.93330000', '58397', '1'),
('331', '17', 'IM022', 'Oru East', '5.58330000', '6.96670000', '51578', '1'),
('332', '17', 'IM023', 'Oru West', '5.56670000', '6.91670000', '47142', '1'),
('333', '17', 'IM024', 'Owerri Municipal', '5.48330000', '7.03330000', '170745', '1'),
('334', '17', 'IM025', 'Owerri North', '5.51670000', '7.01670000', '100527', '1'),
('335', '17', 'IM026', 'Owerri West', '5.45000000', '7.00000000', '56127', '1'),
('336', '17', 'IM027', 'Unuimo', '5.70000000', '7.35000000', '38641', '1'),
('337', '18', 'JI001', 'Auyo', '12.33330000', '9.93330000', '74595', '1'),
('338', '18', 'JI002', 'Babura', '12.75000000', '9.01670000', '79288', '1'),
('339', '18', 'JI003', 'Biriniwa', '12.21670000', '10.23330000', '68511', '1'),
('340', '18', 'JI004', 'Birnin Kudu', '11.45000000', '9.50000000', '139695', '1'),
('341', '18', 'JI005', 'Buji', '11.50000000', '9.31670000', '62093', '1'),
('342', '18', 'JI006', 'Dutse', '11.80000000', '9.33330000', '163612', '1'),
('343', '18', 'JI007', 'Gagarawa', '12.40000000', '9.53330000', '51531', '1'),
('344', '18', 'JI008', 'Garki', '12.43330000', '9.18330000', '80986', '1'),
('345', '18', 'JI009', 'Gumel', '12.63330000', '9.38330000', '97055', '1'),
('346', '18', 'JI010', 'Guri', '12.73330000', '10.41670000', '46100', '1'),
('347', '18', 'JI011', 'Gwaram', '11.28330000', '9.88330000', '128537', '1'),
('348', '18', 'JI012', 'Gwiwa', '12.78330000', '8.33330000', '52172', '1'),
('349', '18', 'JI013', 'Hadejia', '12.45000000', '10.03330000', '123530', '1'),
('350', '18', 'JI014', 'Jahun', '12.06670000', '9.63330000', '80108', '1'),
('351', '18', 'JI015', 'Kafin Hausa', '12.23330000', '9.91670000', '112798', '1'),
('352', '18', 'JI016', 'Kazaure', '12.65000000', '8.41670000', '87058', '1'),
('353', '18', 'JI017', 'Kiri Kasama', '12.36670000', '10.26670000', '78530', '1'),
('354', '18', 'JI018', 'Kiyawa', '11.78330000', '9.61670000', '78131', '1'),
('355', '18', 'JI019', 'Kaugama', '12.46670000', '9.73330000', '89623', '1'),
('356', '18', 'JI020', 'Maigatari', '12.81670000', '9.45000000', '58638', '1'),
('357', '18', 'JI021', 'Malam Madori', '12.56670000', '9.88330000', '78206', '1'),
('358', '18', 'JI022', 'Miga', '12.23330000', '9.71670000', '70935', '1'),
('359', '18', 'JI023', 'Ringim', '12.15000000', '9.16670000', '112843', '1'),
('360', '18', 'JI024', 'Roni', '12.66670000', '8.25000000', '49689', '1'),
('361', '18', 'JI025', 'Sule Tankarkar', '12.66670000', '9.21670000', '49755', '1'),
('362', '18', 'JI026', 'Taura', '12.28330000', '9.28330000', '72267', '1'),
('363', '18', 'JI027', 'Yankwashi', '12.73330000', '8.53330000', '45433', '1'),
('364', '19', 'KD001', 'Birnin Gwari', '10.66670000', '6.53330000', '115230', '1'),
('365', '19', 'KD002', 'Chikun', '10.25000000', '7.00000000', '132771', '1'),
('366', '19', 'KD003', 'Giwa', '11.31670000', '7.45000000', '74839', '1'),
('367', '19', 'KD004', 'Igabi', '10.81670000', '7.71670000', '107680', '1'),
('368', '19', 'KD005', 'Ikara', '11.18330000', '8.21670000', '95178', '1'),
('369', '19', 'KD006', 'Jaba', '9.90000000', '8.06670000', '39361', '1'),
('370', '19', 'KD007', 'Jema\'a', '9.60000000', '8.20000000', '158242', '1'),
('371', '19', 'KD008', 'Kachia', '9.86670000', '7.95000000', '98907', '1'),
('372', '19', 'KD009', 'Kaduna North', '10.51670000', '7.43330000', '305910', '1'),
('373', '19', 'KD010', 'Kaduna South', '10.46670000', '7.41670000', '327085', '1'),
('374', '19', 'KD011', 'Kagarko', '9.83330000', '7.00000000', '78846', '1'),
('375', '19', 'KD012', 'Kajuru', '10.31670000', '7.68330000', '59582', '1'),
('376', '19', 'KD013', 'Kaura', '9.68330000', '8.46670000', '61806', '1'),
('377', '19', 'KD014', 'Kauru', '10.58330000', '8.15000000', '72388', '1'),
('378', '19', 'KD015', 'Kubau', '11.06670000', '8.00000000', '58930', '1'),
('379', '19', 'KD016', 'Kudan', '11.26670000', '7.73330000', '80938', '1'),
('380', '19', 'KD017', 'Lere', '10.38330000', '8.58330000', '139602', '1'),
('381', '19', 'KD018', 'Makarfi', '11.38330000', '7.88330000', '67718', '1'),
('382', '19', 'KD019', 'Sabon Gari', '11.20000000', '7.73330000', '186008', '1'),
('383', '19', 'KD020', 'Sanga', '9.21670000', '8.66670000', '41091', '1'),
('384', '19', 'KD021', 'Soba', '10.98330000', '8.05000000', '87075', '1'),
('385', '19', 'KD022', 'Zangon Kataf', '9.78330000', '8.28330000', '117282', '1'),
('386', '19', 'KD023', 'Zaria', '11.06670000', '7.70000000', '390692', '1'),
('387', '20', 'KN001', 'Ajingi', '11.96670000', '9.05000000', '89825', '1'),
('388', '20', 'KN002', 'Albasu', '11.66670000', '9.13330000', '122975', '1'),
('389', '20', 'KN003', 'Bagwai', '12.15000000', '8.13330000', '90058', '1'),
('390', '20', 'KN004', 'Bebeji', '11.66670000', '8.26670000', '115432', '1'),
('391', '20', 'KN005', 'Bichi', '12.23330000', '8.23330000', '177143', '1'),
('392', '20', 'KN006', 'Bunkure', '11.70000000', '8.55000000', '112806', '1'),
('393', '20', 'KN007', 'Dala', '12.00000000', '8.51670000', '241509', '1'),
('394', '20', 'KN008', 'Dambatta', '12.43330000', '8.51670000', '129920', '1'),
('395', '20', 'KN009', 'Dawakin Kudu', '11.83330000', '8.36670000', '149665', '1'),
('396', '20', 'KN010', 'Dawakin Tofa', '12.10000000', '8.33330000', '112749', '1'),
('397', '20', 'KN011', 'Doguwa', '10.71670000', '8.73330000', '99980', '1'),
('398', '20', 'KN012', 'Fagge', '12.00000000', '8.53330000', '161824', '1'),
('399', '20', 'KN013', 'Gabasawa', '12.15000000', '8.45000000', '116315', '1'),
('400', '20', 'KN014', 'Garko', '11.65000000', '8.80000000', '105516', '1'),
('401', '20', 'KN015', 'Garun Mallam', '11.68330000', '8.38330000', '107248', '1'),
('402', '20', 'KN016', 'Gaya', '11.86670000', '9.00000000', '120993', '1'),
('403', '20', 'KN017', 'Gezawa', '12.11670000', '8.75000000', '126335', '1'),
('404', '20', 'KN018', 'Gwale', '12.01670000', '8.48330000', '188519', '1'),
('405', '20', 'KN019', 'Gwarzo', '11.91670000', '7.93330000', '124961', '1'),
('406', '20', 'KN020', 'Kabo', '11.85000000', '8.13330000', '92302', '1'),
('407', '20', 'KN021', 'Kano Municipal', '12.00000000', '8.51670000', '278022', '1'),
('408', '20', 'KN022', 'Karaye', '11.78330000', '8.01670000', '125382', '1'),
('409', '20', 'KN023', 'Kibiya', '11.53330000', '8.66670000', '69749', '1'),
('410', '20', 'KN024', 'Kiru', '11.70000000', '8.13330000', '170690', '1'),
('411', '20', 'KN025', 'Kumbotso', '11.91670000', '8.51670000', '174631', '1'),
('412', '20', 'KN026', 'Kunchi', '12.10000000', '8.26670000', '70610', '1'),
('413', '20', 'KN027', 'Kura', '11.76670000', '8.41670000', '119836', '1'),
('414', '20', 'KN028', 'Madobi', '11.78330000', '8.30000000', '112807', '1'),
('415', '20', 'KN029', 'Makoda', '12.40000000', '8.43330000', '102226', '1'),
('416', '20', 'KN030', 'Minjibir', '12.16670000', '8.60000000', '85821', '1'),
('417', '20', 'KN031', 'Nasarawa', '12.01670000', '8.51670000', '196673', '1'),
('418', '20', 'KN032', 'Rano', '11.56670000', '8.58330000', '133918', '1'),
('419', '20', 'KN033', 'Rimin Gado', '11.96670000', '8.25000000', '83913', '1'),
('420', '20', 'KN034', 'Rogo', '11.55000000', '7.83330000', '118470', '1'),
('421', '20', 'KN035', 'Shanono', '12.05000000', '7.98330000', '56155', '1'),
('422', '20', 'KN036', 'Sumaila', '11.53330000', '8.96670000', '99712', '1'),
('423', '20', 'KN037', 'Takai', '11.56670000', '9.31670000', '112909', '1'),
('424', '20', 'KN038', 'Tarauni', '12.01670000', '8.51670000', '215743', '1'),
('425', '20', 'KN039', 'Tofa', '12.06670000', '8.26670000', '98822', '1'),
('426', '20', 'KN040', 'Tsanyawa', '12.30000000', '8.00000000', '53346', '1'),
('427', '20', 'KN041', 'Tudun Wada', '11.25000000', '8.40000000', '115009', '1'),
('428', '20', 'KN042', 'Ungogo', '12.10000000', '8.48330000', '209550', '1'),
('429', '20', 'KN043', 'Warawa', '11.86670000', '8.70000000', '106871', '1'),
('430', '20', 'KN044', 'Wudil', '11.80000000', '8.83330000', '112577', '1'),
('431', '21', 'KT001', 'Bakori', '11.55000000', '7.43330000', '84410', '1'),
('432', '21', 'KT002', 'Batagarawa', '12.90000000', '7.60000000', '62263', '1'),
('433', '21', 'KT003', 'Batsari', '12.75000000', '7.25000000', '72781', '1'),
('434', '21', 'KT004', 'Baure', '12.83330000', '8.73330000', '96069', '1'),
('435', '21', 'KT005', 'Bindawa', '12.66670000', '7.81670000', '72073', '1'),
('436', '21', 'KT006', 'Charanchi', '12.63330000', '7.71670000', '65991', '1'),
('437', '21', 'KT007', 'Dandume', '11.46670000', '7.21670000', '72880', '1'),
('438', '21', 'KT008', 'Danja', '11.38330000', '7.55000000', '70308', '1'),
('439', '21', 'KT009', 'Dan Musa', '12.26670000', '7.33330000', '57810', '1'),
('440', '21', 'KT010', 'Daura', '13.03330000', '8.31670000', '103647', '1'),
('441', '21', 'KT011', 'Dutsi', '12.83330000', '8.13330000', '47341', '1'),
('442', '21', 'KT012', 'Dutsin-Ma', '12.45000000', '7.48330000', '119993', '1'),
('443', '21', 'KT013', 'Faskari', '11.71670000', '7.01670000', '90934', '1'),
('444', '21', 'KT014', 'Funtua', '11.51670000', '7.31670000', '159043', '1'),
('445', '21', 'KT015', 'Ingawa', '12.63330000', '8.01670000', '80743', '1'),
('446', '21', 'KT016', 'Jibia', '13.08330000', '7.21670000', '83797', '1'),
('447', '21', 'KT017', 'Kafur', '11.65000000', '7.68330000', '68414', '1'),
('448', '21', 'KT018', 'Kaita', '12.96670000', '7.71670000', '84772', '1'),
('449', '21', 'KT019', 'Kankara', '11.93330000', '7.41670000', '128768', '1'),
('450', '21', 'KT020', 'Kankia', '12.55000000', '7.81670000', '85180', '1'),
('451', '21', 'KT021', 'Katsina', '12.98330000', '7.60000000', '271942', '1'),
('452', '21', 'KT022', 'Kurfi', '12.66670000', '7.48330000', '63520', '1'),
('453', '21', 'KT023', 'Kusada', '12.46670000', '8.50000000', '51149', '1'),
('454', '21', 'KT024', 'Mai\'Adua', '13.18330000', '8.23330000', '95527', '1'),
('455', '21', 'KT025', 'Malumfashi', '11.78330000', '7.61670000', '123357', '1'),
('456', '21', 'KT026', 'Mani', '12.85000000', '7.86670000', '88089', '1'),
('457', '21', 'KT027', 'Mashi', '12.98330000', '7.95000000', '71187', '1'),
('458', '21', 'KT028', 'Matazu', '12.23330000', '7.66670000', '70065', '1'),
('459', '21', 'KT029', 'Musawa', '12.11670000', '7.66670000', '89498', '1'),
('460', '21', 'KT030', 'Rimi', '12.85000000', '7.70000000', '70617', '1'),
('461', '21', 'KT031', 'Sabuwa', '11.16670000', '7.06670000', '47369', '1'),
('462', '21', 'KT032', 'Safana', '12.41670000', '7.21670000', '65054', '1'),
('463', '21', 'KT033', 'Sandamu', '12.96670000', '8.38330000', '63682', '1'),
('464', '21', 'KT034', 'Zango', '12.91670000', '8.48330000', '115691', '1'),
('465', '22', 'KE001', 'Aleiro', '12.00000000', '4.46670000', '41953', '1'),
('466', '22', 'KE002', 'Arewa Dandi', '12.31670000', '4.13330000', '76764', '1'),
('467', '22', 'KE003', 'Argungu', '12.73330000', '4.53330000', '117665', '1'),
('468', '22', 'KE004', 'Augie', '12.88330000', '4.60000000', '64210', '1'),
('469', '22', 'KE005', 'Bagudo', '11.40000000', '4.21670000', '102098', '1'),
('470', '22', 'KE006', 'Birnin Kebbi', '12.45000000', '4.20000000', '159583', '1'),
('471', '22', 'KE007', 'Bunza', '12.08330000', '4.01670000', '60515', '1'),
('472', '22', 'KE008', 'Dandi', '11.90000000', '4.00000000', '95158', '1'),
('473', '22', 'KE009', 'Fakai', '11.48330000', '4.06670000', '33124', '1'),
('474', '22', 'KE010', 'Gwandu', '12.50000000', '4.63330000', '67432', '1'),
('475', '22', 'KE011', 'Jega', '12.21670000', '4.38330000', '82691', '1'),
('476', '22', 'KE012', 'Kalgo', '12.31670000', '4.20000000', '64393', '1'),
('477', '22', 'KE013', 'Koko/Besse', '11.41670000', '4.51670000', '64828', '1'),
('478', '22', 'KE014', 'Maiyama', '12.06670000', '4.36670000', '73954', '1'),
('479', '22', 'KE015', 'Ngaski', '10.41670000', '4.70000000', '45747', '1'),
('480', '22', 'KE016', 'Sakaba', '11.06670000', '5.60000000', '41853', '1'),
('481', '22', 'KE017', 'Shanga', '11.21670000', '4.58330000', '57388', '1'),
('482', '22', 'KE018', 'Suru', '11.63330000', '4.01670000', '94722', '1'),
('483', '22', 'KE019', 'Wasagu/Danko', '11.35000000', '5.38330000', '63714', '1'),
('484', '22', 'KE020', 'Yauri', '10.83330000', '4.81670000', '63232', '1'),
('485', '22', 'KE021', 'Zuru', '11.45000000', '5.23330000', '93313', '1'),
('486', '23', 'KO001', 'Adavi', '7.71670000', '6.46670000', '78672', '1'),
('487', '23', 'KO002', 'Ajaokuta', '7.56670000', '6.65000000', '107305', '1'),
('488', '23', 'KO003', 'Ankpa', '7.38330000', '7.63330000', '119841', '1'),
('489', '23', 'KO004', 'Bassa', '7.90000000', '6.40000000', '60045', '1'),
('490', '23', 'KO005', 'Dekina', '7.70000000', '6.85000000', '144129', '1'),
('491', '23', 'KO006', 'Ibaji', '6.85000000', '6.88330000', '46450', '1'),
('492', '23', 'KO007', 'Idah', '7.10000000', '6.73330000', '58943', '1'),
('493', '23', 'KO008', 'Igalamela-Odolu', '7.08330000', '6.83330000', '70314', '1'),
('494', '23', 'KO009', 'Ijumu', '7.85000000', '5.96670000', '62911', '1'),
('495', '23', 'KO010', 'Kabba/Bunu', '7.83330000', '6.06670000', '86054', '1'),
('496', '23', 'KO011', 'Kogi', '7.93330000', '6.71670000', '24516', '1'),
('497', '23', 'KO012', 'Lokoja', '7.80000000', '6.73330000', '150607', '1'),
('498', '23', 'KO013', 'Mopa-Muro', '7.91670000', '5.83330000', '38196', '1'),
('499', '23', 'KO014', 'Ofu', '7.46670000', '6.98330000', '67072', '1'),
('500', '23', 'KO015', 'Ogori/Magongo', '7.78330000', '6.20000000', '31792', '1'),
('501', '23', 'KO016', 'Okehi', '7.63330000', '6.36670000', '67181', '1'),
('502', '23', 'KO017', 'Okene', '7.55000000', '6.23330000', '182535', '1'),
('503', '23', 'KO018', 'Olamaboro', '7.46670000', '7.50000000', '76787', '1'),
('504', '23', 'KO019', 'Omala', '7.83330000', '7.08330000', '50321', '1'),
('505', '23', 'KO020', 'Yagba East', '8.11670000', '5.81670000', '56520', '1'),
('506', '23', 'KO021', 'Yagba West', '8.13330000', '5.66670000', '49248', '1'),
('507', '24', 'KW001', 'Asa', '8.46670000', '4.46670000', '75111', '1'),
('508', '24', 'KW002', 'Baruten', '9.20000000', '3.30000000', '63330', '1'),
('509', '24', 'KW003', 'Edu', '8.86670000', '4.53330000', '99471', '1'),
('510', '24', 'KW004', 'Ekiti', '8.15000000', '5.41670000', '44569', '1'),
('511', '24', 'KW005', 'Ifelodun', '8.41670000', '4.83330000', '78964', '1'),
('512', '24', 'KW006', 'Ilorin East', '8.50000000', '4.55000000', '132103', '1'),
('513', '24', 'KW007', 'Ilorin South', '8.48330000', '4.53330000', '109897', '1'),
('514', '24', 'KW008', 'Ilorin West', '8.48330000', '4.50000000', '236775', '1'),
('515', '24', 'KW009', 'Irepodun', '8.21670000', '5.10000000', '60672', '1'),
('516', '24', 'KW010', 'Isin', '8.31670000', '5.05000000', '35794', '1'),
('517', '24', 'KW011', 'Kaiama', '9.60000000', '3.95000000', '56875', '1'),
('518', '24', 'KW012', 'Moro', '8.70000000', '4.63330000', '73873', '1'),
('519', '24', 'KW013', 'Offa', '8.15000000', '4.71670000', '97476', '1'),
('520', '24', 'KW014', 'Oke Ero', '8.15000000', '5.13330000', '39557', '1'),
('521', '24', 'KW015', 'Oyun', '8.26670000', '4.78330000', '58752', '1'),
('522', '24', 'KW016', 'Pategi', '8.73330000', '5.75000000', '64531', '1'),
('523', '25', 'LA001', 'Agege', '6.61670000', '3.33330000', '226934', '1'),
('524', '25', 'LA002', 'Ajeromi-Ifelodun', '6.45000000', '3.33330000', '361380', '1'),
('525', '25', 'LA003', 'Alimosho', '6.61670000', '3.30000000', '903952', '1'),
('526', '25', 'LA004', 'Amuwo-Odofin', '6.45000000', '3.30000000', '287875', '1'),
('527', '25', 'LA005', 'Apapa', '6.45000000', '3.36670000', '143320', '1'),
('528', '25', 'LA006', 'Badagry', '6.41670000', '2.88330000', '132357', '1'),
('529', '25', 'LA007', 'Epe', '6.58330000', '3.98330000', '156036', '1'),
('530', '25', 'LA008', 'Eti-Osa', '6.45000000', '3.50000000', '239570', '1'),
('531', '25', 'LA009', 'Ibeju-Lekki', '6.46670000', '3.90000000', '78267', '1'),
('532', '25', 'LA010', 'Ifako-Ijaiye', '6.65000000', '3.31670000', '270244', '1'),
('533', '25', 'LA011', 'Ikeja', '6.60000000', '3.35000000', '362462', '1'),
('534', '25', 'LA012', 'Ikorodu', '6.60000000', '3.50000000', '310504', '1'),
('535', '25', 'LA013', 'Kosofe', '6.56670000', '3.40000000', '306806', '1'),
('536', '25', 'LA014', 'Lagos Island', '6.45000000', '3.40000000', '160797', '1'),
('537', '25', 'LA015', 'Lagos Mainland', '6.48330000', '3.36670000', '251317', '1'),
('538', '25', 'LA016', 'Mushin', '6.53330000', '3.35000000', '366791', '1'),
('539', '25', 'LA017', 'Ojo', '6.46670000', '3.18330000', '252553', '1'),
('540', '25', 'LA018', 'Oshodi-Isolo', '6.55000000', '3.35000000', '358643', '1'),
('541', '25', 'LA019', 'Shomolu', '6.55000000', '3.38330000', '285714', '1'),
('542', '25', 'LA020', 'Surulere', '6.50000000', '3.35000000', '434097', '1'),
('543', '26', 'NA001', 'Akwanga', '8.90000000', '8.40000000', '79359', '1'),
('544', '26', 'NA002', 'Awe', '8.06670000', '8.75000000', '54974', '1'),
('545', '26', 'NA003', 'Doma', '8.38330000', '8.35000000', '66006', '1'),
('546', '26', 'NA004', 'Karu', '9.10000000', '7.66670000', '148770', '1'),
('547', '26', 'NA005', 'Keana', '8.10000000', '8.80000000', '53748', '1'),
('548', '26', 'NA006', 'Keffi', '8.85000000', '7.86670000', '92557', '1'),
('549', '26', 'NA007', 'Kokona', '8.96670000', '8.00000000', '68062', '1'),
('550', '26', 'NA008', 'Lafia', '8.50000000', '8.51670000', '165394', '1'),
('551', '26', 'NA009', 'Nasarawa', '8.53330000', '7.70000000', '106149', '1'),
('552', '26', 'NA010', 'Nasarawa Egon', '8.63330000', '8.40000000', '55891', '1'),
('553', '26', 'NA011', 'Obi', '8.36670000', '8.76670000', '64105', '1'),
('554', '26', 'NA012', 'Toto', '8.38330000', '7.06670000', '76775', '1'),
('555', '26', 'NA013', 'Wamba', '8.93330000', '8.60000000', '55480', '1'),
('556', '27', 'NI001', 'Agaie', '9.01670000', '6.31670000', '69258', '1'),
('557', '27', 'NI002', 'Agwara', '10.70000000', '4.56670000', '44346', '1'),
('558', '27', 'NI003', 'Bida', '9.08330000', '6.01670000', '133720', '1'),
('559', '27', 'NI004', 'Borgu', '10.51670000', '4.51670000', '92724', '1'),
('560', '27', 'NI005', 'Bosso', '9.61670000', '6.48330000', '85954', '1'),
('561', '27', 'NI006', 'Chanchaga', '9.61670000', '6.53330000', '159647', '1'),
('562', '27', 'NI007', 'Edati', '9.03330000', '5.83330000', '48906', '1'),
('563', '27', 'NI008', 'Gbako', '9.10000000', '6.13330000', '58508', '1'),
('564', '27', 'NI009', 'Gurara', '9.36670000', '6.58330000', '59664', '1'),
('565', '27', 'NI010', 'Katcha', '9.15000000', '6.23330000', '61976', '1'),
('566', '27', 'NI011', 'Kontagora', '10.40000000', '5.46670000', '144753', '1'),
('567', '27', 'NI012', 'Lapai', '9.05000000', '6.56670000', '84293', '1'),
('568', '27', 'NI013', 'Lavun', '9.06670000', '5.76670000', '65630', '1'),
('569', '27', 'NI014', 'Magama', '10.60000000', '5.05000000', '81723', '1'),
('570', '27', 'NI015', 'Mariga', '10.31670000', '6.11670000', '73780', '1'),
('571', '27', 'NI016', 'Mashegu', '10.25000000', '5.05000000', '69080', '1'),
('572', '27', 'NI017', 'Mokwa', '9.30000000', '5.05000000', '108775', '1'),
('573', '27', 'NI018', 'Moya', '9.33330000', '6.18330000', '38145', '1'),
('574', '27', 'NI019', 'Paikoro', '9.43330000', '6.80000000', '72105', '1'),
('575', '27', 'NI020', 'Rafi', '10.18330000', '6.26670000', '83918', '1'),
('576', '27', 'NI021', 'Rijau', '11.10000000', '5.25000000', '85956', '1'),
('577', '27', 'NI022', 'Shiroro', '10.06670000', '6.66670000', '83282', '1'),
('578', '27', 'NI023', 'Suleja', '9.18330000', '7.18330000', '127536', '1'),
('579', '27', 'NI024', 'Tafa', '9.21670000', '7.28330000', '85858', '1'),
('580', '27', 'NI025', 'Wushishi', '9.73330000', '6.08330000', '46002', '1'),
('581', '28', 'OG001', 'Abeokuta North', '7.15000000', '3.35000000', '113102', '1'),
('582', '28', 'OG002', 'Abeokuta South', '7.13330000', '3.36670000', '155750', '1'),
('583', '28', 'OG003', 'Ado-Odo/Ota', '6.60000000', '2.93330000', '187845', '1'),
('584', '28', 'OG004', 'Egbado North', '7.00000000', '2.78330000', '63078', '1'),
('585', '28', 'OG005', 'Egbado South', '6.86670000', '2.73330000', '44397', '1'),
('586', '28', 'OG006', 'Ewekoro', '6.93330000', '3.21670000', '58041', '1'),
('587', '28', 'OG007', 'Ifo', '6.81670000', '3.20000000', '134080', '1'),
('588', '28', 'OG008', 'Ijebu East', '6.80000000', '4.00000000', '49404', '1'),
('589', '28', 'OG009', 'Ijebu North', '7.03330000', '3.91670000', '66177', '1'),
('590', '28', 'OG010', 'Ijebu North East', '6.91670000', '3.98330000', '39271', '1'),
('591', '28', 'OG011', 'Ijebu Ode', '6.81670000', '3.91670000', '83197', '1'),
('592', '28', 'OG012', 'Ikenne', '6.86670000', '3.71670000', '46977', '1'),
('593', '28', 'OG013', 'Imeko Afon', '7.48330000', '2.91670000', '50300', '1'),
('594', '28', 'OG014', 'Ipokia', '6.53330000', '2.83330000', '59345', '1'),
('595', '28', 'OG015', 'Obafemi Owode', '6.91670000', '3.50000000', '103066', '1'),
('596', '28', 'OG016', 'Odeda', '7.21670000', '3.50000000', '53535', '1'),
('597', '28', 'OG017', 'Odogbolu', '6.83330000', '3.76670000', '39799', '1'),
('598', '28', 'OG018', 'Ogun Waterside', '6.53330000', '4.30000000', '44872', '1'),
('599', '28', 'OG019', 'Remo North', '6.86670000', '3.63330000', '44882', '1'),
('600', '28', 'OG020', 'Shagamu', '6.85000000', '3.65000000', '111924', '1'),
('601', '29', 'ON001', 'Akoko North-East', '7.63330000', '5.86670000', '58152', '1'),
('602', '29', 'ON002', 'Akoko North-West', '7.76670000', '5.91670000', '69130', '1'),
('603', '29', 'ON003', 'Akoko South-East', '7.43330000', '5.83330000', '50926', '1'),
('604', '29', 'ON004', 'Akoko South-West', '7.43330000', '5.73330000', '53044', '1'),
('605', '29', 'ON005', 'Akure North', '7.35000000', '5.20000000', '83412', '1'),
('606', '29', 'ON006', 'Akure South', '7.25000000', '5.20000000', '176546', '1'),
('607', '29', 'ON007', 'Ese Odo', '6.25000000', '4.85000000', '58658', '1'),
('608', '29', 'ON008', 'Idanre', '7.10000000', '5.11670000', '65157', '1'),
('609', '29', 'ON009', 'Ifedore', '7.35000000', '5.11670000', '59289', '1'),
('610', '29', 'ON010', 'Ilaje', '6.35000000', '4.88330000', '137830', '1'),
('611', '29', 'ON011', 'Ile Oluji/Okeigbo', '7.06670000', '4.86670000', '43918', '1'),
('612', '29', 'ON012', 'Irele', '6.50000000', '4.86670000', '59988', '1'),
('613', '29', 'ON013', 'Odigbo', '6.86670000', '4.85000000', '158783', '1'),
('614', '29', 'ON014', 'Okitipupa', '6.50000000', '4.78330000', '103069', '1'),
('615', '29', 'ON015', 'Ondo East', '7.20000000', '4.93330000', '59417', '1'),
('616', '29', 'ON016', 'Ondo West', '7.10000000', '4.83330000', '123340', '1'),
('617', '29', 'ON017', 'Ose', '7.00000000', '5.60000000', '70234', '1'),
('618', '29', 'ON018', 'Owo', '7.20000000', '5.58330000', '145656', '1'),
('619', '30', 'OS001', 'Atakunmosa East', '7.45000000', '4.81670000', '42511', '1'),
('620', '30', 'OS002', 'Atakunmosa West', '7.50000000', '4.73330000', '41033', '1'),
('621', '30', 'OS003', 'Aiyedaade', '7.33330000', '4.33330000', '71431', '1'),
('622', '30', 'OS004', 'Aiyedire', '7.60000000', '4.21670000', '47798', '1'),
('623', '30', 'OS005', 'Boluwaduro', '7.90000000', '4.83330000', '31201', '1'),
('624', '30', 'OS006', 'Boripe', '7.78330000', '4.66670000', '61719', '1'),
('625', '30', 'OS007', 'Ede North', '7.73330000', '4.43330000', '92505', '1'),
('626', '30', 'OS008', 'Ede South', '7.71670000', '4.43330000', '77638', '1'),
('627', '30', 'OS009', 'Egbedore', '7.78330000', '4.31670000', '46334', '1'),
('628', '30', 'OS010', 'Ejigbo', '7.90000000', '4.31670000', '95882', '1'),
('629', '30', 'OS011', 'Ife Central', '7.46670000', '4.56670000', '106817', '1'),
('630', '30', 'OS012', 'Ife East', '7.50000000', '4.55000000', '125727', '1'),
('631', '30', 'OS013', 'Ife North', '7.56670000', '4.56670000', '37257', '1'),
('632', '30', 'OS014', 'Ife South', '7.40000000', '4.43330000', '44825', '1'),
('633', '30', 'OS015', 'Ifedayo', '7.88330000', '4.75000000', '23696', '1'),
('634', '30', 'OS016', 'Ifelodun', '7.91670000', '4.41670000', '58949', '1'),
('635', '30', 'OS017', 'Ila', '8.01670000', '4.90000000', '73194', '1'),
('636', '30', 'OS018', 'Ilesa East', '7.61670000', '4.73330000', '71211', '1'),
('637', '30', 'OS019', 'Ilesa West', '7.63330000', '4.73330000', '65477', '1'),
('638', '30', 'OS020', 'Irepodun', '7.95000000', '4.55000000', '43646', '1'),
('639', '30', 'OS021', 'Irewole', '7.21670000', '4.40000000', '71874', '1'),
('640', '30', 'OS022', 'Isokan', '7.40000000', '4.26670000', '57238', '1'),
('641', '30', 'OS023', 'Iwo', '7.63330000', '4.18330000', '108888', '1'),
('642', '30', 'OS024', 'Obokun', '7.58330000', '4.80000000', '55035', '1'),
('643', '30', 'OS025', 'Odo Otin', '7.91670000', '4.58330000', '77696', '1'),
('644', '30', 'OS026', 'Ola Oluwa', '7.66670000', '4.33330000', '42438', '1'),
('645', '30', 'OS027', 'Olorunda', '7.80000000', '4.56670000', '90498', '1'),
('646', '30', 'OS028', 'Oriade', '7.70000000', '4.83330000', '69688', '1'),
('647', '30', 'OS029', 'Orolu', '7.81670000', '4.48330000', '38933', '1'),
('648', '30', 'OS030', 'Osogbo', '7.76670000', '4.56670000', '120912', '1'),
('649', '31', 'OY001', 'Afijio', '7.58330000', '3.93330000', '59386', '1'),
('650', '31', 'OY002', 'Akinyele', '7.46670000', '3.88330000', '102077', '1'),
('651', '31', 'OY003', 'Atiba', '8.23330000', '4.16670000', '49767', '1'),
('652', '31', 'OY004', 'Atisbo', '8.21670000', '3.38330000', '65587', '1'),
('653', '31', 'OY005', 'Egbeda', '7.36670000', '3.91670000', '72226', '1'),
('654', '31', 'OY006', 'Ibadan North', '7.38330000', '3.90000000', '168160', '1'),
('655', '31', 'OY007', 'Ibadan North-East', '7.43330000', '3.91670000', '155656', '1'),
('656', '31', 'OY008', 'Ibadan North-West', '7.41670000', '3.88330000', '114846', '1'),
('657', '31', 'OY009', 'Ibadan South-East', '7.35000000', '3.88330000', '141809', '1'),
('658', '31', 'OY010', 'Ibadan South-West', '7.35000000', '3.85000000', '130790', '1'),
('659', '31', 'OY011', 'Ibarapa Central', '7.63330000', '3.21670000', '70270', '1'),
('660', '31', 'OY012', 'Ibarapa East', '7.41670000', '3.40000000', '78723', '1'),
('661', '31', 'OY013', 'Ibarapa North', '7.71670000', '3.11670000', '51340', '1'),
('662', '31', 'OY014', 'Ido', '7.46670000', '3.75000000', '72722', '1'),
('663', '31', 'OY015', 'Irepo', '8.03330000', '4.36670000', '37473', '1'),
('664', '31', 'OY016', 'Iseyin', '7.96670000', '3.60000000', '124952', '1'),
('665', '31', 'OY017', 'Itesiwaju', '7.96670000', '3.18330000', '58382', '1'),
('666', '31', 'OY018', 'Iwajowa', '7.81670000', '2.96670000', '56312', '1'),
('667', '31', 'OY019', 'Kajola', '8.01670000', '3.26670000', '72955', '1'),
('668', '31', 'OY020', 'Lagelu', '7.38330000', '3.86670000', '77921', '1'),
('669', '31', 'OY021', 'Ogbomosho North', '8.13330000', '4.25000000', '110149', '1'),
('670', '31', 'OY022', 'Ogbomosho South', '8.10000000', '4.25000000', '107468', '1'),
('671', '31', 'OY023', 'Ogo Oluwa', '8.21670000', '4.31670000', '41861', '1'),
('672', '31', 'OY024', 'Olorunsogo', '8.26670000', '4.26670000', '28774', '1'),
('673', '31', 'OY025', 'Oluyole', '7.26670000', '3.76670000', '97135', '1'),
('674', '31', 'OY026', 'Ona Ara', '7.36670000', '3.96670000', '72493', '1'),
('675', '31', 'OY027', 'Orelope', '8.21670000', '2.96670000', '48663', '1'),
('676', '31', 'OY028', 'Ori Ire', '8.46670000', '4.01670000', '39992', '1'),
('677', '31', 'OY029', 'Oyo', '7.85000000', '3.93330000', '176608', '1'),
('678', '31', 'OY030', 'Oyo East', '7.85000000', '3.91670000', '53166', '1'),
('679', '31', 'OY031', 'Saki East', '8.61670000', '3.35000000', '52815', '1'),
('680', '31', 'OY032', 'Saki West', '8.66670000', '3.40000000', '101988', '1'),
('681', '31', 'OY033', 'Surulere', '8.30000000', '4.26670000', '34294', '1'),
('682', '32', 'PL001', 'Barkin Ladi', '9.53330000', '8.90000000', '130933', '1'),
('683', '32', 'PL002', 'Bassa', '9.93330000', '8.70000000', '145142', '1'),
('684', '32', 'PL003', 'Bokkos', '9.30000000', '8.98330000', '93064', '1'),
('685', '32', 'PL004', 'Jos East', '9.83330000', '9.03330000', '61045', '1'),
('686', '32', 'PL005', 'Jos North', '9.88330000', '8.88330000', '324168', '1'),
('687', '32', 'PL006', 'Jos South', '9.76670000', '8.86670000', '234179', '1'),
('688', '32', 'PL007', 'Kanam', '9.58330000', '9.60000000', '100953', '1'),
('689', '32', 'PL008', 'Kanke', '9.46670000', '9.31670000', '50306', '1'),
('690', '32', 'PL009', 'Langtang North', '9.13330000', '9.50000000', '96788', '1'),
('691', '32', 'PL010', 'Langtang South', '9.05000000', '9.40000000', '61806', '1'),
('692', '32', 'PL011', 'Mangu', '9.50000000', '9.10000000', '180325', '1'),
('693', '32', 'PL012', 'Mikang', '9.08330000', '9.31670000', '48359', '1'),
('694', '32', 'PL013', 'Pankshin', '9.33330000', '9.43330000', '118894', '1'),
('695', '32', 'PL014', 'Qua\'an Pan', '9.11670000', '9.18330000', '81930', '1'),
('696', '32', 'PL015', 'Riyom', '9.63330000', '8.75000000', '69921', '1'),
('697', '32', 'PL016', 'Shendam', '8.86670000', '9.53330000', '100532', '1'),
('698', '32', 'PL017', 'Wase', '9.10000000', '9.96670000', '71612', '1'),
('699', '33', 'RI001', 'Abua/Odual', '5.00000000', '6.60000000', '71291', '1'),
('700', '33', 'RI002', 'Ahoada East', '5.08330000', '6.65000000', '94856', '1'),
('701', '33', 'RI003', 'Ahoada West', '5.23330000', '6.40000000', '75705', '1'),
('702', '33', 'RI004', 'Akuku-Toru', '4.70000000', '6.40000000', '109518', '1'),
('703', '33', 'RI005', 'Andoni', '4.58330000', '7.41670000', '101871', '1'),
('704', '33', 'RI006', 'Asari-Toru', '4.70000000', '6.50000000', '162130', '1'),
('705', '33', 'RI007', 'Bonny', '4.43330000', '7.16670000', '80622', '1'),
('706', '33', 'RI008', 'Degema', '4.75000000', '6.76670000', '101337', '1'),
('707', '33', 'RI009', 'Eleme', '4.83330000', '7.06670000', '139608', '1'),
('708', '33', 'RI010', 'Emuoha', '5.00000000', '6.80000000', '59472', '1'),
('709', '33', 'RI011', 'Etche', '5.11670000', '6.98330000', '149461', '1'),
('710', '33', 'RI012', 'Gokana', '4.71670000', '7.31670000', '90338', '1'),
('711', '33', 'RI013', 'Ikwerre', '5.11670000', '6.91670000', '124378', '1'),
('712', '33', 'RI014', 'Khana', '4.66670000', '7.36670000', '181353', '1'),
('713', '33', 'RI015', 'Obio/Akpor', '4.81670000', '7.05000000', '354570', '1'),
('714', '33', 'RI016', 'Ogba/Egbema/Ndoni', '5.36670000', '6.63330000', '125622', '1'),
('715', '33', 'RI017', 'Ogu/Bolo', '4.73330000', '7.08330000', '58388', '1'),
('716', '33', 'RI018', 'Okrika', '4.73330000', '7.08330000', '97251', '1'),
('717', '33', 'RI019', 'Omuma', '5.18330000', '6.96670000', '42571', '1'),
('718', '33', 'RI020', 'Opobo/Nkoro', '4.53330000', '7.53330000', '58376', '1'),
('719', '33', 'RI021', 'Oyigbo', '4.83330000', '7.15000000', '93792', '1'),
('720', '33', 'RI022', 'Port Harcourt', '4.81670000', '7.00000000', '363915', '1'),
('721', '33', 'RI023', 'Tai', '4.68330000', '7.25000000', '43237', '1'),
('722', '34', 'SO001', 'Binji', '13.21670000', '4.90000000', '56775', '1'),
('723', '34', 'SO002', 'Bodinga', '12.85000000', '5.16670000', '73767', '1'),
('724', '34', 'SO003', 'Dange Shuni', '12.85000000', '5.33330000', '106630', '1'),
('725', '34', 'SO004', 'Gada', '13.73330000', '5.66670000', '79988', '1'),
('726', '34', 'SO005', 'Goronyo', '13.43330000', '5.66670000', '96721', '1'),
('727', '34', 'SO006', 'Gudu', '13.51670000', '4.81670000', '45899', '1'),
('728', '34', 'SO007', 'Gwadabawa', '13.35000000', '5.23330000', '119336', '1'),
('729', '34', 'SO008', 'Illela', '13.73330000', '5.30000000', '58952', '1'),
('730', '34', 'SO009', 'Isa', '13.20000000', '6.40000000', '85749', '1'),
('731', '34', 'SO010', 'Kebbe', '12.13330000', '4.73330000', '72451', '1'),
('732', '34', 'SO011', 'Kware', '13.21670000', '5.26670000', '67711', '1'),
('733', '34', 'SO012', 'Rabah', '13.11670000', '5.50000000', '61122', '1'),
('734', '34', 'SO013', 'Sabon Birni', '13.56670000', '6.31670000', '95190', '1'),
('735', '34', 'SO014', 'Shagari', '12.63330000', '4.90000000', '50884', '1'),
('736', '34', 'SO015', 'Silame', '13.03330000', '4.85000000', '54573', '1'),
('737', '34', 'SO016', 'Sokoto North', '13.06670000', '5.23330000', '236677', '1'),
('738', '34', 'SO017', 'Sokoto South', '13.05000000', '5.21670000', '135358', '1'),
('739', '34', 'SO018', 'Tambuwal', '12.40000000', '4.65000000', '83008', '1'),
('740', '34', 'SO019', 'Tangaza', '13.41670000', '5.45000000', '59478', '1'),
('741', '34', 'SO020', 'Tureta', '12.60000000', '5.53330000', '52556', '1'),
('742', '34', 'SO021', 'Wamako', '13.08330000', '5.13330000', '104205', '1'),
('743', '34', 'SO022', 'Wurno', '13.26670000', '5.41670000', '74262', '1'),
('744', '34', 'SO023', 'Yabo', '12.73330000', '4.78330000', '56840', '1'),
('745', '35', 'TA001', 'Ardo Kola', '9.05000000', '11.38330000', '62282', '1'),
('746', '35', 'TA002', 'Bali', '7.85000000', '10.96670000', '104005', '1'),
('747', '35', 'TA003', 'Donga', '7.71670000', '10.05000000', '54519', '1'),
('748', '35', 'TA004', 'Gashaka', '7.48330000', '11.36670000', '52820', '1'),
('749', '35', 'TA005', 'Gassol', '9.00000000', '10.48330000', '129348', '1'),
('750', '35', 'TA006', 'Ibi', '8.18330000', '9.75000000', '65187', '1'),
('751', '35', 'TA007', 'Jalingo', '8.90000000', '11.36670000', '193909', '1'),
('752', '35', 'TA008', 'Karim Lamido', '9.31670000', '11.18330000', '98688', '1'),
('753', '35', 'TA009', 'Kumi', '8.61670000', '10.85000000', '44092', '1'),
('754', '35', 'TA010', 'Lau', '9.58330000', '11.26670000', '55203', '1'),
('755', '35', 'TA011', 'Sardauna', '6.78330000', '11.33330000', '72595', '1'),
('756', '35', 'TA012', 'Takum', '7.25000000', '9.98330000', '113742', '1'),
('757', '35', 'TA013', 'Ussa', '7.06670000', '10.25000000', '33057', '1'),
('758', '35', 'TA014', 'Wukari', '7.86670000', '9.78330000', '118760', '1'),
('759', '35', 'TA015', 'Yorro', '8.76670000', '11.06670000', '53055', '1'),
('760', '35', 'TA016', 'Zing', '8.98330000', '11.65000000', '51188', '1'),
('761', '36', 'YO001', 'Bade', '12.96670000', '11.21670000', '90961', '1'),
('762', '36', 'YO002', 'Bursari', '12.58330000', '10.56670000', '30556', '1'),
('763', '36', 'YO003', 'Damaturu', '11.73330000', '11.96670000', '120716', '1'),
('764', '36', 'YO004', 'Fika', '11.28330000', '11.30000000', '71071', '1'),
('765', '36', 'YO005', 'Fune', '11.86670000', '11.90000000', '95553', '1'),
('766', '36', 'YO006', 'Geidam', '12.90000000', '11.93330000', '90194', '1'),
('767', '36', 'YO007', 'Gujba', '11.50000000', '11.93330000', '52063', '1'),
('768', '36', 'YO008', 'Gulani', '10.56670000', '11.78330000', '38343', '1'),
('769', '36', 'YO009', 'Jakusko', '12.36670000', '10.75000000', '70668', '1'),
('770', '36', 'YO010', 'Karasuwa', '12.61670000', '10.35000000', '73011', '1'),
('771', '36', 'YO011', 'Machina', '13.13330000', '10.06670000', '60982', '1'),
('772', '36', 'YO012', 'Nangere', '11.85000000', '11.20000000', '59685', '1'),
('773', '36', 'YO013', 'Nguru', '12.88330000', '10.45000000', '92934', '1'),
('774', '36', 'YO014', 'Potiskum', '11.71670000', '11.08330000', '139886', '1'),
('775', '36', 'YO015', 'Tarmuwa', '11.46670000', '10.73330000', '45942', '1'),
('776', '36', 'YO016', 'Yunusari', '13.05000000', '11.06670000', '43343', '1'),
('777', '36', 'YO017', 'Yusufari', '13.06670000', '11.16670000', '42774', '1'),
('778', '37', 'ZA001', 'Anka', '12.10000000', '6.03330000', '66783', '1'),
('779', '37', 'ZA002', 'Bakura', '12.70000000', '5.86670000', '78697', '1'),
('780', '37', 'ZA003', 'Birnin Magaji/Kiyaw', '12.96670000', '6.90000000', '84505', '1'),
('781', '37', 'ZA004', 'Bukkuyum', '12.13330000', '5.46670000', '78306', '1'),
('782', '37', 'ZA005', 'Bungudu', '12.26670000', '6.55000000', '126363', '1'),
('783', '37', 'ZA006', 'Gummi', '12.13330000', '5.11670000', '77938', '1'),
('784', '37', 'ZA007', 'Gusau', '12.16670000', '6.66670000', '138442', '1'),
('785', '37', 'ZA008', 'Kaura Namoda', '12.58330000', '6.60000000', '106278', '1'),
('786', '37', 'ZA009', 'Maradun', '12.56670000', '6.23330000', '82679', '1'),
('787', '37', 'ZA010', 'Maru', '12.33330000', '6.40000000', '93854', '1'),
('788', '37', 'ZA011', 'Shinkafi', '13.06670000', '6.50000000', '78079', '1'),
('789', '37', 'ZA012', 'Talata Mafara', '12.56670000', '6.06670000', '107367', '1'),
('790', '37', 'ZA013', 'Tsafe', '11.95000000', '6.91670000', '110673', '1'),
('791', '37', 'ZA014', 'Zurmi', '12.76670000', '6.78330000', '95870', '1');

-- ------------------------------------------------------------
-- Table: `login_attempts`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `attempt_type` varchar(50) NOT NULL DEFAULT 'login',
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_login_attempts_ip` (`ip_address`),
  KEY `idx_login_attempts_email` (`email`),
  KEY `idx_login_attempts_created` (`created_at`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `login_attempts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `login_attempts` (`id`, `user_id`, `email`, `ip_address`, `user_agent`, `attempt_type`, `success`, `created_at`) VALUES 
('1', NULL, 'aliyuabubakar11117@gmail', '::1', NULL, 'login', '0', '2026-07-01 23:27:24'),
('2', NULL, 'admin@5gguru.ng', '::1', NULL, 'login', '0', '2026-07-01 23:27:50'),
('5', NULL, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', '0', '2026-07-01 23:58:27'),
('6', '2', 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', '1', '2026-07-02 00:01:44'),
('7', '2', 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', '1', '2026-07-02 00:24:33'),
('8', NULL, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', '0', '2026-07-02 00:29:11'),
('9', NULL, 'aliyu@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', '0', '2026-07-02 00:29:18'),
('10', NULL, 'aliyu@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', '0', '2026-07-02 00:29:22'),
('11', '2', 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', '1', '2026-07-02 00:36:06'),
('12', '2', 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', '1', '2026-07-02 00:41:13'),
('13', NULL, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', '0', '2026-07-02 00:48:39'),
('14', '2', 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', '1', '2026-07-02 00:49:09'),
('15', '2', 'aliyuabubakar11117@gmail.com', '10.180.98.13', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', 'login', '1', '2026-07-02 01:30:15'),
('16', '2', 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', '1', '2026-07-02 02:24:48'),
('17', '2', 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', '1', '2026-07-02 05:52:20'),
('18', NULL, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', '0', '2026-07-02 06:52:32'),
('19', '2', 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', '1', '2026-07-02 06:53:53');

-- ------------------------------------------------------------
-- Table: `notifications`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `type` enum('system','election','result','incident','chat','broadcast','payment','security') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `data_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data_json`)),
  `action_url` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user` (`user_id`),
  KEY `idx_notifications_type` (`type`),
  KEY `idx_notifications_read` (`is_read`),
  KEY `idx_notifications_created` (`created_at`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `notifications` is empty

-- ------------------------------------------------------------
-- Table: `offline_sync_queue`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `offline_sync_queue`;
CREATE TABLE `offline_sync_queue` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `device_id` varchar(255) NOT NULL,
  `data_type` enum('ec8a','incident','checkin','media','chat','profile_update') NOT NULL,
  `priority` tinyint(3) unsigned NOT NULL DEFAULT 5,
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload_json`)),
  `file_path` varchar(500) DEFAULT NULL,
  `file_size` bigint(20) unsigned DEFAULT NULL,
  `file_sha256` varchar(64) DEFAULT NULL,
  `status` enum('queued','syncing','completed','failed','retrying') NOT NULL DEFAULT 'queued',
  `retry_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `max_retries` tinyint(3) unsigned NOT NULL DEFAULT 5,
  `last_error` text DEFAULT NULL,
  `synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sync_user` (`user_id`),
  KEY `idx_sync_device` (`device_id`),
  KEY `idx_sync_status` (`status`),
  KEY `idx_sync_priority` (`priority`),
  CONSTRAINT `offline_sync_queue_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `offline_sync_queue` is empty

-- ------------------------------------------------------------
-- Table: `otp_verifications`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `otp_verifications`;
CREATE TABLE `otp_verifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `otp_code` varchar(10) NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'login',
  `channel` varchar(20) NOT NULL DEFAULT 'email',
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `used_at` timestamp NULL DEFAULT NULL,
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_otp_user` (`user_id`),
  KEY `idx_otp_code` (`otp_code`),
  KEY `idx_otp_expires` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `otp_verifications` (`id`, `user_id`, `otp_code`, `type`, `channel`, `expires_at`, `used`, `used_at`, `attempts`, `created_at`) VALUES 
('1', '2', '746440', '2fa_enable', 'email', '2026-07-02 00:37:05', '1', '2026-07-02 00:37:05', '0', '2026-07-02 00:36:30'),
('2', '2', '044659', 'login', 'email', '2026-07-02 00:41:12', '1', '2026-07-02 00:41:12', '0', '2026-07-02 00:40:44'),
('3', '2', '682700', 'login', 'email', '2026-07-02 00:49:09', '1', '2026-07-02 00:49:09', '0', '2026-07-02 00:48:51'),
('4', '2', '227687', 'login', 'email', '2026-07-02 01:30:15', '1', '2026-07-02 01:30:15', '0', '2026-07-02 01:29:36'),
('5', '2', '738881', 'login', 'email', '2026-07-02 02:24:48', '1', '2026-07-02 02:24:48', '0', '2026-07-02 02:24:24'),
('6', '2', '680586', 'login', 'email', '2026-07-02 05:52:20', '1', '2026-07-02 05:52:20', '0', '2026-07-02 05:51:58'),
('7', '2', '998814', 'login', 'email', '2026-07-02 06:53:53', '1', '2026-07-02 06:53:53', '0', '2026-07-02 06:53:35');

-- ------------------------------------------------------------
-- Table: `password_resets`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pw_resets_user` (`user_id`),
  KEY `idx_pw_resets_token` (`token`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `password_resets` (`id`, `user_id`, `token`, `expires_at`, `used`, `used_at`, `created_at`) VALUES 
('3', '2', 'afde518473b108c9c1cbe54043fb05c0778a573d5079b2702469f4b4dbcd868a', '2026-07-02 06:53:15', '1', '2026-07-02 06:53:15', '2026-07-02 06:52:47');

-- ------------------------------------------------------------
-- Table: `people`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `people`;
CREATE TABLE `people` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `full_name` varchar(200) GENERATED ALWAYS AS (concat(`first_name`,' ',`last_name`)) STORED,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `gender` enum('male','female','other','prefer_not_say') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `category` enum('party_member','volunteer','voter','stakeholder','community_leader','traditional_leader','religious_leader','influencer','other') NOT NULL,
  `state_id` int(10) unsigned DEFAULT NULL,
  `lga_id` int(10) unsigned DEFAULT NULL,
  `ward_id` int(10) unsigned DEFAULT NULL,
  `pu_id` int(10) unsigned DEFAULT NULL,
  `address` text DEFAULT NULL,
  `nin` varchar(20) DEFAULT NULL,
  `voter_id` varchar(50) DEFAULT NULL,
  `social_media_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`social_media_json`)),
  `notes` text DEFAULT NULL,
  `tags_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags_json`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_people_tenant` (`tenant_id`),
  KEY `idx_people_category` (`category`),
  KEY `idx_people_phone` (`phone`),
  KEY `idx_people_pu` (`pu_id`),
  KEY `state_id` (`state_id`),
  KEY `lga_id` (`lga_id`),
  KEY `ward_id` (`ward_id`),
  CONSTRAINT `people_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `people_ibfk_2` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`) ON DELETE SET NULL,
  CONSTRAINT `people_ibfk_3` FOREIGN KEY (`lga_id`) REFERENCES `lgas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `people_ibfk_4` FOREIGN KEY (`ward_id`) REFERENCES `wards` (`id`) ON DELETE SET NULL,
  CONSTRAINT `people_ibfk_5` FOREIGN KEY (`pu_id`) REFERENCES `polling_units` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `people` is empty

-- ------------------------------------------------------------
-- Table: `permissions`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `module` varchar(50) NOT NULL,
  `action` varchar(50) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  UNIQUE KEY `uk_permissions_module_action` (`module`,`action`),
  KEY `idx_permissions_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `permissions` is empty

-- ------------------------------------------------------------
-- Table: `political_parties`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `political_parties`;
CREATE TABLE `political_parties` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `acronym` varchar(20) NOT NULL,
  `logo_url` varchar(500) DEFAULT NULL,
  `chairman_name` varchar(200) DEFAULT NULL,
  `secretary_name` varchar(200) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `social_media_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`social_media_json`)),
  `state_offices_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`state_offices_json`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_parties_tenant` (`tenant_id`),
  CONSTRAINT `political_parties_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `political_parties` is empty

-- ------------------------------------------------------------
-- Table: `polling_units`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `polling_units`;
CREATE TABLE `polling_units` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ward_id` int(10) unsigned NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `gps_lat` decimal(10,8) DEFAULT NULL,
  `gps_lng` decimal(11,8) DEFAULT NULL,
  `gps_accuracy` decimal(6,2) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `registered_voters` int(10) unsigned NOT NULL DEFAULT 0,
  `accredited_voters` int(10) unsigned NOT NULL DEFAULT 0,
  `is_rural` tinyint(1) NOT NULL DEFAULT 0,
  `network_quality` enum('2g','3g','4g','5g','none') DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pu_ward_code` (`ward_id`,`code`),
  KEY `idx_pu_ward` (`ward_id`),
  KEY `idx_pu_gps` (`gps_lat`,`gps_lng`),
  CONSTRAINT `polling_units_ibfk_1` FOREIGN KEY (`ward_id`) REFERENCES `wards` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `polling_units` is empty

-- ------------------------------------------------------------
-- Table: `public_results`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `public_results`;
CREATE TABLE `public_results` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `election_id` bigint(20) unsigned NOT NULL,
  `pu_id` int(10) unsigned DEFAULT NULL,
  `ward_id` int(10) unsigned DEFAULT NULL,
  `lga_id` int(10) unsigned DEFAULT NULL,
  `state_id` int(10) unsigned DEFAULT NULL,
  `level` enum('pu','ward','lga','state','national') NOT NULL,
  `party_votes_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`party_votes_json`)),
  `valid_votes` int(10) unsigned NOT NULL DEFAULT 0,
  `rejected_votes` int(10) unsigned NOT NULL DEFAULT 0,
  `total_votes` int(10) unsigned NOT NULL DEFAULT 0,
  `turnout_percentage` decimal(5,2) DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT 0,
  `published_at` timestamp NULL DEFAULT NULL,
  `published_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_public_results_tenant` (`tenant_id`),
  KEY `idx_public_results_election` (`election_id`),
  KEY `idx_public_results_level` (`level`),
  KEY `idx_public_results_published` (`is_published`),
  CONSTRAINT `public_results_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `public_results_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `public_results` is empty

-- ------------------------------------------------------------
-- Table: `reports`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `reports`;
CREATE TABLE `reports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `election_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('turnout','results','incidents','agents','financial','performance','custom') NOT NULL,
  `format` enum('pdf','excel','csv','json','html') NOT NULL DEFAULT 'pdf',
  `filters_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`filters_json`)),
  `file_url` varchar(500) DEFAULT NULL,
  `file_size` bigint(20) unsigned DEFAULT NULL,
  `generated_by` bigint(20) unsigned NOT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_scheduled` tinyint(1) NOT NULL DEFAULT 0,
  `schedule_cron` varchar(50) DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `public_slug` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `public_slug` (`public_slug`),
  KEY `idx_reports_tenant` (`tenant_id`),
  KEY `idx_reports_election` (`election_id`),
  KEY `idx_reports_type` (`type`),
  KEY `idx_reports_public` (`is_public`),
  KEY `generated_by` (`generated_by`),
  CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reports_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reports_ibfk_3` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `reports` is empty

-- ------------------------------------------------------------
-- Table: `results_ec8a`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `results_ec8a`;
CREATE TABLE `results_ec8a` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `election_id` bigint(20) unsigned NOT NULL,
  `pu_id` int(10) unsigned NOT NULL,
  `ward_id` int(10) unsigned NOT NULL,
  `lga_id` int(10) unsigned NOT NULL,
  `state_id` int(10) unsigned NOT NULL,
  `agent_id` bigint(20) unsigned NOT NULL,
  `assignment_id` bigint(20) unsigned NOT NULL,
  `pu_code` varchar(50) NOT NULL,
  `pu_name` varchar(255) NOT NULL,
  `registered_voters` int(10) unsigned NOT NULL DEFAULT 0,
  `accredited_voters` int(10) unsigned NOT NULL DEFAULT 0,
  `ballot_papers_issued` int(10) unsigned NOT NULL DEFAULT 0,
  `unused_ballots` int(10) unsigned NOT NULL DEFAULT 0,
  `spoiled_ballots` int(10) unsigned NOT NULL DEFAULT 0,
  `rejected_votes` int(10) unsigned NOT NULL DEFAULT 0,
  `valid_votes` int(10) unsigned NOT NULL DEFAULT 0,
  `total_votes_cast` int(10) unsigned NOT NULL DEFAULT 0,
  `party_votes_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`party_votes_json`)),
  `photo_url` varchar(500) DEFAULT NULL,
  `video_url` varchar(500) DEFAULT NULL,
  `audio_url` varchar(500) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `gps_lat` decimal(10,8) DEFAULT NULL,
  `gps_lng` decimal(11,8) DEFAULT NULL,
  `gps_accuracy` decimal(6,2) DEFAULT NULL,
  `device_id` varchar(255) DEFAULT NULL,
  `device_model` varchar(100) DEFAULT NULL,
  `photo_sha256` varchar(64) DEFAULT NULL,
  `video_sha256` varchar(64) DEFAULT NULL,
  `audio_sha256` varchar(64) DEFAULT NULL,
  `ocr_extracted_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`ocr_extracted_json`)),
  `ocr_confidence` decimal(5,2) DEFAULT NULL,
  `ocr_manual_mismatch` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('pending','verified','rejected','flagged','approved') NOT NULL DEFAULT 'pending',
  `verified_by` bigint(20) unsigned DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `is_offline_sync` tinyint(1) NOT NULL DEFAULT 0,
  `offline_created_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ec8a_tenant` (`tenant_id`),
  KEY `idx_ec8a_election` (`election_id`),
  KEY `idx_ec8a_pu` (`pu_id`),
  KEY `idx_ec8a_agent` (`agent_id`),
  KEY `idx_ec8a_status` (`status`),
  KEY `idx_ec8a_created` (`created_at`),
  KEY `verified_by` (`verified_by`),
  CONSTRAINT `results_ec8a_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `results_ec8a_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `results_ec8a_ibfk_3` FOREIGN KEY (`pu_id`) REFERENCES `polling_units` (`id`),
  CONSTRAINT `results_ec8a_ibfk_4` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`),
  CONSTRAINT `results_ec8a_ibfk_5` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `results_ec8a` is empty

-- ------------------------------------------------------------
-- Table: `results_ec8b`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `results_ec8b`;
CREATE TABLE `results_ec8b` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `election_id` bigint(20) unsigned NOT NULL,
  `ward_id` int(10) unsigned NOT NULL,
  `lga_id` int(10) unsigned NOT NULL,
  `state_id` int(10) unsigned NOT NULL,
  `coordinator_id` bigint(20) unsigned NOT NULL,
  `party_votes_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`party_votes_json`)),
  `valid_votes` int(10) unsigned NOT NULL DEFAULT 0,
  `rejected_votes` int(10) unsigned NOT NULL DEFAULT 0,
  `total_votes` int(10) unsigned NOT NULL DEFAULT 0,
  `calculated_total_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`calculated_total_json`)),
  `mismatch_alert` tinyint(1) NOT NULL DEFAULT 0,
  `mismatch_details_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`mismatch_details_json`)),
  `form_photo_url` varchar(500) DEFAULT NULL,
  `form_sha256` varchar(64) DEFAULT NULL,
  `status` enum('pending','verified','rejected','flagged') NOT NULL DEFAULT 'pending',
  `verified_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ec8b_tenant` (`tenant_id`),
  KEY `idx_ec8b_election` (`election_id`),
  KEY `idx_ec8b_ward` (`ward_id`),
  KEY `idx_ec8b_mismatch` (`mismatch_alert`),
  KEY `coordinator_id` (`coordinator_id`),
  CONSTRAINT `results_ec8b_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `results_ec8b_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `results_ec8b_ibfk_3` FOREIGN KEY (`ward_id`) REFERENCES `wards` (`id`),
  CONSTRAINT `results_ec8b_ibfk_4` FOREIGN KEY (`coordinator_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `results_ec8b` is empty

-- ------------------------------------------------------------
-- Table: `results_ec8c`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `results_ec8c`;
CREATE TABLE `results_ec8c` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `election_id` bigint(20) unsigned NOT NULL,
  `lga_id` int(10) unsigned NOT NULL,
  `state_id` int(10) unsigned NOT NULL,
  `coordinator_id` bigint(20) unsigned NOT NULL,
  `party_votes_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`party_votes_json`)),
  `valid_votes` int(10) unsigned NOT NULL DEFAULT 0,
  `rejected_votes` int(10) unsigned NOT NULL DEFAULT 0,
  `total_votes` int(10) unsigned NOT NULL DEFAULT 0,
  `calculated_total_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`calculated_total_json`)),
  `mismatch_alert` tinyint(1) NOT NULL DEFAULT 0,
  `mismatch_details_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`mismatch_details_json`)),
  `form_photo_url` varchar(500) DEFAULT NULL,
  `form_sha256` varchar(64) DEFAULT NULL,
  `status` enum('pending','verified','rejected','flagged') NOT NULL DEFAULT 'pending',
  `verified_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ec8c_tenant` (`tenant_id`),
  KEY `idx_ec8c_election` (`election_id`),
  KEY `idx_ec8c_lga` (`lga_id`),
  KEY `idx_ec8c_mismatch` (`mismatch_alert`),
  KEY `coordinator_id` (`coordinator_id`),
  CONSTRAINT `results_ec8c_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `results_ec8c_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `results_ec8c_ibfk_3` FOREIGN KEY (`lga_id`) REFERENCES `lgas` (`id`),
  CONSTRAINT `results_ec8c_ibfk_4` FOREIGN KEY (`coordinator_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `results_ec8c` is empty

-- ------------------------------------------------------------
-- Table: `results_ec8d`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `results_ec8d`;
CREATE TABLE `results_ec8d` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `election_id` bigint(20) unsigned NOT NULL,
  `state_id` int(10) unsigned NOT NULL,
  `coordinator_id` bigint(20) unsigned NOT NULL,
  `party_votes_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`party_votes_json`)),
  `valid_votes` int(10) unsigned NOT NULL DEFAULT 0,
  `rejected_votes` int(10) unsigned NOT NULL DEFAULT 0,
  `total_votes` int(10) unsigned NOT NULL DEFAULT 0,
  `calculated_total_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`calculated_total_json`)),
  `mismatch_alert` tinyint(1) NOT NULL DEFAULT 0,
  `mismatch_details_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`mismatch_details_json`)),
  `form_photo_url` varchar(500) DEFAULT NULL,
  `form_sha256` varchar(64) DEFAULT NULL,
  `status` enum('pending','verified','rejected','flagged') NOT NULL DEFAULT 'pending',
  `verified_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ec8d_tenant` (`tenant_id`),
  KEY `idx_ec8d_election` (`election_id`),
  KEY `idx_ec8d_state` (`state_id`),
  KEY `idx_ec8d_mismatch` (`mismatch_alert`),
  KEY `coordinator_id` (`coordinator_id`),
  CONSTRAINT `results_ec8d_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `results_ec8d_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `results_ec8d_ibfk_3` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`),
  CONSTRAINT `results_ec8d_ibfk_4` FOREIGN KEY (`coordinator_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `results_ec8d` is empty

-- ------------------------------------------------------------
-- Table: `results_ec8e`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `results_ec8e`;
CREATE TABLE `results_ec8e` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `election_id` bigint(20) unsigned NOT NULL,
  `coordinator_id` bigint(20) unsigned NOT NULL,
  `party_votes_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`party_votes_json`)),
  `valid_votes` int(10) unsigned NOT NULL DEFAULT 0,
  `rejected_votes` int(10) unsigned NOT NULL DEFAULT 0,
  `total_votes` int(10) unsigned NOT NULL DEFAULT 0,
  `calculated_total_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`calculated_total_json`)),
  `mismatch_alert` tinyint(1) NOT NULL DEFAULT 0,
  `mismatch_details_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`mismatch_details_json`)),
  `form_photo_url` varchar(500) DEFAULT NULL,
  `form_sha256` varchar(64) DEFAULT NULL,
  `declaration_time` timestamp NULL DEFAULT NULL,
  `status` enum('pending','verified','declared','rejected','flagged') NOT NULL DEFAULT 'pending',
  `verified_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ec8e_tenant` (`tenant_id`),
  KEY `idx_ec8e_election` (`election_id`),
  KEY `idx_ec8e_mismatch` (`mismatch_alert`),
  KEY `coordinator_id` (`coordinator_id`),
  CONSTRAINT `results_ec8e_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `results_ec8e_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `results_ec8e_ibfk_3` FOREIGN KEY (`coordinator_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `results_ec8e` is empty

-- ------------------------------------------------------------
-- Table: `roles`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `level` enum('super_admin','client_admin','national','state','senatorial','federal_constituency','lga','ward','pu_agent','party_agent','volunteer','observer','situation_room','finance_officer','citizen') NOT NULL,
  `description` text DEFAULT NULL,
  `permissions_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`permissions_json`)),
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_roles_tenant_slug` (`tenant_id`,`slug`),
  KEY `idx_roles_level` (`level`),
  KEY `idx_roles_tenant` (`tenant_id`),
  CONSTRAINT `roles_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`id`, `tenant_id`, `name`, `slug`, `level`, `description`, `permissions_json`, `is_system`, `is_active`, `created_at`, `updated_at`) VALUES 
('1', NULL, 'Super Administrator', 'super_admin', 'super_admin', NULL, '{\"all\": true}', '1', '1', '2026-07-01 23:27:19', '2026-07-01 23:27:19'),
('2', NULL, 'Client Administrator', 'client_admin', 'client_admin', NULL, '{\"all\": true}', '1', '1', '2026-07-01 23:27:19', '2026-07-01 23:27:19'),
('3', NULL, 'National Coordinator', 'national', 'national', NULL, '{\"manage_elections\": true, \"view_all_results\": true}', '1', '1', '2026-07-01 23:27:19', '2026-07-01 23:27:19'),
('4', NULL, 'State Coordinator', 'state', 'state', NULL, '{\"manage_state_elections\": true, \"view_state_results\": true}', '1', '1', '2026-07-01 23:27:19', '2026-07-01 23:27:19'),
('5', NULL, 'LGA Coordinator', 'lga', 'lga', NULL, '{\"manage_lga_elections\": true, \"view_lga_results\": true}', '1', '1', '2026-07-01 23:27:19', '2026-07-01 23:27:19'),
('6', NULL, 'Ward Coordinator', 'ward', 'ward', NULL, '{\"manage_ward_elections\": true, \"view_ward_results\": true}', '1', '1', '2026-07-01 23:27:19', '2026-07-01 23:27:19'),
('7', NULL, 'Polling Unit Agent', 'pu_agent', 'pu_agent', NULL, '{\"submit_results\": true, \"view_pu_results\": true}', '1', '1', '2026-07-01 23:27:19', '2026-07-01 23:27:19'),
('8', NULL, 'Party Agent', 'party_agent', 'party_agent', NULL, '{\"monitor_results\": true, \"view_party_results\": true}', '1', '1', '2026-07-01 23:27:19', '2026-07-01 23:27:19'),
('9', NULL, 'Observer', 'observer', 'observer', NULL, '{\"view_results\": true, \"report_incidents\": true}', '1', '1', '2026-07-01 23:27:19', '2026-07-01 23:27:19'),
('10', NULL, 'Super Administrator', 'super_admin', 'super_admin', NULL, '{\"all\": true}', '1', '1', '2026-07-02 00:12:49', '2026-07-02 00:12:49'),
('11', NULL, 'Client Administrator', 'client_admin', 'client_admin', NULL, '{\"all\": true}', '1', '1', '2026-07-02 00:12:49', '2026-07-02 00:12:49'),
('12', NULL, 'National Coordinator', 'national', 'national', NULL, '{\"manage_elections\": true}', '1', '1', '2026-07-02 00:12:49', '2026-07-02 00:12:49'),
('13', NULL, 'State Coordinator', 'state', 'state', NULL, '{\"manage_state_elections\": true}', '1', '1', '2026-07-02 00:12:49', '2026-07-02 00:12:49'),
('14', NULL, 'LGA Coordinator', 'lga', 'lga', NULL, '{\"manage_lga_elections\": true}', '1', '1', '2026-07-02 00:12:49', '2026-07-02 00:12:49'),
('15', NULL, 'Polling Unit Agent', 'pu_agent', 'pu_agent', NULL, '{\"submit_results\": true}', '1', '1', '2026-07-02 00:12:49', '2026-07-02 00:12:49'),
('16', NULL, 'Super Administrator', 'super_admin', 'super_admin', NULL, '{\"all\": true}', '1', '1', '2026-07-02 00:16:51', '2026-07-02 00:16:51'),
('17', NULL, 'Client Administrator', 'client_admin', 'client_admin', NULL, '{\"all\": true}', '1', '1', '2026-07-02 00:16:51', '2026-07-02 00:16:51'),
('18', NULL, 'National Coordinator', 'national', 'national', NULL, '{\"manage_elections\": true}', '1', '1', '2026-07-02 00:16:51', '2026-07-02 00:16:51'),
('19', NULL, 'State Coordinator', 'state', 'state', NULL, '{\"manage_state_elections\": true}', '1', '1', '2026-07-02 00:16:51', '2026-07-02 00:16:51'),
('20', NULL, 'LGA Coordinator', 'lga', 'lga', NULL, '{\"manage_lga_elections\": true}', '1', '1', '2026-07-02 00:16:51', '2026-07-02 00:16:51');

-- ------------------------------------------------------------
-- Table: `security_events`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `security_events`;
CREATE TABLE `security_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `event_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `device_id` varchar(255) DEFAULT NULL,
  `gps_lat` decimal(10,8) DEFAULT NULL,
  `gps_lng` decimal(11,8) DEFAULT NULL,
  `risk_score` tinyint(3) unsigned DEFAULT NULL,
  `resolved` tinyint(1) NOT NULL DEFAULT 0,
  `resolved_by` bigint(20) unsigned DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_security_tenant` (`tenant_id`),
  KEY `idx_security_user` (`user_id`),
  KEY `idx_security_type` (`event_type`),
  KEY `idx_security_risk` (`risk_score`),
  CONSTRAINT `security_events_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `security_events_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `security_events` (`id`, `tenant_id`, `user_id`, `event_type`, `description`, `ip_address`, `device_id`, `gps_lat`, `gps_lng`, `risk_score`, `resolved`, `resolved_by`, `resolved_at`, `created_at`) VALUES 
('1', NULL, '2', 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, '0', NULL, NULL, '2026-07-02 00:06:54'),
('2', NULL, '2', 'password_reset', 'Password reset requested from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, '0', NULL, NULL, '2026-07-02 00:23:05'),
('3', NULL, '2', 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, '0', NULL, NULL, '2026-07-02 00:24:33'),
('4', NULL, '2', 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, '0', NULL, NULL, '2026-07-02 00:28:55'),
('5', NULL, '2', 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, '0', NULL, NULL, '2026-07-02 00:36:06'),
('6', NULL, '2', 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, '0', NULL, NULL, '2026-07-02 00:40:33'),
('7', NULL, '2', 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, '0', NULL, NULL, '2026-07-02 00:41:13'),
('8', NULL, '2', 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, '0', NULL, NULL, '2026-07-02 00:49:09'),
('9', NULL, '2', 'login', 'Successful login from IP: 10.180.98.13', '10.180.98.13', 'b82256a7d530eb3d10525750af312b15891c852c68fb2b9a6a14508a2782f844', NULL, NULL, NULL, '0', NULL, NULL, '2026-07-02 01:30:15'),
('10', NULL, '2', 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, '0', NULL, NULL, '2026-07-02 02:24:48'),
('11', NULL, '2', 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, '0', NULL, NULL, '2026-07-02 05:52:21'),
('12', NULL, '2', 'password_reset', 'Password reset requested from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, '0', NULL, NULL, '2026-07-02 06:52:52'),
('13', NULL, '2', 'password_change', 'Password reset completed from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, '0', NULL, NULL, '2026-07-02 06:53:15'),
('14', NULL, '2', 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, '0', NULL, NULL, '2026-07-02 06:53:53');

-- ------------------------------------------------------------
-- Table: `senatorial_districts`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `senatorial_districts`;
CREATE TABLE `senatorial_districts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `state_id` int(10) unsigned NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `lgas_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`lgas_json`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sd_state_code` (`state_id`,`code`),
  CONSTRAINT `senatorial_districts_ibfk_1` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `senatorial_districts` is empty

-- ------------------------------------------------------------
-- Table: `states`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `states`;
CREATE TABLE `states` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(10) NOT NULL,
  `name` varchar(100) NOT NULL,
  `capital` varchar(100) DEFAULT NULL,
  `gps_lat` decimal(10,8) DEFAULT NULL,
  `gps_lng` decimal(11,8) DEFAULT NULL,
  `registered_voters` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `states` (`id`, `code`, `name`, `capital`, `gps_lat`, `gps_lng`, `registered_voters`, `is_active`, `created_at`) VALUES 
('1', 'AB', 'Abia', 'Umuahia', '5.41670000', '7.50000000', '1842120', '1', '2026-07-02 02:08:42'),
('2', 'AD', 'Adamawa', 'Yola', '9.20000000', '12.50000000', '1314077', '1', '2026-07-02 02:08:42'),
('3', 'AK', 'Akwa Ibom', 'Uyo', '5.00000000', '7.83330000', '1744622', '1', '2026-07-02 02:08:42'),
('4', 'AN', 'Anambra', 'Awka', '6.20000000', '7.06670000', '2165539', '1', '2026-07-02 02:08:42'),
('5', 'BA', 'Bauchi', 'Bauchi', '10.31570000', '9.84420000', '1941539', '1', '2026-07-02 02:08:42'),
('6', 'BY', 'Bayelsa', 'Yenagoa', '4.75000000', '6.08330000', '924014', '1', '2026-07-02 02:08:42'),
('7', 'BE', 'Benue', 'Makurdi', '7.73330000', '8.53330000', '2085933', '1', '2026-07-02 02:08:42'),
('8', 'BO', 'Borno', 'Maiduguri', '11.83330000', '13.15000000', '1753012', '1', '2026-07-02 02:08:42'),
('9', 'CR', 'Cross River', 'Calabar', '5.33330000', '8.35000000', '1368493', '1', '2026-07-02 02:08:42'),
('10', 'DE', 'Delta', 'Asaba', '6.23330000', '6.75000000', '2444013', '1', '2026-07-02 02:08:42'),
('11', 'EB', 'Ebonyi', 'Abakaliki', '6.33330000', '8.10000000', '1115388', '1', '2026-07-02 02:08:42'),
('12', 'ED', 'Edo', 'Benin City', '6.33330000', '5.61670000', '1841809', '1', '2026-07-02 02:08:42'),
('13', 'EK', 'Ekiti', 'Ado Ekiti', '7.63330000', '5.21670000', '850020', '1', '2026-07-02 02:08:42'),
('14', 'EN', 'Enugu', 'Enugu', '6.45000000', '7.50000000', '1765683', '1', '2026-07-02 02:08:42'),
('15', 'FC', 'Federal Capital Territory', 'Abuja', '9.06670000', '7.48330000', '1602702', '1', '2026-07-02 02:08:42'),
('16', 'GO', 'Gombe', 'Gombe', '10.28330000', '11.16670000', '1085404', '1', '2026-07-02 02:08:42'),
('17', 'IM', 'Imo', 'Owerri', '5.48330000', '7.03330000', '1816533', '1', '2026-07-02 02:08:42'),
('18', 'JI', 'Jigawa', 'Dutse', '11.80000000', '9.33330000', '2018040', '1', '2026-07-02 02:08:42'),
('19', 'KD', 'Kaduna', 'Kaduna', '10.51670000', '7.43330000', '3505938', '1', '2026-07-02 02:08:42'),
('20', 'KN', 'Kano', 'Kano', '12.00000000', '8.51670000', '4663458', '1', '2026-07-02 02:08:42'),
('21', 'KT', 'Katsina', 'Katsina', '12.98330000', '7.60000000', '2491650', '1', '2026-07-02 02:08:42'),
('22', 'KE', 'Kebbi', 'Birnin Kebbi', '12.45000000', '4.20000000', '1479704', '1', '2026-07-02 02:08:42'),
('23', 'KO', 'Kogi', 'Lokoja', '7.80000000', '6.73330000', '1457653', '1', '2026-07-02 02:08:42'),
('24', 'KW', 'Kwara', 'Ilorin', '8.50000000', '4.55000000', '1308828', '1', '2026-07-02 02:08:42'),
('25', 'LA', 'Lagos', 'Ikeja', '6.52440000', '3.37920000', '5931571', '1', '2026-07-02 02:08:42'),
('26', 'NA', 'Nasarawa', 'Lafia', '8.50000000', '8.51670000', '1219823', '1', '2026-07-02 02:08:42'),
('27', 'NI', 'Niger', 'Minna', '9.60000000', '6.55000000', '1840223', '1', '2026-07-02 02:08:42'),
('28', 'OG', 'Ogun', 'Abeokuta', '7.15000000', '3.35000000', '1826046', '1', '2026-07-02 02:08:42'),
('29', 'ON', 'Ondo', 'Akure', '7.25000000', '5.20000000', '1606553', '1', '2026-07-02 02:08:42'),
('30', 'OS', 'Osun', 'Osogbo', '7.76670000', '4.56670000', '1370283', '1', '2026-07-02 02:08:42'),
('31', 'OY', 'Oyo', 'Ibadan', '7.85000000', '3.93330000', '2578312', '1', '2026-07-02 02:08:42'),
('32', 'PL', 'Plateau', 'Jos', '9.88330000', '8.88330000', '1854192', '1', '2026-07-02 02:08:42'),
('33', 'RI', 'Rivers', 'Port Harcourt', '4.81670000', '7.00000000', '2375005', '1', '2026-07-02 02:08:42'),
('34', 'SO', 'Sokoto', 'Sokoto', '13.06670000', '5.23330000', '1560831', '1', '2026-07-02 02:08:42'),
('35', 'TA', 'Taraba', 'Jalingo', '8.90000000', '11.36670000', '1164547', '1', '2026-07-02 02:08:42'),
('36', 'YO', 'Yobe', 'Damaturu', '11.73330000', '11.96670000', '1070612', '1', '2026-07-02 02:08:42'),
('37', 'ZA', 'Zamfara', 'Gusau', '12.16670000', '6.66670000', '1475679', '1', '2026-07-02 02:08:42');

-- ------------------------------------------------------------
-- Table: `subscriptions`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `subscriptions`;
CREATE TABLE `subscriptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `plan` enum('free','basic','standard','premium','enterprise') NOT NULL,
  `billing_cycle` enum('monthly','quarterly','yearly') NOT NULL DEFAULT 'monthly',
  `amount` decimal(15,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'NGN',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `auto_renew` tinyint(1) NOT NULL DEFAULT 1,
  `payment_status` enum('pending','paid','overdue','cancelled','refunded') NOT NULL DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `transaction_reference` varchar(100) DEFAULT NULL,
  `invoice_url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_subscriptions_tenant` (`tenant_id`),
  KEY `idx_subscriptions_status` (`payment_status`),
  CONSTRAINT `subscriptions_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `subscriptions` (`id`, `tenant_id`, `plan`, `billing_cycle`, `amount`, `currency`, `start_date`, `end_date`, `auto_renew`, `payment_status`, `payment_method`, `transaction_reference`, `invoice_url`, `created_at`, `updated_at`) VALUES 
('3', '5', 'enterprise', 'yearly', '9000.00', 'NGN', '2027-08-04', '2028-08-04', '1', 'paid', 'bank_transfer', '', NULL, '2026-07-02 06:19:32', '2026-07-02 06:32:06');

-- ------------------------------------------------------------
-- Table: `support_ticket_replies`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `support_ticket_replies`;
CREATE TABLE `support_ticket_replies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `message` text NOT NULL,
  `attachment_urls_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachment_urls_json`)),
  `is_internal` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ticket_id` (`ticket_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `support_ticket_replies_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `support_ticket_replies_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `support_ticket_replies` is empty

-- ------------------------------------------------------------
-- Table: `support_tickets`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `support_tickets`;
CREATE TABLE `support_tickets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `ticket_number` varchar(50) NOT NULL,
  `category` enum('technical','billing','feature_request','bug_report','account','security','other') NOT NULL,
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `subject` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `status` enum('open','in_progress','waiting','resolved','closed','escalated') NOT NULL DEFAULT 'open',
  `assigned_to` bigint(20) unsigned DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ticket_number` (`ticket_number`),
  KEY `idx_tickets_tenant` (`tenant_id`),
  KEY `idx_tickets_user` (`user_id`),
  KEY `idx_tickets_status` (`status`),
  KEY `idx_tickets_priority` (`priority`),
  KEY `assigned_to` (`assigned_to`),
  CONSTRAINT `support_tickets_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `support_tickets_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `support_tickets_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `support_tickets` is empty

-- ------------------------------------------------------------
-- Table: `system_settings`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL,
  `value` text NOT NULL,
  `type` enum('string','integer','boolean','json','array') NOT NULL DEFAULT 'string',
  `description` text DEFAULT NULL,
  `is_editable` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `system_settings` (`id`, `key`, `value`, `type`, `description`, `is_editable`, `created_at`, `updated_at`) VALUES 
('1', 'site_name', '5G Election Guru', 'string', 'Site name', '1', '2026-07-01 23:27:19', '2026-07-01 23:27:19'),
('2', 'max_login_attempts', '5', 'integer', 'Maximum login attempts before lockout', '1', '2026-07-01 23:27:19', '2026-07-01 23:27:19'),
('3', 'lockout_duration', '15', 'integer', 'Lockout duration in minutes', '1', '2026-07-01 23:27:19', '2026-07-01 23:27:19'),
('4', 'session_timeout', '3600', 'integer', 'Session timeout in seconds', '1', '2026-07-01 23:27:19', '2026-07-01 23:27:19'),
('5', 'otp_expiry', '300', 'integer', 'OTP expiry in seconds', '1', '2026-07-01 23:27:19', '2026-07-01 23:27:19'),
('6', 'two_factor_enabled', 'true', 'boolean', 'Enable two-factor authentication', '1', '2026-07-01 23:27:19', '2026-07-01 23:27:19');

-- ------------------------------------------------------------
-- Table: `tenant_settings`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `tenant_settings`;
CREATE TABLE `tenant_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned NOT NULL,
  `key` varchar(100) NOT NULL,
  `value` text NOT NULL,
  `type` enum('string','integer','boolean','json','array') NOT NULL DEFAULT 'string',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_settings` (`tenant_id`,`key`),
  CONSTRAINT `tenant_settings_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `tenant_settings` is empty

-- ------------------------------------------------------------
-- Table: `tenants`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `tenants`;
CREATE TABLE `tenants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `name` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `type` enum('political_party','candidate','ngo','observer_group','cso','research_institution') NOT NULL DEFAULT 'political_party',
  `subscription_plan` enum('free','basic','standard','premium','enterprise') NOT NULL DEFAULT 'basic',
  `subscription_status` enum('trial','active','suspended','expired','cancelled') NOT NULL DEFAULT 'trial',
  `subscription_start` date DEFAULT NULL,
  `subscription_end` date DEFAULT NULL,
  `max_users` int(10) unsigned NOT NULL DEFAULT 100,
  `max_agents` int(10) unsigned NOT NULL DEFAULT 500,
  `max_storage_mb` bigint(20) unsigned NOT NULL DEFAULT 10737418240,
  `used_storage_mb` bigint(20) unsigned NOT NULL DEFAULT 0,
  `logo_url` varchar(500) DEFAULT NULL,
  `primary_color` varchar(7) DEFAULT '#3b82f6',
  `secondary_color` varchar(7) DEFAULT '#10b981',
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `state_id` int(10) unsigned DEFAULT NULL,
  `lga_id` int(10) unsigned DEFAULT NULL,
  `settings_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings_json`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uuid` (`uuid`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_tenants_slug` (`slug`),
  KEY `idx_tenants_status` (`subscription_status`),
  KEY `idx_tenants_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tenants` (`id`, `uuid`, `name`, `slug`, `type`, `subscription_plan`, `subscription_status`, `subscription_start`, `subscription_end`, `max_users`, `max_agents`, `max_storage_mb`, `used_storage_mb`, `logo_url`, `primary_color`, `secondary_color`, `contact_email`, `contact_phone`, `address`, `state_id`, `lga_id`, `settings_json`, `is_active`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES 
('5', '6a45f1e381a2c-8908443da2c95b46', 'APC', 'apc', 'political_party', 'enterprise', 'trial', NULL, '2028-08-04', '100', '500', '10240', '0', '/uploads/tenants/tenant_1782968803_6a45f1e381678.jpg', '#3b82f6', '#10b981', 'aliyuabubakar11117@gmail.com', '+2348034897634', 'Kangire, Birninkudu\r\nNigeria', '18', '340', NULL, '1', '2', '2026-07-02 06:06:43', '2026-07-02 07:26:30', '2026-07-02 07:26:30');

-- ------------------------------------------------------------
-- Table: `user_sessions`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `user_sessions`;
CREATE TABLE `user_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `token` varchar(500) NOT NULL,
  `device_id` varchar(255) DEFAULT NULL,
  `device_type` enum('web','android','ios') NOT NULL,
  `device_name` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `gps_lat` decimal(10,8) DEFAULT NULL,
  `gps_lng` decimal(11,8) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_activity_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sessions_user` (`user_id`),
  KEY `idx_sessions_token` (`token`(255)),
  KEY `idx_sessions_active` (`is_active`),
  CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `user_sessions` (`id`, `user_id`, `token`, `device_id`, `device_type`, `device_name`, `ip_address`, `gps_lat`, `gps_lng`, `user_agent`, `expires_at`, `last_activity_at`, `is_active`, `created_at`) VALUES 
('1', '2', '3966a639eaf4f49b5d96837eb78af92c5a27d5855641d7a2cb306a28cf10d12b', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-02 00:06:54', '2026-07-02 00:06:54', '0', '2026-07-02 00:01:44'),
('2', '2', 'bf2266abb2984a81b43c554c18dee34b0c1908baa4daba40acbb91fec0d8d3e5', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-02 00:28:56', '2026-07-02 00:28:56', '0', '2026-07-02 00:24:33'),
('3', '2', 'd52a3d1e4dfe980904eb91727da5a7f321abb923e6bcd4ebc6b6815e2be7a140', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-02 00:40:33', '2026-07-02 00:40:33', '0', '2026-07-02 00:36:06'),
('4', '2', '84bdb3a497dc684168b43da8c62f01a0e67dd76ad39055bd441fa14e5aeaae32', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-02 01:41:12', '2026-07-02 00:41:12', '1', '2026-07-02 00:41:12'),
('5', '2', '89d8a9db5cc52cdfaf1950faa1c407c995511950347ab5b2d773d66b7fc7119f', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-02 01:49:09', '2026-07-02 00:49:09', '1', '2026-07-02 00:49:09'),
('6', '2', '5615cecd64952b7ac23038671a536c942b0da004a50635c0ff58825de1c65ed1', 'b82256a7d530eb3d10525750af312b15891c852c68fb2b9a6a14508a2782f844', 'web', NULL, '10.180.98.13', NULL, NULL, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '2026-08-01 01:30:15', '2026-07-02 01:30:15', '1', '2026-07-02 01:30:15'),
('7', '2', '90a2d99b1d3f2f384198f2c00a764c1fbaa9549e9a64ff0f548a3bc50dd441d6', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-02 03:24:48', '2026-07-02 02:24:48', '1', '2026-07-02 02:24:48'),
('8', '2', 'e3327684f06c88d5e08744773c85001db390f17e2498e18985db5a478b379f4c', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-02 06:52:20', '2026-07-02 05:52:20', '1', '2026-07-02 05:52:20'),
('9', '2', '9cce8411343aacebad875aadd1fca346265b8c94a01853d0c3d6c5ab8f957aa6', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-02 07:53:53', '2026-07-02 06:53:53', '1', '2026-07-02 06:53:53');

-- ------------------------------------------------------------
-- Table: `users`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint(20) unsigned DEFAULT NULL,
  `user_code` varchar(20) NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `full_name` varchar(200) GENERATED ALWAYS AS (concat(`first_name`,' ',`last_name`)) STORED,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `phone_verified_at` timestamp NULL DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `two_factor_secret` varchar(255) DEFAULT NULL,
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `two_factor_verified_at` timestamp NULL DEFAULT NULL,
  `gender` enum('male','female','other','prefer_not_say') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `photograph_url` varchar(500) DEFAULT NULL,
  `nin` varchar(20) DEFAULT NULL,
  `bvn` varchar(20) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `account_number` varchar(20) DEFAULT NULL,
  `account_name` varchar(200) DEFAULT NULL,
  `emergency_contact_name` varchar(200) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `next_of_kin_name` varchar(200) DEFAULT NULL,
  `next_of_kin_phone` varchar(20) DEFAULT NULL,
  `residential_address` text DEFAULT NULL,
  `state_id` int(10) unsigned DEFAULT NULL,
  `lga_id` int(10) unsigned DEFAULT NULL,
  `ward_id` int(10) unsigned DEFAULT NULL,
  `pu_id` int(10) unsigned DEFAULT NULL,
  `jurisdiction_type` enum('national','state','senatorial','federal_constituency','lga','ward','pu') DEFAULT NULL,
  `jurisdiction_id` bigint(20) unsigned DEFAULT NULL,
  `device_id` varchar(255) DEFAULT NULL,
  `device_fingerprint` varchar(255) DEFAULT NULL,
  `device_bound` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `last_login_device` varchar(255) DEFAULT NULL,
  `last_login_gps_lat` decimal(10,8) DEFAULT NULL,
  `last_login_gps_lng` decimal(11,8) DEFAULT NULL,
  `login_attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `status` enum('active','suspended','pending','archived') NOT NULL DEFAULT 'pending',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_code` (`user_code`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_tenant` (`tenant_id`),
  KEY `idx_users_role` (`role_id`),
  KEY `idx_users_phone` (`phone`),
  KEY `idx_users_status` (`status`),
  KEY `idx_users_jurisdiction` (`jurisdiction_type`,`jurisdiction_id`),
  KEY `idx_users_pu` (`pu_id`),
  KEY `idx_users_dob` (`date_of_birth`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `users_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `users` is empty

-- ------------------------------------------------------------
-- Table: `wards`
-- ------------------------------------------------------------

DROP TABLE IF EXISTS `wards`;
CREATE TABLE `wards` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lga_id` int(10) unsigned NOT NULL,
  `code` varchar(30) NOT NULL,
  `name` varchar(150) NOT NULL,
  `gps_lat` decimal(10,8) DEFAULT NULL,
  `gps_lng` decimal(11,8) DEFAULT NULL,
  `registered_voters` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_wards_lga_code` (`lga_id`,`code`),
  KEY `idx_wards_lga` (`lga_id`),
  CONSTRAINT `wards_ibfk_1` FOREIGN KEY (`lga_id`) REFERENCES `lgas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table `wards` is empty

SET FOREIGN_KEY_CHECKS = 1;
