<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

include('conn.php');

class AdminProfile
{
  private $conn;

  public function __construct()
  {
    $this->conn = DatabaseConnection::getInstance()->getConnection();
  }

  public function getProfile($json)
  {
    try {
      $data = json_decode($json, true);
      $user_id = $data['user_id'] ?? null;

      if (!$user_id) {
        return json_encode(['error' => 'User ID is required']);
      }

      $sql = "SELECT u.user_id, u.usertype_id, u.username, u.email, u.status, u.created_at,
                     up.first_name, up.last_name, up.gender, up.birth_date, up.phone, up.address,
                     ut.name as usertype_name
              FROM users u
              LEFT JOIN user_profiles up ON u.user_id = up.user_id
              LEFT JOIN usertypes ut ON u.usertype_id = ut.usertype_id
              WHERE u.user_id = :user_id";

      $stmt = $this->conn->prepare($sql);
      $stmt->execute([':user_id' => $user_id]);
      $profile = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$profile) {
        return json_encode(['error' => 'User not found']);
      }

      return json_encode(['success' => true, 'data' => $profile]);
    } catch (PDOException $e) {
      return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
  }

  public function updateProfile($json)
  {
    $data = json_decode($json, true);

    if (empty($data['user_id'])) {
      return json_encode(['error' => 'User ID is required']);
    }

    $this->conn->beginTransaction();

    try {
      // Update user email
      $stmt = $this->conn->prepare("UPDATE users SET 
                                      email = :email,
                                      updated_at = CURRENT_TIMESTAMP
                                      WHERE user_id = :user_id");
      $stmt->execute([
        ':user_id' => $data['user_id'],
        ':email' => $data['email']
      ]);

      // Update or insert user profile
      $checkStmt = $this->conn->prepare("SELECT profile_id FROM user_profiles WHERE user_id = :user_id");
      $checkStmt->execute([':user_id' => $data['user_id']]);

      if ($checkStmt->rowCount() > 0) {
        // Update existing profile
        $stmt = $this->conn->prepare("UPDATE user_profiles SET
                                        first_name = :first_name,
                                        last_name = :last_name,
                                        gender = :gender,
                                        birth_date = :birth_date,
                                        phone = :phone,
                                        address = :address
                                        WHERE user_id = :user_id");
      } else {
        // Insert new profile
        $stmt = $this->conn->prepare("INSERT INTO user_profiles 
                                        (user_id, first_name, last_name, gender, birth_date, phone, address)
                                        VALUES (:user_id, :first_name, :last_name, :gender, :birth_date, :phone, :address)");
      }

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
      $this->logAction($data['user_id'], 'update', "Updated profile information", $_SERVER['REMOTE_ADDR'] ?? 'unknown');

      $this->conn->commit();

      return json_encode(['success' => true]);
    } catch (PDOException $e) {
      $this->conn->rollBack();
      return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
  }

  public function changePassword($json)
  {
    $data = json_decode($json, true);

    if (empty($data['user_id']) || empty($data['current_password']) || empty($data['new_password'])) {
      return json_encode(['error' => 'User ID, current password, and new password are required']);
    }

    try {
      // Verify current password
      $stmt = $this->conn->prepare("SELECT password_hash FROM users WHERE user_id = :user_id");
      $stmt->execute([':user_id' => $data['user_id']]);
      $user = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$user) {
        return json_encode(['error' => 'User not found']);
      }

      if (!password_verify($data['current_password'], $user['password_hash'])) {
        return json_encode(['error' => 'Current password is incorrect']);
      }

      // Update password
      $newPasswordHash = password_hash($data['new_password'], PASSWORD_BCRYPT);
      $stmt = $this->conn->prepare("UPDATE users SET 
                                      password_hash = :password,
                                      updated_at = CURRENT_TIMESTAMP
                                      WHERE user_id = :user_id");
      $stmt->execute([
        ':user_id' => $data['user_id'],
        ':password' => $newPasswordHash
      ]);

      // Log the action
      $this->logAction($data['user_id'], 'security', "Changed password", $_SERVER['REMOTE_ADDR'] ?? 'unknown');

      return json_encode(['success' => true, 'message' => 'Password updated successfully']);
    } catch (PDOException $e) {
      return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
  }

  public function uploadPhoto($json)
  {
    // This would handle file uploads - implementation depends on how you want to store photos
    // For now, return a placeholder response
    return json_encode(['success' => false, 'message' => 'Photo upload not implemented yet']);
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

$profile = new AdminProfile();

if (in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
  $operation = $_REQUEST['operation'] ?? '';
  $json = $_REQUEST['json'] ?? '';

  switch ($operation) {
    case 'getProfile':
      echo $profile->getProfile($json);
      break;
    case 'updateProfile':
      echo $profile->updateProfile($json);
      break;
    case 'changePassword':
      echo $profile->changePassword($json);
      break;
    case 'uploadPhoto':
      echo $profile->uploadPhoto($json);
      break;
    default:
      echo json_encode(['error' => 'Invalid Operation']);
      break;
  }
} else {
  echo json_encode(['error' => 'Invalid Request Method']);
}
?>
