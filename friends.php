<?php 
  session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

  	# database connection file
  	include 'app/db.conn.php';
  	include 'app/helpers/user.php';
  	include 'app/helpers/conversations.php';
    include 'app/helpers/timeAgo.php';
    include 'app/helpers/last_chat.php';

# Getting User data
$user = getUser($_SESSION['user_id'], $pdo);

// Handle friend request actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_request'])) {
        $friend_id = $_POST['friend_id'];
        $stmt = $pdo->prepare("INSERT INTO friend_requests (sender_id, receiver_id, status, created_at) VALUES (?, ?, 'pending', NOW())");
        $stmt->execute([$_SESSION['user_id'], $friend_id]);
        $success_message = "Friend request sent successfully!";
    } elseif (isset($_POST['accept_request'])) {
        $request_id = $_POST['request_id'];
        $sender_id = $_POST['sender_id'];
        
        // Update request status to accepted
        $stmt = $pdo->prepare("UPDATE friend_requests SET status = 'accepted', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$request_id]);
        
        // Check if friendship already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)");
        $stmt->execute([$_SESSION['user_id'], $sender_id, $sender_id, $_SESSION['user_id']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing['count'] == 0) {
            // Add to friends table only if it doesn't exist
            $stmt = $pdo->prepare("INSERT INTO friends (user_id, friend_id, created_at) VALUES (?, ?, NOW()), (?, ?, NOW())");
            $stmt->execute([$_SESSION['user_id'], $sender_id, $sender_id, $_SESSION['user_id']]);
        }
        
        $success_message = "Friend request accepted!";
    } elseif (isset($_POST['reject_request'])) {
        $request_id = $_POST['request_id'];
        $stmt = $pdo->prepare("UPDATE friend_requests SET status = 'rejected', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$request_id]);
        $success_message = "Friend request rejected.";
    } elseif (isset($_POST['remove_friend'])) {
        $friend_id = $_POST['friend_id'];
        $stmt = $pdo->prepare("DELETE FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)");
        $stmt->execute([$_SESSION['user_id'], $friend_id, $friend_id, $_SESSION['user_id']]);
        $success_message = "Friend removed successfully.";
    }
}

// Get search query
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get all users (excluding current user)
$users_query = "SELECT u.*, 
                CASE WHEN f1.user_id IS NOT NULL THEN 'friend' 
                     WHEN fr1.sender_id IS NOT NULL AND fr1.status = 'pending' THEN 'request_sent'
                     WHEN fr2.receiver_id IS NOT NULL AND fr2.status = 'pending' THEN 'request_received'
                     ELSE 'none' END as relationship_status
                FROM users u 
                LEFT JOIN friends f1 ON (f1.user_id = ? AND f1.friend_id = u.user_id)
                LEFT JOIN friend_requests fr1 ON (fr1.sender_id = ? AND fr1.receiver_id = u.user_id AND fr1.status = 'pending')
                LEFT JOIN friend_requests fr2 ON (fr2.sender_id = u.user_id AND fr2.receiver_id = ? AND fr2.status = 'pending')
                WHERE u.user_id != ? AND u.status = 'active'";

$users_params = [$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']];

if (!empty($search)) {
    $users_query .= " AND (CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.username LIKE ? OR u.student_id LIKE ?)";
    $search_param = "%$search%";
    $users_params = array_merge($users_params, [$search_param, $search_param, $search_param]);
}

$users_query .= " ORDER BY u.first_name, u.last_name";
$stmt = $pdo->prepare($users_query);
$stmt->execute($users_params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current user's friends
$stmt = $pdo->prepare("
    SELECT DISTINCT u.* FROM users u 
    JOIN friends f ON (f.user_id = ? AND f.friend_id = u.user_id) OR (f.friend_id = ? AND f.user_id = u.user_id)
    WHERE u.status = 'active'
    ORDER BY u.first_name, u.last_name
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$friends = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending friend requests
$stmt = $pdo->prepare("
    SELECT fr.*, u.first_name, u.last_name, u.username, u.profile_image, u.programme 
    FROM friend_requests fr 
    JOIN users u ON fr.sender_id = u.user_id 
    WHERE fr.receiver_id = ? AND fr.status = 'pending'
    ORDER BY fr.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent activity
$stmt = $pdo->prepare("
    SELECT 'friend_request' as type, fr.created_at, u.first_name, u.last_name, u.profile_image
    FROM friend_requests fr 
    JOIN users u ON fr.sender_id = u.user_id 
    WHERE fr.receiver_id = ? AND fr.status = 'pending'
    UNION ALL
    SELECT 'new_friend' as type, f.created_at, u.first_name, u.last_name, u.profile_image
    FROM friends f 
    JOIN users u ON f.friend_id = u.user_id 
    WHERE f.user_id = ? AND f.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="nQ3Z1OYKyIL41u1iEHbRdHlb2cHDs8PjDvHC3Slg">

    <!-- Theme Colors -->
    <meta name="theme-color" content="#1E40AF">
    <meta name="msapplication-navbutton-color" content="#1E40AF">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <!-- Primary Meta Tags -->
    <title>Friends - CITSA Connect</title>
    <meta name="title" content="Friends - CITSA Connect">
    <meta name="description" content="Connect with friends and alumni through our student-alumni communication platform.">
    <meta name="keywords" content="Student Portal, Alumni Network, CITSA, UCC, Computer Science, IT, Communication Platform, Professional Networking">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="favicon.ico">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Bootstrap Icons CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- AOS Animation -->
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">

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
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--bg-light);
        }

        .main {
            margin-left: 0;
            margin-top: 5px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }

        body.sidebar-open .main {
            margin-left: 300px;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(26, 60, 109, 0.1);
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(26, 60, 109, 0.15);
        }

        .pagetitle {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            border: none;
            color: white;
            border-radius: 25px;
            padding: 10px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(26, 60, 109, 0.3);
            color: white;
        }

        .user-card {
            border: 1px solid #e9ecef;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            background: white;
        }

        .user-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(26, 60, 109, 0.15);
        }

        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-color);
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-friend {
            background-color: #d4edda;
            color: #155724;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-request {
            background-color: #cce5ff;
            color: #004085;
        }

        .search-box {
            background: white;
            border-radius: 25px;
            padding: 15px 25px;
            box-shadow: 0 4px 15px rgba(26, 60, 109, 0.1);
            margin-bottom: 30px;
        }

        .search-input {
            border: none;
            outline: none;
            width: 100%;
            font-size: 1rem;
        }

        .search-input::placeholder {
            color: #6b7280;
        }

        .search-input-container {
            position: relative;
        }

        .search-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            max-height: 300px;
            overflow-y: auto;
            z-index: 99999;
            display: none;
        }

        .search-suggestion-item {
            padding: 12px 15px;
            border-bottom: 1px solid #f3f4f6;
            cursor: pointer;
            transition: background-color 0.2s ease;
            display: flex;
            align-items: center;
        }

        .search-suggestion-item:last-child {
            border-bottom: none;
        }

        .search-suggestion-item:hover {
            background-color: #f8fafc;
        }

        .search-suggestion-item.selected {
            background-color: var(--primary-color);
            color: white;
        }

        .search-suggestion-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 12px;
            border: 2px solid #e5e7eb;
        }

        .search-suggestion-content {
            flex: 1;
        }

        .search-suggestion-name {
            font-weight: 600;
            margin-bottom: 2px;
        }

        .search-suggestion-details {
            font-size: 0.8rem;
            color: #6b7280;
        }

        .search-suggestion-item.selected .search-suggestion-details {
            color: rgba(255, 255, 255, 0.8);
        }

        .activity-item {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .activity-item:hover {
            background-color: rgba(26, 60, 109, 0.05);
            border-radius: 10px;
        }

        .activity-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .stats-card {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(26, 60, 109, 0.3);
        }

        .stats-card i {
            font-size: 2.5rem;
            margin-bottom: 15px;
            opacity: 0.8;
        }

        @media (max-width: 768px) {
            .main {
                margin-left: 0 !important;
                margin-top: 5px !important;
            }
            
            body.sidebar-open .main {
                margin-left: 0 !important;
            }
        }

        @media (max-width: 400px) {
            .stats-cards-row,
            .all-users-section {
                display: none !important;
            }
            
            .search-suggestions {
                z-index: 99999 !important;
                position: fixed !important;
                top: 50% !important;
                left: 20px !important;
                right: 20px !important;
                width: calc(100% - 40px) !important;
                max-height: 250px !important;
                transform: translateY(-50%) !important;
                margin-top: 20px !important;
            }
        }
    </style>
</head>

<body>
    <!-- Include Navigation -->
    <?php include 'nav.php'; ?>

    <!-- ======= Main Content ======= -->
<main id="main" class="main">
        <div class="pagetitle" data-aos="fade-up">
            <h1>Friends</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="home.php">Home</a></li>
          <li class="breadcrumb-item active">Friends</li>
        </ol>
      </nav>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert" data-aos="fade-up">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <section class="section">
            <!-- Stats Cards -->
            <div class="row mb-4 stats-cards-row" data-aos="fade-up" data-aos-delay="100">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card">
                        <i class="bi bi-people"></i>
                        <h3><?php echo count($friends); ?></h3>
                        <p class="mb-0">Friends</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card">
                        <i class="bi bi-person-plus"></i>
                        <h3><?php echo count($pending_requests); ?></h3>
                        <p class="mb-0">Pending Requests</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card">
                        <i class="bi bi-search"></i>
                        <h3><?php echo count($users); ?></h3>
                        <p class="mb-0">Users Found</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card">
                        <i class="bi bi-activity"></i>
                        <h3><?php echo count($recent_activity); ?></h3>
                        <p class="mb-0">Recent Activities</p>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Search and Users -->
                <div class="col-lg-8" data-aos="fade-up" data-aos-delay="200">
                    <!-- Search Box -->
                    <div class="search-box">
                        <form method="GET" action="" class="d-flex align-items-center">
                            <i class="fas fa-search me-3" style="color: var(--primary-color);"></i>
                            <div class="search-input-container position-relative flex-grow-1">
                                <input type="text" class="form-control" id="searchInput" name="search" 
                                       placeholder="Search by name or username..."
                                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                                <div id="searchSuggestions" class="search-suggestions"></div>
                            </div>
                            <button type="submit" class="btn btn-primary-custom ms-3">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>

                    <!-- Users List -->
                    <div class="card all-users-section">
                        <div class="card-body">
                            <h5 class="card-title" style="color: var(--primary-color);">
                                <i class="fas fa-users me-2"></i>
                                <?php echo empty($search) ? 'All Users' : 'Search Results'; ?>
                                <span class="badge bg-primary ms-2"><?php echo count($users); ?></span>
                            </h5>
                            
                            <?php if (empty($users)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No users found</h5>
                                    <p class="text-muted">Try adjusting your search terms</p>
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($users as $user_item): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="user-card">
                                                <div class="d-flex align-items-center">
                                                    <img src="profile/<?php echo htmlspecialchars($user_item['profile_image'] ?? 'default-avatar.png'); ?>" 
                                                         class="user-avatar me-3" alt="Profile">
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($user_item['first_name'] . ' ' . $user_item['last_name']); ?></h6>
                                                        <p class="mb-1 text-muted small">
                                                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($user_item['username']); ?>
                                                        </p>
                                                        <p class="mb-2 text-muted small">
                                                            <i class="fas fa-graduation-cap me-1"></i><?php echo htmlspecialchars($user_item['programme'] ?? 'Programme not specified'); ?>
                                                        </p>
                                                        
                                                        <?php if ($user_item['relationship_status'] === 'friend'): ?>
                                                            <span class="status-badge status-friend">
                                                                <i class="fas fa-check me-1"></i>Friends
                                                            </span>
                                                            <form method="POST" class="d-inline ms-2">
                                                                <input type="hidden" name="friend_id" value="<?php echo $user_item['user_id']; ?>">
                                                                <button type="submit" name="remove_friend" class="btn btn-sm btn-outline-danger" 
                                                                        onclick="return confirm('Remove this friend?')">
                                                                    <i class="fas fa-user-minus"></i>
                                                                </button>
                                                            </form>
                                                        <?php elseif ($user_item['relationship_status'] === 'request_sent'): ?>
                                                            <span class="status-badge status-pending">
                                                                <i class="fas fa-clock me-1"></i>Request Sent
                                                            </span>
                                                        <?php elseif ($user_item['relationship_status'] === 'request_received'): ?>
                                                            <span class="status-badge status-request">
                                                                <i class="fas fa-bell me-1"></i>Request Received
                                                            </span>
                                                        <?php else: ?>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="friend_id" value="<?php echo $user_item['user_id']; ?>">
                                                                <button type="submit" name="send_request" class="btn btn-sm btn-primary-custom">
                                                                    <i class="fas fa-user-plus me-1"></i>Add Friend
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="300">
                    <!-- Pending Requests -->
                    <?php if (!empty($pending_requests)): ?>
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="card-title" style="color: var(--primary-color);">
                                    <i class="fas fa-bell me-2"></i>Pending Requests
                                    <span class="badge bg-warning ms-2"><?php echo count($pending_requests); ?></span>
                                </h5>
                                
                                <?php foreach ($pending_requests as $request): ?>
                                    <!-- Debug: Show available fields -->
                                    <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
                                        <div class="alert alert-info small">
                                            <strong>Debug Info:</strong><br>
                                            Available fields: <?php echo implode(', ', array_keys($request)); ?><br>
                                            Programme value: <?php echo var_export($request['programme'] ?? 'NULL', true); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="activity-item">
                                        <div class="d-flex align-items-center">
                                            <img src="profile/<?php echo htmlspecialchars($request['profile_image'] ?? 'default-avatar.png'); ?>" 
                                                 class="activity-avatar me-3" alt="Profile">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></h6>
                                                <p class="mb-2 text-muted small">
                                                    <i class="fas fa-graduation-cap me-1"></i>
                                                    <?php 
                                                    // Debug: Check what fields are available
                                                    if (isset($request['programme']) && !empty($request['programme'])) {
                                                        echo htmlspecialchars($request['programme']);
                                                    } else {
                                                        echo 'Programme not specified';
                                                    }
                                                    ?>
                                                </p>
                                                <div class="btn-group btn-group-sm">
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                        <input type="hidden" name="sender_id" value="<?php echo $request['sender_id']; ?>">
                                                        <button type="submit" name="accept_request" class="btn btn-success">
                                                            <i class="fas fa-check"></i> Accept
                                                        </button>
                                                    </form>
                                                    <form method="POST" class="d-inline ms-1">
                                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                        <button type="submit" name="reject_request" class="btn btn-danger">
                                                            <i class="fas fa-times"></i> Reject
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- My Friends -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title" style="color: var(--primary-color);">
                                <i class="fas fa-heart me-2"></i>My Friends
                                <span class="badge bg-success ms-2"><?php echo count($friends); ?></span>
                            </h5>
                            
                            <?php if (empty($friends)): ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-users fa-2x text-muted mb-2"></i>
                                    <p class="text-muted small">No friends yet. Start connecting!</p>
                                </div>
                            <?php else: ?>
                                <?php foreach (array_slice($friends, 0, 5) as $friend): ?>
                                    <div class="activity-item">
                                        <div class="d-flex align-items-center">
                                            <img src="profile/<?php echo htmlspecialchars($friend['profile_image'] ?? 'default-avatar.png'); ?>" 
                                                 class="activity-avatar me-3" alt="Profile">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($friend['first_name'] . ' ' . $friend['last_name']); ?></h6>
                                                <p class="mb-0 text-muted small"><?php echo htmlspecialchars($friend['programme'] ?? 'Programme not specified'); ?></p>
                                            </div>
                                            <a href="private.php?user_id=<?php echo $friend['user_id']; ?>" class="btn btn-sm btn-outline-primary" title="Send Message">
                                                <i class="fas fa-comment"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <?php if (count($friends) > 5): ?>
                                    <div class="text-center mt-3">
                                        <a href="#" class="btn btn-sm btn-primary-custom">
                                            View All <?php echo count($friends); ?> Friends
                                        </a>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title" style="color: var(--primary-color);">
                                <i class="fas fa-activity me-2"></i>Recent Activity
                            </h5>
                            
                            <?php if (empty($recent_activity)): ?>
                                <div class="text-center py-3">
                                    <i class="fas fa-info-circle fa-2x text-muted mb-2"></i>
                                    <p class="text-muted small">No recent activity</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recent_activity as $activity): ?>
                                    <div class="activity-item">
                                        <div class="d-flex align-items-center">
                                            <img src="profile/<?php echo htmlspecialchars($activity['profile_image'] ?? 'default-avatar.png'); ?>" 
                                                 class="activity-avatar me-3" alt="Profile">
                                            <div class="flex-grow-1">
                                                <p class="mb-1">
                                                    <strong><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></strong>
                                                    <?php if ($activity['type'] === 'friend_request'): ?>
                                                        sent you a friend request
                                                    <?php else: ?>
                                                        became your friend
                                                    <?php endif; ?>
                                                </p>
                                                <small class="text-muted"><?php echo timeAgo($activity['created_at']); ?></small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include 'footer.php'; ?>

 <!-- Vendor JS Files -->
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    
    <!-- AOS Animation -->
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>

     <!-- Template Main JS File -->
    <script src="assets/js/main.js"></script>

    <script>
        // Initialize AOS
        AOS.init({
            duration: 1000,
            once: true,
            offset: 100
        });

        // Add hover effects to cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            // Add click effects to user cards
            const userCards = document.querySelectorAll('.user-card');
            userCards.forEach(card => {
                card.addEventListener('click', function(e) {
                    if (!e.target.closest('button') && !e.target.closest('form')) {
                        this.style.transform = 'scale(0.98)';
                        setTimeout(() => {
                            this.style.transform = 'scale(1)';
                        }, 150);
                    }
                });
            });

            // Search Autocomplete Functionality
            const searchInput = document.getElementById('searchInput');
            const searchSuggestions = document.getElementById('searchSuggestions');
            let searchTimeout;
            let selectedIndex = -1;
            let suggestions = [];

            // Handle search input
            searchInput.addEventListener('input', function() {
                const query = this.value.trim();
                
                // Clear previous timeout
                clearTimeout(searchTimeout);
                
                // Hide suggestions if query is too short
                if (query.length < 2) {
                    hideSuggestions();
                    return;
                }
                
                // Debounce search requests
                searchTimeout = setTimeout(() => {
                    fetchSuggestions(query);
                }, 300);
            });

            // Handle keyboard navigation
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    selectNext();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    selectPrevious();
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (selectedIndex >= 0 && suggestions[selectedIndex]) {
                        selectSuggestion(suggestions[selectedIndex]);
                    } else {
                        // Submit the form if no suggestion is selected
                        this.closest('form').submit();
                    }
                } else if (e.key === 'Escape') {
                    hideSuggestions();
                    this.blur();
                }
            });

            // Handle focus and blur
            searchInput.addEventListener('focus', function() {
                if (this.value.trim().length >= 2) {
                    fetchSuggestions(this.value.trim());
                }
            });

            // Hide suggestions when clicking outside
            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !searchSuggestions.contains(e.target)) {
                    hideSuggestions();
                }
            });

            // Fetch search suggestions
            function fetchSuggestions(query) {
                fetch(`search_suggestions.php?q=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.suggestions && data.suggestions.length > 0) {
                            suggestions = data.suggestions;
                            showSuggestions(data.suggestions);
                        } else {
                            hideSuggestions();
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching suggestions:', error);
                        hideSuggestions();
                    });
            }

            // Show suggestions dropdown
            function showSuggestions(suggestions) {
                searchSuggestions.innerHTML = '';
                selectedIndex = -1;
                
                suggestions.forEach((suggestion, index) => {
                    const item = document.createElement('div');
                    item.className = 'search-suggestion-item';
                    item.innerHTML = `
                        <img src="profile/${suggestion.profile_image}" alt="Profile" class="search-suggestion-avatar">
                        <div class="search-suggestion-content">
                            <div class="search-suggestion-name">${suggestion.name}</div>
                            <div class="search-suggestion-details">@${suggestion.username}</div>
                        </div>
                    `;
                    
                    item.addEventListener('click', () => selectSuggestion(suggestion));
                    item.addEventListener('mouseenter', () => {
                        selectedIndex = index;
                        updateSelection();
                    });
                    
                    searchSuggestions.appendChild(item);
                });
                
                searchSuggestions.style.display = 'block';
            }

            // Hide suggestions dropdown
            function hideSuggestions() {
                searchSuggestions.style.display = 'none';
                selectedIndex = -1;
                suggestions = [];
            }

            // Select next suggestion
            function selectNext() {
                if (suggestions.length === 0) return;
                selectedIndex = (selectedIndex + 1) % suggestions.length;
                updateSelection();
            }

            // Select previous suggestion
            function selectPrevious() {
                if (suggestions.length === 0) return;
                selectedIndex = selectedIndex <= 0 ? suggestions.length - 1 : selectedIndex - 1;
                updateSelection();
            }

            // Update visual selection
            function updateSelection() {
                const items = searchSuggestions.querySelectorAll('.search-suggestion-item');
                items.forEach((item, index) => {
                    item.classList.toggle('selected', index === selectedIndex);
                });
            }

            // Select a suggestion
            function selectSuggestion(suggestion) {
                searchInput.value = suggestion.name;
                hideSuggestions();
                
                // Optionally, you can redirect to the user's profile or perform other actions
                // window.location.href = `profile.php?user_id=${suggestion.id}`;
            }
        });
    </script>
</body>
</html>