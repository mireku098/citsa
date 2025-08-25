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

// Display registration success message if exists
if (isset($_SESSION['registration_success'])) {
    $success_message = $_SESSION['registration_success'];
    unset($_SESSION['registration_success']);
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = 'Invalid request. Please try again.';
    } else {
        $login_identifier = trim($_POST['username']); // This can be username or student ID
        $password = $_POST['password'];
        
        if (empty($login_identifier) || empty($password)) {
            $error_message = 'Please fill in all fields.';
        } else {
            try {
                $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Check if login identifier is username or student ID
                $stmt = $pdo->prepare("SELECT user_id, username, student_id, email, password, first_name, last_name FROM users WHERE username = ? OR student_id = ?");
                $stmt->execute([$login_identifier, $login_identifier]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name']; // For backward compatibility
                    
                    if (isset($_POST['remember_me']) && $_POST['remember_me'] == '1') {
                        $token = bin2hex(random_bytes(32));
                        setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
                    }
                    
                    session_regenerate_id(true);
                    header('Location: home.php');
                    exit();
                } else {
                    $error_message = 'Invalid username/student ID or password.';
                }
            } catch (PDOException $e) {
                $error_message = 'Database connection error. Please try again later.';
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
    
    <!-- AOS Animation -->
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">

        <!-- Cascading stylesheet-->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        .login-container {
            background: url('assets/img/logo.png') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }
        
        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.7);
            z-index: 1;
        }
        
        .login-card {
            position: relative;
            z-index: 2;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .logo-img {
            width: 100px;
            height: 100px;
            object-fit: contain;
            margin-top: 0;
            margin-bottom: 5px;
        }
        
        .login-logo {
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
        
        .login-header {
            text-align: center;
            margin-bottom: 10px;
        }
        
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
    </style>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <div class="login-container">
        <div class="login-card" data-aos="fade-up" data-aos-duration="1000">
            <div class="login-header">
                <div class="login-logo">
                    <img src="assets/img/logo.png" alt="CITSA Logo" class="logo-img">
                </div>
                <h2 class="text-[#1a3c6d] fw-bold mb-2">Welcome Back</h2>
                <p class="text-muted mb-0">Sign in to your CITSA Connect account</p>
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

            <form method="POST" action="" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="mb-3">
                    <label for="username" class="form-label fw-semibold text-[#1a3c6d]">
                        <i class="fas fa-user me-2"></i>Username or Student ID
                    </label>
                    <input type="text" class="form-control" id="username" name="username" 
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                           placeholder="Enter your username or student ID"
                           required>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label fw-semibold text-[#1a3c6d]">
                        <i class="fas fa-lock me-2"></i>Password
                    </label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" required>
                        <span class="input-group-text" style="cursor: pointer;" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </span>
                    </div>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me" value="1">
                    <label class="form-check-label" for="remember_me">
                        Remember me
                    </label>
                </div>

                <button type="submit" name="login" class="btn btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                </button>
            </form>

            <div class="login-footer">
                <p class="mb-2">Don't have an account? 
                    <a href="register.php">Create one here</a>
                </p>
                <p class="mb-0">
                    <a href="#" onclick="showForgotPassword()">Forgot your password?</a>
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
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
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

        // Show forgot password modal
        function showForgotPassword() {
            Swal.fire({
                title: 'Forgot Password?',
                text: 'Please contact your system administrator to reset your password.',
                icon: 'info',
                confirmButtonColor: '#1a3c6d',
                confirmButtonText: 'OK'
            });
        }

        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (!username || !password) {
                e.preventDefault();
                Swal.fire({
                    title: 'Error!',
                    text: 'Please fill in all fields.',
                    icon: 'error',
                    confirmButtonColor: '#1a3c6d'
                });
            }
        });
    </script>
</body>
</html> 