<?php
session_start();
ob_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection and helpers
include 'app/db.conn.php';
include 'app/helpers/user.php';

// Get current user data
$user = getUser($_SESSION['user_id'], $pdo);

if (!$user) {
    header("Location: logout.php");
    exit();
}

// Get user statistics - count accepted friend requests
$stmt = $pdo->prepare("SELECT COUNT(*) as friend_count FROM friend_requests WHERE (sender_id = ? OR receiver_id = ?) AND status = 'accepted'");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$friendCount = $stmt->fetch(PDO::FETCH_ASSOC)['friend_count'];



// Get recent activity (check if messages table exists)
try {
    $stmt = $pdo->prepare("
        SELECT 'message' as type, m.message as content, m.created_at as date, u.name as user_name, u.profile_image
        FROM messages m 
        JOIN users u ON (m.sender_id = u.user_id OR m.receiver_id = u.user_id)
        WHERE (m.sender_id = ? OR m.receiver_id = ?) AND u.user_id != ?
        ORDER BY m.created_at DESC LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
    $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recentActivity = [];
}
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
    <title>Student-Alumni Portal - CITSA Connect</title>
    <meta name="title" content="Student-Alumni Portal - CITSA Connect">
    <meta name="description" content="Connect with alumni, share experiences, and build your professional network through our student-alumni communication platform.">
    <meta name="keywords" content="Student Portal, Alumni Network, CITSA, UCC, Computer Science, IT, Communication Platform, Professional Networking">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://citsa-connect.org">
    <meta property="og:title" content="Student-Alumni Portal - CITSA Connect">
    <meta property="og:description" content="Connect with alumni, share experiences, and build your professional network through our student-alumni communication platform.">
    <meta property="og:image" content="https://citsa-connect.org/path/to/your/logo.png">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://citsa-connect.org">
    <meta property="twitter:title" content="Student-Alumni Portal - CITSA Connect">
    <meta property="twitter:description" content="Connect with alumni, share experiences, and build your professional network through our student-alumni communication platform.">
    <meta property="twitter:image" content="https://citsa-connect.org/path/to/your/logo.png">

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
            --primary-color: #1E40AF;
            --secondary-color: #3B82F6;
            --success-color: #10B981;
            --warning-color: #F59E0B;
            --danger-color: #EF4444;
            --dark-color: #1F2937;
            --light-color: #F3F4F6;
            --border-color: #E5E7EB;
        }

        .profile-section {
            padding: 2rem 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin-top: 80px;
        }

        .profile-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .profile-card:hover {
            transform: translateY(-5px);
        }

        .profile-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 5px solid white;
            object-fit: cover;
            margin: 0 auto 1rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .profile-stats {
            display: flex;
            justify-content: space-around;
            margin-top: 1.5rem;
            padding: 1rem 0;
            border-top: 1px solid rgba(255,255,255,0.2);
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            display: block;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .nav-tabs {
            border: none;
            background: #f8f9fa;
            border-radius: 15px;
            padding: 0.5rem;
            margin-bottom: 2rem;
        }

        .nav-tabs .nav-link {
            border: none;
            border-radius: 10px;
            color: var(--dark-color);
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
        }

        .nav-tabs .nav-link.active {
            background: var(--primary-color);
            color: white !important;
        }

        .nav-tabs .nav-link:hover {
            background: rgba(30, 64, 175, 0.1);
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid var(--border-color);
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(30, 64, 175, 0.25);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30, 64, 175, 0.3);
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }

        .activity-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }

        .activity-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 1rem;
        }

        .activity-content {
            flex: 1;
        }

        .activity-time {
            color: #6c757d;
            font-size: 0.85rem;
        }

        .label {
            font-weight: 600;
            color: var(--dark-color);
        }

        .value {
            color: #6c757d;
        }

        .file-upload {
            position: relative;
            display: inline-block;
            cursor: pointer;
            width: 100%;
        }

        .file-upload input[type=file] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .upload-btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: var(--light-color);
            border: 2px dashed var(--border-color);
            border-radius: 10px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .upload-btn:hover {
            border-color: var(--primary-color);
            background: rgba(30, 64, 175, 0.05);
        }



        @media (max-width: 768px) {
            .profile-section {
                margin-top: 5px; /* Reduced margin on mobile */
            }
            
            .profile-stats {
                flex-direction: column;
                gap: 1rem;
            }
            
            .nav-tabs {
                flex-direction: column;
            }
            
            .nav-tabs .nav-link {
                margin-bottom: 0.5rem;
            }
        }

        @media (max-width: 576px) {
            .profile-section {
                margin-top:55px; /* Consistent 5px margin on very small screens */
                padding: 1rem 0;
            }
        }
    </style>
</head>

<body>
    <?php include 'nav.php'; ?>

    <div class="profile-section">
        <div class="container">
            <div class="row">
                <!-- Profile Card -->
                <div class="col-lg-4 mb-4">
                    <div class="profile-card">
                        <div class="profile-header">
                            <img src="profile/<?= htmlspecialchars($user['profile_image'] ?? 'default-avatar.png') ?>" alt="Profile" class="profile-avatar">
                            <h3><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h3>
                            <p class="mb-0">
                            <!-- <span class="badge <?= getUserTypeBadgeClass($user['user_type']) ?>">
                                <?= getUserTypeLabel($user['user_type']) ?>
                            </span> -->
                        </p>

                        </div>
                        
                        <div class="p-4">
                            <div class="profile-stats">
                                <div class="stat-item">
                                    <span class="stat-number"><?= $friendCount ?></span>
                                    <span class="stat-label">Friends</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <!-- <div class="profile-card mt-4">
                        <div class="p-4">
                            <h5 class="mb-3">Recent Activity</h5>
                            <?php if (empty($recentActivity)): ?>
                                <p class="text-muted">No recent activity</p>
                            <?php else: ?>
                                <?php foreach ($recentActivity as $activity): ?>
                                    <div class="activity-item">
                                        <img src="profile/<?= htmlspecialchars($activity['profile_image'] ?? 'default-avatar.png') ?>" alt="User" class="activity-avatar">
                                        <div class="activity-content">
                                            <div class="fw-bold"><?= htmlspecialchars($activity['user_name']) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars(substr($activity['content'], 0, 50)) ?>...</div>
                                            <div class="activity-time"><?= date('M j, Y', strtotime($activity['date'])) ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div> -->
                </div>

                <!-- Profile Content -->
                <div class="col-lg-8">
                    <div class="profile-card">
                        <div class="p-4">
                            <!-- Navigation Tabs -->
                            <ul class="nav nav-tabs" id="profileTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">
                                        <i class="bi bi-person-circle me-2"></i>Overview
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="edit-tab" data-bs-toggle="tab" data-bs-target="#edit" type="button" role="tab">
                                        <i class="bi bi-pencil-square me-2"></i>Edit Profile
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password" type="button" role="tab">
                                        <i class="bi bi-shield-lock me-2"></i>Change Password
                                    </button>
                                </li>
                            </ul>

                            <!-- Tab Content -->
                            <div class="tab-content" id="profileTabsContent">
                                <!-- Overview Tab -->
                                <div class="tab-pane fade show active" id="overview" role="tabpanel">
                                    <h4 class="mb-4">Profile Overview</h4>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-3">
                                            <span class="label">Full Name:</span>
                                        </div>
                                        <div class="col-md-9">
                                            <span class="value"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-3">
                                            <span class="label">Username:</span>
                                        </div>
                                        <div class="col-md-9">
                                            <span class="value">@<?= htmlspecialchars($user['username']) ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-3">
                                            <span class="label">Student ID:</span>
                                        </div>
                                        <div class="col-md-9">
                                            <span class="value"><?= htmlspecialchars($user['student_id']) ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-3">
                                            <span class="label">Programme:</span>
                                        </div>
                                        <div class="col-md-9">
                                            <span class="value"><?= htmlspecialchars($user['programme']) ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-3">
                                            <span class="label">User Type:</span>
                                        </div>
                                        <div class="col-md-9">
                                            <span class="value">
                            <span class="badge <?= getUserTypeBadgeClass($user['user_type']) ?>">
                                <?= getUserTypeLabel($user['user_type']) ?>
                            </span>
                        </span>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-3">
                                            <span class="label">Member Since:</span>
                                        </div>
                                        <div class="col-md-9">
                                            <span class="value"><?= date('F j, Y', strtotime($user['created_at'])) ?></span>
                                        </div>
                                    </div>
                                    
                                    <?php if ($user['about']): ?>
                                    <div class="row mb-3">
                                        <div class="col-md-3">
                                            <span class="label">About:</span>
                                        </div>
                                        <div class="col-md-9">
                                            <span class="value"><?= htmlspecialchars($user['about']) ?></span>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Edit Profile Tab -->
                                <div class="tab-pane fade" id="edit" role="tabpanel">
                                    <h4 class="mb-4">Edit Profile</h4>
                                    
                                    <form id="profileForm" method="POST" action="update_profile.php" enctype="multipart/form-data">
                                        <div class="row mb-3">
                                            <label class="col-md-3 col-form-label">Profile Image</label>
                                            <div class="col-md-9">
                                                <div class="d-flex align-items-center mb-3">
                                                    <img src="profile/<?= htmlspecialchars($user['profile_image'] ?? 'default-avatar.png') ?>" alt="Current Profile" class="rounded-circle me-3" style="width: 80px; height: 80px; object-fit: cover;">
                                                    <div class="file-upload">
                                                        <input type="file" name="profile_image" accept="image/*" id="profileImage">
                                                        <label for="profileImage" class="upload-btn">
                                                            <i class="bi bi-camera me-2"></i>Choose New Image
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <label class="col-md-3 col-form-label">First Name</label>
                                            <div class="col-md-9">
                                                <input type="text" class="form-control" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <label class="col-md-3 col-form-label">Last Name</label>
                                            <div class="col-md-9">
                                                <input type="text" class="form-control" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <label class="col-md-3 col-form-label">Username</label>
                                            <div class="col-md-9">
                                                <input type="text" class="form-control" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <label class="col-md-3 col-form-label">Email</label>
                                            <div class="col-md-9">
                                                <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <label class="col-md-3 col-form-label">Programme</label>
                                            <div class="col-md-9">
                                                <input type="text" class="form-control" name="programme" value="<?= htmlspecialchars($user['programme']) ?>" required>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <label class="col-md-3 col-form-label">About</label>
                                            <div class="col-md-9">
                                                <textarea class="form-control" name="about" rows="4" placeholder="Tell us about yourself..."><?= htmlspecialchars($user['about'] ?? '') ?></textarea>
                                            </div>
                                        </div>

                                        <div class="text-end">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-check-circle me-2"></i>Save Changes
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                <!-- Change Password Tab -->
                                <div class="tab-pane fade" id="password" role="tabpanel">
                                    <h4 class="mb-4">Change Password</h4>
                                    
                                    <form id="passwordForm" method="POST" action="change_password.php">
                                        <div class="row mb-3">
                                            <label class="col-md-3 col-form-label">Current Password</label>
                                            <div class="col-md-9">
                                                <input type="password" class="form-control" name="current_password" required>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <label class="col-md-3 col-form-label">New Password</label>
                                            <div class="col-md-9">
                                                <input type="password" class="form-control" name="new_password" id="newPassword" required>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <label class="col-md-3 col-form-label">Confirm New Password</label>
                                            <div class="col-md-9">
                                                <input type="password" class="form-control" name="confirm_password" id="confirmPassword" required>
                                            </div>
                                        </div>

                                        <div class="text-end">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-shield-check me-2"></i>Change Password
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- AOS Animation -->
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>

    <script>
        // Initialize AOS
        AOS.init();

        // File upload preview
        document.getElementById('profileImage').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Update both the main profile avatar and the edit form avatar
                    const profileAvatars = document.querySelectorAll('.profile-avatar, .rounded-circle[alt="Current Profile"]');
                    profileAvatars.forEach(avatar => {
                        avatar.src = e.target.result;
                    });
                };
                reader.readAsDataURL(file);
            }
        });

        // Password confirmation validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                Swal.fire('Error', 'New passwords do not match!', 'error');
            }
        });

        // Show success/error messages
        <?php if (isset($_GET['status'])): ?>
            <?php if ($_GET['status'] === 'success'): ?>
                Swal.fire('Success', '<?= isset($_GET['message']) ? htmlspecialchars($_GET['message']) : 'Operation completed successfully!' ?>', 'success');
            <?php else: ?>
                Swal.fire('Error', '<?= isset($_GET['message']) ? htmlspecialchars($_GET['message']) : 'An error occurred!' ?>', 'error');
            <?php endif; ?>
        <?php endif; ?>
    </script>
</body>
</html>

