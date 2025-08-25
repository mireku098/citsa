# Secure Registration System Documentation

## Overview

The secure registration system implements a validation mechanism that ensures only authorized students and alumni can register for the CITSA Connect platform. This prevents unauthorized access and maintains the integrity of the student-alumni community.

## Key Features

### 1. Database-Driven Validation
- **Authorized Students Database**: A separate `authorized_students` table contains the official department student records
- **Real-time Validation**: Registration attempts are validated against this database in real-time
- **Secure Access Control**: Only students in the authorized database can register

### 2. Multi-Field Validation
- **First Name & Last Name**: Split from single "name" field for better data management
- **Student ID Format**: Must follow the pattern `PS/PROGRAM/YEAR/NUMBER`
- **Name Matching**: First name, last name, and student ID must all match the authorized database

### 3. Student Classification Logic
- **Current Students**: Year 21 and above (completing 2025 and beyond)
- **Alumni**: Year 20 and below (completed by August 2024)
- **Automatic Classification**: User type is determined from the student ID year

## Database Schema

### Authorized Students Table
```sql
CREATE TABLE `authorized_students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `programme` varchar(100) NOT NULL,
  `user_type` ENUM('student', 'alumni') NOT NULL DEFAULT 'student',
  `status` ENUM('active', 'inactive', 'graduated') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_id` (`student_id`),
  KEY `idx_name_search` (`first_name`, `last_name`),
  KEY `idx_student_id` (`student_id`)
);
```

### Updated Users Table
```sql
CREATE TABLE `users` (
  `user_id` INT(11) NOT NULL AUTO_INCREMENT,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `username` VARCHAR(225) NOT NULL,
  `student_id` VARCHAR(225) NOT NULL,
  `password` VARCHAR(1000) NOT NULL,
  `profile_image` VARCHAR(225) DEFAULT 'default-avatar.png',
  `last_seen` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `programme` VARCHAR(225) NOT NULL,
  `user_type` ENUM('student', 'alumni') NOT NULL DEFAULT 'student',
  `status` ENUM('active', 'inactive', 'banned') NOT NULL DEFAULT 'active',
  `about` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `student_id` (`student_id`)
);
```

## Program Codes

| Code | Full Program Name |
|------|------------------|
| ITC  | B.Sc. Information Technology |
| CSC  | B.Sc. Computer Science |
| BED  | B.Ed. Information Technology |
| BIT  | B.Ed. Computer Science |

## Validation Process

### 1. Input Validation
- First name and last name must be at least 2 characters
- Student ID must follow the correct format
- All required fields must be filled

### 2. Database Validation
```php
function validateStudentRegistration($first_name, $last_name, $student_id, $pdo) {
    // 1. Validate student ID format
    $student_validation = validateStudentId($student_id);
    
    // 2. Check if student exists in authorized database
    $stmt = $pdo->prepare("
        SELECT * FROM authorized_students 
        WHERE student_id = ? 
        AND LOWER(first_name) = LOWER(?) 
        AND LOWER(last_name) = LOWER(?)
        AND status = 'active'
    ");
    
    // 3. Check if already registered
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE student_id = ?");
    
    // 4. Return validation result
    return [
        'valid' => true/false,
        'message' => 'Success/Error message',
        'student_data' => $authorized_student_data
    ];
}
```

### 3. Error Handling
- **Invalid Format**: Student ID doesn't match expected pattern
- **Name Mismatch**: Student ID exists but names don't match
- **Not Found**: Student not in authorized database
- **Already Registered**: Student ID already has an account

## Test Data

The system includes test data for both current students and alumni:

### Current Students (Year 21+)
- John Doe (PS/ITC/21/0001) - B.Sc. Information Technology
- Jane Smith (PS/CSC/21/0002) - B.Sc. Computer Science
- Michael Johnson (PS/BED/21/0003) - B.Ed. Information Technology
- Sarah Williams (PS/BIT/21/0004) - B.Ed. Computer Science

### Alumni (Year 20 and below)
- William Jackson (PS/ITC/20/0001) - B.Sc. Information Technology
- Patricia White (PS/CSC/20/0002) - B.Sc. Computer Science
- Richard Harris (PS/BED/20/0003) - B.Ed. Information Technology
- Jennifer Martin (PS/BIT/20/0004) - B.Ed. Computer Science

## Files Modified

### Core Files
1. **`citsa.sql`** - Database schema with new tables and test data
2. **`register.php`** - Updated registration form and validation logic
3. **`login.php`** - Updated to handle first_name and last_name
4. **`profile.php`** - Updated profile form for separate name fields
5. **`update_profile.php`** - Updated profile update logic
6. **`friends.php`** - Updated queries to use CONCAT for full names

### Helper Files
1. **`app/helpers/user.php`** - Added `validateStudentRegistration()` function
2. **`app/helpers/notifications.php`** - Updated for new name structure

### Test Files
1. **`test_secure_registration.php`** - Comprehensive test page

## Security Benefits

### 1. Access Control
- Only legitimate students and alumni can register
- Prevents fake accounts and unauthorized access
- Maintains community integrity

### 2. Data Integrity
- Ensures accurate student information
- Prevents duplicate registrations
- Maintains consistent data structure

### 3. Audit Trail
- All registrations are validated against official records
- Clear validation messages for troubleshooting
- Database logs for security monitoring

## Admin Panel Integration

The system is designed to integrate with a future admin panel where:
- Department administrators can manage the authorized_students database
- Add new students as they enroll
- Update student status (active, graduated, inactive)
- Monitor registration attempts and validation failures

## Testing

### Test Page
Visit `test_secure_registration.php` to:
- View all authorized students in the database
- Test validation logic with various scenarios
- Verify security features are working correctly

### Test Scenarios
1. **Valid Registration**: Use test data from authorized database
2. **Invalid Names**: Try wrong names for existing student IDs
3. **Invalid Student ID**: Try student IDs not in database
4. **Wrong Format**: Try malformed student IDs
5. **Duplicate Registration**: Try registering same student ID twice

## Future Enhancements

1. **Admin Panel**: Web interface for managing authorized students
2. **Bulk Import**: CSV/Excel import for large student databases
3. **Email Verification**: Additional email-based verification
4. **Two-Factor Authentication**: Enhanced security for sensitive operations
5. **Audit Logging**: Detailed logs of all registration attempts

## Implementation Notes

- The system maintains backward compatibility with existing user sessions
- All existing functionality continues to work with the new name structure
- Error messages are user-friendly and informative
- The validation process is efficient and secure
- Test data is comprehensive and covers all scenarios

## Support

For questions or issues with the secure registration system:
1. Check the test page for validation results
2. Review the database schema and test data
3. Verify all required files are properly updated
4. Test with the provided test scenarios 