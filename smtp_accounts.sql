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
-- Table structure for table `smtp_accounts`
--

CREATE TABLE `smtp_accounts` (
  `id` int(11) NOT NULL,
  `smtp_server_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `from_name` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `daily_limit` int(11) DEFAULT 500,
  `hourly_limit` int(11) DEFAULT 100,
  `hour_started_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sent_today` int(11) NOT NULL DEFAULT 0,
  `total_sent` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `smtp_accounts`
--

INSERT INTO `smtp_accounts` (`id`, `smtp_server_id`, `email`, `from_name`, `password`, `daily_limit`, `hourly_limit`, `hour_started_at`, `is_active`, `created_at`, `sent_today`, `total_sent`) VALUES
(30, 14, 'pavan@relyonsoft.info', NULL, '&0b1Qg31v', 500, 50, '2025-11-27 09:44:06', 1, '2025-10-28 07:03:12', 0, 0),
(31, 14, 'praveen@relyonsoft.info', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:04', 1, '2025-10-28 10:07:36', 0, 0),
(32, 15, 'praveen@relyonmail.xyz', NULL, 'xZ^1u55i2', 500, 50, '2025-11-27 09:44:06', 1, '2025-10-28 10:07:36', 0, 36),
(33, 16, 'praveen@payrollsoft.in', NULL, 'xZ^1u55i2', 500, 50, '2025-11-27 09:44:06', 1, '2025-10-28 10:07:36', 0, 32),
(34, 17, 'praveen@relyonmails1.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-27 09:44:06', 1, '2025-10-28 10:07:36', 0, 0),
(35, 18, 'praveen@relyonmails3.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-27 09:44:07', 1, '2025-10-28 10:07:36', 0, 0),
(36, 19, 'praveen@relyonmails2.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-27 09:44:07', 1, '2025-10-28 10:07:36', 0, 29),
(37, 20, 'praveen@relyonmail.online', NULL, 'xZ^1u55i2', 500, 50, '2025-11-27 09:44:07', 1, '2025-10-28 10:07:36', 0, 0),
(38, 14, 'chethan@relyonsoft.info', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:05', 1, '2025-10-28 10:07:36', 0, 0),
(39, 15, 'chethan@relyonmail.xyz', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:06', 1, '2025-10-28 10:07:36', 0, 35),
(40, 16, 'chethan@payrollsoft.in', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:06', 1, '2025-10-28 10:07:36', 0, 32),
(41, 17, 'chethan@relyonmails1.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:06', 1, '2025-10-28 10:07:36', 0, 0),
(42, 18, 'chethan@relyonmails3.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:06', 1, '2025-10-28 10:07:36', 0, 0),
(43, 19, 'chethan@relyonmails2.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:06', 1, '2025-10-28 10:07:36', 0, 29),
(44, 20, 'chethan@relyonmail.online', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:07', 1, '2025-10-28 10:07:36', 0, 0),
(45, 14, 'divya@relyonsoft.info', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:07', 1, '2025-10-28 10:07:36', 0, 0),
(46, 15, 'divya@relyonmail.xyz', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:06', 1, '2025-10-28 10:07:36', 0, 36),
(47, 16, 'divya@payrollsoft.in', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:06', 1, '2025-10-28 10:07:36', 0, 32),
(48, 17, 'divya@relyonmails1.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:06', 1, '2025-10-28 10:07:36', 0, 0),
(49, 18, 'divya@relyonmails3.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:10', 1, '2025-10-28 10:07:36', 0, 0),
(50, 19, 'divya@relyonmails2.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:10', 1, '2025-10-28 10:07:36', 0, 29),
(51, 20, 'divya@relyonmail.online', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:10', 1, '2025-10-28 10:07:36', 0, 0),
(52, 14, 'veena@relyonsoft.info', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:08', 1, '2025-10-28 10:07:36', 0, 0),
(53, 15, 'veena@relyonmail.xyz', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:12', 1, '2025-10-28 10:07:36', 0, 35),
(54, 16, 'veena@payrollsoft.in', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:13', 1, '2025-10-28 10:07:36', 0, 32),
(55, 17, 'veena@relyonmails1.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:12', 1, '2025-10-28 10:07:36', 0, 0),
(56, 18, 'veena@relyonmails3.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:13', 1, '2025-10-28 10:07:36', 0, 0),
(57, 19, 'veena@relyonmails2.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:13', 1, '2025-10-28 10:07:36', 0, 29),
(58, 20, 'veena@relyonmail.online', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:13', 1, '2025-10-28 10:07:36', 0, 0),
(59, 14, 'richa@relyonsoft.info', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:09', 1, '2025-10-28 10:07:36', 0, 0),
(60, 15, 'richa@relyonmail.xyz', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:12', 1, '2025-10-28 10:07:36', 0, 36),
(61, 16, 'richa@payrollsoft.in', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:13', 1, '2025-10-28 10:07:36', 0, 31),
(62, 17, 'richa@relyonmails1.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:16', 1, '2025-10-28 10:07:36', 0, 0),
(63, 18, 'richa@relyonmails3.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:13', 1, '2025-10-28 10:07:36', 0, 0),
(64, 19, 'richa@relyonmails2.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:13', 1, '2025-10-28 10:07:36', 0, 28),
(65, 20, 'richa@relyonmail.online', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:13', 1, '2025-10-28 10:07:36', 0, 0),
(66, 14, 'navya@relyonsoft.info', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:10', 1, '2025-10-28 10:07:36', 0, 0),
(67, 15, 'navya@relyonmail.xyz', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:13', 1, '2025-10-28 10:07:36', 0, 37),
(68, 16, 'navya@payrollsoft.in', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:13', 1, '2025-10-28 10:07:36', 0, 32),
(69, 17, 'navya@relyonmails1.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:19', 1, '2025-10-28 10:07:36', 0, 0),
(70, 18, 'navya@relyonmails3.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:30', 1, '2025-10-28 10:07:36', 0, 0),
(71, 19, 'navya@relyonmails2.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:19', 1, '2025-10-28 10:07:36', 0, 13),
(72, 20, 'navya@relyonmail.online', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:25', 1, '2025-10-28 10:07:36', 0, 0),
(73, 14, 'ankitha@relyonsoft.info', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:11', 1, '2025-10-28 10:07:36', 0, 0),
(74, 15, 'ankitha@relyonmail.xyz', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:13', 1, '2025-10-28 10:07:36', 0, 37),
(75, 16, 'ankitha@payrollsoft.in', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:20', 1, '2025-10-28 10:07:36', 0, 32),
(76, 17, 'ankitha@relyonmails1.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:21', 1, '2025-10-28 10:07:36', 0, 0),
(77, 18, 'ankitha@relyonmails3.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:30', 1, '2025-10-28 10:07:36', 0, 0),
(78, 19, 'ankitha@relyonmails2.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:19', 1, '2025-10-28 10:07:36', 0, 13),
(79, 20, 'ankitha@relyonmail.online', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:26', 1, '2025-10-28 10:07:36', 0, 0),
(80, 14, 'vani@relyonsoft.info', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:12', 1, '2025-10-28 10:07:36', 0, 0),
(81, 15, 'vani@relyonmail.xyz', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:19', 1, '2025-10-28 10:07:36', 0, 36),
(82, 16, 'vani@payrollsoft.in', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:20', 1, '2025-10-28 10:07:36', 0, 32),
(83, 17, 'vani@relyonmails1.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:24', 1, '2025-10-28 10:07:36', 0, 0),
(84, 18, 'vani@relyonmails3.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:30', 1, '2025-10-28 10:07:36', 0, 0),
(85, 19, 'vani@relyonmails2.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:20', 1, '2025-10-28 10:07:36', 0, 16),
(86, 20, 'vani@relyonmail.online', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:26', 1, '2025-10-28 10:07:36', 0, 0),
(87, 14, 'david@relyonsoft.info', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:13', 1, '2025-10-28 10:07:36', 0, 0),
(88, 15, 'david@relyonmail.xyz', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:19', 1, '2025-10-28 10:07:36', 0, 40),
(89, 16, 'david@payrollsoft.in', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:22', 1, '2025-10-28 10:07:36', 0, 32),
(90, 17, 'david@relyonmails1.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:24', 1, '2025-10-28 10:07:36', 0, 0),
(91, 18, 'david@relyonmails3.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:30', 1, '2025-10-28 10:07:36', 0, 0),
(92, 19, 'david@relyonmails2.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:22', 1, '2025-10-28 10:07:36', 0, 13),
(93, 20, 'david@relyonmail.online', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:26', 1, '2025-10-28 10:07:36', 0, 0),
(94, 14, 'yathish@relyonsoft.info', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:15', 1, '2025-10-28 10:07:36', 0, 0),
(95, 15, 'yathish@relyonmail.xyz', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:19', 1, '2025-10-28 10:07:36', 0, 37),
(96, 16, 'yathish@payrollsoft.in', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:22', 1, '2025-10-28 10:07:36', 0, 32),
(97, 17, 'yathish@relyonmails1.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:26', 1, '2025-10-28 10:07:36', 0, 0),
(98, 18, 'yathish@relyonmails3.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:30', 1, '2025-10-28 10:07:36', 0, 0),
(99, 19, 'yathish@relyonmails2.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:22', 1, '2025-10-28 10:07:36', 0, 13),
(100, 20, 'yathish@relyonmail.online', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:26', 1, '2025-10-28 10:07:36', 0, 0),
(101, 14, 'priyanka@relyonsoft.info', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:15', 1, '2025-10-28 10:07:36', 0, 0),
(102, 15, 'priyanka@relyonmail.xyz', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:20', 1, '2025-10-28 10:07:36', 0, 9),
(103, 16, 'priyanka@payrollsoft.in', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:22', 1, '2025-10-28 10:07:36', 0, 8),
(104, 17, 'priyanka@relyonmails1.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:26', 1, '2025-10-28 10:07:36', 0, 0),
(105, 18, 'priyanka@relyonmails3.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:30', 1, '2025-10-28 10:07:36', 0, 0),
(106, 19, 'priyanka@relyonmails2.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:22', 1, '2025-10-28 10:07:36', 0, 7),
(107, 20, 'priyanka@relyonmail.online', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:26', 1, '2025-10-28 10:07:36', 0, 0),
(108, 14, 'nisha@relyonsoft.info', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:16', 1, '2025-10-28 10:07:36', 0, 0),
(109, 15, 'nisha@relyonmail.xyz', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:20', 1, '2025-10-28 10:07:36', 0, 9),
(110, 16, 'nisha@payrollsoft.in', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:22', 1, '2025-10-28 10:07:36', 0, 8),
(111, 17, 'nisha@relyonmails1.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:27', 1, '2025-10-28 10:07:36', 0, 0),
(112, 18, 'nisha@relyonmails3.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:30', 1, '2025-10-28 10:07:36', 0, 0),
(113, 19, 'nisha@relyonmails2.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:22', 1, '2025-10-28 10:07:36', 0, 7),
(114, 20, 'nisha@relyonmail.online', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:26', 1, '2025-10-28 10:07:36', 0, 0),
(115, 14, 'pooja@relyonsoft.info', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:17', 1, '2025-10-28 10:07:36', 0, 0),
(116, 15, 'pooja@relyonmail.xyz', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:20', 1, '2025-10-28 10:07:36', 0, 9),
(117, 16, 'pooja@payrollsoft.in', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:22', 1, '2025-10-28 10:07:36', 0, 8),
(118, 17, 'pooja@relyonmails1.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:27', 1, '2025-10-28 10:07:36', 0, 0),
(119, 18, 'pooja@relyonmails3.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:30', 1, '2025-10-28 10:07:36', 0, 0),
(120, 19, 'pooja@relyonmails2.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:24', 1, '2025-10-28 10:07:36', 0, 7),
(121, 20, 'pooja@relyonmail.online', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:26', 1, '2025-10-28 10:07:36', 0, 0),
(122, 14, 'sandeep@relyonsoft.info', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:18', 1, '2025-10-28 10:07:36', 0, 0),
(123, 15, 'sandeep@relyonmail.xyz', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:20', 1, '2025-10-28 10:07:36', 0, 9),
(124, 16, 'sandeep@payrollsoft.in', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:22', 1, '2025-10-28 10:07:36', 0, 8),
(125, 17, 'sandeep@relyonmails1.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:27', 1, '2025-10-28 10:07:36', 0, 0),
(126, 18, 'sandeep@relyonmails3.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:31', 1, '2025-10-28 10:07:36', 0, 0),
(127, 19, 'sandeep@relyonmails2.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:24', 1, '2025-10-28 10:07:36', 0, 7),
(128, 20, 'sandeep@relyonmail.online', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:26', 1, '2025-10-28 10:07:36', 0, 0),
(129, 14, 'sowmya@relyonsoft.info', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:19', 1, '2025-10-28 10:07:36', 0, 0),
(130, 15, 'sowmya@relyonmail.xyz', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:20', 1, '2025-10-28 10:07:36', 0, 9),
(131, 16, 'sowmya@payrollsoft.in', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:22', 1, '2025-10-28 10:07:36', 0, 8),
(132, 17, 'sowmya@relyonmails1.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:27', 1, '2025-10-28 10:07:36', 0, 0),
(133, 18, 'sowmya@relyonmails3.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:31', 1, '2025-10-28 10:07:36', 0, 0),
(134, 19, 'sowmya@relyonmails2.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:24', 1, '2025-10-28 10:07:36', 0, 7),
(135, 20, 'sowmya@relyonmail.online', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:26', 1, '2025-10-28 10:07:36', 0, 0),
(136, 14, 'prajwal@relyonsoft.info', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:20', 1, '2025-10-28 10:07:36', 0, 0),
(137, 15, 'prajwal@relyonmail.xyz', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:20', 1, '2025-10-28 10:07:36', 0, 7),
(138, 16, 'prajwal@payrollsoft.in', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:23', 1, '2025-10-28 10:07:36', 0, 8),
(139, 17, 'prajwal@relyonmails1.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:27', 1, '2025-10-28 10:07:36', 0, 0),
(140, 18, 'prajwal@relyonmails3.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:31', 1, '2025-10-28 10:07:36', 0, 0),
(141, 19, 'prajwal@relyonmails2.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:24', 1, '2025-10-28 10:07:36', 0, 3),
(142, 20, 'prajwal@relyonmail.online', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:26', 1, '2025-10-28 10:07:36', 0, 0),
(143, 14, 'spandana@relyonsoft.info', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:22', 1, '2025-10-28 10:07:36', 0, 0),
(144, 15, 'spandana@relyonmail.xyz', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:20', 1, '2025-10-28 10:07:36', 0, 7),
(145, 16, 'spandana@payrollsoft.in', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:23', 1, '2025-10-28 10:07:36', 0, 8),
(146, 17, 'spandana@relyonmails1.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:30', 1, '2025-10-28 10:07:36', 0, 0),
(147, 18, 'spandana@relyonmails3.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:31', 1, '2025-10-28 10:07:36', 0, 0),
(148, 19, 'spandana@relyonmails2.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:24', 1, '2025-10-28 10:07:36', 0, 3),
(149, 20, 'spandana@relyonmail.online', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:33', 1, '2025-10-28 10:07:36', 0, 0),
(150, 14, 'gagana@relyonsoft.info', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:23', 1, '2025-10-28 10:07:36', 0, 0),
(151, 15, 'gagana@relyonmail.xyz', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:26', 1, '2025-10-28 10:07:36', 0, 7),
(152, 16, 'gagana@payrollsoft.in', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:23', 1, '2025-10-28 10:07:36', 0, 8),
(153, 17, 'gagana@relyonmails1.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:30', 1, '2025-10-28 10:07:36', 0, 0),
(154, 18, 'gagana@relyonmails3.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:31', 1, '2025-10-28 10:07:36', 0, 0),
(155, 19, 'gagana@relyonmails2.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:24', 1, '2025-10-28 10:07:36', 0, 3),
(156, 20, 'gagana@relyonmail.online', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:33', 1, '2025-10-28 10:07:36', 0, 0),
(157, 14, 'sachin@relyonsoft.info', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:24', 1, '2025-10-28 10:07:36', 0, 0),
(158, 15, 'sachin@relyonmail.xyz', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:26', 1, '2025-10-28 10:07:36', 0, 7),
(159, 16, 'sachin@payrollsoft.in', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:23', 1, '2025-10-28 10:07:36', 0, 8),
(160, 17, 'sachin@relyonmails1.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:30', 1, '2025-10-28 10:07:36', 0, 0),
(161, 18, 'sachin@relyonmails3.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:31', 1, '2025-10-28 10:07:36', 0, 0),
(162, 19, 'sachin@relyonmails2.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:25', 1, '2025-10-28 10:07:36', 0, 3),
(163, 20, 'sachin@relyonmail.online', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:33', 1, '2025-10-28 10:07:36', 0, 0),
(164, 14, 'alexander@relyonsoft.info', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:25', 1, '2025-10-28 10:07:36', 0, 0),
(165, 15, 'alexander@relyonmail.xyz', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:26', 1, '2025-10-28 10:07:36', 0, 4),
(166, 16, 'alexander@payrollsoft.in', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:23', 1, '2025-10-28 10:07:36', 0, 8),
(167, 17, 'alexander@relyonmails1.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:30', 1, '2025-10-28 10:07:36', 0, 0),
(168, 18, 'alexander@relyonmails3.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:31', 1, '2025-10-28 10:07:36', 0, 0),
(169, 19, 'alexander@relyonmails2.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:25', 1, '2025-10-28 10:07:36', 0, 0),
(170, 20, 'alexander@relyonmail.online', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:33', 1, '2025-10-28 10:07:36', 0, 0),
(171, 14, 'tony@relyonsoft.info', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:26', 1, '2025-10-28 10:07:36', 0, 0),
(172, 15, 'tony@relyonmail.xyz', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:30', 1, '2025-10-28 10:07:36', 0, 0),
(173, 16, 'tony@payrollsoft.in', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:29', 1, '2025-10-28 10:07:36', 0, 0),
(174, 17, 'tony@relyonmails1.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:33', 1, '2025-10-28 10:07:36', 0, 0),
(175, 18, 'tony@relyonmails3.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:31', 1, '2025-10-28 10:07:36', 0, 0),
(176, 19, 'tony@relyonmails2.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:25', 1, '2025-10-28 10:07:36', 0, 0),
(177, 20, 'tony@relyonmail.online', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:33', 1, '2025-10-28 10:07:36', 0, 0),
(178, 14, 'joel@relyonsoft.info', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:27', 1, '2025-10-28 10:07:36', 0, 0),
(179, 15, 'joel@relyonmail.xyz', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:32', 1, '2025-10-28 10:07:36', 0, 0),
(180, 16, 'joel@payrollsoft.in', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:29', 1, '2025-10-28 10:07:36', 0, 0),
(181, 17, 'joel@relyonmails1.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:33', 1, '2025-10-28 10:07:36', 0, 0),
(182, 18, 'joel@relyonmails3.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:38', 1, '2025-10-28 10:07:36', 0, 0),
(183, 19, 'joel@relyonmails2.com', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:30', 1, '2025-10-28 10:07:36', 0, 0),
(184, 20, 'joel@relyonmail.online', NULL, 'xZ^1u55i2', 500, 50, '2025-11-26 20:40:34', 1, '2025-10-28 10:07:36', 0, 0),
(185, 14, 'akash@relyonsoft.info', NULL, 'xZ^1u55i24', 500, 50, '2025-11-26 20:40:28', 1, '2025-10-28 10:07:36', 0, 0),
(186, 15, 'akash@relyonmail.xyz', NULL, 'xZ^1u55i24', 500, 50, '2025-11-26 20:40:32', 1, '2025-10-28 10:07:36', 0, 0),
(187, 16, 'akash@payrollsoft.in', NULL, 'xZ^1u55i24', 500, 50, '2025-11-26 20:40:31', 1, '2025-10-28 10:07:36', 0, 0),
(188, 17, 'akash@relyonmails1.com', NULL, 'xZ^1u55i24', 500, 50, '2025-11-26 20:40:33', 1, '2025-10-28 10:07:36', 0, 0),
(189, 18, 'akash@relyonmails3.com', NULL, 'xZ^1u55i24', 500, 50, '2025-11-26 20:40:38', 1, '2025-10-28 10:07:36', 0, 0),
(190, 19, 'akash@relyonmails2.com', NULL, 'xZ^1u55i24', 500, 50, '2025-11-26 20:40:31', 1, '2025-10-28 10:07:36', 0, 0),
(191, 20, 'akash@relyonmail.online', NULL, 'xZ^1u55i24', 500, 50, '2025-11-26 20:40:34', 1, '2025-10-28 10:07:36', 0, 0);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=192;

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
