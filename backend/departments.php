<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

include('conn.php');

class Departments
{
  private $conn;

  public function __construct()
  {
    $this->conn = DatabaseConnection::getInstance()->getConnection();
  }

  public function getAll($json)
  {
    try {
      $stmt = $this->conn->query("SELECT department_id, name, description 
                                   FROM departments 
                                   ORDER BY name ASC");
      $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

      return json_encode([
        'success' => true,
        'data' => $departments
      ]);
    } catch (PDOException $e) {
      return json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
      ]);
    }
  }

  public function getById($json)
  {
    try {
      $data = json_decode($json, true);
      $department_id = $data['department_id'] ?? null;

      if (!$department_id) {
        return json_encode([
          'success' => false,
          'error' => 'Department ID is required'
        ]);
      }

      $stmt = $this->conn->prepare("SELECT department_id, name, description 
                                     FROM departments 
                                     WHERE department_id = :department_id");
      $stmt->execute([':department_id' => $department_id]);
      $department = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($department) {
        return json_encode([
          'success' => true,
          'data' => $department
        ]);
      } else {
        return json_encode([
          'success' => false,
          'error' => 'Department not found'
        ]);
      }
    } catch (PDOException $e) {
      return json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
      ]);
    }
  }

  public function create($json)
  {
    try {
      $data = json_decode($json, true);

      if (empty($data['name'])) {
        return json_encode([
          'success' => false,
          'error' => 'Department name is required'
        ]);
      }

      $stmt = $this->conn->prepare("INSERT INTO departments (name, description) 
                                     VALUES (:name, :description)");
      $stmt->execute([
        ':name' => $data['name'],
        ':description' => $data['description'] ?? null
      ]);

      $department_id = $this->conn->lastInsertId();

      // Log the action
      if (!empty($data['admin_user_id'])) {
        $this->logAction($data['admin_user_id'], 'create_department', "Created department: {$data['name']}");
      }

      return json_encode([
        'success' => true,
        'department_id' => $department_id,
        'message' => 'Department created successfully'
      ]);
    } catch (PDOException $e) {
      return json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
      ]);
    }
  }

  public function update($json)
  {
    try {
      $data = json_decode($json, true);

      if (empty($data['department_id']) || empty($data['name'])) {
        return json_encode([
          'success' => false,
          'error' => 'Department ID and name are required'
        ]);
      }

      $stmt = $this->conn->prepare("UPDATE departments 
                                     SET name = :name, 
                                         description = :description 
                                     WHERE department_id = :department_id");
      $stmt->execute([
        ':department_id' => $data['department_id'],
        ':name' => $data['name'],
        ':description' => $data['description'] ?? null
      ]);

      // Log the action
      if (!empty($data['admin_user_id'])) {
        $this->logAction($data['admin_user_id'], 'update_department', "Updated department: {$data['name']}");
      }

      return json_encode([
        'success' => true,
        'message' => 'Department updated successfully'
      ]);
    } catch (PDOException $e) {
      return json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
      ]);
    }
  }

  public function delete($json)
  {
    try {
      $data = json_decode($json, true);
      $department_id = $data['department_id'] ?? null;

      if (!$department_id) {
        return json_encode([
          'success' => false,
          'error' => 'Department ID is required'
        ]);
      }

      // Check if department has doctors
      $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM doctors WHERE department_id = :department_id");
      $stmt->execute([':department_id' => $department_id]);
      $result = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($result['count'] > 0) {
        return json_encode([
          'success' => false,
          'error' => 'Cannot delete department with assigned doctors'
        ]);
      }

      // Get department name for logging
      $stmt = $this->conn->prepare("SELECT name FROM departments WHERE department_id = :department_id");
      $stmt->execute([':department_id' => $department_id]);
      $dept = $stmt->fetch(PDO::FETCH_ASSOC);

      $stmt = $this->conn->prepare("DELETE FROM departments WHERE department_id = :department_id");
      $stmt->execute([':department_id' => $department_id]);

      // Log the action
      if (!empty($data['admin_user_id']) && $dept) {
        $this->logAction($data['admin_user_id'], 'delete_department', "Deleted department: {$dept['name']}");
      }

      return json_encode([
        'success' => true,
        'message' => 'Department deleted successfully'
      ]);
    } catch (PDOException $e) {
      return json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
      ]);
    }
  }

  private function logAction($user_id, $action, $description)
  {
    try {
      $stmt = $this->conn->prepare("INSERT INTO system_logs (user_id, action, description) 
                                     VALUES (:user_id, :action, :description)");
      $stmt->execute([
        ':user_id' => $user_id,
        ':action' => $action,
        ':description' => $description
      ]);
    } catch (PDOException $e) {
      // Log silently fails
    }
  }
}

$departments = new Departments();

if (in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
  $operation = $_REQUEST['operation'] ?? '';
  $json = $_REQUEST['json'] ?? '';

  switch ($operation) {
    case 'getAll':
      echo $departments->getAll($json);
      break;
    case 'getById':
      echo $departments->getById($json);
      break;
    case 'create':
      echo $departments->create($json);
      break;
    case 'update':
      echo $departments->update($json);
      break;
    case 'delete':
      echo $departments->delete($json);
      break;
    default:
      echo json_encode(['error' => 'Invalid Operation']);
      break;
  }
} else {
  echo json_encode(['error' => 'Invalid Request Method']);
}
?>

