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

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get the action and request ID
$action = $_POST['action'] ?? '';
$request_id = (int)($_POST['request_id'] ?? 0);

if (empty($action) || $request_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

$current_user_id = $_SESSION['user_id'];
$success = false;
$message = '';

try {
    switch ($action) {
        case 'accept':
            $success = acceptFriendRequest($request_id, $current_user_id, $pdo);
            $message = $success ? 'Friend request accepted successfully!' : 'Failed to accept friend request';
            break;
            
        case 'reject':
            $success = rejectFriendRequest($request_id, $current_user_id, $pdo);
            $message = $success ? 'Friend request rejected' : 'Failed to reject friend request';
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit();
    }
    
    // Get updated count
    $new_count = getPendingFriendRequestsCount($current_user_id, $pdo);
    
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'new_count' => $new_count
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
?> 