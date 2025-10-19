# Patient Registration Testing Guide

## Quick Test Scenarios

### Test 1: Basic Patient Registration (No Portal Account)
**Purpose**: Register a patient without creating a user account for portal access

**Steps**:
1. Navigate to Patients page
2. Click "Add New Patient" button
3. Fill in required fields:
   - First Name: `Test`
   - Last Name: `Patient`
   - Date of Birth: `1990-01-15`
   - Gender: Select `Male`
4. Leave "Create patient portal account" **UNCHECKED**
5. Click "Register Patient"

**Expected Result**:
- Success message with patient code (e.g., "P0001")
- Patient appears in patients list
- No user account created
- Patient data saved in patients table only

---

### Test 2: Full Patient Registration WITH Portal Account
**Purpose**: Register a patient with complete medical info and portal account

**Steps**:
1. Click "Add New Patient"
2. Fill in ALL fields:
   
   **Personal Information:**
   - First Name: `John`
   - Last Name: `Smith`
   - Middle Name: `Michael`
   - Date of Birth: `1985-06-20`
   - Gender: `Male`
   - Blood Type: `O+`
   - Email: `john.smith@email.com` *(required for account)*
   - Phone: `555-1234`
   - Address: `123 Main Street, City, State`
   
   **Emergency Contact:**
   - Name: `Jane Smith`
   - Phone: `555-5678`
   
   **Medical Information:**
   - Known Allergies: `Penicillin, Peanuts`
   - Existing Medical Conditions: `Type 2 Diabetes, Hypertension`
   
   **Insurance:**
   - Provider: `Blue Cross Blue Shield`
   - Policy Number: `BC123456789`
   
   **Additional Notes:**
   - `Patient prefers morning appointments. Needs wheelchair access.`
   
   **Account Creation:**
   - ✅ CHECK "Create patient portal account"

3. Click "Register Patient"

**Expected Result**:
- Success message showing:
  - Patient Code (e.g., "P0042")
  - Username (e.g., "john.smith")
  - Default password: `patient123`
- Patient appears in list with full name including middle name
- User account created in `users` table
- Profile created in `user_profiles` table
- All medical info saved

---

### Test 3: Patient with Allergies but No Insurance
**Purpose**: Test optional fields work correctly

**Steps**:
1. Click "Add New Patient"
2. Fill in:
   - First Name: `Sarah`
   - Last Name: `Johnson`
   - Date of Birth: `1992-03-10`
   - Gender: `Female`
   - Blood Type: `A+`
   - Known Allergies: `Latex, Shellfish, Aspirin`
   - Leave Insurance fields EMPTY
   - Leave notes EMPTY
   - Do NOT check "Create patient portal account"
3. Submit

**Expected Result**:
- Patient registered successfully
- Allergies saved
- Insurance fields null in database
- No portal account created

---

### Test 4: Update Existing Patient Medical Info
**Purpose**: Verify medical information can be updated

**Steps**:
1. Find a patient in the list
2. Click the three dots menu > "Edit"
3. Update:
   - Existing Conditions: Add `Asthma`
   - Notes: Add `Recent surgery on 2024-01-15`
   - Phone: Update to new number
4. Save changes

**Expected Result**:
- Success message
- Changes reflected immediately in patient record
- Table updates with new information

---

### Test 5: Search Patients by Different Criteria
**Purpose**: Test search functionality with new fields

**Test Searches**:
1. Search by first name: `John`
2. Search by last name: `Smith`
3. Search by middle name: `Michael`
4. Search by phone: `555-1234`
5. Search by patient code: `P0042`
6. Search by email: `john.smith@email.com`

**Expected Result**:
- All searches return correct matching patients
- Middle name search works
- Phone number search works with new field name

---

### Test 6: Filter by Blood Type
**Purpose**: Verify blood type filtering

**Steps**:
1. Use blood type dropdown filter
2. Select `O+`
3. Apply filter

**Expected Result**:
- Only patients with blood type O+ displayed
- Count updates correctly

---

### Test 7: Verify Patient Statistics
**Purpose**: Check statistics reflect new tracking fields

**Steps**:
1. Note the statistics cards at top of page:
   - Total Patients
   - Active Patients (should use `is_active` field)
   - New Patients This Month (should use `date_registered`)
2. Register a new patient
3. Refresh page

**Expected Result**:
- Total count increases by 1
- New Patients increases by 1
- Active Patients increases by 1 (if is_active = 1)

---

### Test 8: Patient Without Email (No Portal)
**Purpose**: Verify email is optional when not creating account

**Steps**:
1. Add new patient
2. Fill required fields (name, DOB, gender)
3. Leave EMAIL field empty
4. Do NOT check "Create patient portal account"
5. Submit

**Expected Result**:
- Patient registered successfully
- Email field null in database
- No validation error

---

### Test 9: Try to Create Account Without Email
**Purpose**: Verify email validation when account creation requested

**Steps**:
1. Add new patient
2. Fill required fields
3. Leave EMAIL field empty
4. CHECK "Create patient portal account"
5. Try to submit

**Expected Result**:
- Validation error OR
- Error message: "Email required when creating patient account"
- Form does not submit

---

### Test 10: View Patient Details in Table
**Purpose**: Verify table displays new fields correctly

**Check Table Displays**:
- ✅ Full name includes middle name
- ✅ Age calculated from date_of_birth
- ✅ Gender shown correctly
- ✅ Blood type badge displayed
- ✅ Phone number shows (not "N/A" if provided)
- ✅ Last visit date displayed

---

## Database Verification Tests

### Check Patients Table Structure
```sql
-- Verify new columns exist
DESCRIBE patients;

-- Should show these columns:
-- first_name, last_name, middle_name
-- date_of_birth, gender, blood_type
-- phone_number, email, address
-- emergency_contact_name, emergency_contact_phone
-- allergies, existing_conditions
-- date_registered, last_visit, notes, is_active
```

### Check Patient Data After Registration
```sql
-- After Test 2 (Full Registration)
SELECT * FROM patients WHERE patient_code = 'P0042';

-- Verify all fields populated:
-- allergies = 'Penicillin, Peanuts'
-- existing_conditions = 'Type 2 Diabetes, Hypertension'
-- emergency_contact_name = 'Jane Smith'
-- emergency_contact_phone = '555-5678'
-- notes contains appointment preference
```

### Check User Account Creation
```sql
-- After Test 2 (with account)
SELECT u.username, p.first_name, p.last_name, p.email
FROM patients p
LEFT JOIN users u ON p.user_id = u.user_id
WHERE p.patient_code = 'P0042';

-- Should return:
-- username = 'john.smith'
-- email matches patient record
```

### Check Active Status
```sql
-- Verify is_active field working
SELECT COUNT(*) as active_count 
FROM patients 
WHERE is_active = 1;

-- Should match "Active Patients" count in UI
```

---

## Error Handling Tests

### Test Missing Required Fields
1. Try to submit form without first_name → Should show error
2. Try to submit form without last_name → Should show error
3. Try to submit form without date_of_birth → Should show error
4. Try to submit form without gender → Should show error

### Test Invalid Data
1. Enter invalid date format → Should validate
2. Enter future date for date_of_birth → Should reject
3. Enter invalid email format → Should validate

---

## Performance Tests

### Large Patient List
1. Register 50+ patients
2. Test search responsiveness
3. Test filter speed
4. Test pagination (if implemented)

### Data Integrity
1. Register patient with special characters in name: `O'Brien`, `José`
2. Register patient with long allergy list (500+ characters)
3. Register patient with HTML in notes → Should be sanitized

---

## Compatibility Tests

### Test With Existing Patients (Migration)
If you have existing patient records:
1. Check old patients display correctly
2. Verify old emergency_contact field displays if still present
3. Edit old patient → Should use new field structure
4. Check patients without middle names don't break display

---

## Success Criteria

✅ **All registration scenarios work**
✅ **Medical information saves and displays correctly**
✅ **Search includes all new fields**
✅ **Statistics use new tracking fields**
✅ **Portal account creation works optionally**
✅ **Table displays middle names**
✅ **Emergency contact split into name/phone**
✅ **No errors in browser console**
✅ **No PHP errors in backend**

---

## Common Issues & Solutions

### Issue: Patient not showing in list
**Solution**: Check browser console for errors, verify axios request succeeds

### Issue: Middle name not displaying
**Solution**: Check renderPatientsTable() function includes middle_name in fullName

### Issue: Phone shows "N/A"
**Solution**: Verify field name is `phone_number` not `phone`

### Issue: Statistics incorrect
**Solution**: Verify backend uses `is_active` and `date_registered` fields

### Issue: Can't create account
**Solution**: Ensure email is provided when create_account checkbox is checked

---

**Testing Date**: _____________
**Tested By**: _____________
**Test Results**: _____________

