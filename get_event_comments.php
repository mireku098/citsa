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

// Check if event_id is provided
if (!isset($_GET['event_id']) || !is_numeric($_GET['event_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid event ID']);
    exit();
}

$event_id = (int)$_GET['event_id'];
$user_id = $_SESSION['user_id'];

try {
    // Get comments for the event with user information and like counts
    $stmt = $pdo->prepare("
        SELECT 
            ec.comment_id,
            ec.comment_text,
            ec.created_at,
            ec.updated_at,
            u.user_id,
            u.username,
            u.profile_image,
            COUNT(ecl.like_id) as like_count,
            MAX(CASE WHEN ecl.user_id = ? THEN 1 ELSE 0 END) as user_liked
        FROM event_comments ec
        JOIN users u ON ec.user_id = u.user_id
        LEFT JOIN event_comment_likes ecl ON ec.comment_id = ecl.comment_id
        WHERE ec.event_id = ? AND ec.status = 'active'
        GROUP BY ec.comment_id
        ORDER BY ec.created_at ASC
    ");
    
    $stmt->execute([$user_id, $event_id]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the comments for frontend
    $formatted_comments = [];
    foreach ($comments as $comment) {
        $formatted_comments[] = [
            'comment_id' => $comment['comment_id'],
            'comment_text' => $comment['comment_text'],
            'created_at' => $comment['created_at'],
            'updated_at' => $comment['updated_at'],
            'user' => [
                'user_id' => $comment['user_id'],
                'username' => $comment['username'],
                'profile_image' => $comment['profile_image'] ?: 'default-avatar.png'
            ],
            'like_count' => (int)$comment['like_count'],
            'user_liked' => (bool)$comment['user_liked'],
            'is_own_comment' => $comment['user_id'] == $user_id
        ];
    }
    
    echo json_encode([
        'success' => true,
        'comments' => $formatted_comments,
        'total_count' => count($formatted_comments)
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
