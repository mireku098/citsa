<?php
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Handle AJAX requests for message notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_message_count') {
        // Get updated message count
        $unread_count = getUnreadMessagesCount($_SESSION['user_id'], $pdo);
        
        echo json_encode([
            'success' => true,
            'count' => $unread_count
        ]);
        exit();
    }
}

// Include helpers
include 'app/helpers/user.php';
include 'app/helpers/notifications.php';
include 'app/helpers/platform_management.php';
include 'app/helpers/chat_room_notifications.php';

// Get current user data if not already set
if (!isset($user)) {
    $user = getUser($_SESSION['user_id'], $pdo);
}

// Get pending friend requests count
$pending_requests_count = getPendingFriendRequestsCount($_SESSION['user_id'], $pdo);

// Get unread messages count (private + chat rooms)
$unread_messages_count = getUnreadMessagesCount($_SESSION['user_id'], $pdo);
$chat_room_unread_counts = getAllChatRoomsUnreadCount($_SESSION['user_id'], $pdo);
$chat_room_unread_total = 0;
if (is_array($chat_room_unread_counts)) {
    foreach ($chat_room_unread_counts as $cnt) {
        $chat_room_unread_total += (int)$cnt;
    }
}
$total_unread_messages_count = (int)$unread_messages_count + $chat_room_unread_total;
?>

<!-- Bootstrap Icons CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<!-- Remix Icons CSS -->
<link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
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
    }

    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background-color: var(--bg-light);
    }

    .header {
        background: var(--white);
        box-shadow: 0 2px 10px rgba(26, 60, 109, 0.1);
        border-bottom: 1px solid rgba(26, 60, 109, 0.1);
        z-index: 1000;
        padding: 0.5rem 1.5rem;
        width: 100%;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
    }

    .sidebar {
        background: var(--white);
        box-shadow: 2px 0 10px rgba(26, 60, 109, 0.1);
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        width: 300px;
        z-index: 999;
        transition: transform 0.3s ease;
        padding-top: 60px;
        transform: translateX(-100%);
        margin-top: 15px;
    }

    .sidebar.show {
        transform: translateX(0);
    }

    .sidebar-nav {
        padding: 0;
        margin: 0;
        list-style: none;
    }

    .sidebar-nav .nav-heading {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-light);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 1rem 1.5rem 0.5rem;
        margin-bottom: 0.5rem;
    }

    .sidebar-nav .nav-item {
        margin-bottom: 0.25rem;
    }

    .sidebar-nav .nav-link {
        color: var(--text-dark) !important;
        padding: 0.75rem 1.5rem;
        display: flex;
        align-items: center;
        text-decoration: none;
        transition: all 0.3s ease;
        border-radius: 0.5rem;
        margin: 0 0.5rem;
    }

    .sidebar-nav .nav-link:hover {
        background-color: rgba(26, 60, 109, 0.1);
        color: var(--primary-color) !important;
        transform: translateX(5px);
    }

    .sidebar-nav .nav-link.active {
        background-color: var(--primary-color);
        color: var(--white) !important;
    }

    .sidebar-nav .nav-link i {
        font-size: 1.1rem;
        margin-right: 0.75rem;
        width: 20px;
        text-align: center;
    }

    .main {
        margin-left: 0;
        padding-top: 80px;
        transition: margin-left 0.3s ease;
    }

    body.sidebar-open .sidebar {
        transform: translateX(0);
    }

    body.sidebar-open .main {
        margin-left: 300px;
    }

    .header-nav .nav-link {
        color: var(--text-dark);
        padding: 0.5rem 1rem;
        position: relative;
    }

    .header-nav .nav-link:hover {
        color: var(--primary-color);
    }

    .dropdown-menu {
        border: none;
        box-shadow: 0 4px 20px rgba(26, 60, 109, 0.15);
        border-radius: 0.75rem;
    }

    .dropdown-header {
        background-color: var(--bg-light);
        border-radius: 0.75rem 0.75rem 0 0;
    }

    .btn-primary-custom {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
        color: var(--white);
    }

    .btn-primary-custom:hover {
        background-color: var(--secondary-color);
        border-color: var(--secondary-color);
        color: var(--white);
    }

    .navbar-toggler {
        background: var(--primary-color);
        border: none;
        border-radius: 8px;
        padding: 8px 12px;
        color: white;
        transition: all 0.3s ease;
    }

    .navbar-toggler:hover {
        background: var(--secondary-color);
        transform: scale(1.05);
    }

    .navbar-toggler:focus {
        box-shadow: 0 0 0 0.2rem rgba(26, 60, 109, 0.25);
    }

    .navbar-toggler-icon {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.9%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
    }

    @media (max-width: 768px) {
        .main {
            margin-left: 0;
        }
        
        body.sidebar-open .main {
            margin-left: 0;
        }

        /* Hide logo and branding on mobile */
        .navbar-brand,
        .ms-3 h5,
        .ms-3 p {
            display: none !important;
        }

        /* Show header-nav on mobile but adjust spacing */
        .header-nav {
            display: flex !important;
        }

        /* Position toggler at extreme left, other elements at extreme right */
        .d-flex.align-items-center.justify-content-between.w-100 {
            justify-content: space-between !important;
            margin-bottom: 5px;
        }

        /* Ensure toggle button is visible and positioned at extreme left */
        #sidebarToggle {
            position: relative;  
            left: -35px;           
            display: block !important;
        }
        /* Position header-nav at extreme right */
        .header-nav {
            margin-left: auto;
            gap: 0.125rem;
        }

        /* Reduce margins between the three right-side buttons */
        .header-nav .nav-link {
            padding: 0.125rem 0.25rem;
        }

        .header-nav .dropdown {
            margin: 0;
        }

        /* Ensure minimal spacing between buttons */
        .header-nav .dropdown + .dropdown {
            margin-left: 0.125rem;
        }
    }

    /* Breadcrumb styling */
    .breadcrumb-item a {
        color: #fff !important;
        text-decoration: none !important;
    }

    .breadcrumb-item a:hover {
        color: #fff !important;
        text-decoration: none !important;
    }

    .breadcrumb-item.active {
        color: #fff !important;
    }

    /* Notification dropdown styling */
    .notification-dropdown {
        width: 350px !important;
        max-height: 400px;
        overflow-y: auto;
    }

    .notification-item {
        border-bottom: 1px solid #f0f0f0;
        padding: 12px 16px;
        transition: background-color 0.2s ease;
    }

    .notification-item:hover {
        background-color: #f8f9fa;
    }

    .notification-item:last-child {
        border-bottom: none;
    }

    .notification-avatar {
        width: 40px;
        height: 40px;
        object-fit: cover;
        border: 2px solid #e9ecef;
    }

    .notification-actions {
        margin-top: 8px;
    }

    .notification-actions .btn {
        font-size: 0.75rem;
        padding: 4px 8px;
    }

    .notification-time {
        font-size: 0.75rem;
        color: #6c757d;
    }

    .notification-name {
        font-weight: 600;
        color: #1a3c6d;
        margin-bottom: 2px;
    }

    .notification-programme {
        font-size: 0.8rem;
        color: #6c757d;
        margin-bottom: 4px;
    }

    .notification-message {
        font-size: 0.8rem;
        color: #495057;
        margin-bottom: 4px;
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .message-badge {
        font-size: 0.7rem;
        padding: 2px 6px;
    }

    .clickable-message {
        cursor: pointer;
        transition: all 0.2s ease;
        border-radius: 8px;
        padding: 8px;
        margin: 2px 0;
    }

    .clickable-message:hover {
        background-color: #e3f2fd !important;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .clickable-message:active {
        transform: translateY(0);
        background-color: #bbdefb !important;
    }

    .clickable-message.loading {
        opacity: 0.7;
        pointer-events: none;
    }

</style>

<!-- ======= Header ======= -->
<header class="header fixed-top d-flex align-items-center">
    <div class="d-flex align-items-center justify-content-between w-100">
        <div class="d-flex align-items-center">
            <!-- Logo Section -->
            <a class="navbar-brand d-flex align-items-center gap-3" href="#">
                <?php
                $platform_logo = getPlatformSetting('platform_logo', $pdo, 'citsa-logo.png');
                $logo_path = 'uploads/platform/' . $platform_logo;
                if (file_exists($logo_path)) {
                    echo '<img src="' . $logo_path . '" alt="Platform Logo" style="width: 40px; height: 40px; object-fit: contain;">';
                } else {
                    echo '<div class="bg-white rounded-lg p-2 border border-[#1a3c6d]">';
                    echo '<i class="fas fa-graduation-cap text-[#1a3c6d]" style="font-size: 2rem;"></i>';
                    echo '</div>';
                }
                ?>
            </a>
            
            <!-- Branding -->
            <div class="ms-3">
                <?php
                $platform_name = getPlatformSetting('platform_name', $pdo, 'CITSA Connect');
                $platform_description = getPlatformSetting('platform_description', $pdo, 'Student-Alumni Portal');
                ?>
                <h5 class="mb-0 fw-bold" style="font-size: 1.25rem; color: var(--primary-color);"><?php echo htmlspecialchars($platform_name); ?></h5>
                <p class="mb-0 text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($platform_description); ?></p>
            </div>
            
            <!-- Toggle Sidebar Button -->
            <button class="navbar-toggler ms-3" type="button" id="sidebarToggle">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>

        <!-- Right: Notification, Messages, Profile -->
        <nav class="header-nav d-flex align-items-center gap-3">
            <!-- Notification Icon -->
            <div class="dropdown">
                <a class="nav-link position-relative" href="#" data-bs-toggle="dropdown" id="notificationDropdown">
                    <i class="bi bi-bell fs-5" style="color: var(--primary-color);"></i>
                    <?php if ($pending_requests_count > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notificationBadge"><?php echo $pending_requests_count; ?></span>
                    <?php endif; ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end notification-dropdown">
                    <li class="dropdown-header">
                        <h6 class="mb-0">Notifications</h6>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <div id="notificationContent">
                        <li class="notification-item text-center">
                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <span class="ms-2">Loading notifications...</span>
                        </li>
                    </div>
                    <li><hr class="dropdown-divider"></li>
                    <li class="text-center">
                        <a href="friends.php" class="dropdown-item text-primary">
                            <i class="bi bi-people me-2"></i>View All Friend Requests
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Messages Icon -->
            <div class="dropdown">
                <a class="nav-link position-relative" href="#" data-bs-toggle="dropdown" id="messageDropdown">
                    <i class="bi bi-chat-left-text fs-5" style="color: var(--primary-color);"></i>
                    <?php if ($total_unread_messages_count > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="messageBadge"><?php echo $total_unread_messages_count; ?></span>
                    <?php endif; ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end notification-dropdown">
                    <li class="dropdown-header">
                        <h6 class="mb-0">Messages</h6>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <div id="messageContent">
                        <li class="notification-item text-center">
                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <span class="ms-2">Loading messages...</span>
                        </li>
                    </div>
                    <li><hr class="dropdown-divider"></li>
                    <li class="text-center">
                        <a href="private.php" class="dropdown-item text-primary">
                            <i class="bi bi-chat-dots me-2"></i>View All Messages
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Profile Dropdown -->
            <li class="nav-item dropdown pe-3 list-unstyled">
                <a class="nav-link nav-profile d-flex align-items-center pe-0" href="#" data-bs-toggle="dropdown">
                    <img src="profile/<?php echo htmlspecialchars($user['profile_image'] ?? 'default-avatar.png'); ?>" class="rounded-circle" style="width: 35px; height: 35px; object-fit: cover;">
                    <span class="d-none d-md-block dropdown-toggle ps-2" style="color: var(--primary-color); font-weight: 600;"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow profile">
                    <li class="dropdown-header">
                        <h6><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h6>
                        <span class="text-muted"><?php echo htmlspecialchars($user['programme']); ?></span>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item d-flex align-items-center" href="profile.php">
                            <i class="bi bi-person me-2"></i>
                            <span>My Profile</span>
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item d-flex align-items-center" href="profile.php" onclick="showAccountSettings()">
                            <i class="bi bi-gear me-2"></i>
                            <span>Account Settings</span>
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item d-flex align-items-center" href="#" onclick="showHelp()">
                            <i class="bi bi-question-circle me-2"></i>
                            <span>Need Help?</span>
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item d-flex align-items-center" href="logout.php">
                            <i class="bi bi-box-arrow-right me-2"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </li>
        </nav>
    </div>
</header>

<!-- ======= Sidebar ======= -->
<aside id="sidebar" class="sidebar">
    <ul class="sidebar-nav" id="sidebar-nav">
        <li class="nav-heading">Pages</li>
        <li class="nav-item">
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'home.php') ? 'active' : ''; ?>" href="home.php">
                <i class="bi bi-house"></i>
                <span>Home</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'friends.php') ? 'active' : ''; ?>" href="friends.php">
                <i class="ri-chat-smile-3-line"></i>
                <span>Friends</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'private.php') ? 'active' : ''; ?>" href="private.php">
                <i class="ri-admin-line"></i>
                <span>Private Chat</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'chat_room.php') ? 'active' : ''; ?>" href="chat_room.php">
                <i class="ri-wechat-line"></i>
                <span>Chat Rooms</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'active' : ''; ?>" href="profile.php">
                <i class="ri-account-circle-fill"></i>
                <span>Profile</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="logout.php">
                <i class="bi bi-box-arrow-in-left"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</aside>

<script>
// Sidebar toggle functionality
document.getElementById('sidebarToggle').addEventListener('click', function() {
    document.body.classList.toggle('sidebar-open');
    
    // Update icon based on sidebar state
    const icon = this.querySelector('.navbar-toggler-icon');
    if (document.body.classList.contains('sidebar-open')) {
        // Show X icon when sidebar is open
        icon.style.backgroundImage = "url(\"data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23ffffff'%3e%3cpath d='M.293.293a1 1 0 011.414 0L8 6.586 14.293.293a1 1 0 111.414 1.414L9.414 8l6.293 6.293a1 1 0 01-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 01-1.414-1.414L6.586 8 .293 1.707a1 1 0 010-1.414z'/%3e%3c/svg%3e\")";
    } else {
        // Show hamburger icon when sidebar is closed
        icon.style.backgroundImage = "url(\"data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.9%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e\")";
    }
});

// Account Settings Modal
function showAccountSettings() {
    Swal.fire({
        title: 'Account Settings',
        html: `
            <div class="text-start">
                <div class="mb-3">
                    <strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?>
                </div>
                <div class="mb-3">
                    <strong>Student ID:</strong> <?php echo htmlspecialchars($user['student_id']); ?>
                </div>
                <div class="mb-3">
                    <strong>Programme:</strong> <?php echo htmlspecialchars($user['programme']); ?>
                </div>
                <div class="mb-3">
                                            <strong>User Type:</strong> 
                        <span class="badge <?= getUserTypeBadgeClass($user['user_type']) ?>">
                            <?= getUserTypeLabel($user['user_type']) ?>
                        </span>
                </div>
            </div>
        `,
        icon: 'info',
        confirmButtonColor: '#1a3c6d',
        confirmButtonText: 'Close',
        customClass: {
            popup: 'rounded-3'
        }
    });
}

// Help Modal
function showHelp() {
    Swal.fire({
        title: 'Need Help?',
        html: `
            <div class="text-start">
                <h6 class="text-primary">How to use <?php echo htmlspecialchars(getPlatformSetting('platform_name', $pdo, 'CITSA Connect')); ?>:</h6>
                <ul class="text-start">
                    <li>Use the sidebar to navigate between different sections</li>
                    <li>Click on "Friends" to find and connect with other users</li>
                    <li>Use "Private Chat" for one-on-one conversations</li>
                    <li>Join "Chat Rooms" for group discussions</li>
                    <li>Update your profile to share more about yourself</li>
                </ul>
                <div class="mt-3 p-3 bg-light rounded">
                    <strong>For technical support:</strong> Contact your system administrator
                </div>
            </div>
        `,
        icon: 'question',
        confirmButtonColor: '#1a3c6d',
        confirmButtonText: 'Got it!',
        customClass: {
            popup: 'rounded-3'
        }
    });
}

// Notification System
document.addEventListener('DOMContentLoaded', function() {
    const notificationDropdown = document.getElementById('notificationDropdown');
    const notificationContent = document.getElementById('notificationContent');
    const notificationBadge = document.getElementById('notificationBadge');
    
    // Load notifications when dropdown is shown
    notificationDropdown.addEventListener('click', function() {
        loadNotifications();
    });
    
    function loadNotifications() {
        fetch('get_notifications.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayNotifications(data.friend_requests, data.request_count);
                } else {
                    notificationContent.innerHTML = '<li class="notification-item text-center text-muted">Failed to load notifications</li>';
                }
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
                notificationContent.innerHTML = '<li class="notification-item text-center text-muted">Error loading notifications</li>';
            });
    }
    
    function displayNotifications(friendRequests, requestCount) {
        if (friendRequests.length === 0) {
            notificationContent.innerHTML = '<li class="notification-item text-center text-muted">No new notifications</li>';
            return;
        }
        
        let html = '';
        friendRequests.forEach(request => {
            html += `
                <li class="notification-item">
                    <div class="d-flex align-items-start">
                        <img src="profile/${request.sender_profile_image}" class="rounded-circle me-3 notification-avatar">
                        <div class="flex-grow-1">
                            <div class="notification-name">${request.sender_name}</div>
                            <div class="notification-programme">${request.sender_programme}</div>
                            <div class="notification-time">${request.time_ago}</div>
                            <div class="notification-actions">
                                <button class="btn btn-sm btn-success me-1" onclick="handleFriendRequest(${request.id}, 'accept')">
                                    <i class="bi bi-check"></i> Accept
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="handleFriendRequest(${request.id}, 'reject')">
                                    <i class="bi bi-x"></i> Decline
                                </button>
                            </div>
                        </div>
                    </div>
                </li>
            `;
        });
        
        notificationContent.innerHTML = html;
    }
    
    // Global function to handle friend request actions
    window.handleFriendRequest = function(requestId, action) {
        const formData = new FormData();
        formData.append('request_id', requestId);
        formData.append('action', action);
        
        fetch('handle_friend_request.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                Swal.fire({
                    title: 'Success!',
                    text: data.message,
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                });
                
                // Update notification count
                if (notificationBadge) {
                    if (data.new_count > 0) {
                        notificationBadge.textContent = data.new_count;
                    } else {
                        notificationBadge.remove();
                    }
                }
                
                // Reload notifications
                loadNotifications();
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: data.message,
                    icon: 'error',
                    confirmButtonColor: '#1a3c6d'
                });
            }
        })
        .catch(error => {
            console.error('Error handling friend request:', error);
            Swal.fire({
                title: 'Error!',
                text: 'An error occurred while processing your request.',
                icon: 'error',
                confirmButtonColor: '#1a3c6d'
            });
        });
    };
    
    // Auto-refresh notifications every 30 seconds
    setInterval(loadNotifications, 30000);
});

// Message Notification System
document.addEventListener('DOMContentLoaded', function() {
    const messageDropdown = document.getElementById('messageDropdown');
    const messageContent = document.getElementById('messageContent');
    const messageBadge = document.getElementById('messageBadge');
    
    // Load messages when dropdown is shown
    messageDropdown.addEventListener('click', function() {
        loadMessages();
    });
    
    function loadMessages() {
        Promise.all([
            fetch('get_message_notifications.php').then(r => r.json()).catch(() => ({ success: false })),
            fetch('get_chat_room_notifications.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'action=get_recent' })
              .then(r => r.json()).catch(() => ({ success: false }))
        ])
            .then(([privData, roomData]) => {
                if (!privData.success && !roomData.success) {
                    messageContent.innerHTML = '<li class="notification-item text-center text-muted">Failed to load messages</li>';
                    return;
                }
                const conversations = privData.success ? (privData.conversations || []) : [];
                const rooms = roomData.success ? (roomData.rooms || []) : [];
                displayMessagesCombined(conversations, privData.unread_count || 0, rooms);
            })
            .catch(error => {
                console.error('Error loading messages:', error);
                messageContent.innerHTML = '<li class="notification-item text-center text-muted">Error loading messages</li>';
            });
    }
    
    function displayMessagesCombined(conversations, unreadCount, rooms) {
        const parts = [];
        // compute total unread
        let totalUnread = Number(unreadCount || 0);
        const unified = [];
        // map private into unified
        conversations.forEach(c => {
            unified.push({
                type: 'private',
                id: c.conversation_id,
                avatar: c.sender_profile_image,
                title: c.sender_name,
                message: c.last_message,
                time: c.last_message_time,
                time_ago: c.time_ago,
                unread: Number(c.unread_count || 0)
            });
        });
        // map rooms into unified
        rooms.forEach(r => {
            unified.push({
                type: 'room',
                id: r.room_id,
                avatar: r.sender_profile_image,
                title: r.room_name,
                message: `@${r.sender_username}: ${r.last_message}`,
                time: r.last_message_time,
                time_ago: r.time_ago,
                unread: Number(r.unread_count || 0)
            });
            totalUnread += Number(r.unread_count || 0);
        });
        // sort by time desc
        unified.sort((a,b) => new Date(b.time) - new Date(a.time));
        if (unified.length === 0) {
            messageContent.innerHTML = '<li class="notification-item text-center text-muted">No new messages</li>';
            // update badge to zero
            const badge0 = document.getElementById('messageBadge');
            if (badge0) badge0.remove();
            return;
        }
        unified.forEach(item => {
            parts.push(`
                <li class="notification-item ${item.type === 'private' ? 'clickable-message' : 'clickable-room'}" data-${item.type === 'private' ? 'conversation-id' : 'room-id'}="${item.id}">
                    <div class="d-flex align-items-start">
                        <img src="profile/${item.avatar}" class="rounded-circle me-3 notification-avatar">
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="notification-name">${item.title}</div>
                                <span class="badge bg-primary ms-2 message-badge">${item.unread}</span>
                            </div>
                            <div class="notification-message">${item.message}</div>
                            <div class="notification-time" data-time="${item.time}">${item.time_ago}</div>
                        </div>
                    </div>
                </li>
            `);
        });
        messageContent.innerHTML = parts.join('');
        if (parts.length === 0) {
            messageContent.innerHTML = '<li class="notification-item text-center text-muted">No new messages</li>';
        } else {
            messageContent.innerHTML = parts.join('');
        }

        // Add click handlers
        messageContent.querySelectorAll('.clickable-message').forEach(item => {
            item.addEventListener('click', function() {
                const conversationId = this.getAttribute('data-conversation-id');
                this.classList.add('loading');
                this.style.cursor = 'wait';
                openChat(conversationId);
            });
        });
        messageContent.querySelectorAll('.clickable-room').forEach(item => {
            item.addEventListener('click', function() {
                const roomId = this.getAttribute('data-room-id');
                this.classList.add('loading');
                this.style.cursor = 'wait';

                // Optimistically decrement total badge by this room's unread
                const roomBadge = this.querySelector('.message-badge');
                const dec = roomBadge ? parseInt(roomBadge.textContent || '0', 10) : 0;
                const totalBadgeEl = document.getElementById('messageBadge');
                if (totalBadgeEl && dec > 0) {
                    const currentTotal = parseInt(totalBadgeEl.textContent || '0', 10) || 0;
                    const nextTotal = Math.max(0, currentTotal - dec);
                    if (nextTotal > 0) {
                        totalBadgeEl.textContent = nextTotal;
                    } else {
                        totalBadgeEl.remove();
                    }
                }
                if (roomBadge) {
                    roomBadge.remove();
                }
                // Remove the room item from the list immediately
                this.remove();

                // Mark room messages as read, then navigate
                fetch('get_chat_room_notifications.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=mark_as_read&room_id=${encodeURIComponent(roomId)}`
                }).finally(() => {
                    window.location.href = `chat_room.php?room=${encodeURIComponent(roomId)}`;
                });
            });
        });

        updateMessageTimes();

        // Update or create message badge with total unread
        const badge = document.getElementById('messageBadge');
        const anchor = document.getElementById('messageDropdown');
        if (totalUnread > 0) {
            if (badge) {
                badge.textContent = totalUnread;
            } else if (anchor) {
                const span = document.createElement('span');
                span.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                span.id = 'messageBadge';
                span.textContent = totalUnread;
                anchor.appendChild(span);
            }
        } else if (badge) {
            badge.remove();
        }
    }
    
    function openChat(conversationId) {
        console.log('Opening chat for conversation:', conversationId);
        
        // Validate conversation ID
        if (!conversationId || isNaN(conversationId)) {
            console.error('Invalid conversation ID:', conversationId);
            return;
        }
        
        // Close the dropdown first
        const messageDropdown = document.getElementById('messageDropdown');
        if (messageDropdown) {
            try {
                const dropdown = bootstrap.Dropdown.getInstance(messageDropdown);
                if (dropdown) {
                    dropdown.hide();
                } else {
                    // Fallback: manually hide the dropdown
                    messageDropdown.classList.remove('show');
                    const dropdownMenu = messageDropdown.querySelector('.dropdown-menu');
                    if (dropdownMenu) {
                        dropdownMenu.classList.remove('show');
                    }
                }
            } catch (error) {
                console.log('Error closing dropdown:', error);
                // Fallback: manually hide
                messageDropdown.classList.remove('show');
            }
        }
        
        // Navigate to the chat
        console.log('Navigating to:', `private.php?conversation_id=${conversationId}`);
        window.location.href = `private.php?conversation_id=${conversationId}`;
    }
    
    function updateMessageTimes() {
        const timeElements = document.querySelectorAll('.notification-time[data-time]');
        timeElements.forEach(element => {
            const messageTime = element.getAttribute('data-time');
            element.textContent = timeAgo(messageTime);
        });
    }
    
    // Helper function for time ago calculation
    function timeAgo(datetime) {
        const now = new Date();
        const messageTime = new Date(datetime);
        const diffMs = now - messageTime;
        const diffSeconds = Math.floor(diffMs / 1000);
        const diffMinutes = Math.floor(diffSeconds / 60);
        const diffHours = Math.floor(diffMinutes / 60);
        const diffDays = Math.floor(diffHours / 24);
        
        if (diffSeconds < 60) {
            return 'Just now';
        } else if (diffMinutes < 60) {
            return `${diffMinutes} min${diffMinutes > 1 ? 's' : ''} ago`;
        } else if (diffHours < 24) {
            return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
        } else {
            return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
        }
    }
    
    // Auto-refresh messages every 30 seconds
    setInterval(loadMessages, 30000);

    // Periodically refresh the total unread badge (private + rooms) even if dropdown is not opened
    function refreshTotalMessageBadge() {
        Promise.all([
            fetch('get_message_notifications.php')
              .then(r => r.json())
              .then(d => (d && d.success ? Number(d.unread_count || 0) : 0))
              .catch(() => 0),
            fetch('get_chat_room_notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_all_unread_counts'
            })
              .then(r => r.json())
              .then(d => {
                  if (!d || !d.success) return 0;
                  const counts = d.unread_counts || {};
                  let sum = 0; for (const k in counts) { sum += Number(counts[k] || 0); }
                  return sum;
              })
              .catch(() => 0)
        ])
        .then(([privUnread, roomUnread]) => {
            const total = Number(privUnread) + Number(roomUnread);
            const badge = document.getElementById('messageBadge');
            const anchor = document.getElementById('messageDropdown');
            if (total > 0) {
                if (badge) {
                    badge.textContent = total;
                } else if (anchor) {
                    const span = document.createElement('span');
                    span.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                    span.id = 'messageBadge';
                    span.textContent = total;
                    anchor.appendChild(span);
                }
            } else if (badge) {
                badge.remove();
            }
        })
        .catch(() => {});
    }

    // Run immediately and every 20s
    refreshTotalMessageBadge();
    setInterval(refreshTotalMessageBadge, 20000);
    
    // Update message times every minute for real-time display
    setInterval(updateMessageTimes, 60000);
});

</script>
