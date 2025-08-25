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
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        $request_id = $input['request_id'] ?? null;
        
        if (!$request_id) {
            $response['message'] = 'Request ID is required';
        } else {
            // Check if request exists and is pending
            $stmt = $pdo->prepare("
                SELECT id FROM room_members 
                WHERE id = ? AND status = 'pending'
            ");
            $stmt->execute([$request_id]);
            $request = $stmt->fetch();
            
            if (!$request) {
                $response['message'] = 'Request not found or already processed';
            } else {
                // Reject the request
                $stmt = $pdo->prepare("
                    UPDATE room_members 
                    SET status = 'rejected' 
                    WHERE id = ?
                ");
                
                if ($stmt->execute([$request_id])) {
                    $response['success'] = true;
                    $response['message'] = 'Club join request rejected successfully';
                } else {
                    $response['message'] = 'Failed to reject request';
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
