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
        $email = trim($_POST['email']);
        $department = trim($_POST['department']);
        $position = trim($_POST['position']);
        $role_id = (int)$_POST['role_id'];
        $password = $_POST['password'];
        
        // Validation
        if (empty($first_name) || empty($last_name) || empty($username) || empty($email) || 
            empty($department) || empty($position) || empty($role_id) || empty($password)) {
            $response['message'] = 'All fields are required';
            echo json_encode($response);
            exit();
        }
        
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()['count'] > 0) {
            $response['message'] = 'Username already exists';
            echo json_encode($response);
            exit();
        }
        
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()['count'] > 0) {
            $response['message'] = 'Email already exists';
            echo json_encode($response);
            exit();
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Combine first and last name
        $full_name = $first_name . ' ' . $last_name;
        
        // Insert new administrator
        $stmt = $pdo->prepare("
            INSERT INTO admins (name, username, password, email, department, position, role_id, profile_image)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'default-avatar.png')
        ");
        
        if ($stmt->execute([$full_name, $username, $hashed_password, $email, $department, $position, $role_id])) {
            $response['success'] = true;
            $response['message'] = 'Administrator added successfully';
        } else {
            $response['message'] = 'Failed to add administrator';
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
