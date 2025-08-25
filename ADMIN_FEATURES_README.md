# CITSA Admin Dashboard - New Features

## Overview
This document describes the new administrator management and event posting features added to the CITSA admin dashboard.

## New Features Added

### 1. Administrator Management
- **Add Administrators**: Create new admin accounts with different roles
- **Role-based Access**: Three levels of access:
  - **Super Admin**: Full system access (all permissions)
  - **Admin**: Administrative access (users, chat rooms, clubs, authorized users, events)
  - **Editor**: Content management access (events, announcements only)
- **View Administrators**: See all admin accounts with their roles and last activity

### 2. Event Management
- **Create Events**: Post various types of content:
  - Events (general events)
  - Announcements (important notices)
  - Meetings (scheduled meetings)
  - Workshops (training sessions)
- **Event Status**: Draft or Published
- **Rich Content**: Title, description, date, time, location
- **User Dashboard**: Public events page for users to view announcements

## Database Changes

### New Tables Created
1. **`admin_roles`** - Defines admin roles and permissions
2. **`events`** - Stores events and announcements
3. **`event_attachments`** - Stores files related to events

### Modified Tables
1. **`admins`** - Added `role_id` column for role-based access

## Setup Instructions

### 1. Database Setup
Run the SQL commands in `admin/create_tables.sql` to create the necessary tables:

```sql
-- Run this file in your MySQL database
source admin/create_tables.sql;
```

### 2. File Structure
Ensure these new files are in place:
```
admin/
├── get_administrators.php
├── add_administrator.php
├── delete_administrator.php
├── get_events.php
├── add_event.php
├── delete_event.php
└── create_tables.sql

events.php (in root directory)
```

### 3. Default Admin Account
The system comes with a default super admin account:
- **Username**: admin
- **Password**: password
- **Role**: Super Admin

## Usage Guide

### Adding New Administrators
1. Log in to the admin dashboard
2. Navigate to "Administrators" tab
3. Click "Add Administrator" button
4. Fill in the required information:
   - First Name & Last Name
   - Username (must be unique)
   - Email (must be unique)
   - Department
   - Position
   - Role (Admin or Editor)
   - Password
5. Click "Add Administrator"

### Creating Events
1. Navigate to "Events" tab
2. Click "Create Event" button
3. Fill in event details:
   - Title
   - Description
   - Date & Time
   - Location (optional)
   - Event Type
   - Status (Draft or Published)
4. Click "Create Event"

### Event Types
- **Event**: General events and activities
- **Announcement**: Important notices and updates
- **Meeting**: Scheduled meetings
- **Workshop**: Training sessions and workshops

### User Access
Users can view published events at `/events.php`:
- Filter events by type
- View detailed information
- See event locations and times
- Access to all published content

## Security Features

### Role-based Access Control
- **Super Admins**: Can manage all aspects including other admins
- **Admins**: Can manage users, content, and events
- **Editors**: Can only create and manage events/announcements

### Session Management
- All admin functions require valid session
- Password hashing using bcrypt
- Input validation and sanitization

## API Endpoints

### Administrator Management
- `GET admin/get_administrators.php` - Fetch all administrators
- `POST admin/add_administrator.php` - Add new administrator
- `POST admin/delete_administrator.php` - Delete administrator

### Event Management
- `GET admin/get_events.php` - Fetch all events
- `POST admin/add_event.php` - Create new event
- `POST admin/delete_event.php` - Delete event

## Troubleshooting

### Common Issues
1. **Database Connection Errors**: Ensure database credentials are correct in `app/db.conn.php`
2. **Permission Denied**: Check if the admin has the required role for the action
3. **Events Not Displaying**: Verify events are set to "published" status

### Database Issues
- If tables don't exist, run the `create_tables.sql` file
- Check for foreign key constraints
- Ensure proper database permissions

## Future Enhancements

### Planned Features
- Event editing functionality
- File attachments for events
- Event categories and tags
- Event notifications
- Calendar integration
- Event registration system

### Customization
- Modify event types in the database
- Add new admin roles
- Customize event display templates
- Add event approval workflow

## Support

For technical support or feature requests, contact the development team or refer to the main CITSA platform documentation.

---

**Note**: This system is designed for educational purposes and should be deployed in a secure environment with proper access controls and regular security updates.
