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
        $input = json_decode(file_get_contents('php://input'), true);
        $event_id = (int)$input['event_id'];
        
        // Check if event exists
        $stmt = $pdo->prepare("SELECT event_id FROM events WHERE event_id = ?");
        $stmt->execute([$event_id]);
        if (!$stmt->fetch()) {
            $response['message'] = 'Event not found';
            echo json_encode($response);
            exit();
        }
        
        // Delete event (this will also delete related attachments due to CASCADE)
        $stmt = $pdo->prepare("DELETE FROM events WHERE event_id = ?");
        if ($stmt->execute([$event_id])) {
            $response['success'] = true;
            $response['message'] = 'Event deleted successfully';
        } else {
            $response['message'] = 'Failed to delete event';
        }
        
    } catch (PDOException $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
