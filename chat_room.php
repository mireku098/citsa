<?php
// Prevent any output before JSON response
// error_reporting(0);
// ini_set('display_errors', 0);

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Include database connection and helpers
try {
    include 'app/db.conn.php';
include 'app/helpers/user.php';
include 'app/helpers/chat_rooms.php';
include 'app/helpers/platform_management.php';
include 'app/helpers/chat_room_online.php';
include 'app/helpers/chat_room_notifications.php';
include 'app/helpers/club_management.php';
} catch (Exception $e) {
    // If this is an AJAX request, return JSON error
    if (isset($_POST['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Database connection error']);
        exit();
    }
    // Otherwise, redirect to login
    header('Location: login.php');
    exit();
}

// Get current user data
$user = getUser($_SESSION['user_id'], $pdo);

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'send_message':
            $room_id = $_POST['room_id'];
            $message = trim($_POST['message']);
            
            // Check if user can access this room
            if (!canAccessRoom($user, $room_id, $pdo)) {
                echo json_encode(['success' => false, 'error' => 'Access denied']);
                exit();
            }
            
            // Additional check for club platforms - user must be approved member
            if (strpos($room_id, 'club_') === 0) {
                $club_id = str_replace('club_', '', $room_id);
                if (!canUserAccessClubChat($_SESSION['user_id'], $club_id, $pdo)) {
                    echo json_encode(['success' => false, 'error' => 'You must be an approved member to send messages in this club.']);
                    exit();
                }
            }
            
            if (!empty($message)) {
                // Send the message as plain text
                $message_id = sendRoomMessage($room_id, $_SESSION['user_id'], $message, $pdo);
                if ($message_id) {
                    echo json_encode(['success' => true, 'message_id' => $message_id]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to send message']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
            }
            exit();
            break;
            
        case 'get_messages':
            $room_id = $_POST['room_id'];
            $last_message_id = $_POST['last_message_id'] ?? 0;
            
            // Check if user can access this room
            if (!canAccessRoom($user, $room_id, $pdo)) {
                echo json_encode(['success' => false, 'error' => 'Access denied']);
                exit();
            }
            
            $messages = getRoomMessages($room_id, $last_message_id, $pdo);
            
            // Messages are already in plain text, no decryption needed
            echo json_encode(['success' => true, 'messages' => $messages]);
            exit();
            break;
            
        case 'join_club':
            $club_id = $_POST['club_id'];
            
            // Check if user can join clubs
            $can_join = canUserJoinClub($_SESSION['user_id'], $pdo);
            
            if (!$can_join['can_join']) {
                echo json_encode(['success' => false, 'error' => $can_join['reason']]);
                exit();
            }
            
            // Create join request instead of directly joining
            if (createClubJoinRequest($_SESSION['user_id'], $club_id, $pdo)) {
                echo json_encode(['success' => true, 'message' => 'Join request submitted successfully. Waiting for admin approval.']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to submit join request. You may already have a pending request or be a member.']);
            }
            exit();
            break;
            
        case 'leave_club':
            $club_id = $_POST['club_id'];
            
            if (leaveClub($_SESSION['user_id'], $club_id, $pdo)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to leave club']);
            }
            exit();
            break;

        case 'upload_file':
            // Check if required database columns exist
            try {
                $check_stmt = $pdo->prepare("SHOW COLUMNS FROM chat_room_messages LIKE 'message_type'");
                $check_stmt->execute();
                $column_exists = $check_stmt->fetch();
                
                if (!$column_exists) {
                    echo json_encode(['success' => false, 'error' => 'File attachment support not set up. Please run setup_chat_room_files.php first.']);
                    exit();
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
                exit();
            }
            
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'error' => 'File upload failed']);
                exit();
            }
            
            $file = $_FILES['file'];
            $room_id = $_POST['room_id'];
            $message = trim($_POST['message'] ?? '');
            
            // Check if user can access this room
            if (!canAccessRoom($user, $room_id, $pdo)) {
                echo json_encode(['success' => false, 'error' => 'Access denied']);
                exit();
            }
            
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
            $upload_dir = 'uploads/chat_rooms/';
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
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO chat_room_messages (room_id, sender_id, message, message_type, file_url, file_name, file_size, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$room_id, $_SESSION['user_id'], $message, $message_type, $file_path, $file['name'], $file['size']]);
                    
                    echo json_encode([
                        'success' => true, 
                        'message_id' => $pdo->lastInsertId(),
                        'file_url' => $file_path,
                        'file_name' => $file['name'],
                        'file_size' => $file['size'],
                        'message_type' => $message_type
                    ]);
                } catch (PDOException $e) {
                    // Log the error for debugging
                    error_log("Chat room file upload database error: " . $e->getMessage());
                    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
                }
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
            $uploads_dir = realpath('uploads/chat_rooms/');
            
            if (!$real_path || !$uploads_dir || strpos($real_path, $uploads_dir) !== 0) {
                echo json_encode(['success' => false, 'error' => 'Access denied']);
                exit();
            }
            
            // Check if file exists
            if (!file_exists($real_path)) {
                echo json_encode(['success' => false, 'error' => 'File not found']);
                exit();
            }
            
            // Check if user can access this file (they must be able to access the room)
            $stmt = $pdo->prepare("
                SELECT m.room_id 
                FROM chat_room_messages m 
                WHERE m.file_url = ? AND m.id = (SELECT MAX(id) FROM chat_room_messages WHERE file_url = ?)
            ");
            $stmt->execute([$file_path, $file_path]);
            $file_access = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$file_access || !canAccessRoom($user, $file_access['room_id'], $pdo)) {
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

        case 'check_club_request_status':
            $club_id = $_POST['club_id'];
            
            // Get user's request status for this club
            $requests = getUserClubRequests($_SESSION['user_id'], $pdo);
            $club_request = null;
            
            foreach ($requests as $request) {
                if ($request['club_id'] == $club_id) {
                    $club_request = $request;
                    break;
                }
            }
            
            if ($club_request) {
                echo json_encode([
                    'success' => true, 
                    'status' => $club_request['status'],
                    'message' => $club_request['status'] === 'pending' ? 'Request pending approval' : 
                                ($club_request['status'] === 'approved' ? 'Request approved' : 'Request rejected'),
                    'rejection_reason' => $club_request['rejection_reason'] ?? null
                ]);
            } else {
                echo json_encode(['success' => true, 'status' => 'none', 'message' => 'No request found']);
            }
            exit();
            break;
    }
}

// Get available rooms for the user
$available_rooms = getAvailableRooms($user, $pdo);

// Get all clubs with user's status for display
$all_clubs = getAllClubsWithStatus($_SESSION['user_id'], $pdo);

// Get selected room - only if explicitly provided
$selected_room = $_GET['room'] ?? null;

// Verify user can access the selected room if one is specified
if ($selected_room && !canAccessRoom($user, $selected_room, $pdo)) {
    $selected_room = null; // Reset to null if access denied
}

// If a room is explicitly opened, mark its messages as read before rendering
if ($selected_room) {
    try {
        markChatRoomMessagesAsRead($_SESSION['user_id'], $selected_room, $pdo);
    } catch (Exception $e) {
        // Fail silently; notifications will reconcile on next refresh
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>CITSA Connect - Chat Rooms</title>
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
            overflow-x: hidden;
            margin: 0;
            padding: 0;
        }

        .chat-container {
            height: calc(100vh - 120px);
            display: flex;
            background: var(--white);
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(26, 60, 109, 0.1);
            overflow: hidden;
            width: 100%;
        }
       

        /* For screens 769px and larger */
@media (min-width: 769px) {
    .chat-container {
        height: calc(100vh - 150px);
        margin-top: 15px;
        margin-bottom: 10px;
        margin-left: 0;
        margin-right: 0;
        flex-direction: row; /* ensure horizontal layout */
        width: 100%;
    }

    .chat-sidebar {
        width: 300px;
        height: auto;
        border-right: 1px solid #e9ecef;
        position: relative;
        margin-top: 10px;
    }

    .chat-main {
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .chat-interface {
        margin-top: 0;
        height: 70vh;
        display: flex;
        flex-direction: column;
    }

    .mobile-back-btn {
        display: none !important;
    }
}



        @media (max-width: 768px) {
            body {
                height: 100dvh;
                height: 100vh; /* Fallback */
            }
            
            .chat-container {
                height: 100dvh;
                height: 100vh; /* Fallback */
                margin-top: 0;
            }
            
            .chat-sidebar {
                width: 100%;
                height: calc(100dvh - 60px); /* Account for navigation height */
                height: calc(100vh - 60px); /* Fallback */
                position: fixed;
                top: 0;
                left: 0;
                z-index: 1000;
                border-radius: 0;
                border-right: none;
                margin-top: 60px; /* Account for navigation height */
            }
            
            .chat-main {
                width: 100%;
                height: calc(100dvh - 60px); /* Account for navigation height */
                height: calc(100vh - 60px); /* Fallback */
                position: fixed;
                top: 0;
                left: 0;
                z-index: 1001;
                border-radius: 0;
                min-height: 0;
                overflow: hidden;
            }
            
            #main.main {
                margin-top: 5px !important;
                padding-top: 10px !important;
            }
            
            .chat-interface {
                height: 100dvh;
                height: 100vh; /* Fallback */
                padding-top: 130px; /* 70px header + 60px navigation */
                padding-bottom: 0;
            }
            
            .chat-messages {
                padding: 15px;
                gap: 12px;
                height: calc(100dvh - 250px); /* Adjusted for 130px padding-top + 120px input */
                height: calc(100vh - 250px); /* Fallback */
                overflow-x: hidden;
                word-wrap: break-word;
                padding-bottom: 120px; /* Account for fixed input height */
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
                margin-top: 60px; /* Account for navigation height */
            }
            
            .chat-input {
                padding: 15px 20px;
                height: 120px;
                min-height: 120px;
                margin: 0;
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: var(--white);
                border-top: 1px solid #e9ecef;
                z-index: 1000;
            }
            
            .message-content {
                max-width: 85%;
                word-wrap: break-word;
                overflow-wrap: break-word;
            }
        }

        .chat-sidebar {
            width: 300px;
            background: var(--white);
            border-right: 1px solid #e9ecef;
            display: flex;
            flex-direction: column;
            margin-top: 0;
            height: auto;
        }

        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .room-item {
            padding: 15px;
            border-bottom: 1px solid #f1f3f4;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            background: white;
        }

        .room-item:first-child {
            border-top: none;
        }

        .room-item:last-child {
            border-bottom: none;
        }

        .room-item:hover {
            background: #f8f9fa;
        }

        .room-item.active {
            background: var(--primary-color);
            color: white !important;
        }

        .room-item.active .room-name,
        .room-item.active .room-description {
            color: white !important;
        }

        .room-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
        }

        .room-icon.general { background: #28a745; }
        .room-icon.students { background: #007bff; }
        .room-icon.alumni { background: #6f42c1; }
        .room-icon.year_based { background: #fd7e14; }
        .room-icon.program { background: #20c997; }
        .room-icon.club { background: #dc3545; }

        .room-last-message {
            font-size: 0.75rem;
            color: var(--text-light);
            margin-top: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 200px;
        }

        .room-item.active .room-last-message {
            color: rgba(255, 255, 255, 0.8);
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
            padding: 20px;
        }

        .chat-input {
            background: var(--white);
            border-top: 1px solid #e9ecef;
            padding: 15px 20px;
            position: relative; /* Default positioning for desktop */
        }

        .chat-input-container {
            display: flex;
            flex-direction: column;
            gap: 10px;
            position: relative;
            width: 100%;
            box-sizing: border-box;
        }

        .action-buttons-row {
            display: flex;
            gap: 8px;
            align-items: center;
            width: 100%;
            box-sizing: border-box;
            flex-wrap: wrap;
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
            width: 100%;
            box-sizing: border-box;
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
            margin-left: 20px;
        }

        .message-content {
            max-width: 70%;
            padding: 8px 12px;
            border-radius: 18px;
            position: relative;
            word-wrap: break-word;
            display: inline-block;
            margin: 4px 0;
        }

        .message.sent .message-content {
            background: var(--message-sent);
            color: white;
            border-bottom-right-radius: 4px;
            margin-left: auto;
            text-align: left;
        }

        .message.received .message-content {
            background: var(--message-received);
            color: var(--text-dark);
            border-bottom-left-radius: 4px;
            position: relative;
        }

        .message.received .message-content::before {
            content: '';
            position: absolute;
            bottom: -8px;
            left: -8px;
            width: 0;
            height: 0;
            border-left: 8px solid transparent;
            border-right: 8px solid var(--message-received);
            border-bottom: 8px solid transparent;
        }

        .message.received {
            position: relative;
        }

        .message.received .message-avatar {
            position: absolute;
            bottom: -15px;
            left: -15px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            object-fit: cover;
            z-index: 1;
            display: block;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .message.received .message-avatar:hover {
            transform: scale(1.1);
        }

        .message-time {
            font-size: 0.75rem;
            opacity: 0.7;
            margin-top: 2px;
        }

        .message-sender {
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 2px;
            color: var(--text-dark);
        }

        .message.received .message-sender {
            color: var(--primary-color);
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
            max-width: 100%;
            box-sizing: border-box;
            word-wrap: break-word;
            overflow-wrap: break-word;
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
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: absolute;
            bottom: 100%;
            left: 0;
            margin-bottom: 10px;
            z-index: 1000;
            width: auto;
            /* Temporary: Make it more visible for debugging */
            border: 2px solid #ff0000;
        }

        .emoji-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 8px;
            width: 100%;
        }

        .emoji {
            font-size: 1.2rem;
            cursor: pointer;
            padding: 8px;
            border-radius: 5px;
            transition: all 0.2s ease;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 32px;
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

        .club-join-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .club-join-btn:hover {
            background: var(--accent-color);
            transform: translateY(-1px);
        }

        .club-leave-btn {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 20px;
            padding: 5px 15px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .club-leave-btn:hover {
            background: #c82333;
        }



        .welcome-screen {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        @media (max-width: 768px) {
            .welcome-screen {
                display: none !important;
            }
        }

                @media (max-width: 768px) {
            .chat-container {
                flex-direction: column;
                height: calc(100vh - 140px);
            }
            
            .chat-sidebar {
                width: 100%;
                height: 100%;
                border-right: none;
                border-bottom: none;
                position: absolute;
                top: 0;
                left: 0;
                z-index: 1000;
                background: var(--white);
            }
            
            .chat-sidebar.hidden {
                display: none;
            }
            
            .chat-main {
                flex: 1;
                width: 100%;
                display: flex;
                flex-direction: column;
            }
            
            .chat-main.hidden {
                display: none;
            }
            
            .chat-interface {
                display: flex !important;
                flex-direction: column;
                height: 100%;
            }
            
                         .chat-messages {
                 padding: 10px;
                 padding-bottom: 140px; /* Ensure enough space above the fixed input */
                 overflow-x: hidden;
                 word-wrap: break-word;
                 flex: 1;
                 min-height: 0;
             }
            
                         .chat-input {
                 padding: 10px 15px;
                 margin: 0;
                 flex-shrink: 0;
                 position: fixed;
                 bottom: 0;
                 left: 0;
                 right: 0;
                 background: var(--white);
                 border-top: 1px solid #e9ecef;
                 z-index: 1000;
             }
            
            .message-content {
                max-width: 85%;
                word-wrap: break-word;
                overflow-wrap: break-word;
            }
            
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
            
            .chat-header {
                position: relative;
                flex-shrink: 0;
            }
            
                         .online-count-text {
                 font-size: 0.8rem;
                 opacity: 0.8;
             }
             
             .room-last-message {
                 font-size: 0.7rem;
                 line-height: 1.3;
                 max-width: 100%;
                 margin-bottom: 8px;
             }
             
             .room-item {
                 padding: 12px 15px;
                 margin-bottom: 8px;
             }
        }
        
        .mobile-back-btn {
            display: none;
        }
        
                    /* Additional fixes for screens below 769px */
            @media (max-width: 768.9px) {
                #main.main {
                    height: 100dvh;
                    height: 100vh; /* Fallback */
                    padding: 0;
                    margin: 0;
                }
                
                .chat-container {
                    height: 100dvh;
                    height: 100vh; /* Fallback */
                    margin-top: 0; /* Remove margin since we're using padding-top on interface */
                }
                
                .chat-sidebar {
                    width: 100%;
                    height: calc(100dvh - 60px); /* Account for navigation height */
                    height: calc(100vh - 60px); /* Fallback */
                    position: fixed;
                    top: 0;
                    left: 0;
                    z-index: 1000;
                    border-radius: 0;
                    border-right: none;
                    margin-top: 60px; /* Account for navigation height */
                    padding-bottom: 20px; /* Ensure bottom spacing */
                }
            
            .chat-main {
                width: 100%;
                height: calc(100dvh - 60px); /* Account for navigation height */
                height: calc(100vh - 60px); /* Fallback */
                position: fixed;
                top: 0;
                left: 0;
                z-index: 1001;
                border-radius: 0;
                min-height: 0;
                overflow: hidden;
            }
            
            .chat-interface {
                height: 100dvh;
                height: 100vh; /* Fallback */
                border-radius: 0;
                position: relative;
                padding-top: 130px; /* 70px header + 60px navigation */
                padding-bottom: 0;
                display: flex !important;
                flex-direction: column;
            }
            
            .chat-messages {
                padding: 15px;
                gap: 12px;
                height: calc(100dvh - 250px); /* Adjusted for 130px padding-top + 120px input */
                height: calc(100vh - 250px); /* Fallback */
                position: relative;
                overscroll-behavior: contain;
                overflow-y: auto;
                overflow-x: hidden;
                margin-top: 0;
                margin-bottom: 0;
                padding-bottom: 150px; /* Increased padding to ensure no overlap with input */
                scroll-behavior: smooth;
                -webkit-overflow-scrolling: touch;
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
                margin-top: 60px; /* Account for navigation height */
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
                box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
            }
            
            .chat-input-container {
                height: 100%;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
            }
            
            .message-input {
                max-width: 100%;
                box-sizing: border-box;
            }
            
            .message-input-row {
                width: 100%;
                box-sizing: border-box;
                flex: 1;
                display: flex;
                align-items: end;
                gap: 10px;
            }
            
            /* Mobile input styling */
            .action-buttons-row {
                flex-shrink: 0;
                margin-bottom: 10px;
            }
            
            /* Ensure no horizontal scroll on mobile */
            .chat-container,
            .chat-main,
            .chat-interface,
            .chat-messages,
            .chat-input,
            .chat-input-container {
                max-width: 100vw;
                overflow-x: hidden;
            }
            
            /* Ensure room items have proper spacing on mobile */
            .room-item {
                padding: 12px 15px;
                margin-bottom: 10px;
            }
            
            .room-last-message {
                font-size: 0.7rem;
                line-height: 1.3;
                margin-bottom: 8px;
                max-width: 100%;
            }
            
            /* Fix for very small screens */
            @media (max-width: 480px) {
                .chat-container {
                    margin-top: 0;
                    height: 100dvh;
                    height: 100vh; /* Fallback */
                }

                .chat-sidebar {
                    margin-top: 60px; /* Account for navigation height */
                    height: calc(100dvh - 60px); /* Account for navigation height */
                    height: calc(100vh - 60px); /* Fallback */
                }
                
                .chat-input {
                    padding: 8px 12px;
                    height: 120px;
                    min-height: 120px;
                }
                
                .action-buttons-row {
                    gap: 5px;
                }
                
                .action-btn {
                    width: 32px;
                    height: 32px;
                }
                
                .room-item {
                    padding: 10px 12px;
                    margin-bottom: 6px;
                }
                
                .room-last-message {
                    font-size: 0.65rem;
                    line-height: 1.2;
                    margin-bottom: 6px;
                }
            }
        }

        /* Ensure navigation is visible and properly positioned */
        .header {
            z-index: 1030 !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
        }

        /* Ensure main content doesn't overlap navigation */
        #main.main {
            position: relative;
            z-index: 1;
        }

        /* Larger screen adjustments - ensure content is below navigation */
        @media (min-width: 769px) {
            #main.main {
                margin-top: 40px !important;
            }
        }

        /* File Attachment Styles */
        .message-text {
            margin-top: 8px;
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
            margin-bottom: 4px;
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

        /* Username Styling */
        .message-sender span {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .own-username {
            color: var(--primary-color);
            font-weight: 600;
        }

        /* User Profile Modal Styles */
        .user-profile-modal {
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .user-profile-content {
            position: relative;
            background: white;
            border-radius: 16px;
            padding: 0;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            cursor: default;
            overflow: hidden;
        }

        .user-profile-header {
            background: var(--primary-color);
            color: white;
            padding: 20px;
            text-align: center;
            position: relative;
        }

        .user-profile-header h5 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .user-profile-close {
            position: absolute;
            top: 15px;
            right: 20px;
            color: white;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.2s ease;
        }

        .user-profile-close:hover {
            opacity: 1;
        }

        .user-profile-body {
            padding: 30px 20px;
            text-align: center;
        }

        .user-profile-avatar {
            margin-bottom: 20px;
        }

        .user-profile-avatar img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary-color);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .user-profile-info {
            text-align: center;
        }

        .user-profile-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
        }

        .user-profile-username {
            font-size: 1rem;
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 12px;
        }

        .user-profile-type {
            display: inline-block;
            background: var(--primary-color);
            color: white;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 12px;
        }

        .user-profile-programme {
            color: #666;
            font-size: 0.9rem;
            font-weight: 500;
            padding: 8px 16px;
            background: #f8f9fa;
            border-radius: 12px;
            display: inline-block;
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

            .message-sender span {
                font-size: 0.85rem;
            }

            /* Mobile User Profile Modal */
            .user-profile-content {
                width: 95%;
                max-width: 350px;
            }

            .user-profile-header {
                padding: 15px;
            }

            .user-profile-header h5 {
                font-size: 1rem;
            }

            .user-profile-close {
                top: 12px;
                right: 15px;
                font-size: 20px;
            }

            .user-profile-body {
                padding: 20px 15px;
            }

            .user-profile-avatar img {
                width: 80px;
                height: 80px;
            }

            .user-profile-name {
                font-size: 1.1rem;
            }

            .user-profile-username {
                font-size: 0.9rem;
            }
        }

        .clubs-section {
            margin-top: 20px;
            padding: 15px;
            border-top: 1px solid #e9ecef;
        }

        .clubs-section-title {
            color: var(--text-dark);
            font-weight: 600;
            margin-bottom: 15px;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .club-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            margin-bottom: 10px;
            background: var(--bg-light);
            border-radius: 10px;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .club-item:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transform: translateY(-1px);
        }

        .club-info {
            flex: 1;
            margin-right: 15px;
        }

        .club-info h6 {
            color: var(--text-dark);
            font-size: 0.9rem;
            margin-bottom: 4px;
        }

        .club-info p {
            color: var(--text-light);
            font-size: 0.8rem;
            margin: 0;
        }

        .club-actions {
            flex-shrink: 0;
        }

        .club-access-btn, .club-status-btn, .club-join-btn {
            font-size: 0.75rem;
            padding: 6px 12px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .club-access-btn {
            background: #28a745;
            color: white;
        }

        .club-access-btn:hover {
            background: #218838;
            transform: translateY(-1px);
        }

        .club-status-btn {
            background: #ffc107;
            color: #212529;
        }

        .club-status-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .club-join-btn {
            background: var(--primary-color);
            color: white;
        }

        .club-join-btn:hover {
            background: var(--accent-color);
            transform: translateY(-1px);
        }
    </style>
</head>

<body>
    <!-- Include Navigation -->
    <?php include 'nav.php'; ?>

    <!-- ======= Main Content ======= -->
    <main id="main" class="main" style="margin-top: 5px; padding: 20px; height: 100%; width: 100%; overflow-x: hidden;">
                <div class="chat-container">
            <!-- Chat Sidebar -->
            <div class="chat-sidebar" id="chatSidebar">
                <div class="flex-grow-1 overflow-auto" style="padding-top: 10px;">
                    <!-- Available Chat Rooms -->
                    <?php foreach ($available_rooms as $room): ?>
                        <div class="room-item <?php echo ($selected_room && $selected_room === $room['id']) ? 'active' : ''; ?>"
                             onclick="selectRoom('<?php echo $room['id']; ?>', '<?php echo $room['type']; ?>', <?php echo isset($room['club_id']) ? $room['club_id'] : 'null'; ?>, event)"
                             onmouseenter="refreshRoomLastMessage('<?php echo $room['id']; ?>')"
                             title="Click to join this chat room">
                            <div class="room-icon <?php echo $room['type']; ?>">
                                <?php
                                // Get platform icon from database or use default
                                $icon_info = getPlatformIcon($room['type'], $pdo);
                                $icon_class = $icon_info['icon_class'];
                                $icon_color = $icon_info['icon_color'];
                                ?>
                                <i class="<?php echo $icon_class; ?>" style="color: <?php echo $icon_color; ?>;"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1 room-name"><?php echo htmlspecialchars($room['name']); ?></h6>
                                <p class="mb-0 small text-muted room-last-message" data-room-id="<?php echo $room['id']; ?>">No messages yet</p>
                            </div>
                            <?php if ($room['type'] === 'club'): ?>
                                <?php if ($room['is_member']): ?>
                                    <button class="club-leave-btn" onclick="event.stopPropagation(); leaveClub(<?php echo $room['club_id']; ?>)">
                                        Leave
                                    </button>
                                <?php else: ?>
                                    <button class="club-join-btn" onclick="event.stopPropagation(); joinClub(<?php echo $room['club_id']; ?>)">
                                        Join
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- All Clubs Section -->
                    <div class="clubs-section">
                        <h6 class="clubs-section-title">Available Clubs</h6>
                        <?php foreach ($all_clubs as $club): ?>
                            <div class="club-item">
                                <div class="club-info">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($club['name']); ?></h6>
                                    <p class="mb-0 small text-muted"><?php echo htmlspecialchars($club['description']); ?></p>
                                </div>
                                <div class="club-actions">
                                    <?php if ($club['is_approved']): ?>
                                        <button class="club-access-btn btn btn-success btn-sm" onclick="selectRoom('club_<?php echo $club['id']; ?>', 'club', <?php echo $club['id']; ?>, event)">
                                            <i class="fas fa-comments"></i> Chat
                                        </button>
                                    <?php elseif ($club['is_pending']): ?>
                                        <button class="club-status-btn btn btn-warning btn-sm" disabled>
                                            <i class="fas fa-clock"></i> Pending
                                        </button>
                                    <?php elseif ($club['is_rejected']): ?>
                                        <button class="club-status-btn btn btn-danger btn-sm" disabled>
                                            <i class="fas fa-times"></i> Rejected
                                        </button>
                                    <?php else: ?>
                                        <button class="club-join-btn btn btn-primary btn-sm" onclick="joinClub(<?php echo $club['id']; ?>)">
                                            <i class="fas fa-plus"></i> Join
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Chat Main Area -->
            <div class="chat-main" id="chatMain">
                <!-- Welcome Screen -->
                <div class="welcome-screen" id="welcomeScreen">
                    <div class="d-flex align-items-center justify-content-center h-100">
                        <div class="text-center">
                            <i class="fas fa-comments fa-4x text-muted mb-4"></i>
                            <h4 class="text-muted">Welcome to Chat Rooms</h4>
                            <p class="text-muted">Select a room from the sidebar to start chatting</p>
                        </div>
                    </div>
                </div>

                <!-- Chat Interface -->
                <div class="chat-interface" id="chatInterface" style="display: none;">
                    <!-- Chat Header -->
                    <div class="chat-header">
                        <div class="d-flex align-items-center">
                            <button class="mobile-back-btn d-none" onclick="showRoomsList()" title="Back to rooms">
                                <i class="fas fa-arrow-left"></i>
                            </button>
                            <div class="me-3">
                                <i class="fas fa-comments fa-lg"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-0" id="selectedRoomName"></h6>
                                <span class="online-count-text" id="onlineCount">0 online</span>
                            </div>
                        </div>
                    </div>
                                
                    <!-- Chat Messages -->
                    <div class="chat-messages" id="chatMessages" onscroll="handleChatScroll()">
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


    <!-- Vendor JS Files -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- AOS Animation -->
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>

    <script>
        // Handle mobile viewport height changes for keyboard
        function handleViewportHeight() {
            const vh = window.innerHeight * 0.01;
            document.documentElement.style.setProperty('--vh', `${vh}px`);
        }

        let currentRoomId = '<?php echo $selected_room ?: ''; ?>';
        let lastMessageId = 0;
        let messageInterval;

        // Initialize chat
        document.addEventListener('DOMContentLoaded', function() {
            // On mobile, show rooms list by default if no room is selected
            if (window.innerWidth <= 768 && !currentRoomId) {
                showRoomsList();
            } else if (currentRoomId && currentRoomId.trim() !== '') {
                selectRoom(currentRoomId);
                // Also refresh the last message for the initial room
                setTimeout(() => refreshRoomLastMessage(currentRoomId), 500);
            } else if (window.innerWidth > 768) {
                // On desktop, ensure proper layout even without room selected
                const sidebar = document.getElementById('chatSidebar');
                const main = document.getElementById('chatMain');
                const welcomeScreen = document.getElementById('welcomeScreen');
                sidebar.classList.remove('hidden');
                main.classList.remove('hidden');
                if (welcomeScreen) welcomeScreen.style.display = 'flex';
            }
            
            // Initialize message notification count (commented out due to errors)
            // updateMessageNotification();
            
            // Auto-resize textarea
            const messageInput = document.getElementById('messageInput');
            if (messageInput) {
                messageInput.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = Math.min(this.scrollHeight, 100) + 'px';
                });
                
                // Send message on Enter
                messageInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        sendMessage();
                    }
                });
            }
            
            // Handle viewport height changes for mobile keyboard
            handleViewportHeight();
            window.addEventListener('resize', handleViewportHeight);
            window.addEventListener('orientationchange', handleViewportHeight);
            
            // Load last messages for all rooms
            loadAllRoomLastMessages();
            
            // Refresh last messages when page becomes visible
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden && currentRoomId) {
                    refreshRoomLastMessage(currentRoomId);
                }
            });
            
            // Refresh last messages when window gains focus
            window.addEventListener('focus', function() {
                if (currentRoomId) {
                    refreshRoomLastMessage(currentRoomId);
                }
            });
            
            // Refresh last messages when user returns to the page
            window.addEventListener('pageshow', function(event) {
                if (event.persisted && currentRoomId) {
                    refreshRoomLastMessage(currentRoomId);
                }
            });
            
            // Refresh last messages when page is refreshed
            window.addEventListener('load', function() {
                if (currentRoomId) {
                    setTimeout(() => refreshRoomLastMessage(currentRoomId), 1000);
                }
            });
            
            // Refresh last messages when user navigates back to the page
            window.addEventListener('popstate', function() {
                if (currentRoomId) {
                    setTimeout(() => refreshRoomLastMessage(currentRoomId), 500);
                }
            });
            
            // Refresh last messages when user switches between tabs
            window.addEventListener('storage', function(event) {
                if (event.key === 'lastMessageUpdate' && currentRoomId) {
                    refreshRoomLastMessage(currentRoomId);
                }
            });
        });

        function selectRoom(roomId, roomType = null, clubId = null, event = null) {
            currentRoomId = roomId;
            
            // Reset lastMessageId when switching rooms
            lastMessageId = 0;
            
            // Update URL without page reload
            const url = new URL(window.location);
            url.searchParams.set('room', roomId);
            window.history.pushState({}, '', url);
            
            // Update active state in sidebar
            document.querySelectorAll('.room-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Find the room item and activate it
            let roomItem = null;
            if (event && event.target) {
                roomItem = event.target.closest('.room-item');
            }
            
            // If no event or room item found, find by room ID
            if (!roomItem) {
                roomItem = document.querySelector(`[onclick*="${roomId}"]`);
            }
            
            if (roomItem) {
                roomItem.classList.add('active');
                
                // Get room information from the room item
                const roomName = roomItem.querySelector('.room-name').textContent;
                
                // Update chat interface
                document.getElementById('selectedRoomName').textContent = roomName;
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
            
            // Clear existing messages
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.innerHTML = '';
            }
            
            // Load messages
            setTimeout(() => {
                loadMessages(true);
                
                // Start polling for new messages
                if (messageInterval) {
                    clearInterval(messageInterval);
                }
                startMessagePolling();
                
                // Update online status and start online status updates
                updateOnlineStatus();
                startOnlineStatusUpdates();
                
                // Refresh the last message for this room
                refreshRoomLastMessage(roomId);

                // Mark room messages as read for notifications
                markRoomAsRead(roomId);
                
                // Also refresh the last message for all rooms to keep them updated
                setTimeout(() => loadAllRoomLastMessages(), 500);
            }, 100);
        }

        // Mark current room messages as read (for notification badge)
        function markRoomAsRead(roomId) {
            if (!roomId) return;
            fetch('get_chat_room_notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=mark_as_read&room_id=${encodeURIComponent(roomId)}`
            })
            .then(r => r.json())
            .then(() => {})
            .catch(() => {});
        }

        // Handle chat scroll to refresh last message when user scrolls to top
        function handleChatScroll() {
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages && currentRoomId) {
                // If user scrolls to top (within 100px), refresh the last message
                if (chatMessages.scrollTop < 100) {
                    refreshRoomLastMessage(currentRoomId);
                }
            }
        }

        function loadMessages(forceScrollToBottom = false) {
            if (!currentRoomId) {
                console.log('No room ID set');
                return;
            }
            
            console.log('Loading messages for room:', currentRoomId);
            
            // Store current scroll position before loading messages
            const chatMessages = document.getElementById('chatMessages');
            const wasAtBottom = chatMessages ? (chatMessages.scrollHeight - chatMessages.scrollTop - chatMessages.clientHeight < 50) : true;
            
            fetch('chat_room.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_messages&room_id=${currentRoomId}&last_message_id=${lastMessageId}`
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
                    }
                    
                    // Refresh the last message for this room after loading messages
                    if (data.messages.length > 0) {
                        refreshRoomLastMessage(currentRoomId);
                        
                        // Also refresh the last message for all rooms to keep them updated
                        setTimeout(() => loadAllRoomLastMessages(), 1000);
                    }
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
            
            const isOwnMessage = message.sender_id == <?php echo $_SESSION['user_id']; ?>;
            
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${isOwnMessage ? 'sent' : 'received'}`;
            messageDiv.setAttribute('data-message-id', message.id);
            
            const messageTime = formatTime(message.created_at);
            
            if (isOwnMessage) {
                // Sent message - show "You" with special styling
                messageDiv.innerHTML = `
                    <div class="message-content">
                        <div class="message-sender">
                            <span class="own-username">You</span>
                        </div>
                        ${formatMessageContent(message)}
                        <div class="message-time">${messageTime}</div>
                    </div>
                `;
            } else {
                // Received message - show profile image and username with random color
                const username = message.username;
                const profileImage = message.profile_image || 'default-avatar.png';
                const profileSrc = `profile/${profileImage}`;
                
                // Generate consistent random color for username
                const usernameColor = getUsernameColor(username);
                
                // Add error handling for profile image
                const img = new Image();
                img.onload = function() {
                    // Profile image loaded successfully
                };
                img.onerror = function() {
                    // Use default avatar if image fails to load
                    const avatarElement = messageDiv.querySelector('.message-avatar');
                    if (avatarElement) {
                        avatarElement.src = 'profile/default-avatar.png';
                    }
                };
                img.src = profileSrc;
                
                messageDiv.innerHTML = `
                    <div class="message-content">
                        <div class="message-sender">
                            <span style="color: ${usernameColor};">@${username}</span>
                        </div>
                        ${formatMessageContent(message)}
                        <div class="message-time">${messageTime}</div>
                    </div>
                    <img src="${profileSrc}" alt="@${username}" class="message-avatar" title="@${username}" onclick="showUserProfile('${username}', '${message.first_name}', '${message.last_name}', '${message.profile_image}', '${message.programme}', '${message.user_type}')">
                `;
            }
            
            chatMessages.appendChild(messageDiv);
            
            // Refresh the last message for this room when a new message is added
            if (currentRoomId) {
                refreshRoomLastMessage(currentRoomId);
                
                // Also refresh the last message for all rooms to keep them updated
                setTimeout(() => loadAllRoomLastMessages(), 1000);
            }
        }

        function getUsernameColor(username) {
            // Generate a consistent color based on username hash
            let hash = 0;
            for (let i = 0; i < username.length; i++) {
                hash = username.charCodeAt(i) + ((hash << 5) - hash);
            }
            
            // Generate HSL color with good saturation and lightness
            const hue = Math.abs(hash) % 360;
            const saturation = 70 + (Math.abs(hash) % 20); // 70-90% saturation
            const lightness = 45 + (Math.abs(hash) % 15);  // 45-60% lightness
            
            return `hsl(${hue}, ${saturation}%, ${lightness}%)`;
        }

        function formatMessageContent(message) {
            let content = '';
            
            // Add file attachment first if exists
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
            
            // Add text message below file attachment if exists
            if (message.message && message.message.trim()) {
                content += `<div class="message-text">${message.message}</div>`;
            }
            
            return content || '<div class="message-text">No content</div>';
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
            form.action = 'chat_room.php';
            
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

        function showUserProfile(username, firstName, lastName, profileImage, programme, userType) {
            const fullName = `${firstName} ${lastName}`;
            const profileSrc = profileImage ? `profile/${profileImage}` : 'profile/default-avatar.png';
            
            // Create profile modal HTML
            const modalHTML = `
                <div id="userProfileModal" class="user-profile-modal" onclick="closeUserProfile()">
                    <div class="user-profile-content" onclick="event.stopPropagation()">
                        <div class="user-profile-header">
                            <span class="user-profile-close" onclick="closeUserProfile()">&times;</span>
                            <h5>User Profile</h5>
                        </div>
                        <div class="user-profile-body">
                            <div class="user-profile-avatar">
                                <img src="${profileSrc}" alt="${fullName}" onerror="this.src='profile/default-avatar.png'">
                            </div>
                            <div class="user-profile-info">
                                <h6 class="user-profile-name">${fullName}</h6>
                                <div class="user-profile-username">@${username}</div>
                                <div class="user-profile-type">${userType === 'student' ? 'Student' : 'Alumni'}</div>
                                ${programme ? `<div class="user-profile-programme">${programme}</div>` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            
            // Prevent body scroll
            document.body.style.overflow = 'hidden';
        }

        function closeUserProfile() {
            const modal = document.getElementById('userProfileModal');
            if (modal) {
                modal.remove();
                document.body.style.overflow = 'auto';
            }
        }

        function sendMessage() {
            const messageInput = document.getElementById('messageInput');
            const message = messageInput.value.trim();
            
            if (!message || !currentRoomId) return;
            
            fetch('chat_room.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=send_message&room_id=${currentRoomId}&message=${encodeURIComponent(message)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    messageInput.value = '';
                    messageInput.style.height = 'auto';
                    handleMessageSent();
                    
                    // Refresh the last message for this room
                    refreshRoomLastMessage(currentRoomId);
                    
                    // Also refresh the last message for all rooms to keep them updated
                    setTimeout(() => loadAllRoomLastMessages(), 500);
                } else {
                    Swal.fire('Error', data.error, 'error');
                }
            });
        }

        function joinClub(clubId) {
            fetch('chat_room.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=join_club&club_id=${clubId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Success', data.message || 'Join request submitted successfully!', 'success').then(() => {
                        // Update the button to show pending status
                        updateJoinButtonStatus(clubId, 'pending');
                    });
                } else {
                    Swal.fire('Error', data.error, 'error');
                }
            });
        }

        function leaveClub(clubId) {
            Swal.fire({
                title: 'Leave Club',
                text: 'Are you sure you want to leave this club?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, leave'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('chat_room.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=leave_club&club_id=${clubId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Success', 'You have left the club', 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', data.error, 'error');
                        }
                    });
                }
            });
        }

        function startMessagePolling() {
            messageInterval = setInterval(checkForUpdates, 3000);
        }

        // Online Status Management
        let onlineStatusInterval;
        let lastActivityTime = Date.now();

        // Start online status updates
        function startOnlineStatusUpdates() {
            // Clear any existing interval
            if (onlineStatusInterval) {
                clearInterval(onlineStatusInterval);
            }
            
            // Update online status every 30 seconds
            onlineStatusInterval = setInterval(updateOnlineStatus, 30000);
            
            // Refresh all room last messages every 2 minutes
            setInterval(loadAllRoomLastMessages, 120000);
            
            // Add event listeners for user activity
            document.addEventListener('click', updateOnlineStatusOnActivity);
            document.addEventListener('keypress', updateOnlineStatusOnActivity);
            document.addEventListener('scroll', updateOnlineStatusOnActivity);
            document.addEventListener('mousemove', updateOnlineStatusOnActivity);
        }

        // Stop online status updates
        function stopOnlineStatusUpdates() {
            if (onlineStatusInterval) {
                clearInterval(onlineStatusInterval);
                onlineStatusInterval = null;
            }
            
            // Remove event listeners
            document.removeEventListener('click', updateOnlineStatusOnActivity);
            document.removeEventListener('keypress', updateOnlineStatusOnActivity);
            document.removeEventListener('scroll', updateOnlineStatusOnActivity);
            document.removeEventListener('mousemove', updateOnlineStatusOnActivity);
        }

        // Update online status on user activity
        function updateOnlineStatusOnActivity() {
            lastActivityTime = Date.now();
            updateOnlineStatus();
        }

        // Update online status
        function updateOnlineStatus() {
            if (!currentRoomId) return;
            
            fetch('update_chat_room_online.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_online_status&room_id=${currentRoomId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update online count display
                    const onlineCountElement = document.getElementById('onlineCount');
                    if (onlineCountElement) {
                        onlineCountElement.textContent = `${data.online_count} online`;
                    }
                }
            })
            .catch(error => {
                console.error('Error updating online status:', error);
            });
        }

        // Get online count for a specific room
        function getOnlineCount(roomId) {
            fetch('update_chat_room_online.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_online_count&room_id=${roomId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update online count display if this is the current room
                    if (roomId === currentRoomId) {
                        const onlineCountElement = document.getElementById('onlineCount');
                        if (onlineCountElement) {
                            onlineCountElement.textContent = `${data.online_count} online`;
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error getting online count:', error);
            });
        }

        // Load last messages for all rooms
        function loadAllRoomLastMessages() {
            // Get all room items
            const roomItems = document.querySelectorAll('.room-item');
            
            roomItems.forEach(roomItem => {
                const roomId = roomItem.getAttribute('onclick')?.match(/selectRoom\('([^']+)'/)?.[1];
                if (roomId) {
                    // Get last message for this room
                    fetch('update_chat_room_online.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=get_last_message&room_id=${roomId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.last_message) {
                            const lastMessageElement = roomItem.querySelector('.room-last-message');
                            if (lastMessageElement) {
                                const message = data.last_message.message;
                                const username = data.last_message.username;
                                lastMessageElement.textContent = `${username}: ${message}`;
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error getting last message for room:', roomId, error);
                    });
                }
            });
        }

        // Refresh last message for a specific room
        function refreshRoomLastMessage(roomId) {
            fetch('update_chat_room_online.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_last_message&room_id=${roomId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.last_message) {
                    // Update the room item's last message
                    const roomItem = document.querySelector(`[onclick*="selectRoom('${roomId}')"]`);
                    if (roomItem) {
                        const lastMessageElement = roomItem.querySelector('.room-last-message');
                        if (lastMessageElement) {
                            const message = data.last_message.message;
                            const username = data.last_message.username;
                            lastMessageElement.textContent = `${username}: ${message}`;
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error refreshing last message for room:', roomId, error);
            });
        }

        function checkForUpdates() {
            if (!currentRoomId) return;
            
            fetch('chat_room.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_messages&room_id=${currentRoomId}&last_message_id=${lastMessageId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.messages && data.messages.length > 0) {
                    console.log('New messages detected, refreshing immediately');
                    refreshMessagesImmediately();
                    
                    // Refresh the last message for this room when new messages are detected
                    refreshRoomLastMessage(currentRoomId);
                    
                    // Also refresh the last message for all rooms to keep them updated
                    setTimeout(() => loadAllRoomLastMessages(), 1000);
                }
            })
            .catch(error => {
                console.error('Error checking for updates:', error);
            });
        }

        function refreshMessagesImmediately() {
            if (window.refreshTimeout) {
                clearTimeout(window.refreshTimeout);
            }
            
            window.refreshTimeout = setTimeout(() => {
                loadMessages();
            }, 100);
        }

        function handleMessageSent() {
            refreshMessagesImmediately();
            setTimeout(() => {
                const chatMessages = document.getElementById('chatMessages');
                if (chatMessages) {
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                }
            }, 200);
        }

        function scrollToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }

        function updateMessageNotification() {
            // Update the message notification count in nav.php
            fetch('nav.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=update_message_count'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Update the notification badge if it exists
                    const messageBadge = document.querySelector('.message-notification-badge');
                    if (messageBadge) {
                        messageBadge.textContent = data.count;
                        messageBadge.style.display = data.count > 0 ? 'block' : 'none';
                    }
                }
            })
            .catch(error => {
                console.error('Error updating message notification:', error);
                // Don't show error to user as this is not critical
            });
        }

        // File Upload Functions
        function toggleFileUpload() {
            document.getElementById('fileInput').click();
        }

        function handleFileUpload() {
            const fileInput = document.getElementById('fileInput');
            const file = fileInput.files[0];
            
            if (!file || !currentRoomId) return;
            
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
                    formData.append('room_id', currentRoomId);
                    formData.append('message', message);
                    
                    // Upload file
                    fetch('chat_room.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        // Check if response is valid JSON
                        const contentType = response.headers.get('content-type');
                        if (!contentType || !contentType.includes('application/json')) {
                            throw new Error('Server returned non-JSON response. This usually means there\'s a PHP error.');
                        }
                        return response.text();
                    })
                    .then(responseText => {
                        let data;
                        try {
                            data = JSON.parse(responseText);
                        } catch (e) {
                            console.error('Invalid JSON response:', responseText);
                            throw new Error('Server returned invalid JSON. Please check the console for details.');
                        }
                        
                        if (data.success) {
                            Swal.fire('Success', 'File uploaded successfully!', 'success');
                            fileInput.value = ''; // Clear the input
                            
                            // Refresh messages to show the new file
                            setTimeout(() => {
                                loadMessages(true);
                                // Also refresh the last message for this room
                                refreshRoomLastMessage(currentRoomId);
                                
                                // Also refresh the last message for all rooms to keep them updated
                                setTimeout(() => loadAllRoomLastMessages(), 1000);
                            }, 500);
                        } else {
                            Swal.fire('Error', data.error || 'Upload failed', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Upload error:', error);
                        let errorMessage = 'Upload failed. Please try again.';
                        
                        // Try to parse error response for more specific error messages
                        if (error.message && error.message.includes('JSON')) {
                            errorMessage = 'Server error occurred. Please check if file attachment support is set up.';
                        }
                        
                        Swal.fire('Error', errorMessage, 'error');
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
            
            // Get computed style to see what's actually being displayed
            const computedStyle = window.getComputedStyle(emojiPicker);
            const currentDisplay = emojiPicker.style.display;
            const computedDisplay = computedStyle.display;
            const isVisible = computedDisplay === 'block';
            
            console.log('Inline display style:', currentDisplay);
            console.log('Computed display style:', computedDisplay);
            console.log('Current visibility:', isVisible);
            
            if (isVisible) {
                emojiPicker.style.display = 'none';
                console.log('Hiding emoji picker');
            } else {
                emojiPicker.style.display = 'block';
                console.log('Showing emoji picker');
                
                // Debug positioning
                setTimeout(() => {
                    const rect = emojiPicker.getBoundingClientRect();
                    console.log('Emoji picker position:', {
                        top: rect.top,
                        left: rect.left,
                        width: rect.width,
                        height: rect.height,
                        visible: rect.width > 0 && rect.height > 0
                    });
                }, 100);
            }
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

        // Initialize emoji picker on page load
        document.addEventListener('DOMContentLoaded', function() {
            const emojiPicker = document.getElementById('emojiPicker');
            if (emojiPicker) {
                console.log('Initial display style:', emojiPicker.style.display);
            }
        });

        // File input change event
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('fileInput');
            if (fileInput) {
                fileInput.addEventListener('change', handleFileUpload);
            }
        });

        function showChatPanel() {
            const sidebar = document.getElementById('chatSidebar');
            const main = document.getElementById('chatMain');
            const backBtn = document.querySelector('.mobile-back-btn');
            
            if (window.innerWidth <= 768) {
                sidebar.classList.add('hidden');
                main.classList.remove('hidden');
                if (backBtn) backBtn.classList.remove('d-none');
            }
        }

        function showRoomsList() {
            const sidebar = document.getElementById('chatSidebar');
            const main = document.getElementById('chatMain');
            const backBtn = document.querySelector('.mobile-back-btn');
            
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('hidden');
                main.classList.add('hidden');
                if (backBtn) backBtn.classList.add('d-none');
            }
            
            // Clear current room
            if (currentRoomId) {
                // Cleanup online status for the current room
                fetch('update_chat_room_online.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=leave_room&room_id=${currentRoomId}`
                }).catch(error => {
                    console.error('Error leaving room:', error);
                });
                
                stopOnlineStatusUpdates();
            }
            
            currentRoomId = null;
            if (messageInterval) {
                clearInterval(messageInterval);
            }
            
            // Hide chat interface and show welcome screen
            document.getElementById('chatInterface').style.display = 'none';
            document.getElementById('welcomeScreen').style.display = 'flex';
            
            // Update URL
            const url = new URL(window.location);
            url.searchParams.delete('room');
            window.history.pushState({}, '', url);
        }

        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('chatSidebar');
            const main = document.getElementById('chatMain');
            const backBtn = document.querySelector('.mobile-back-btn');
            
            if (window.innerWidth > 768) {
                // On desktop, ensure both sidebar and main are visible
                sidebar.classList.remove('hidden');
                main.classList.remove('hidden');
                if (backBtn) backBtn.classList.add('d-none');
                
                // Show chat interface if room is selected
                if (currentRoomId) {
                    document.getElementById('chatInterface').style.display = 'flex';
                }
            } else {
                // On mobile, ensure proper state
                if (currentRoomId) {
                    sidebar.classList.add('hidden');
                    main.classList.remove('hidden');
                    if (backBtn) backBtn.classList.remove('d-none');
                } else {
                    sidebar.classList.remove('hidden');
                    main.classList.add('hidden');
                    if (backBtn) backBtn.classList.add('d-none');
                }
            }
        });
        
        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            if (messageInterval) {
                clearInterval(messageInterval);
            }
            
            // Cleanup online status
            if (currentRoomId) {
                fetch('update_chat_room_online.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=leave_room&room_id=${currentRoomId}`
                }).catch(error => {
                    console.error('Error leaving room:', error);
                });
            }
            
            stopOnlineStatusUpdates();
        });

        function checkClubRequestStatus(clubId) {
            fetch('chat_room.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=check_club_request_status&club_id=${clubId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateJoinButtonStatus(clubId, data.status);
                }
            });
        }

        function updateJoinButtonStatus(clubId, status) {
            // Find the club item container
            const clubItem = document.querySelector(`.club-item:has([onclick*="joinClub(${clubId})"])`) || 
                            document.querySelector(`.club-item:has([onclick*="selectRoom('club_${clubId}")`);
            
            if (clubItem) {
                const clubActions = clubItem.querySelector('.club-actions');
                if (clubActions) {
                    if (status === 'pending') {
                        clubActions.innerHTML = `
                            <button class="club-status-btn btn btn-warning btn-sm" disabled>
                                <i class="fas fa-clock"></i> Pending
                            </button>
                        `;
                    } else if (status === 'approved') {
                        clubActions.innerHTML = `
                            <button class="club-access-btn btn btn-success btn-sm" onclick="selectRoom('club_${clubId}', 'club', ${clubId}, event)">
                                <i class="fas fa-comments"></i> Chat
                            </button>
                        `;
                    } else if (status === 'rejected') {
                        clubActions.innerHTML = `
                            <button class="club-status-btn btn btn-danger btn-sm" disabled>
                                <i class="fas fa-times"></i> Rejected
                            </button>
                        `;
                    }
                }
            }
        }

        function checkAllClubRequestStatuses() {
            // Get all club items and check their status
            const clubItems = document.querySelectorAll('.club-item');
            clubItems.forEach(clubItem => {
                const joinBtn = clubItem.querySelector('[onclick*="joinClub("]');
                if (joinBtn) {
                    const onclick = joinBtn.getAttribute('onclick');
                    const match = onclick.match(/joinClub\((\d+)\)/);
                    if (match) {
                        const clubId = match[1];
                        checkClubRequestStatus(clubId);
                    }
                }
            });
        }

        // Load last messages for all rooms
        loadAllRoomLastMessages();
        
        // Check club request statuses for all clubs
        checkAllClubRequestStatuses();
    </script>
</body>
</html> 