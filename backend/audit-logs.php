<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

include('conn.php');

class AuditLogs
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
      $user_id = $data['user_id'] ?? null;
      $action = $data['action'] ?? null;
      $from_date = $data['from_date'] ?? null;
      $to_date = $data['to_date'] ?? null;
      $search = $data['search'] ?? null;
      $limit = $data['limit'] ?? 100;
      $offset = $data['offset'] ?? 0;

      $sql = "SELECT sl.log_id, sl.user_id, sl.action, sl.description, sl.ip_address, sl.created_at,
                     u.username,
                     up.first_name, up.last_name
              FROM system_logs sl
              LEFT JOIN users u ON sl.user_id = u.user_id
              LEFT JOIN user_profiles up ON u.user_id = up.user_id
              WHERE 1=1";

      $params = [];

      if ($user_id) {
        $sql .= " AND sl.user_id = :user_id";
        $params[':user_id'] = $user_id;
      }

      if ($action) {
        $sql .= " AND sl.action LIKE :action";
        $params[':action'] = "%$action%";
      }

      if ($from_date) {
        $sql .= " AND DATE(sl.created_at) >= :from_date";
        $params[':from_date'] = $from_date;
      }

      if ($to_date) {
        $sql .= " AND DATE(sl.created_at) <= :to_date";
        $params[':to_date'] = $to_date;
      }

      if ($search) {
        $sql .= " AND (sl.description LIKE :search OR sl.action LIKE :search OR u.username LIKE :search OR up.first_name LIKE :search OR up.last_name LIKE :search)";
        $params[':search'] = "%$search%";
      }

      $sql .= " ORDER BY sl.created_at DESC LIMIT :limit OFFSET :offset";

      $stmt = $this->conn->prepare($sql);
      
      // Bind parameters
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
      $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
      $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
      
      $stmt->execute();
      $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

      // Get total count
      $countSql = "SELECT COUNT(*) as total FROM system_logs sl
                   LEFT JOIN users u ON sl.user_id = u.user_id
                   LEFT JOIN user_profiles up ON u.user_id = up.user_id
                   WHERE 1=1";
      
      if ($user_id) $countSql .= " AND sl.user_id = :user_id";
      if ($action) $countSql .= " AND sl.action LIKE :action";
      if ($from_date) $countSql .= " AND DATE(sl.created_at) >= :from_date";
      if ($to_date) $countSql .= " AND DATE(sl.created_at) <= :to_date";
      if ($search) $countSql .= " AND (sl.description LIKE :search OR sl.action LIKE :search OR u.username LIKE :search OR up.first_name LIKE :search OR up.last_name LIKE :search)";

      $countStmt = $this->conn->prepare($countSql);
      foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
      }
      $countStmt->execute();
      $total = $countStmt->fetchColumn();

      return json_encode([
        'success' => true,
        'data' => $logs,
        'total' => (int)$total,
        'limit' => (int)$limit,
        'offset' => (int)$offset
      ]);
    } catch (PDOException $e) {
      return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
  }

  public function getStatistics($json)
  {
    try {
      // Total logs
      $stmt = $this->conn->query("SELECT COUNT(*) as total FROM system_logs");
      $total = $stmt->fetchColumn();

      // Today's logs
      $stmt = $this->conn->query("SELECT COUNT(*) as today FROM system_logs WHERE DATE(created_at) = CURDATE()");
      $today = $stmt->fetchColumn();

      // Active users (users who performed actions today)
      $stmt = $this->conn->query("SELECT COUNT(DISTINCT user_id) as active FROM system_logs WHERE DATE(created_at) = CURDATE()");
      $activeUsers = $stmt->fetchColumn();

      // Failed actions (you can customize this based on your action naming)
      $stmt = $this->conn->query("SELECT COUNT(*) as failed FROM system_logs 
                                   WHERE action LIKE '%failed%' OR action LIKE '%error%' 
                                   OR description LIKE '%failed%' OR description LIKE '%error%'");
      $failed = $stmt->fetchColumn();

      return json_encode([
        'success' => true,
        'data' => [
          'total' => (int)$total,
          'today' => (int)$today,
          'active_users' => (int)$activeUsers,
          'failed' => (int)$failed
        ]
      ]);
    } catch (PDOException $e) {
      return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
  }

  public function getUsers($json)
  {
    try {
      // Get distinct users who have logged actions
      $stmt = $this->conn->query("SELECT DISTINCT u.user_id, u.username, 
                                   CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, '')) as full_name,
                                   up.last_name, up.first_name
                                   FROM system_logs sl
                                   INNER JOIN users u ON sl.user_id = u.user_id
                                   LEFT JOIN user_profiles up ON u.user_id = up.user_id
                                   ORDER BY up.last_name, up.first_name");
      $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

      return json_encode(['success' => true, 'data' => $users]);
    } catch (PDOException $e) {
      return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
  }

  public function clearOldLogs($json)
  {
    $data = json_decode($json, true);
    $days = $data['days'] ?? 90; // Default to 90 days

    try {
      $stmt = $this->conn->prepare("DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)");
      $stmt->execute([':days' => $days]);
      $deleted = $stmt->rowCount();

      // Log this action
      $this->logAction($data['admin_user_id'] ?? 1, 'maintenance', "Cleared logs older than $days days ($deleted records)", $_SERVER['REMOTE_ADDR'] ?? 'unknown');

      return json_encode(['success' => true, 'deleted' => $deleted]);
    } catch (PDOException $e) {
      return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
  }

  public function export($json)
  {
    try {
      $data = json_decode($json, true);
      
      // Get logs with same filters as getAll
      $logs = json_decode($this->getAll($json), true);
      
      if (isset($logs['success']) && $logs['success']) {
        // Log the export action
        $this->logAction($data['admin_user_id'] ?? 1, 'export', "Exported audit logs ({$logs['total']} records)", $_SERVER['REMOTE_ADDR'] ?? 'unknown');
        
        return json_encode([
          'success' => true,
          'data' => $logs['data'],
          'message' => 'Logs exported successfully'
        ]);
      }
      
      return json_encode($logs);
    } catch (Exception $e) {
      return json_encode(['error' => 'Export Error: ' . $e->getMessage()]);
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

$auditLogs = new AuditLogs();

if (in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
  $operation = $_REQUEST['operation'] ?? '';
  $json = $_REQUEST['json'] ?? '';

  switch ($operation) {
    case 'getAll':
      echo $auditLogs->getAll($json);
      break;
    case 'getStatistics':
      echo $auditLogs->getStatistics($json);
      break;
    case 'getUsers':
      echo $auditLogs->getUsers($json);
      break;
    case 'clearOldLogs':
      echo $auditLogs->clearOldLogs($json);
      break;
    case 'export':
      echo $auditLogs->export($json);
      break;
    default:
      echo json_encode(['error' => 'Invalid Operation']);
      break;
  }
} else {
  echo json_encode(['error' => 'Invalid Request Method']);
}
?>
