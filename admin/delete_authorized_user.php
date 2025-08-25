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
        $user_id = $input['user_id'] ?? null;
        
        if (!$user_id) {
            $response['message'] = 'User ID is required';
        } else {
            // Check if authorized user exists
            $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM authorized_students WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $response['message'] = 'Authorized user not found';
            } else {
                // Delete authorized user
                $stmt = $pdo->prepare("DELETE FROM authorized_students WHERE id = ?");
                
                if ($stmt->execute([$user_id])) {
                    $response['success'] = true;
                    $response['message'] = 'Authorized user deleted successfully';
                } else {
                    $response['message'] = 'Failed to delete authorized user';
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
