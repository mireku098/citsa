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
    // Get all authorized users with their information
    $stmt = $pdo->query("
        SELECT 
            id,
            first_name,
            last_name,
            student_id,
            programme,
            user_type,
            status,
            created_at
        FROM authorized_students
        ORDER BY created_at DESC
    ");
    
    $authorized_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($authorized_users);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
