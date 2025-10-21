<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

include('conn.php');

class Users
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
      $usertype = $data['usertype_id'] ?? null;
      $status = $data['status'] ?? null;
      $search = $data['search'] ?? null;

      $sql = "SELECT u.user_id, u.usertype_id, u.username, u.email, u.status, u.created_at,
                     up.first_name, up.last_name, up.phone, up.address,
                     ut.name as usertype_name,
                     d.specialization, d.department_id, dept.name as department_name
              FROM users u
              LEFT JOIN user_profiles up ON u.user_id = up.user_id
              LEFT JOIN usertypes ut ON u.usertype_id = ut.usertype_id
              LEFT JOIN doctors d ON u.user_id = d.user_id
              LEFT JOIN departments dept ON d.department_id = dept.department_id
              WHERE 1=1";

      $params = [];

      if ($usertype) {
        $sql .= " AND u.usertype_id = :usertype_id";
        $params[':usertype_id'] = $usertype;
      }

      if ($status) {
        $sql .= " AND u.status = :status";
        $params[':status'] = $status;
      }

      if ($search) {
        $sql .= " AND (u.username LIKE :search OR u.email LIKE :search OR up.first_name LIKE :search OR up.last_name LIKE :search)";
        $params[':search'] = "%$search%";
      }

      $sql .= " ORDER BY u.created_at DESC";

      $stmt = $this->conn->prepare($sql);
      $stmt->execute($params);
      $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

      return json_encode(['success' => true, 'data' => $users]);
    } catch (PDOException $e) {
      return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
  }

  public function getById($json)
  {
    try {
      $data = json_decode($json, true);
      $user_id = $data['user_id'] ?? null;

      if (!$user_id) {
        return json_encode(['error' => 'User ID is required']);
      }

      $sql = "SELECT u.*, up.first_name, up.last_name, up.gender, up.birth_date, up.phone, up.address
              FROM users u
              LEFT JOIN user_profiles up ON u.user_id = up.user_id
              WHERE u.user_id = :user_id";

      $stmt = $this->conn->prepare($sql);
      $stmt->execute([':user_id' => $user_id]);
      $user = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$user) {
        return json_encode(['error' => 'User not found']);
      }

      return json_encode(['success' => true, 'data' => $user]);
    } catch (PDOException $e) {
      return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
  }

  public function create($json)
  {
    $data = json_decode($json, true);

    if (empty($data['username']) || empty($data['password']) || empty($data['usertype_id'])) {
      return json_encode(['error' => 'Username, password, and user type are required']);
    }

    // Check if username already exists
    $checkStmt = $this->conn->prepare("SELECT user_id FROM users WHERE username = :username");
    $checkStmt->execute([':username' => $data['username']]);
    if ($checkStmt->rowCount() > 0) {
      return json_encode(['error' => 'Username already exists']);
    }

    // Check if email already exists
    if (!empty($data['email'])) {
      $checkStmt = $this->conn->prepare("SELECT user_id FROM users WHERE email = :email");
      $checkStmt->execute([':email' => $data['email']]);
      if ($checkStmt->rowCount() > 0) {
        return json_encode(['error' => 'Email already exists']);
      }
    }

    $this->conn->beginTransaction();

    try {
      // Insert user
      $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);
      $stmt = $this->conn->prepare("INSERT INTO users (usertype_id, username, password_hash, email, status)
                                      VALUES (:usertype_id, :username, :password, :email, :status)");
      $stmt->execute([
        ':usertype_id' => $data['usertype_id'],
        ':username' => $data['username'],
        ':password' => $passwordHash,
        ':email' => $data['email'] ?? null,
        ':status' => $data['status'] ?? 'active'
      ]);

      $user_id = $this->conn->lastInsertId();

      // Insert user profile
      $stmt = $this->conn->prepare("INSERT INTO user_profiles (user_id, first_name, last_name, gender, birth_date, phone, address)
                                      VALUES (:user_id, :first_name, :last_name, :gender, :birth_date, :phone, :address)");
      $stmt->execute([
        ':user_id' => $user_id,
        ':first_name' => $data['first_name'] ?? null,
        ':last_name' => $data['last_name'] ?? null,
        ':gender' => $data['gender'] ?? null,
        ':birth_date' => $data['birth_date'] ?? null,
        ':phone' => $data['phone'] ?? null,
        ':address' => $data['address'] ?? null
      ]);

      // Create role-specific records
      $this->createRoleSpecificRecord($user_id, $data['usertype_id'], $data);

      // Log the action
      $this->logAction($data['admin_user_id'] ?? 1, 'create', "Created user: {$data['username']}", $_SERVER['REMOTE_ADDR'] ?? 'unknown');

      $this->conn->commit();

      return json_encode(['success' => true, 'user_id' => $user_id]);
    } catch (PDOException $e) {
      $this->conn->rollBack();
      return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
  }

  public function update($json)
  {
    $data = json_decode($json, true);

    if (empty($data['user_id'])) {
      return json_encode(['error' => 'User ID is required']);
    }

    $this->conn->beginTransaction();

    try {
      // Update user table
      $sql = "UPDATE users SET 
              email = :email, 
              status = :status,
              updated_at = CURRENT_TIMESTAMP
              WHERE user_id = :user_id";

      $params = [
        ':user_id' => $data['user_id'],
        ':email' => $data['email'] ?? null,
        ':status' => $data['status'] ?? 'active'
      ];

      // Update password if provided
      if (!empty($data['password'])) {
        $sql = "UPDATE users SET 
                email = :email, 
                password_hash = :password,
                status = :status,
                updated_at = CURRENT_TIMESTAMP
                WHERE user_id = :user_id";
        $params[':password'] = password_hash($data['password'], PASSWORD_BCRYPT);
      }

      $stmt = $this->conn->prepare($sql);
      $stmt->execute($params);

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
        ':user_id' => $data['user_id'],
        ':first_name' => $data['first_name'] ?? null,
        ':last_name' => $data['last_name'] ?? null,
        ':gender' => $data['gender'] ?? null,
        ':birth_date' => $data['birth_date'] ?? null,
        ':phone' => $data['phone'] ?? null,
        ':address' => $data['address'] ?? null
      ]);

      // Log the action
      $this->logAction($data['admin_user_id'] ?? 1, 'update', "Updated user ID: {$data['user_id']}", $_SERVER['REMOTE_ADDR'] ?? 'unknown');

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

    if (empty($data['user_id'])) {
      return json_encode(['error' => 'User ID is required']);
    }

    // Prevent deleting the admin user (user_id = 1)
    if ($data['user_id'] == 1) {
      return json_encode(['error' => 'Cannot delete the system administrator']);
    }

    try {
      $this->conn->beginTransaction();

      // Get username before deletion for logging
      $stmt = $this->conn->prepare("SELECT username FROM users WHERE user_id = :user_id");
      $stmt->execute([':user_id' => $data['user_id']]);
      $username = $stmt->fetchColumn();

      // Soft delete: Set status to 'inactive' instead of deleting the record
      $stmt = $this->conn->prepare("UPDATE users SET status = 'inactive' WHERE user_id = :user_id");
      $stmt->execute([':user_id' => $data['user_id']]);

      // Log the action
      $this->logAction($data['admin_user_id'] ?? 1, 'delete', "Deactivated user: $username", $_SERVER['REMOTE_ADDR'] ?? 'unknown');

      $this->conn->commit();

      return json_encode(['success' => true]);
    } catch (PDOException $e) {
      $this->conn->rollBack();
      return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
  }

  private function createRoleSpecificRecord($user_id, $usertype_id, $data)
  {
    switch ($usertype_id) {
      case 2: // Doctor
        if (isset($data['specialization']) || isset($data['license_no'])) {
          $stmt = $this->conn->prepare("INSERT INTO doctors (user_id, specialization, license_no, department_id)
                                          VALUES (:user_id, :specialization, :license_no, :department_id)");
          $stmt->execute([
            ':user_id' => $user_id,
            ':specialization' => $data['specialization'] ?? null,
            ':license_no' => $data['license_no'] ?? null,
            ':department_id' => $data['department_id'] ?? null
          ]);
        }
        break;

      case 3: // Secretary
        $stmt = $this->conn->prepare("INSERT INTO secretaries (user_id, assigned_doctor_id)
                                        VALUES (:user_id, :assigned_doctor_id)");
        $stmt->execute([
          ':user_id' => $user_id,
          ':assigned_doctor_id' => $data['assigned_doctor_id'] ?? null
        ]);
        break;

      case 4: // Receptionist
        $stmt = $this->conn->prepare("INSERT INTO receptionists (user_id) VALUES (:user_id)");
        $stmt->execute([':user_id' => $user_id]);
        break;

      case 5: // Patient
        $patient_code = 'P' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $stmt = $this->conn->prepare("INSERT INTO patients (user_id, patient_code, blood_type, emergency_contact)
                                        VALUES (:user_id, :patient_code, :blood_type, :emergency_contact)");
        $stmt->execute([
          ':user_id' => $user_id,
          ':patient_code' => $patient_code,
          ':blood_type' => $data['blood_type'] ?? null,
          ':emergency_contact' => $data['emergency_contact'] ?? null
        ]);
        break;
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
      // Log silently fails, don't interrupt main operation
    }
  }
}

$users = new Users();

if (in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
  $operation = $_REQUEST['operation'] ?? '';
  $json = $_REQUEST['json'] ?? '';

  switch ($operation) {
    case 'getAll':
      echo $users->getAll($json);
      break;
    case 'getById':
      echo $users->getById($json);
      break;
    case 'create':
      echo $users->create($json);
      break;
    case 'update':
      echo $users->update($json);
      break;
    case 'delete':
      echo $users->delete($json);
      break;
    default:
      echo json_encode(['error' => 'Invalid Operation']);
      break;
  }
} else {
  echo json_encode(['error' => 'Invalid Request Method']);
}
?>
