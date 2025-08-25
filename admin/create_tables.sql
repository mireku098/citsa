-- Add new tables for administrator management and events

-- Table for administrator roles and permissions
CREATE TABLE `admin_roles` (
  `role_id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  `description` text,
  `permissions` text,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default roles
INSERT INTO `admin_roles` (`role_name`, `description`, `permissions`) VALUES
('super_admin', 'Full system access', 'all'),
('admin', 'Administrative access', 'users,chat_rooms,clubs,authorized_users,events'),
('editor', 'Content management access', 'events,announcements');

-- Add role_id column to admins table
ALTER TABLE `admins` ADD COLUMN `role_id` int(11) NOT NULL DEFAULT 2 AFTER `position`;
ALTER TABLE `admins` ADD FOREIGN KEY (`role_id`) REFERENCES `admin_roles`(`role_id`);

-- Table for events/announcements
CREATE TABLE `events` (
  `event_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `event_date` date NOT NULL,
  `event_time` time DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `event_type` enum('event','announcement','meeting','workshop') NOT NULL DEFAULT 'event',
  `status` enum('draft','published','cancelled') NOT NULL DEFAULT 'draft',
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`event_id`),
  FOREIGN KEY (`created_by`) REFERENCES `admins`(`admin_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table for event attachments/files
CREATE TABLE `event_attachments` (
  `attachment_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `file_size` int(11) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`attachment_id`),
  FOREIGN KEY (`event_id`) REFERENCES `events`(`event_id`) ON DELETE CASCADE,
  FOREIGN KEY (`uploaded_by`) REFERENCES `admins`(`admin_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Update existing admin to have super_admin role
UPDATE `admins` SET `role_id` = 1 WHERE `admin_id` = 1;
