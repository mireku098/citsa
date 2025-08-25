<?php
/**
 * Chat Room Notifications Helper Functions
 * Handles notification system for chat rooms
 */

if (!function_exists('markChatRoomMessagesAsRead')) {
    /**
     * Mark all messages in a chat room as read for a specific user
     * @param int $user_id User ID
     * @param string $room_id Room ID
     * @param PDO $pdo Database connection
     * @return bool Success status
     */
    function markChatRoomMessagesAsRead($user_id, $room_id, $pdo) {
        try {
            // First, check if the table exists, if not create it
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `chat_room_message_reads` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `user_id` int(11) NOT NULL,
                    `room_id` varchar(100) NOT NULL,
                    `message_id` int(11) NOT NULL,
                    `read_at` datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `user_message` (`user_id`, `message_id`),
                    KEY `user_room` (`user_id`, `room_id`),
                    KEY `room_id` (`room_id`),
                    KEY `read_at` (`read_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
            
            // Mark all unread messages in this room as read
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO chat_room_message_reads (user_id, room_id, message_id, read_at)
                SELECT ?, ?, m.id, NOW()
                FROM chat_room_messages m
                WHERE m.room_id = ?
                AND m.sender_id != ?
                AND m.id NOT IN (
                    SELECT message_id 
                    FROM chat_room_message_reads 
                    WHERE user_id = ? AND room_id = ?
                )
            ");
            
            return $stmt->execute([$user_id, $room_id, $room_id, $user_id, $user_id, $room_id]);
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('getChatRoomUnreadCount')) {
    /**
     * Get count of unread messages in a chat room for a specific user
     * @param int $user_id User ID
     * @param string $room_id Room ID
     * @param PDO $pdo Database connection
     * @return int Number of unread messages
     */
    function getChatRoomUnreadCount($user_id, $room_id, $pdo) {
        try {
            // First, check if the table exists, if not create it
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `chat_room_message_reads` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `user_id` int(11) NOT NULL,
                    `room_id` varchar(100) NOT NULL,
                    `message_id` int(11) NOT NULL,
                    `read_at` datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `user_message` (`user_id`, `message_id`),
                    KEY `user_room` (`user_id`, `room_id`),
                    KEY `room_id` (`room_id`),
                    KEY `read_at` (`read_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
            
            // Get count of unread messages
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM chat_room_messages m
                WHERE m.room_id = ?
                AND m.sender_id != ?
                AND m.id NOT IN (
                    SELECT message_id 
                    FROM chat_room_message_reads 
                    WHERE user_id = ? AND room_id = ?
                )
            ");
            
            $stmt->execute([$room_id, $user_id, $user_id, $room_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? intval($result['count']) : 0;
        } catch (Exception $e) {
            return 0;
        }
    }
}

if (!function_exists('getAllChatRoomsUnreadCount')) {
    /**
     * Get unread count for all chat rooms for a specific user
     * @param int $user_id User ID
     * @param PDO $pdo Database connection
     * @return array Array of room_id => unread_count
     */
    function getAllChatRoomsUnreadCount($user_id, $pdo) {
        try {
            // First, check if the table exists, if not create it
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `chat_room_message_reads` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `user_id` int(11) NOT NULL,
                    `room_id` varchar(100) NOT NULL,
                    `message_id` int(11) NOT NULL,
                    `read_at` datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `user_message` (`user_id`, `message_id`),
                    KEY `user_room` (`user_id`, `room_id`),
                    KEY `room_id` (`room_id`),
                    KEY `read_at` (`read_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
            
            // Get count for all rooms
            $stmt = $pdo->prepare("
                SELECT m.room_id, COUNT(*) as count
                FROM chat_room_messages m
                WHERE m.sender_id != ?
                AND m.id NOT IN (
                    SELECT message_id 
                    FROM chat_room_message_reads 
                    WHERE user_id = ?
                )
                GROUP BY m.room_id
            ");
            
            $stmt->execute([$user_id, $user_id]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $counts = [];
            foreach ($results as $result) {
                $counts[$result['room_id']] = intval($result['count']);
            }
            
            return $counts;
        } catch (Exception $e) {
            return [];
        }
    }
}

if (!function_exists('markSpecificChatRoomMessageAsRead')) {
    /**
     * Mark a specific message as read
     * @param int $user_id User ID
     * @param int $message_id Message ID
     * @param string $room_id Room ID
     * @param PDO $pdo Database connection
     * @return bool Success status
     */
    function markSpecificChatRoomMessageAsRead($user_id, $message_id, $room_id, $pdo) {
        try {
            // First, check if the table exists, if not create it
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `chat_room_message_reads` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `user_id` int(11) NOT NULL,
                    `room_id` varchar(100) NOT NULL,
                    `message_id` int(11) NOT NULL,
                    `read_at` datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `user_message` (`user_id`, `message_id`),
                    KEY `user_room` (`user_id`, `room_id`),
                    KEY `room_id` (`room_id`),
                    KEY `read_at` (`read_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
            
            // Mark specific message as read
            $stmt = $pdo->prepare("
                INSERT INTO chat_room_message_reads (user_id, room_id, message_id, read_at) 
                VALUES (?, ?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE read_at = NOW()
            ");
            
            return $stmt->execute([$user_id, $room_id, $message_id]);
        } catch (Exception $e) {
            return false;
        }
    }
}
?>
