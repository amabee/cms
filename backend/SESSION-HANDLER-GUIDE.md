# Session Handler Usage Guide

## Overview
The `session-handler.js` module manages user authentication state and session data across the application using localStorage and cookies.

## Features
- ✅ Stores user data from login API response
- ✅ Manages session persistence (Remember Me functionality)
- ✅ Auto-redirects to appropriate dashboards based on user role
- ✅ Protects pages requiring authentication
- ✅ Role-based access control
- ✅ Session expiration handling
- ✅ Notification management

## Setup

### 1. Include the script in your HTML:
```html
<script src="frontend/assets/js/session-handler.js"></script>
```

### 2. The SessionHandler is available globally as `window.SessionHandler`

## API Reference

### Authentication Methods

#### `saveSession(userData, rememberMe)`
Save user session after successful login.
```javascript
// After successful login
SessionHandler.saveSession(response.data, rememberMe);
```

#### `isAuthenticated()`
Check if user is logged in.
```javascript
if (SessionHandler.isAuthenticated()) {
  // User is logged in
}
```

#### `requireAuth()`
Protect a page - redirects to login if not authenticated.
```javascript
// Call at the top of your page script
if (!SessionHandler.requireAuth()) {
  return; // User will be redirected to login
}
```

#### `requireUserType(allowedTypes)`
Restrict page access to specific user roles.
```javascript
// Allow only doctors (type 2)
SessionHandler.requireUserType(2);

// Allow multiple roles (doctors and secretaries)
SessionHandler.requireUserType([2, 3]);
```

#### `logout()`
Clear session and redirect to login page.
```javascript
SessionHandler.logout();
```

### User Data Methods

#### `getSession()`
Get full session object.
```javascript
var session = SessionHandler.getSession();
// Returns: { user: {...}, notifications: [...], timestamp: ..., rememberMe: ... }
```

#### `getUser()`
Get current user object.
```javascript
var user = SessionHandler.getUser();
// Returns: { user_id, usertype_id, username, email, profile, extra }
```

#### `getUserType()`
Get user type ID.
```javascript
var userType = SessionHandler.getUserType();
// Returns: 1=Admin, 2=Doctor, 3=Secretary, 4=Receptionist, 5=Patient
```

#### `getUserFullName()`
Get user's full name.
```javascript
var fullName = SessionHandler.getUserFullName();
// Returns: "John Doe" or username if name not available
```

#### `getUserRole()`
Get user role name.
```javascript
var role = SessionHandler.getUserRole();
// Returns: "Admin", "Doctor", "Secretary", "Receptionist", or "Patient"
```

### Navigation Methods

#### `redirectToDashboard()`
Redirect to role-appropriate dashboard.
```javascript
SessionHandler.redirectToDashboard();
// Redirects based on user type:
// Admin → admin-dashboard.html
// Doctor → doctor-dashboard.html
// Secretary → secretary-dashboard.html
// Receptionist → receptionist-dashboard.html
// Patient → patient-dashboard.html
```

### Notification Methods

#### `updateNotifications(notifications)`
Update notifications in session.
```javascript
SessionHandler.updateNotifications(newNotifications);
```

#### `getUnreadNotificationsCount()`
Get count of unread notifications.
```javascript
var count = SessionHandler.getUnreadNotificationsCount();
```

## User Type IDs

| ID | Role |
|----|------|
| 1 | Admin |
| 2 | Doctor |
| 3 | Secretary |
| 4 | Receptionist |
| 5 | Patient |

## Example Usage

### Login Page
```javascript
// Check if already logged in
if (SessionHandler.isAuthenticated()) {
  SessionHandler.redirectToDashboard();
  return;
}

// After successful login
axios.post('backend/auth.php', postData)
  .then(function(response) {
    if (response.data.success) {
      var rememberMe = document.getElementById('remember').checked;
      SessionHandler.saveSession(response.data, rememberMe);
      SessionHandler.redirectToDashboard();
    }
  });
```

### Protected Page
```javascript
// Require authentication
if (!SessionHandler.requireAuth()) {
  return;
}

// Display user info
document.getElementById('userName').textContent = SessionHandler.getUserFullName();
document.getElementById('userRole').textContent = SessionHandler.getUserRole();

// Logout button
document.getElementById('logoutBtn').addEventListener('click', function() {
  SessionHandler.logout();
});
```

### Role-Restricted Page (Doctor Only)
```javascript
// Only allow doctors to access this page
if (!SessionHandler.requireUserType(2)) {
  return;
}

// Doctor-specific code here
var user = SessionHandler.getUser();
console.log('Doctor ID:', user.extra.doctor_id);
```

### Role-Restricted Page (Multiple Roles)
```javascript
// Allow doctors and secretaries
if (!SessionHandler.requireUserType([2, 3])) {
  return;
}

// Code for doctors and secretaries
```

## Storage Details

- **localStorage**: Stores complete session data (user object, notifications, timestamp)
- **Cookies**: Stores quick-check flags (clinic_auth, clinic_user_id, clinic_usertype)
- **Session Duration**: 24 hours (unless "Remember Me" is checked, then 30 days)

## Security Notes

- Session data is stored in localStorage (client-side)
- Never store sensitive data like passwords in session
- Server should still validate session/token on each request
- Consider implementing JWT tokens for production
- Add CSRF protection for form submissions

## Dashboard Redirect URLs

Current paths in `session-handler.js`:

```javascript
const dashboards = {
  1: 'frontend/admin/dashboard.html',
  2: 'frontend/doctor/dashboard.html',
  3: 'frontend/secretary/dashboard.html',
  4: 'frontend/receptionist/dashboard.html',
  5: 'frontend/patients/dashboard.html'
};
```

**Note**: All frontend pages are organized in the `frontend/` folder by user role. Paths are relative to the domain root.

## Browser Compatibility

- Requires localStorage support (all modern browsers)
- Requires cookie support
- IE11+ supported
