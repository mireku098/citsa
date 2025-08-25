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

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($file_extension === 'csv') {
            // Process CSV file
            $handle = fopen($file['tmp_name'], 'r');
            $success_count = 0;
            $error_count = 0;
            
            // Skip header row
            fgetcsv($handle);
            
            while (($data = fgetcsv($handle)) !== false) {
                if (count($data) >= 4) {
                    $first_name = trim($data[0]);
                    $last_name = trim($data[1]); // This can include middle name
                    $programme = trim($data[2]);
                    $student_id = trim($data[3]);
                    
                    try {
                        // Check if user already exists by student ID
                        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE student_id = ?");
                        $stmt->execute([$student_id]);
                        
                        if (!$stmt->fetch()) {
                            // Generate username from first and last name
                            $username = strtolower(str_replace(' ', '', $first_name . $last_name));
                            
                            // Check if username exists, if so add a number
                            $base_username = $username;
                            $counter = 1;
                            while (true) {
                                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
                                $stmt->execute([$username]);
                                if (!$stmt->fetch()) break;
                                $username = $base_username . $counter;
                                $counter++;
                            }
                            
                            // Insert new user
                            $stmt = $pdo->prepare("INSERT INTO users (username, first_name, last_name, programme, student_id, user_type, created_at) VALUES (?, ?, ?, ?, ?, 'student', NOW())");
                            $stmt->execute([$username, $first_name, $last_name, $programme, $student_id]);
                            $success_count++;
                                }
                            } catch (PDOException $e) {
                        $error_count++;
                    }
                }
            }
            
            fclose($handle);
            
            if ($success_count > 0) {
                $message = "Successfully uploaded $success_count users. $error_count errors occurred.";
                $message_type = 'success';
            } else {
                $message = "No new users were added. $error_count errors occurred.";
                $message_type = 'warning';
            }
        } else {
            $message = "Please upload a valid CSV file.";
            $message_type = 'error';
        }
                    } else {
        $message = "Error uploading file.";
        $message_type = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Authorized Users - CITSA Admin</title>
    
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

        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .upload-area:hover {
            border-color: var(--primary-color);
            background-color: rgba(26, 60, 109, 0.05);
        }

        .upload-area.dragover {
            border-color: var(--primary-color);
            background-color: rgba(26, 60, 109, 0.1);
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
                            <a class="nav-link" href="authorized_users.php">
                                <i class="bi bi-shield-check"></i>
                                Authorized Users
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

            <!-- Main content -->
            <main class="main-content">
                <!-- Page Title -->
                <div class="mb-4">
                    <h1 class="h2">Upload Authorized Users</h1>
                    <p class="text-muted">Upload student information for registration verification</p>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Admin</a></li>
                            <li class="breadcrumb-item"><a href="platform.php">Platform</a></li>
                            <li class="breadcrumb-item active">Upload Users</li>
                        </ol>
                    </nav>
                </div>

                <!-- Message -->
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type === 'error' ? 'danger' : $message_type; ?> alert-dismissible fade show" role="alert">
                        <i class="bi bi-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : 'x-circle'); ?> me-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Upload Form -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-upload me-2"></i>
                            Upload Student Information via CSV
                        </h5>
                        <small class="text-white-50">For registration verification purposes</small>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="upload-area mb-4" id="uploadArea">
                                        <i class="bi bi-cloud-upload display-4 text-muted mb-3"></i>
                                        <h5>Drag & Drop CSV file here</h5>
                                        <p class="text-muted">or click to browse</p>
                                        <input type="file" name="csv_file" id="csvFile" accept=".csv" class="d-none" required>
                                        <button type="button" class="btn btn-primary-custom" onclick="document.getElementById('csvFile').click()">
                                            <i class="bi bi-folder2-open me-2"></i>Browse Files
                                        </button>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="csvFile" class="form-label">Selected File:</label>
                                        <input type="text" class="form-control" id="selectedFileName" readonly placeholder="No file selected">
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary-custom" id="uploadBtn" disabled>
                                        <i class="bi bi-upload me-2"></i>Upload Users
                                    </button>
                                </form>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="bi bi-info-circle me-2"></i>
                                            CSV Format
                                        </h6>
                                        <p class="card-text small">
                                            Your CSV file should have the following columns:<br>
                                            <strong>First Name, Last Name (with Middle), Program, Student ID</strong>
                                        </p>
                                        <p class="card-text small">
                                            <strong>Example:</strong><br>
                                            John,Michael Doe,Computer Science,2023001
                                        </p>
                                        <p class="card-text small text-muted">
                                            <i class="bi bi-info-circle me-1"></i>
                                            Middle names can be included in the Last Name column
                                        </p>
                                        <a href="#" class="btn btn-sm btn-outline-primary" onclick="downloadSampleCSV()">
                                            <i class="bi bi-download me-1"></i>Download Sample
                                        </a>
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
        // File selection handling
        document.getElementById('csvFile').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                document.getElementById('selectedFileName').value = file.name;
                document.getElementById('uploadBtn').disabled = false;
} else {
                document.getElementById('selectedFileName').value = '';
                document.getElementById('uploadBtn').disabled = true;
            }
        });

        // Drag and drop functionality
        const uploadArea = document.getElementById('uploadArea');
        
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                const file = files[0];
                if (file.type === 'text/csv' || file.name.endsWith('.csv')) {
                    document.getElementById('csvFile').files = files;
                    document.getElementById('selectedFileName').value = file.name;
                    document.getElementById('uploadBtn').disabled = false;
                } else {
                    alert('Please select a valid CSV file.');
                }
            }
        });

        // Download sample CSV
        function downloadSampleCSV() {
            const csvContent = 'First Name,Last Name (with Middle),Program,Student ID\nJohn,Michael Doe,Computer Science,2023001\nJane,Elizabeth Smith,Information Technology,2023002\nRobert,James Wilson,Software Engineering,2023003';
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'sample_authorized_users.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>
