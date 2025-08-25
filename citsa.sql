-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 20, 2025 at 10:52 PM
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
-- Database: `citsa`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `name` varchar(225) NOT NULL,
  `username` varchar(225) NOT NULL,
  `password` varchar(1000) NOT NULL,
  `email` varchar(225) NOT NULL,
  `profile_image` varchar(225) NOT NULL DEFAULT 'default-avatar.png',
  `department` varchar(225) NOT NULL,
  `position` varchar(225) NOT NULL,
  `role_id` int(11) NOT NULL DEFAULT 2,
  `last_seen` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `name`, `username`, `password`, `email`, `profile_image`, `department`, `position`, `role_id`, `last_seen`) VALUES
(1, 'Admin User', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@citsa.com', 'default-avatar.png', 'Computer Science', 'System Administrator', 1, '2025-08-20 02:30:57');

-- --------------------------------------------------------

--
-- Table structure for table `admin_roles`
--

CREATE TABLE `admin_roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `permissions` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_roles`
--

INSERT INTO `admin_roles` (`role_id`, `role_name`, `description`, `permissions`, `created_at`) VALUES
(1, 'super_admin', 'Full system access', 'all', '2025-08-12 06:32:41'),
(2, 'admin', 'Administrative access', 'users,chat_rooms,clubs,authorized_users,events', '2025-08-12 06:32:41'),
(3, 'editor', 'Content management access', 'events,announcements', '2025-08-12 06:32:41');

-- --------------------------------------------------------

--
-- Table structure for table `authorized_students`
--

CREATE TABLE `authorized_students` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `programme` varchar(100) NOT NULL,
  `user_type` enum('student','alumni') NOT NULL DEFAULT 'student',
  `status` enum('active','inactive','graduated') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `authorized_students`
--

INSERT INTO `authorized_students` (`id`, `first_name`, `last_name`, `student_id`, `programme`, `user_type`, `status`, `created_at`) VALUES
(1, 'John', 'Doe', 'PS/ITC/21/0001', 'B.Sc. Information Technology', 'student', 'active', '2025-08-01 08:17:24'),
(2, 'Jane', 'Smith', 'PS/CSC/21/0002', 'B.Sc. Computer Science', 'student', 'active', '2025-08-01 08:17:24'),
(3, 'William', 'Jackson', 'PS/ITC/20/0001', 'B.Sc. Information Technology', 'alumni', 'graduated', '2025-08-01 08:17:24'),
(4, 'Patricia', 'White', 'PS/CSC/20/0002', 'B.Sc. Computer Science', 'alumni', 'graduated', '2025-08-01 08:17:24'),
(5, 'king', 'kyere', 'ps/itc/20/0100', 'B.Sc. Information Technology', 'alumni', 'active', '2025-08-14 01:32:15');

-- --------------------------------------------------------

--
-- Table structure for table `chats`
--

CREATE TABLE `chats` (
  `chat_id` int(11) NOT NULL,
  `from_id` int(11) NOT NULL,
  `to_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `opened` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_rooms`
--

CREATE TABLE `chat_rooms` (
  `room_id` int(11) NOT NULL,
  `room_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `department` varchar(225) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_room_messages`
--

CREATE TABLE `chat_room_messages` (
  `id` int(11) NOT NULL,
  `room_id` varchar(50) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `message_type` enum('text','image','file') DEFAULT 'text',
  `file_url` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `read_status` tinyint(1) DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `decryption_key` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_room_message_reads`
--

CREATE TABLE `chat_room_message_reads` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `room_id` varchar(100) NOT NULL,
  `last_read_message_id` int(11) DEFAULT 0,
  `last_read_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_room_message_reads`
--

INSERT INTO `chat_room_message_reads` (`id`, `user_id`, `room_id`, `last_read_message_id`, `last_read_at`, `updated_at`) VALUES
(1, 5, 'general', 13, '2025-08-14 02:07:25', '2025-08-14 02:07:25');

-- --------------------------------------------------------

--
-- Table structure for table `chat_room_online_users`
--

CREATE TABLE `chat_room_online_users` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `room_id` varchar(100) NOT NULL,
  `last_activity` datetime DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_room_online_users`
--

INSERT INTO `chat_room_online_users` (`id`, `user_id`, `room_id`, `last_activity`, `is_active`) VALUES
(1, 5, 'general', '2025-08-20 12:28:23', 0),
(116, 5, 'students_only', '2025-08-20 04:22:48', 1),
(204, 7, 'general', '2025-08-14 02:56:34', 1),
(333, 6, 'general', '2025-08-20 10:02:37', 0),
(2252, 5, 'level_400', '2025-08-20 03:51:33', 1),
(3030, 7, 'students_only', '2025-08-14 01:25:25', 0),
(3630, 8, 'alumni_only', '2025-08-14 01:39:17', 0),
(3725, 8, 'general', '2025-08-14 01:39:22', 0),
(6636, 6, 'level_400', '2025-08-14 04:15:02', 1),
(9375, 6, 'students_only', '2025-08-20 03:13:25', 0),
(10640, 5, 'club_1', '2025-08-20 04:01:25', 1);

-- --------------------------------------------------------

--
-- Table structure for table `clubs`
--

CREATE TABLE `clubs` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clubs`
--

INSERT INTO `clubs` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'Networking Club', 'Network administration and security', '2025-08-01 10:59:26'),
(2, 'Cybersecurity Club', 'Cybersecurity and ethical hacking', '2025-08-01 10:59:26'),
(3, 'Web Development Club', 'Web development and design', '2025-08-01 10:59:26'),
(4, 'Machine Learning & AI Club', 'AI and machine learning', '2025-08-01 10:59:26');

-- --------------------------------------------------------

--
-- Table structure for table `conversations`
--

CREATE TABLE `conversations` (
  `conversation_id` int(11) NOT NULL,
  `user_1` int(11) NOT NULL,
  `user_2` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `conversations`
--

INSERT INTO `conversations` (`conversation_id`, `user_1`, `user_2`) VALUES
(1, 5, 6),
(3, 6, 7),
(2, 7, 5);

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `event_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `event_date` date NOT NULL,
  `event_time` time DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `event_type` enum('event','announcement','meeting','workshop') NOT NULL DEFAULT 'event',
  `status` enum('draft','published','cancelled') NOT NULL DEFAULT 'draft',
  `image_path` varchar(500) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`event_id`, `title`, `description`, `event_date`, `event_time`, `location`, `event_type`, `status`, `image_path`, `created_by`, `created_at`, `updated_at`) VALUES
(2, 'Handing Over', 'A handing over ceremony for the department', '2025-08-21', '11:30:00', 'NLT', 'meeting', 'published', 'uploads/events/event_1755716984_68a61d7871d33.jpg', 1, '2025-08-20 19:09:44', '2025-08-20 19:09:44');

-- --------------------------------------------------------

--
-- Table structure for table `event_attachments`
--

CREATE TABLE `event_attachments` (
  `attachment_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `file_size` int(11) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `friends`
--

CREATE TABLE `friends` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `friend_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `friends`
--

INSERT INTO `friends` (`id`, `user_id`, `friend_id`, `created_at`) VALUES
(3, 5, 7, '2025-07-31 14:29:50'),
(4, 7, 5, '2025-07-31 14:29:50'),
(5, 6, 5, '2025-08-01 00:33:51'),
(6, 5, 6, '2025-08-01 00:33:51'),
(7, 6, 7, '2025-08-11 05:55:02'),
(8, 7, 6, '2025-08-11 05:55:02');

-- --------------------------------------------------------

--
-- Table structure for table `friend_requests`
--

CREATE TABLE `friend_requests` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `status` enum('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `friend_requests`
--

INSERT INTO `friend_requests` (`id`, `sender_id`, `receiver_id`, `status`, `created_at`, `updated_at`) VALUES
(1, 6, 5, 'accepted', '2025-07-31 07:34:04', '2025-07-31 07:34:17'),
(2, 7, 5, 'accepted', '2025-07-31 14:27:54', '2025-07-31 14:29:50'),
(3, 7, 6, 'accepted', '2025-07-31 14:27:57', '2025-08-11 05:55:02'),
(4, 5, 6, 'accepted', '2025-08-01 00:33:19', '2025-08-01 00:33:51');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `message_type` enum('text','image','file','voice','video') DEFAULT 'text',
  `file_url` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `read_status` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `conversation_id`, `sender_id`, `message`, `message_type`, `file_url`, `file_name`, `file_size`, `read_status`, `created_at`) VALUES
(36, 2, 5, 'hey', 'text', NULL, NULL, NULL, 0, '2025-08-20 09:58:32'),
(37, 1, 5, 'hey', 'text', NULL, NULL, NULL, 1, '2025-08-20 10:01:59');

-- --------------------------------------------------------

--
-- Table structure for table `message_reactions`
--

CREATE TABLE `message_reactions` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reaction_type` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `platform_settings`
--

CREATE TABLE `platform_settings` (
  `id` int(11) NOT NULL,
  `site_name` varchar(255) NOT NULL DEFAULT 'CITSA Platform',
  `site_description` text DEFAULT NULL,
  `max_file_size` int(11) NOT NULL DEFAULT 10,
  `allowed_file_types` text DEFAULT 'jpg,jpeg,png,gif,pdf,doc,docx',
  `maintenance_mode` tinyint(1) NOT NULL DEFAULT 0,
  `user_registration` tinyint(1) NOT NULL DEFAULT 1,
  `maintenance_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `platform_settings`
--

INSERT INTO `platform_settings` (`id`, `site_name`, `site_description`, `max_file_size`, `allowed_file_types`, `maintenance_mode`, `user_registration`, `maintenance_message`, `created_at`, `updated_at`) VALUES
(1, 'CITSA Platform', 'Computer Science & IT Student Association', 10, 'jpg,jpeg,png,gif,pdf,doc,docx', 0, 1, 'Platform is currently under maintenance. Please check back later.', '2025-08-20 10:45:50', '2025-08-20 10:45:50');

-- --------------------------------------------------------

--
-- Table structure for table `room_members`
--

CREATE TABLE `room_members` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `room_messages`
--

CREATE TABLE `room_messages` (
  `message_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(225) NOT NULL,
  `first_name` varchar(100) NOT NULL DEFAULT '',
  `last_name` varchar(100) NOT NULL DEFAULT '',
  `student_id` varchar(225) NOT NULL,
  `email` varchar(225) NOT NULL DEFAULT '',
  `password` varchar(1000) NOT NULL,
  `profile_image` varchar(225) DEFAULT 'default-avatar.png',
  `last_seen` datetime NOT NULL DEFAULT current_timestamp(),
  `programme` varchar(225) NOT NULL,
  `user_type` enum('student','alumni') NOT NULL DEFAULT 'student',
  `status` enum('active','inactive','banned') NOT NULL DEFAULT 'active',
  `about` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `online_status` enum('online','offline','away') DEFAULT 'offline'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `first_name`, `last_name`, `student_id`, `email`, `password`, `profile_image`, `last_seen`, `programme`, `user_type`, `status`, `about`, `created_at`, `online_status`) VALUES
(5, 'eden', 'Eden', 'Ofori', 'ps/itc/21/0096', 'edenofori1@student.ucc.edu.gh', '$2y$10$.Tz1YpV0YOmqfq1Gydcy0uIA7.PvF/CWIKd6Dy5O1rFAk3eAXA9mi', 'profile_5_1753988679.jpg', '2025-08-20 12:29:15', 'B.Sc. Information Technology', 'student', 'active', 'i love my self', '2025-07-30 20:38:29', 'offline'),
(6, 'olivia', 'Olivia', 'Nyarko', 'ps/itc/21/0068', 'olivia@student.ucc.edu.gh', '$2y$10$dFIWLa4BqIN91Y0l4V.xY.tiZBhhD5sXOY8iqgoEh8EAuDuKd9i8y', 'profile_6_1753988735.jpg', '2025-08-20 10:04:39', 'B.Sc. Information Technology', 'student', 'active', '', '2025-07-31 07:32:08', 'away'),
(7, 'Mickey', 'Micheal', 'Mireku', 'Ps/itc/21/0112', 'mickey@student.ucc.edu.gh', '$2y$10$ON0S9FYuLWnV9VGjbYv1NOfKR/uZxG3EInOP0YMBDnqFKf6ek73WG', 'default-avatar.png', '2025-08-16 16:47:13', 'B.Sc. Information Technology', 'student', 'active', NULL, '2025-07-31 14:22:22', 'online'),
(8, 'king', 'King', 'Kyere', 'ps/itc/20/0100', 'king@gmail.com', '$2y$10$oGvmfJcQvh2.bJOT3Ap2oe2zE18/g/v54VIkd3Ml7TsDL2aQsT2gG', '689d3e208ae6e.jpg', '2025-08-14 01:39:07', 'B.Sc. Information Technology', 'alumni', 'active', NULL, '2025-08-14 01:38:40', 'offline');

-- --------------------------------------------------------

--
-- Table structure for table `user_clubs`
--

CREATE TABLE `user_clubs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `club_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_at` timestamp NULL DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `joined_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `admin_roles`
--
ALTER TABLE `admin_roles`
  ADD PRIMARY KEY (`role_id`);

--
-- Indexes for table `authorized_students`
--
ALTER TABLE `authorized_students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD KEY `idx_name_search` (`first_name`,`last_name`),
  ADD KEY `idx_student_id` (`student_id`);

--
-- Indexes for table `chats`
--
ALTER TABLE `chats`
  ADD PRIMARY KEY (`chat_id`),
  ADD KEY `to_id` (`to_id`),
  ADD KEY `idx_from_to` (`from_id`,`to_id`);

--
-- Indexes for table `chat_rooms`
--
ALTER TABLE `chat_rooms`
  ADD PRIMARY KEY (`room_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `chat_room_messages`
--
ALTER TABLE `chat_room_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `idx_chat_room_messages_room_created` (`room_id`,`created_at`),
  ADD KEY `idx_read_status` (`read_status`);

--
-- Indexes for table `chat_room_message_reads`
--
ALTER TABLE `chat_room_message_reads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_room` (`user_id`,`room_id`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `chat_room_online_users`
--
ALTER TABLE `chat_room_online_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_room` (`user_id`,`room_id`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `last_activity` (`last_activity`);

--
-- Indexes for table `clubs`
--
ALTER TABLE `clubs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`conversation_id`),
  ADD KEY `user_2` (`user_2`),
  ADD KEY `idx_users` (`user_1`,`user_2`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`event_id`);

--
-- Indexes for table `event_attachments`
--
ALTER TABLE `event_attachments`
  ADD PRIMARY KEY (`attachment_id`);

--
-- Indexes for table `friends`
--
ALTER TABLE `friends`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_friendship` (`user_id`,`friend_id`),
  ADD KEY `friend_id` (`friend_id`);

--
-- Indexes for table `friend_requests`
--
ALTER TABLE `friend_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_friendship` (`sender_id`,`receiver_id`),
  ADD KEY `receiver_id` (`receiver_id`),
  ADD KEY `idx_friendship_status` (`status`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conversation` (`conversation_id`),
  ADD KEY `idx_sender` (`sender_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `message_reactions`
--
ALTER TABLE `message_reactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_reaction` (`message_id`,`user_id`,`reaction_type`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `platform_settings`
--
ALTER TABLE `platform_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `room_members`
--
ALTER TABLE `room_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_member` (`room_id`,`user_id`),
  ADD KEY `idx_user_status` (`user_id`,`status`);

--
-- Indexes for table `room_messages`
--
ALTER TABLE `room_messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_room_time` (`room_id`,`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_clubs`
--
ALTER TABLE `user_clubs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_club_unique` (`user_id`,`club_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `club_id` (`club_id`),
  ADD KEY `idx_user_clubs_user` (`user_id`),
  ADD KEY `idx_user_clubs_club` (`club_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_club_id` (`club_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `admin_roles`
--
ALTER TABLE `admin_roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `authorized_students`
--
ALTER TABLE `authorized_students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `chats`
--
ALTER TABLE `chats`
  MODIFY `chat_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_rooms`
--
ALTER TABLE `chat_rooms`
  MODIFY `room_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_room_messages`
--
ALTER TABLE `chat_room_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `chat_room_message_reads`
--
ALTER TABLE `chat_room_message_reads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `chat_room_online_users`
--
ALTER TABLE `chat_room_online_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16111;

--
-- AUTO_INCREMENT for table `clubs`
--
ALTER TABLE `clubs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `conversation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `event_attachments`
--
ALTER TABLE `event_attachments`
  MODIFY `attachment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `friends`
--
ALTER TABLE `friends`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `friend_requests`
--
ALTER TABLE `friend_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `message_reactions`
--
ALTER TABLE `message_reactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `platform_settings`
--
ALTER TABLE `platform_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `room_members`
--
ALTER TABLE `room_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `room_messages`
--
ALTER TABLE `room_messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `user_clubs`
--
ALTER TABLE `user_clubs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `chats`
--
ALTER TABLE `chats`
  ADD CONSTRAINT `chats_ibfk_1` FOREIGN KEY (`from_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chats_ibfk_2` FOREIGN KEY (`to_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_rooms`
--
ALTER TABLE `chat_rooms`
  ADD CONSTRAINT `chat_rooms_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `admins` (`admin_id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_room_messages`
--
ALTER TABLE `chat_room_messages`
  ADD CONSTRAINT `chat_room_messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `conversations`
--
ALTER TABLE `conversations`
  ADD CONSTRAINT `conversations_ibfk_1` FOREIGN KEY (`user_1`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversations_ibfk_2` FOREIGN KEY (`user_2`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `friends`
--
ALTER TABLE `friends`
  ADD CONSTRAINT `friends_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `friends_ibfk_2` FOREIGN KEY (`friend_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `friend_requests`
--
ALTER TABLE `friend_requests`
  ADD CONSTRAINT `friend_requests_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `friend_requests_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`conversation_id`),
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `message_reactions`
--
ALTER TABLE `message_reactions`
  ADD CONSTRAINT `message_reactions_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `message_reactions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `room_members`
--
ALTER TABLE `room_members`
  ADD CONSTRAINT `room_members_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `chat_rooms` (`room_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `room_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `room_messages`
--
ALTER TABLE `room_messages`
  ADD CONSTRAINT `room_messages_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `chat_rooms` (`room_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `room_messages_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_clubs`
--
ALTER TABLE `user_clubs`
  ADD CONSTRAINT `user_clubs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
