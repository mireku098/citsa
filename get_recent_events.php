<?php
session_start();
require_once 'app/db.conn.php';

header('Content-Type: application/json');

try {
    // Get recent published events (limit to 6 most recent)
    $stmt = $pdo->query("
        SELECT e.event_id, e.title, e.description, e.event_date, e.event_time, 
               e.location, e.event_type, e.image_path, e.created_at,
               a.name as created_by_name
        FROM events e
        LEFT JOIN admins a ON e.created_by = a.admin_id
        WHERE e.status = 'published'
        ORDER BY e.event_date ASC, e.event_time ASC
        LIMIT 6
    ");
    
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($events);
    
} catch (PDOException $e) {
    // If table doesn't exist yet, return empty array
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        echo json_encode([]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
