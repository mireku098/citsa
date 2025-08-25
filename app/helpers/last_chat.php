<?php
if (!function_exists('getLastChat')) {
    function getLastChat($conversation_id, $pdo) {
        $stmt = $pdo->prepare("
            SELECT * FROM messages 
            WHERE conversation_id = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$conversation_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('getUnreadCount')) {
    function getUnreadCount($user_id, $conversation_id, $pdo) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM messages 
            WHERE conversation_id = ? AND sender_id != ? AND read_status = 0
        ");
        $stmt->execute([$conversation_id, $user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }
}

if (!function_exists('markAsRead')) {
    function markAsRead($conversation_id, $user_id, $pdo) {
        $stmt = $pdo->prepare("
            UPDATE messages 
            SET read_status = 1 
            WHERE conversation_id = ? AND sender_id != ? AND read_status = 0
        ");
        return $stmt->execute([$conversation_id, $user_id]);
    }
}
?> 