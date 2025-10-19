-- ==========================================================
-- 🔄 MIGRATION: Update Secretaries and Receptionists Tables
-- ==========================================================
-- Run this script to add new columns to existing staff tables
-- Date: October 19, 2025
-- ==========================================================

USE clinic_cms;

-- ==========================================================
-- UPDATE SECRETARIES TABLE
-- ==========================================================

ALTER TABLE secretaries 
ADD COLUMN IF NOT EXISTS office_location VARCHAR(150) AFTER assigned_doctor_id,
ADD COLUMN IF NOT EXISTS contact_number VARCHAR(50) AFTER office_location,
ADD COLUMN IF NOT EXISTS email VARCHAR(150) AFTER contact_number,
ADD COLUMN IF NOT EXISTS shift_start TIME AFTER email,
ADD COLUMN IF NOT EXISTS shift_end TIME AFTER shift_start,
ADD COLUMN IF NOT EXISTS work_days VARCHAR(100) AFTER shift_end,
ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive', 'on leave') DEFAULT 'active' AFTER work_days,
ADD COLUMN IF NOT EXISTS notes TEXT AFTER status,
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER notes,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- ==========================================================
-- UPDATE RECEPTIONISTS TABLE
-- ==========================================================

ALTER TABLE receptionists 
ADD COLUMN IF NOT EXISTS frontdesk_location VARCHAR(150) AFTER user_id,
ADD COLUMN IF NOT EXISTS contact_number VARCHAR(50) AFTER frontdesk_location,
ADD COLUMN IF NOT EXISTS email VARCHAR(150) AFTER contact_number,
ADD COLUMN IF NOT EXISTS shift_start TIME AFTER email,
ADD COLUMN IF NOT EXISTS shift_end TIME AFTER shift_start,
ADD COLUMN IF NOT EXISTS work_days VARCHAR(100) AFTER shift_end,
ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive', 'on leave') DEFAULT 'active' AFTER work_days,
ADD COLUMN IF NOT EXISTS notes TEXT AFTER status,
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER notes,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- ==========================================================
-- ✅ Migration Complete
-- ==========================================================
-- All new columns have been added to secretaries and receptionists tables
-- ==========================================================
