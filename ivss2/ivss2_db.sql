-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 15, 2026 at 03:28 PM
-- Server version: 8.4.8
-- PHP Version: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ivss2_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
CREATE TABLE IF NOT EXISTS `admins` (
  `admin_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `name`, `email`, `password`, `created_at`) VALUES
(1, 'Admin IVSS', 'admin@ivss.om', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2026-04-30 19:14:01');

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

DROP TABLE IF EXISTS `complaints`;
CREATE TABLE IF NOT EXISTS `complaints` (
  `complaint_id` int NOT NULL AUTO_INCREMENT,
  `request_id` int NOT NULL,
  `user_id` int NOT NULL,
  `garage_id` int NOT NULL,
  `type` enum('price','service') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'price = too expensive | service = bad quality',
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('open','reviewed','resolved','dismissed') COLLATE utf8mb4_unicode_ci DEFAULT 'open',
  `admin_note` text COLLATE utf8mb4_unicode_ci COMMENT 'Admin reply / action note',
  `garage_note` text COLLATE utf8mb4_unicode_ci COMMENT 'Garage response',
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`complaint_id`),
  KEY `request_id` (`request_id`),
  KEY `idx_garage` (`garage_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`type`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `complaints`
--

INSERT INTO `complaints` (`complaint_id`, `request_id`, `user_id`, `garage_id`, `type`, `message`, `status`, `admin_note`, `garage_note`, `resolved_at`, `created_at`) VALUES
(1, 4, 3, 1, 'price', 'السعر مرتفع', 'resolved', NULL, 'ليس مرتفع', '2026-05-01 10:16:04', '2026-05-01 09:19:19'),
(2, 12, 4, 5, 'price', 'saggghfjahjfhkjhfhak', 'open', NULL, 'dhjjdj', NULL, '2026-05-06 07:07:47'),
(3, 15, 1, 5, 'price', 'faSGGASEGARGGAAG', 'open', NULL, NULL, NULL, '2026-05-11 17:50:48');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

DROP TABLE IF EXISTS `feedback`;
CREATE TABLE IF NOT EXISTS `feedback` (
  `feedback_id` int NOT NULL AUTO_INCREMENT,
  `request_id` int NOT NULL,
  `user_id` int NOT NULL,
  `garage_id` int DEFAULT NULL,
  `rating` tinyint NOT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`feedback_id`),
  KEY `request_id` (`request_id`),
  KEY `user_id` (`user_id`),
  KEY `garage_id` (`garage_id`)
) ;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`feedback_id`, `request_id`, `user_id`, `garage_id`, `rating`, `comment`, `created_at`) VALUES
(1, 12, 4, 5, 2, '', '2026-05-06 07:10:42'),
(2, 13, 1, 5, 4, '', '2026-05-06 07:14:06'),
(3, 11, 1, 5, 5, '', '2026-05-06 07:14:27'),
(4, 9, 4, 1, 5, '', '2026-05-08 22:55:15'),
(5, 15, 1, 5, 3, '', '2026-05-11 17:50:22');

-- --------------------------------------------------------

--
-- Table structure for table `garages`
--

DROP TABLE IF EXISTS `garages`;
CREATE TABLE IF NOT EXISTS `garages` (
  `garage_id` int NOT NULL AUTO_INCREMENT,
  `owner_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `garage_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` int UNSIGNED NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `services` text COLLATE utf8mb4_unicode_ci COMMENT 'Comma-separated list of offered services',
  `is_active` tinyint(1) DEFAULT '1',
  `rating` decimal(3,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`garage_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `garages`
--

INSERT INTO `garages` (`garage_id`, `owner_name`, `garage_name`, `email`, `phone`, `password`, `location`, `latitude`, `longitude`, `services`, `is_active`, `rating`, `created_at`) VALUES
(1, 'Ahmed Al-Wadi', 'Al-Wadi Garage', 'alwadi@ivss.om', 91000001, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Qurum, Muscat', 23.5843532, 58.4706573, 'towing,battery,tire,repair', 1, 5.00, '2026-04-30 19:14:01'),
(2, 'Khalid Al-Balushi', 'Muscat Auto Services', 'muscatauto@ivss.om', 91000002, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Al-Khuwair, Muscat', 23.6070000, 58.3890000, 'battery,fuel,repair', 1, 4.60, '2026-04-30 19:14:01'),
(3, 'Salem Al-Rashdi', 'Gulf Motors', 'gulf@ivss.om', 91000003, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Bausher, Muscat', 23.6200000, 58.3750000, 'towing,tire,lockout,repair', 1, 4.90, '2026-04-30 19:14:01'),
(4, 'Nasser Al-Hinai', 'Quick Fix Garage', 'quickfix@ivss.om', 71000004, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ruwi, Muscat', 23.5900000, 58.4200000, 'tire,fuel,battery', 1, 4.50, '2026-04-30 19:14:01'),
(5, 'Ali', 'WAASS-Care', 'khalid.rawahi@gmail.com', 99876665, '$2y$10$pQ.sP6tXobukDehm5azgue6sgIlni/IEGimmXfZ4wLS2un6pdggYS', 'Muscat', 22.7125703, 58.5561599, 'battery,fuel,lockout,repair', 1, 3.50, '2026-05-03 18:39:02'),
(6, 'Ali', 'EDDss', 'AAAA@gmail.com', 99999999, '$2y$10$NLNf5xHSa7jOoqVKB8AaQuiKxF5sZthwptAHOwgqB.sz3a3UkgE5a', 'Muscat', NULL, NULL, 'fuel', 1, 0.00, '2026-05-04 21:15:39');

-- --------------------------------------------------------

--
-- Table structure for table `garage_subscriptions`
--

DROP TABLE IF EXISTS `garage_subscriptions`;
CREATE TABLE IF NOT EXISTS `garage_subscriptions` (
  `subscription_id` int NOT NULL AUTO_INCREMENT,
  `garage_id` int NOT NULL,
  `plan_id` int NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('pending_payment','active','expired','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'pending_payment',
  `payment_status` enum('pending','paid','failed') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `payment_method` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_paid` decimal(10,3) DEFAULT '0.000',
  `renewal_auto` tinyint(1) DEFAULT '0',
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`subscription_id`),
  KEY `plan_id` (`plan_id`),
  KEY `idx_garage_status` (`garage_id`,`status`),
  KEY `idx_end_date` (`end_date`)
) ENGINE=MyISAM AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `garage_subscriptions`
--

INSERT INTO `garage_subscriptions` (`subscription_id`, `garage_id`, `plan_id`, `start_date`, `end_date`, `status`, `payment_status`, `payment_method`, `amount_paid`, `renewal_auto`, `paid_at`, `created_at`) VALUES
(1, 1, 2, '2026-05-02', '2026-06-01', 'expired', 'paid', NULL, 45.000, 0, NULL, '2026-05-02 14:00:17'),
(2, 5, 1, '2026-05-03', '2026-05-10', 'expired', 'paid', 'cash', 15.000, 0, '2026-05-03 18:39:49', '2026-05-03 18:39:12'),
(3, 5, 2, '2026-05-03', '2026-07-07', 'active', 'paid', 'online', 45.000, 0, '2026-05-03 18:40:03', '2026-05-03 18:40:00'),
(5, 5, 3, '2026-05-03', '2027-06-26', 'expired', 'paid', 'online', 400.000, 0, '2026-05-03 18:44:07', '2026-05-03 18:40:51'),
(6, 5, 1, '2026-05-04', '2026-05-11', 'expired', 'paid', 'card', 15.000, 0, '2026-05-04 22:10:42', '2026-05-04 22:04:03'),
(8, 5, 1, '2026-05-05', '2026-06-11', 'cancelled', 'pending', NULL, 15.000, 0, NULL, '2026-05-05 21:45:39'),
(9, 1, 1, '2026-05-05', '2026-05-12', 'expired', 'paid', 'card', 15.000, 0, '2026-05-05 21:49:07', '2026-05-05 21:45:51'),
(10, 1, 3, '2026-05-05', '2027-05-05', 'pending_payment', 'pending', NULL, 400.000, 0, NULL, '2026-05-05 21:52:42'),
(11, 6, 1, '2026-05-05', '2026-05-12', 'active', 'paid', 'manual', 15.000, 0, '2026-05-05 22:23:24', '2026-05-05 22:23:24');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `identifier` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'email + role',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_identifier` (`identifier`),
  KEY `idx_attempted` (`attempted_at`)
) ENGINE=MyISAM AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `identifier`, `ip_address`, `attempted_at`) VALUES
(35, 'khalid.rawahi@gmail.com|user', '::1', '2026-05-11 17:49:01');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
CREATE TABLE IF NOT EXISTS `payments` (
  `payment_id` int NOT NULL AUTO_INCREMENT,
  `request_id` int NOT NULL,
  `user_id` int NOT NULL,
  `amount` decimal(10,3) NOT NULL,
  `method` enum('card','cash','online') COLLATE utf8mb4_unicode_ci DEFAULT 'card',
  `status` enum('pending','paid','failed') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `invoice_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_id`),
  UNIQUE KEY `request_id` (`request_id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `request_id`, `user_id`, `amount`, `method`, `status`, `invoice_number`, `paid_at`, `created_at`) VALUES
(1, 2, 1, 15.000, 'online', 'paid', 'IVSS-3A3FDA-2026', '2026-04-30 19:38:28', '2026-04-30 19:37:39'),
(2, 3, 3, 15.000, 'cash', 'paid', 'IVSS-F4A779-2026', '2026-04-30 19:42:10', '2026-04-30 19:41:35'),
(3, 4, 3, 5.500, 'cash', 'paid', 'IVSS-368FE7-2026', '2026-05-01 18:27:58', '2026-05-01 09:16:19'),
(4, 6, 1, 15.000, 'cash', 'paid', 'IVSS-5A7CA3-2026', '2026-05-02 14:06:18', '2026-05-02 14:05:41'),
(5, 7, 1, 15.000, 'cash', 'paid', 'IVSS-0A8070-2026', '2026-05-04 08:52:09', '2026-05-02 19:28:48'),
(6, 9, 4, 30.000, 'card', 'paid', 'IVSS-15938F-2026', '2026-05-04 08:48:03', '2026-05-04 08:46:41'),
(7, 10, 1, 15.000, 'card', 'pending', 'IVSS-875031-2026', NULL, '2026-05-04 22:11:36'),
(8, 12, 4, 29.000, 'card', 'paid', 'IVSS-02A0D7-2026', '2026-05-06 07:09:52', '2026-05-06 07:09:04'),
(9, 11, 1, 2.000, 'card', 'pending', 'IVSS-92979C-2026', NULL, '2026-05-06 07:12:25'),
(10, 13, 1, 15.000, 'card', 'pending', 'IVSS-E1B025-2026', NULL, '2026-05-06 07:12:30'),
(11, 15, 1, 10.000, 'card', 'paid', 'IVSS-310E92-2026', '2026-05-11 17:51:33', '2026-05-11 17:49:39');

-- --------------------------------------------------------

--
-- Table structure for table `service_requests`
--

DROP TABLE IF EXISTS `service_requests`;
CREATE TABLE IF NOT EXISTS `service_requests` (
  `request_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `garage_id` int DEFAULT NULL,
  `service_type` enum('towing','battery','tire','fuel','lockout','repair','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `vehicle_type` enum('sedan','suv','pickup','van','motorcycle','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `location_desc` varchar(300) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `price` decimal(10,3) DEFAULT NULL COMMENT 'Price set by garage before completing',
  `price_set_at` timestamp NULL DEFAULT NULL COMMENT 'When the garage set the price',
  `status` enum('pending','accepted','in_progress','completed','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`request_id`),
  KEY `user_id` (`user_id`),
  KEY `garage_id` (`garage_id`)
) ENGINE=MyISAM AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `service_requests`
--

INSERT INTO `service_requests` (`request_id`, `user_id`, `garage_id`, `service_type`, `vehicle_type`, `location_desc`, `notes`, `price`, `price_set_at`, `status`, `created_at`, `updated_at`) VALUES
(2, 1, 1, 'battery', 'suv', 'muscat', 'vhgvhjbhjbjhooooo', 10.000, '2026-05-01 09:13:09', 'completed', '2026-04-30 19:36:20', '2026-05-01 09:13:09'),
(3, 3, 1, 'repair', 'other', 'muscat', 'cvchvhgjghj', 5.000, '2026-05-01 09:12:58', 'completed', '2026-04-30 19:41:07', '2026-05-01 09:12:58'),
(4, 3, 1, 'tire', 'other', 'muscat', 'tgfyuhguijol;kol;', 5.500, '2026-05-01 09:16:08', 'completed', '2026-05-01 09:13:39', '2026-05-01 09:16:19'),
(5, 3, 3, 'battery', 'pickup', 'muscat', 'asgzgsae', NULL, NULL, 'pending', '2026-05-02 13:59:25', '2026-05-02 13:59:25'),
(6, 1, 1, 'battery', 'pickup', 'muscat', 'njmjmj', 2.000, '2026-05-02 14:05:49', 'completed', '2026-05-02 14:05:05', '2026-05-02 14:05:49'),
(7, 1, 1, 'battery', 'other', 'muscat', 'cfghyj', 5.000, '2026-05-02 19:28:56', 'completed', '2026-05-02 19:28:10', '2026-05-02 19:28:56'),
(8, 4, 3, 'fuel', 'suv', 'ibra', '', NULL, NULL, 'pending', '2026-05-04 08:44:25', '2026-05-04 08:44:25'),
(9, 4, 1, 'battery', 'suv', 'ibra', '', 30.000, '2026-05-04 08:46:36', 'completed', '2026-05-04 08:45:42', '2026-05-04 08:46:41'),
(10, 1, 1, 'battery', 'pickup', 'ibra', 'daasfvg', 5.000, '2026-05-04 22:11:39', 'completed', '2026-05-04 22:11:18', '2026-05-04 22:11:39'),
(11, 1, 5, 'battery', 'suv', 'ibra', 'drghh', 2.000, '2026-05-06 07:12:13', 'completed', '2026-05-05 22:00:53', '2026-05-06 07:12:25'),
(12, 4, 5, 'battery', 'pickup', 'ibra', 'ceveve', 29.000, '2026-05-06 07:04:35', 'completed', '2026-05-06 07:03:24', '2026-05-06 07:09:04'),
(13, 1, 5, 'repair', 'pickup', 'ibra', 'hteshsh', NULL, NULL, 'completed', '2026-05-06 07:11:41', '2026-05-06 07:12:30'),
(14, 4, 4, 'battery', 'suv', 'ibra', 'svsgs', NULL, NULL, 'pending', '2026-05-08 22:45:45', '2026-05-08 22:45:45'),
(15, 1, 5, 'repair', 'van', 'ibra', 'ffgjhkjklj', 10.000, '2026-05-11 17:49:32', 'completed', '2026-05-11 17:48:52', '2026-05-11 17:49:39');

-- --------------------------------------------------------

--
-- Table structure for table `subscription_history`
--

DROP TABLE IF EXISTS `subscription_history`;
CREATE TABLE IF NOT EXISTS `subscription_history` (
  `history_id` int NOT NULL AUTO_INCREMENT,
  `garage_id` int NOT NULL,
  `plan_id` int NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'expired',
  `amount_paid` decimal(10,3) DEFAULT '0.000',
  `archived_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`history_id`),
  KEY `garage_id` (`garage_id`),
  KEY `plan_id` (`plan_id`)
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subscription_history`
--

INSERT INTO `subscription_history` (`history_id`, `garage_id`, `plan_id`, `start_date`, `end_date`, `status`, `amount_paid`, `archived_at`) VALUES
(1, 5, 1, '2026-05-03', '2026-05-10', 'active', 15.000, '2026-05-03 18:40:00'),
(2, 5, 2, '2026-05-03', '2026-06-02', 'active', 45.000, '2026-05-03 18:40:05'),
(3, 5, 2, '2026-05-03', '2026-06-02', 'expired', 45.000, '2026-05-03 18:40:51'),
(4, 5, 3, '2026-05-03', '2027-06-26', 'active', 400.000, '2026-05-04 22:04:03'),
(5, 5, 1, '2026-05-04', '2026-05-11', 'active', 15.000, '2026-05-05 21:40:30'),
(6, 5, 1, '2026-05-04', '2026-05-11', 'expired', 15.000, '2026-05-05 21:45:39'),
(7, 1, 2, '2026-05-02', '2026-06-01', 'active', 45.000, '2026-05-05 21:45:51'),
(8, 1, 1, '2026-05-05', '2026-05-12', 'active', 15.000, '2026-05-05 21:52:42');

-- --------------------------------------------------------

--
-- Table structure for table `subscription_plans`
--

DROP TABLE IF EXISTS `subscription_plans`;
CREATE TABLE IF NOT EXISTS `subscription_plans` (
  `plan_id` int NOT NULL AUTO_INCREMENT,
  `plan_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `duration_days` int NOT NULL,
  `price` decimal(10,3) NOT NULL,
  `features` json DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`plan_id`),
  UNIQUE KEY `plan_name` (`plan_name`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subscription_plans`
--

INSERT INTO `subscription_plans` (`plan_id`, `plan_name`, `duration_days`, `price`, `features`, `is_active`, `created_at`) VALUES
(1, 'Weekly', 7, 15.000, '{\"support\": \"Email\", \"analytics\": true, \"api_access\": false, \"daily_requests\": 10}', 1, '2026-05-02 09:26:59'),
(2, 'Monthly', 30, 45.000, '{\"support\": \"24/7\", \"analytics\": true, \"api_access\": false, \"daily_requests\": 50}', 1, '2026-05-02 09:26:59'),
(3, 'Yearly', 365, 400.000, '{\"support\": \"24/7 VIP\", \"analytics\": true, \"api_access\": true, \"daily_requests\": 9999}', 1, '2026-05-02 09:26:59');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` int UNSIGNED NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `language` enum('en','ar') COLLATE utf8mb4_unicode_ci DEFAULT 'en',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `phone`, `password`, `language`, `is_active`, `created_at`) VALUES
(1, 'Mohammed Al-Siyabi', 'driver1@ivss.om', 77777777, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'en', 1, '2026-04-30 19:14:01'),
(2, 'Fatima Al-Zadjali', 'driver2@ivss.om', 92000002, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'en', 1, '2026-04-30 19:14:01'),
(3, 'tblcomputer', 'kktt@gmail.com', 73550004, '$2y$10$jnmnaWeBJ.ulneeHMx/6xOIdp1sVHruSVvCxTII/7ww5rnsvgzK16', 'en', 1, '2026-04-30 19:40:30'),
(4, 'Ali', 'driver5@ivss.om', 70366888, '$2y$10$.CN.TRifFckVxw57PWIY7eRKTHs5aVf1Ix32wwFVgeeCL3pJDVU4y', 'en', 1, '2026-05-04 08:42:18'),
(5, 'Ali', 'khalid.rawahi@gmail.com', 95556767, '$2y$10$ps6gYhcLwbcz1xpQeYRLDuFWfuLTzvR0s5BtXdK9ccz8HH6tgKtKi', 'en', 1, '2026-05-04 09:19:31'),
(6, 'Ali', 'zzi@gmail.com', 99999999, '$2y$10$ygBW6TV.bmp0ggqMl5Tnsuc7PeixX/XyKhkUxTIhkIkHq1PP0KxP6', 'en', 1, '2026-05-05 21:32:32');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
