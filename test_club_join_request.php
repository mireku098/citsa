<?php
/**
 * Test Club Join Request
 * This script simulates a club join request to test the functionality
 */

// Start session
session_start();

// Include database connection and helpers
include 'app/db.conn.php';
include 'app/helpers/club_management.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test Club Join Request</h1>\n";

// Check if we have a user session
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>❌ No user session found. You need to log in first.</p>\n";
    echo "<p><a href='login.php'>Go to Login</a></p>\n";
    exit();
}

$user_id = $_SESSION['user_id'];
echo "<p style='color: green;'>✅ User session found: $user_id</p>\n";

// Get user information
try {
    $stmt = $pdo->prepare("SELECT user_type, student_id, username FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "<p><strong>User Info:</strong></p>\n";
        echo "<ul>\n";
        echo "<li>User ID: {$user_id}</li>\n";
        echo "<li>Username: {$user['username']}</li>\n";
        echo "<li>User Type: {$user['user_type']}</li>\n";
        echo "<li>Student ID: {$user['student_id']}</li>\n";
        echo "</ul>\n";
    } else {
        echo "<p style='color: red;'>❌ User not found in database</p>\n";
        exit();
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error getting user info: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    exit();
}

// Get available clubs
try {
    $stmt = $pdo->query("SELECT id, name FROM clubs LIMIT 5");
    $clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p><strong>Available Clubs:</strong></p>\n";
    echo "<ul>\n";
    foreach ($clubs as $club) {
        echo "<li>ID: {$club['id']} - {$club['name']}</li>\n";
    }
    echo "</ul>\n";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error getting clubs: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    exit();
}

// Test canUserJoinClub function
echo "<h2>Testing canUserJoinClub Function</h2>\n";
try {
    $result = canUserJoinClub($user_id, $pdo);
    echo "<p><strong>Result:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>can_join: " . ($result['can_join'] ? 'true' : 'false') . "</li>\n";
    echo "<li>reason: " . htmlspecialchars($result['reason']) . "</li>\n";
    echo "</ul>\n";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error in canUserJoinClub: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

// Test creating a club join request
if (isset($_GET['club_id']) && is_numeric($_GET['club_id'])) {
    $club_id = (int)$_GET['club_id'];
    echo "<h2>Testing Club Join Request for Club ID: $club_id</h2>\n";
    
    try {
        $result = createClubJoinRequest($user_id, $club_id, $pdo);
        if ($result) {
            echo "<p style='color: green;'>✅ Club join request created successfully!</p>\n";
        } else {
            echo "<p style='color: red;'>❌ Failed to create club join request</p>\n";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error creating club join request: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    }
} else {
    echo "<h2>Test Club Join Request</h2>\n";
    echo "<p>Click a link below to test joining a club:</p>\n";
    echo "<ul>\n";
    foreach ($clubs as $club) {
        echo "<li><a href='?club_id={$club['id']}'>Join {$club['name']}</a></li>\n";
    }
    echo "</ul>\n";
}

// Show current club memberships
echo "<h2>Current Club Memberships</h2>\n";
try {
    $stmt = $pdo->prepare("
        SELECT uc.*, c.name as club_name 
        FROM user_clubs uc 
        JOIN clubs c ON uc.club_id = c.id 
        WHERE uc.user_id = ?
        ORDER BY uc.requested_at DESC
    ");
    $stmt->execute([$user_id]);
    $memberships = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($memberships)) {
        echo "<p>No club memberships found.</p>\n";
    } else {
        echo "<ul>\n";
        foreach ($memberships as $membership) {
            $status_color = $membership['status'] === 'approved' ? 'green' : 
                           ($membership['status'] === 'pending' ? 'orange' : 'red');
            echo "<li style='color: $status_color;'>";
            echo "{$membership['club_name']} - Status: {$membership['status']}";
            if ($membership['requested_at']) {
                echo " (Requested: " . date('M d, Y H:i', strtotime($membership['requested_at'])) . ")";
            }
            echo "</li>\n";
        }
        echo "</ul>\n";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error getting memberships: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}

echo "<hr>\n";
echo "<p><small>Test completed at: " . date('Y-m-d H:i:s') . "</small></p>\n";
?>
