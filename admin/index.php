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

// Get statistics
$stats = [];

// Total users
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
$stats['total_users'] = $stmt->fetch()['total'];

// Total chat rooms
$stmt = $pdo->query("SELECT COUNT(*) as total FROM chat_rooms");
$stats['total_chat_rooms'] = $stmt->fetch()['total'];

// Total clubs
$stmt = $pdo->query("SELECT COUNT(*) as total FROM clubs");
$stats['total_clubs'] = $stmt->fetch()['total'];

// Total messages
$stmt = $pdo->query("SELECT COUNT(*) as total FROM messages");
$stats['total_messages'] = $stmt->fetch()['total'];

// Online users
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE online_status = 'online'");
$stats['online_users'] = $stmt->fetch()['total'];

// Recent users
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
$recent_users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CITSA Admin Dashboard</title>
    
    <!-- Bootstrap CSS -->
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
    <!-- Admin Styles CSS -->
    <link href="includes/admin-styles.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
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
                            <a class="nav-link active" href="index.php">
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
                    <h1 class="h2">Dashboard Overview</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="#">Admin</a></li>
                            <li class="breadcrumb-item active">Dashboard</li>
                        </ol>
                    </nav>
                </div>

                        <!-- Statistics Cards -->
                        <div class="row mb-4">
                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="card card-stats">
                                    <div class="card-body">
                                                <div class="stats-icon bg-gradient-primary mx-auto mb-3">
                                                    <i class="bi bi-people"></i>
                                                </div>
                                                <h5 class="card-title text-uppercase text-muted mb-0">Total Users</h5>
                                <span class="h2 font-weight-bold mb-0"><?php echo number_format($stats['total_users']); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="card card-stats">
                                    <div class="card-body">
                                                <div class="stats-icon bg-gradient-success mx-auto mb-3">
                                    <i class="bi bi-circle-fill"></i>
                                                </div>
                                <h5 class="card-title text-uppercase text-muted mb-0">Online Users</h5>
                                <span class="h2 font-weight-bold mb-0"><?php echo number_format($stats['online_users']); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="card card-stats">
                                    <div class="card-body">
                                                <div class="stats-icon bg-gradient-warning mx-auto mb-3">
                                    <i class="bi bi-chat-dots"></i>
                                                </div>
                                <h5 class="card-title text-uppercase text-muted mb-0">Chat Rooms</h5>
                                <span class="h2 font-weight-bold mb-0"><?php echo number_format($stats['total_chat_rooms']); ?></span>
                                    </div>
                                </div>
                            </div>

                                                         <div class="col-xl-3 col-md-6 mb-4">
                                 <div class="card card-stats">
                                     <div class="card-body">
                                                 <div class="stats-icon bg-gradient-info mx-auto mb-3">
                                    <i class="bi bi-collection"></i>
                                                 </div>
                                <h5 class="card-title text-uppercase text-muted mb-0">Clubs</h5>
                                <span class="h2 font-weight-bold mb-0"><?php echo number_format($stats['total_clubs']); ?></span>
                                     </div>
                                 </div>
                             </div>
                         </div>

                <!-- Charts Row -->
                         <div class="row mb-4">
                    <div class="col-lg-8 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-graph-up me-2"></i>
                                    User Activity (Last 7 Days)
                                </h5>
                            </div>
                                     <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="userActivityChart"></canvas>
                                         </div>
                                     </div>
                                 </div>
                             </div>

                    <div class="col-lg-4 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-pie-chart me-2"></i>
                                    Online Status Distribution
                                </h5>
                            </div>
                                     <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="onlineStatusChart"></canvas>
                                     </div>
                                     </div>
                                 </div>
                             </div>
                         </div>

                <!-- Quick Actions -->
                        <div class="row">
                    <div class="col-lg-8 mb-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">
                                            <i class="bi bi-clock-history me-2"></i>
                                            Recent Users
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                    <table class="table">
                                                <thead>
                                                    <tr>
                                                        <th>User</th>
                                                        <th>Programme</th>
                                                        <th>Status</th>
                                                        <th>Joined</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($recent_users as $user): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                        <img src="../profile/<?php echo $user['profile_image'] ?? 'default-avatar.png'; ?>" class="rounded-circle me-2" width="32" height="32" alt="User">
                                                                <div>
                                                                    <div class="fw-bold"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                                                                    <small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($user['programme']); ?></td>
                                                        <td>
                                                    <span class="badge bg-<?php echo $user['online_status'] === 'online' ? 'success' : 'secondary'; ?>">
                                                                <?php echo ucfirst($user['online_status']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                    <div class="col-lg-4 mb-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">
                                    <i class="bi bi-lightning me-2"></i>
                                    Quick Actions
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="users.php" class="btn btn-primary">
                                        <i class="bi bi-people me-2"></i>Manage Users
                                    </a>
                                    <a href="chat_rooms.php" class="btn btn-success">
                                        <i class="bi bi-chat-dots me-2"></i>Manage Chat Rooms
                                    </a>
                                    <a href="clubs.php" class="btn btn-warning">
                                        <i class="bi bi-collection me-2"></i>Manage Clubs
                                    </a>
                                    <a href="events.php" class="btn btn-info">
                                        <i class="bi bi-calendar-event me-2"></i>Manage Events
                                    </a>
                                    <a href="club_requests.php" class="btn btn-secondary">
                                        <i class="bi bi-clock-history me-2"></i>Club Requests
                                    </a>
                                    <a href="platform.php" class="btn btn-dark">
                                        <i class="bi bi-gear me-2"></i>Platform Settings
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
         </div>
     </div>

     <!-- Bootstrap JS -->
    <script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // User Activity Chart
        const userActivityCtx = document.getElementById('userActivityChart').getContext('2d');
        new Chart(userActivityCtx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Active Users',
                    data: [65, 59, 80, 81, 56, 55, 40],
                    borderColor: '#1a3c6d',
                    backgroundColor: 'rgba(26, 60, 109, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Online Status Chart
        const onlineStatusCtx = document.getElementById('onlineStatusChart').getContext('2d');
        new Chart(onlineStatusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Online', 'Offline'],
                datasets: [{
                    data: [<?php echo $stats['online_users']; ?>, <?php echo $stats['total_users'] - $stats['online_users']; ?>],
                    backgroundColor: ['#10b981', '#6b7280'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

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
