-- Development database schema
-- This is used for testing and development purposes

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Create tables for development

CREATE TABLE IF NOT EXISTS `users_dev` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `preferensi_kurs` enum('IDR','USD') DEFAULT 'IDR',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `language` varchar(5) DEFAULT 'id',
  `is_admin` tinyint(1) DEFAULT '0',
  `last_activity` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(64) NULL,
  `token_expires` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert test user
INSERT INTO `users_dev` (`nama`, `email`, `password`, `is_admin`) VALUES
('Test Admin', 'test@admin.com', '$2y$10$Df79wGVHPa3vyF6vIrBYs.nt3Yu7kaKXrRxX.21Ny3ZJ9AyIHHJMu', 1),
('Test User', 'test@user.com', '$2y$10$Df79wGVHPa3vyF6vIrBYs.nt3Yu7kaKXrRxX.21Ny3ZJ9AyIHHJMu', 0);

-- Create other development tables
CREATE TABLE IF NOT EXISTS `transactions_dev` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `kategori` varchar(50) NOT NULL,
  `jumlah` decimal(15,2) NOT NULL,
  `kurs` enum('IDR','USD') NOT NULL,
  `jenis` enum('pemasukan','pengeluaran') NOT NULL,
  `tanggal` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `storage_type_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert test transactions
INSERT INTO `transactions_dev` (`user_id`, `kategori`, `jumlah`, `kurs`, `jenis`, `tanggal`, `storage_type_id`) VALUES
(1, 'Test Income', 100000.00, 'IDR', 'pemasukan', CURDATE(), 1),
(1, 'Test Expense', 50000.00, 'IDR', 'pengeluaran', CURDATE(), 1);

-- Add more development tables and test data as needed

COMMIT;
