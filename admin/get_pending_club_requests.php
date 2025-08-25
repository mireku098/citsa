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
    // Get pending club join requests with user and club information
    $stmt = $pdo->query("
        SELECT 
            rm.id,
            CONCAT(u.first_name, ' ', u.last_name) as user_name,
            u.student_id,
            c.name as club_name,
            rm.joined_at
        FROM room_members rm
        JOIN users u ON rm.user_id = u.user_id
        JOIN clubs c ON rm.room_id = c.id
        WHERE rm.status = 'pending'
        ORDER BY rm.joined_at DESC
    ");
    
    $pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($pending_requests);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
