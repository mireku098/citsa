<?php
// Start output buffering to prevent unwanted output
ob_start();



// Set error reporting to log errors instead of displaying them
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Debug: Log session info
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'not set'));

// Include database connection and helper files
include 'app/db.conn.php';
include 'app/helpers/user.php';
include 'app/helpers/conversations.php';
include 'app/helpers/timeAgo.php';
include 'app/helpers/last_chat.php';

// Debug: Check database connection
error_log("Database connection status: " . ($pdo ? 'connected' : 'failed'));

// Get current user data
$user = getUser($_SESSION['user_id'], $pdo);
error_log("User data retrieved: " . ($user ? 'success' : 'failed'));



// Update user's online status
try {
    $stmt = $pdo->prepare("UPDATE users SET online_status = 'online', last_seen = NOW() WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
} catch (PDOException $e) {
    // If online_status column doesn't exist, just update last_seen
    $stmt = $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
}

// Get user's friends for chat
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.user_id, u.username, u.first_name, u.last_name, u.profile_image, 
               u.online_status, u.last_seen, u.status
        FROM users u 
        JOIN friends f ON (f.user_id = ? AND f.friend_id = u.user_id) OR (f.friend_id = ? AND f.user_id = u.user_id)
        WHERE u.status = 'active'
        ORDER BY u.online_status DESC, u.first_name ASC, u.last_name ASC
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $friends = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Fallback query if online_status column doesn't exist
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.user_id, u.username, u.first_name, u.last_name, u.profile_image, 
               u.status
        FROM users u 
        JOIN friends f ON (f.user_id = ? AND f.friend_id = u.user_id) OR (f.friend_id = ? AND f.user_id = u.user_id)
        WHERE u.status = 'active'
        ORDER BY u.first_name ASC, u.last_name ASC
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $friends = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
error_log("Friends count: " . count($friends));



// Get user's conversations
$conversations = getConversations($_SESSION['user_id'], $pdo);
error_log("Conversations count: " . count($conversations));



// Handle GET requests for beacon API
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'update_online_status') {
        try {
            $stmt = $pdo->prepare("UPDATE users SET online_status = 'online', last_seen = NOW() WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Failed to update online status']);
        }
        exit();
    } elseif ($_GET['action'] === 'mark_offline') {
        try {
            $stmt = $pdo->prepare("UPDATE users SET online_status = 'offline', last_seen = NOW() WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Failed to mark offline']);
        }
        exit();
    }
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    // Prevent any output before JSON response
    ob_clean();
    header('Content-Type: application/json');
    
    // Debug: Check if there's any output before this point
    $output = ob_get_contents();
    if (!empty($output)) {
        error_log("Unexpected output before AJAX: " . $output);
        ob_clean();
    }
    
    // Error handling
    try {
        // Debug: Log the action being processed
        error_log("Processing AJAX action: " . $_POST['action']);
    
    switch ($_POST['action']) {
        case 'send_message':
            $conversation_id = $_POST['conversation_id'];
            $message = trim($_POST['message']);
            $message_type = $_POST['message_type'] ?? 'text';
            $file_url = $_POST['file_url'] ?? null;
            $file_name = $_POST['file_name'] ?? null;
            $file_size = $_POST['file_size'] ?? null;
            
            if (!empty($message) || $message_type !== 'text') {
                // Store the message as plain text
                
                $stmt = $pdo->prepare("
                    INSERT INTO messages (conversation_id, sender_id, message, message_type, file_url, file_name, file_size, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$conversation_id, $_SESSION['user_id'], $message, $message_type, $file_url, $file_name, $file_size]);
                
                // Update conversation last message
                updateLastMessage($conversation_id, $pdo);
                
                echo json_encode(['success' => true, 'message_id' => $pdo->lastInsertId()]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
            }
            exit();
            break;

        case 'upload_file':
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'error' => 'File upload failed']);
                exit();
            }
            
            $file = $_FILES['file'];
            $conversation_id = $_POST['conversation_id'];
            $message = trim($_POST['message'] ?? '');
            
            // Validate file size (max 10MB)
            if ($file['size'] > 10 * 1024 * 1024) {
                echo json_encode(['success' => false, 'error' => 'File size too large. Maximum 10MB allowed.']);
                exit();
            }
            
            // Validate file type
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain'];
            if (!in_array($file['type'], $allowed_types)) {
                echo json_encode(['success' => false, 'error' => 'File type not allowed.']);
                exit();
            }
            
            // Create upload directory if it doesn't exist
            $upload_dir = 'uploads/private_chat/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $unique_filename = uniqid() . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $unique_filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                // Determine message type
                $message_type = strpos($file['type'], 'image/') === 0 ? 'image' : 'file';
                
                // Insert message with file attachment
                $stmt = $pdo->prepare("
                    INSERT INTO messages (conversation_id, sender_id, message, message_type, file_url, file_name, file_size, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$conversation_id, $_SESSION['user_id'], $message, $message_type, $file_path, $file['name'], $file['size']]);
                
                // Update conversation last message
                updateLastMessage($conversation_id, $pdo);
                
                echo json_encode([
                    'success' => true, 
                    'message_id' => $pdo->lastInsertId(),
                    'file_url' => $file_path,
                    'file_name' => $file['name'],
                    'file_size' => $file['size'],
                    'message_type' => $message_type
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to save file']);
            }
            exit();
            break;

        case 'download_file':
            $file_path = $_POST['file_path'] ?? '';
            $file_name = $_POST['file_name'] ?? '';
            
            if (empty($file_path) || empty($file_name)) {
                echo json_encode(['success' => false, 'error' => 'Invalid file information']);
                exit();
            }
            
            // Security check: ensure file is in uploads directory
            $real_path = realpath($file_path);
            $uploads_dir = realpath('uploads/private_chat/');
            
            if (!$real_path || !$uploads_dir || strpos($real_path, $uploads_dir) !== 0) {
                echo json_encode(['success' => false, 'error' => 'Access denied']);
                exit();
            }
            
            // Check if file exists
            if (!file_exists($real_path)) {
                echo json_encode(['success' => false, 'error' => 'File not found']);
                exit();
            }
            
            // Check if user has access to this file (they must be part of the conversation)
            $stmt = $pdo->prepare("
                SELECT m.conversation_id, c.user_1, c.user_2 
                FROM messages m 
                JOIN conversations c ON m.conversation_id = c.conversation_id 
                WHERE m.file_url = ? AND m.id = (SELECT MAX(id) FROM messages WHERE file_url = ?)
            ");
            $stmt->execute([$file_path, $file_path]);
            $file_access = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$file_access || ($file_access['user_1'] != $_SESSION['user_id'] && $file_access['user_2'] != $_SESSION['user_id'])) {
                echo json_encode(['success' => false, 'error' => 'Access denied']);
                exit();
            }
            
            // Set headers for file download
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $file_name . '"');
            header('Content-Length: ' . filesize($real_path));
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            
            // Output file content
            readfile($real_path);
            exit();
            break;
            
        case 'get_messages':
            $conversation_id = $_POST['conversation_id'];
            $last_message_id = $_POST['last_message_id'] ?? 0;
            
            // Debug: Log the parameters
            error_log("Getting messages for conversation: $conversation_id, last_message_id: $last_message_id");
            
            try {
                $stmt = $pdo->prepare("
                    SELECT m.*, u.first_name, u.last_name, u.username, u.profile_image,
                           GROUP_CONCAT(CONCAT(mr.reaction_type, ':', mr.user_id) SEPARATOR ',') as reactions,
                           (SELECT reaction_type FROM message_reactions WHERE message_id = m.id AND user_id = ? LIMIT 1) as user_reaction
                    FROM messages m
                    JOIN users u ON m.sender_id = u.user_id
                    LEFT JOIN message_reactions mr ON m.id = mr.message_id
                    WHERE m.conversation_id = ? AND m.id > ?
                    GROUP BY m.id
                    ORDER BY m.created_at ASC
                ");
                $stmt->execute([$_SESSION['user_id'], $conversation_id, $last_message_id]);
            } catch (PDOException $e) {
                // If message_reactions table doesn't exist, use simpler query
                $stmt = $pdo->prepare("
                    SELECT m.*, u.first_name, u.last_name, u.username, u.profile_image
                    FROM messages m
                    JOIN users u ON m.sender_id = u.user_id
                    WHERE m.conversation_id = ? AND m.id > ?
                    ORDER BY m.created_at ASC
                ");
                $stmt->execute([$conversation_id, $last_message_id]);
            }
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Messages are already in plain text, no decryption needed
            
            // Mark messages as read
            $stmt = $pdo->prepare("
                UPDATE messages 
                SET read_status = 1 
                WHERE conversation_id = ? AND sender_id != ? AND read_status = 0
            ");
            $stmt->execute([$conversation_id, $_SESSION['user_id']]);
            
            // Debug: Log the response
            error_log("Sending " . count($messages) . " messages");
            $response = json_encode(['success' => true, 'messages' => $messages]);
            error_log("Response length: " . strlen($response));
            echo $response;
            exit();
            break;
            
        case 'add_reaction':
            $message_id = $_POST['message_id'];
            $reaction_type = $_POST['reaction_type'];
            
            try {
                // Check if user already has a reaction on this message
                $stmt = $pdo->prepare("
                    SELECT reaction_type FROM message_reactions 
                    WHERE message_id = ? AND user_id = ?
                ");
                $stmt->execute([$message_id, $_SESSION['user_id']]);
                $existing_reaction = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing_reaction) {
                    if ($existing_reaction['reaction_type'] === $reaction_type) {
                        // Same reaction clicked - remove it (undo)
                        $stmt = $pdo->prepare("
                            DELETE FROM message_reactions 
                            WHERE message_id = ? AND user_id = ?
                        ");
                        $stmt->execute([$message_id, $_SESSION['user_id']]);
                    } else {
                        // Different reaction clicked - replace it
                        $stmt = $pdo->prepare("
                            UPDATE message_reactions 
                            SET reaction_type = ? 
                            WHERE message_id = ? AND user_id = ?
                        ");
                        $stmt->execute([$reaction_type, $message_id, $_SESSION['user_id']]);
                    }
                } else {
                    // No existing reaction - add new one
                    $stmt = $pdo->prepare("
                        INSERT INTO message_reactions (message_id, user_id, reaction_type) 
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$message_id, $_SESSION['user_id'], $reaction_type]);
                }
                
                echo json_encode(['success' => true]);
            } catch (PDOException $e) {
                // If message_reactions table doesn't exist, just return success
                echo json_encode(['success' => true, 'note' => 'Reactions not available']);
            }
            exit();
            break;
            
        case 'search_messages':
            $conversation_id = $_POST['conversation_id'];
            $search_term = $_POST['search_term'];
            
            try {
                $stmt = $pdo->prepare("
                    SELECT m.*, u.first_name, u.last_name, u.username, u.profile_image
                    FROM messages m
                    JOIN users u ON m.sender_id = u.user_id
                    WHERE m.conversation_id = ? AND m.message LIKE ?
                    ORDER BY m.created_at DESC
                ");
                $stmt->execute([$conversation_id, "%$search_term%"]);
                $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'results' => $search_results]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'error' => 'Search not available']);
            }
            exit();
            break;
            
        case 'create_conversation':
            $friend_id = $_POST['friend_id'];
            
            // Check if conversation already exists
            $stmt = $pdo->prepare("
                SELECT conversation_id FROM conversations 
                WHERE (user_1 = ? AND user_2 = ?) OR (user_1 = ? AND user_2 = ?)
            ");
            $stmt->execute([$_SESSION['user_id'], $friend_id, $friend_id, $_SESSION['user_id']]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                echo json_encode(['success' => true, 'conversation_id' => $existing['conversation_id']]);
            } else {
                // Create new conversation
                $stmt = $pdo->prepare("INSERT INTO conversations (user_1, user_2) VALUES (?, ?)");
                $stmt->execute([$_SESSION['user_id'], $friend_id]);
                $conversation_id = $pdo->lastInsertId();
                
                echo json_encode(['success' => true, 'conversation_id' => $conversation_id]);
            }
            exit();
            break;
            
        case 'update_online_status':
            try {
                $stmt = $pdo->prepare("UPDATE users SET online_status = 'online', last_seen = NOW() WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                echo json_encode(['success' => true]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'error' => 'Failed to update online status']);
            }
            exit();
            break;
            
        case 'mark_offline':
            try {
                $stmt = $pdo->prepare("UPDATE users SET online_status = 'offline', last_seen = NOW() WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                echo json_encode(['success' => true]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'error' => 'Failed to mark offline']);
            }
            exit();
            break;
            
        case 'mark_away':
            try {
                $stmt = $pdo->prepare("UPDATE users SET online_status = 'away', last_seen = NOW() WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                echo json_encode(['success' => true]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'error' => 'Failed to mark away']);
            }
            exit();
            break;
            
        case 'get_friends_status':
            try {
                $stmt = $pdo->prepare("
                    SELECT DISTINCT u.user_id, u.online_status, u.last_seen
                    FROM users u 
                    JOIN friends f ON (f.user_id = ? AND f.friend_id = u.user_id) OR (f.friend_id = ? AND f.user_id = u.user_id)
                    WHERE u.status = 'active'
                ");
                $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
                $friends_status = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'friends' => $friends_status]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'error' => 'Failed to get friends status']);
            }
            exit();
            break;
    }
} catch (Exception $e) {
    error_log("AJAX Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit();
}
}

// Get selected conversation
$selected_conversation = null;
$selected_friend = null;

// Check if user_id is provided to auto-open conversation
if (isset($_GET['user_id'])) {
    $friend_id = $_GET['user_id'];
    
    // Verify this is actually a friend
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM friends 
        WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)
    ");
    $stmt->execute([$_SESSION['user_id'], $friend_id, $friend_id, $_SESSION['user_id']]);
    $is_friend = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    
    if ($is_friend) {
        // Get or create conversation with this friend
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   CASE WHEN c.user_1 = ? THEN c.user_2 ELSE c.user_1 END as other_user_id
            FROM conversations c 
            WHERE (c.user_1 = ? AND c.user_2 = ?) OR (c.user_1 = ? AND c.user_2 = ?)
        ");
        $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $friend_id, $friend_id, $_SESSION['user_id']]);
        $selected_conversation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$selected_conversation) {
            // Create new conversation if it doesn't exist
            $stmt = $pdo->prepare("INSERT INTO conversations (user_1, user_2) VALUES (?, ?)");
            $stmt->execute([$_SESSION['user_id'], $friend_id]);
            $conversation_id = $pdo->lastInsertId();
            
            // Get the newly created conversation
            $stmt = $pdo->prepare("
                SELECT c.*, 
                       CASE WHEN c.user_1 = ? THEN c.user_2 ELSE c.user_1 END as other_user_id
                FROM conversations c 
                WHERE c.conversation_id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $conversation_id]);
            $selected_conversation = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if ($selected_conversation) {
            $selected_friend = getUser($selected_conversation['other_user_id'], $pdo);
        }
    }
} elseif (isset($_GET['conversation_id'])) {
    $conversation_id = $_GET['conversation_id'];
    
    // Get conversation details
    $stmt = $pdo->prepare("
        SELECT c.*, 
               CASE WHEN c.user_1 = ? THEN c.user_2 ELSE c.user_1 END as other_user_id
        FROM conversations c 
        WHERE c.conversation_id = ? AND (c.user_1 = ? OR c.user_2 = ?)
    ");
    $stmt->execute([$_SESSION['user_id'], $conversation_id, $_SESSION['user_id'], $_SESSION['user_id']]);
    $selected_conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($selected_conversation) {
        $selected_friend = getUser($selected_conversation['other_user_id'], $pdo);
    }
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>CITSA Connect - Private Chat</title>
    <meta content="Student-Alumni Portal" name="description">
    <meta content="CITSA, UCC, Student Portal" name="keywords">

    <!-- Favicons -->
    <link href="assets/img/favicon.png" rel="icon">
    <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

    <!-- Google Fonts -->
    <link href="https://fonts.gstatic.com" rel="preconnect">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">

    <!-- Vendor CSS Files -->
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
    <link href="assets/vendor/quill/quill.snow.css" rel="stylesheet">
    <link href="assets/vendor/quill/quill.bubble.css" rel="stylesheet">
    <link href="assets/vendor/remixicon/remixicon.css" rel="stylesheet">
    <link href="assets/vendor/simple-datatables/style.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Cascading stylesheet-->
    <link rel="stylesheet" href="assets/css/styles.css">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary-color: #1a3c6d;
            --secondary-color: #15325d;
            --accent-color: #2d5a9e;
            --text-dark: #1a3c6d;
            --text-light: #6b7280;
            --bg-light: #f8fafc;
            --white: #ffffff;
            --chat-bg: #f8f9fa;
            --message-sent: #007bff;
            --message-received: #e9ecef;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--bg-light);
            height: 100vh;
            height: 100dvh;
            overflow: hidden;
            margin: 0;
            padding: 0;
        }
        
        /* Mobile body adjustments */
        @media (max-width: 768px) {
            body {
                margin: 0;
                padding: 0;
                overflow: hidden;
                height: 100dvh;
                height: 100vh; /* Fallback */
            }
        }

        .chat-container {
            height: calc(100vh - 120px);
            display: flex;
            background: var(--white);
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(26, 60, 109, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: none;
        }

        .chat-sidebar {
            width: 300px;
            background: var(--white);
            border-right: 1px solid #e9ecef;
            display: flex;
            flex-direction: column;
        }

        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .welcome-screen {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .chat-interface {
            flex: 1;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .chat-header {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .chat-messages {
            flex: 1;
            background: var(--chat-bg);
            overflow-y: auto;
            overflow-x: hidden;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            /* Mobile scroll behavior */
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Mobile-specific improvements */
        @media (max-width: 768px) {
            .chat-container {
                height: 100dvh;
                height: 100vh; /* Fallback */
                border-radius: 0;
                margin-top: 40px;
            }
            
            .chat-sidebar {
                width: 100%;
                height: 100dvh;
                height: 100vh; /* Fallback */
                position: fixed;
                top: 0;
                left: 0;
                z-index: 1000;
                border-radius: 0;
                border-right: none;
            }
            
            .chat-main {
                width: 100%;
                height: 100dvh;
                height: 100vh; /* Fallback */
                position: fixed;
                top: 0;
                left: 0;
                z-index: 1001;
                border-radius: 0;
            }
            
            .chat-interface {
                height: 100dvh;
                height: 100vh; /* Fallback */
                border-radius: 0;
                position: relative;
                padding-top: 70px;
                padding-bottom: 0;
            }
            
            .chat-messages {
                padding: 15px;
                padding-bottom: 140px; /* Add bottom padding to account for fixed input */
                gap: 12px;
                height: calc(100dvh - 190px);
                height: calc(100vh - 190px); /* Fallback */
                position: relative;
                overscroll-behavior: contain;
                overflow-y: auto;
                overflow-x: hidden;
                margin-top: 0;
                margin-bottom: 0;
            }
            
            .chat-header {
                border-radius: 0;
                padding: 15px 20px;
                height: 70px;
                min-height: 70px;
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                z-index: 10;
            }
            
            .chat-input {
                border-radius: 0;
                padding: 15px 20px;
                height: 120px;
                min-height: 120px;
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: var(--white);
                border-top: 1px solid #e9ecef;
                z-index: 1000;
            }
            
            .chat-input-container {
                height: 100%;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
            }
            
            /* Mobile input styling */
            .action-buttons-row {
                flex-shrink: 0;
                margin-bottom: 10px;
            }
            
            .message-input-row {
                flex: 1;
                display: flex;
                align-items: end;
                gap: 10px;
            }
        }

        /* Larger screen adjustments - ensure content is below navigation */
        @media (min-width: 769px) {
            #main.main {
                margin-top: 60px !important;
            }
            
            .chat-container {
                width: 100%;
                max-width: none;
                height: calc(100vh - 150px);
                margin-top: 10px;
            }
            
            .search-box {
                margin-bottom: 25px;
            }
        }
            
            .message-input {
                flex: 1;
                min-height: 40px;
                max-height: 60px;
                resize: none;
            }
            
            .send-btn {
                flex-shrink: 0;
                width: 40px;
                height: 40px;
            }
            
            /* Smooth scroll to top/bottom on mobile */
            .chat-messages::-webkit-scrollbar {
                width: 4px;
            }
            
            .chat-messages::-webkit-scrollbar-track {
                background: transparent;
            }
            
            .chat-messages::-webkit-scrollbar-thumb {
                background: rgba(26, 60, 109, 0.3);
                border-radius: 2px;
            }
            
            .chat-messages::-webkit-scrollbar-thumb:hover {
                background: rgba(26, 60, 109, 0.5);
            }
            
            /* Scroll position indicators */
            .chat-messages.at-top::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 3px;
                background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
                opacity: 0.8;
                z-index: 10;
            }
            
            .chat-messages.at-bottom::after {
                content: '';
                position: absolute;
                bottom: 0;
                left: 0;
                right: 0;
                height: 3px;
                background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
                opacity: 0.8;
                z-index: 10;
            }
            
            /* Mobile layout classes */
            .chat-sidebar.hidden {
                display: none;
            }
            
            .chat-main.hidden {
                display: none;
            }
        
        


        .chat-input {
            background: var(--white);
            border-top: 1px solid #e9ecef;
            padding: 15px 20px;
        }

        .chat-input-container {
            display: flex;
            flex-direction: column;
            gap: 10px;
            position: relative;
        }

        .action-buttons-row {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .action-btn {
            background: transparent;
            border: none;
            color: var(--text-light);
            padding: 8px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
        }

        .action-btn:hover {
            background: rgba(26, 60, 109, 0.1);
            color: var(--primary-color);
            transform: scale(1.1);
        }

        .message-input-row {
            display: flex;
            align-items: end;
            gap: 10px;
        }

        .message {
            margin-bottom: 15px;
            display: flex;
            align-items: flex-end;
            width: 100%;
        }

        .message.sent {
            justify-content: flex-end;
            margin-left: auto;
        }

        .message.received {
            justify-content: flex-start;
            margin-right: auto;
        }

        .message-content {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 18px;
            position: relative;
            word-wrap: break-word;
            display: inline-block;
            margin: 8px 0;
        }

        .message.sent .message-content {
            background: var(--message-sent);
            color: white;
            border-bottom-right-radius: 4px;
            margin-left: auto;
            text-align: right;
        }

        .message.received .message-content {
            background: var(--message-received);
            color: var(--text-dark);
            border-bottom-left-radius: 4px;
        }

        .message-time {
            font-size: 0.75rem;
            opacity: 0.7;
            margin-top: 4px;
        }

        .message-reactions {
            position: absolute;
            top: -8px;
            right: -8px;
            display: flex;
            gap: 2px;
            background: white;
            border-radius: 12px;
            padding: 2px 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            z-index: 5;
        }

        .reaction {
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s ease;
            padding: 1px;
        }

        .reaction:hover {
            transform: scale(1.2);
        }

        .friend-item {
            padding: 12px 15px;
            border-bottom: 1px solid #f1f3f4;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            background: white;
        }

        .friend-item:hover {
            background: #f8f9fa;
        }

        .friend-item.active {
            background: var(--primary-color);
            color: white !important;
        }
        
        .friend-item.active .friend-name,
        .friend-item.active .small {
            color: white !important;
        }

        .friend-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 12px;
            object-fit: cover;
        }

        .online-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #28a745;
            border: 2px solid white;
            position: absolute;
            bottom: 0;
            right: 0;
        }

        .offline-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #6c757d;
            border: 2px solid white;
            position: absolute;
            bottom: 0;
            right: 0;
        }

        .online-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #28a745;
            border: 2px solid white;
            position: absolute;
            bottom: 0;
            right: 0;
            box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.3);
        }
        
        .away-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #ffc107;
            border: 2px solid white;
            position: absolute;
            bottom: 0;
            right: 0;
        }



        .message-input {
            border: none;
            outline: none;
            resize: none;
            padding: 10px 15px;
            border-radius: 20px;
            background: #f8f9fa;
            flex: 1;
            margin-right: 10px;
        }

        .message-input:focus {
            background: white;
            box-shadow: 0 0 0 2px var(--primary-color);
        }

        .send-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .send-btn:hover {
            background: var(--secondary-color);
            transform: scale(1.05);
        }

        .emoji-picker {
            background: var(--white);
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: absolute;
            bottom: 100%;
            left: 0;
            margin-bottom: 10px;
            z-index: 1000;
            min-width: 200px;
            max-width: 300px;
        }

        .emoji-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 8px;
        }

        .emoji {
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
            border-radius: 5px;
            transition: all 0.2s ease;
            text-align: center;
        }

        .emoji:hover {
            background: rgba(26, 60, 109, 0.1);
            transform: scale(1.2);
        }



        .voice-recording {
            background: #dc3545;
            color: white;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .file-upload {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin: 10px 0;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .file-upload:hover {
            border-color: var(--primary-color);
            background: #f0f8ff;
        }



        .typing-indicator {
            font-style: italic;
            color: #6c757d;
            padding: 5px 15px;
        }

        .message-actions {
            position: absolute;
            top: -30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: none;
            padding: 5px;
            z-index: 10;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .message.sent .message-actions {
            right: 0;
        }

        .message.received .message-actions {
            left: 10px;
        }

        .message-actions.show {
            display: flex;
            opacity: 1;
        }

        .message-content:hover .message-actions {
            display: flex;
            opacity: 1;
        }

        .action-btn {
            background: none;
            border: none;
            padding: 5px;
            cursor: pointer;
            border-radius: 4px;
            margin: 0 2px;
        }

        .action-btn:hover {
            background: #f8f9fa;
        }

        .action-btn.active {
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            transform: scale(1.1);
        }

        .action-btn.active:hover {
            background: var(--secondary-color);
        }

        .voice-call-btn, .video-call-btn {
            background: none;
            border: none;
            color: white;
            padding: 8px;
            border-radius: 50%;
            margin-left: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .voice-call-btn:hover, .video-call-btn:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .unread-badge {
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            margin-left: auto;
        }

        .emoji-grid {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            gap: 8px;
            padding: 15px;
            max-height: 300px;
            overflow-y: auto;
        }

        .emoji {
            font-size: 1.3rem;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            text-align: center;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
        }

        .emoji:hover {
            background-color: #f8f9fa;
        }

        @media (max-width: 576px) {
            .emoji-grid {
                grid-template-columns: repeat(6, 1fr);
                gap: 6px;
                padding: 10px;
            }
        }

        @media (max-width: 400px) {
            .emoji-grid {
                grid-template-columns: repeat(5, 1fr);
                gap: 5px;
                padding: 8px;
            }
        }

        @media (max-width: 768px) {
            .chat-container {
                flex-direction: column;
                height: calc(100vh - 140px);
                position: relative;
            }
            
            .chat-sidebar {
                width: 100%;
                height: 100%;
                display: block;
                border-right: none;
                position: absolute;
                top: 0;
                left: 0;
                z-index: 10;
                background: white;
                transition: transform 0.3s ease;
            }
            
            .chat-sidebar.hidden {
                transform: translateX(-100%);
            }
            
            .chat-main {
                width: 100%;
                height: 100%;
                display: flex;
                position: absolute;
                top: 0;
                left: 0;
                z-index: 5;
                background: white;
            }
            
            .chat-main.hidden {
                display: none;
            }
            
            .chat-messages {
                padding: 10px;
            }
            
            .chat-input {
                padding: 10px 15px;
            }
            
            .message-content {
                max-width: 85%;
            }
            
            .friend-avatar {
                width: 35px;
                height: 35px;
            }
            
            .search-box {
                background: white;
                border-radius: 15px;
                padding: 10px 15px;
                box-shadow: 0 2px 8px rgba(26, 60, 109, 0.1);
                margin-bottom: 15px;
                transition: all 0.3s ease;
            }
            
            .search-input {
                border: none;
                outline: none;
                width: 100%;
                font-size: 0.9rem;
                background: transparent;
                color: #333;
                padding: 5px 0;
            }
            
            .search-input::placeholder {
                color: #6b7280;
            }
            
            .search-input:focus {
                box-shadow: none;
            }
            
            .search-box:hover {
                box-shadow: 0 6px 20px rgba(26, 60, 109, 0.15);
                transform: translateY(-1px);
            }
            
            .friend-item {
                padding: 8px 12px;
            }
            
            .friend-item h6 {
                font-size: 0.9rem;
            }
            
            .friend-item p {
                font-size: 0.8rem;
            }
            
            .chat-header {
                padding: 10px 15px;
            }
            
            .chat-header h6 {
                font-size: 0.9rem;
            }
            
            .voice-call-btn, .video-call-btn {
                padding: 6px;
                margin-left: 5px;
            }
            
            /* Mobile back button */
            .mobile-back-btn {
                display: block !important;
                background: none;
                border: none;
                color: white;
                font-size: 1.2rem;
                padding: 5px;
                margin-right: 10px;
            }
            
            .mobile-back-btn:hover {
                color: rgba(255, 255, 255, 0.8);
            }
        }


        @media (min-width: 769px) {
            .search-box {
                margin-bottom: 10px;
                padding: 10px 15px;
                border-radius: 25px;
                box-shadow: 0 4px 15px rgba(26, 60, 109, 0.1);
            }
            
            .search-input {
                border: none;
                outline: none;
                font-size: 1rem;
                padding: 0;
                background: transparent;
            }
        }

        /* File Attachment Styles */
        .message-text {
            margin-bottom: 8px;
            word-wrap: break-word;
        }

        .image-attachment {
            margin-top: 8px;
        }

        .message-image {
            max-width: 300px;
            max-height: 300px;
            border-radius: 12px;
            cursor: pointer;
            transition: transform 0.2s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .message-image:hover {
            transform: scale(1.02);
        }

        .file-attachment {
            display: flex;
            align-items: center;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 12px;
            margin-top: 8px;
            max-width: 350px;
            transition: all 0.2s ease;
        }

        .file-attachment:hover {
            background: #e9ecef;
            border-color: #dee2e6;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .file-icon {
            font-size: 2rem;
            color: #6c757d;
            margin-right: 12px;
            min-width: 40px;
        }

        .file-icon .fa-file-pdf {
            color: #dc3545;
        }

        .file-icon .fa-file-word {
            color: #0d6efd;
        }

        .file-icon .fa-file-alt {
            color: #6c757d;
        }

        .file-info {
            flex: 1;
            min-width: 0;
        }

        .file-name {
            font-weight: 600;
            color: #495057;
            margin-bottom: 4px;
            word-break: break-word;
            font-size: 0.9rem;
        }

        .file-size {
            font-size: 0.8rem;
            color: #6c757d;
        }

        .file-download {
            margin-left: 12px;
        }

        .download-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            color: white;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .download-btn:hover {
            background: var(--accent-color);
            color: white;
            transform: scale(1.05);
        }

        /* Image Modal Styles */
        .image-modal {
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .image-modal-content {
            position: relative;
            max-width: 90%;
            max-height: 90%;
            text-align: center;
        }

        .image-modal-close {
            position: absolute;
            top: -40px;
            right: 0;
            color: white;
            font-size: 35px;
            font-weight: bold;
            cursor: pointer;
            z-index: 10000;
        }

        .image-modal-close:hover {
            color: #ddd;
        }

        .image-modal-img {
            max-width: 100%;
            max-height: 100%;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        /* Mobile Responsive File Attachments */
        @media (max-width: 768px) {
            .message-image {
                max-width: 250px;
                max-height: 250px;
            }

            .file-attachment {
                max-width: 280px;
                padding: 10px;
            }

            .file-icon {
                font-size: 1.5rem;
                margin-right: 10px;
                min-width: 35px;
            }

            .download-btn {
                width: 35px;
                height: 35px;
            }

            .image-modal-content {
                max-width: 95%;
                max-height: 95%;
            }

            /* Ensure last message is visible above input on mobile */
            .chat-messages {
                padding-bottom: 160px !important;
                margin-bottom: 20px;
                /* Ensure smooth scrolling on mobile */
                -webkit-overflow-scrolling: touch;
                scroll-behavior: smooth;
            }

            /* Add safe area for mobile devices */
            .chat-messages::after {
                content: '';
                display: block;
                height: 20px;
                width: 100%;
            }

            /* Ensure input area is properly positioned */
            .chat-input {
                box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
            }

            /* Add bottom safe area for devices with home indicators */
            .chat-messages {
                padding-bottom: calc(160px + env(safe-area-inset-bottom)) !important;
            }
        }
    </style>
</head>

<body>
    <!-- Include Navigation -->
    <?php include 'nav.php'; ?>

    <!-- ======= Main Content ======= -->
    <main id="main" class="main" style="margin-top: 5px; padding: 20px; height: calc(100vh - 100px); width: 100%;">

        <div class="chat-container">
            <!-- Chat Sidebar -->
            <div class="chat-sidebar" id="chatSidebar">
                <div class="search-box">
                    <input type="text" class="search-input" placeholder="Search friends..." id="friendSearch">
                </div>
                
                <div class="flex-grow-1 overflow-auto">
                    <?php if (empty($friends)): ?>
                        <div class="text-center p-4">
                            <i class="fas fa-users fa-2x text-muted mb-3"></i>
                            <p class="text-muted">No friends yet. Add friends to start chatting!</p>
                            <a href="friends.php" class="btn btn-primary btn-sm">Find Friends</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($friends as $friend): ?>
                            <?php
                            // Get conversation with this friend
                            $conversation = getConversation($_SESSION['user_id'], $friend['user_id'], $pdo);
                            $last_message = $conversation ? getLastChat($conversation['conversation_id'], $pdo) : null;
                            $unread_count = $conversation ? getUnreadCount($_SESSION['user_id'], $conversation['conversation_id'], $pdo) : 0;
                            ?>
                            <div class="friend-item <?php echo (isset($_GET['conversation_id']) && $conversation && $_GET['conversation_id'] == $conversation['conversation_id']) ? 'active' : ''; ?>" 
                                 data-conversation-id="<?php echo $conversation ? $conversation['conversation_id'] : ''; ?>"
                                 data-friend-id="<?php echo $friend['user_id']; ?>"
                                 data-last-seen="<?php echo htmlspecialchars($friend['last_seen'] ?? ''); ?>"
                                 data-is-online="<?php echo htmlspecialchars($friend['online_status'] ?? 'offline'); ?>"
                                 onclick="selectConversation(<?php echo $conversation ? $conversation['conversation_id'] : 'null'; ?>, <?php echo $friend['user_id']; ?>)">
                                <div class="position-relative">
                                    <img src="profile/<?php echo htmlspecialchars($friend['profile_image'] ?? 'default-avatar.png'); ?>" 
                                         class="friend-avatar" alt="<?php echo htmlspecialchars($friend['first_name'] . ' ' . $friend['last_name']); ?>">
                                    <div class="<?php 
                                        if (isset($friend['online_status'])) {
                                            if ($friend['online_status'] === 'online') {
                                                echo 'online-indicator';
                                            } elseif ($friend['online_status'] === 'away') {
                                                echo 'away-indicator';
                                            } else {
                                                echo 'offline-indicator';
                                            }
                                        } else {
                                            echo 'offline-indicator';
                                        }
                                    ?>"></div>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($friend['first_name'] . ' ' . $friend['last_name']); ?></h6>
                                    <p class="mb-0 small text-muted">
                                        <?php if ($last_message): ?>
                                            <?php 
                                                echo htmlspecialchars(substr($last_message['message'], 0, 30)); 
                                            ?>
                                            <?php echo strlen($last_message['message']) > 30 ? '...' : ''; ?>
                                        <?php else: ?>
                                            Start a conversation
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <?php if ($unread_count > 0): ?>
                                    <div class="unread-badge"><?php echo $unread_count; ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Chat Main Area -->
            <div class="chat-main" id="chatMain">
                <!-- Welcome Screen -->
                <div class="welcome-screen" id="welcomeScreen">
                    <div class="d-flex align-items-center justify-content-center h-100">
                        <div class="text-center">
                            <i class="fas fa-comments fa-4x text-muted mb-4"></i>
                            <h4 class="text-muted">Welcome to Private Chat</h4>
                            <p class="text-muted">Select a friend from the sidebar to start chatting</p>
                        </div>
                    </div>
                </div>

                <!-- Chat Interface -->
                <div class="chat-interface" id="chatInterface" style="display: none;">
                    <!-- Chat Header -->
                    <div class="chat-header">
                        <div class="d-flex align-items-center">
                            <button class="mobile-back-btn d-none" onclick="showFriendsList()" title="Back to friends">
                                <i class="fas fa-arrow-left"></i>
                            </button>
                            <div class="position-relative">
                                <img src="" class="friend-avatar" id="selectedFriendAvatar" alt="Friend">
                                <div class="online-dot" id="selectedFriendOnlineDot" style="display: none;"></div>
                            </div>
                            <div class="ms-3">
                                <h6 class="mb-0" id="selectedFriendName"></h6>
                                <small class="opacity-75" id="selectedFriendStatus"></small>
                            </div>
                        </div>
                        <div class="d-flex align-items-center">
                            <button class="btn btn-sm btn-outline-light ms-2 d-none d-md-block" onclick="toggleSidebar()">
                                <i class="fas fa-bars"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Chat Messages -->
                    <div class="chat-messages" id="chatMessages">
                        <!-- Messages will be loaded here -->
                    </div>
                    


                    <!-- Chat Input -->
                    <div class="chat-input">
                        <div class="chat-input-container">
                            <!-- Action Buttons Row -->
                            <div class="action-buttons-row">
                                <button class="action-btn" onclick="toggleFileUpload()" title="Attach Files">
                                    <i class="fas fa-paperclip"></i>
                                </button>
                                <button class="action-btn" onclick="toggleEmojiPicker()" title="Emoji">
                                    <i class="fas fa-smile"></i>
                                </button>
                                <button class="action-btn" onclick="toggleVoiceInput()" title="Voice Message">
                                    <i class="fas fa-microphone"></i>
                                </button>
                            </div>
                            
                            <!-- Message Input Row -->
                            <div class="message-input-row">
                                <textarea class="message-input" id="messageInput" placeholder="Type your message..." rows="1"></textarea>
                                <button class="send-btn" onclick="sendMessage()" title="Send Message">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                            
                            <!-- Hidden File Input -->
                            <input type="file" id="fileInput" style="display: none;" accept="image/*,.pdf,.doc,.docx,.txt" onchange="handleFileUpload()">
                            
                            <!-- Emoji Picker (Hidden by default) -->
                            <div class="emoji-picker" id="emojiPicker" style="display: none;">
                                <div class="emoji-grid">
                                    <span class="emoji" onclick="insertEmoji('😊')">😊</span>
                                    <span class="emoji" onclick="insertEmoji('😂')">😂</span>
                                    <span class="emoji" onclick="insertEmoji('❤️')">❤️</span>
                                    <span class="emoji" onclick="insertEmoji('👍')">👍</span>
                                    <span class="emoji" onclick="insertEmoji('🎉')">🎉</span>
                                    <span class="emoji" onclick="insertEmoji('🔥')">🔥</span>
                                    <span class="emoji" onclick="insertEmoji('😎')">😎</span>
                                    <span class="emoji" onclick="insertEmoji('🤔')">🤔</span>
                                    <span class="emoji" onclick="insertEmoji('👏')">👏</span>
                                    <span class="emoji" onclick="insertEmoji('💯')">💯</span>
                                    <span class="emoji" onclick="insertEmoji('✨')">✨</span>
                                    <span class="emoji" onclick="insertEmoji('🚀')">🚀</span>
                                </div>
                            </div>
                            

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>





]
    <!-- Vendor JS Files -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    
    <!-- AOS Animation -->
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>

    <!-- Template Main JS File -->
    <script>
        // Prevent TinyMCE errors by checking if it exists
        if (typeof tinymce !== 'undefined') {
            // Only load main.js if TinyMCE is available
            document.write('<script src="assets/js/main.js"><\/script>');
        }
    </script>

    <script>
        let currentConversationId = <?php echo $selected_conversation ? $selected_conversation['conversation_id'] : 'null'; ?>;
        let lastMessageId = 0;
        let messageInterval;
        let typingTimer;

        // Handle mobile viewport height changes for keyboard
        function handleViewportHeight() {
            const vh = window.innerHeight * 0.01;
            document.documentElement.style.setProperty('--vh', `${vh}px`);
            
            // On mobile, adjust chat messages padding when keyboard appears
            if (window.innerWidth <= 768) {
                const chatMessages = document.getElementById('chatMessages');
                if (chatMessages) {
                    // Add extra padding when keyboard is likely open
                    if (window.innerHeight < window.outerHeight * 0.8) {
                        chatMessages.style.paddingBottom = '200px';
                    } else {
                        chatMessages.style.paddingBottom = '160px';
                    }
                }
            }
        }

        // Initialize chat
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize mobile layout
            if (window.innerWidth <= 768) {
                const sidebar = document.getElementById('chatSidebar');
                const main = document.getElementById('chatMain');
                const backBtn = document.querySelector('.mobile-back-btn');
                
                // If there's a conversation selected, show chat panel
                if (currentConversationId) {
                    sidebar.classList.add('hidden');
                    main.classList.remove('hidden');
                    if (backBtn) backBtn.classList.remove('d-none');
                } else {
                    // Show friends list by default
                    sidebar.classList.remove('hidden');
                    main.classList.add('hidden');
                    if (backBtn) backBtn.classList.add('d-none');
                }
            }
            
            if (currentConversationId) {
                // Auto-open the conversation if one is selected
                autoOpenConversation();
                loadMessages(true); // Force scroll to bottom when restoring conversation on refresh
                startMessagePolling();
            }
            
            // Setup hover events for existing messages
            setupMessageHoverEvents();
            
            // Mark user as active when scrolling chat messages
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.addEventListener('scroll', updateOnlineStatusOnActivity);
            }
            
            // Mark user as active when clicking on chat interface elements
            const chatInterface = document.getElementById('chatInterface');
            if (chatInterface) {
                chatInterface.addEventListener('click', updateOnlineStatusOnActivity);
            }
            
            // Mark user as active when clicking on sidebar elements
            const chatSidebar = document.getElementById('chatSidebar');
            if (chatSidebar) {
                chatSidebar.addEventListener('click', updateOnlineStatusOnActivity);
            }
            

            
            // Mark user as active when moving mouse (with throttling)
            let mouseMoveTimeout;
            document.addEventListener('mousemove', function() {
                if (mouseMoveTimeout) {
                    clearTimeout(mouseMoveTimeout);
                }
                mouseMoveTimeout = setTimeout(updateOnlineStatusOnActivity, 5000); // Throttle to every 5 seconds
            });
            
            // Auto-resize textarea
            const messageInput = document.getElementById('messageInput');
            if (messageInput) {
                messageInput.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = Math.min(this.scrollHeight, 100) + 'px';
                    // Mark user as active when typing
                    updateOnlineStatusOnActivity();
                });
                
                // Send message on Enter
                messageInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        sendMessage();
                    }
                    // Mark user as active when typing
                    updateOnlineStatusOnActivity();
                });
                
                            // Mark user as active when focusing on textarea
            messageInput.addEventListener('focus', function() {
                updateOnlineStatusOnActivity();
            });
            
            // Handle viewport height changes for mobile keyboard
            handleViewportHeight();
            window.addEventListener('resize', handleViewportHeight);
            window.addEventListener('orientationchange', handleViewportHeight);
            }
            
            // Friend search
            const friendSearch = document.getElementById('friendSearch');
            if (friendSearch) {
                friendSearch.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const friendItems = document.querySelectorAll('.friend-item');
                    
                    friendItems.forEach(item => {
                        const name = item.querySelector('h6').textContent.toLowerCase();
                        if (name.includes(searchTerm)) {
                            item.style.display = 'flex';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                    
                    // Mark user as active when searching
                    updateOnlineStatusOnActivity();
                });
            }
        });

        function autoOpenConversation() {
            if (!currentConversationId) return;
            
            // Update URL to show conversation_id
            const url = new URL(window.location);
            url.searchParams.set('conversation_id', currentConversationId);
            window.history.pushState({}, '', url);
            
            // Find the friend item for this conversation
            const friendItem = document.querySelector(`[data-conversation-id="${currentConversationId}"]`);
            if (!friendItem) return;
            
            // Update active state in sidebar
            document.querySelectorAll('.friend-item').forEach(item => {
                item.classList.remove('active');
            });
            friendItem.classList.add('active');
            
            // Get friend information from the element
            const friendName = friendItem.querySelector('h6').textContent;
            const friendAvatar = friendItem.querySelector('.friend-avatar').src;
            
            // Get online status and last seen from data attributes
            const isOnline = friendItem.getAttribute('data-is-online');
            const lastSeen = friendItem.getAttribute('data-last-seen');
            
            // Determine status text
            let friendStatus;
            if (isOnline === 'online') {
                friendStatus = 'Online';
            } else if (isOnline === 'away') {
                friendStatus = 'Away';
            } else if (lastSeen) {
                friendStatus = `Last seen ${lastSeen}`;
            } else {
                friendStatus = 'Offline';
            }
            
            // Update chat interface
            document.getElementById('selectedFriendName').textContent = friendName;
            document.getElementById('selectedFriendAvatar').src = friendAvatar;
            document.getElementById('selectedFriendStatus').textContent = friendStatus;
            
            // Show/hide online dot with proper styling
            const onlineDot = document.getElementById('selectedFriendOnlineDot');
            if (onlineDot) {
                if (isOnline === 'online') {
                    onlineDot.style.display = 'block';
                    onlineDot.className = 'online-dot';
                } else if (isOnline === 'away') {
                    onlineDot.style.display = 'block';
                    onlineDot.className = 'away-indicator';
                } else {
                    onlineDot.style.display = 'none';
                }
            }
            
            // Show chat interface and hide welcome screen
            document.getElementById('welcomeScreen').style.display = 'none';
            document.getElementById('chatInterface').style.display = 'flex';
            document.getElementById('chatInterface').style.flexDirection = 'column';
            document.getElementById('chatInterface').style.height = '100%';
            
            // On mobile, show chat and hide sidebar
            if (window.innerWidth <= 768) {
                showChatPanel();
            }
        }

        function selectConversation(conversationId, friendId) {
            if (!conversationId) {
                // Create new conversation
                createConversation(friendId);
                return;
            }
            
            currentConversationId = conversationId;
            
            // Reset lastMessageId when switching conversations
            lastMessageId = 0;
            
            // Clear existing messages from chat interface
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.innerHTML = '';
            }
            
            // Update URL without page reload
            const url = new URL(window.location);
            url.searchParams.set('conversation_id', conversationId);
            window.history.pushState({}, '', url);
            
            // Update active state in sidebar
            document.querySelectorAll('.friend-item').forEach(item => {
                item.classList.remove('active');
            });
            event.target.closest('.friend-item').classList.add('active');
            
            // Get friend information from the clicked element
            const friendItem = event.target.closest('.friend-item');
            const friendName = friendItem.querySelector('h6').textContent;
            const friendAvatar = friendItem.querySelector('.friend-avatar').src;
            
            // Get online status and last seen from data attributes
            const isOnline = friendItem.getAttribute('data-is-online');
            const lastSeen = friendItem.getAttribute('data-last-seen');
            
            // Determine status text
            let friendStatus;
            if (isOnline === 'online') {
                friendStatus = 'Online';
            } else if (isOnline === 'away') {
                friendStatus = 'Away';
            } else if (lastSeen) {
                friendStatus = `Last seen ${lastSeen}`;
            } else {
                friendStatus = 'Offline';
            }
            
            // Update chat interface
            document.getElementById('selectedFriendName').textContent = friendName;
            document.getElementById('selectedFriendAvatar').src = friendAvatar;
            document.getElementById('selectedFriendStatus').textContent = friendStatus;
            
            // Show/hide online dot with proper styling
            const onlineDot = document.getElementById('selectedFriendOnlineDot');
            if (onlineDot) {
                if (isOnline === 'online') {
                    onlineDot.style.display = 'block';
                    onlineDot.className = 'online-dot';
                } else if (isOnline === 'away') {
                    onlineDot.style.display = 'block';
                    onlineDot.className = 'away-indicator';
                } else {
                    onlineDot.style.display = 'none';
                }
            }
            
            // Show chat interface and hide welcome screen
            document.getElementById('welcomeScreen').style.display = 'none';
            document.getElementById('chatInterface').style.display = 'flex';
            document.getElementById('chatInterface').style.flexDirection = 'column';
            document.getElementById('chatInterface').style.height = '100%';
            
            // On mobile, show chat and hide sidebar
            if (window.innerWidth <= 768) {
                showChatPanel();
            }
            
            // Load messages after ensuring the chat panel is visible
            setTimeout(() => {
                loadMessages(true); // Force scroll to bottom for new conversation
                
                // Start polling for new messages
                if (messageInterval) {
                    clearInterval(messageInterval);
                }
                startMessagePolling();
            }, 100);
        }

        function createConversation(friendId) {
            fetch('private.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=create_conversation&friend_id=${friendId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update URL without page reload
                    const url = new URL(window.location);
                    url.searchParams.set('conversation_id', data.conversation_id);
                    window.history.pushState({}, '', url);
                    
                    currentConversationId = data.conversation_id;
                    
                    // Reset lastMessageId for new conversation
                    lastMessageId = 0;
                    
                    // Clear existing messages from chat interface
                    const chatMessages = document.getElementById('chatMessages');
                    if (chatMessages) {
                        chatMessages.innerHTML = '';
                    }
                    
                    // Get friend information from the clicked element
                    const friendItem = event.target.closest('.friend-item');
                    const friendName = friendItem.querySelector('h6').textContent;
                    const friendAvatar = friendItem.querySelector('.friend-avatar').src;
                    
                    // Get online status and last seen from data attributes
                    const isOnline = friendItem.getAttribute('data-is-online');
                    const lastSeen = friendItem.getAttribute('data-last-seen');
                    
                    // Determine status text
                    let friendStatus;
                    if (isOnline === 'online') {
                        friendStatus = 'Online';
                    } else if (isOnline === 'away') {
                        friendStatus = 'Away';
                    } else if (lastSeen) {
                        friendStatus = `Last seen ${lastSeen}`;
                    } else {
                        friendStatus = 'Offline';
                    }
                    
                    // Update chat interface
                    document.getElementById('selectedFriendName').textContent = friendName;
                    document.getElementById('selectedFriendAvatar').src = friendAvatar;
                    document.getElementById('selectedFriendStatus').textContent = friendStatus;
                    
                    // Show/hide online dot with proper styling
                    const onlineDot = document.getElementById('selectedFriendOnlineDot');
                    if (onlineDot) {
                        if (isOnline === 'online') {
                            onlineDot.style.display = 'block';
                            onlineDot.className = 'online-dot';
                        } else if (isOnline === 'away') {
                            onlineDot.style.display = 'block';
                            onlineDot.className = 'away-indicator';
                        } else {
                            onlineDot.style.display = 'none';
                        }
                    }
                    
                    // Show chat interface and hide welcome screen
                    document.getElementById('welcomeScreen').style.display = 'none';
                    document.getElementById('chatInterface').style.display = 'flex';
                    document.getElementById('chatInterface').style.flexDirection = 'column';
                    document.getElementById('chatInterface').style.height = '100%';
                    
                    // On mobile, show chat and hide sidebar
                    if (window.innerWidth <= 768) {
                        showChatPanel();
                    }
                    
                    // Load messages after ensuring the chat panel is visible
                    setTimeout(() => {
                        loadMessages(true); // Force scroll to bottom for new conversation
                        
                        // Start polling for new messages
                        if (messageInterval) {
                            clearInterval(messageInterval);
                        }
                        startMessagePolling();
                    }, 100);
                }
            });
        }

        function loadMessages(forceScrollToBottom = false) {
            if (!currentConversationId) {
                console.log('No conversation ID set');
                return;
            }
            
            console.log('Loading messages for conversation:', currentConversationId);
            
            // Store current scroll position before loading messages
            const chatMessages = document.getElementById('chatMessages');
            const wasAtBottom = chatMessages ? (chatMessages.scrollHeight - chatMessages.scrollTop - chatMessages.clientHeight < 50) : true;
            
            fetch('private.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_messages&conversation_id=${currentConversationId}&last_message_id=${lastMessageId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Messages received:', data.messages.length);
                    data.messages.forEach(message => {
                        addMessageToChat(message);
                        lastMessageId = Math.max(lastMessageId, message.id);
                    });
                    
                    // Scroll to bottom if forced, user was already at bottom, or if it's a new conversation
                    if (forceScrollToBottom || wasAtBottom) {
                        scrollToBottom();
                        
                        // On mobile, ensure extra padding for input area
                        if (window.innerWidth <= 768) {
                            setTimeout(() => {
                                const chatMessages = document.getElementById('chatMessages');
                                if (chatMessages) {
                                    chatMessages.scrollTop = chatMessages.scrollHeight + 150;
                                }
                            }, 200);
                        }
                    }
                    
                    // Setup hover events for the newly added messages
                    setupMessageHoverEvents();
                } else {
                    console.error('Failed to load messages:', data.error);
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
            });
        }

        function addMessageToChat(message) {
            const chatMessages = document.getElementById('chatMessages');
            if (!chatMessages) {
                console.error('Chat messages container not found');
                return;
            }
            
            // Check if message already exists to prevent duplicates
            const existingMessage = chatMessages.querySelector(`[data-message-id="${message.id}"]`);
            if (existingMessage) {
                console.log('Message already exists, skipping:', message.id);
                return;
            }
            
            const isOwnMessage = message.sender_id == <?php echo $_SESSION['user_id']; ?>;
            
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${isOwnMessage ? 'sent' : 'received'}`;
            messageDiv.setAttribute('data-message-id', message.id);
            
            const messageContent = formatMessage(message);
            
            messageDiv.innerHTML = `
                <div class="message-content">
                    ${messageContent}
                    <div class="message-time">${formatTime(message.created_at)}</div>
                    ${message.reactions ? formatReactions(message.reactions) : ''}
                    <div class="message-actions">
                        <button class="action-btn ${message.user_reaction === '👍' ? 'active' : ''}" onclick="event.stopPropagation(); addReaction(${message.id}, '👍')">👍</button>
                        <button class="action-btn ${message.user_reaction === '❤️' ? 'active' : ''}" onclick="event.stopPropagation(); addReaction(${message.id}, '❤️')">❤️</button>
                        <button class="action-btn ${message.user_reaction === '😂' ? 'active' : ''}" onclick="event.stopPropagation(); addReaction(${message.id}, '😂')">😂</button>
                    </div>
                </div>
            `;
            
            chatMessages.appendChild(messageDiv);
        }

        function formatMessage(message) {
            let content = '';
            
            // Add text message if exists
            if (message.message && message.message.trim()) {
                content += `<div class="message-text">${message.message}</div>`;
            }
            
            // Add file attachment if exists
            if (message.file_url) {
                if (message.message_type === 'image') {
                    content += `
                        <div class="image-attachment">
                            <img src="${message.file_url}" alt="Image" class="message-image" onclick="openImageModal('${message.file_url}')">
                        </div>
                    `;
                } else if (message.message_type === 'file') {
                    const fileExtension = message.file_name.split('.').pop().toLowerCase();
                    let fileIcon = 'fa-file-alt'; // Default icon
                    
                    // Set appropriate icon based on file type
                    if (['pdf'].includes(fileExtension)) {
                        fileIcon = 'fa-file-pdf';
                    } else if (['doc', 'docx'].includes(fileExtension)) {
                        fileIcon = 'fa-file-word';
                    } else if (['txt'].includes(fileExtension)) {
                        fileIcon = 'fa-file-alt';
                    }
                    
                    content += `
                        <div class="file-attachment">
                            <div class="file-icon">
                                <i class="fas ${fileIcon}"></i>
                            </div>
                            <div class="file-info">
                                <div class="file-name">${message.file_name}</div>
                                <div class="file-size">${formatFileSize(message.file_size)}</div>
                            </div>
                            <div class="file-download">
                                <button class="download-btn" title="Download File" onclick="downloadFile('${message.file_url}', '${message.file_name}')">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                        </div>
                    `;
                }
            }
            
            return content || 'No content';
        }

        function formatTime(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function openImageModal(imageSrc) {
            // Create modal HTML
            const modalHTML = `
                <div id="imageModal" class="image-modal" onclick="closeImageModal()">
                    <div class="image-modal-content">
                        <span class="image-modal-close">&times;</span>
                        <img src="${imageSrc}" alt="Full size image" class="image-modal-img">
                    </div>
                </div>
            `;
            
            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            
            // Prevent body scroll
            document.body.style.overflow = 'hidden';
        }

        function closeImageModal() {
            const modal = document.getElementById('imageModal');
            if (modal) {
                modal.remove();
                document.body.style.overflow = 'auto';
            }
        }

        function downloadFile(filePath, fileName) {
            // Create a form to submit the download request
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'private.php';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'download_file';
            
            const filePathInput = document.createElement('input');
            filePathInput.type = 'hidden';
            filePathInput.name = 'file_path';
            filePathInput.value = filePath;
            
            const fileNameInput = document.createElement('input');
            fileNameInput.type = 'hidden';
            fileNameInput.name = 'file_name';
            fileNameInput.value = fileName;
            
            form.appendChild(actionInput);
            form.appendChild(filePathInput);
            form.appendChild(fileNameInput);
            
            // Submit the form
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        function formatReactions(reactions) {
            if (!reactions) return '';
            
            const uniqueReactions = new Set();
            reactions.split(',').forEach(reaction => {
                const [type, userId] = reaction.split(':');
                uniqueReactions.add(type);
            });
            
            return `
                <div class="message-reactions">
                    ${Array.from(uniqueReactions).map(type => 
                        `<span class="reaction">${type}</span>`
                    ).join('')}
                </div>
            `;
        }

        function sendMessage() {
            const messageInput = document.getElementById('messageInput');
            const message = messageInput.value.trim();
            
            if (!message || !currentConversationId) return;
            
            // Mark user as active when sending message
            updateOnlineStatusOnActivity();
            
            fetch('private.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=send_message&conversation_id=${currentConversationId}&message=${encodeURIComponent(message)}&message_type=text`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    messageInput.value = '';
                    messageInput.style.height = 'auto';
                    handleMessageSent();
                }
            });
        }

        function addReaction(messageId, reactionType) {
            console.log('Adding reaction:', reactionType, 'to message:', messageId);
            
            // Mark user as active when adding reaction
            updateOnlineStatusOnActivity();
            
            // Find the message element
            const messageElement = document.querySelector(`[data-message-id="${messageId}"]`);
            if (!messageElement) {
                console.error('Message element not found for ID:', messageId);
                return;
            }
            
            // Find the reaction button that was clicked
            const reactionButton = event.target;
            const isCurrentlyActive = reactionButton.classList.contains('active');
            
            console.log('Current reaction state:', isCurrentlyActive ? 'active' : 'inactive');
            
            // Store original state for potential revert
            const originalReactions = messageElement.querySelector('.message-reactions');
            const originalReactionsHTML = originalReactions ? originalReactions.outerHTML : '';
            
            // Immediately update the DOM
            if (isCurrentlyActive) {
                // If already active, remove the reaction (undo)
                console.log('Removing reaction (undo)');
                reactionButton.classList.remove('active');
                
                // Remove this reaction from the message display
                if (originalReactions) {
                    const reactionSpans = originalReactions.querySelectorAll('.reaction');
                    reactionSpans.forEach(span => {
                        if (span.textContent === reactionType) {
                            span.remove();
                        }
                    });
                    
                    // If no reactions left, remove the entire reactions container
                    if (originalReactions.children.length === 0) {
                        originalReactions.remove();
                    }
                }
            } else {
                // Remove active class from other reaction buttons on this message
                const otherButtons = messageElement.querySelectorAll('.action-btn');
                otherButtons.forEach(btn => btn.classList.remove('active'));
                // Add active class to clicked button
                console.log('Adding new reaction');
                reactionButton.classList.add('active');
                
                // Add this reaction to the message display
                let reactionsContainer = messageElement.querySelector('.message-reactions');
                if (!reactionsContainer) {
                    reactionsContainer = document.createElement('div');
                    reactionsContainer.className = 'message-reactions';
                    messageElement.querySelector('.message-content').appendChild(reactionsContainer);
                }
                
                // Remove any existing reaction from this user (replace)
                const existingReactions = reactionsContainer.querySelectorAll('.reaction');
                existingReactions.forEach(span => span.remove());
                
                // Add the new reaction
                const reactionSpan = document.createElement('span');
                reactionSpan.className = 'reaction';
                reactionSpan.textContent = reactionType;
                reactionsContainer.appendChild(reactionSpan);
            }
            
            fetch('private.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=add_reaction&message_id=${messageId}&reaction_type=${reactionType}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the reaction count for smart polling
                    handleReactionChange();
                } else {
                    // Revert the visual change if the request failed
                    if (originalReactionsHTML) {
                        const messageContent = messageElement.querySelector('.message-content');
                        const currentReactions = messageContent.querySelector('.message-reactions');
                        if (currentReactions) {
                            currentReactions.outerHTML = originalReactionsHTML;
                        } else if (originalReactionsHTML !== '') {
                            messageContent.insertAdjacentHTML('beforeend', originalReactionsHTML);
                        }
                    }
                    
                    // Revert button states
                    if (isCurrentlyActive) {
                        reactionButton.classList.add('active');
                    } else {
                        reactionButton.classList.remove('active');
                        const otherButtons = messageElement.querySelectorAll('.action-btn');
                        otherButtons.forEach(btn => {
                            if (btn.textContent === reactionType) {
                                btn.classList.add('active');
                            }
                        });
                    }
                }
            })
            .catch(error => {
                console.error('Error adding reaction:', error);
                // Revert the visual change on error
                if (originalReactionsHTML) {
                    const messageContent = messageElement.querySelector('.message-content');
                    const currentReactions = messageContent.querySelector('.message-reactions');
                    if (currentReactions) {
                        currentReactions.outerHTML = originalReactionsHTML;
                    } else if (originalReactionsHTML !== '') {
                        messageContent.insertAdjacentHTML('beforeend', originalReactionsHTML);
                    }
                }
                
                // Revert button states
                if (isCurrentlyActive) {
                    reactionButton.classList.add('active');
                } else {
                    reactionButton.classList.remove('active');
                }
            });
        }

        function startMessagePolling() {
            // Use enhanced polling that checks for specific changes
            messageInterval = setInterval(checkForUpdates, 2000);
        }



        function refreshMessagesImmediately() {
            // Clear any existing timeout to prevent multiple rapid refreshes
            if (window.refreshTimeout) {
                clearTimeout(window.refreshTimeout);
            }
            
            // Set a small delay to ensure the server has processed the change
            window.refreshTimeout = setTimeout(() => {
                loadMessages();
            }, 100);
        }

        function refreshMessagesWithDelay(delay = 500) {
            // Clear any existing timeout
            if (window.refreshTimeout) {
                clearTimeout(window.refreshTimeout);
            }
            
            // Set a custom delay for specific actions
            window.refreshTimeout = setTimeout(() => {
                loadMessages();
            }, delay);
        }

        function handleMessageSent() {
            // Immediately refresh after sending a message
            refreshMessagesImmediately();
            // Force scroll to bottom when user sends a message
            setTimeout(() => {
                const chatMessages = document.getElementById('chatMessages');
                if (chatMessages) {
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                }
            }, 200);
        }

        function handleReactionChange() {
            // Immediately refresh after reaction changes
            refreshMessagesImmediately();
        }

        function scrollToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                // Add a small delay to ensure content is rendered
                setTimeout(() => {
                    if (window.innerWidth <= 768) {
                        // On mobile, add extra padding to ensure last message is visible above input
                        chatMessages.scrollTop = chatMessages.scrollHeight + 150;
                    } else {
                        // On desktop, normal scroll to bottom
                        chatMessages.scrollTop = chatMessages.scrollHeight;
                    }
                }, 100);
            }
        }



        function toggleMessageActions(messageContent) {
            // Hide all other message actions first
            document.querySelectorAll('.message-actions').forEach(action => {
                action.classList.remove('show');
            });
            
            // Toggle the clicked message's actions
            const actions = messageContent.querySelector('.message-actions');
            if (actions) {
                actions.classList.toggle('show');
            }
        }

        function setupMessageHoverEvents() {
            // Add hover event listeners to all message content elements that don't already have them
            document.querySelectorAll('.message-content').forEach(messageContent => {
                // Check if this element already has hover events
                if (messageContent.dataset.hoverEventsAdded) {
                    return;
                }
                
                messageContent.addEventListener('mouseenter', function() {
                    // Hide all other message actions first
                    document.querySelectorAll('.message-actions').forEach(action => {
                        action.classList.remove('show');
                    });
                    
                    // Show this message's actions
                    const actions = this.querySelector('.message-actions');
                    if (actions) {
                        actions.classList.add('show');
                    }
                });
                
                messageContent.addEventListener('mouseleave', function() {
                    // Hide this message's actions when mouse leaves
                    const actions = this.querySelector('.message-actions');
                    if (actions) {
                        actions.classList.remove('show');
                    }
                });
                
                // Mark this element as having hover events
                messageContent.dataset.hoverEventsAdded = 'true';
            });
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('chatSidebar');
            const main = document.getElementById('chatMain');
            
            // Only toggle on desktop
            if (window.innerWidth > 768) {
                sidebar.classList.toggle('show');
                main.classList.toggle('show');
            }
            
            // Mark user as active when toggling sidebar
            updateOnlineStatusOnActivity();
        }

        function showChatPanel() {
            const sidebar = document.getElementById('chatSidebar');
            const main = document.getElementById('chatMain');
            const backBtn = document.querySelector('.mobile-back-btn');
            
            if (window.innerWidth <= 768) {
                sidebar.classList.add('hidden');
                main.classList.remove('hidden');
                if (backBtn) backBtn.classList.remove('d-none');
            }
            
            // Mark user as active when showing chat panel
            updateOnlineStatusOnActivity();
        }

        function showFriendsList() {
            const sidebar = document.getElementById('chatSidebar');
            const main = document.getElementById('chatMain');
            const backBtn = document.querySelector('.mobile-back-btn');
            
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('hidden');
                main.classList.add('hidden');
                if (backBtn) backBtn.classList.add('d-none');
            }
            
            // Mark user as active when showing friends list
            updateOnlineStatusOnActivity();
            
            // Clear current conversation
            currentConversationId = null;
            if (messageInterval) {
                clearInterval(messageInterval);
            }
            
            // Show welcome screen and hide chat interface
            document.getElementById('welcomeScreen').style.display = 'flex';
            document.getElementById('chatInterface').style.display = 'none';
            
            // Update URL
            const url = new URL(window.location);
            url.searchParams.delete('conversation_id');
            window.history.pushState({}, '', url);
        }







        // Enhanced refresh system for immediate updates
        
        function checkForUpdates() {
            if (!currentConversationId) return;
            
            fetch('private.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_messages&conversation_id=${currentConversationId}&last_message_id=${lastMessageId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.messages) {
                    // Only process if there are actually new messages
                    if (data.messages.length > 0) {
                        console.log('New messages detected, refreshing immediately');
                        refreshMessagesImmediately();
                    }
                }
            })
            .catch(error => {
                console.error('Error checking for updates:', error);
            });
        }

        function smartRefresh() {
            // Smart refresh that adapts based on user activity
            const now = Date.now();
            const timeSinceLastActivity = now - (window.lastUserActivity || 0);
            
            // If user is actively using the chat, refresh more frequently
            if (timeSinceLastActivity < 30000) { // 30 seconds
                refreshMessagesImmediately();
            } else {
                // If user is inactive, use normal polling
                checkForUpdates();
            }
        }

        // Track user activity
        function updateUserActivity() {
            window.lastUserActivity = Date.now();
        }

        // Add activity tracking to various user interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Track typing, clicking, scrolling, etc.
            const chatContainer = document.getElementById('chatMessages');
            const messageInput = document.getElementById('messageInput');
            
            if (chatContainer) {
                chatContainer.addEventListener('scroll', updateUserActivity);
                chatContainer.addEventListener('click', updateUserActivity);
            }
            
            if (messageInput) {
                messageInput.addEventListener('input', updateUserActivity);
                messageInput.addEventListener('focus', updateUserActivity);
            }
            
            // Track clicks on reaction buttons
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('action-btn')) {
                    updateUserActivity();
                }
            });
        });
        
        // Handle page refresh and restore conversation state
        document.addEventListener('DOMContentLoaded', function() {
            // Check if there's a conversation ID in the URL
            const urlParams = new URLSearchParams(window.location.search);
            const conversationId = urlParams.get('conversation_id');
            
            if (conversationId) {
                // Restore the conversation
                currentConversationId = conversationId;
                
                // Find the friend item for this conversation
                const friendItems = document.querySelectorAll('.friend-item');
                let targetFriendItem = null;
                
                friendItems.forEach(item => {
                    const dataConversationId = item.getAttribute('data-conversation-id');
                    if (dataConversationId == conversationId) {
                        targetFriendItem = item;
                    }
                });
                
                if (targetFriendItem) {
                    // Get friend information
                    const friendName = targetFriendItem.querySelector('h6').textContent;
                    const friendAvatar = targetFriendItem.querySelector('.friend-avatar').src;
                    
                    // Get online status from the indicator
                    const onlineIndicator = targetFriendItem.querySelector('.online-indicator');
                    const friendStatus = onlineIndicator ? 'Online' : 'Offline';
                    
                    // Update chat interface
                    document.getElementById('selectedFriendName').textContent = friendName;
                    document.getElementById('selectedFriendAvatar').src = friendAvatar;
                    document.getElementById('selectedFriendStatus').textContent = friendStatus;
                    
                    // Show/hide online dot
                    const onlineDot = document.getElementById('selectedFriendOnlineDot');
                    if (onlineDot) {
                        onlineDot.style.display = onlineIndicator ? 'block' : 'none';
                    }
                    
                    // Show chat interface and hide welcome screen
                    document.getElementById('welcomeScreen').style.display = 'none';
                    document.getElementById('chatInterface').style.display = 'flex';
                    
                    // On mobile, show chat panel
                    if (window.innerWidth <= 768) {
                        showChatPanel();
                    }
                    
                    // Load messages
                    setTimeout(() => {
                        loadMessages();
                        startMessagePolling();
                    }, 100);
                    
                    // Update active state
                    friendItems.forEach(item => item.classList.remove('active'));
                    targetFriendItem.classList.add('active');
                }
            }
        });

        // File Upload Functions
        function toggleFileUpload() {
            document.getElementById('fileInput').click();
            
            // Mark user as active when using file upload
            updateOnlineStatusOnActivity();
        }

        function handleFileUpload() {
            const fileInput = document.getElementById('fileInput');
            const file = fileInput.files[0];
            
            if (!file || !currentConversationId) return;
            
            // Mark user as active when uploading files
            updateOnlineStatusOnActivity();
            
            // Validate file size (10MB max)
            if (file.size > 10 * 1024 * 1024) {
                Swal.fire('Error', 'File size too large. Maximum 10MB allowed.', 'error');
                fileInput.value = '';
                return;
            }
            
            // Ask for optional message
            Swal.fire({
                title: 'Add a message (optional)',
                input: 'text',
                inputPlaceholder: 'Type a message to go with your file...',
                showCancelButton: true,
                confirmButtonText: 'Upload File',
                cancelButtonText: 'Cancel',
                inputValidator: (value) => {
                    // Message is optional, so no validation needed
                    return null;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const message = result.value || '';
                    
                    // Show file upload progress
                    Swal.fire({
                        title: 'Uploading File...',
                        html: `Uploading ${file.name}...`,
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Create FormData for file upload
                    const formData = new FormData();
                    formData.append('action', 'upload_file');
                    formData.append('file', file);
                    formData.append('conversation_id', currentConversationId);
                    formData.append('message', message);
                    
                    // Upload file
                    fetch('private.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Success', 'File uploaded successfully!', 'success');
                            fileInput.value = ''; // Clear the input
                            
                            // Refresh messages to show the new file
                            setTimeout(() => {
                                loadMessages(true);
                            }, 500);
                        } else {
                            Swal.fire('Error', data.error || 'Upload failed', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Upload error:', error);
                        Swal.fire('Error', 'Upload failed. Please try again.', 'error');
                    });
                }
            });
        }

        // Emoji Functions
        function toggleEmojiPicker() {
            const emojiPicker = document.getElementById('emojiPicker');
            console.log('Emoji picker element:', emojiPicker);
            
            if (!emojiPicker) {
                console.error('Emoji picker element not found!');
                return;
            }
            
            const isVisible = emojiPicker.style.display !== 'none';
            console.log('Current visibility:', isVisible);
            
            if (isVisible) {
                emojiPicker.style.display = 'none';
                console.log('Hiding emoji picker');
            } else {
                emojiPicker.style.display = 'block';
                console.log('Showing emoji picker');
            }
            
            // Mark user as active when using emoji picker
            updateOnlineStatusOnActivity();
        }

        function insertEmoji(emoji) {
            const messageInput = document.getElementById('messageInput');
            const cursorPos = messageInput.selectionStart;
            const textBefore = messageInput.value.substring(0, cursorPos);
            const textAfter = messageInput.value.substring(cursorPos);
            
            messageInput.value = textBefore + emoji + textAfter;
            messageInput.focus();
            messageInput.setSelectionRange(cursorPos + emoji.length, cursorPos + emoji.length);
            
            // Hide emoji picker after selection
            document.getElementById('emojiPicker').style.display = 'none';
            
            // Mark user as active when inserting emoji
            updateOnlineStatusOnActivity();
        }



        // Voice Input Functions
        let isRecording = false;
        let mediaRecorder = null;
        let audioChunks = [];

        function toggleVoiceInput() {
            const voiceBtn = document.querySelector('.action-btn[onclick="toggleVoiceInput()"]');
            
            if (!isRecording) {
                startVoiceRecording(voiceBtn);
            } else {
                stopVoiceRecording(voiceBtn);
            }
            
            // Mark user as active when using voice input
            updateOnlineStatusOnActivity();
        }

        function startVoiceRecording(button) {
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                navigator.mediaDevices.getUserMedia({ audio: true })
                    .then(stream => {
                        mediaRecorder = new MediaRecorder(stream);
                        audioChunks = [];
                        
                        mediaRecorder.ondataavailable = (event) => {
                            audioChunks.push(event.data);
                        };
                        
                        mediaRecorder.onstop = () => {
                            const audioBlob = new Blob(audioChunks, { type: 'audio/wav' });
                            // Here you would typically upload the audio file
                            Swal.fire('Voice Message', 'Voice message recorded! (Upload functionality to be implemented)', 'success');
                        };
                        
                        mediaRecorder.start();
                        isRecording = true;
                        button.classList.add('voice-recording');
                        button.innerHTML = '<i class="fas fa-stop"></i>';
                        
                        // Mark user as active when starting voice recording
                        updateOnlineStatusOnActivity();
                        
                        // Auto-stop after 30 seconds
                        setTimeout(() => {
                            if (isRecording) {
                                stopVoiceRecording(button);
                            }
                        }, 30000);
                    })
                    .catch(error => {
                        Swal.fire('Error', 'Microphone access denied or not available', 'error');
                    });
            } else {
                Swal.fire('Error', 'Voice recording not supported in this browser', 'error');
            }
        }

        function stopVoiceRecording(button) {
            if (mediaRecorder && isRecording) {
                mediaRecorder.stop();
                mediaRecorder.stream.getTracks().forEach(track => track.stop());
                isRecording = false;
                button.classList.remove('voice-recording');
                button.innerHTML = '<i class="fas fa-microphone"></i>';
                
                // Mark user as active when stopping voice recording
                updateOnlineStatusOnActivity();
            }
        }

        // Close emoji picker when clicking outside
        document.addEventListener('click', function(event) {
            const emojiPicker = document.getElementById('emojiPicker');
            const emojiBtn = document.querySelector('.action-btn[onclick="toggleEmojiPicker()"]');
            
            if (emojiPicker && emojiPicker.style.display !== 'none' && 
                !emojiPicker.contains(event.target) && 
                !emojiBtn.contains(event.target)) {
                emojiPicker.style.display = 'none';
            }
        });

        // Debug: Test emoji picker on page load
        document.addEventListener('DOMContentLoaded', function() {
            const emojiPicker = document.getElementById('emojiPicker');
            if (emojiPicker) {
                console.log('Emoji picker found on page load');
                console.log('Initial display style:', emojiPicker.style.display);
            } else {
                console.error('Emoji picker not found on page load!');
            }
        });

        // File input change event
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('fileInput');
            if (fileInput) {
                fileInput.addEventListener('change', handleFileUpload);
            }
        });

        // Handle window resize for mobile layout
        window.addEventListener('resize', function() {
            if (window.innerWidth <= 768) {
                // Mobile layout
                const sidebar = document.getElementById('chatSidebar');
                const main = document.getElementById('chatMain');
                const backBtn = document.querySelector('.mobile-back-btn');
                
                if (currentConversationId) {
                    sidebar.classList.add('hidden');
                    main.classList.remove('hidden');
                    if (backBtn) backBtn.classList.remove('d-none');
                } else {
                    sidebar.classList.remove('hidden');
                    main.classList.add('hidden');
                    if (backBtn) backBtn.classList.add('d-none');
                }
            } else {
                // Desktop layout - reset to normal
                const sidebar = document.getElementById('chatSidebar');
                const main = document.getElementById('chatMain');
                const backBtn = document.querySelector('.mobile-back-btn');
                
                sidebar.classList.remove('hidden');
                main.classList.remove('hidden');
                if (backBtn) backBtn.classList.add('d-none');
            }
        });

        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            if (messageInterval) {
                clearInterval(messageInterval);
            }
            
            // Mark as offline when leaving
            if (navigator.sendBeacon) {
                navigator.sendBeacon('private.php?action=mark_offline');
            } else {
                // Fallback for browsers that don't support sendBeacon
                fetch('private.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=mark_offline'
                });
            }
        });
        
        // Online Status Management
        let onlineStatusInterval;
        let lastActivityTime = Date.now();
        
        // Start online status updates
        function startOnlineStatusUpdates() {
            // Update online status every 30 seconds
            onlineStatusInterval = setInterval(updateOnlineStatus, 30000);
            
            // Check for inactivity every minute
            setInterval(checkInactivity, 60000);
            
            // Update on user activity
            document.addEventListener('click', updateOnlineStatusOnActivity);
            document.addEventListener('keypress', updateOnlineStatusOnActivity);
            document.addEventListener('scroll', updateOnlineStatusOnActivity);
            
            // Mark as back online when user becomes active
            document.addEventListener('click', markBackOnline);
            document.addEventListener('keypress', markBackOnline);
            document.addEventListener('scroll', markBackOnline);
            
            // Update on page visibility change
            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'visible') {
                    updateOnlineStatus();
                    // Refresh friends status when page becomes visible
                    refreshFriendsList();
                } else {
                    // Mark as away when page is hidden
                    fetch('private.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=mark_away'
                    });
                }
            });
        }
        
        // Update online status on user activity
        function updateOnlineStatusOnActivity() {
            lastActivityTime = Date.now();
            updateOnlineStatus();
        }
        
        // Check for inactivity and mark as away if needed
        function checkInactivity() {
            const inactiveThreshold = 5 * 60 * 1000; // 5 minutes in milliseconds
            const timeSinceLastActivity = Date.now() - lastActivityTime;
            
            if (timeSinceLastActivity > inactiveThreshold) {
                // Mark as away if inactive
                fetch('private.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=mark_away'
                });
            }
        }
        
        // Mark user as back online when they become active again
        function markBackOnline() {
            const currentTime = Date.now();
            const timeSinceLastActivity = currentTime - lastActivityTime;
            
            // If user was away and now active, mark as online
            if (timeSinceLastActivity < 60000) { // Less than 1 minute since last activity
                updateOnlineStatus();
            }
        }
        
        // Update online status
        function updateOnlineStatus() {
            fetch('private.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=update_online_status'
            });
        }
        
        // Update friend's online status in the UI
        function updateFriendOnlineStatus(friendId, onlineStatus, lastSeen) {
            const friendItem = document.querySelector(`[data-friend-id="${friendId}"]`);
            if (friendItem) {
                const indicator = friendItem.querySelector('.online-indicator, .offline-indicator, .away-indicator');
                
                if (indicator) {
                    if (onlineStatus === 'online') {
                        indicator.className = 'online-indicator';
                    } else if (onlineStatus === 'away') {
                        indicator.className = 'away-indicator';
                    } else {
                        indicator.className = 'offline-indicator';
                    }
                }
                
                // Note: We don't update the status text here since we want to keep showing the last message
                // The online status is only shown by the colored dot indicator
            }
        }
        
        // Update selected friend's status in chat header
        function updateSelectedFriendStatus() {
            if (currentConversationId) {
                const friendItem = document.querySelector(`[data-conversation-id="${currentConversationId}"]`);
                if (friendItem) {
                    const friendId = friendItem.getAttribute('data-friend-id');
                    const isOnline = friendItem.getAttribute('data-is-online');
                    const lastSeen = friendItem.getAttribute('data-last-seen');
                    
                    const onlineDot = document.getElementById('selectedFriendOnlineDot');
                    const statusText = document.getElementById('selectedFriendStatus');
                    
                    if (onlineDot) {
                        if (isOnline === 'online') {
                            onlineDot.style.display = 'block';
                            onlineDot.className = 'online-dot';
                        } else if (isOnline === 'away') {
                            onlineDot.style.display = 'block';
                            onlineDot.className = 'away-indicator';
                        } else {
                            onlineDot.style.display = 'none';
                        }
                    }
                    
                    if (statusText) {
                        if (isOnline === 'online') {
                            statusText.textContent = 'Online';
                        } else if (isOnline === 'away') {
                            statusText.textContent = 'Away';
                        } else if (lastSeen) {
                            statusText.textContent = `Last seen ${lastSeen}`;
                        } else {
                            statusText.textContent = 'Offline';
                        }
                    }
                }
            }
        }
        
        // Initialize online status system
        document.addEventListener('DOMContentLoaded', function() {
            // Start online status updates after a short delay
            setTimeout(startOnlineStatusUpdates, 1000);
            
            // Update selected friend status initially
            updateSelectedFriendStatus();
            
            // Refresh friends list every 2 minutes to get updated online statuses
            setInterval(refreshFriendsList, 120000);
            
            // Initialize mobile scroll behavior
            initializeMobileScroll();
        });
        
        // Mobile scroll behavior functions
        function initializeMobileScroll() {
            const chatMessages = document.getElementById('chatMessages');
            if (!chatMessages) return;
            
            // Add scroll event listener for mobile
            chatMessages.addEventListener('scroll', handleMobileScroll);
            
            // Add touch gesture support for mobile
            if ('ontouchstart' in window) {
                chatMessages.addEventListener('touchstart', handleTouchStart);
                chatMessages.addEventListener('touchend', handleTouchEnd);
            }
        }
        
        let touchStartY = 0;
        let touchEndY = 0;
        
        function handleTouchStart(e) {
            touchStartY = e.touches[0].clientY;
        }
        
        function handleTouchEnd(e) {
            touchEndY = e.changedTouches[0].clientY;
            const touchDiff = touchStartY - touchEndY;
            const chatMessages = document.getElementById('chatMessages');
            
            if (!chatMessages) return;
            
            // Swipe up (scroll to top)
            if (touchDiff > 50 && chatMessages.scrollTop < 100) {
                scrollToTop();
            }
            // Swipe down (scroll to bottom)
            else if (touchDiff < -50 && 
                     (chatMessages.scrollHeight - chatMessages.scrollTop - chatMessages.clientHeight) < 100) {
                scrollToBottom();
            }
        }
        
        function handleMobileScroll(e) {
            const chatMessages = e.target;
            const isAtTop = chatMessages.scrollTop < 50;
            const isAtBottom = (chatMessages.scrollHeight - chatMessages.scrollTop - chatMessages.clientHeight) < 50;
            
            // Add visual feedback for scroll position
            if (isAtTop) {
                chatMessages.classList.add('at-top');
            } else {
                chatMessages.classList.remove('at-top');
            }
            
            if (isAtBottom) {
                chatMessages.classList.add('at-bottom');
            } else {
                chatMessages.classList.remove('at-bottom');
            }
        }
        

        
        function scrollToTop() {
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            }
        }
        
        function scrollToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.scrollTo({
                    top: chatMessages.scrollHeight,
                    behavior: 'smooth'
                });
            }
        }
        
        // Auto-scroll to bottom when new messages arrive (mobile-friendly)
        function autoScrollToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages && window.innerWidth <= 768) {
                // On mobile, use smooth scroll for better UX
                scrollToBottom();
            } else if (chatMessages) {
                // On desktop, instant scroll
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }
        
        // Refresh friends list to get updated online statuses
        function refreshFriendsList() {
            // Fetch updated online statuses via AJAX
            fetch('private.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_friends_status'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.friends) {
                    data.friends.forEach(friend => {
                        updateFriendOnlineStatus(
                            friend.user_id, 
                            friend.online_status, 
                            friend.last_seen
                        );
                    });
                }
            })
            .catch(error => {
                console.log('Failed to refresh friends status:', error);
            });
        }
    </script>
</body>
</html> 