<?php
/**
 * Message Encryption Helper Functions
 * Provides secure encryption/decryption for user messages using AES-256-CBC
 */

// Encryption configuration
define('ENCRYPTION_METHOD', 'AES-256-CBC');
define('ENCRYPTION_KEY_LENGTH', 32); // 256 bits
define('ENCRYPTION_IV_LENGTH', 16);  // 128 bits

if (!function_exists('generateEncryptionKey')) {
    /**
     * Generate a new encryption key
     * @return string Base64 encoded encryption key
     */
    function generateEncryptionKey() {
        return base64_encode(random_bytes(ENCRYPTION_KEY_LENGTH));
    }
}

if (!function_exists('getOrCreateEncryptionKey')) {
    /**
     * Get the encryption key from session or create a new one
     * @return string Base64 encoded encryption key
     */
    function getOrCreateEncryptionKey() {
        if (!isset($_SESSION['encryption_key'])) {
            $_SESSION['encryption_key'] = generateEncryptionKey();
        }
        return $_SESSION['encryption_key'];
    }
}

if (!function_exists('encryptMessage')) {
    /**
     * Encrypt a message using AES-256-CBC
     * @param string $message The plaintext message to encrypt
     * @param string $encryption_key Base64 encoded encryption key
     * @return string Base64 encoded encrypted message with IV
     */
    function encryptMessage($message, $encryption_key = null) {
        if ($encryption_key === null) {
            $encryption_key = getOrCreateEncryptionKey();
        }
        
        // Decode the base64 key
        $key = base64_decode($encryption_key);
        
        // Generate a random IV
        $iv = random_bytes(ENCRYPTION_IV_LENGTH);
        
        // Encrypt the message
        $encrypted = openssl_encrypt($message, ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv);
        
        if ($encrypted === false) {
            throw new Exception('Encryption failed: ' . openssl_error_string());
        }
        
        // Combine IV and encrypted data, then base64 encode
        $encrypted_data = $iv . $encrypted;
        return base64_encode($encrypted_data);
    }
}

if (!function_exists('decryptMessage')) {
    /**
     * Decrypt a message using AES-256-CBC
     * @param string $encrypted_message Base64 encoded encrypted message with IV
     * @param string $encryption_key Base64 encoded encryption key
     * @return string The decrypted plaintext message
     */
    function decryptMessage($encrypted_message, $encryption_key = null) {
        if ($encryption_key === null) {
            $encryption_key = getOrCreateEncryptionKey();
        }
        
        try {
            // Decode the base64 encrypted data
            $encrypted_data = base64_decode($encrypted_message);
            
            // Decode the base64 key
            $key = base64_decode($encryption_key);
            
            // Extract IV and encrypted data
            $iv = substr($encrypted_data, 0, ENCRYPTION_IV_LENGTH);
            $encrypted = substr($encrypted_data, ENCRYPTION_IV_LENGTH);
            
            // Decrypt the message
            $decrypted = openssl_decrypt($encrypted, ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv);
            
            if ($decrypted === false) {
                throw new Exception('Decryption failed: ' . openssl_error_string());
            }
            
            return $decrypted;
        } catch (Exception $e) {
            // If decryption fails, return the original message (for backward compatibility)
            error_log("Message decryption failed: " . $e->getMessage());
            return $encrypted_message;
        }
    }
}

if (!function_exists('isEncryptedMessage')) {
    /**
     * Check if a message appears to be encrypted
     * @param string $message The message to check
     * @return bool True if the message appears to be encrypted
     */
    function isEncryptedMessage($message) {
        // Check if the message is base64 encoded and has the expected length
        if (!preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $message)) {
            return false;
        }
        
        $decoded = base64_decode($message);
        return strlen($decoded) >= ENCRYPTION_IV_LENGTH;
    }
}

if (!function_exists('encryptMessageSafely')) {
    /**
     * Encrypt a message only if it's not already encrypted
     * @param string $message The message to encrypt
     * @param string $encryption_key Base64 encoded encryption key
     * @return string The encrypted message (or original if already encrypted)
     */
    function encryptMessageSafely($message, $encryption_key = null) {
        // Don't encrypt if already encrypted
        if (isEncryptedMessage($message)) {
            return $message;
        }
        
        return encryptMessage($message, $encryption_key);
    }
}

if (!function_exists('decryptMessageSafely')) {
    /**
     * Decrypt a message only if it appears to be encrypted
     * @param string $message The message to decrypt
     * @param string $encryption_key Base64 encoded encryption key
     * @return string The decrypted message (or original if not encrypted)
     */
    function decryptMessageSafely($message, $encryption_key = null) {
        // Only decrypt if it appears to be encrypted
        if (isEncryptedMessage($message)) {
            return decryptMessage($message, $encryption_key);
        }
        
        return $message;
    }
}

if (!function_exists('migrateExistingMessages')) {
    /**
     * Migrate existing unencrypted messages to encrypted format
     * @param PDO $pdo Database connection
     * @param string $table_name The messages table name
     * @param string $message_column The message column name
     * @return int Number of messages migrated
     */
    function migrateExistingMessages($pdo, $table_name, $message_column) {
        try {
            // Get all unencrypted messages
            $stmt = $pdo->prepare("SELECT id, $message_column FROM $table_name WHERE $message_column IS NOT NULL AND $message_column != ''");
            $stmt->execute();
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $migrated_count = 0;
            
            foreach ($messages as $message) {
                $plaintext = $message[$message_column];
                
                // Skip if already encrypted
                if (isEncryptedMessage($plaintext)) {
                    continue;
                }
                
                // Encrypt the message
                $encrypted = encryptMessage($plaintext);
                
                // Update the database
                $update_stmt = $pdo->prepare("UPDATE $table_name SET $message_column = ? WHERE id = ?");
                if ($update_stmt->execute([$encrypted, $message['id']])) {
                    $migrated_count++;
                }
            }
            
            return $migrated_count;
        } catch (Exception $e) {
            error_log("Message migration failed: " . $e->getMessage());
            return 0;
        }
    }
}
?>
