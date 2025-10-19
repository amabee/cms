-- Database Backup
-- Database: clinic_cms
-- Date: 2025-10-19 08:18:51

SET FOREIGN_KEY_CHECKS=0;

-- Table: appointments
DROP TABLE IF EXISTS `appointments`;
CREATE TABLE `appointments` (
  `appointment_id` int NOT NULL AUTO_INCREMENT,
  `patient_id` int NOT NULL,
  `doctor_id` int NOT NULL,
  `appointment_date` date DEFAULT NULL,
  `appointment_time` time DEFAULT NULL,
  `reason` text,
  `status` enum('pending','confirmed','completed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`appointment_id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: billing
DROP TABLE IF EXISTS `billing`;
CREATE TABLE `billing` (
  `billing_id` int NOT NULL AUTO_INCREMENT,
  `appointment_id` int NOT NULL,
  `patient_id` int NOT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `discount` decimal(10,2) DEFAULT '0.00',
  `net_amount` decimal(10,2) DEFAULT NULL,
  `status` enum('unpaid','paid') DEFAULT 'unpaid',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`billing_id`),
  KEY `appointment_id` (`appointment_id`),
  KEY `patient_id` (`patient_id`),
  CONSTRAINT `billing_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`),
  CONSTRAINT `billing_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: billing_items
DROP TABLE IF EXISTS `billing_items`;
CREATE TABLE `billing_items` (
  `item_id` int NOT NULL AUTO_INCREMENT,
  `billing_id` int NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`item_id`),
  KEY `billing_id` (`billing_id`),
  CONSTRAINT `billing_items_ibfk_1` FOREIGN KEY (`billing_id`) REFERENCES `billing` (`billing_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: departments
DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
  `department_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `description` text,
  PRIMARY KEY (`department_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for table: departments
INSERT INTO `departments` (`department_id`, `name`, `description`) VALUES ('1', 'General Medicine', 'Provides comprehensive primary care and diagnosis for adult patients.');
INSERT INTO `departments` (`department_id`, `name`, `description`) VALUES ('2', 'Pediatrics', 'Specializes in the health and medical care of infants, children, and adolescents.');
INSERT INTO `departments` (`department_id`, `name`, `description`) VALUES ('3', 'Obstetrics and Gynecology', 'Focuses on women’s reproductive health, pregnancy, and childbirth.');
INSERT INTO `departments` (`department_id`, `name`, `description`) VALUES ('4', 'Surgery', 'Performs general and specialized surgical procedures.');
INSERT INTO `departments` (`department_id`, `name`, `description`) VALUES ('5', 'Orthopedics', 'Treats conditions related to bones, joints, and muscles.');
INSERT INTO `departments` (`department_id`, `name`, `description`) VALUES ('6', 'Cardiology', 'Provides diagnosis and treatment of heart and circulatory system diseases.');
INSERT INTO `departments` (`department_id`, `name`, `description`) VALUES ('7', 'Ophthalmology', 'Specializes in eye examinations, vision correction, and eye surgery.');
INSERT INTO `departments` (`department_id`, `name`, `description`) VALUES ('8', 'Otolaryngology (ENT)', 'Focuses on conditions of the ear, nose, and throat.');
INSERT INTO `departments` (`department_id`, `name`, `description`) VALUES ('9', 'Dermatology', 'Handles diagnosis and treatment of skin, hair, and nail disorders.');
INSERT INTO `departments` (`department_id`, `name`, `description`) VALUES ('10', 'Dentistry', 'Provides dental and oral healthcare services.');
INSERT INTO `departments` (`department_id`, `name`, `description`) VALUES ('11', 'Radiology', 'Performs diagnostic imaging such as X-rays, CT scans, and ultrasounds.');
INSERT INTO `departments` (`department_id`, `name`, `description`) VALUES ('12', 'Laboratory', 'Handles medical tests and diagnostic services for patient samples.');
INSERT INTO `departments` (`department_id`, `name`, `description`) VALUES ('13', 'Emergency Medicine', 'Provides urgent care and treatment for patients in critical condition.');
INSERT INTO `departments` (`department_id`, `name`, `description`) VALUES ('14', 'Psychiatry', 'Deals with diagnosis and treatment of mental health disorders.');
INSERT INTO `departments` (`department_id`, `name`, `description`) VALUES ('15', 'Physical Therapy and Rehabilitation', 'Provides therapy for recovery and mobility improvement after injury or surgery.');
INSERT INTO `departments` (`department_id`, `name`, `description`) VALUES ('16', 'Urology', 'Specializes in urinary tract and male reproductive system conditions.');
INSERT INTO `departments` (`department_id`, `name`, `description`) VALUES ('17', 'Neurology', 'Focuses on diseases of the brain and nervous system.');
INSERT INTO `departments` (`department_id`, `name`, `description`) VALUES ('18', 'Anesthesiology', 'Provides anesthesia and pain management during surgical procedures.');
INSERT INTO `departments` (`department_id`, `name`, `description`) VALUES ('19', 'Pulmonology', 'Specializes in lung and respiratory diseases.');
INSERT INTO `departments` (`department_id`, `name`, `description`) VALUES ('20', 'Family Medicine', 'Offers continuous and comprehensive healthcare for families and individuals.');

-- Table: doctors
DROP TABLE IF EXISTS `doctors`;
CREATE TABLE `doctors` (
  `doctor_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `license_no` varchar(100) DEFAULT NULL,
  `ptr_no` varchar(100) DEFAULT NULL,
  `s2_license_no` varchar(100) DEFAULT NULL,
  `years_of_experience` int DEFAULT '0',
  `room_number` varchar(50) DEFAULT NULL,
  `consultation_fee` decimal(10,2) DEFAULT '0.00',
  `schedule_days` varchar(100) DEFAULT NULL,
  `schedule_start` time DEFAULT NULL,
  `schedule_end` time DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `status` enum('active','inactive','on leave') DEFAULT 'active',
  `profile_photo` varchar(255) DEFAULT NULL,
  `biography` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `department_id` int DEFAULT NULL,
  PRIMARY KEY (`doctor_id`),
  KEY `user_id` (`user_id`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `doctors_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `doctors_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for table: doctors
INSERT INTO `doctors` (`doctor_id`, `user_id`, `specialization`, `license_no`, `ptr_no`, `s2_license_no`, `years_of_experience`, `room_number`, `consultation_fee`, `schedule_days`, `schedule_start`, `schedule_end`, `contact_number`, `email`, `status`, `profile_photo`, `biography`, `created_at`, `updated_at`, `department_id`) VALUES ('1', '4', 'Inventore excepturi ', NULL, NULL, NULL, '0', NULL, '0.00', NULL, NULL, NULL, NULL, NULL, 'active', NULL, NULL, '2025-10-19 13:34:36', '2025-10-19 13:34:36', NULL);
INSERT INTO `doctors` (`doctor_id`, `user_id`, `specialization`, `license_no`, `ptr_no`, `s2_license_no`, `years_of_experience`, `room_number`, `consultation_fee`, `schedule_days`, `schedule_start`, `schedule_end`, `contact_number`, `email`, `status`, `profile_photo`, `biography`, `created_at`, `updated_at`, `department_id`) VALUES ('2', '5', 'Est magni dolorem a', 'Non quo excepteur cu', NULL, NULL, '0', NULL, '0.00', NULL, NULL, NULL, NULL, NULL, 'active', NULL, NULL, '2025-10-19 13:34:36', '2025-10-19 13:34:36', '12');
INSERT INTO `doctors` (`doctor_id`, `user_id`, `specialization`, `license_no`, `ptr_no`, `s2_license_no`, `years_of_experience`, `room_number`, `consultation_fee`, `schedule_days`, `schedule_start`, `schedule_end`, `contact_number`, `email`, `status`, `profile_photo`, `biography`, `created_at`, `updated_at`, `department_id`) VALUES ('3', '6', 'Pariatur Velit quo ', 'Vel aliquip dolore c', NULL, NULL, '0', NULL, '0.00', NULL, NULL, NULL, NULL, NULL, 'active', NULL, NULL, '2025-10-19 13:34:36', '2025-10-19 13:34:36', '5');
INSERT INTO `doctors` (`doctor_id`, `user_id`, `specialization`, `license_no`, `ptr_no`, `s2_license_no`, `years_of_experience`, `room_number`, `consultation_fee`, `schedule_days`, `schedule_start`, `schedule_end`, `contact_number`, `email`, `status`, `profile_photo`, `biography`, `created_at`, `updated_at`, `department_id`) VALUES ('4', '7', 'Praesentium qui labo', 'Ut aute molestias cu', 'Sit proident eos f', 'Excepturi et qui id ', '9', '617', '76.00', '27', '09:00:00', '16:00:00', '448', 'kypeca@mailinator.com', 'active', NULL, 'Et temporibus facere', '2025-10-19 13:44:55', '2025-10-19 13:44:55', '18');

-- Table: insurance
DROP TABLE IF EXISTS `insurance`;
CREATE TABLE `insurance` (
  `insurance_id` int NOT NULL AUTO_INCREMENT,
  `company_name` varchar(150) DEFAULT NULL,
  `policy_no` varchar(100) DEFAULT NULL,
  `coverage_details` text,
  PRIMARY KEY (`insurance_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for table: insurance
INSERT INTO `insurance` (`insurance_id`, `company_name`, `policy_no`, `coverage_details`) VALUES ('1', 'PhilHealth', 'PH-001', 'Provides basic healthcare coverage for all Filipinos, including hospitalization, outpatient benefits, and maternity care.');
INSERT INTO `insurance` (`insurance_id`, `company_name`, `policy_no`, `coverage_details`) VALUES ('2', 'Maxicare Health Corporation', 'MX-1001', 'Comprehensive HMO plans including inpatient, outpatient, dental, and wellness programs.');
INSERT INTO `insurance` (`insurance_id`, `company_name`, `policy_no`, `coverage_details`) VALUES ('3', 'Intellicare Health Services, Inc.', 'IC-2002', 'Covers consultations, hospitalization, preventive care, and laboratory services.');
INSERT INTO `insurance` (`insurance_id`, `company_name`, `policy_no`, `coverage_details`) VALUES ('4', 'Pacific Cross Philippines', 'PC-3003', 'International and local health insurance coverage including emergency medical assistance.');
INSERT INTO `insurance` (`insurance_id`, `company_name`, `policy_no`, `coverage_details`) VALUES ('5', 'MediCard Philippines, Inc.', 'MD-4004', 'Provides HMO services for inpatient, outpatient, dental, and preventive care.');
INSERT INTO `insurance` (`insurance_id`, `company_name`, `policy_no`, `coverage_details`) VALUES ('6', 'Cocolife Health Care', 'CL-5005', 'Covers hospitalization, outpatient consultations, and critical illness programs.');
INSERT INTO `insurance` (`insurance_id`, `company_name`, `policy_no`, `coverage_details`) VALUES ('7', 'Pru Life UK Health', 'PR-6006', 'Health insurance with coverage for hospitalization, surgeries, and preventive healthcare.');
INSERT INTO `insurance` (`insurance_id`, `company_name`, `policy_no`, `coverage_details`) VALUES ('8', 'Philippine Axa Life', 'AX-7007', 'Comprehensive health plans with medical, surgical, and wellness benefits.');
INSERT INTO `insurance` (`insurance_id`, `company_name`, `policy_no`, `coverage_details`) VALUES ('9', 'FWD Life Philippines', 'FW-8008', 'Covers hospitalization, outpatient services, and lifestyle wellness programs.');
INSERT INTO `insurance` (`insurance_id`, `company_name`, `policy_no`, `coverage_details`) VALUES ('10', 'Generali Philippines', 'GN-9009', 'Provides inpatient and outpatient coverage, emergency care, and preventive health programs.');

-- Table: lab_request_items
DROP TABLE IF EXISTS `lab_request_items`;
CREATE TABLE `lab_request_items` (
  `lab_request_item_id` int NOT NULL AUTO_INCREMENT,
  `lab_request_id` int NOT NULL,
  `lab_test_id` int NOT NULL,
  `result` text,
  `status` enum('pending','in-progress','completed','verified') DEFAULT 'pending',
  `normal_range` varchar(150) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `collected_by` int DEFAULT NULL,
  `verified_by` int DEFAULT NULL,
  `date_collected` datetime DEFAULT NULL,
  `date_verified` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`lab_request_item_id`),
  KEY `lab_request_id` (`lab_request_id`),
  KEY `lab_test_id` (`lab_test_id`),
  KEY `collected_by` (`collected_by`),
  KEY `verified_by` (`verified_by`),
  CONSTRAINT `lab_request_items_ibfk_1` FOREIGN KEY (`lab_request_id`) REFERENCES `lab_requests` (`lab_request_id`) ON DELETE CASCADE,
  CONSTRAINT `lab_request_items_ibfk_2` FOREIGN KEY (`lab_test_id`) REFERENCES `lab_tests` (`lab_test_id`),
  CONSTRAINT `lab_request_items_ibfk_3` FOREIGN KEY (`collected_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `lab_request_items_ibfk_4` FOREIGN KEY (`verified_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: lab_requests
DROP TABLE IF EXISTS `lab_requests`;
CREATE TABLE `lab_requests` (
  `lab_request_id` int NOT NULL AUTO_INCREMENT,
  `request_no` varchar(50) DEFAULT NULL,
  `patient_id` int NOT NULL,
  `doctor_id` int NOT NULL,
  `requested_by` int DEFAULT NULL,
  `priority` enum('normal','urgent') DEFAULT 'normal',
  `status` enum('requested','in-progress','completed','cancelled') DEFAULT 'requested',
  `payment_status` enum('unpaid','paid','waived') DEFAULT 'unpaid',
  `total_cost` decimal(10,2) DEFAULT '0.00',
  `remarks` text,
  `date_requested` datetime DEFAULT CURRENT_TIMESTAMP,
  `date_completed` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`lab_request_id`),
  UNIQUE KEY `request_no` (`request_no`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  KEY `requested_by` (`requested_by`),
  CONSTRAINT `lab_requests_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  CONSTRAINT `lab_requests_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`),
  CONSTRAINT `lab_requests_ibfk_3` FOREIGN KEY (`requested_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: lab_tests
DROP TABLE IF EXISTS `lab_tests`;
CREATE TABLE `lab_tests` (
  `lab_test_id` int NOT NULL AUTO_INCREMENT,
  `test_code` varchar(50) DEFAULT NULL,
  `test_name` varchar(150) NOT NULL,
  `description` text,
  `sample_type` varchar(100) DEFAULT NULL,
  `normal_range` varchar(150) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT '0.00',
  `active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`lab_test_id`),
  UNIQUE KEY `test_code` (`test_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: medical_records
DROP TABLE IF EXISTS `medical_records`;
CREATE TABLE `medical_records` (
  `record_id` int NOT NULL AUTO_INCREMENT,
  `appointment_id` int NOT NULL,
  `patient_id` int NOT NULL,
  `doctor_id` int NOT NULL,
  `diagnosis` text,
  `treatment` text,
  `notes` text,
  `record_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`record_id`),
  KEY `appointment_id` (`appointment_id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  CONSTRAINT `medical_records_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`),
  CONSTRAINT `medical_records_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  CONSTRAINT `medical_records_ibfk_3` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: notifications
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `notification_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `title` varchar(150) DEFAULT NULL,
  `message` text,
  `is_read` tinyint(1) DEFAULT '0',
  `type` enum('info','warning','alert','appointment','billing') DEFAULT 'info',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for table: notifications
INSERT INTO `notifications` (`notification_id`, `user_id`, `title`, `message`, `is_read`, `type`, `created_at`) VALUES ('1', '1', 'Welcome Admin!', 'Your clinic management system is successfully installed.', '0', 'info', '2025-10-19 09:16:18');

-- Table: patients
DROP TABLE IF EXISTS `patients`;
CREATE TABLE `patients` (
  `patient_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `patient_code` varchar(50) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT 'Other',
  `emergency_contact` varchar(100) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` text,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `blood_type` varchar(5) DEFAULT NULL,
  `allergies` text,
  `existing_conditions` text,
  `insurance_id` int DEFAULT NULL,
  `date_registered` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_visit` datetime DEFAULT NULL,
  `notes` text,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`patient_id`),
  UNIQUE KEY `patient_code` (`patient_code`),
  KEY `user_id` (`user_id`),
  KEY `insurance_id` (`insurance_id`),
  CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `patients_ibfk_2` FOREIGN KEY (`insurance_id`) REFERENCES `insurance` (`insurance_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for table: patients
INSERT INTO `patients` (`patient_id`, `user_id`, `patient_code`, `first_name`, `last_name`, `middle_name`, `date_of_birth`, `gender`, `emergency_contact`, `phone_number`, `email`, `address`, `emergency_contact_name`, `emergency_contact_phone`, `blood_type`, `allergies`, `existing_conditions`, `insurance_id`, `date_registered`, `last_visit`, `notes`, `is_active`) VALUES ('2', '3', 'P8438', 'Lev', 'Serrano', 'Quyn Barrett', '1997-06-05', 'Female', NULL, '+1 (631) 497-9409', 'dezykivyni@mailinator.com', 'Eius odio voluptatem', 'Emery Moses', '+1 (313) 124-8972', 'B-', 'Vel deserunt ut simi', 'In deserunt magna it', NULL, '2025-10-19 13:03:58', NULL, 'Enim nisi autem aut', '1');
INSERT INTO `patients` (`patient_id`, `user_id`, `patient_code`, `first_name`, `last_name`, `middle_name`, `date_of_birth`, `gender`, `emergency_contact`, `phone_number`, `email`, `address`, `emergency_contact_name`, `emergency_contact_phone`, `blood_type`, `allergies`, `existing_conditions`, `insurance_id`, `date_registered`, `last_visit`, `notes`, `is_active`) VALUES ('3', '9', 'P5706', 'Merritt', 'Faulkner', 'Scott Sanford', '2013-07-17', 'Male', NULL, '+1 (953) 135-5463', 'haqot@mailinator.com', 'Fuga Ut soluta inve', 'Reuben Gentry', '+1 (783) 974-1034', 'A-', 'Quasi explicabo Sun', 'At quas aut culpa r', '7', '2025-10-19 14:21:21', NULL, 'Minim mollit cupidat', '1');

-- Table: payments
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `payment_id` int NOT NULL AUTO_INCREMENT,
  `billing_id` int NOT NULL,
  `amount_paid` decimal(10,2) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `reference_no` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`payment_id`),
  KEY `billing_id` (`billing_id`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`billing_id`) REFERENCES `billing` (`billing_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: prescriptions
DROP TABLE IF EXISTS `prescriptions`;
CREATE TABLE `prescriptions` (
  `prescription_id` int NOT NULL AUTO_INCREMENT,
  `record_id` int NOT NULL,
  `medicine_name` varchar(150) DEFAULT NULL,
  `dosage` varchar(100) DEFAULT NULL,
  `frequency` varchar(100) DEFAULT NULL,
  `duration` varchar(100) DEFAULT NULL,
  `instructions` text,
  PRIMARY KEY (`prescription_id`),
  KEY `record_id` (`record_id`),
  CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`record_id`) REFERENCES `medical_records` (`record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: queue
DROP TABLE IF EXISTS `queue`;
CREATE TABLE `queue` (
  `queue_id` int NOT NULL AUTO_INCREMENT,
  `appointment_id` int NOT NULL,
  `queue_number` int NOT NULL,
  `status` enum('waiting','called','done','skipped') DEFAULT 'waiting',
  `timestamp_in` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `timestamp_out` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`queue_id`),
  KEY `appointment_id` (`appointment_id`),
  CONSTRAINT `queue_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: receptionists
DROP TABLE IF EXISTS `receptionists`;
CREATE TABLE `receptionists` (
  `receptionist_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `frontdesk_location` varchar(150) DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `shift_start` time DEFAULT NULL,
  `shift_end` time DEFAULT NULL,
  `work_days` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive','on leave') DEFAULT 'active',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`receptionist_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `receptionists_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for table: receptionists
INSERT INTO `receptionists` (`receptionist_id`, `user_id`, `frontdesk_location`, `contact_number`, `email`, `shift_start`, `shift_end`, `work_days`, `status`, `notes`, `created_at`, `updated_at`) VALUES ('1', '8', 'Near Table', '524', 'rulam@mailinator.com', '08:00:00', '17:00:00', '19', 'active', 'Enim commodi accusam', '2025-10-19 14:10:16', '2025-10-19 14:10:42');

-- Table: schedules
DROP TABLE IF EXISTS `schedules`;
CREATE TABLE `schedules` (
  `schedule_id` int NOT NULL AUTO_INCREMENT,
  `doctor_id` int NOT NULL,
  `day_of_week` enum('Mon','Tue','Wed','Thu','Fri','Sat','Sun') DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `max_patients` int DEFAULT '20',
  PRIMARY KEY (`schedule_id`),
  KEY `doctor_id` (`doctor_id`),
  CONSTRAINT `schedules_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: secretaries
DROP TABLE IF EXISTS `secretaries`;
CREATE TABLE `secretaries` (
  `secretary_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `assigned_doctor_id` int DEFAULT NULL,
  `office_location` varchar(150) DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `shift_start` time DEFAULT NULL,
  `shift_end` time DEFAULT NULL,
  `work_days` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive','on leave') DEFAULT 'active',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`secretary_id`),
  KEY `user_id` (`user_id`),
  KEY `assigned_doctor_id` (`assigned_doctor_id`),
  CONSTRAINT `secretaries_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `secretaries_ibfk_2` FOREIGN KEY (`assigned_doctor_id`) REFERENCES `doctors` (`doctor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: system_logs
DROP TABLE IF EXISTS `system_logs`;
CREATE TABLE `system_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `description` text,
  `ip_address` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `system_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for table: system_logs
INSERT INTO `system_logs` (`log_id`, `user_id`, `action`, `description`, `ip_address`, `created_at`) VALUES ('1', '1', 'create', 'Created doctor: Howard Reyes', '127.0.0.1', '2025-10-19 13:06:56');
INSERT INTO `system_logs` (`log_id`, `user_id`, `action`, `description`, `ip_address`, `created_at`) VALUES ('2', '1', 'create', 'Created doctor: Ferdinand Lambert', '127.0.0.1', '2025-10-19 13:15:25');
INSERT INTO `system_logs` (`log_id`, `user_id`, `action`, `description`, `ip_address`, `created_at`) VALUES ('3', '1', 'create', 'Created doctor: Penelope Burke', '127.0.0.1', '2025-10-19 13:17:25');
INSERT INTO `system_logs` (`log_id`, `user_id`, `action`, `description`, `ip_address`, `created_at`) VALUES ('4', '1', 'create', 'Created doctor: Olivia Diaz', '127.0.0.1', '2025-10-19 13:44:55');
INSERT INTO `system_logs` (`log_id`, `user_id`, `action`, `description`, `ip_address`, `created_at`) VALUES ('5', '1', 'create', 'Created Receptionist: Noelani Hill', '127.0.0.1', '2025-10-19 14:10:16');
INSERT INTO `system_logs` (`log_id`, `user_id`, `action`, `description`, `ip_address`, `created_at`) VALUES ('6', '1', 'update', 'Updated staff member ID: 8', '127.0.0.1', '2025-10-19 14:10:42');
INSERT INTO `system_logs` (`log_id`, `user_id`, `action`, `description`, `ip_address`, `created_at`) VALUES ('7', '1', 'register_patient', 'Registered patient: Merritt Faulkner (Code: P5706)', '127.0.0.1', '2025-10-19 14:21:21');
INSERT INTO `system_logs` (`log_id`, `user_id`, `action`, `description`, `ip_address`, `created_at`) VALUES ('8', '1', 'settings', 'Updated system settings', '127.0.0.1', '2025-10-19 15:31:06');
INSERT INTO `system_logs` (`log_id`, `user_id`, `action`, `description`, `ip_address`, `created_at`) VALUES ('9', '1', 'settings', 'Updated system settings', '127.0.0.1', '2025-10-19 15:31:35');
INSERT INTO `system_logs` (`log_id`, `user_id`, `action`, `description`, `ip_address`, `created_at`) VALUES ('10', '1', 'backup', 'Created database backup: backup_clinic_cms_2025-10-19_07-46-33.sql', '127.0.0.1', '2025-10-19 15:46:34');

-- Table: system_settings
DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `setting_id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) DEFAULT NULL,
  `setting_value` text,
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for table: system_settings
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`) VALUES ('1', 'hospital_name', 'St. Gabriel Medical Center');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`) VALUES ('2', 'address', 'Kauswagan Highway, Cagayan de Oro City');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`) VALUES ('3', 'contact_number', '+63 2 8888 1234');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`) VALUES ('4', 'email', 'info@stgabrielmc.ph');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`) VALUES ('5', 'website', 'https://www.stgabrielmc.ph');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`) VALUES ('6', 'logo', 'logo.png');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`) VALUES ('7', 'timezone', 'Asia/Manila');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`) VALUES ('8', 'default_currency', 'PHP');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`) VALUES ('9', 'default_language', 'en-PH');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`) VALUES ('10', 'appointment_prefix', 'APT');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`) VALUES ('11', 'default_consultation_fee', '500');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`) VALUES ('12', 'default_insurance_coverage', 'PhilHealth');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`) VALUES ('13', 'working_hours_start', '08:00');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`) VALUES ('14', 'working_hours_end', '17:00');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`) VALUES ('15', 'queue_prefix', 'Q');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`) VALUES ('16', 'system_name', 'My Clinic CMSS');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`) VALUES ('17', 'date_format', 'MM/DD/YYYY');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`) VALUES ('18', 'time_format', '12');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`) VALUES ('19', 'language', 'en');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`) VALUES ('20', 'admin_user_id', '1');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`) VALUES ('21', 'clinic_name', 'Polymedic Clinic');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`) VALUES ('22', 'phone', '25152211');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`) VALUES ('23', 'opening_time', '08:00');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`) VALUES ('24', 'closing_time', '17:00');
INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`) VALUES ('25', 'working_days[]', '1');

-- Table: user_profiles
DROP TABLE IF EXISTS `user_profiles`;
CREATE TABLE `user_profiles` (
  `profile_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`profile_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for table: user_profiles
INSERT INTO `user_profiles` (`profile_id`, `user_id`, `first_name`, `last_name`, `gender`, `birth_date`, `address`, `phone`) VALUES ('1', '1', 'System', 'Administrator', 'other', '1990-01-01', 'Default Clinic HQ', '000-000-0000');
INSERT INTO `user_profiles` (`profile_id`, `user_id`, `first_name`, `last_name`, `gender`, `birth_date`, `address`, `phone`) VALUES ('3', '3', 'Lev', 'Serrano', 'female', '1997-06-05', 'Eius odio voluptatem', '+1 (631) 497-9409');
INSERT INTO `user_profiles` (`profile_id`, `user_id`, `first_name`, `last_name`, `gender`, `birth_date`, `address`, `phone`) VALUES ('4', '4', 'Howard', 'Reyes', NULL, NULL, NULL, '+1 (634) 894-9205');
INSERT INTO `user_profiles` (`profile_id`, `user_id`, `first_name`, `last_name`, `gender`, `birth_date`, `address`, `phone`) VALUES ('5', '5', 'Ferdinand', 'Lambert', NULL, NULL, NULL, '+1 (528) 627-6768');
INSERT INTO `user_profiles` (`profile_id`, `user_id`, `first_name`, `last_name`, `gender`, `birth_date`, `address`, `phone`) VALUES ('6', '6', 'Penelope', 'Burke', NULL, NULL, NULL, '+1 (105) 214-3733');
INSERT INTO `user_profiles` (`profile_id`, `user_id`, `first_name`, `last_name`, `gender`, `birth_date`, `address`, `phone`) VALUES ('7', '7', 'Olivia', 'Diaz', 'other', '1987-07-31', 'Excepteur exercitati', '+1 (567) 137-1878');
INSERT INTO `user_profiles` (`profile_id`, `user_id`, `first_name`, `last_name`, `gender`, `birth_date`, `address`, `phone`) VALUES ('8', '8', 'Noelani', 'Hill', 'female', '2004-07-22', 'Consequat Non asper', '+1 (293) 785-3704');
INSERT INTO `user_profiles` (`profile_id`, `user_id`, `first_name`, `last_name`, `gender`, `birth_date`, `address`, `phone`) VALUES ('9', '9', 'Merritt', 'Faulkner', 'male', '2013-07-17', 'Fuga Ut soluta inve', '+1 (953) 135-5463');

-- Table: users
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `usertype_id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  KEY `usertype_id` (`usertype_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`usertype_id`) REFERENCES `usertypes` (`usertype_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for table: users
INSERT INTO `users` (`user_id`, `usertype_id`, `username`, `password_hash`, `email`, `status`, `created_at`, `updated_at`) VALUES ('1', '1', 'admin', '$2y$10$MviUXoBQe4z5Z1/cGzcFiO6WzoebQlRdresYeBSNe.Dtk/uw/aQSq', 'admin@clinic.local', 'active', '2025-10-19 09:16:18', '2025-10-19 09:16:38');
INSERT INTO `users` (`user_id`, `usertype_id`, `username`, `password_hash`, `email`, `status`, `created_at`, `updated_at`) VALUES ('3', '5', 'dezykivyni', '$2y$10$3ql1SyJkQm6RDO7N95Zoder1LgyESJxhlMptVYns0yGUS5b.A3KGy', 'dezykivyni@mailinator.com', 'active', '2025-10-19 13:03:58', '2025-10-19 13:03:58');
INSERT INTO `users` (`user_id`, `usertype_id`, `username`, `password_hash`, `email`, `status`, `created_at`, `updated_at`) VALUES ('4', '2', 'jequf', '$2y$10$a2lAChjAqOG3Gb/edPCJyu6Cu2kZlz4/4Scc63AcIginEbZaGheha', 'jequf@mailinator.com', 'active', '2025-10-19 13:06:56', '2025-10-19 13:06:56');
INSERT INTO `users` (`user_id`, `usertype_id`, `username`, `password_hash`, `email`, `status`, `created_at`, `updated_at`) VALUES ('5', '2', 'ryte', '$2y$10$OgSq3TWBMKlkIWaIf0O/BeXvaR4y6TrNBN1Zk99/3n1mv6sEWQcRS', 'ryte@mailinator.com', 'active', '2025-10-19 13:15:25', '2025-10-19 13:15:25');
INSERT INTO `users` (`user_id`, `usertype_id`, `username`, `password_hash`, `email`, `status`, `created_at`, `updated_at`) VALUES ('6', '2', 'vekar', '$2y$10$YTjtgIRLWpYhLHh6wCDA6utenWJ.7yJUT2juRpyI2cycEhJM6Oo8.', 'vekar@mailinator.com', 'active', '2025-10-19 13:17:25', '2025-10-19 13:17:25');
INSERT INTO `users` (`user_id`, `usertype_id`, `username`, `password_hash`, `email`, `status`, `created_at`, `updated_at`) VALUES ('7', '2', 'kypeca', '$2y$10$F3vmurkczRPZvP5YLr1bn.288OoHJ673zfD3u7l2zg6BIxGma1gye', 'kypeca@mailinator.com', 'active', '2025-10-19 13:44:55', '2025-10-19 13:44:55');
INSERT INTO `users` (`user_id`, `usertype_id`, `username`, `password_hash`, `email`, `status`, `created_at`, `updated_at`) VALUES ('8', '4', 'wuwaz', '$2y$10$pPNvIOOlToWDJwLafK.FBe4Gf5bvwxKWJyKNm2hMzI/2KN6rhbOLi', 'wuwaz@mailinator.com', 'active', '2025-10-19 14:10:16', '2025-10-19 14:10:42');
INSERT INTO `users` (`user_id`, `usertype_id`, `username`, `password_hash`, `email`, `status`, `created_at`, `updated_at`) VALUES ('9', '5', 'haqot', '$2y$10$Wlav3IfT6ytwGz71/5TgVeAZJMGjvexhv2nI1/q8tNvfFey4p6lzm', 'haqot@mailinator.com', 'active', '2025-10-19 14:21:21', '2025-10-19 14:21:21');

-- Table: usertypes
DROP TABLE IF EXISTS `usertypes`;
CREATE TABLE `usertypes` (
  `usertype_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  PRIMARY KEY (`usertype_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data for table: usertypes
INSERT INTO `usertypes` (`usertype_id`, `name`) VALUES ('1', 'Admin');
INSERT INTO `usertypes` (`usertype_id`, `name`) VALUES ('2', 'Doctor');
INSERT INTO `usertypes` (`usertype_id`, `name`) VALUES ('3', 'Secretary');
INSERT INTO `usertypes` (`usertype_id`, `name`) VALUES ('4', 'Receptionist');
INSERT INTO `usertypes` (`usertype_id`, `name`) VALUES ('5', 'Patient');

-- Table: vital_signs
DROP TABLE IF EXISTS `vital_signs`;
CREATE TABLE `vital_signs` (
  `vital_id` int NOT NULL AUTO_INCREMENT,
  `patient_id` int NOT NULL,
  `appointment_id` int NOT NULL,
  `temperature` decimal(4,1) DEFAULT NULL,
  `blood_pressure` varchar(20) DEFAULT NULL,
  `pulse_rate` int DEFAULT NULL,
  `resp_rate` int DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `height` decimal(5,2) DEFAULT NULL,
  `recorded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`vital_id`),
  KEY `patient_id` (`patient_id`),
  KEY `appointment_id` (`appointment_id`),
  CONSTRAINT `vital_signs_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  CONSTRAINT `vital_signs_ibfk_2` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

SET FOREIGN_KEY_CHECKS=1;
