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
-- Table structure for table `smtp_servers`
--

CREATE TABLE `smtp_servers` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `host` varchar(100) NOT NULL,
  `port` int(11) NOT NULL,
  `encryption` varchar(10) NOT NULL,
  `received_email` varchar(150) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_uid` bigint(20) UNSIGNED DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `smtp_servers`
--

INSERT INTO `smtp_servers` (`id`, `name`, `host`, `port`, `encryption`, `received_email`, `is_active`, `created_at`, `last_uid`) VALUES
(9, 'SMTP5', 'smtp.gmail.com', 465, 'ssl', 'panchalpavan7090@gmail.com', 1, '2025-08-13 12:21:58', 64916),
(14, 'SMTP2', 'relyonsoft.info', 465, 'ssl', 'reply@relyonsoft.com', 1, '2025-10-28 07:03:12', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `smtp_servers`
--
ALTER TABLE `smtp_servers`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `smtp_servers`
--
ALTER TABLE `smtp_servers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
