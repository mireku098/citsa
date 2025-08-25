<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Include database connection and helpers
include 'app/db.conn.php';
include 'app/helpers/notifications.php';

// Get pending friend requests
$pending_requests = getPendingFriendRequests($_SESSION['user_id'], $pdo);
$request_count = getPendingFriendRequestsCount($_SESSION['user_id'], $pdo);

// Format the data for the frontend
$formatted_requests = [];
foreach ($pending_requests as $request) {
    $formatted_requests[] = [
        'id' => $request['id'],
        'sender_id' => $request['sender_id'],
        'sender_name' => $request['first_name'] . ' ' . $request['last_name'],
        'sender_username' => $request['username'],
        'sender_profile_image' => $request['profile_image'] ?: 'default-avatar.png',
        'sender_programme' => $request['programme'],
        'sender_student_id' => $request['student_id'],
        'created_at' => $request['created_at'],
        'time_ago' => timeAgo($request['created_at'])
    ];
}

echo json_encode([
    'success' => true,
    'friend_requests' => $formatted_requests,
    'request_count' => $request_count
]);

// Helper function for time ago (if not already included)
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
?> 