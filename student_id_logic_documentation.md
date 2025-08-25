# Student ID Logic Documentation

## Overview

The CITSA Connect system automatically determines whether a user is a **Current Student** or **Alumni** based on their student ID format. This logic is essential for creating chat rooms with appropriate access controls and user experience features.

## Student ID Format

### Pattern
```
PS/[PROGRAM]/[YEAR]/[NUMBER]
```

### Components
- **PS**: Prefix (always "PS")
- **[PROGRAM]**: 3-letter program code
- **[YEAR]**: 2-digit year when schooling started
- **[NUMBER]**: Sequential number

### Examples
- `PS/ITC/21/0001` - B.Sc. Information Technology student, started 2021
- `PS/CSC/20/0002` - B.Sc. Computer Science alumni, started 2020
- `PS/BED/19/0003` - B.Ed. Information Technology alumni, started 2019

## Program Codes

| Code | Full Name |
|------|-----------|
| ITC | B.Sc. Information Technology |
| CSC | B.Sc. Computer Science |
| BED | B.Ed. Information Technology |
| BIT | B.Ed. Computer Science |

## User Type Classification Logic

### Alumni (Year ≤ 20)
- **Years**: 15, 16, 17, 18, 19, 20
- **Status**: Alumni (completed by August 2024)
- **Badge Color**: Green (`bg-success`)
- **Label**: "Alumni"

### Current Students (Year ≥ 21)
- **Years**: 21, 22, 23, 24, 25, 26, 27, 28, 29, 30
- **Status**: Current Student (completes 2025 and beyond)
- **Badge Color**: Blue (`bg-primary`)
- **Label**: "Current Student"

## Implementation

### PHP Helper Functions

#### `determineUserTypeFromStudentId($student_id)`
Determines user type based on student ID year.

```php
function determineUserTypeFromStudentId($student_id) {
    if (!preg_match('/^PS\/([A-Z]{3})\/(\d{2})\/\d+$/i', $student_id, $matches)) {
        return 'student'; // Default fallback
    }
    
    $year = intval($matches[2]);
    return ($year <= 20) ? 'alumni' : 'student';
}
```

#### `getProgrammeFromStudentId($student_id)`
Extracts and maps program code to full program name.

```php
function getProgrammeFromStudentId($student_id) {
    if (!preg_match('/^PS\/([A-Z]{3})\/(\d{2})\/\d+$/i', $student_id, $matches)) {
        return '';
    }
    
    $program_code = strtoupper($matches[1]);
    $programme_map = [
        'ITC' => 'B.Sc. Information Technology',
        'CSC' => 'B.Sc. Computer Science',
        'BED' => 'B.Ed. Information Technology',
        'BIT' => 'B.Ed. Computer Science'
    ];
    
    return $programme_map[$program_code] ?? '';
}
```

#### `validateStudentId($student_id)`
Comprehensive validation with detailed results.

```php
function validateStudentId($student_id) {
    $result = [
        'valid' => false,
        'message' => '',
        'programme' => '',
        'year' => 0,
        'user_type' => 'student'
    ];
    
    // Format validation
    if (!preg_match('/^PS\/([A-Z]{3})\/(\d{2})\/\d+$/i', $student_id, $matches)) {
        $result['message'] = 'Invalid student ID format. Expected: PS/PROGRAM/YEAR/NUMBER';
        return $result;
    }
    
    $program_code = strtoupper($matches[1]);
    $year = intval($matches[2]);
    
    // Program code validation
    $valid_programs = ['ITC', 'CSC', 'BED', 'BIT'];
    if (!in_array($program_code, $valid_programs)) {
        $result['message'] = 'Invalid program code. Valid codes: ITC, CSC, BED, BIT';
        return $result;
    }
    
    // Year validation
    if ($year < 15 || $year > 30) {
        $result['message'] = 'Invalid year. Year should be between 15-30';
        return $result;
    }
    
    $result['valid'] = true;
    $result['programme'] = getProgrammeFromStudentId($student_id);
    $result['year'] = $year;
    $result['user_type'] = determineUserTypeFromStudentId($student_id);
    
    return $result;
}
```

#### `getUserTypeLabel($user_type)`
Returns user-friendly labels.

```php
function getUserTypeLabel($user_type) {
    switch ($user_type) {
        case 'alumni': return 'Alumni';
        case 'student': return 'Current Student';
        default: return 'User';
    }
}
```

#### `getUserTypeBadgeClass($user_type)`
Returns Bootstrap badge classes for styling.

```php
function getUserTypeBadgeClass($user_type) {
    switch ($user_type) {
        case 'alumni': return 'bg-success';
        case 'student': return 'bg-primary';
        default: return 'bg-secondary';
    }
}
```

### Registration Process

1. **User enters student ID** in registration form
2. **Real-time validation** occurs via JavaScript
3. **Auto-detection** of program and user type
4. **Server-side validation** ensures data integrity
5. **User type and program** are automatically set in database

### Frontend Integration

#### Real-time Validation
```javascript
document.getElementById('student_id').addEventListener('input', function() {
    const studentId = this.value.trim();
    if (studentId.length >= 10) {
        const result = validateStudentId(studentId);
        if (result.valid) {
            // Show auto-detected information
            showAutoDetectedInfo(result);
        } else {
            // Show error message
            showError(result.message);
        }
    }
});
```

#### Auto-detection Display
- **Program field**: Automatically populated and read-only
- **User type field**: Automatically determined and displayed with badge
- **Visual feedback**: Green/red borders for valid/invalid input

## Database Integration

### Users Table
The `users` table includes a `user_type` field:
```sql
`user_type` ENUM('student', 'alumni') NOT NULL DEFAULT 'student'
```

### Registration Query
```php
$stmt = $pdo->prepare("INSERT INTO users (name, username, student_id, password, programme, user_type, profile_image, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())");
$stmt->execute([$name, $username, $student_id, $password_hash, $programme, $user_type, $profile_image]);
```

## Chat Room Application

This logic enables the creation of chat rooms with different access levels:

### Room Types
1. **Alumni Chat Rooms**: Only accessible to alumni (year ≤ 20)
2. **Student Chat Rooms**: Only accessible to current students (year ≥ 21)
3. **General Chat Rooms**: Accessible to both students and alumni
4. **Department-specific Rooms**: Based on program codes (ITC, CSC, BED, BIT)

### Access Control Logic
```php
function canAccessRoom($user_id, $room_type, $room_department = null) {
    $user = getUser($user_id, $pdo);
    
    switch ($room_type) {
        case 'alumni':
            return $user['user_type'] === 'alumni';
        case 'student':
            return $user['user_type'] === 'student';
        case 'department':
            return $room_department === extractDepartmentFromStudentId($user['student_id']);
        case 'general':
            return true;
        default:
            return false;
    }
}
```

## Testing

### Test Cases
1. **Valid Alumni IDs**: `PS/ITC/20/0001`, `PS/CSC/19/0002`
2. **Valid Student IDs**: `PS/ITC/21/0001`, `PS/CSC/22/0002`
3. **Invalid Formats**: `INVALID_ID`, `PS/ITC/21`
4. **Invalid Programs**: `PS/ABC/21/0001`
5. **Invalid Years**: `PS/ITC/35/0001`

### Test Page
Visit `test_student_id_logic.php` to:
- Test real-time validation
- See auto-detection in action
- View example student IDs
- Test PHP helper functions

## Error Handling

### Validation Errors
- **Format Error**: "Invalid student ID format. Expected: PS/PROGRAM/YEAR/NUMBER"
- **Program Error**: "Invalid program code. Valid codes: ITC, CSC, BED, BIT"
- **Year Error**: "Invalid year. Year should be between 15-30"

### Fallback Behavior
- Invalid format defaults to 'student' user type
- Missing program code returns empty string
- Database errors log to error log

## Future Considerations

### Year Updates
- **2025**: Year 21 students become alumni
- **2026**: Year 22 students become alumni
- **Automatic Updates**: Consider yearly automatic user type updates

### Program Expansion
- Add new program codes as needed
- Update `programme_map` array
- Maintain backward compatibility

### Chat Room Features
- Alumni mentorship programs
- Student-alumni networking
- Department-specific discussions
- Career guidance rooms

## Files Modified

1. **`app/helpers/user.php`**: Added helper functions
2. **`register.php`**: Updated registration logic
3. **`profile.php`**: Enhanced user type display
4. **`nav.php`**: Updated user type display
5. **`test_student_id_logic.php`**: Created test page

## Usage Examples

### Registration
```php
$student_validation = validateStudentId($student_id);
if ($student_validation['valid']) {
    $user_type = $student_validation['user_type'];
    $programme = $student_validation['programme'];
    // Proceed with registration
}
```

### Display User Type
```php
<span class="badge <?= getUserTypeBadgeClass($user['user_type']) ?>">
    <?= getUserTypeLabel($user['user_type']) ?>
</span>
```

### Access Control
```php
if ($user['user_type'] === 'alumni') {
    // Show alumni-only features
} else {
    // Show student-only features
}
```

This system provides a robust foundation for distinguishing between students and alumni, enabling targeted features and appropriate access controls throughout the CITSA Connect platform. 