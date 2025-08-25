<?php
/**
 * Platform Management Helper Functions
 * Handles platform customization, settings, and icons
 */

if (!function_exists('getPlatformSetting')) {
    /**
     * Get a platform setting value
     * @param string $key Setting key
     * @param PDO $pdo Database connection
     * @param string $default Default value if setting not found
     * @return string Setting value or default
     */
    function getPlatformSetting($key, $pdo, $default = '') {
        try {
            $stmt = $pdo->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['setting_value'] : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
}

if (!function_exists('setPlatformSetting')) {
    /**
     * Set a platform setting value
     * @param string $key Setting key
     * @param string $value Setting value
     * @param PDO $pdo Database connection
     * @param string $type Setting type (text, image, json)
     * @return bool Success status
     */
    function setPlatformSetting($key, $value, $pdo, $type = 'text') {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO platform_settings (setting_key, setting_value, setting_type) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value), 
                setting_type = VALUES(setting_type),
                updated_at = CURRENT_TIMESTAMP
            ");
            return $stmt->execute([$key, $value, $type]);
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('getPlatformIcon')) {
    /**
     * Get platform icon information
     * @param string $platformType Platform type
     * @param PDO $pdo Database connection
     * @return array Icon information
     */
    function getPlatformIcon($platformType, $pdo) {
        try {
            $stmt = $pdo->prepare("
                SELECT icon_name, icon_class, icon_color, is_active 
                FROM platform_icons 
                WHERE platform_type = ? AND is_active = 1
            ");
            $stmt->execute([$platformType]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return $result;
            }
            
            // Return default icon if not found
            $defaultIcons = [
                'general' => ['icon_name' => 'CITSA Logo', 'icon_class' => 'fas fa-graduation-cap', 'icon_color' => '#1a3c6d'],
                'students' => ['icon_name' => 'Student Icon', 'icon_class' => 'fas fa-user-graduate', 'icon_color' => '#007bff'],
                'alumni' => ['icon_name' => 'Alumni Icon', 'icon_class' => 'fas fa-user-tie', 'icon_color' => '#28a745'],
                'year_based' => ['icon_name' => 'Level Icon', 'icon_class' => 'fas fa-layer-group', 'icon_color' => '#ffc107'],
                'program' => ['icon_name' => 'Program Icon', 'icon_class' => 'fas fa-code', 'icon_color' => '#dc3545'],
                'club' => ['icon_name' => 'Club Icon', 'icon_class' => 'fas fa-users', 'icon_color' => '#6f42c1']
            ];
            
            return $defaultIcons[$platformType] ?? ['icon_name' => 'Platform', 'icon_class' => 'fas fa-comments', 'icon_color' => '#6c757d'];
        } catch (Exception $e) {
            return ['icon_name' => 'Platform', 'icon_class' => 'fas fa-comments', 'icon_color' => '#6c757d'];
        }
    }
}

if (!function_exists('getAllPlatformSettings')) {
    /**
     * Get all platform settings
     * @param PDO $pdo Database connection
     * @return array All platform settings
     */
    function getAllPlatformSettings($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM platform_settings ORDER BY setting_key");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}

if (!function_exists('getAllPlatformIcons')) {
    /**
     * Get all platform icons
     * @param PDO $pdo Database connection
     * @return array All platform icons
     */
    function getAllPlatformIcons($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM platform_icons ORDER BY platform_type");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}

if (!function_exists('updatePlatformIcon')) {
    /**
     * Update platform icon
     * @param string $platformType Platform type
     * @param string $iconName Icon name
     * @param string $iconClass Icon CSS class
     * @param string $iconColor Icon color
     * @param PDO $pdo Database connection
     * @return bool Success status
     */
    function updatePlatformIcon($platformType, $iconName, $iconClass, $iconColor, $pdo) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO platform_icons (platform_type, icon_name, icon_class, icon_color) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                icon_name = VALUES(icon_name), 
                icon_class = VALUES(icon_class),
                icon_color = VALUES(icon_color),
                updated_at = CURRENT_TIMESTAMP
            ");
            return $stmt->execute([$platformType, $iconName, $iconClass, $iconColor]);
        } catch (Exception $e) {
            return false;
        }
    }
}
?>
