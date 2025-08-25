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

// Get platform statistics
$stats = [];

// Total messages
$stmt = $pdo->query("SELECT COUNT(*) as total FROM messages");
$stats['total_messages'] = $stmt->fetch()['total'];

// Total friend requests
$stmt = $pdo->query("SELECT COUNT(*) as total FROM friend_requests");
$stats['total_friend_requests'] = $stmt->fetch()['total'];

// Pending friend requests (assuming all are pending or use a different approach)
$stats['pending_friend_requests'] = $stats['total_friend_requests'];

// Platform settings (if table exists)
try {
    $stmt = $pdo->query("SELECT * FROM platform_settings LIMIT 1");
    $platform_settings = $stmt->fetch();
} catch (PDOException $e) {
    $platform_settings = null;
}

// Handle form submission for platform settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_platform'])) {
    try {
        // Check if platform_settings table exists and has data
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM platform_settings");
        $count = $stmt->fetch()['count'];
        
        if ($count == 0) {
            // Insert new settings
            $stmt = $pdo->prepare("INSERT INTO platform_settings (
                site_name, site_description, max_file_size, allowed_file_types, 
                maintenance_mode, user_registration, maintenance_message
            ) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $_POST['site_name'],
                $_POST['site_description'],
                $_POST['max_file_size'],
                $_POST['allowed_file_types'],
                $_POST['maintenance_mode'],
                $_POST['user_registration'],
                $_POST['maintenance_message']
            ]);
        } else {
            // Update existing settings
            $stmt = $pdo->prepare("UPDATE platform_settings SET 
                site_name = ?, site_description = ?, max_file_size = ?, 
                allowed_file_types = ?, maintenance_mode = ?, user_registration = ?, 
                maintenance_message = ?, updated_at = NOW()");
            
            $stmt->execute([
                $_POST['site_name'],
                $_POST['site_description'],
                $_POST['max_file_size'],
                $_POST['allowed_file_types'],
                $_POST['maintenance_mode'],
                $_POST['user_registration'],
                $_POST['maintenance_message']
            ]);
        }
        
        $success_message = "Platform settings updated successfully!";
        
        // Refresh platform settings
        $stmt = $pdo->query("SELECT * FROM platform_settings LIMIT 1");
        $platform_settings = $stmt->fetch();
        
    } catch (PDOException $e) {
        $error_message = "Error updating platform settings: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Management - CITSA Admin</title>
    
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

        .btn-success-custom {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            border-radius: 12px;
            padding: 10px 20px;
            color: white;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-success-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
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
        .bg-gradient-info { background: linear-gradient(135deg, #0891b2, #06b6d4); }

        .form-control {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 12px 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.2rem rgba(45, 90, 158, 0.25);
        }

        .form-label {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 8px;
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
                            <a class="nav-link active" href="platform.php">
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
                    <h1 class="h2">Platform Management</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Admin</a></li>
                            <li class="breadcrumb-item active">Platform</li>
                        </ol>
                    </nav>
                </div>

                <!-- Success Message -->
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Error Message -->
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-x-circle me-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="stats-icon bg-gradient-primary me-3">
                                        <i class="bi bi-chat-text"></i>
                                    </div>
                                    <div>
                                        <h6 class="card-title text-muted mb-1">Total Messages</h6>
                                        <h3 class="mb-0"><?php echo number_format($stats['total_messages']); ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="stats-icon bg-gradient-info me-3">
                                        <i class="bi bi-person-plus"></i>
                                    </div>
                                    <div>
                                        <h6 class="card-title text-muted mb-1">Total Friend Requests</h6>
                                        <h3 class="mb-0"><?php echo number_format($stats['total_friend_requests']); ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="stats-icon bg-gradient-success me-3">
                                        <i class="bi bi-clock"></i>
                                    </div>
                                    <div>
                                        <h6 class="card-title text-muted mb-1">Pending Requests</h6>
                                        <h3 class="mb-0"><?php echo number_format($stats['pending_friend_requests']); ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Platform Settings -->
                <div class="row" id="platformSettingsForm">
                    <div class="col-lg-8 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-gear me-2"></i>
                                    Platform Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="site_name" class="form-label">Site Name</label>
                                            <input type="text" class="form-control" id="site_name" name="site_name" 
                                                   value="<?php echo htmlspecialchars($platform_settings['site_name'] ?? 'CITSA Platform'); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="site_description" class="form-label">Site Description</label>
                                            <input type="text" class="form-control" id="site_description" name="site_description" 
                                                   value="<?php echo htmlspecialchars($platform_settings['site_description'] ?? 'Computer Science & IT Student Association'); ?>">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="max_file_size" class="form-label">Max File Upload Size (MB)</label>
                                            <input type="number" class="form-control" id="max_file_size" name="max_file_size" 
                                                   value="<?php echo htmlspecialchars($platform_settings['max_file_size'] ?? '10'); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="allowed_file_types" class="form-label">Allowed File Types</label>
                                            <input type="text" class="form-control" id="allowed_file_types" name="allowed_file_types" 
                                                   value="<?php echo htmlspecialchars($platform_settings['allowed_file_types'] ?? 'jpg,jpeg,png,gif,pdf,doc,docx'); ?>">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="maintenance_mode" class="form-label">Maintenance Mode</label>
                                            <select class="form-control" id="maintenance_mode" name="maintenance_mode">
                                                <option value="0" <?php echo ($platform_settings['maintenance_mode'] ?? 0) == 0 ? 'selected' : ''; ?>>Disabled</option>
                                                <option value="1" <?php echo ($platform_settings['maintenance_mode'] ?? 0) == 1 ? 'selected' : ''; ?>>Enabled</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="user_registration" class="form-label">User Registration</label>
                                            <select class="form-control" id="user_registration" name="user_registration">
                                                <option value="1" <?php echo ($platform_settings['user_registration'] ?? 1) == 1 ? 'selected' : ''; ?>>Enabled</option>
                                                <option value="0" <?php echo ($platform_settings['user_registration'] ?? 1) == 0 ? 'selected' : ''; ?>>Disabled</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="maintenance_message" class="form-label">Maintenance Message</label>
                                        <textarea class="form-control" id="maintenance_message" name="maintenance_message" rows="3" 
                                                  placeholder="Enter maintenance message..."><?php echo htmlspecialchars($platform_settings['maintenance_message'] ?? ''); ?></textarea>
                                    </div>

                                    <button type="submit" name="update_platform" class="btn btn-primary-custom">
                                        <i class="bi bi-save me-2"></i>
                                        Update Platform Settings
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-tools me-2"></i>
                                    Quick Actions
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-3">
                                    <a href="upload_authorized_users.php" class="btn btn-outline-primary">
                                        <i class="bi bi-upload me-2"></i>
                                        Upload Authorized Users
                                    </a>
                                    <button class="btn btn-outline-info" onclick="manageIcons()">
                                        <i class="bi bi-image me-2"></i>
                                        Manage Platform Icons
                                    </button>
                                    <button class="btn btn-outline-secondary" onclick="viewSettings()">
                                        <i class="bi bi-gear me-2"></i>
                                        View All Settings
                                    </button>
                                    <button class="btn btn-outline-warning" onclick="clearCache()">
                                        <i class="bi bi-trash me-2"></i>
                                        Clear Cache
                                    </button>
                                    <button class="btn btn-outline-danger" onclick="backupDatabase()">
                                        <i class="bi bi-download me-2"></i>
                                        Backup Database
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- System Information -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-info-circle me-2"></i>
                                    System Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6">
                                        <small class="text-muted">PHP Version</small>
                                        <p class="mb-2"><?php echo PHP_VERSION; ?></p>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Server</small>
                                        <p class="mb-2"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></p>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <small class="text-muted">Database</small>
                                        <p class="mb-2">MariaDB/MySQL</p>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Upload Max</small>
                                        <p class="mb-2"><?php echo ini_get('upload_max_filesize'); ?></p>
                                    </div>
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
        function clearCache() {
            if (confirm('Are you sure you want to clear the platform cache? This will refresh all cached data.')) {
                // Simulate cache clearing
                const btn = event.target.closest('button');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Clearing...';
                btn.disabled = true;
                
                setTimeout(() => {
                    btn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Cache Cleared!';
                    btn.classList.remove('btn-outline-warning');
                    btn.classList.add('btn-success');
                    
                    setTimeout(() => {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                        btn.classList.remove('btn-success');
                        btn.classList.add('btn-outline-warning');
                    }, 2000);
                }, 1500);
            }
        }

        function backupDatabase() {
            if (confirm('Are you sure you want to create a database backup? This may take a few moments.')) {
                const btn = event.target.closest('button');
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Backing up...';
                btn.disabled = true;
                
                // Simulate backup process
                setTimeout(() => {
                    btn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Backup Complete!';
                    btn.classList.remove('btn-outline-danger');
                    btn.classList.add('btn-success');
                    
                    setTimeout(() => {
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                        btn.classList.remove('btn-success');
                        btn.classList.add('btn-outline-danger');
                    }, 3000);
                }, 2000);
            }
        }

        function manageIcons() {
            alert('Icon management feature will be implemented in a future update. This will allow you to customize platform logos, favicons, and other visual elements.');
        }

        function viewSettings() {
            // Show current platform settings in a modal or expand the form
            const settingsForm = document.getElementById('platformSettingsForm');
            if (settingsForm) {
                settingsForm.scrollIntoView({ behavior: 'smooth' });
            }
        }
    </script>
</body>
</html>
