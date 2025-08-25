<?php
if (!function_exists('getUser')) {
function getUser($user_id, $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('getAllUsers')) {
function getAllUsers($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE status = 'active' ORDER BY first_name, last_name");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('getUserByUsername')) {
function getUserByUsername($username, $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('getUserByStudentId')) {
function getUserByStudentId($student_id, $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE student_id = ?");
    $stmt->execute([$student_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
}

if (!function_exists('determineUserTypeFromStudentId')) {
    /**
     * Determines if a user is a student or alumni based on their student ID
     * 
     * Student ID format: PS/[PROGRAM]/[YEAR]/[NUMBER]
     * Examples: PS/ITC/20/00001, PS/CSC/21/00002
     * 
     * Logic:
     * - Year 20 and below = Alumni (completed by August 2024)
     * - Year 21 and above = Current Student (completes 2025 and beyond)
     * 
     * @param string $student_id The student ID in format PS/PROGRAM/YEAR/NUMBER
     * @return string 'student' or 'alumni'
     */
    function determineUserTypeFromStudentId($student_id) {
        // Validate student ID format
        if (!preg_match('/^PS\/([A-Z]{3})\/(\d{2})\/\d+$/i', $student_id, $matches)) {
            // If format doesn't match, default to student
            return 'student';
        }
        
        $year = intval($matches[2]);
        
        // Year 20 and below = Alumni (completed by August 2024)
        // Year 21 and above = Current Student (completes 2025 and beyond)
        if ($year <= 20) {
            return 'alumni';
        } else {
            return 'student';
        }
    }
}

if (!function_exists('getProgrammeFromStudentId')) {
    /**
     * Extracts the programme from student ID
     * 
     * @param string $student_id The student ID in format PS/PROGRAM/YEAR/NUMBER
     * @return string The programme name or empty string if invalid format
     */
    function getProgrammeFromStudentId($student_id) {
        // Validate student ID format
        if (!preg_match('/^PS\/([A-Z]{3})\/(\d{2})\/\d+$/i', $student_id, $matches)) {
            return '';
        }
        
        $program_code = strtoupper($matches[1]);
        
        // Map program codes to full names
        $programme_map = [
            'ITC' => 'B.Sc. Information Technology',
            'CSC' => 'B.Sc. Computer Science',
            'BED' => 'B.Ed. Information Technology', // Assuming B.Ed. uses BED code
            'BIT' => 'B.Ed. Computer Science'        // Assuming alternative code
        ];
        
        return $programme_map[$program_code] ?? '';
    }
}

if (!function_exists('validateStudentId')) {
    /**
     * Validates student ID format and returns validation result
     * 
     * @param string $student_id The student ID to validate
     * @return array ['valid' => bool, 'message' => string, 'programme' => string, 'year' => int, 'user_type' => string]
     */
    function validateStudentId($student_id) {
        $result = [
            'valid' => false,
            'message' => '',
            'programme' => '',
            'year' => 0,
            'user_type' => 'student'
        ];
        
        // Check if student ID matches expected format
        if (!preg_match('/^PS\/([A-Z]{3})\/(\d{2})\/\d+$/i', $student_id, $matches)) {
            $result['message'] = 'Invalid student ID format. Expected format: PS/PROGRAM/YEAR/NUMBER (e.g., PS/ITC/21/0001)';
            return $result;
        }
        
        $program_code = strtoupper($matches[1]);
        $year = intval($matches[2]);
        
        // Validate program code
        $valid_programs = ['ITC', 'CSC', 'BED', 'BIT'];
        if (!in_array($program_code, $valid_programs)) {
            $result['message'] = 'Invalid program code. Valid codes: ITC, CSC, BED, BIT';
            return $result;
        }
        
        // Validate year (should be reasonable range)
        if ($year < 15 || $year > 30) {
            $result['message'] = 'Invalid year in student ID. Year should be between 15-30';
            return $result;
        }
        
        // Get programme name
        $programme = getProgrammeFromStudentId($student_id);
        if (empty($programme)) {
            $result['message'] = 'Could not determine programme from student ID';
            return $result;
        }
        
        // Determine user type
        $user_type = determineUserTypeFromStudentId($student_id);
        
        $result['valid'] = true;
        $result['message'] = 'Valid student ID';
        $result['programme'] = $programme;
        $result['year'] = $year;
        $result['user_type'] = $user_type;
        
        return $result;
    }
}

if (!function_exists('getUserTypeLabel')) {
    /**
     * Gets a user-friendly label for user type
     * 
     * @param string $user_type 'student' or 'alumni'
     * @return string User-friendly label
     */
    function getUserTypeLabel($user_type) {
        switch ($user_type) {
            case 'alumni':
                return 'Alumni';
            case 'student':
                return 'Student';
            default:
                return 'User';
        }
    }
}

if (!function_exists('getUserTypeBadgeClass')) {
    /**
     * Gets Bootstrap badge class for user type
     * 
     * @param string $user_type 'student' or 'alumni'
     * @return string Bootstrap badge class
     */
    function getUserTypeBadgeClass($user_type) {
        switch ($user_type) {
            case 'alumni':
                return 'bg-success';
            case 'student':
                return 'bg-primary';
            default:
                return 'bg-secondary';
        }
    }
}

if (!function_exists('validateStudentRegistration')) {
    /**
     * Validates student registration against the authorized_students database
     * 
     * @param string $first_name Student's first name
     * @param string $last_name Student's last name
     * @param string $student_id Student ID
     * @param PDO $pdo Database connection
     * @return array ['valid' => bool, 'message' => string, 'student_data' => array|null]
     */
    function validateStudentRegistration($first_name, $last_name, $student_id, $pdo) {
        try {
            // First, validate the student ID format
            $student_validation = validateStudentId($student_id);
            if (!$student_validation['valid']) {
                return [
                    'valid' => false,
                    'message' => $student_validation['message'],
                    'student_data' => null
                ];
            }
            
            // Check if student exists in authorized database
            $stmt = $pdo->prepare("
                SELECT * FROM authorized_students 
                WHERE student_id = ? 
                AND LOWER(first_name) = LOWER(?) 
                AND LOWER(last_name) = LOWER(?)
                AND status = 'active'
            ");
            $stmt->execute([$student_id, trim($first_name), trim($last_name)]);
            $authorized_student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$authorized_student) {
                // Check if student ID exists but names don't match
                $stmt = $pdo->prepare("SELECT * FROM authorized_students WHERE student_id = ? AND status = 'active'");
                $stmt->execute([$student_id]);
                $student_exists = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($student_exists) {
                    return [
                        'valid' => false,
                        'message' => 'Student ID found but names do not match. Please check your first name and last name.',
                        'student_data' => null
                    ];
                } else {
                    return [
                        'valid' => false,
                        'message' => 'Student not found in authorized database. Please contact the department for registration.',
                        'student_data' => null
                    ];
                }
            }
            
            // Check if student is already registered
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE student_id = ?");
            $stmt->execute([$student_id]);
            if ($stmt->fetch()) {
                return [
                    'valid' => false,
                    'message' => 'Student ID is already registered. Please login instead.',
                    'student_data' => null
                ];
            }
            
            // Validation successful
            return [
                'valid' => true,
                'message' => 'Student validation successful.',
                'student_data' => $authorized_student
            ];
            
        } catch (PDOException $e) {
            error_log("Student validation error: " . $e->getMessage());
            return [
                'valid' => false,
                'message' => 'Database error during validation. Please try again.',
                'student_data' => null
            ];
        }
    }
}

if (!function_exists('updateUserProfile')) {
function updateUserProfile($user_id, $data, $pdo) {
        $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, programme = ?, profile_image = ? WHERE user_id = ?");
        return $stmt->execute([$data['first_name'], $data['last_name'], $data['email'], $data['programme'], $data['profile_image'], $user_id]);
    }
}
?> 