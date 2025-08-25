<?php
/**
 * Chat Room Online Status Helper Functions
 * Handles tracking of online users in chat rooms
 */

if (!function_exists('updateChatRoomOnlineStatus')) {
    /**
     * Update user's online status in a specific chat room
     * @param int $user_id User ID
     * @param string $room_id Room ID
     * @param PDO $pdo Database connection
     * @return bool Success status
     */
    function updateChatRoomOnlineStatus($user_id, $room_id, $pdo) {
        try {
            // First, check if the table exists, if not create it
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `chat_room_online_users` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `user_id` int(11) NOT NULL,
                    `room_id` varchar(100) NOT NULL,
                    `last_activity` datetime DEFAULT CURRENT_TIMESTAMP,
                    `is_active` tinyint(1) DEFAULT 1,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `user_room` (`user_id`, `room_id`),
                    KEY `room_id` (`room_id`),
                    KEY `last_activity` (`last_activity`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
            
            // Update or insert user's online status
            $stmt = $pdo->prepare("
                INSERT INTO chat_room_online_users (user_id, room_id, last_activity, is_active) 
                VALUES (?, ?, NOW(), 1) 
                ON DUPLICATE KEY UPDATE 
                last_activity = NOW(), 
                is_active = 1
            ");
            
            return $stmt->execute([$user_id, $room_id]);
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('getChatRoomOnlineUsers')) {
    /**
     * Get number of online users in a specific chat room
     * @param string $room_id Room ID
     * @param PDO $pdo Database connection
     * @return int Number of online users
     */
    function getChatRoomOnlineUsers($room_id, $pdo) {
        try {
            // First, check if the table exists, if not create it
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `chat_room_online_users` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `user_id` int(11) NOT NULL,
                    `room_id` varchar(100) NOT NULL,
                    `last_activity` datetime DEFAULT CURRENT_TIMESTAMP,
                    `is_active` tinyint(1) DEFAULT 1,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `user_room` (`user_id`, `room_id`),
                    KEY `room_id` (`room_id`),
                    KEY `last_activity` (`last_activity`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
            
            // Get count of active users in the last 5 minutes
            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT user_id) as count 
                FROM chat_room_online_users 
                WHERE room_id = ? 
                AND last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                AND is_active = 1
            ");
            
            $stmt->execute([$room_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? intval($result['count']) : 0;
        } catch (Exception $e) {
            return 0;
        }
    }
}

if (!function_exists('getChatRoomOnlineUsersList')) {
    /**
     * Get list of online users in a specific chat room
     * @param string $room_id Room ID
     * @param PDO $pdo Database connection
     * @return array Array of online users
     */
    function getChatRoomOnlineUsersList($room_id, $pdo) {
        try {
            // First, check if the table exists, if not create it
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `chat_room_online_users` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `user_id` int(11) NOT NULL,
                    `room_id` varchar(100) NOT NULL,
                    `last_activity` datetime DEFAULT CURRENT_TIMESTAMP,
                    `is_active` tinyint(1) DEFAULT 1,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `user_room` (`user_id`, `room_id`),
                    KEY `room_id` (`room_id`),
                    KEY `last_activity` (`last_activity`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
            
            // Get list of active users in the last 5 minutes
            $stmt = $pdo->prepare("
                SELECT cru.user_id, u.username, u.first_name, u.last_name, u.profile_image, u.user_type, cru.last_activity
                FROM chat_room_online_users cru
                JOIN users u ON cru.user_id = u.user_id
                WHERE cru.room_id = ? 
                AND cru.last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                AND cru.is_active = 1
                ORDER BY cru.last_activity DESC
            ");
            
            $stmt->execute([$room_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}

if (!function_exists('cleanupInactiveChatRoomUsers')) {
    /**
     * Clean up inactive users from chat rooms (mark as offline)
     * @param PDO $pdo Database connection
     * @return bool Success status
     */
    function cleanupInactiveChatRoomUsers($pdo) {
        try {
            // Mark users as inactive if they haven't been active in the last 5 minutes
            $stmt = $pdo->prepare("
                UPDATE chat_room_online_users 
                SET is_active = 0 
                WHERE last_activity < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                AND is_active = 1
            ");
            
            return $stmt->execute();
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('removeUserFromChatRoom')) {
    /**
     * Remove user from a specific chat room's online list
     * @param int $user_id User ID
     * @param string $room_id Room ID
     * @param PDO $pdo Database connection
     * @return bool Success status
     */
    function removeUserFromChatRoom($user_id, $room_id, $pdo) {
        try {
            $stmt = $pdo->prepare("
                UPDATE chat_room_online_users 
                SET is_active = 0 
                WHERE user_id = ? AND room_id = ?
            ");
            
            return $stmt->execute([$user_id, $room_id]);
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('getAllChatRoomsOnlineCount')) {
    /**
     * Get online count for all chat rooms
     * @param PDO $pdo Database connection
     * @return array Array of room_id => online_count
     */
    function getAllChatRoomsOnlineCount($pdo) {
        try {
            // First, check if the table exists, if not create it
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `chat_room_online_users` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `user_id` int(11) NOT NULL,
                    `room_id` varchar(100) NOT NULL,
                    `last_activity` datetime DEFAULT CURRENT_TIMESTAMP,
                    `is_active` tinyint(1) DEFAULT 1,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `user_room` (`user_id`, `room_id`),
                    KEY `room_id` (`room_id`),
                    KEY `last_activity` (`last_activity`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
            
            // Get count for all rooms
            $stmt = $pdo->prepare("
                SELECT room_id, COUNT(DISTINCT user_id) as count 
                FROM chat_room_online_users 
                WHERE last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                AND is_active = 1
                GROUP BY room_id
            ");
            
            $stmt->execute();
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

if (!function_exists('getLastRoomMessage')) {
    /**
     * Get the last message for a specific chat room
     * @param string $room_id Room ID
     * @param PDO $pdo Database connection
     * @return array|null Last message data or null if no messages
     */
    function getLastRoomMessage($room_id, $pdo) {
        try {
            $stmt = $pdo->prepare("
                SELECT m.*, u.username, u.first_name, u.last_name
                FROM chat_room_messages m
                JOIN users u ON m.sender_id = u.user_id
                WHERE m.room_id = ?
                ORDER BY m.created_at DESC
                LIMIT 1
            ");
            
            $stmt->execute([$room_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                // Format the message for display
                $message = $result['message'];
                if (strlen($message) > 50) {
                    $message = substr($message, 0, 47) . '...';
                }
                
                return [
                    'message' => $message,
                    'username' => $result['username'],
                    'first_name' => $result['first_name'],
                    'last_name' => $result['last_name'],
                    'created_at' => $result['created_at'],
                    'message_type' => $result['message_type']
                ];
            }
            
            return null;
        } catch (Exception $e) {
            return null;
        }
    }
}
?>
