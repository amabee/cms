<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

include('conn.php');

class AUTH
{
  private $conn;

  public function __construct()
  {
    $this->conn = DatabaseConnection::getInstance()->getConnection();
  }

  public function login($json)
  {
    $json = json_decode($json, true);

    try {
      if (empty($json['username']) || empty($json['password'])) {
        return json_encode(['error' => 'Username or Password required!']);
      }

      $username = $json['username'];
      $password = $json['password'];

  // select all columns to be resilient to different column naming (user_id vs id)
  $sql = "SELECT * FROM users WHERE (username = :username OR email = :username) LIMIT 1";

  $stmt = $this->conn->prepare($sql);
  $stmt->bindParam(':username', $username, PDO::PARAM_STR);
  $stmt->execute();

      if ($stmt->rowCount() === 0) {
        return json_encode(['error' => 'Invalid Credentials']);
      }

      $row = $stmt->fetch(PDO::FETCH_ASSOC);

      // detect status column (defaults to 'status' in schema)
      $status = $row['status'] ?? null;
      if ($status !== null && $status !== 'active') {
        return json_encode(['error' => 'Account is not active']);
      }
      // detect password column (password_hash per schema, fallback to password)
      $hashCol = isset($row['password_hash']) ? 'password_hash' : (isset($row['password']) ? 'password' : null);
      if ($hashCol === null || !password_verify($password, $row[$hashCol])) {
        return json_encode(['error' => 'Invalid Credentials']);
      }

      // determine user id field name (user_id per schema, fallback to id)
      $uid = $row['user_id'] ?? $row['id'] ?? null;
      if ($uid === null) {
        return json_encode(['error' => 'Invalid user identifier in database']);
      }

      $profileSql = "SELECT first_name, last_name, gender, birth_date, address, phone 
                           FROM user_profiles WHERE user_id = :user_id LIMIT 1";
      $pstmt = $this->conn->prepare($profileSql);
      $pstmt->bindParam(':user_id', $uid, PDO::PARAM_INT);
      $pstmt->execute();
      $profile = $pstmt->fetch(PDO::FETCH_ASSOC) ?: new stdClass();

      $extraData = new stdClass();

  $usertype = $row['usertype_id'] ?? $row['type'] ?? null;
  switch ($usertype) {
        case 2:
          $extraStmt = $this->conn->prepare("SELECT doctor_id, specialization, license_no, department_id 
                                                       FROM doctors WHERE user_id = :uid LIMIT 1");
          $extraStmt->execute([':uid' => $uid]);
          $extraData = $extraStmt->fetch(PDO::FETCH_ASSOC) ?: new stdClass();
          break;

        case 3:
          $extraStmt = $this->conn->prepare("SELECT secretary_id, assigned_doctor_id 
                                                       FROM secretaries WHERE user_id = :uid LIMIT 1");
          $extraStmt->execute([':uid' => $uid]);
          $extraData = $extraStmt->fetch(PDO::FETCH_ASSOC) ?: new stdClass();
          break;

        case 4:
          $extraStmt = $this->conn->prepare("SELECT receptionist_id 
                                                       FROM receptionists WHERE user_id = :uid LIMIT 1");
          $extraStmt->execute([':uid' => $uid]);
          $extraData = $extraStmt->fetch(PDO::FETCH_ASSOC) ?: new stdClass();
          break;

        case 5:
          $extraStmt = $this->conn->prepare("SELECT patient_id, patient_code, blood_type, emergency_contact 
                                                       FROM patients WHERE user_id = :uid LIMIT 1");
          $extraStmt->execute([':uid' => $uid]);
          $extraData = $extraStmt->fetch(PDO::FETCH_ASSOC) ?: new stdClass();
          break;

        default:
          break;
      }

      $user = [
        'user_id' => $uid,
        'usertype_id' => $usertype,
        'username' => $row['username'] ?? $row['user'] ?? null,
        'email' => $row['email'] ?? null,
        'profile' => $profile,
        'extra' => $extraData
      ];

      // Optionally fetch unread notifications
      $notifSql = "SELECT notification_id, title, message, type, is_read, created_at 
                         FROM notifications 
                         WHERE user_id = :uid AND is_read = 0";
  $notifStmt = $this->conn->prepare($notifSql);
  $notifStmt->execute([':uid' => $uid]);
      $notifications = $notifStmt->fetchAll(PDO::FETCH_ASSOC);

      $response = [
        'success' => true,
        'user' => $user,
        'notifications' => $notifications
      ];

      return json_encode($response);
    } catch (PDOException $e) {
      return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
  }

  public function signup($json)
  {
    return json_encode(['error' => 'Signup not implemented']);
  }
}

$auth = new AUTH();

if (in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
  $operation = $_REQUEST['operation'] ?? '';
  $json = $_REQUEST['json'] ?? '';

  switch ($operation) {
    case 'login':
      echo $auth->login($json);
      break;
    case 'signup':
      echo $auth->signup($json);
      break;
    default:
      echo json_encode(['error' => 'Invalid Operation']);
      break;
  }
} else {
  echo json_encode(['error' => 'Invalid Request Method']);
}
?>

