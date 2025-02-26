-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 26, 2025 at 04:22 AM
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
-- Database: `hr_ttv`
--

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `position_id` int(11) NOT NULL,
  `resume_path` varchar(255) DEFAULT NULL,
  `cover_letter` longtext DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`id`, `user_id`, `position_id`, `resume_path`, `cover_letter`, `status`, `created_at`, `updated_at`, `updated_by`) VALUES
(1, 2, 3, 'uploads/resumes/resume_2_1740449706.pdf', '', 'pending', '2025-02-25 02:15:06', '2025-02-25 02:15:06', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `application_details`
--

CREATE TABLE `application_details` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `fullname` varchar(100) DEFAULT NULL,
  `registered_address` text DEFAULT NULL,
  `present_address` text DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `marital_status` varchar(20) DEFAULT NULL,
  `birthplace` varchar(100) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `age` int(3) DEFAULT NULL,
  `weight` int(3) DEFAULT NULL,
  `height` int(3) DEFAULT NULL,
  `nationality` varchar(50) DEFAULT NULL,
  `race` varchar(50) DEFAULT NULL,
  `religion` varchar(50) DEFAULT NULL,
  `id_card` varchar(20) DEFAULT NULL,
  `issued_at` varchar(100) DEFAULT NULL,
  `issued_date` date DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `application_drafts`
--

CREATE TABLE `application_drafts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `position_id` int(11) NOT NULL,
  `data` longtext DEFAULT NULL,
  `resume_path` varchar(255) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `application_education`
--

CREATE TABLE `application_education` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `level` enum('secondary','vocational','university') NOT NULL,
  `period_from` varchar(20) DEFAULT NULL,
  `period_to` varchar(20) DEFAULT NULL,
  `institute` varchar(100) DEFAULT NULL,
  `degree` varchar(100) DEFAULT NULL,
  `major` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `application_status_logs`
--

CREATE TABLE `application_status_logs` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `old_status` enum('pending','approved','rejected') NOT NULL,
  `new_status` enum('pending','approved','rejected') NOT NULL,
  `changed_by` int(11) NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_info`
--

CREATE TABLE `contact_info` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `working_hours` varchar(100) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contact_info`
--

INSERT INTO `contact_info` (`id`, `name`, `email`, `phone`, `address`, `working_hours`, `updated_at`, `updated_by`) VALUES
(1, 'Chaiwat ', 'support@hr.ttv', '098-648-5736', '225 ถนนภักดีบริรักษ์ อำเภอนางรอง จังหวัดบุรีรัมย์ บุรีรัมย์ 31110', 'จันทร์-ศุกร์ เวลา 08:00-17:00 น.', '2025-02-25 06:14:14', 1);

-- --------------------------------------------------------

--
-- Table structure for table `form_data`
--

CREATE TABLE `form_data` (
  `id` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `form_data`
--

INSERT INTO `form_data` (`id`, `name`, `email`, `phone`, `message`, `created_at`) VALUES
(1, 'Project', 'somchai@email.com', '0986485736', 'hhh', '2025-02-25 04:33:07'),
(2, 'CHAIWAT SANGSANIT', 'chxlor@gmail.com', '098-6485799', 'กฟกฟกฟ', '2025-02-25 04:47:12'),
(3, 'CHAIWAT SANGSANIT', 'chxlor@gmail.com', '098-6485799', 'กฟกฟกฟ', '2025-02-25 04:47:33'),
(4, 'Project', 'somchai@email.com', '0986485736', 'hhh', '2025-02-25 04:47:40'),
(5, 'CHAIWAT SANGSANIT', 'somchai@email.com', '0892343423', 'dadad', '2025-02-25 04:47:47'),
(6, 'CHAIWAT SANGSANIT', 'chxlor@gmail.com', '098-6485799', 'กฟกฟกฟ', '2025-02-25 06:02:09');

-- --------------------------------------------------------

--
-- Table structure for table `positions`
--

CREATE TABLE `positions` (
  `id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `salary_min` int(11) DEFAULT NULL,
  `salary_max` int(11) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `type` enum('Full-time','Part-time','Contract','Internship') DEFAULT 'Full-time',
  `status` enum('open','closed','draft') DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `positions`
--

INSERT INTO `positions` (`id`, `title`, `department`, `description`, `requirements`, `salary_min`, `salary_max`, `location`, `type`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 'Senior Software Engineer', 'IT', 'พัฒนาและดูแลระบบหลักขององค์กร', '- ประสบการณ์ 5 ปีขึ้นไป\n- เชี่ยวชาญ PHP, JavaScript\n- มีประสบการณ์ด้าน Cloud', 80000, 120000, 'กรุงเทพฯ', 'Full-time', 'open', '2025-02-19 06:42:23', '2025-02-26 02:07:24', 1, 1),
(2, 'UX/UI Designer', 'IT', 'ออกแบบและพัฒนา User Interface', '- ประสบการณ์ 3 ปีขึ้นไป\n- เชี่ยวชาญ Figma, Adobe XD', 45000, 75000, 'กรุงเทพฯ', 'Full-time', 'open', '2025-02-19 06:42:23', '2025-02-19 06:42:23', 1, NULL),
(3, 'Digital Marketing', 'Marketing', 'วางแผนและดำเนินการด้านการตลาดดิจิทัล', '- ประสบการณ์ 2 ปีขึ้นไป\n- เชี่ยวชาญ Google Ads, Facebook Ads', 35000, 55000, 'กรุงเทพฯ', 'Full-time', 'open', '2025-02-19 06:42:23', '2025-02-19 06:42:23', 1, NULL),
(4, 'gggg', 'gg', 'g4g4', 'g4g4g', 44, 444, 'กรุงเทพฯ', 'Full-time', 'open', '2025-02-26 02:08:07', '2025-02-26 02:08:16', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password`, `role`, `created_at`, `updated_at`, `last_login`) VALUES
(1, 'Admin User', 'admin@hr.ttv', '0812345678', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '2025-02-19 06:42:23', '2025-02-26 01:15:09', '2025-02-26 01:15:09'),
(2, 'User Test', 'user@test.com', '0899999999', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', '2025-02-19 06:59:35', '2025-02-26 01:28:57', '2025-02-26 01:28:57'),
(3, 'chaiwat sangsanit', 'chxlor@gmail.com', '098-6485736', '$2y$10$p0dkE05gY4epR3DN6tjY5eVpjadXv3OoffjMGyUlLKTFqN2MZY5a.', 'user', '2025-02-19 07:31:07', '2025-02-19 07:31:07', NULL),
(5, 'Bank', 'admin@gmail.com', '098-6485799', '$2y$10$gnSEVFzxphb7u6Bre5FC1erXE.vTmZNpgVgLedZ8rfHZIgcotuQ5O', 'admin', '2025-02-25 01:40:03', '2025-02-25 01:40:03', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `position_id` (`position_id`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_application_status` (`status`),
  ADD KEY `idx_application_dates` (`created_at`,`updated_at`);

--
-- Indexes for table `application_details`
--
ALTER TABLE `application_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `application_id` (`application_id`),
  ADD KEY `idx_details_birthdate` (`birthdate`),
  ADD KEY `idx_details_id_card` (`id_card`);

--
-- Indexes for table `application_drafts`
--
ALTER TABLE `application_drafts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_position` (`user_id`,`position_id`),
  ADD KEY `position_id` (`position_id`),
  ADD KEY `idx_draft_created_at` (`created_at`),
  ADD KEY `idx_draft_updated_at` (`updated_at`);

--
-- Indexes for table `application_education`
--
ALTER TABLE `application_education`
  ADD PRIMARY KEY (`id`),
  ADD KEY `application_id` (`application_id`);

--
-- Indexes for table `application_status_logs`
--
ALTER TABLE `application_status_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `application_id` (`application_id`),
  ADD KEY `changed_by` (`changed_by`);

--
-- Indexes for table `contact_info`
--
ALTER TABLE `contact_info`
  ADD PRIMARY KEY (`id`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `form_data`
--
ALTER TABLE `form_data`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `positions`
--
ALTER TABLE `positions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_position_status` (`status`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_user_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `application_details`
--
ALTER TABLE `application_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `application_drafts`
--
ALTER TABLE `application_drafts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `application_education`
--
ALTER TABLE `application_education`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `application_status_logs`
--
ALTER TABLE `application_status_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contact_info`
--
ALTER TABLE `contact_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `form_data`
--
ALTER TABLE `form_data`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `positions`
--
ALTER TABLE `positions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `applications_ibfk_2` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`),
  ADD CONSTRAINT `applications_ibfk_3` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `application_details`
--
ALTER TABLE `application_details`
  ADD CONSTRAINT `application_details_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `application_drafts`
--
ALTER TABLE `application_drafts`
  ADD CONSTRAINT `application_drafts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `application_drafts_ibfk_2` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`);

--
-- Constraints for table `application_education`
--
ALTER TABLE `application_education`
  ADD CONSTRAINT `application_education_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `application_status_logs`
--
ALTER TABLE `application_status_logs`
  ADD CONSTRAINT `application_status_logs_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`),
  ADD CONSTRAINT `application_status_logs_ibfk_2` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `contact_info`
--
ALTER TABLE `contact_info`
  ADD CONSTRAINT `contact_info_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `positions`
--
ALTER TABLE `positions`
  ADD CONSTRAINT `positions_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `positions_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
