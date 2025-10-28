-- Add hospital/clinic information settings to system_settings table
-- These will be used for dynamic email content and system branding

INSERT IGNORE INTO system_settings (setting_key, setting_value, description) VALUES
('hospital_name', 'Healthcare Management Clinic', 'Hospital/Clinic name used in emails and system branding'),
('hospital_address', '123 Healthcare St, Medical City, HC 12345', 'Complete hospital address for contact information'),
('hospital_phone', '+1 (555) 123-4567', 'Main hospital contact phone number'),
('hospital_website', 'https://yourhospital.com', 'Hospital official website URL'),
('hospital_email', 'info@yourhospital.com', 'Hospital general contact email'),
('hospital_fax', '+1 (555) 123-4568', 'Hospital fax number (optional)'),
('hospital_description', 'Providing quality healthcare services to our community', 'Brief description of the hospital/clinic'),
('hospital_logo_url', '/cms/assets/images/hospital-logo.png', 'Path to hospital logo image');

-- Create table if it doesn't exist (backup in case it's missing)
CREATE TABLE IF NOT EXISTS `system_settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL UNIQUE,
  `setting_value` text,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
