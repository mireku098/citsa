<?php
/**
 * Notification Helper Functions
 * Handles friend request notifications and other notification-related functionality
 */

/**
 * Get pending friend requests count for a user
 * @param int $user_id The user ID
 * @param PDO $pdo Database connection
 * @return int Number of pending friend requests
 */
if (!function_exists('getPendingFriendRequestsCount')) {
    function getPendingFriendRequestsCount($user_id, $pdo) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM friend_requests 
            WHERE receiver_id = ? AND status = 'pending'
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['count'];
    }
}

/**
 * Get pending friend requests for a user
 * @param int $user_id The user ID
 * @param PDO $pdo Database connection
 * @return array Array of pending friend requests with sender details
 */
if (!function_exists('getPendingFriendRequests')) {
    function getPendingFriendRequests($user_id, $pdo) {
        $stmt = $pdo->prepare("
            SELECT fr.*, u.first_name, u.last_name, u.username, u.profile_image, u.programme, u.student_id
            FROM friend_requests fr 
            JOIN users u ON fr.sender_id = u.user_id 
            WHERE fr.receiver_id = ? AND fr.status = 'pending'
            ORDER BY fr.created_at DESC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/**
 * Accept a friend request
 * @param int $request_id The friend request ID
 * @param int $current_user_id The current user ID
 * @param PDO $pdo Database connection
 * @return bool Success status
 */
if (!function_exists('acceptFriendRequest')) {
    function acceptFriendRequest($request_id, $current_user_id, $pdo) {
        try {
            $pdo->beginTransaction();
            
            // Get the request details
            $stmt = $pdo->prepare("SELECT sender_id FROM friend_requests WHERE id = ? AND receiver_id = ? AND status = 'pending'");
            $stmt->execute([$request_id, $current_user_id]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$request) {
                return false;
            }
            
            $sender_id = $request['sender_id'];
            
            // Update request status to accepted
            $stmt = $pdo->prepare("UPDATE friend_requests SET status = 'accepted', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$request_id]);
            
            // Check if friendship already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)");
            $stmt->execute([$current_user_id, $sender_id, $sender_id, $current_user_id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing['count'] == 0) {
                // Add to friends table (bidirectional friendship) only if it doesn't exist
                $stmt = $pdo->prepare("INSERT INTO friends (user_id, friend_id, created_at) VALUES (?, ?, NOW()), (?, ?, NOW())");
                $stmt->execute([$current_user_id, $sender_id, $sender_id, $current_user_id]);
            }
            
            $pdo->commit();
            return true;
        } catch (Exception $e) {
            $pdo->rollBack();
            return false;
        }
    }
}

/**
 * Reject a friend request
 * @param int $request_id The friend request ID
 * @param int $current_user_id The current user ID
 * @param PDO $pdo Database connection
 * @return bool Success status
 */
if (!function_exists('rejectFriendRequest')) {
    function rejectFriendRequest($request_id, $current_user_id, $pdo) {
        try {
            $stmt = $pdo->prepare("UPDATE friend_requests SET status = 'rejected', updated_at = NOW() WHERE id = ? AND receiver_id = ? AND status = 'pending'");
            $stmt->execute([$request_id, $current_user_id]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
}

/**
 * Get total unread messages count for a user
 * @param int $user_id The user ID
 * @param PDO $pdo Database connection
 * @return int Number of unread messages
 */
if (!function_exists('getUnreadMessagesCount')) {
    function getUnreadMessagesCount($user_id, $pdo) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM messages m
            JOIN conversations c ON m.conversation_id = c.conversation_id
            WHERE (c.user_1 = ? OR c.user_2 = ?) 
            AND m.sender_id != ? 
            AND m.read_status = 0
        ");
        $stmt->execute([$user_id, $user_id, $user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['count'];
    }
}

/**
 * Get unread messages for a user with sender details
 * @param int $user_id The user ID
 * @param PDO $pdo Database connection
 * @return array Array of unread messages with sender details
 */
if (!function_exists('getUnreadMessages')) {
    function getUnreadMessages($user_id, $pdo) {
        $stmt = $pdo->prepare("
            SELECT m.*, u.first_name, u.last_name, u.username, u.profile_image, u.programme,
                   c.conversation_id, 
                   (SELECT COUNT(*) FROM messages WHERE conversation_id = c.conversation_id AND sender_id != ? AND read_status = 0) as unread_count
            FROM messages m
            JOIN conversations c ON m.conversation_id = c.conversation_id
            JOIN users u ON m.sender_id = u.user_id
            WHERE (c.user_1 = ? OR c.user_2 = ?) 
            AND m.sender_id != ? 
            AND m.read_status = 0
            ORDER BY m.created_at DESC
        ");
        $stmt->execute([$user_id, $user_id, $user_id, $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/**
 * Get recent conversations with unread counts
 * @param int $user_id The user ID
 * @param PDO $pdo Database connection
 * @return array Array of conversations with unread counts
 */
if (!function_exists('getRecentConversationsWithUnread')) {
    function getRecentConversationsWithUnread($user_id, $pdo) {
        $stmt = $pdo->prepare("
            SELECT c.conversation_id, 
                   u.first_name, u.last_name, u.username, u.profile_image, u.programme,
                   (SELECT COUNT(*) FROM messages WHERE conversation_id = c.conversation_id AND sender_id != ? AND read_status = 0) as unread_count,
                   (SELECT message FROM messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message,
                   (SELECT created_at FROM messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message_time
            FROM conversations c
            JOIN users u ON (c.user_1 = u.user_id OR c.user_2 = u.user_id)
            WHERE (c.user_1 = ? OR c.user_2 = ?) AND u.user_id != ?
            HAVING unread_count > 0
            ORDER BY last_message_time DESC
            LIMIT 10
        ");
        $stmt->execute([$user_id, $user_id, $user_id, $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

?> 