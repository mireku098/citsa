<?php
session_start();

// Security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

// Database configuration
$db_host = 'localhost';
$db_name = 'citsa';
$db_user = 'root';
$db_pass = '';

// CSRF protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error_message = '';
$success_message = '';

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = 'Invalid request. Please try again.';
    } else {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $username = trim($_POST['username']);
        $student_id = trim($_POST['student_id']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Include helper functions
        include 'app/helpers/user.php';
        
        // Input validation
        if (empty($first_name) || empty($last_name) || empty($username) || empty($student_id) || empty($email) || empty($password) || empty($confirm_password)) {
            $error_message = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error_message = 'Password must be at least 8 characters long.';
        } elseif ($password !== $confirm_password) {
            $error_message = 'Passwords do not match.';
        } else {
            try {
                $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Validate student registration against authorized database
                $validation_result = validateStudentRegistration($first_name, $last_name, $student_id, $pdo);
                
                if (!$validation_result['valid']) {
                    $error_message = $validation_result['message'];
                } else {
                    // Get student data from authorized database
                    $authorized_student = $validation_result['student_data'];
                    $programme = $authorized_student['programme'];
                    $user_type = $authorized_student['user_type'];
                    
                    // Check if username already exists
                    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
                    $stmt->execute([$username]);
                    
                    if ($stmt->fetch()) {
                        $error_message = 'Username is already taken.';
                    } else {
                        // Check if email already exists
                        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
                        $stmt->execute([$email]);
                        
                        if ($stmt->fetch()) {
                            $error_message = 'Email address is already registered.';
                        } else {
                        // Handle profile picture upload
                        $profile_image = 'default-avatar.png'; // Default image
                        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                            $file_type = $_FILES['profile_picture']['type'];
                            
                            if (in_array($file_type, $allowed_types)) {
                                $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                                $profile_image = uniqid() . '.' . $file_extension;
                                $upload_path = 'profile/' . $profile_image;
                                
                                if (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                                    $error_message = 'Failed to upload profile picture.';
                                }
                            } else {
                                $error_message = 'Invalid file type. Please upload JPEG, PNG, or GIF images only.';
                            }
                        }
                        
                        if (empty($error_message)) {
                            // Hash password
                            $password_hash = password_hash($password, PASSWORD_DEFAULT);
                            
                            // Insert new user with first_name, last_name, and email
                            $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, username, student_id, email, password, programme, user_type, profile_image, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())");
                            $stmt->execute([$first_name, $last_name, $username, $student_id, $email, $password_hash, $programme, $user_type, $profile_image]);
                            
                            // Redirect to login page with success message
                            $_SESSION['registration_success'] = 'Registration successful! You can now login with your student ID or username.';
                            header('Location: login.php');
                            exit();
                        }
                    }
                }
            }
            } catch (PDOException $e) {
                $error_message = 'Database connection error. Please try again later.';
                error_log("Registration error: " . $e->getMessage());
            }
        }
    }
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
    <meta name="apple-mobile-web-app-capable" content="yes">
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
    
    <!-- AOS Animation -->
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">

    <!-- Cascading stylesheet-->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        .register-container {
            background: url('assets/img/logo.png') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }
        
        .register-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.7);
            z-index: 1;
        }
        
        .register-card {
            position: relative;
            z-index: 2;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .register-logo {
            text-align: center;
            margin-bottom: 10px;
            background: none !important;
            border-radius: 0 !important;
            display: block !important;
            width: auto !important;
            height: auto !important;
            color: inherit !important;
            font-size: inherit !important;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 10px;
        }
    </style>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <div class="register-container">
        <div class="register-card" data-aos="fade-up" data-aos-duration="1000">
            <div class="register-header">
                <div class="register-logo">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h2 class="text-[#1a3c6d] fw-bold mb-2">Create Account</h2>
                <p class="text-muted mb-0">Join the CITSA Connect community</p>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="registerForm" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <!-- Row 1: First Name and Last Name -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="first_name" class="form-label fw-semibold text-[#1a3c6d]">
                                <i class="fas fa-user me-2"></i>First Name
                            </label>
                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                   value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" 
                                   required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="last_name" class="form-label fw-semibold text-[#1a3c6d]">
                                <i class="fas fa-user me-2"></i>Last Name
                            </label>
                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                   value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" 
                                   required>
                        </div>
                    </div>
                </div>

                <!-- Row 2: Username -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label for="username" class="form-label fw-semibold text-[#1a3c6d]">
                                <i class="fas fa-at me-2"></i>Username
                            </label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                                   required>
                        </div>
                    </div>
                </div>

                <!-- Row 3: Email -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold text-[#1a3c6d]">
                                <i class="fas fa-envelope me-2"></i>Email Address
                            </label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                   required>
                        </div>
                    </div>
                </div>

                <!-- Row 4: Student ID -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label for="student_id" class="form-label fw-semibold text-[#1a3c6d]">
                                <i class="fas fa-id-card me-2"></i>Student ID
                            </label>
                            <input type="text" class="form-control" id="student_id" name="student_id" 
                                   placeholder="PS/ITC/21/0000"
                                   value="<?php echo isset($_POST['student_id']) ? htmlspecialchars($_POST['student_id']) : ''; ?>" 
                                   required>
                           
                        </div>
                    </div>
                </div>

                <!-- Row 5: Profile Picture -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label for="profile_picture" class="form-label fw-semibold text-[#1a3c6d]">
                                <i class="fas fa-camera me-2"></i>Profile Picture
                            </label>
                            <input type="file" class="form-control" id="profile_picture" name="profile_picture" 
                                   accept="image/*">
                            <small class="form-text text-muted">Upload JPEG, PNG, or GIF (optional)</small>
                        </div>
                    </div>
                </div>

                <!-- Row 6: Password and Confirm Password -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold text-[#1a3c6d]">
                                <i class="fas fa-lock me-2"></i>Password
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" required>
                                <span class="input-group-text" style="cursor: pointer;" onclick="togglePassword('password')">
                                    <i class="fas fa-eye" id="toggleIcon1"></i>
                                </span>
                            </div>
                            <div class="password-strength" id="passwordStrength"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label fw-semibold text-[#1a3c6d]">
                                <i class="fas fa-lock me-2"></i>Confirm Password
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <span class="input-group-text" style="cursor: pointer;" onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye" id="toggleIcon2"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="terms" name="terms" value="1" required>
                    <label class="form-check-label" for="terms">
                        I agree to the <a href="#" onclick="showTerms()">Terms and Conditions</a>
                    </label>
                </div>

                <button type="submit" name="register" class="btn btn-register">
                    <i class="fas fa-user-plus me-2"></i>Create Account
                </button>
            </form>

            <div class="register-footer">
                <p class="mb-0">Already have an account? 
                    <a href="login.php">Sign in here</a>
                </p>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- AOS Animation -->
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
    
    <script>
        // Initialize AOS
        AOS.init({
            duration: 1000,
            once: true
        });

        // Toggle password visibility
        function togglePassword(fieldId) {
            const passwordInput = document.getElementById(fieldId);
            const toggleIcon = document.getElementById(fieldId === 'password' ? 'toggleIcon1' : 'toggleIcon2');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Password strength checker
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('passwordStrength');
            let strength = 0;
            let message = '';

            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;

            switch (strength) {
                case 0:
                case 1:
                    message = '<span class="strength-weak">Weak password</span>';
                    break;
                case 2:
                case 3:
                    message = '<span class="strength-medium">Medium strength password</span>';
                    break;
                case 4:
                case 5:
                    message = '<span class="strength-strong">Strong password</span>';
                    break;
            }

            strengthDiv.innerHTML = message;
        });

        // Show terms modal
        function showTerms() {
            Swal.fire({
                title: 'Terms and Conditions',
                html: `
                    <div style="text-align: left; max-height: 300px; overflow-y: auto;">
                        <h6>1. Account Registration</h6>
                        <p>You must provide accurate and complete information when creating your account.</p>
                        
                        <h6>2. Privacy</h6>
                        <p>Your personal information will be protected and used only for platform purposes.</p>
                        
                        <h6>3. Conduct</h6>
                        <p>You agree to use the platform responsibly and not engage in harmful activities.</p>
                        
                        <h6>4. Termination</h6>
                        <p>We reserve the right to terminate accounts that violate our terms.</p>
                    </div>
                `,
                icon: 'info',
                confirmButtonColor: '#1a3c6d',
                confirmButtonText: 'I Understand'
            });
        }

        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const terms = document.getElementById('terms').checked;
            const studentId = document.getElementById('student_id').value.trim();
            
            // Validate names
            if (firstName.length < 2) {
                e.preventDefault();
                Swal.fire({
                    title: 'Invalid First Name!',
                    text: 'First name must be at least 2 characters long.',
                    icon: 'error',
                    confirmButtonColor: '#1a3c6d'
                });
                return;
            }
            
            if (lastName.length < 2) {
                e.preventDefault();
                Swal.fire({
                    title: 'Invalid Last Name!',
                    text: 'Last name must be at least 2 characters long.',
                    icon: 'error',
                    confirmButtonColor: '#1a3c6d'
                });
                return;
            }
            
            // Validate email format
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                Swal.fire({
                    title: 'Invalid Email!',
                    text: 'Please enter a valid email address.',
                    icon: 'error',
                    confirmButtonColor: '#1a3c6d'
                });
                return;
            }
            
            // Validate student ID format before submission
            const studentIdRegex = /^PS\/([A-Z]{3})\/(\d{2})\/\d+$/i;
            if (!studentIdRegex.test(studentId)) {
                e.preventDefault();
                Swal.fire({
                    title: 'Invalid Student ID!',
                    text: 'Please enter a valid student ID in the format PS/PROGRAM/YEAR/NUMBER',
                    icon: 'error',
                    confirmButtonColor: '#1a3c6d'
                });
                return;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                Swal.fire({
                    title: 'Error!',
                    text: 'Passwords do not match.',
                    icon: 'error',
                    confirmButtonColor: '#1a3c6d'
                });
                return;
            }
            
            if (!terms) {
                e.preventDefault();
                Swal.fire({
                    title: 'Error!',
                    text: 'Please accept the terms and conditions.',
                    icon: 'error',
                    confirmButtonColor: '#1a3c6d'
                });
                return;
            }
        });
    </script>
</body>
</html> 