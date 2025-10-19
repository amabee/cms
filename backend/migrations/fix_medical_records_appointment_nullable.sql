-- Make appointment_id nullable in medical_records table
-- This allows creating medical records without linking to an appointment

-- First, drop the foreign key constraint
ALTER TABLE `medical_records` 
DROP FOREIGN KEY `medical_records_ibfk_1`;

-- Change the column to allow NULL
ALTER TABLE `medical_records` 
MODIFY COLUMN `appointment_id` int NULL;

-- Re-add the foreign key constraint with NULL allowed
ALTER TABLE `medical_records` 
ADD CONSTRAINT `medical_records_ibfk_1` 
FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`)
ON DELETE SET NULL ON UPDATE CASCADE;
