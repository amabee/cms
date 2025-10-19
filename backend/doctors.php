<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

include('conn.php');

class Doctors
{
  private $conn;

  public function __construct()
  {
    $this->conn = DatabaseConnection::getInstance()->getConnection();
  }

  public function getAll($json)
  {
    try {
      $data = json_decode($json, true);
      $specialization = $data['specialization'] ?? null;
      $status = $data['status'] ?? null;
      $search = $data['search'] ?? null;

      $sql = "SELECT d.doctor_id, d.user_id, d.department_id, d.specialization, d.license_no, 
                     d.ptr_no, d.s2_license_no, d.years_of_experience, d.room_number, 
                     d.consultation_fee, d.schedule_days, d.schedule_start, d.schedule_end,
                     d.contact_number, d.email, d.status, d.profile_photo, d.biography,
                     d.created_at, d.updated_at,
                     u.username,
                     up.first_name, up.last_name, up.gender, up.birth_date, up.phone, up.address,
                     dep.name as department_name
              FROM doctors d
              INNER JOIN users u ON d.user_id = u.user_id
              LEFT JOIN user_profiles up ON u.user_id = up.user_id
              LEFT JOIN departments dep ON d.department_id = dep.department_id
              WHERE 1=1";

      $params = [];

      if ($specialization) {
        $sql .= " AND d.specialization = :specialization";
        $params[':specialization'] = $specialization;
      }

      if ($status) {
        $sql .= " AND d.status = :status";
        $params[':status'] = $status;
      }

      if ($search) {
        $sql .= " AND (up.first_name LIKE :search OR up.last_name LIKE :search OR d.license_no LIKE :search OR d.email LIKE :search OR d.contact_number LIKE :search)";
        $params[':search'] = "%$search%";
      }

      $sql .= " ORDER BY up.last_name, up.first_name";

      $stmt = $this->conn->prepare($sql);
      $stmt->execute($params);
      $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

      return json_encode(['success' => true, 'data' => $doctors]);
    } catch (PDOException $e) {
      return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
  }

  public function getById($json)
  {
    try {
      $data = json_decode($json, true);
      $doctor_id = $data['doctor_id'] ?? null;

      if (!$doctor_id) {
        return json_encode(['error' => 'Doctor ID is required']);
      }

      $sql = "SELECT d.*, u.username, u.email, u.status,
                     up.first_name, up.last_name, up.gender, up.birth_date, up.phone, up.address
              FROM doctors d
              INNER JOIN users u ON d.user_id = u.user_id
              LEFT JOIN user_profiles up ON u.user_id = up.user_id
              WHERE d.doctor_id = :doctor_id";

      $stmt = $this->conn->prepare($sql);
      $stmt->execute([':doctor_id' => $doctor_id]);
      $doctor = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$doctor) {
        return json_encode(['error' => 'Doctor not found']);
      }

      return json_encode(['success' => true, 'data' => $doctor]);
    } catch (PDOException $e) {
      return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
  }

  public function create($json)
  {
    $data = json_decode($json, true);

    if (empty($data['first_name']) || empty($data['last_name']) || empty($data['email'])) {
      return json_encode(['error' => 'First name, last name, and email are required']);
    }

    // Check if email already exists
    $checkStmt = $this->conn->prepare("SELECT user_id FROM users WHERE email = :email");
    $checkStmt->execute([':email' => $data['email']]);
    if ($checkStmt->rowCount() > 0) {
      return json_encode(['error' => 'Email already exists']);
    }

    $this->conn->beginTransaction();

    try {
      // Generate username from email
      $email = $data['email'];
      $local = strtolower(preg_replace('/[^a-z0-9._-]/', '', strstr($email, '@', true) ?: $email));
      $baseUsername = $local ?: 'doctor';
      $username = $baseUsername;

      // Ensure username uniqueness
      $i = 0;
      while (true) {
        $check = $this->conn->prepare('SELECT user_id FROM users WHERE username = :username');
        $check->execute([':username' => $username]);
        if ($check->rowCount() === 0) break;
        $i++;
        $username = $baseUsername . $i;
      }

      // Create user account
      $defaultPassword = 'doctor123'; // Default password, should be changed on first login
      $passwordHash = password_hash($defaultPassword, PASSWORD_BCRYPT);
      
      $stmt = $this->conn->prepare("INSERT INTO users (usertype_id, username, password_hash, email, status)
                                      VALUES (2, :username, :password, :email, :status)");
      $stmt->execute([
        ':username' => $username,
        ':password' => $passwordHash,
        ':email' => $data['email'],
        ':status' => $data['status'] ?? 'active'
      ]);

      $user_id = $this->conn->lastInsertId();

      // Create user profile
      $stmt = $this->conn->prepare("INSERT INTO user_profiles (user_id, first_name, last_name, gender, birth_date, phone, address)
                                      VALUES (:user_id, :first_name, :last_name, :gender, :birth_date, :phone, :address)");
      $stmt->execute([
        ':user_id' => $user_id,
        ':first_name' => $data['first_name'],
        ':last_name' => $data['last_name'],
        ':gender' => $data['gender'] ?? null,
        ':birth_date' => $data['birth_date'] ?? null,
        ':phone' => $data['phone'] ?? null,
        ':address' => $data['address'] ?? null
      ]);

      // Create doctor record with all new fields
      $stmt = $this->conn->prepare("INSERT INTO doctors (
                                      user_id, department_id, specialization, license_no, ptr_no, s2_license_no,
                                      years_of_experience, room_number, consultation_fee, schedule_days,
                                      schedule_start, schedule_end, contact_number, email, status, biography
                                    ) VALUES (
                                      :user_id, :department_id, :specialization, :license_no, :ptr_no, :s2_license_no,
                                      :years_of_experience, :room_number, :consultation_fee, :schedule_days,
                                      :schedule_start, :schedule_end, :contact_number, :email, :status, :biography
                                    )");
      $stmt->execute([
        ':user_id' => $user_id,
        ':department_id' => $data['department_id'] ?? null,
        ':specialization' => $data['specialization'] ?? null,
        ':license_no' => $data['license_no'] ?? null,
        ':ptr_no' => $data['ptr_no'] ?? null,
        ':s2_license_no' => $data['s2_license_no'] ?? null,
        ':years_of_experience' => $data['years_of_experience'] ?? 0,
        ':room_number' => $data['room_number'] ?? null,
        ':consultation_fee' => $data['consultation_fee'] ?? 0.00,
        ':schedule_days' => $data['schedule_days'] ?? null,
        ':schedule_start' => $data['schedule_start'] ?? null,
        ':schedule_end' => $data['schedule_end'] ?? null,
        ':contact_number' => $data['contact_number'] ?? null,
        ':email' => $data['email'] ?? null,
        ':status' => $data['status'] ?? 'active',
        ':biography' => $data['biography'] ?? null
      ]);

      $doctor_id = $this->conn->lastInsertId();

      // Log the action
      $this->logAction($data['admin_user_id'] ?? 1, 'create', "Created doctor: {$data['first_name']} {$data['last_name']}", $_SERVER['REMOTE_ADDR'] ?? 'unknown');

      $this->conn->commit();

      return json_encode(['success' => true, 'doctor_id' => $doctor_id, 'username' => $username]);
    } catch (PDOException $e) {
      $this->conn->rollBack();
      return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
  }

  public function update($json)
  {
    $data = json_decode($json, true);

    if (empty($data['doctor_id'])) {
      return json_encode(['error' => 'Doctor ID is required']);
    }

    $this->conn->beginTransaction();

    try {
      // Get user_id from doctor_id
      $stmt = $this->conn->prepare("SELECT user_id FROM doctors WHERE doctor_id = :doctor_id");
      $stmt->execute([':doctor_id' => $data['doctor_id']]);
      $result = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$result) {
        return json_encode(['error' => 'Doctor not found']);
      }

      $user_id = $result['user_id'];

      // Update user account
      $stmt = $this->conn->prepare("UPDATE users SET
                                      email = :email,
                                      updated_at = CURRENT_TIMESTAMP
                                      WHERE user_id = :user_id");
      $stmt->execute([
        ':user_id' => $user_id,
        ':email' => $data['email']
      ]);

      // Update user profile
      $stmt = $this->conn->prepare("UPDATE user_profiles SET
                                      first_name = :first_name,
                                      last_name = :last_name,
                                      gender = :gender,
                                      birth_date = :birth_date,
                                      phone = :phone,
                                      address = :address
                                      WHERE user_id = :user_id");
      $stmt->execute([
        ':user_id' => $user_id,
        ':first_name' => $data['first_name'],
        ':last_name' => $data['last_name'],
        ':gender' => $data['gender'] ?? null,
        ':birth_date' => $data['birth_date'] ?? null,
        ':phone' => $data['phone'] ?? null,
        ':address' => $data['address'] ?? null
      ]);

      // Update doctor record with all new fields
      $stmt = $this->conn->prepare("UPDATE doctors SET
                                      department_id = :department_id,
                                      specialization = :specialization,
                                      license_no = :license_no,
                                      ptr_no = :ptr_no,
                                      s2_license_no = :s2_license_no,
                                      years_of_experience = :years_of_experience,
                                      room_number = :room_number,
                                      consultation_fee = :consultation_fee,
                                      schedule_days = :schedule_days,
                                      schedule_start = :schedule_start,
                                      schedule_end = :schedule_end,
                                      contact_number = :contact_number,
                                      email = :email,
                                      status = :status,
                                      biography = :biography
                                      WHERE doctor_id = :doctor_id");
      $stmt->execute([
        ':doctor_id' => $data['doctor_id'],
        ':department_id' => $data['department_id'] ?? null,
        ':specialization' => $data['specialization'] ?? null,
        ':license_no' => $data['license_no'] ?? null,
        ':ptr_no' => $data['ptr_no'] ?? null,
        ':s2_license_no' => $data['s2_license_no'] ?? null,
        ':years_of_experience' => $data['years_of_experience'] ?? 0,
        ':room_number' => $data['room_number'] ?? null,
        ':consultation_fee' => $data['consultation_fee'] ?? 0.00,
        ':schedule_days' => $data['schedule_days'] ?? null,
        ':schedule_start' => $data['schedule_start'] ?? null,
        ':schedule_end' => $data['schedule_end'] ?? null,
        ':contact_number' => $data['contact_number'] ?? null,
        ':email' => $data['email'] ?? null,
        ':status' => $data['status'] ?? 'active',
        ':biography' => $data['biography'] ?? null
      ]);

      // Log the action
      $this->logAction($data['admin_user_id'] ?? 1, 'update', "Updated doctor ID: {$data['doctor_id']}", $_SERVER['REMOTE_ADDR'] ?? 'unknown');

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

    if (empty($data['doctor_id'])) {
      return json_encode(['error' => 'Doctor ID is required']);
    }

    try {
      $this->conn->beginTransaction();

      // Get doctor details for logging
      $stmt = $this->conn->prepare("SELECT d.user_id, up.first_name, up.last_name
                                      FROM doctors d
                                      LEFT JOIN user_profiles up ON d.user_id = up.user_id
                                      WHERE d.doctor_id = :doctor_id");
      $stmt->execute([':doctor_id' => $data['doctor_id']]);
      $doctor = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$doctor) {
        return json_encode(['error' => 'Doctor not found']);
      }

      // Delete doctor record (user will be deleted by cascade or manually)
      $stmt = $this->conn->prepare("DELETE FROM doctors WHERE doctor_id = :doctor_id");
      $stmt->execute([':doctor_id' => $data['doctor_id']]);

      // Delete user account
      $stmt = $this->conn->prepare("DELETE FROM users WHERE user_id = :user_id");
      $stmt->execute([':user_id' => $doctor['user_id']]);

      // Log the action
      $this->logAction($data['admin_user_id'] ?? 1, 'delete', "Deleted doctor: {$doctor['first_name']} {$doctor['last_name']}", $_SERVER['REMOTE_ADDR'] ?? 'unknown');

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
      // Total doctors
      $stmt = $this->conn->query("SELECT COUNT(*) as total FROM doctors");
      $total = $stmt->fetchColumn();

      // Active doctors
      $stmt = $this->conn->query("SELECT COUNT(*) as active FROM doctors d
                                   INNER JOIN users u ON d.user_id = u.user_id
                                   WHERE u.status = 'active'");
      $active = $stmt->fetchColumn();

      // Specializations count
      $stmt = $this->conn->query("SELECT COUNT(DISTINCT specialization) as specializations FROM doctors");
      $specializations = $stmt->fetchColumn();

      // On duty today (assuming we have schedules)
      $today = date('D'); // Get day abbreviation (Mon, Tue, etc.)
      $stmt = $this->conn->prepare("SELECT COUNT(DISTINCT s.doctor_id) as on_duty 
                                     FROM schedules s
                                     INNER JOIN doctors d ON s.doctor_id = d.doctor_id
                                     INNER JOIN users u ON d.user_id = u.user_id
                                     WHERE s.day_of_week = :day AND u.status = 'active'");
      $stmt->execute([':day' => $today]);
      $onDuty = $stmt->fetchColumn();

      return json_encode([
        'success' => true,
        'data' => [
          'total' => (int)$total,
          'active' => (int)$active,
          'specializations' => (int)$specializations,
          'on_duty' => (int)$onDuty
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

$doctors = new Doctors();

if (in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
  $operation = $_REQUEST['operation'] ?? '';
  $json = $_REQUEST['json'] ?? '';

  switch ($operation) {
    case 'getAll':
      echo $doctors->getAll($json);
      break;
    case 'getById':
      echo $doctors->getById($json);
      break;
    case 'create':
      echo $doctors->create($json);
      break;
    case 'update':
      echo $doctors->update($json);
      break;
    case 'delete':
      echo $doctors->delete($json);
      break;
    case 'getStatistics':
      echo $doctors->getStatistics($json);
      break;
    default:
      echo json_encode(['error' => 'Invalid Operation']);
      break;
  }
} else {
  echo json_encode(['error' => 'Invalid Request Method']);
}
?>
