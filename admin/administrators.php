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

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$stmt = $pdo->query("SELECT COUNT(*) as total FROM admins");
$total_administrators = $stmt->fetch()['total'];
$total_pages = ceil($total_administrators / $limit);

$stmt = $pdo->prepare("SELECT * FROM admins ORDER BY admin_id DESC LIMIT ? OFFSET ?");
$stmt->bindParam(1, $limit, PDO::PARAM_INT);
$stmt->bindParam(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$administrators = $stmt->fetchAll();

// Get active administrators count (all administrators are considered active)
$active_administrators = $total_administrators;

// Get super admins count (with error handling)
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM admins WHERE role = 'super_admin'");
    $super_admins = $stmt->fetch()['total'];
} catch (PDOException $e) {
    // If role column doesn't exist, assume no super admins
    $super_admins = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrators Management - CITSA Admin</title>
    
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
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.15);
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

        .table tbody tr:hover {
            background-color: rgba(26, 60, 109, 0.05);
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            border: none;
            border-radius: 12px;
            padding: 10px 20px;
            color: white;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(26, 60, 109, 0.3);
            color: white;
        }

        .btn-danger-custom {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border: none;
            border-radius: 12px;
            padding: 10px 20px;
            color: white;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-danger-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.3);
            color: white;
        }

        .btn-warning-custom {
            background: linear-gradient(135deg, #ffc107, #fd7e14);
            border: none;
            border-radius: 12px;
            padding: 10px 20px;
            color: white;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-warning-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 193, 7, 0.3);
            color: white;
        }

        .stats-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(26, 60, 109, 0.1);
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-8px);
        }

        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .bg-gradient-primary { background: linear-gradient(135deg, var(--primary-color), var(--accent-color)); }
        .bg-gradient-success { background: linear-gradient(135deg, #059669, #10b981); }
        .bg-gradient-warning { background: linear-gradient(135deg, #d97706, #f59e0b); }

        .pagination .page-link {
            border: none;
            color: var(--primary-color);
            border-radius: 12px;
            margin: 0 5px;
            padding: 12px 18px;
            transition: all 0.3s ease;
        }

        .pagination .page-link:hover,
        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            transform: translateY(-2px);
        }

        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
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
                            <a class="nav-link active" href="administrators.php">
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

            <!-- Main content -->
            <main class="main-content">
                <!-- Page Title -->
                <div class="mb-4">
                    <h1 class="h2">Administrators Management</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Admin</a></li>
                            <li class="breadcrumb-item active">Administrators</li>
                        </ol>
                    </nav>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-6 col-md-6 mb-4">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="stats-icon bg-gradient-primary me-3">
                                        <i class="bi bi-shield-check"></i>
                                    </div>
                                    <div>
                                        <h6 class="card-title text-muted mb-1">Total Administrators</h6>
                                        <h3 class="mb-0"><?php echo number_format($total_administrators); ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-6 col-md-6 mb-4">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="stats-icon bg-gradient-success me-3">
                                        <i class="bi bi-check-circle"></i>
                                    </div>
                                    <div>
                                        <h6 class="card-title text-muted mb-1">Active Administrators</h6>
                                        <h3 class="mb-0"><?php echo number_format($active_administrators); ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Administrators Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-person-badge me-2"></i>
                            Administrators List
                        </h5>
                        <a href="add_administrator.php" class="btn btn-primary-custom">
                            <i class="bi bi-plus-circle me-2"></i>
                            Add New Admin
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($administrators)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">
                                                <i class="bi bi-inbox display-4"></i>
                                                <p class="mt-2">No administrators found</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($administrators as $admin): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($admin['admin_id'] ?? $admin['id'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($admin['username'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($admin['email'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="edit_administrator.php?id=<?php echo $admin['admin_id'] ?? $admin['id'] ?? ''; ?>" 
                                                           class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <?php if (($admin['admin_id'] ?? $admin['id'] ?? '') != $_SESSION['admin_id']): ?>
                                                            <button type="button" 
                                                                    class="btn btn-sm btn-outline-danger"
                                                                    onclick="deleteAdministrator(<?php echo $admin['admin_id'] ?? $admin['id'] ?? ''; ?>)">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Administrators pagination" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                                                <i class="bi bi-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>">
                                                <i class="bi bi-chevron-right"></i>
                                            </a>
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
        function deleteAdministrator(adminId) {
            if (confirm('Are you sure you want to delete this administrator? This action cannot be undone.')) {
                // Redirect to delete script
                window.location.href = 'delete_administrator.php?id=' + adminId;
            }
        }
    </script>
</body>
</html>
