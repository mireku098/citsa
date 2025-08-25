<?php
session_start();
require_once '../app/db.conn.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Get admin info
$admin_id = $_SESSION['admin_id'];
$stmt = $pdo->prepare("SELECT * FROM admins WHERE admin_id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

// Get users with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

        // Get total approved club members count (active students only)
        $stmt = $pdo->query("
            SELECT COUNT(DISTINCT uc.user_id) as total 
            FROM user_clubs uc 
            JOIN users u ON uc.user_id = u.user_id 
            WHERE uc.status = 'approved' AND u.user_type = 'student'
        ");
        $total_club_members = $stmt->fetch()['total'];
        $total_pages = ceil($total_club_members / $limit);
        
        // Get approved club members with their details and club info (active students only)
        $stmt = $pdo->prepare("
            SELECT DISTINCT 
                u.user_id, u.username, u.first_name, u.last_name, u.email, u.programme, u.user_type,
                COUNT(uc.id) as total_clubs,
                GROUP_CONCAT(DISTINCT CONCAT(c.name, ' (#', c.id, ')') SEPARATOR ', ') as club_names,
                MAX(uc.status) as latest_status
            FROM users u 
            INNER JOIN user_clubs uc ON u.user_id = uc.user_id 
            INNER JOIN clubs c ON uc.club_id = c.id
            WHERE uc.status = 'approved' AND u.user_type = 'student'
            GROUP BY u.user_id
            ORDER BY u.created_at DESC 
            LIMIT ? OFFSET ?
        ");
$stmt->bindParam(1, $limit, PDO::PARAM_INT);
$stmt->bindParam(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$club_members = $stmt->fetchAll();

        // Get statistics for approved members only
        $stmt = $pdo->query("
            SELECT COUNT(DISTINCT uc.user_id) as total 
            FROM user_clubs uc 
            JOIN users u ON uc.user_id = u.user_id 
            WHERE uc.status = 'approved' AND u.user_type = 'student'
        ");
        $approved_members = $stmt->fetch()['total'];
        
        // Get count of active students with no club memberships
        $stmt = $pdo->query("
            SELECT COUNT(*) as total FROM users u 
            WHERE u.user_type = 'student' 
            AND NOT EXISTS (SELECT 1 FROM user_clubs uc WHERE uc.user_id = u.user_id AND uc.status = 'approved')
        ");
        $no_clubs = $stmt->fetch()['total'];
        
        // Set other stats to 0 since we only show approved members
        $pending_members = 0;
        $rejected_members = 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club Members Management - CITSA Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
    <!-- Admin Styles CSS -->
    <link href="includes/admin-styles.css" rel="stylesheet">
    
    <style>



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

        .table tbody tr:hover {
            background-color: rgba(26, 60, 109, 0.05);
        }

        .badge-online {
            background: linear-gradient(135deg, #059669, #10b981);
            color: white;
        }

        .badge-offline {
            background: linear-gradient(135deg, #6b7280, #9ca3af);
            color: white;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            border: none;
            color: white;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(26, 60, 109, 0.3);
            color: white;
        }

        .stats-card {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }

        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-no-clubs {
            background-color: #e2e3e5;
            color: #383d41;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
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
                            <a class="nav-link" href="club_requests.php">
                                <i class="bi bi-clock-history"></i>
                                Club Requests
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
                            <a class="nav-link" href="events.php">
                                <i class="bi bi-calendar-event"></i>
                                Events
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

            <!-- Top Navbar -->
            <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top">
                <div class="container-fluid">
                    <!-- Brand -->
                    <a class="navbar-brand fw-bold" href="index.php">CITSA Admin</a>
                    
                    <!-- Sidebar Toggle Button -->
                    <button class="btn btn-outline-primary me-2" id="sidebarToggle">
                        <i class="bi bi-list"></i>
                    </button>

                    <!-- Right Side (Admin Info / Profile) -->
                    <ul class="nav navbar-nav ms-auto">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="adminMenu" role="button" data-bs-toggle="dropdown">
                                <img src="../profile/<?php echo $admin['profile_image'] ?? 'default-avatar.png'; ?>" 
                                     class="rounded-circle me-2" width="32" height="32" alt="Admin">
                                <span><?php echo htmlspecialchars($admin['name'] ?? $admin['username'] ?? $admin['email'] ?? 'Admin'); ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person"></i> Profile</a></li>
                                <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear"></i> Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="main-content">
                <!-- Page Title -->
                <div class="mb-4">
                    <h1 class="h2">Club Members Management</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Admin</a></li>
                            <li class="breadcrumb-item active">Club Members</li>
                        </ol>
                    </nav>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="stats-card">
                            <div class="d-flex align-items-center">
                                <div class="stats-icon bg-gradient-primary me-3">
                                    <i class="bi bi-people-fill"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0"><?php echo $total_club_members; ?></h6>
                                    <small class="text-muted">Approved Members</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="stats-card">
                            <div class="d-flex align-items-center">
                                <div class="stats-icon bg-gradient-info me-3">
                                    <i class="bi bi-person-x"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0"><?php echo $no_clubs; ?></h6>
                                    <small class="text-muted">Students Without Clubs</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Club Members Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-people-fill me-2"></i>
                            Club Members List
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Programme</th>
                                        <th>Club Memberships</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($club_members as $member): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="../profile/<?php echo $member['profile_image'] ?? 'default-avatar.png'; ?>" class="rounded-circle me-3" width="40" height="40" alt="User">
                                                <div>
                                                                                                    <div class="fw-bold"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></div>
                                                <small class="text-muted">@<?php echo htmlspecialchars($member['username']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($member['programme'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php if ($member['total_clubs'] > 0): ?>
                                                <div>
                                                    <strong><?php echo $member['total_clubs']; ?></strong> club(s)
                                                    <?php if ($member['club_names']): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($member['club_names']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">No clubs</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewMemberDetails(<?php echo $member['user_id']; ?>)">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeMember(<?php echo $member['user_id']; ?>)">
                                                    <i class="bi bi-person-x"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <nav aria-label="Club members pagination" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                                </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function viewMemberDetails(userId) {
            // Open modal or redirect to detailed view
            alert('Member details view will be implemented. User ID: ' + userId);
        }

        function removeMember(userId) {
            if (confirm('Are you sure you want to remove this member from all clubs? This action cannot be undone.')) {
                // Create a form to submit the remove request
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'remove_member.php';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'user_id';
                input.value = userId;
                
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Sidebar Toggle Functionality
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        const topNavbar = document.querySelector('.navbar');
        const sidebarToggle = document.getElementById('sidebarToggle');

        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
            if (sidebar.classList.contains('active')) {
                // Sidebar hidden
                sidebar.style.transform = 'translateX(-100%)';
                mainContent.style.marginLeft = '0';
                topNavbar.style.marginLeft = '0';
            } else {
                // Sidebar shown
                sidebar.style.transform = 'translateX(0)';
                mainContent.style.marginLeft = '280px';
                topNavbar.style.marginLeft = '280px';
            }
        });
    </script>
</body>
</html>
