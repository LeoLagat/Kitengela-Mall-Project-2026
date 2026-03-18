-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 18, 2026 at 12:07 PM
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
-- Database: `kitengela_mall_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `administrators`
--

CREATE TABLE `administrators` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('super_admin','admin') DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `administrators`
--

INSERT INTO `administrators` (`id`, `username`, `password`, `role`, `created_at`) VALUES
(2, 'leolagat', '$2y$10$cwmACWBV/RYtcL6a.f237urGHWiiXTBLaQWoK7rcw2iUb8ZwZyJ7y', 'super_admin', '2026-03-10 08:52:41'),
(3, 'ryan', '$2y$10$ftKsEJM7v2HV0dR0CGwaPOcUc0q.XanzFx9rCHISefB74SnSlBlia', 'admin', '2026-03-10 08:54:58');

-- --------------------------------------------------------

--
-- Table structure for table `admin_activity`
--

CREATE TABLE `admin_activity` (
  `id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `username` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_activity`
--

INSERT INTO `admin_activity` (`id`, `action`, `ip_address`, `created_at`, `username`) VALUES
(1, 'visited activity log', '::1', '2026-03-18 13:54:46', 'leolagat'),
(2, 'visited activity log', '::1', '2026-03-18 13:54:51', 'leolagat'),
(3, 'viewed dashboard', '::1', '2026-03-18 13:55:28', 'leolagat'),
(4, 'viewed dashboard', '::1', '2026-03-18 14:02:02', 'leolagat'),
(5, 'viewed dashboard', '::1', '2026-03-18 14:02:34', 'leolagat'),
(6, 'executed manual bypass for vehicle KLH559Y (bay 1) | previous status: pending', '::1', '2026-03-18 14:02:42', 'leolagat'),
(7, 'executed emergency bypass for vehicle KLH559Y (vehicle already exited but gate forced open)', '::1', '2026-03-18 14:02:52', 'leolagat'),
(8, 'viewed dashboard', '::1', '2026-03-18 14:02:55', 'leolagat'),
(9, 'visited add user page', '::1', '2026-03-18 14:02:58', 'leolagat'),
(10, 'visited activity log', '::1', '2026-03-18 14:03:00', 'leolagat'),
(11, 'viewed dashboard', '::1', '2026-03-18 14:03:18', 'leolagat'),
(12, 'visited database search', '::1', '2026-03-18 14:03:22', 'leolagat');

-- --------------------------------------------------------

--
-- Table structure for table `mpesa_transactions`
--

CREATE TABLE `mpesa_transactions` (
  `id` int(11) NOT NULL,
  `plate_number` varchar(20) NOT NULL,
  `phone_number` varchar(15) NOT NULL DEFAULT '',
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` varchar(20) NOT NULL DEFAULT 'Completed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `log_id` int(11) NOT NULL,
  `checkout_id` varchar(255) DEFAULT NULL,
  `receipt_number` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mpesa_transactions`
--

INSERT INTO `mpesa_transactions` (`id`, `plate_number`, `phone_number`, `amount`, `status`, `created_at`, `log_id`, `checkout_id`, `receipt_number`) VALUES
(1, 'KDT657A', '254727516126', 50.00, 'Completed', '2026-03-11 19:37:47', 1, 'ws_CO_11032026223739340727516126', 'UCB6L90BP7'),
(2, 'KGT457A', '254727516126', 70.00, 'Completed', '2026-03-11 21:08:12', 5, 'ws_CO_12032026000803085727516126', 'UCC6L90K44'),
(3, 'KYT349T', '254727516126', 250.00, 'Completed', '2026-03-12 07:44:01', 6, 'ws_CO_12032026104353208727516126', 'UCC6L9155H'),
(4, 'KFG456D', '254700000000', 1000.00, 'Completed', '2026-03-12 13:38:36', 7, 'SIM-1773322716', 'SIM-1773322716'),
(5, 'KHH123T', '254700000000', 1000.00, 'Completed', '2026-03-15 11:30:00', 9, 'SIM-1773574200', 'SIM-1773574200'),
(6, 'KDE125T', '254727516126', 90.00, 'Completed', '2026-03-15 21:23:33', 10, 'ws_CO_16032026002325201727516126', 'UCG6L9EGC8'),
(7, 'KBA345G', '254727516126', 1000.00, 'Completed', '2026-03-16 17:51:22', 12, 'ws_CO_16032026205112196727516126', 'UCG6L9HMZB'),
(8, 'KMB99R', '254700000000', 50.00, 'Completed', '2026-03-17 19:55:04', 17, 'SIM-1773777304', 'SIM-1773777304'),
(9, 'KMM444M', '254727516126', 1000.00, 'Pending', '2026-03-18 10:52:30', 22, 'ws_CO_18032026135232093727516126', NULL),
(10, 'KMM444M', '254700000000', 1000.00, 'Completed', '2026-03-18 10:52:50', 22, 'SIM-1773831170', 'SIM-1773831170');

-- --------------------------------------------------------

--
-- Table structure for table `owner_accounts`
--

CREATE TABLE `owner_accounts` (
  `id` int(11) NOT NULL,
  `plate_number` varchar(20) NOT NULL,
  `owner_name` varchar(100) DEFAULT NULL,
  `invoice_monthly` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `owner_accounts`
--

INSERT INTO `owner_accounts` (`id`, `plate_number`, `owner_name`, `invoice_monthly`, `created_at`, `deleted_at`) VALUES
(1, 'KCW546H', 'NAIVAS ', 1, '2026-03-11 22:33:39', NULL),
(2, 'KJU685', 'ARTCAFEE', 1, '2026-03-11 22:33:51', NULL),
(3, 'KMB999R', 'DR.MATRESS', 1, '2026-03-11 22:34:14', NULL),
(4, 'KHU456', 'GALITOS RESTAURANT ', 1, '2026-03-12 17:10:01', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `owner_vehicle_fees`
--

CREATE TABLE `owner_vehicle_fees` (
  `id` int(11) NOT NULL,
  `plate_number` varchar(20) NOT NULL,
  `owner_name` varchar(100) DEFAULT NULL,
  `nominal_fee` decimal(10,2) DEFAULT 0.00,
  `discount_given` decimal(10,2) DEFAULT 0.00,
  `total_due` decimal(10,2) DEFAULT 0.00,
  `due_period` date NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `owner_vehicle_fees`
--

INSERT INTO `owner_vehicle_fees` (`id`, `plate_number`, `owner_name`, `nominal_fee`, `discount_given`, `total_due`, `due_period`, `created_at`) VALUES
(1, 'KCW546H', 'NAIVAS ', 280.00, 84.00, 196.00, '2026-04-12', '2026-03-11 23:02:43'),
(2, 'KJU685', 'ARTCAFEE', 0.00, 0.00, 0.00, '2026-04-11', '2026-03-11 23:02:43'),
(3, 'KMB999R', 'DR.MATRESS', 0.00, 0.00, 0.00, '2026-04-11', '2026-03-11 23:02:43'),
(4, 'KHU456', 'GALITOS RESTAURANT ', 1000.00, 300.00, 700.00, '2026-04-18', '2026-03-18 12:29:28');

-- --------------------------------------------------------

--
-- Table structure for table `parking_bays`
--

CREATE TABLE `parking_bays` (
  `id` int(11) NOT NULL,
  `bay_number` varchar(50) NOT NULL,
  `floor_level` varchar(50) NOT NULL,
  `current_status` enum('vacant','occupied') DEFAULT 'vacant'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parking_bays`
--

INSERT INTO `parking_bays` (`id`, `bay_number`, `floor_level`, `current_status`) VALUES
(1, 'B1-001', 'Basement 1', 'vacant'),
(2, 'B1-002', 'Basement 1', 'vacant'),
(3, 'B1-003', 'Basement 1', 'vacant'),
(4, 'B1-004', 'Basement 1', 'vacant'),
(5, 'B1-005', 'Basement 1', 'vacant'),
(6, 'B1-006', 'Basement 1', 'vacant'),
(7, 'B1-007', 'Basement 1', 'vacant'),
(8, 'B1-008', 'Basement 1', 'vacant'),
(9, 'B1-009', 'Basement 1', 'vacant'),
(10, 'B1-010', 'Basement 1', 'vacant'),
(11, 'B1-011', 'Basement 1', 'vacant'),
(12, 'B1-012', 'Basement 1', 'vacant'),
(13, 'B1-013', 'Basement 1', 'vacant'),
(14, 'B1-014', 'Basement 1', 'vacant'),
(15, 'B1-015', 'Basement 1', 'vacant'),
(16, 'B1-016', 'Basement 1', 'vacant'),
(17, 'B1-017', 'Basement 1', 'vacant'),
(18, 'B1-018', 'Basement 1', 'vacant'),
(19, 'B1-019', 'Basement 1', 'vacant'),
(20, 'B1-020', 'Basement 1', 'vacant'),
(21, 'B1-021', 'Basement 1', 'vacant'),
(22, 'B1-022', 'Basement 1', 'vacant'),
(23, 'B1-023', 'Basement 1', 'vacant'),
(24, 'B1-024', 'Basement 1', 'vacant'),
(25, 'B1-025', 'Basement 1', 'vacant'),
(26, 'B1-026', 'Basement 1', 'vacant'),
(27, 'B1-027', 'Basement 1', 'vacant'),
(28, 'B1-028', 'Basement 1', 'vacant'),
(29, 'B1-029', 'Basement 1', 'vacant'),
(30, 'B1-030', 'Basement 1', 'vacant'),
(31, 'B1-031', 'Basement 1', 'vacant'),
(32, 'B1-032', 'Basement 1', 'vacant'),
(33, 'B1-033', 'Basement 1', 'vacant'),
(34, 'B1-034', 'Basement 1', 'vacant'),
(35, 'B1-035', 'Basement 1', 'vacant'),
(36, 'B1-036', 'Basement 1', 'vacant'),
(37, 'B1-037', 'Basement 1', 'vacant'),
(38, 'B1-038', 'Basement 1', 'vacant'),
(39, 'B1-039', 'Basement 1', 'vacant'),
(40, 'B1-040', 'Basement 1', 'vacant'),
(41, 'B1-041', 'Basement 1', 'vacant'),
(42, 'B1-042', 'Basement 1', 'vacant'),
(43, 'B1-043', 'Basement 1', 'vacant'),
(44, 'B1-044', 'Basement 1', 'vacant'),
(45, 'B1-045', 'Basement 1', 'vacant'),
(46, 'B1-046', 'Basement 1', 'vacant'),
(47, 'B1-047', 'Basement 1', 'vacant'),
(48, 'B1-048', 'Basement 1', 'vacant'),
(49, 'B1-049', 'Basement 1', 'vacant'),
(50, 'B1-050', 'Basement 1', 'vacant'),
(51, 'B1-051', 'Basement 1', 'vacant'),
(52, 'B1-052', 'Basement 1', 'vacant'),
(53, 'B1-053', 'Basement 1', 'vacant'),
(54, 'B1-054', 'Basement 1', 'vacant'),
(55, 'B1-055', 'Basement 1', 'vacant'),
(56, 'B1-056', 'Basement 1', 'vacant'),
(57, 'B1-057', 'Basement 1', 'vacant'),
(58, 'B1-058', 'Basement 1', 'vacant'),
(59, 'B1-059', 'Basement 1', 'vacant'),
(60, 'B1-060', 'Basement 1', 'vacant'),
(61, 'B1-061', 'Basement 1', 'vacant'),
(62, 'B1-062', 'Basement 1', 'vacant'),
(63, 'B1-063', 'Basement 1', 'vacant'),
(64, 'B1-064', 'Basement 1', 'vacant'),
(65, 'B1-065', 'Basement 1', 'vacant'),
(66, 'B1-066', 'Basement 1', 'vacant'),
(67, 'B1-067', 'Basement 1', 'vacant'),
(68, 'B1-068', 'Basement 1', 'vacant'),
(69, 'B1-069', 'Basement 1', 'vacant'),
(70, 'B1-070', 'Basement 1', 'vacant'),
(71, 'B1-071', 'Basement 1', 'vacant'),
(72, 'B1-072', 'Basement 1', 'vacant'),
(73, 'B1-073', 'Basement 1', 'vacant'),
(74, 'B1-074', 'Basement 1', 'vacant'),
(75, 'B1-075', 'Basement 1', 'vacant'),
(76, 'B1-076', 'Basement 1', 'vacant'),
(77, 'B1-077', 'Basement 1', 'vacant'),
(78, 'B1-078', 'Basement 1', 'vacant'),
(79, 'B1-079', 'Basement 1', 'vacant'),
(80, 'B1-080', 'Basement 1', 'vacant'),
(81, 'B1-081', 'Basement 1', 'vacant'),
(82, 'B1-082', 'Basement 1', 'vacant'),
(83, 'B1-083', 'Basement 1', 'vacant'),
(84, 'B1-084', 'Basement 1', 'vacant'),
(85, 'B1-085', 'Basement 1', 'vacant'),
(86, 'B1-086', 'Basement 1', 'vacant'),
(87, 'B1-087', 'Basement 1', 'vacant'),
(88, 'B1-088', 'Basement 1', 'vacant'),
(89, 'B1-089', 'Basement 1', 'vacant'),
(90, 'B1-090', 'Basement 1', 'vacant'),
(91, 'B1-091', 'Basement 1', 'vacant'),
(92, 'B1-092', 'Basement 1', 'vacant'),
(93, 'B1-093', 'Basement 1', 'vacant'),
(94, 'B1-094', 'Basement 1', 'vacant'),
(95, 'B1-095', 'Basement 1', 'vacant'),
(96, 'B1-096', 'Basement 1', 'vacant'),
(97, 'B1-097', 'Basement 1', 'vacant'),
(98, 'B1-098', 'Basement 1', 'vacant'),
(99, 'B1-099', 'Basement 1', 'vacant'),
(100, 'B1-100', 'Basement 1', 'vacant'),
(101, 'B1-101', 'Basement 1', 'vacant'),
(102, 'B1-102', 'Basement 1', 'vacant'),
(103, 'B1-103', 'Basement 1', 'vacant'),
(104, 'B1-104', 'Basement 1', 'vacant'),
(105, 'B1-105', 'Basement 1', 'vacant'),
(106, 'B1-106', 'Basement 1', 'vacant'),
(107, 'B1-107', 'Basement 1', 'vacant'),
(108, 'B1-108', 'Basement 1', 'vacant'),
(109, 'B1-109', 'Basement 1', 'vacant'),
(110, 'B1-110', 'Basement 1', 'vacant'),
(111, 'B1-111', 'Basement 1', 'vacant'),
(112, 'B1-112', 'Basement 1', 'vacant'),
(113, 'B1-113', 'Basement 1', 'vacant'),
(114, 'B1-114', 'Basement 1', 'vacant'),
(115, 'B1-115', 'Basement 1', 'vacant'),
(116, 'B1-116', 'Basement 1', 'vacant'),
(117, 'B1-117', 'Basement 1', 'vacant'),
(118, 'B1-118', 'Basement 1', 'vacant'),
(119, 'B1-119', 'Basement 1', 'vacant'),
(120, 'B1-120', 'Basement 1', 'vacant'),
(121, 'B1-121', 'Basement 1', 'vacant'),
(122, 'B1-122', 'Basement 1', 'vacant'),
(123, 'B1-123', 'Basement 1', 'vacant'),
(124, 'B1-124', 'Basement 1', 'vacant'),
(125, 'B1-125', 'Basement 1', 'vacant'),
(126, 'B1-126', 'Basement 1', 'vacant'),
(127, 'B1-127', 'Basement 1', 'vacant'),
(128, 'B1-128', 'Basement 1', 'vacant'),
(129, 'B1-129', 'Basement 1', 'vacant'),
(130, 'B1-130', 'Basement 1', 'vacant'),
(131, 'B1-131', 'Basement 1', 'vacant'),
(132, 'B2-001', 'Basement 2', 'vacant'),
(133, 'B2-002', 'Basement 2', 'vacant'),
(134, 'B2-003', 'Basement 2', 'vacant'),
(135, 'B2-004', 'Basement 2', 'vacant'),
(136, 'B2-005', 'Basement 2', 'vacant'),
(137, 'B2-006', 'Basement 2', 'vacant'),
(138, 'B2-007', 'Basement 2', 'vacant'),
(139, 'B2-008', 'Basement 2', 'vacant'),
(140, 'B2-009', 'Basement 2', 'vacant'),
(141, 'B2-010', 'Basement 2', 'vacant'),
(142, 'B2-011', 'Basement 2', 'vacant'),
(143, 'B2-012', 'Basement 2', 'vacant'),
(144, 'B2-013', 'Basement 2', 'vacant'),
(145, 'B2-014', 'Basement 2', 'vacant'),
(146, 'B2-015', 'Basement 2', 'vacant'),
(147, 'B2-016', 'Basement 2', 'vacant'),
(148, 'B2-017', 'Basement 2', 'vacant'),
(149, 'B2-018', 'Basement 2', 'vacant'),
(150, 'B2-019', 'Basement 2', 'vacant'),
(151, 'B2-020', 'Basement 2', 'vacant'),
(152, 'B2-021', 'Basement 2', 'vacant'),
(153, 'B2-022', 'Basement 2', 'vacant'),
(154, 'B2-023', 'Basement 2', 'vacant'),
(155, 'B2-024', 'Basement 2', 'vacant'),
(156, 'B2-025', 'Basement 2', 'vacant'),
(157, 'B2-026', 'Basement 2', 'vacant'),
(158, 'B2-027', 'Basement 2', 'vacant'),
(159, 'B2-028', 'Basement 2', 'vacant'),
(160, 'B2-029', 'Basement 2', 'vacant'),
(161, 'B2-030', 'Basement 2', 'vacant'),
(162, 'B2-031', 'Basement 2', 'vacant'),
(163, 'B2-032', 'Basement 2', 'vacant'),
(164, 'B2-033', 'Basement 2', 'vacant'),
(165, 'B2-034', 'Basement 2', 'vacant'),
(166, 'B2-035', 'Basement 2', 'vacant'),
(167, 'B2-036', 'Basement 2', 'vacant'),
(168, 'B2-037', 'Basement 2', 'vacant'),
(169, 'B2-038', 'Basement 2', 'vacant'),
(170, 'B2-039', 'Basement 2', 'vacant'),
(171, 'B2-040', 'Basement 2', 'vacant'),
(172, 'B2-041', 'Basement 2', 'vacant'),
(173, 'B2-042', 'Basement 2', 'vacant'),
(174, 'B2-043', 'Basement 2', 'vacant'),
(175, 'B2-044', 'Basement 2', 'vacant'),
(176, 'B2-045', 'Basement 2', 'vacant'),
(177, 'B2-046', 'Basement 2', 'vacant'),
(178, 'B2-047', 'Basement 2', 'vacant'),
(179, 'B2-048', 'Basement 2', 'vacant'),
(180, 'B2-049', 'Basement 2', 'vacant'),
(181, 'B2-050', 'Basement 2', 'vacant'),
(182, 'B2-051', 'Basement 2', 'vacant'),
(183, 'B2-052', 'Basement 2', 'vacant'),
(184, 'B2-053', 'Basement 2', 'vacant'),
(185, 'B2-054', 'Basement 2', 'vacant'),
(186, 'B2-055', 'Basement 2', 'vacant'),
(187, 'B2-056', 'Basement 2', 'vacant'),
(188, 'B2-057', 'Basement 2', 'vacant'),
(189, 'B2-058', 'Basement 2', 'vacant'),
(190, 'B2-059', 'Basement 2', 'vacant'),
(191, 'B2-060', 'Basement 2', 'vacant'),
(192, 'B2-061', 'Basement 2', 'vacant'),
(193, 'B2-062', 'Basement 2', 'vacant'),
(194, 'B2-063', 'Basement 2', 'vacant'),
(195, 'B2-064', 'Basement 2', 'vacant'),
(196, 'B2-065', 'Basement 2', 'vacant'),
(197, 'B2-066', 'Basement 2', 'vacant'),
(198, 'B2-067', 'Basement 2', 'vacant'),
(199, 'B2-068', 'Basement 2', 'vacant'),
(200, 'B2-069', 'Basement 2', 'vacant'),
(201, 'B2-070', 'Basement 2', 'vacant'),
(202, 'B2-071', 'Basement 2', 'vacant'),
(203, 'B2-072', 'Basement 2', 'vacant'),
(204, 'B2-073', 'Basement 2', 'vacant'),
(205, 'B2-074', 'Basement 2', 'vacant'),
(206, 'B2-075', 'Basement 2', 'vacant'),
(207, 'B2-076', 'Basement 2', 'vacant'),
(208, 'B2-077', 'Basement 2', 'vacant'),
(209, 'B2-078', 'Basement 2', 'vacant'),
(210, 'B2-079', 'Basement 2', 'vacant'),
(211, 'B2-080', 'Basement 2', 'vacant'),
(212, 'B2-081', 'Basement 2', 'vacant'),
(213, 'B2-082', 'Basement 2', 'vacant'),
(214, 'B2-083', 'Basement 2', 'vacant'),
(215, 'B2-084', 'Basement 2', 'vacant'),
(216, 'B2-085', 'Basement 2', 'vacant'),
(217, 'B2-086', 'Basement 2', 'vacant'),
(218, 'B2-087', 'Basement 2', 'vacant'),
(219, 'B2-088', 'Basement 2', 'vacant'),
(220, 'B2-089', 'Basement 2', 'vacant'),
(221, 'B2-090', 'Basement 2', 'vacant'),
(222, 'B2-091', 'Basement 2', 'vacant'),
(223, 'B2-092', 'Basement 2', 'vacant'),
(224, 'B2-093', 'Basement 2', 'vacant'),
(225, 'B2-094', 'Basement 2', 'vacant'),
(226, 'B2-095', 'Basement 2', 'vacant'),
(227, 'B2-096', 'Basement 2', 'vacant'),
(228, 'B2-097', 'Basement 2', 'vacant'),
(229, 'B2-098', 'Basement 2', 'vacant'),
(230, 'B2-099', 'Basement 2', 'vacant'),
(231, 'B2-100', 'Basement 2', 'vacant'),
(232, 'B2-101', 'Basement 2', 'vacant'),
(233, 'B2-102', 'Basement 2', 'vacant'),
(234, 'B2-103', 'Basement 2', 'vacant'),
(235, 'B2-104', 'Basement 2', 'vacant'),
(236, 'B2-105', 'Basement 2', 'vacant'),
(237, 'B2-106', 'Basement 2', 'vacant'),
(238, 'B2-107', 'Basement 2', 'vacant'),
(239, 'B2-108', 'Basement 2', 'vacant'),
(240, 'B2-109', 'Basement 2', 'vacant'),
(241, 'B2-110', 'Basement 2', 'vacant'),
(242, 'B2-111', 'Basement 2', 'vacant'),
(243, 'B2-112', 'Basement 2', 'vacant'),
(244, 'B2-113', 'Basement 2', 'vacant'),
(245, 'B2-114', 'Basement 2', 'vacant'),
(246, 'B2-115', 'Basement 2', 'vacant'),
(247, 'B2-116', 'Basement 2', 'vacant'),
(248, 'B2-117', 'Basement 2', 'vacant'),
(249, 'B2-118', 'Basement 2', 'vacant'),
(250, 'B2-119', 'Basement 2', 'vacant'),
(251, 'B2-120', 'Basement 2', 'vacant'),
(252, 'B2-121', 'Basement 2', 'vacant'),
(253, 'B2-122', 'Basement 2', 'vacant'),
(254, 'B2-123', 'Basement 2', 'vacant'),
(255, 'B2-124', 'Basement 2', 'vacant'),
(256, 'B2-125', 'Basement 2', 'vacant'),
(257, 'B2-126', 'Basement 2', 'vacant'),
(258, 'B2-127', 'Basement 2', 'vacant'),
(259, 'B2-128', 'Basement 2', 'vacant'),
(260, 'B2-129', 'Basement 2', 'vacant'),
(261, 'B2-130', 'Basement 2', 'vacant'),
(262, 'B2-131', 'Basement 2', 'vacant');

-- --------------------------------------------------------

--
-- Table structure for table `restricted_vehicles`
--

CREATE TABLE `restricted_vehicles` (
  `id` int(11) NOT NULL,
  `plate_number` varchar(20) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `added_at` datetime DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `restricted_vehicles`
--

INSERT INTO `restricted_vehicles` (`id`, `plate_number`, `reason`, `added_at`, `deleted_at`) VALUES
(1, 'KBV890E', 'PARKING_VIOLATION', '2026-03-11 22:31:13', NULL),
(2, 'KGG123G', 'THIEF', '2026-03-11 22:36:25', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `revenue_archive`
--

CREATE TABLE `revenue_archive` (
  `id` int(11) NOT NULL,
  `archived_revenue` decimal(15,2) NOT NULL DEFAULT 0.00,
  `archived_date` datetime DEFAULT current_timestamp(),
  `admin_who_cleared` varchar(100) DEFAULT NULL,
  `log_count_cleared` int(11) DEFAULT 0,
  `notes` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_vehicles`
--

CREATE TABLE `staff_vehicles` (
  `id` int(11) NOT NULL,
  `plate_number` varchar(20) NOT NULL,
  `employee_name` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_vehicles`
--

INSERT INTO `staff_vehicles` (`id`, `plate_number`, `employee_name`, `created_at`, `deleted_at`) VALUES
(1, 'KTT67Q', 'EMPLOYEE1', '2026-03-11 21:57:42', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_logs`
--

CREATE TABLE `vehicle_logs` (
  `id` int(11) NOT NULL,
  `plate_number` varchar(20) NOT NULL,
  `bay_id` int(11) NOT NULL,
  `entry_time` datetime NOT NULL,
  `exit_time` datetime DEFAULT NULL,
  `total_fee` decimal(10,2) DEFAULT 0.00,
  `payment_status` enum('pending','paid') DEFAULT 'pending',
  `mpesa_checkout_id` varchar(255) DEFAULT NULL,
  `nominal_fee` decimal(10,2) DEFAULT 0.00,
  `paid_at` datetime DEFAULT NULL,
  `is_manual_bypass` tinyint(1) NOT NULL DEFAULT 0,
  `bypassed_by` varchar(100) DEFAULT NULL,
  `bypassed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicle_logs`
--

INSERT INTO `vehicle_logs` (`id`, `plate_number`, `bay_id`, `entry_time`, `exit_time`, `total_fee`, `payment_status`, `mpesa_checkout_id`, `nominal_fee`, `paid_at`, `is_manual_bypass`, `bypassed_by`, `bypassed_at`) VALUES
(1, 'KDT657A', 1, '2026-03-11 21:55:15', '2026-03-11 22:37:47', 50.00, 'paid', 'ws_CO_11032026223739340727516126', 0.00, NULL, 0, NULL, NULL),
(2, 'KCW546H', 2, '2026-03-11 22:34:29', '2026-03-11 23:17:40', 35.00, '', NULL, 50.00, NULL, 0, NULL, NULL),
(3, 'KMB999R', 3, '2026-03-11 22:35:05', '2026-03-11 23:16:03', 35.00, 'paid', NULL, 50.00, '2026-03-18 13:30:20', 0, NULL, NULL),
(4, 'KJU685', 4, '2026-03-11 22:35:37', '2026-03-11 23:17:12', 35.00, 'paid', NULL, 50.00, NULL, 0, NULL, NULL),
(5, 'KGT457A', 1, '2026-03-11 22:39:37', '2026-03-12 00:08:12', 70.00, 'paid', 'ws_CO_12032026000803085727516126', 0.00, NULL, 0, NULL, NULL),
(6, 'KYT349T', 1, '2026-03-12 00:08:33', '2026-03-12 10:44:01', 250.00, 'paid', 'ws_CO_12032026104353208727516126', 0.00, NULL, 0, NULL, NULL),
(7, 'KFG456D', 2, '2026-03-12 00:55:43', '2026-03-12 16:38:36', 1000.00, 'paid', 'ws_CO_12032026163817040727516126', 0.00, NULL, 0, NULL, NULL),
(8, 'KCW546H', 3, '2026-03-12 00:56:22', '2026-03-12 10:42:15', 161.00, '', NULL, 230.00, NULL, 0, NULL, NULL),
(9, 'KHH123T', 1, '2026-03-14 22:45:32', '2026-03-15 14:30:00', 1000.00, 'paid', NULL, 0.00, NULL, 0, NULL, NULL),
(10, 'KDE125T', 1, '2026-03-15 21:58:48', '2026-03-16 00:23:33', 90.00, 'paid', 'ws_CO_16032026002325201727516126', 0.00, NULL, 0, NULL, NULL),
(11, 'KDX 555H', 1, '2026-03-16 07:43:18', '2026-03-16 07:44:18', 0.00, 'paid', NULL, 0.00, NULL, 0, NULL, NULL),
(12, 'KBA345G', 1, '2026-03-16 08:16:26', '2026-03-16 20:51:22', 1000.00, 'paid', 'ws_CO_16032026205112196727516126', 0.00, NULL, 0, NULL, NULL),
(13, 'KDT657A', 1, '2026-03-17 22:14:27', '2026-03-17 22:14:50', 0.00, 'paid', NULL, 0.00, NULL, 0, NULL, NULL),
(14, 'KJU685', 1, '2026-03-17 22:16:08', '2026-03-17 22:46:41', 0.00, '', NULL, 0.00, NULL, 0, NULL, NULL),
(15, 'KCW546H', 2, '2026-03-17 22:16:50', '2026-03-17 22:46:58', 0.00, 'paid', NULL, 0.00, NULL, 0, NULL, NULL),
(16, 'KHU456', 3, '2026-03-17 22:17:09', '2026-03-17 22:48:04', 0.00, '', NULL, 0.00, NULL, 0, NULL, NULL),
(17, 'KMB99R', 4, '2026-03-17 22:17:23', '2026-03-17 22:55:04', 50.00, 'paid', 'ws_CO_17032026225443962727516126', 0.00, NULL, 0, NULL, NULL),
(18, 'KMB999R', 5, '2026-03-17 22:17:42', '2026-03-17 22:50:17', 0.00, 'paid', NULL, 0.00, NULL, 0, NULL, NULL),
(19, 'KGG456Y', 6, '2026-03-17 22:44:06', '2026-03-17 22:45:15', 0.00, 'paid', NULL, 0.00, NULL, 0, NULL, NULL),
(20, 'KGT356Y', 1, '2026-03-17 22:55:49', '2026-03-17 22:56:05', 0.00, 'paid', NULL, 0.00, NULL, 0, NULL, NULL),
(21, 'KHU456', 1, '2026-03-17 23:04:01', '2026-03-18 12:04:19', 700.00, 'paid', NULL, 1000.00, '2026-03-18 12:04:19', 0, NULL, NULL),
(22, 'KMM444M', 2, '2026-03-17 23:08:50', '2026-03-18 13:52:50', 1000.00, 'paid', 'ws_CO_18032026135232093727516126', 0.00, NULL, 0, NULL, NULL),
(23, 'KLH559Y', 1, '2026-03-18 14:02:30', '2026-03-18 14:02:42', 0.00, 'paid', NULL, 0.00, NULL, 1, 'leolagat', '2026-03-18 14:02:52');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `administrators`
--
ALTER TABLE `administrators`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `admin_activity`
--
ALTER TABLE `admin_activity`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mpesa_transactions`
--
ALTER TABLE `mpesa_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_checkout` (`checkout_id`),
  ADD KEY `idx_plate` (`plate_number`),
  ADD KEY `fk_log` (`log_id`);

--
-- Indexes for table `owner_accounts`
--
ALTER TABLE `owner_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `plate_number` (`plate_number`);

--
-- Indexes for table `owner_vehicle_fees`
--
ALTER TABLE `owner_vehicle_fees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_owner_fee_plate` (`plate_number`);

--
-- Indexes for table `parking_bays`
--
ALTER TABLE `parking_bays`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `restricted_vehicles`
--
ALTER TABLE `restricted_vehicles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `plate_number` (`plate_number`);

--
-- Indexes for table `revenue_archive`
--
ALTER TABLE `revenue_archive`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `staff_vehicles`
--
ALTER TABLE `staff_vehicles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `plate_number` (`plate_number`);

--
-- Indexes for table `vehicle_logs`
--
ALTER TABLE `vehicle_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bay_id` (`bay_id`),
  ADD KEY `idx_plate_number` (`plate_number`),
  ADD KEY `idx_plate_payment` (`plate_number`,`payment_status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `administrators`
--
ALTER TABLE `administrators`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `admin_activity`
--
ALTER TABLE `admin_activity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `mpesa_transactions`
--
ALTER TABLE `mpesa_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `owner_accounts`
--
ALTER TABLE `owner_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `owner_vehicle_fees`
--
ALTER TABLE `owner_vehicle_fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `parking_bays`
--
ALTER TABLE `parking_bays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=263;

--
-- AUTO_INCREMENT for table `restricted_vehicles`
--
ALTER TABLE `restricted_vehicles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `revenue_archive`
--
ALTER TABLE `revenue_archive`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_vehicles`
--
ALTER TABLE `staff_vehicles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `vehicle_logs`
--
ALTER TABLE `vehicle_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `mpesa_transactions`
--
ALTER TABLE `mpesa_transactions`
  ADD CONSTRAINT `fk_log` FOREIGN KEY (`log_id`) REFERENCES `vehicle_logs` (`id`);

--
-- Constraints for table `owner_vehicle_fees`
--
ALTER TABLE `owner_vehicle_fees`
  ADD CONSTRAINT `owner_vehicle_fees_ibfk_1` FOREIGN KEY (`plate_number`) REFERENCES `owner_accounts` (`plate_number`);

--
-- Constraints for table `vehicle_logs`
--
ALTER TABLE `vehicle_logs`
  ADD CONSTRAINT `vehicle_logs_ibfk_1` FOREIGN KEY (`bay_id`) REFERENCES `parking_bays` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
