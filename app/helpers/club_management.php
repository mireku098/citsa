<?php
/**
 * Club Management Helper Functions
 * Handles club join requests, approvals, and user restrictions
 */

if (!function_exists('canUserJoinClub')) {
    /**
     * Check if a user can join clubs
     * @param int $user_id User ID
     * @param PDO $pdo Database connection
     * @return array Array with 'can_join' boolean and 'reason' string
     */
    function canUserJoinClub($user_id, $pdo) {
        try {
            // Get user information
            $stmt = $pdo->prepare("SELECT user_type, student_id FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return ['can_join' => false, 'reason' => 'User not found'];
            }
            
            // Only students can join clubs
            if ($user['user_type'] !== 'student') {
                return ['can_join' => false, 'reason' => 'Only students can join clubs'];
            }
            
            // Check if user has already joined 2 clubs
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM user_clubs WHERE user_id = ? AND status = 'approved'");
            $stmt->execute([$user_id]);
            $club_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($club_count >= 2) {
                return ['can_join' => false, 'reason' => 'You can only join a maximum of 2 clubs'];
            }
            
            return ['can_join' => true, 'reason' => 'Eligible to join clubs'];
        } catch (Exception $e) {
            error_log("Error checking if user can join club: " . $e->getMessage());
            return ['can_join' => false, 'reason' => 'System error occurred'];
        }
    }
}

if (!function_exists('createClubJoinRequest')) {
    /**
     * Create a club join request
     * @param int $user_id User ID
     * @param int $club_id Club ID
     * @param PDO $pdo Database connection
     * @return bool Success status
     */
    function createClubJoinRequest($user_id, $club_id, $pdo) {
        try {
            // Check if user already has a pending or approved request for this club
            $stmt = $pdo->prepare("
                SELECT status FROM user_clubs 
                WHERE user_id = ? AND club_id = ? 
                AND status IN ('pending', 'approved')
            ");
            $stmt->execute([$user_id, $club_id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                if ($existing['status'] === 'approved') {
                    return false; // Already a member
                } else {
                    return false; // Already has a pending request
                }
            }
            
            // Create join request
            $stmt = $pdo->prepare("
                INSERT INTO user_clubs (user_id, club_id, status, requested_at) 
                VALUES (?, ?, 'pending', NOW())
            ");
            return $stmt->execute([$user_id, $club_id]);
        } catch (Exception $e) {
            error_log("Error creating club join request: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('getUserClubRequests')) {
    /**
     * Get user's club join requests
     * @param int $user_id User ID
     * @param PDO $pdo Database connection
     * @return array Array of club requests
     */
    function getUserClubRequests($user_id, $pdo) {
        try {
            $stmt = $pdo->prepare("
                SELECT uc.*, c.name as club_name, c.description as club_description
                FROM user_clubs uc
                JOIN clubs c ON uc.club_id = c.id
                WHERE uc.user_id = ?
                ORDER BY uc.requested_at DESC
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting user club requests: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('getPendingClubRequests')) {
    /**
     * Get all pending club join requests (for admin approval)
     * @param PDO $pdo Database connection
     * @return array Array of pending requests
     */
    function getPendingClubRequests($pdo) {
        try {
            $stmt = $pdo->prepare("
                SELECT uc.*, c.name as club_name, c.description as club_description,
                       u.username, u.first_name, u.last_name, u.student_id
                FROM user_clubs uc
                JOIN clubs c ON uc.club_id = c.id
                JOIN users u ON uc.user_id = u.user_id
                WHERE uc.status = 'pending'
                ORDER BY uc.requested_at ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting pending club requests: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('approveClubRequest')) {
    /**
     * Approve a club join request
     * @param int $request_id Request ID
     * @param PDO $pdo Database connection
     * @return bool Success status
     */
    function approveClubRequest($request_id, $pdo) {
        try {
            // Get the request details
            $stmt = $pdo->prepare("
                SELECT uc.*, u.user_id, c.name as club_name
                FROM user_clubs uc
                JOIN users u ON uc.user_id = u.user_id
                JOIN clubs c ON uc.club_id = c.id
                WHERE uc.id = ? AND uc.status = 'pending'
            ");
            $stmt->execute([$request_id]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$request) {
                return false; // Request not found or already processed
            }
            
            // Check if user can still join (might have joined other clubs since request)
            $can_join = canUserJoinClub($request['user_id'], $pdo);
            if (!$can_join['can_join']) {
                // Reject the request if user can no longer join
                $stmt = $pdo->prepare("
                    UPDATE user_clubs 
                    SET status = 'rejected', rejection_reason = ?, processed_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$can_join['reason'], $request_id]);
                return false;
            }
            
            // Approve the request
            $stmt = $pdo->prepare("
                UPDATE user_clubs 
                SET status = 'approved', approved_at = NOW(), processed_at = NOW() 
                WHERE id = ?
            ");
            return $stmt->execute([$request_id]);
        } catch (Exception $e) {
            error_log("Error approving club request: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('rejectClubRequest')) {
    /**
     * Reject a club join request
     * @param int $request_id Request ID
     * @param string $rejection_reason Reason for rejection
     * @param PDO $pdo Database connection
     * @return bool Success status
     */
    function rejectClubRequest($request_id, $rejection_reason, $pdo) {
        try {
            $stmt = $pdo->prepare("
                UPDATE user_clubs 
                SET status = 'rejected', rejection_reason = ?, processed_at = NOW() 
                WHERE id = ? AND status = 'pending'
            ");
            return $stmt->execute([$rejection_reason, $request_id]);
        } catch (Exception $e) {
            error_log("Error rejecting club request: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('canUserAccessClubChat')) {
    /**
     * Check if user can access club chat (must be approved member)
     * @param int $user_id User ID
     * @param int $club_id Club ID
     * @param PDO $pdo Database connection
     * @return bool True if user can access club chat
     */
    function canUserAccessClubChat($user_id, $club_id, $pdo) {
        try {
            $stmt = $pdo->prepare("
                SELECT status FROM user_clubs 
                WHERE user_id = ? AND club_id = ? AND status = 'approved'
            ");
            $stmt->execute([$user_id, $club_id]);
            return $stmt->fetch() !== false;
        } catch (Exception $e) {
            error_log("Error checking club chat access: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('getUserClubStatus')) {
    /**
     * Get user's status for a specific club
     * @param int $user_id User ID
     * @param int $club_id Club ID
     * @param PDO $pdo Database connection
     * @return string|null Club status or null if not found
     */
    function getUserClubStatus($user_id, $club_id, $pdo) {
        try {
            $stmt = $pdo->prepare("
                SELECT status FROM user_clubs 
                WHERE user_id = ? AND club_id = ?
            ");
            $stmt->execute([$user_id, $club_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['status'] : null;
        } catch (Exception $e) {
            error_log("Error getting user club status: " . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('getClubMembers')) {
    /**
     * Get approved members of a club
     * @param int $club_id Club ID
     * @param PDO $pdo Database connection
     * @return array Array of club members
     */
    function getClubMembers($club_id, $pdo) {
        try {
            $stmt = $pdo->prepare("
                SELECT uc.*, u.username, u.first_name, u.last_name, u.student_id, u.profile_image
                FROM user_clubs uc
                JOIN users u ON uc.user_id = u.user_id
                WHERE uc.club_id = ? AND uc.status = 'approved'
                ORDER BY uc.approved_at ASC
            ");
            $stmt->execute([$club_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting club members: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('getClubPendingRequests')) {
    /**
     * Get pending requests for a specific club
     * @param int $club_id Club ID
     * @param PDO $pdo Database connection
     * @return array Array of pending requests
     */
    function getClubPendingRequests($club_id, $pdo) {
        try {
            $stmt = $pdo->prepare("
                SELECT uc.*, u.username, u.first_name, u.last_name, u.student_id
                FROM user_clubs uc
                JOIN users u ON uc.user_id = u.user_id
                WHERE uc.club_id = ? AND uc.status = 'pending'
                ORDER BY uc.requested_at ASC
            ");
            $stmt->execute([$club_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting club pending requests: " . $e->getMessage());
            return [];
        }
    }
}
?>
