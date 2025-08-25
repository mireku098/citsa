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
        // Get form data
        $room_name = trim($_POST['room_name']);
        $description = trim($_POST['description'] ?? '');
        $department = trim($_POST['department']);
        
        // Validate required fields
        if (empty($room_name) || empty($department)) {
            $response['message'] = 'Room name and department are required';
        } else {
            // Check if room name already exists
            $stmt = $pdo->prepare("SELECT room_id FROM chat_rooms WHERE room_name = ?");
            $stmt->execute([$room_name]);
            if ($stmt->fetch()) {
                $response['message'] = 'Room name already exists';
            } else {
                // Insert new chat room
                $stmt = $pdo->prepare("
                    INSERT INTO chat_rooms (room_name, description, department, created_by, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                
                if ($stmt->execute([$room_name, $description, $department, $_SESSION['admin_id']])) {
                    $response['success'] = true;
                    $response['message'] = 'Chat room created successfully';
                } else {
                    $response['message'] = 'Failed to create chat room';
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
