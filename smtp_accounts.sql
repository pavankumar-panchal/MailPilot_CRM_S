-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Oct 28, 2025 at 10:30 AM
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
-- Database: `CRM`
--

-- --------------------------------------------------------

--
-- Table structure for table `smtp_accounts`
--

CREATE TABLE `smtp_accounts` (
  `id` int(11) NOT NULL,
  `smtp_server_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `daily_limit` int(11) DEFAULT 500,
  `hourly_limit` int(11) DEFAULT 100,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `smtp_accounts`
--

INSERT INTO `smtp_accounts` (`id`, `smtp_server_id`, `email`, `password`, `daily_limit`, `hourly_limit`, `is_active`, `created_at`) VALUES
(29, 9, 'panchalpavan800@gmail.com', 'rdbm lmej hyek ljzg', 500, 100, 1, '2025-10-26 04:00:11'),
(30, 14, 'pavan@relyonsoft.info', '&0b1Qg31v', 500, 100, 1, '2025-10-28 07:03:12');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `smtp_accounts`
--
ALTER TABLE `smtp_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `smtp_server_id` (`smtp_server_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `smtp_accounts`
--
ALTER TABLE `smtp_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `smtp_accounts`
--
ALTER TABLE `smtp_accounts`
  ADD CONSTRAINT `smtp_accounts_ibfk_1` FOREIGN KEY (`smtp_server_id`) REFERENCES `smtp_servers` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
