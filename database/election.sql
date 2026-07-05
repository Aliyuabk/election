-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 05, 2026 at 11:07 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `election`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `activity_type` varchar(50) NOT NULL,
  `description` varchar(500) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `device_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `tenant_id`, `activity_type`, `description`, `entity_type`, `entity_id`, `ip_address`, `device_id`, `created_at`) VALUES
(1, 2, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-01 23:01:44'),
(2, 2, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-01 23:06:54'),
(3, 2, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-01 23:24:33'),
(4, 2, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-01 23:28:55'),
(5, 2, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-01 23:36:06'),
(6, 2, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-01 23:40:33'),
(7, 2, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-01 23:41:13'),
(8, 2, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-01 23:49:09'),
(9, 2, NULL, 'login', 'User logged in successfully', NULL, NULL, '10.180.98.13', NULL, '2026-07-02 00:30:15'),
(18, 2, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 01:24:48'),
(19, 2, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 04:52:20'),
(20, 2, 5, 'tenant_created', 'New tenant created: APC', NULL, NULL, '::1', NULL, '2026-07-02 05:06:43'),
(26, 2, NULL, 'password_reset', 'Password reset requested', NULL, NULL, '::1', NULL, '2026-07-02 05:52:52'),
(27, 2, NULL, 'password_change', 'Password reset successfully', NULL, NULL, '::1', NULL, '2026-07-02 05:53:15'),
(28, 2, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 05:53:53'),
(29, 2, NULL, 'inec_clear', 'Cleared INEC data: polling_units', NULL, NULL, '::1', NULL, '2026-07-02 06:39:04'),
(30, 2, NULL, 'settings_changed', 'Email settings updated', NULL, NULL, '::1', NULL, '2026-07-02 06:54:08'),
(31, 2, NULL, 'settings_changed', 'Email settings updated', NULL, NULL, '::1', NULL, '2026-07-02 06:54:25'),
(33, 7, NULL, 'password_reset', 'Password reset requested', NULL, NULL, '::1', NULL, '2026-07-02 17:24:18'),
(34, 7, NULL, 'password_change', 'Password reset successfully', NULL, NULL, '::1', NULL, '2026-07-02 17:24:39'),
(35, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 17:24:47'),
(36, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-02 17:34:23'),
(37, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 17:34:32'),
(38, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-02 17:35:33'),
(39, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 17:35:39'),
(40, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-02 17:35:45'),
(41, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 17:36:18'),
(42, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-02 17:38:27'),
(43, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 17:38:33'),
(44, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-02 17:44:47'),
(45, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 17:44:55'),
(46, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-02 17:45:09'),
(47, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 17:45:14'),
(48, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-02 17:45:20'),
(49, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 17:46:14'),
(52, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-02 17:54:35'),
(53, 8, NULL, 'password_reset', 'Password reset requested', NULL, NULL, '::1', NULL, '2026-07-02 17:55:01'),
(54, 8, NULL, 'password_change', 'Password reset successfully', NULL, NULL, '::1', NULL, '2026-07-02 17:56:03'),
(55, 8, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 17:56:27'),
(56, 8, NULL, 'backup_created', 'Created backup: backup_2026-07-02_185750_full.sql', NULL, NULL, '::1', NULL, '2026-07-02 17:57:51'),
(57, 7, NULL, 'password_reset', 'Password reset requested', NULL, NULL, '::1', NULL, '2026-07-02 19:20:06'),
(58, 7, NULL, 'password_change', 'Password reset successfully', NULL, NULL, '::1', NULL, '2026-07-02 19:21:18'),
(59, 8, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 19:21:30'),
(60, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 19:23:57'),
(61, 8, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-02 19:48:39'),
(62, 8, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 19:48:47'),
(63, 8, NULL, 'tenant_updated', 'Updated tenant: PDP (ID: 10)', NULL, NULL, '::1', NULL, '2026-07-02 20:12:21'),
(64, 8, NULL, 'tenant_updated', 'Updated tenant: PDP (ID: 10)', NULL, NULL, '::1', NULL, '2026-07-02 20:19:32'),
(65, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-02 20:23:14'),
(66, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 20:24:13'),
(67, 7, NULL, 'user_suspended', 'User ID: 8', NULL, NULL, '::1', NULL, '2026-07-02 21:13:28'),
(68, 7, NULL, 'user_activated', 'User ID: 8', NULL, NULL, '::1', NULL, '2026-07-02 21:13:37'),
(69, 7, NULL, 'user_created', 'Created user: Aliyu Abubakar for tenant ID: 10', NULL, NULL, '::1', NULL, '2026-07-02 21:15:55'),
(70, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-02 21:16:22'),
(71, 9, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 21:16:30'),
(72, 9, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-02 21:16:33'),
(73, 8, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 21:16:44'),
(74, 8, NULL, 'election_created', 'Created election: 2027 election for tenant ID: 10', NULL, NULL, '::1', NULL, '2026-07-02 21:24:09'),
(75, 8, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 21:24:48'),
(76, 8, NULL, 'user_created', 'Created user: Aliyu abubakar (ID: 10) for tenant ID: 10', NULL, NULL, '::1', NULL, '2026-07-02 21:25:26'),
(77, 8, NULL, 'election_created', 'Created election: 2027 election for tenant ID: 10', NULL, NULL, '::1', NULL, '2026-07-02 21:27:48'),
(78, 8, NULL, 'election_created', 'Created election: 2027 election for tenant ID: 10', NULL, NULL, '::1', NULL, '2026-07-02 21:31:55'),
(79, 8, NULL, 'election_created', 'Created election: 2027 election for tenant ID: 10', NULL, NULL, '::1', NULL, '2026-07-02 21:31:58'),
(80, 8, NULL, 'election_created', 'Created election: 2027 election (ID: 6) for tenant ID: 10', NULL, NULL, '::1', NULL, '2026-07-02 21:32:40'),
(81, 8, NULL, 'election_created', 'Created election: 2027 election (ID: 7) for tenant ID: 10', NULL, NULL, '::1', NULL, '2026-07-02 21:33:02'),
(82, 8, NULL, 'election_created', 'Created election: 2027 election (ID: 8) for tenant ID: 10', NULL, NULL, '::1', NULL, '2026-07-02 21:33:09'),
(83, 8, NULL, 'subscription_deleted', 'Deleted subscription ID: 3', NULL, NULL, '::1', NULL, '2026-07-02 21:34:31'),
(84, 8, NULL, 'subscription_created', 'Created subscription for tenant ID: 10 with plan: standard', NULL, NULL, '::1', NULL, '2026-07-02 21:36:56'),
(85, 8, NULL, 'invoice_created', 'Created invoice: INV-2026-2181 for tenant ID: 10', NULL, NULL, '::1', NULL, '2026-07-02 22:02:10'),
(86, 8, NULL, 'role_permissions_updated', 'Updated permissions for role: Super Administrator (ID: 1)', NULL, NULL, '::1', NULL, '2026-07-02 22:05:19'),
(87, 8, NULL, 'inec_data_cleared', 'Cleared INEC wards data', NULL, NULL, '::1', NULL, '2026-07-02 22:26:13'),
(88, 8, NULL, 'inec_data_cleared', 'Cleared INEC states data', NULL, NULL, '::1', NULL, '2026-07-02 22:26:19'),
(89, 8, NULL, 'inec_data_uploaded', 'Uploaded INEC polling_units data', NULL, NULL, '::1', NULL, '2026-07-02 22:26:39'),
(90, 8, NULL, 'inec_data_uploaded', 'Uploaded INEC polling_units data', NULL, NULL, '::1', NULL, '2026-07-02 22:26:47'),
(91, 8, NULL, 'invoice_sent', 'Sent invoice ID: 1', NULL, NULL, '::1', NULL, '2026-07-02 22:37:49'),
(92, 8, NULL, 'invoice_paid', 'Marked invoice ID: 1 as paid', NULL, NULL, '::1', NULL, '2026-07-02 22:39:02'),
(93, 8, NULL, 'inec_upload', 'Uploaded INEC data: inec_states_1783032177.csv', NULL, NULL, '::1', NULL, '2026-07-02 22:42:57'),
(94, 8, NULL, 'inec_upload', 'Uploaded INEC data: inec_states_1783032182.csv', NULL, NULL, '::1', NULL, '2026-07-02 22:43:02'),
(95, 8, NULL, '2fa_enabled', '2FA enabled', NULL, NULL, '::1', NULL, '2026-07-02 23:19:51'),
(96, 8, NULL, 'password_change', 'Password changed successfully', NULL, NULL, '::1', NULL, '2026-07-02 23:20:11'),
(97, 8, NULL, '2fa_disabled', '2FA disabled', NULL, NULL, '::1', NULL, '2026-07-02 23:20:17'),
(98, 8, NULL, 'profile_updated', 'Profile information updated', NULL, NULL, '::1', NULL, '2026-07-02 23:27:22'),
(99, 8, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-02 23:28:13'),
(100, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 23:28:20'),
(101, 7, NULL, 'backup_created', 'Created backup: backup_2026-07-03_00-44-19_full.sql', NULL, NULL, '::1', NULL, '2026-07-02 23:44:21'),
(102, 7, NULL, 'ticket_created', 'Created ticket: TKT-2026-83269', NULL, NULL, '::1', NULL, '2026-07-02 23:48:52'),
(103, 7, NULL, 'ticket_replied', 'Replied to ticket ID: 1', NULL, NULL, '::1', NULL, '2026-07-02 23:49:16'),
(104, 7, NULL, 'ticket_assigned', 'Assigned ticket ID: 1 to user: 9', NULL, NULL, '::1', NULL, '2026-07-02 23:49:36'),
(105, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-03 00:20:32'),
(106, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-03 00:20:39'),
(107, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-03 00:25:34'),
(108, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-03 00:25:49'),
(109, 7, NULL, 'election_updated', 'Updated election: 2027 election (ID: 8)', NULL, NULL, '::1', NULL, '2026-07-03 00:39:51'),
(110, 7, NULL, 'election_deleted', 'Deleted election ID: 8', NULL, NULL, '::1', NULL, '2026-07-03 00:40:27'),
(111, 7, NULL, 'election_status_changed', 'Changed election ID: 7 status to active', NULL, NULL, '::1', NULL, '2026-07-03 00:44:31'),
(112, 7, NULL, 'election_updated', 'Updated election: 2027 election (ID: 7)', NULL, NULL, '::1', NULL, '2026-07-03 00:45:27'),
(113, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-03 00:48:05'),
(114, 9, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-03 00:48:12'),
(115, 9, NULL, 'organization_logo_updated', 'Updated organization logo', NULL, NULL, '::1', NULL, '2026-07-03 01:06:27'),
(116, 9, NULL, 'user_suspended', 'Suspended user ID: 10', NULL, NULL, '::1', NULL, '2026-07-03 01:10:12'),
(117, 9, NULL, 'user_activated', 'Activated user ID: 10', NULL, NULL, '::1', NULL, '2026-07-03 01:10:19'),
(118, 9, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-03 01:23:50'),
(119, 9, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-03 01:24:27'),
(120, 9, NULL, 'user_archived', 'Archived user ID: 10', NULL, NULL, '::1', NULL, '2026-07-03 01:27:28'),
(121, 9, NULL, 'user_activated', 'Activated user ID: 10', NULL, NULL, '::1', NULL, '2026-07-03 01:27:34'),
(122, 9, NULL, 'user_deleted', 'Deleted user ID: 10', NULL, NULL, '::1', NULL, '2026-07-03 01:27:50'),
(123, 9, NULL, 'user_deleted', 'Deleted user ID: 8', NULL, NULL, '::1', NULL, '2026-07-03 01:28:09'),
(124, 9, NULL, 'election_duplicated', 'Duplicated election ID: 7', NULL, NULL, '::1', NULL, '2026-07-03 01:32:30'),
(125, 9, NULL, 'election_deleted', 'Deleted election ID: 9', NULL, NULL, '::1', NULL, '2026-07-03 01:32:37'),
(126, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-03 13:57:24'),
(127, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-03 13:57:28'),
(128, 8, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-03 13:57:37'),
(129, 8, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-03 13:57:39'),
(130, 9, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-03 13:57:50'),
(131, 9, NULL, 'state_added', 'Added state: Jigawa', NULL, NULL, '::1', NULL, '2026-07-03 14:07:14'),
(132, 9, NULL, 'state_added', 'Added state: Kano', NULL, NULL, '::1', NULL, '2026-07-03 14:20:24'),
(133, 9, NULL, 'state_added', 'Added state: Kaduna', NULL, NULL, '::1', NULL, '2026-07-03 14:20:52'),
(134, 9, NULL, 'state_added', 'Added state: Kano', NULL, NULL, '::1', NULL, '2026-07-03 14:21:18'),
(135, 9, NULL, 'state_deleted', 'Deleted state ID: 39', NULL, NULL, '::1', NULL, '2026-07-03 14:21:27'),
(136, 9, NULL, 'state_deleted', 'Deleted state ID: 40', NULL, NULL, '::1', NULL, '2026-07-03 14:21:34'),
(137, 9, NULL, 'state_deleted', 'Deleted state ID: 38', NULL, NULL, '::1', NULL, '2026-07-03 14:21:43'),
(138, 9, NULL, 'lga_added', 'Added LGA: Rano', NULL, NULL, '::1', NULL, '2026-07-03 14:24:14'),
(139, 9, NULL, 'election_deleted', 'Deleted election ID: 7', NULL, NULL, '::1', NULL, '2026-07-03 14:36:19'),
(140, 9, NULL, 'election_archived', 'Archived election ID: 6', NULL, NULL, '::1', NULL, '2026-07-03 14:36:24'),
(141, 9, NULL, 'election_deleted', 'Deleted election ID: 6', NULL, NULL, '::1', NULL, '2026-07-03 14:36:28'),
(142, 9, NULL, 'election_deleted', 'Deleted election ID: 5', NULL, NULL, '::1', NULL, '2026-07-03 14:36:35'),
(143, 9, NULL, 'election_deleted', 'Deleted election ID: 3', NULL, NULL, '::1', NULL, '2026-07-03 14:36:40'),
(144, 9, NULL, 'election_deleted', 'Deleted election ID: 4', NULL, NULL, '::1', NULL, '2026-07-03 14:36:48'),
(145, 9, NULL, 'election_deleted', 'Deleted election ID: 2', NULL, NULL, '::1', NULL, '2026-07-03 14:36:54'),
(146, 9, NULL, 'ward_added', 'Added Ward: APC', NULL, NULL, '::1', NULL, '2026-07-03 14:47:20'),
(147, 9, NULL, 'pu_added', 'Added Polling Unit: APC', NULL, NULL, '::1', NULL, '2026-07-03 14:52:39'),
(148, 9, NULL, 'election_created', 'Created election: 2027 election (ID: 10)', NULL, NULL, '::1', NULL, '2026-07-03 15:14:26'),
(149, 9, NULL, 'candidate_added', 'Added candidate: Aliyu Abubakar to election ID: 10', NULL, NULL, '::1', NULL, '2026-07-03 15:17:19'),
(150, 9, NULL, 'election_pus_assigned_all', 'Assigned all polling units to election ID: 10', NULL, NULL, '::1', NULL, '2026-07-03 15:17:49'),
(151, 9, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-03 18:45:44'),
(152, 8, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-03 18:46:41'),
(153, 8, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-03 18:48:19'),
(154, 9, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-03 18:48:34'),
(155, 9, NULL, 'election_pu_removed', 'Removed polling unit ID: 1 from election ID: 10', NULL, NULL, '::1', NULL, '2026-07-03 18:49:53'),
(156, 9, NULL, 'party_added', 'Added party: Aliyu Abubakar', NULL, NULL, '::1', NULL, '2026-07-03 20:21:40'),
(157, 9, NULL, 'broadcast_created', 'Created broadcast ID: 1', NULL, NULL, '::1', NULL, '2026-07-03 21:12:26'),
(158, 9, NULL, 'budget_created', 'Created budget: APC', NULL, NULL, '::1', NULL, '2026-07-03 21:29:55'),
(159, 9, NULL, 'audit_logs_cleared', 'Cleared audit logs older than 30 days', NULL, NULL, '::1', NULL, '2026-07-03 22:10:56'),
(160, 9, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-03 22:46:14'),
(161, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-03 22:46:29'),
(162, 7, NULL, 'user_created', 'Created user: Aliyu Abubakar (ID: 12) for tenant ID: 10', NULL, NULL, '::1', NULL, '2026-07-03 22:49:35'),
(163, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-03 22:49:51'),
(164, 12, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-03 22:51:54'),
(165, 12, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-03 22:55:16'),
(166, 12, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-03 22:55:40'),
(167, 12, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-03 22:55:40'),
(168, 12, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-03 22:56:24'),
(169, 12, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-03 23:05:29'),
(170, 12, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-03 23:06:19'),
(171, 12, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-03 23:18:20'),
(172, 12, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-03 23:18:52'),
(173, 12, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-03 23:18:52'),
(174, 12, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-03 23:19:37'),
(175, 12, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-03 23:23:53'),
(176, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-03 23:24:07'),
(177, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-03 23:30:25'),
(178, 12, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-03 23:30:33'),
(179, 12, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-03 23:30:33'),
(180, 12, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-03 23:31:59'),
(181, 12, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-03 23:31:59'),
(182, 12, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-03 23:37:13'),
(183, 12, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-03 23:37:13'),
(184, 12, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-03 23:40:08'),
(185, 12, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-03 23:40:08'),
(186, 12, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-03 23:40:41'),
(187, 12, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-03 23:40:41'),
(188, 12, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-03 23:40:51'),
(189, 12, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-03 23:40:51'),
(190, 12, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-03 23:41:11'),
(191, 12, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-03 23:52:07'),
(192, 12, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-03 23:52:15'),
(193, 12, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-03 23:52:56'),
(194, 12, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-03 23:53:04'),
(195, 12, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-04 00:18:01'),
(196, 12, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-04 00:18:18'),
(197, 12, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-04 00:24:29'),
(198, 12, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-04 00:24:36'),
(199, 12, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-04 00:25:34'),
(200, 12, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-04 00:25:40'),
(201, 12, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-04 00:26:53'),
(202, 12, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-04 00:27:21'),
(203, 12, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-04 00:29:07'),
(204, 12, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-04 00:29:31'),
(205, 12, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-04 00:30:25'),
(206, 12, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-04 00:30:46'),
(207, 12, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-04 00:31:21'),
(208, 12, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-04 00:31:28'),
(209, 12, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-04 00:32:57'),
(210, 12, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-04 00:33:05'),
(211, 12, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-04 00:39:41'),
(212, 12, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-04 00:39:59'),
(213, 12, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-04 01:04:39'),
(214, 12, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-05 09:01:48'),
(215, 12, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-05 09:04:18'),
(216, 12, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-05 09:04:33'),
(217, 12, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-05 09:04:52'),
(218, 12, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-05 09:05:01');

-- --------------------------------------------------------

--
-- Table structure for table `agent_assignments`
--

CREATE TABLE `agent_assignments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `election_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `pu_id` int(10) UNSIGNED NOT NULL,
  `ward_id` int(10) UNSIGNED NOT NULL,
  `lga_id` int(10) UNSIGNED NOT NULL,
  `state_id` int(10) UNSIGNED NOT NULL,
  `assignment_type` enum('data_agent','party_agent','volunteer','observer') NOT NULL,
  `status` enum('pending','active','completed','suspended','reassigned') NOT NULL DEFAULT 'pending',
  `assigned_by` bigint(20) UNSIGNED NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `agent_checkins`
--

CREATE TABLE `agent_checkins` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `election_id` bigint(20) UNSIGNED NOT NULL,
  `agent_id` bigint(20) UNSIGNED NOT NULL,
  `assignment_id` bigint(20) UNSIGNED NOT NULL,
  `pu_id` int(10) UNSIGNED NOT NULL,
  `checkin_type` enum('arrival','departure','material_received','accreditation_started','voting_started','voting_ended','counting_started','counting_ended') NOT NULL,
  `gps_lat` decimal(10,8) NOT NULL,
  `gps_lng` decimal(11,8) NOT NULL,
  `gps_accuracy` decimal(6,2) DEFAULT NULL,
  `gps_distance_from_pu` decimal(8,2) DEFAULT NULL,
  `photo_url` varchar(500) DEFAULT NULL,
  `device_id` varchar(255) DEFAULT NULL,
  `device_battery` tinyint(3) UNSIGNED DEFAULT NULL,
  `network_type` enum('2g','3g','4g','5g','wifi','none') DEFAULT NULL,
  `is_offline_sync` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `agent_payments`
--

CREATE TABLE `agent_payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `election_id` bigint(20) UNSIGNED NOT NULL,
  `agent_id` bigint(20) UNSIGNED NOT NULL,
  `assignment_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_type` enum('advance','daily_allowance','completion_bonus','transport','other') NOT NULL,
  `payment_method` enum('cash','bank_transfer','mobile_money') NOT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `account_number` varchar(20) DEFAULT NULL,
  `account_name` varchar(200) DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `status` enum('pending','processing','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  `paid_at` timestamp NULL DEFAULT NULL,
  `paid_by` bigint(20) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `api_keys`
--

CREATE TABLE `api_keys` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `key_hash` varchar(255) NOT NULL,
  `key_prefix` varchar(20) NOT NULL,
  `permissions_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`permissions_json`)),
  `rate_limit` int(10) UNSIGNED NOT NULL DEFAULT 1000,
  `rate_limit_window` int(10) UNSIGNED NOT NULL DEFAULT 3600,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `api_logs`
--

CREATE TABLE `api_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `api_key_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `tenant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `method` varchar(10) NOT NULL,
  `endpoint` varchar(500) NOT NULL,
  `request_body` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_body`)),
  `response_status` smallint(5) UNSIGNED DEFAULT NULL,
  `response_body` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_body`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `duration_ms` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `old_values_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values_json`)),
  `new_values_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values_json`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `device_id` varchar(255) DEFAULT NULL,
  `gps_lat` decimal(10,8) DEFAULT NULL,
  `gps_lng` decimal(11,8) DEFAULT NULL,
  `severity` enum('info','warning','error','critical') NOT NULL DEFAULT 'info',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `backups`
--

CREATE TABLE `backups` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `backup_type` enum('full','database','files','tenant_data') NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` bigint(20) UNSIGNED NOT NULL,
  `file_sha256` varchar(64) NOT NULL,
  `status` enum('pending','in_progress','completed','failed','restored') NOT NULL DEFAULT 'pending',
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `restored_at` timestamp NULL DEFAULT NULL,
  `restored_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `backups`
--

INSERT INTO `backups` (`id`, `tenant_id`, `backup_type`, `file_path`, `file_size`, `file_sha256`, `status`, `started_at`, `completed_at`, `restored_at`, `restored_by`, `created_by`, `created_at`) VALUES
(11, NULL, 'full', '../../backups/backup_2026-07-03_00-44-19_full.sql', 132862, 'e1512ced60ce3989d5f40c1279f2d9435a2972abd0bbe25b66da2e0107a0c7b2', 'completed', '2026-07-02 23:44:19', '2026-07-02 23:44:21', NULL, NULL, 7, '2026-07-02 23:44:19');

-- --------------------------------------------------------

--
-- Table structure for table `broadcasts`
--

CREATE TABLE `broadcasts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `election_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sender_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `target_audience` enum('all','national','state','senatorial','federal_constituency','lga','ward','pu','role_specific') NOT NULL,
  `target_ids_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`target_ids_json`)),
  `target_role_id` bigint(20) UNSIGNED DEFAULT NULL,
  `send_via` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`send_via`)),
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `status` enum('draft','scheduled','sending','sent','failed','cancelled') NOT NULL DEFAULT 'draft',
  `read_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_recipients` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `broadcasts`
--

INSERT INTO `broadcasts` (`id`, `tenant_id`, `election_id`, `sender_id`, `title`, `message`, `target_audience`, `target_ids_json`, `target_role_id`, `send_via`, `scheduled_at`, `sent_at`, `status`, `read_count`, `total_recipients`, `created_at`) VALUES
(1, 10, NULL, 9, 'update', 'asnzbx', 'all', '[\"41\",\"792\"]', 0, '[\"email\"]', '2026-07-03 14:12:00', '2026-07-03 21:12:41', 'sent', 3, 3, '2026-07-03 21:12:26');

-- --------------------------------------------------------

--
-- Table structure for table `budgets`
--

CREATE TABLE `budgets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `election_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `spent_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `remaining_amount` decimal(15,2) GENERATED ALWAYS AS (`total_amount` - `spent_amount`) STORED,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('draft','active','closed','cancelled') NOT NULL DEFAULT 'draft',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `budgets`
--

INSERT INTO `budgets` (`id`, `tenant_id`, `election_id`, `name`, `total_amount`, `spent_amount`, `start_date`, `end_date`, `status`, `created_by`, `created_at`) VALUES
(1, 10, 10, 'APC', 1000000000.00, 0.00, '2026-07-03', '2026-08-08', 'active', 9, '2026-07-03 21:29:55');

-- --------------------------------------------------------

--
-- Table structure for table `candidates`
--

CREATE TABLE `candidates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `election_id` bigint(20) UNSIGNED NOT NULL,
  `party_id` bigint(20) UNSIGNED DEFAULT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `candidates`
--

INSERT INTO `candidates` (`id`, `tenant_id`, `election_id`, `party_id`, `first_name`, `last_name`, `photograph_url`, `position`, `biography`, `manifesto`, `contact_email`, `contact_phone`, `social_media_json`, `campaign_logo_url`, `is_active`, `created_at`) VALUES
(1, 10, 10, NULL, 'Aliyu', 'Abubakar', NULL, 'CM', '', NULL, 'aliyuabubakar11117@gmail.com', '+2348034897634', NULL, NULL, 1, '2026-07-03 15:17:19');

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `room_id` bigint(20) UNSIGNED NOT NULL,
  `sender_id` bigint(20) UNSIGNED NOT NULL,
  `message_type` enum('text','image','video','audio','file','location','system') NOT NULL DEFAULT 'text',
  `content` text NOT NULL,
  `media_url` varchar(500) DEFAULT NULL,
  `media_size` bigint(20) UNSIGNED DEFAULT NULL,
  `media_sha256` varchar(64) DEFAULT NULL,
  `gps_lat` decimal(10,8) DEFAULT NULL,
  `gps_lng` decimal(11,8) DEFAULT NULL,
  `is_offline_sync` tinyint(1) NOT NULL DEFAULT 0,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_rooms`
--

CREATE TABLE `chat_rooms` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('direct','group','broadcast') NOT NULL DEFAULT 'group',
  `election_id` bigint(20) UNSIGNED DEFAULT NULL,
  `jurisdiction_type` enum('national','state','senatorial','federal_constituency','lga','ward','pu') DEFAULT NULL,
  `jurisdiction_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_room_members`
--

CREATE TABLE `chat_room_members` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `room_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `role` enum('admin','member') NOT NULL DEFAULT 'member',
  `last_read_message_id` bigint(20) UNSIGNED DEFAULT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `elections`
--

CREATE TABLE `elections` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
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
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `elections`
--

INSERT INTO `elections` (`id`, `tenant_id`, `name`, `type`, `cycle`, `election_date`, `start_time`, `end_time`, `states_json`, `lgas_json`, `wards_json`, `pus_json`, `status`, `description`, `logo_url`, `settings_json`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(2, 10, '2027 election', 'presidential', '2031', '2026-07-29', '16:29:00', '11:23:00', NULL, NULL, NULL, NULL, 'upcoming', '', NULL, NULL, 8, '2026-07-02 21:24:09', '2026-07-03 14:36:54', '2026-07-03 14:36:54'),
(3, 10, '2027 election', 'presidential', '2031', '2026-07-29', '16:29:00', '11:23:00', NULL, NULL, NULL, NULL, 'upcoming', '', NULL, NULL, 8, '2026-07-02 21:27:48', '2026-07-03 14:36:40', '2026-07-03 14:36:40'),
(4, 10, '2027 election', 'presidential', '2031', '2026-07-29', '16:29:00', '11:23:00', NULL, NULL, NULL, NULL, 'upcoming', '', NULL, NULL, 8, '2026-07-02 21:31:55', '2026-07-03 14:36:48', '2026-07-03 14:36:48'),
(5, 10, '2027 election', 'presidential', '2031', '2026-07-29', '16:29:00', '11:23:00', NULL, NULL, NULL, NULL, 'upcoming', '', NULL, NULL, 8, '2026-07-02 21:31:58', '2026-07-03 14:36:35', '2026-07-03 14:36:35'),
(6, 10, '2027 election', 'presidential', '2031', '2026-07-29', '16:29:00', '11:23:00', NULL, NULL, NULL, NULL, 'archived', '', NULL, NULL, 8, '2026-07-02 21:32:40', '2026-07-03 14:36:28', '2026-07-03 14:36:28'),
(7, 10, '2027 election', 'presidential', '2031', '2026-07-29', '16:29:00', '11:23:00', NULL, NULL, NULL, NULL, 'upcoming', '', NULL, NULL, 8, '2026-07-02 21:33:01', '2026-07-03 14:36:19', '2026-07-03 14:36:19'),
(8, 10, '2027 election', 'presidential', '2031', '2026-07-29', '16:29:00', '11:23:00', NULL, NULL, NULL, NULL, 'upcoming', '', NULL, NULL, 8, '2026-07-02 21:33:09', '2026-07-03 00:40:27', '2026-07-03 00:40:27'),
(9, 10, '2027 election (Copy)', 'presidential', '2031', '2026-07-29', '16:29:00', '11:23:00', NULL, NULL, NULL, NULL, 'draft', '', NULL, NULL, 9, '2026-07-03 01:32:30', '2026-07-03 01:32:37', '2026-07-03 01:32:37'),
(10, 10, '2027 election', 'house_of_assembly', '2031', '2026-08-12', '23:20:00', '16:19:00', '[\"41\"]', NULL, NULL, '[]', 'upcoming', '', NULL, NULL, 9, '2026-07-03 15:14:26', '2026-07-03 18:49:53', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `election_materials`
--

CREATE TABLE `election_materials` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `election_id` bigint(20) UNSIGNED NOT NULL,
  `pu_id` int(10) UNSIGNED NOT NULL,
  `agent_id` bigint(20) UNSIGNED NOT NULL,
  `material_type` enum('ballot_papers','result_sheets','stamp','ink','bvas','generator','tent','chairs','tables','other') NOT NULL,
  `quantity_received` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `quantity_used` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `quantity_damaged` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `quantity_returned` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `condition` enum('excellent','good','fair','poor','missing') DEFAULT NULL,
  `photo_url` varchar(500) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `budget_id` bigint(20) UNSIGNED NOT NULL,
  `election_id` bigint(20) UNSIGNED DEFAULT NULL,
  `category` enum('agent_payment','transport','materials','logistics','security','communication','media','other') NOT NULL,
  `description` text NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `receipt_url` varchar(500) DEFAULT NULL,
  `paid_to_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `paid_by_user_id` bigint(20) UNSIGNED NOT NULL,
  `payment_method` enum('cash','bank_transfer','mobile_money','cheque','other') NOT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `status` enum('pending','approved','rejected','paid') NOT NULL DEFAULT 'pending',
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `gps_lat` decimal(10,8) DEFAULT NULL,
  `gps_lng` decimal(11,8) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `federal_constituencies`
--

CREATE TABLE `federal_constituencies` (
  `id` int(10) UNSIGNED NOT NULL,
  `state_id` int(10) UNSIGNED NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `lgas_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`lgas_json`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `incidents`
--

CREATE TABLE `incidents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `election_id` bigint(20) UNSIGNED DEFAULT NULL,
  `reporter_id` bigint(20) UNSIGNED NOT NULL,
  `pu_id` int(10) UNSIGNED DEFAULT NULL,
  `ward_id` int(10) UNSIGNED DEFAULT NULL,
  `lga_id` int(10) UNSIGNED DEFAULT NULL,
  `state_id` int(10) UNSIGNED DEFAULT NULL,
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
  `assigned_to` bigint(20) UNSIGNED DEFAULT NULL,
  `resolved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `is_offline_sync` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `subscription_id` bigint(20) UNSIGNED DEFAULT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `tax_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL,
  `status` enum('draft','sent','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
  `due_date` date NOT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `paid_by` bigint(20) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `tenant_id`, `subscription_id`, `invoice_number`, `amount`, `tax_amount`, `total_amount`, `status`, `due_date`, `paid_at`, `paid_by`, `notes`, `created_at`) VALUES
(1, 10, 4, 'INV-2026-2181', 10000.00, 10.00, 10010.00, 'paid', '2026-08-01', '2026-07-02 22:39:02', NULL, '', '2026-07-02 22:02:10'),
(2, 10, NULL, 'INV-2026-33945', 10000.00, 0.00, 10000.00, 'paid', '2026-08-01', '2026-07-02 22:30:18', NULL, '', '2026-07-02 22:29:49');

-- --------------------------------------------------------

--
-- Table structure for table `lgas`
--

CREATE TABLE `lgas` (
  `id` int(10) UNSIGNED NOT NULL,
  `state_id` int(10) UNSIGNED NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `gps_lat` decimal(10,8) DEFAULT NULL,
  `gps_lng` decimal(11,8) DEFAULT NULL,
  `registered_voters` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lgas`
--

INSERT INTO `lgas` (`id`, `state_id`, `code`, `name`, `gps_lat`, `gps_lng`, `registered_voters`, `is_active`) VALUES
(792, 41, 'RN', 'Rano', NULL, NULL, 5677772, 1);

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `attempt_type` varchar(50) NOT NULL DEFAULT 'login',
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `user_id`, `email`, `ip_address`, `user_agent`, `attempt_type`, `success`, `created_at`) VALUES
(1, NULL, 'aliyuabubakar11117@gmail', '::1', NULL, 'login', 0, '2026-07-01 22:27:24'),
(2, NULL, 'admin@5gguru.ng', '::1', NULL, 'login', 0, '2026-07-01 22:27:50'),
(5, NULL, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 0, '2026-07-01 22:58:27'),
(6, 2, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-01 23:01:44'),
(7, 2, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-01 23:24:33'),
(8, NULL, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 0, '2026-07-01 23:29:11'),
(9, NULL, 'aliyu@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 0, '2026-07-01 23:29:18'),
(10, NULL, 'aliyu@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 0, '2026-07-01 23:29:22'),
(11, 2, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-01 23:36:06'),
(12, 2, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-01 23:41:13'),
(13, NULL, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 0, '2026-07-01 23:48:39'),
(14, 2, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-01 23:49:09'),
(15, 2, 'aliyuabubakar11117@gmail.com', '10.180.98.13', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', 'login', 1, '2026-07-02 00:30:15'),
(16, 2, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-02 01:24:48'),
(17, 2, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-02 04:52:20'),
(18, NULL, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 0, '2026-07-02 05:52:32'),
(19, 2, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-02 05:53:53'),
(20, NULL, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 0, '2026-07-02 17:16:25'),
(21, NULL, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 0, '2026-07-02 17:16:33'),
(22, NULL, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 0, '2026-07-02 17:23:50'),
(23, 7, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-02 17:24:47'),
(24, 7, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-02 17:34:32'),
(25, 7, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-02 17:35:39'),
(26, 7, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-02 17:36:17'),
(27, 7, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-02 17:38:33'),
(28, 7, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-02 17:44:55'),
(29, 7, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-02 17:45:14'),
(30, 7, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-02 17:46:14'),
(31, NULL, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 0, '2026-07-02 17:54:47'),
(32, 8, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-02 17:56:26'),
(33, NULL, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 0, '2026-07-02 19:19:51'),
(34, 8, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-02 19:21:30'),
(35, 7, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-02 19:23:57'),
(36, 8, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-02 19:48:46'),
(37, 7, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-02 20:24:13'),
(38, 9, 'aliyuabubakar1117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-02 21:16:30'),
(39, 8, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-02 21:16:44'),
(40, 8, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-02 21:24:48'),
(41, 7, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-02 23:28:20'),
(42, 7, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-03 00:20:39'),
(43, 7, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-03 00:25:49'),
(44, 9, 'aliyuabubakar1117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-03 00:48:12'),
(45, 9, 'aliyuabubakar1117@gmail.com', '::1', 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', 'login', 1, '2026-07-03 01:24:27'),
(46, 7, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-03 13:57:24'),
(47, 8, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-03 13:57:37'),
(48, 9, 'aliyuabubakar1117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-03 13:57:50'),
(49, NULL, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 0, '2026-07-03 18:46:15'),
(50, NULL, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 0, '2026-07-03 18:46:26'),
(51, 8, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-03 18:46:41'),
(52, 9, 'aliyuabubakar1117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-03 18:48:34'),
(53, NULL, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 0, '2026-07-03 22:46:21'),
(54, 7, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-03 22:46:29'),
(55, 12, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-03 22:51:54'),
(56, 12, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-03 22:55:39'),
(57, 12, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-03 22:56:24'),
(58, NULL, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 0, '2026-07-03 23:05:42'),
(59, 12, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-03 23:06:19'),
(60, 12, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-03 23:18:52'),
(61, 12, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-03 23:19:37'),
(62, 7, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-03 23:24:07'),
(63, 12, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-03 23:30:32'),
(64, 12, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-03 23:31:59'),
(65, 12, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-03 23:37:13'),
(66, 12, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-03 23:40:08'),
(67, 12, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-03 23:40:41'),
(68, 12, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-03 23:40:51'),
(69, 12, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-03 23:41:11'),
(70, 12, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-03 23:52:15'),
(71, 12, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-03 23:53:04'),
(72, 12, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-04 00:18:18'),
(73, 12, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-04 00:24:36'),
(74, 12, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-04 00:25:40'),
(75, 12, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-04 00:27:21'),
(76, 12, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-04 00:29:31'),
(77, 12, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-04 00:30:46'),
(78, 12, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-04 00:31:28'),
(79, 12, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-04 00:33:05'),
(80, 12, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-04 00:39:59'),
(81, 12, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-05 09:01:48'),
(82, 12, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-05 09:04:33'),
(83, 12, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-05 09:05:01');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `type` enum('system','election','result','incident','chat','broadcast','payment','security') NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `data_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data_json`)),
  `action_url` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `offline_sync_queue`
--

CREATE TABLE `offline_sync_queue` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `device_id` varchar(255) NOT NULL,
  `data_type` enum('ec8a','incident','checkin','media','chat','profile_update') NOT NULL,
  `priority` tinyint(3) UNSIGNED NOT NULL DEFAULT 5,
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload_json`)),
  `file_path` varchar(500) DEFAULT NULL,
  `file_size` bigint(20) UNSIGNED DEFAULT NULL,
  `file_sha256` varchar(64) DEFAULT NULL,
  `status` enum('queued','syncing','completed','failed','retrying') NOT NULL DEFAULT 'queued',
  `retry_count` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `max_retries` tinyint(3) UNSIGNED NOT NULL DEFAULT 5,
  `last_error` text DEFAULT NULL,
  `synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `otp_verifications`
--

CREATE TABLE `otp_verifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `otp_code` varchar(10) NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'login',
  `channel` varchar(20) NOT NULL DEFAULT 'email',
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `used_at` timestamp NULL DEFAULT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `otp_verifications`
--

INSERT INTO `otp_verifications` (`id`, `user_id`, `otp_code`, `type`, `channel`, `expires_at`, `used`, `used_at`, `attempts`, `created_at`) VALUES
(1, 2, '746440', '2fa_enable', 'email', '2026-07-01 23:37:05', 1, '2026-07-01 23:37:05', 0, '2026-07-01 23:36:30'),
(2, 2, '044659', 'login', 'email', '2026-07-01 23:41:12', 1, '2026-07-01 23:41:12', 0, '2026-07-01 23:40:44'),
(3, 2, '682700', 'login', 'email', '2026-07-01 23:49:09', 1, '2026-07-01 23:49:09', 0, '2026-07-01 23:48:51'),
(4, 2, '227687', 'login', 'email', '2026-07-02 00:30:15', 1, '2026-07-02 00:30:15', 0, '2026-07-02 00:29:36'),
(5, 2, '738881', 'login', 'email', '2026-07-02 01:24:48', 1, '2026-07-02 01:24:48', 0, '2026-07-02 01:24:24'),
(6, 2, '680586', 'login', 'email', '2026-07-02 04:52:20', 1, '2026-07-02 04:52:20', 0, '2026-07-02 04:51:58'),
(7, 2, '998814', 'login', 'email', '2026-07-02 05:53:53', 1, '2026-07-02 05:53:53', 0, '2026-07-02 05:53:35'),
(8, 7, '334823', 'login', 'email', '2026-07-02 17:46:14', 1, '2026-07-02 17:46:14', 0, '2026-07-02 17:45:55'),
(9, 7, '419659', 'login', 'email', '2026-07-02 19:23:57', 1, '2026-07-02 19:23:57', 0, '2026-07-02 19:23:24'),
(10, 7, '407600', 'login', 'email', '2026-07-02 20:24:13', 1, '2026-07-02 20:24:13', 0, '2026-07-02 20:23:21'),
(11, 12, '885678', 'login', 'email', '2026-07-03 22:51:54', 1, '2026-07-03 22:51:54', 0, '2026-07-03 22:51:33'),
(12, 12, '425184', 'login', 'email', '2026-07-03 22:55:39', 1, '2026-07-03 22:55:39', 0, '2026-07-03 22:55:23'),
(13, 12, '185519', 'login', 'email', '2026-07-03 22:56:24', 1, '2026-07-03 22:56:24', 0, '2026-07-03 22:56:10'),
(14, 12, '616687', 'login', 'email', '2026-07-03 23:06:19', 1, '2026-07-03 23:06:19', 0, '2026-07-03 23:05:55');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `token`, `expires_at`, `used`, `used_at`, `created_at`) VALUES
(3, 2, 'afde518473b108c9c1cbe54043fb05c0778a573d5079b2702469f4b4dbcd868a', '2026-07-02 05:53:15', 1, '2026-07-02 05:53:15', '2026-07-02 05:52:47'),
(5, 8, '4d0f11ddf15291017b335a2559ec51bef996e98260b651ba6077577a65199409', '2026-07-02 17:56:03', 1, '2026-07-02 17:56:03', '2026-07-02 17:54:55'),
(6, 7, '4ac233c9079f1c39cb7503188344cc4037799ed669b8362ad271147b5b10e4c8', '2026-07-02 19:21:18', 1, '2026-07-02 19:21:18', '2026-07-02 19:20:01');

-- --------------------------------------------------------

--
-- Table structure for table `people`
--

CREATE TABLE `people` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `full_name` varchar(200) GENERATED ALWAYS AS (concat(`first_name`,' ',`last_name`)) STORED,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `gender` enum('male','female','other','prefer_not_say') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `category` enum('party_member','volunteer','voter','stakeholder','community_leader','traditional_leader','religious_leader','influencer','other') NOT NULL,
  `state_id` int(10) UNSIGNED DEFAULT NULL,
  `lga_id` int(10) UNSIGNED DEFAULT NULL,
  `ward_id` int(10) UNSIGNED DEFAULT NULL,
  `pu_id` int(10) UNSIGNED DEFAULT NULL,
  `address` text DEFAULT NULL,
  `nin` varchar(20) DEFAULT NULL,
  `voter_id` varchar(50) DEFAULT NULL,
  `social_media_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`social_media_json`)),
  `notes` text DEFAULT NULL,
  `tags_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags_json`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `module` varchar(50) NOT NULL,
  `action` varchar(50) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `political_parties`
--

CREATE TABLE `political_parties` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `political_parties`
--

INSERT INTO `political_parties` (`id`, `tenant_id`, `name`, `acronym`, `logo_url`, `chairman_name`, `secretary_name`, `contact_email`, `contact_phone`, `website`, `social_media_json`, `state_offices_json`, `is_active`, `created_at`) VALUES
(1, 10, 'Aliyu Abubakar', 'APC', '/uploads/parties/6a4819d4ce60d_download.jpg', '', '', 'aliyuabubakar11117@gmail.com', '+2348034897634', '', '{\"facebook\":\"\",\"twitter\":\"\",\"instagram\":\"\",\"linkedin\":\"\",\"youtube\":\"\"}', '[]', 1, '2026-07-03 20:21:40');

-- --------------------------------------------------------

--
-- Table structure for table `polling_units`
--

CREATE TABLE `polling_units` (
  `id` int(10) UNSIGNED NOT NULL,
  `ward_id` int(10) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `gps_lat` decimal(10,8) DEFAULT NULL,
  `gps_lng` decimal(11,8) DEFAULT NULL,
  `gps_accuracy` decimal(6,2) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `registered_voters` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `accredited_voters` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `is_rural` tinyint(1) NOT NULL DEFAULT 0,
  `network_quality` enum('2g','3g','4g','5g','none') DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `polling_units`
--

INSERT INTO `polling_units` (`id`, `ward_id`, `code`, `name`, `description`, `gps_lat`, `gps_lng`, `gps_accuracy`, `address`, `registered_voters`, `accredited_voters`, `is_rural`, `network_quality`, `is_active`, `created_at`) VALUES
(1, 1, 'RN', 'APC', 'kabsnxns', NULL, NULL, NULL, '', 6543, 0, 1, '5g', 1, '2026-07-03 14:52:39');

-- --------------------------------------------------------

--
-- Table structure for table `public_results`
--

CREATE TABLE `public_results` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `election_id` bigint(20) UNSIGNED NOT NULL,
  `pu_id` int(10) UNSIGNED DEFAULT NULL,
  `ward_id` int(10) UNSIGNED DEFAULT NULL,
  `lga_id` int(10) UNSIGNED DEFAULT NULL,
  `state_id` int(10) UNSIGNED DEFAULT NULL,
  `level` enum('pu','ward','lga','state','national') NOT NULL,
  `party_votes_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`party_votes_json`)),
  `valid_votes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `rejected_votes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_votes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `turnout_percentage` decimal(5,2) DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT 0,
  `published_at` timestamp NULL DEFAULT NULL,
  `published_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `election_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('turnout','results','incidents','agents','financial','performance','custom') NOT NULL,
  `format` enum('pdf','excel','csv','json','html') NOT NULL DEFAULT 'pdf',
  `filters_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`filters_json`)),
  `file_url` varchar(500) DEFAULT NULL,
  `file_size` bigint(20) UNSIGNED DEFAULT NULL,
  `generated_by` bigint(20) UNSIGNED NOT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_scheduled` tinyint(1) NOT NULL DEFAULT 0,
  `schedule_cron` varchar(50) DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `public_slug` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `results_ec8a`
--

CREATE TABLE `results_ec8a` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `election_id` bigint(20) UNSIGNED NOT NULL,
  `pu_id` int(10) UNSIGNED NOT NULL,
  `ward_id` int(10) UNSIGNED NOT NULL,
  `lga_id` int(10) UNSIGNED NOT NULL,
  `state_id` int(10) UNSIGNED NOT NULL,
  `agent_id` bigint(20) UNSIGNED NOT NULL,
  `assignment_id` bigint(20) UNSIGNED NOT NULL,
  `pu_code` varchar(50) NOT NULL,
  `pu_name` varchar(255) NOT NULL,
  `registered_voters` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `accredited_voters` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `ballot_papers_issued` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `unused_ballots` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `spoiled_ballots` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `rejected_votes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `valid_votes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_votes_cast` int(10) UNSIGNED NOT NULL DEFAULT 0,
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
  `verified_by` bigint(20) UNSIGNED DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `is_offline_sync` tinyint(1) NOT NULL DEFAULT 0,
  `offline_created_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `results_ec8b`
--

CREATE TABLE `results_ec8b` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `election_id` bigint(20) UNSIGNED NOT NULL,
  `ward_id` int(10) UNSIGNED NOT NULL,
  `lga_id` int(10) UNSIGNED NOT NULL,
  `state_id` int(10) UNSIGNED NOT NULL,
  `coordinator_id` bigint(20) UNSIGNED NOT NULL,
  `party_votes_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`party_votes_json`)),
  `valid_votes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `rejected_votes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_votes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `calculated_total_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`calculated_total_json`)),
  `mismatch_alert` tinyint(1) NOT NULL DEFAULT 0,
  `mismatch_details_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`mismatch_details_json`)),
  `form_photo_url` varchar(500) DEFAULT NULL,
  `form_sha256` varchar(64) DEFAULT NULL,
  `status` enum('pending','verified','rejected','flagged') NOT NULL DEFAULT 'pending',
  `verified_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `results_ec8c`
--

CREATE TABLE `results_ec8c` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `election_id` bigint(20) UNSIGNED NOT NULL,
  `lga_id` int(10) UNSIGNED NOT NULL,
  `state_id` int(10) UNSIGNED NOT NULL,
  `coordinator_id` bigint(20) UNSIGNED NOT NULL,
  `party_votes_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`party_votes_json`)),
  `valid_votes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `rejected_votes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_votes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `calculated_total_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`calculated_total_json`)),
  `mismatch_alert` tinyint(1) NOT NULL DEFAULT 0,
  `mismatch_details_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`mismatch_details_json`)),
  `form_photo_url` varchar(500) DEFAULT NULL,
  `form_sha256` varchar(64) DEFAULT NULL,
  `status` enum('pending','verified','rejected','flagged') NOT NULL DEFAULT 'pending',
  `verified_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `results_ec8d`
--

CREATE TABLE `results_ec8d` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `election_id` bigint(20) UNSIGNED NOT NULL,
  `state_id` int(10) UNSIGNED NOT NULL,
  `coordinator_id` bigint(20) UNSIGNED NOT NULL,
  `party_votes_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`party_votes_json`)),
  `valid_votes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `rejected_votes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_votes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `calculated_total_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`calculated_total_json`)),
  `mismatch_alert` tinyint(1) NOT NULL DEFAULT 0,
  `mismatch_details_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`mismatch_details_json`)),
  `form_photo_url` varchar(500) DEFAULT NULL,
  `form_sha256` varchar(64) DEFAULT NULL,
  `status` enum('pending','verified','rejected','flagged') NOT NULL DEFAULT 'pending',
  `verified_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `results_ec8e`
--

CREATE TABLE `results_ec8e` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `election_id` bigint(20) UNSIGNED NOT NULL,
  `coordinator_id` bigint(20) UNSIGNED NOT NULL,
  `party_votes_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`party_votes_json`)),
  `valid_votes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `rejected_votes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_votes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `calculated_total_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`calculated_total_json`)),
  `mismatch_alert` tinyint(1) NOT NULL DEFAULT 0,
  `mismatch_details_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`mismatch_details_json`)),
  `form_photo_url` varchar(500) DEFAULT NULL,
  `form_sha256` varchar(64) DEFAULT NULL,
  `declaration_time` timestamp NULL DEFAULT NULL,
  `status` enum('pending','verified','declared','rejected','flagged') NOT NULL DEFAULT 'pending',
  `verified_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `level` enum('super_admin','client_admin','national','state','senatorial','federal_constituency','lga','ward','pu_agent','party_agent','volunteer','observer','situation_room','finance_officer','citizen') NOT NULL,
  `description` text DEFAULT NULL,
  `permissions_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`permissions_json`)),
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `tenant_id`, `name`, `slug`, `level`, `description`, `permissions_json`, `is_system`, `is_active`, `created_at`, `updated_at`) VALUES
(1, NULL, 'Super Administrator', 'super_admin', 'super_admin', 'Full system access with all permissions', '{\"manage_tenants\":\"1\",\"manage_users\":\"1\",\"manage_elections\":\"1\",\"view_results\":\"1\",\"manage_agents\":\"1\",\"manage_roles\":\"1\",\"view_audit_logs\":\"1\",\"manage_subscriptions\":\"1\",\"manage_billing\":\"1\",\"manage_inec_data\":\"1\",\"view_reports\":\"1\",\"manage_settings\":\"1\",\"manage_backups\":\"1\",\"view_security\":\"1\",\"manage_api\":\"1\",\"manage_support\":\"1\"}', 1, 1, '2026-07-03 23:29:50', '2026-07-03 23:29:50'),
(2, NULL, 'Client Administrator', 'client_admin', 'client_admin', 'Full access to manage a specific tenant', '{\"all\": true}', 1, 1, '2026-07-03 23:29:50', '2026-07-03 23:29:50'),
(3, NULL, 'National Coordinator', 'national', 'national', 'Coordinates election activities nationwide', '{\"manage_elections\": true, \"view_all_results\": true, \"manage_broadcasts\": true, \"view_reports\": true, \"manage_incidents\": true, \"view_analytics\": true}', 1, 1, '2026-07-03 23:29:50', '2026-07-03 23:29:50'),
(4, NULL, 'State Coordinator', 'state', 'state', 'Coordinates election activities within a state', '{\"manage_state_elections\": true, \"view_state_results\": true, \"manage_lga_coordinators\": true, \"manage_broadcasts\": true, \"view_reports\": true, \"manage_incidents\": true, \"verify_results\": true}', 1, 1, '2026-07-03 23:29:50', '2026-07-03 23:29:50'),
(5, NULL, 'Senatorial Coordinator', 'senatorial', 'senatorial', 'Coordinates election activities within a senatorial district', '{\"view_senatorial_results\": true, \"manage_broadcasts\": true, \"view_reports\": true, \"view_analytics\": true, \"monitor_district\": true}', 1, 1, '2026-07-03 23:29:50', '2026-07-03 23:29:50'),
(6, NULL, 'Federal Constituency Coordinator', 'federal_constituency', 'federal_constituency', 'Coordinates election activities within a federal constituency', '{\"view_constituency_results\": true, \"manage_broadcasts\": true, \"view_reports\": true, \"verify_results\": true, \"monitor_constituency\": true}', 1, 1, '2026-07-03 23:29:50', '2026-07-03 23:29:50'),
(7, NULL, 'LGA Coordinator', 'lga', 'lga', 'Coordinates election activities within a Local Government Area', '{\"manage_lga_elections\": true, \"view_lga_results\": true, \"manage_wards\": true, \"approve_results\": true, \"manage_broadcasts\": true, \"view_reports\": true, \"monitor_incidents\": true}', 1, 1, '2026-07-03 23:29:50', '2026-07-03 23:29:50'),
(8, NULL, 'Ward Coordinator', 'ward', 'ward', 'Supervises Polling Unit Agents within a Ward', '{\"manage_ward_elections\": true, \"view_ward_results\": true, \"manage_pu_agents\": true, \"upload_ec8b\": true, \"manage_broadcasts\": true, \"view_reports\": true, \"manage_incidents\": true}', 1, 1, '2026-07-03 23:29:50', '2026-07-03 23:29:50'),
(9, NULL, 'Polling Unit Agent', 'pu_agent', 'pu_agent', 'Submits results from a polling unit', '{\"submit_results\": true, \"view_pu_results\": true, \"report_incidents\": true, \"checkin\": true}', 1, 1, '2026-07-03 23:29:50', '2026-07-03 23:29:50'),
(10, NULL, 'Party Agent', 'party_agent', 'party_agent', 'Monitors results on behalf of a political party', '{\"monitor_results\": true, \"view_party_results\": true, \"report_incidents\": true}', 1, 1, '2026-07-03 23:29:50', '2026-07-03 23:29:50'),
(11, NULL, 'Observer', 'observer', 'observer', 'Observes election process', '{\"view_results\": true, \"report_incidents\": true}', 1, 1, '2026-07-03 23:29:50', '2026-07-03 23:29:50'),
(12, NULL, 'Situation Room', 'situation_room', 'situation_room', 'Manages election situation room operations', '{\"view_all_results\": true, \"manage_incidents\": true, \"monitor_elections\": true, \"view_reports\": true}', 1, 1, '2026-07-03 23:29:50', '2026-07-03 23:29:50'),
(13, NULL, 'Finance Officer', 'finance_officer', 'finance_officer', 'Manages financial aspects of election activities', '{\"manage_budgets\": true, \"manage_expenses\": true, \"manage_payments\": true, \"view_financial_reports\": true}', 1, 1, '2026-07-03 23:29:50', '2026-07-03 23:29:50'),
(14, NULL, 'Citizen', 'citizen', 'citizen', 'Regular citizen with view-only access', '{\"view_public_results\": true, \"report_incidents\": true}', 1, 1, '2026-07-03 23:29:50', '2026-07-03 23:29:50');

-- --------------------------------------------------------

--
-- Table structure for table `role_id_mapping`
--

CREATE TABLE `role_id_mapping` (
  `old_id` bigint(20) UNSIGNED DEFAULT NULL,
  `new_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `role_id_mapping`
--

INSERT INTO `role_id_mapping` (`old_id`, `new_id`) VALUES
(1, 1),
(2, 2),
(3, 3),
(4, 4),
(5, 7),
(6, 8),
(7, 9),
(8, 10),
(9, 11),
(10, 1),
(11, 2),
(12, 3),
(13, 4),
(14, 7),
(15, 9),
(16, 1),
(17, 2),
(18, 3),
(19, 4),
(20, 7),
(21, 1);

-- --------------------------------------------------------

--
-- Table structure for table `security_events`
--

CREATE TABLE `security_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `event_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `device_id` varchar(255) DEFAULT NULL,
  `gps_lat` decimal(10,8) DEFAULT NULL,
  `gps_lng` decimal(11,8) DEFAULT NULL,
  `risk_score` tinyint(3) UNSIGNED DEFAULT NULL,
  `resolved` tinyint(1) NOT NULL DEFAULT 0,
  `resolved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `security_events`
--

INSERT INTO `security_events` (`id`, `tenant_id`, `user_id`, `event_type`, `description`, `ip_address`, `device_id`, `gps_lat`, `gps_lng`, `risk_score`, `resolved`, `resolved_by`, `resolved_at`, `created_at`) VALUES
(1, NULL, 2, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-01 23:06:54'),
(2, NULL, 2, 'password_reset', 'Password reset requested from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-01 23:23:05'),
(3, NULL, 2, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-01 23:24:33'),
(4, NULL, 2, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-01 23:28:55'),
(5, NULL, 2, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-01 23:36:06'),
(6, NULL, 2, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-01 23:40:33'),
(7, NULL, 2, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-01 23:41:13'),
(8, NULL, 2, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-01 23:49:09'),
(9, NULL, 2, 'login', 'Successful login from IP: 10.180.98.13', '10.180.98.13', 'b82256a7d530eb3d10525750af312b15891c852c68fb2b9a6a14508a2782f844', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 00:30:15'),
(10, NULL, 2, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 01:24:48'),
(11, NULL, 2, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 04:52:21'),
(12, NULL, 2, 'password_reset', 'Password reset requested from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 05:52:52'),
(13, NULL, 2, 'password_change', 'Password reset completed from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 05:53:15'),
(14, NULL, 2, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 05:53:53'),
(15, NULL, 7, 'password_reset', 'Password reset requested from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 17:24:18'),
(16, NULL, 7, 'password_change', 'Password reset completed from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 17:24:39'),
(17, NULL, 7, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 17:24:47'),
(18, NULL, 7, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 17:34:23'),
(19, NULL, 7, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 17:34:32'),
(20, NULL, 7, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 17:35:33'),
(21, NULL, 7, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 17:35:39'),
(22, NULL, 7, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 17:35:45'),
(23, NULL, 7, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 17:36:18'),
(24, NULL, 7, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 17:38:27'),
(25, NULL, 7, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 17:38:33'),
(26, NULL, 7, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 17:44:47'),
(27, NULL, 7, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 17:44:55'),
(28, NULL, 7, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 17:45:09'),
(29, NULL, 7, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 17:45:14'),
(30, NULL, 7, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 17:45:20'),
(31, NULL, 7, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 17:46:14'),
(32, NULL, 7, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 17:54:35'),
(33, NULL, 8, 'password_reset', 'Password reset requested from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 17:55:01'),
(34, NULL, 8, 'password_change', 'Password reset completed from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 17:56:03'),
(35, NULL, 8, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 17:56:27'),
(36, NULL, 7, 'password_reset', 'Password reset requested from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 19:20:06'),
(37, NULL, 7, 'password_change', 'Password reset completed from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 19:21:18'),
(38, NULL, 8, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 19:21:30'),
(39, NULL, 7, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 19:23:57'),
(40, NULL, 8, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 19:48:40'),
(41, NULL, 8, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 19:48:47'),
(42, NULL, 7, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 20:23:14'),
(43, NULL, 7, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 20:24:13'),
(44, NULL, 7, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 21:16:23'),
(45, NULL, 9, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 21:16:30'),
(46, NULL, 9, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 21:16:33'),
(47, NULL, 8, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 21:16:44'),
(48, NULL, 8, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 21:24:48'),
(49, NULL, 8, 'password_change', 'Password changed from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 23:20:11'),
(50, NULL, 8, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 23:28:14'),
(51, NULL, 7, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-02 23:28:20'),
(52, NULL, 7, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 00:20:32'),
(53, NULL, 7, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 00:20:39'),
(54, NULL, 7, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 00:25:34'),
(55, NULL, 7, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 00:25:49'),
(56, NULL, 7, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 00:48:05'),
(57, NULL, 9, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 00:48:12'),
(58, NULL, 9, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 01:23:50'),
(59, NULL, 9, 'login', 'Successful login from IP: ::1', '::1', '346cfda3700772c83bfaed0b20e27c541cda69d3bc68d306f79f9e7a3a3df763', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 01:24:27'),
(60, NULL, 7, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 13:57:24'),
(61, NULL, 7, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 13:57:28'),
(62, NULL, 8, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 13:57:37'),
(63, NULL, 8, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 13:57:40'),
(64, NULL, 9, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 13:57:50'),
(65, NULL, 9, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 18:45:44'),
(66, NULL, 8, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 18:46:41'),
(67, NULL, 8, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 18:48:19'),
(68, NULL, 9, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 18:48:34'),
(69, NULL, 9, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 22:46:14'),
(70, NULL, 7, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 22:46:29'),
(71, NULL, 7, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 22:49:51'),
(72, NULL, 12, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 22:51:54'),
(73, NULL, 12, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 22:55:16'),
(74, NULL, 12, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 22:55:40'),
(75, NULL, 12, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 22:55:40'),
(76, NULL, 12, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 22:56:24'),
(77, NULL, 12, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:05:29'),
(78, NULL, 12, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:06:19'),
(79, NULL, 12, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:18:20'),
(80, NULL, 12, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:18:52'),
(81, NULL, 12, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:18:52'),
(82, NULL, 12, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:19:37'),
(83, NULL, 12, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:23:53'),
(84, NULL, 7, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:24:07'),
(85, NULL, 7, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:30:25'),
(86, NULL, 12, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:30:33'),
(87, NULL, 12, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:30:33'),
(88, NULL, 12, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:31:59'),
(89, NULL, 12, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:31:59'),
(90, NULL, 12, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:37:13'),
(91, NULL, 12, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:37:13'),
(92, NULL, 12, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:40:08'),
(93, NULL, 12, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:40:08'),
(94, NULL, 12, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:40:41'),
(95, NULL, 12, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:40:41'),
(96, NULL, 12, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:40:51'),
(97, NULL, 12, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:40:51'),
(98, NULL, 12, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:41:11'),
(99, NULL, 12, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:52:07'),
(100, NULL, 12, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:52:16'),
(101, NULL, 12, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:52:56'),
(102, NULL, 12, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:53:04'),
(103, NULL, 12, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:18:01'),
(104, NULL, 12, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:18:18'),
(105, NULL, 12, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:24:29'),
(106, NULL, 12, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:24:36'),
(107, NULL, 12, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:25:34'),
(108, NULL, 12, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:25:40'),
(109, NULL, 12, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:26:53'),
(110, NULL, 12, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:27:21'),
(111, NULL, 12, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:29:07'),
(112, NULL, 12, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:29:31'),
(113, NULL, 12, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:30:25'),
(114, NULL, 12, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:30:46'),
(115, NULL, 12, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:31:21'),
(116, NULL, 12, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:31:28'),
(117, NULL, 12, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:32:57'),
(118, NULL, 12, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:33:05'),
(119, NULL, 12, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:39:41'),
(120, NULL, 12, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:39:59'),
(121, NULL, 12, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 01:04:40'),
(122, NULL, 12, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:01:49'),
(123, NULL, 12, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:04:18'),
(124, NULL, 12, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:04:33'),
(125, NULL, 12, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:04:52'),
(126, NULL, 12, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:05:01');

-- --------------------------------------------------------

--
-- Table structure for table `senatorial_districts`
--

CREATE TABLE `senatorial_districts` (
  `id` int(10) UNSIGNED NOT NULL,
  `state_id` int(10) UNSIGNED NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `lgas_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`lgas_json`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `states`
--

CREATE TABLE `states` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(10) NOT NULL,
  `name` varchar(100) NOT NULL,
  `capital` varchar(100) DEFAULT NULL,
  `gps_lat` decimal(10,8) DEFAULT NULL,
  `gps_lng` decimal(11,8) DEFAULT NULL,
  `registered_voters` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `states`
--

INSERT INTO `states` (`id`, `code`, `name`, `capital`, `gps_lat`, `gps_lng`, `registered_voters`, `is_active`, `created_at`) VALUES
(41, '1222', 'Kano', 'Kano', NULL, NULL, 133234, 1, '2026-07-03 14:21:18');

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subscriptions`
--

INSERT INTO `subscriptions` (`id`, `tenant_id`, `plan`, `billing_cycle`, `amount`, `currency`, `start_date`, `end_date`, `auto_renew`, `payment_status`, `payment_method`, `transaction_reference`, `invoice_url`, `created_at`, `updated_at`) VALUES
(4, 10, 'standard', 'yearly', 10000.00, 'NGN', '2026-07-02', '2027-07-02', 1, 'paid', 'cash', '', '', '2026-07-02 21:36:56', '2026-07-02 21:36:56');

-- --------------------------------------------------------

--
-- Table structure for table `subscription_plans`
--

CREATE TABLE `subscription_plans` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `duration_days` int(11) NOT NULL DEFAULT 30,
  `user_limit` int(11) NOT NULL DEFAULT 100,
  `storage_limit_mb` int(11) NOT NULL DEFAULT 10240,
  `features` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

CREATE TABLE `support_tickets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `ticket_number` varchar(50) NOT NULL,
  `category` enum('technical','billing','feature_request','bug_report','account','security','other') NOT NULL,
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `subject` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `status` enum('open','in_progress','waiting','resolved','closed','escalated') NOT NULL DEFAULT 'open',
  `assigned_to` bigint(20) UNSIGNED DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `support_tickets`
--

INSERT INTO `support_tickets` (`id`, `tenant_id`, `user_id`, `ticket_number`, `category`, `priority`, `subject`, `description`, `status`, `assigned_to`, `resolved_at`, `created_at`, `updated_at`) VALUES
(1, 10, 7, 'TKT-2026-83269', 'billing', 'medium', 'Fee Payment Issue', 'by kowaguru', 'in_progress', 9, NULL, '2026-07-02 23:48:52', '2026-07-02 23:49:36');

-- --------------------------------------------------------

--
-- Table structure for table `support_ticket_replies`
--

CREATE TABLE `support_ticket_replies` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ticket_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `message` text NOT NULL,
  `attachment_urls_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachment_urls_json`)),
  `is_internal` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `support_ticket_replies`
--

INSERT INTO `support_ticket_replies` (`id`, `ticket_id`, `user_id`, `message`, `attachment_urls_json`, `is_internal`, `created_at`) VALUES
(1, 1, 7, 'okay', NULL, 1, '2026-07-02 23:49:16');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `key` varchar(100) NOT NULL,
  `value` text NOT NULL,
  `type` enum('string','integer','boolean','json','array') NOT NULL DEFAULT 'string',
  `description` text DEFAULT NULL,
  `is_editable` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `key`, `value`, `type`, `description`, `is_editable`, `created_at`, `updated_at`) VALUES
(1, 'site_name', '5G Election Guru', 'string', 'Site name', 1, '2026-07-01 22:27:19', '2026-07-02 07:19:17'),
(2, 'max_login_attempts', '10', 'integer', 'Maximum login attempts before lockout', 1, '2026-07-01 22:27:19', '2026-07-02 23:22:33'),
(3, 'lockout_duration', '5', 'integer', 'Lockout duration in minutes', 1, '2026-07-01 22:27:19', '2026-07-02 23:22:33'),
(4, 'session_timeout', '3600', 'integer', 'Session timeout in seconds', 1, '2026-07-01 22:27:19', '2026-07-02 07:19:17'),
(5, 'otp_expiry', '300', 'integer', 'OTP expiry in seconds', 1, '2026-07-01 22:27:19', '2026-07-02 07:19:17'),
(6, 'two_factor_enabled', 'true', 'boolean', 'Enable two-factor authentication', 1, '2026-07-01 22:27:19', '2026-07-02 07:19:17'),
(7, 'backup_schedule_type', 'database', 'string', NULL, 1, '2026-07-02 07:05:06', '2026-07-02 07:08:28'),
(8, 'backup_schedule_frequency', 'daily', 'string', NULL, 1, '2026-07-02 07:05:06', '2026-07-02 07:08:28'),
(11, 'site_url', 'http://localhost/election', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-02 07:19:17'),
(12, 'contact_email', 'admin@5gguru.ng', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-02 07:19:17'),
(13, 'contact_phone', '+2348005555555', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-02 07:19:17'),
(14, 'timezone', 'Africa/Lagos', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-02 07:19:17'),
(15, 'captcha_enabled', 'false', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-02 23:22:12'),
(16, 'password_min_length', '8', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-02 07:19:17'),
(17, 'smtp_host', 'smtp.gmail.com', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-02 07:19:17'),
(18, 'smtp_port', '587', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-02 07:19:17'),
(19, 'smtp_username', 'aliyuabubakarjdh@gmail.com', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-02 23:22:50'),
(20, 'smtp_password', 'crhebdkjibmmwyqs', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-02 23:22:50'),
(21, 'smtp_encryption', 'tls', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-02 07:19:17'),
(22, 'sender_name', '5G Election Guru', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-02 07:19:17'),
(23, 'sender_email', 'no-reply@5gguru.ng', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-02 07:19:17'),
(24, 'max_upload_size', '10', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-02 07:19:17'),
(25, 'allowed_file_types', 'jpg,jpeg,png,gif,svg,pdf,doc,docx,xls,xlsx,csv', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-02 07:19:17'),
(26, 'storage_path', '../uploads/', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-02 07:19:17'),
(27, 'auto_backup_enabled', 'true', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-02 07:19:17'),
(28, 'backup_frequency', 'daily', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-02 07:19:17'),
(29, 'backup_retention', '30', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-02 07:19:17'),
(30, 'backup_time', '02:00', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-02 07:19:17');

-- --------------------------------------------------------

--
-- Table structure for table `tenants`
--

CREATE TABLE `tenants` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(36) NOT NULL DEFAULT uuid(),
  `name` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `type` enum('political_party','candidate','ngo','observer_group','cso','research_institution') NOT NULL DEFAULT 'political_party',
  `subscription_plan` enum('free','basic','standard','premium','enterprise') NOT NULL DEFAULT 'basic',
  `subscription_status` enum('trial','active','suspended','expired','cancelled') NOT NULL DEFAULT 'trial',
  `subscription_start` date DEFAULT NULL,
  `subscription_end` date DEFAULT NULL,
  `max_users` int(10) UNSIGNED NOT NULL DEFAULT 100,
  `max_agents` int(10) UNSIGNED NOT NULL DEFAULT 500,
  `max_storage_mb` bigint(20) UNSIGNED NOT NULL DEFAULT 10737418240,
  `used_storage_mb` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `logo_url` varchar(500) DEFAULT NULL,
  `primary_color` varchar(7) DEFAULT '#3b82f6',
  `secondary_color` varchar(7) DEFAULT '#10b981',
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `state_id` int(10) UNSIGNED DEFAULT NULL,
  `lga_id` int(10) UNSIGNED DEFAULT NULL,
  `settings_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings_json`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tenants`
--

INSERT INTO `tenants` (`id`, `uuid`, `name`, `slug`, `type`, `subscription_plan`, `subscription_status`, `subscription_start`, `subscription_end`, `max_users`, `max_agents`, `max_storage_mb`, `used_storage_mb`, `logo_url`, `primary_color`, `secondary_color`, `contact_email`, `contact_phone`, `address`, `state_id`, `lga_id`, `settings_json`, `is_active`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(5, '6a45f1e381a2c-8908443da2c95b46', 'APC', 'apc', 'political_party', 'enterprise', 'trial', NULL, '2028-08-04', 100, 500, 10240, 0, '/uploads/tenants/tenant_1782968803_6a45f1e381678.jpg', '#3b82f6', '#10b981', 'aliyuabubakar11117@gmail.com', '+2348034897634', 'Kangire, Birninkudu\r\nNigeria', 18, 340, NULL, 1, 2, '2026-07-02 05:06:43', '2026-07-02 06:26:30', '2026-07-02 06:26:30'),
(10, '6a460ff9bc2d0-6459ddb4b82efea1', 'PDP', 'pdp', 'political_party', 'standard', 'active', '2026-07-02', '2027-07-02', 100, 500, 10240, 0, '/uploads/tenants/tenant_10_1783040787.jpeg', '#3b82f6', '#10b981', 'aliyuabubakar11117@gmail.com', '+2348034897634', 'Birnin kudu\r\nBirnin Kudu', 18, NULL, NULL, 1, 2, '2026-07-02 07:15:05', '2026-07-03 01:06:27', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tenant_settings`
--

CREATE TABLE `tenant_settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `key` varchar(100) NOT NULL,
  `value` text NOT NULL,
  `type` enum('string','integer','boolean','json','array') NOT NULL DEFAULT 'string'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_code` varchar(20) NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL,
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
  `state_id` int(10) UNSIGNED DEFAULT NULL,
  `lga_id` int(10) UNSIGNED DEFAULT NULL,
  `ward_id` int(10) UNSIGNED DEFAULT NULL,
  `pu_id` int(10) UNSIGNED DEFAULT NULL,
  `jurisdiction_type` enum('national','state','senatorial','federal_constituency','lga','ward','pu') DEFAULT NULL,
  `jurisdiction_id` bigint(20) UNSIGNED DEFAULT NULL,
  `device_id` varchar(255) DEFAULT NULL,
  `device_fingerprint` varchar(255) DEFAULT NULL,
  `device_bound` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `last_login_device` varchar(255) DEFAULT NULL,
  `last_login_gps_lat` decimal(10,8) DEFAULT NULL,
  `last_login_gps_lng` decimal(11,8) DEFAULT NULL,
  `login_attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `status` enum('active','suspended','pending','archived') NOT NULL DEFAULT 'pending',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `tenant_id`, `user_code`, `role_id`, `first_name`, `last_name`, `email`, `phone`, `phone_verified_at`, `password_hash`, `remember_token`, `two_factor_secret`, `two_factor_enabled`, `two_factor_verified_at`, `gender`, `date_of_birth`, `photograph_url`, `nin`, `bvn`, `bank_name`, `account_number`, `account_name`, `emergency_contact_name`, `emergency_contact_phone`, `next_of_kin_name`, `next_of_kin_phone`, `residential_address`, `state_id`, `lga_id`, `ward_id`, `pu_id`, `jurisdiction_type`, `jurisdiction_id`, `device_id`, `device_fingerprint`, `device_bound`, `last_login_at`, `last_login_ip`, `last_login_device`, `last_login_gps_lat`, `last_login_gps_lng`, `login_attempts`, `locked_until`, `status`, `email_verified_at`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(7, NULL, 'ADMIN001', 1, 'Super', 'Admin', 'aliyuabubakar11117@gmail.com', '+2348005555555', NULL, '$2y$10$mPOOaqv.b.itbANJKSqx2uhRtgZBolaxEoEB7sNikIdjoP.AcHPLS', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-07-03 23:24:07', '::1', NULL, NULL, NULL, 0, NULL, 'active', '2026-07-02 17:23:24', NULL, '2026-07-02 17:23:24', '2026-07-03 23:29:50', NULL),
(12, 10, 'USR000001', 4, 'Aliyu', 'Abubakar', 'aliyuabubakarjdh@gmail.com', '+234902770200', NULL, '$2y$10$NXRYRWj01fPTvY0fTYEtL.11o6D/6FqApW1yvmAhcRO04q4B0c/za', NULL, NULL, 0, NULL, 'male', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-07-05 09:05:01', '::1', NULL, NULL, NULL, 0, NULL, 'active', NULL, 7, '2026-07-03 22:49:35', '2026-07-05 09:05:01', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_sessions`
--

INSERT INTO `user_sessions` (`id`, `user_id`, `token`, `device_id`, `device_type`, `device_name`, `ip_address`, `gps_lat`, `gps_lng`, `user_agent`, `expires_at`, `last_activity_at`, `is_active`, `created_at`) VALUES
(19, 8, 'c52c008af3dc64fbfba261c972c2604c6ba528352c542fe15fde956c9ae92048', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-02 19:48:40', '2026-07-02 19:48:40', 0, '2026-07-02 19:21:30'),
(20, 7, 'f845c553b411dd60d654bd1678405531b59c06140bcd1e75c5e188f90ce5d80c', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-02 20:23:14', '2026-07-02 20:23:14', 0, '2026-07-02 19:23:57'),
(22, 7, '1f276b00975a2da770ddd04cb56c0e813e0af03f2332495157fc835bc3b0bef0', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-02 21:16:23', '2026-07-02 21:16:23', 0, '2026-07-02 20:24:13'),
(23, 9, '2e5e317e296fac4ca2bc016c3af8643dc45c3010bf9af0b2327079abbf398134', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-02 21:16:33', '2026-07-02 21:16:33', 0, '2026-07-02 21:16:30'),
(24, 8, '653b53cb8586ca4e2e0436f388ed488afae1634a56740a8136334dfc16236338', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-02 23:28:14', '2026-07-02 23:28:14', 0, '2026-07-02 21:16:44'),
(25, 8, '76c67929cea3e5d36f26f56dbf2646ee8226890dd8bb94d71fa3f2531b4b213f', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-02 22:24:48', '2026-07-02 21:24:48', 1, '2026-07-02 21:24:48'),
(26, 7, 'bcced3ba365b57c189a3ef639cc5848abd3a42322ab2af95b35f1f00b9740cdc', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-03 00:20:32', '2026-07-03 00:20:32', 0, '2026-07-02 23:28:20'),
(27, 7, '421d2c2dcccd5ad991ad036ad1a5a9a6ae4be661ea9488ecc34359af5e91c4e2', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-03 00:25:34', '2026-07-03 00:25:34', 0, '2026-07-03 00:20:39'),
(28, 7, 'b5969d4dcc2d2872e806f4dc1abe00487f0e8a8b588d1e7e05b9c4b93f0641fb', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-03 00:48:05', '2026-07-03 00:48:05', 0, '2026-07-03 00:25:49'),
(29, 9, 'bdf48911daa4705ffca4c132bb3dac170f7b08ddd68d824cf79d8faa9c022628', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-03 01:23:50', '2026-07-03 01:23:50', 0, '2026-07-03 00:48:12'),
(31, 7, 'b5d434b1c80b0641210c9dc3210635b8a62294f8dc12ed28afb4090b1fc0ffcb', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-03 13:57:28', '2026-07-03 13:57:28', 0, '2026-07-03 13:57:24'),
(32, 8, '5148924fc7ba2bb90c54877db01a94a3f0435314d62ee461212cd5d5a6c21ac0', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-03 13:57:40', '2026-07-03 13:57:40', 0, '2026-07-03 13:57:37'),
(33, 9, '03ef92b01168c66a135a7bbcc327d09e9d26c59aceb4cfcd5ad573a9782a3b4b', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-03 18:45:45', '2026-07-03 18:45:45', 0, '2026-07-03 13:57:50'),
(34, 8, '788734b50ae4dccfa8172a849cfefe823e02edc5a083bc31c7dde50f9a2f701b', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-03 18:48:19', '2026-07-03 18:48:19', 0, '2026-07-03 18:46:41'),
(35, 9, 'c826788fcc7fee9fe2252e2139743ad3aac0c629dce655279e04f85bc66710cb', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-03 22:46:14', '2026-07-03 22:46:14', 0, '2026-07-03 18:48:34'),
(36, 7, '59e52496b2da16ac6e714ac800527fbed7a24bf828de912750e1eb97662e48c7', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-03 22:49:51', '2026-07-03 22:49:51', 0, '2026-07-03 22:46:29'),
(37, 12, '564b1c545e706d0ae9da2781ab8c3ffb6089a37e6af5991a8a4edc4dd8d73640', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-03 22:55:16', '2026-07-03 22:55:16', 0, '2026-07-03 22:51:54'),
(38, 12, 'ffb3d2b877adcbd2e9539f92c52af5cd7a58b5a25dd144d54d4222be4fb3443b', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-03 22:55:40', '2026-07-03 22:55:40', 0, '2026-07-03 22:55:39'),
(39, 12, '04f8d7be20c97b852fad7815de3cdecae30d67e844b81523730cc217390f5b4f', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-03 23:05:29', '2026-07-03 23:05:29', 0, '2026-07-03 22:56:24'),
(40, 12, 'fef45b7095932f54d47ba8105cbf41c656007b61a88ed0d44589c4633d3bf0a1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-03 23:18:20', '2026-07-03 23:18:20', 0, '2026-07-03 23:06:19'),
(41, 12, '55d62e1a239f42d7a23f5d25fed22a5bf466edd1fc255facac9bd7415793a5c4', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-03 23:18:52', '2026-07-03 23:18:52', 0, '2026-07-03 23:18:52'),
(42, 12, 'fdf87bf6ab9f9898393cb55100cc4ce458acdd5b0a4723453d3750ce9dafcb75', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-03 23:23:53', '2026-07-03 23:23:53', 0, '2026-07-03 23:19:37'),
(43, 7, '9fb683a3af9161792dcfda3b0c0189b71a532c4f36f4cbcca9942084e37c63a3', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-03 23:30:25', '2026-07-03 23:30:25', 0, '2026-07-03 23:24:07'),
(44, 12, 'a3dda5ad041471a28330952a0253ea59ef2e90b1846562383b3d3064a7a267d1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-03 23:30:33', '2026-07-03 23:30:33', 0, '2026-07-03 23:30:32'),
(45, 12, '6d9dc937c808c2bbd88dc280c4bcc9ab5cf3914b5490b48ed18fc912a6f6f069', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-03 23:31:59', '2026-07-03 23:31:59', 0, '2026-07-03 23:31:59'),
(46, 12, '9f0dcb3f71a6cdd03b860fdd6624cb3826fcfeb38e4cc2d94f758b578cdad981', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-03 23:37:13', '2026-07-03 23:37:13', 0, '2026-07-03 23:37:13'),
(47, 12, '393712b6af2034d18a2549f36748da4c8482eb59b4a5a9cd62019f1ebe2afc3c', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-03 23:40:08', '2026-07-03 23:40:08', 0, '2026-07-03 23:40:08'),
(48, 12, 'ac69afd2af422f21cb5e5068b4cbc6eb9c169393bfcaeca97232033fa62a2103', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-03 23:40:41', '2026-07-03 23:40:41', 0, '2026-07-03 23:40:41'),
(49, 12, '3d7baa829df0ee7908ac6fdf5ed7d9cc526fe77c55ca8fac6f28dc037ff57932', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-03 23:40:51', '2026-07-03 23:40:51', 0, '2026-07-03 23:40:51'),
(50, 12, '973e3b3b028fda866e4a764136af58c0df0dad9426dc819c47c5a3c68213bf77', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-03 23:52:07', '2026-07-03 23:52:07', 0, '2026-07-03 23:41:10'),
(51, 12, 'dc582aa866327fcc8f625c3e976a4331e0d19a7646d14c1a45a905e8b28e3468', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-03 23:52:56', '2026-07-03 23:52:56', 0, '2026-07-03 23:52:15'),
(52, 12, 'ff62e5de1ed28932225762bcf3b5b03bce59e6ce770e1cec20f65f66203dfe85', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-04 00:18:01', '2026-07-04 00:18:01', 0, '2026-07-03 23:53:04'),
(53, 12, 'b453beadd8e67b4963f7be0f3e7a8826f9a1a525cd2e498ecb6fc30629900da5', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-04 00:24:29', '2026-07-04 00:24:29', 0, '2026-07-04 00:18:18'),
(54, 12, '491beb59189b9e5f9448e8ed831b6a346f3a19050f7d4447a0e2717bd809003e', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-04 00:25:34', '2026-07-04 00:25:34', 0, '2026-07-04 00:24:35'),
(55, 12, '1fe92333cb0a3a3d7a1a0fbc1d464dd78fc8b30335462ce72f24c506b58529ad', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-04 00:26:53', '2026-07-04 00:26:53', 0, '2026-07-04 00:25:40'),
(56, 12, '8c605250b199ef9f58e4e90f3a9e5a5c63c15e0f8d8f280b2810627194a2c451', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-04 00:29:07', '2026-07-04 00:29:07', 0, '2026-07-04 00:27:21'),
(57, 12, '9d6e2c7c6c129565e253f9222d7184537278ebebba5763da161911c65a4c676e', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-04 00:30:25', '2026-07-04 00:30:25', 0, '2026-07-04 00:29:31'),
(58, 12, '89b9097365bcd155d52590bfe5aae6b28d2ea1a40a8034d6da6ff46502392f65', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-04 00:31:21', '2026-07-04 00:31:21', 0, '2026-07-04 00:30:46'),
(59, 12, 'd935dc461fe7a5f8a428983c6eda04fa825f1e128ea6267bfd0fab3046d249ed', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-04 00:32:57', '2026-07-04 00:32:57', 0, '2026-07-04 00:31:28'),
(60, 12, '188dd1e52ee880ec1017717970990a0c0f137a7bd9bb8474b6804778b82f2232', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-04 00:39:41', '2026-07-04 00:39:41', 0, '2026-07-04 00:33:05'),
(61, 12, 'ad543e91343bee1205e7d8f4adc618558c09924d506f00ef61b7ac13ae22665b', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-04 01:04:40', '2026-07-04 01:04:40', 0, '2026-07-04 00:39:59'),
(62, 12, 'bbac67f5bc01dea658cd7a129f3800cc5721e697c4696a83fd0787d095b198f6', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-05 09:04:18', '2026-07-05 09:04:18', 0, '2026-07-05 09:01:48'),
(63, 12, '299340a0db44c05cfea9c913889ac9c2273b0671996cfb7e2999afdb8fe4b37f', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-05 09:04:52', '2026-07-05 09:04:52', 0, '2026-07-05 09:04:33'),
(64, 12, 'ce183060c25912ea4522413c1da81136e7c1712a7d91575288d5494743444cab', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-05 10:05:01', '2026-07-05 09:05:01', 1, '2026-07-05 09:05:01');

-- --------------------------------------------------------

--
-- Table structure for table `wards`
--

CREATE TABLE `wards` (
  `id` int(10) UNSIGNED NOT NULL,
  `lga_id` int(10) UNSIGNED NOT NULL,
  `code` varchar(30) NOT NULL,
  `name` varchar(150) NOT NULL,
  `gps_lat` decimal(10,8) DEFAULT NULL,
  `gps_lng` decimal(11,8) DEFAULT NULL,
  `registered_voters` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `wards`
--

INSERT INTO `wards` (`id`, `lga_id`, `code`, `name`, `gps_lat`, `gps_lng`, `registered_voters`, `is_active`) VALUES
(1, 792, '503715', 'APC', NULL, NULL, 0, 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_activity_user` (`user_id`),
  ADD KEY `idx_activity_tenant` (`tenant_id`),
  ADD KEY `idx_activity_type` (`activity_type`),
  ADD KEY `idx_activity_created` (`created_at`);

--
-- Indexes for table `agent_assignments`
--
ALTER TABLE `agent_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_assignments_tenant` (`tenant_id`),
  ADD KEY `idx_assignments_election` (`election_id`),
  ADD KEY `idx_assignments_user` (`user_id`),
  ADD KEY `idx_assignments_pu` (`pu_id`),
  ADD KEY `idx_assignments_status` (`status`),
  ADD KEY `assigned_by` (`assigned_by`);

--
-- Indexes for table `agent_checkins`
--
ALTER TABLE `agent_checkins`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_checkins_tenant` (`tenant_id`),
  ADD KEY `idx_checkins_election` (`election_id`),
  ADD KEY `idx_checkins_agent` (`agent_id`),
  ADD KEY `idx_checkins_pu` (`pu_id`),
  ADD KEY `idx_checkins_type` (`checkin_type`);

--
-- Indexes for table `agent_payments`
--
ALTER TABLE `agent_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_agent_payments_tenant` (`tenant_id`),
  ADD KEY `idx_agent_payments_election` (`election_id`),
  ADD KEY `idx_agent_payments_agent` (`agent_id`),
  ADD KEY `paid_by` (`paid_by`);

--
-- Indexes for table `api_keys`
--
ALTER TABLE `api_keys`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_api_keys_tenant` (`tenant_id`),
  ADD KEY `idx_api_keys_prefix` (`key_prefix`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `api_logs`
--
ALTER TABLE `api_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_api_logs_key` (`api_key_id`),
  ADD KEY `idx_api_logs_user` (`user_id`),
  ADD KEY `idx_api_logs_endpoint` (`endpoint`(100)),
  ADD KEY `idx_api_logs_created` (`created_at`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_tenant` (`tenant_id`),
  ADD KEY `idx_audit_user` (`user_id`),
  ADD KEY `idx_audit_action` (`action`),
  ADD KEY `idx_audit_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_audit_created` (`created_at`);

--
-- Indexes for table `backups`
--
ALTER TABLE `backups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_backups_tenant` (`tenant_id`),
  ADD KEY `idx_backups_status` (`status`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `broadcasts`
--
ALTER TABLE `broadcasts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_broadcasts_tenant` (`tenant_id`),
  ADD KEY `idx_broadcasts_election` (`election_id`),
  ADD KEY `idx_broadcasts_status` (`status`),
  ADD KEY `sender_id` (`sender_id`);

--
-- Indexes for table `budgets`
--
ALTER TABLE `budgets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_budgets_tenant` (`tenant_id`),
  ADD KEY `idx_budgets_election` (`election_id`);

--
-- Indexes for table `candidates`
--
ALTER TABLE `candidates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_candidates_tenant` (`tenant_id`),
  ADD KEY `idx_candidates_election` (`election_id`),
  ADD KEY `idx_candidates_party` (`party_id`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_chat_messages_room` (`room_id`),
  ADD KEY `idx_chat_messages_sender` (`sender_id`),
  ADD KEY `idx_chat_messages_created` (`created_at`);

--
-- Indexes for table `chat_rooms`
--
ALTER TABLE `chat_rooms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_chat_rooms_tenant` (`tenant_id`),
  ADD KEY `idx_chat_rooms_election` (`election_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `chat_room_members`
--
ALTER TABLE `chat_room_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_chat_members` (`room_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `elections`
--
ALTER TABLE `elections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_elections_tenant` (`tenant_id`),
  ADD KEY `idx_elections_type` (`type`),
  ADD KEY `idx_elections_status` (`status`),
  ADD KEY `idx_elections_date` (`election_date`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `election_materials`
--
ALTER TABLE `election_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_materials_tenant` (`tenant_id`),
  ADD KEY `idx_materials_election` (`election_id`),
  ADD KEY `idx_materials_pu` (`pu_id`),
  ADD KEY `agent_id` (`agent_id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_expenses_tenant` (`tenant_id`),
  ADD KEY `idx_expenses_budget` (`budget_id`),
  ADD KEY `idx_expenses_status` (`status`),
  ADD KEY `paid_to_user_id` (`paid_to_user_id`),
  ADD KEY `paid_by_user_id` (`paid_by_user_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `federal_constituencies`
--
ALTER TABLE `federal_constituencies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_fc_state_code` (`state_id`,`code`);

--
-- Indexes for table `incidents`
--
ALTER TABLE `incidents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_incidents_tenant` (`tenant_id`),
  ADD KEY `idx_incidents_election` (`election_id`),
  ADD KEY `idx_incidents_reporter` (`reporter_id`),
  ADD KEY `idx_incidents_pu` (`pu_id`),
  ADD KEY `idx_incidents_type` (`incident_type`),
  ADD KEY `idx_incidents_severity` (`severity`),
  ADD KEY `idx_incidents_status` (`status`),
  ADD KEY `idx_incidents_panic` (`is_panic`),
  ADD KEY `idx_incidents_created` (`created_at`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `resolved_by` (`resolved_by`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `idx_invoices_tenant` (`tenant_id`),
  ADD KEY `idx_invoices_status` (`status`),
  ADD KEY `subscription_id` (`subscription_id`);

--
-- Indexes for table `lgas`
--
ALTER TABLE `lgas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_lgas_state_code` (`state_id`,`code`),
  ADD KEY `idx_lgas_state` (`state_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_login_attempts_ip` (`ip_address`),
  ADD KEY `idx_login_attempts_email` (`email`),
  ADD KEY `idx_login_attempts_created` (`created_at`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_user` (`user_id`),
  ADD KEY `idx_notifications_type` (`type`),
  ADD KEY `idx_notifications_read` (`is_read`),
  ADD KEY `idx_notifications_created` (`created_at`);

--
-- Indexes for table `offline_sync_queue`
--
ALTER TABLE `offline_sync_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sync_user` (`user_id`),
  ADD KEY `idx_sync_device` (`device_id`),
  ADD KEY `idx_sync_status` (`status`),
  ADD KEY `idx_sync_priority` (`priority`);

--
-- Indexes for table `otp_verifications`
--
ALTER TABLE `otp_verifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_otp_user` (`user_id`),
  ADD KEY `idx_otp_code` (`otp_code`),
  ADD KEY `idx_otp_expires` (`expires_at`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pw_resets_user` (`user_id`),
  ADD KEY `idx_pw_resets_token` (`token`);

--
-- Indexes for table `people`
--
ALTER TABLE `people`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_people_tenant` (`tenant_id`),
  ADD KEY `idx_people_category` (`category`),
  ADD KEY `idx_people_phone` (`phone`),
  ADD KEY `idx_people_pu` (`pu_id`),
  ADD KEY `state_id` (`state_id`),
  ADD KEY `lga_id` (`lga_id`),
  ADD KEY `ward_id` (`ward_id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD UNIQUE KEY `uk_permissions_module_action` (`module`,`action`),
  ADD KEY `idx_permissions_module` (`module`);

--
-- Indexes for table `political_parties`
--
ALTER TABLE `political_parties`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_parties_tenant` (`tenant_id`);

--
-- Indexes for table `polling_units`
--
ALTER TABLE `polling_units`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_pu_ward_code` (`ward_id`,`code`),
  ADD KEY `idx_pu_ward` (`ward_id`),
  ADD KEY `idx_pu_gps` (`gps_lat`,`gps_lng`);

--
-- Indexes for table `public_results`
--
ALTER TABLE `public_results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_public_results_tenant` (`tenant_id`),
  ADD KEY `idx_public_results_election` (`election_id`),
  ADD KEY `idx_public_results_level` (`level`),
  ADD KEY `idx_public_results_published` (`is_published`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `public_slug` (`public_slug`),
  ADD KEY `idx_reports_tenant` (`tenant_id`),
  ADD KEY `idx_reports_election` (`election_id`),
  ADD KEY `idx_reports_type` (`type`),
  ADD KEY `idx_reports_public` (`is_public`),
  ADD KEY `generated_by` (`generated_by`);

--
-- Indexes for table `results_ec8a`
--
ALTER TABLE `results_ec8a`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ec8a_tenant` (`tenant_id`),
  ADD KEY `idx_ec8a_election` (`election_id`),
  ADD KEY `idx_ec8a_pu` (`pu_id`),
  ADD KEY `idx_ec8a_agent` (`agent_id`),
  ADD KEY `idx_ec8a_status` (`status`),
  ADD KEY `idx_ec8a_created` (`created_at`),
  ADD KEY `verified_by` (`verified_by`);

--
-- Indexes for table `results_ec8b`
--
ALTER TABLE `results_ec8b`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ec8b_tenant` (`tenant_id`),
  ADD KEY `idx_ec8b_election` (`election_id`),
  ADD KEY `idx_ec8b_ward` (`ward_id`),
  ADD KEY `idx_ec8b_mismatch` (`mismatch_alert`),
  ADD KEY `coordinator_id` (`coordinator_id`);

--
-- Indexes for table `results_ec8c`
--
ALTER TABLE `results_ec8c`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ec8c_tenant` (`tenant_id`),
  ADD KEY `idx_ec8c_election` (`election_id`),
  ADD KEY `idx_ec8c_lga` (`lga_id`),
  ADD KEY `idx_ec8c_mismatch` (`mismatch_alert`),
  ADD KEY `coordinator_id` (`coordinator_id`);

--
-- Indexes for table `results_ec8d`
--
ALTER TABLE `results_ec8d`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ec8d_tenant` (`tenant_id`),
  ADD KEY `idx_ec8d_election` (`election_id`),
  ADD KEY `idx_ec8d_state` (`state_id`),
  ADD KEY `idx_ec8d_mismatch` (`mismatch_alert`),
  ADD KEY `coordinator_id` (`coordinator_id`);

--
-- Indexes for table `results_ec8e`
--
ALTER TABLE `results_ec8e`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ec8e_tenant` (`tenant_id`),
  ADD KEY `idx_ec8e_election` (`election_id`),
  ADD KEY `idx_ec8e_mismatch` (`mismatch_alert`),
  ADD KEY `coordinator_id` (`coordinator_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_roles_tenant_slug` (`tenant_id`,`slug`),
  ADD KEY `idx_roles_level` (`level`),
  ADD KEY `idx_roles_tenant` (`tenant_id`);

--
-- Indexes for table `security_events`
--
ALTER TABLE `security_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_security_tenant` (`tenant_id`),
  ADD KEY `idx_security_user` (`user_id`),
  ADD KEY `idx_security_type` (`event_type`),
  ADD KEY `idx_security_risk` (`risk_score`);

--
-- Indexes for table `senatorial_districts`
--
ALTER TABLE `senatorial_districts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_sd_state_code` (`state_id`,`code`);

--
-- Indexes for table `states`
--
ALTER TABLE `states`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_subscriptions_tenant` (`tenant_id`),
  ADD KEY `idx_subscriptions_status` (`payment_status`);

--
-- Indexes for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ticket_number` (`ticket_number`),
  ADD KEY `idx_tickets_tenant` (`tenant_id`),
  ADD KEY `idx_tickets_user` (`user_id`),
  ADD KEY `idx_tickets_status` (`status`),
  ADD KEY `idx_tickets_priority` (`priority`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- Indexes for table `support_ticket_replies`
--
ALTER TABLE `support_ticket_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticket_id` (`ticket_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key` (`key`);

--
-- Indexes for table `tenants`
--
ALTER TABLE `tenants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uuid` (`uuid`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_tenants_slug` (`slug`),
  ADD KEY `idx_tenants_status` (`subscription_status`),
  ADD KEY `idx_tenants_active` (`is_active`);

--
-- Indexes for table `tenant_settings`
--
ALTER TABLE `tenant_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_tenant_settings` (`tenant_id`,`key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_code` (`user_code`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_tenant` (`tenant_id`),
  ADD KEY `idx_users_role` (`role_id`),
  ADD KEY `idx_users_phone` (`phone`),
  ADD KEY `idx_users_status` (`status`),
  ADD KEY `idx_users_jurisdiction` (`jurisdiction_type`,`jurisdiction_id`),
  ADD KEY `idx_users_pu` (`pu_id`),
  ADD KEY `idx_users_dob` (`date_of_birth`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sessions_user` (`user_id`),
  ADD KEY `idx_sessions_token` (`token`(255)),
  ADD KEY `idx_sessions_active` (`is_active`);

--
-- Indexes for table `wards`
--
ALTER TABLE `wards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_wards_lga_code` (`lga_id`,`code`),
  ADD KEY `idx_wards_lga` (`lga_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=219;

--
-- AUTO_INCREMENT for table `agent_assignments`
--
ALTER TABLE `agent_assignments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `agent_checkins`
--
ALTER TABLE `agent_checkins`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `agent_payments`
--
ALTER TABLE `agent_payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `api_keys`
--
ALTER TABLE `api_keys`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `api_logs`
--
ALTER TABLE `api_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `backups`
--
ALTER TABLE `backups`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `broadcasts`
--
ALTER TABLE `broadcasts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `budgets`
--
ALTER TABLE `budgets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `candidates`
--
ALTER TABLE `candidates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_rooms`
--
ALTER TABLE `chat_rooms`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_room_members`
--
ALTER TABLE `chat_room_members`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `elections`
--
ALTER TABLE `elections`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `election_materials`
--
ALTER TABLE `election_materials`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `federal_constituencies`
--
ALTER TABLE `federal_constituencies`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `incidents`
--
ALTER TABLE `incidents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `lgas`
--
ALTER TABLE `lgas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=793;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=84;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `offline_sync_queue`
--
ALTER TABLE `offline_sync_queue`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `otp_verifications`
--
ALTER TABLE `otp_verifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `people`
--
ALTER TABLE `people`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `political_parties`
--
ALTER TABLE `political_parties`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `polling_units`
--
ALTER TABLE `polling_units`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `public_results`
--
ALTER TABLE `public_results`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `results_ec8a`
--
ALTER TABLE `results_ec8a`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `results_ec8b`
--
ALTER TABLE `results_ec8b`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `results_ec8c`
--
ALTER TABLE `results_ec8c`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `results_ec8d`
--
ALTER TABLE `results_ec8d`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `results_ec8e`
--
ALTER TABLE `results_ec8e`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `security_events`
--
ALTER TABLE `security_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=127;

--
-- AUTO_INCREMENT for table `senatorial_districts`
--
ALTER TABLE `senatorial_districts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `states`
--
ALTER TABLE `states`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `support_tickets`
--
ALTER TABLE `support_tickets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `support_ticket_replies`
--
ALTER TABLE `support_ticket_replies`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `tenants`
--
ALTER TABLE `tenants`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `tenant_settings`
--
ALTER TABLE `tenant_settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `wards`
--
ALTER TABLE `wards`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `activity_logs_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `agent_assignments`
--
ALTER TABLE `agent_assignments`
  ADD CONSTRAINT `agent_assignments_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `agent_assignments_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `agent_assignments_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `agent_assignments_ibfk_4` FOREIGN KEY (`pu_id`) REFERENCES `polling_units` (`id`),
  ADD CONSTRAINT `agent_assignments_ibfk_5` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `agent_checkins`
--
ALTER TABLE `agent_checkins`
  ADD CONSTRAINT `agent_checkins_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `agent_checkins_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `agent_checkins_ibfk_3` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `agent_checkins_ibfk_4` FOREIGN KEY (`pu_id`) REFERENCES `polling_units` (`id`);

--
-- Constraints for table `agent_payments`
--
ALTER TABLE `agent_payments`
  ADD CONSTRAINT `agent_payments_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `agent_payments_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `agent_payments_ibfk_3` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `agent_payments_ibfk_4` FOREIGN KEY (`paid_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `api_keys`
--
ALTER TABLE `api_keys`
  ADD CONSTRAINT `api_keys_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `api_keys_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `api_logs`
--
ALTER TABLE `api_logs`
  ADD CONSTRAINT `api_logs_ibfk_1` FOREIGN KEY (`api_key_id`) REFERENCES `api_keys` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `audit_logs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `backups`
--
ALTER TABLE `backups`
  ADD CONSTRAINT `backups_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `backups_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `broadcasts`
--
ALTER TABLE `broadcasts`
  ADD CONSTRAINT `broadcasts_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `broadcasts_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `broadcasts_ibfk_3` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `budgets`
--
ALTER TABLE `budgets`
  ADD CONSTRAINT `budgets_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `budgets_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `candidates`
--
ALTER TABLE `candidates`
  ADD CONSTRAINT `candidates_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `candidates_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `candidates_ibfk_3` FOREIGN KEY (`party_id`) REFERENCES `political_parties` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `chat_rooms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `chat_rooms`
--
ALTER TABLE `chat_rooms`
  ADD CONSTRAINT `chat_rooms_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_rooms_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `chat_rooms_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `chat_room_members`
--
ALTER TABLE `chat_room_members`
  ADD CONSTRAINT `chat_room_members_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `chat_rooms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_room_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `elections`
--
ALTER TABLE `elections`
  ADD CONSTRAINT `elections_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `elections_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `election_materials`
--
ALTER TABLE `election_materials`
  ADD CONSTRAINT `election_materials_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `election_materials_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `election_materials_ibfk_3` FOREIGN KEY (`pu_id`) REFERENCES `polling_units` (`id`),
  ADD CONSTRAINT `election_materials_ibfk_4` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`budget_id`) REFERENCES `budgets` (`id`),
  ADD CONSTRAINT `expenses_ibfk_3` FOREIGN KEY (`paid_to_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `expenses_ibfk_4` FOREIGN KEY (`paid_by_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `expenses_ibfk_5` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `federal_constituencies`
--
ALTER TABLE `federal_constituencies`
  ADD CONSTRAINT `federal_constituencies_ibfk_1` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `incidents`
--
ALTER TABLE `incidents`
  ADD CONSTRAINT `incidents_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `incidents_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `incidents_ibfk_3` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `incidents_ibfk_4` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `incidents_ibfk_5` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `lgas`
--
ALTER TABLE `lgas`
  ADD CONSTRAINT `lgas_ibfk_1` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD CONSTRAINT `login_attempts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `offline_sync_queue`
--
ALTER TABLE `offline_sync_queue`
  ADD CONSTRAINT `offline_sync_queue_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `people`
--
ALTER TABLE `people`
  ADD CONSTRAINT `people_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `people_ibfk_2` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `people_ibfk_3` FOREIGN KEY (`lga_id`) REFERENCES `lgas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `people_ibfk_4` FOREIGN KEY (`ward_id`) REFERENCES `wards` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `people_ibfk_5` FOREIGN KEY (`pu_id`) REFERENCES `polling_units` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `political_parties`
--
ALTER TABLE `political_parties`
  ADD CONSTRAINT `political_parties_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `polling_units`
--
ALTER TABLE `polling_units`
  ADD CONSTRAINT `polling_units_ibfk_1` FOREIGN KEY (`ward_id`) REFERENCES `wards` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `public_results`
--
ALTER TABLE `public_results`
  ADD CONSTRAINT `public_results_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `public_results_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reports_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reports_ibfk_3` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `results_ec8a`
--
ALTER TABLE `results_ec8a`
  ADD CONSTRAINT `results_ec8a_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `results_ec8a_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `results_ec8a_ibfk_3` FOREIGN KEY (`pu_id`) REFERENCES `polling_units` (`id`),
  ADD CONSTRAINT `results_ec8a_ibfk_4` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `results_ec8a_ibfk_5` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `results_ec8b`
--
ALTER TABLE `results_ec8b`
  ADD CONSTRAINT `results_ec8b_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `results_ec8b_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `results_ec8b_ibfk_3` FOREIGN KEY (`ward_id`) REFERENCES `wards` (`id`),
  ADD CONSTRAINT `results_ec8b_ibfk_4` FOREIGN KEY (`coordinator_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `results_ec8c`
--
ALTER TABLE `results_ec8c`
  ADD CONSTRAINT `results_ec8c_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `results_ec8c_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `results_ec8c_ibfk_3` FOREIGN KEY (`lga_id`) REFERENCES `lgas` (`id`),
  ADD CONSTRAINT `results_ec8c_ibfk_4` FOREIGN KEY (`coordinator_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `results_ec8d`
--
ALTER TABLE `results_ec8d`
  ADD CONSTRAINT `results_ec8d_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `results_ec8d_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `results_ec8d_ibfk_3` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`),
  ADD CONSTRAINT `results_ec8d_ibfk_4` FOREIGN KEY (`coordinator_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `results_ec8e`
--
ALTER TABLE `results_ec8e`
  ADD CONSTRAINT `results_ec8e_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `results_ec8e_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `results_ec8e_ibfk_3` FOREIGN KEY (`coordinator_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `roles`
--
ALTER TABLE `roles`
  ADD CONSTRAINT `roles_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `security_events`
--
ALTER TABLE `security_events`
  ADD CONSTRAINT `security_events_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `security_events_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `senatorial_districts`
--
ALTER TABLE `senatorial_districts`
  ADD CONSTRAINT `senatorial_districts_ibfk_1` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `subscriptions_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD CONSTRAINT `support_tickets_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `support_tickets_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `support_tickets_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `support_ticket_replies`
--
ALTER TABLE `support_ticket_replies`
  ADD CONSTRAINT `support_ticket_replies_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `support_ticket_replies_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `tenant_settings`
--
ALTER TABLE `tenant_settings`
  ADD CONSTRAINT `tenant_settings_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wards`
--
ALTER TABLE `wards`
  ADD CONSTRAINT `wards_ibfk_1` FOREIGN KEY (`lga_id`) REFERENCES `lgas` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;





