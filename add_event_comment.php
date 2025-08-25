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

if (!$input || !isset($input['event_id']) || !isset($input['comment_text'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit();
}

$event_id = (int)$input['event_id'];
$comment_text = trim($input['comment_text']);
$user_id = $_SESSION['user_id'];

// Validate input
if (!is_numeric($event_id) || $event_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid event ID']);
    exit();
}

if (empty($comment_text) || strlen($comment_text) > 1000) {
    http_response_code(400);
    echo json_encode(['error' => 'Comment text must be between 1 and 1000 characters']);
    exit();
}

try {
    // First, verify the event exists and is published
    $stmt = $pdo->prepare("SELECT event_id, title FROM events WHERE event_id = ? AND status = 'published'");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        http_response_code(404);
        echo json_encode(['error' => 'Event not found or not published']);
        exit();
    }
    
    // Insert the comment
    $stmt = $pdo->prepare("
        INSERT INTO event_comments (event_id, user_id, comment_text) 
        VALUES (?, ?, ?)
    ");
    
    $stmt->execute([$event_id, $user_id, $comment_text]);
    $comment_id = $pdo->lastInsertId();
    
    // Get the newly created comment with user information
    $stmt = $pdo->prepare("
        SELECT 
            ec.comment_id,
            ec.comment_text,
            ec.created_at,
            ec.updated_at,
            u.user_id,
            u.username,
            u.profile_image
        FROM event_comments ec
        JOIN users u ON ec.user_id = u.user_id
        WHERE ec.comment_id = ?
    ");
    
    $stmt->execute([$comment_id]);
    $new_comment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Format the response
    $formatted_comment = [
        'comment_id' => $new_comment['comment_id'],
        'comment_text' => $new_comment['comment_text'],
        'created_at' => $new_comment['created_at'],
        'updated_at' => $new_comment['updated_at'],
                    'user' => [
                'user_id' => $new_comment['user_id'],
                'username' => $new_comment['username'],
                'profile_image' => $new_comment['profile_image'] ?: 'default-avatar.png'
            ],
        'like_count' => 0,
        'user_liked' => false,
        'is_own_comment' => true
    ];
    
    echo json_encode([
        'success' => true,
        'message' => 'Comment added successfully',
        'comment' => $formatted_comment
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
