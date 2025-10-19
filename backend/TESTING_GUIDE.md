# 🧪 Testing Guide - Admin Pages Integration

## Quick Start Testing

### 1. Start Your Server
```bash
cd c:\laragon\www\cms
php -S localhost:8000
```

Or if using Laragon, just start Apache.

---

## 2. Login
Navigate to: `http://localhost/cms/frontend/login.html`

**Default Admin Credentials:**
```
Username: admin
Password: admin123
```

---

## 3. Test Each Page

### ✅ Dashboard Test
**URL:** `http://localhost/cms/frontend/admin/dashboard.html`

**What to Check:**
- [ ] Statistics cards show numbers (not "0" or "N/A")
- [ ] Recent activities list appears
- [ ] User info displays in navbar (name, role, avatar)
- [ ] All menu items are clickable

**Expected Behavior:**
- Dashboard loads automatically on page load
- Statistics: Total Users, Doctors, Patients, Staff, etc.
- Recent activities with timestamps (e.g., "5 minutes ago")

---

### ✅ Users Management Test
**URL:** `http://localhost/cms/frontend/admin/users.html`

#### Test 1: View Users
- [ ] Users table loads with data
- [ ] Search box filters in real-time
- [ ] Filter by User Type works
- [ ] Filter by Status works

#### Test 2: Create User
1. Click "Add User" button
2. Fill in form:
   ```
   Username: test.user
   Email: test@clinic.com
   Password: test123
   User Type: Doctor / Secretary / Receptionist
   First Name: Test
   Last Name: User
   ```
3. Click "Save"
4. Check for success alert
5. Verify user appears in table

#### Test 3: Edit User
1. Click Edit (⋮ menu) on any user
2. Change first name
3. Save
4. Verify changes appear in table

#### Test 4: Delete User
1. Click Delete on any user (except admin)
2. Confirm deletion
3. Verify user removed from table

**⚠️ Important:** Cannot delete admin user (user_id=1)

---

### ✅ Doctors Management Test
**URL:** `http://localhost/cms/frontend/admin/doctors.html`

#### Test 1: View Statistics
- [ ] Total Doctors count
- [ ] Active Doctors count
- [ ] Total Specializations count
- [ ] On Duty Today count

#### Test 2: Create Doctor
1. Click "Add Doctor"
2. Fill in form:
   ```
   First Name: Jane
   Last Name: Doe
   Email: jane.doe@clinic.com
   Phone: 555-1234
   Specialization: Cardiology
   License Number: MD-12345
   ```
3. Click "Save"
4. Check alert: "Doctor added successfully! Default password: doctor123"
5. Verify doctor appears in table
6. Verify statistics updated

#### Test 3: Filter Doctors
- [ ] Search by name works
- [ ] Filter by Specialization works
- [ ] Filter by Status works
- [ ] Reset Filters button clears all

---

### ✅ Staff Management Test
**URL:** `http://localhost/cms/frontend/admin/staff.html`

#### Test 1: View Statistics
- [ ] Secretaries count
- [ ] Receptionists count
- [ ] Total Staff count

#### Test 2: Add Secretary
1. Click "Add Staff"
2. Select User Type: Secretary
3. Fill in form:
   ```
   First Name: Emily
   Last Name: Brown
   Email: emily@clinic.com
   Phone: 555-2001
   Assigned Doctor: (Select one)
   ```
4. Save and verify

#### Test 3: Add Receptionist
1. Click "Add Staff"
2. Select User Type: Receptionist
3. Fill in form
4. Save and verify statistics update

---

### ✅ Patients Management Test
**URL:** `http://localhost/cms/frontend/admin/patients.html`

#### Test 1: View Statistics
- [ ] Total Patients
- [ ] Active Patients
- [ ] New Patients (this month)
- [ ] Today's Appointments

#### Test 2: Register Patient WITHOUT Account
1. Click "Register Patient"
2. Fill in form:
   ```
   First Name: John
   Last Name: Smith
   Date of Birth: 1985-05-15
   Gender: Male
   Blood Type: O+
   Email: john@email.com
   Phone: 555-3001
   ```
3. **Leave "Create User Account" UNCHECKED**
4. Save
5. Check alert for Patient Code (e.g., P0001)

#### Test 3: Register Patient WITH Account
1. Click "Register Patient"
2. Fill in form
3. **CHECK "Create User Account"**
4. Save
5. Check alert: "Patient Code: P0002 | Default password: patient123"
6. Patient can now login

#### Test 4: Filter Patients
- [ ] Search by name works
- [ ] Filter by Blood Type works
- [ ] Last Visit column shows dates

---

### ✅ Profile Management Test
**URL:** `http://localhost/cms/frontend/admin/profile.html`

#### Test 1: View Profile
- [ ] Username displays
- [ ] Email displays
- [ ] Name displays
- [ ] Phone displays
- [ ] All fields populated from API

#### Test 2: Update Profile
1. Change First Name to "Updated"
2. Change Phone to "555-9999"
3. Click "Update Profile"
4. Check success alert
5. **Page reloads automatically**
6. Verify navbar shows "Updated Admin"

#### Test 3: Change Password
1. Go to "Change Password" tab
2. Fill in:
   ```
   Current Password: admin123
   New Password: newpass123
   Confirm Password: newpass123
   ```
3. Click "Change Password"
4. Check success alert
5. Logout and login with new password

**⚠️ Important:** You need current password to change it!

---

### ✅ System Settings Test
**URL:** `http://localhost/cms/frontend/admin/settings.html`

#### Test 1: Load Settings
- [ ] All tabs load existing settings
- [ ] Text fields populate
- [ ] Checkboxes checked/unchecked correctly

#### Test 2: Save General Settings
1. Go to "General" tab
2. Change "System Name"
3. Click "Save General Settings"
4. Refresh page
5. Verify setting persists

#### Test 3: Save Clinic Information
1. Go to "Clinic Info" tab
2. Change clinic name, address, etc.
3. Click "Save Clinic Information"
4. Verify success alert

#### Test 4: Save Each Tab
- [ ] General Settings ✅
- [ ] Clinic Information ✅
- [ ] Appointments ✅
- [ ] Notifications ✅
- [ ] Email Configuration ✅
- [ ] Security ✅
- [ ] Backup ✅

---

### ✅ Audit Logs Test
**URL:** `http://localhost/cms/frontend/admin/audit-logs.html`

#### Test 1: View Statistics
- [ ] Total Logs count
- [ ] Today's Logs count
- [ ] Active Users count
- [ ] Failed Actions count

#### Test 2: View Logs
- [ ] Logs table populated
- [ ] Shows: Timestamp, User, Action, Description, IP
- [ ] Logs sorted by newest first

#### Test 3: Filter Logs
1. User Filter:
   - [ ] Dropdown populates with users
   - [ ] Filtering works
2. Action Filter:
   - [ ] Filter by action type
3. Date Range:
   - [ ] From Date works
   - [ ] To Date works
4. Search:
   - [ ] Search box filters logs

#### Test 4: Export Logs
1. Click "Export Logs" button
2. Verify CSV file downloads
3. Open CSV and check:
   - [ ] Headers: Timestamp, User, Action, Description, IP
   - [ ] Data matches filtered logs

#### Test 5: Clear Old Logs
1. Click "Clear Old Logs"
2. Confirm dialog
3. Verify success message
4. Statistics update

---

## 4. Integration Testing

### Test Workflow: Create Doctor → Check Audit Logs

1. **Go to Doctors page**
2. Add new doctor "Dr. Test Person"
3. **Go to Audit Logs page**
4. Verify new entry:
   - Action: "create_doctor"
   - User: Your username
   - Description: Contains doctor details
   - IP Address: Your IP

### Test Workflow: Update Profile → Check Session

1. **Go to Profile page**
2. Change your name to "John Updated"
3. Save
4. **Check navbar** - Should show "John Updated"
5. **Go to Dashboard** - Name persists
6. **Logout and Login** - Name still shows

### Test Workflow: Register Patient → Check Patients

1. **Go to Patients page**
2. Register patient "Test Patient"
3. Note the Patient Code (e.g., P0005)
4. **Go to Audit Logs**
5. Verify "create_patient" action logged
6. **Go back to Patients**
7. Search for "Test Patient"
8. Verify appears in results

---

## 5. Error Testing

### Test 1: Duplicate Username
1. Go to Users page
2. Try to create user with username "admin"
3. Should show error: "Username already exists"

### Test 2: Duplicate Email
1. Try to create doctor with existing email
2. Should show error: "Email already exists"

### Test 3: Delete Admin User
1. Go to Users page
2. Try to delete admin user (user_id=1)
3. Should show error: "Cannot delete system administrator"

### Test 4: Wrong Current Password
1. Go to Profile page
2. Try to change password with wrong current password
3. Should show error: "Current password is incorrect"

### Test 5: Password Mismatch
1. Change password tab
2. Enter different passwords in New/Confirm fields
3. Should show error: "New passwords do not match!"

---

## 6. Performance Testing

### Check Page Load Times
- [ ] Dashboard loads in < 2 seconds
- [ ] Users page loads in < 2 seconds
- [ ] Audit logs (100 records) loads in < 3 seconds

### Check Search Performance
- [ ] Search updates table in < 500ms
- [ ] Filter changes apply instantly
- [ ] Statistics recalculate quickly

---

## 7. Browser Console Check

Open Developer Tools (F12) and check:

### No JavaScript Errors
```
✅ No red errors in Console
✅ All API calls return 200 status
✅ Response data is valid JSON
```

### Network Tab
```
✅ All requests to backend/*.php succeed
✅ Response times < 1 second
✅ POST requests have correct Content-Type
```

---

## 8. Common Issues & Solutions

### Issue: Page shows "Error loading..."
**Check:**
1. Backend files exist in `backend/` folder?
2. Database connection working? (Check conn.php)
3. Tables created? (Run schema.sql)

### Issue: Statistics show 0
**Check:**
1. Database has data?
2. Backend API returns data? (Check browser Network tab)
3. Operation name correct in frontend?

### Issue: "User not logged in"
**Solution:**
1. Clear browser localStorage
2. Login again
3. Check SessionHandler is working

### Issue: Changes don't persist
**Check:**
1. API success response received?
2. Database updated? (Check with phpMyAdmin)
3. Page reloading correctly?

---

## 9. Final Checklist

Before marking complete, verify:

### All Pages Load ✅
- [ ] Dashboard
- [ ] Users
- [ ] Doctors
- [ ] Staff
- [ ] Patients
- [ ] Profile
- [ ] Settings
- [ ] Audit Logs

### All CRUD Operations Work ✅
- [ ] Create (Add new records)
- [ ] Read (View existing records)
- [ ] Update (Edit records)
- [ ] Delete (Remove records)

### All Features Work ✅
- [ ] Search/Filter
- [ ] Statistics
- [ ] Export (CSV)
- [ ] Session Management
- [ ] Audit Logging
- [ ] Error Handling

### All Security Works ✅
- [ ] Admin cannot be deleted
- [ ] Password change requires current password
- [ ] All actions logged
- [ ] Session persists

---

## 10. Test Report Template

```
## Test Report - [Date]

**Tester:** [Your Name]
**Environment:** Windows / Laragon / PHP [Version]

### Test Results:

| Page | Load | Create | Read | Update | Delete | Status |
|------|------|--------|------|--------|--------|--------|
| Dashboard | ✅ | - | ✅ | - | - | PASS |
| Users | ✅ | ✅ | ✅ | ✅ | ✅ | PASS |
| Doctors | ✅ | ✅ | ✅ | - | - | PASS |
| Staff | ✅ | ✅ | ✅ | - | - | PASS |
| Patients | ✅ | ✅ | ✅ | - | - | PASS |
| Profile | ✅ | - | ✅ | ✅ | - | PASS |
| Settings | ✅ | - | ✅ | ✅ | - | PASS |
| Audit Logs | ✅ | - | ✅ | - | ✅ | PASS |

### Issues Found:
1. [Describe any issues]

### Overall Status: ✅ PASS / ❌ FAIL
```

---

**Happy Testing!** 🧪🚀

If you encounter any issues, check:
1. Browser console for errors
2. Network tab for failed requests
3. PHP error logs
4. Database connection

**Everything should work smoothly!** ✨
