-- Run this in phpMyAdmin or your MySQL client against dcsm_db_NEW
-- Creates the vouchers table required for the voucher system

CREATE TABLE IF NOT EXISTS `vouchers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `created_by` varchar(50) DEFAULT NULL,
  `status` enum('active','used','revoked') NOT NULL DEFAULT 'active',
  `expires_at` date DEFAULT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `used_by_control_no` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
