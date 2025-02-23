-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Feb 23, 2025 at 05:11 AM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `keuangan`
--

-- --------------------------------------------------------

--
-- Table structure for table `auth_attempts`
--

CREATE TABLE `auth_attempts` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attempted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_successful` tinyint(1) DEFAULT '0',
  `attempt_type` enum('login','2fa','backup_code') COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `database_version`
--

CREATE TABLE `database_version` (
  `id` int NOT NULL,
  `version` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `database_version`
--

INSERT INTO `database_version` (`id`, `version`, `updated_at`) VALUES
(1, '1.0.3', '2025-02-19 13:27:57');

-- --------------------------------------------------------

--
-- Table structure for table `debts`
--

CREATE TABLE `debts` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `nama` varchar(100) NOT NULL,
  `nomor_hp` varchar(20) NOT NULL,
  `jumlah` decimal(15,2) NOT NULL,
  `kurs` enum('IDR','USD') NOT NULL,
  `jenis` enum('hutang','piutang') NOT NULL,
  `tanggal_pinjam` date NOT NULL,
  `jatuh_tempo` date NOT NULL,
  `status` enum('belum_lunas','lunas') DEFAULT 'belum_lunas',
  `keterangan` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `debts`
--

INSERT INTO `debts` (`id`, `user_id`, `nama`, `nomor_hp`, `jumlah`, `kurs`, `jenis`, `tanggal_pinjam`, `jatuh_tempo`, `status`, `keterangan`, `created_at`) VALUES
(1, 1, 'Dias', '-', 30000.00, 'IDR', 'piutang', '2025-02-15', '2025-03-08', 'lunas', 'Merusakan kunci', '2025-02-15 18:03:37'),
(4, 2, 'demo', '0895676754', 20000.00, 'IDR', 'piutang', '2025-02-18', '2025-02-25', 'lunas', 'test', '2025-02-18 12:45:16');

-- --------------------------------------------------------

--
-- Table structure for table `financial_targets`
--

CREATE TABLE `financial_targets` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `target_type` enum('pemasukan','pengeluaran','saldo') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `kurs` enum('IDR','USD') NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `period_type` enum('daily','weekly','monthly','yearly') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `financial_targets`
--

INSERT INTO `financial_targets` (`id`, `user_id`, `target_type`, `amount`, `kurs`, `is_active`, `period_type`, `start_date`, `end_date`, `created_at`) VALUES
(1, 2, 'pemasukan', 20000.00, 'IDR', 1, 'monthly', '2025-02-01', '2025-02-28', '2025-02-17 15:19:07'),
(13, 1, 'pemasukan', 60000.00, 'IDR', 1, 'daily', '2025-02-22', '2025-02-24', '2025-02-22 04:41:48');

-- --------------------------------------------------------

--
-- Table structure for table `storage_types`
--

CREATE TABLE `storage_types` (
  `id` int NOT NULL,
  `nama` varchar(50) NOT NULL,
  `jenis` enum('cash','bank','ewallet') NOT NULL,
  `icon` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `storage_types`
--

INSERT INTO `storage_types` (`id`, `nama`, `jenis`, `icon`) VALUES
(1, 'Cash/Fisik', 'cash', 'fa-money-bill-wave'),
(2, 'BCA', 'bank', 'fa-university'),
(3, 'Mandiri', 'bank', 'fa-university'),
(4, 'BRI', 'bank', 'fa-university'),
(5, 'BNI', 'bank', 'fa-university'),
(6, 'GoPay', 'ewallet', 'fa-wallet'),
(7, 'OVO', 'ewallet', 'fa-wallet'),
(8, 'DANA', 'ewallet', 'fa-wallet'),
(9, 'ShopeePay', 'ewallet', 'fa-wallet');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `kategori` varchar(50) NOT NULL,
  `jumlah` decimal(15,2) NOT NULL,
  `kurs` enum('IDR','USD') NOT NULL,
  `jenis` enum('pemasukan','pengeluaran') NOT NULL,
  `tanggal` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `storage_type_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `user_id`, `kategori`, `jumlah`, `kurs`, `jenis`, `tanggal`, `created_at`, `storage_type_id`) VALUES
(2, 1, 'uang jajan', 57203.65, 'IDR', 'pemasukan', '2025-02-14', '2025-02-14 12:15:38', 1),
(3, 1, 'jajan', 6000.00, 'IDR', 'pengeluaran', '2025-02-15', '2025-02-15 06:18:34', 1),
(4, 1, 'selki', 10047.10, 'IDR', 'pengeluaran', '2025-02-15', '2025-02-15 09:27:28', 1),
(5, 1, 'uang jajan', 15070.65, 'IDR', 'pemasukan', '2025-02-15', '2025-02-15 13:24:12', 1),
(6, 1, 'jajan', 5000.00, 'IDR', 'pengeluaran', '2025-02-16', '2025-02-15 19:03:21', 1),
(8, 2, 'demo', 10000.00, 'IDR', 'pemasukan', '2025-02-16', '2025-02-16 08:50:52', 1),
(9, 2, 'demo', 5000.00, 'IDR', 'pengeluaran', '2025-02-16', '2025-02-16 08:59:39', 1),
(10, 2, 'test', 5000.00, 'IDR', 'pemasukan', '2025-02-18', '2025-02-18 12:56:01', 1),
(11, 2, 'demonstration', 10000.00, 'IDR', 'pemasukan', '2025-02-18', '2025-02-18 14:27:20', 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `nama` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `preferensi_kurs` enum('IDR','USD') DEFAULT 'IDR',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `language` varchar(5) DEFAULT 'id',
  `is_admin` tinyint(1) DEFAULT '0',
  `last_activity` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nama`, `email`, `password`, `preferensi_kurs`, `created_at`, `language`, `is_admin`, `last_activity`) VALUES
(1, 'riz', 'rizqiaditya531@gmail.com', '$2y$10$8.7n1DK5FAW1tblTT5UKMOS1L5FIuVGoMRMWIg4nkCFcQUgp3n.i6', 'IDR', '2025-02-14 11:09:17', NULL, 0, '2025-02-23 04:11:19'),
(2, 'demo', 'demo@rgames.eu.org', '$2y$10$g7ecDdLjyroTIdEYvM1k3eXdkOCbMinIQnttYyNYX.Dmr4RsNKeRi', NULL, '2025-02-16 08:49:55', NULL, 0, '2025-02-23 05:10:36'),
(4, 'Admin', 'admin@admin.com', '$2y$10$Df79wGVHPa3vyF6vIrBYs.nt3Yu7kaKXrRxX.21Ny3ZJ9AyIHHJMu', 'IDR', '2025-02-22 05:22:13', 'id', 1, '2025-02-23 04:46:04');

-- --------------------------------------------------------

--
-- Table structure for table `user_2fa`
--

CREATE TABLE `user_2fa` (
  `user_id` int NOT NULL,
  `secret_key` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_enabled` tinyint(1) DEFAULT '0',
  `backup_codes` text COLLATE utf8mb4_unicode_ci,
  `attempts` int DEFAULT '0',
  `last_attempt` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text NOT NULL,
  `device_info` text NOT NULL,
  `login_time` datetime NOT NULL,
  `last_activity` datetime NOT NULL,
  `is_active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `auth_attempts`
--
ALTER TABLE `auth_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_auth_attempts_user_id` (`user_id`),
  ADD KEY `idx_auth_attempts_ip` (`ip_address`),
  ADD KEY `idx_auth_attempts_time` (`attempted_at`);

--
-- Indexes for table `database_version`
--
ALTER TABLE `database_version`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `version_unique` (`version`),
  ADD KEY `idx_database_version_version` (`version`);

--
-- Indexes for table `debts`
--
ALTER TABLE `debts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_debts_user_id` (`user_id`),
  ADD KEY `idx_debts_status` (`status`);

--
-- Indexes for table `financial_targets`
--
ALTER TABLE `financial_targets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_target` (`user_id`,`target_type`,`period_type`,`start_date`,`end_date`);

--
-- Indexes for table `storage_types`
--
ALTER TABLE `storage_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `fk_transactions_storage_type` (`storage_type_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_2fa`
--
ALTER TABLE `user_2fa`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `idx_user_2fa_is_enabled` (`is_enabled`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `auth_attempts`
--
ALTER TABLE `auth_attempts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `database_version`
--
ALTER TABLE `database_version`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `debts`
--
ALTER TABLE `debts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `financial_targets`
--
ALTER TABLE `financial_targets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `storage_types`
--
ALTER TABLE `storage_types`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `auth_attempts`
--
ALTER TABLE `auth_attempts`
  ADD CONSTRAINT `auth_attempts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `debts`
--
ALTER TABLE `debts`
  ADD CONSTRAINT `debts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `financial_targets`
--
ALTER TABLE `financial_targets`
  ADD CONSTRAINT `financial_targets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_transactions_storage_type` FOREIGN KEY (`storage_type_id`) REFERENCES `storage_types` (`id`),
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `transactions_storage_type_id_foreign` FOREIGN KEY (`storage_type_id`) REFERENCES `storage_types` (`id`);

--
-- Constraints for table `user_2fa`
--
ALTER TABLE `user_2fa`
  ADD CONSTRAINT `user_2fa_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
