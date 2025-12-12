-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 11, 2025 at 01:30 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.4.3

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `trial`
--

-- --------------------------------------------------------

--
-- Table structure for table `checklist_images`
--

CREATE TABLE `checklist_images` (
  `id` int(11) NOT NULL,
  `checklist_id` int(11) NOT NULL,
  `image_path` varchar(500) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `checklist_images`
--

INSERT INTO `checklist_images` (`id`, `checklist_id`, `image_path`, `uploaded_at`) VALUES
(6, 57, '20251210_144755_ed0a1473_Site-clearing-and-layout.jpg', '2025-12-10 14:47:55'),
(7, 61, '20251210_144845_58fd0074_Site-clearing-and-layout.jpg', '2025-12-10 14:48:45'),
(8, 65, '20251210_145004_39b5e412_Foundation.jpg', '2025-12-10 14:50:04'),
(9, 69, '20251210_145726_438afe8b_Structural-frame.jpg', '2025-12-10 14:57:26'),
(10, 73, '20251210_145741_38f8042a_Walling-and-partitions.jpg', '2025-12-10 14:57:41'),
(11, 77, '20251210_145750_cfed591a_Roofing.jpg', '2025-12-10 14:57:50'),
(12, 81, '20251210_145817_0235f36b_Rough-ins-electrical-plumbing-drainage.jpg', '2025-12-10 14:58:17'),
(13, 85, '20251210_145835_e99a8c39_Ceiling-framing-and-ceiling-installation.jpg', '2025-12-10 14:58:35'),
(14, 89, '20251210_145849_077bed64_Wall-finishing-plastering-drywall.jpg', '2025-12-10 14:58:49'),
(15, 93, '20251210_145858_1296a7a6_flooring.jpg', '2025-12-10 14:58:58'),
(16, 97, '20251210_145907_6b1af506_Doors-and-windows.jpg', '2025-12-10 14:59:07'),
(17, 101, '20251210_145920_2fd3d0eb_Painting.jpg', '2025-12-10 14:59:20'),
(18, 113, '20251210_145934_1f673952_Testing-and-punchlist.png', '2025-12-10 14:59:34'),
(19, 117, '20251210_145945_6c20c0b9_Final-cleaning-and-turnover.jpg', '2025-12-10 14:59:45');

-- --------------------------------------------------------

--
-- Table structure for table `deleted_projects`
--

CREATE TABLE `deleted_projects` (
  `id` int(11) NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `constructor_id` int(11) NOT NULL,
  `status` enum('Ongoing','Completed','Pending') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `folder_path` varchar(255) DEFAULT NULL,
  `deleted_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `deleted_projects`
--

INSERT INTO `deleted_projects` (`id`, `name`, `location`, `constructor_id`, `status`, `created_at`, `folder_path`, `deleted_at`) VALUES
(8, 'East Homes 1', 'Estefania', 4, 'Pending', '2025-10-23 16:00:00', 'uploads/projects/8_east_homes_1', '2025-10-24 14:51:10'),
(8, 'East Homes 1', 'Estefania', 4, 'Pending', '2025-10-23 16:00:00', 'uploads/projects/8_east_homes_1', '2025-10-24 14:52:36'),
(8, 'East Homes 1', 'Estefania', 4, 'Pending', '2025-10-23 16:00:00', 'uploads/projects/8_east_homes_1', '2025-10-24 14:52:37'),
(8, 'East Homes 1', 'Estefania', 4, 'Pending', '2025-10-23 16:00:00', 'uploads/projects/8_east_homes_1', '2025-10-24 14:52:43'),
(8, 'East Homes 1', 'Estefania', 4, 'Pending', '2025-10-23 16:00:00', 'uploads/projects/8_east_homes_1', '2025-10-24 18:18:20'),
(8, 'East Homes 1', 'Estefania', 4, 'Pending', '2025-10-23 16:00:00', 'uploads/projects/8_east_homes_1', '2025-10-24 18:18:23'),
(8, 'East Homes 1', 'Estefania', 4, 'Pending', '2025-10-23 16:00:00', 'uploads/projects/8_east_homes_1', '2025-10-24 18:18:24'),
(8, 'East Homes 1', 'Estefania', 4, 'Pending', '2025-10-23 16:00:00', 'uploads/projects/8_east_homes_1', '2025-10-24 18:18:29');

-- --------------------------------------------------------

--
-- Table structure for table `deleted_project_units`
--

CREATE TABLE `deleted_project_units` (
  `id` int(11) NOT NULL DEFAULT 0,
  `project_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `materials`
--

CREATE TABLE `materials` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `total_quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `remaining_quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `unit_of_measurement` varchar(50) NOT NULL,
  `purpose` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `materials`
--

INSERT INTO `materials` (`id`, `project_id`, `name`, `supplier`, `total_quantity`, `remaining_quantity`, `unit_of_measurement`, `purpose`, `created_at`) VALUES
(6, 10, 'Cement', 'Rj4 Construction Supplies', 54.00, 54.00, 'sacks', '', '2025-10-31 06:53:09'),
(13, 11, 'Cement', '', 40.00, 38.00, 'kg', '', '2025-12-10 14:26:23'),
(14, 11, 'Sand', '', 35.00, 35.00, 'm³', '', '2025-12-10 14:26:46'),
(15, 11, 'Gravel', '', 30.00, 30.00, 'm³', '', '2025-12-10 14:27:17'),
(16, 11, 'Rebar (assorted 10mm–12mm)', '', 220.00, 220.00, 'pcs', '', '2025-12-10 14:27:43'),
(17, 11, 'Tie wire', '', 25.00, 25.00, 'kg', '', '2025-12-10 14:27:58'),
(18, 11, 'CHB 6\"', '', 2000.00, 2000.00, 'pcs', '', '2025-12-10 14:28:20'),
(19, 11, 'Coco lumber (assorted for forms)', '', 1200.00, 1200.00, 'ft', '', '2025-12-10 14:28:40'),
(20, 11, 'Plywood 1/2\" (formworks)', '', 25.00, 25.00, 'sheets', '', '2025-12-10 14:29:18'),
(21, 11, 'Metal roofing sheets', '', 70.00, 70.00, 'sheets', '', '2025-12-10 14:29:47'),
(22, 11, 'Roof framing steel (assorted)', '', 450.00, 450.00, 'kg', '', '2025-12-10 14:30:03'),
(23, 11, 'PVC pipes (water + drain assorted 1/2\"–4\")', '', 135.00, 135.00, 'm', '', '2025-12-10 14:30:28'),
(24, 11, 'Electrical wires (assorted 2.0–3.5mm²)', '', 480.00, 480.00, 'm', '', '2025-12-10 14:30:43'),
(25, 11, 'Electrical conduits (PVC 20mm)', '', 220.00, 220.00, 'm', '', '2025-12-10 14:31:10'),
(26, 11, 'Ceiling boards (gypsum/fiber cement)', '', 35.00, 35.00, 'sheets', '', '2025-12-10 14:31:26'),
(27, 11, 'Floor tiles', '', 55.00, 55.00, 'm²', '', '2025-12-10 14:31:42');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `units` int(11) NOT NULL,
  `progress` int(11) DEFAULT 0,
  `status` enum('Ongoing','Completed','Pending') NOT NULL DEFAULT 'Pending',
  `is_deleted` tinyint(1) DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `folder_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `name`, `location`, `units`, `progress`, `status`, `is_deleted`, `deleted_at`, `created_at`, `folder_path`) VALUES
(2, 'Lumina Homes Renovation', 'Mandalagan', 5, 0, 'Pending', 0, NULL, '2025-08-21 01:45:49', NULL),
(7, 'East Homes 5', 'Mansilingan, Bacolod City', 0, 0, 'Pending', 1, '2025-10-29 17:06:58', '2025-10-23 16:00:00', 'uploads/projects/7_east_homes_5'),
(10, 'East Homes 2', 'Estefania, Bacolod City', 5, 100, 'Completed', 0, NULL, '2025-10-28 04:50:00', NULL),
(11, 'East Homes 3', 'Estefania', 4, 25, 'Ongoing', 0, NULL, '2025-10-28 06:50:40', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `project_checklists`
--

CREATE TABLE `project_checklists` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `item_description` varchar(500) DEFAULT NULL,
  `is_completed` tinyint(1) DEFAULT 0,
  `completed_by_user_id` int(11) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `unit_id` int(11) DEFAULT NULL,
  `proof_image_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_checklists`
--

INSERT INTO `project_checklists` (`id`, `project_id`, `item_description`, `is_completed`, `completed_by_user_id`, `completed_at`, `created_at`, `unit_id`, `proof_image_path`) VALUES
(25, 11, 'flooring', 0, NULL, NULL, '2025-10-28 07:24:27', NULL, NULL),
(42, 2, 'foundation', 0, NULL, NULL, '2025-10-29 09:36:25', NULL, NULL),
(43, 2, 'foundation', 0, NULL, NULL, '2025-10-29 09:36:43', 21, NULL),
(47, 10, 'flooring', 1, NULL, '2025-12-04 13:44:53', '2025-12-04 13:44:49', 15, NULL),
(48, 10, 'flooring', 1, NULL, '2025-12-04 13:44:58', '2025-12-04 13:44:49', 16, NULL),
(49, 10, 'flooring', 1, NULL, '2025-12-04 13:45:03', '2025-12-04 13:44:49', 17, NULL),
(50, 10, 'flooring', 1, NULL, '2025-12-04 13:45:07', '2025-12-04 13:44:49', 18, NULL),
(51, 10, 'flooring', 1, NULL, '2025-12-04 13:45:13', '2025-12-04 13:44:49', 19, NULL),
(54, 11, 'Foundation', 0, NULL, NULL, '2025-12-10 14:08:20', 23, NULL),
(55, 11, 'Foundation', 0, NULL, NULL, '2025-12-10 14:08:20', 24, NULL),
(56, 11, 'Foundation', 0, NULL, NULL, '2025-12-10 14:08:20', 25, NULL),
(57, 11, 'Site clearing and layout', 1, NULL, '2025-12-10 14:47:45', '2025-12-10 14:09:00', 22, NULL),
(58, 11, 'Site clearing and layout', 0, NULL, NULL, '2025-12-10 14:09:00', 23, NULL),
(59, 11, 'Site clearing and layout', 0, NULL, NULL, '2025-12-10 14:09:00', 24, NULL),
(60, 11, 'Site clearing and layout', 0, NULL, NULL, '2025-12-10 14:09:00', 25, NULL),
(61, 11, 'Excavation/earthworks', 1, NULL, '2025-12-10 14:48:47', '2025-12-10 14:09:09', 22, NULL),
(62, 11, 'Excavation/earthworks', 0, NULL, NULL, '2025-12-10 14:09:09', 23, NULL),
(63, 11, 'Excavation/earthworks', 0, NULL, NULL, '2025-12-10 14:09:09', 24, NULL),
(64, 11, 'Excavation/earthworks', 0, NULL, NULL, '2025-12-10 14:09:09', 25, NULL),
(65, 11, 'Foundation', 1, NULL, '2025-12-10 14:50:07', '2025-12-10 14:09:18', 22, NULL),
(69, 11, 'Structural frame', 1, NULL, '2025-12-10 14:57:30', '2025-12-10 14:09:30', 22, NULL),
(70, 11, 'Structural frame', 0, NULL, NULL, '2025-12-10 14:09:30', 23, NULL),
(71, 11, 'Structural frame', 0, NULL, NULL, '2025-12-10 14:09:30', 24, NULL),
(72, 11, 'Structural frame', 0, NULL, NULL, '2025-12-10 14:09:30', 25, NULL),
(73, 11, 'Walling and partitions', 1, NULL, '2025-12-10 14:57:43', '2025-12-10 14:09:50', 22, NULL),
(74, 11, 'Walling and partitions', 0, NULL, NULL, '2025-12-10 14:09:50', 23, NULL),
(75, 11, 'Walling and partitions', 0, NULL, NULL, '2025-12-10 14:09:50', 24, NULL),
(76, 11, 'Walling and partitions', 0, NULL, NULL, '2025-12-10 14:09:50', 25, NULL),
(77, 11, 'Roofing', 1, NULL, '2025-12-10 14:57:53', '2025-12-10 14:09:58', 22, NULL),
(78, 11, 'Roofing', 0, NULL, NULL, '2025-12-10 14:09:58', 23, NULL),
(79, 11, 'Roofing', 0, NULL, NULL, '2025-12-10 14:09:58', 24, NULL),
(80, 11, 'Roofing', 0, NULL, NULL, '2025-12-10 14:09:58', 25, NULL),
(81, 11, 'Rough-ins (electrical, plumbing, drainage)', 1, NULL, '2025-12-10 14:58:20', '2025-12-10 14:10:11', 22, NULL),
(82, 11, 'Rough-ins (electrical, plumbing, drainage)', 0, NULL, NULL, '2025-12-10 14:10:11', 23, NULL),
(83, 11, 'Rough-ins (electrical, plumbing, drainage)', 0, NULL, NULL, '2025-12-10 14:10:11', 24, NULL),
(84, 11, 'Rough-ins (electrical, plumbing, drainage)', 0, NULL, NULL, '2025-12-10 14:10:11', 25, NULL),
(85, 11, 'Ceiling framing and ceiling installation', 1, NULL, '2025-12-10 14:58:38', '2025-12-10 14:10:20', 22, NULL),
(86, 11, 'Ceiling framing and ceiling installation', 0, NULL, NULL, '2025-12-10 14:10:20', 23, NULL),
(87, 11, 'Ceiling framing and ceiling installation', 0, NULL, NULL, '2025-12-10 14:10:20', 24, NULL),
(88, 11, 'Ceiling framing and ceiling installation', 0, NULL, NULL, '2025-12-10 14:10:20', 25, NULL),
(89, 11, 'Wall finishing (plastering/drywall)', 1, NULL, '2025-12-10 14:58:51', '2025-12-10 14:10:31', 22, NULL),
(90, 11, 'Wall finishing (plastering/drywall)', 0, NULL, NULL, '2025-12-10 14:10:31', 23, NULL),
(91, 11, 'Wall finishing (plastering/drywall)', 0, NULL, NULL, '2025-12-10 14:10:31', 24, NULL),
(92, 11, 'Wall finishing (plastering/drywall)', 0, NULL, NULL, '2025-12-10 14:10:31', 25, NULL),
(93, 11, 'Flooring', 1, NULL, '2025-12-10 14:59:00', '2025-12-10 14:10:40', 22, NULL),
(94, 11, 'Flooring', 0, NULL, NULL, '2025-12-10 14:10:40', 23, NULL),
(95, 11, 'Flooring', 0, NULL, NULL, '2025-12-10 14:10:40', 24, NULL),
(96, 11, 'Flooring', 0, NULL, NULL, '2025-12-10 14:10:40', 25, NULL),
(97, 11, 'Doors and windows', 1, NULL, '2025-12-10 14:59:09', '2025-12-10 14:10:49', 22, NULL),
(98, 11, 'Doors and windows', 0, NULL, NULL, '2025-12-10 14:10:49', 23, NULL),
(99, 11, 'Doors and windows', 0, NULL, NULL, '2025-12-10 14:10:49', 24, NULL),
(100, 11, 'Doors and windows', 0, NULL, NULL, '2025-12-10 14:10:49', 25, NULL),
(101, 11, 'Painting', 1, NULL, '2025-12-10 14:59:22', '2025-12-10 14:10:58', 22, NULL),
(102, 11, 'Painting', 0, NULL, NULL, '2025-12-10 14:10:58', 23, NULL),
(103, 11, 'Painting', 0, NULL, NULL, '2025-12-10 14:10:58', 24, NULL),
(104, 11, 'Painting', 0, NULL, NULL, '2025-12-10 14:10:58', 25, NULL),
(106, 11, 'Fixtures and final fit-out', 0, NULL, NULL, '2025-12-10 14:11:07', 23, NULL),
(107, 11, 'Fixtures and final fit-out', 0, NULL, NULL, '2025-12-10 14:11:07', 24, NULL),
(108, 11, 'Fixtures and final fit-out', 0, NULL, NULL, '2025-12-10 14:11:07', 25, NULL),
(110, 11, 'Exterior works', 0, NULL, NULL, '2025-12-10 14:11:49', 23, NULL),
(111, 11, 'Exterior works', 0, NULL, NULL, '2025-12-10 14:11:49', 24, NULL),
(112, 11, 'Exterior works', 0, NULL, NULL, '2025-12-10 14:11:49', 25, NULL),
(113, 11, 'Testing and punchlist', 1, NULL, '2025-12-10 14:59:37', '2025-12-10 14:11:59', 22, NULL),
(114, 11, 'Testing and punchlist', 0, NULL, NULL, '2025-12-10 14:11:59', 23, NULL),
(115, 11, 'Testing and punchlist', 0, NULL, NULL, '2025-12-10 14:11:59', 24, NULL),
(116, 11, 'Testing and punchlist', 0, NULL, NULL, '2025-12-10 14:11:59', 25, NULL),
(117, 11, 'Final cleaning and turnover', 1, NULL, '2025-12-10 14:59:48', '2025-12-10 14:12:06', 22, NULL),
(118, 11, 'Final cleaning and turnover', 0, NULL, NULL, '2025-12-10 14:12:06', 23, NULL),
(119, 11, 'Final cleaning and turnover', 0, NULL, NULL, '2025-12-10 14:12:06', 24, NULL),
(120, 11, 'Final cleaning and turnover', 0, NULL, NULL, '2025-12-10 14:12:06', 25, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `project_reports`
--

CREATE TABLE `project_reports` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `report_date` date NOT NULL,
  `progress_percentage` int(11) DEFAULT 0,
  `work_done` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_reports`
--

INSERT INTO `project_reports` (`id`, `project_id`, `unit_id`, `report_date`, `progress_percentage`, `work_done`, `remarks`, `created_by`, `created_at`, `updated_at`) VALUES
(16, 11, 22, '2025-12-10', 100, 'House 1 - Excavation/earthworks', '10 workers working during clearing and ready to go for building foundation.', 'admin', '2025-12-10 15:12:13', '2025-12-10 15:12:13');

-- --------------------------------------------------------

--
-- Table structure for table `project_units`
--

CREATE TABLE `project_units` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `progress` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_units`
--

INSERT INTO `project_units` (`id`, `project_id`, `name`, `description`, `progress`, `created_at`) VALUES
(15, 10, 'uraaa', 'Oysters Street', 100, '2025-10-28 12:50:00'),
(16, 10, 'Unit 2', '', 100, '2025-10-28 12:50:00'),
(17, 10, 'Unit 3', '', 100, '2025-10-28 12:50:00'),
(18, 10, 'Unit 4', '', 100, '2025-10-28 13:01:15'),
(19, 10, 'Unit 5', '', 100, '2025-10-28 13:01:15'),
(21, 2, 'Block 1 Lot 2', 'Oysters Street', 0, '2025-10-28 14:27:52'),
(22, 11, 'House 1', '129sqr mtrs.', 100, '2025-10-28 14:50:40'),
(23, 11, 'House 2', '150 sqr. mtrs.', 0, '2025-10-28 14:50:40'),
(24, 11, 'House 3', '150 sqr. mtrs.', 0, '2025-10-28 14:50:40'),
(25, 11, 'House 4', '150 sqr. mtrs.', 0, '2025-10-28 14:50:40'),
(32, 2, 'Unit 2', '', 0, '2025-12-10 19:56:31'),
(33, 2, 'Unit 3', '', 0, '2025-12-10 19:56:31'),
(34, 2, 'Unit 4', '', 0, '2025-12-10 19:56:31'),
(35, 2, 'Unit 5', '', 0, '2025-12-10 19:56:31');

-- --------------------------------------------------------

--
-- Table structure for table `report_images`
--

CREATE TABLE `report_images` (
  `id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `report_images`
--

INSERT INTO `report_images` (`id`, `report_id`, `image_path`, `caption`, `uploaded_at`) VALUES
(10, 16, 'report_16_20251210_144845_58fd0074_Site-clearing-and-layout.jpg', NULL, '2025-12-10 15:12:13');

-- --------------------------------------------------------

--
-- Table structure for table `report_material_usage`
--

CREATE TABLE `report_material_usage` (
  `id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `quantity_used` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `report_material_usage`
--

INSERT INTO `report_material_usage` (`id`, `report_id`, `material_id`, `quantity_used`, `created_at`) VALUES
(17, 16, 13, 2, '2025-12-10 15:12:13');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `display_name` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('super_admin','admin','constructor') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','disabled') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `display_name`, `password`, `role`, `created_at`, `updated_at`, `status`) VALUES
(1, 'Rowena Tupas', 'Rowena Tupas', '$2y$12$vKqUfJk1KS5RqMyjwk5jbOfJoqAb2va7rKswYr/OdXLmhWnSc9cgO', 'super_admin', '2025-11-03 14:51:49', '2025-11-06 11:13:09', 'active'),
(2, 'Employee1', 'Employee 1', '$2y$12$AkKHmSwBhWSAQYMkv9n87eJkyBVoXaCvfrIouEVHmzU4jFFb1kfFO', 'admin', '2025-11-03 14:51:49', '2025-12-10 14:33:45', 'active'),
(4, 'Employee2', 'Employee 2', '$2y$12$Y4mDBP06vB/Qa6DqMWZrq.ZWapIFmW2AyoUYgN9Av.t2VsKXYaCqu', 'admin', '2025-11-03 14:51:49', '2025-12-10 10:58:11', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
-- System audit log - DO NOT DELETE OR MODIFY RECORDS
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='System audit log - DO NOT DELETE OR MODIFY RECORDS';

--
-- Indexes for dumped tables
--

--
-- Indexes for table `checklist_images`
--
ALTER TABLE `checklist_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `checklist_id` (`checklist_id`);

--
-- Indexes for table `materials`
--
ALTER TABLE `materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_project_materials` (`project_id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `project_checklists`
--
ALTER TABLE `project_checklists`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_checklist_item` (`project_id`,`unit_id`,`item_description`),
  ADD KEY `completed_by_user_id` (`completed_by_user_id`),
  ADD KEY `unit_id` (`unit_id`);

--
-- Indexes for table `project_reports`
--
ALTER TABLE `project_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `project_units`
--
ALTER TABLE `project_units`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `report_images`
--
ALTER TABLE `report_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `report_id` (`report_id`);

--
-- Indexes for table `report_material_usage`
--
ALTER TABLE `report_material_usage`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_report_material` (`report_id`,`material_id`),
  ADD KEY `material_id` (`material_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `username_2` (`username`);

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_activity_user` (`user_id`),
  ADD KEY `idx_activity_action` (`action`),
  ADD KEY `idx_activity_created` (`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `checklist_images`
--
ALTER TABLE `checklist_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `materials`
--
ALTER TABLE `materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `project_checklists`
--
ALTER TABLE `project_checklists`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=121;

--
-- AUTO_INCREMENT for table `project_reports`
--
ALTER TABLE `project_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `project_units`
--
ALTER TABLE `project_units`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `report_images`
--
ALTER TABLE `report_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `report_material_usage`
--
ALTER TABLE `report_material_usage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `checklist_images`
--
ALTER TABLE `checklist_images`
  ADD CONSTRAINT `checklist_images_ibfk_1` FOREIGN KEY (`checklist_id`) REFERENCES `project_checklists` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `materials`
--
ALTER TABLE `materials`
  ADD CONSTRAINT `fk_project_materials` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `materials_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_checklists`
--
ALTER TABLE `project_checklists`
  ADD CONSTRAINT `project_checklists_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_checklists_ibfk_2` FOREIGN KEY (`completed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `project_checklists_ibfk_3` FOREIGN KEY (`unit_id`) REFERENCES `project_units` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_reports`
--
ALTER TABLE `project_reports`
  ADD CONSTRAINT `project_reports_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_units`
--
ALTER TABLE `project_units`
  ADD CONSTRAINT `project_units_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `report_images`
--
ALTER TABLE `report_images`
  ADD CONSTRAINT `report_images_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `project_reports` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `report_material_usage`
--
ALTER TABLE `report_material_usage`
  ADD CONSTRAINT `report_material_usage_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `project_reports` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `report_material_usage_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
