# Email Field Implementation Documentation

## Overview
This document outlines the implementation of the email field feature in the CITSA Connect system. The email field has been added to both the registration form and profile management system, with comprehensive validation and database integration.

## Key Features

### 1. Registration Form Enhancement
- **New Email Field**: Added email input field to the registration form
- **Real-time Validation**: Client-side email format validation
- **Server-side Validation**: Comprehensive email validation including format and uniqueness checks
- **Database Integration**: Email stored in the `users` table with unique constraint

### 2. Profile Management
- **Email Editing**: Users can update their email address in their profile
- **Validation**: Both client-side and server-side validation for email updates
- **Duplicate Prevention**: Prevents users from using email addresses already registered by other users

### 3. Session Management
- **Email Storage**: Email address stored in user session for easy access
- **Backward Compatibility**: Maintains existing session structure while adding email

## Database Schema Changes

### Users Table Modification
```sql
-- Added email column to users table
ALTER TABLE `users` ADD COLUMN `email` VARCHAR(225) NOT NULL AFTER `student_id`;

-- Added unique constraint for email
ALTER TABLE `users` ADD UNIQUE KEY `email` (`email`);
```

### Updated Users Table Structure
```sql
CREATE TABLE `users` (
  `user_id` INT(11) NOT NULL AUTO_INCREMENT,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `username` VARCHAR(225) NOT NULL,
  `student_id` VARCHAR(225) NOT NULL,
  `email` VARCHAR(225) NOT NULL, -- NEW FIELD
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
  UNIQUE KEY `student_id` (`student_id`),
  UNIQUE KEY `email` (`email`) -- NEW UNIQUE KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

## Files Modified

### 1. Database Schema (`citsa.sql`)
- Added `email` column to `users` table
- Added unique constraint for email
- Updated sample data to include email addresses

### 2. Registration Form (`register.php`)
**PHP Backend Changes:**
```php
// Email retrieval and validation
$email = trim($_POST['email']);

// Validation checks
if (empty($email)) {
    $error_message = 'Email address is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error_message = 'Please enter a valid email address.';
} else {
    // Check for duplicate email
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $error_message = 'Email address is already registered.';
    }
}

// Updated INSERT query
$stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, username, student_id, email, password, programme, user_type, profile_image, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())");
$stmt->execute([$first_name, $last_name, $username, $student_id, $email, $password_hash, $programme, $user_type, $profile_image]);
```

**HTML Form Changes:**
```html
<!-- Email input field -->
<div class="row">
    <div class="col-md-12">
        <div class="mb-3">
            <label for="email" class="form-label fw-semibold text-[#1a3c6d]">
                <i class="fas fa-envelope me-2"></i>Email Address
            </label>
            <input type="email" class="form-control" id="email" name="email" 
                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                   required>
        </div>
    </div>
</div>
```

**JavaScript Validation:**
```javascript
// Email format validation
const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
if (!emailRegex.test(email)) {
    e.preventDefault();
    Swal.fire({
        title: 'Invalid Email!',
        text: 'Please enter a valid email address.',
        icon: 'error',
        confirmButtonColor: '#1a3c6d'
    });
    return;
}
```

### 3. Login System (`login.php`)
```php
// Updated SELECT query to include email
$stmt = $pdo->prepare("SELECT user_id, username, student_id, email, password, first_name, last_name FROM users WHERE username = ? OR student_id = ?");

// Store email in session
$_SESSION['email'] = $user['email'];
```

### 4. Profile Management (`profile.php`)
```html
<!-- Email input field in profile form -->
<div class="row mb-3">
    <label class="col-md-3 col-form-label">Email</label>
    <div class="col-md-9">
        <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
    </div>
</div>
```

### 5. Profile Update (`update_profile.php`)
```php
// Email retrieval and validation
$email = trim($_POST['email']);

if (empty($email)) {
    header("Location: profile.php?status=error&message=Email address is required");
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: profile.php?status=error&message=Please enter a valid email address");
    exit();
}

// Check for duplicate email (excluding current user)
$stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
$stmt->execute([$email, $user_id]);
if ($stmt->fetch()) {
    header("Location: profile.php?status=error&message=Email address is already registered");
    exit();
}

// Updated UPDATE queries
if ($profile_image) {
    $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, username = ?, email = ?, programme = ?, about = ?, profile_image = ? WHERE user_id = ?");
    $stmt->execute([$first_name, $last_name, $username, $email, $programme, $about, $profile_image, $user_id]);
} else {
    $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, username = ?, email = ?, programme = ?, about = ? WHERE user_id = ?");
    $stmt->execute([$first_name, $last_name, $username, $email, $programme, $about, $user_id]);
}
```

### 6. User Helper Functions (`app/helpers/user.php`)
```php
// Updated updateUserProfile function
if (!function_exists('updateUserProfile')) {
    function updateUserProfile($user_id, $data, $pdo) {
        $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, programme = ?, profile_image = ? WHERE user_id = ?");
        return $stmt->execute([$data['first_name'], $data['last_name'], $data['email'], $data['programme'], $data['profile_image'], $user_id]);
    }
}
```

## Validation Rules

### 1. Email Format Validation
- **Client-side**: JavaScript regex pattern `/^[^\s@]+@[^\s@]+\.[^\s@]+$/`
- **Server-side**: PHP `filter_var($email, FILTER_VALIDATE_EMAIL)`
- **Requirements**: Must contain @ symbol, domain name, and top-level domain

### 2. Uniqueness Validation
- **Registration**: Email must not exist in the `users` table
- **Profile Update**: Email must not be used by any other user (excluding current user)
- **Database Constraint**: Unique key on `email` column prevents duplicates

### 3. Required Field Validation
- **Registration**: Email is a required field
- **Profile Update**: Email is a required field
- **Empty Check**: Server-side validation for empty email addresses

## Error Handling

### 1. Registration Errors
- Invalid email format
- Email already registered
- Empty email field

### 2. Profile Update Errors
- Invalid email format
- Email already used by another user
- Empty email field

### 3. User Feedback
- SweetAlert2 notifications for client-side errors
- Redirect with error messages for server-side errors
- Clear error messages for each validation failure

## Testing

### 1. Test Page (`test_email_field.php`)
Created a comprehensive test page that includes:
- Email validation test cases
- Database user display with email addresses
- Manual testing instructions for registration and profile updates

### 2. Test Cases
**Valid Emails:**
- `john.doe@student.ucc.edu.gh`
- `jane.smith@student.ucc.edu.gh`
- `test.email@domain.co.uk`

**Invalid Emails:**
- `invalid-email`
- `test@`
- `@domain.com`
- `test@domain`

### 3. Manual Testing Instructions
**Registration Testing:**
1. Go to `register.php`
2. Fill in all required fields including email
3. Try submitting with invalid email format
4. Try submitting with duplicate email
5. Complete registration with valid data

**Profile Update Testing:**
1. Login to the system
2. Go to profile page
3. Edit email address
4. Try saving with invalid email format
5. Try saving with duplicate email
6. Save with valid email

## Security Considerations

### 1. Input Sanitization
- All email inputs are trimmed to remove whitespace
- HTML special characters are escaped when displaying
- Prepared statements prevent SQL injection

### 2. Validation Layers
- Client-side validation for immediate user feedback
- Server-side validation for security
- Database constraints for data integrity

### 3. Session Security
- Email stored in session for authenticated users
- Session validation on all profile operations

## Integration with Existing Features

### 1. Notification System
- Email can be used for future email notifications
- Session email available for all notification functions

### 2. User Management
- Email displayed in user lists and profiles
- Email used for user identification and communication

### 3. Friend System
- Email available for friend requests and messaging
- Email displayed in user search results

## Future Enhancements

### 1. Email Verification
- Email verification system for new registrations
- Verification tokens and email confirmation links

### 2. Password Reset
- Email-based password reset functionality
- Secure token generation and validation

### 3. Email Notifications
- Friend request notifications via email
- Message notifications via email
- System announcements via email

### 4. Admin Features
- Email management in admin panel
- Bulk email functionality
- Email templates and customization

## Database Migration

### For Existing Installations
If you have an existing database without the email field, run these SQL commands:

```sql
-- Add email column
ALTER TABLE `users` ADD COLUMN `email` VARCHAR(225) NOT NULL DEFAULT '' AFTER `student_id`;

-- Add unique constraint
ALTER TABLE `users` ADD UNIQUE KEY `email` (`email`);

-- Update existing users with placeholder emails (if needed)
UPDATE `users` SET `email` = CONCAT(username, '@placeholder.com') WHERE `email` = '';
```

## Troubleshooting

### Common Issues

1. **Duplicate Email Error**
   - Check if email already exists in database
   - Verify unique constraint is properly set

2. **Email Validation Failing**
   - Check email format using PHP filter_var
   - Verify client-side regex pattern

3. **Session Email Not Available**
   - Ensure login.php includes email in SELECT query
   - Verify session is properly started

4. **Profile Update Errors**
   - Check for duplicate email validation logic
   - Verify user_id is correctly passed to queries

### Debug Steps

1. Check database structure for email column
2. Verify unique constraint exists
3. Test email validation functions
4. Check session variables after login
5. Review error logs for specific issues

## Conclusion

The email field implementation provides a robust foundation for user communication and identification in the CITSA Connect system. The comprehensive validation, security measures, and integration with existing features ensure a smooth user experience while maintaining data integrity and security.

The implementation follows best practices for form validation, database design, and user experience, making it ready for future enhancements such as email verification and notification systems. 