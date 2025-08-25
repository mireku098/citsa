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
    // Get administrators with their roles
    $stmt = $pdo->query("
        SELECT a.admin_id, a.name, a.username, a.email, a.department, a.position, 
               a.last_seen, ar.role_name
        FROM admins a
        LEFT JOIN admin_roles ar ON a.role_id = ar.role_id
        ORDER BY a.created_at DESC
    ");
    
    $administrators = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($administrators);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
