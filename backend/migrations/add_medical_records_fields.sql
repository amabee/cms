-- Add chief_complaints and vital_signs columns to medical_records table
-- Remove treatment column (if it exists)

-- Add the new columns
ALTER TABLE `medical_records` 
ADD COLUMN `chief_complaints` TEXT NULL COMMENT 'Patient chief complaints' AFTER `doctor_id`,
ADD COLUMN `vital_signs` TEXT NULL COMMENT 'Patient vital signs' AFTER `chief_complaints`;

-- Drop treatment column if it exists (use IF EXISTS to avoid errors)
SET @sql = 'ALTER TABLE `medical_records` DROP COLUMN `treatment`';
SET @stmt = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
       AND TABLE_NAME = 'medical_records' 
       AND COLUMN_NAME = 'treatment') > 0,
    @sql,
    'SELECT 1' -- Do nothing if column doesn't exist
);
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
