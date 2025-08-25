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
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Validate input
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            header("Location: profile.php?status=error&message=All password fields are required");
            exit();
        }

        // Check if new passwords match
        if ($new_password !== $confirm_password) {
            header("Location: profile.php?status=error&message=New passwords do not match");
            exit();
        }

        // Validate new password strength
        if (strlen($new_password) < 8) {
            header("Location: profile.php?status=error&message=New password must be at least 8 characters long");
            exit();
        }

        // Get current user's password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            header("Location: profile.php?status=error&message=User not found");
            exit();
        }

        // Verify current password
        if (!password_verify($current_password, $user['password'])) {
            header("Location: profile.php?status=error&message=Current password is incorrect");
            exit();
        }

        // Hash new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Update password
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->execute([$hashed_password, $user_id]);

        if ($stmt->rowCount() > 0) {
            header("Location: profile.php?status=success&message=Password changed successfully");
        } else {
            header("Location: profile.php?status=error&message=Failed to update password");
        }

    } catch (Exception $e) {
        error_log("Password change error: " . $e->getMessage());
        header("Location: profile.php?status=error&message=An unexpected error occurred");
    }
} else {
    header("Location: profile.php");
}
exit();
?> 