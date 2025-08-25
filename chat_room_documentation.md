# Chat Room System Documentation

## Overview

The Chat Room System is a comprehensive multi-platform chat solution designed for CITSA Connect, providing different chat environments based on user types, academic levels, programs, and club memberships.

## Features

### 1. Multiple Chat Platforms

#### General Platform
- **Access**: All users (students and alumni)
- **Purpose**: Open discussion platform for the entire community
- **Room ID**: `general`

#### Students-Only Platform
- **Access**: Current students only
- **Purpose**: Exclusive platform for current students
- **Room ID**: `students_only`

#### Alumni-Only Platform
- **Access**: Alumni only
- **Purpose**: Exclusive platform for alumni networking
- **Room ID**: `alumni_only`

### 2. Year-Based Platforms (Level Platforms)

#### Level Classification Logic
- **Year 21+**: Level 400 (Final Year)
- **Year 22+**: Level 300 (Third Year)
- **Year 23+**: Level 200 (Second Year)
- **Year 24+**: Level 100 (First Year)

#### Access Control
- Students are automatically placed in their corresponding level platform
- Room IDs: `level_100`, `level_200`, `level_300`, `level_400`

### 3. Program-Based Platforms

#### Supported Programs
- **ITC**: Information Technology Platform
- **CSC**: Computer Science Platform
- **BED**: B.Ed. Information Technology Platform
- **BIT**: B.Ed. Computer Science Platform

#### Access Control
- Students are automatically placed in their program platform
- Room IDs: `program_ITC`, `program_CSC`, `program_BED`, `program_BIT`

### 4. Club-Based Platforms

#### Available Clubs
1. **Networking Club** - Network administration and security
2. **Cybersecurity Club** - Cybersecurity and ethical hacking
3. **Web Development Club** - Web development and design
4. **Machine Learning & AI Club** - AI and machine learning

#### Club Management
- Students can join a maximum of **2 clubs**
- Join/Leave functionality with confirmation dialogs
- Access to club chat rooms only for members

## Database Structure

### Tables

#### `chat_room_messages`
```sql
CREATE TABLE chat_room_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_id VARCHAR(50) NOT NULL,
    sender_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE
);
```

#### `user_clubs`
```sql
CREATE TABLE user_clubs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    club_id INT NOT NULL,
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY user_club_unique (user_id, club_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);
```

#### `clubs`
```sql
CREATE TABLE clubs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

## Helper Functions

### Core Functions

#### `getUserYearFromStudentId($student_id)`
- Extracts year from student ID format: `PS/[PROGRAM]/[YEAR]/[NUMBER]`
- Returns integer year or null if invalid format

#### `getLevelFromYear($year)`
- Maps year to academic level
- Returns level number (100, 200, 300, 400)

#### `getUserProgramCode($student_id)`
- Extracts program code from student ID
- Returns program code (ITC, CSC, BED, BIT) or null

#### `canAccessRoom($user, $room_id, $pdo)`
- Checks if user can access a specific room
- Implements all access control logic
- Returns boolean

#### `getAvailableRooms($user, $pdo)`
- Returns array of rooms user can access
- Includes room metadata and membership status

#### `getRoomMessages($room_id, $last_message_id, $pdo)`
- Retrieves messages for a room
- Supports pagination via `last_message_id`

#### `sendRoomMessage($room_id, $sender_id, $message, $pdo)`
- Sends a message to a room
- Returns message ID on success

#### `joinClub($user_id, $club_id, $pdo)`
- Adds user to a club
- Enforces 2-club maximum limit
- Returns boolean success status

#### `leaveClub($user_id, $club_id, $pdo)`
- Removes user from a club
- Returns boolean success status

## User Interface

### Chat Room Interface
- **Sidebar**: Lists available rooms with icons and descriptions
- **Main Chat Area**: Message display with sender information
- **Input Area**: Message composition with send button
- **Real-time Updates**: Automatic message polling every 3 seconds

### Room Types and Icons
- 🌐 General Platform
- 🎓 Students Only
- 👔 Alumni Network
- 📚 Level Platforms
- 💻 Program Platforms
- 👥 Club Platforms

### Message Display
- **Sent Messages**: Right-aligned with blue background
- **Received Messages**: Left-aligned with gray background
- **Sender Info**: Name and user type badge
- **Timestamp**: Formatted time display

## Security Features

### Access Control
- Server-side validation of room access
- User type verification
- Club membership checks
- Year/level validation

### Input Validation
- Message content sanitization
- Empty message prevention
- SQL injection protection via prepared statements

### Session Management
- Login requirement for all chat functions
- Session-based user identification

## Setup Instructions

### 1. Database Setup
Run the setup script:
```bash
http://localhost/citsa/setup_chat_rooms.php
```

### 2. Test the System
Visit the test page:
```bash
http://localhost/citsa/test_chat_rooms.php
```

### 3. Access Chat Rooms
Navigate to:
```bash
http://localhost/citsa/chat_room.php
```

## Usage Examples

### For Students
1. **General Platform**: Open discussion with all users
2. **Students Only**: Connect with current students
3. **Level Platform**: Chat with classmates in same year
4. **Program Platform**: Discuss program-specific topics
5. **Club Platforms**: Join up to 2 clubs for specialized discussions

### For Alumni
1. **General Platform**: Stay connected with the community
2. **Alumni Network**: Network with fellow alumni
3. **Club Platforms**: Join clubs if they were members as students

## Technical Implementation

### AJAX Endpoints
- `action=send_message`: Send message to room
- `action=get_messages`: Retrieve room messages
- `action=join_club`: Join a club
- `action=leave_club`: Leave a club

### Real-time Features
- Message polling every 3 seconds
- Automatic scroll to bottom for new messages
- User activity tracking
- Responsive design for mobile devices

### Error Handling
- Access denied notifications
- Club limit enforcement
- Network error recovery
- Graceful degradation

## Future Enhancements

### Planned Features
- File sharing in chat rooms
- Message reactions and emojis
- User typing indicators
- Message search functionality
- Room moderation tools
- Push notifications
- Message encryption
- Voice/video chat integration

### Scalability Considerations
- Database indexing for performance
- Message pagination for large rooms
- Caching for frequently accessed data
- Load balancing for high traffic

## Troubleshooting

### Common Issues
1. **Access Denied**: Check user type and club membership
2. **Messages Not Loading**: Verify room access permissions
3. **Club Join Failed**: Check 2-club limit
4. **Database Errors**: Run setup script to create tables

### Debug Tools
- `test_chat_rooms.php`: Comprehensive system test
- Browser console logs for JavaScript errors
- Server error logs for PHP issues

## Support

For technical support or feature requests, contact the system administrator or refer to the CITSA Connect documentation. 