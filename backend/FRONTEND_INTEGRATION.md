# 🔗 Frontend Integration Quick Reference

This guide shows you exactly what to replace in each admin page to connect to the backend APIs.

---

## Common Pattern

All pages follow this pattern:

```javascript
// At the top of your page's JavaScript
const user = SessionHandler.getUser();

// For all API calls
async function callAPI(endpoint, operation, data = {}) {
  try {
    const params = new URLSearchParams();
    params.append('operation', operation);
    params.append('json', JSON.stringify({
      ...data,
      admin_user_id: user.user_id // For logging
    }));

    const response = await axios.post(`../../backend/${endpoint}`, params);
    
    if (response.data.success) {
      return response.data;
    } else {
      throw new Error(response.data.error || 'Unknown error');
    }
  } catch (error) {
    console.error('API Error:', error);
    throw error;
  }
}
```

---

## 1. Dashboard (dashboard.html)

### Load Statistics
Replace the `loadDashboardStats()` function:

```javascript
async function loadDashboardStats() {
  try {
    const user = SessionHandler.getUser();
    const response = await callAPI('dashboard.php', 'getStatistics', {
      usertype_id: user.usertype_id,
      user_id: user.user_id
    });

    const stats = response.data;

    // Update UI
    document.getElementById('totalUsers').textContent = stats.users.total;
    document.getElementById('totalDoctors').textContent = stats.doctors.total;
    document.getElementById('totalPatients').textContent = stats.patients.total;
    document.getElementById('totalStaff').textContent = stats.staff.total;
    document.getElementById('totalAppointments').textContent = stats.appointments.today;

    // Render recent activities
    renderRecentActivities(stats.recent_activities);
  } catch (error) {
    alert('Failed to load dashboard: ' + error.message);
  }
}

// Call on page load
loadDashboardStats();
```

---

## 2. Users Page (users.html)

### Load Users
```javascript
async function loadUsers() {
  try {
    const response = await callAPI('users.php', 'getAll', {
      search: document.getElementById('searchInput').value,
      usertype_id: document.getElementById('filterUserType').value,
      status: document.getElementById('filterStatus').value
    });

    renderUsersTable(response.data);
  } catch (error) {
    alert('Failed to load users: ' + error.message);
  }
}
```

### Add User
```javascript
document.getElementById('addUserForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  const jsonData = {};
  formData.forEach((value, key) => { jsonData[key] = value; });

  try {
    await callAPI('users.php', 'create', jsonData);
    alert('User created successfully!');
    $('#addUserModal').modal('hide');
    this.reset();
    loadUsers();
  } catch (error) {
    alert('Failed to create user: ' + error.message);
  }
});
```

### Edit User
```javascript
async function editUser(userId) {
  try {
    // First, get user data
    const response = await callAPI('users.php', 'getById', { user_id: userId });
    const user = response.data;

    // Populate edit form
    document.getElementById('editUserId').value = user.user_id;
    document.getElementById('editUsername').value = user.username;
    document.getElementById('editEmail').value = user.email;
    document.getElementById('editFirstName').value = user.first_name || '';
    document.getElementById('editLastName').value = user.last_name || '';
    document.getElementById('editUserType').value = user.usertype_id;
    document.getElementById('editStatus').value = user.status;

    $('#editUserModal').modal('show');
  } catch (error) {
    alert('Failed to load user: ' + error.message);
  }
}

document.getElementById('editUserForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  const jsonData = {};
  formData.forEach((value, key) => { jsonData[key] = value; });

  try {
    await callAPI('users.php', 'update', jsonData);
    alert('User updated successfully!');
    $('#editUserModal').modal('hide');
    loadUsers();
  } catch (error) {
    alert('Failed to update user: ' + error.message);
  }
});
```

### Delete User
```javascript
async function deleteUser(userId, username) {
  if (!confirm(`Are you sure you want to delete user "${username}"?`)) return;

  try {
    await callAPI('users.php', 'delete', { user_id: userId });
    alert('User deleted successfully!');
    loadUsers();
  } catch (error) {
    alert('Failed to delete user: ' + error.message);
  }
}
```

---

## 3. Doctors Page (doctors.html)

### Load Doctors & Statistics
```javascript
async function loadDoctors() {
  try {
    const response = await callAPI('doctors.php', 'getAll', {
      search: document.getElementById('searchInput').value,
      specialization: document.getElementById('filterSpecialization').value,
      status: document.getElementById('filterStatus').value
    });

    renderDoctorsTable(response.data);
  } catch (error) {
    alert('Failed to load doctors: ' + error.message);
  }
}

async function loadStatistics() {
  try {
    const response = await callAPI('doctors.php', 'getStatistics', {});
    const stats = response.data;

    document.getElementById('totalDoctors').textContent = stats.total;
    document.getElementById('activeDoctors').textContent = stats.active;
    document.getElementById('totalSpecializations').textContent = stats.specializations;
    document.getElementById('onDutyToday').textContent = stats.on_duty;
  } catch (error) {
    console.error('Failed to load statistics:', error);
  }
}

// Load both on page load
loadDoctors();
loadStatistics();
```

### Add Doctor
```javascript
document.getElementById('addDoctorForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  const jsonData = {};
  formData.forEach((value, key) => { jsonData[key] = value; });

  try {
    const response = await callAPI('doctors.php', 'create', jsonData);
    alert(`Doctor created successfully! Username: ${response.username}, Password: doctor123`);
    $('#addDoctorModal').modal('hide');
    this.reset();
    loadDoctors();
    loadStatistics();
  } catch (error) {
    alert('Failed to create doctor: ' + error.message);
  }
});
```

### Update & Delete
Similar pattern to Users page, just replace `'users.php'` with `'doctors.php'` and use `doctor_id` instead of `user_id`.

---

## 4. Staff Page (staff.html)

### Load Staff & Statistics
```javascript
async function loadStaff() {
  try {
    const response = await callAPI('staff.php', 'getAll', {
      search: document.getElementById('searchInput').value,
      usertype_id: document.getElementById('filterRole').value,
      status: document.getElementById('filterStatus').value
    });

    renderStaffTable(response.data);
  } catch (error) {
    alert('Failed to load staff: ' + error.message);
  }
}

async function loadStatistics() {
  try {
    const response = await callAPI('staff.php', 'getStatistics', {});
    const stats = response.data;

    document.getElementById('totalSecretaries').textContent = stats.secretaries;
    document.getElementById('totalReceptionists').textContent = stats.receptionists;
    document.getElementById('totalStaff').textContent = stats.total;
  } catch (error) {
    console.error('Failed to load statistics:', error);
  }
}

loadStaff();
loadStatistics();
```

### Add Staff
```javascript
document.getElementById('addStaffForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  const jsonData = {};
  formData.forEach((value, key) => { jsonData[key] = value; });

  try {
    const response = await callAPI('staff.php', 'create', jsonData);
    alert(`Staff member created! Username: ${response.username}, Password: staff123`);
    $('#addStaffModal').modal('hide');
    this.reset();
    loadStaff();
    loadStatistics();
  } catch (error) {
    alert('Failed to create staff member: ' + error.message);
  }
});
```

---

## 5. Patients Page (patients.html)

### Load Patients & Statistics
```javascript
async function loadPatients() {
  try {
    const response = await callAPI('patients.php', 'getAll', {
      search: document.getElementById('searchInput').value,
      blood_type: document.getElementById('filterBloodType').value
    });

    renderPatientsTable(response.data);
  } catch (error) {
    alert('Failed to load patients: ' + error.message);
  }
}

async function loadStatistics() {
  try {
    const response = await callAPI('patients.php', 'getStatistics', {});
    const stats = response.data;

    document.getElementById('totalPatients').textContent = stats.total;
    document.getElementById('activePatients').textContent = stats.active;
    document.getElementById('newPatients').textContent = stats.new_patients;
    document.getElementById('todayAppointments').textContent = stats.today_appointments;
  } catch (error) {
    console.error('Failed to load statistics:', error);
  }
}

loadPatients();
loadStatistics();
```

### Register Patient
```javascript
document.getElementById('registerPatientForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  const jsonData = {};
  formData.forEach((value, key) => { jsonData[key] = value; });

  try {
    const response = await callAPI('patients.php', 'register', jsonData);
    alert(`Patient registered! Patient Code: ${response.patient_code}`);
    $('#registerPatientModal').modal('hide');
    this.reset();
    loadPatients();
    loadStatistics();
  } catch (error) {
    alert('Failed to register patient: ' + error.message);
  }
});
```

---

## 6. Profile Page (profile.html)

### Load Profile
```javascript
async function loadProfile() {
  try {
    const user = SessionHandler.getUser();
    const response = await callAPI('admin-profile.php', 'getProfile', {
      user_id: user.user_id
    });

    const profile = response.data;

    // Populate form
    document.getElementById('username').value = profile.username;
    document.getElementById('email').value = profile.email || '';
    document.getElementById('firstName').value = profile.first_name || '';
    document.getElementById('lastName').value = profile.last_name || '';
    document.getElementById('phone').value = profile.phone || '';
    document.getElementById('dateOfBirth').value = profile.birth_date || '';
    document.getElementById('address').value = profile.address || '';

    // Update display
    document.getElementById('profileEmail').textContent = profile.email;
  } catch (error) {
    alert('Failed to load profile: ' + error.message);
  }
}

loadProfile();
```

### Update Profile
```javascript
document.getElementById('profileForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  const user = SessionHandler.getUser();
  const jsonData = { user_id: user.user_id };
  formData.forEach((value, key) => { jsonData[key] = value; });

  try {
    await callAPI('admin-profile.php', 'updateProfile', jsonData);
    alert('Profile updated successfully!');
    
    // Update session with new data
    const updatedUser = await callAPI('admin-profile.php', 'getProfile', {
      user_id: user.user_id
    });
    SessionHandler.updateUser(updatedUser.data);
    
    loadProfile();
  } catch (error) {
    alert('Failed to update profile: ' + error.message);
  }
});
```

### Change Password
```javascript
document.getElementById('changePasswordForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  
  if (formData.get('new_password') !== formData.get('confirm_password')) {
    alert('New passwords do not match!');
    return;
  }

  const user = SessionHandler.getUser();
  const jsonData = {
    user_id: user.user_id,
    current_password: formData.get('current_password'),
    new_password: formData.get('new_password')
  };

  try {
    await callAPI('admin-profile.php', 'changePassword', jsonData);
    alert('Password updated successfully!');
    this.reset();
  } catch (error) {
    alert('Failed to change password: ' + error.message);
  }
});
```

---

## 7. Settings Page (settings.html)

### Load Settings
```javascript
async function loadSettings() {
  try {
    const response = await callAPI('system_settings.php', 'all', {});
    const settings = response.data;

    // Populate all forms with settings
    Object.keys(settings).forEach(key => {
      const input = document.querySelector(`[name="${key}"]`);
      if (input) {
        if (input.type === 'checkbox') {
          input.checked = settings[key] === 'true' || settings[key] === '1';
        } else {
          input.value = settings[key];
        }
      }
    });
  } catch (error) {
    console.error('Failed to load settings:', error);
  }
}

loadSettings();
```

### Save Settings (All Forms)
```javascript
// General Settings
document.getElementById('generalForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  await saveSettings(this, 'General settings');
});

// Clinic Info
document.getElementById('clinicForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  await saveSettings(this, 'Clinic information');
});

// ... Similar for all other forms

async function saveSettings(form, label) {
  const formData = new FormData(form);
  const jsonData = {};
  formData.forEach((value, key) => {
    // Handle checkboxes
    const input = form.querySelector(`[name="${key}"]`);
    if (input && input.type === 'checkbox') {
      jsonData[key] = input.checked ? '1' : '0';
    } else {
      jsonData[key] = value;
    }
  });

  try {
    await callAPI('system_settings.php', 'update', jsonData);
    alert(`${label} saved successfully!`);
  } catch (error) {
    alert(`Failed to save ${label.toLowerCase()}: ` + error.message);
  }
}
```

---

## 8. Audit Logs Page (audit-logs.html)

### Load Logs & Statistics
```javascript
async function loadAuditLogs() {
  try {
    const response = await callAPI('audit-logs.php', 'getAll', {
      user_id: document.getElementById('filterUser').value,
      action: document.getElementById('filterAction').value,
      from_date: document.getElementById('filterFromDate').value,
      to_date: document.getElementById('filterToDate').value,
      search: document.getElementById('searchInput').value,
      limit: 100,
      offset: 0
    });

    renderLogsTable(response.data);
    document.getElementById('logsCount').textContent = response.total + ' logs';
  } catch (error) {
    alert('Failed to load logs: ' + error.message);
  }
}

async function loadStatistics() {
  try {
    const response = await callAPI('audit-logs.php', 'getStatistics', {});
    const stats = response.data;

    document.getElementById('totalLogs').textContent = stats.total;
    document.getElementById('todayLogs').textContent = stats.today;
    document.getElementById('activeUsers').textContent = stats.active_users;
    document.getElementById('failedActions').textContent = stats.failed;
  } catch (error) {
    console.error('Failed to load statistics:', error);
  }
}

async function loadUserFilter() {
  try {
    const response = await callAPI('audit-logs.php', 'getUsers', {});
    const users = response.data;
    
    const select = document.getElementById('filterUser');
    users.forEach(user => {
      const option = document.createElement('option');
      option.value = user.user_id;
      option.textContent = `${user.first_name} ${user.last_name}`;
      select.appendChild(option);
    });
  } catch (error) {
    console.error('Failed to load users:', error);
  }
}

loadAuditLogs();
loadStatistics();
loadUserFilter();
```

### Export Logs
```javascript
document.getElementById('exportBtn').addEventListener('click', async function() {
  try {
    const response = await callAPI('audit-logs.php', 'export', {
      // Same filters as getAll
      user_id: document.getElementById('filterUser').value,
      action: document.getElementById('filterAction').value,
      from_date: document.getElementById('filterFromDate').value,
      to_date: document.getElementById('filterToDate').value
    });

    // Convert to CSV and download
    const csv = convertToCSV(response.data);
    downloadCSV(csv, 'audit-logs-' + new Date().toISOString() + '.csv');
    
    alert('Logs exported successfully!');
  } catch (error) {
    alert('Failed to export logs: ' + error.message);
  }
});

function convertToCSV(data) {
  const headers = ['Timestamp', 'User', 'Action', 'Description', 'IP Address'];
  const rows = data.map(log => [
    log.created_at,
    `${log.first_name} ${log.last_name}`,
    log.action,
    log.description,
    log.ip_address
  ]);
  
  return [headers, ...rows].map(row => row.join(',')).join('\n');
}

function downloadCSV(csv, filename) {
  const blob = new Blob([csv], { type: 'text/csv' });
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();
}
```

### Clear Old Logs
```javascript
document.getElementById('clearOldLogsBtn').addEventListener('click', async function() {
  if (!confirm('Clear logs older than 90 days?')) return;

  try {
    const response = await callAPI('audit-logs.php', 'clearOldLogs', { days: 90 });
    alert(`${response.deleted} old logs cleared successfully!`);
    loadAuditLogs();
    loadStatistics();
  } catch (error) {
    alert('Failed to clear logs: ' + error.message);
  }
});
```

---

## Helper: Update Session Handler

Add this method to `session-handler.js` if not already there:

```javascript
updateUser: function(userData) {
  var session = this.getSession();
  if (session) {
    session.user = userData;
    localStorage.setItem('clinic_user_session', JSON.stringify(session));
  }
}
```

---

## Testing Checklist

- [ ] Dashboard loads statistics correctly
- [ ] Users page: Create, Read, Update, Delete
- [ ] Doctors page: CRUD + Statistics
- [ ] Staff page: CRUD + Statistics  
- [ ] Patients page: CRUD + Statistics
- [ ] Profile page: View, Update, Change Password
- [ ] Settings page: Load, Save all tabs
- [ ] Audit Logs: View, Filter, Export, Clear

---

## Common Issues & Solutions

### Issue: "Network Error" or CORS
**Solution**: Make sure your backend files are in `backend/` folder and accessible

### Issue: "Invalid Operation"
**Solution**: Check that the operation name matches exactly (case-sensitive)

### Issue: "Database Error"
**Solution**: Run the `schema.sql` to create all required tables

### Issue: Session not persisting
**Solution**: Make sure `SessionHandler.requireUserType(1)` is called at the top of each page

### Issue: Can't delete admin user
**Solution**: This is by design - user_id 1 cannot be deleted for security
