# Club Approval System

## Overview

The Club Approval System implements a secure approval workflow for students wanting to join clubs. Instead of direct joining, students submit join requests that must be approved by administrators before they can access club chat platforms and participate in club activities.

## Key Features

- **Student-Only Access**: Only students can join clubs (alumni and other user types are restricted)
- **Maximum Club Limit**: Students can only join a maximum of 2 clubs
- **Admin Approval Required**: All join requests must be approved by administrators
- **Request Tracking**: Full tracking of request status, approval/rejection reasons, and timestamps
- **Chat Access Control**: Only approved members can send messages in club chat platforms
- **Status Management**: Clear visual indicators for pending, approved, and rejected requests

## How It Works

### 1. Student Request Process
1. Student clicks "Join Club" button in chat room
2. System checks eligibility (student type, club count < 2)
3. Join request is created with 'pending' status
4. Student sees "Request submitted" confirmation
5. Join button changes to "Pending" status

### 2. Admin Approval Process
1. Admin navigates to "Club Requests" in admin panel
2. Views all pending join requests with student details
3. Can approve or reject each request
4. Optional rejection reason can be provided
5. Student is notified of decision

### 3. Access Control
1. Only approved club members can send messages in club chats
2. Unapproved users see "Access denied" message
3. System automatically enforces membership requirements

## Database Structure

### user_clubs Table
```sql
CREATE TABLE user_clubs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    club_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL,
    processed_at TIMESTAMP NULL,
    rejection_reason TEXT NULL,
    joined_at TIMESTAMP NULL,
    UNIQUE KEY unique_user_club (user_id, club_id),
    INDEX idx_status (status),
    INDEX idx_user_id (user_id),
    INDEX idx_club_id (club_id)
);
```

### Key Fields
- **status**: Request status (pending/approved/rejected)
- **requested_at**: When the request was submitted
- **approved_at**: When the request was approved
- **rejection_reason**: Reason for rejection (if applicable)
- **joined_at**: When the user actually joined (for approved requests)

## Setup Instructions

### 1. Database Setup
Run the setup script to create/modify the database structure:

```bash
# Navigate to your project directory
cd /path/to/your/project

# Run the setup script
php setup_club_approval_system.php
```

This script will:
- Create the `user_clubs` table if it doesn't exist
- Add necessary columns to existing tables
- Create database indexes for performance
- Update existing records to proper status

### 2. File Verification
Ensure these files are present and properly included:

- `app/helpers/club_management.php` - Core club management functions
- `admin/club_requests.php` - Admin interface for managing requests
- Updated `chat_room.php` - Modified join functionality
- Updated `admin/index.php` - Added Club Requests navigation

### 3. Testing
1. Test student club join requests
2. Verify admin approval/rejection functionality
3. Confirm chat access restrictions work properly

## User Experience

### For Students
- **Join Button States**:
  - Default: "Join Club" (blue)
  - Pending: "Pending" (yellow, disabled)
  - Approved: "Member" (green, disabled)
  - Rejected: "Rejected" (red, disabled)

- **Request Process**:
  - Click "Join Club" → Request submitted
  - Wait for admin approval
  - Receive notification of decision
  - Access granted/denied accordingly

### For Administrators
- **Club Requests Panel**:
  - View all pending requests
  - See student details and club information
  - Approve or reject requests
  - Provide rejection reasons
  - Track request history

## API Endpoints

### Student Actions
- `POST /chat_room.php?action=join_club` - Submit join request
- `POST /chat_room.php?action=check_club_request_status` - Check request status

### Admin Actions
- `POST /admin/club_requests.php?action=approve_request` - Approve request
- `POST /admin/club_requests.php?action=reject_request` - Reject request
- `POST /admin/club_requests.php?action=get_pending_requests` - Get pending requests

## Security Features

### Access Control
- **Student Verification**: Only users with `user_type = 'student'` can join clubs
- **Club Limit Enforcement**: Maximum 2 clubs per student
- **Chat Access Control**: Only approved members can send messages in club chats
- **Admin Authentication**: Club request management requires admin privileges

### Data Validation
- **Request Uniqueness**: One pending/approved request per user per club
- **Status Validation**: Proper status transitions (pending → approved/rejected)
- **Timestamp Tracking**: Full audit trail of request lifecycle

## Troubleshooting

### Common Issues

#### Students Can't Join Clubs
- Verify user has `user_type = 'student'`
- Check if user has already joined 2 clubs
- Ensure club exists and is active
- Check database connection and table structure

#### Admin Can't See Requests
- Verify admin is logged in with proper privileges
- Check if `club_management.php` helper is included
- Ensure `user_clubs` table exists with proper structure
- Check for JavaScript errors in browser console

#### Chat Access Denied
- Verify user is approved member of the club
- Check club ID format in room_id
- Ensure `canUserAccessClubChat()` function is working
- Verify database queries are returning correct results

### Error Logs
Check your PHP error logs for:
- Database connection issues
- Missing table/column errors
- Function call failures
- Permission denied errors

## Customization

### Adding New Status Types
To add new request statuses (e.g., 'under_review'):

1. Modify the `status` ENUM in the database
2. Update the `updateJoinButtonStatus()` function
3. Add corresponding CSS classes for new statuses
4. Update admin interface to handle new statuses

### Modifying Approval Rules
To change approval requirements:

1. Modify the `canUserJoinClub()` function
2. Update validation logic in `createClubJoinRequest()`
3. Adjust admin approval workflow if needed
4. Update user interface messages

### Adding Notification System
To implement email/SMS notifications:

1. Create notification helper functions
2. Integrate with approval/rejection processes
3. Add notification preferences to user settings
4. Implement notification templates

## Future Enhancements

### Potential Improvements
1. **Email Notifications**: Automatic emails for request status changes
2. **Bulk Operations**: Approve/reject multiple requests at once
3. **Request History**: Full audit trail of all requests
4. **Auto-Approval**: Rules-based automatic approval for certain conditions
5. **Club Admin Roles**: Allow club creators to approve their own requests

### Advanced Features
1. **Request Expiration**: Automatic rejection of old pending requests
2. **Approval Workflow**: Multi-level approval process
3. **Club Categories**: Different rules for different types of clubs
4. **Seasonal Restrictions**: Time-based joining limitations
5. **Integration**: Connect with student registration systems

## Support

For issues or questions about the club approval system:

1. **Check this documentation** for setup and troubleshooting
2. **Review error logs** for specific error messages
3. **Verify database structure** using the setup script
4. **Test functionality** step by step
5. **Check file permissions** and include paths

## Compliance

This system provides:
- **Access Control**: Proper restriction of club access
- **Audit Trail**: Complete tracking of all join requests
- **Data Privacy**: Student information protected by approval workflow
- **Administrative Oversight**: Proper control over club membership
- **Scalability**: Efficient database structure for large numbers of requests

---

**Note**: This system is designed to work with the existing CITSA platform structure. Ensure all dependencies are properly included and the database structure matches the expected schema.
