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
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $student_id = trim($_POST['student_id']);
        $programme = trim($_POST['programme']);
        $user_type = trim($_POST['user_type']);
        
        // Validate required fields
        if (empty($first_name) || empty($last_name) || empty($student_id) || empty($programme) || empty($user_type)) {
            $response['message'] = 'All fields are required';
        } else {
            // Check if student ID already exists
            $stmt = $pdo->prepare("SELECT id FROM authorized_students WHERE student_id = ?");
            $stmt->execute([$student_id]);
            if ($stmt->fetch()) {
                $response['message'] = 'Student ID already exists in authorized list';
            } else {
                // Insert new authorized user
                $stmt = $pdo->prepare("
                    INSERT INTO authorized_students (first_name, last_name, student_id, programme, user_type, status, created_at)
                    VALUES (?, ?, ?, ?, ?, 'active', NOW())
                ");
                
                if ($stmt->execute([$first_name, $last_name, $student_id, $programme, $user_type])) {
                    $response['success'] = true;
                    $response['message'] = 'Authorized user added successfully';
                } else {
                    $response['message'] = 'Failed to add authorized user';
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
