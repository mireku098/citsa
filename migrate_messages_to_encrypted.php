<?php
/**
 * Message Encryption Migration Script
 * This script encrypts all existing unencrypted messages in the database
 * Run this script once after implementing message encryption
 */

// Start session to get encryption key
session_start();

// Include database connection and encryption helper
include 'app/db.conn.php';
include 'app/helpers/encryption.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Message Encryption Migration</h1>\n";
echo "<p>This script will encrypt all existing unencrypted messages in the database.</p>\n";

try {
    // Generate encryption key if not exists
    $encryption_key = getOrCreateEncryptionKey();
    echo "<p>Using encryption key: " . substr($encryption_key, 0, 20) . "...</p>\n";
    
    // Migrate private messages
    echo "<h2>Migrating Private Messages...</h2>\n";
    $private_migrated = migrateExistingMessages($pdo, 'messages', 'message');
    echo "<p>Migrated $private_migrated private messages</p>\n";
    
    // Migrate chat room messages
    echo "<h2>Migrating Chat Room Messages...</h2>\n";
    $chat_room_migrated = migrateExistingMessages($pdo, 'chat_room_messages', 'message');
    echo "<p>Migrated $chat_room_migrated chat room messages</p>\n";
    
    // Total migrated
    $total_migrated = $private_migrated + $chat_room_migrated;
    echo "<h2>Migration Complete!</h2>\n";
    echo "<p>Total messages migrated: $total_migrated</p>\n";
    
    if ($total_migrated > 0) {
        echo "<p><strong>Important:</strong> All existing messages have been encrypted. The encryption key is stored in your session.</p>\n";
        echo "<p>Make sure to save this encryption key securely for future access to these messages.</p>\n";
        echo "<p>Encryption Key: $encryption_key</p>\n";
    } else {
        echo "<p>No messages needed migration (all were already encrypted or no messages exist).</p>\n";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error during migration: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p>Please check the error logs and try again.</p>\n";
}

echo "<hr>\n";
echo "<p><small>Migration completed at: " . date('Y-m-d H:i:s') . "</small></p>\n";
?>
