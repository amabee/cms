<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

include('conn.php');

class LabRequestsAPI
{
  private $conn;

  public function __construct()
  {
    $this->conn = DatabaseConnection::getInstance()->getConnection();
  }

  public function getAll($json)
  {
    $data = json_decode($json, true);
    $search = $data['search'] ?? '';
    $status = $data['status'] ?? 'all';
    $payment_status = $data['payment_status'] ?? 'all';
    $priority = $data['priority'] ?? 'all';
    $date_from = $data['date_from'] ?? '';
    $date_to = $data['date_to'] ?? '';
    $page = $data['page'] ?? 1;
    $limit = $data['limit'] ?? 10;
    $offset = ($page - 1) * $limit;

    try {
      // Build query with joins
      $sql = "SELECT lr.*, 
              CONCAT(p.first_name, ' ', p.last_name) as patient_name,
              p.patient_code,
              CONCAT(COALESCE(dp.first_name, ''), ' ', COALESCE(dp.last_name, '')) as doctor_name,
              d.specialization,
              CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, '')) as requested_by_name
              FROM lab_requests lr
              INNER JOIN patients p ON lr.patient_id = p.patient_id
              INNER JOIN doctors d ON lr.doctor_id = d.doctor_id
              LEFT JOIN users du ON d.user_id = du.user_id
              LEFT JOIN user_profiles dp ON du.user_id = dp.user_id
              LEFT JOIN users ru ON lr.requested_by = ru.user_id
              LEFT JOIN user_profiles up ON ru.user_id = up.user_id
              WHERE 1=1";
      
      $params = [];

      if (!empty($search)) {
        $sql .= " AND (lr.request_no LIKE :search OR p.first_name LIKE :search OR p.last_name LIKE :search OR p.patient_code LIKE :search)";
        $params[':search'] = "%$search%";
      }

      if ($status !== 'all') {
        $sql .= " AND lr.status = :status";
        $params[':status'] = $status;
      }

      if ($payment_status !== 'all') {
        $sql .= " AND lr.payment_status = :payment_status";
        $params[':payment_status'] = $payment_status;
      }

      if ($priority !== 'all') {
        $sql .= " AND lr.priority = :priority";
        $params[':priority'] = $priority;
      }

      if (!empty($date_from)) {
        $sql .= " AND DATE(lr.date_requested) >= :date_from";
        $params[':date_from'] = $date_from;
      }

      if (!empty($date_to)) {
        $sql .= " AND DATE(lr.date_requested) <= :date_to";
        $params[':date_to'] = $date_to;
      }

      // Get total count
      $countSql = "SELECT COUNT(*) as total FROM lab_requests lr
                   INNER JOIN patients p ON lr.patient_id = p.patient_id
                   WHERE 1=1";
      
      if (!empty($search)) {
        $countSql .= " AND (lr.request_no LIKE :search OR p.first_name LIKE :search OR p.last_name LIKE :search OR p.patient_code LIKE :search)";
      }
      if ($status !== 'all') {
        $countSql .= " AND lr.status = :status";
      }
      if ($payment_status !== 'all') {
        $countSql .= " AND lr.payment_status = :payment_status";
      }
      if ($priority !== 'all') {
        $countSql .= " AND lr.priority = :priority";
      }
      if (!empty($date_from)) {
        $countSql .= " AND DATE(lr.date_requested) >= :date_from";
      }
      if (!empty($date_to)) {
        $countSql .= " AND DATE(lr.date_requested) <= :date_to";
      }

      $countStmt = $this->conn->prepare($countSql);
      foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
      }
      $countStmt->execute();
      $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

      // Get paginated results
      $sql .= " ORDER BY lr.date_requested DESC LIMIT :limit OFFSET :offset";
      $stmt = $this->conn->prepare($sql);
      
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
      $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
      $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
      
      $stmt->execute();
      $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

      // Get items for each request
      foreach ($requests as &$request) {
        $itemsStmt = $this->conn->prepare("SELECT lri.*, lt.test_name, lt.test_code, lt.price
          FROM lab_request_items lri
          INNER JOIN lab_tests lt ON lri.lab_test_id = lt.lab_test_id
          WHERE lri.lab_request_id = :lab_request_id
          ORDER BY lt.test_name");
        $itemsStmt->execute([':lab_request_id' => $request['lab_request_id']]);
        $request['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
      }

      return json_encode([
        'success' => true,
        'data' => $requests,
        'total' => (int)$total,
        'page' => (int)$page,
        'limit' => (int)$limit
      ]);

    } catch (PDOException $e) {
      return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
  }

  public function getById($json)
  {
    $data = json_decode($json, true);
    $lab_request_id = $data['lab_request_id'] ?? 0;

    if (empty($lab_request_id)) {
      return json_encode(['error' => 'Lab request ID is required']);
    }

    try {
      $stmt = $this->conn->prepare("SELECT lr.*, 
        CONCAT(p.first_name, ' ', p.last_name) as patient_name,
        p.patient_code, p.date_of_birth, p.gender,
        CONCAT(COALESCE(dp.first_name, ''), ' ', COALESCE(dp.last_name, '')) as doctor_name,
        d.specialization,
        CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, '')) as requested_by_name
        FROM lab_requests lr
        INNER JOIN patients p ON lr.patient_id = p.patient_id
        INNER JOIN doctors d ON lr.doctor_id = d.doctor_id
        LEFT JOIN users du ON d.user_id = du.user_id
        LEFT JOIN user_profiles dp ON du.user_id = dp.user_id
        LEFT JOIN users ru ON lr.requested_by = ru.user_id
        LEFT JOIN user_profiles up ON ru.user_id = up.user_id
        WHERE lr.lab_request_id = :lab_request_id");
      
      $stmt->execute([':lab_request_id' => $lab_request_id]);
      $request = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($request) {
        // Get items
        $itemsStmt = $this->conn->prepare("SELECT lri.*, lt.test_name, lt.test_code, lt.price, lt.unit, lt.normal_range,
          CONCAT(COALESCE(cp.first_name, ''), ' ', COALESCE(cp.last_name, '')) as collected_by_name,
          CONCAT(COALESCE(vp.first_name, ''), ' ', COALESCE(vp.last_name, '')) as verified_by_name
          FROM lab_request_items lri
          INNER JOIN lab_tests lt ON lri.lab_test_id = lt.lab_test_id
          LEFT JOIN users cu ON lri.collected_by = cu.user_id
          LEFT JOIN user_profiles cp ON cu.user_id = cp.user_id
          LEFT JOIN users vu ON lri.verified_by = vu.user_id
          LEFT JOIN user_profiles vp ON vu.user_id = vp.user_id
          WHERE lri.lab_request_id = :lab_request_id
          ORDER BY lt.test_name");
        $itemsStmt->execute([':lab_request_id' => $lab_request_id]);
        $request['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        return json_encode(['success' => true, 'data' => $request]);
      } else {
        return json_encode(['error' => 'Lab request not found']);
      }

    } catch (PDOException $e) {
      return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
  }

  public function create($json)
  {
    $data = json_decode($json, true);
    $admin_user_id = $data['admin_user_id'] ?? 1;

    // Required fields
    $patient_id = $data['patient_id'] ?? 0;
    $doctor_id = $data['doctor_id'] ?? 0;
    $priority = $data['priority'] ?? 'normal';
    $payment_status = $data['payment_status'] ?? 'unpaid';
    $remarks = $data['remarks'] ?? '';
    $tests = $data['tests'] ?? []; // Array of lab_test_ids

    // Validation
    if (empty($patient_id) || empty($doctor_id)) {
      return json_encode(['error' => 'Patient and doctor are required']);
    }

    if (empty($tests)) {
      return json_encode(['error' => 'At least one test is required']);
    }

    try {
      $this->conn->beginTransaction();

      // Generate request number
      $request_no = $this->generateRequestNumber();

      // Calculate total cost
      $total_cost = 0;
      $testPlaceholders = implode(',', array_fill(0, count($tests), '?'));
      $costStmt = $this->conn->prepare("SELECT SUM(price) as total FROM lab_tests WHERE lab_test_id IN ($testPlaceholders)");
      $costStmt->execute($tests);
      $costResult = $costStmt->fetch(PDO::FETCH_ASSOC);
      $total_cost = $costResult['total'] ?? 0;

      // Insert lab request
      $stmt = $this->conn->prepare("INSERT INTO lab_requests 
        (request_no, patient_id, doctor_id, requested_by, priority, status, payment_status, total_cost, remarks)
        VALUES (:request_no, :patient_id, :doctor_id, :requested_by, :priority, 'requested', :payment_status, :total_cost, :remarks)");

      $stmt->execute([
        ':request_no' => $request_no,
        ':patient_id' => $patient_id,
        ':doctor_id' => $doctor_id,
        ':requested_by' => $admin_user_id,
        ':priority' => $priority,
        ':payment_status' => $payment_status,
        ':total_cost' => $total_cost,
        ':remarks' => $remarks
      ]);

      $lab_request_id = $this->conn->lastInsertId();

      // Insert lab request items
      $itemStmt = $this->conn->prepare("INSERT INTO lab_request_items 
        (lab_request_id, lab_test_id, status) 
        VALUES (:lab_request_id, :lab_test_id, 'pending')");

      foreach ($tests as $test_id) {
        $itemStmt->execute([
          ':lab_request_id' => $lab_request_id,
          ':lab_test_id' => $test_id
        ]);
      }

      // Get patient name for logging
      $patientStmt = $this->conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM patients WHERE patient_id = :patient_id");
      $patientStmt->execute([':patient_id' => $patient_id]);
      $patient = $patientStmt->fetch(PDO::FETCH_ASSOC);

      $this->conn->commit();

      // Log the action
      $this->logAction($admin_user_id, 'create', "Created lab request {$request_no} for patient: {$patient['name']} (" . count($tests) . " tests)", $_SERVER['REMOTE_ADDR'] ?? 'unknown');

      return json_encode([
        'success' => true,
        'message' => 'Lab request created successfully',
        'lab_request_id' => $lab_request_id,
        'request_no' => $request_no
      ]);

    } catch (PDOException $e) {
      $this->conn->rollBack();
      return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
  }

  public function update($json)
  {
    $data = json_decode($json, true);
    $admin_user_id = $data['admin_user_id'] ?? 1;
    $lab_request_id = $data['lab_request_id'] ?? 0;

    if (empty($lab_request_id)) {
      return json_encode(['error' => 'Lab request ID is required']);
    }

    try {
      // Check if lab request exists
      $checkStmt = $this->conn->prepare("SELECT request_no FROM lab_requests WHERE lab_request_id = :lab_request_id");
      $checkStmt->execute([':lab_request_id' => $lab_request_id]);
      $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
      
      if (!$existing) {
        return json_encode(['error' => 'Lab request not found']);
      }

      $priority = $data['priority'] ?? 'normal';
      $status = $data['status'] ?? 'requested';
      $payment_status = $data['payment_status'] ?? 'unpaid';
      $remarks = $data['remarks'] ?? '';

      // Update lab request
      $stmt = $this->conn->prepare("UPDATE lab_requests SET 
        priority = :priority,
        status = :status,
        payment_status = :payment_status,
        remarks = :remarks,
        date_completed = :date_completed
        WHERE lab_request_id = :lab_request_id");

      $date_completed = ($status === 'completed') ? date('Y-m-d H:i:s') : null;

      $stmt->execute([
        ':priority' => $priority,
        ':status' => $status,
        ':payment_status' => $payment_status,
        ':remarks' => $remarks,
        ':date_completed' => $date_completed,
        ':lab_request_id' => $lab_request_id
      ]);

      // Log the action
      $this->logAction($admin_user_id, 'update', "Updated lab request: {$existing['request_no']} (Status: {$status})", $_SERVER['REMOTE_ADDR'] ?? 'unknown');

      return json_encode([
        'success' => true,
        'message' => 'Lab request updated successfully'
      ]);

    } catch (PDOException $e) {
      return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
  }

  public function updateItem($json)
  {
    $data = json_decode($json, true);
    $admin_user_id = $data['admin_user_id'] ?? 1;
    $lab_request_item_id = $data['lab_request_item_id'] ?? 0;

    if (empty($lab_request_item_id)) {
      return json_encode(['error' => 'Lab request item ID is required']);
    }

    try {
      $result = $data['result'] ?? '';
      $status = $data['status'] ?? 'pending';
      $collected_by = $data['collected_by'] ?? null;
      $verified_by = $data['verified_by'] ?? null;

      $date_collected = null;
      $date_verified = null;

      if ($status === 'in-progress' && empty($collected_by)) {
        $collected_by = $admin_user_id;
        $date_collected = date('Y-m-d H:i:s');
      } elseif (!empty($collected_by) && empty($date_collected)) {
        $date_collected = date('Y-m-d H:i:s');
      }

      if ($status === 'verified' && empty($verified_by)) {
        $verified_by = $admin_user_id;
        $date_verified = date('Y-m-d H:i:s');
      } elseif (!empty($verified_by) && empty($date_verified)) {
        $date_verified = date('Y-m-d H:i:s');
      }

      // Update item
      $stmt = $this->conn->prepare("UPDATE lab_request_items SET 
        result = :result,
        status = :status,
        collected_by = :collected_by,
        verified_by = :verified_by,
        date_collected = :date_collected,
        date_verified = :date_verified
        WHERE lab_request_item_id = :lab_request_item_id");

      $stmt->execute([
        ':result' => $result,
        ':status' => $status,
        ':collected_by' => $collected_by,
        ':verified_by' => $verified_by,
        ':date_collected' => $date_collected,
        ':date_verified' => $date_verified,
        ':lab_request_item_id' => $lab_request_item_id
      ]);

      // Get test name for logging
      $testStmt = $this->conn->prepare("SELECT lt.test_name, lr.request_no 
        FROM lab_request_items lri
        INNER JOIN lab_tests lt ON lri.lab_test_id = lt.lab_test_id
        INNER JOIN lab_requests lr ON lri.lab_request_id = lr.lab_request_id
        WHERE lri.lab_request_item_id = :lab_request_item_id");
      $testStmt->execute([':lab_request_item_id' => $lab_request_item_id]);
      $test = $testStmt->fetch(PDO::FETCH_ASSOC);

      // Log the action
      $this->logAction($admin_user_id, 'update', "Updated lab test result: {$test['test_name']} in request {$test['request_no']} (Status: {$status})", $_SERVER['REMOTE_ADDR'] ?? 'unknown');

      return json_encode([
        'success' => true,
        'message' => 'Lab test result updated successfully'
      ]);

    } catch (PDOException $e) {
      return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
  }

  public function delete($json)
  {
    $data = json_decode($json, true);
    $admin_user_id = $data['admin_user_id'] ?? 1;
    $lab_request_id = $data['lab_request_id'] ?? 0;

    if (empty($lab_request_id)) {
      return json_encode(['error' => 'Lab request ID is required']);
    }

    try {
      // Check if lab request exists
      $checkStmt = $this->conn->prepare("SELECT request_no, status FROM lab_requests WHERE lab_request_id = :lab_request_id");
      $checkStmt->execute([':lab_request_id' => $lab_request_id]);
      $request = $checkStmt->fetch(PDO::FETCH_ASSOC);
      
      if (!$request) {
        return json_encode(['error' => 'Lab request not found']);
      }

      // Prevent deletion of completed requests
      if ($request['status'] === 'completed') {
        return json_encode(['error' => 'Cannot delete completed lab requests']);
      }

      // Delete lab request (cascade will delete items)
      $stmt = $this->conn->prepare("DELETE FROM lab_requests WHERE lab_request_id = :lab_request_id");
      $stmt->execute([':lab_request_id' => $lab_request_id]);

      // Log the action
      $this->logAction($admin_user_id, 'delete', "Deleted lab request: {$request['request_no']}", $_SERVER['REMOTE_ADDR'] ?? 'unknown');

      return json_encode([
        'success' => true,
        'message' => 'Lab request deleted successfully'
      ]);

    } catch (PDOException $e) {
      return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
  }

  public function getStatistics($json)
  {
    try {
      // Total requests
      $totalStmt = $this->conn->query("SELECT COUNT(*) as total FROM lab_requests");
      $total = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];

      // Today's requests
      $todayStmt = $this->conn->query("SELECT COUNT(*) as today FROM lab_requests WHERE DATE(date_requested) = CURDATE()");
      $today = $todayStmt->fetch(PDO::FETCH_ASSOC)['today'];

      // Pending requests
      $pendingStmt = $this->conn->query("SELECT COUNT(*) as pending FROM lab_requests WHERE status = 'requested'");
      $pending = $pendingStmt->fetch(PDO::FETCH_ASSOC)['pending'];

      // In progress
      $inProgressStmt = $this->conn->query("SELECT COUNT(*) as inprogress FROM lab_requests WHERE status = 'in-progress'");
      $inProgress = $inProgressStmt->fetch(PDO::FETCH_ASSOC)['inprogress'];

      // Completed
      $completedStmt = $this->conn->query("SELECT COUNT(*) as completed FROM lab_requests WHERE status = 'completed'");
      $completed = $completedStmt->fetch(PDO::FETCH_ASSOC)['completed'];

      // Unpaid
      $unpaidStmt = $this->conn->query("SELECT COUNT(*) as unpaid FROM lab_requests WHERE payment_status = 'unpaid'");
      $unpaid = $unpaidStmt->fetch(PDO::FETCH_ASSOC)['unpaid'];

      // Urgent
      $urgentStmt = $this->conn->query("SELECT COUNT(*) as urgent FROM lab_requests WHERE priority = 'urgent' AND status != 'completed'");
      $urgent = $urgentStmt->fetch(PDO::FETCH_ASSOC)['urgent'];

      return json_encode([
        'success' => true,
        'data' => [
          'total' => (int)$total,
          'today' => (int)$today,
          'pending' => (int)$pending,
          'in_progress' => (int)$inProgress,
          'completed' => (int)$completed,
          'unpaid' => (int)$unpaid,
          'urgent' => (int)$urgent
        ]
      ]);

    } catch (PDOException $e) {
      return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
  }

  private function generateRequestNumber()
  {
    $prefix = 'LAB';
    $date = date('Ymd');
    
    // Get the last request number for today
    $stmt = $this->conn->prepare("SELECT request_no FROM lab_requests 
      WHERE request_no LIKE :pattern 
      ORDER BY lab_request_id DESC LIMIT 1");
    $stmt->execute([':pattern' => "{$prefix}-{$date}-%"]);
    $last = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($last) {
      // Extract sequence number and increment
      $parts = explode('-', $last['request_no']);
      $sequence = (int)end($parts) + 1;
    } else {
      $sequence = 1;
    }

    return sprintf("%s-%s-%04d", $prefix, $date, $sequence);
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

$api = new LabRequestsAPI();

if (in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
  $operation = $_REQUEST['operation'] ?? '';
  $json = $_REQUEST['json'] ?? '';

  switch ($operation) {
    case 'getAll':
      echo $api->getAll($json);
      break;
    case 'getById':
      echo $api->getById($json);
      break;
    case 'create':
      echo $api->create($json);
      break;
    case 'update':
      echo $api->update($json);
      break;
    case 'updateItem':
      echo $api->updateItem($json);
      break;
    case 'delete':
      echo $api->delete($json);
      break;
    case 'getStatistics':
      echo $api->getStatistics($json);
      break;
    default:
      echo json_encode(['error' => 'Invalid Operation']);
      break;
  }
} else {
  echo json_encode(['error' => 'Invalid Request Method']);
}
?>
