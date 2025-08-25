<?php
session_start();
require_once 'app/db.conn.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin/login.php");
    exit();
}

echo "<h2>Cleaning up duplicate events...</h2>";

try {
    // First, let's see what duplicates we have
    $stmt = $pdo->query("
        SELECT title, event_date, event_time, location, event_type, status, created_by, COUNT(*) as count
        FROM events 
        GROUP BY title, event_date, event_time, location, event_type, status, created_by
        HAVING COUNT(*) > 1
        ORDER BY count DESC
    ");
    
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($duplicates)) {
        echo "<p>No duplicate events found!</p>";
        exit();
    }
    
    echo "<h3>Found the following duplicates:</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Title</th><th>Date</th><th>Time</th><th>Location</th><th>Type</th><th>Status</th><th>Count</th></tr>";
    
    foreach ($duplicates as $dup) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($dup['title']) . "</td>";
        echo "<td>" . htmlspecialchars($dup['event_date']) . "</td>";
        echo "<td>" . htmlspecialchars($dup['event_time']) . "</td>";
        echo "<td>" . htmlspecialchars($dup['location']) . "</td>";
        echo "<td>" . htmlspecialchars($dup['event_type']) . "</td>";
        echo "<td>" . htmlspecialchars($dup['status']) . "</td>";
        echo "<td>" . $dup['count'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Now clean up duplicates, keeping only the first occurrence
    echo "<h3>Cleaning up duplicates...</h3>";
    
    foreach ($duplicates as $dup) {
        // Get all IDs for this duplicate group
        $stmt = $pdo->prepare("
            SELECT event_id, created_at 
            FROM events 
            WHERE title = ? AND event_date = ? AND event_time = ? AND location = ? AND event_type = ? AND status = ? AND created_by = ?
            ORDER BY created_at ASC
        ");
        $stmt->execute([
            $dup['title'], 
            $dup['event_date'], 
            $dup['event_time'], 
            $dup['location'], 
            $dup['event_type'], 
            $dup['status'], 
            $dup['created_by']
        ]);
        
        $event_ids = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($event_ids) > 1) {
            // Keep the first one (oldest), delete the rest
            $first_id = $event_ids[0]['event_id'];
            $ids_to_delete = array_slice($event_ids, 1);
            
            $delete_ids = array_column($ids_to_delete, 'event_id');
            $placeholders = str_repeat('?,', count($delete_ids) - 1) . '?';
            
            $stmt = $pdo->prepare("DELETE FROM events WHERE event_id IN ($placeholders)");
            $stmt->execute($delete_ids);
            
            echo "<p>✓ Kept event ID {$first_id}, deleted " . count($delete_ids) . " duplicates for '{$dup['title']}'</p>";
        }
    }
    
    // Verify cleanup
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM events");
    $total = $stmt->fetch()['total'];
    
    echo "<h3>Cleanup completed!</h3>";
    echo "<p>Total events remaining: {$total}</p>";
    
    // Check for remaining duplicates
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM (
            SELECT title, event_date, event_time, location, event_type, status, created_by, COUNT(*) as dup_count
            FROM events 
            GROUP BY title, event_date, event_time, location, event_type, status, created_by
            HAVING COUNT(*) > 1
        ) as duplicates
    ");
    
    $remaining_dups = $stmt->fetch()['count'];
    
    if ($remaining_dups == 0) {
        echo "<p style='color: green;'>✓ All duplicates have been successfully removed!</p>";
    } else {
        echo "<p style='color: orange;'>⚠ {$remaining_dups} duplicate groups still remain. Manual review may be needed.</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<br><a href='admin/events.php'>← Back to Events Management</a>";
?>
