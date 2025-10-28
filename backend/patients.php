<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

include('conn.php');
include('email-service.php');

class Patients
{
  private $conn;

  public function __construct()
  {
    $this->conn = DatabaseConnection::getInstance()->getConnection();
  }

  public function registerPatient($json)
  {
    $data = json_decode($json, true);

    if (empty($data['first_name']) || empty($data['last_name']) || empty($data['date_of_birth'])) {
      return json_encode(['error' => 'First name, last name, and date of birth are required']);
    }

    $this->conn->beginTransaction();

    try {
      $user_id = null;
      $create_account = isset($data['create_account']) && $data['create_account'] == 1;

      // If create_account is true, create a user account
      if ($create_account) {
        if (empty($data['email'])) {
          $this->conn->rollBack();
          return json_encode(['error' => 'Email is required when creating an account']);
        }

        // Generate username from email local part
        $email = $data['email'];
        $local = strtolower(preg_replace('/[^a-z0-9._-]/', '', strstr($email, '@', true) ?: $email));
        $baseUsername = $local ?: 'patient';
        $username = $baseUsername;

        // Ensure uniqueness by appending number suffix if needed
        $i = 0;
        while (true) {
          $check = $this->conn->prepare('SELECT user_id FROM users WHERE username = :username LIMIT 1');
          $check->execute([':username' => $username]);
          if ($check->rowCount() === 0) break;
          $i++;
          $username = $baseUsername . $i;
        }

        // Default password for new patient accounts
        $passwordHash = password_hash('patient123', PASSWORD_BCRYPT);
        $stmt = $this->conn->prepare("INSERT INTO users (usertype_id, username, password_hash, email, status) 
                                          VALUES (5, :username, :password, :email, 'active')");
        $stmt->execute([
          ':username' => $username,
          ':password' => $passwordHash,
          ':email' => $data['email']
        ]);
        $user_id = $this->conn->lastInsertId();

        // Create user profile
        $stmt = $this->conn->prepare("INSERT INTO user_profiles (user_id, first_name, last_name, gender, birth_date, address, phone)
                                          VALUES (:user_id, :first_name, :last_name, :gender, :birth_date, :address, :phone)");
        $stmt->execute([
          ':user_id' => $user_id,
          ':first_name' => $data['first_name'],
          ':last_name' => $data['last_name'],
          ':gender' => $data['gender'] ?? 'Other',
          ':birth_date' => $data['date_of_birth'],
          ':address' => $data['address'] ?? null,
          ':phone' => $data['phone_number'] ?? null
        ]);
      }

      // Generate unique patient code
      $patient_code = 'P' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
      
      // Check if patient code already exists, regenerate if needed
      do {
        $check = $this->conn->prepare('SELECT patient_id FROM patients WHERE patient_code = :code LIMIT 1');
        $check->execute([':code' => $patient_code]);
        if ($check->rowCount() === 0) break;
        $patient_code = 'P' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
      } while (true);

      // Insert patient record with all new fields
      $stmt = $this->conn->prepare("INSERT INTO patients 
        (user_id, patient_code, first_name, last_name, middle_name, date_of_birth, gender, 
         phone_number, email, address, emergency_contact_name, emergency_contact_phone, 
         blood_type, allergies, existing_conditions, insurance_id, notes, is_active)
        VALUES 
        (:user_id, :patient_code, :first_name, :last_name, :middle_name, :date_of_birth, :gender,
         :phone_number, :email, :address, :emergency_contact_name, :emergency_contact_phone,
         :blood_type, :allergies, :existing_conditions, :insurance_id, :notes, 1)");
      
      $stmt->execute([
        ':user_id' => $user_id,
        ':patient_code' => $patient_code,
        ':first_name' => $data['first_name'],
        ':last_name' => $data['last_name'],
        ':middle_name' => $data['middle_name'] ?? null,
        ':date_of_birth' => $data['date_of_birth'],
        ':gender' => $data['gender'] ?? 'Other',
        ':phone_number' => $data['phone_number'] ?? null,
        ':email' => $data['email'] ?? null,
        ':address' => $data['address'] ?? null,
        ':emergency_contact_name' => $data['emergency_contact_name'] ?? null,
        ':emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
        ':blood_type' => $data['blood_type'] ?? null,
        ':allergies' => $data['allergies'] ?? null,
        ':existing_conditions' => $data['existing_conditions'] ?? null,
        ':insurance_id' => $data['insurance_id'] ?? null,
        ':notes' => $data['notes'] ?? null
      ]);

      $patient_id = $this->conn->lastInsertId();

      // Log the action
      if (!empty($data['admin_user_id'])) {
        $logStmt = $this->conn->prepare("INSERT INTO system_logs (user_id, action, description, ip_address) 
                                          VALUES (:user_id, 'register_patient', :description, :ip)");
        $logStmt->execute([
          ':user_id' => $data['admin_user_id'],
          ':description' => "Registered patient: {$data['first_name']} {$data['last_name']} (Code: {$patient_code})",
          ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
        ]);
      }

      $this->conn->commit();

      // Send welcome email if account was created and email provided
      $emailSent = false;
      if ($create_account && !empty($data['email'])) {
        try {
          $emailData = array_merge($data, [
            'username' => $username,
            'patient_id' => $patient_id,
            'patient_code' => $patient_code
          ]);
          $emailSent = EmailService::sendPatientRegistrationEmail($emailData);
        } catch (Exception $e) {
          // Log email error but don't fail the registration
          error_log("Failed to send welcome email to {$data['email']}: " . $e->getMessage());
        }
      }

      return json_encode([
        'success' => true, 
        'patient_code' => $patient_code,
        'patient_id' => $patient_id,
        'username' => $create_account ? $username : null,
        'email_sent' => $emailSent
      ]);
    } catch (PDOException $e) {
      $this->conn->rollBack();
      return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
  }

  public function getAll($json)
  {
    try {
      $data = json_decode($json, true);
      $blood_type = $data['blood_type'] ?? null;
      $search = $data['search'] ?? null;
      $status = $data['status'] ?? null;

      $sql = "SELECT p.patient_id, p.user_id, p.patient_code, 
                     p.first_name, p.last_name, p.middle_name,
                     p.date_of_birth, p.gender, 
                     p.phone_number, p.email, p.address,
                     p.emergency_contact_name, p.emergency_contact_phone,
                     p.blood_type, p.allergies, p.existing_conditions,
                     p.date_registered, p.last_visit, p.notes, p.is_active,
                     i.company_name as insurance_company, i.policy_no as insurance_policy,
                     u.username, u.status as account_status,
                     (SELECT MAX(a.appointment_date) FROM appointments a WHERE a.patient_id = p.patient_id) as last_visit_date
              FROM patients p
              LEFT JOIN users u ON p.user_id = u.user_id
              LEFT JOIN insurance i ON p.insurance_id = i.insurance_id
              WHERE 1=1";

      $params = [];

      if ($blood_type) {
        $sql .= " AND p.blood_type = :blood_type";
        $params[':blood_type'] = $blood_type;
      }

      if ($search) {
        $sql .= " AND (p.first_name LIKE :search OR p.last_name LIKE :search OR p.middle_name LIKE :search 
                      OR p.patient_code LIKE :search OR p.email LIKE :search OR p.phone_number LIKE :search)";
        $params[':search'] = "%$search%";
      }

      if ($status !== null) {
        $sql .= " AND p.is_active = :status";
        $params[':status'] = $status;
      }

      $sql .= " ORDER BY p.last_name, p.first_name";

      $stmt = $this->conn->prepare($sql);
      $stmt->execute($params);
      $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

      // Get last visit for each patient
      foreach ($patients as &$patient) {
        $stmt = $this->conn->prepare("SELECT MAX(appointment_date) as last_visit 
                                       FROM appointments 
                                       WHERE patient_id = :patient_id AND status = 'completed'");
        $stmt->execute([':patient_id' => $patient['patient_id']]);
        $patient['last_visit'] = $stmt->fetchColumn();
      }

      return json_encode(['success' => true, 'data' => $patients]);
    } catch (PDOException $e) {
      return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
  }

  public function getById($json)
  {
    try {
      $data = json_decode($json, true);
      $patient_id = $data['patient_id'] ?? null;

      if (!$patient_id) {
        return json_encode(['error' => 'Patient ID is required']);
      }

      // Convert user_id to patient_id if needed
      $patientCheckSql = "SELECT patient_id FROM patients WHERE user_id = :user_id";
      $patientCheckStmt = $this->conn->prepare($patientCheckSql);
      $patientCheckStmt->execute([':user_id' => $patient_id]);
      $patientRecord = $patientCheckStmt->fetch(PDO::FETCH_ASSOC);
      
      if ($patientRecord) {
        // It's a user_id, convert to patient_id
        $patient_id = $patientRecord['patient_id'];
      }

      $sql = "SELECT p.*, 
                     i.company_name as insurance_company, i.policy_no as insurance_policy,
                     u.username, u.email as user_email, u.status as account_status,
                     (SELECT MAX(a.appointment_date) FROM appointments a WHERE a.patient_id = p.patient_id) as last_visit_date
              FROM patients p
              LEFT JOIN users u ON p.user_id = u.user_id
              LEFT JOIN insurance i ON p.insurance_id = i.insurance_id
              WHERE p.patient_id = :patient_id";

      $stmt = $this->conn->prepare($sql);
      $stmt->execute([':patient_id' => $patient_id]);
      $patient = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$patient) {
        return json_encode(['error' => 'Patient not found']);
      }

      return json_encode(['success' => true, 'data' => $patient]);
    } catch (PDOException $e) {
      return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
  }

  public function update($json)
  {
    $data = json_decode($json, true);

    if (empty($data['patient_id'])) {
      return json_encode(['error' => 'Patient ID is required']);
    }

    // Convert user_id to patient_id if needed
    $patientCheckSql = "SELECT patient_id, user_id FROM patients WHERE user_id = :user_id";
    $patientCheckStmt = $this->conn->prepare($patientCheckSql);
    $patientCheckStmt->execute([':user_id' => $data['patient_id']]);
    $patientRecord = $patientCheckStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($patientRecord) {
      // It's a user_id, convert to patient_id
      $data['patient_id'] = $patientRecord['patient_id'];
    }

    $this->conn->beginTransaction();

    try {
      // Get current patient data first
      $stmt = $this->conn->prepare("SELECT * FROM patients WHERE patient_id = :patient_id");
      $stmt->execute([':patient_id' => $data['patient_id']]);
      $currentPatient = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$currentPatient) {
        return json_encode(['error' => 'Patient not found']);
      }

      $user_id = $currentPatient['user_id'];

      // Build dynamic update query for patients table
      $updateFields = [];
      $updateParams = [':patient_id' => $data['patient_id']];

      // Only update fields that are provided
      $patientFields = [
        'first_name', 'last_name', 'middle_name', 'date_of_birth', 'gender',
        'phone_number', 'email', 'address', 'emergency_contact_name',
        'emergency_contact_phone', 'blood_type', 'allergies', 
        'existing_conditions', 'notes', 'is_active'
      ];

      foreach ($patientFields as $field) {
        if (isset($data[$field])) {
          $updateFields[] = "$field = :$field";
          $updateParams[":$field"] = $data[$field];
        }
      }

      // Update patient record if there are fields to update
      if (!empty($updateFields)) {
        $sql = "UPDATE patients SET " . implode(', ', $updateFields) . " WHERE patient_id = :patient_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($updateParams);
      }

      // Update user account and profile if user exists and relevant data is provided
      if ($user_id) {
        // Update email in users table
        if (isset($data['email'])) {
          $stmt = $this->conn->prepare("UPDATE users SET email = :email WHERE user_id = :user_id");
          $stmt->execute([
            ':user_id' => $user_id,
            ':email' => $data['email']
          ]);
        }

        // Build dynamic update for user_profiles
        $profileUpdateFields = [];
        $profileParams = [':user_id' => $user_id];

        $profileFieldMap = [
          'first_name' => 'first_name',
          'last_name' => 'last_name',
          'gender' => 'gender',
          'date_of_birth' => 'birth_date',
          'phone_number' => 'phone',
          'address' => 'address'
        ];

        foreach ($profileFieldMap as $dataKey => $dbKey) {
          if (isset($data[$dataKey])) {
            $profileUpdateFields[] = "$dbKey = :$dbKey";
            $profileParams[":$dbKey"] = $data[$dataKey];
          }
        }

        // Update user profile if there are fields to update
        if (!empty($profileUpdateFields)) {
          $sql = "UPDATE user_profiles SET " . implode(', ', $profileUpdateFields) . " WHERE user_id = :user_id";
          $stmt = $this->conn->prepare($sql);
          $stmt->execute($profileParams);
        }
      }

      // Log the action
      if (!empty($data['admin_user_id'])) {
        $logStmt = $this->conn->prepare("INSERT INTO system_logs (user_id, action, description, ip_address) 
                                          VALUES (:user_id, 'update_patient', :description, :ip)");
        $logStmt->execute([
          ':user_id' => $data['admin_user_id'],
          ':description' => "Updated patient: {$data['first_name']} {$data['last_name']} (ID: {$data['patient_id']})",
          ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
        ]);
      }

      $this->conn->commit();

      return json_encode(['success' => true]);
    } catch (PDOException $e) {
      $this->conn->rollBack();
      return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
  }

  public function delete($json)
  {
    $data = json_decode($json, true);

    if (empty($data['patient_id'])) {
      return json_encode(['error' => 'Patient ID is required']);
    }

    try {
      $this->conn->beginTransaction();

      // Get patient details
      $stmt = $this->conn->prepare("SELECT p.user_id, p.patient_code, p.first_name, p.last_name
                                      FROM patients p
                                      WHERE p.patient_id = :patient_id");
      $stmt->execute([':patient_id' => $data['patient_id']]);
      $patient = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$patient) {
        return json_encode(['error' => 'Patient not found']);
      }

      // Delete patient record
      $stmt = $this->conn->prepare("DELETE FROM patients WHERE patient_id = :patient_id");
      $stmt->execute([':patient_id' => $data['patient_id']]);

      // Delete user if exists
      if ($patient['user_id']) {
        $stmt = $this->conn->prepare("DELETE FROM users WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $patient['user_id']]);
      }

      // Log the action
      if (!empty($data['admin_user_id'])) {
        $logStmt = $this->conn->prepare("INSERT INTO system_logs (user_id, action, description, ip_address) 
                                          VALUES (:user_id, 'delete_patient', :description, :ip)");
        $logStmt->execute([
          ':user_id' => $data['admin_user_id'],
          ':description' => "Deleted patient: {$patient['first_name']} {$patient['last_name']} (Code: {$patient['patient_code']})",
          ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
        ]);
      }

      $this->conn->commit();

      return json_encode(['success' => true]);
    } catch (PDOException $e) {
      $this->conn->rollBack();
      return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
  }

  public function getStatistics($json)
  {
    try {
      // Total patients
      $stmt = $this->conn->query("SELECT COUNT(*) as total FROM patients");
      $total = $stmt->fetchColumn();

      // Active patients (is_active = 1)
      $stmt = $this->conn->query("SELECT COUNT(*) as active FROM patients WHERE is_active = 1");
      $active = $stmt->fetchColumn();

      // New patients this month
      $stmt = $this->conn->query("SELECT COUNT(*) as new_patients FROM patients
                                   WHERE MONTH(date_registered) = MONTH(CURRENT_DATE)
                                   AND YEAR(date_registered) = YEAR(CURRENT_DATE)");
      $newPatients = $stmt->fetchColumn();

      // Appointments today
      $stmt = $this->conn->query("SELECT COUNT(*) as today_appointments FROM appointments
                                   WHERE DATE(appointment_date) = CURDATE()");
      $todayAppointments = $stmt->fetchColumn();

      return json_encode([
        'success' => true,
        'data' => [
          'total' => (int)$total,
          'active' => (int)$active,
          'new_patients' => (int)$newPatients,
          'today_appointments' => (int)$todayAppointments
        ]
      ]);
    } catch (PDOException $e) {
      return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
  }

  private function logAction($user_id, $action, $description, $ip_address)
  {
    try {
      $stmt = $this->conn->prepare("INSERT INTO system_logs (user_id, action, description, ip_address)
                                      VALUES (:user_id, :action, :description, :ip_address)");
      $stmt->execute([
        ':user_id' => $user_id,
        ':action' => $action,
        ':description' => $description,
        ':ip_address' => $ip_address
      ]);
    } catch (PDOException $e) {
      // Log silently fails
    }
  }
}

$patients = new Patients();

if (in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
  $operation = $_REQUEST['operation'] ?? '';
  $json = $_REQUEST['json'] ?? '';

  switch ($operation) {
    case 'register':
      echo $patients->registerPatient($json);
      break;
    case 'getAll':
      echo $patients->getAll($json);
      break;
    case 'getById':
      echo $patients->getById($json);
      break;
    case 'update':
      echo $patients->update($json);
      break;
    case 'delete':
      echo $patients->delete($json);
      break;
    case 'getStatistics':
      echo $patients->getStatistics($json);
      break;
    default:
      echo json_encode(['error' => 'Invalid Operation']);
      break;
  }
} else {
  echo json_encode(['error' => 'Invalid Request Method']);
}
?>

