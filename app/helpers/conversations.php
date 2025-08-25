<?php
if (!function_exists('getConversations')) {
    function getConversations($user_id, $pdo) {
        $stmt = $pdo->prepare("
            SELECT c.*, u.first_name, u.last_name, u.username, u.profile_image, u.status
            FROM conversations c
            JOIN users u ON (c.user_1 = u.user_id OR c.user_2 = u.user_id)
            WHERE (c.user_1 = ? OR c.user_2 = ?) AND u.user_id != ?
        ");
        $stmt->execute([$user_id, $user_id, $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('getConversation')) {
    function getConversation($user_1, $user_2, $pdo) {
        $stmt = $pdo->prepare("
            SELECT * FROM conversations 
            WHERE (user_1 = ? AND user_2 = ?) OR (user_1 = ? AND user_2 = ?)
        ");
        $stmt->execute([$user_1, $user_2, $user_2, $user_1]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('createConversation')) {
    function createConversation($user_1, $user_2, $pdo) {
        $stmt = $pdo->prepare("INSERT INTO conversations (user_1, user_2) VALUES (?, ?)");
        $stmt->execute([$user_1, $user_2]);
        return $pdo->lastInsertId();
    }
}

if (!function_exists('updateLastMessage')) {
    function updateLastMessage($conversation_id, $pdo) {
        // For now, we'll just return true since last_message_time column doesn't exist
        // This function can be updated when the database schema is modified
        return true;
    }
}
?> 