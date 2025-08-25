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

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Get form data
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $event_date = $_POST['event_date'];
        $event_time = $_POST['event_time'] ?: null;
        $location = trim($_POST['location']) ?: null;
        $event_type = $_POST['event_type'];
        $status = $_POST['status'];
        $created_by = $_SESSION['admin_id'];
        
        // Validation
        if (empty($title) || empty($description) || empty($event_date) || empty($event_type) || empty($status)) {
            $message = 'Required fields cannot be empty';
            $message_type = 'danger';
        } elseif (!strtotime($event_date)) {
            $message = 'Invalid date format';
            $message_type = 'danger';
        } else {
            // Validate event type
            $valid_types = ['event', 'announcement', 'meeting', 'workshop'];
            if (!in_array($event_type, $valid_types)) {
                $message = 'Invalid event type';
                $message_type = 'danger';
                            } else {
                    // Validate status
                    $valid_statuses = ['draft', 'published'];
                    if (!in_array($status, $valid_statuses)) {
                        $message = 'Invalid status';
                        $message_type = 'danger';
                    } else {
                        // Check for duplicate events (same title, date, time, and location)
                        $stmt = $pdo->prepare("
                            SELECT COUNT(*) as count 
                            FROM events 
                            WHERE title = ? AND event_date = ? AND event_time = ? AND location = ? AND created_by = ?
                        ");
                        $stmt->execute([$title, $event_date, $event_time, $location, $created_by]);
                        $duplicate_count = $stmt->fetch()['count'];
                        
                        if ($duplicate_count > 0) {
                            $message = 'An event with the same title, date, time, and location already exists. Please check for duplicates or modify the details.';
                            $message_type = 'danger';
                        } else {
                            // Handle image upload
                            $image_path = null;
                            if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === UPLOAD_ERR_OK) {
                                $upload_dir = '../uploads/events/';
                                
                                // Create directory if it doesn't exist
                                if (!is_dir($upload_dir)) {
                                    mkdir($upload_dir, 0755, true);
                                }
                                
                                $file_info = pathinfo($_FILES['event_image']['name']);
                                $file_extension = strtolower($file_info['extension']);
                                
                                // Validate file type
                                $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                                if (!in_array($file_extension, $allowed_types)) {
                                    $message = 'Invalid file type. Only JPG, PNG, and GIF are allowed.';
                                    $message_type = 'danger';
                                } elseif ($_FILES['event_image']['size'] > 5 * 1024 * 1024) {
                                    $message = 'File size too large. Maximum size is 5MB.';
                                    $message_type = 'danger';
                                } else {
                                    // Generate unique filename
                                    $filename = 'event_' . time() . '_' . uniqid() . '.' . $file_extension;
                                    $upload_path = $upload_dir . $filename;
                                    
                                    if (move_uploaded_file($_FILES['event_image']['tmp_name'], $upload_path)) {
                                        $image_path = 'uploads/events/' . $filename;
                                    } else {
                                        $message = 'Failed to upload image.';
                                        $message_type = 'danger';
                                    }
                                }
                            }
                            
                            if (empty($message)) {
                                // Insert new event
                                $stmt = $pdo->prepare("
                                    INSERT INTO events (title, description, event_date, event_time, location, event_type, status, image_path, created_by)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                                ");
                                
                                if ($stmt->execute([$title, $description, $event_date, $event_time, $location, $event_type, $status, $image_path, $created_by])) {
                                    $message = 'Event created successfully!';
                                    $message_type = 'success';
                                    
                                    // Clear form data on success
                                    $_POST = array();
                                    
                                    // Redirect to prevent form resubmission
                                    header("Location: events.php?success=1");
                                    exit();
                                } else {
                                    $message = 'Failed to create event';
                                    $message_type = 'danger';
                                }
                            }
                        }
                    }
                }
        }
    } catch (PDOException $e) {
        $message = 'Database error: ' . $e->getMessage();
        $message_type = 'danger';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Event - CITSA Admin</title>
    
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

        .form-control, .form-select {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            padding: 12px 16px;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(26, 60, 109, 0.25);
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

        .btn-secondary-custom {
            background: #6c757d;
            border: none;
            color: white;
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-secondary-custom:hover {
            background: #5a6268;
            transform: translateY(-2px);
            color: white;
        }

        .form.submitting {
            opacity: 0.7;
            pointer-events: none;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .image-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 10px;
            border: 2px dashed #dee2e6;
            padding: 20px;
            text-align: center;
            background: #f8f9fa;
        }

        .image-preview img {
            max-width: 100%;
            max-height: 100%;
            border-radius: 8px;
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
                    <h1 class="h2">Add New Event</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Admin</a></li>
                            <li class="breadcrumb-item"><a href="events.php">Events</a></li>
                            <li class="breadcrumb-item active">Add Event</li>
                        </ol>
                    </nav>
                </div>

                <!-- Alert Messages -->
                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Add Event Form -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-calendar-plus me-2"></i>
                            Event Details
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Event Title *</label>
                                        <input type="text" class="form-control" id="title" name="title" 
                                               value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" 
                                               required placeholder="Enter event title">
                                    </div>

                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description *</label>
                                        <textarea class="form-control" id="description" name="description" rows="4" 
                                                  required placeholder="Enter event description"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="event_date" class="form-label">Event Date *</label>
                                                <input type="date" class="form-control" id="event_date" name="event_date" 
                                                       value="<?php echo $_POST['event_date'] ?? ''; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="event_time" class="form-label">Event Time</label>
                                                <input type="time" class="form-control" id="event_time" name="event_time" 
                                                       value="<?php echo $_POST['event_time'] ?? ''; ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="location" class="form-label">Location</label>
                                                <input type="text" class="form-control" id="location" name="location" 
                                                       value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>" 
                                                       placeholder="Enter event location">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="event_type" class="form-label">Event Type *</label>
                                                <select class="form-select" id="event_type" name="event_type" required>
                                                    <option value="">Select event type</option>
                                                    <option value="event" <?php echo ($_POST['event_type'] ?? '') === 'event' ? 'selected' : ''; ?>>Event</option>
                                                    <option value="announcement" <?php echo ($_POST['event_type'] ?? '') === 'announcement' ? 'selected' : ''; ?>>Announcement</option>
                                                    <option value="meeting" <?php echo ($_POST['event_type'] ?? '') === 'meeting' ? 'selected' : ''; ?>>Meeting</option>
                                                    <option value="workshop" <?php echo ($_POST['event_type'] ?? '') === 'workshop' ? 'selected' : ''; ?>>Workshop</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status *</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="">Select status</option>
                                            <option value="draft" <?php echo ($_POST['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                            <option value="published" <?php echo ($_POST['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Published</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="event_image" class="form-label">Event Image</label>
                                        <input type="file" class="form-control" id="event_image" name="event_image" 
                                               accept="image/*" onchange="previewImage(this)">
                                        <small class="text-muted">Max size: 5MB. Formats: JPG, PNG, GIF</small>
                                    </div>

                                    <div class="image-preview" id="imagePreview">
                                        <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
                                        <p class="text-muted mt-2">No image selected</p>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex gap-2 mt-4">
                                <button type="submit" class="btn btn-primary-custom">
                                    <i class="bi bi-check-circle me-2"></i>Create Event
                                </button>
                                <a href="events.php" class="btn btn-secondary-custom">
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
    
    <script>
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" alt="Preview" class="img-fluid">`;
                }
                
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.innerHTML = `
                    <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-2">No image selected</p>
                `;
            }
        }

        // Set minimum date to today
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('event_date').min = today;
            
            // Prevent form double-submission
            const form = document.querySelector('form');
            const submitBtn = form.querySelector('button[type="submit"]');
            
            form.addEventListener('submit', function(e) {
                // Disable submit button to prevent double-submission
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Creating Event...';
                
                // Add loading state to form
                form.classList.add('submitting');
            });
        });
    </script>
</body>
</html>
