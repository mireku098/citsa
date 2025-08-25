<?php
// Prevent any output before JSON response
error_reporting(0);
ini_set('display_errors', 0);

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Include database connection and helpers
try {
    include 'app/db.conn.php';
    include 'app/helpers/notifications.php';
    include 'app/helpers/timeAgo.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit();
}

try {
    // Get unread messages count and recent conversations
    $unread_count = getUnreadMessagesCount($_SESSION['user_id'], $pdo);
    $recent_conversations = getRecentConversationsWithUnread($_SESSION['user_id'], $pdo);

    // Format the data for the frontend
    $formatted_conversations = [];
    foreach ($recent_conversations as $conversation) {
        $formatted_conversations[] = [
            'conversation_id' => $conversation['conversation_id'],
            'sender_name' => $conversation['first_name'] . ' ' . $conversation['last_name'],
            'sender_username' => $conversation['username'],
            'sender_profile_image' => $conversation['profile_image'] ?: 'default-avatar.png',
            'unread_count' => $conversation['unread_count'],
            'last_message' => $conversation['last_message'],
            'last_message_time' => $conversation['last_message_time'],
            'time_ago' => timeAgo($conversation['last_message_time'])
        ];
    }

    echo json_encode([
        'success' => true,
        'conversations' => $formatted_conversations,
        'unread_count' => $unread_count
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error loading messages']);
}

// Helper function for time ago (if not already included)
if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        $time = time() - strtotime($datetime);
        
        if ($time < 60) {
            return 'Just now';
        } elseif ($time < 3600) {
            $minutes = floor($time / 60);
            return $minutes . ' min' . ($minutes > 1 ? 's' : '') . ' ago';
        } elseif ($time < 86400) {
            $hours = floor($time / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } else {
            $days = floor($time / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        }
    }
}
?> 