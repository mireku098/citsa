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
            // Get request details
            $stmt = $pdo->prepare("
                SELECT rm.*, u.user_id, c.id as club_id, c.name as club_name
                FROM room_members rm
                JOIN users u ON rm.user_id = u.user_id
                JOIN clubs c ON rm.room_id = c.id
                WHERE rm.id = ? AND rm.status = 'pending'
            ");
            $stmt->execute([$request_id]);
            $request = $stmt->fetch();
            
            if (!$request) {
                $response['message'] = 'Request not found or already processed';
            } else {
                // Check if user is already a member of this club
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count FROM room_members 
                    WHERE user_id = ? AND room_id = ? AND status = 'approved'
                ");
                $stmt->execute([$request['user_id'], $request['room_id']]);
                $existing_membership = $stmt->fetch();
                
                if ($existing_membership['count'] > 0) {
                    $response['message'] = 'User is already a member of this club';
                } else {
                    // Check 2-club limit
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as count FROM room_members 
                        WHERE user_id = ? AND status = 'approved'
                    ");
                    $stmt->execute([$request['user_id']]);
                    $club_count = $stmt->fetch();
                    
                    if ($club_count['count'] >= 2) {
                        $response['message'] = 'User has reached the maximum limit of 2 clubs';
                    } else {
                        // Approve the request
                        $stmt = $pdo->prepare("
                            UPDATE room_members 
                            SET status = 'approved', joined_at = NOW() 
                            WHERE id = ?
                        ");
                        
                        if ($stmt->execute([$request_id])) {
                            $response['success'] = true;
                            $response['message'] = 'Club join request approved successfully';
                        } else {
                            $response['message'] = 'Failed to approve request';
                        }
                    }
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
