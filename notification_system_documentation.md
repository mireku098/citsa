# Notification System Documentation

## Overview
The notification system has been implemented to show friend request notifications and message notifications in real-time. When a user receives a new friend request or unread message, it will be displayed in the respective notification dropdowns.

## Features

### 1. Real-time Notification Badge
- Shows the number of pending friend requests on the bell icon
- Badge automatically updates when requests are accepted/rejected
- Badge disappears when there are no pending requests

### 2. Notification Dropdown
- Displays all pending friend requests
- Shows sender's profile picture, name, and programme
- Shows when the request was sent (time ago format)
- Provides Accept and Decline buttons for each request

### 3. Accept/Decline Functionality
- Accept button: Accepts the friend request and adds both users as friends
- Decline button: Rejects the friend request
- Both actions show success/error messages using SweetAlert2
- Notification count updates automatically after actions

### 4. Message Notifications
- Shows unread message count on the message icon
- Displays recent conversations with unread messages
- Shows sender details, last message, and unread count
- Provides "Open Chat" button to go directly to conversation
- Badge automatically updates when messages are read

### 5. Auto-refresh
- Notifications refresh every 30 seconds automatically
- Manual refresh when dropdown is opened

## Files Created/Modified

### New Files:
1. `app/helpers/notifications.php` - Helper functions for notification system
2. `handle_friend_request.php` - AJAX handler for accept/reject actions
3. `get_notifications.php` - AJAX endpoint to fetch friend request notifications
4. `get_message_notifications.php` - AJAX endpoint to fetch message notifications
5. `test_notifications.php` - Test page to verify friend request functionality
6. `test_message_notifications.php` - Test page to verify message functionality
7. `notification_system_documentation.md` - This documentation

### Modified Files:
1. `nav.php` - Updated to include notification system with dropdown

## Database Structure
The system uses the existing `friend_requests` table:
```sql
CREATE TABLE friend_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(user_id),
    FOREIGN KEY (receiver_id) REFERENCES users(user_id)
);
```

## Helper Functions

### `getPendingFriendRequestsCount($user_id, $pdo)`
Returns the number of pending friend requests for a user.

### `getPendingFriendRequests($user_id, $pdo)`
Returns an array of pending friend requests with sender details.

### `acceptFriendRequest($request_id, $current_user_id, $pdo)`
Accepts a friend request and creates bidirectional friendship.

### `rejectFriendRequest($request_id, $current_user_id, $pdo)`
Rejects a friend request.

### `getUnreadMessagesCount($user_id, $pdo)`
Returns the total number of unread messages for a user.

### `getUnreadMessages($user_id, $pdo)`
Returns an array of unread messages with sender details.

### `getRecentConversationsWithUnread($user_id, $pdo)`
Returns recent conversations that have unread messages.

## API Endpoints

### GET `/get_notifications.php`
Returns JSON with pending friend requests and count.

### GET `/get_message_notifications.php`
Returns JSON with unread messages and recent conversations.

### POST `/handle_friend_request.php`
Accepts POST data:
- `action`: 'accept' or 'reject'
- `request_id`: The friend request ID

Returns JSON response with success status and updated count.

## Usage

### For Users:
1. **Friend Request Notifications:**
   - Click the bell icon in the navigation to see friend request notifications
   - Click "Accept" to accept a friend request
   - Click "Decline" to reject a friend request
   - Click "View All Friend Requests" to go to the friends page

2. **Message Notifications:**
   - Click the message icon in the navigation to see unread messages
   - Click "Open Chat" to go directly to a conversation
   - Click "View All Messages" to go to the private messages page

### For Developers:
1. Include `app/helpers/notifications.php` in your files
2. Use the helper functions to get notification data
3. The notification system is automatically included in `nav.php`

## Testing
- Visit `test_notifications.php` to test the friend request notification system functionality.
- Visit `test_message_notifications.php` to test the message notification system functionality.

## Styling
The notification dropdown uses custom CSS classes:
- `.notification-dropdown` - Main dropdown container
- `.notification-item` - Individual notification items
- `.notification-avatar` - Profile picture styling
- `.notification-name` - Sender name styling
- `.notification-programme` - Programme text styling
- `.notification-time` - Time ago text styling
- `.notification-actions` - Action buttons container

## Browser Compatibility
- Modern browsers with ES6+ support
- Requires Bootstrap 5.3.0+
- Requires SweetAlert2 for alerts
- Uses Fetch API for AJAX requests 