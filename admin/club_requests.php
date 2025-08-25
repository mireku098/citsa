<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

// Include database connection and helpers
include '../app/db.conn.php';
include '../app/helpers/club_management.php';

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'approve_request':
            $request_id = $_POST['request_id'];
            
            if (approveClubRequest($request_id, $pdo)) {
                echo json_encode(['success' => true, 'message' => 'Request approved successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to approve request']);
            }
            exit();
            break;
            
        case 'reject_request':
            $request_id = $_POST['request_id'];
            $rejection_reason = $_POST['rejection_reason'] ?? 'Request rejected by admin';
            
            if (rejectClubRequest($request_id, $rejection_reason, $pdo)) {
                echo json_encode(['success' => true, 'message' => 'Request rejected successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to reject request']);
            }
            exit();
            break;
            
        case 'get_pending_requests':
            $pending_requests = getPendingClubRequests($pdo);
            echo json_encode(['success' => true, 'requests' => $pending_requests]);
            exit();
            break;
    }
}

// Get pending requests for display
$pending_requests = getPendingClubRequests($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club Join Requests - CITSA Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1a3c6d;
            --secondary-color: #15325d;
            --accent-color: #2d5a9e;
        }

        body {
            background-color: #f8fafc;
        }

        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            width: 280px;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
        }

        .sidebar .nav-link {
            color: rgba(255,255,255,0.9);
            border-radius: 12px;
            margin: 4px 0;
            padding: 12px 20px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.15);
        }

        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .main-content {
            margin-left: 280px;
            padding: 30px;
        }

        .card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(26, 60, 109, 0.1);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            border-radius: 20px 20px 0 0 !important;
            border: none;
            padding: 20px 25px;
        }

        .table th {
            background: #f8fafc;
            border: none;
            font-weight: 600;
            color: var(--primary-color);
            padding: 18px 15px;
        }

        .table td {
            vertical-align: middle;
            padding: 15px;
            border-color: #e9ecef;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(26, 60, 109, 0.3);
            color: white;
        }

        .request-card {
            border: 1px solid #e9ecef;
            border-radius: 15px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .request-card:hover {
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .status-badge {
            font-size: 0.8rem;
            padding: 5px 12px;
            border-radius: 20px;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }

        .stats-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(26, 60, 109, 0.1);
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(26, 60, 109, 0.15);
        }

        .stats-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .bg-gradient-warning {
            background: linear-gradient(135deg, #ffc107, #ff8f00);
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="position-sticky pt-3">
                <div class="text-center mb-4">
                    <img src="../assets/img/logo.png" alt="CITSA Logo" height="60" class="mb-2">
                    <h5 class="text-white">CITSA Admin</h5>
                    <small class="text-white-50">Computer Science & IT</small>
                </div>
                
                <ul class="nav flex-column px-3">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="bi bi-speedometer2"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
                            <i class="bi bi-people"></i>
                            Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="chat_rooms.php">
                            <i class="bi bi-chat-dots"></i>
                            Chat Rooms
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="clubs.php">
                            <i class="bi bi-collection"></i>
                            Clubs
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="club_requests.php">
                            <i class="bi bi-clock-history"></i>
                            Club Requests
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="events.php">
                            <i class="bi bi-calendar-event"></i>
                            Events
                        </a>
                    </li>
                                            <li class="nav-item">
                            <a class="nav-link" href="club_members.php">
                                <i class="bi bi-people-fill"></i>
                                Club Members
                            </a>
                        </li>
                    <li class="nav-item">
                        <a class="nav-link" href="administrators.php">
                            <i class="bi bi-person-badge"></i>
                            Administrators
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="platform.php">
                            <i class="bi bi-gear-wide-connected"></i>
                            Platform
                        </a>
                    </li>
                    <li class="nav-item mt-4">
                        <a class="nav-link text-danger" href="logout.php">
                            <i class="bi bi-box-arrow-right"></i>
                            Logout
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main content -->
        <main class="main-content">
            <!-- Page Title -->
            <div class="mb-4">
                <h1 class="h2">Club Join Requests Management</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Admin</a></li>
                        <li class="breadcrumb-item active">Club Requests</li>
                    </ol>
                </nav>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card stats-card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="stats-icon bg-gradient-warning me-3">
                                    <i class="bi bi-clock"></i>
                                </div>
                                <div>
                                    <h6 class="card-title text-muted mb-1">Pending Requests</h6>
                                    <h3 class="mb-0"><?php echo count($pending_requests); ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Club Requests -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-clock-history me-2"></i>
                        Pending Club Join Requests
                    </h5>
                    <button class="btn btn-primary-custom" onclick="refreshRequests()">
                        <i class="bi bi-arrow-clockwise me-2"></i>Refresh
                    </button>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-check-circle display-4"></i>
                            <h5 class="mt-3">No Pending Requests</h5>
                            <p>All club join requests have been processed.</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($pending_requests as $request): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="request-card p-3">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($request['club_name']); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($request['club_description']); ?></small>
                                            </div>
                                            <span class="status-badge status-pending">Pending</span>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <strong>Student:</strong> <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?><br>
                                            <strong>Student ID:</strong> <?php echo htmlspecialchars($request['student_id']); ?><br>
                                            <strong>Username:</strong> @<?php echo htmlspecialchars($request['username']); ?><br>
                                            <strong>Requested:</strong> <?php echo date('M d, Y H:i', strtotime($request['requested_at'])); ?>
                                        </div>
                                        
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-success btn-sm flex-fill" 
                                                    onclick="approveRequest(<?php echo $request['id']; ?>)">
                                                <i class="bi bi-check me-1"></i>Approve
                                            </button>
                                            <button class="btn btn-danger btn-sm flex-fill" 
                                                    onclick="rejectRequest(<?php echo $request['id']; ?>)">
                                                <i class="bi bi-x me-1"></i>Reject
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Bootstrap JS -->
    <script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function approveRequest(requestId) {
            if (confirm('Are you sure you want to approve this club join request?')) {
                fetch('club_requests.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=approve_request&request_id=${requestId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Success', data.message, 'success').then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', data.error, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Error', 'An error occurred while processing the request', 'error');
                });
            }
        }

        function rejectRequest(requestId) {
            const rejectionReason = prompt('Please provide a reason for rejection (optional):');
            
            fetch('club_requests.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=reject_request&request_id=${requestId}&rejection_reason=${encodeURIComponent(rejectionReason || 'Request rejected by admin')}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Success', data.message, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', data.error, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error', 'An error occurred while processing the request', 'error');
            });
        }

        function refreshRequests() {
            location.reload();
        }
    </script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>
