<?php
/**
 * Chat Room Helper Functions
 * Handles chat room access, message filtering, and user permissions
 */

// Include platform management helper
if (file_exists(__DIR__ . '/platform_management.php')) {
    require_once __DIR__ . '/platform_management.php';
}

if (!function_exists('getUserYearFromStudentId')) {
    /**
     * Get user's year from student ID
     * @param string $student_id The student ID
     * @return int|null The year or null if invalid format
     */
    function getUserYearFromStudentId($student_id) {
        if (preg_match('/^PS\/[A-Z]{3}\/(\d{2})\/\d+$/i', $student_id, $matches)) {
            return intval($matches[1]);
        }
        return null;
    }
}

if (!function_exists('getLevelFromYear')) {
    /**
     * Get level based on year
     * @param int $year The year
     * @return int The level
     */
    function getLevelFromYear($year) {
        if ($year >= 21) return 400; // Year 21+ = Level 400
        if ($year >= 22) return 300; // Year 22+ = Level 300
        if ($year >= 23) return 200; // Year 23+ = Level 200
        if ($year >= 24) return 100; // Year 24+ = Level 100
        return 100; // Default
    }
}

if (!function_exists('getUserProgramCode')) {
    /**
     * Get user's program code from student ID
     * @param string $student_id The student ID
     * @return string|null The program code or null if invalid format
     */
    function getUserProgramCode($student_id) {
        if (preg_match('/^PS\/([A-Z]{3})\/\d{2}\/\d+$/i', $student_id, $matches)) {
            return $matches[1];
        }
        return null;
    }
}

if (!function_exists('canAccessRoom')) {
    /**
     * Check if user can access a specific room
     * @param array $user User data
     * @param string $room_id Room ID
     * @param PDO $pdo Database connection
     * @return bool True if user can access the room
     */
    function canAccessRoom($user, $room_id, $pdo) {
        // General platform - everyone can access
        if ($room_id === 'general') {
            return true;
        }
        
        // Students only platform
        if ($room_id === 'students_only') {
            return $user['user_type'] === 'student';
        }
        
        // Alumni only platform
        if ($room_id === 'alumni_only') {
            return $user['user_type'] === 'alumni';
        }
        
        // Year-based platforms (Level platforms)
        if (preg_match('/^level_(\d+)$/', $room_id, $matches)) {
            if ($user['user_type'] !== 'student') {
                return false;
            }
            
            $user_year = getUserYearFromStudentId($user['student_id']);
            if (!$user_year) {
                return false;
            }
            
            $user_level = getLevelFromYear($user_year);
            $room_level = intval($matches[1]);
            
            return $user_level === $room_level;
        }
        
        // Program-based platforms
        if (preg_match('/^program_([A-Z]{3})$/', $room_id, $matches)) {
            $user_program = getUserProgramCode($user['student_id']);
            return $user_program === $matches[1];
        }
        
        // Club platforms
        if (preg_match('/^club_(\d+)$/', $room_id, $matches)) {
            if ($user['user_type'] !== 'student') {
                return false;
            }
            
            $club_id = intval($matches[1]);
            
            // Check if user is an APPROVED member of this club
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM user_clubs WHERE user_id = ? AND club_id = ? AND status = 'approved'");
            $stmt->execute([$user['user_id'], $club_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['count'] > 0;
        }
        
        return false;
    }
}

if (!function_exists('getAvailableRooms')) {
    /**
     * Get available chat rooms for a user
     * @param array $user User data
     * @param PDO $pdo Database connection
     * @return array Array of available rooms
     */
    function getAvailableRooms($user, $pdo) {
        $available_rooms = [];
        
        // 1. General platform (everyone)
        $general_name = getPlatformSetting('general_platform_name', $pdo, 'General Platform');
        $general_description = getPlatformSetting('general_platform_description', $pdo, 'CITSA Student-Alumni Platform');
        
        $available_rooms[] = [
            'id' => 'general',
            'name' => $general_name,
            'description' => $general_description,
            'type' => 'general',
            'access' => 'all'
        ];
        
        // 2. Students-only platform
        if ($user['user_type'] === 'student') {
            $students_name = getPlatformSetting('students_platform_name', $pdo, 'Students Only');
            $students_description = getPlatformSetting('students_platform_description', $pdo, 'Student Platform');
            
            $available_rooms[] = [
                'id' => 'students_only',
                'name' => $students_name,
                'description' => $students_description,
                'type' => 'students',
                'access' => 'students'
            ];
        }
        
        // 3. Alumni-only platform
        if ($user['user_type'] === 'alumni') {
            $alumni_name = getPlatformSetting('alumni_platform_name', $pdo, 'Alumni Network');
            $alumni_description = getPlatformSetting('alumni_platform_description', $pdo, 'CITSA Alumni Platform');
            
            $available_rooms[] = [
                'id' => 'alumni_only',
                'name' => $alumni_name,
                'description' => $alumni_description,
                'type' => 'alumni',
                'access' => 'alumni'
            ];
        }
        
        // 4. Year-based platforms (for students)
        if ($user['user_type'] === 'student') {
            $user_year = getUserYearFromStudentId($user['student_id']);
            if ($user_year) {
                $user_level = getLevelFromYear($user_year);
                $available_rooms[] = [
                    'id' => "level_{$user_level}",
                    'name' => "Level {$user_level} Platform",
                    'description' => "Platform for Level {$user_level} students (Year {$user_year})",
                    'type' => 'year_based',
                    'access' => 'level_' . $user_level
                ];
            }
        }
        
        // 5. Program-based platforms
        $program_platforms = [
            'ITC' => 'Information Technology',
            'CSC' => 'Computer Science',
            'BED' => 'B.Ed. Information Technology',
            'BIT' => 'B.Ed. Computer Science'
        ];
        
        $user_program_code = getUserProgramCode($user['student_id']);
        if ($user_program_code && isset($program_platforms[$user_program_code])) {
            $available_rooms[] = [
                'id' => "program_{$user_program_code}",
                'name' => $program_platforms[$user_program_code] . ' Platform',
                'description' => "Platform for {$program_platforms[$user_program_code]} students",
                'type' => 'program',
                'access' => 'program_' . $user_program_code
            ];
        }
        
        // 6. Club platforms (for students)
        if ($user['user_type'] === 'student') {
            $clubs = [
                ['id' => 1, 'name' => 'Networking Club', 'description' => 'Network administration and security'],
                ['id' => 2, 'name' => 'Cybersecurity Club', 'description' => 'Cybersecurity and ethical hacking'],
                ['id' => 3, 'name' => 'Web Development Club', 'description' => 'Web development and design'],
                ['id' => 4, 'name' => 'Machine Learning & AI Club', 'description' => 'AI and machine learning']
            ];
            
            // Get user's club statuses
            $stmt = $pdo->prepare("SELECT club_id, status FROM user_clubs WHERE user_id = ?");
            $stmt->execute([$user['user_id']]);
            $user_clubs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            foreach ($clubs as $club) {
                $club_status = $user_clubs[$club['id']] ?? null;
                $is_approved = $club_status === 'approved';
                $is_pending = $club_status === 'pending';
                $is_rejected = $club_status === 'rejected';
                
                // Only show club rooms that user has been approved for
                if ($is_approved) {
                    $available_rooms[] = [
                        'id' => "club_{$club['id']}",
                        'name' => $club['name'],
                        'description' => $club['description'],
                        'type' => 'club',
                        'access' => 'club_' . $club['id'],
                        'is_member' => true,
                        'club_id' => $club['id'],
                        'status' => 'approved'
                    ];
                }
            }
        }
        
        return $available_rooms;
    }
}

if (!function_exists('getAllClubsWithStatus')) {
    /**
     * Get all clubs with user's request status (for UI display)
     * @param int $user_id User ID
     * @param PDO $pdo Database connection
     * @return array Array of clubs with status
     */
    function getAllClubsWithStatus($user_id, $pdo) {
        $clubs = [
            ['id' => 1, 'name' => 'Networking Club', 'description' => 'Network administration and security'],
            ['id' => 2, 'name' => 'Cybersecurity Club', 'description' => 'Cybersecurity and ethical hacking'],
            ['id' => 3, 'name' => 'Web Development Club', 'description' => 'Web development and design'],
            ['id' => 4, 'name' => 'Machine Learning & AI Club', 'description' => 'AI and machine learning']
        ];
        
        // Get user's club statuses
        $stmt = $pdo->prepare("SELECT club_id, status FROM user_clubs WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user_clubs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        foreach ($clubs as &$club) {
            $club_status = $user_clubs[$club['id']] ?? null;
            $club['status'] = $club_status;
            $club['can_join'] = $club_status === null; // Can join if no status (no request made)
            $club['is_approved'] = $club_status === 'approved';
            $club['is_pending'] = $club_status === 'pending';
            $club['is_rejected'] = $club_status === 'rejected';
        }
        
        return $clubs;
    }
}

if (!function_exists('getRoomMessages')) {
    /**
     * Get messages for a specific room
     * @param string $room_id Room ID
     * @param int $last_message_id Last message ID (for pagination)
     * @param PDO $pdo Database connection
     * @return array Array of messages
     */
    function getRoomMessages($room_id, $last_message_id = 0, $pdo) {
        $stmt = $pdo->prepare("
            SELECT m.*, u.first_name, u.last_name, u.username, u.profile_image, u.user_type, u.programme
            FROM chat_room_messages m
            JOIN users u ON m.sender_id = u.user_id
            WHERE m.room_id = ? AND m.id > ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$room_id, $last_message_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('sendRoomMessage')) {
    /**
     * Send a message to a room
     * @param string $room_id Room ID
     * @param int $sender_id Sender user ID
     * @param string $message Message content
     * @param PDO $pdo Database connection
     * @return int|false Message ID on success, false on failure
     */
    function sendRoomMessage($room_id, $sender_id, $message, $pdo, $message_type = 'text', $file_url = null, $file_name = null, $file_size = null) {
        $stmt = $pdo->prepare("
            INSERT INTO chat_room_messages (room_id, sender_id, message, message_type, file_url, file_name, file_size, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        if ($stmt->execute([$room_id, $sender_id, $message, $message_type, $file_url, $file_name, $file_size])) {
            return $pdo->lastInsertId();
        }
        
        return false;
    }
}

if (!function_exists('joinClub')) {
    /**
     * Join a club
     * @param int $user_id User ID
     * @param int $club_id Club ID
     * @param PDO $pdo Database connection
     * @return bool Success status
     */
    function joinClub($user_id, $club_id, $pdo) {
        try {
            // Check if user has already joined 2 clubs
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM user_clubs WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $club_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($club_count >= 2) {
                return false;
            }
            
            // Check if user is already in this club
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM user_clubs WHERE user_id = ? AND club_id = ?");
            $stmt->execute([$user_id, $club_id]);
            $already_joined = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($already_joined > 0) {
                return false;
            }
            
            // Join the club
            $stmt = $pdo->prepare("INSERT INTO user_clubs (user_id, club_id, joined_at) VALUES (?, ?, NOW())");
            return $stmt->execute([$user_id, $club_id]);
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('leaveClub')) {
    /**
     * Leave a club
     * @param int $user_id User ID
     * @param int $club_id Club ID
     * @param PDO $pdo Database connection
     * @return bool Success status
     */
    function leaveClub($user_id, $club_id, $pdo) {
        try {
            $stmt = $pdo->prepare("DELETE FROM user_clubs WHERE user_id = ? AND club_id = ?");
            return $stmt->execute([$user_id, $club_id]);
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('getUserClubs')) {
    /**
     * Get user's joined clubs
     * @param int $user_id User ID
     * @param PDO $pdo Database connection
     * @return array Array of club IDs
     */
    function getUserClubs($user_id, $pdo) {
        $stmt = $pdo->prepare("SELECT club_id FROM user_clubs WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
?> 