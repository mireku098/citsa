<?php
session_start();
require_once 'app/db.conn.php';
require_once 'app/helpers/chat_room_notifications.php';
require_once 'app/helpers/user.php';
require_once 'app/helpers/chat_rooms.php';
require_once 'app/helpers/timeAgo.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Handle AJAX requests (support POST and GET for robustness)
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_POST['action'] ?? $_GET['action'] ?? 'get_recent';
    $room_id = $_POST['room_id'] ?? $_GET['room_id'] ?? '';
    $message_id = $_POST['message_id'] ?? $_GET['message_id'] ?? '';
    $user_id = $_SESSION['user_id'];
    
    try {
        switch ($action) {
            case 'get_recent':
                // Build user and available rooms to ensure access control
                $user = getUser($user_id, $pdo);
                $available_rooms = getAvailableRooms($user, $pdo);
                $roomIdToName = [];
                foreach ($available_rooms as $r) {
                    $roomIdToName[$r['id']] = $r['name'];
                }

                // Ensure reads table exists via helper (implicitly created)
                $unreadCounts = getAllChatRoomsUnreadCount($user_id, $pdo);

                $rooms = [];
                foreach ($unreadCounts as $roomId => $unread) {
                    $unread = (int)$unread;
                    if ($unread <= 0) { continue; }
                    if (!isset($roomIdToName[$roomId])) { continue; }

                    // Fetch latest unread message details for preview
                    $stmt2 = $pdo->prepare("
                        SELECT m.id, m.message, m.message_type, m.created_at, u.username, u.first_name, u.last_name, u.profile_image
                        FROM chat_room_messages m
                        JOIN users u ON u.user_id = m.sender_id
                        LEFT JOIN chat_room_message_reads r ON r.message_id = m.id AND r.user_id = ?
                        WHERE m.room_id = ? AND m.sender_id != ? AND r.id IS NULL
                        ORDER BY m.created_at DESC
                        LIMIT 1
                    ");
                    $stmt2->execute([$user_id, $roomId, $user_id]);
                    $last = $stmt2->fetch(PDO::FETCH_ASSOC);
                    if (!$last) { continue; }

                    $preview = $last['message'] ?? '';
                    if (!$preview && ($last['message_type'] ?? '') === 'image') { $preview = '[Image]'; }
                    if (!$preview && ($last['message_type'] ?? '') === 'file') { $preview = '[File]'; }
                    if (mb_strlen($preview) > 70) { $preview = mb_substr($preview, 0, 67) . '...'; }

                    $rooms[] = [
                        'room_id' => $roomId,
                        'room_name' => $roomIdToName[$roomId] ?? $roomId,
                        'last_message' => $preview,
                        'last_message_time' => $last['created_at'],
                        'time_ago' => timeAgo($last['created_at']),
                        'sender_username' => $last['username'],
                        'sender_name' => trim(($last['first_name'] ?? '') . ' ' . ($last['last_name'] ?? '')),
                        'sender_profile_image' => $last['profile_image'] ?: 'default-avatar.png',
                        'unread_count' => $unread
                    ];
                }

                // If no unread rooms found, fall back to latest message per accessible room (preview only)
                if (empty($rooms)) {
                    $stmt = $pdo->prepare("
                        SELECT m.room_id, MAX(m.id) AS last_message_id, MAX(m.created_at) AS last_message_time
                        FROM chat_room_messages m
                        GROUP BY m.room_id
                        ORDER BY last_message_time DESC
                        LIMIT 15
                    ");
                    $stmt->execute();
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($rows as $row) {
                        $roomId = $row['room_id'];
                        if (!isset($roomIdToName[$roomId])) { continue; }
                        $stmt2 = $pdo->prepare("
                            SELECT m.id, m.message, m.message_type, m.created_at, u.username, u.first_name, u.last_name, u.profile_image
                            FROM chat_room_messages m
                            JOIN users u ON u.user_id = m.sender_id
                            WHERE m.id = ?
                            LIMIT 1
                        ");
                        $stmt2->execute([$row['last_message_id']]);
                        $last = $stmt2->fetch(PDO::FETCH_ASSOC);
                        if (!$last) { continue; }
                        $preview = $last['message'] ?? '';
                        if (!$preview && ($last['message_type'] ?? '') === 'image') { $preview = '[Image]'; }
                        if (!$preview && ($last['message_type'] ?? '') === 'file') { $preview = '[File]'; }
                        if (mb_strlen($preview) > 70) { $preview = mb_substr($preview, 0, 67) . '...'; }
                        $rooms[] = [
                            'room_id' => $roomId,
                            'room_name' => $roomIdToName[$roomId] ?? $roomId,
                            'last_message' => $preview,
                            'last_message_time' => $last['created_at'],
                            'time_ago' => timeAgo($last['created_at']),
                            'sender_username' => $last['username'],
                            'sender_name' => trim(($last['first_name'] ?? '') . ' ' . ($last['last_name'] ?? '')),
                            'sender_profile_image' => $last['profile_image'] ?: 'default-avatar.png',
                            'unread_count' => 0
                        ];
                    }
                }

                // Sort by last_message_time DESC for consistent ordering
                usort($rooms, function($a, $b) {
                    return strtotime($b['last_message_time']) <=> strtotime($a['last_message_time']);
                });

                echo json_encode([
                    'success' => true,
                    'rooms' => $rooms
                ]);
                break;
            case 'get_unread_count':
                if (empty($room_id)) {
                    throw new Exception('Room ID is required');
                }
                
                $unread_count = getChatRoomUnreadCount($user_id, $room_id, $pdo);
                
                echo json_encode([
                    'success' => true,
                    'unread_count' => $unread_count
                ]);
                break;
                
            case 'get_all_unread_counts':
                $unread_counts = getAllChatRoomsUnreadCount($user_id, $pdo);
                
                echo json_encode([
                    'success' => true,
                    'unread_counts' => $unread_counts
                ]);
                break;
                
            case 'mark_as_read':
                if (empty($room_id)) {
                    throw new Exception('Room ID is required');
                }
                
                $success = markChatRoomMessagesAsRead($user_id, $room_id, $pdo);
                
                if ($success) {
                    // Get updated unread count
                    $unread_count = getChatRoomUnreadCount($user_id, $room_id, $pdo);
                    
                    echo json_encode([
                        'success' => true,
                        'unread_count' => $unread_count,
                        'message' => 'Messages marked as read'
                    ]);
                } else {
                    throw new Exception('Failed to mark messages as read');
                }
                break;
                
            case 'mark_message_as_read':
                if (empty($message_id) || empty($room_id)) {
                    throw new Exception('Message ID and Room ID are required');
                }
                
                $success = markSpecificChatRoomMessageAsRead($user_id, $message_id, $room_id, $pdo);
                
                if ($success) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Message marked as read'
                    ]);
                } else {
                    throw new Exception('Failed to mark message as read');
                }
                break;
                
            default:
                // Return empty successful payload rather than 400 to avoid noisy console errors
                echo json_encode([
                    'success' => true,
                    'rooms' => [],
                    'unread_counts' => []
                ]);
                break;
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    // Fallback: no method handled
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
