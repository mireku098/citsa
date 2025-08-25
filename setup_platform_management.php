<?php
/**
 * Platform Management Setup Script
 * Run this script to set up the platform management system
 */

require_once 'app/db.conn.php';

echo "<h2>Platform Management Setup</h2>";

try {
    // Create platform_settings table
    $sql = "CREATE TABLE IF NOT EXISTS `platform_settings` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `setting_key` varchar(100) NOT NULL,
        `setting_value` text NOT NULL,
        `setting_type` enum('text', 'image', 'json') DEFAULT 'text',
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `setting_key` (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $pdo->exec($sql);
    echo "✅ Platform settings table created successfully<br>";
    
    // Create platform_icons table
    $sql = "CREATE TABLE IF NOT EXISTS `platform_icons` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `platform_type` varchar(50) NOT NULL,
        `icon_name` varchar(100) NOT NULL,
        `icon_class` varchar(100) NOT NULL,
        `icon_color` varchar(7) DEFAULT '#1a3c6d',
        `is_active` tinyint(1) DEFAULT 1,
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `platform_type` (`platform_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $pdo->exec($sql);
    echo "✅ Platform icons table created successfully<br>";
    
    // Insert default platform settings
    $defaultSettings = [
        ['platform_name', 'CITSA Connect', 'text'],
        ['platform_description', 'Student-Alumni Platform', 'text'],
        ['platform_logo', 'citsa-logo.png', 'text'],
        ['general_platform_name', 'General Platform', 'text'],
        ['general_platform_description', 'CITSA Student-Alumni Platform', 'text'],
        ['students_platform_name', 'Students Only', 'text'],
        ['students_platform_description', 'Student Platform', 'text'],
        ['alumni_platform_name', 'Alumni Network', 'text'],
        ['alumni_platform_description', 'CITSA Alumni Platform', 'text'],
        ['platform_theme_color', '#1a3c6d', 'text'],
        ['platform_accent_color', '#007bff', 'text']
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO platform_settings (setting_key, setting_value, setting_type) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
        setting_value = VALUES(setting_value), 
        setting_type = VALUES(setting_type)
    ");
    
    foreach ($defaultSettings as $setting) {
        $stmt->execute($setting);
    }
    echo "✅ Default platform settings inserted successfully<br>";
    
    // Insert default platform icons
    $defaultIcons = [
        ['general', 'CITSA Logo', 'fas fa-graduation-cap', '#1a3c6d'],
        ['students', 'Student Icon', 'fas fa-user-graduate', '#007bff'],
        ['alumni', 'Alumni Icon', 'fas fa-user-tie', '#28a745'],
        ['year_based', 'Level Icon', 'fas fa-layer-group', '#ffc107'],
        ['program', 'Program Icon', 'fas fa-code', '#dc3545'],
        ['club', 'Club Icon', 'fas fa-users', '#6f42c1']
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO platform_icons (platform_type, icon_name, icon_class, icon_color) 
        VALUES (?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
        icon_name = VALUES(icon_name), 
        icon_class = VALUES(icon_class),
        icon_color = VALUES(icon_color)
    ");
    
    foreach ($defaultIcons as $icon) {
        $stmt->execute($icon);
    }
    echo "✅ Default platform icons inserted successfully<br>";
    
    // Create uploads directory
    $uploadDir = 'uploads/platform/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
        echo "✅ Platform uploads directory created successfully<br>";
    } else {
        echo "✅ Platform uploads directory already exists<br>";
    }
    
    // Create .htaccess file
    $htaccessContent = "# Deny access to all files except images
<FilesMatch \"^(?!.*\.(jpg|jpeg|png|gif|webp)$)\">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# Allow access to image files
<FilesMatch \"\.(jpg|jpeg|png|gif|webp)$\">
    Order Allow,Deny
    Allow from all
</FilesMatch>

# Prevent script execution
<FilesMatch \"\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)$\">
    Order Allow,Deny
    Deny from all
</FilesMatch>";
    
    $htaccessFile = $uploadDir . '.htaccess';
    if (!file_exists($htaccessFile)) {
        file_put_contents($htaccessFile, $htaccessContent);
        echo "✅ .htaccess file created successfully<br>";
    } else {
        echo "✅ .htaccess file already exists<br>";
    }
    
    echo "<br><strong>🎉 Platform Management Setup Complete!</strong><br>";
    echo "You can now access the Platform tab in the admin dashboard to customize your platform.<br>";
    echo "<a href='admin/index.php'>Go to Admin Dashboard</a>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
