<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

include('conn.php');

class LabTestsAPI
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
    $status = $data['status'] ?? 'all'; // all, active, inactive
    $page = $data['page'] ?? 1;
    $limit = $data['limit'] ?? 10;
    $offset = ($page - 1) * $limit;

    try {
      // Build query
      $sql = "SELECT * FROM lab_tests WHERE 1=1";
      $params = [];

      if (!empty($search)) {
        $sql .= " AND (test_code LIKE :search OR test_name LIKE :search OR description LIKE :search)";
        $params[':search'] = "%$search%";
      }

      if ($status === 'active') {
        $sql .= " AND active = 1";
      } elseif ($status === 'inactive') {
        $sql .= " AND active = 0";
      }

      // Get total count
      $countSql = "SELECT COUNT(*) as total FROM lab_tests WHERE 1=1";
      if (!empty($search)) {
        $countSql .= " AND (test_code LIKE :search OR test_name LIKE :search OR description LIKE :search)";
      }
      if ($status === 'active') {
        $countSql .= " AND active = 1";
      } elseif ($status === 'inactive') {
        $countSql .= " AND active = 0";
      }

      $countStmt = $this->conn->prepare($countSql);
      if (!empty($search)) {
        $countStmt->bindValue(':search', "%$search%");
      }
      $countStmt->execute();
      $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

      // Get paginated results
      $sql .= " ORDER BY test_name ASC LIMIT :limit OFFSET :offset";
      $stmt = $this->conn->prepare($sql);
      
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
      $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
      $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
      
      $stmt->execute();
      $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

      return json_encode([
        'success' => true,
        'data' => $tests,
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
    $lab_test_id = $data['lab_test_id'] ?? 0;

    if (empty($lab_test_id)) {
      return json_encode(['error' => 'Lab test ID is required']);
    }

    try {
      $stmt = $this->conn->prepare("SELECT * FROM lab_tests WHERE lab_test_id = :lab_test_id");
      $stmt->execute([':lab_test_id' => $lab_test_id]);
      $test = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($test) {
        return json_encode(['success' => true, 'data' => $test]);
      } else {
        return json_encode(['error' => 'Lab test not found']);
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
    $test_code = $data['test_code'] ?? '';
    $test_name = $data['test_name'] ?? '';
    $description = $data['description'] ?? '';
    $sample_type = $data['sample_type'] ?? '';
    $normal_range = $data['normal_range'] ?? '';
    $unit = $data['unit'] ?? '';
    $price = $data['price'] ?? 0;
    $active = $data['active'] ?? 1;

    // Validation
    if (empty($test_code) || empty($test_name)) {
      return json_encode(['error' => 'Test code and name are required']);
    }

    try {
      // Check if test code already exists
      $checkStmt = $this->conn->prepare("SELECT lab_test_id FROM lab_tests WHERE test_code = :test_code");
      $checkStmt->execute([':test_code' => $test_code]);
      if ($checkStmt->fetch()) {
        return json_encode(['error' => 'Test code already exists']);
      }

      // Insert lab test
      $stmt = $this->conn->prepare("INSERT INTO lab_tests 
        (test_code, test_name, description, sample_type, normal_range, unit, price, active)
        VALUES (:test_code, :test_name, :description, :sample_type, :normal_range, :unit, :price, :active)");

      $stmt->execute([
        ':test_code' => $test_code,
        ':test_name' => $test_name,
        ':description' => $description,
        ':sample_type' => $sample_type,
        ':normal_range' => $normal_range,
        ':unit' => $unit,
        ':price' => $price,
        ':active' => $active
      ]);

      $lab_test_id = $this->conn->lastInsertId();

      // Log the action
      $this->logAction($admin_user_id, 'create', "Created lab test: {$test_name} ({$test_code})", $_SERVER['REMOTE_ADDR'] ?? 'unknown');

      return json_encode([
        'success' => true,
        'message' => 'Lab test created successfully',
        'lab_test_id' => $lab_test_id
      ]);

    } catch (PDOException $e) {
      return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
  }

  public function update($json)
  {
    $data = json_decode($json, true);
    $admin_user_id = $data['admin_user_id'] ?? 1;
    $lab_test_id = $data['lab_test_id'] ?? 0;

    if (empty($lab_test_id)) {
      return json_encode(['error' => 'Lab test ID is required']);
    }

    try {
      // Check if lab test exists
      $checkStmt = $this->conn->prepare("SELECT test_name FROM lab_tests WHERE lab_test_id = :lab_test_id");
      $checkStmt->execute([':lab_test_id' => $lab_test_id]);
      $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
      
      if (!$existing) {
        return json_encode(['error' => 'Lab test not found']);
      }

      // Update fields
      $test_code = $data['test_code'] ?? '';
      $test_name = $data['test_name'] ?? '';
      $description = $data['description'] ?? '';
      $sample_type = $data['sample_type'] ?? '';
      $normal_range = $data['normal_range'] ?? '';
      $unit = $data['unit'] ?? '';
      $price = $data['price'] ?? 0;
      $active = $data['active'] ?? 1;

      if (empty($test_code) || empty($test_name)) {
        return json_encode(['error' => 'Test code and name are required']);
      }

      // Check if test code is taken by another test
      $checkCodeStmt = $this->conn->prepare("SELECT lab_test_id FROM lab_tests 
        WHERE test_code = :test_code AND lab_test_id != :lab_test_id");
      $checkCodeStmt->execute([
        ':test_code' => $test_code,
        ':lab_test_id' => $lab_test_id
      ]);
      if ($checkCodeStmt->fetch()) {
        return json_encode(['error' => 'Test code already exists']);
      }

      // Update lab test
      $stmt = $this->conn->prepare("UPDATE lab_tests SET 
        test_code = :test_code,
        test_name = :test_name,
        description = :description,
        sample_type = :sample_type,
        normal_range = :normal_range,
        unit = :unit,
        price = :price,
        active = :active
        WHERE lab_test_id = :lab_test_id");

      $stmt->execute([
        ':test_code' => $test_code,
        ':test_name' => $test_name,
        ':description' => $description,
        ':sample_type' => $sample_type,
        ':normal_range' => $normal_range,
        ':unit' => $unit,
        ':price' => $price,
        ':active' => $active,
        ':lab_test_id' => $lab_test_id
      ]);

      // Log the action
      $this->logAction($admin_user_id, 'update', "Updated lab test: {$test_name} (ID: {$lab_test_id})", $_SERVER['REMOTE_ADDR'] ?? 'unknown');

      return json_encode([
        'success' => true,
        'message' => 'Lab test updated successfully'
      ]);

    } catch (PDOException $e) {
      return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
  }

  public function delete($json)
  {
    $data = json_decode($json, true);
    $admin_user_id = $data['admin_user_id'] ?? 1;
    $lab_test_id = $data['lab_test_id'] ?? 0;

    if (empty($lab_test_id)) {
      return json_encode(['error' => 'Lab test ID is required']);
    }

    try {
      // Check if lab test exists
      $checkStmt = $this->conn->prepare("SELECT test_name FROM lab_tests WHERE lab_test_id = :lab_test_id");
      $checkStmt->execute([':lab_test_id' => $lab_test_id]);
      $test = $checkStmt->fetch(PDO::FETCH_ASSOC);
      
      if (!$test) {
        return json_encode(['error' => 'Lab test not found']);
      }

      // Check if test is used in any requests
      $usageStmt = $this->conn->prepare("SELECT COUNT(*) as count FROM lab_request_items 
        WHERE lab_test_id = :lab_test_id");
      $usageStmt->execute([':lab_test_id' => $lab_test_id]);
      $usage = $usageStmt->fetch(PDO::FETCH_ASSOC);

      if ($usage['count'] > 0) {
        return json_encode(['error' => 'Cannot delete lab test. It is used in ' . $usage['count'] . ' request(s). Consider deactivating it instead.']);
      }

      // Delete lab test
      $stmt = $this->conn->prepare("DELETE FROM lab_tests WHERE lab_test_id = :lab_test_id");
      $stmt->execute([':lab_test_id' => $lab_test_id]);

      // Log the action
      $this->logAction($admin_user_id, 'delete', "Deleted lab test: {$test['test_name']} (ID: {$lab_test_id})", $_SERVER['REMOTE_ADDR'] ?? 'unknown');

      return json_encode([
        'success' => true,
        'message' => 'Lab test deleted successfully'
      ]);

    } catch (PDOException $e) {
      return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
    }
  }

  public function toggleStatus($json)
  {
    $data = json_decode($json, true);
    $admin_user_id = $data['admin_user_id'] ?? 1;
    $lab_test_id = $data['lab_test_id'] ?? 0;

    if (empty($lab_test_id)) {
      return json_encode(['error' => 'Lab test ID is required']);
    }

    try {
      // Get current status
      $stmt = $this->conn->prepare("SELECT test_name, active FROM lab_tests WHERE lab_test_id = :lab_test_id");
      $stmt->execute([':lab_test_id' => $lab_test_id]);
      $test = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$test) {
        return json_encode(['error' => 'Lab test not found']);
      }

      $newStatus = $test['active'] ? 0 : 1;
      $statusText = $newStatus ? 'activated' : 'deactivated';

      // Update status
      $updateStmt = $this->conn->prepare("UPDATE lab_tests SET active = :active WHERE lab_test_id = :lab_test_id");
      $updateStmt->execute([
        ':active' => $newStatus,
        ':lab_test_id' => $lab_test_id
      ]);

      // Log the action
      $this->logAction($admin_user_id, 'update', "Lab test {$statusText}: {$test['test_name']} (ID: {$lab_test_id})", $_SERVER['REMOTE_ADDR'] ?? 'unknown');

      return json_encode([
        'success' => true,
        'message' => "Lab test {$statusText} successfully",
        'active' => $newStatus
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

$api = new LabTestsAPI();

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
    case 'delete':
      echo $api->delete($json);
      break;
    case 'toggleStatus':
      echo $api->toggleStatus($json);
      break;
    default:
      echo json_encode(['error' => 'Invalid Operation']);
      break;
  }
} else {
  echo json_encode(['error' => 'Invalid Request Method']);
}
?>
