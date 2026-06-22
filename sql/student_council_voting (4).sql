-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 17, 2026 at 04:43 PM
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
-- Database: `student_council_voting`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `full_name`, `email`, `created_at`) VALUES
(2, 'UchihaJerick', '$2y$10$UCKX7PQeX/WEmSQ.8G3UgOfffgy.TcsFBy7FiFV9989fGnPfWgXfW', 'Jerick Dacera', 'dacerajerick38@gmail.com', '2026-02-12 06:44:31');

-- --------------------------------------------------------

--
-- Table structure for table `candidates`
--

CREATE TABLE `candidates` (
  `id` int(11) NOT NULL,
  `position_id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `platform` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `achievements` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `candidates`
--

INSERT INTO `candidates` (`id`, `position_id`, `student_id`, `full_name`, `platform`, `image`, `bio`, `achievements`, `photo`, `created_at`) VALUES
(88, 84, '2023-001', 'Jelaica D. Bato', '', NULL, NULL, NULL, '', '2026-04-12 15:16:41'),
(89, 84, '2021-003', 'Limar Thessa Savandal', '', NULL, NULL, NULL, '', '2026-04-12 15:17:02'),
(90, 85, '8575-78', 'Uchiha Jerick', '', NULL, NULL, NULL, 'assets/uploads/students/student_8575-78_69ac040d9d015.png', '2026-04-12 15:17:12'),
(91, 85, '2021-001', 'Ramces Mascardo', '', NULL, NULL, NULL, '', '2026-04-12 15:17:23'),
(97, 88, '2022-002', 'Rizalyn C. consigna', '', NULL, NULL, NULL, '', '2026-05-17 14:33:27'),
(98, 88, '2023-001', 'Jelaica D. Bato', '', NULL, NULL, NULL, '', '2026-05-17 14:33:34'),
(99, 89, '2021-002', 'Khrissia myra Cruiz', '', NULL, NULL, NULL, '', '2026-05-17 14:33:41'),
(100, 89, '2022-001', 'Raizy Maricole Savandal', '', NULL, NULL, NULL, '', '2026-05-17 14:33:50');

-- --------------------------------------------------------

--
-- Table structure for table `elections`
--

CREATE TABLE `elections` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `closed_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `status` enum('pending','active','closed','completed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `elections`
--

INSERT INTO `elections` (`id`, `title`, `description`, `start_date`, `end_date`, `closed_at`, `deleted_at`, `deleted_by`, `is_active`, `status`, `created_at`) VALUES
(20, 'SSC Election', NULL, '2026-04-12 23:18:00', '2026-04-12 23:41:00', NULL, NULL, NULL, 0, '', '2026-04-12 15:16:02'),
(22, 'SSC 2027', NULL, '2026-05-17 22:33:00', '2026-05-17 22:59:00', NULL, NULL, NULL, 1, 'active', '2026-05-17 14:32:54');

-- --------------------------------------------------------

--
-- Table structure for table `eligible_ids`
--

CREATE TABLE `eligible_ids` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `is_registered` tinyint(1) DEFAULT 0,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `eligible_ids`
--

INSERT INTO `eligible_ids` (`id`, `student_id`, `is_registered`, `uploaded_at`) VALUES
(89, '2021-001', 1, '2026-03-07 08:03:27'),
(90, '2021-002', 1, '2026-03-07 08:03:27'),
(91, '2021-003', 1, '2026-03-07 08:03:27'),
(92, '2022-001', 1, '2026-03-07 08:03:27'),
(93, '2022-002', 1, '2026-03-07 08:03:27'),
(94, '2023-001', 1, '2026-03-07 08:03:27'),
(95, '2026-2099', 1, '2026-03-07 09:50:29'),
(96, '2993-457', 0, '2026-03-07 10:45:09'),
(97, '7575-877', 0, '2026-03-07 10:45:09'),
(98, '8575-78', 1, '2026-03-07 10:45:09');

-- --------------------------------------------------------

--
-- Table structure for table `positions`
--

CREATE TABLE `positions` (
  `id` int(11) NOT NULL,
  `election_id` int(11) DEFAULT NULL,
  `position_name` varchar(100) NOT NULL,
  `display_order` int(11) DEFAULT 0,
  `max_votes` int(11) DEFAULT 1,
  `max_candidates` int(11) NOT NULL DEFAULT 5
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `positions`
--

INSERT INTO `positions` (`id`, `election_id`, `position_name`, `display_order`, `max_votes`, `max_candidates`) VALUES
(3, NULL, 'President', 1, 1, 5),
(4, NULL, 'Vice President', 2, 1, 5),
(5, NULL, 'Secretary', 3, 1, 5),
(6, NULL, 'Treasurer', 4, 1, 5),
(7, NULL, 'Auditor', 5, 1, 5),
(8, NULL, 'PRO', 6, 1, 5),
(84, 20, 'President', 1, 1, 2),
(85, 20, 'Vice President', 2, 1, 2),
(88, 22, 'President', 1, 1, 2),
(89, 22, 'Vice President', 2, 1, 2);

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `has_voted` tinyint(1) DEFAULT 0,
  `registered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_id`, `full_name`, `contact_number`, `password`, `profile_photo`, `has_voted`, `registered_at`, `updated_at`) VALUES
(18, '2021-001', 'Ramces Mascardo', NULL, '$2y$10$NggxvyTEve3ZT0hFX9RLbeqgT3YQLZClc6isRtyxGCdOjiZgtjH.W', NULL, 1, '2026-03-07 08:05:29', '2026-03-07 08:24:32'),
(19, '2021-002', 'Khrissia myra Cruiz', NULL, '$2y$10$ZV3J2vJTtDtQKoXRusbv9Ot3CvJdC27IbI/M.53ed2.krO7niWw.m', NULL, 1, '2026-03-07 08:06:59', '2026-03-07 08:26:27'),
(20, '2021-003', 'Limar Thessa Savandal', NULL, '$2y$10$3vk4krPasBIHqvfkmFrCvONzmPWlLYJwC.QOJYsB5pppdJEcBiO16', NULL, 1, '2026-03-07 08:07:49', '2026-03-07 08:23:19'),
(21, '2022-001', 'Raizy Maricole Savandal', NULL, '$2y$10$Fh1H50xOY3E/Gkb8DSsb6.Tav.EbPO9.boB53wuTvEQnWjKkJiGuq', NULL, 1, '2026-03-07 08:08:52', '2026-03-07 08:21:29'),
(22, '2022-002', 'Rizalyn C. consigna', NULL, '$2y$10$aAfMdV4JvTTczddRdi.HvuDw/nWWjlyAf4/Q8wPXc2ck9nVrrorya', NULL, 1, '2026-03-07 08:09:55', '2026-03-07 08:20:19'),
(23, '2023-001', 'Jelaica D. Bato', NULL, '$2y$10$i8kKjzLBOAshV63RQiNp3OxUvNv.J7go25MVS1y77Hpz0HmJJKosG', NULL, 1, '2026-03-07 08:11:22', '2026-03-07 08:19:01'),
(24, '2026-2099', 'Wewe Mascardo', NULL, '$2y$10$M9HyILIMh7.8I7KHzrt5D.IS5LeQJjlJY3FrJ4hQsi3Edhz9y1l5K', 'assets/uploads/students/student_2026-2099_69d38ec8c3370.png', 1, '2026-03-07 09:52:01', '2026-04-06 10:45:28'),
(25, '8575-78', 'Uchiha Jerick', NULL, '$2y$10$3RXrv2oXnzNoDuIOIR.jeu4x7/FN4BFigjVBkVs2YFaO8GZznZciO', 'assets/uploads/students/student_8575-78_69ac040d9d015.png', 1, '2026-03-07 10:46:02', '2026-05-17 13:36:33');

-- --------------------------------------------------------

--
-- Table structure for table `student_profile_history`
--

CREATE TABLE `student_profile_history` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `field_name` varchar(50) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_profile_history`
--

INSERT INTO `student_profile_history` (`id`, `student_id`, `field_name`, `old_value`, `new_value`, `changed_at`) VALUES
(10, '8575-78', 'profile_photo', '', 'assets/uploads/students/student_8575-78_69ac040d9d015.png', '2026-03-07 10:55:09'),
(11, '2026-2099', 'profile_photo', '', 'assets/uploads/students/student_2026-2099_69d38ec8c3370.png', '2026-04-06 10:45:28');

-- --------------------------------------------------------

--
-- Table structure for table `votes`
--

CREATE TABLE `votes` (
  `id` int(11) NOT NULL,
  `election_id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `position_id` int(11) NOT NULL,
  `voted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `votes`
--

INSERT INTO `votes` (`id`, `election_id`, `student_id`, `candidate_id`, `position_id`, `voted_at`) VALUES
(108, 20, '2026-2099', 89, 84, '2026-04-12 15:18:40'),
(109, 20, '2026-2099', 90, 85, '2026-04-12 15:18:40'),
(110, 20, '2023-001', 88, 84, '2026-04-12 15:19:46'),
(111, 20, '2023-001', 90, 85, '2026-04-12 15:19:46'),
(112, 20, '2022-001', 88, 84, '2026-04-12 15:20:35'),
(113, 20, '2022-001', 91, 85, '2026-04-12 15:20:35'),
(114, 20, '2021-003', 88, 84, '2026-04-12 15:21:33'),
(115, 20, '2021-003', 90, 85, '2026-04-12 15:21:33'),
(116, 20, '2021-002', 88, 84, '2026-04-12 15:22:05'),
(117, 20, '2021-002', 91, 85, '2026-04-12 15:22:05'),
(124, 22, '2021-001', 97, 88, '2026-05-17 14:34:32'),
(125, 22, '2021-001', 99, 89, '2026-05-17 14:34:32'),
(126, 22, '2022-001', 97, 88, '2026-05-17 14:35:07'),
(127, 22, '2022-001', 99, 89, '2026-05-17 14:35:07'),
(128, 22, '2023-001', 98, 88, '2026-05-17 14:35:33'),
(129, 22, '2023-001', 100, 89, '2026-05-17 14:35:33');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `candidates`
--
ALTER TABLE `candidates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_candidate` (`position_id`,`student_id`),
  ADD KEY `idx_candidate_position` (`position_id`),
  ADD KEY `idx_candidate_student` (`student_id`),
  ADD KEY `idx_candidate_full_name` (`full_name`);

--
-- Indexes for table `elections`
--
ALTER TABLE `elections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_election_active` (`is_active`),
  ADD KEY `idx_election_dates` (`start_date`,`end_date`),
  ADD KEY `idx_election_created_at` (`created_at`),
  ADD KEY `idx_election_status` (`status`);

--
-- Indexes for table `eligible_ids`
--
ALTER TABLE `eligible_ids`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`);

--
-- Indexes for table `positions`
--
ALTER TABLE `positions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_position_election` (`election_id`),
  ADD KEY `idx_position_name` (`position_name`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD KEY `idx_student_id_number` (`student_id`),
  ADD KEY `idx_student_full_name` (`full_name`),
  ADD KEY `idx_student_created` (`registered_at`),
  ADD KEY `idx_student_has_voted` (`has_voted`);

--
-- Indexes for table `student_profile_history`
--
ALTER TABLE `student_profile_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `votes`
--
ALTER TABLE `votes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_vote` (`election_id`,`student_id`,`position_id`),
  ADD KEY `idx_vote_election_student` (`election_id`,`student_id`),
  ADD KEY `idx_vote_election` (`election_id`),
  ADD KEY `idx_vote_student` (`student_id`),
  ADD KEY `idx_vote_candidate` (`candidate_id`),
  ADD KEY `idx_vote_position` (`position_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `candidates`
--
ALTER TABLE `candidates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT for table `elections`
--
ALTER TABLE `elections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `eligible_ids`
--
ALTER TABLE `eligible_ids`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=102;

--
-- AUTO_INCREMENT for table `positions`
--
ALTER TABLE `positions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=90;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `student_profile_history`
--
ALTER TABLE `student_profile_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `votes`
--
ALTER TABLE `votes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=130;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `candidates`
--
ALTER TABLE `candidates`
  ADD CONSTRAINT `candidates_ibfk_1` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `candidates_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `positions`
--
ALTER TABLE `positions`
  ADD CONSTRAINT `positions_ibfk_1` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `eligible_ids` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `student_profile_history`
--
ALTER TABLE `student_profile_history`
  ADD CONSTRAINT `student_profile_history_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `votes`
--
ALTER TABLE `votes`
  ADD CONSTRAINT `votes_ibfk_1` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `votes_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `votes_ibfk_3` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `votes_ibfk_4` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
