# Patient Table Structure Update - Summary

## Overview
This document summarizes the changes made to adapt the clinic CMS to the modified patients table structure where patient personal information is now stored directly in the `patients` table instead of the `user_profiles` table.

## Database Schema Changes

### New Patients Table Structure
The `patients` table now includes the following fields:

**Name Fields:**
- `first_name` (VARCHAR, NOT NULL) - Previously in user_profiles
- `last_name` (VARCHAR, NOT NULL) - Previously in user_profiles
- `middle_name` (VARCHAR, NULL) - NEW field

**Personal Information:**
- `date_of_birth` (DATE, NOT NULL) - Previously in user_profiles
- `gender` (ENUM, NOT NULL) - Previously in user_profiles
- `blood_type` (VARCHAR, NULL) - Existing field

**Contact Information:**
- `phone_number` (VARCHAR, NULL) - Previously in user_profiles (was 'phone')
- `email` (VARCHAR, NULL) - Previously in user_profiles
- `address` (TEXT, NULL) - Previously in user_profiles

**Emergency Contact:**
- `emergency_contact_name` (VARCHAR, NULL) - NEW detailed field
- `emergency_contact_phone` (VARCHAR, NULL) - NEW detailed field
- Replaced single `emergency_contact` field

**Medical Information (NEW):**
- `allergies` (TEXT, NULL) - NEW field for patient allergies
- `existing_conditions` (TEXT, NULL) - NEW field for medical history

**Tracking Fields (NEW):**
- `date_registered` (TIMESTAMP, DEFAULT CURRENT_TIMESTAMP) - NEW field
- `last_visit` (DATE, NULL) - NEW field
- `notes` (TEXT, NULL) - NEW field for additional remarks
- `is_active` (TINYINT, DEFAULT 1) - NEW status field

**Existing Fields:**
- `patient_id` (Primary key)
- `patient_code` (Unique identifier)
- `user_id` (Foreign key, NULL) - Only set if patient has portal account
- `insurance_id` (Foreign key)

## Backend Changes

### File: `backend/patients.php`

All major functions updated to work with new table structure:

#### 1. registerPatient() Function
**Changes:**
- Now inserts all patient fields directly into `patients` table
- Added fields: `middle_name`, `phone_number`, `emergency_contact_name`, `emergency_contact_phone`, `allergies`, `existing_conditions`, `notes`
- Removed dependency on `user_profiles` for patient data storage
- `user_profiles` only created if `create_account` checkbox is selected
- Generates unique `patient_code` (P0001-P9999)
- Returns: `patient_code`, `patient_id`, `username` (if account created)

**New Required Fields:**
- first_name, last_name, date_of_birth, gender (all NOT NULL in database)

**Optional Account Creation:**
- Checkbox determines if user account is created
- Default password: `patient123`

#### 2. getAll() Function
**Changes:**
- Query now selects name fields from `patients` table: `p.first_name`, `p.last_name`, `p.middle_name`
- Removed JOIN with `user_profiles` for patient data
- Returns all new fields: `phone_number`, `email`, `address`, `emergency_contact_name`, `emergency_contact_phone`, `allergies`, `existing_conditions`, `date_registered`, `last_visit`, `notes`, `is_active`
- Added `status` filter parameter for `is_active` field
- Search expanded to include `middle_name`, `phone_number`
- Includes `last_visit_date` from appointments subquery

#### 3. getById() Function
**Changes:**
- Query simplified to select all patient fields: `p.*`
- Removed `user_profiles` JOIN for basic patient data
- Returns complete patient record including insurance and last visit

#### 4. update() Function
**Changes:**
- UPDATE query targets `patients` table with all new fields
- Updates: `first_name`, `last_name`, `middle_name`, `date_of_birth`, `gender`, `phone_number`, `email`, `address`, `emergency_contact_name`, `emergency_contact_phone`, `allergies`, `existing_conditions`, `notes`, `is_active`
- `user_profiles` update is now conditional (only if `user_id` exists)
- Proper logging with patient name

#### 5. delete() Function
**Changes:**
- Query gets patient details from `patients` table: `p.first_name`, `p.last_name`
- Removed dependency on `user_profiles` for patient name
- Updated logging to use patient name from `patients` table

#### 6. getStatistics() Function
**Changes:**
- Active count uses `is_active = 1` instead of user status
- New patients count uses `date_registered` field
- Simplified queries (no user_profiles dependency)

## Frontend Changes

### File: `frontend/admin/patients.html`

#### 1. Add Patient Modal Form
**New/Updated Fields:**
- Added `middle_name` input field
- Changed `date_of_birth` to required
- Split emergency contact into two fields:
  - `emergency_contact_name`
  - `emergency_contact_phone`
- NEW Medical Information section:
  - `allergies` (textarea)
  - `existing_conditions` (textarea)
- NEW Insurance Information section:
  - `insurance_provider`
  - `insurance_policy_number`
- Added `notes` textarea for additional remarks
- Added `create_account` checkbox with explanation

**Removed:**
- Old single `emergency_contact` field
- Old single `insurance` field (if existed)

#### 2. Form Submission Handler
**Updated jsonData object to include:**
- `middle_name`
- `phone_number` (changed from `phone`)
- `emergency_contact_name`
- `emergency_contact_phone`
- `allergies`
- `existing_conditions`
- `insurance_provider`
- `insurance_policy_number`
- `notes`
- `create_account` (boolean converted to 1/0)

#### 3. Patient Table Display (renderPatientsTable)
**Changes:**
- Full name now includes middle name: `first_name + middle_name + last_name`
- Changed phone display from `patient.phone` to `patient.phone_number`
- Table correctly displays all new patient information

## Key Behavioral Changes

### Self-Contained Patients Table
- Patient data is now independent of user accounts
- A patient record can exist without a user account
- User accounts are optional and only created when needed for patient portal access

### Optional User Accounts
- `user_id` in patients table is now nullable
- When `create_account` is checked during registration:
  - User account created in `users` table
  - Profile created in `user_profiles` table  
  - Username generated from patient name
  - Default password: `patient123`
- When `create_account` is NOT checked:
  - Only patient record created
  - No user account or profile created
  - Patient data complete in patients table

### Enhanced Medical Tracking
- System now tracks patient allergies
- System tracks existing medical conditions
- Additional notes field for important remarks
- Last visit date tracked automatically
- Active/inactive status per patient

## Testing Checklist

### Registration Testing
- [ ] Register patient WITHOUT account (basic info only)
- [ ] Register patient WITH account (creates user + profile)
- [ ] Verify middle name displays correctly
- [ ] Test with allergies and medical conditions
- [ ] Verify patient code generation (P0001-P9999)
- [ ] Check email validation when create_account is checked

### Display Testing
- [ ] Patient list shows middle names
- [ ] Phone numbers display correctly
- [ ] Last visit dates appear
- [ ] Statistics reflect new is_active field

### Update Testing
- [ ] Update patient medical information
- [ ] Update emergency contact details
- [ ] Change patient active status
- [ ] Verify notes field saves

### Data Integrity
- [ ] Existing patients without user accounts work correctly
- [ ] Patients with user accounts maintain profile sync
- [ ] Search includes middle name and phone
- [ ] Statistics use new date_registered field

## Backward Compatibility

### Preserved Features
- `user_id` remains optional for existing integrations
- Patient records without user accounts fully functional
- Insurance linkage unchanged
- Appointment system compatibility maintained

### Migration Notes
If migrating existing data:
1. Existing patient records need name/contact fields populated from user_profiles
2. `emergency_contact` field data should be split into name/phone
3. Set `date_registered` to existing creation dates
4. Default `is_active` to 1 for existing patients

## Benefits of New Structure

1. **Simplified Queries**: No complex JOINs needed for basic patient info
2. **Better Data Organization**: Medical data grouped with patient record
3. **Flexible Architecture**: Patient records independent of user accounts
4. **Enhanced Tracking**: More detailed medical and contact information
5. **Improved Performance**: Fewer table joins for common queries

## Files Modified

### Backend
- `backend/patients.php` - All major functions updated

### Frontend
- `frontend/admin/patients.html` - Form and display updated

### Documentation
- This summary document created

## Next Steps

1. ✅ Backend API fully updated
2. ✅ Frontend form updated with new fields
3. ✅ Form submission handler updated
4. ✅ Table display updated
5. ⏳ End-to-end testing needed
6. ⏳ Update API_DOCUMENTATION.md with new fields
7. ⏳ Update TESTING_GUIDE.md with new scenarios
8. ⏳ Check if other modules (appointments, medical records) need updates

---

**Last Updated:** 2024
**Status:** Implementation Complete - Testing Pending
