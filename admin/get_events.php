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
    // Get events with creator information
    $stmt = $pdo->query("
        SELECT e.event_id, e.title, e.description, e.event_date, e.event_time, 
               e.location, e.event_type, e.status, e.image_path, e.created_at,
               a.name as created_by_name
        FROM events e
        LEFT JOIN admins a ON e.created_by = a.admin_id
        ORDER BY e.created_at DESC
    ");
    
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($events);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
