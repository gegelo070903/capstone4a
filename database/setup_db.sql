-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 21, 2025 at 03:13 AM
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
-- Database: `trial`
--

-- --------------------------------------------------------

--
-- Table structure for table `construction_reports`
--

CREATE TABLE `construction_reports` (
  `id` int(11) NOT NULL,
  `constructor_id` int(11) DEFAULT NULL,
  `report_date` date DEFAULT NULL,
  `start_time` varchar(10) DEFAULT NULL,
  `end_time` varchar(10) DEFAULT NULL,
  `status` enum('complete','ongoing') DEFAULT NULL,
  `description` text DEFAULT NULL,
  `proof_image` varchar(255) DEFAULT NULL,
  `materials_left` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `construction_reports`
--

INSERT INTO `construction_reports` (`id`, `constructor_id`, `report_date`, `start_time`, `end_time`, `status`, `description`, `proof_image`, `materials_left`) VALUES
(1, 2, '2025-08-09', '07:00', '17:00', 'ongoing', '10 workers', NULL, '10 hollowblocks'),
(2, 2, '2025-08-22', '05:00', '17:00', 'complete', '15 workers', NULL, 'None'),
(3, 2, '1234-03-12', '12:31', '15:45', 'ongoing', 'Project ONGOING', 'uploads/proof_68a2cb7ba3ea34.04508546.png', 'None');

-- --------------------------------------------------------

--
-- Table structure for table `images`
--

CREATE TABLE `images` (
  `id` int(11) NOT NULL,
  `image` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `materials`
--

CREATE TABLE `materials` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `supplier` varchar(100) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `time` varchar(10) DEFAULT NULL,
  `purpose` varchar(255) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `materials`
--

INSERT INTO `materials` (`id`, `name`, `price`, `supplier`, `date`, `time`, `purpose`, `quantity`, `total_amount`) VALUES
(1, 'Hollow Blocks', 13.00, 'Rj4 Construction Supplies', '2025-08-04', '13:02:06', '2 house for Lumina', 200, 2600.00),
(2, 'Gravel', 100.00, 'Rj4 Construction Supplies', '2025-08-04', '07:11:05 P', '2', 2, 200.00),
(3, 'Hollow Blocks', 15.00, 'Rj4 Construction Supplies', '2025-08-19', '09:54:52 P', '2', 122, 1830.00);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','constructor') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`) VALUES
(1, 'admin', 'adminpass', 'admin'),
(2, 'constructor1', 'constructor1', 'constructor'),
(3, 'gelo', 'gelo09', 'admin');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `construction_reports`
--
ALTER TABLE `construction_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `constructor_id` (`constructor_id`);

--
-- Indexes for table `images`
--
ALTER TABLE `images`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `materials`
--
ALTER TABLE `materials`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `construction_reports`
--
ALTER TABLE `construction_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `images`
--
ALTER TABLE `images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `materials`
--
ALTER TABLE `materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `construction_reports`
--
ALTER TABLE `construction_reports`
  ADD CONSTRAINT `construction_reports_ibfk_1` FOREIGN KEY (`constructor_id`) REFERENCES `users` (`id`);
COMMIT;


ALTER TABLE `projects` ADD `location` VARCHAR(255) NULL AFTER `name`;
ALTER TABLE `construction_reports` ADD `project_id` INT(11) NULL AFTER `id`;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
