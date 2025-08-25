<?php
session_start();
require_once '../app/db.conn.php';
require_once '../app/helpers/platform_management.php';

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $icons = getAllPlatformIcons($pdo);
    echo json_encode($icons);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
