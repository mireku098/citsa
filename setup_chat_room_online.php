<?php
/**
 * Chat Room Online Status Setup Script
 * Run this script to set up the online status tracking system
 */

require_once 'app/db.conn.php';

echo "<h2>Chat Room Online Status Setup</h2>";

try {
    // Create chat_room_online_users table
    $sql = "CREATE TABLE IF NOT EXISTS `chat_room_online_users` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `room_id` varchar(100) NOT NULL,
        `last_activity` datetime DEFAULT CURRENT_TIMESTAMP,
        `is_active` tinyint(1) DEFAULT 1,
        PRIMARY KEY (`id`),
        UNIQUE KEY `user_room` (`user_id`, `room_id`),
        KEY `room_id` (`room_id`),
        KEY `last_activity` (`last_activity`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $pdo->exec($sql);
    echo "✅ Chat room online users table created successfully<br>";
    
    echo "<br><strong>🎉 Chat Room Online Status Setup Complete!</strong><br>";
    echo "The online status tracking system is now ready to use.<br>";
    echo "Users will now see how many people are online in each chat room.<br>";
    echo "<br><a href='chat_room.php'>Go to Chat Rooms</a>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
