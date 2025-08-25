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
        // Get form data
        $club_name = trim($_POST['club_name']);
        $description = trim($_POST['description'] ?? '');
        
        // Validate required fields
        if (empty($club_name)) {
            $response['message'] = 'Club name is required';
        } else {
            // Check if club name already exists
            $stmt = $pdo->prepare("SELECT id FROM clubs WHERE name = ?");
            $stmt->execute([$club_name]);
            if ($stmt->fetch()) {
                $response['message'] = 'Club name already exists';
            } else {
                // Insert new club
                $stmt = $pdo->prepare("
                    INSERT INTO clubs (name, description, created_at)
                    VALUES (?, ?, NOW())
                ");
                
                if ($stmt->execute([$club_name, $description])) {
                    $response['success'] = true;
                    $response['message'] = 'Club created successfully';
                } else {
                    $response['message'] = 'Failed to create club';
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
