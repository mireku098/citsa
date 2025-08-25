<?php
session_start();
require_once 'app/db.conn.php';
require_once 'app/helpers/chat_room_online.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $room_id = $_POST['room_id'] ?? '';
    $user_id = $_SESSION['user_id'];
    
    try {
        switch ($action) {
            case 'update_online_status':
                if (empty($room_id)) {
                    throw new Exception('Room ID is required');
                }
                
                // Update user's online status in the chat room
                $success = updateChatRoomOnlineStatus($user_id, $room_id, $pdo);
                
                if ($success) {
                    // Get updated online count
                    $online_count = getChatRoomOnlineUsers($room_id, $pdo);
                    
                    echo json_encode([
                        'success' => true,
                        'online_count' => $online_count,
                        'message' => 'Online status updated'
                    ]);
                } else {
                    throw new Exception('Failed to update online status');
                }
                break;
                
            case 'get_online_count':
                if (empty($room_id)) {
                    throw new Exception('Room ID is required');
                }
                
                $online_count = getChatRoomOnlineUsers($room_id, $pdo);
                
                echo json_encode([
                    'success' => true,
                    'online_count' => $online_count
                ]);
                break;
                
            case 'get_online_users':
                if (empty($room_id)) {
                    throw new Exception('Room ID is required');
                }
                
                $online_users = getChatRoomOnlineUsersList($room_id, $pdo);
                
                echo json_encode([
                    'success' => true,
                    'online_users' => $online_users
                ]);
                break;
                
            case 'leave_room':
                if (empty($room_id)) {
                    throw new Exception('Room ID is required');
                }
                
                $success = removeUserFromChatRoom($user_id, $room_id, $pdo);
                
                if ($success) {
                    // Get updated online count
                    $online_count = getChatRoomOnlineUsers($room_id, $pdo);
                    
                    echo json_encode([
                        'success' => true,
                        'online_count' => $online_count,
                        'message' => 'Left room successfully'
                    ]);
                } else {
                    throw new Exception('Failed to leave room');
                }
                break;
                
            case 'get_last_message':
                if (empty($room_id)) {
                    throw new Exception('Room ID is required');
                }
                
                $last_message = getLastRoomMessage($room_id, $pdo);
                
                echo json_encode([
                    'success' => true,
                    'last_message' => $last_message
                ]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
