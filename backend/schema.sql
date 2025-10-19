-- ==========================================================
-- 🏥 CLINIC MANAGEMENT SYSTEM DATABASE SCHEMA
-- ==========================================================

CREATE DATABASE IF NOT EXISTS clinic_cms;
USE clinic_cms;

-- ==========================================================
-- USER & ACCESS CONTROL
-- ==========================================================

CREATE TABLE usertypes (
    usertype_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL
);

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    usertype_id INT NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usertype_id) REFERENCES usertypes(usertype_id)
);

CREATE TABLE user_profiles (
    profile_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    gender ENUM('male','female','other'),
    birth_date DATE,
    address VARCHAR(255),
    phone VARCHAR(50),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- ==========================================================
-- STAFF & PATIENTS
-- ==========================================================

CREATE TABLE departments (
    department_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    description TEXT
);

CREATE TABLE doctors (
    doctor_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    specialization VARCHAR(100),
    license_no VARCHAR(100),
    department_id INT,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (department_id) REFERENCES departments(department_id)
);

CREATE TABLE secretaries (
    secretary_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    assigned_doctor_id INT,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (assigned_doctor_id) REFERENCES doctors(doctor_id)
);

CREATE TABLE receptionists (
    receptionist_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE insurance (
    insurance_id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(150),
    policy_no VARCHAR(100),
    coverage_details TEXT
);

CREATE TABLE patients (
    patient_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    patient_code VARCHAR(50) UNIQUE,
    emergency_contact VARCHAR(100),
    blood_type VARCHAR(5),
    insurance_id INT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (insurance_id) REFERENCES insurance(insurance_id)
);

-- ==========================================================
-- APPOINTMENTS, SCHEDULES & QUEUE
-- ==========================================================

CREATE TABLE schedules (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    day_of_week ENUM('Mon','Tue','Wed','Thu','Fri','Sat','Sun'),
    start_time TIME,
    end_time TIME,
    max_patients INT DEFAULT 20,
    FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id)
);

CREATE TABLE appointments (
    appointment_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATE,
    appointment_time TIME,
    reason TEXT,
    status ENUM('pending','confirmed','completed','cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id)
);

CREATE TABLE queue (
    queue_id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    queue_number INT NOT NULL,
    status ENUM('waiting','called','done','skipped') DEFAULT 'waiting',
    timestamp_in TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    timestamp_out TIMESTAMP NULL,
    FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id)
);

-- ==========================================================
-- MEDICAL RECORDS
-- ==========================================================

CREATE TABLE vital_signs (
    vital_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    appointment_id INT NOT NULL,
    temperature DECIMAL(4,1),
    blood_pressure VARCHAR(20),
    pulse_rate INT,
    resp_rate INT,
    weight DECIMAL(5,2),
    height DECIMAL(5,2),
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id),
    FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id)
);

CREATE TABLE medical_records (
    record_id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    diagnosis TEXT,
    treatment TEXT,
    notes TEXT,
    record_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id),
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id)
);

CREATE TABLE prescriptions (
    prescription_id INT AUTO_INCREMENT PRIMARY KEY,
    record_id INT NOT NULL,
    medicine_name VARCHAR(150),
    dosage VARCHAR(100),
    frequency VARCHAR(100),
    duration VARCHAR(100),
    instructions TEXT,
    FOREIGN KEY (record_id) REFERENCES medical_records(record_id)
);

CREATE TABLE lab_tests (
    labtest_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    test_type VARCHAR(150),
    result TEXT,
    status ENUM('requested','in-progress','completed') DEFAULT 'requested',
    date_requested TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_completed TIMESTAMP NULL,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id)
);

-- ==========================================================
-- BILLING & PAYMENTS
-- ==========================================================

CREATE TABLE billing (
    billing_id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    patient_id INT NOT NULL,
    total_amount DECIMAL(10,2),
    discount DECIMAL(10,2) DEFAULT 0.00,
    net_amount DECIMAL(10,2),
    status ENUM('unpaid','paid') DEFAULT 'unpaid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id),
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id)
);

CREATE TABLE billing_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    billing_id INT NOT NULL,
    description VARCHAR(255),
    amount DECIMAL(10,2),
    FOREIGN KEY (billing_id) REFERENCES billing(billing_id)
);

CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    billing_id INT NOT NULL,
    amount_paid DECIMAL(10,2),
    payment_method VARCHAR(50),
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reference_no VARCHAR(100),
    FOREIGN KEY (billing_id) REFERENCES billing(billing_id)
);

-- ==========================================================
-- SYSTEM LOGS & SETTINGS
-- ==========================================================

CREATE TABLE system_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100),
    description TEXT,
    ip_address VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE system_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE,
    setting_value TEXT
);

-- ==========================================================
-- 🔔 NOTIFICATIONS
-- ==========================================================

CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150),
    message TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    type ENUM('info','warning','alert','appointment','billing') DEFAULT 'info',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- ==========================================================
-- 🧑‍💼 SEED INITIAL DATA
-- ==========================================================

-- Default user types
INSERT INTO usertypes (name) VALUES
('Admin'),
('Doctor'),
('Secretary'),
('Receptionist'),
('Patient');

-- Default admin user (password: admin123)
INSERT INTO users (usertype_id, username, password_hash, email, status)
VALUES (1, 'admin', 
'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 
'admin@clinic.local', 'active');

INSERT INTO user_profiles (user_id, first_name, last_name, gender, birth_date, address, phone)
VALUES (1, 'System', 'Administrator', 'other', '1990-01-01', 'Default Clinic HQ', '000-000-0000');

-- Clinic system info
INSERT INTO system_settings (setting_key, setting_value) VALUES
('clinic_name', 'My Clinic Management System'),
('clinic_address', '123 Main St, City'),
('clinic_contact', '+63 900 000 0000'),
('timezone', 'Asia/Manila');

-- Example notification
INSERT INTO notifications (user_id, title, message, type)
VALUES (1, 'Welcome Admin!', 'Your clinic management system is successfully installed.', 'info');
