<?php
session_start();
require_once 'app/db.conn.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['comment_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing comment ID']);
    exit();
}

$comment_id = (int)$input['comment_id'];
$user_id = $_SESSION['user_id'];

// Validate input
if (!is_numeric($comment_id) || $comment_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid comment ID']);
    exit();
}

try {
    // Check if the comment exists and belongs to the user
    $stmt = $pdo->prepare("SELECT comment_id FROM event_comments WHERE comment_id = ? AND user_id = ? AND status = 'active'");
    $stmt->execute([$comment_id, $user_id]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Comment not found or you do not have permission to delete it']);
        exit();
    }
    
    // Soft delete the comment (set status to deleted)
    $stmt = $pdo->prepare("UPDATE event_comments SET status = 'deleted' WHERE comment_id = ?");
    $stmt->execute([$comment_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Comment deleted successfully'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
