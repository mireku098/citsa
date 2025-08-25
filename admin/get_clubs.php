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
    // Get all clubs with member count
    $stmt = $pdo->query("
        SELECT 
            c.id,
            c.name,
            c.description,
            c.created_at,
            (SELECT COUNT(*) FROM user_clubs uc WHERE uc.club_id = c.id) as member_count
        FROM clubs c
        ORDER BY c.created_at DESC
    ");
    
    $clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($clubs);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
