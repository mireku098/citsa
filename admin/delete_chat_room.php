<?php
session_start();
require_once '../app/db.conn.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    try {
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        $room_id = $input['room_id'] ?? null;
        
        if (!$room_id) {
            $response['message'] = 'Room ID is required';
        } else {
            // Check if room exists
            $stmt = $pdo->prepare("SELECT room_id, room_name FROM chat_rooms WHERE room_id = ?");
            $stmt->execute([$room_id]);
            $room = $stmt->fetch();
            
            if (!$room) {
                $response['message'] = 'Chat room not found';
            } else {
                // Delete chat room (you might want to soft delete instead)
                $stmt = $pdo->prepare("DELETE FROM chat_rooms WHERE room_id = ?");
                
                if ($stmt->execute([$room_id])) {
                    $response['success'] = true;
                    $response['message'] = 'Chat room deleted successfully';
                } else {
                    $response['message'] = 'Failed to delete chat room';
                }
            }
        }
    } catch (PDOException $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
