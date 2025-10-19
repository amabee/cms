<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

include('conn.php');

class Dashboard
{
  private $conn;

  public function __construct()
  {
    $this->conn = DatabaseConnection::getInstance()->getConnection();
  }

  public function getStatistics($json)
  {
    try {
      $data = json_decode($json, true);
      $usertype_id = $data['usertype_id'] ?? null;

      $stats = [];

      // Common statistics for all users
      $stats['system'] = $this->getSystemStats();

      // Role-specific statistics
      switch ($usertype_id) {
        case 1: // Admin
          $stats['users'] = $this->getUserStats();
          $stats['doctors'] = $this->getDoctorStats();
          $stats['patients'] = $this->getPatientStats();
          $stats['staff'] = $this->getStaffStats();
          $stats['appointments'] = $this->getAppointmentStats();
          $stats['recent_activities'] = $this->getRecentActivities();
          break;

        case 2: // Doctor
          $stats['my_appointments'] = $this->getDoctorAppointments($data['user_id'] ?? null);
          $stats['my_patients'] = $this->getDoctorPatients($data['user_id'] ?? null);
          break;

        case 3: // Secretary
        case 4: // Receptionist
          $stats['appointments'] = $this->getAppointmentStats();
          $stats['patients'] = $this->getPatientStats();
          break;

        case 5: // Patient
          $stats['my_appointments'] = $this->getPatientAppointments($data['user_id'] ?? null);
          break;
      }

      return json_encode(['success' => true, 'data' => $stats]);
    } catch (PDOException $e) {
      return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
  }

  private function getSystemStats()
  {
    $stmt = $this->conn->query("SELECT 
                                  (SELECT COUNT(*) FROM users WHERE status = 'active') as active_users,
                                  (SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = CURDATE()) as today_appointments");
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  private function getUserStats()
  {
    $stmt = $this->conn->query("SELECT COUNT(*) as total FROM users");
    $total = $stmt->fetchColumn();

    $stmt = $this->conn->query("SELECT COUNT(*) as active FROM users WHERE status = 'active'");
    $active = $stmt->fetchColumn();

    return ['total' => (int)$total, 'active' => (int)$active];
  }

  private function getDoctorStats()
  {
    $stmt = $this->conn->query("SELECT COUNT(*) as total FROM doctors");
    $total = $stmt->fetchColumn();

    $stmt = $this->conn->query("SELECT COUNT(*) as active FROM doctors d
                                 INNER JOIN users u ON d.user_id = u.user_id
                                 WHERE u.status = 'active'");
    $active = $stmt->fetchColumn();

    return ['total' => (int)$total, 'active' => (int)$active];
  }

  private function getPatientStats()
  {
    $stmt = $this->conn->query("SELECT COUNT(*) as total FROM patients");
    $total = $stmt->fetchColumn();

    $stmt = $this->conn->query("SELECT COUNT(*) as new_this_month FROM patients p
                                 LEFT JOIN users u ON p.user_id = u.user_id
                                 WHERE MONTH(COALESCE(u.created_at, CURRENT_DATE)) = MONTH(CURRENT_DATE)
                                 AND YEAR(COALESCE(u.created_at, CURRENT_DATE)) = YEAR(CURRENT_DATE)");
    $newThisMonth = $stmt->fetchColumn();

    return ['total' => (int)$total, 'new_this_month' => (int)$newThisMonth];
  }

  private function getStaffStats()
  {
    $stmt = $this->conn->query("SELECT COUNT(*) as secretaries FROM secretaries");
    $secretaries = $stmt->fetchColumn();

    $stmt = $this->conn->query("SELECT COUNT(*) as receptionists FROM receptionists");
    $receptionists = $stmt->fetchColumn();

    return [
      'secretaries' => (int)$secretaries,
      'receptionists' => (int)$receptionists,
      'total' => (int)($secretaries + $receptionists)
    ];
  }

  private function getAppointmentStats()
  {
    $stmt = $this->conn->query("SELECT 
                                  COUNT(*) as total,
                                  SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                                  SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                                  SUM(CASE WHEN DATE(appointment_date) = CURDATE() THEN 1 ELSE 0 END) as today
                                 FROM appointments");
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  private function getRecentActivities($limit = 10)
  {
    $stmt = $this->conn->prepare("SELECT sl.log_id, sl.action, sl.description, sl.created_at,
                                    up.first_name, up.last_name, u.username
                                   FROM system_logs sl
                                   LEFT JOIN users u ON sl.user_id = u.user_id
                                   LEFT JOIN user_profiles up ON u.user_id = up.user_id
                                   ORDER BY sl.created_at DESC
                                   LIMIT :limit");
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  private function getDoctorAppointments($user_id)
  {
    if (!$user_id) return [];

    // Get doctor_id from user_id
    $stmt = $this->conn->prepare("SELECT doctor_id FROM doctors WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $doctor_id = $stmt->fetchColumn();

    if (!$doctor_id) return [];

    $stmt = $this->conn->prepare("SELECT 
                                    COUNT(*) as total,
                                    SUM(CASE WHEN DATE(appointment_date) = CURDATE() THEN 1 ELSE 0 END) as today,
                                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
                                   FROM appointments
                                   WHERE doctor_id = :doctor_id");
    $stmt->execute([':doctor_id' => $doctor_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }

  private function getDoctorPatients($user_id)
  {
    if (!$user_id) return [];

    // Get doctor_id from user_id
    $stmt = $this->conn->prepare("SELECT doctor_id FROM doctors WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $doctor_id = $stmt->fetchColumn();

    if (!$doctor_id) return [];

    $stmt = $this->conn->prepare("SELECT COUNT(DISTINCT patient_id) as total
                                   FROM appointments
                                   WHERE doctor_id = :doctor_id");
    $stmt->execute([':doctor_id' => $doctor_id]);
    return ['total' => (int)$stmt->fetchColumn()];
  }

  private function getPatientAppointments($user_id)
  {
    if (!$user_id) return [];

    // Get patient_id from user_id
    $stmt = $this->conn->prepare("SELECT patient_id FROM patients WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $patient_id = $stmt->fetchColumn();

    if (!$patient_id) return [];

    $stmt = $this->conn->prepare("SELECT 
                                    COUNT(*) as total,
                                    SUM(CASE WHEN DATE(appointment_date) >= CURDATE() THEN 1 ELSE 0 END) as upcoming,
                                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                                   FROM appointments
                                   WHERE patient_id = :patient_id");
    $stmt->execute([':patient_id' => $patient_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }
}

$dashboard = new Dashboard();

if (in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
  $operation = $_REQUEST['operation'] ?? 'getStatistics';
  $json = $_REQUEST['json'] ?? '';

  switch ($operation) {
    case 'getStatistics':
      echo $dashboard->getStatistics($json);
      break;
    default:
      echo json_encode(['error' => 'Invalid Operation']);
      break;
  }
} else {
  echo json_encode(['error' => 'Invalid Request Method']);
}
?>
