<?php
/**
 * Setup Script for Chat Room File Attachments
 * Run this script once to add file attachment support to your database
 */

// Database configuration
$host = 'localhost';
$dbname = 'citsa';
$username = 'root';
$password = '';

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Setting up Chat Room File Attachments...</h2>";
    
    // Check if columns already exist
    $stmt = $pdo->prepare("SHOW COLUMNS FROM chat_room_messages LIKE 'message_type'");
    $stmt->execute();
    $exists = $stmt->fetch();
    
    if ($exists) {
        echo "<p style='color: green;'>✅ File attachment columns already exist!</p>";
    } else {
        // Add file attachment columns
        $sql = "ALTER TABLE `chat_room_messages` 
                ADD COLUMN `message_type` ENUM('text', 'image', 'file') DEFAULT 'text' AFTER `message`,
                ADD COLUMN `file_url` VARCHAR(500) NULL AFTER `message_type`,
                ADD COLUMN `file_name` VARCHAR(255) NULL AFTER `file_url`,
                ADD COLUMN `file_size` INT(11) NULL AFTER `file_name`";
        
        $pdo->exec($sql);
        echo "<p style='color: green;'>✅ File attachment columns added successfully!</p>";
        
        // Update existing messages to have text type
        $updateSql = "UPDATE `chat_room_messages` SET `message_type` = 'text' WHERE `message_type` IS NULL";
        $pdo->exec($updateSql);
        echo "<p style='color: green;'>✅ Existing messages updated to text type!</p>";
    }
    
    // Create uploads directory
    $uploadDir = 'uploads/chat_rooms/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
        echo "<p style='color: green;'>✅ Uploads directory created: $uploadDir</p>";
    } else {
        echo "<p style='color: green;'>✅ Uploads directory already exists: $uploadDir</p>";
    }
    
    // Create .htaccess file
    $htaccessFile = $uploadDir . '.htaccess';
    if (!file_exists($htaccessFile)) {
        $htaccessContent = "# Deny direct access to uploaded files for security
<Files \"*\">
    Order Deny,Allow
    Deny from all
</Files>

# Allow access only to image files for display
<FilesMatch \"\.(jpg|jpeg|png|gif|webp)$\">
    Order Allow,Deny
    Allow from all
</FilesMatch>

# Deny access to all other file types
<FilesMatch \"\.(pdf|doc|docx|txt)$\">
    Order Deny,Allow
    Deny from all
</FilesMatch>";
        
        file_put_contents($htaccessFile, $htaccessContent);
        echo "<p style='color: green;'>✅ .htaccess security file created!</p>";
    } else {
        echo "<p style='color: green;'>✅ .htaccess security file already exists!</p>";
    }
    
    echo "<h3 style='color: green;'>🎉 Setup Complete!</h3>";
    echo "<p>Chat room file attachments are now ready to use!</p>";
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ul>";
    echo "<li>Go to any chat room</li>";
    echo "<li>Click the paperclip icon to attach files</li>";
    echo "<li>Upload images, PDFs, Word documents, or text files</li>";
    echo "<li>Click on images to view them full-size</li>";
    echo "<li>Download files using the download button</li>";
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Database Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database connection settings.</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 50px auto;
    padding: 20px;
    background: #f5f5f5;
}

h2, h3 {
    color: #333;
}

p {
    margin: 10px 0;
    padding: 10px;
    background: white;
    border-radius: 5px;
    border-left: 4px solid #007bff;
}

ul {
    background: white;
    padding: 20px 40px;
    border-radius: 5px;
    border-left: 4px solid #28a745;
}

li {
    margin: 8px 0;
}
</style>
