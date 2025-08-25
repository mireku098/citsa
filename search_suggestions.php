<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Include database connection
include 'app/db.conn.php';

// Get search query
$query = trim($_GET['q'] ?? '');

if (empty($query) || strlen($query) < 2) {
    echo json_encode(['suggestions' => []]);
    exit();
}

try {
    // Search for users by name, username, or student ID
    $stmt = $pdo->prepare("
        SELECT user_id, first_name, last_name, username, student_id, programme, profile_image
        FROM users 
        WHERE user_id != ? 
        AND (
            LOWER(first_name) LIKE LOWER(?) 
            OR LOWER(last_name) LIKE LOWER(?) 
            OR LOWER(username) LIKE LOWER(?) 
            OR LOWER(student_id) LIKE LOWER(?)
        )
        AND status = 'active'
        ORDER BY 
            CASE 
                WHEN LOWER(first_name) LIKE LOWER(?) THEN 1
                WHEN LOWER(last_name) LIKE LOWER(?) THEN 2
                WHEN LOWER(username) LIKE LOWER(?) THEN 3
                WHEN LOWER(student_id) LIKE LOWER(?) THEN 4
                ELSE 5
            END,
            first_name, last_name
        LIMIT 10
    ");
    
    $searchTerm = "%{$query}%";
    $stmt->execute([
        $_SESSION['user_id'],
        $searchTerm, $searchTerm, $searchTerm, $searchTerm,
        $searchTerm, $searchTerm, $searchTerm, $searchTerm
    ]);
    
    $suggestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format suggestions for display
    $formattedSuggestions = [];
    foreach ($suggestions as $user) {
        $formattedSuggestions[] = [
            'id' => $user['user_id'],
            'name' => $user['first_name'] . ' ' . $user['last_name'],
            'username' => $user['username'],
            'student_id' => $user['student_id'],
            'programme' => $user['programme'],
            'profile_image' => $user['profile_image'] ?? 'default-avatar.png',
            'display_text' => $user['first_name'] . ' ' . $user['last_name'] . ' (@' . $user['username'] . ') - ' . $user['student_id']
        ];
    }
    
    echo json_encode(['suggestions' => $formattedSuggestions]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>
