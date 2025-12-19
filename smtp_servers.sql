-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Dec 18, 2025 at 12:51 PM
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
(14, 'SMTP_relyonsoft.info', 'relyonsoft.info', 465, 'ssl', 'pavankumar.c@relyonsoft.com', 1, '2025-10-28 07:03:12', 0),
(15, 'SMTP_relyonmail.xyz', 'relyonmail.xyz', 465, 'ssl', 'pavankumar.c@relyonsoft.com', 1, '2025-10-28 10:07:36', 0),
(16, 'SMTP_payrollsoft.in', 'payrollsoft.in', 465, 'ssl', 'pavankumar.c@relyonsoft.com', 1, '2025-10-28 10:07:36', 0),
(17, 'SMTP_relyonmails1.com', 'relyonmails1.com', 465, 'ssl', 'pavankumar.c@relyonsoft.com', 1, '2025-10-28 10:07:36', 0),
(18, 'SMTP_relyonmails3.com', 'relyonmails3.com', 465, 'ssl', 'pavankumar.c@relyonsoft.com', 1, '2025-10-28 10:07:36', 0),
(19, 'SMTP_relyonmails2.com', 'relyonmails2.com', 465, 'ssl', 'pavankumar.c@relyonsoft.com', 1, '2025-10-28 10:07:36', 0),
(20, 'SMTP_relyonmail.online', 'relyonmail.online', 465, 'ssl', 'pavankumar.c@relyonsoft.com', 1, '2025-10-28 10:07:36', 0);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
