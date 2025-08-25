<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Check if user_id is provided
if (!isset($_POST['user_id']) || empty($_POST['user_id'])) {
    $_SESSION['error'] = 'User ID is required';
    header('Location: club_members.php');
    exit();
}

$user_id = $_POST['user_id'];

try {
    // Get user details for confirmation
    $stmt = $pdo->prepare("SELECT first_name, last_name, username FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $_SESSION['error'] = 'User not found';
        header('Location: club_members.php');
        exit();
    }
    
    // Remove user from all clubs (set status to 'removed' instead of deleting)
    $stmt = $pdo->prepare("UPDATE user_clubs SET status = 'removed', processed_at = NOW() WHERE user_id = ? AND status = 'approved'");
    $result = $stmt->execute([$user_id]);
    
    if ($result) {
        $affected_rows = $stmt->rowCount();
        $_SESSION['success'] = "Successfully removed {$user['first_name']} {$user['last_name']} (@{$user['username']}) from {$affected_rows} club(s)";
    } else {
        $_SESSION['error'] = 'Failed to remove member from clubs';
    }
    
} catch (PDOException $e) {
    error_log("Error removing member: " . $e->getMessage());
    $_SESSION['error'] = 'An error occurred while removing the member';
}

header('Location: club_members.php');
exit();
?>
