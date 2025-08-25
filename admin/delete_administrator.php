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
        $admin_id = (int)$input['admin_id'];
        
        // Prevent self-deletion
        if ($admin_id == $_SESSION['admin_id']) {
            $response['message'] = 'You cannot delete your own account';
            echo json_encode($response);
            exit();
        }
        
        // Check if admin exists
        $stmt = $pdo->prepare("SELECT admin_id FROM admins WHERE admin_id = ?");
        $stmt->execute([$admin_id]);
        if (!$stmt->fetch()) {
            $response['message'] = 'Administrator not found';
            echo json_encode($response);
            exit();
        }
        
        // Delete administrator
        $stmt = $pdo->prepare("DELETE FROM admins WHERE admin_id = ?");
        if ($stmt->execute([$admin_id])) {
            $response['success'] = true;
            $response['message'] = 'Administrator deleted successfully';
        } else {
            $response['message'] = 'Failed to delete administrator';
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
