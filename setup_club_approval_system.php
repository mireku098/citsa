<?php
/**
 * Club Approval System Database Setup
 * This script sets up the database structure needed for the club approval system
 */

// Include database connection
include 'app/db.conn.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Club Approval System Database Setup</h1>\n";
echo "<p>This script will set up the database structure needed for the club approval system.</p>\n";

try {
    // Check if user_clubs table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_clubs'");
    $table_exists = $stmt->fetch();
    
    if (!$table_exists) {
        echo "<h2>Creating user_clubs table...</h2>\n";
        
        // Create user_clubs table
        $create_table_sql = "
            CREATE TABLE user_clubs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                club_id INT NOT NULL,
                status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                approved_at TIMESTAMP NULL,
                processed_at TIMESTAMP NULL,
                rejection_reason TEXT NULL,
                joined_at TIMESTAMP NULL,
                UNIQUE KEY unique_user_club (user_id, club_id),
                INDEX idx_status (status),
                INDEX idx_user_id (user_id),
                INDEX idx_club_id (club_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $pdo->exec($create_table_sql);
        echo "<p style='color: green;'>✅ user_clubs table created successfully</p>\n";
    } else {
        echo "<p style='color: blue;'>ℹ️ user_clubs table already exists</p>\n";
    }
    
    // Check and add status column if it doesn't exist
    $stmt = $pdo->query("SHOW COLUMNS FROM user_clubs LIKE 'status'");
    $status_column_exists = $stmt->fetch();
    
    if (!$status_column_exists) {
        echo "<h2>Adding status column...</h2>\n";
        
        $add_status_sql = "ALTER TABLE user_clubs ADD COLUMN status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' AFTER club_id";
        $pdo->exec($add_status_sql);
        echo "<p style='color: green;'>✅ status column added successfully</p>\n";
    } else {
        echo "<p style='color: blue;'>ℹ️ status column already exists</p>\n";
    }
    
    // Check and add requested_at column if it doesn't exist
    $stmt = $pdo->query("SHOW COLUMNS FROM user_clubs LIKE 'requested_at'");
    $requested_at_column_exists = $stmt->fetch();
    
    if (!$requested_at_column_exists) {
        echo "<h2>Adding requested_at column...</h2>\n";
        
        $add_requested_at_sql = "ALTER TABLE user_clubs ADD COLUMN requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER status";
        $pdo->exec($add_requested_at_sql);
        echo "<p style='color: green;'>✅ requested_at column added successfully</p>\n";
    } else {
        echo "<p style='color: blue;'>ℹ️ requested_at column already exists</p>\n";
    }
    
    // Check and add approved_at column if it doesn't exist
    $stmt = $pdo->query("SHOW COLUMNS FROM user_clubs LIKE 'approved_at'");
    $approved_at_column_exists = $stmt->fetch();
    
    if (!$approved_at_column_exists) {
        echo "<h2>Adding approved_at column...</h2>\n";
        
        $add_approved_at_sql = "ALTER TABLE user_clubs ADD COLUMN approved_at TIMESTAMP NULL AFTER requested_at";
        $pdo->exec($add_approved_at_sql);
        echo "<p style='color: green;'>✅ approved_at column added successfully</p>\n";
    } else {
        echo "<p style='color: blue;'>ℹ️ approved_at column already exists</p>\n";
    }
    
    // Check and add processed_at column if it doesn't exist
    $stmt = $pdo->query("SHOW COLUMNS FROM user_clubs LIKE 'processed_at'");
    $processed_at_column_exists = $stmt->fetch();
    
    if (!$processed_at_column_exists) {
        echo "<h2>Adding processed_at column...</h2>\n";
        
        $add_processed_at_sql = "ALTER TABLE user_clubs ADD COLUMN processed_at TIMESTAMP NULL AFTER approved_at";
        $pdo->exec($add_processed_at_sql);
        echo "<p style='color: green;'>✅ processed_at column added successfully</p>\n";
    } else {
        echo "<p style='color: blue;'>ℹ️ processed_at column already exists</p>\n";
    }
    
    // Check and add rejection_reason column if it doesn't exist
    $stmt = $pdo->query("SHOW COLUMNS FROM user_clubs LIKE 'rejection_reason'");
    $rejection_reason_column_exists = $stmt->fetch();
    
    if (!$rejection_reason_column_exists) {
        echo "<h2>Adding rejection_reason column...</h2>\n";
        
        $add_rejection_reason_sql = "ALTER TABLE user_clubs ADD COLUMN rejection_reason TEXT NULL AFTER processed_at";
        $pdo->exec($add_rejection_reason_sql);
        echo "<p style='color: green;'>✅ rejection_reason column added successfully</p>\n";
    } else {
        echo "<p style='color: blue;'>ℹ️ rejection_reason column already exists</p>\n";
    }
    
    // Check and add joined_at column if it doesn't exist
    $stmt = $pdo->query("SHOW COLUMNS FROM user_clubs LIKE 'joined_at'");
    $joined_at_column_exists = $stmt->fetch();
    
    if (!$joined_at_column_exists) {
        echo "<h2>Adding joined_at column...</h2>\n";
        
        $add_joined_at_sql = "ALTER TABLE user_clubs ADD COLUMN joined_at TIMESTAMP NULL AFTER rejection_reason";
        $pdo->exec($add_joined_at_sql);
        echo "<p style='color: green;'>✅ joined_at column added successfully</p>\n";
    } else {
        echo "<p style='color: blue;'>ℹ️ joined_at column already exists</p>\n";
    }
    
    // Add indexes for better performance
    echo "<h2>Adding database indexes...</h2>\n";
    
    try {
        $pdo->exec("ALTER TABLE user_clubs ADD INDEX idx_status (status)");
        echo "<p style='color: green;'>✅ status index added successfully</p>\n";
    } catch (Exception $e) {
        echo "<p style='color: blue;'>ℹ️ status index already exists</p>\n";
    }
    
    try {
        $pdo->exec("ALTER TABLE user_clubs ADD INDEX idx_user_id (user_id)");
        echo "<p style='color: green;'>✅ user_id index added successfully</p>\n>";
    } catch (Exception $e) {
        echo "<p style='color: blue;'>ℹ️ user_id index already exists</p>\n";
    }
    
    try {
        $pdo->exec("ALTER TABLE user_clubs ADD INDEX idx_club_id (club_id)");
        echo "<p style='color: green;'>✅ club_id index added successfully</p>\n>";
    } catch (Exception $e) {
        echo "<p style='color: blue;'>ℹ️ club_id index already exists</p>\n";
    }
    
    // Update existing records to have proper status
    echo "<h2>Updating existing records...</h2>\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM user_clubs WHERE status IS NULL OR status = ''");
    $null_status_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($null_status_count > 0) {
        $update_sql = "UPDATE user_clubs SET status = 'approved', joined_at = NOW() WHERE status IS NULL OR status = ''";
        $pdo->exec($update_sql);
        echo "<p style='color: green;'>✅ Updated $null_status_count existing records to 'approved' status</p>\n";
    } else {
        echo "<p style='color: blue;'>ℹ️ No existing records need updating</p>\n";
    }
    
    echo "<hr>\n";
    echo "<h2>Setup Complete!</h2>\n";
    echo "<p style='color: green;'>✅ The club approval system database structure has been set up successfully.</p>\n";
    echo "<p><strong>What was set up:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>user_clubs table (if it didn't exist)</li>\n";
    echo "<li>status column for tracking request status (pending/approved/rejected)</li>\n";
    echo "<li>requested_at column for when the request was made</li>\n";
    echo "<li>approved_at column for when the request was approved</li>\n";
    echo "<li>processed_at column for when the request was processed</li>\n";
    echo "<li>rejection_reason column for storing rejection reasons</li>\n";
    echo "<li>joined_at column for when the user actually joined</li>\n";
    echo "<li>Database indexes for better performance</li>\n";
    echo "</ul>\n";
    echo "<p><strong>Next steps:</strong></p>\n";
    echo "<ol>\n";
    echo "<li>Test the club join functionality in the chat room</li>\n";
    echo "<li>Use the admin panel to approve/reject club join requests</li>\n";
    echo "<li>Verify that only approved members can send messages in club chats</li>\n";
    echo "</ol>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error during setup: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p>Please check the error logs and try again.</p>\n";
}

echo "<hr>\n";
echo "<p><small>Setup completed at: " . date('Y-m-d H:i:s') . "</small></p>\n";
?>
