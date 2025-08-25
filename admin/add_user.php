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
        $username = trim($_POST['username']);
        $student_id = trim($_POST['student_id']);
        $email = trim($_POST['email']);
        $programme = trim($_POST['programme']);
        $password = $_POST['password'];
        
        // Validate required fields
        if (empty($first_name) || empty($last_name) || empty($username) || 
            empty($student_id) || empty($email) || empty($programme) || empty($password)) {
            $response['message'] = 'All fields are required';
        } else {
            // Check if username already exists
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $response['message'] = 'Username already exists';
            } else {
                // Check if student ID already exists
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE student_id = ?");
                $stmt->execute([$student_id]);
                if ($stmt->fetch()) {
                    $response['message'] = 'Student ID already exists';
                } else {
                    // Check if email already exists
                    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) {
                        $response['message'] = 'Email already exists';
                    } else {
                        // Hash password
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        
                        // Insert new user
                        $stmt = $pdo->prepare("
                            INSERT INTO users (username, first_name, last_name, student_id, email, password, programme, user_type, status, online_status, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'student', 'active', 'offline', NOW())
                        ");
                        
                        if ($stmt->execute([$username, $first_name, $last_name, $student_id, $email, $hashed_password, $programme])) {
                            $response['success'] = true;
                            $response['message'] = 'User added successfully';
                        } else {
                            $response['message'] = 'Failed to add user';
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
