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
CREATE TABLE `category` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
CREATE TABLE `equipment_location` (
  `location_id` int(11) NOT NULL,
  `location_name` varchar(150) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
CREATE TABLE `notification` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `role` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
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
ALTER TABLE `breakdown`
  ADD PRIMARY KEY (`breakdown_id`);
ALTER TABLE `category`
  ADD PRIMARY KEY (`category_id`);
ALTER TABLE `equipment`
  ADD PRIMARY KEY (`id`);
ALTER TABLE `equipment_condition_history`
  ADD PRIMARY KEY (`condition_id`);
ALTER TABLE `equipment_location`
  ADD PRIMARY KEY (`location_id`);
ALTER TABLE `maintenance`
  ADD PRIMARY KEY (`mid`);
ALTER TABLE `maintenance_schedule`
  ADD PRIMARY KEY (`schedule_id`);
ALTER TABLE `notification`
  ADD PRIMARY KEY (`notification_id`);
ALTER TABLE `role`
  ADD PRIMARY KEY (`role_id`);
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`);
ALTER TABLE `breakdown`
  MODIFY `breakdown_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;
ALTER TABLE `category`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;
ALTER TABLE `equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;
ALTER TABLE `equipment_condition_history`
  MODIFY `condition_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;
ALTER TABLE `equipment_location`
  MODIFY `location_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;
ALTER TABLE `maintenance`
  MODIFY `mid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;
ALTER TABLE `maintenance_schedule`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;
ALTER TABLE `notification`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;
ALTER TABLE `role`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;
