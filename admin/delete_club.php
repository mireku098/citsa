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
        $club_id = $input['club_id'] ?? null;
        
        if (!$club_id) {
            $response['message'] = 'Club ID is required';
        } else {
            // Check if club exists
            $stmt = $pdo->prepare("SELECT id, name FROM clubs WHERE id = ?");
            $stmt->execute([$club_id]);
            $club = $stmt->fetch();
            
            if (!$club) {
                $response['message'] = 'Club not found';
            } else {
                // Delete club (you might want to soft delete instead)
                $stmt = $pdo->prepare("DELETE FROM clubs WHERE id = ?");
                
                if ($stmt->execute([$club_id])) {
                    $response['success'] = true;
                    $response['message'] = 'Club deleted successfully';
                } else {
                    $response['message'] = 'Failed to delete club';
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
