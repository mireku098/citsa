# Event Comments System - CITSA Platform

## Overview
The Event Comments System allows users to comment on events and announcements posted by administrators. Users can view, add, like, and delete their own comments, creating an interactive community experience around events.

## Features

### 🗨️ **Comment Management**
- **Add Comments**: Users can write comments up to 1000 characters
- **View Comments**: See all comments for each event with user information
- **Delete Comments**: Users can delete their own comments
- **Real-time Updates**: Comments update immediately after posting
- **Live Count Updates**: Comment counts update in real-time

### 👥 **User Experience**
- **User Profiles**: Comments show usernames and profile pictures
- **Time Stamps**: Human-readable "time ago" format
- **Auto-resize Input**: Comment input automatically adjusts height
- **Responsive Design**: Works on all device sizes

### 🎨 **Visual Design**
- **Modern UI**: Clean, card-based design with hover effects
- **Color Coding**: Different colors for different event types
- **Icons**: Bootstrap Icons for better visual communication
- **Loading States**: Spinners and loading indicators
- **Toast Notifications**: Success/error messages

## Database Structure

### Tables Created

#### `event_comments`
```sql
CREATE TABLE event_comments (
    comment_id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    comment_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('active', 'hidden', 'deleted') DEFAULT 'active',
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);
```

#### `event_comment_likes`
```sql
CREATE TABLE event_comment_likes (
    like_id INT AUTO_INCREMENT PRIMARY KEY,
    comment_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_comment_user (comment_id, user_id),
    FOREIGN KEY (comment_id) REFERENCES event_comments(comment_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);
```

## File Structure

### Backend Files
- `setup_event_comments_system.php` - Database setup script
- `get_event_comments.php` - Retrieve comments for an event
- `add_event_comment.php` - Add new comments

- `delete_event_comment.php` - Delete user's own comments

### Frontend Integration
- `home.php` - Main events page with comment functionality
- `app/helpers/timeAgo.php` - Time formatting helper functions

## Setup Instructions

### 1. Database Setup
Run the setup script to create the necessary tables:
```bash
php setup_event_comments_system.php
```

### 2. File Permissions
Ensure all PHP files have proper permissions and are accessible via web server.

### 3. Database Connection
Verify that `app/db.conn.php` contains correct database credentials.

## API Endpoints

### GET `/get_event_comments.php`
Retrieves comments for a specific event.

**Parameters:**
- `event_id` (required): The ID of the event

**Response:**
```json
{
    "success": true,
    "comments": [
        {
            "comment_id": 1,
            "comment_text": "Great event!",
            "created_at": "2025-01-20 10:30:00",
                            "user": {
                    "user_id": 5,
                    "username": "eden",
                    "profile_image": "profile_5_1753988679.jpg"
                },
            
            "is_own_comment": false
        }
    ],
    "total_count": 1
}
```

### POST `/add_event_comment.php`
Adds a new comment to an event.

**Request Body:**
```json
{
    "event_id": 1,
    "comment_text": "This is my comment"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Comment added successfully",
    "comment": {
        "comment_id": 2,
        "comment_text": "This is my comment",
        "created_at": "2025-01-20 10:35:00",
        "user": {...},
        
        "is_own_comment": true
    }
}
```



### POST `/delete_event_comment.php`
Deletes a user's own comment.

**Request Body:**
```json
{
    "comment_id": 1
}
```

**Response:**
```json
{
    "success": true,
    "message": "Comment deleted successfully"
}
```

## Frontend JavaScript Functions

### Core Functions
- `toggleComments(eventId)` - Show/hide comments section
- `loadCommentCount(eventId)` - Load comment count for an event
- `loadComments(eventId)` - Load comments from server
- `displayComments(eventId, comments)` - Render comments in UI
- `addComment(eventId)` - Submit new comment
- `deleteComment(commentId)` - Delete comment

### Helper Functions
- `formatTimeAgo(datetime)` - Format timestamp
- `updateCharCount(eventId)` - Update character counter
- `showToast(message, type)` - Display notifications
- `getEventIdFromComment(commentId)` - Find parent event

## Security Features

### Input Validation
- Comment text length limits (1-1000 characters)
- Event ID validation
- User authentication checks
- SQL injection prevention with prepared statements

### Access Control
- Users can only delete their own comments
- Comments are soft-deleted (status changed to 'deleted')
- Event must be published to allow comments

### Data Sanitization
- HTML entities are properly escaped
- User input is validated and sanitized
- Database queries use parameterized statements

## User Interface Elements

### Comment Section
- **Toggle Button**: Shows/hides comments with comment count
- **Comments List**: Scrollable list of all comments
- **Comment Form**: Styled textarea with auto-resize and send button

### Comment Display
- **User Avatar**: Profile picture from user's profile
- **User Info**: Username with timestamp beside it
- **Comment Text**: The actual comment content
- **Delete Button**: Trash icon for comment owners

## Responsive Design

### Mobile Optimizations
- Smaller padding and margins on mobile
- Touch-friendly button sizes
- Optimized comment layout for small screens
- Responsive text sizing

### Desktop Features
- Hover effects on comment cards
- Larger comment containers
- Enhanced visual hierarchy
- Better spacing and typography

## Future Enhancements

### Planned Features
- **Comment Editing**: Allow users to edit their comments
- **Comment Replies**: Nested comment system
- **Comment Moderation**: Admin tools for comment management
- **Comment Notifications**: Notify users of replies/likes
- **Comment Search**: Search within comments
- **Comment Export**: Export comments for analysis

### Technical Improvements
- **Real-time Updates**: WebSocket integration for live comments
- **Comment Pagination**: Load comments in chunks
- **Comment Caching**: Redis caching for better performance
- **Comment Analytics**: Track comment engagement metrics

## Troubleshooting

### Common Issues

#### Comments Not Loading
- Check database connection
- Verify event_comments table exists
- Check user authentication
- Review browser console for JavaScript errors

#### Comments Not Posting
- Verify user is logged in
- Check comment text length
- Ensure event exists and is published
- Check server error logs

#### Like System Not Working
- Verify event_comment_likes table exists
- Check for duplicate like entries
- Review database constraints

### Debug Mode
Enable error reporting in PHP files for development:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## Browser Compatibility

### Supported Browsers
- Chrome 80+
- Firefox 75+
- Safari 13+
- Edge 80+

### Required Features
- ES6+ JavaScript support
- Fetch API
- CSS Grid/Flexbox
- Modern CSS features

## Performance Considerations

### Database Optimization
- Indexes on frequently queried columns
- Efficient JOIN operations
- Pagination for large comment lists
- Soft deletes to maintain referential integrity

### Frontend Optimization
- Lazy loading of comments
- Debounced character counting
- Efficient DOM manipulation
- Minimal re-renders

## Support and Maintenance

### Regular Tasks
- Monitor database performance
- Clean up deleted comments periodically
- Update security patches
- Backup comment data

### Monitoring
- Track comment activity
- Monitor system performance
- User feedback collection
- Error log analysis

---

**Note**: This system is designed for educational purposes and should be deployed in a secure environment with proper access controls and regular security updates.
