-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jul 27, 2026 at 04:44 PM
-- Server version: 8.0.41
-- PHP Version: 8.4.21

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `utgoohwm_election`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `tenant_id` bigint UNSIGNED DEFAULT NULL,
  `activity_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` bigint UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `tenant_id`, `activity_type`, `description`, `entity_type`, `entity_id`, `ip_address`, `device_id`, `created_at`) VALUES
(1, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-01 23:01:44'),
(2, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-01 23:06:54'),
(3, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-01 23:24:33'),
(4, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-01 23:28:55'),
(5, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-01 23:36:06'),
(6, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-01 23:40:33'),
(7, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-01 23:41:13'),
(8, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-01 23:49:09'),
(9, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '10.180.98.13', NULL, '2026-07-02 00:30:15'),
(18, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 01:24:48'),
(19, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 04:52:20'),
(26, 7, NULL, 'password_reset', 'Password reset requested', NULL, NULL, '::1', NULL, '2026-07-02 05:52:52'),
(27, 7, NULL, 'password_change', 'Password reset successfully', NULL, NULL, '::1', NULL, '2026-07-02 05:53:15'),
(28, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 05:53:53'),
(29, 7, NULL, 'inec_clear', 'Cleared INEC data: polling_units', NULL, NULL, '::1', NULL, '2026-07-02 06:39:04'),
(30, 7, NULL, 'settings_changed', 'Email settings updated', NULL, NULL, '::1', NULL, '2026-07-02 06:54:08'),
(31, 7, NULL, 'settings_changed', 'Email settings updated', NULL, NULL, '::1', NULL, '2026-07-02 06:54:25'),
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
(53, 7, NULL, 'password_reset', 'Password reset requested', NULL, NULL, '::1', NULL, '2026-07-02 17:55:01'),
(54, 7, NULL, 'password_change', 'Password reset successfully', NULL, NULL, '::1', NULL, '2026-07-02 17:56:03'),
(55, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 17:56:27'),
(56, 7, NULL, 'backup_created', 'Created backup: backup_2026-07-02_185750_full.sql', NULL, NULL, '::1', NULL, '2026-07-02 17:57:51'),
(57, 7, NULL, 'password_reset', 'Password reset requested', NULL, NULL, '::1', NULL, '2026-07-02 19:20:06'),
(58, 7, NULL, 'password_change', 'Password reset successfully', NULL, NULL, '::1', NULL, '2026-07-02 19:21:18'),
(59, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 19:21:30'),
(60, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 19:23:57'),
(61, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-02 19:48:39'),
(62, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 19:48:47'),
(63, 7, NULL, 'tenant_updated', 'Updated tenant: PDP (ID: 10)', NULL, NULL, '::1', NULL, '2026-07-02 20:12:21'),
(64, 7, NULL, 'tenant_updated', 'Updated tenant: PDP (ID: 10)', NULL, NULL, '::1', NULL, '2026-07-02 20:19:32'),
(65, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-02 20:23:14'),
(66, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 20:24:13'),
(67, 7, NULL, 'user_suspended', 'User ID: 8', NULL, NULL, '::1', NULL, '2026-07-02 21:13:28'),
(68, 7, NULL, 'user_activated', 'User ID: 8', NULL, NULL, '::1', NULL, '2026-07-02 21:13:37'),
(69, 7, NULL, 'user_created', 'Created user: Aliyu Abubakar for tenant ID: 10', NULL, NULL, '::1', NULL, '2026-07-02 21:15:55'),
(70, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-02 21:16:22'),
(71, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 21:16:30'),
(72, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-02 21:16:33'),
(73, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 21:16:44'),
(74, 7, NULL, 'election_created', 'Created election: 2027 election for tenant ID: 10', NULL, NULL, '::1', NULL, '2026-07-02 21:24:09'),
(75, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-02 21:24:48'),
(76, 7, NULL, 'user_created', 'Created user: Aliyu abubakar (ID: 10) for tenant ID: 10', NULL, NULL, '::1', NULL, '2026-07-02 21:25:26'),
(77, 7, NULL, 'election_created', 'Created election: 2027 election for tenant ID: 10', NULL, NULL, '::1', NULL, '2026-07-02 21:27:48'),
(78, 7, NULL, 'election_created', 'Created election: 2027 election for tenant ID: 10', NULL, NULL, '::1', NULL, '2026-07-02 21:31:55'),
(79, 7, NULL, 'election_created', 'Created election: 2027 election for tenant ID: 10', NULL, NULL, '::1', NULL, '2026-07-02 21:31:58'),
(80, 7, NULL, 'election_created', 'Created election: 2027 election (ID: 6) for tenant ID: 10', NULL, NULL, '::1', NULL, '2026-07-02 21:32:40'),
(81, 7, NULL, 'election_created', 'Created election: 2027 election (ID: 7) for tenant ID: 10', NULL, NULL, '::1', NULL, '2026-07-02 21:33:02'),
(82, 7, NULL, 'election_created', 'Created election: 2027 election (ID: 8) for tenant ID: 10', NULL, NULL, '::1', NULL, '2026-07-02 21:33:09'),
(83, 7, NULL, 'subscription_deleted', 'Deleted subscription ID: 3', NULL, NULL, '::1', NULL, '2026-07-02 21:34:31'),
(84, 7, NULL, 'subscription_created', 'Created subscription for tenant ID: 10 with plan: standard', NULL, NULL, '::1', NULL, '2026-07-02 21:36:56'),
(85, 7, NULL, 'invoice_created', 'Created invoice: INV-2026-2181 for tenant ID: 10', NULL, NULL, '::1', NULL, '2026-07-02 22:02:10'),
(86, 7, NULL, 'role_permissions_updated', 'Updated permissions for role: Super Administrator (ID: 1)', NULL, NULL, '::1', NULL, '2026-07-02 22:05:19'),
(87, 7, NULL, 'inec_data_cleared', 'Cleared INEC wards data', NULL, NULL, '::1', NULL, '2026-07-02 22:26:13'),
(88, 7, NULL, 'inec_data_cleared', 'Cleared INEC states data', NULL, NULL, '::1', NULL, '2026-07-02 22:26:19'),
(89, 7, NULL, 'inec_data_uploaded', 'Uploaded INEC polling_units data', NULL, NULL, '::1', NULL, '2026-07-02 22:26:39'),
(90, 7, NULL, 'inec_data_uploaded', 'Uploaded INEC polling_units data', NULL, NULL, '::1', NULL, '2026-07-02 22:26:47'),
(91, 7, NULL, 'invoice_sent', 'Sent invoice ID: 1', NULL, NULL, '::1', NULL, '2026-07-02 22:37:49'),
(92, 7, NULL, 'invoice_paid', 'Marked invoice ID: 1 as paid', NULL, NULL, '::1', NULL, '2026-07-02 22:39:02'),
(93, 7, NULL, 'inec_upload', 'Uploaded INEC data: inec_states_1783032177.csv', NULL, NULL, '::1', NULL, '2026-07-02 22:42:57'),
(94, 7, NULL, 'inec_upload', 'Uploaded INEC data: inec_states_1783032182.csv', NULL, NULL, '::1', NULL, '2026-07-02 22:43:02'),
(95, 7, NULL, '2fa_enabled', '2FA enabled', NULL, NULL, '::1', NULL, '2026-07-02 23:19:51'),
(96, 7, NULL, 'password_change', 'Password changed successfully', NULL, NULL, '::1', NULL, '2026-07-02 23:20:11'),
(97, 7, NULL, '2fa_disabled', '2FA disabled', NULL, NULL, '::1', NULL, '2026-07-02 23:20:17'),
(98, 7, NULL, 'profile_updated', 'Profile information updated', NULL, NULL, '::1', NULL, '2026-07-02 23:27:22'),
(99, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-02 23:28:13'),
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
(114, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-03 00:48:12'),
(115, 7, NULL, 'organization_logo_updated', 'Updated organization logo', NULL, NULL, '::1', NULL, '2026-07-03 01:06:27'),
(116, 7, NULL, 'user_suspended', 'Suspended user ID: 10', NULL, NULL, '::1', NULL, '2026-07-03 01:10:12'),
(117, 7, NULL, 'user_activated', 'Activated user ID: 10', NULL, NULL, '::1', NULL, '2026-07-03 01:10:19'),
(118, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-03 01:23:50'),
(119, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-03 01:24:27'),
(120, 7, NULL, 'user_archived', 'Archived user ID: 10', NULL, NULL, '::1', NULL, '2026-07-03 01:27:28'),
(121, 7, NULL, 'user_activated', 'Activated user ID: 10', NULL, NULL, '::1', NULL, '2026-07-03 01:27:34'),
(122, 7, NULL, 'user_deleted', 'Deleted user ID: 10', NULL, NULL, '::1', NULL, '2026-07-03 01:27:50'),
(123, 7, NULL, 'user_deleted', 'Deleted user ID: 8', NULL, NULL, '::1', NULL, '2026-07-03 01:28:09'),
(124, 7, NULL, 'election_duplicated', 'Duplicated election ID: 7', NULL, NULL, '::1', NULL, '2026-07-03 01:32:30'),
(125, 7, NULL, 'election_deleted', 'Deleted election ID: 9', NULL, NULL, '::1', NULL, '2026-07-03 01:32:37'),
(126, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-03 13:57:24'),
(127, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-03 13:57:28'),
(128, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-03 13:57:37'),
(129, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-03 13:57:39'),
(130, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-03 13:57:50'),
(131, 7, NULL, 'state_added', 'Added state: Jigawa', NULL, NULL, '::1', NULL, '2026-07-03 14:07:14'),
(132, 7, NULL, 'state_added', 'Added state: Kano', NULL, NULL, '::1', NULL, '2026-07-03 14:20:24'),
(133, 7, NULL, 'state_added', 'Added state: Kaduna', NULL, NULL, '::1', NULL, '2026-07-03 14:20:52'),
(134, 7, NULL, 'state_added', 'Added state: Kano', NULL, NULL, '::1', NULL, '2026-07-03 14:21:18'),
(135, 7, NULL, 'state_deleted', 'Deleted state ID: 39', NULL, NULL, '::1', NULL, '2026-07-03 14:21:27'),
(136, 7, NULL, 'state_deleted', 'Deleted state ID: 40', NULL, NULL, '::1', NULL, '2026-07-03 14:21:34'),
(137, 7, NULL, 'state_deleted', 'Deleted state ID: 38', NULL, NULL, '::1', NULL, '2026-07-03 14:21:43'),
(138, 7, NULL, 'lga_added', 'Added LGA: Rano', NULL, NULL, '::1', NULL, '2026-07-03 14:24:14'),
(139, 7, NULL, 'election_deleted', 'Deleted election ID: 7', NULL, NULL, '::1', NULL, '2026-07-03 14:36:19'),
(140, 7, NULL, 'election_archived', 'Archived election ID: 6', NULL, NULL, '::1', NULL, '2026-07-03 14:36:24'),
(141, 7, NULL, 'election_deleted', 'Deleted election ID: 6', NULL, NULL, '::1', NULL, '2026-07-03 14:36:28'),
(142, 7, NULL, 'election_deleted', 'Deleted election ID: 5', NULL, NULL, '::1', NULL, '2026-07-03 14:36:35'),
(143, 7, NULL, 'election_deleted', 'Deleted election ID: 3', NULL, NULL, '::1', NULL, '2026-07-03 14:36:40'),
(144, 7, NULL, 'election_deleted', 'Deleted election ID: 4', NULL, NULL, '::1', NULL, '2026-07-03 14:36:48'),
(145, 7, NULL, 'election_deleted', 'Deleted election ID: 2', NULL, NULL, '::1', NULL, '2026-07-03 14:36:54'),
(146, 7, NULL, 'ward_added', 'Added Ward: APC', NULL, NULL, '::1', NULL, '2026-07-03 14:47:20'),
(147, 7, NULL, 'pu_added', 'Added Polling Unit: APC', NULL, NULL, '::1', NULL, '2026-07-03 14:52:39'),
(148, 7, NULL, 'election_created', 'Created election: 2027 election (ID: 10)', NULL, NULL, '::1', NULL, '2026-07-03 15:14:26'),
(149, 7, NULL, 'candidate_added', 'Added candidate: Aliyu Abubakar to election ID: 10', NULL, NULL, '::1', NULL, '2026-07-03 15:17:19'),
(150, 7, NULL, 'election_pus_assigned_all', 'Assigned all polling units to election ID: 10', NULL, NULL, '::1', NULL, '2026-07-03 15:17:49'),
(151, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-03 18:45:44'),
(152, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-03 18:46:41'),
(153, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-03 18:48:19'),
(154, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-03 18:48:34'),
(155, 7, NULL, 'election_pu_removed', 'Removed polling unit ID: 1 from election ID: 10', NULL, NULL, '::1', NULL, '2026-07-03 18:49:53'),
(156, 7, NULL, 'party_added', 'Added party: Aliyu Abubakar', NULL, NULL, '::1', NULL, '2026-07-03 20:21:40'),
(157, 7, NULL, 'broadcast_created', 'Created broadcast ID: 1', NULL, NULL, '::1', NULL, '2026-07-03 21:12:26'),
(158, 7, NULL, 'budget_created', 'Created budget: APC', NULL, NULL, '::1', NULL, '2026-07-03 21:29:55'),
(159, 7, NULL, 'audit_logs_cleared', 'Cleared audit logs older than 30 days', NULL, NULL, '::1', NULL, '2026-07-03 22:10:56'),
(160, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-03 22:46:14'),
(161, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-03 22:46:29'),
(162, 7, NULL, 'user_created', 'Created user: Aliyu Abubakar (ID: 12) for tenant ID: 10', NULL, NULL, '::1', NULL, '2026-07-03 22:49:35'),
(163, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-03 22:49:51'),
(176, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-03 23:24:07'),
(177, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-03 23:30:25'),
(242, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-05 09:46:18'),
(243, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-05 09:46:36'),
(244, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-05 09:46:42'),
(245, 7, NULL, 'pu_deleted', 'Deleted Polling Unit ID: 1', NULL, NULL, '::1', NULL, '2026-07-05 09:47:12'),
(246, 7, NULL, 'ward_deleted', 'Deleted Ward ID: 1', NULL, NULL, '::1', NULL, '2026-07-05 09:47:21'),
(247, 7, NULL, 'lga_deleted', 'Deleted LGA ID: 792', NULL, NULL, '::1', NULL, '2026-07-05 09:47:34'),
(248, 7, NULL, 'state_deleted', 'Deleted state ID: 41', NULL, NULL, '::1', NULL, '2026-07-05 09:47:44'),
(249, 7, NULL, 'state_added', 'Added state: Jigawa', NULL, NULL, '::1', NULL, '2026-07-05 09:48:08'),
(250, 7, NULL, 'lga_added', 'Added LGA: Birnin Kudu', NULL, NULL, '::1', NULL, '2026-07-05 09:48:34'),
(251, 7, NULL, 'ward_added', 'Added Ward: kangire', NULL, NULL, '::1', NULL, '2026-07-05 09:49:12'),
(252, 7, NULL, 'pu_added', 'Added Polling Unit: kangire Primary School', NULL, NULL, '::1', NULL, '2026-07-05 09:49:50'),
(253, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-05 09:49:55'),
(256, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-05 09:55:56'),
(257, 7, NULL, 'state_added', 'Added state: kano', NULL, NULL, '::1', NULL, '2026-07-05 09:56:19'),
(258, 7, NULL, 'lga_added', 'Added LGA: Kano', NULL, NULL, '::1', NULL, '2026-07-05 09:56:44'),
(259, 7, NULL, 'ward_added', 'Added Ward: na ibaa', NULL, NULL, '::1', NULL, '2026-07-05 09:57:14'),
(260, 7, NULL, 'pu_added', 'Added Polling Unit: NI', NULL, NULL, '::1', NULL, '2026-07-05 09:57:32'),
(268, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-06 17:29:54'),
(269, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-06 17:30:01'),
(285, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-07 18:33:45'),
(286, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-07 18:34:14'),
(294, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-09 13:39:56'),
(295, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-09 13:40:54'),
(296, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-09 13:41:02'),
(297, 7, NULL, 'tenant_deleted', 'Deleted tenant ID: 10', NULL, NULL, '::1', NULL, '2026-07-09 13:41:23'),
(298, 7, NULL, 'tenant_created', 'Created new tenant: All Progressive Congress with admin: lubunaaliyuabk@gmail.com', NULL, NULL, '::1', NULL, '2026-07-09 13:48:14'),
(299, 7, NULL, 'tenant_created', 'Created new tenant: All Progressive Congress with admin: lubunaaliyuabk@gmail.com', NULL, NULL, '::1', NULL, '2026-07-09 13:51:27'),
(300, 7, NULL, 'tenant_created', 'Created new tenant: All Progressive Congress with admin: lubunaaliyuabk@gmail.com', NULL, NULL, '::1', NULL, '2026-07-09 13:54:42'),
(301, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-09 13:55:45'),
(307, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-09 13:58:39'),
(309, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-09 14:07:48'),
(310, 7, NULL, 'tenant_updated', 'Updated tenant: All Progressive Congress (ID: 13)', NULL, NULL, '::1', NULL, '2026-07-09 14:11:00'),
(311, 7, NULL, 'tenant_updated', 'Updated tenant: All Progressive Congress (ID: 13)', NULL, NULL, '::1', NULL, '2026-07-09 14:11:23'),
(312, 7, NULL, 'tenant_updated', 'Updated tenant: All Progressive Congress (ID: 13)', NULL, NULL, '::1', NULL, '2026-07-09 14:11:59'),
(313, 7, NULL, 'tenant_updated', 'Updated tenant: All Progressive Congress (ID: 13)', NULL, NULL, '::1', NULL, '2026-07-09 14:12:15'),
(314, 7, NULL, 'tenant_created', 'Created new tenant: All Progressive Congress with admin: lubunaaliyuabk@gmail.com', NULL, NULL, '::1', NULL, '2026-07-09 14:16:04'),
(315, 7, NULL, 'tenant_updated', 'Updated tenant: All Progressive Congress (ID: 14)', NULL, NULL, '::1', NULL, '2026-07-09 14:18:42'),
(316, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-09 14:19:16'),
(317, 21, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-09 14:19:22'),
(318, 21, NULL, 'logout', 'User logged out successfully', NULL, NULL, '::1', NULL, '2026-07-09 14:19:28'),
(319, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '::1', NULL, '2026-07-09 14:19:33'),
(320, 7, NULL, 'user_updated', 'Updated user: Aliyu Abubakar (ID: 21)', NULL, NULL, '::1', NULL, '2026-07-09 14:19:57'),
(321, 7, NULL, 'user_updated', 'Updated user: Aliyu Abubakar (ID: 21)', NULL, NULL, '::1', NULL, '2026-07-09 14:22:14'),
(322, 7, NULL, 'user_updated', 'Updated user: Aliyu Abubakar (ID: 21)', NULL, NULL, '::1', NULL, '2026-07-09 14:22:23'),
(323, 7, NULL, 'user_created', 'Created user: Aliyu Abubakar (ID: 22) for tenant ID: 14', NULL, NULL, '::1', NULL, '2026-07-09 14:23:04'),
(324, 7, NULL, 'user_password_reset', 'Reset password for user ID: 22', NULL, NULL, '::1', NULL, '2026-07-09 14:25:16'),
(325, 7, NULL, 'user_password_reset', 'Reset password for user ID: 7', NULL, NULL, '::1', NULL, '2026-07-09 14:26:01'),
(326, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 14:28:16'),
(327, 7, NULL, 'election_created', 'Created election: 2027 presidential election (ID: 11) for tenant ID: 14', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 14:31:57'),
(328, 7, NULL, 'election_updated', 'Updated election: 2027 presidential election (ID: 11)', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 14:45:41'),
(329, 7, NULL, 'election_status_changed', 'Changed election ID: 11 status to active', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 14:48:30'),
(330, 7, NULL, 'election_status_changed', 'Changed election ID: 11 status to closed', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 14:50:23'),
(331, 7, NULL, 'election_updated', 'Updated election: 2027 presidential election (ID: 11)', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 14:50:42'),
(332, 7, NULL, 'election_status_changed', 'Changed election ID: 11 status to active', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 14:50:46'),
(333, 7, NULL, 'election_status_changed', 'Changed election ID: 11 status to closed', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 14:50:47'),
(334, 7, NULL, 'ticket_created', 'Created ticket: TKT-2026-01215', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 15:01:07'),
(335, 7, NULL, 'ticket_escalated', 'Escalated ticket ID: 2', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 15:01:14'),
(336, 7, NULL, 'invoice_generated', 'Generated invoice: INV-2026-63265', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 15:12:09'),
(337, 7, NULL, 'invoice_sent', 'Sent invoice ID: 3', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 15:12:23'),
(338, 7, NULL, 'invoice_sent', 'Sent invoice ID: 3', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 15:12:28'),
(339, 7, NULL, 'invoice_sent', 'Sent invoice ID: 3', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 15:12:32'),
(340, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 15:13:57'),
(341, 21, NULL, 'login', 'User logged in successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 15:14:32'),
(342, 21, NULL, 'organization_logo_updated', 'Updated organization logo', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 15:15:18'),
(343, 21, NULL, 'organization_logo_updated', 'Updated organization logo', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 15:17:32'),
(344, 21, NULL, 'organization_logo_updated', 'Updated organization logo', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 15:18:00'),
(345, 21, NULL, 'organization_logo_updated', 'Updated organization logo', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 15:18:34'),
(346, 21, NULL, 'user_updated', 'Updated user: Aliyu Abubakar', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 15:19:12'),
(347, 21, NULL, 'user_created', 'Created user: ibrahim sule', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 15:25:02'),
(348, 21, NULL, 'user_updated', 'Updated user: ibrahim sule', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 15:25:40'),
(349, 21, NULL, 'user_updated', 'Updated user: ibrahim sule', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 15:25:46'),
(350, 21, NULL, 'user_suspended', 'Suspended user ID: 21', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 15:28:05'),
(351, 21, NULL, 'user_activated', 'Activated user ID: 21', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 15:28:14'),
(352, 21, NULL, 'user_password_reset', 'Reset password for user ID: 23', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 15:31:18'),
(353, 21, NULL, 'user_password_reset', 'Reset password for user ID: 21', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 15:31:36'),
(354, 21, NULL, 'logout', 'User logged out successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 15:32:07'),
(355, 21, NULL, 'password_reset', 'Password reset requested', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 15:32:23'),
(356, 21, NULL, 'password_change', 'Password reset successfully', NULL, NULL, '::1', NULL, '2026-07-09 15:33:34'),
(357, 21, NULL, 'login', 'User logged in successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 15:34:10'),
(358, 21, NULL, 'election_updated', 'Updated election ID: 11', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 15:37:06'),
(359, 21, NULL, 'candidate_added', 'Added candidate: Aliyu Boss to election ID: 11', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 15:42:17'),
(360, 21, NULL, 'election_updated', 'Updated election ID: 11', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 15:43:04'),
(361, 21, NULL, 'election_updated', 'Updated election ID: 11', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 15:43:30'),
(362, 21, NULL, 'login', 'User logged in successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 21:35:07'),
(363, 21, NULL, 'agent_added', 'Added agent: isah Musa', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 22:06:14'),
(364, 21, NULL, 'agent_assigned', 'Assigned agent ID: 24 to PU: 28', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 22:11:43'),
(365, 21, NULL, 'agent_reassigned', 'Reassigned agent from assignment ID: 1 to PU: 22', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 22:12:35'),
(366, 21, NULL, 'logout', 'User logged out successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 22:13:26'),
(367, 21, NULL, 'login', 'User logged in successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 22:14:24'),
(368, 21, NULL, 'user_updated', 'Updated user: Aliyu Abubakar', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 22:15:01'),
(369, 21, NULL, 'logout', 'User logged out successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 22:15:09'),
(370, 22, NULL, 'login', 'User logged in successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 22:15:20'),
(371, 22, NULL, 'logout', 'User logged out successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 22:23:08'),
(372, 21, NULL, 'login', 'User logged in successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 22:23:37'),
(373, 21, NULL, 'election_updated', 'Updated election ID: 11', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 22:24:08'),
(374, 21, NULL, 'logout', 'User logged out successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 22:24:11'),
(375, 22, NULL, 'login', 'User logged in successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 22:24:15'),
(376, 22, NULL, 'election_updated', 'Updated election: 2027 Governorship Election (ID: 11)', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 22:41:55'),
(377, 22, NULL, 'election_updated', 'Updated election: 2027 Governorship Election (ID: 11)', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 22:42:47'),
(378, 22, 14, 'settings_changed', 'Updated system settings', NULL, NULL, NULL, NULL, '2026-07-09 22:43:04'),
(379, 22, 14, 'settings_changed', 'Updated system settings', NULL, NULL, NULL, NULL, '2026-07-09 22:43:43'),
(380, 22, NULL, 'logout', 'User logged out successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 22:43:58'),
(381, 22, NULL, 'login', 'User logged in successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 22:45:11'),
(382, 22, NULL, 'settings_changed', 'Updated system settings', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 22:45:42'),
(383, 22, NULL, 'logout', 'User logged out successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 22:45:54'),
(384, 21, NULL, 'login', 'User logged in successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 22:45:59'),
(385, 21, NULL, 'user_updated', 'Updated user: isah Musa', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 22:46:56'),
(386, 21, NULL, 'logout', 'User logged out successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 22:47:04'),
(387, 24, NULL, 'login', 'User logged in successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 22:47:17'),
(388, 24, NULL, 'logout', 'User logged out successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 22:55:15'),
(389, 21, NULL, 'login', 'User logged in successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 22:55:20'),
(390, 21, NULL, 'logout', 'User logged out successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 22:55:36'),
(391, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 22:55:41'),
(392, 7, NULL, 'user_updated', 'Updated user: ibrahim sule (ID: 23)', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 23:18:00'),
(393, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 23:18:19'),
(394, 24, NULL, 'login', 'User logged in successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 23:18:23'),
(395, 24, NULL, 'logout', 'User logged out successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 23:23:56'),
(396, 22, NULL, 'login', 'User logged in successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 23:24:04'),
(397, 22, NULL, 'logout', 'User logged out successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 23:24:11'),
(398, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 23:24:15'),
(399, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 23:24:21'),
(400, 21, NULL, 'login', 'User logged in successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 23:24:25'),
(401, 21, NULL, 'logout', 'User logged out successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 23:24:42'),
(402, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 23:24:48'),
(403, 7, NULL, 'user_updated', 'Updated user: isah Musa (ID: 24)', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 23:25:28'),
(404, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 23:25:30'),
(405, 24, NULL, 'login', 'User logged in successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 23:25:34'),
(406, 24, NULL, 'lga_coordinator_assigned', 'Assigned LGA Coordinator: Aliyu Abubakar (ID: 25) for LGA ID: 1', NULL, NULL, '10.59.66.251', NULL, '2026-07-09 23:32:21'),
(407, 24, NULL, 'election_created', 'Created election: 2027 Governorship Election (ID: 12) for tenant ID: 14', NULL, NULL, '10.59.66.251', NULL, '2026-07-10 00:26:31'),
(408, 24, NULL, 'election_created', 'Created election: 2027 Governorship Election (ID: 13) for tenant ID: 14', NULL, NULL, '10.59.66.251', NULL, '2026-07-10 00:27:09'),
(409, 24, NULL, 'broadcast_created', 'Created broadcast: aaadsds (ID: 9)', NULL, NULL, '10.59.66.251', NULL, '2026-07-10 00:28:46'),
(410, 24, NULL, 'broadcast_created', 'Created broadcast: aaa (ID: 10)', NULL, NULL, '10.59.66.251', NULL, '2026-07-10 00:29:07'),
(411, 24, NULL, 'profile_updated', 'Updated profile information', NULL, NULL, '10.59.66.251', NULL, '2026-07-10 00:31:27'),
(412, 24, NULL, 'election_created', 'Created election: 2027 Governorship Election (ID: 14) for tenant ID: 14', NULL, NULL, '10.59.66.251', NULL, '2026-07-10 00:41:38'),
(413, 24, NULL, 'incident_reported', 'Reported incident: Violence (ID: 1)', NULL, NULL, '10.59.66.251', NULL, '2026-07-10 00:43:15'),
(414, 24, NULL, 'election_created', 'Created election: 2027 Governorship Election (ID: 15) for tenant ID: 14 in state: Jigawa', NULL, NULL, '10.59.66.251', NULL, '2026-07-10 00:45:14'),
(415, 24, NULL, 'broadcast_created', 'Created broadcast: aqdfddfgf (ID: 11)', NULL, NULL, '10.59.66.251', NULL, '2026-07-10 00:52:01'),
(416, 24, NULL, 'incident_resolved', 'Resolved incident ID: 1', NULL, NULL, '10.59.66.251', NULL, '2026-07-10 00:53:04'),
(417, 7, NULL, 'password_reset', 'Password reset requested', NULL, NULL, '10.59.66.251', NULL, '2026-07-10 09:07:39'),
(418, 7, NULL, 'password_reset', 'Password reset requested', NULL, NULL, '10.59.66.251', NULL, '2026-07-10 09:12:13'),
(419, 24, NULL, 'login', 'User logged in successfully', NULL, NULL, '10.59.66.157', NULL, '2026-07-10 09:12:59'),
(420, 24, NULL, 'login', 'User logged in successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-10 09:13:44'),
(421, 24, NULL, 'coordinator_suspended', 'Suspended LGA Coordinator: Aliyu Abubakar (ID: 25) - Reason: stupid', 'user', 25, '10.59.66.251', NULL, '2026-07-10 09:30:48'),
(422, 24, NULL, 'coordinator_password_reset', 'Reset password for LGA Coordinator: Aliyu Abubakar (ID: 25)', 'user', 25, '10.59.66.251', NULL, '2026-07-10 09:33:37'),
(423, 24, NULL, 'broadcast_created', 'Created broadcast: TTTddv (ID: 12)', 'broadcasts', 12, '10.59.66.251', NULL, '2026-07-10 09:52:57'),
(424, 24, NULL, 'session_revoked', 'Revoked session ID: 114', NULL, NULL, '10.59.66.251', NULL, '2026-07-10 10:44:48'),
(425, 24, NULL, 'session_revoked', 'Revoked session ID: 114', NULL, NULL, '10.59.66.251', NULL, '2026-07-10 10:44:54'),
(426, 24, NULL, 'profile_photo_updated', 'Profile photo updated', NULL, NULL, '10.59.66.251', NULL, '2026-07-10 10:45:06'),
(427, 24, NULL, 'coordinator_activated', 'Activated LGA Coordinator: Aliyu Abubakar (ID: 25)', 'user', 25, '10.59.66.251', NULL, '2026-07-10 10:47:39'),
(428, 24, NULL, 'logout', 'User logged out successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-10 10:48:07'),
(429, 21, NULL, 'login', 'User logged in successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-10 10:48:11'),
(430, 21, NULL, 'logout', 'User logged out successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-10 10:50:47'),
(431, 24, NULL, 'login', 'User logged in successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-10 10:50:51'),
(432, 24, NULL, 'logout', 'User logged out successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-10 10:51:55'),
(433, 25, NULL, 'login', 'User logged in successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-10 10:52:02'),
(434, 25, NULL, 'profile_photo_updated', 'Profile photo updated', NULL, NULL, '10.59.66.251', NULL, '2026-07-10 11:35:50'),
(435, 25, NULL, 'agent_created', 'Created PU Agent: Aliyu Abubakar (ID: 26)', 'users', 26, '10.59.66.251', NULL, '2026-07-10 13:13:42'),
(436, 25, NULL, 'agent_assigned', 'Assigned agent ID: 26 to PU: DANGAJE/DANGAJE (ID: 23)', 'users', 26, '10.59.66.251', NULL, '2026-07-10 13:14:04'),
(437, 25, NULL, 'logout', 'User logged out successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-10 13:14:29'),
(438, 21, NULL, 'login', 'User logged in successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-10 13:14:33'),
(439, 21, NULL, 'logout', 'User logged out successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-10 13:17:37'),
(440, 21, NULL, 'login', 'User logged in successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-10 13:17:56'),
(441, 21, NULL, 'user_created', 'Created user: Aliyu Abubakar (ID: 27)', NULL, NULL, '10.59.66.251', NULL, '2026-07-10 13:33:58'),
(442, 21, NULL, 'logout', 'User logged out successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-10 13:34:56'),
(443, 26, NULL, 'login', 'User logged in successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-10 13:35:02'),
(444, 26, NULL, 'logout', 'User logged out successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-10 13:35:04'),
(445, 27, NULL, 'login', 'User logged in successfully', NULL, NULL, '10.59.66.251', NULL, '2026-07-10 13:35:08'),
(446, 7, NULL, 'password_reset', 'Password reset requested', NULL, NULL, '102.89.83.93', NULL, '2026-07-10 22:04:31'),
(447, 7, NULL, 'password_reset', 'Password reset requested', NULL, NULL, '102.89.83.93', NULL, '2026-07-10 22:10:46'),
(448, 21, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.89.83.93', NULL, '2026-07-10 22:12:33'),
(449, 21, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.89.83.93', NULL, '2026-07-10 22:13:54'),
(450, 21, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.89.83.93', NULL, '2026-07-10 22:14:21'),
(451, 21, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.89.83.93', NULL, '2026-07-10 22:14:26'),
(452, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.89.83.93', NULL, '2026-07-10 22:15:05'),
(453, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.89.83.93', NULL, '2026-07-10 22:19:28'),
(454, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.89.83.93', NULL, '2026-07-10 22:21:02'),
(455, 7, NULL, 'user_created', 'Created user: Aliyu Yahaya (ID: 28) for tenant ID: 14', NULL, NULL, '102.89.83.93', NULL, '2026-07-10 22:22:27'),
(456, 7, NULL, 'user_updated', 'Updated user: Aliyu Yahaya (ID: 28)', NULL, NULL, '102.89.83.93', NULL, '2026-07-10 22:22:47'),
(457, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.89.83.93', NULL, '2026-07-10 22:23:04'),
(458, 28, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.89.83.93', NULL, '2026-07-10 22:23:17'),
(459, 7, NULL, 'password_reset', 'Password reset requested', NULL, NULL, '102.89.83.93', NULL, '2026-07-10 22:25:08'),
(460, 28, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.89.83.93', NULL, '2026-07-10 22:26:21'),
(461, 28, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.89.83.93', NULL, '2026-07-10 22:26:24'),
(462, 28, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.88.115.148', NULL, '2026-07-10 22:29:58'),
(463, 28, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.103.45', NULL, '2026-07-10 23:26:28'),
(464, 21, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.103.29', NULL, '2026-07-11 13:00:31'),
(465, 21, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.103.29', NULL, '2026-07-11 13:02:07'),
(466, 22, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.103.29', NULL, '2026-07-11 13:02:11'),
(467, 22, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.103.29', NULL, '2026-07-11 13:02:21'),
(468, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.103.29', NULL, '2026-07-11 13:02:29'),
(469, 7, NULL, 'user_created', 'Created user: Aliyu Abubakar (ID: 29) for tenant ID: 14', NULL, NULL, '102.91.103.29', NULL, '2026-07-11 13:03:12'),
(470, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.103.29', NULL, '2026-07-11 13:03:15'),
(471, 29, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.103.29', NULL, '2026-07-11 13:03:59'),
(472, 29, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.103.29', NULL, '2026-07-11 13:04:05'),
(473, 28, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.104.170', NULL, '2026-07-11 13:48:58'),
(474, 28, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.104.170', NULL, '2026-07-11 13:59:26'),
(475, 28, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.92.135', NULL, '2026-07-11 14:13:19'),
(476, 28, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.103.61', NULL, '2026-07-15 12:53:48'),
(477, 28, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.77.202', NULL, '2026-07-15 21:04:50'),
(478, 28, NULL, 'user_updated', 'Updated user: Aliyu Abubakar (ID: 26)', NULL, NULL, '102.91.77.202', NULL, '2026-07-15 21:05:44'),
(479, 29, NULL, 'password_change', 'Password changed successfully', NULL, NULL, NULL, NULL, '2026-07-15 22:03:22'),
(480, 28, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.77.202', NULL, '2026-07-15 22:05:20'),
(481, 28, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.77.202', NULL, '2026-07-15 22:59:33'),
(482, 28, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.77.202', NULL, '2026-07-15 23:00:16'),
(483, 28, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.77.202', NULL, '2026-07-15 23:00:24'),
(484, 28, NULL, 'user_updated', 'Updated user: ibrahim sule (ID: 23)', NULL, NULL, '102.91.77.202', NULL, '2026-07-15 23:00:47'),
(485, 28, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.77.202', NULL, '2026-07-15 23:00:50'),
(486, 23, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.77.202', NULL, '2026-07-15 23:00:53'),
(487, 23, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.77.202', NULL, '2026-07-15 23:01:37'),
(488, 28, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.77.202', NULL, '2026-07-15 23:01:44'),
(489, 28, NULL, 'user_updated', 'Updated user: Aliyu Abubakar (ID: 25)', NULL, NULL, '102.91.77.202', NULL, '2026-07-15 23:02:07'),
(490, 28, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.77.202', NULL, '2026-07-15 23:02:08'),
(491, 25, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.77.202', NULL, '2026-07-15 23:02:13'),
(492, 25, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.77.202', NULL, '2026-07-15 23:04:04'),
(493, 28, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.77.202', NULL, '2026-07-15 23:04:18'),
(494, 28, NULL, 'user_updated', 'Updated user: Aliyu Abubakar (ID: 27)', NULL, NULL, '102.91.77.202', NULL, '2026-07-15 23:04:43'),
(495, 28, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.77.202', NULL, '2026-07-15 23:04:44'),
(496, 27, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.77.202', NULL, '2026-07-15 23:04:50'),
(497, 27, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.77.202', NULL, '2026-07-15 23:06:39'),
(498, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.77.202', NULL, '2026-07-15 23:06:46'),
(499, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.77.202', NULL, '2026-07-15 23:07:32'),
(500, 22, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.77.202', NULL, '2026-07-15 23:07:38'),
(501, 22, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.77.202', NULL, '2026-07-15 23:08:01'),
(502, 7, NULL, 'password_reset', 'Password reset requested', NULL, NULL, '102.91.77.202', NULL, '2026-07-15 23:09:02'),
(503, 28, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.77.202', NULL, '2026-07-15 23:09:37'),
(504, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.93.133', NULL, '2026-07-16 09:16:10'),
(505, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.93.133', NULL, '2026-07-16 09:31:46'),
(506, 21, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.93.133', NULL, '2026-07-16 09:31:51'),
(507, 21, NULL, 'agent_added', 'Added agent: ba sule jumbe', NULL, NULL, '102.91.93.133', NULL, '2026-07-16 09:33:49'),
(508, 21, NULL, 'agent_assigned', 'Assigned agent ID: 30 to PU: 26', NULL, NULL, '102.91.93.133', NULL, '2026-07-16 09:34:16'),
(509, 21, NULL, 'user_updated', 'Updated user: ba sule jumbe (ID: 30)', NULL, NULL, '102.91.93.133', NULL, '2026-07-16 09:34:54'),
(510, 21, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.93.133', NULL, '2026-07-16 09:36:10'),
(511, 28, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.93.133', NULL, '2026-07-16 09:36:16'),
(512, 28, NULL, 'user_updated', 'Updated user: ba sule jumbe (ID: 30)', NULL, NULL, '102.91.93.133', NULL, '2026-07-16 09:40:52'),
(513, 28, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.93.133', NULL, '2026-07-16 10:00:52'),
(514, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.93.133', NULL, '2026-07-16 10:01:00'),
(515, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.93.133', NULL, '2026-07-16 10:01:03'),
(516, 21, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.93.133', NULL, '2026-07-16 10:01:11'),
(517, 21, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.93.133', NULL, '2026-07-16 10:01:38'),
(518, 28, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.93.133', NULL, '2026-07-16 10:01:47'),
(519, 28, NULL, 'user_created', 'Created user: Aliyu Abubakar (ID: 31) for tenant ID: 14', NULL, NULL, '102.91.93.133', NULL, '2026-07-16 10:02:30'),
(520, 28, NULL, 'user_updated', 'Updated user: Aliyu Abubakar (ID: 31)', NULL, NULL, '102.91.93.133', NULL, '2026-07-16 10:03:22'),
(521, 28, NULL, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.187', NULL, '2026-07-18 15:59:03'),
(522, 28, NULL, 'logout', 'User logged out successfully', NULL, NULL, '197.210.70.187', NULL, '2026-07-18 15:59:34'),
(523, 22, NULL, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.187', NULL, '2026-07-18 15:59:39'),
(524, 22, NULL, 'logout', 'User logged out successfully', NULL, NULL, '197.210.70.187', NULL, '2026-07-18 16:12:49'),
(525, 28, NULL, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.187', NULL, '2026-07-18 16:13:00'),
(526, 28, NULL, 'logout', 'User logged out successfully', NULL, NULL, '197.210.70.187', NULL, '2026-07-18 16:13:16'),
(527, 27, NULL, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.187', NULL, '2026-07-18 16:13:22'),
(528, 27, NULL, 'logout', 'User logged out successfully', NULL, NULL, '197.210.70.187', NULL, '2026-07-18 16:21:47'),
(529, 21, NULL, 'password_reset', 'Password reset requested', NULL, NULL, '197.210.70.187', NULL, '2026-07-18 16:22:11'),
(530, 21, NULL, 'password_reset', 'Password reset requested', NULL, NULL, '197.210.70.187', NULL, '2026-07-18 16:30:29'),
(531, 21, NULL, 'password_reset', 'Password reset requested', NULL, NULL, '197.210.70.187', NULL, '2026-07-18 16:30:37'),
(532, 28, NULL, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.187', NULL, '2026-07-18 16:31:14'),
(533, 28, NULL, 'user_updated', 'Updated user: Aliyu Abubakar (ID: 21)', NULL, NULL, '197.210.70.187', NULL, '2026-07-18 16:31:27'),
(534, 28, NULL, 'logout', 'User logged out successfully', NULL, NULL, '197.210.70.187', NULL, '2026-07-18 16:31:29'),
(535, 28, NULL, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.187', NULL, '2026-07-18 16:31:40'),
(536, 28, NULL, 'user_updated', 'Updated user: Aliyu Abubakar (ID: 21)', NULL, NULL, '197.210.70.187', NULL, '2026-07-18 16:31:57'),
(537, 28, NULL, 'logout', 'User logged out successfully', NULL, NULL, '197.210.70.187', NULL, '2026-07-18 16:32:00'),
(538, 21, NULL, 'password_reset', 'Password reset requested', NULL, NULL, '197.210.70.187', NULL, '2026-07-18 16:46:23'),
(539, 21, NULL, 'password_reset', 'Password reset requested', NULL, NULL, '102.88.112.143', NULL, '2026-07-18 21:12:11'),
(540, 21, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.88.112.143', 'fcf1660350ef203ae312963f5f777eebb485291d84a215757669fa95d9592db3', '2026-07-18 21:35:19'),
(541, 21, NULL, 'password_reset', 'Password reset requested', NULL, NULL, '102.88.112.143', 'fcf1660350ef203ae312963f5f777eebb485291d84a215757669fa95d9592db3', '2026-07-18 21:36:09'),
(542, 21, NULL, 'password_reset', 'Password reset requested', NULL, NULL, '102.88.112.143', 'fcf1660350ef203ae312963f5f777eebb485291d84a215757669fa95d9592db3', '2026-07-18 21:37:59'),
(543, 21, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.88.112.143', 'fcf1660350ef203ae312963f5f777eebb485291d84a215757669fa95d9592db3', '2026-07-18 21:40:26'),
(544, 21, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.88.112.143', 'fcf1660350ef203ae312963f5f777eebb485291d84a215757669fa95d9592db3', '2026-07-18 22:41:31'),
(545, 21, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.88.112.143', 'fcf1660350ef203ae312963f5f777eebb485291d84a215757669fa95d9592db3', '2026-07-18 22:41:47'),
(546, 21, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.88.112.143', 'fcf1660350ef203ae312963f5f777eebb485291d84a215757669fa95d9592db3', '2026-07-18 22:47:35'),
(547, 21, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.88.112.143', 'fcf1660350ef203ae312963f5f777eebb485291d84a215757669fa95d9592db3', '2026-07-18 22:47:38'),
(548, 21, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.88.112.143', 'fcf1660350ef203ae312963f5f777eebb485291d84a215757669fa95d9592db3', '2026-07-18 22:57:43'),
(549, 21, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.88.112.143', 'fcf1660350ef203ae312963f5f777eebb485291d84a215757669fa95d9592db3', '2026-07-18 22:57:44'),
(550, 21, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.88.112.143', 'fcf1660350ef203ae312963f5f777eebb485291d84a215757669fa95d9592db3', '2026-07-18 22:57:59'),
(551, 21, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.88.112.143', 'fcf1660350ef203ae312963f5f777eebb485291d84a215757669fa95d9592db3', '2026-07-18 22:58:34'),
(552, 28, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.88.112.143', 'fcf1660350ef203ae312963f5f777eebb485291d84a215757669fa95d9592db3', '2026-07-18 22:58:39'),
(553, 28, NULL, 'user_updated', 'Updated user: Aliyu Yahaya (ID: 28)', NULL, NULL, '102.88.112.143', 'fcf1660350ef203ae312963f5f777eebb485291d84a215757669fa95d9592db3', '2026-07-18 22:59:49');
INSERT INTO `activity_logs` (`id`, `user_id`, `tenant_id`, `activity_type`, `description`, `entity_type`, `entity_id`, `ip_address`, `device_id`, `created_at`) VALUES
(554, 28, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.88.112.143', 'fcf1660350ef203ae312963f5f777eebb485291d84a215757669fa95d9592db3', '2026-07-18 22:59:51'),
(555, 25, NULL, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.43', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', '2026-07-19 11:32:11'),
(556, 25, NULL, 'logout', 'User logged out successfully', NULL, NULL, '197.210.70.43', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', '2026-07-19 11:32:20'),
(557, 21, NULL, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.43', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', '2026-07-19 11:32:59'),
(558, 21, NULL, 'logout', 'User logged out successfully', NULL, NULL, '197.210.70.43', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', '2026-07-19 11:33:13'),
(559, 21, NULL, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.43', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', '2026-07-19 11:33:40'),
(560, 21, NULL, 'user_updated', 'Updated user: Aliyu Abubakar (ID: 27)', NULL, NULL, '197.210.70.43', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', '2026-07-19 11:34:08'),
(561, 21, NULL, 'logout', 'User logged out successfully', NULL, NULL, '197.210.70.43', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', '2026-07-19 11:34:11'),
(562, 27, NULL, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.43', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', '2026-07-19 11:34:31'),
(563, 27, NULL, 'broadcast_pu_agents', 'Sent broadcast to PU Agents: hello (1 recipients)', 'broadcasts', 13, '197.210.70.43', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', '2026-07-19 11:37:23'),
(564, 27, NULL, 'broadcast_pu_agents', 'Sent broadcast to PU Agents: hello (1 recipients)', 'broadcasts', 14, '197.210.70.43', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', '2026-07-19 11:37:48'),
(565, 27, NULL, 'logout', 'User logged out successfully', NULL, NULL, '197.210.70.43', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', '2026-07-19 12:23:36'),
(566, 27, NULL, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.43', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', '2026-07-19 12:23:38'),
(567, 27, NULL, 'agent_suspended', 'Suspended agent ID: 26', 'user', 26, '197.210.70.43', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', '2026-07-19 13:13:21'),
(568, 27, NULL, 'profile_photo_updated', 'Profile photo updated', 'user', 27, '197.210.70.43', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', '2026-07-19 13:44:34'),
(569, 27, NULL, 'agent_activated', 'Activated agent ID: 26', 'user', 26, '197.210.70.43', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', '2026-07-19 14:14:30'),
(570, 26, 14, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.43', NULL, '2026-07-19 14:59:01'),
(571, 26, 14, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.43', NULL, '2026-07-19 14:59:06'),
(572, 26, 14, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.43', NULL, '2026-07-19 14:59:09'),
(573, 26, 14, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.43', NULL, '2026-07-19 14:59:11'),
(574, 26, 14, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.43', NULL, '2026-07-19 15:00:37'),
(575, 26, 14, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.43', NULL, '2026-07-19 15:00:39'),
(576, 21, NULL, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.43', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', '2026-07-19 15:03:51'),
(577, 26, 14, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.43', NULL, '2026-07-19 15:04:23'),
(578, 26, 14, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.43', NULL, '2026-07-19 15:04:51'),
(579, 26, 14, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.43', NULL, '2026-07-19 15:05:06'),
(580, 26, 14, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.43', NULL, '2026-07-19 15:05:14'),
(581, 26, 14, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.43', NULL, '2026-07-19 15:09:38'),
(582, 26, 14, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.43', NULL, '2026-07-19 15:12:50'),
(583, 29, 14, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.43', NULL, '2026-07-19 15:15:18'),
(584, 31, 14, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.43', NULL, '2026-07-19 15:16:45'),
(585, 30, 15, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.43', NULL, '2026-07-19 15:17:35'),
(586, 29, 14, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.43', NULL, '2026-07-19 15:18:37'),
(587, 26, 14, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.43', NULL, '2026-07-19 16:09:47'),
(588, 26, 14, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.43', NULL, '2026-07-19 16:09:57'),
(589, 26, 14, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.43', NULL, '2026-07-19 16:10:04'),
(590, 26, 14, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.43', NULL, '2026-07-19 16:10:19'),
(591, 26, NULL, 'password_change', 'Password changed successfully', NULL, NULL, NULL, NULL, '2026-07-19 16:17:07'),
(592, 27, NULL, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.43', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', '2026-07-19 16:27:22'),
(593, 26, 14, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.43', NULL, '2026-07-19 16:28:26'),
(594, 26, 14, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.43', NULL, '2026-07-19 16:38:35'),
(595, 27, NULL, 'logout', 'User logged out successfully', NULL, NULL, '197.210.70.43', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', '2026-07-19 16:54:49'),
(596, 26, 14, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.43', NULL, '2026-07-19 16:55:40'),
(597, 26, NULL, 'password_change', 'Password changed successfully', NULL, NULL, NULL, NULL, '2026-07-19 16:56:06'),
(598, 31, 14, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.43', NULL, '2026-07-19 16:56:41'),
(599, 29, 14, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.43', NULL, '2026-07-19 16:57:16'),
(600, 30, 15, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.43', NULL, '2026-07-19 16:57:43'),
(601, 26, 14, 'login', 'User logged in successfully', NULL, NULL, '197.210.70.43', NULL, '2026-07-19 17:04:48'),
(602, 26, 14, 'login', 'User logged in successfully', NULL, NULL, '102.91.77.164', NULL, '2026-07-20 19:18:32'),
(603, 27, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.103.170', '9b9bd6260fcaddd147a31695595902b936b448e2f0b8007640f93a97ecd67767', '2026-07-23 15:46:28'),
(604, 27, NULL, 'broadcast_created', 'Created broadcast: aasd (ID: 15)', 'broadcasts', 15, '102.91.103.170', '9b9bd6260fcaddd147a31695595902b936b448e2f0b8007640f93a97ecd67767', '2026-07-23 16:20:14'),
(605, 27, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', '2026-07-24 13:33:17'),
(606, 27, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', '2026-07-24 14:03:27'),
(607, 27, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', '2026-07-24 14:27:33'),
(608, 21, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', '2026-07-24 14:28:14'),
(609, 21, NULL, 'user_created', 'Created user: Aliyu Abubakar (ID: 32)', NULL, NULL, '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', '2026-07-24 14:29:16'),
(610, 21, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', '2026-07-24 14:29:22'),
(611, 32, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', '2026-07-24 14:29:25'),
(612, 32, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', '2026-07-24 14:29:30'),
(613, 27, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', '2026-07-24 14:29:40'),
(614, 27, NULL, 'volunteer_assigned', 'Assigned volunteer: Aliyu Abubakar (ID: 32) to PU: KANGIRE PRI. SCH. II. (ID: 26)', 'user', 32, '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', '2026-07-24 15:38:17'),
(615, 27, NULL, 'task_assigned', 'Assigned task to volunteer ID: 32 - Good', 'volunteer_tasks', 1, '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', '2026-07-24 15:38:57'),
(616, 27, NULL, 'task_status_updated', 'Updated task ID: 1 from \'pending\' to \'in_progress\'', 'volunteer_tasks', 1, '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', '2026-07-24 16:01:46'),
(617, 27, NULL, 'task_status_updated', 'Updated task ID: 1 from \'in_progress\' to \'completed\'', 'volunteer_tasks', 1, '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', '2026-07-24 16:02:16'),
(618, 27, NULL, 'volunteer_assigned', 'Assigned volunteer: Aliyu Abubakar (ID: 32) to PU: FIRYA (ID: 28)', 'user', 32, '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', '2026-07-24 16:03:54'),
(619, 27, NULL, 'agent_reassigned', 'Reassigned agent ID: 26 to PU: 17', 'user', 26, '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', '2026-07-24 16:06:47'),
(620, 27, NULL, 'agent_reassigned', 'Reassigned agent ID: 26 to PU: 12', 'user', 26, '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', '2026-07-24 16:07:12'),
(621, 27, NULL, 'agent_assigned', 'Assigned agent: Aliyu Abubakar (ID: 26) to PU: KANGIRE GABAS/KANGIRE FILI (ID: 3)', 'user', 26, '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', '2026-07-24 16:20:26'),
(622, 27, NULL, 'chat_message', 'Sent message to Aliyu Abubakar (ID: 26)', 'chat', 1, '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', '2026-07-24 16:39:45'),
(623, 27, NULL, 'chat_message', 'Sent message to Aliyu Abubakar (ID: 32)', 'chat', 2, '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', '2026-07-24 16:44:52'),
(624, 27, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', '2026-07-24 16:49:15'),
(625, 27, NULL, 'chat_message', 'Sent message to Aliyu Abubakar (ID: 26)', 'chat', 1, '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', '2026-07-24 16:53:13'),
(626, 26, 14, 'login', 'User logged in successfully', NULL, NULL, '102.91.104.59', NULL, '2026-07-24 16:59:20'),
(627, 27, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', '2026-07-24 17:27:55'),
(628, 26, 14, 'login', 'User logged in successfully', NULL, NULL, '102.91.104.59', NULL, '2026-07-24 17:38:32'),
(629, 26, 14, 'login', 'User logged in successfully', NULL, NULL, '102.91.104.59', NULL, '2026-07-24 17:52:05'),
(630, 27, NULL, 'chat_message', 'Sent message to Aliyu Abubakar (ID: 26)', 'chat', 1, '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', '2026-07-24 17:56:59'),
(631, 26, 14, 'login', 'User logged in successfully', NULL, NULL, '102.91.104.59', NULL, '2026-07-24 18:01:58'),
(632, 27, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', '2026-07-24 18:10:28'),
(633, 27, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', '2026-07-24 18:10:45'),
(634, 27, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.105.161', '9161a7e307dc2c1fa53c878bc0f2367a061e14095e5735af1246b23b0b22dcac', '2026-07-24 18:31:49'),
(635, 27, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.105.161', '9161a7e307dc2c1fa53c878bc0f2367a061e14095e5735af1246b23b0b22dcac', '2026-07-24 18:32:04'),
(636, 29, 14, 'login', 'User logged in successfully', NULL, NULL, '102.91.105.161', NULL, '2026-07-24 18:32:27'),
(637, 27, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.105.161', '9161a7e307dc2c1fa53c878bc0f2367a061e14095e5735af1246b23b0b22dcac', '2026-07-24 18:34:25'),
(638, 27, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.105.161', '9161a7e307dc2c1fa53c878bc0f2367a061e14095e5735af1246b23b0b22dcac', '2026-07-24 18:34:44'),
(639, 29, 14, 'login', 'User logged in successfully', NULL, NULL, '102.91.105.161', NULL, '2026-07-24 18:35:34'),
(640, 27, NULL, 'chat_message', 'Sent message to Aliyu Abubakar (ID: 29)', 'chat', 3, '102.91.105.161', '9161a7e307dc2c1fa53c878bc0f2367a061e14095e5735af1246b23b0b22dcac', '2026-07-24 18:36:30'),
(641, 27, NULL, 'chat_message', 'Sent message to Aliyu Abubakar (ID: 29)', 'chat', 3, '102.91.105.161', '9161a7e307dc2c1fa53c878bc0f2367a061e14095e5735af1246b23b0b22dcac', '2026-07-24 18:37:22'),
(642, 27, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.92.216', 'f5828db79f1e3f5bac52690d9499d62aa54315ca12caaf090ed03ab01cd751d6', '2026-07-25 20:19:51'),
(643, 26, 14, 'login', 'User logged in successfully', NULL, NULL, '102.91.92.216', NULL, '2026-07-25 20:40:29'),
(644, 27, NULL, 'chat_message', 'Sent message to Aliyu Abubakar (ID: 26)', 'chat', 1, '102.91.92.216', 'f5828db79f1e3f5bac52690d9499d62aa54315ca12caaf090ed03ab01cd751d6', '2026-07-25 20:43:49'),
(645, 27, NULL, 'chat_message', 'Sent message to Aliyu Abubakar (ID: 26)', 'chat', 1, '102.91.92.216', 'f5828db79f1e3f5bac52690d9499d62aa54315ca12caaf090ed03ab01cd751d6', '2026-07-25 20:44:04'),
(646, 27, NULL, 'chat_message', 'Sent message to Aliyu Abubakar (ID: 26)', 'chat', 1, '102.91.92.216', 'f5828db79f1e3f5bac52690d9499d62aa54315ca12caaf090ed03ab01cd751d6', '2026-07-25 20:45:18'),
(647, 27, NULL, 'chat_message', 'Sent message to Aliyu Abubakar (ID: 26)', 'chat', 1, '102.91.92.216', 'f5828db79f1e3f5bac52690d9499d62aa54315ca12caaf090ed03ab01cd751d6', '2026-07-25 20:46:02'),
(648, 27, NULL, 'chat_message', 'Sent message to Aliyu Abubakar (ID: 26)', 'chat', 1, '102.91.92.216', 'f5828db79f1e3f5bac52690d9499d62aa54315ca12caaf090ed03ab01cd751d6', '2026-07-25 20:53:37'),
(649, 27, NULL, 'chat_message', 'Sent message to Aliyu Abubakar (ID: 26)', 'chat', 1, '102.91.92.216', 'f5828db79f1e3f5bac52690d9499d62aa54315ca12caaf090ed03ab01cd751d6', '2026-07-25 20:54:04'),
(650, 27, NULL, 'login', 'User logged in successfully', NULL, NULL, '197.210.53.133', '94f663f7190b5b7a6c9ace22e788bb55e7bdf3a7bc8e8df84cf12df3aa3f377f', '2026-07-26 11:04:06'),
(651, 27, NULL, 'login', 'User logged in successfully', NULL, NULL, '197.210.53.133', '94f663f7190b5b7a6c9ace22e788bb55e7bdf3a7bc8e8df84cf12df3aa3f377f', '2026-07-26 11:05:14'),
(652, 29, 14, 'login', 'User logged in successfully', NULL, NULL, '197.210.53.133', NULL, '2026-07-26 11:06:02'),
(653, 26, 14, 'login', 'User logged in successfully', NULL, NULL, '197.210.53.133', NULL, '2026-07-26 11:09:40'),
(654, 26, 14, 'login', 'User logged in successfully', NULL, NULL, '197.210.53.133', NULL, '2026-07-26 11:16:24'),
(655, 27, NULL, 'login', 'User logged in successfully', NULL, NULL, '197.210.53.133', '94f663f7190b5b7a6c9ace22e788bb55e7bdf3a7bc8e8df84cf12df3aa3f377f', '2026-07-26 11:28:46'),
(656, 26, 14, 'login', 'User logged in successfully', NULL, NULL, '197.210.53.133', NULL, '2026-07-26 11:29:22'),
(657, 27, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', '2026-07-27 10:05:39'),
(658, 27, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', '2026-07-27 10:06:38'),
(659, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', '2026-07-27 10:07:01'),
(660, 7, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', '2026-07-27 12:26:40'),
(661, 7, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', '2026-07-27 12:29:43'),
(662, 21, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', '2026-07-27 12:30:15'),
(663, 21, NULL, 'user_created', 'Created user: federal cons (ID: 33) with role: Federal Constituency Coordinator', NULL, NULL, '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', '2026-07-27 12:56:05'),
(664, 21, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', '2026-07-27 12:56:11'),
(665, 33, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', '2026-07-27 12:56:16'),
(666, 33, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', '2026-07-27 12:56:49'),
(667, 27, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', '2026-07-27 12:57:15'),
(668, 27, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', '2026-07-27 12:57:21'),
(669, 21, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', '2026-07-27 12:58:39'),
(670, 21, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', '2026-07-27 13:02:35'),
(671, 21, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', '2026-07-27 13:03:33'),
(672, 21, NULL, 'user_created', 'Created user: senatorial cood (ID: 34) with role: Senatorial Coordinator', NULL, NULL, '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', '2026-07-27 13:06:44'),
(673, 21, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', '2026-07-27 13:06:50'),
(674, 34, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', '2026-07-27 13:08:52'),
(675, 34, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', '2026-07-27 13:14:11'),
(676, 27, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', '2026-07-27 13:14:46'),
(677, 27, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', '2026-07-27 13:14:54'),
(678, 21, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', '2026-07-27 13:15:32'),
(679, 21, NULL, '2fa_disabled', '2FA disabled', NULL, NULL, '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', '2026-07-27 13:16:21'),
(680, 21, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', '2026-07-27 13:16:33'),
(681, 21, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', '2026-07-27 13:16:37'),
(682, 21, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', '2026-07-27 13:31:26'),
(683, 34, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', '2026-07-27 13:31:35'),
(684, 34, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', '2026-07-27 13:38:49'),
(685, 21, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', '2026-07-27 13:39:01'),
(686, 21, NULL, 'user_updated', 'Updated user: senatorial cood (ID: 34)', NULL, NULL, '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', '2026-07-27 13:39:35'),
(687, 21, NULL, 'logout', 'User logged out successfully', NULL, NULL, '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', '2026-07-27 13:39:44'),
(688, 34, NULL, 'login', 'User logged in successfully', NULL, NULL, '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', '2026-07-27 13:39:57');

-- --------------------------------------------------------

--
-- Table structure for table `agent_assignments`
--

CREATE TABLE `agent_assignments` (
  `id` bigint UNSIGNED NOT NULL,
  `tenant_id` bigint UNSIGNED NOT NULL,
  `election_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `pu_id` int UNSIGNED NOT NULL,
  `ward_id` int UNSIGNED NOT NULL,
  `lga_id` int UNSIGNED NOT NULL,
  `state_id` int UNSIGNED NOT NULL,
  `assignment_type` enum('data_agent','party_agent','volunteer','observer') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','active','completed','suspended','reassigned') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `assigned_by` bigint UNSIGNED NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `agent_assignments`
--

INSERT INTO `agent_assignments` (`id`, `tenant_id`, `election_id`, `user_id`, `pu_id`, `ward_id`, `lga_id`, `state_id`, `assignment_type`, `status`, `assigned_by`, `assigned_at`, `completed_at`, `notes`) VALUES
(1, 14, 11, 24, 28, 1, 1, 1, 'party_agent', 'reassigned', 21, '2026-07-09 22:11:42', NULL, ''),
(2, 14, 11, 24, 22, 1, 1, 1, 'party_agent', 'pending', 21, '2026-07-09 22:12:35', NULL, ''),
(3, 14, 15, 30, 26, 1, 1, 1, 'volunteer', 'pending', 21, '2026-07-16 09:34:16', NULL, ''),
(4, 14, 11, 32, 26, 1, 1, 1, 'volunteer', 'active', 27, '2026-07-24 15:38:17', NULL, ''),
(5, 14, 11, 32, 28, 1, 1, 1, 'volunteer', 'active', 27, '2026-07-24 16:03:54', NULL, ''),
(6, 14, 11, 26, 17, 1, 1, 1, 'data_agent', 'reassigned', 27, '2026-07-24 16:06:47', NULL, ''),
(7, 14, 11, 26, 12, 1, 1, 1, 'data_agent', 'reassigned', 27, '2026-07-24 16:07:12', NULL, ''),
(8, 14, 11, 26, 3, 1, 1, 1, 'data_agent', 'active', 27, '2026-07-24 16:20:26', NULL, '');

-- --------------------------------------------------------

--
-- Table structure for table `agent_checkins`
--

CREATE TABLE `agent_checkins` (
  `id` bigint UNSIGNED NOT NULL,
  `tenant_id` bigint UNSIGNED NOT NULL,
  `election_id` bigint UNSIGNED NOT NULL,
  `agent_id` bigint UNSIGNED NOT NULL,
  `assignment_id` bigint UNSIGNED NOT NULL,
  `pu_id` int UNSIGNED NOT NULL,
  `checkin_type` enum('arrival','departure','material_received','accreditation_started','voting_started','voting_ended','counting_started','counting_ended') COLLATE utf8mb4_unicode_ci NOT NULL,
  `gps_lat` decimal(10,8) NOT NULL,
  `gps_lng` decimal(11,8) NOT NULL,
  `gps_accuracy` decimal(6,2) DEFAULT NULL,
  `gps_distance_from_pu` decimal(8,2) DEFAULT NULL,
  `photo_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_battery` tinyint UNSIGNED DEFAULT NULL,
  `network_type` enum('2g','3g','4g','5g','wifi','none') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_offline_sync` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `agent_payments`
--

CREATE TABLE `agent_payments` (
  `id` bigint UNSIGNED NOT NULL,
  `tenant_id` bigint UNSIGNED NOT NULL,
  `election_id` bigint UNSIGNED NOT NULL,
  `agent_id` bigint UNSIGNED NOT NULL,
  `assignment_id` bigint UNSIGNED NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_type` enum('advance','daily_allowance','completion_bonus','transport','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_method` enum('cash','bank_transfer','mobile_money') COLLATE utf8mb4_unicode_ci NOT NULL,
  `bank_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','processing','paid','failed','refunded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `paid_at` timestamp NULL DEFAULT NULL,
  `paid_by` bigint UNSIGNED DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `api_keys`
--

CREATE TABLE `api_keys` (
  `id` bigint UNSIGNED NOT NULL,
  `tenant_id` bigint UNSIGNED DEFAULT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key_prefix` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `permissions_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `rate_limit` int UNSIGNED NOT NULL DEFAULT '1000',
  `rate_limit_window` int UNSIGNED NOT NULL DEFAULT '3600',
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Table structure for table `api_logs`
--

CREATE TABLE `api_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `api_key_id` bigint UNSIGNED DEFAULT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `tenant_id` bigint UNSIGNED DEFAULT NULL,
  `method` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `endpoint` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_body` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `response_status` smallint UNSIGNED DEFAULT NULL,
  `response_body` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `duration_ms` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `tenant_id` bigint UNSIGNED DEFAULT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` bigint UNSIGNED DEFAULT NULL,
  `old_values_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `new_values_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `device_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gps_lat` decimal(10,8) DEFAULT NULL,
  `gps_lng` decimal(11,8) DEFAULT NULL,
  `severity` enum('info','warning','error','critical') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Table structure for table `backups`
--

CREATE TABLE `backups` (
  `id` bigint UNSIGNED NOT NULL,
  `tenant_id` bigint UNSIGNED DEFAULT NULL,
  `backup_type` enum('full','database','files','tenant_data') COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` bigint UNSIGNED NOT NULL,
  `file_sha256` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending','in_progress','completed','failed','restored') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `restored_at` timestamp NULL DEFAULT NULL,
  `restored_by` bigint UNSIGNED DEFAULT NULL,
  `created_by` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
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
  `id` bigint UNSIGNED NOT NULL,
  `tenant_id` bigint UNSIGNED NOT NULL,
  `election_id` bigint UNSIGNED DEFAULT NULL,
  `sender_id` bigint UNSIGNED NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_audience` enum('all','national','state','senatorial','federal_constituency','lga','ward','pu','role_specific') COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_ids_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `target_role_id` bigint UNSIGNED DEFAULT NULL,
  `send_via` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `status` enum('draft','scheduled','sending','sent','failed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `read_count` int UNSIGNED NOT NULL DEFAULT '0',
  `total_recipients` int UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

--
-- Dumping data for table `broadcasts`
--

INSERT INTO `broadcasts` (`id`, `tenant_id`, `election_id`, `sender_id`, `title`, `message`, `target_audience`, `target_ids_json`, `target_role_id`, `send_via`, `scheduled_at`, `sent_at`, `status`, `read_count`, `total_recipients`, `created_at`) VALUES
(9, 14, NULL, 24, 'aaadsds', 'dsfgrg', 'state', '[1]', NULL, '[\"email\",\"in_app\"]', NULL, '2026-07-10 00:28:46', 'sent', 0, 0, '2026-07-10 00:28:46'),
(10, 14, NULL, 24, 'aaa', 'asass', 'all', '[1]', NULL, '[\"email\",\"in_app\"]', NULL, '2026-07-10 00:29:07', 'sent', 0, 0, '2026-07-10 00:29:07'),
(11, 14, NULL, 24, 'aqdfddfgf', 'sdfbndfb', 'all', '[1]', NULL, '[\"email\",\"in_app\"]', NULL, '2026-07-10 00:52:01', 'sent', 0, 3, '2026-07-10 00:50:58'),
(12, 14, NULL, 24, 'TTTddv', 'dnbcdbc', 'lga', NULL, NULL, '[\"email\"]', NULL, '2026-07-10 09:52:57', 'failed', 0, 0, '2026-07-10 09:52:57'),
(13, 14, NULL, 27, 'hello', 'how are', 'role_specific', '[26]', NULL, '[\"email\"]', NULL, '2026-07-19 11:37:23', 'sent', 0, 1, '2026-07-19 11:37:23'),
(14, 14, NULL, 27, 'hello', 'how are', 'role_specific', '[26]', NULL, '[\"email\"]', NULL, '2026-07-19 11:37:48', 'sent', 0, 1, '2026-07-19 11:37:48'),
(15, 14, NULL, 27, 'aasd', 'dwdwe', 'all', NULL, NULL, '[\"email\",\"in_app\"]', NULL, '2026-07-23 16:20:23', 'sent', 0, 10, '2026-07-23 16:20:14');

-- --------------------------------------------------------

--
-- Table structure for table `budgets`
--

CREATE TABLE `budgets` (
  `id` bigint UNSIGNED NOT NULL,
  `tenant_id` bigint UNSIGNED NOT NULL,
  `election_id` bigint UNSIGNED DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `spent_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `remaining_amount` decimal(15,2) GENERATED ALWAYS AS ((`total_amount` - `spent_amount`)) STORED,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('draft','active','closed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_by` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `candidates`
--

CREATE TABLE `candidates` (
  `id` bigint UNSIGNED NOT NULL,
  `tenant_id` bigint UNSIGNED NOT NULL,
  `election_id` bigint UNSIGNED NOT NULL,
  `party_id` bigint UNSIGNED DEFAULT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(200) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (concat(`first_name`,_utf8mb4' ',`last_name`)) STORED,
  `photograph_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `biography` text COLLATE utf8mb4_unicode_ci,
  `manifesto` text COLLATE utf8mb4_unicode_ci,
  `contact_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `social_media_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `campaign_logo_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

--
-- Dumping data for table `candidates`
--

INSERT INTO `candidates` (`id`, `tenant_id`, `election_id`, `party_id`, `first_name`, `last_name`, `photograph_url`, `position`, `biography`, `manifesto`, `contact_email`, `contact_phone`, `social_media_json`, `campaign_logo_url`, `is_active`, `created_at`) VALUES
(2, 14, 11, NULL, 'Aliyu', 'Boss', NULL, 'Governor', '', NULL, 'boss@gmail.com', '89766', NULL, NULL, 1, '2026-07-09 15:42:17');

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` bigint UNSIGNED NOT NULL,
  `room_id` bigint UNSIGNED NOT NULL,
  `sender_id` bigint UNSIGNED NOT NULL,
  `receiver_id` bigint UNSIGNED DEFAULT NULL,
  `message_type` enum('text','image','video','audio','file','location','system') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `media_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `media_size` bigint UNSIGNED DEFAULT NULL,
  `media_sha256` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `read_at` timestamp NULL DEFAULT NULL,
  `gps_lat` decimal(10,8) DEFAULT NULL,
  `gps_lng` decimal(11,8) DEFAULT NULL,
  `is_offline_sync` tinyint(1) NOT NULL DEFAULT '0',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`id`, `room_id`, `sender_id`, `receiver_id`, `message_type`, `content`, `media_url`, `media_size`, `media_sha256`, `is_read`, `read_at`, `gps_lat`, `gps_lng`, `is_offline_sync`, `is_deleted`, `created_at`) VALUES
(1, 1, 29, 2, 'text', 'hello', NULL, NULL, NULL, 0, NULL, NULL, NULL, 0, 0, '2026-07-15 22:01:12'),
(2, 1, 29, 2, 'text', '', NULL, NULL, NULL, 0, NULL, NULL, NULL, 0, 0, '2026-07-15 22:01:23'),
(3, 1, 31, 2, 'text', 'okay i observ', NULL, NULL, NULL, 0, NULL, NULL, NULL, 0, 0, '2026-07-16 10:17:59'),
(4, 1, 30, 2, 'text', 'hello', NULL, NULL, NULL, 0, NULL, NULL, NULL, 0, 0, '2026-07-19 12:00:45'),
(5, 1, 27, 26, 'text', 'hi', '', NULL, NULL, 1, '2026-07-24 17:56:03', NULL, NULL, 0, 0, '2026-07-24 16:39:45'),
(6, 2, 27, 32, 'text', 'hi', '', NULL, NULL, 0, NULL, NULL, NULL, 0, 0, '2026-07-24 16:44:52'),
(7, 1, 27, 26, 'location', '📍 Location: 10.470059, 9.830564\r\nhttps://maps.google.com/?q=10.470059,9.830564', '', NULL, NULL, 1, '2026-07-24 17:56:03', NULL, NULL, 0, 0, '2026-07-24 16:53:13'),
(8, 1, 26, 27, 'text', 'how are you', '', NULL, NULL, 1, '2026-07-24 17:56:43', NULL, NULL, 0, 0, '2026-07-24 17:56:34'),
(9, 1, 27, 26, 'text', 'i am fine', '', NULL, NULL, 1, '2026-07-24 17:57:11', NULL, NULL, 0, 0, '2026-07-24 17:56:59'),
(10, 1, 26, 27, 'text', 'Can you see this message normally?', '', NULL, NULL, 1, '2026-07-24 17:59:05', NULL, NULL, 0, 0, '2026-07-24 17:58:59'),
(11, 1, 26, 27, 'text', '📍 Location shared', '', NULL, NULL, 1, '2026-07-24 18:11:13', NULL, NULL, 0, 0, '2026-07-24 18:10:54'),
(12, 3, 27, 29, 'text', 'Hello', '', NULL, NULL, 1, '2026-07-24 18:36:47', NULL, NULL, 0, 0, '2026-07-24 18:36:30'),
(13, 3, 29, 27, 'text', 'hi', '', NULL, NULL, 1, '2026-07-24 18:37:03', NULL, NULL, 0, 0, '2026-07-24 18:36:58'),
(14, 3, 27, 29, 'location', '📍 Location: 9.056700, 7.496900\r\nhttps://maps.google.com/?q=9.056700,7.496900', '', NULL, NULL, 1, '2026-07-24 18:37:37', NULL, NULL, 0, 0, '2026-07-24 18:37:22'),
(15, 3, 29, 27, 'text', '📍 Location shared', '', NULL, NULL, 1, '2026-07-24 18:37:37', NULL, NULL, 0, 0, '2026-07-24 18:37:29'),
(16, 1, 27, 26, 'location', '📍 Yali, Bauchi State: 10.470769, 9.830348', '', NULL, NULL, 1, '2026-07-25 20:45:23', NULL, NULL, 0, 0, '2026-07-25 20:43:49'),
(17, 1, 27, 26, 'text', '📍 Yali, Bauchi State: 10.470769, 9.830348', '', NULL, NULL, 1, '2026-07-25 20:45:23', NULL, NULL, 0, 0, '2026-07-25 20:44:04'),
(18, 1, 26, 27, 'text', 'Agent is typing', '', NULL, NULL, 1, '2026-07-25 20:44:36', NULL, NULL, 0, 0, '2026-07-25 20:44:34'),
(19, 1, 26, 27, 'text', 'hi', '', NULL, NULL, 1, '2026-07-25 20:45:12', NULL, NULL, 0, 0, '2026-07-25 20:45:11'),
(20, 1, 27, 26, 'text', 'hhh', '', NULL, NULL, 1, '2026-07-25 20:45:23', NULL, NULL, 0, 0, '2026-07-25 20:45:18'),
(21, 1, 27, 26, 'image', '', '/election/uploads/chat/1785012347_0ac4fc099d33d726.png', NULL, NULL, 0, NULL, NULL, NULL, 0, 0, '2026-07-25 20:46:02'),
(22, 1, 27, 26, 'file', '{\"url\":\"\\/election\\/uploads\\/chat\\/1785012815_4d9b2e3f12b978e0.png\",\"filename\":\"a6bce81ec9baf9009ab9603fc67dc510.png\",\"filesize\":1193480,\"filetype\":\"png\"}', '/election/uploads/chat/1785012815_4d9b2e3f12b978e0.png', NULL, NULL, 0, NULL, NULL, NULL, 0, 0, '2026-07-25 20:53:37'),
(23, 1, 27, 26, 'location', '📍 Yali, Bauchi State: 10.470769, 9.830348', '', NULL, NULL, 0, NULL, NULL, NULL, 0, 0, '2026-07-25 20:54:04');

-- --------------------------------------------------------

--
-- Table structure for table `chat_rooms`
--

CREATE TABLE `chat_rooms` (
  `id` bigint UNSIGNED NOT NULL,
  `tenant_id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('direct','group','broadcast') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'group',
  `election_id` bigint UNSIGNED DEFAULT NULL,
  `jurisdiction_type` enum('national','state','senatorial','federal_constituency','lga','ward','pu') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jurisdiction_id` bigint UNSIGNED DEFAULT NULL,
  `created_by` bigint UNSIGNED NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chat_rooms`
--

INSERT INTO `chat_rooms` (`id`, `tenant_id`, `name`, `type`, `election_id`, `jurisdiction_type`, `jurisdiction_id`, `created_by`, `is_active`, `created_at`) VALUES
(1, 14, 'Chat between Aliyu Abubakar and Aliyu Abubakar', 'direct', NULL, NULL, NULL, 27, 1, '2026-07-24 16:39:45'),
(2, 14, 'Chat between Aliyu Abubakar and Aliyu Abubakar', 'direct', NULL, NULL, NULL, 27, 1, '2026-07-24 16:44:52'),
(3, 14, 'Chat between Aliyu Abubakar and Aliyu Abubakar', 'direct', NULL, NULL, NULL, 27, 1, '2026-07-24 18:36:30');

-- --------------------------------------------------------

--
-- Table structure for table `chat_room_members`
--

CREATE TABLE `chat_room_members` (
  `id` bigint UNSIGNED NOT NULL,
  `room_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `role` enum('admin','member') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'member',
  `last_read_message_id` bigint UNSIGNED DEFAULT NULL,
  `joined_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chat_room_members`
--

INSERT INTO `chat_room_members` (`id`, `room_id`, `user_id`, `role`, `last_read_message_id`, `joined_at`) VALUES
(1, 1, 27, 'member', NULL, '2026-07-24 16:39:45'),
(2, 1, 26, 'member', NULL, '2026-07-24 16:39:45'),
(3, 2, 27, 'member', NULL, '2026-07-24 16:44:52'),
(4, 2, 32, 'member', NULL, '2026-07-24 16:44:52'),
(5, 3, 27, 'member', NULL, '2026-07-24 18:36:30'),
(6, 3, 29, 'member', NULL, '2026-07-24 18:36:30');

-- --------------------------------------------------------

--
-- Table structure for table `community_reports`
--

CREATE TABLE `community_reports` (
  `id` bigint UNSIGNED NOT NULL,
  `volunteer_id` bigint UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `video_url` varchar(500) DEFAULT NULL,
  `status` enum('draft','submitted','approved','rejected') DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `elections`
--

CREATE TABLE `elections` (
  `id` bigint UNSIGNED NOT NULL,
  `tenant_id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('presidential','governorship','senatorial','house_of_reps','house_of_assembly','lga_chairman','councillorship','party_primary','internal_party') COLLATE utf8mb4_unicode_ci NOT NULL,
  `cycle` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `election_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `states_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `lgas_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `wards_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `pus_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `status` enum('draft','upcoming','active','closed','cancelled','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `description` text COLLATE utf8mb4_unicode_ci,
  `logo_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `settings_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `created_by` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL
) ;

--
-- Dumping data for table `elections`
--

INSERT INTO `elections` (`id`, `tenant_id`, `name`, `type`, `cycle`, `election_date`, `start_time`, `end_time`, `states_json`, `lgas_json`, `wards_json`, `pus_json`, `status`, `description`, `logo_url`, `settings_json`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(11, 14, '2027 Governorship Election', 'governorship', '2031', '2026-07-09', '18:29:00', NULL, '[1]', '[]', '[\"1\"]', '[]', 'active', '', NULL, NULL, 7, '2026-07-09 14:31:57', '2026-07-24 15:14:39', NULL),
(12, 14, '2027 Governorship Election', 'governorship', '2031', '2026-07-31', NULL, NULL, '[1]', NULL, NULL, NULL, 'draft', '', NULL, NULL, 24, '2026-07-10 00:26:31', '2026-07-10 00:26:31', NULL),
(13, 14, '2027 Governorship Election', 'governorship', '2031', '2026-07-24', NULL, NULL, '[1]', NULL, NULL, NULL, 'draft', '', NULL, NULL, 24, '2026-07-10 00:27:09', '2026-07-10 00:27:09', NULL),
(14, 14, '2027 Governorship Election', 'house_of_reps', '2031', '2026-07-09', NULL, NULL, '[1]', NULL, NULL, NULL, 'draft', '', NULL, NULL, 24, '2026-07-10 00:41:38', '2026-07-10 00:41:38', NULL),
(15, 14, '2027 Governorship Election', 'governorship', '2031', '2026-07-11', NULL, NULL, '[1]', '[]', '[]', '[]', 'upcoming', '', NULL, NULL, 24, '2026-07-10 00:45:14', '2026-07-10 00:45:14', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `election_checklists`
--

CREATE TABLE `election_checklists` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `election_id` bigint UNSIGNED DEFAULT NULL,
  `pu_id` int UNSIGNED DEFAULT NULL,
  `materials_arrived` tinyint(1) NOT NULL DEFAULT '0',
  `poll_opened` tinyint(1) NOT NULL DEFAULT '0',
  `accreditation_started` tinyint(1) NOT NULL DEFAULT '0',
  `voting_started` tinyint(1) NOT NULL DEFAULT '0',
  `counting_started` tinyint(1) NOT NULL DEFAULT '0',
  `poll_closed` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('draft','submitted','completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `submitted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `election_checklists`
--

INSERT INTO `election_checklists` (`id`, `user_id`, `election_id`, `pu_id`, `materials_arrived`, `poll_opened`, `accreditation_started`, `voting_started`, `counting_started`, `poll_closed`, `status`, `submitted_at`, `created_at`, `updated_at`) VALUES
(1, 26, NULL, NULL, 0, 0, 0, 0, 1, 0, 'submitted', '2026-07-15 22:50:04', '2026-07-15 22:50:00', '2026-07-15 22:50:04');

-- --------------------------------------------------------

--
-- Table structure for table `election_materials`
--

CREATE TABLE `election_materials` (
  `id` bigint UNSIGNED NOT NULL,
  `tenant_id` bigint UNSIGNED NOT NULL,
  `election_id` bigint UNSIGNED NOT NULL,
  `pu_id` int UNSIGNED NOT NULL,
  `agent_id` bigint UNSIGNED NOT NULL,
  `material_type` enum('ballot_papers','result_sheets','stamp','ink','bvas','generator','tent','chairs','tables','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity_received` int UNSIGNED NOT NULL DEFAULT '0',
  `quantity_used` int UNSIGNED NOT NULL DEFAULT '0',
  `quantity_damaged` int UNSIGNED NOT NULL DEFAULT '0',
  `quantity_returned` int UNSIGNED NOT NULL DEFAULT '0',
  `condition` enum('excellent','good','fair','poor','missing') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `election_polling_units`
--

CREATE TABLE `election_polling_units` (
  `id` bigint UNSIGNED NOT NULL,
  `election_id` bigint UNSIGNED NOT NULL,
  `pu_id` int UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` bigint UNSIGNED NOT NULL,
  `tenant_id` bigint UNSIGNED NOT NULL,
  `budget_id` bigint UNSIGNED NOT NULL,
  `election_id` bigint UNSIGNED DEFAULT NULL,
  `category` enum('agent_payment','transport','materials','logistics','security','communication','media','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `receipt_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paid_to_user_id` bigint UNSIGNED DEFAULT NULL,
  `paid_by_user_id` bigint UNSIGNED NOT NULL,
  `payment_method` enum('cash','bank_transfer','mobile_money','cheque','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','approved','rejected','paid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `approved_by` bigint UNSIGNED DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `gps_lat` decimal(10,8) DEFAULT NULL,
  `gps_lng` decimal(11,8) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `federal_constituencies`
--

CREATE TABLE `federal_constituencies` (
  `id` int UNSIGNED NOT NULL,
  `state_id` int UNSIGNED NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lgas_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ;

--
-- Dumping data for table `federal_constituencies`
--

INSERT INTO `federal_constituencies` (`id`, `state_id`, `code`, `name`, `lgas_json`, `is_active`) VALUES
(1, 1, 'FC01', 'Babura/Garki', '[\"Babura\",\"Garki\"]', 1),
(2, 1, 'FC02', 'Gumel/Maigatari/Gagarawa/Sule Tankarkar', '[\"Gumel\",\"Maigatari\",\"Gagarawa\",\"Sule Tankarkar\"]', 1),
(3, 1, 'FC03', 'Kazaure/Roni/Gwiwa/Yankwashi', '[\"Kazaure\",\"Roni\",\"Gwiwa\",\"Yankwashi\"]', 1),
(4, 1, 'FC04', 'Ringim/Taura', '[\"Ringim\",\"Taura\"]', 1),
(5, 1, 'FC05', 'Hadejia/Kafin Hausa', '[\"Hadejia\",\"Kafin Hausa\"]', 1),
(6, 1, 'FC06', 'Mallam Madori/Kaugama', '[\"Mallam Madori\",\"Kaugama\"]', 1),
(7, 1, 'FC07', 'Birnin Kudu/Buji', '[\"Birnin Kudu\",\"Buji\"]', 1),
(8, 1, 'FC08', 'Birniwa/Guri/Kirikasamma', '[\"Birniwa\",\"Guri\",\"Kirikasamma\"]', 1),
(9, 1, 'FC09', 'Dutse/Kiyawa', '[\"Dutse\",\"Kiyawa\"]', 1),
(10, 1, 'FC10', 'Gwaram', '[\"Gwaram\"]', 1),
(11, 1, 'FC11', 'Jahun/Miga', '[\"Jahun\",\"Miga\"]', 1);

-- --------------------------------------------------------

--
-- Table structure for table `incidents`
--

CREATE TABLE `incidents` (
  `id` bigint UNSIGNED NOT NULL,
  `tenant_id` bigint UNSIGNED NOT NULL,
  `election_id` bigint UNSIGNED DEFAULT NULL,
  `reporter_id` bigint UNSIGNED NOT NULL,
  `pu_id` int UNSIGNED DEFAULT NULL,
  `ward_id` int UNSIGNED DEFAULT NULL,
  `lga_id` int UNSIGNED DEFAULT NULL,
  `state_id` int UNSIGNED DEFAULT NULL,
  `incident_type` enum('violence','intimidation','ballot_stuffing','vote_buying','voter_suppression','material_shortage','delay','technical_issue','other','panic_button') COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` enum('low','medium','high','critical') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `is_panic` tinyint(1) NOT NULL DEFAULT '0',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `gps_lat` decimal(10,8) DEFAULT NULL,
  `gps_lng` decimal(11,8) DEFAULT NULL,
  `gps_accuracy` decimal(6,2) DEFAULT NULL,
  `photo_urls_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `video_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `audio_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('reported','acknowledged','investigating','resolved','escalated','false_alarm') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'reported',
  `assigned_to` bigint UNSIGNED DEFAULT NULL,
  `resolved_by` bigint UNSIGNED DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolution_notes` text COLLATE utf8mb4_unicode_ci,
  `is_offline_sync` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

--
-- Dumping data for table `incidents`
--

INSERT INTO `incidents` (`id`, `tenant_id`, `election_id`, `reporter_id`, `pu_id`, `ward_id`, `lga_id`, `state_id`, `incident_type`, `severity`, `is_panic`, `title`, `description`, `gps_lat`, `gps_lng`, `gps_accuracy`, `photo_urls_json`, `video_url`, `audio_url`, `device_id`, `status`, `assigned_to`, `resolved_by`, `resolved_at`, `resolution_notes`, `is_offline_sync`, `created_at`, `updated_at`) VALUES
(1, 14, NULL, 24, 18, 1, 1, 1, 'violence', 'medium', 1, 'Violence', 'in turewa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'resolved', NULL, 24, '2026-07-10 00:53:04', 'sddfdbfnd', 0, '2026-07-10 00:43:15', '2026-07-10 00:53:04');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` bigint UNSIGNED NOT NULL,
  `tenant_id` bigint UNSIGNED NOT NULL,
  `subscription_id` bigint UNSIGNED DEFAULT NULL,
  `invoice_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `tax_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(15,2) NOT NULL,
  `status` enum('draft','sent','paid','overdue','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `due_date` date NOT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `paid_by` bigint UNSIGNED DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `tenant_id`, `subscription_id`, `invoice_number`, `amount`, `tax_amount`, `total_amount`, `status`, `due_date`, `paid_at`, `paid_by`, `notes`, `created_at`) VALUES
(3, 14, NULL, 'INV-2026-63265', 1000000.00, 10000.00, 1010000.00, 'sent', '2026-08-08', NULL, NULL, '', '2026-07-09 15:12:09');

-- --------------------------------------------------------

--
-- Table structure for table `lgas`
--

CREATE TABLE `lgas` (
  `id` int UNSIGNED NOT NULL,
  `state_id` int UNSIGNED NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gps_lat` decimal(10,8) DEFAULT NULL,
  `gps_lng` decimal(11,8) DEFAULT NULL,
  `registered_voters` int UNSIGNED NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lgas`
--

INSERT INTO `lgas` (`id`, `state_id`, `code`, `name`, `gps_lat`, `gps_lng`, `registered_voters`, `is_active`) VALUES
(1, 1, 'BK', 'Birnin Kudu', NULL, NULL, 0, 1);

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `attempt_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'login',
  `success` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
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
(58, NULL, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 0, '2026-07-03 23:05:42'),
(62, 7, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-03 23:24:07'),
(88, NULL, 'aliyuabubakarjdh@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 0, '2026-07-05 09:25:02'),
(96, 7, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-05 09:46:18'),
(97, 7, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-05 09:46:42'),
(99, 7, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-05 09:55:56'),
(100, 7, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-06 17:29:54'),
(102, 7, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-07 18:33:45'),
(106, 7, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-09 13:39:56'),
(107, 7, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-09 13:41:01'),
(110, 7, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-09 13:58:39'),
(111, NULL, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 0, '2026-07-09 14:07:41'),
(112, 7, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-09 14:07:48'),
(113, 21, 'lubunaaliyuabk@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-09 14:19:22'),
(114, 7, 'aliyuabubakar11117@gmail.com', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-09 14:19:33'),
(115, NULL, 'aliyuabubakar11117@gmail.com', '10.59.66.251', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 0, '2026-07-09 14:27:30'),
(116, NULL, 'aliyuabubakar11117@gmail.com', '10.59.66.251', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 0, '2026-07-09 14:27:54'),
(117, 7, 'aliyuabubakar11117@gmail.com', '10.59.66.251', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-09 14:28:16'),
(118, NULL, 'zaproxy@example.com', '10.59.66.251', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'login', 0, '2026-07-09 14:40:49'),
(119, NULL, 'zaproxy@example.com', '10.59.66.251', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'login', 0, '2026-07-09 14:40:50'),
(120, NULL, 'c:/Windows/system.ini', '10.59.66.251', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'login', 0, '2026-07-09 14:41:03'),
(121, 21, 'lubunaaliyuabk@gmail.com', '10.59.66.251', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-09 15:14:32'),
(122, NULL, 'lubunaaliyuabk@gmail.com', '10.59.66.251', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 0, '2026-07-09 15:32:11'),
(123, 21, 'lubunaaliyuabk@gmail.com', '10.59.66.251', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-09 15:34:10'),
(124, 21, 'lubunaaliyuabk@gmail.com', '10.59.66.251', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-09 21:35:07'),
(125, NULL, 'aliyuabubakardh@gmail.com', '10.59.66.251', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 0, '2026-07-09 22:13:32'),
(126, 21, 'lubunaaliyuabk@gmail.com', '10.59.66.251', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-09 22:14:24'),
(127, 22, 'aliyuabubakardh@gmail.com', '10.59.66.251', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-09 22:15:20'),
(128, 21, 'lubunaaliyuabk@gmail.com', '10.59.66.251', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-09 22:23:37'),
(129, 22, 'aliyuabubakardh@gmail.com', '10.59.66.251', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-09 22:24:15'),
(130, 22, 'aliyuabubakardh@gmail.com', '10.59.66.251', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-09 22:45:11'),
(131, 21, 'lubunaaliyuabk@gmail.com', '10.59.66.251', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-09 22:45:59'),
(132, 24, 'agent@gmail.com', '10.59.66.251', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-09 22:47:17'),
(133, 21, 'lubunaaliyuabk@gmail.com', '10.59.66.251', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-09 22:55:20'),
(134, 7, 'aliyuabubakar11117@gmail.com', '10.59.66.251', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-09 22:55:41'),
(135, 24, 'agent@gmail.com', '10.59.66.251', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-09 23:18:23'),
(136, 22, 'aliyuabubakardh@gmail.com', '10.59.66.251', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-09 23:24:04'),
(137, 7, 'aliyuabubakar11117@gmail.com', '10.59.66.251', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-09 23:24:14'),
(138, 21, 'lubunaaliyuabk@gmail.com', '10.59.66.251', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-09 23:24:24'),
(139, 7, 'aliyuabubakar11117@gmail.com', '10.59.66.251', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-09 23:24:48'),
(140, 24, 'agent@gmail.com', '10.59.66.251', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-09 23:25:34'),
(141, 24, 'agent@gmail.com', '10.59.66.157', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', 'login', 1, '2026-07-10 09:12:59'),
(142, 24, 'agent@gmail.com', '10.59.66.251', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-10 09:13:44'),
(143, 21, 'lubunaaliyuabk@gmail.com', '10.59.66.251', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-10 10:48:11'),
(144, 24, 'agent@gmail.com', '10.59.66.251', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-10 10:50:51'),
(145, 25, 'aliyuabubakar1111@gmail.com', '10.59.66.251', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-10 10:52:02'),
(146, 21, 'lubunaaliyuabk@gmail.com', '10.59.66.251', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-10 13:14:33'),
(147, NULL, 'agent1@mail.com', '10.59.66.251', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 0, '2026-07-10 13:17:42'),
(148, 21, 'lubunaaliyuabk@gmail.com', '10.59.66.251', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-10 13:17:56'),
(149, 26, 'agent1@gmail.com', '10.59.66.251', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-10 13:35:02'),
(150, 27, 'abarshiaminu2005@gmail.com', '10.59.66.251', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-10 13:35:08'),
(151, NULL, 'aliyuabubakar11117@gmail.com', '102.89.83.93', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 0, '2026-07-10 22:04:15'),
(152, NULL, 'aliyuabubakar11117@gmail.com', '102.89.83.93', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 0, '2026-07-10 22:04:23'),
(153, NULL, 'aliyuabubakar11117@gmail.com', '102.89.83.93', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', 'login', 0, '2026-07-10 22:05:18'),
(154, 21, 'lubunaaliyuabk@gmail.com', '102.89.83.93', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-10 22:12:33'),
(155, NULL, 'aliyuabubakar11117@gmail.com', '102.89.83.93', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 0, '2026-07-10 22:14:03'),
(156, 21, 'lubunaaliyuabk@gmail.com', '102.89.83.93', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-10 22:14:21'),
(157, 7, 'aliyuabubakar11117@gmail.com', '102.89.83.93', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-10 22:15:05'),
(158, 7, 'aliyuabubakar11117@gmail.com', '102.89.83.93', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-10 22:21:02'),
(159, 28, 'kowagurutechltd@gmail.com', '102.89.83.93', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-10 22:23:17'),
(160, NULL, 'admin@karamalogistics.com', '102.89.83.93', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 0, '2026-07-10 22:25:01'),
(161, 28, 'kowagurutechltd@gmail.com', '102.89.83.93', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-10 22:26:24'),
(162, 28, 'kowagurutechltd@gmail.com', '102.88.115.148', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'login', 1, '2026-07-10 22:29:58'),
(163, 28, 'kowagurutechltd@gmail.com', '102.91.103.45', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-10 23:26:28'),
(164, 21, 'lubunaaliyuabk@gmail.com', '102.91.103.29', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-11 13:00:31'),
(165, 22, 'aliyuabubakardh@gmail.com', '102.91.103.29', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-11 13:02:11'),
(166, 7, 'aliyuabubakar11117@gmail.com', '102.91.103.29', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-11 13:02:29'),
(167, 29, 'agent2@gmail.com', '102.91.103.29', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-11 13:03:59'),
(168, 28, 'kowagurutechltd@gmail.com', '102.91.104.170', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', 'login', 1, '2026-07-11 13:48:58'),
(169, 28, 'kowagurutechltd@gmail.com', '102.91.104.170', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-11 13:59:26'),
(170, 28, 'kowagurutechltd@gmail.com', '102.91.92.135', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', 'login', 1, '2026-07-11 14:13:19'),
(171, 28, 'kowagurutechltd@gmail.com', '102.91.103.61', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-15 12:53:48'),
(172, 28, 'kowagurutechltd@gmail.com', '102.91.77.202', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-15 21:04:50'),
(173, 28, 'kowagurutechltd@gmail.com', '102.91.77.202', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-15 22:05:20'),
(174, 28, 'kowagurutechltd@gmail.com', '102.91.77.202', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-15 22:59:33'),
(175, NULL, 'ibrahim@gmail.com', '102.91.77.202', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 0, '2026-07-15 23:00:20'),
(176, 28, 'kowagurutechltd@gmail.com', '102.91.77.202', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-15 23:00:24'),
(177, 23, 'ibrahim@gmail.com', '102.91.77.202', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-15 23:00:53'),
(178, 28, 'kowagurutechltd@gmail.com', '102.91.77.202', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-15 23:01:44'),
(179, 25, 'aliyuabubakar1111@gmail.com', '102.91.77.202', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-15 23:02:13'),
(180, NULL, 'abarshiaminu2005@gmail.com', '102.91.77.202', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 0, '2026-07-15 23:04:08'),
(181, 28, 'kowagurutechltd@gmail.com', '102.91.77.202', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-15 23:04:18'),
(182, 27, 'abarshiaminu2005@gmail.com', '102.91.77.202', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-15 23:04:50'),
(183, 7, 'aliyuabubakar11117@gmail.com', '102.91.77.202', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-15 23:06:46'),
(184, 22, 'aliyuabubakardh@gmail.com', '102.91.77.202', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-15 23:07:38'),
(185, 28, 'kowagurutechltd@gmail.com', '102.91.77.202', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', 'login', 1, '2026-07-15 23:09:37'),
(186, 7, 'aliyuabubakar11117@gmail.com', '102.91.93.133', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-16 09:16:10'),
(187, 21, 'lubunaaliyuabk@gmail.com', '102.91.93.133', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-16 09:31:51'),
(188, 28, 'kowagurutechltd@gmail.com', '102.91.93.133', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-16 09:36:16'),
(189, 7, 'aliyuabubakar11117@gmail.com', '102.91.93.133', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-16 10:01:00'),
(190, 21, 'lubunaaliyuabk@gmail.com', '102.91.93.133', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-16 10:01:11'),
(191, 28, 'kowagurutechltd@gmail.com', '102.91.93.133', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-16 10:01:47'),
(192, 28, 'kowagurutechltd@gmail.com', '197.210.70.187', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-18 15:59:03'),
(193, 22, 'aliyuabubakardh@gmail.com', '197.210.70.187', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-18 15:59:39'),
(194, 28, 'kowagurutechltd@gmail.com', '197.210.70.187', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-18 16:13:00'),
(195, 27, 'abarshiaminu2005@gmail.com', '197.210.70.187', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-18 16:13:22'),
(196, 28, 'kowagurutechltd@gmail.com', '197.210.70.187', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-18 16:31:14'),
(197, NULL, 'lubunaaliyuabk@gmail.com', '197.210.70.187', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 0, '2026-07-18 16:31:34'),
(198, 28, 'kowagurutechltd@gmail.com', '197.210.70.187', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-18 16:31:40'),
(199, 21, 'lubunaaliyuabk@gmail.com', '102.88.112.143', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-18 21:35:19'),
(200, 21, 'lubunaaliyuabk@gmail.com', '102.88.112.143', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-18 22:41:31'),
(201, 21, 'lubunaaliyuabk@gmail.com', '102.88.112.143', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-18 22:47:35'),
(202, 21, 'lubunaaliyuabk@gmail.com', '102.88.112.143', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-18 22:57:43'),
(203, 21, 'lubunaaliyuabk@gmail.com', '102.88.112.143', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-18 22:57:59'),
(204, 28, 'kowagurutechltd@gmail.com', '102.88.112.143', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-18 22:58:39'),
(205, 25, 'aliyuabubakar1111@gmail.com', '197.210.70.43', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-19 11:32:11'),
(206, 21, 'lubunaaliyuabk@gmail.com', '197.210.70.43', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-19 11:32:59'),
(207, NULL, 'abarshiaminu2005@gmail.com', '197.210.70.43', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 0, '2026-07-19 11:33:23'),
(208, 21, 'lubunaaliyuabk@gmail.com', '197.210.70.43', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-19 11:33:40'),
(209, NULL, 'abarshiaminu2005@gmail.com', '197.210.70.43', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 0, '2026-07-19 11:34:23'),
(210, 27, 'abarshiaminu2005@gmail.com', '197.210.70.43', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-19 11:34:31'),
(211, 27, 'abarshiaminu2005@gmail.com', '197.210.70.43', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-19 12:23:38'),
(212, 21, 'lubunaaliyuabk@gmail.com', '197.210.70.43', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-19 15:03:51'),
(213, 27, 'abarshiaminu2005@gmail.com', '197.210.70.43', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-19 16:27:22'),
(214, 27, 'abarshiaminu2005@gmail.com', '102.91.103.170', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-23 15:46:28'),
(215, 27, 'abarshiaminu2005@gmail.com', '102.91.104.59', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-24 13:33:17'),
(216, 27, 'abarshiaminu2005@gmail.com', '102.91.104.59', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-24 14:03:27'),
(217, 21, 'lubunaaliyuabk@gmail.com', '102.91.104.59', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-24 14:28:14'),
(218, 32, 'volunteer@gmail.com', '102.91.104.59', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-24 14:29:25'),
(219, 27, 'abarshiaminu2005@gmail.com', '102.91.104.59', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-24 14:29:40'),
(220, 27, 'abarshiaminu2005@gmail.com', '102.91.104.59', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-24 16:49:15'),
(221, 27, 'abarshiaminu2005@gmail.com', '102.91.104.59', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-24 17:27:55'),
(222, 27, 'abarshiaminu2005@gmail.com', '102.91.104.59', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-24 18:10:45'),
(223, 27, 'abarshiaminu2005@gmail.com', '102.91.105.161', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-24 18:32:04'),
(224, 27, 'abarshiaminu2005@gmail.com', '102.91.105.161', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-24 18:34:44'),
(225, 27, 'abarshiaminu2005@gmail.com', '102.91.92.216', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-25 20:19:51'),
(226, 27, 'abarshiaminu2005@gmail.com', '197.210.53.133', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-26 11:04:06'),
(227, 27, 'abarshiaminu2005@gmail.com', '197.210.53.133', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-26 11:05:14'),
(228, 27, 'abarshiaminu2005@gmail.com', '197.210.53.133', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-26 11:28:46'),
(229, 27, 'abarshiaminu2005@gmail.com', '102.91.103.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-27 10:05:39'),
(230, NULL, 'aliyuabubakarjdh@gmail.com', '102.91.103.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 0, '2026-07-27 10:06:53'),
(231, 7, 'aliyuabubakar11117@gmail.com', '102.91.103.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-27 10:07:01'),
(232, 7, 'aliyuabubakar11117@gmail.com', '102.91.103.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-27 12:26:40'),
(233, 21, 'lubunaaliyuabk@gmail.com', '102.91.103.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-27 12:30:15'),
(234, 33, 'federal@gmail.com', '102.91.103.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-27 12:56:16'),
(235, 27, 'abarshiaminu2005@gmail.com', '102.91.103.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-27 12:57:15'),
(236, 21, 'lubunaaliyuabk@gmail.com', '102.91.103.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-27 12:58:39'),
(237, NULL, 'senatorial@gmail.com', '102.91.103.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 0, '2026-07-27 13:02:42'),
(238, NULL, 'senatorial@gmail.com', '102.91.103.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 0, '2026-07-27 13:02:51'),
(239, 21, 'lubunaaliyuabk@gmail.com', '102.91.103.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-27 13:03:33'),
(240, 34, 'senatorial@gmail.com', '102.91.103.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-27 13:08:52'),
(241, 27, 'abarshiaminu2005@gmail.com', '102.91.103.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-27 13:14:46'),
(242, 21, 'lubunaaliyuabk@gmail.com', '102.91.103.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-27 13:15:32'),
(243, 21, 'lubunaaliyuabk@gmail.com', '102.91.103.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-27 13:16:37'),
(244, 34, 'senatorial@gmail.com', '102.91.103.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-27 13:31:35'),
(245, 21, 'lubunaaliyuabk@gmail.com', '102.91.103.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-27 13:39:01'),
(246, 34, 'senatorial@gmail.com', '102.91.103.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'login', 1, '2026-07-27 13:39:57');

-- --------------------------------------------------------

--
-- Table structure for table `media_uploads`
--

CREATE TABLE `media_uploads` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `tenant_id` bigint UNSIGNED DEFAULT NULL,
  `election_id` bigint UNSIGNED DEFAULT NULL,
  `pu_id` int UNSIGNED DEFAULT NULL,
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` bigint UNSIGNED NOT NULL,
  `file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `type` enum('system','election','result','incident','chat','broadcast','payment','security') COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `action_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Table structure for table `observer_assignments`
--

CREATE TABLE `observer_assignments` (
  `id` bigint UNSIGNED NOT NULL,
  `observer_id` bigint UNSIGNED NOT NULL,
  `coordinator_id` bigint UNSIGNED NOT NULL,
  `election_id` bigint UNSIGNED NOT NULL,
  `pu_id` int UNSIGNED NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `observer_incidents`
--

CREATE TABLE `observer_incidents` (
  `id` bigint UNSIGNED NOT NULL,
  `observer_id` bigint UNSIGNED NOT NULL,
  `polling_unit_id` int UNSIGNED NOT NULL,
  `type` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `video_url` varchar(500) DEFAULT NULL,
  `status` enum('reported','investigating','resolved') DEFAULT 'reported',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `observer_observations`
--

CREATE TABLE `observer_observations` (
  `id` bigint UNSIGNED NOT NULL,
  `observer_id` bigint UNSIGNED NOT NULL,
  `polling_unit_id` int UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `time` varchar(10) DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `video_url` varchar(500) DEFAULT NULL,
  `status` enum('draft','submitted','approved','rejected') DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `offline_sync_queue`
--

CREATE TABLE `offline_sync_queue` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `device_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `data_type` enum('ec8a','incident','checkin','media','chat','profile_update') COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` tinyint UNSIGNED NOT NULL DEFAULT '5',
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint UNSIGNED DEFAULT NULL,
  `file_sha256` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('queued','syncing','completed','failed','retrying') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `retry_count` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `max_retries` tinyint UNSIGNED NOT NULL DEFAULT '5',
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `synced_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Table structure for table `otp_verifications`
--

CREATE TABLE `otp_verifications` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `otp_code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'login',
  `channel` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'email',
  `expires_at` timestamp NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `used_at` timestamp NULL DEFAULT NULL,
  `attempts` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
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
(14, 12, '616687', 'login', 'email', '2026-07-03 23:06:19', 1, '2026-07-03 23:06:19', 0, '2026-07-03 23:05:55'),
(15, 12, '787979', 'login', 'email', '2026-07-05 09:33:43', 1, '2026-07-05 09:33:43', 0, '2026-07-05 09:33:26'),
(16, 12, '725166', 'login', 'email', '2026-07-05 09:51:02', 0, NULL, 0, '2026-07-05 09:46:02'),
(17, 12, '877875', 'login', 'email', '2026-07-05 09:50:17', 1, '2026-07-05 09:50:17', 0, '2026-07-05 09:50:03'),
(18, 12, '130855', 'login', 'email', '2026-07-06 17:34:28', 0, NULL, 0, '2026-07-06 17:29:28'),
(19, 12, '492376', 'login', 'email', '2026-07-06 17:35:09', 0, NULL, 0, '2026-07-06 17:30:09'),
(20, 12, '187615', 'login', 'email', '2026-07-06 17:35:57', 0, NULL, 0, '2026-07-06 17:30:57'),
(21, 12, '049555', 'login', 'email', '2026-07-06 20:37:12', 1, '2026-07-06 20:37:12', 0, '2026-07-06 20:36:15'),
(22, 21, '974121', 'login', 'email', '2026-07-09 15:14:32', 1, '2026-07-09 15:14:32', 0, '2026-07-09 15:14:05'),
(23, 21, '611175', 'login', 'email', '2026-07-09 15:34:10', 1, '2026-07-09 15:34:10', 0, '2026-07-09 15:33:45'),
(24, 21, '060321', 'login', 'email', '2026-07-09 21:35:07', 1, '2026-07-09 21:35:07', 0, '2026-07-09 21:34:28'),
(25, 21, '945331', 'login', 'email', '2026-07-09 22:18:39', 0, NULL, 0, '2026-07-09 22:13:39'),
(26, 21, '661256', 'login', 'email', '2026-07-09 22:14:24', 1, '2026-07-09 22:14:24', 0, '2026-07-09 22:14:05'),
(27, 21, '698121', 'login', 'email', '2026-07-09 22:23:37', 1, '2026-07-09 22:23:37', 0, '2026-07-09 22:23:17'),
(28, 21, '072251', 'login', 'email', '2026-07-09 22:49:03', 0, NULL, 0, '2026-07-09 22:44:03'),
(29, 21, '805160', 'login', 'email', '2026-07-09 22:49:33', 0, NULL, 0, '2026-07-09 22:44:33'),
(30, 7, '997134', 'login', 'email', '2026-07-10 20:24:32', 0, NULL, 0, '2026-07-10 22:19:32'),
(31, 7, '614223', 'login', 'email', '2026-07-10 20:25:23', 0, NULL, 0, '2026-07-10 22:20:23'),
(32, 21, '369939', 'login', 'email', '2026-07-18 14:37:11', 0, NULL, 0, '2026-07-18 16:32:11'),
(33, 21, '451873', 'login', 'email', '2026-07-18 19:07:26', 0, NULL, 0, '2026-07-18 21:02:26'),
(34, 21, '621865', 'login', 'email', '2026-07-18 19:16:48', 0, NULL, 0, '2026-07-18 21:11:48'),
(35, 21, '736733', 'login', 'email', '2026-07-18 19:46:23', 0, NULL, 0, '2026-07-18 21:41:23'),
(36, 21, '637259', 'login', 'email', '2026-07-18 19:49:50', 0, NULL, 0, '2026-07-18 21:44:50'),
(37, 21, '901908', 'login', 'email', '2026-07-18 19:50:17', 0, NULL, 0, '2026-07-18 21:45:17'),
(38, 21, '709873', 'login', 'email', '2026-07-18 19:50:32', 0, NULL, 0, '2026-07-18 21:45:32'),
(39, 21, '593119', 'login', 'email', '2026-07-18 22:45:22', 0, NULL, 0, '2026-07-18 22:40:22'),
(40, 21, '724860', 'login', 'email', '2026-07-18 22:45:31', 0, NULL, 0, '2026-07-18 22:40:31'),
(41, 21, '348805', 'login', 'email', '2026-07-18 22:45:48', 0, NULL, 0, '2026-07-18 22:40:48'),
(42, 21, '267452', 'login', 'email', '2026-07-18 22:46:15', 1, '2026-07-18 22:41:31', 0, '2026-07-18 22:41:15'),
(43, 21, '421448', 'login', 'email', '2026-07-18 22:52:21', 1, '2026-07-18 22:47:35', 0, '2026-07-18 22:47:21'),
(44, 21, '737472', 'login', 'email', '2026-07-18 23:02:31', 1, '2026-07-18 22:57:43', 0, '2026-07-18 22:57:31'),
(45, 21, '009427', 'login', 'email', '2026-07-18 23:02:47', 1, '2026-07-18 22:57:59', 0, '2026-07-18 22:57:47'),
(46, 28, '083591', 'login', 'email', '2026-07-18 23:04:53', 0, NULL, 0, '2026-07-18 22:59:53'),
(47, 28, '650573', 'login', 'email', '2026-07-19 11:36:56', 0, NULL, 0, '2026-07-19 11:31:56'),
(48, 21, '052778', 'login', 'email', '2026-07-19 11:37:25', 1, '2026-07-19 11:32:59', 0, '2026-07-19 11:32:25'),
(49, 21, '636609', 'login', 'email', '2026-07-19 11:38:27', 1, '2026-07-19 11:33:40', 0, '2026-07-19 11:33:27'),
(50, 21, '069074', 'login', 'email', '2026-07-19 15:07:35', 1, '2026-07-19 15:03:51', 0, '2026-07-19 15:02:35'),
(51, 21, '127244', 'login', 'email', '2026-07-24 14:32:44', 1, '2026-07-24 14:28:14', 0, '2026-07-24 14:27:44'),
(52, 21, '957395', 'login', 'email', '2026-07-27 12:34:51', 1, '2026-07-27 12:30:15', 0, '2026-07-27 12:29:51'),
(53, 21, '882564', 'login', 'email', '2026-07-27 13:02:31', 0, NULL, 0, '2026-07-27 12:57:31'),
(54, 21, '734165', 'login', 'email', '2026-07-27 13:02:58', 1, '2026-07-27 12:58:39', 0, '2026-07-27 12:57:58'),
(55, 21, '291391', 'login', 'email', '2026-07-27 13:07:58', 1, '2026-07-27 13:03:33', 0, '2026-07-27 13:02:58'),
(56, 21, '044142', 'login', 'email', '2026-07-27 13:20:08', 1, '2026-07-27 13:15:32', 0, '2026-07-27 13:15:08');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` timestamp NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT '0',
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `people`
--

CREATE TABLE `people` (
  `id` bigint UNSIGNED NOT NULL,
  `tenant_id` bigint UNSIGNED NOT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(200) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (concat(`first_name`,_utf8mb4' ',`last_name`)) STORED,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gender` enum('male','female','other','prefer_not_say') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `category` enum('party_member','volunteer','voter','stakeholder','community_leader','traditional_leader','religious_leader','influencer','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `state_id` int UNSIGNED DEFAULT NULL,
  `lga_id` int UNSIGNED DEFAULT NULL,
  `ward_id` int UNSIGNED DEFAULT NULL,
  `pu_id` int UNSIGNED DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `nin` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `voter_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `social_media_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `tags_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` bigint UNSIGNED NOT NULL,
  `module` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `political_parties`
--

CREATE TABLE `political_parties` (
  `id` bigint UNSIGNED NOT NULL,
  `tenant_id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `acronym` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `logo_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `chairman_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `secretary_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `social_media_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `state_offices_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Table structure for table `polling_units`
--

CREATE TABLE `polling_units` (
  `id` int UNSIGNED NOT NULL,
  `ward_id` int UNSIGNED NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `gps_lat` decimal(10,8) DEFAULT NULL,
  `gps_lng` decimal(11,8) DEFAULT NULL,
  `gps_accuracy` decimal(6,2) DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `registered_voters` int UNSIGNED NOT NULL DEFAULT '0',
  `accredited_voters` int UNSIGNED NOT NULL DEFAULT '0',
  `is_rural` tinyint(1) NOT NULL DEFAULT '0',
  `network_quality` enum('2g','3g','4g','5g','none') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `polling_units`
--

INSERT INTO `polling_units` (`id`, `ward_id`, `code`, `name`, `description`, `gps_lat`, `gps_lng`, `gps_accuracy`, `address`, `registered_voters`, `accredited_voters`, `is_rural`, `network_quality`, `is_active`, `created_at`) VALUES
(1, 1, '17-03-02-001', 'KANGIRE YAMMA/AREWA/KANGIRE P.S', 'EXISTING PU', NULL, NULL, NULL, NULL, 0, 0, 1, NULL, 1, '2026-07-09 13:39:19'),
(2, 1, '17-03-02-002', 'GWALA/GWALA', 'EXISTING PU', NULL, NULL, NULL, NULL, 0, 0, 1, NULL, 1, '2026-07-09 13:39:19'),
(3, 1, '17-03-02-003', 'KANGIRE GABAS/KANGIRE FILI', 'EXISTING PU', NULL, NULL, NULL, NULL, 0, 0, 1, NULL, 1, '2026-07-09 13:39:19'),
(4, 1, '17-03-02-004', 'RIGAR FADAMA/RIGAR FADAMA', 'EXISTING PU', NULL, NULL, NULL, NULL, 0, 0, 1, NULL, 1, '2026-07-09 13:39:19'),
(5, 1, '17-03-02-005', 'UNGUWAR LEMO / UNGUWAR LEMO', 'EXISTING PU', NULL, NULL, NULL, NULL, 0, 0, 1, NULL, 1, '2026-07-09 13:39:19'),
(6, 1, '17-03-02-006', 'JANRUWA/JANRUWA', 'EXISTING PU', NULL, NULL, NULL, NULL, 0, 0, 1, NULL, 1, '2026-07-09 13:39:19'),
(7, 1, '17-03-02-007', 'GIDAN BELLO/GIDAN BELLO', 'EXISTING PU', NULL, NULL, NULL, NULL, 0, 0, 1, NULL, 1, '2026-07-09 13:39:19'),
(8, 1, '17-03-02-008', 'UNGUWAR MAKADDAS /JUWANTUDU', 'EXISTING PU', NULL, NULL, NULL, NULL, 0, 0, 1, NULL, 1, '2026-07-09 13:39:19'),
(9, 1, '17-03-02-009', 'UNGUWAR MAKADDAS /JUWAN KWARI', 'EXISTING PU', NULL, NULL, NULL, NULL, 0, 0, 1, NULL, 1, '2026-07-09 13:39:19'),
(10, 1, '17-03-02-010', 'SHAWU GABAS/YAMMA/SHAWU P.SCH.', 'EXISTING PU', NULL, NULL, NULL, NULL, 0, 0, 1, NULL, 1, '2026-07-09 13:39:19'),
(11, 1, '17-03-02-011', 'DUMUS TSANGAYA/DUMUS TSANGAYA', 'EXISTING PU', NULL, NULL, NULL, NULL, 0, 0, 1, NULL, 1, '2026-07-09 13:39:19'),
(12, 1, '17-03-02-012', 'DUMUS GABAS/YAMMA/DUMUS GABAS', 'EXISTING PU', NULL, NULL, NULL, NULL, 0, 0, 1, NULL, 1, '2026-07-09 13:39:19'),
(13, 1, '17-03-02-013', 'DUMAS GABAS/ DUMUS P.SCH.', 'EXISTING PU', NULL, NULL, NULL, NULL, 0, 0, 1, NULL, 1, '2026-07-09 13:39:19'),
(14, 1, '17-03-02-014', 'YALWA GABAS/YAMMA/YALWA YAMMA', 'EXISTING PU', NULL, NULL, NULL, NULL, 0, 0, 1, NULL, 1, '2026-07-09 13:39:19'),
(15, 1, '17-03-02-015', 'WAZA GABAS/YAMMA/WAZA YAMMA', 'EXISTING PU', NULL, NULL, NULL, NULL, 0, 0, 1, NULL, 1, '2026-07-09 13:39:19'),
(16, 1, '17-03-02-016', 'HALIMBE GIDAN BELLO/HALIMBE GIDAN BELLO', 'EXISTING PU', NULL, NULL, NULL, NULL, 0, 0, 1, NULL, 1, '2026-07-09 13:39:19'),
(17, 1, '17-03-02-017', 'DUHUWA RAJU/DUHUWA RAJU', 'EXISTING PU', NULL, NULL, NULL, NULL, 0, 0, 1, NULL, 1, '2026-07-09 13:39:19'),
(18, 1, '17-03-02-018', 'KURIMA/KOFAR AMBO', 'EXISTING PU', NULL, NULL, NULL, NULL, 0, 0, 1, NULL, 1, '2026-07-09 13:39:19'),
(19, 1, '17-03-02-019', 'TSANGAYAR M. HALLIRU/GIDAN M. HALLIRU', 'EXISTING PU', NULL, NULL, NULL, NULL, 0, 0, 1, NULL, 1, '2026-07-09 13:39:19'),
(20, 1, '17-03-02-020', 'UNGUWAR BUSA/GIDAN HARUNA TIGA', 'EXISTING PU', NULL, NULL, NULL, NULL, 0, 0, 1, NULL, 1, '2026-07-09 13:39:19'),
(21, 1, '17-03-02-021', 'KUKAR DAN SARKI/KUKAR DAN SARKI', 'EXISTING PU', NULL, NULL, NULL, NULL, 0, 0, 1, NULL, 1, '2026-07-09 13:39:19'),
(22, 1, '17-03-02-022', 'BABBAN TITI/GIDAN DAN GARI', 'EXISTING PU', NULL, NULL, NULL, NULL, 0, 0, 1, NULL, 1, '2026-07-09 13:39:19'),
(23, 1, '17-03-02-023', 'DANGAJE/DANGAJE', 'EXISTING PU', NULL, NULL, NULL, NULL, 0, 0, 1, NULL, 1, '2026-07-09 13:39:19'),
(24, 1, '17-03-02-024', 'ZADAU/ZADAU', 'EXISTING PU', NULL, NULL, NULL, NULL, 0, 0, 1, NULL, 1, '2026-07-09 13:39:19'),
(25, 1, '17-03-02-025', 'KUKAR JAFARU', 'EXISTING PU', NULL, NULL, NULL, NULL, 0, 0, 1, NULL, 1, '2026-07-09 13:39:19'),
(26, 1, '17-03-02-026', 'KANGIRE PRI. SCH. II.', 'NEW PU', NULL, NULL, NULL, NULL, 0, 0, 1, NULL, 1, '2026-07-09 13:39:19'),
(27, 1, '17-03-02-027', 'JUWAN TUDU PRI. SCH. II', 'NEW PU', NULL, NULL, NULL, NULL, 0, 0, 1, NULL, 1, '2026-07-09 13:39:19'),
(28, 1, '17-03-02-028', 'FIRYA', 'NEW PU', NULL, NULL, NULL, NULL, 0, 0, 1, NULL, 1, '2026-07-09 13:39:19'),
(29, 1, '17-03-02-029', 'KAN-KAROFI', 'NEW PU', NULL, NULL, NULL, NULL, 0, 0, 1, NULL, 1, '2026-07-09 13:39:19'),
(30, 1, '17-03-02-030', 'U.B.E SCH.', 'NEW PU', NULL, NULL, NULL, NULL, 0, 0, 1, NULL, 1, '2026-07-09 13:39:19'),
(31, 1, '17-03-02-031', 'GINDIN TRANSFORMER DANGARI', 'NEW PU', NULL, NULL, NULL, NULL, 0, 0, 1, NULL, 1, '2026-07-09 13:39:19'),
(32, 1, '17-03-02-032', 'MAKWALLA', 'NEW PU', NULL, NULL, NULL, NULL, 0, 0, 1, NULL, 1, '2026-07-09 13:39:19'),
(33, 1, '17-03-02-033', 'MADAKANCHI POLE WIRE', 'NEW PU', NULL, NULL, NULL, NULL, 0, 0, 1, NULL, 1, '2026-07-09 13:39:19');

-- --------------------------------------------------------

--
-- Table structure for table `public_results`
--

CREATE TABLE `public_results` (
  `id` bigint UNSIGNED NOT NULL,
  `tenant_id` bigint UNSIGNED NOT NULL,
  `election_id` bigint UNSIGNED NOT NULL,
  `pu_id` int UNSIGNED DEFAULT NULL,
  `ward_id` int UNSIGNED DEFAULT NULL,
  `lga_id` int UNSIGNED DEFAULT NULL,
  `state_id` int UNSIGNED DEFAULT NULL,
  `level` enum('pu','ward','lga','state','national') COLLATE utf8mb4_unicode_ci NOT NULL,
  `party_votes_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `valid_votes` int UNSIGNED NOT NULL DEFAULT '0',
  `rejected_votes` int UNSIGNED NOT NULL DEFAULT '0',
  `total_votes` int UNSIGNED NOT NULL DEFAULT '0',
  `turnout_percentage` decimal(5,2) DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT '0',
  `published_at` timestamp NULL DEFAULT NULL,
  `published_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` bigint UNSIGNED NOT NULL,
  `tenant_id` bigint UNSIGNED NOT NULL,
  `election_id` bigint UNSIGNED DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('turnout','results','incidents','agents','financial','performance','custom') COLLATE utf8mb4_unicode_ci NOT NULL,
  `format` enum('pdf','excel','csv','json','html') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pdf',
  `filters_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `file_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint UNSIGNED DEFAULT NULL,
  `generated_by` bigint UNSIGNED NOT NULL,
  `generated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_scheduled` tinyint(1) NOT NULL DEFAULT '0',
  `schedule_cron` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT '0',
  `public_slug` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Table structure for table `results_ec8a`
--

CREATE TABLE `results_ec8a` (
  `id` bigint UNSIGNED NOT NULL,
  `tenant_id` bigint UNSIGNED NOT NULL,
  `election_id` bigint UNSIGNED NOT NULL,
  `pu_id` int UNSIGNED NOT NULL,
  `ward_id` int UNSIGNED NOT NULL,
  `lga_id` int UNSIGNED NOT NULL,
  `state_id` int UNSIGNED NOT NULL,
  `agent_id` bigint UNSIGNED NOT NULL,
  `assignment_id` bigint UNSIGNED NOT NULL,
  `pu_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pu_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `registered_voters` int UNSIGNED NOT NULL DEFAULT '0',
  `accredited_voters` int UNSIGNED NOT NULL DEFAULT '0',
  `ballot_papers_issued` int UNSIGNED NOT NULL DEFAULT '0',
  `unused_ballots` int UNSIGNED NOT NULL DEFAULT '0',
  `spoiled_ballots` int UNSIGNED NOT NULL DEFAULT '0',
  `rejected_votes` int UNSIGNED NOT NULL DEFAULT '0',
  `valid_votes` int UNSIGNED NOT NULL DEFAULT '0',
  `total_votes_cast` int UNSIGNED NOT NULL DEFAULT '0',
  `party_votes_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `photo_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `audio_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `gps_lat` decimal(10,8) DEFAULT NULL,
  `gps_lng` decimal(11,8) DEFAULT NULL,
  `gps_accuracy` decimal(6,2) DEFAULT NULL,
  `device_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_model` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo_sha256` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_sha256` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `audio_sha256` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ocr_extracted_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `ocr_confidence` decimal(5,2) DEFAULT NULL,
  `ocr_manual_mismatch` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('pending','verified','rejected','flagged','approved') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `verified_by` bigint UNSIGNED DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci,
  `is_offline_sync` tinyint(1) NOT NULL DEFAULT '0',
  `offline_created_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Table structure for table `results_ec8b`
--

CREATE TABLE `results_ec8b` (
  `id` bigint UNSIGNED NOT NULL,
  `tenant_id` bigint UNSIGNED NOT NULL,
  `election_id` bigint UNSIGNED NOT NULL,
  `ward_id` int UNSIGNED NOT NULL,
  `lga_id` int UNSIGNED NOT NULL,
  `state_id` int UNSIGNED NOT NULL,
  `coordinator_id` bigint UNSIGNED NOT NULL,
  `party_votes_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `valid_votes` int UNSIGNED NOT NULL DEFAULT '0',
  `rejected_votes` int UNSIGNED NOT NULL DEFAULT '0',
  `total_votes` int UNSIGNED NOT NULL DEFAULT '0',
  `calculated_total_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `mismatch_alert` tinyint(1) NOT NULL DEFAULT '0',
  `mismatch_details_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `form_photo_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `form_sha256` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','verified','rejected','flagged') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `verified_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Table structure for table `results_ec8c`
--

CREATE TABLE `results_ec8c` (
  `id` bigint UNSIGNED NOT NULL,
  `tenant_id` bigint UNSIGNED NOT NULL,
  `election_id` bigint UNSIGNED NOT NULL,
  `lga_id` int UNSIGNED NOT NULL,
  `state_id` int UNSIGNED NOT NULL,
  `coordinator_id` bigint UNSIGNED NOT NULL,
  `party_votes_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `valid_votes` int UNSIGNED NOT NULL DEFAULT '0',
  `rejected_votes` int UNSIGNED NOT NULL DEFAULT '0',
  `total_votes` int UNSIGNED NOT NULL DEFAULT '0',
  `calculated_total_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `mismatch_alert` tinyint(1) NOT NULL DEFAULT '0',
  `mismatch_details_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `form_photo_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `form_sha256` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','verified','rejected','flagged') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `verified_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Table structure for table `results_ec8d`
--

CREATE TABLE `results_ec8d` (
  `id` bigint UNSIGNED NOT NULL,
  `tenant_id` bigint UNSIGNED NOT NULL,
  `election_id` bigint UNSIGNED NOT NULL,
  `state_id` int UNSIGNED NOT NULL,
  `coordinator_id` bigint UNSIGNED NOT NULL,
  `party_votes_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `valid_votes` int UNSIGNED NOT NULL DEFAULT '0',
  `rejected_votes` int UNSIGNED NOT NULL DEFAULT '0',
  `total_votes` int UNSIGNED NOT NULL DEFAULT '0',
  `calculated_total_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `mismatch_alert` tinyint(1) NOT NULL DEFAULT '0',
  `mismatch_details_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `form_photo_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `form_sha256` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','verified','rejected','flagged') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `verified_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Table structure for table `results_ec8e`
--

CREATE TABLE `results_ec8e` (
  `id` bigint UNSIGNED NOT NULL,
  `tenant_id` bigint UNSIGNED NOT NULL,
  `election_id` bigint UNSIGNED NOT NULL,
  `coordinator_id` bigint UNSIGNED NOT NULL,
  `party_votes_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `valid_votes` int UNSIGNED NOT NULL DEFAULT '0',
  `rejected_votes` int UNSIGNED NOT NULL DEFAULT '0',
  `total_votes` int UNSIGNED NOT NULL DEFAULT '0',
  `calculated_total_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `mismatch_alert` tinyint(1) NOT NULL DEFAULT '0',
  `mismatch_details_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `form_photo_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `form_sha256` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `declaration_time` timestamp NULL DEFAULT NULL,
  `status` enum('pending','verified','declared','rejected','flagged') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `verified_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint UNSIGNED NOT NULL,
  `tenant_id` bigint UNSIGNED DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `level` enum('super_admin','client_admin','national','state','senatorial','federal_constituency','lga','ward','pu_agent','party_agent','volunteer','observer','situation_room','finance_officer','citizen') COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `permissions_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

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
(14, NULL, 'Citizen', 'citizen', 'citizen', 'Regular citizen with view-only access', '{\"view_public_results\": true, \"report_incidents\": true}', 1, 1, '2026-07-03 23:29:50', '2026-07-03 23:29:50'),
(15, NULL, 'Volunteer', 'volunteer', 'volunteer', 'Volunteeer election process', '{\n    \"login\": true,\n    \"dashboard\": true,\n    \"assigned_tasks\": true,\n    \"community_reports\": true,\n    \"upload_media\": true,\n    \"chat\": true,\n    \"voice_chat\": true,\n    \"file_sharing\": true,\n    \"notifications\": true,\n    \"profile\": true\n}', 0, 1, '2026-07-19 11:57:27', '2026-07-24 14:36:02');

-- --------------------------------------------------------

--
-- Table structure for table `role_id_mapping`
--

CREATE TABLE `role_id_mapping` (
  `old_id` bigint UNSIGNED DEFAULT NULL,
  `new_id` bigint UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

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
  `id` bigint UNSIGNED NOT NULL,
  `tenant_id` bigint UNSIGNED DEFAULT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `event_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gps_lat` decimal(10,8) DEFAULT NULL,
  `gps_lng` decimal(11,8) DEFAULT NULL,
  `risk_score` tinyint UNSIGNED DEFAULT NULL,
  `resolved` tinyint(1) NOT NULL DEFAULT '0',
  `resolved_by` bigint UNSIGNED DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
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
(72, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 22:51:54'),
(73, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 22:55:16'),
(74, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 22:55:40'),
(75, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 22:55:40'),
(76, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 22:56:24'),
(77, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:05:29'),
(78, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:06:19'),
(79, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:18:20'),
(80, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:18:52'),
(81, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:18:52'),
(82, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:19:37'),
(83, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:23:53'),
(84, NULL, 7, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:24:07'),
(85, NULL, 7, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:30:25'),
(86, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:30:33'),
(87, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:30:33'),
(88, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:31:59'),
(89, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:31:59'),
(90, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:37:13'),
(91, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:37:13'),
(92, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:40:08'),
(93, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:40:08'),
(94, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:40:41'),
(95, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:40:41'),
(96, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:40:51'),
(97, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:40:51'),
(98, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:41:11'),
(99, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:52:07'),
(100, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:52:16'),
(101, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:52:56'),
(102, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-03 23:53:04'),
(103, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:18:01'),
(104, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:18:18'),
(105, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:24:29'),
(106, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:24:36'),
(107, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:25:34'),
(108, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:25:40'),
(109, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:26:53'),
(110, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:27:21'),
(111, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:29:07'),
(112, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:29:31'),
(113, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:30:25'),
(114, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:30:46'),
(115, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:31:21'),
(116, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:31:28'),
(117, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:32:57'),
(118, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:33:05'),
(119, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:39:41'),
(120, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 00:39:59'),
(121, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 01:04:40'),
(122, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:01:49'),
(123, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:04:18'),
(124, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:04:33'),
(125, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:04:52'),
(126, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:05:01'),
(127, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:16:15'),
(128, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:16:33'),
(129, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:18:43'),
(130, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:18:50'),
(131, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:19:29'),
(132, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:19:35'),
(133, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:24:21'),
(134, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:24:27'),
(135, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:24:36'),
(136, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:25:08'),
(137, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:26:54'),
(138, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:27:16'),
(139, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:27:26'),
(140, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:27:33'),
(141, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:27:36'),
(142, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:27:54'),
(143, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:28:57'),
(144, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:29:03'),
(145, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:31:51'),
(146, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:32:21'),
(147, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:32:51'),
(148, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:33:43'),
(149, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:45:54'),
(150, NULL, 7, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:46:18'),
(151, NULL, 7, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:46:36'),
(152, NULL, 7, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:46:42'),
(153, NULL, 7, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:49:55'),
(154, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:50:17'),
(155, NULL, 7, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-05 09:55:56'),
(156, NULL, 7, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-06 17:29:54'),
(157, NULL, 7, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-06 17:30:01'),
(158, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-06 20:37:13'),
(161, NULL, 7, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-07 18:33:45'),
(162, NULL, 7, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-07 18:34:14'),
(163, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-07 18:34:22'),
(164, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-07 18:35:43'),
(165, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-07 19:45:12'),
(166, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 12:59:04'),
(167, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 13:39:48'),
(168, NULL, 7, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 13:39:56'),
(169, NULL, 7, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 13:40:54'),
(170, NULL, 7, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 13:41:02'),
(171, NULL, 7, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 13:55:45'),
(172, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 13:55:51'),
(173, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 13:57:29'),
(174, NULL, NULL, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 13:57:36'),
(175, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 13:58:24'),
(176, NULL, 7, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 13:58:39'),
(177, NULL, NULL, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 14:07:34'),
(178, NULL, 7, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 14:07:48'),
(179, NULL, 7, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 14:19:16'),
(180, NULL, 21, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 14:19:22'),
(181, NULL, 21, 'logout', 'User logged out from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 14:19:28'),
(182, NULL, 7, 'login', 'Successful login from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 14:19:33'),
(183, NULL, 7, 'login', 'Successful login from IP: 10.59.66.251', '10.59.66.251', '6a579af1c0bb49fd2d0ad7c141c0a89d250c86c365d5163cf76ddb0837b72c75', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 14:28:16'),
(184, NULL, 7, 'logout', 'User logged out from IP: 10.59.66.251', '10.59.66.251', '6a579af1c0bb49fd2d0ad7c141c0a89d250c86c365d5163cf76ddb0837b72c75', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 15:13:57'),
(185, NULL, 21, 'login', 'Successful login from IP: 10.59.66.251', '10.59.66.251', '6a579af1c0bb49fd2d0ad7c141c0a89d250c86c365d5163cf76ddb0837b72c75', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 15:14:32'),
(186, NULL, 21, 'logout', 'User logged out from IP: 10.59.66.251', '10.59.66.251', '6a579af1c0bb49fd2d0ad7c141c0a89d250c86c365d5163cf76ddb0837b72c75', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 15:32:07'),
(187, NULL, 21, 'password_reset', 'Password reset requested from IP: 10.59.66.251', '10.59.66.251', '6a579af1c0bb49fd2d0ad7c141c0a89d250c86c365d5163cf76ddb0837b72c75', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 15:32:23'),
(188, NULL, 21, 'password_change', 'Password reset completed from IP: ::1', '::1', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 15:33:34'),
(189, NULL, 21, 'login', 'Successful login from IP: 10.59.66.251', '10.59.66.251', '6a579af1c0bb49fd2d0ad7c141c0a89d250c86c365d5163cf76ddb0837b72c75', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 15:34:10'),
(190, NULL, 21, 'login', 'Successful login from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 21:35:08'),
(191, NULL, 21, 'logout', 'User logged out from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 22:13:26'),
(192, NULL, 21, 'login', 'Successful login from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 22:14:24'),
(193, NULL, 21, 'logout', 'User logged out from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 22:15:09'),
(194, NULL, 22, 'login', 'Successful login from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 22:15:20'),
(195, NULL, 22, 'logout', 'User logged out from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 22:23:08'),
(196, NULL, 21, 'login', 'Successful login from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 22:23:37'),
(197, NULL, 21, 'logout', 'User logged out from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 22:24:11'),
(198, NULL, 22, 'login', 'Successful login from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 22:24:15'),
(199, NULL, 22, 'logout', 'User logged out from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 22:43:58'),
(200, NULL, 22, 'login', 'Successful login from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 22:45:11'),
(201, NULL, 22, 'logout', 'User logged out from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 22:45:54'),
(202, NULL, 21, 'login', 'Successful login from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 22:45:59'),
(203, NULL, 21, 'logout', 'User logged out from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 22:47:04'),
(204, NULL, 24, 'login', 'Successful login from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 22:47:17'),
(205, NULL, 24, 'logout', 'User logged out from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 22:55:15'),
(206, NULL, 21, 'login', 'Successful login from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 22:55:20'),
(207, NULL, 21, 'logout', 'User logged out from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 22:55:36'),
(208, NULL, 7, 'login', 'Successful login from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 22:55:41'),
(209, NULL, 7, 'logout', 'User logged out from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 23:18:19'),
(210, NULL, 24, 'login', 'Successful login from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 23:18:23'),
(211, NULL, 24, 'logout', 'User logged out from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 23:23:56'),
(212, NULL, 22, 'login', 'Successful login from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 23:24:04'),
(213, NULL, 22, 'logout', 'User logged out from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 23:24:11'),
(214, NULL, 7, 'login', 'Successful login from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 23:24:15'),
(215, NULL, 7, 'logout', 'User logged out from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 23:24:21'),
(216, NULL, 21, 'login', 'Successful login from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 23:24:25'),
(217, NULL, 21, 'logout', 'User logged out from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 23:24:43'),
(218, NULL, 7, 'login', 'Successful login from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 23:24:48'),
(219, NULL, 7, 'logout', 'User logged out from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 23:25:30'),
(220, NULL, 24, 'login', 'Successful login from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-09 23:25:34'),
(221, NULL, 7, 'password_reset', 'Password reset requested from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-10 09:07:39'),
(222, NULL, 7, 'password_reset', 'Password reset requested from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-10 09:12:13'),
(223, NULL, 24, 'login', 'Successful login from IP: 10.59.66.157', '10.59.66.157', 'dd480bfcd698f240d48dad2252eca7303e4789762f7c183629a220a9d04693e5', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-10 09:12:59'),
(224, NULL, 24, 'login', 'Successful login from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-10 09:13:44'),
(225, NULL, 24, 'logout', 'User logged out from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-10 10:48:07'),
(226, NULL, 21, 'login', 'Successful login from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-10 10:48:11'),
(227, NULL, 21, 'logout', 'User logged out from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-10 10:50:47'),
(228, NULL, 24, 'login', 'Successful login from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-10 10:50:51'),
(229, NULL, 24, 'logout', 'User logged out from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-10 10:51:56'),
(230, NULL, 25, 'login', 'Successful login from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-10 10:52:02'),
(231, NULL, 25, 'logout', 'User logged out from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-10 13:14:29'),
(232, NULL, 21, 'login', 'Successful login from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-10 13:14:33'),
(233, NULL, 21, 'logout', 'User logged out from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-10 13:17:37'),
(234, NULL, 21, 'login', 'Successful login from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-10 13:17:56'),
(235, NULL, 21, 'logout', 'User logged out from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-10 13:34:56'),
(236, NULL, 26, 'login', 'Successful login from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-10 13:35:02'),
(237, NULL, 26, 'logout', 'User logged out from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-10 13:35:04'),
(238, NULL, 27, 'login', 'Successful login from IP: 10.59.66.251', '10.59.66.251', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-10 13:35:08'),
(239, NULL, 7, 'password_reset', 'Password reset requested from IP: 102.89.83.93', '102.89.83.93', 'f80c32747451414de1a1fda16c5bda7fbc56e01058e440f49dc05b8bb239bf2a', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-10 22:10:46'),
(240, NULL, 21, 'login', 'Successful login from IP: 102.89.83.93', '102.89.83.93', 'f80c32747451414de1a1fda16c5bda7fbc56e01058e440f49dc05b8bb239bf2a', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-10 22:12:33'),
(241, NULL, 21, 'logout', 'User logged out from IP: 102.89.83.93', '102.89.83.93', 'f80c32747451414de1a1fda16c5bda7fbc56e01058e440f49dc05b8bb239bf2a', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-10 22:13:54'),
(242, NULL, 21, 'login', 'Successful login from IP: 102.89.83.93', '102.89.83.93', 'f80c32747451414de1a1fda16c5bda7fbc56e01058e440f49dc05b8bb239bf2a', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-10 22:14:21'),
(243, NULL, 21, 'logout', 'User logged out from IP: 102.89.83.93', '102.89.83.93', 'f80c32747451414de1a1fda16c5bda7fbc56e01058e440f49dc05b8bb239bf2a', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-10 22:14:26'),
(244, NULL, 7, 'login', 'Successful login from IP: 102.89.83.93', '102.89.83.93', 'f80c32747451414de1a1fda16c5bda7fbc56e01058e440f49dc05b8bb239bf2a', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-10 22:15:05'),
(245, NULL, 7, 'logout', 'User logged out from IP: 102.89.83.93', '102.89.83.93', 'f80c32747451414de1a1fda16c5bda7fbc56e01058e440f49dc05b8bb239bf2a', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-10 22:19:28'),
(246, NULL, 7, 'login', 'Successful login from IP: 102.89.83.93', '102.89.83.93', 'f80c32747451414de1a1fda16c5bda7fbc56e01058e440f49dc05b8bb239bf2a', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-10 22:21:02'),
(247, NULL, 7, 'logout', 'User logged out from IP: 102.89.83.93', '102.89.83.93', 'f80c32747451414de1a1fda16c5bda7fbc56e01058e440f49dc05b8bb239bf2a', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-10 22:23:04'),
(248, NULL, 28, 'login', 'Successful login from IP: 102.89.83.93', '102.89.83.93', 'f80c32747451414de1a1fda16c5bda7fbc56e01058e440f49dc05b8bb239bf2a', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-10 22:23:17'),
(249, NULL, 7, 'password_reset', 'Password reset requested from IP: 102.89.83.93', '102.89.83.93', 'f80c32747451414de1a1fda16c5bda7fbc56e01058e440f49dc05b8bb239bf2a', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-10 22:25:08'),
(250, NULL, 28, 'logout', 'User logged out from IP: 102.89.83.93', '102.89.83.93', 'f80c32747451414de1a1fda16c5bda7fbc56e01058e440f49dc05b8bb239bf2a', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-10 22:26:21'),
(251, NULL, 28, 'login', 'Successful login from IP: 102.89.83.93', '102.89.83.93', 'f80c32747451414de1a1fda16c5bda7fbc56e01058e440f49dc05b8bb239bf2a', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-10 22:26:24'),
(252, NULL, 28, 'login', 'Successful login from IP: 102.88.115.148', '102.88.115.148', 'aab9a5d60b16af51ae0bc446d37a7f46291804f146a380ac2d1964db17dce571', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-10 22:29:58'),
(253, NULL, 28, 'login', 'Successful login from IP: 102.91.103.45', '102.91.103.45', '0ab3a1a7c3c255d97ad36a930f16547ce0bdea8df526c8c00a7267d91c981ef3', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-10 23:26:28'),
(254, NULL, 21, 'login', 'Successful login from IP: 102.91.103.29', '102.91.103.29', '39efd42b46b61b96c194f3b9966cd708e2c3590971a1adeabf023e80852f83f2', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-11 13:00:31'),
(255, NULL, 21, 'logout', 'User logged out from IP: 102.91.103.29', '102.91.103.29', '39efd42b46b61b96c194f3b9966cd708e2c3590971a1adeabf023e80852f83f2', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-11 13:02:07'),
(256, NULL, 22, 'login', 'Successful login from IP: 102.91.103.29', '102.91.103.29', '39efd42b46b61b96c194f3b9966cd708e2c3590971a1adeabf023e80852f83f2', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-11 13:02:11'),
(257, NULL, 22, 'logout', 'User logged out from IP: 102.91.103.29', '102.91.103.29', '39efd42b46b61b96c194f3b9966cd708e2c3590971a1adeabf023e80852f83f2', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-11 13:02:21'),
(258, NULL, 7, 'login', 'Successful login from IP: 102.91.103.29', '102.91.103.29', '39efd42b46b61b96c194f3b9966cd708e2c3590971a1adeabf023e80852f83f2', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-11 13:02:29');
INSERT INTO `security_events` (`id`, `tenant_id`, `user_id`, `event_type`, `description`, `ip_address`, `device_id`, `gps_lat`, `gps_lng`, `risk_score`, `resolved`, `resolved_by`, `resolved_at`, `created_at`) VALUES
(259, NULL, 7, 'logout', 'User logged out from IP: 102.91.103.29', '102.91.103.29', '39efd42b46b61b96c194f3b9966cd708e2c3590971a1adeabf023e80852f83f2', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-11 13:03:15'),
(260, NULL, 29, 'login', 'Successful login from IP: 102.91.103.29', '102.91.103.29', '39efd42b46b61b96c194f3b9966cd708e2c3590971a1adeabf023e80852f83f2', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-11 13:03:59'),
(261, NULL, 29, 'logout', 'User logged out from IP: 102.91.103.29', '102.91.103.29', '39efd42b46b61b96c194f3b9966cd708e2c3590971a1adeabf023e80852f83f2', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-11 13:04:05'),
(262, NULL, 28, 'login', 'Successful login from IP: 102.91.104.170', '102.91.104.170', 'e94c934aed781183f918b54585376a63bfe639d25f80032384e2a58ec9517a05', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-11 13:48:58'),
(263, NULL, 28, 'login', 'Successful login from IP: 102.91.104.170', '102.91.104.170', '9ce7f450064053f72a15eefe50245efa182b4fef095ed6bcbf1f99f48857dcdf', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-11 13:59:26'),
(264, NULL, 28, 'login', 'Successful login from IP: 102.91.92.135', '102.91.92.135', 'ca593686404cf83fd1f2a1d22e731b0a765dfb51330beba05bb8621cbf8f2516', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-11 14:13:20'),
(265, NULL, 28, 'login', 'Successful login from IP: 102.91.103.61', '102.91.103.61', 'c5b54ee590e12da9bc0c342aa5c150c2f6e63ed8a8e6d5865a11a6d5bfa0ac9d', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-15 12:53:48'),
(266, NULL, 28, 'login', 'Successful login from IP: 102.91.77.202', '102.91.77.202', '961501809bcc275ddb0bdccf6376d84511768d0309bb5184eeae9b70b2fb00b8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-15 21:04:50'),
(267, NULL, 28, 'login', 'Successful login from IP: 102.91.77.202', '102.91.77.202', '961501809bcc275ddb0bdccf6376d84511768d0309bb5184eeae9b70b2fb00b8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-15 22:05:20'),
(268, NULL, 28, 'login', 'Successful login from IP: 102.91.77.202', '102.91.77.202', '961501809bcc275ddb0bdccf6376d84511768d0309bb5184eeae9b70b2fb00b8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-15 22:59:33'),
(269, NULL, 28, 'logout', 'User logged out from IP: 102.91.77.202', '102.91.77.202', '961501809bcc275ddb0bdccf6376d84511768d0309bb5184eeae9b70b2fb00b8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-15 23:00:16'),
(270, NULL, 28, 'login', 'Successful login from IP: 102.91.77.202', '102.91.77.202', '961501809bcc275ddb0bdccf6376d84511768d0309bb5184eeae9b70b2fb00b8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-15 23:00:25'),
(271, NULL, 28, 'logout', 'User logged out from IP: 102.91.77.202', '102.91.77.202', '961501809bcc275ddb0bdccf6376d84511768d0309bb5184eeae9b70b2fb00b8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-15 23:00:50'),
(272, NULL, 23, 'login', 'Successful login from IP: 102.91.77.202', '102.91.77.202', '961501809bcc275ddb0bdccf6376d84511768d0309bb5184eeae9b70b2fb00b8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-15 23:00:53'),
(273, NULL, 23, 'logout', 'User logged out from IP: 102.91.77.202', '102.91.77.202', '961501809bcc275ddb0bdccf6376d84511768d0309bb5184eeae9b70b2fb00b8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-15 23:01:37'),
(274, NULL, 28, 'login', 'Successful login from IP: 102.91.77.202', '102.91.77.202', '961501809bcc275ddb0bdccf6376d84511768d0309bb5184eeae9b70b2fb00b8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-15 23:01:44'),
(275, NULL, 28, 'logout', 'User logged out from IP: 102.91.77.202', '102.91.77.202', '961501809bcc275ddb0bdccf6376d84511768d0309bb5184eeae9b70b2fb00b8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-15 23:02:08'),
(276, NULL, 25, 'login', 'Successful login from IP: 102.91.77.202', '102.91.77.202', '961501809bcc275ddb0bdccf6376d84511768d0309bb5184eeae9b70b2fb00b8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-15 23:02:13'),
(277, NULL, 25, 'logout', 'User logged out from IP: 102.91.77.202', '102.91.77.202', '961501809bcc275ddb0bdccf6376d84511768d0309bb5184eeae9b70b2fb00b8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-15 23:04:04'),
(278, NULL, 28, 'login', 'Successful login from IP: 102.91.77.202', '102.91.77.202', '961501809bcc275ddb0bdccf6376d84511768d0309bb5184eeae9b70b2fb00b8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-15 23:04:18'),
(279, NULL, 28, 'logout', 'User logged out from IP: 102.91.77.202', '102.91.77.202', '961501809bcc275ddb0bdccf6376d84511768d0309bb5184eeae9b70b2fb00b8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-15 23:04:45'),
(280, NULL, 27, 'login', 'Successful login from IP: 102.91.77.202', '102.91.77.202', '961501809bcc275ddb0bdccf6376d84511768d0309bb5184eeae9b70b2fb00b8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-15 23:04:50'),
(281, NULL, 27, 'logout', 'User logged out from IP: 102.91.77.202', '102.91.77.202', '961501809bcc275ddb0bdccf6376d84511768d0309bb5184eeae9b70b2fb00b8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-15 23:06:39'),
(282, NULL, 7, 'login', 'Successful login from IP: 102.91.77.202', '102.91.77.202', '961501809bcc275ddb0bdccf6376d84511768d0309bb5184eeae9b70b2fb00b8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-15 23:06:46'),
(283, NULL, 7, 'logout', 'User logged out from IP: 102.91.77.202', '102.91.77.202', '961501809bcc275ddb0bdccf6376d84511768d0309bb5184eeae9b70b2fb00b8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-15 23:07:32'),
(284, NULL, 22, 'login', 'Successful login from IP: 102.91.77.202', '102.91.77.202', '961501809bcc275ddb0bdccf6376d84511768d0309bb5184eeae9b70b2fb00b8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-15 23:07:38'),
(285, NULL, 22, 'logout', 'User logged out from IP: 102.91.77.202', '102.91.77.202', '961501809bcc275ddb0bdccf6376d84511768d0309bb5184eeae9b70b2fb00b8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-15 23:08:02'),
(286, NULL, 7, 'password_reset', 'Password reset requested from IP: 102.91.77.202', '102.91.77.202', '961501809bcc275ddb0bdccf6376d84511768d0309bb5184eeae9b70b2fb00b8', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-15 23:09:02'),
(287, NULL, 28, 'login', 'Successful login from IP: 102.91.77.202', '102.91.77.202', '77465f4c7b38a07d3999eb8f7c243b0f681088e3c1d8d3f088fbf87bf83a5730', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-15 23:09:37'),
(288, NULL, 7, 'login', 'Successful login from IP: 102.91.93.133', '102.91.93.133', 'e165bc07aeec3a0019903490a1fa30662a83689d13fbf4b96f0fa0233c9950b5', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-16 09:16:10'),
(289, NULL, 7, 'logout', 'User logged out from IP: 102.91.93.133', '102.91.93.133', 'e165bc07aeec3a0019903490a1fa30662a83689d13fbf4b96f0fa0233c9950b5', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-16 09:31:46'),
(290, NULL, 21, 'login', 'Successful login from IP: 102.91.93.133', '102.91.93.133', 'e165bc07aeec3a0019903490a1fa30662a83689d13fbf4b96f0fa0233c9950b5', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-16 09:31:51'),
(291, NULL, 21, 'logout', 'User logged out from IP: 102.91.93.133', '102.91.93.133', 'e165bc07aeec3a0019903490a1fa30662a83689d13fbf4b96f0fa0233c9950b5', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-16 09:36:10'),
(292, NULL, 28, 'login', 'Successful login from IP: 102.91.93.133', '102.91.93.133', 'e165bc07aeec3a0019903490a1fa30662a83689d13fbf4b96f0fa0233c9950b5', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-16 09:36:16'),
(293, NULL, 28, 'logout', 'User logged out from IP: 102.91.93.133', '102.91.93.133', 'e165bc07aeec3a0019903490a1fa30662a83689d13fbf4b96f0fa0233c9950b5', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-16 10:00:52'),
(294, NULL, 7, 'login', 'Successful login from IP: 102.91.93.133', '102.91.93.133', 'e165bc07aeec3a0019903490a1fa30662a83689d13fbf4b96f0fa0233c9950b5', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-16 10:01:00'),
(295, NULL, 7, 'logout', 'User logged out from IP: 102.91.93.133', '102.91.93.133', 'e165bc07aeec3a0019903490a1fa30662a83689d13fbf4b96f0fa0233c9950b5', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-16 10:01:03'),
(296, NULL, 21, 'login', 'Successful login from IP: 102.91.93.133', '102.91.93.133', 'e165bc07aeec3a0019903490a1fa30662a83689d13fbf4b96f0fa0233c9950b5', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-16 10:01:11'),
(297, NULL, 21, 'logout', 'User logged out from IP: 102.91.93.133', '102.91.93.133', 'e165bc07aeec3a0019903490a1fa30662a83689d13fbf4b96f0fa0233c9950b5', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-16 10:01:38'),
(298, NULL, 28, 'login', 'Successful login from IP: 102.91.93.133', '102.91.93.133', 'e165bc07aeec3a0019903490a1fa30662a83689d13fbf4b96f0fa0233c9950b5', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-16 10:01:47'),
(299, NULL, 28, 'login', 'Successful login from IP: 197.210.70.187', '197.210.70.187', '2d0efc8e9906ba0552bb6f625465f865147298005c19ff96f6a58a9cae19ef0b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-18 15:59:03'),
(300, NULL, 28, 'logout', 'User logged out from IP: 197.210.70.187', '197.210.70.187', '2d0efc8e9906ba0552bb6f625465f865147298005c19ff96f6a58a9cae19ef0b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-18 15:59:34'),
(301, NULL, 22, 'login', 'Successful login from IP: 197.210.70.187', '197.210.70.187', '2d0efc8e9906ba0552bb6f625465f865147298005c19ff96f6a58a9cae19ef0b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-18 15:59:39'),
(302, NULL, 22, 'logout', 'User logged out from IP: 197.210.70.187', '197.210.70.187', '2d0efc8e9906ba0552bb6f625465f865147298005c19ff96f6a58a9cae19ef0b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-18 16:12:49'),
(303, NULL, 28, 'login', 'Successful login from IP: 197.210.70.187', '197.210.70.187', '2d0efc8e9906ba0552bb6f625465f865147298005c19ff96f6a58a9cae19ef0b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-18 16:13:00'),
(304, NULL, 28, 'logout', 'User logged out from IP: 197.210.70.187', '197.210.70.187', '2d0efc8e9906ba0552bb6f625465f865147298005c19ff96f6a58a9cae19ef0b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-18 16:13:16'),
(305, NULL, 27, 'login', 'Successful login from IP: 197.210.70.187', '197.210.70.187', '2d0efc8e9906ba0552bb6f625465f865147298005c19ff96f6a58a9cae19ef0b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-18 16:13:22'),
(306, NULL, 27, 'logout', 'User logged out from IP: 197.210.70.187', '197.210.70.187', '2d0efc8e9906ba0552bb6f625465f865147298005c19ff96f6a58a9cae19ef0b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-18 16:21:47'),
(307, NULL, 21, 'password_reset', 'Password reset requested from IP: 197.210.70.187', '197.210.70.187', '2d0efc8e9906ba0552bb6f625465f865147298005c19ff96f6a58a9cae19ef0b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-18 16:22:11'),
(308, NULL, 21, 'password_reset', 'Password reset requested from IP: 197.210.70.187', '197.210.70.187', '2d0efc8e9906ba0552bb6f625465f865147298005c19ff96f6a58a9cae19ef0b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-18 16:30:29'),
(309, NULL, 21, 'password_reset', 'Password reset requested from IP: 197.210.70.187', '197.210.70.187', '2d0efc8e9906ba0552bb6f625465f865147298005c19ff96f6a58a9cae19ef0b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-18 16:30:37'),
(310, NULL, 28, 'login', 'Successful login from IP: 197.210.70.187', '197.210.70.187', '2d0efc8e9906ba0552bb6f625465f865147298005c19ff96f6a58a9cae19ef0b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-18 16:31:14'),
(311, NULL, 28, 'logout', 'User logged out from IP: 197.210.70.187', '197.210.70.187', '2d0efc8e9906ba0552bb6f625465f865147298005c19ff96f6a58a9cae19ef0b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-18 16:31:29'),
(312, NULL, 28, 'login', 'Successful login from IP: 197.210.70.187', '197.210.70.187', '2d0efc8e9906ba0552bb6f625465f865147298005c19ff96f6a58a9cae19ef0b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-18 16:31:40'),
(313, NULL, 28, 'logout', 'User logged out from IP: 197.210.70.187', '197.210.70.187', '2d0efc8e9906ba0552bb6f625465f865147298005c19ff96f6a58a9cae19ef0b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-18 16:32:00'),
(314, NULL, 21, 'password_reset', 'Password reset requested from IP: 197.210.70.187', '197.210.70.187', '2d0efc8e9906ba0552bb6f625465f865147298005c19ff96f6a58a9cae19ef0b', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-18 16:46:23'),
(315, NULL, 21, 'password_reset', 'Password reset requested from IP: 102.88.112.143', '102.88.112.143', 'fcf1660350ef203ae312963f5f777eebb485291d84a215757669fa95d9592db3', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-18 21:12:11'),
(316, NULL, NULL, 'csrf_validation_failed', 'CSRF token validation failed on forgot password', '102.88.112.143', 'fcf1660350ef203ae312963f5f777eebb485291d84a215757669fa95d9592db3', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-18 21:30:31'),
(317, NULL, NULL, 'rate_limit_exceeded', 'Password reset rate limit exceeded for email: lubunaaliyuabk@gmail.com', '102.88.112.143', 'fcf1660350ef203ae312963f5f777eebb485291d84a215757669fa95d9592db3', NULL, NULL, 50, 0, NULL, NULL, '2026-07-18 21:38:42'),
(318, NULL, 21, 'logout', 'User logged out from IP: 102.88.112.143', '102.88.112.143', 'fcf1660350ef203ae312963f5f777eebb485291d84a215757669fa95d9592db3', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-18 21:40:26'),
(319, NULL, 21, 'login', 'Successful login from IP: 102.88.112.143', '102.88.112.143', 'fcf1660350ef203ae312963f5f777eebb485291d84a215757669fa95d9592db3', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-18 22:41:31'),
(320, NULL, 21, 'logout', 'User logged out from IP: 102.88.112.143', '102.88.112.143', 'fcf1660350ef203ae312963f5f777eebb485291d84a215757669fa95d9592db3', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-18 22:41:47'),
(321, NULL, 21, 'login', 'Successful login from IP: 102.88.112.143', '102.88.112.143', 'fcf1660350ef203ae312963f5f777eebb485291d84a215757669fa95d9592db3', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-18 22:47:35'),
(322, NULL, 21, 'logout', 'User logged out from IP: 102.88.112.143', '102.88.112.143', 'fcf1660350ef203ae312963f5f777eebb485291d84a215757669fa95d9592db3', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-18 22:47:38'),
(323, NULL, 21, 'login', 'Successful login from IP: 102.88.112.143', '102.88.112.143', 'fcf1660350ef203ae312963f5f777eebb485291d84a215757669fa95d9592db3', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-18 22:57:43'),
(324, NULL, 21, 'logout', 'User logged out from IP: 102.88.112.143', '102.88.112.143', 'fcf1660350ef203ae312963f5f777eebb485291d84a215757669fa95d9592db3', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-18 22:57:44'),
(325, NULL, 21, 'login', 'Successful login from IP: 102.88.112.143', '102.88.112.143', 'fcf1660350ef203ae312963f5f777eebb485291d84a215757669fa95d9592db3', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-18 22:57:59'),
(326, NULL, 21, 'logout', 'User logged out from IP: 102.88.112.143', '102.88.112.143', 'fcf1660350ef203ae312963f5f777eebb485291d84a215757669fa95d9592db3', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-18 22:58:34'),
(327, NULL, 28, 'login', 'Successful login from IP: 102.88.112.143', '102.88.112.143', 'fcf1660350ef203ae312963f5f777eebb485291d84a215757669fa95d9592db3', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-18 22:58:39'),
(328, NULL, 28, 'logout', 'User logged out from IP: 102.88.112.143', '102.88.112.143', 'fcf1660350ef203ae312963f5f777eebb485291d84a215757669fa95d9592db3', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-18 22:59:51'),
(329, NULL, 25, 'login', 'Successful login from IP: 197.210.70.43', '197.210.70.43', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-19 11:32:11'),
(330, NULL, 25, 'logout', 'User logged out from IP: 197.210.70.43', '197.210.70.43', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-19 11:32:20'),
(331, NULL, 21, 'login', 'Successful login from IP: 197.210.70.43', '197.210.70.43', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-19 11:32:59'),
(332, NULL, 21, 'logout', 'User logged out from IP: 197.210.70.43', '197.210.70.43', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-19 11:33:13'),
(333, NULL, 21, 'login', 'Successful login from IP: 197.210.70.43', '197.210.70.43', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-19 11:33:40'),
(334, NULL, 21, 'logout', 'User logged out from IP: 197.210.70.43', '197.210.70.43', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-19 11:34:11'),
(335, NULL, 27, 'login', 'Successful login from IP: 197.210.70.43', '197.210.70.43', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-19 11:34:31'),
(336, NULL, 27, 'logout', 'User logged out from IP: 197.210.70.43', '197.210.70.43', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-19 12:23:36'),
(337, NULL, 27, 'login', 'Successful login from IP: 197.210.70.43', '197.210.70.43', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-19 12:23:38'),
(338, NULL, 21, 'login', 'Successful login from IP: 197.210.70.43', '197.210.70.43', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-19 15:03:51'),
(339, NULL, 27, 'login', 'Successful login from IP: 197.210.70.43', '197.210.70.43', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-19 16:27:22'),
(340, NULL, 27, 'logout', 'User logged out from IP: 197.210.70.43', '197.210.70.43', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-19 16:54:49'),
(341, NULL, 27, 'login', 'Successful login from IP: 102.91.103.170', '102.91.103.170', '9b9bd6260fcaddd147a31695595902b936b448e2f0b8007640f93a97ecd67767', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-23 15:46:28'),
(342, NULL, 27, 'login', 'Successful login from IP: 102.91.104.59', '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-24 13:33:17'),
(343, NULL, 27, 'login', 'Successful login from IP: 102.91.104.59', '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-24 14:03:27'),
(344, NULL, 27, 'logout', 'User logged out from IP: 102.91.104.59', '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-24 14:27:33'),
(345, NULL, 21, 'login', 'Successful login from IP: 102.91.104.59', '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-24 14:28:14'),
(346, NULL, 21, 'logout', 'User logged out from IP: 102.91.104.59', '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-24 14:29:22'),
(347, NULL, 32, 'login', 'Successful login from IP: 102.91.104.59', '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-24 14:29:25'),
(348, NULL, 32, 'logout', 'User logged out from IP: 102.91.104.59', '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-24 14:29:30'),
(349, NULL, 27, 'login', 'Successful login from IP: 102.91.104.59', '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-24 14:29:40'),
(350, NULL, 27, 'login', 'Successful login from IP: 102.91.104.59', '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-24 16:49:15'),
(351, NULL, 27, 'login', 'Successful login from IP: 102.91.104.59', '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-24 17:27:55'),
(352, NULL, 27, 'logout', 'User logged out from IP: 102.91.104.59', '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-24 18:10:28'),
(353, NULL, 27, 'login', 'Successful login from IP: 102.91.104.59', '102.91.104.59', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-24 18:10:45'),
(354, NULL, 27, 'logout', 'User logged out from IP: 102.91.105.161', '102.91.105.161', '9161a7e307dc2c1fa53c878bc0f2367a061e14095e5735af1246b23b0b22dcac', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-24 18:31:49'),
(355, NULL, 27, 'login', 'Successful login from IP: 102.91.105.161', '102.91.105.161', '9161a7e307dc2c1fa53c878bc0f2367a061e14095e5735af1246b23b0b22dcac', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-24 18:32:04'),
(356, NULL, 27, 'logout', 'User logged out from IP: 102.91.105.161', '102.91.105.161', '9161a7e307dc2c1fa53c878bc0f2367a061e14095e5735af1246b23b0b22dcac', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-24 18:34:25'),
(357, NULL, 27, 'login', 'Successful login from IP: 102.91.105.161', '102.91.105.161', '9161a7e307dc2c1fa53c878bc0f2367a061e14095e5735af1246b23b0b22dcac', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-24 18:34:44'),
(358, NULL, 27, 'login', 'Successful login from IP: 102.91.92.216', '102.91.92.216', 'f5828db79f1e3f5bac52690d9499d62aa54315ca12caaf090ed03ab01cd751d6', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-25 20:19:51'),
(359, NULL, 27, 'login', 'Successful login from IP: 197.210.53.133', '197.210.53.133', '94f663f7190b5b7a6c9ace22e788bb55e7bdf3a7bc8e8df84cf12df3aa3f377f', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-26 11:04:06'),
(360, NULL, 27, 'login', 'Successful login from IP: 197.210.53.133', '197.210.53.133', '94f663f7190b5b7a6c9ace22e788bb55e7bdf3a7bc8e8df84cf12df3aa3f377f', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-26 11:05:14'),
(361, NULL, 27, 'login', 'Successful login from IP: 197.210.53.133', '197.210.53.133', '94f663f7190b5b7a6c9ace22e788bb55e7bdf3a7bc8e8df84cf12df3aa3f377f', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-26 11:28:46'),
(362, NULL, 27, 'login', 'Successful login from IP: 102.91.103.236', '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-27 10:05:39'),
(363, NULL, 27, 'logout', 'User logged out from IP: 102.91.103.236', '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-27 10:06:38'),
(364, NULL, 7, 'login', 'Successful login from IP: 102.91.103.236', '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-27 10:07:01'),
(365, NULL, 7, 'login', 'Successful login from IP: 102.91.103.236', '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-27 12:26:40'),
(366, NULL, 7, 'logout', 'User logged out from IP: 102.91.103.236', '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-27 12:29:43'),
(367, NULL, 21, 'login', 'Successful login from IP: 102.91.103.236', '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-27 12:30:15'),
(368, NULL, 21, 'logout', 'User logged out from IP: 102.91.103.236', '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-27 12:56:11'),
(369, NULL, 33, 'login', 'Successful login from IP: 102.91.103.236', '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-27 12:56:16'),
(370, NULL, 33, 'logout', 'User logged out from IP: 102.91.103.236', '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-27 12:56:49'),
(371, NULL, 27, 'login', 'Successful login from IP: 102.91.103.236', '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-27 12:57:15'),
(372, NULL, 27, 'logout', 'User logged out from IP: 102.91.103.236', '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-27 12:57:21'),
(373, NULL, 21, 'login', 'Successful login from IP: 102.91.103.236', '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-27 12:58:39'),
(374, NULL, 21, 'logout', 'User logged out from IP: 102.91.103.236', '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-27 13:02:35'),
(375, NULL, 21, 'login', 'Successful login from IP: 102.91.103.236', '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-27 13:03:33'),
(376, NULL, 21, 'logout', 'User logged out from IP: 102.91.103.236', '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-27 13:06:50'),
(377, NULL, 34, 'login', 'Successful login from IP: 102.91.103.236', '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-27 13:08:52'),
(378, NULL, 34, 'logout', 'User logged out from IP: 102.91.103.236', '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-27 13:14:11'),
(379, NULL, 27, 'login', 'Successful login from IP: 102.91.103.236', '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-27 13:14:46'),
(380, NULL, 27, 'logout', 'User logged out from IP: 102.91.103.236', '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-27 13:14:54'),
(381, NULL, 21, 'login', 'Successful login from IP: 102.91.103.236', '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-27 13:15:32'),
(382, NULL, 21, '2fa_disabled', '2FA disabled from IP: 102.91.103.236', '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-27 13:16:21'),
(383, NULL, 21, 'logout', 'User logged out from IP: 102.91.103.236', '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-27 13:16:33'),
(384, NULL, 21, 'login', 'Successful login from IP: 102.91.103.236', '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-27 13:16:37'),
(385, NULL, 21, 'logout', 'User logged out from IP: 102.91.103.236', '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-27 13:31:26'),
(386, NULL, 34, 'login', 'Successful login from IP: 102.91.103.236', '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-27 13:31:35'),
(387, NULL, 34, 'logout', 'User logged out from IP: 102.91.103.236', '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-27 13:38:49'),
(388, NULL, 21, 'login', 'Successful login from IP: 102.91.103.236', '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-27 13:39:01'),
(389, NULL, 21, 'logout', 'User logged out from IP: 102.91.103.236', '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-27 13:39:44'),
(390, NULL, 34, 'login', 'Successful login from IP: 102.91.103.236', '102.91.103.236', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', NULL, NULL, NULL, 0, NULL, NULL, '2026-07-27 13:39:57');

-- --------------------------------------------------------

--
-- Table structure for table `senatorial_districts`
--

CREATE TABLE `senatorial_districts` (
  `id` int UNSIGNED NOT NULL,
  `state_id` int UNSIGNED NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lgas_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ;

--
-- Dumping data for table `senatorial_districts`
--

INSERT INTO `senatorial_districts` (`id`, `state_id`, `code`, `name`, `lgas_json`, `is_active`) VALUES
(1, 1, 'JN', 'Jigawa North West', '[\"Garki\",\"Babura\",\"Gumel\",\"Maigatari\",\"Gagarawa\",\"Sule Tankarkar\",\"Kazaure\",\"Roni\",\"Gwiwa\",\"Yankwashi\",\"Ringim\",\"Taura\"]', 1),
(2, 1, 'JE', 'Jigawa North East', '[\"Hadejia\",\"Kafin Hausa\",\"Mallam Madori\",\"Kaugama\"]', 1),
(3, 1, 'JS', 'Jigawa South West', '[\"Birnin Kudu\",\"Buji\",\"Birniwa\",\"Guri\",\"Kirikasamma\",\"Dutse\",\"Kiyawa\",\"Gwaram\",\"Jahun\",\"Miga\"]', 1);

-- --------------------------------------------------------

--
-- Table structure for table `states`
--

CREATE TABLE `states` (
  `id` int UNSIGNED NOT NULL,
  `code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `capital` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gps_lat` decimal(10,8) DEFAULT NULL,
  `gps_lng` decimal(11,8) DEFAULT NULL,
  `registered_voters` int UNSIGNED NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `states`
--

INSERT INTO `states` (`id`, `code`, `name`, `capital`, `gps_lat`, `gps_lng`, `registered_voters`, `is_active`, `created_at`) VALUES
(1, 'JG', 'Jigawa', 'Dutse', NULL, NULL, 0, 1, '2026-07-09 13:39:19');

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` bigint UNSIGNED NOT NULL,
  `tenant_id` bigint UNSIGNED NOT NULL,
  `plan_id` int DEFAULT NULL,
  `plan` enum('free','basic','standard','premium','enterprise') COLLATE utf8mb4_unicode_ci NOT NULL,
  `billing_cycle` enum('monthly','quarterly','yearly') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly',
  `amount` decimal(15,2) NOT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NGN',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `auto_renew` tinyint(1) NOT NULL DEFAULT '1',
  `payment_status` enum('pending','paid','overdue','cancelled','refunded') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `payment_method` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transaction_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoice_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subscriptions`
--

INSERT INTO `subscriptions` (`id`, `tenant_id`, `plan_id`, `plan`, `billing_cycle`, `amount`, `currency`, `start_date`, `end_date`, `auto_renew`, `payment_status`, `payment_method`, `transaction_reference`, `invoice_url`, `created_at`, `updated_at`, `status`) VALUES
(5, 14, NULL, 'premium', 'monthly', 10000000.00, 'NGN', '2026-07-09', '2026-08-08', 1, 'pending', NULL, NULL, NULL, '2026-07-09 15:07:12', '2026-07-09 15:08:53', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `subscription_plans`
--

CREATE TABLE `subscription_plans` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `duration_days` int NOT NULL DEFAULT '30',
  `user_limit` int NOT NULL DEFAULT '100',
  `storage_limit_mb` int NOT NULL DEFAULT '10240',
  `features` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `subscription_plans`
--

INSERT INTO `subscription_plans` (`id`, `name`, `price`, `duration_days`, `user_limit`, `storage_limit_mb`, `features`, `is_active`, `created_at`, `updated_at`) VALUES
(3, 'Premium', 10000000.00, 730, 100, 30240, 'All Features', 1, '2026-07-09 14:52:20', '2026-07-09 14:58:51');

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

CREATE TABLE `support_tickets` (
  `id` bigint UNSIGNED NOT NULL,
  `tenant_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `ticket_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` enum('technical','billing','feature_request','bug_report','account','security','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` enum('low','medium','high','urgent') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('open','in_progress','waiting','resolved','closed','escalated') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `assigned_to` bigint UNSIGNED DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `support_tickets`
--

INSERT INTO `support_tickets` (`id`, `tenant_id`, `user_id`, `ticket_number`, `category`, `priority`, `subject`, `description`, `status`, `assigned_to`, `resolved_at`, `created_at`, `updated_at`) VALUES
(2, 14, 7, 'TKT-2026-01215', 'billing', 'urgent', 'Fee Payment Issue', 'zdf', 'escalated', NULL, NULL, '2026-07-09 15:01:07', '2026-07-09 15:01:14');

-- --------------------------------------------------------

--
-- Table structure for table `support_ticket_replies`
--

CREATE TABLE `support_ticket_replies` (
  `id` bigint UNSIGNED NOT NULL,
  `ticket_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `attachment_urls_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `is_internal` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` bigint UNSIGNED NOT NULL,
  `key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('string','integer','boolean','json','array') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'string',
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_editable` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `key`, `value`, `type`, `description`, `is_editable`, `created_at`, `updated_at`) VALUES
(1, 'site_name', '5G Election Guru', 'string', 'Site name', 1, '2026-07-01 22:27:19', '2026-07-09 22:45:42'),
(2, 'max_login_attempts', '10', 'integer', 'Maximum login attempts before lockout', 1, '2026-07-01 22:27:19', '2026-07-09 22:45:42'),
(3, 'lockout_duration', '5', 'integer', 'Lockout duration in minutes', 1, '2026-07-01 22:27:19', '2026-07-02 23:22:33'),
(4, 'session_timeout', '3600', 'integer', 'Session timeout in seconds', 1, '2026-07-01 22:27:19', '2026-07-09 22:45:42'),
(5, 'otp_expiry', '300', 'integer', 'OTP expiry in seconds', 1, '2026-07-01 22:27:19', '2026-07-09 22:45:42'),
(6, 'two_factor_enabled', 'true', 'boolean', 'Enable two-factor authentication', 1, '2026-07-01 22:27:19', '2026-07-09 22:45:42'),
(7, 'backup_schedule_type', 'database', 'string', NULL, 1, '2026-07-02 07:05:06', '2026-07-02 07:08:28'),
(8, 'backup_schedule_frequency', 'daily', 'string', NULL, 1, '2026-07-02 07:05:06', '2026-07-02 07:08:28'),
(11, 'site_url', 'http://localhost/election', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-09 22:45:42'),
(12, 'contact_email', 'admin@5gguru.ng', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-09 22:45:42'),
(13, 'contact_phone', '+2348005555555', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-09 22:45:42'),
(14, 'timezone', 'Africa/Lagos', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-09 22:45:42'),
(15, 'captcha_enabled', 'false', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-09 22:45:42'),
(16, 'password_min_length', '8', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-09 22:45:42'),
(17, 'smtp_host', 'smtp.gmail.com', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-09 22:45:42'),
(18, 'smtp_port', '587', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-09 22:45:42'),
(19, 'smtp_username', 'aliyuabubakarjdh@gmail.com', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-09 22:45:42'),
(20, 'smtp_password', 'crhebdkjibmmwyqs', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-09 22:45:42'),
(21, 'smtp_encryption', 'tls', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-09 22:45:42'),
(22, 'sender_name', '5G Election Guru', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-09 22:45:42'),
(23, 'sender_email', 'no-reply@5gguru.ng', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-09 22:45:42'),
(24, 'max_upload_size', '10', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-09 22:45:42'),
(25, 'allowed_file_types', 'jpg,jpeg,png,gif,svg,pdf,doc,docx,xls,xlsx,csv', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-09 22:45:42'),
(26, 'storage_path', '../uploads/', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-02 07:19:17'),
(27, 'auto_backup_enabled', 'true', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-09 22:45:42'),
(28, 'backup_frequency', 'daily', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-09 22:45:42'),
(29, 'backup_retention', '30', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-09 22:45:42'),
(30, 'backup_time', '02:00', 'string', NULL, 1, '2026-07-02 07:18:50', '2026-07-09 22:45:42');

-- --------------------------------------------------------

--
-- Table structure for table `tenants`
--

CREATE TABLE `tenants` (
  `id` bigint UNSIGNED NOT NULL,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('political_party','candidate','ngo','observer_group','cso','research_institution') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'political_party',
  `subscription_plan` enum('free','basic','standard','premium','enterprise') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'basic',
  `subscription_status` enum('trial','active','suspended','expired','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'trial',
  `subscription_start` date DEFAULT NULL,
  `subscription_end` date DEFAULT NULL,
  `max_users` int UNSIGNED NOT NULL DEFAULT '100',
  `max_agents` int UNSIGNED NOT NULL DEFAULT '500',
  `max_storage_mb` bigint UNSIGNED NOT NULL DEFAULT '10737418240',
  `used_storage_mb` bigint UNSIGNED NOT NULL DEFAULT '0',
  `logo_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `primary_color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT '#3b82f6',
  `secondary_color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT '#10b981',
  `contact_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `state_id` int UNSIGNED DEFAULT NULL,
  `lga_id` int UNSIGNED DEFAULT NULL,
  `settings_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL
) ;

--
-- Dumping data for table `tenants`
--

INSERT INTO `tenants` (`id`, `uuid`, `name`, `slug`, `type`, `subscription_plan`, `subscription_status`, `subscription_start`, `subscription_end`, `max_users`, `max_agents`, `max_storage_mb`, `used_storage_mb`, `logo_url`, `primary_color`, `secondary_color`, `contact_email`, `contact_phone`, `address`, `state_id`, `lga_id`, `settings_json`, `is_active`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(14, '6a4fad242b5ce-0d02d10eca9a58f8', 'All Progressive Congress', 'all-progressive-congress', 'political_party', 'premium', 'trial', '2026-07-09', '2026-08-08', 100, 500, 10737418240, 0, '/election/uploads/tenants/tenant_14_1783610314.jpg', '#f73b61', '#b4b710', 'aliyuabubakarjdh@gmail.com', '+2349027702002', 'Kangire, Birninkudu\r\nKangire', 1, 1, NULL, 1, 7, '2026-07-09 14:16:04', '2026-07-09 15:18:34', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tenant_settings`
--

CREATE TABLE `tenant_settings` (
  `id` bigint UNSIGNED NOT NULL,
  `tenant_id` bigint UNSIGNED NOT NULL,
  `key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('string','integer','boolean','json','array') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'string'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tenant_settings`
--

INSERT INTO `tenant_settings` (`id`, `tenant_id`, `key`, `value`, `type`) VALUES
(1, 14, 'user_notification_email', '1', 'string'),
(2, 14, 'user_notification_inapp', '1', 'string'),
(3, 14, 'user_language', 'en', 'string');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint UNSIGNED NOT NULL,
  `tenant_id` bigint UNSIGNED DEFAULT NULL,
  `user_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role_id` bigint UNSIGNED NOT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(200) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (concat(`first_name`,_utf8mb4' ',`last_name`)) STORED,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `google_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone_verified_at` timestamp NULL DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `two_factor_secret` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `two_factor_verified_at` timestamp NULL DEFAULT NULL,
  `gender` enum('male','female','other','prefer_not_say') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `photograph_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nin` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bvn` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `next_of_kin_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `next_of_kin_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `residential_address` text COLLATE utf8mb4_unicode_ci,
  `state_id` int UNSIGNED DEFAULT NULL,
  `lga_id` int UNSIGNED DEFAULT NULL,
  `ward_id` int UNSIGNED DEFAULT NULL,
  `pu_id` int UNSIGNED DEFAULT NULL,
  `senatorial_id` int UNSIGNED DEFAULT NULL,
  `federal_constituency_id` int UNSIGNED DEFAULT NULL,
  `jurisdiction_type` enum('national','state','senatorial','federal_constituency','lga','ward','pu') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jurisdiction_id` bigint UNSIGNED DEFAULT NULL,
  `device_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_fingerprint` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_bound` tinyint(1) NOT NULL DEFAULT '1',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_login_device` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_login_gps_lat` decimal(10,8) DEFAULT NULL,
  `last_login_gps_lng` decimal(11,8) DEFAULT NULL,
  `login_attempts` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `locked_until` timestamp NULL DEFAULT NULL,
  `status` enum('active','suspended','pending','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `tenant_id`, `user_code`, `role_id`, `first_name`, `last_name`, `email`, `google_id`, `avatar`, `phone`, `phone_verified_at`, `password_hash`, `remember_token`, `two_factor_secret`, `two_factor_enabled`, `two_factor_verified_at`, `gender`, `date_of_birth`, `photograph_url`, `nin`, `bvn`, `bank_name`, `account_number`, `account_name`, `emergency_contact_name`, `emergency_contact_phone`, `next_of_kin_name`, `next_of_kin_phone`, `residential_address`, `state_id`, `lga_id`, `ward_id`, `pu_id`, `senatorial_id`, `federal_constituency_id`, `jurisdiction_type`, `jurisdiction_id`, `device_id`, `device_fingerprint`, `device_bound`, `last_login_at`, `last_login_ip`, `last_login_device`, `last_login_gps_lat`, `last_login_gps_lng`, `login_attempts`, `locked_until`, `status`, `email_verified_at`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(7, NULL, 'ADMIN001', 1, 'Super', 'Admin', 'aliyuabubakar11117@gmail.com', NULL, NULL, '+2348005555555', NULL, '$2y$10$gPZC.B3tnq9mjtLvr4SxseQzsfZzlkaSzzzktAIlSpeSd0qp1ldIO', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-07-27 12:26:40', '102.91.103.236', NULL, NULL, NULL, 0, NULL, 'active', '2026-07-02 17:23:24', NULL, '2026-07-02 17:23:24', '2026-07-27 12:26:40', NULL),
(21, 14, 'USR000014', 2, 'Aliyu', 'Abubakar', 'lubunaaliyuabk@gmail.com', NULL, NULL, '+2348034897638', NULL, '$2y$10$Ji4dxGUlacL6Sy3Dk7ncUu0J8nJJ7e3.D4X5t5GImMZSLNL3gRiPC', NULL, NULL, 0, NULL, 'male', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-07-27 13:39:01', '102.91.103.236', NULL, NULL, NULL, 0, NULL, 'active', '2026-07-09 14:16:04', 7, '2026-07-09 14:16:04', '2026-07-27 13:39:01', NULL),
(22, 14, 'USR000002', 3, 'Aliyu', 'Abubakar', 'aliyuabubakardh@gmail.com', NULL, NULL, '+2349027702002', NULL, '$2y$10$Gk2pc7ug2HrHsICzhnAwSuRNwtJJFJtnzXX4EwzTcUybZ/qqb6T6q', NULL, NULL, 0, NULL, 'male', '2002-02-24', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-07-18 15:59:39', '197.210.70.187', NULL, NULL, NULL, 0, NULL, 'active', NULL, 7, '2026-07-09 14:23:04', '2026-07-18 15:59:39', NULL),
(23, 14, 'USR000003', 4, 'ibrahim', 'sule', 'ibrahim@gmail.com', NULL, NULL, '+2348034907634', NULL, '$2y$10$8ec1HFTe/V2ngpXvR9jc8OeY5VdY3MuqdzHE84dBUy6xcoKQwdZy.', NULL, NULL, 0, NULL, 'male', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, 'state', 1, NULL, NULL, 1, '2026-07-15 23:00:53', '102.91.77.202', NULL, NULL, NULL, 0, NULL, 'active', NULL, 21, '2026-07-09 15:25:02', '2026-07-15 23:00:53', NULL),
(24, 14, 'AGT13713', 4, 'isah', 'Musa', 'agent@gmail.com', NULL, NULL, '80335673727', NULL, '$2y$10$XzaBC1oT6nxMUj5qgRL3COpmlhDmAPNNN3zIyXoGzw1NshtN.7tXi', NULL, NULL, 0, NULL, 'male', '2010-02-09', '/election/uploads/profiles/profile_24_1783680306.png', '79476978233', NULL, 'Sterling', '8034897634', 'Aliyu Abubakar', NULL, NULL, NULL, NULL, '', 1, NULL, NULL, NULL, NULL, NULL, 'state', 1, NULL, NULL, 1, '2026-07-10 10:50:51', '10.59.66.251', NULL, NULL, NULL, 0, NULL, 'active', NULL, 21, '2026-07-09 22:06:13', '2026-07-10 10:50:51', NULL),
(25, 14, 'LGA000005', 7, 'Aliyu', 'Abubakar', 'aliyuabubakar1111@gmail.com', NULL, NULL, '+23484897634', NULL, '$2y$10$5H9bRatgQ00gHK3QX6Ror.zaZfi00IXR7WmjjXSeUI2eVHaa0vjMa', NULL, NULL, 0, NULL, 'male', NULL, '/election/uploads/profiles/profile_25_1783683350.jpg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL, 'lga', 1, NULL, NULL, 1, '2026-07-19 11:32:11', '197.210.70.43', NULL, NULL, NULL, 0, NULL, 'active', NULL, 24, '2026-07-09 23:32:21', '2026-07-19 11:32:11', NULL),
(26, 14, 'AGT93914', 9, 'Aliyu', 'Abubakar', 'agent1@gmail.com', NULL, NULL, '+2348034897634', NULL, '$2y$10$NV.vq2GfMqHTTBditi/5BuyYpZ7R1ysfpcMM1gXVxZZaVWP3cNNCW', NULL, NULL, 0, NULL, 'male', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Kangire, Birninkudu\r\nNigeria', 1, 1, 1, 3, NULL, NULL, 'pu', 1, NULL, NULL, 1, '2026-07-26 11:29:22', '197.210.53.133', NULL, NULL, NULL, 0, NULL, 'active', NULL, 25, '2026-07-10 13:13:42', '2026-07-26 11:29:22', NULL),
(27, 14, 'USR801680', 8, 'Aliyu', 'Abubakar', 'abarshiaminu2005@gmail.com', NULL, NULL, '+2348034897634', NULL, '$2y$10$wzx3ez2cSkeWfmdXvA1pleLbP4cQmAWuqScAQmezx58f.7Z77mSX2', NULL, NULL, 0, NULL, 'male', NULL, '/election/uploads/profiles/profile_27_1784468674.jpg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Kangire, Birninkudu', 1, 1, 1, NULL, NULL, NULL, 'ward', 1, NULL, NULL, 1, '2026-07-27 13:14:46', '102.91.103.236', NULL, NULL, NULL, 0, NULL, 'active', NULL, 21, '2026-07-10 13:33:57', '2026-07-27 13:14:46', NULL),
(28, 14, 'USR000008', 1, 'Aliyu', 'Yahaya', 'kowagurutechltd@gmail.com', NULL, NULL, '09032356601', NULL, '$2y$10$3ikAaNWR/nR2roodOPzj/.4TTmb1pXSatJLXLUTYH12qH3tQSfxUK', NULL, NULL, 1, NULL, 'male', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-07-18 22:58:39', '102.88.112.143', NULL, NULL, NULL, 0, NULL, 'active', NULL, 7, '2026-07-10 22:22:27', '2026-07-18 22:59:49', NULL),
(29, 14, 'USR000009', 10, 'Aliyu', 'Abubakar', 'agent2@gmail.com', NULL, NULL, '+2348034897634', NULL, '$2y$10$8LCOUoCl0G4VaSeqYW.M7uYN5BmCDCV3y5VRcI97XAYgCa5VCDOsa', NULL, NULL, 0, NULL, 'male', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 1, 28, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-07-26 11:06:02', '197.210.53.133', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, NULL, 0, NULL, 'active', NULL, 7, '2026-07-11 13:03:12', '2026-07-26 11:06:02', NULL),
(30, 14, 'AGT36886', 15, 'ba sule', 'jumbe', 'agent3@gmail.com', NULL, NULL, '9765678765456', NULL, '$2y$10$p1//WAheNDiTBX39oq5MJuKtYlH6dy.A4AGJYiyrb4DXMWR8w7VcO', NULL, NULL, 0, NULL, 'male', NULL, NULL, '79476978233', NULL, 'Sterling', '1234567890', 'Aliyu Abubakar', NULL, NULL, NULL, NULL, 'Kangire, Birninkudu\r\nNigeria', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-07-19 16:57:43', '197.210.70.43', NULL, NULL, NULL, 0, NULL, 'active', NULL, 21, '2026-07-16 09:33:49', '2026-07-24 16:30:09', NULL),
(31, 14, 'USR000011', 15, 'Aliyu', 'Abubakar', 'observer@gmail.com', NULL, NULL, '+2349027702002', NULL, '$2y$10$nQg3tg/H4FbK5haAE9mpaeoPeADaTw0r7iD6vYzwVEDEhHtAZmi82', NULL, NULL, 0, NULL, 'male', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-07-19 16:56:41', '197.210.70.43', NULL, NULL, NULL, 0, NULL, 'active', NULL, 28, '2026-07-16 10:02:30', '2026-07-24 15:14:39', NULL),
(32, 14, 'USR060557', 15, 'Aliyu', 'Abubakar', 'volunteer@gmail.com', NULL, NULL, '+23480348934', NULL, '$2y$10$HaQM4jNTmcrh8cRSyYJLN.uYlqbFfVn0H4DkavJ0.buvROOuUsVjG', NULL, NULL, 0, NULL, 'male', NULL, NULL, '79476978233', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Birnin kudu 1', NULL, NULL, 1, 28, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-07-24 14:29:25', '102.91.104.59', NULL, NULL, NULL, 0, NULL, 'active', NULL, 21, '2026-07-24 14:29:16', '2026-07-24 16:03:54', NULL),
(33, 14, 'USR092249', 6, 'federal', 'cons', 'federal@gmail.com', NULL, NULL, '+234902702002', NULL, '$2y$10$425U7I7MjfaFsfLDr7gnieZs/3gwMOQQOlUXBNRGOuLVGXcsUN4v2', NULL, NULL, 0, NULL, 'male', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Kangire', 1, NULL, NULL, NULL, NULL, 7, NULL, NULL, NULL, NULL, 1, '2026-07-27 12:56:16', '102.91.103.236', NULL, NULL, NULL, 0, NULL, 'active', NULL, 21, '2026-07-27 12:56:05', '2026-07-27 12:56:16', NULL),
(34, 14, 'USR914497', 5, 'senatorial', 'cood', 'senatorial@gmail.com', NULL, NULL, '+2349027702002', NULL, '$2y$10$g8Jt81qod3HCyVnXfVO.XepykUPA5ehEwWraza2cXtnmxYgVircDa', NULL, NULL, 0, NULL, 'male', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Kangire', 1, NULL, NULL, NULL, 3, NULL, NULL, NULL, NULL, NULL, 1, '2026-07-27 13:39:57', '102.91.103.236', NULL, NULL, NULL, 0, NULL, 'active', NULL, 21, '2026-07-27 13:06:44', '2026-07-27 13:39:57', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `token` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `device_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_type` enum('web','android','ios') COLLATE utf8mb4_unicode_ci NOT NULL,
  `device_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gps_lat` decimal(10,8) DEFAULT NULL,
  `gps_lng` decimal(11,8) DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `expires_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_activity_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
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
(43, 7, '9fb683a3af9161792dcfda3b0c0189b71a532c4f36f4cbcca9942084e37c63a3', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-03 23:30:25', '2026-07-03 23:30:25', 0, '2026-07-03 23:24:07'),
(76, 7, 'dbf4e14cedfbc1ba35a257ddca187970e93f35cac317e69a435a77151e4b0bbc', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-05 09:46:36', '2026-07-05 09:46:36', 0, '2026-07-05 09:46:17'),
(77, 7, '3a95ee4a505921e58dbee800af5d7a121847de38670f1c7f4c4600f17d085be7', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-05 09:49:55', '2026-07-05 09:49:55', 0, '2026-07-05 09:46:42'),
(79, 7, 'a82eb19196aa3be59f9485237ef96943f0c32475abce8f58305c0f7b445c6f6a', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-05 10:55:56', '2026-07-05 09:55:56', 1, '2026-07-05 09:55:56'),
(80, 7, '7844918fc4a426666879a5b68eae5af825f34f01b76f948eba537ffdf81911df', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-06 17:30:01', '2026-07-06 17:30:01', 0, '2026-07-06 17:29:54'),
(82, 7, 'c7f32ffe5bdc4c67439050370e5ffad2654c637012b8e98d5e18e4246a83f055', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-07 18:34:14', '2026-07-07 18:34:14', 0, '2026-07-07 18:33:45'),
(86, 7, '32c849eb5629d7b97c974f81935a318c9509250c96d50cb6c93a6579d5c2da8d', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-09 13:40:54', '2026-07-09 13:40:54', 0, '2026-07-09 13:39:56'),
(87, 7, '72c87cba4c05a197678887c658cb5bc521851f59e0d9dce7b5d3317b2f042a0e', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-09 13:55:45', '2026-07-09 13:55:45', 0, '2026-07-09 13:41:01'),
(90, 7, '3acfc99d5a75f20a76047aa97cbd6bec67d386be8b20f1125f5c4fb95bc56958', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-09 14:07:34', '2026-07-09 14:07:34', 0, '2026-07-09 13:58:39'),
(91, 7, '17ef47fb1e6ad7e61973e0a0aae16731fe6126ce5b474a4bcf2631457b605136', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-09 14:19:16', '2026-07-09 14:19:16', 0, '2026-07-09 14:07:48'),
(92, 21, '92014cabdf135efc6a00cbde35871d38f4bb7049c15754ad2e901715d3f4f006', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-09 14:19:28', '2026-07-09 14:19:28', 0, '2026-07-09 14:19:22'),
(93, 7, '183e3312dff05dc3cf94025a83df9a403cf4ad09bdbcfa38baa38ae26667b8a4', '7d5f7e3029f983fcc3b5b85ccb1101184a5629cafb15f11a557a869b7042fb8b', 'web', NULL, '::1', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-09 15:19:33', '2026-07-09 14:19:33', 1, '2026-07-09 14:19:33'),
(94, 7, '3f3ecdb64dccc361fcfb1a299289f2a727845a1867f8de7eda3381c073c87871', '6a579af1c0bb49fd2d0ad7c141c0a89d250c86c365d5163cf76ddb0837b72c75', 'web', NULL, '10.59.66.251', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-09 15:13:57', '2026-07-09 15:13:57', 0, '2026-07-09 14:28:16'),
(95, 21, '83d602a68f9d6ee291e0334ad51c64a2f566a9e81a11593f9d814b4d8a9dee31', '6a579af1c0bb49fd2d0ad7c141c0a89d250c86c365d5163cf76ddb0837b72c75', 'web', NULL, '10.59.66.251', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-09 15:32:07', '2026-07-09 15:32:07', 0, '2026-07-09 15:14:32'),
(96, 21, '9c7ccf1776b49c40397e13bde7e802ddd9d8248b35d30636702146e1c35fe6a8', '6a579af1c0bb49fd2d0ad7c141c0a89d250c86c365d5163cf76ddb0837b72c75', 'web', NULL, '10.59.66.251', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-09 16:34:10', '2026-07-09 15:34:10', 1, '2026-07-09 15:34:10'),
(97, 21, '1d26b1743ed2875caefb75bd89ee4ecacab777a89a36afde8b1c4ac991b8506a', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', 'web', NULL, '10.59.66.251', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-09 22:13:26', '2026-07-09 22:13:26', 0, '2026-07-09 21:35:07'),
(98, 21, '5c0d5bd28c234c57963404f0e391ac0336c8df1c279ce31ef070efb8484dd805', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', 'web', NULL, '10.59.66.251', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-09 22:15:09', '2026-07-09 22:15:09', 0, '2026-07-09 22:14:24'),
(99, 22, 'c569cbc10fbee8b1ac1a1be02d093979d699ad105d11852a18e04ab976171a0a', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', 'web', NULL, '10.59.66.251', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-09 22:23:08', '2026-07-09 22:23:08', 0, '2026-07-09 22:15:20'),
(100, 21, '40c91825ef4537f0b9a25aa68b6ca87d6e16dd40ae52bde527e9bc9839a03608', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', 'web', NULL, '10.59.66.251', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-09 22:24:11', '2026-07-09 22:24:11', 0, '2026-07-09 22:23:37'),
(101, 22, 'f3f74c0718a51b335e2d9e9a46a2279cc4733294ebb82a51cc77679a14567aea', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', 'web', NULL, '10.59.66.251', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-09 22:43:58', '2026-07-09 22:43:58', 0, '2026-07-09 22:24:15'),
(102, 22, 'f740b57471f18b38e228bef342111a29d379e1f99760481e4db6df21c4416338', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', 'web', NULL, '10.59.66.251', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-09 22:45:54', '2026-07-09 22:45:54', 0, '2026-07-09 22:45:11'),
(103, 21, '94ee6179210d62b29fe1266d3b54da46a8cd4d55c18e7db3c9f2400a09e51f8d', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', 'web', NULL, '10.59.66.251', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-09 22:47:04', '2026-07-09 22:47:04', 0, '2026-07-09 22:45:59'),
(104, 24, '6ee38b73fb0992efcec66925dd8c17296a172865b046f657e61f77c1e825a845', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', 'web', NULL, '10.59.66.251', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-09 22:55:15', '2026-07-09 22:55:15', 0, '2026-07-09 22:47:17'),
(105, 21, '929e2d9b3d5de494ad9506cd9a1596e90303fbb697e2a7de3a32dd8eb74c7540', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', 'web', NULL, '10.59.66.251', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-09 22:55:36', '2026-07-09 22:55:36', 0, '2026-07-09 22:55:20'),
(106, 7, 'e0b7c02009d511c924bbee4c0afc80eb757d42f33190a24080b25a95b16eaa0c', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', 'web', NULL, '10.59.66.251', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-09 23:18:19', '2026-07-09 23:18:19', 0, '2026-07-09 22:55:41'),
(107, 24, 'd891018ddc81e79156dcf869d08357f774114c91fc9cd0a2a6b7d5e4dfbd87b0', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', 'web', NULL, '10.59.66.251', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-09 23:23:56', '2026-07-09 23:23:56', 0, '2026-07-09 23:18:23'),
(108, 22, '4ac323179620678501d612954beb6a3f999fef4ebcafb64bd92ca8247ae52c2d', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', 'web', NULL, '10.59.66.251', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-09 23:24:11', '2026-07-09 23:24:11', 0, '2026-07-09 23:24:03'),
(109, 7, '60d93f401292c1cc9b6dd0e1822bb4697e7f202b4f5b152126c257c828887467', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', 'web', NULL, '10.59.66.251', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-09 23:24:21', '2026-07-09 23:24:21', 0, '2026-07-09 23:24:14'),
(110, 21, '1eab4badfc2199b4781947a68519427f0bdf098c25b577c1acca6b9d5f1fdfdf', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', 'web', NULL, '10.59.66.251', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-09 23:24:43', '2026-07-09 23:24:43', 0, '2026-07-09 23:24:24'),
(111, 7, '966267b020dc29d446853b917f245e39e5dfcdb3bae7ae5ce44d99d711d7c56d', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', 'web', NULL, '10.59.66.251', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-09 23:25:30', '2026-07-09 23:25:30', 0, '2026-07-09 23:24:48'),
(112, 24, '4768e2bec5d64ef9e2cf8ff17a508163fdbd172225a24d4a4dc97c858da6c298', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', 'web', NULL, '10.59.66.251', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-10 00:25:34', '2026-07-09 23:25:34', 1, '2026-07-09 23:25:34'),
(113, 24, '19e18ac1fb3740c0619cfcefaf8bfb4e4f9703a59f0bc3e2e54549c7c7dce8fc', 'dd480bfcd698f240d48dad2252eca7303e4789762f7c183629a220a9d04693e5', 'web', NULL, '10.59.66.157', NULL, NULL, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '2026-07-10 10:12:59', '2026-07-10 09:12:59', 1, '2026-07-10 09:12:59'),
(114, 24, 'f53f88b5553ee6537816280d0a7a8ed4cb1190d753e3c93ab93a3d0b941b0128', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', 'web', NULL, '10.59.66.251', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-10 10:44:48', '2026-07-10 10:44:48', 0, '2026-07-10 09:13:44'),
(115, 21, '9b69d617c4511fe67baf947d434c3c1475ac84295ea859990458577479031d3e', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', 'web', NULL, '10.59.66.251', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-10 10:50:47', '2026-07-10 10:50:47', 0, '2026-07-10 10:48:11'),
(116, 24, '19047e4e97dc47e7466109bd134048a09174c390e9cec46962d96b31cfaf6c77', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', 'web', NULL, '10.59.66.251', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-10 10:51:56', '2026-07-10 10:51:56', 0, '2026-07-10 10:50:51'),
(117, 25, 'f7e437da5f85038510da6820ecf3876bc38b9e4a15929a75fe65c36926b78958', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', 'web', NULL, '10.59.66.251', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-10 13:14:29', '2026-07-10 13:14:29', 0, '2026-07-10 10:52:02'),
(118, 21, 'a6b9f813b11895b20f3edab6f44a003591b5451d7ef27cac7f0c11cc4ed3f146', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', 'web', NULL, '10.59.66.251', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-10 13:17:37', '2026-07-10 13:17:37', 0, '2026-07-10 13:14:33'),
(119, 21, 'd243632ded1aae375402da8ca4f831b9ecb49db2b8ea691f63b50a2006aa9e0d', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', 'web', NULL, '10.59.66.251', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-10 13:34:56', '2026-07-10 13:34:56', 0, '2026-07-10 13:17:56'),
(120, 26, '7bb5aa110b494f99bae44cdc542c0326a7f9d0726f881a166fee284a3f4f4a4c', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', 'web', NULL, '10.59.66.251', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-10 13:35:04', '2026-07-10 13:35:04', 0, '2026-07-10 13:35:02'),
(121, 27, '73cd5e61431476a745dbd1d9f395113b83e782656b50784f182e3900a86a28b7', 'a1eac6408331efd7d538b964c6b01411ba465d414a9e33463151c387ef2f8ca8', 'web', NULL, '10.59.66.251', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-10 14:35:08', '2026-07-10 13:35:08', 1, '2026-07-10 13:35:08'),
(122, 21, '75a0def2a5c3730e8be94814723ebe7b61adebd4d5ee6d96316f25f7998ad442', 'f80c32747451414de1a1fda16c5bda7fbc56e01058e440f49dc05b8bb239bf2a', 'web', NULL, '102.89.83.93', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-10 22:13:54', '2026-07-10 22:13:54', 0, '2026-07-10 22:12:33'),
(123, 21, 'bee21fd40daef09e4e5dbd1fb6bb3bfb3fb529248dee87e37949cf98f2972129', 'f80c32747451414de1a1fda16c5bda7fbc56e01058e440f49dc05b8bb239bf2a', 'web', NULL, '102.89.83.93', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-10 22:14:26', '2026-07-10 22:14:26', 0, '2026-07-10 22:14:21'),
(124, 7, '1560aa8445d3dfaf93c7417e3b94c21297cfc88a504efbf3d2124ad950d29303', 'f80c32747451414de1a1fda16c5bda7fbc56e01058e440f49dc05b8bb239bf2a', 'web', NULL, '102.89.83.93', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-10 22:19:28', '2026-07-10 22:19:28', 0, '2026-07-10 22:15:05'),
(125, 7, 'fabfa514eb9a0da974c7e1cdbf5268c2f465b18f64dc7addcb8649147d4cd46f', 'f80c32747451414de1a1fda16c5bda7fbc56e01058e440f49dc05b8bb239bf2a', 'web', NULL, '102.89.83.93', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-10 22:23:04', '2026-07-10 22:23:04', 0, '2026-07-10 22:21:02'),
(126, 28, 'af8b95e744f4447d62a1dc9c26c135911e7951b3e95d2615878eed9c11baa5f2', 'f80c32747451414de1a1fda16c5bda7fbc56e01058e440f49dc05b8bb239bf2a', 'web', NULL, '102.89.83.93', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-10 22:26:21', '2026-07-10 22:26:21', 0, '2026-07-10 22:23:17'),
(127, 28, 'a1d616049930e0e425af625332b541acfc2a6f3bd7262798553ee8951c173df2', 'f80c32747451414de1a1fda16c5bda7fbc56e01058e440f49dc05b8bb239bf2a', 'web', NULL, '102.89.83.93', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-10 21:26:24', '2026-07-10 22:26:24', 1, '2026-07-10 22:26:24'),
(128, 28, 'bcbf7ce61d3305ef4389a78c30ff1d386dbc1d13f25ee4d992da991411d4ea59', 'aab9a5d60b16af51ae0bc446d37a7f46291804f146a380ac2d1964db17dce571', 'web', NULL, '102.88.115.148', NULL, NULL, 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-10 21:29:58', '2026-07-10 22:29:58', 1, '2026-07-10 22:29:58'),
(129, 28, '8efd7c72a29c7de56e6e846eee3a621fef4fc06994c7ccf843d720ad8e990953', '0ab3a1a7c3c255d97ad36a930f16547ce0bdea8df526c8c00a7267d91c981ef3', 'web', NULL, '102.91.103.45', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-10 22:26:28', '2026-07-10 23:26:28', 1, '2026-07-10 23:26:28'),
(130, 21, '93f10b07d7c40a8716c3afedbf2aa2f18938b405ca77e97052eac0f1421543f4', '39efd42b46b61b96c194f3b9966cd708e2c3590971a1adeabf023e80852f83f2', 'web', NULL, '102.91.103.29', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-11 13:02:07', '2026-07-11 13:02:07', 0, '2026-07-11 13:00:31'),
(131, 22, 'e9f3a92f213401d11ca6d38f09220ec0324ac87cb8bc2110c3b2d4f8691e6ff8', '39efd42b46b61b96c194f3b9966cd708e2c3590971a1adeabf023e80852f83f2', 'web', NULL, '102.91.103.29', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-11 13:02:21', '2026-07-11 13:02:21', 0, '2026-07-11 13:02:11'),
(132, 7, '4707a35d52223d03d6e21362a5176790534d906bc219c6349d89206f973acc5b', '39efd42b46b61b96c194f3b9966cd708e2c3590971a1adeabf023e80852f83f2', 'web', NULL, '102.91.103.29', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-11 13:03:15', '2026-07-11 13:03:15', 0, '2026-07-11 13:02:29'),
(133, 29, 'b293bcf617f0873e8fdb4b98496728c9d7b47770b4c551a04a30e8d27e0e0b31', '39efd42b46b61b96c194f3b9966cd708e2c3590971a1adeabf023e80852f83f2', 'web', NULL, '102.91.103.29', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-11 13:04:05', '2026-07-11 13:04:05', 0, '2026-07-11 13:03:59'),
(134, 29, 'b1ec97f497706a88a59ab9ad61f6497c3ed48e0f8a78fcc8a9dfda0bc2eac732', NULL, '', 'device_1783776581467', '102.91.104.170', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-11 13:41:22', '2026-07-11 13:41:22', 1, '2026-07-11 13:41:22'),
(135, 29, '880ae696608803e97dd1582be5aa1231c531d19c2025589227f75513648aaf1e', NULL, '', 'device_1783776581467', '102.91.104.170', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-11 13:45:20', '2026-07-11 13:45:20', 1, '2026-07-11 13:45:20'),
(136, 28, '417f7d244942d26ea411752a6d867bdf6b97f9cdb25add67e8719d84494bafdf', 'e94c934aed781183f918b54585376a63bfe639d25f80032384e2a58ec9517a05', 'web', NULL, '102.91.104.170', NULL, NULL, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36', '2026-07-11 12:48:58', '2026-07-11 13:48:58', 1, '2026-07-11 13:48:58'),
(137, 29, '73e2b9ee6573f91508762e2ca0b18c9be614f4033d858c9ccdeddd3b7dea1bf9', NULL, '', 'device_1783778312162', '102.91.104.170', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-11 13:57:52', '2026-07-11 13:57:52', 1, '2026-07-11 13:57:52'),
(138, 28, '9857b53136d557adfaf913060eed62a0cdefe9cf846c47cf4c7d1e50d0fba47f', '9ce7f450064053f72a15eefe50245efa182b4fef095ed6bcbf1f99f48857dcdf', 'web', NULL, '102.91.104.170', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-11 12:59:26', '2026-07-11 13:59:26', 1, '2026-07-11 13:59:26'),
(139, 28, '11a55aace85730739c122fc41efd90672f24d3d61ea19a28ff7fd19d7e7301e1', 'ca593686404cf83fd1f2a1d22e731b0a765dfb51330beba05bb8621cbf8f2516', 'web', NULL, '102.91.92.135', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-07-11 13:13:19', '2026-07-11 14:13:19', 1, '2026-07-11 14:13:19'),
(140, 29, 'c61891577e5ffdef39445c361ab883098101b005e0388af07fc7d3e66c09e79d', NULL, '', 'device_1783872531700', '102.91.105.27', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-12 16:08:09', '2026-07-12 16:08:09', 1, '2026-07-12 16:08:09'),
(141, 29, '1d981266af36a725defb826154f36a9c91a61e865456f8442e7327ec1122ca93', NULL, '', 'device_1783872531700', '102.91.105.27', NULL, NULL, 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-12 16:11:50', '2026-07-12 16:11:50', 1, '2026-07-12 16:11:50'),
(142, 29, '5eeb421dd8252c2de49661cb3b4386aa661c22ec3b9d17c9cea1474c40afbfc7', NULL, '', 'device_1783872531700', '102.91.105.27', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-12 16:13:06', '2026-07-12 16:13:06', 1, '2026-07-12 16:13:06'),
(143, 29, 'aa5002b47f2fc55512307d18e43ba8e48a99c357093742ec677ad7b3d80d211b', NULL, '', 'device_1783874367629', '102.91.105.27', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-12 16:38:45', '2026-07-12 16:38:45', 1, '2026-07-12 16:38:45'),
(144, 29, 'a16c06d58f4ff2e310a210aa0b165dc182d58d39ac5ef23be3d4d2b0a8d11470', NULL, '', 'device_1783874885944', '102.91.105.27', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-12 16:47:32', '2026-07-12 16:47:32', 1, '2026-07-12 16:47:32'),
(145, 29, 'afccca58576fd759a38605eb35320d380edff95f42badcc9222bff1bbe0e8b9d', NULL, '', 'device_1783881078953', '102.91.78.40', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-12 18:30:37', '2026-07-12 18:30:37', 1, '2026-07-12 18:30:37'),
(146, 29, '30ba2b41461a674d6e9dc05516e62d226d59b99d9a83c4af8e20b5e4e251d77f', NULL, '', 'device_1783882065651', '102.91.78.40', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-12 18:47:11', '2026-07-12 18:47:11', 1, '2026-07-12 18:47:11'),
(147, 29, '115fee368707a4e748e3adbbe84ada96480e8356af2ccda02ad15ef68bee9952', NULL, '', 'device_1783882065651', '102.91.78.40', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-12 18:58:29', '2026-07-12 18:58:29', 1, '2026-07-12 18:58:29'),
(148, 29, '29046caefc08821aa576f137e404545bc8952b85d2bc54a97077c95ec5e83eb7', NULL, '', 'device_1783960459777', '102.91.93.47', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-13 16:33:37', '2026-07-13 16:33:37', 1, '2026-07-13 16:33:37'),
(149, 29, '5cbf51647498f9eb853efa83f1cdd3778b855279e10f08b54cc0aac2bf9765bb', NULL, '', 'device_1783960459777', '102.91.93.47', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 16:36:37', '2026-07-13 16:36:37', 1, '2026-07-13 16:36:37'),
(150, 28, 'd67ed806160daa004d7725c69a9c7586d69bf38faa951162a3fdee146e4d6ca0', 'c5b54ee590e12da9bc0c342aa5c150c2f6e63ed8a8e6d5865a11a6d5bfa0ac9d', 'web', NULL, '102.91.103.61', NULL, NULL, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-14 10:53:48', '2026-07-15 12:53:48', 1, '2026-07-15 12:53:48'),
(151, 28, '20046a84c0c7e239eb78341e7f619625b68450fa7e2e0b798b48f071f499a3fd', '961501809bcc275ddb0bdccf6376d84511768d0309bb5184eeae9b70b2fb00b8', 'web', NULL, '102.91.77.202', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-14 19:04:50', '2026-07-15 21:04:50', 1, '2026-07-15 21:04:50'),
(152, 29, '169ee46b9cb64e3c3094fc4346b700c1303b04745f87c26df53a49a7fdb2845d', NULL, '', NULL, '102.91.77.202', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-15 21:57:08', '2026-07-15 21:57:08', 1, '2026-07-15 21:57:08'),
(153, 28, '72d38670c7d0e57edfdfa7fe9907730828e95935a752ccffd387b764db659d20', '961501809bcc275ddb0bdccf6376d84511768d0309bb5184eeae9b70b2fb00b8', 'web', NULL, '102.91.77.202', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 21:05:20', '2026-07-15 22:05:20', 1, '2026-07-15 22:05:20'),
(154, 26, '61cca72506a897231f70c5c35957187efb1804cdd6224077dcec934d09e63346', NULL, '', NULL, '102.91.77.202', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-15 22:05:53', '2026-07-15 22:05:53', 1, '2026-07-15 22:05:53'),
(155, 26, 'd694f92b65a782f7912f3340604cbae71cd69ef832d6cc3c8661f2805e26c109', NULL, '', NULL, '102.91.77.202', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-15 22:37:49', '2026-07-15 22:37:49', 1, '2026-07-15 22:37:49'),
(156, 26, 'b77d6277992fd38f4ebe678d7cdbfdcb07d521e56e212aee76b7709b858d2e0b', NULL, '', NULL, '102.91.77.202', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-15 22:57:13', '2026-07-15 22:57:13', 1, '2026-07-15 22:57:13'),
(157, 29, '2e4bfe9e7612f1e4e53fe6dcaf332a613f83a9affbab926b52e19a00fe57a201', NULL, '', NULL, '102.91.77.202', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-15 22:59:03', '2026-07-15 22:59:03', 1, '2026-07-15 22:59:03'),
(158, 28, 'bf35adc576746ee74d9faf977584b7ebb397dd7b6815759a854aa05d5a178cb9', '961501809bcc275ddb0bdccf6376d84511768d0309bb5184eeae9b70b2fb00b8', 'web', NULL, '102.91.77.202', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 23:00:16', '2026-07-15 23:00:16', 0, '2026-07-15 22:59:33'),
(159, 28, 'ff8bf5896c9633587b4d3083c3978648de98a848605521198697f1eb3ca7d430', '961501809bcc275ddb0bdccf6376d84511768d0309bb5184eeae9b70b2fb00b8', 'web', NULL, '102.91.77.202', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 23:00:50', '2026-07-15 23:00:50', 0, '2026-07-15 23:00:24'),
(160, 23, 'd1769f6c977bc75f6fa662fdc528907468728f74e9c32ef38a715e17355637f2', '961501809bcc275ddb0bdccf6376d84511768d0309bb5184eeae9b70b2fb00b8', 'web', NULL, '102.91.77.202', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 23:01:37', '2026-07-15 23:01:37', 0, '2026-07-15 23:00:53'),
(161, 28, '8ca8c59649c742551d58d3f3dc1c69a38a3af7eecc2e2e918414edd44e139914', '961501809bcc275ddb0bdccf6376d84511768d0309bb5184eeae9b70b2fb00b8', 'web', NULL, '102.91.77.202', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 23:02:08', '2026-07-15 23:02:08', 0, '2026-07-15 23:01:44'),
(162, 25, '11cf9eef85f55c7b4d9f3e0abb4589b78ba0a4b3d124eaed7329ac99d3712495', '961501809bcc275ddb0bdccf6376d84511768d0309bb5184eeae9b70b2fb00b8', 'web', NULL, '102.91.77.202', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 23:04:04', '2026-07-15 23:04:04', 0, '2026-07-15 23:02:13'),
(163, 28, '47c33293c281966b268fac073ee2139125ca1db424e32bb1a2430873b691a51e', '961501809bcc275ddb0bdccf6376d84511768d0309bb5184eeae9b70b2fb00b8', 'web', NULL, '102.91.77.202', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 23:04:45', '2026-07-15 23:04:45', 0, '2026-07-15 23:04:18'),
(164, 27, '66fa42e65ddedfb64c36ab4e60a717930b1c9871fc512d4d8beb00c64381a53f', '961501809bcc275ddb0bdccf6376d84511768d0309bb5184eeae9b70b2fb00b8', 'web', NULL, '102.91.77.202', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 23:06:39', '2026-07-15 23:06:39', 0, '2026-07-15 23:04:50'),
(165, 7, '08639a396b155ceb2d7bee9dcd2184b9139e0554c23e4e2e52be0fd201346b4d', '961501809bcc275ddb0bdccf6376d84511768d0309bb5184eeae9b70b2fb00b8', 'web', NULL, '102.91.77.202', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 23:07:32', '2026-07-15 23:07:32', 0, '2026-07-15 23:06:46'),
(166, 22, '8cd3e534b29877104f4599137f7cbc12fc6dad468bb294f82744174aabc75ead', '961501809bcc275ddb0bdccf6376d84511768d0309bb5184eeae9b70b2fb00b8', 'web', NULL, '102.91.77.202', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-15 23:08:02', '2026-07-15 23:08:02', 0, '2026-07-15 23:07:38'),
(167, 28, '3fd34e7e5e1e3f1fd5f5f133be4f85a2fe84c58b3234fe17a82879fe5fc5a745', '77465f4c7b38a07d3999eb8f7c243b0f681088e3c1d8d3f088fbf87bf83a5730', 'web', NULL, '102.91.77.202', NULL, NULL, 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-15 22:09:37', '2026-07-15 23:09:37', 1, '2026-07-15 23:09:37'),
(168, 7, '776f64c67f8e73b53ca52bf369a36ab975fdfa1588aa995712c00b036713d2ba', 'e165bc07aeec3a0019903490a1fa30662a83689d13fbf4b96f0fa0233c9950b5', 'web', NULL, '102.91.93.133', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 09:31:46', '2026-07-16 09:31:46', 0, '2026-07-16 09:16:10'),
(169, 21, 'd7fdd8a9c395189eba3d5f85f6f21af703dbab8e3c746c9e7ad8b6932a82d810', 'e165bc07aeec3a0019903490a1fa30662a83689d13fbf4b96f0fa0233c9950b5', 'web', NULL, '102.91.93.133', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 09:36:10', '2026-07-16 09:36:10', 0, '2026-07-16 09:31:51'),
(170, 30, '459c79d078bfdda853e6320f817f96ca48586af8231ac53f3a851c5456bef00a', NULL, '', NULL, '102.91.93.133', NULL, NULL, 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-16 09:35:05', '2026-07-16 09:35:05', 1, '2026-07-16 09:35:05'),
(171, 28, '23aca54e7e3598c5c5380e1df75c62ad2bb0224d65ef30f591b4fb2f8ad17e0b', 'e165bc07aeec3a0019903490a1fa30662a83689d13fbf4b96f0fa0233c9950b5', 'web', NULL, '102.91.93.133', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 10:00:52', '2026-07-16 10:00:52', 0, '2026-07-16 09:36:16'),
(172, 30, 'aa1f675c8b78ee432139148ca36984940458a02cce3096327081c92dbad41e7b', NULL, '', NULL, '102.91.93.133', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-16 09:42:18', '2026-07-16 09:42:18', 1, '2026-07-16 09:42:18'),
(173, 30, '1440f3a66ac7da723e5bc95a6104704384f47866261ccc035f0c21d67598b9a9', NULL, '', NULL, '102.91.93.133', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-16 09:59:58', '2026-07-16 09:59:58', 1, '2026-07-16 09:59:58'),
(174, 7, 'e80fefabcc766402d28cdd335d4dd48b16d5b078ccb06d56697da2ef2fa4f030', 'e165bc07aeec3a0019903490a1fa30662a83689d13fbf4b96f0fa0233c9950b5', 'web', NULL, '102.91.93.133', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 10:01:03', '2026-07-16 10:01:03', 0, '2026-07-16 10:01:00'),
(175, 21, '77c948bc3ee3517bc978784dd6cf407086c0a99fa29ddb278a3c45d264415999', 'e165bc07aeec3a0019903490a1fa30662a83689d13fbf4b96f0fa0233c9950b5', 'web', NULL, '102.91.93.133', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 10:01:38', '2026-07-16 10:01:38', 0, '2026-07-16 10:01:11'),
(176, 28, '265d64e3badf395ff576d902b3cfacd140f899b914e7481f582a3bac48219cf7', 'e165bc07aeec3a0019903490a1fa30662a83689d13fbf4b96f0fa0233c9950b5', 'web', NULL, '102.91.93.133', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 09:01:47', '2026-07-16 10:01:47', 1, '2026-07-16 10:01:47'),
(177, 31, 'b774567a04629d3d2862dcbc7dac65efb57d9e07dea4c4aaf52b846fb0b69b26', NULL, '', NULL, '102.91.93.133', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-16 10:03:24', '2026-07-16 10:03:24', 1, '2026-07-16 10:03:24'),
(178, 26, 'fc184dfbc6ce4cbe30df1f242b21d15557e2731465ef174de124acd94d69f8a0', NULL, '', NULL, '102.91.93.133', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-16 11:02:07', '2026-07-16 11:02:07', 1, '2026-07-16 11:02:07'),
(179, 28, '3c6d25eac15970e6d1ec336340ff5e53e1475ac678768b7c299a6d7b66aa8c63', '2d0efc8e9906ba0552bb6f625465f865147298005c19ff96f6a58a9cae19ef0b', 'web', NULL, '197.210.70.187', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 15:59:34', '2026-07-18 15:59:34', 0, '2026-07-18 15:59:03'),
(180, 22, '43467be6cf3362de00c350d35443e695b6b46ffbbfebad637d8a846a0b9f8da1', '2d0efc8e9906ba0552bb6f625465f865147298005c19ff96f6a58a9cae19ef0b', 'web', NULL, '197.210.70.187', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 16:12:49', '2026-07-18 16:12:49', 0, '2026-07-18 15:59:39'),
(181, 28, 'c0bc2f21fc5f519b027824dc71fb88d49a3a3e864ed01fa0371435c63488324e', '2d0efc8e9906ba0552bb6f625465f865147298005c19ff96f6a58a9cae19ef0b', 'web', NULL, '197.210.70.187', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 16:13:16', '2026-07-18 16:13:16', 0, '2026-07-18 16:13:00'),
(182, 27, '4f82b6ad249a001bb2bc82c7a8f69fd5deeaab5821a49960fabeba8f9dc30cf1', '2d0efc8e9906ba0552bb6f625465f865147298005c19ff96f6a58a9cae19ef0b', 'web', NULL, '197.210.70.187', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 16:21:47', '2026-07-18 16:21:47', 0, '2026-07-18 16:13:22'),
(183, 28, '1fdde672fd109ede2b86ad3a9be7fc216d09c23846eba54c767025b24b7e438b', '2d0efc8e9906ba0552bb6f625465f865147298005c19ff96f6a58a9cae19ef0b', 'web', NULL, '197.210.70.187', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 16:31:29', '2026-07-18 16:31:29', 0, '2026-07-18 16:31:14'),
(184, 28, 'c22734566cac8845a0536780e3887539b3c1dcfb66bc81206432420d7ebaa38b', '2d0efc8e9906ba0552bb6f625465f865147298005c19ff96f6a58a9cae19ef0b', 'web', NULL, '197.210.70.187', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 16:32:00', '2026-07-18 16:32:00', 0, '2026-07-18 16:31:40'),
(185, 21, 'cf60993cd4ae8e50d303ff80a9cd258e76c8f6ad145dd87d83475e10bf1cc530', 'fcf1660350ef203ae312963f5f777eebb485291d84a215757669fa95d9592db3', 'web', NULL, '102.88.112.143', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-17 19:35:19', '2026-07-18 21:35:19', 1, '2026-07-18 21:35:19'),
(186, 21, 'f7c585f115d7354447235bc9dbb596086f33d8981a13857cbe1564f3c8f3a706', 'fcf1660350ef203ae312963f5f777eebb485291d84a215757669fa95d9592db3', 'web', NULL, '102.88.112.143', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 22:41:47', '2026-07-18 22:41:47', 0, '2026-07-18 22:41:31'),
(187, 21, 'd70ccfa2f8b373406170079b46262eed44d9e046411833c11ee20893d0641ada', 'fcf1660350ef203ae312963f5f777eebb485291d84a215757669fa95d9592db3', 'web', NULL, '102.88.112.143', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 22:47:38', '2026-07-18 22:47:38', 0, '2026-07-18 22:47:35'),
(188, 21, '4735241349368da8d6b8bcf4876ec3aa7bdce1799e09ec44d9cfe57921fcc60a', 'fcf1660350ef203ae312963f5f777eebb485291d84a215757669fa95d9592db3', 'web', NULL, '102.88.112.143', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 22:57:44', '2026-07-18 22:57:44', 0, '2026-07-18 22:57:43'),
(189, 21, 'd2f6dbf1b4fd80a57354641ec5eba7847ab4190922c55c1e4ef89303d042d4de', 'fcf1660350ef203ae312963f5f777eebb485291d84a215757669fa95d9592db3', 'web', NULL, '102.88.112.143', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 22:58:34', '2026-07-18 22:58:34', 0, '2026-07-18 22:57:59'),
(190, 28, '127016df2983622a7ca595d592857f591e110d69b195b5bf207f5963bb578f76', 'fcf1660350ef203ae312963f5f777eebb485291d84a215757669fa95d9592db3', 'web', NULL, '102.88.112.143', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 22:59:51', '2026-07-18 22:59:51', 0, '2026-07-18 22:58:39'),
(191, 26, '9ac0214bf3b2843c61587aa8d7952467715c11d34d6f7e55c2c1c238db42459d', NULL, '', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-19 11:23:41', '2026-07-19 11:23:41', 1, '2026-07-19 11:23:41'),
(192, 25, 'fd2ae0e1b8a3a0888145015bb30af0706aab782c1f20d6f4bf2aa4d52f94572e', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', 'web', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 11:32:20', '2026-07-19 11:32:20', 0, '2026-07-19 11:32:11'),
(193, 21, '82b0d8501c303ad63ccc58732f86e8d6fcde4c44353a39dd748973a14e24364a', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', 'web', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 11:33:13', '2026-07-19 11:33:13', 0, '2026-07-19 11:32:59'),
(194, 21, '1eb2898fa968e01a304f178d4582958d17773ac432c44e8fb1f2c7e367814cf2', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', 'web', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 11:34:11', '2026-07-19 11:34:11', 0, '2026-07-19 11:33:40'),
(195, 27, 'dac608d2d9f0223763d23ceb5768859574a688b3295ea1479ebc074284eba007', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', 'web', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 12:23:36', '2026-07-19 12:23:36', 0, '2026-07-19 11:34:31'),
(196, 29, 'e575209fe352024175ee1a45e09b9ead491c0d13b621a40a1a2e49e911801bb0', NULL, '', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 11:38:08', '2026-07-19 11:38:08', 1, '2026-07-19 11:38:08'),
(197, 26, 'c7d52f3ac88e8b9d5da8c056088f0f8671253cef600d8e3d48fcbea4df5d050d', NULL, '', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 11:38:43', '2026-07-19 11:38:43', 1, '2026-07-19 11:38:43'),
(198, 30, 'd8db753e5a37eab191f45ce0e97536843f2bb3ad472127c509643c6fa78a2901', NULL, '', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-19 12:00:21', '2026-07-19 12:00:21', 1, '2026-07-19 12:00:21'),
(199, 27, '03f80faf04004907fddf66597f62b8e20ce5f5a6cf40fad274d4ab14a2217c01', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', 'web', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 13:23:38', '2026-07-19 12:23:38', 1, '2026-07-19 12:23:38'),
(200, 26, '665c5cd4b22ddb7febae0e5b5addba5148ed10055a41c593bafec1698c766a09', NULL, '', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-26 11:59:01', '2026-07-19 14:59:01', 1, '2026-07-19 14:59:01'),
(201, 26, 'ebca8edaadf5102a02ca1df7caba1b9b9c2c096fd7ef5aa7faa27a2e79ec4419', NULL, '', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-26 11:59:06', '2026-07-19 14:59:06', 1, '2026-07-19 14:59:06'),
(202, 26, '1f098ad4d85c8d635738f5d163444ca642bd5ef30f865f8e9e2d3608b28b4027', NULL, '', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-26 11:59:09', '2026-07-19 14:59:09', 1, '2026-07-19 14:59:09');
INSERT INTO `user_sessions` (`id`, `user_id`, `token`, `device_id`, `device_type`, `device_name`, `ip_address`, `gps_lat`, `gps_lng`, `user_agent`, `expires_at`, `last_activity_at`, `is_active`, `created_at`) VALUES
(203, 26, '8986666174f0c14f8567efa7d832001eeb7a69c7024d7eb71b062c59ddd49c4f', NULL, '', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-26 11:59:11', '2026-07-19 14:59:11', 1, '2026-07-19 14:59:11'),
(204, 26, 'dd863d004278516155ef1b9edcfae496fa85a8254598425773a398df1580a6a4', NULL, '', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-26 12:00:37', '2026-07-19 15:00:37', 1, '2026-07-19 15:00:37'),
(205, 26, 'c68904bc938452408ef73c8721aa17845a360c8f37fd2ad0af37ceece0fbd0a3', NULL, '', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-26 12:00:39', '2026-07-19 15:00:39', 1, '2026-07-19 15:00:39'),
(206, 21, 'b6e25a545536f24f1455c3e031add69737bd38dc6a687ca94e324d2cabc6698e', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', 'web', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 16:03:51', '2026-07-19 15:03:51', 1, '2026-07-19 15:03:51'),
(207, 26, '39152eef321cc3cc458696130cf64b4e9daf2d308cf803bb3a4b13f7d565bfbc', NULL, '', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-26 12:04:23', '2026-07-19 15:04:23', 1, '2026-07-19 15:04:23'),
(208, 26, '46a51736e9b426b552a017fb7e7dbf1308af5480fb531f56e70fdfa498d96dfc', NULL, '', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-26 12:04:51', '2026-07-19 15:04:51', 1, '2026-07-19 15:04:51'),
(209, 26, 'e0234baab738b23985ae0d397bf2a5349fa77bcceeffb8532d1e2b286bbdebf5', NULL, '', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-26 12:05:06', '2026-07-19 15:05:06', 1, '2026-07-19 15:05:06'),
(210, 26, '417d3a10a90e2c11dad75dab888ade8327094b4508ee38b57358233eb6d3584d', NULL, '', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-26 12:05:14', '2026-07-19 15:05:14', 1, '2026-07-19 15:05:14'),
(211, 26, '844fc7f4424d392b5a237c1ece96a89af0c3ef916935c1e39699de7b7a8c2bcc', NULL, '', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-26 12:09:38', '2026-07-19 15:09:38', 1, '2026-07-19 15:09:38'),
(212, 26, 'f311335b1773f2cb22bf80e431cdb63ff84b4b4bbea1849da8bedaf576eb45bb', NULL, '', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-26 12:12:50', '2026-07-19 15:12:50', 1, '2026-07-19 15:12:50'),
(213, 29, 'cbaadfcb52a5f2134b74bd4e15374c795629bf5929c94746bdaa664be7affa43', NULL, '', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-26 12:15:18', '2026-07-19 15:15:18', 1, '2026-07-19 15:15:18'),
(214, 31, 'e2def573c612e211acd0f724c7e69425375421f2881b2ee16b3b6763137f845e', NULL, '', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-26 12:16:45', '2026-07-19 15:16:45', 1, '2026-07-19 15:16:45'),
(215, 30, '8a8049934186b43425dd95dbc0c10e26f9d21f1ba06eceafc660d589eec24a9d', NULL, '', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-26 12:17:35', '2026-07-19 15:17:35', 1, '2026-07-19 15:17:35'),
(216, 29, '5501bfecfaa40e5282ac9458a00268aa7c0fe7de81be108842d298076ac2110d', NULL, '', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-26 12:18:37', '2026-07-19 15:18:37', 1, '2026-07-19 15:18:37'),
(217, 26, '578692974b1e416f202c3304e6dc3f7659a913b0ba37a85d16d3df39ccde46b0', NULL, '', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-26 13:09:47', '2026-07-19 16:09:47', 1, '2026-07-19 16:09:47'),
(218, 26, '9c2fbd49149c93ffea4b495d0201da6b9514c751887aa466226e41cd6d67381b', NULL, '', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-26 13:09:57', '2026-07-19 16:09:57', 1, '2026-07-19 16:09:57'),
(219, 26, '935d0ecdda79dd2c9fb8ce5ebec473af6f00fab10daeefb0dbb7370ca7d87dc5', NULL, '', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-26 13:10:04', '2026-07-19 16:10:04', 1, '2026-07-19 16:10:04'),
(220, 26, 'df080ca19f952532497c0bc28762da7694af515266cba51a11a4f8a4a3dac572', NULL, '', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-26 13:10:19', '2026-07-19 16:10:19', 1, '2026-07-19 16:10:19'),
(221, 27, '8ee6f5236e7284e2423ad1666c8b2303726e2f7493c429f94f73ccbad1c92562', 'a478559d003940cee9d0c1731b2cf8557cafe9361d7a8e99ee56c41ed2a2a03d', 'web', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 16:54:49', '2026-07-19 16:54:49', 0, '2026-07-19 16:27:22'),
(222, 26, '6d7ef9f5e547aa7987628cca856ee57ffc452e4bde23763a46b74901e4b5416b', NULL, '', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-26 13:28:26', '2026-07-19 16:28:26', 1, '2026-07-19 16:28:26'),
(223, 26, '4aabfe34861c3739569b2d0115a75d484dafebacd8d0965fd3d1e833294e4854', NULL, '', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-26 13:38:35', '2026-07-19 16:38:35', 1, '2026-07-19 16:38:35'),
(224, 26, 'c6210e749c738dba0d1e15250309150d68945d35e524f1af59b21f29d25f1486', NULL, '', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-26 13:55:40', '2026-07-19 16:55:40', 1, '2026-07-19 16:55:40'),
(225, 31, 'c8b81f343ffd37e11d9a04a4305e4fc6a638ca00be2b28a45386bc0aa286bcc7', NULL, '', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-26 13:56:41', '2026-07-19 16:56:41', 1, '2026-07-19 16:56:41'),
(226, 29, '99820ca19499c2d8da739966fff71195515b92f8a8cbddb60a19ff1732257991', NULL, '', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-26 13:57:16', '2026-07-19 16:57:16', 1, '2026-07-19 16:57:16'),
(227, 30, '3a722309b9ee1467cb89d92074e70e12e57e82d52d6c04b08a46dfacbafeab1a', NULL, '', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-26 13:57:43', '2026-07-19 16:57:43', 1, '2026-07-19 16:57:43'),
(228, 26, 'da93df92eacd452da227ef4685605affc58bdc37223e80690bbed85ead3c7d93', NULL, '', NULL, '197.210.70.43', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-26 14:04:48', '2026-07-19 17:04:48', 1, '2026-07-19 17:04:48'),
(229, 26, 'bc9c301a2adacf9dfc07d1cfae1eafab0e426d9afb5ac6e563418877a6859113', NULL, '', NULL, '102.91.77.164', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-27 16:18:32', '2026-07-20 19:18:32', 1, '2026-07-20 19:18:32'),
(230, 27, '6e1bf0f6c44cc27a792678d80a90a66730f729358235c74ef627542a8d7f920a', '9b9bd6260fcaddd147a31695595902b936b448e2f0b8007640f93a97ecd67767', 'web', NULL, '102.91.103.170', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 16:46:28', '2026-07-23 15:46:28', 1, '2026-07-23 15:46:28'),
(231, 27, '138bbf80e82fe1a08fee6cbb35cca850749a579d8e227ad45878f45363af9c78', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', 'web', NULL, '102.91.104.59', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 14:33:17', '2026-07-24 13:33:17', 1, '2026-07-24 13:33:17'),
(232, 27, '53621f1614ed8956bfe52b88f23181c3c62709d85687862bf454d7dcb7820d9b', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', 'web', NULL, '102.91.104.59', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 14:27:33', '2026-07-24 14:27:33', 0, '2026-07-24 14:03:27'),
(233, 21, 'a82a1f57b466bb828ad6ec88eca2e05713bf837cbad733301d11ed4e17f493da', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', 'web', NULL, '102.91.104.59', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 14:29:22', '2026-07-24 14:29:22', 0, '2026-07-24 14:28:14'),
(234, 32, '2f97f50842e034a3f4ed21b8b2766a7ed35ccf825356fdf41b4b759875bf5d27', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', 'web', NULL, '102.91.104.59', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 14:29:30', '2026-07-24 14:29:30', 0, '2026-07-24 14:29:25'),
(235, 27, '17bf92369e49a9f872a92a1fe68f58c3361d3d8ad64594dedc8847208360f144', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', 'web', NULL, '102.91.104.59', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 15:29:40', '2026-07-24 14:29:40', 1, '2026-07-24 14:29:40'),
(236, 27, '8e3b12dca2f9bdc47eaf2ed46d3b9a244d5588329514df3936780a7d7347a651', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', 'web', NULL, '102.91.104.59', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 17:49:15', '2026-07-24 16:49:15', 1, '2026-07-24 16:49:15'),
(237, 26, 'cbf04d5034b3b5edf2f5694519650bcd101d536ca9f27c7bddacde7a566db995', NULL, '', NULL, '102.91.104.59', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-31 13:59:20', '2026-07-24 16:59:20', 1, '2026-07-24 16:59:20'),
(238, 27, '8c9892babe778de770974bdd3a9b1e453492eec7bc9d99fd5feb6dfee78a504a', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', 'web', NULL, '102.91.104.59', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 18:10:28', '2026-07-24 18:10:28', 0, '2026-07-24 17:27:55'),
(239, 26, 'cfe2cfdcde1d1980777c4fee608bf731e565125b352b70d531757ea5242df72d', NULL, '', NULL, '102.91.104.59', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-31 14:38:32', '2026-07-24 17:38:32', 1, '2026-07-24 17:38:32'),
(240, 26, '433bd162994a78161c8b907376a5c4a82646432fe8c65d79d89b3e74a8ea551a', NULL, '', NULL, '102.91.104.59', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-31 14:52:05', '2026-07-24 17:52:05', 1, '2026-07-24 17:52:05'),
(241, 26, 'a58d569a550adc3643684de9be6986cfa097e8dbcabd6f6965a08707cb5ee286', NULL, '', NULL, '102.91.104.59', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-31 15:01:58', '2026-07-24 18:01:58', 1, '2026-07-24 18:01:58'),
(242, 27, '54a54d74f41db2f8df091deacd9828c538b437ac3e8dcad67f068c6570a5fea8', '2d6a7186a60a3cf9d04662bad3f7222dfaf7cf4de368ebce3f2a67803b988bc2', 'web', NULL, '102.91.104.59', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 18:31:49', '2026-07-24 18:31:49', 0, '2026-07-24 18:10:45'),
(243, 27, '15245b07eade3f8d8dcbc5756506d6e140c1eb7083273b77083bcffa8bf6e17b', '9161a7e307dc2c1fa53c878bc0f2367a061e14095e5735af1246b23b0b22dcac', 'web', NULL, '102.91.105.161', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 18:34:25', '2026-07-24 18:34:25', 0, '2026-07-24 18:32:04'),
(244, 29, '177bc12fbd3541977326e03ccad5fee6f43baade3368d8f7b20bed40361e34ef', NULL, '', NULL, '102.91.105.161', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-31 15:32:27', '2026-07-24 18:32:27', 1, '2026-07-24 18:32:27'),
(245, 27, '6170c4baded6f0f21bcc75e9d0d78d787dca54db5c3192983de6ca33c765334c', '9161a7e307dc2c1fa53c878bc0f2367a061e14095e5735af1246b23b0b22dcac', 'web', NULL, '102.91.105.161', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 19:34:44', '2026-07-24 18:34:44', 1, '2026-07-24 18:34:44'),
(246, 29, 'a6e47641d169a9423128505d7d3c3cc433b3e4a9ad07e0b9c85c9f9e21443bf2', NULL, '', NULL, '102.91.105.161', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-31 15:35:34', '2026-07-24 18:35:34', 1, '2026-07-24 18:35:34'),
(247, 27, '611c1aabfca49cbcfeab35410a30eb87ec635e0983d5de754e45a92b82ba0b48', 'f5828db79f1e3f5bac52690d9499d62aa54315ca12caaf090ed03ab01cd751d6', 'web', NULL, '102.91.92.216', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 21:19:51', '2026-07-25 20:19:51', 1, '2026-07-25 20:19:51'),
(248, 26, 'c3a2542dc1690c4a135a68c7ff8b97bcc5cbb0e7bdf9ffe866ccff08b4af6230', NULL, '', NULL, '102.91.92.216', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-08-01 17:40:29', '2026-07-25 20:40:29', 1, '2026-07-25 20:40:29'),
(249, 27, 'b0ab473a79d64f3c8cb8df3e5f34c733e87b9ad8882dd6cc69e139957c56db2a', '94f663f7190b5b7a6c9ace22e788bb55e7bdf3a7bc8e8df84cf12df3aa3f377f', 'web', NULL, '197.210.53.133', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-26 12:04:06', '2026-07-26 11:04:06', 1, '2026-07-26 11:04:06'),
(250, 27, '092f0869201893e573b7beec94abe915513babd82822b5363c62259d480d0b9c', '94f663f7190b5b7a6c9ace22e788bb55e7bdf3a7bc8e8df84cf12df3aa3f377f', 'web', NULL, '197.210.53.133', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-26 12:05:14', '2026-07-26 11:05:14', 1, '2026-07-26 11:05:14'),
(251, 29, '59a9fa6afd2e8c68779792ab65d6e1607bb95e36ce0f7896c1665bf75459c0a6', NULL, '', NULL, '197.210.53.133', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-08-02 08:06:02', '2026-07-26 11:06:02', 1, '2026-07-26 11:06:02'),
(252, 26, 'bcb5995adbbd8b64faca417e26edffbd258677077e2e26f05f9218df004fa399', NULL, '', NULL, '197.210.53.133', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-08-02 08:09:40', '2026-07-26 11:09:40', 1, '2026-07-26 11:09:40'),
(253, 26, '3299fab8a26e92c62d60e45b458d951ee42321d9c7ea203bbc15eebbb5564393', NULL, '', NULL, '197.210.53.133', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-08-02 08:16:24', '2026-07-26 11:16:24', 1, '2026-07-26 11:16:24'),
(254, 27, 'd2419316567248309618ccf94a47e390643c146534949730e2e9671d3a09bdff', '94f663f7190b5b7a6c9ace22e788bb55e7bdf3a7bc8e8df84cf12df3aa3f377f', 'web', NULL, '197.210.53.133', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-26 12:28:46', '2026-07-26 11:28:46', 1, '2026-07-26 11:28:46'),
(255, 26, '275f4659e7374f8abecd1ad308a96e83b95e9701904b133c608cbcae42ca2c8a', NULL, '', NULL, '197.210.53.133', NULL, NULL, 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-08-02 08:29:22', '2026-07-26 11:29:22', 1, '2026-07-26 11:29:22'),
(256, 27, '09a3d45d26d4e154e41bdad201ed7ae85bb3a317ccd66fdd85541cbff086281b', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', 'web', NULL, '102.91.103.236', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-27 10:06:38', '2026-07-27 10:06:38', 0, '2026-07-27 10:05:39'),
(257, 7, '05e764a76724bad3f3abe9ff1931516747c55d31927943f3f22f5f7ac7c17830', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', 'web', NULL, '102.91.103.236', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-27 11:07:01', '2026-07-27 10:07:01', 1, '2026-07-27 10:07:01'),
(258, 7, 'c4c1a31fc4ee3f584eddae16a5fbe76e46022472a64a2fbab1397737cb19283b', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', 'web', NULL, '102.91.103.236', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-27 12:29:43', '2026-07-27 12:29:43', 0, '2026-07-27 12:26:40'),
(259, 21, '1e5615f8b2b8793712152fac828646bab34aeccb9ac18d38321219573902a799', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', 'web', NULL, '102.91.103.236', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-27 12:56:11', '2026-07-27 12:56:11', 0, '2026-07-27 12:30:15'),
(260, 33, '4fbb5bdafa6055e59c664c1365fb835dd33acff5a386341db3670d8148cc3871', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', 'web', NULL, '102.91.103.236', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-27 12:56:49', '2026-07-27 12:56:49', 0, '2026-07-27 12:56:16'),
(261, 27, '693be6a0814cb5f5ac49156fd9cabccfc22416628c968ebfd887de32a5a5ac9a', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', 'web', NULL, '102.91.103.236', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-27 12:57:21', '2026-07-27 12:57:21', 0, '2026-07-27 12:57:15'),
(262, 21, '062c743e82a9d68c5d091990433fe994148c383658dad0aa8379b679820a3b40', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', 'web', NULL, '102.91.103.236', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-27 13:02:35', '2026-07-27 13:02:35', 0, '2026-07-27 12:58:39'),
(263, 21, 'eb7a8ec68e26dc358965007e9123e538838abaabdcf9d7bb4153a924b94611ed', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', 'web', NULL, '102.91.103.236', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-27 13:06:50', '2026-07-27 13:06:50', 0, '2026-07-27 13:03:33'),
(264, 34, '0b1b1d94eb7862669bf2417a4f4709ecd8a76db783f03326013056d643bb10d3', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', 'web', NULL, '102.91.103.236', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-27 13:14:11', '2026-07-27 13:14:11', 0, '2026-07-27 13:08:52'),
(265, 27, '93ed5fbfb3aeb46dc6ba100e3609163e913d84cb7828f3cb6422310944e2f0a1', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', 'web', NULL, '102.91.103.236', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-27 13:14:54', '2026-07-27 13:14:54', 0, '2026-07-27 13:14:46'),
(266, 21, 'a1be78407315256f39b7dbf3819191061f800319b682c8aeaf0a9786a2b6fa11', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', 'web', NULL, '102.91.103.236', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-27 13:16:33', '2026-07-27 13:16:33', 0, '2026-07-27 13:15:32'),
(267, 21, '5bd31ad9fc1627dcf2dbfc2196e3fc0bfc6cd6e93867c041ede78c1e0fe5838c', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', 'web', NULL, '102.91.103.236', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-27 13:31:26', '2026-07-27 13:31:26', 0, '2026-07-27 13:16:37'),
(268, 34, '662f40e6fa862f45cf75dc2ebedf063008f7a88be58211501ff75514f0f93855', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', 'web', NULL, '102.91.103.236', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-27 13:38:49', '2026-07-27 13:38:49', 0, '2026-07-27 13:31:35'),
(269, 21, '1ab343a2b2acbbce34240621ebef28688290edc2b6f79545358f61b720613ced', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', 'web', NULL, '102.91.103.236', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-27 13:39:44', '2026-07-27 13:39:44', 0, '2026-07-27 13:39:01'),
(270, 34, 'f15c1fc1579443781e83a9f9921a29a1184b2fd4044dc0b7512db14983b0eb45', '25ccdef86749f19e05b4303533411d451057b84333c8e56cf515de9630bd0d06', 'web', NULL, '102.91.103.236', NULL, NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-27 14:39:57', '2026-07-27 13:39:57', 1, '2026-07-27 13:39:57');

-- --------------------------------------------------------

--
-- Table structure for table `volunteer_assignments`
--

CREATE TABLE `volunteer_assignments` (
  `id` bigint UNSIGNED NOT NULL,
  `volunteer_id` bigint UNSIGNED NOT NULL,
  `coordinator_id` bigint UNSIGNED NOT NULL,
  `area` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `volunteer_tasks`
--

CREATE TABLE `volunteer_tasks` (
  `id` bigint UNSIGNED NOT NULL,
  `volunteer_id` bigint UNSIGNED NOT NULL,
  `assigned_by` bigint UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `assigned_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `started_at` timestamp NULL DEFAULT NULL,
  `due_date` timestamp NULL DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `priority` enum('low','normal','high') NOT NULL DEFAULT 'normal',
  `task_type` varchar(50) DEFAULT 'general',
  `status` enum('pending','in_progress','completed') DEFAULT 'pending',
  `report` text,
  `completed_at` timestamp NULL DEFAULT NULL,
  `completion_notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `volunteer_tasks`
--

INSERT INTO `volunteer_tasks` (`id`, `volunteer_id`, `assigned_by`, `title`, `description`, `assigned_date`, `started_at`, `due_date`, `location`, `priority`, `task_type`, `status`, `report`, `completed_at`, `completion_notes`, `created_at`, `updated_at`) VALUES
(1, 32, 27, 'Good', 'dcdc d', '2026-07-24 15:38:57', '2026-07-24 16:01:46', NULL, 'kangire', 'normal', 'general', 'completed', NULL, '2026-07-24 16:02:16', '', '2026-07-24 15:38:57', '2026-07-24 16:02:16');

-- --------------------------------------------------------

--
-- Table structure for table `wards`
--

CREATE TABLE `wards` (
  `id` int UNSIGNED NOT NULL,
  `lga_id` int UNSIGNED NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gps_lat` decimal(10,8) DEFAULT NULL,
  `gps_lng` decimal(11,8) DEFAULT NULL,
  `registered_voters` int UNSIGNED NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `wards`
--

INSERT INTO `wards` (`id`, `lga_id`, `code`, `name`, `gps_lat`, `gps_lng`, `registered_voters`, `is_active`) VALUES
(1, 1, '17-03-02', 'Kangire', NULL, NULL, 0, 1);

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
  ADD KEY `idx_chat_messages_created` (`created_at`),
  ADD KEY `idx_receiver_id` (`receiver_id`);

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
-- Indexes for table `community_reports`
--
ALTER TABLE `community_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_community_reports_volunteer` (`volunteer_id`),
  ADD KEY `idx_community_reports_status` (`status`);

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
-- Indexes for table `election_checklists`
--
ALTER TABLE `election_checklists`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_checklists_user` (`user_id`),
  ADD KEY `idx_checklists_election` (`election_id`),
  ADD KEY `idx_checklists_pu` (`pu_id`);

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
-- Indexes for table `election_polling_units`
--
ALTER TABLE `election_polling_units`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_election_pu` (`election_id`,`pu_id`),
  ADD KEY `idx_election_pu_election` (`election_id`),
  ADD KEY `idx_election_pu_pu` (`pu_id`);

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
-- Indexes for table `media_uploads`
--
ALTER TABLE `media_uploads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_media_user` (`user_id`),
  ADD KEY `idx_media_election` (`election_id`),
  ADD KEY `idx_media_pu` (`pu_id`);

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
-- Indexes for table `observer_assignments`
--
ALTER TABLE `observer_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_observer_assignments_observer` (`observer_id`),
  ADD KEY `idx_observer_assignments_pu` (`pu_id`);

--
-- Indexes for table `observer_incidents`
--
ALTER TABLE `observer_incidents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_observer_incidents_observer` (`observer_id`);

--
-- Indexes for table `observer_observations`
--
ALTER TABLE `observer_observations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_observer_observations_observer` (`observer_id`);

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
  ADD KEY `idx_otp_expires` (`expires_at`),
  ADD KEY `idx_otp_user_code` (`user_id`,`otp_code`);

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
  ADD KEY `idx_users_dob` (`date_of_birth`),
  ADD KEY `idx_google_id` (`google_id`),
  ADD KEY `idx_users_senatorial` (`senatorial_id`),
  ADD KEY `idx_users_constituency` (`federal_constituency_id`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sessions_user` (`user_id`),
  ADD KEY `idx_sessions_token` (`token`(255)),
  ADD KEY `idx_sessions_active` (`is_active`);

--
-- Indexes for table `volunteer_assignments`
--
ALTER TABLE `volunteer_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_volunteer_assignments_volunteer` (`volunteer_id`),
  ADD KEY `idx_volunteer_assignments_coordinator` (`coordinator_id`);

--
-- Indexes for table `volunteer_tasks`
--
ALTER TABLE `volunteer_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_volunteer_tasks_volunteer` (`volunteer_id`),
  ADD KEY `idx_volunteer_tasks_status` (`status`);

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
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=689;

--
-- AUTO_INCREMENT for table `agent_assignments`
--
ALTER TABLE `agent_assignments`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `agent_checkins`
--
ALTER TABLE `agent_checkins`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `agent_payments`
--
ALTER TABLE `agent_payments`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `api_keys`
--
ALTER TABLE `api_keys`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `api_logs`
--
ALTER TABLE `api_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `backups`
--
ALTER TABLE `backups`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `broadcasts`
--
ALTER TABLE `broadcasts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `budgets`
--
ALTER TABLE `budgets`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `candidates`
--
ALTER TABLE `candidates`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `chat_rooms`
--
ALTER TABLE `chat_rooms`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `chat_room_members`
--
ALTER TABLE `chat_room_members`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `community_reports`
--
ALTER TABLE `community_reports`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `elections`
--
ALTER TABLE `elections`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `election_checklists`
--
ALTER TABLE `election_checklists`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `election_materials`
--
ALTER TABLE `election_materials`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `election_polling_units`
--
ALTER TABLE `election_polling_units`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `federal_constituencies`
--
ALTER TABLE `federal_constituencies`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `incidents`
--
ALTER TABLE `incidents`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `lgas`
--
ALTER TABLE `lgas`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=247;

--
-- AUTO_INCREMENT for table `media_uploads`
--
ALTER TABLE `media_uploads`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `observer_assignments`
--
ALTER TABLE `observer_assignments`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `observer_incidents`
--
ALTER TABLE `observer_incidents`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `observer_observations`
--
ALTER TABLE `observer_observations`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `offline_sync_queue`
--
ALTER TABLE `offline_sync_queue`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `otp_verifications`
--
ALTER TABLE `otp_verifications`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `people`
--
ALTER TABLE `people`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `political_parties`
--
ALTER TABLE `political_parties`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `polling_units`
--
ALTER TABLE `polling_units`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `public_results`
--
ALTER TABLE `public_results`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `results_ec8a`
--
ALTER TABLE `results_ec8a`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `results_ec8b`
--
ALTER TABLE `results_ec8b`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `results_ec8c`
--
ALTER TABLE `results_ec8c`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `results_ec8d`
--
ALTER TABLE `results_ec8d`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `results_ec8e`
--
ALTER TABLE `results_ec8e`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `security_events`
--
ALTER TABLE `security_events`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=391;

--
-- AUTO_INCREMENT for table `senatorial_districts`
--
ALTER TABLE `senatorial_districts`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `states`
--
ALTER TABLE `states`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `support_tickets`
--
ALTER TABLE `support_tickets`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `support_ticket_replies`
--
ALTER TABLE `support_ticket_replies`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `tenants`
--
ALTER TABLE `tenants`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tenant_settings`
--
ALTER TABLE `tenant_settings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=271;

--
-- AUTO_INCREMENT for table `volunteer_assignments`
--
ALTER TABLE `volunteer_assignments`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `volunteer_tasks`
--
ALTER TABLE `volunteer_tasks`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `wards`
--
ALTER TABLE `wards`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
