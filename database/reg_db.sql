-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 09, 2026 at 11:35 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `reg_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `breakdown`
--

CREATE TABLE `breakdown` (
  `breakdown_id` int(11) NOT NULL,
  `equipment_id` int(11) DEFAULT NULL,
  `reported_by_user_id` int(11) DEFAULT NULL,
  `breakdown_date` datetime DEFAULT NULL,
  `issue_description` text DEFAULT NULL,
  `priority` enum('Low','Medium','High') DEFAULT NULL,
  `statuss` varchar(50) DEFAULT NULL,
  `create_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `category`
--

CREATE TABLE `category` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `category`
--

INSERT INTO `category` (`category_id`, `category_name`, `user_id`, `created_at`, `updated_at`) VALUES
(2, 'name', 1, '2026-02-03 14:02:06', '2026-02-03 14:02:06'),
(3, 'oziya', 1, '2026-02-03 14:32:37', '2026-02-03 15:01:30'),
(4, 'Machine', 1, '2026-02-03 14:32:52', '2026-02-03 17:57:37'),
(5, 'manz', 1, '2026-02-03 14:33:04', '2026-02-03 14:33:04'),
(6, 'yayobye', 1, '2026-02-03 14:46:20', '2026-02-03 14:57:37');

-- --------------------------------------------------------

--
-- Table structure for table `equipment`
--

CREATE TABLE `equipment` (
  `id` int(11) NOT NULL,
  `equipment_name` varchar(150) DEFAULT NULL,
  `equipment_image` text NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `equipment_location_id` int(11) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `starting_date` date DEFAULT NULL,
  `expired_date` date DEFAULT NULL,
  `statuss` varchar(50) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `equipment`
--

INSERT INTO `equipment` (`id`, `equipment_name`, `equipment_image`, `category_id`, `serial_number`, `equipment_location_id`, `purchase_date`, `starting_date`, `expired_date`, `statuss`, `user_id`, `created_at`, `updated_at`) VALUES
(1, 'generator', 'equipment_1770138325_69822ad5d753e.JPG', 4, '007GDF66', 2, '2025-12-10', NULL, NULL, 'Active', 1, '2026-02-03 17:05:25', '2026-02-03 17:05:25'),
(5, 'cyamatwi', 'equipment_1770143069_69823d5d675a2.png', 4, '007GD', 2, '2025-12-10', '0000-00-00', '2026-03-14', 'Active', 1, '2026-02-03 18:24:29', '2026-02-03 18:24:29');

-- --------------------------------------------------------

--
-- Table structure for table `equipment_condition_history`
--

CREATE TABLE `equipment_condition_history` (
  `condition_id` int(11) NOT NULL,
  `equipment_id` int(11) DEFAULT NULL,
  `conditions` varchar(50) DEFAULT NULL,
  `recorded_by_user_id` int(11) DEFAULT NULL,
  `recorded_date` datetime DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `equipment_location`
--

CREATE TABLE `equipment_location` (
  `location_id` int(11) NOT NULL,
  `location_name` varchar(150) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `equipment_location`
--

INSERT INTO `equipment_location` (`location_id`, `location_name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'kicukiro', 'kicukiro/kigali/Rwanda', '2026-02-03 16:07:00', '2026-02-03 16:07:22'),
(2, 'karongi', 'western province', '2026-02-03 16:38:04', '2026-02-03 16:38:04');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance`
--

CREATE TABLE `maintenance` (
  `mid` int(11) NOT NULL,
  `equipment_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `maintenance_type` varchar(50) DEFAULT NULL,
  `maintenance_schedule_id` int(11) DEFAULT NULL,
  `completed_date` datetime DEFAULT NULL,
  `description` text DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `statuss` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_schedule`
--

CREATE TABLE `maintenance_schedule` (
  `schedule_id` int(11) NOT NULL,
  `equipment_id` int(11) DEFAULT NULL,
  `maintenance_type` varchar(50) DEFAULT NULL,
  `interval_value` int(11) DEFAULT NULL,
  `interval_unit` enum('SECOND','MINUTE','DAY','MONTH','YEAR') DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `assigned_to_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification`
--

CREATE TABLE `notification` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `role`
--

CREATE TABLE `role` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role`
--

INSERT INTO `role` (`role_id`, `role_name`, `created_at`, `updated_at`) VALUES
(1, 'admin', '2026-02-03 12:54:17', '2026-02-03 12:54:17'),
(2, 'technician ', '2026-02-03 12:54:17', '2026-02-03 12:54:17'),
(3, 'owner', '2026-02-03 12:54:29', '2026-02-03 12:54:29');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `firstname` varchar(100) DEFAULT NULL,
  `lastname` varchar(100) DEFAULT NULL,
  `gander` varchar(10) DEFAULT NULL,
  `user_image` text DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `passwords` varchar(255) DEFAULT NULL,
  `statuss` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `role_id`, `firstname`, `lastname`, `gander`, `user_image`, `email`, `username`, `passwords`, `statuss`, `created_at`, `updated_at`, `last_login`) VALUES
(1, 1, 'manzi', 'eugene', 'Male', '1770145677_6982478d64ae6.jpg', 'nendayishimiye@gmail.com', 'ne', '$2y$10$HPOlnVfej/yMtIzPBzZ0NegEiYk53Vy3DCAQ1xHP6afvnCGGF9Opa', '1', '2026-02-03 12:57:19', '2026-02-03 21:03:02', NULL),
(2, 2, 'fabrice', 'igiraneza', 'male', NULL, 'igiraneza@gmail.com', 'manzi', '$2y$10$slXmwhpIddieKv5SGIKDu.v0/veGtU3k8CRdmFyMex3wU4EvPVXk6', '1', '2026-02-03 16:35:27', '2026-02-03 16:35:27', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `breakdown`
--
ALTER TABLE `breakdown`
  ADD PRIMARY KEY (`breakdown_id`);

--
-- Indexes for table `category`
--
ALTER TABLE `category`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `equipment`
--
ALTER TABLE `equipment`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `equipment_condition_history`
--
ALTER TABLE `equipment_condition_history`
  ADD PRIMARY KEY (`condition_id`);

--
-- Indexes for table `equipment_location`
--
ALTER TABLE `equipment_location`
  ADD PRIMARY KEY (`location_id`);

--
-- Indexes for table `maintenance`
--
ALTER TABLE `maintenance`
  ADD PRIMARY KEY (`mid`);

--
-- Indexes for table `maintenance_schedule`
--
ALTER TABLE `maintenance_schedule`
  ADD PRIMARY KEY (`schedule_id`);

--
-- Indexes for table `notification`
--
ALTER TABLE `notification`
  ADD PRIMARY KEY (`notification_id`);

--
-- Indexes for table `role`
--
ALTER TABLE `role`
  ADD PRIMARY KEY (`role_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `breakdown`
--
ALTER TABLE `breakdown`
  MODIFY `breakdown_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `category`
--
ALTER TABLE `category`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `equipment`
--
ALTER TABLE `equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `equipment_condition_history`
--
ALTER TABLE `equipment_condition_history`
  MODIFY `condition_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `equipment_location`
--
ALTER TABLE `equipment_location`
  MODIFY `location_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `maintenance`
--
ALTER TABLE `maintenance`
  MODIFY `mid` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `maintenance_schedule`
--
ALTER TABLE `maintenance_schedule`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification`
--
ALTER TABLE `notification`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `role`
--
ALTER TABLE `role`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
