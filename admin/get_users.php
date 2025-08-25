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
    // Get all users with their information
    $stmt = $pdo->query("
        SELECT 
            u.user_id,
            u.username,
            u.first_name,
            u.last_name,
            u.student_id,
            u.email,
            u.profile_image,
            u.programme,
            u.user_type,
            u.status,
            u.online_status,
            u.last_seen,
            u.created_at
        FROM users u
        ORDER BY u.created_at DESC
    ");
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($users);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
