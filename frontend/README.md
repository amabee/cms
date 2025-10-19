# Frontend Structure

All frontend pages are organized inside the `frontend/` folder by user role.

## Directory Structure

```
frontend/
├── admin/              # Admin dashboard and pages
│   └── dashboard.html
├── doctor/             # Doctor dashboard and pages
├── secretary/          # Secretary dashboard and pages
├── receptionist/       # Receptionist dashboard and pages
├── patients/           # Patient dashboard and pages
└── assets/             # Shared assets (CSS, JS, images)
    ├── css/
    ├── js/
    │   └── session-handler.js  # Reusable session management
    ├── img/
    └── vendor/
```

## URL Access Patterns

- **Admin Dashboard**: `http://cms.dev/frontend/admin/dashboard.html`
- **Doctor Dashboard**: `http://cms.dev/frontend/doctor/dashboard.html`
- **Secretary Dashboard**: `http://cms.dev/frontend/secretary/dashboard.html`
- **Receptionist Dashboard**: `http://cms.dev/frontend/receptionist/dashboard.html`
- **Patient Dashboard**: `http://cms.dev/frontend/patients/dashboard.html`

## Asset Paths

When creating pages inside usertype folders (admin, doctor, etc.), use **relative paths**:

### From `frontend/admin/dashboard.html`:
```html
<!-- CSS -->
<link rel="stylesheet" href="../assets/vendor/css/core.css" />
<link rel="stylesheet" href="../assets/css/demo.css" />

<!-- Images -->
<img src="../assets/img/favicon/logo.png" />

<!-- JavaScript -->
<script src="../assets/vendor/js/bootstrap.js"></script>
<script src="../assets/js/session-handler.js"></script>
```

## Session Handler Integration

All dashboard pages should include the session handler and require appropriate authentication:

```javascript
// Include the session handler
<script src="../assets/js/session-handler.js"></script>

<script>
  // Require specific user type
  if (!SessionHandler.requireUserType(1)) {  // 1=Admin, 2=Doctor, etc.
    return;
  }
  
  // Get user data
  var user = SessionHandler.getUser();
  var fullName = SessionHandler.getUserFullName();
</script>
```

## User Type IDs

| ID | Role | Dashboard Path |
|----|------|----------------|
| 1 | Admin | `frontend/admin/dashboard.html` |
| 2 | Doctor | `frontend/doctor/dashboard.html` |
| 3 | Secretary | `frontend/secretary/dashboard.html` |
| 4 | Receptionist | `frontend/receptionist/dashboard.html` |
| 5 | Patient | `frontend/patients/dashboard.html` |

## Notes

- The `patients` folder uses plural form (not `patient`)
- All static assets are shared from `frontend/assets/`
- Backend API calls go to `../../backend/` from usertype folders
- Login page remains at root: `login.html`
