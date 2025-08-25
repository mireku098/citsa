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
    $response = ['success' => true, 'message' => 'Platform updated successfully'];
    
    // Handle logo upload if provided
    if (isset($_FILES['platform_logo']) && $_FILES['platform_logo']['error'] === UPLOAD_ERR_OK) {
        $logo = $_FILES['platform_logo'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        
        if (!in_array($logo['type'], $allowedTypes)) {
            throw new Exception('Invalid logo format. Only JPG, PNG, and GIF are allowed.');
        }
        
        if ($logo['size'] > 2 * 1024 * 1024) { // 2MB limit
            throw new Exception('Logo file size too large. Maximum size is 2MB.');
        }
        
        // Create uploads directory if it doesn't exist
        $uploadDir = '../uploads/platform/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($logo['name'], PATHINFO_EXTENSION);
        $filename = 'platform-logo-' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        if (move_uploaded_file($logo['tmp_name'], $filepath)) {
            // Update platform logo setting
            setPlatformSetting('platform_logo', 'uploads/platform/' . $filename, $pdo, 'image');
        } else {
            throw new Exception('Failed to upload logo file.');
        }
    }
    
    // Update platform settings
    $settings = [
        'platform_name' => $_POST['platform_name'] ?? '',
        'platform_description' => $_POST['platform_description'] ?? '',
        'general_platform_name' => $_POST['general_platform_name'] ?? '',
        'students_platform_name' => $_POST['students_platform_name'] ?? '',
        'alumni_platform_name' => $_POST['alumni_platform_name'] ?? '',
        'platform_theme_color' => $_POST['platform_theme_color'] ?? ''
    ];
    
    foreach ($settings as $key => $value) {
        if (!empty($value)) {
            setPlatformSetting($key, $value, $pdo);
        }
    }
    
    // Update platform icons
    $icons = [
        'general' => $_POST['general_icon_class'] ?? 'fas fa-graduation-cap',
        'students' => $_POST['students_icon_class'] ?? 'fas fa-user-graduate',
        'alumni' => $_POST['alumni_icon_class'] ?? 'fas fa-user-tie'
    ];
    
    foreach ($icons as $platformType => $iconClass) {
        if (!empty($iconClass)) {
            updatePlatformIcon($platformType, ucfirst($platformType) . ' Icon', $iconClass, '#1a3c6d', $pdo);
        }
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
