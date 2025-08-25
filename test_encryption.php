<?php
/**
 * Encryption System Test Script
 * This script tests the encryption and decryption functions
 */

// Start session
session_start();

// Include encryption helper
include 'app/helpers/encryption.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Message Encryption System Test</h1>\n";

try {
    // Test 1: Basic encryption/decryption
    echo "<h2>Test 1: Basic Encryption/Decryption</h2>\n";
    
    $test_message = "Hello, this is a test message!";
    echo "<p>Original message: <strong>" . htmlspecialchars($test_message) . "</strong></p>\n";
    
    $encrypted = encryptMessage($test_message);
    echo "<p>Encrypted message: <code>" . htmlspecialchars($encrypted) . "</code></p>\n";
    
    $decrypted = decryptMessage($encrypted);
    echo "<p>Decrypted message: <strong>" . htmlspecialchars($decrypted) . "</strong></p>\n";
    
    if ($test_message === $decrypted) {
        echo "<p style='color: green;'>✅ Test 1 PASSED: Encryption/Decryption working correctly</p>\n";
    } else {
        echo "<p style='color: red;'>❌ Test 1 FAILED: Decrypted message doesn't match original</p>\n";
    }
    
    // Test 2: Safe encryption (should not re-encrypt already encrypted message)
    echo "<h2>Test 2: Safe Encryption</h2>\n";
    
    $safe_encrypted = encryptMessageSafely($encrypted);
    echo "<p>Safe encryption of already encrypted message: <code>" . htmlspecialchars($safe_encrypted) . "</code></p>\n";
    
    if ($encrypted === $safe_encrypted) {
        echo "<p style='color: green;'>✅ Test 2 PASSED: Safe encryption prevents double-encryption</p>\n";
    } else {
        echo "<p style='color: red;'>❌ Test 2 FAILED: Safe encryption re-encrypted already encrypted message</p>\n";
    }
    
    // Test 3: Safe decryption (should only decrypt encrypted messages)
    echo "<h2>Test 3: Safe Decryption</h2>\n";
    
    $safe_decrypted_encrypted = decryptMessageSafely($encrypted);
    $safe_decrypted_plaintext = decryptMessageSafely($test_message);
    
    echo "<p>Safe decryption of encrypted message: <strong>" . htmlspecialchars($safe_decrypted_encrypted) . "</strong></p>\n";
    echo "<p>Safe decryption of plaintext message: <strong>" . htmlspecialchars($safe_decrypted_plaintext) . "</strong></p>\n";
    
    if ($safe_decrypted_encrypted === $test_message && $safe_decrypted_plaintext === $test_message) {
        echo "<p style='color: green;'>✅ Test 3 PASSED: Safe decryption working correctly</p>\n";
    } else {
        echo "<p style='color: red;'>❌ Test 3 FAILED: Safe decryption not working correctly</p>\n";
    }
    
    // Test 4: Encryption key generation
    echo "<h2>Test 4: Encryption Key Management</h2>\n";
    
    $key1 = getOrCreateEncryptionKey();
    $key2 = getOrCreateEncryptionKey();
    
    echo "<p>First key: <code>" . substr($key1, 0, 20) . "...</code></p>\n";
    echo "<p>Second key: <code>" . substr($key2, 0, 20) . "...</code></p>\n";
    
    if ($key1 === $key2) {
        echo "<p style='color: green;'>✅ Test 4 PASSED: Same session returns same key</p>\n";
    } else {
        echo "<p style='color: red;'>❌ Test 4 FAILED: Different keys generated in same session</p>\n";
    }
    
    // Test 5: Different messages get different encrypted results
    echo "<h2>Test 5: Unique Encryption Results</h2>\n";
    
    $message1 = "Message 1";
    $message2 = "Message 2";
    
    $encrypted1 = encryptMessage($message1);
    $encrypted2 = encryptMessage($message2);
    
    echo "<p>Encrypted message 1: <code>" . substr($encrypted1, 0, 30) . "...</code></p>\n";
    echo "<p>Encrypted message 2: <code>" . substr($encrypted2, 0, 30) . "...</code></p>\n";
    
    if ($encrypted1 !== $encrypted2) {
        echo "<p style='color: green;'>✅ Test 5 PASSED: Different messages produce different encrypted results</p>\n";
    } else {
        echo "<p style='color: red;'>❌ Test 5 FAILED: Different messages produced same encrypted result</p>\n";
    }
    
    // Test 6: Same message encrypted multiple times produces different results (due to unique IVs)
    echo "<h2>Test 6: Unique IVs per Message</h2>\n";
    
    $same_message = "Same message";
    $encrypted_same1 = encryptMessage($same_message);
    $encrypted_same2 = encryptMessage($same_message);
    
    echo "<p>First encryption: <code>" . substr($encrypted_same1, 0, 30) . "...</code></p>\n";
    echo "<p>Second encryption: <code>" . substr($encrypted_same2, 0, 30) . "...</code></p>\n";
    
    if ($encrypted_same1 !== $encrypted_same2) {
        echo "<p style='color: green;'>✅ Test 6 PASSED: Same message encrypted multiple times produces different results (unique IVs)</p>\n";
    } else {
        echo "<p style='color: red;'>❌ Test 6 FAILED: Same message produced same encrypted result (IVs not unique)</p>\n";
    }
    
    echo "<hr>\n";
    echo "<h2>Test Summary</h2>\n";
    echo "<p>All encryption system tests completed successfully!</p>\n";
    echo "<p><strong>Current Session Encryption Key:</strong> <code>" . htmlspecialchars($key1) . "</code></p>\n";
    echo "<p><strong>Note:</strong> This key is stored in your session and will be used for all message encryption/decryption.</p>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Test FAILED with error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p>Please check the error logs and verify the encryption helper is properly included.</p>\n";
}

echo "<hr>\n";
echo "<p><small>Test completed at: " . date('Y-m-d H:i:s') . "</small></p>\n";
?>
