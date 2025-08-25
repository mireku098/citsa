<?php
session_start();
ob_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection
include 'app/db.conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $user_id = $_SESSION['user_id'];
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $programme = trim($_POST['programme']);
        $about = trim($_POST['about'] ?? '');

        // Validate input
        if (empty($first_name) || empty($last_name) || empty($username) || empty($email) || empty($programme)) {
            header("Location: profile.php?status=error&message=All required fields must be filled");
            exit();
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header("Location: profile.php?status=error&message=Please enter a valid email address");
            exit();
        }

        // Check if username is already taken by another user
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
        $stmt->execute([$username, $user_id]);
        if ($stmt->fetch()) {
            header("Location: profile.php?status=error&message=Username is already taken");
            exit();
        }

        // Check if email is already taken by another user
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            header("Location: profile.php?status=error&message=Email address is already registered");
            exit();
        }

        // Handle profile image upload
        $profile_image = null;
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_image'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 5 * 1024 * 1024; // 5MB

            // Validate file type
            if (!in_array($file['type'], $allowed_types)) {
                header("Location: profile.php?status=error&message=Invalid image type. Please use JPEG, PNG, GIF, or WebP");
                exit();
            }

            // Validate file size
            if ($file['size'] > $max_size) {
                header("Location: profile.php?status=error&message=Image size must be less than 5MB");
                exit();
            }

            // Create profile directory if it doesn't exist
            $profile_dir = 'profile';
            if (!is_dir($profile_dir)) {
                mkdir($profile_dir, 0755, true);
            }

            // Generate unique filename
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
            $filepath = $profile_dir . '/' . $filename;

            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $profile_image = $filename;
                
                // Delete old profile image if it exists and is not the default
                $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $old_image = $stmt->fetchColumn();
                
                if ($old_image && $old_image !== 'default-avatar.png' && file_exists($profile_dir . '/' . $old_image)) {
                    unlink($profile_dir . '/' . $old_image);
                }
            } else {
                header("Location: profile.php?status=error&message=Failed to upload image");
                exit();
            }
        }

        // Update user profile
        if ($profile_image) {
            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, username = ?, email = ?, programme = ?, about = ?, profile_image = ? WHERE user_id = ?");
            $stmt->execute([$first_name, $last_name, $username, $email, $programme, $about, $profile_image, $user_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, username = ?, email = ?, programme = ?, about = ? WHERE user_id = ?");
            $stmt->execute([$first_name, $last_name, $username, $email, $programme, $about, $user_id]);
        }

        if ($stmt->rowCount() > 0) {
            header("Location: profile.php?status=success&message=Profile updated successfully");
        } else {
            header("Location: profile.php?status=error&message=No changes were made");
        }

    } catch (Exception $e) {
        error_log("Profile update error: " . $e->getMessage());
        header("Location: profile.php?status=error&message=An unexpected error occurred");
    }
} else {
    header("Location: profile.php");
}
exit();
?> 