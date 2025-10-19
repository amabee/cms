<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

include('conn.php');

class Staff
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
      $usertype = $data['usertype_id'] ?? null; // 3 = Secretary, 4 = Receptionist
      $status = $data['status'] ?? null;
      $search = $data['search'] ?? null;

      $sql = "SELECT u.user_id, u.usertype_id, u.username, u.email, u.status, u.created_at,
                     up.first_name, up.last_name, up.phone, up.address,
                     ut.name as usertype_name,
                     s.secretary_id, s.assigned_doctor_id,
                     r.receptionist_id,
                     dep.name as department_name
              FROM users u
              LEFT JOIN user_profiles up ON u.user_id = up.user_id
              LEFT JOIN usertypes ut ON u.usertype_id = ut.usertype_id
              LEFT JOIN secretaries s ON u.user_id = s.user_id
              LEFT JOIN receptionists r ON u.user_id = r.user_id
              LEFT JOIN doctors d ON s.assigned_doctor_id = d.doctor_id
              LEFT JOIN departments dep ON d.department_id = dep.department_id
              WHERE u.usertype_id IN (3, 4)";

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

      $sql .= " ORDER BY up.last_name, up.first_name";

      $stmt = $this->conn->prepare($sql);
      $stmt->execute($params);
      $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

      return json_encode(['success' => true, 'data' => $staff]);
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

      $sql = "SELECT u.*, up.first_name, up.last_name, up.gender, up.birth_date, up.phone, up.address,
                     s.secretary_id, s.assigned_doctor_id,
                     r.receptionist_id
              FROM users u
              LEFT JOIN user_profiles up ON u.user_id = up.user_id
              LEFT JOIN secretaries s ON u.user_id = s.user_id
              LEFT JOIN receptionists r ON u.user_id = r.user_id
              WHERE u.user_id = :user_id AND u.usertype_id IN (3, 4)";

      $stmt = $this->conn->prepare($sql);
      $stmt->execute([':user_id' => $user_id]);
      $staff = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$staff) {
        return json_encode(['error' => 'Staff member not found']);
      }

      return json_encode(['success' => true, 'data' => $staff]);
    } catch (PDOException $e) {
      return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
  }

  public function create($json)
  {
    $data = json_decode($json, true);

    if (empty($data['first_name']) || empty($data['last_name']) || empty($data['email']) || empty($data['usertype_id'])) {
      return json_encode(['error' => 'First name, last name, email, and role are required']);
    }

    if (!in_array($data['usertype_id'], [3, 4])) {
      return json_encode(['error' => 'Invalid staff role']);
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
      $baseUsername = $local ?: 'staff';
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
      $defaultPassword = 'staff123'; // Default password
      $passwordHash = password_hash($defaultPassword, PASSWORD_BCRYPT);
      
      $stmt = $this->conn->prepare("INSERT INTO users (usertype_id, username, password_hash, email, status)
                                      VALUES (:usertype_id, :username, :password, :email, :status)");
      $stmt->execute([
        ':usertype_id' => $data['usertype_id'],
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

      // Create role-specific record
      if ($data['usertype_id'] == 3) {
        // Secretary
        $stmt = $this->conn->prepare("INSERT INTO secretaries (user_id, assigned_doctor_id)
                                        VALUES (:user_id, :assigned_doctor_id)");
        $stmt->execute([
          ':user_id' => $user_id,
          ':assigned_doctor_id' => $data['assigned_doctor_id'] ?? null
        ]);
      } else {
        // Receptionist
        $stmt = $this->conn->prepare("INSERT INTO receptionists (user_id) VALUES (:user_id)");
        $stmt->execute([':user_id' => $user_id]);
      }

      // Log the action
      $role = $data['usertype_id'] == 3 ? 'Secretary' : 'Receptionist';
      $this->logAction($data['admin_user_id'] ?? 1, 'create', "Created $role: {$data['first_name']} {$data['last_name']}", $_SERVER['REMOTE_ADDR'] ?? 'unknown');

      $this->conn->commit();

      return json_encode(['success' => true, 'user_id' => $user_id, 'username' => $username]);
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
      $stmt = $this->conn->prepare("UPDATE users SET 
                                      email = :email, 
                                      status = :status,
                                      updated_at = CURRENT_TIMESTAMP
                                      WHERE user_id = :user_id");
      $stmt->execute([
        ':user_id' => $data['user_id'],
        ':email' => $data['email'],
        ':status' => $data['status'] ?? 'active'
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
        ':user_id' => $data['user_id'],
        ':first_name' => $data['first_name'],
        ':last_name' => $data['last_name'],
        ':gender' => $data['gender'] ?? null,
        ':birth_date' => $data['birth_date'] ?? null,
        ':phone' => $data['phone'] ?? null,
        ':address' => $data['address'] ?? null
      ]);

      // Update role-specific data if secretary
      if (isset($data['assigned_doctor_id'])) {
        $stmt = $this->conn->prepare("UPDATE secretaries SET assigned_doctor_id = :assigned_doctor_id WHERE user_id = :user_id");
        $stmt->execute([
          ':user_id' => $data['user_id'],
          ':assigned_doctor_id' => $data['assigned_doctor_id']
        ]);
      }

      // Log the action
      $this->logAction($data['admin_user_id'] ?? 1, 'update', "Updated staff member ID: {$data['user_id']}", $_SERVER['REMOTE_ADDR'] ?? 'unknown');

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

    try {
      $this->conn->beginTransaction();

      // Get staff details for logging
      $stmt = $this->conn->prepare("SELECT u.usertype_id, up.first_name, up.last_name
                                      FROM users u
                                      LEFT JOIN user_profiles up ON u.user_id = up.user_id
                                      WHERE u.user_id = :user_id");
      $stmt->execute([':user_id' => $data['user_id']]);
      $staff = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$staff) {
        return json_encode(['error' => 'Staff member not found']);
      }

      // Delete role-specific record
      if ($staff['usertype_id'] == 3) {
        $stmt = $this->conn->prepare("DELETE FROM secretaries WHERE user_id = :user_id");
      } else {
        $stmt = $this->conn->prepare("DELETE FROM receptionists WHERE user_id = :user_id");
      }
      $stmt->execute([':user_id' => $data['user_id']]);

      // Delete user account
      $stmt = $this->conn->prepare("DELETE FROM users WHERE user_id = :user_id");
      $stmt->execute([':user_id' => $data['user_id']]);

      // Log the action
      $role = $staff['usertype_id'] == 3 ? 'Secretary' : 'Receptionist';
      $this->logAction($data['admin_user_id'] ?? 1, 'delete', "Deleted $role: {$staff['first_name']} {$staff['last_name']}", $_SERVER['REMOTE_ADDR'] ?? 'unknown');

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
      // Total secretaries
      $stmt = $this->conn->query("SELECT COUNT(*) as total FROM secretaries");
      $secretaries = $stmt->fetchColumn();

      // Total receptionists
      $stmt = $this->conn->query("SELECT COUNT(*) as total FROM receptionists");
      $receptionists = $stmt->fetchColumn();

      // Total staff
      $total = $secretaries + $receptionists;

      return json_encode([
        'success' => true,
        'data' => [
          'secretaries' => (int)$secretaries,
          'receptionists' => (int)$receptionists,
          'total' => (int)$total
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

$staff = new Staff();

if (in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
  $operation = $_REQUEST['operation'] ?? '';
  $json = $_REQUEST['json'] ?? '';

  switch ($operation) {
    case 'getAll':
      echo $staff->getAll($json);
      break;
    case 'getById':
      echo $staff->getById($json);
      break;
    case 'create':
      echo $staff->create($json);
      break;
    case 'update':
      echo $staff->update($json);
      break;
    case 'delete':
      echo $staff->delete($json);
      break;
    case 'getStatistics':
      echo $staff->getStatistics($json);
      break;
    default:
      echo json_encode(['error' => 'Invalid Operation']);
      break;
  }
} else {
  echo json_encode(['error' => 'Invalid Request Method']);
}
?>
