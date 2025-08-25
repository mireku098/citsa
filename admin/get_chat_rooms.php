<?php
session_start();
require_once '../app/db.conn.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    // Get all chat rooms with creator information and member count
    $stmt = $pdo->query("
        SELECT 
            cr.room_id,
            cr.room_name,
            cr.description,
            cr.department,
            cr.created_at,
            u.username as creator_name,
            u.first_name as creator_first_name,
            u.last_name as creator_last_name,
            (SELECT COUNT(*) FROM room_members rm WHERE rm.room_id = cr.room_id AND rm.status = 'approved') as member_count
        FROM chat_rooms cr
        LEFT JOIN users u ON cr.created_by = u.user_id
        ORDER BY cr.created_at DESC
    ");
    
    $chat_rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($chat_rooms);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
