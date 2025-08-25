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
    // Check if the comment exists and is active
    $stmt = $pdo->prepare("SELECT comment_id FROM event_comments WHERE comment_id = ? AND status = 'active'");
    $stmt->execute([$comment_id]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Comment not found']);
        exit();
    }
    
    // Check if user already liked the comment
    $stmt = $pdo->prepare("SELECT like_id FROM event_comment_likes WHERE comment_id = ? AND user_id = ?");
    $stmt->execute([$comment_id, $user_id]);
    $existing_like = $stmt->fetch();
    
    if ($existing_like) {
        // Unlike the comment
        $stmt = $pdo->prepare("DELETE FROM event_comment_likes WHERE comment_id = ? AND user_id = ?");
        $stmt->execute([$comment_id, $user_id]);
        
        $action = 'unliked';
    } else {
        // Like the comment
        $stmt = $pdo->prepare("INSERT INTO event_comment_likes (comment_id, user_id) VALUES (?, ?)");
        $stmt->execute([$comment_id, $user_id]);
        
        $action = 'liked';
    }
    
    // Get updated like count
    $stmt = $pdo->prepare("SELECT COUNT(*) as like_count FROM event_comment_likes WHERE comment_id = ?");
    $stmt->execute([$comment_id]);
    $like_count = $stmt->fetch(PDO::FETCH_ASSOC)['like_count'];
    
    echo json_encode([
        'success' => true,
        'action' => $action,
        'like_count' => (int)$like_count,
        'user_liked' => $action === 'liked'
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
