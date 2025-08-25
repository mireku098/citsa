<?php
/**
 * Debug Encryption Session State
 * This script checks the current session and encryption key status
 */

// Start session
session_start();

// Include encryption helper
include 'app/helpers/encryption.php';

echo "<h1>Encryption Session Debug</h1>\n";

// Check session status
echo "<h2>Session Status</h2>\n";
echo "<p>Session ID: " . session_id() . "</p>\n";
echo "<p>Session started: " . (session_status() === PHP_SESSION_ACTIVE ? 'Yes' : 'No') . "</p>\n";

// Check if encryption key exists
echo "<h2>Encryption Key Status</h2>\n";
if (isset($_SESSION['encryption_key'])) {
    echo "<p style='color: green;'>✅ Encryption key exists in session</p>\n";
    echo "<p>Key (first 20 chars): " . substr($_SESSION['encryption_key'], 0, 20) . "...</p>\n";
} else {
    echo "<p style='color: red;'>❌ No encryption key in session</p>\n";
}

// Try to get or create encryption key
echo "<h2>Testing Encryption Key Generation</h2>\n";
try {
    $key = getOrCreateEncryptionKey();
    echo "<p style='color: green;'>✅ Encryption key generated/retrieved successfully</p>\n";
    echo "<p>Key (first 20 chars): " . substr($key, 0, 20) . "...</p>\n";
    
    // Test encryption/decryption
    $test_message = "Test message for debugging";
    $encrypted = encryptMessage($test_message, $key);
    $decrypted = decryptMessage($encrypted, $key);
    
    echo "<p>Test message: " . htmlspecialchars($test_message) . "</p>\n";
    echo "<p>Encrypted: " . htmlspecialchars(substr($encrypted, 0, 50)) . "...</p>\n";
    echo "<p>Decrypted: " . htmlspecialchars($decrypted) . "</p>\n";
    
    if ($test_message === $decrypted) {
        echo "<p style='color: green;'>✅ Encryption/Decryption test PASSED</p>\n";
    } else {
        echo "<p style='color: red;'>❌ Encryption/Decryption test FAILED</p>\n";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

// Check if functions exist
echo "<h2>Function Availability</h2>\n";
$functions = [
    'encryptMessage',
    'decryptMessage', 
    'encryptMessageSafely',
    'decryptMessageSafely',
    'getOrCreateEncryptionKey'
];

foreach ($functions as $func) {
    if (function_exists($func)) {
        echo "<p style='color: green;'>✅ $func function exists</p>\n";
    } else {
        echo "<p style='color: red;'>❌ $func function missing</p>\n";
    }
}

// Check session data
echo "<h2>Session Data</h2>\n";
if (empty($_SESSION)) {
    echo "<p>Session is empty</p>\n";
} else {
    echo "<p>Session contains " . count($_SESSION) . " keys:</p>\n";
    echo "<ul>\n";
    foreach ($_SESSION as $key => $value) {
        if (is_string($value) && strlen($value) > 50) {
            echo "<li>$key: " . substr($value, 0, 50) . "...</li>\n";
        } else {
            echo "<li>$key: " . htmlspecialchars(var_export($value, true)) . "</li>\n";
        }
    }
    echo "</ul>\n";
}

echo "<hr>\n";
echo "<p><small>Debug completed at: " . date('Y-m-d H:i:s') . "</small></p>\n";
?>
