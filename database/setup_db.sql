-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 12, 2025 at 11:38 PM
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
(4, 11, 'Gravel', '', 45.00, 41.00, 'sacks', '', '2025-11-03 14:38:51'),
(6, 10, 'Cement', 'Rj4 Construction Supplies', 54.00, 54.00, 'sacks', '', '2025-10-31 06:53:09'),
(8, 11, 'Hollow Blocks', '', 1000.00, 1000.00, 'pcs', '', '2025-10-31 18:30:13'),
(10, 11, 'Cement', '', 45.00, 42.00, 'sacks', '', '2025-11-03 14:38:08');

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
(2, 'Lumina Homes Renovation', 'Mandalagan', 1, 0, 'Pending', 0, NULL, '2025-08-21 01:45:49', NULL),
(5, 'Deca Homes', 'Bacolod City', 0, 0, 'Pending', 1, '2025-10-29 17:06:50', '2025-09-08 11:09:13', NULL),
(7, 'East Homes 5', 'Mansilingan, Bacolod City', 0, 0, 'Pending', 1, '2025-10-29 17:06:58', '2025-10-23 16:00:00', 'uploads/projects/7_east_homes_5'),
(9, 'East Homes 1', 'Estefania', 3, 0, 'Ongoing', 0, NULL, '2025-10-27 06:06:43', NULL),
(10, 'lumina', 'Estefania', 5, 0, 'Ongoing', 0, NULL, '2025-10-28 04:50:00', NULL),
(11, 'East Homes 3', 'Estefania', 4, 33, 'Ongoing', 0, NULL, '2025-10-28 06:50:40', NULL);

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
  `unit_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_checklists`
--

INSERT INTO `project_checklists` (`id`, `project_id`, `item_description`, `is_completed`, `completed_by_user_id`, `completed_at`, `created_at`, `unit_id`) VALUES
(8, 5, 'Ceiling', 0, NULL, NULL, '2025-10-21 03:43:10', 1),
(9, 5, 'flooring', 0, NULL, NULL, '2025-10-24 00:43:27', 2),
(11, 9, 'flooring', 0, NULL, NULL, '2025-10-27 06:07:02', 5),
(12, 9, 'flooring', 0, NULL, NULL, '2025-10-27 06:07:02', 6),
(25, 11, 'flooring', 0, NULL, NULL, '2025-10-28 07:24:27', NULL),
(27, 11, 'flooring', 1, NULL, '2025-10-28 10:55:46', '2025-10-28 07:24:53', 23),
(28, 11, 'flooring', 1, NULL, '2025-10-28 10:55:09', '2025-10-28 07:24:53', 24),
(29, 11, 'flooring', 1, NULL, '2025-10-28 10:55:40', '2025-10-28 07:24:53', 25),
(30, 11, 'ceiling', 1, NULL, '2025-11-01 07:53:28', '2025-10-28 07:25:11', 22),
(33, 11, 'foundation', 1, NULL, '2025-10-28 10:55:47', '2025-10-28 07:44:55', 23),
(34, 11, 'foundation', 1, NULL, '2025-10-28 10:57:42', '2025-10-28 07:44:55', 24),
(35, 11, 'foundation', 0, NULL, NULL, '2025-10-28 07:44:55', 25),
(42, 2, 'foundation', 0, NULL, NULL, '2025-10-29 09:36:25', NULL),
(43, 2, 'foundation', 0, NULL, NULL, '2025-10-29 09:36:43', 21),
(44, 11, 'foundation', 0, NULL, NULL, '2025-10-29 09:54:18', 22),
(46, 11, 'flooring', 0, NULL, NULL, '2025-11-03 12:55:45', 22);

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
(5, 11, 22, '2025-11-06', 10, 'foundation', '10 workers', 'admin', '2025-11-01 00:14:51', '2025-11-06 13:38:06'),
(6, 11, 23, '2025-11-06', 0, 'asdasd', 'asdasd', 'admin', '2025-11-01 00:55:11', '2025-11-06 13:46:56'),
(10, 11, 22, '2025-11-06', 11, 'asda', 'asdasd', 'admin', '2025-11-01 06:52:51', '2025-11-06 13:46:47'),
(11, 11, 23, '2025-01-11', 5, 'asdas', 'asdas', 'admin', '2025-11-01 06:54:56', '2025-11-03 12:49:34'),
(12, 11, 22, '2025-11-01', 20, 'asda', 'asdas', 'admin', '2025-11-06 13:09:08', '2025-11-06 13:46:33');

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
(1, 5, 'House 1', '', 0, '2025-10-21 10:58:39'),
(2, 5, 'House 2', '', 0, '2025-10-21 10:58:50'),
(3, 5, 'House 3', '', 0, '2025-10-21 11:04:02'),
(4, 5, 'House 4', '', 0, '2025-10-21 11:07:04'),
(5, 9, 'Unit 1', '', 0, '2025-10-27 14:06:43'),
(6, 9, 'Unit 2', '', 0, '2025-10-27 14:06:43'),
(15, 10, 'uraaa', 'Oysters Street', 0, '2025-10-28 12:50:00'),
(16, 10, 'Unit 2', '', 0, '2025-10-28 12:50:00'),
(17, 10, 'Unit 3', '', 0, '2025-10-28 12:50:00'),
(18, 10, 'Unit 4', '', 0, '2025-10-28 13:01:15'),
(19, 10, 'Unit 5', '', 0, '2025-10-28 13:01:15'),
(20, 9, 'Unit 3', '', 0, '2025-10-28 14:27:28'),
(21, 2, 'Block 1 Lot 2', 'Oysters Street', 0, '2025-10-28 14:27:52'),
(22, 11, 'House 1 ', 'Ura!!!!~', 33, '2025-10-28 14:50:40'),
(23, 11, 'Unit 2', '', 0, '2025-10-28 14:50:40'),
(24, 11, 'Unit 3', '', 100, '2025-10-28 14:50:40'),
(25, 11, 'Unit 4', '', 0, '2025-10-28 14:50:40');

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
(5, 5, '20251101_011451_b2223b13_1.png', NULL, '2025-11-01 00:14:51'),
(6, 6, '20251101_015511_a655bc59_Bar_Chart_Vasquez_BSIS3-A.png', NULL, '2025-11-01 00:55:11'),
(8, 10, '20251101_075251_69f68018_Generated_Image_October_17__2025_-_5_50PM__1.png', NULL, '2025-11-01 06:52:51'),
(9, 11, '20251101_075456_141310ae_Generated_Image_October_18__2025_-_11_17AM.png', NULL, '2025-11-01 06:54:56');

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
(12, 5, 4, 4, '2025-11-03 13:42:19'),
(13, 10, 10, 3, '2025-11-03 13:42:19');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','constructor') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','disabled') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`, `updated_at`, `status`) VALUES
(1, 'admin', '$2y$12$kGsUeMc665Otg3VoqmSE3OM8coqvyBwnBpuFdiS/ASaSuOocLX0ya', 'admin', '2025-11-03 14:51:49', '2025-11-06 11:13:09', 'active'),
(2, 'constructor1', '$2y$12$2lzqG.Ruv.1iUFCc8nVeN.rSKHUhqlvJeQfJWqDpQobpAod6wIrAO', 'constructor', '2025-11-03 14:51:49', '2025-11-06 11:13:16', 'active'),
(4, 'construtor2', 'constructor2', 'constructor', '2025-11-03 14:51:49', '2025-11-03 14:51:49', 'active');

--
-- Indexes for dumped tables
--

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
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `materials`
--
ALTER TABLE `materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `project_checklists`
--
ALTER TABLE `project_checklists`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `project_reports`
--
ALTER TABLE `project_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `project_units`
--
ALTER TABLE `project_units`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `report_images`
--
ALTER TABLE `report_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `report_material_usage`
--
ALTER TABLE `report_material_usage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

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
