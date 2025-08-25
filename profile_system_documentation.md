# Profile System Documentation

## Overview
The profile system provides a comprehensive user profile management interface for CITSA Connect. Users can view their profile information, edit their details, change their password, and upload profile images.

## Features

### 🎯 **Core Features**
- **Profile Overview**: Display user information, statistics, and recent activity
- **Profile Editing**: Update personal information and profile image
- **Password Management**: Secure password change functionality
- **Image Upload**: Profile picture upload with validation
- **Responsive Design**: Mobile-friendly interface

### 📊 **User Statistics**
- **Friend Count**: Number of accepted friend connections
- **Message Count**: Total messages sent/received
- **User Status**: Student or Alumni designation
- **Member Since**: Account creation date

### 🖼️ **Profile Image Management**
- **Supported Formats**: JPEG, PNG, GIF, WebP
- **Size Limit**: Maximum 5MB
- **Auto-cleanup**: Old images are automatically deleted
- **Default Avatar**: Fallback to default image if none uploaded

## File Structure

### **Frontend Files**
- `profile.php` - Main profile page with tabs and forms
- `nav.php` - Navigation component (included)
- `footer.php` - Footer component (included)

### **Backend Files**
- `update_profile.php` - Handles profile updates and image uploads
- `change_password.php` - Handles password changes
- `app/db.conn.php` - Database connection
- `app/helpers/user.php` - User helper functions

### **Assets**
- `profile/` - Directory for uploaded profile images
- `profile/default-avatar.png` - Default profile image

## Database Tables

### **Users Table**
```sql
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(225) NOT NULL,
    username VARCHAR(225) UNIQUE NOT NULL,
    student_id VARCHAR(225) UNIQUE NOT NULL,
    password VARCHAR(1000) NOT NULL,
    profile_image VARCHAR(225) DEFAULT 'default-avatar.png',
    programme VARCHAR(225) NOT NULL,
    user_type ENUM('student', 'alumni') DEFAULT 'student',
    about TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### **Friends Table**
```sql
CREATE TABLE friends (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(user_id),
    FOREIGN KEY (receiver_id) REFERENCES users(user_id)
);
```

## User Interface

### **Profile Overview Tab**
- **Personal Information**: Name, username, student ID, programme
- **Account Details**: User type, member since date
- **About Section**: User bio (if provided)
- **Statistics Cards**: Friend count, message count, status

### **Edit Profile Tab**
- **Profile Image**: Current image display with upload option
- **Personal Details**: Editable name, username, programme
- **About Section**: Textarea for bio/description
- **Form Validation**: Required field validation

### **Change Password Tab**
- **Current Password**: Verification field
- **New Password**: Input with strength requirements
- **Confirm Password**: Matching validation
- **Security**: Minimum 8 characters required

## Security Features

### **Input Validation**
- **File Upload**: Type and size validation
- **Password Strength**: Minimum length requirements
- **Username Uniqueness**: Prevents duplicate usernames
- **SQL Injection Prevention**: Prepared statements

### **Session Management**
- **Authentication Check**: Redirects to login if not authenticated
- **CSRF Protection**: Token-based form protection
- **Secure Redirects**: Proper header redirects

### **File Security**
- **Upload Directory**: Isolated profile image directory
- **File Type Validation**: Only image files allowed
- **Size Limits**: Prevents large file uploads
- **Cleanup**: Automatic deletion of old images

## Error Handling

### **User Feedback**
- **Success Messages**: Green notifications for successful operations
- **Error Messages**: Red notifications for failed operations
- **Validation Errors**: Specific error messages for form validation
- **SweetAlert2**: Modern, responsive alert system

### **Exception Handling**
- **Database Errors**: Graceful handling of database issues
- **File Upload Errors**: Proper error messages for upload failures
- **Missing Tables**: Fallback handling for missing database tables

## Responsive Design

### **Mobile Optimization**
- **Flexible Layout**: Adapts to different screen sizes
- **Touch-Friendly**: Large buttons and touch targets
- **Readable Text**: Appropriate font sizes for mobile
- **Optimized Images**: Responsive image sizing

### **Desktop Features**
- **Sidebar Layout**: Profile card and content side-by-side
- **Hover Effects**: Interactive elements with hover states
- **Tab Navigation**: Clean tab-based interface
- **Statistics Display**: Visual representation of user stats

## JavaScript Features

### **Form Validation**
- **Client-side Validation**: Real-time form checking
- **Password Matching**: Confirm password validation
- **File Preview**: Image preview before upload
- **AJAX Submissions**: Smooth form submissions

### **User Experience**
- **AOS Animations**: Scroll-triggered animations
- **Loading States**: Visual feedback during operations
- **Auto-focus**: Smart focus management
- **Keyboard Navigation**: Tab and enter key support

## Performance Optimizations

### **Database Queries**
- **Efficient Joins**: Optimized queries for user data
- **Indexed Columns**: Proper database indexing
- **Error Handling**: Graceful fallbacks for missing data
- **Connection Management**: Proper PDO usage

### **File Management**
- **Image Optimization**: Appropriate image sizes
- **Directory Structure**: Organized file storage
- **Cleanup Processes**: Automatic old file removal
- **Caching**: Browser caching for static assets

## Future Enhancements

### **Planned Features**
- **Profile Privacy**: Public/private profile settings
- **Social Links**: Integration with social media
- **Activity Timeline**: Detailed activity history
- **Profile Verification**: Email/phone verification
- **Two-Factor Authentication**: Enhanced security

### **Technical Improvements**
- **Image Cropping**: Client-side image editing
- **Progressive Upload**: Large file upload progress
- **Real-time Updates**: Live profile updates
- **API Integration**: RESTful API endpoints

## Usage Instructions

### **For Users**
1. Navigate to the profile page
2. View your profile information in the Overview tab
3. Edit your details in the Edit Profile tab
4. Change your password in the Change Password tab
5. Upload a new profile image if desired

### **For Developers**
1. Ensure database tables are created
2. Set up the profile directory with write permissions
3. Configure file upload limits in PHP
4. Test all form submissions and validations
5. Verify responsive design on different devices

## Troubleshooting

### **Common Issues**
- **Image Upload Fails**: Check directory permissions and file size limits
- **Password Change Error**: Verify current password is correct
- **Profile Not Loading**: Check database connection and user session
- **Mobile Layout Issues**: Test responsive breakpoints

### **Debug Information**
- **Error Logging**: Check PHP error logs for detailed errors
- **Database Queries**: Verify table structure and data integrity
- **File Permissions**: Ensure profile directory is writable
- **Session Issues**: Check session configuration and cookies 