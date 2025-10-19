# 🔌 Clinic CMS - API Integration Guide

## Backend API Endpoints

All API endpoints are located in the `backend/` folder and follow the same pattern:
- **Method**: POST or GET
- **Parameters**: 
  - `operation`: The operation to perform
  - `json`: JSON string with request data
- **Response**: JSON with `success` field or `error` field

---

## 1. Authentication API (`auth.php`)

### Login
```javascript
const params = new URLSearchParams();
params.append('operation', 'login');
params.append('json', JSON.stringify({
  username: 'admin',
  password: 'admin123'
}));

const response = await axios.post('backend/auth.php', params);
```

**Response**:
```json
{
  "success": true,
  "user": {
    "user_id": 1,
    "usertype_id": 1,
    "username": "admin",
    "email": "admin@clinic.com",
    "profile": { "first_name": "John", "last_name": "Admin" },
    "extra": {}
  },
  "notifications": []
}
```

---

## 2. Users API (`users.php`)

### Get All Users
```javascript
params.append('operation', 'getAll');
params.append('json', JSON.stringify({
  usertype_id: 2, // optional: filter by user type
  status: 'active', // optional: filter by status
  search: 'john' // optional: search term
}));
```

### Get User by ID
```javascript
params.append('operation', 'getById');
params.append('json', JSON.stringify({ user_id: 1 }));
```

### Create User
```javascript
params.append('operation', 'create');
params.append('json', JSON.stringify({
  username: 'newuser',
  password: 'password123',
  email: 'user@clinic.com',
  usertype_id: 2, // 1=Admin, 2=Doctor, 3=Secretary, 4=Receptionist, 5=Patient
  first_name: 'John',
  last_name: 'Doe',
  phone: '123-456-7890',
  status: 'active',
  admin_user_id: 1 // for logging
}));
```

### Update User
```javascript
params.append('operation', 'update');
params.append('json', JSON.stringify({
  user_id: 2,
  email: 'newemail@clinic.com',
  first_name: 'Jane',
  last_name: 'Smith',
  phone: '987-654-3210',
  status: 'active',
  password: 'newpass123', // optional: only if changing password
  admin_user_id: 1
}));
```

### Delete User
```javascript
params.append('operation', 'delete');
params.append('json', JSON.stringify({
  user_id: 2,
  admin_user_id: 1
}));
```

---

## 3. Doctors API (`doctors.php`)

### Get All Doctors
```javascript
params.append('operation', 'getAll');
params.append('json', JSON.stringify({
  specialization: 'Cardiology', // optional
  status: 'active', // optional
  search: 'smith' // optional
}));
```

### Get Doctor by ID
```javascript
params.append('operation', 'getById');
params.append('json', JSON.stringify({ doctor_id: 1 }));
```

### Create Doctor
```javascript
params.append('operation', 'create');
params.append('json', JSON.stringify({
  first_name: 'John',
  last_name: 'Smith',
  email: 'dr.smith@clinic.com',
  phone: '123-456-7890',
  specialization: 'Cardiology',
  license_no: 'MD-12345',
  department_id: 1, // optional
  status: 'active',
  admin_user_id: 1
}));
```
**Note**: Default password is `doctor123`

### Update Doctor
```javascript
params.append('operation', 'update');
params.append('json', JSON.stringify({
  doctor_id: 1,
  first_name: 'Jane',
  last_name: 'Smith',
  email: 'dr.jsmith@clinic.com',
  specialization: 'Pediatrics',
  license_no: 'MD-12345',
  status: 'active',
  admin_user_id: 1
}));
```

### Delete Doctor
```javascript
params.append('operation', 'delete');
params.append('json', JSON.stringify({
  doctor_id: 1,
  admin_user_id: 1
}));
```

### Get Statistics
```javascript
params.append('operation', 'getStatistics');
params.append('json', JSON.stringify({}));
```

**Response**:
```json
{
  "success": true,
  "data": {
    "total": 10,
    "active": 8,
    "specializations": 5,
    "on_duty": 3
  }
}
```

---

## 4. Staff API (`staff.php`)

### Get All Staff
```javascript
params.append('operation', 'getAll');
params.append('json', JSON.stringify({
  usertype_id: 3, // optional: 3=Secretary, 4=Receptionist
  status: 'active', // optional
  search: 'jane' // optional
}));
```

### Create Staff
```javascript
params.append('operation', 'create');
params.append('json', JSON.stringify({
  first_name: 'Jane',
  last_name: 'Doe',
  email: 'jane@clinic.com',
  phone: '123-456-7890',
  usertype_id: 3, // 3=Secretary, 4=Receptionist
  assigned_doctor_id: 1, // optional, for secretaries
  status: 'active',
  admin_user_id: 1
}));
```
**Note**: Default password is `staff123`

### Update Staff
```javascript
params.append('operation', 'update');
params.append('json', JSON.stringify({
  user_id: 5,
  first_name: 'Jane',
  last_name: 'Smith',
  email: 'jane.smith@clinic.com',
  phone: '987-654-3210',
  assigned_doctor_id: 2, // optional, for secretaries
  status: 'active',
  admin_user_id: 1
}));
```

### Delete Staff
```javascript
params.append('operation', 'delete');
params.append('json', JSON.stringify({
  user_id: 5,
  admin_user_id: 1
}));
```

### Get Statistics
```javascript
params.append('operation', 'getStatistics');
params.append('json', JSON.stringify({}));
```

**Response**:
```json
{
  "success": true,
  "data": {
    "secretaries": 5,
    "receptionists": 3,
    "total": 8
  }
}
```

---

## 5. Patients API (`patients.php`)

### Register Patient
```javascript
params.append('operation', 'register');
params.append('json', JSON.stringify({
  // Required fields
  first_name: 'John',
  last_name: 'Patient',
  date_of_birth: '1990-05-15',
  gender: 'male', // 'male', 'female', 'other'
  
  // Optional personal information
  middle_name: 'Michael',
  blood_type: 'O+',
  phone_number: '123-456-7890',
  email: 'patient@email.com',
  address: '123 Main St, City, State',
  
  // Emergency contact (split into detailed fields)
  emergency_contact_name: 'Jane Doe',
  emergency_contact_phone: '987-654-3210',
  
  // Medical information (NEW)
  allergies: 'Penicillin, Peanuts',
  existing_conditions: 'Diabetes, Hypertension',
  
  // Insurance information
  insurance_provider: 'Blue Cross',
  insurance_policy_number: 'BC123456789',
  
  // Additional notes
  notes: 'Patient prefers morning appointments',
  
  // User account creation (optional)
  create_account: 1, // 0 = no account, 1 = create portal account
  admin_user_id: 1
}));
```

**Response**:
```json
{
  "success": true,
  "patient_code": "P0042",
  "patient_id": 42,
  "username": "john.patient", // only if create_account = 1
  "message": "Patient registered successfully"
}
```

**Notes**:
- Only `first_name`, `last_name`, `date_of_birth`, and `gender` are required
- If `create_account = 1`, system creates user account with default password: `patient123`
- Patient code is auto-generated (P0001-P9999)
- `email` is required if `create_account = 1`

### Get All Patients
```javascript
params.append('operation', 'getAll');
params.append('json', JSON.stringify({
  blood_type: 'O+', // optional filter
  status: 'active', // optional: 'active', 'inactive', or omit for all
  search: 'john' // optional: searches name, email, phone, patient_code
}));
```

**Response**:
```json
{
  "success": true,
  "data": [
    {
      "patient_id": 1,
      "patient_code": "P0001",
      "user_id": 5, // null if no portal account
      "first_name": "John",
      "last_name": "Patient",
      "middle_name": "Michael",
      "date_of_birth": "1990-05-15",
      "gender": "male",
      "blood_type": "O+",
      "phone_number": "123-456-7890",
      "email": "john.patient@email.com",
      "address": "123 Main St",
      "emergency_contact_name": "Jane Doe",
      "emergency_contact_phone": "987-654-3210",
      "allergies": "Penicillin",
      "existing_conditions": "Diabetes",
      "date_registered": "2024-01-15 10:30:00",
      "last_visit": "2024-03-20",
      "notes": "Prefers morning appointments",
      "is_active": 1,
      "insurance_id": 3,
      "company_name": "Blue Cross",
      "last_visit_date": "2024-03-20"
    }
  ]
}
```

### Get Patient by ID
```javascript
params.append('operation', 'getById');
params.append('json', JSON.stringify({ patient_id: 1 }));
```

**Response**: Returns single patient object with all fields (same structure as getAll)

### Update Patient
```javascript
params.append('operation', 'update');
params.append('json', JSON.stringify({
  patient_id: 1,
  
  // Name fields
  first_name: 'John',
  last_name: 'Patient',
  middle_name: 'Michael',
  
  // Personal information
  date_of_birth: '1990-05-15',
  gender: 'male',
  blood_type: 'O+',
  
  // Contact information
  phone_number: '123-456-7890',
  email: 'john.patient@email.com',
  address: '456 Oak Ave, City, State',
  
  // Emergency contact
  emergency_contact_name: 'Jane Doe',
  emergency_contact_phone: '987-654-3210',
  
  // Medical information
  allergies: 'Penicillin, Peanuts',
  existing_conditions: 'Diabetes Type 2, Hypertension',
  
  // Status and notes
  is_active: 1, // 0 = inactive, 1 = active
  notes: 'Updated notes about patient',
  
  admin_user_id: 1
}));
```

**Response**:
```json
{
  "success": true,
  "message": "Patient information updated successfully"
}
```

### Delete Patient
```javascript
params.append('operation', 'delete');
params.append('json', JSON.stringify({
  patient_id: 1,
  admin_user_id: 1
}));
```

**Response**:
```json
{
  "success": true,
  "message": "Patient and associated user account deleted successfully"
}
```

**Notes**:
- Deletes patient record, associated user account (if exists), and all related data
- Action is logged in system_logs

### Get Statistics
```javascript
params.append('operation', 'getStatistics');
params.append('json', JSON.stringify({}));
```

**Response**:
```json
{
  "success": true,
  "data": {
    "total": 150,
    "active": 145, // patients with is_active = 1
    "new_patients": 12, // registered this month
    "today_appointments": 8
  }
}
```

---

## 6. Audit Logs API (`audit-logs.php`)

### Get All Logs
```javascript
params.append('operation', 'getAll');
params.append('json', JSON.stringify({
  user_id: 1, // optional: filter by user
  action: 'create', // optional: login, logout, create, update, delete, view, export, settings
  from_date: '2024-01-01', // optional
  to_date: '2024-12-31', // optional
  search: 'patient', // optional
  limit: 50, // optional, default 100
  offset: 0 // optional, for pagination
}));
```

**Response**:
```json
{
  "success": true,
  "data": [
    {
      "log_id": 1,
      "user_id": 1,
      "username": "admin",
      "first_name": "John",
      "last_name": "Admin",
      "action": "create",
      "description": "Created new user: jane.doe",
      "ip_address": "192.168.1.100",
      "created_at": "2024-01-15 14:30:00"
    }
  ],
  "total": 150,
  "limit": 50,
  "offset": 0
}
```

### Get Statistics
```javascript
params.append('operation', 'getStatistics');
params.append('json', JSON.stringify({}));
```

### Get Users (for filter dropdown)
```javascript
params.append('operation', 'getUsers');
params.append('json', JSON.stringify({}));
```

### Clear Old Logs
```javascript
params.append('operation', 'clearOldLogs');
params.append('json', JSON.stringify({
  days: 90, // delete logs older than 90 days
  admin_user_id: 1
}));
```

### Export Logs
```javascript
params.append('operation', 'export');
params.append('json', JSON.stringify({
  // same filters as getAll
  admin_user_id: 1
}));
```

---

## 7. Admin Profile API (`admin-profile.php`)

### Get Profile
```javascript
params.append('operation', 'getProfile');
params.append('json', JSON.stringify({ user_id: 1 }));
```

### Update Profile
```javascript
params.append('operation', 'updateProfile');
params.append('json', JSON.stringify({
  user_id: 1,
  first_name: 'John',
  last_name: 'Admin',
  email: 'admin@clinic.com',
  phone: '123-456-7890',
  date_of_birth: '1985-01-01',
  address: '123 Admin St',
  gender: 'male'
}));
```

### Change Password
```javascript
params.append('operation', 'changePassword');
params.append('json', JSON.stringify({
  user_id: 1,
  current_password: 'oldpass123',
  new_password: 'newpass456'
}));
```

---

## 8. System Settings API (`system_settings.php`)

### Get All Settings
```javascript
params.append('operation', 'all');
params.append('json', JSON.stringify({}));
```

**Response**:
```json
{
  "success": true,
  "data": {
    "clinic_name": "My Clinic",
    "clinic_address": "123 Main St",
    "clinic_contact": "+63 900 000 0000",
    "timezone": "Asia/Manila"
  }
}
```

### Get Single Setting
```javascript
params.append('operation', 'get');
params.append('json', JSON.stringify({
  setting_key: 'clinic_name'
}));
```

### Update Settings
```javascript
params.append('operation', 'update');
params.append('json', JSON.stringify({
  clinic_name: 'New Clinic Name',
  clinic_address: '456 New St',
  timezone: 'America/New_York',
  admin_user_id: 1
}));
```

---

## 9. Dashboard API (`dashboard.php`)

### Get Statistics
```javascript
params.append('operation', 'getStatistics');
params.append('json', JSON.stringify({
  usertype_id: 1, // User type for role-specific stats
  user_id: 1 // Current user ID
}));
```

**Response for Admin**:
```json
{
  "success": true,
  "data": {
    "system": {
      "active_users": 50,
      "today_appointments": 12
    },
    "users": {
      "total": 55,
      "active": 50
    },
    "doctors": {
      "total": 10,
      "active": 8
    },
    "patients": {
      "total": 150,
      "new_this_month": 10
    },
    "staff": {
      "secretaries": 5,
      "receptionists": 3,
      "total": 8
    },
    "appointments": {
      "total": 500,
      "pending": 15,
      "confirmed": 20,
      "today": 12
    },
    "recent_activities": [
      {
        "log_id": 1,
        "action": "create",
        "description": "Created new user",
        "created_at": "2024-01-15 14:30:00",
        "first_name": "John",
        "last_name": "Admin"
      }
    ]
  }
}
```

---

## Integration Example

Here's a complete example for integrating the Users API:

```javascript
// Load all users
async function loadUsers() {
  try {
    const params = new URLSearchParams();
    params.append('operation', 'getAll');
    params.append('json', JSON.stringify({
      search: document.getElementById('searchInput').value,
      usertype_id: document.getElementById('filterUserType').value,
      status: document.getElementById('filterStatus').value
    }));

    const response = await axios.post('../../backend/users.php', params);

    if (response.data.success) {
      renderUsersTable(response.data.data);
    } else {
      console.error('Error:', response.data.error);
      alert('Failed to load users: ' + response.data.error);
    }
  } catch (error) {
    console.error('Error:', error);
    alert('Network error: ' + error.message);
  }
}

// Create new user
async function addUser(formData) {
  try {
    const params = new URLSearchParams();
    params.append('operation', 'create');
    
    const user = SessionHandler.getUser();
    const jsonData = {
      ...formData,
      admin_user_id: user.user_id
    };
    
    params.append('json', JSON.stringify(jsonData));

    const response = await axios.post('../../backend/users.php', params);

    if (response.data.success) {
      alert('User created successfully!');
      loadUsers(); // Reload the list
      $('#addUserModal').modal('hide');
    } else {
      alert('Failed to create user: ' + response.data.error);
    }
  } catch (error) {
    console.error('Error:', error);
    alert('Network error: ' + error.message);
  }
}
```

---

## Error Handling

All APIs return errors in this format:
```json
{
  "error": "Error message here"
}
```

Always check for the `success` field or `error` field:
```javascript
if (response.data.success) {
  // Handle success
} else if (response.data.error) {
  // Handle error
  console.error(response.data.error);
}
```

---

## Security Notes

1. **Authentication**: Always pass `admin_user_id` for logging purposes
2. **Input Validation**: All APIs validate required fields
3. **SQL Injection**: All queries use prepared statements
4. **Password Hashing**: Passwords are hashed using bcrypt
5. **Logging**: All create/update/delete operations are logged to `system_logs`

---

## Database Tables Reference

- `users` - User accounts
- `user_profiles` - User personal information
- `usertypes` - User role definitions (1=Admin, 2=Doctor, 3=Secretary, 4=Receptionist, 5=Patient)
- `doctors` - Doctor-specific data
- `secretaries` - Secretary-specific data
- `receptionists` - Receptionist-specific data
- `patients` - Patient-specific data
- `system_logs` - Audit trail of all actions
- `system_settings` - System configuration
- `appointments` - Appointment records
- `notifications` - User notifications
