<?php
session_start();
require_once '../app/db.conn.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$message = '';
$message_type = '';
$event = null;

// Get event ID from URL
$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($event_id <= 0) {
    header("Location: events.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $event_date = $_POST['event_date'];
        $event_time = $_POST['event_time'];
        $location = trim($_POST['location']);
        $max_participants = (int)$_POST['max_participants'];
        
        // Basic validation
        if (empty($title)) {
            throw new Exception("Event title is required.");
        }
        
        if (empty($event_date)) {
            throw new Exception("Event date is required.");
        }
        
        // Update event
        $stmt = $pdo->prepare("UPDATE events SET 
            title = ?, description = ?, event_date = ?, event_time = ?, 
            location = ?, max_participants = ?, updated_at = NOW() 
            WHERE event_id = ?");
        
        $stmt->execute([
            $title, $description, $event_date, $event_time, 
            $location, $max_participants, $event_id
        ]);
        
        $message = "Event updated successfully!";
        $message_type = 'success';
        
        // Refresh event data
        $stmt = $pdo->prepare("SELECT * FROM events WHERE event_id = ?");
        $stmt->execute([$event_id]);
        $event = $stmt->fetch();
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
    }
}

// Get event data if not already loaded
if (!$event) {
    $stmt = $pdo->prepare("SELECT * FROM events WHERE event_id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
    
    if (!$event) {
        header("Location: events.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Event - CITSA Admin</title>
    
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
                            <a class="nav-link active" href="events.php">
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
                    <h1 class="h2">Edit Event</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Admin</a></li>
                            <li class="breadcrumb-item"><a href="events.php">Events</a></li>
                            <li class="breadcrumb-item active">Edit Event</li>
                        </ol>
                    </nav>
                </div>

                <!-- Message -->
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type === 'error' ? 'danger' : $message_type; ?> alert-dismissible fade show" role="alert">
                        <i class="bi bi-<?php echo $message_type === 'success' ? 'check-circle' : 'x-circle'; ?> me-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Edit Form -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-pencil me-2"></i>
                            Edit Event: <?php echo htmlspecialchars($event['title']); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="title" class="form-label">Event Title *</label>
                                <input type="text" class="form-control" id="title" name="title" 
                                       value="<?php echo htmlspecialchars($event['title']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($event['description'] ?? ''); ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="event_date" class="form-label">Event Date *</label>
                                    <input type="date" class="form-control" id="event_date" name="event_date" 
                                           value="<?php echo htmlspecialchars($event['event_date']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="event_time" class="form-label">Event Time</label>
                                    <input type="time" class="form-control" id="event_time" name="event_time" 
                                           value="<?php echo htmlspecialchars($event['event_time'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="location" class="form-label">Location</label>
                                    <input type="text" class="form-control" id="location" name="location" 
                                           value="<?php echo htmlspecialchars($event['location'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="max_participants" class="form-label">Max Participants</label>
                                    <input type="number" class="form-control" id="max_participants" name="max_participants" 
                                           value="<?php echo htmlspecialchars($event['max_participants'] ?? ''); ?>" min="1">
                                </div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary-custom">
                                    <i class="bi bi-save me-2"></i>Update Event
                                </button>
                                <a href="events.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-2"></i>Back to Events
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
