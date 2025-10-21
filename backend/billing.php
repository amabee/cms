<?php
include('conn.php');

$billing = new Billing();

if (in_array(needle: $_SERVER['REQUEST_METHOD'], haystack: ['GET', 'POST'])) {
  $operation = $_REQUEST['operation'] ?? '';
  $json = $_REQUEST['json'] ?? '';

  switch ($operation) {
    case 'getAll':
      echo $billing->getAll(json: $json);
      break;
    case 'getById':
      echo $billing->getById(json: $json);
      break;
    case 'getByPatient':
      echo $billing->getByPatient(json: $json);
      break;
    case 'create':
      echo $billing->create(json: $json);
      break;
    case 'update':
      echo $billing->update(json: $json);
      break;
    case 'delete':
      echo $billing->delete(json: $json);
      break;
    case 'updateStatus':
      echo $billing->updateStatus(json: $json);
      break;
    case 'addPayment':
      echo $billing->addPayment(json: $json);
      break;
    case 'getPayments':
      echo $billing->getPayments(json: $json);
      break;
    case 'getStatistics':
      echo $billing->getStatistics(json: $json);
      break;
    default:
      echo json_encode(value: ['error' => 'Invalid Operation']);
      break;
  }
} else {
  echo json_encode(value: ['error' => 'Invalid Request Method']);
}

class Billing
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
      $page = isset($data['page']) ? (int) $data['page'] : 1;
      $limit = isset($data['limit']) ? (int) $data['limit'] : 10;
      $offset = ($page - 1) * $limit;
      $search = isset($data['search']) ? $data['search'] : '';
      $status = isset($data['status']) ? $data['status'] : '';

      $sql = "SELECT b.*, 
                           CONCAT(pup.first_name, ' ', pup.last_name) as patient_name,
                           pup.phone as patient_phone,
                           a.appointment_date,
                           a.appointment_time
                    FROM billing b
                    LEFT JOIN patients p ON b.patient_id = p.patient_id
                    LEFT JOIN users pu ON p.user_id = pu.user_id
                    LEFT JOIN user_profiles pup ON pu.user_id = pup.user_id
                    LEFT JOIN appointments a ON b.appointment_id = a.appointment_id
                    WHERE 1=1";

      $params = [];

      if (!empty($search)) {
        $sql .= " AND (pup.first_name LIKE :search OR pup.last_name LIKE :search)";
        $params[':search'] = "%$search%";
      }

      if (!empty($status)) {
        $sql .= " AND b.status = :status";
        $params[':status'] = $status;
      }

      $sql .= " ORDER BY b.created_at DESC LIMIT :limit OFFSET :offset";

      $stmt = $this->conn->prepare($sql);
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
      $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
      $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
      $stmt->execute();

      $billings = $stmt->fetchAll(PDO::FETCH_ASSOC);

      // Get total count
      $countSql = "SELECT COUNT(*) as total FROM billing b
                         LEFT JOIN patients p ON b.patient_id = p.patient_id
                         LEFT JOIN users pu ON p.user_id = pu.user_id
                         LEFT JOIN user_profiles pup ON pu.user_id = pup.user_id
                         WHERE 1=1";

      if (!empty($search)) {
        $countSql .= " AND (pup.first_name LIKE :search OR pup.last_name LIKE :search)";
      }
      if (!empty($status)) {
        $countSql .= " AND b.status = :status";
      }

      $countStmt = $this->conn->prepare($countSql);
      foreach ($params as $key => $value) {
        if ($key !== ':limit' && $key !== ':offset') {
          $countStmt->bindValue($key, $value);
        }
      }
      $countStmt->execute();
      $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

      return $this->response(true, 'Billings retrieved successfully', $billings, ['total' => $total]);

    } catch (Exception $e) {
      return $this->response(false, 'Error: ' . $e->getMessage(), null);
    }
  }

  public function getById($json)
  {
    try {
      $data = json_decode($json, true);

      if (!isset($data['billing_id'])) {
        return $this->response(false, 'Billing ID is required', null);
      }

      $sql = "SELECT b.*, 
                           CONCAT(pup.first_name, ' ', pup.last_name) as patient_name,
                           pup.phone as patient_phone,
                           pu.email as patient_email,
                           a.appointment_date,
                           a.appointment_time,
                           a.reason as appointment_reason
                    FROM billing b
                    LEFT JOIN patients p ON b.patient_id = p.patient_id
                    LEFT JOIN users pu ON p.user_id = pu.user_id
                    LEFT JOIN user_profiles pup ON pu.user_id = pup.user_id
                    LEFT JOIN appointments a ON b.appointment_id = a.appointment_id
                    WHERE b.billing_id = :billing_id";

      $stmt = $this->conn->prepare($sql);
      $stmt->bindParam(':billing_id', $data['billing_id'], PDO::PARAM_INT);
      $stmt->execute();

      $billing = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$billing) {
        return $this->response(false, 'Billing record not found', null);
      }

      // Get billing items
      $itemsSql = "SELECT * FROM billing_items WHERE billing_id = :billing_id";
      $itemsStmt = $this->conn->prepare($itemsSql);
      $itemsStmt->bindParam(':billing_id', $data['billing_id'], PDO::PARAM_INT);
      $itemsStmt->execute();
      $billing['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

      // Get payments
      $paymentsSql = "SELECT * FROM payments WHERE billing_id = :billing_id ORDER BY payment_date DESC";
      $paymentsStmt = $this->conn->prepare($paymentsSql);
      $paymentsStmt->bindParam(':billing_id', $data['billing_id'], PDO::PARAM_INT);
      $paymentsStmt->execute();
      $billing['payments'] = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);

      return $this->response(true, 'Billing retrieved successfully', $billing);

    } catch (Exception $e) {
      return $this->response(false, 'Error: ' . $e->getMessage(), null);
    }
  }

  public function getByPatient($json)
  {
    try {
      $data = json_decode($json, true);

      if (!isset($data['patient_id'])) {
        return $this->response(false, 'Patient ID is required', null);
      }

      // Convert user_id to patient_id if needed
      $patientCheckSql = "SELECT patient_id FROM patients WHERE user_id = :user_id";
      $patientCheckStmt = $this->conn->prepare($patientCheckSql);
      $patientCheckStmt->execute([':user_id' => $data['patient_id']]);
      $patientRecord = $patientCheckStmt->fetch(PDO::FETCH_ASSOC);
      
      if ($patientRecord) {
        // It's a user_id, convert to patient_id
        $data['patient_id'] = $patientRecord['patient_id'];
      }

      $sql = "SELECT b.*, 
                           a.appointment_date,
                           a.appointment_time
                    FROM billing b
                    LEFT JOIN appointments a ON b.appointment_id = a.appointment_id
                    WHERE b.patient_id = :patient_id
                    ORDER BY b.created_at DESC";

      $stmt = $this->conn->prepare($sql);
      $stmt->bindParam(':patient_id', $data['patient_id'], PDO::PARAM_INT);
      $stmt->execute();

      $billings = $stmt->fetchAll(PDO::FETCH_ASSOC);

      return $this->response(true, 'Patient billings retrieved successfully', $billings);

    } catch (Exception $e) {
      return $this->response(false, 'Error: ' . $e->getMessage(), null);
    }
  }

  public function create($json)
  {
    try {
      $data = json_decode($json, true);

      $this->conn->beginTransaction();

      // Insert billing record
      $sql = "INSERT INTO billing (appointment_id, patient_id, total_amount, discount, net_amount, status)
                    VALUES (:appointment_id, :patient_id, :total_amount, :discount, :net_amount, :status)";

      $stmt = $this->conn->prepare($sql);
      $stmt->execute([
        ':appointment_id' => $data['appointment_id'],
        ':patient_id' => $data['patient_id'],
        ':total_amount' => $data['total_amount'],
        ':discount' => $data['discount'] ?? 0.00,
        ':net_amount' => $data['net_amount'],
        ':status' => $data['status'] ?? 'unpaid'
      ]);

      $billing_id = $this->conn->lastInsertId();

      // Insert billing items
      if (isset($data['items']) && is_array($data['items'])) {
        $itemSql = "INSERT INTO billing_items (billing_id, description, amount) 
                           VALUES (:billing_id, :description, :amount)";
        $itemStmt = $this->conn->prepare($itemSql);

        foreach ($data['items'] as $item) {
          $itemStmt->execute([
            ':billing_id' => $billing_id,
            ':description' => $item['description'],
            ':amount' => $item['amount']
          ]);
        }
      }

      // Log action
      $this->logAction(
        $data['admin_user_id'] ?? 1,
        'create',
        "Created billing #$billing_id for patient ID: {$data['patient_id']}",
        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
      );

      $this->conn->commit();

      return $this->response(true, 'Billing created successfully', ['billing_id' => $billing_id]);

    } catch (Exception $e) {
      $this->conn->rollBack();
      return $this->response(false, 'Error: ' . $e->getMessage(), null);
    }
  }

  public function update($json)
  {
    try {
      $data = json_decode($json, true);

      if (!isset($data['billing_id'])) {
        return $this->response(false, 'Billing ID is required', null);
      }

      $this->conn->beginTransaction();

      // Build update query dynamically based on provided fields
      $updates = [];
      $params = [':billing_id' => $data['billing_id']];

      if (isset($data['total_amount'])) {
        $updates[] = "total_amount = :total_amount";
        $params[':total_amount'] = $data['total_amount'];
      }
      if (isset($data['discount'])) {
        $updates[] = "discount = :discount";
        $params[':discount'] = $data['discount'];
      }
      if (isset($data['net_amount'])) {
        $updates[] = "net_amount = :net_amount";
        $params[':net_amount'] = $data['net_amount'];
      }
      if (isset($data['status'])) {
        $updates[] = "status = :status";
        $params[':status'] = $data['status'];
      }

      if (empty($updates)) {
        return $this->response(false, 'No fields to update', null);
      }

      // Update billing record
      $sql = "UPDATE billing SET " . implode(', ', $updates) . " WHERE billing_id = :billing_id";
      $stmt = $this->conn->prepare($sql);
      $stmt->execute($params);

      // Update billing items - delete old ones and insert new
      if (isset($data['items']) && is_array($data['items'])) {
        $deleteSql = "DELETE FROM billing_items WHERE billing_id = :billing_id";
        $deleteStmt = $this->conn->prepare($deleteSql);
        $deleteStmt->execute([':billing_id' => $data['billing_id']]);

        $itemSql = "INSERT INTO billing_items (billing_id, description, amount) 
                           VALUES (:billing_id, :description, :amount)";
        $itemStmt = $this->conn->prepare($itemSql);

        foreach ($data['items'] as $item) {
          $itemStmt->execute([
            ':billing_id' => $data['billing_id'],
            ':description' => $item['description'],
            ':amount' => $item['amount']
          ]);
        }
      }

      // Log action
      $this->logAction(
        $data['admin_user_id'] ?? 1,
        'update',
        "Updated billing #{$data['billing_id']}",
        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
      );

      $this->conn->commit();

      return $this->response(true, 'Billing updated successfully', null);

    } catch (Exception $e) {
      $this->conn->rollBack();
      return $this->response(false, 'Error: ' . $e->getMessage(), null);
    }
  }

  public function delete($json)
  {
    try {
      $data = json_decode($json, true);

      if (!isset($data['billing_id'])) {
        return $this->response(false, 'Billing ID is required', null);
      }

      $this->conn->beginTransaction();

      // Delete billing items first
      $itemSql = "DELETE FROM billing_items WHERE billing_id = :billing_id";
      $itemStmt = $this->conn->prepare($itemSql);
      $itemStmt->execute([':billing_id' => $data['billing_id']]);

      // Delete payments
      $paymentSql = "DELETE FROM payments WHERE billing_id = :billing_id";
      $paymentStmt = $this->conn->prepare($paymentSql);
      $paymentStmt->execute([':billing_id' => $data['billing_id']]);

      // Delete billing record
      $sql = "DELETE FROM billing WHERE billing_id = :billing_id";
      $stmt = $this->conn->prepare($sql);
      $stmt->execute([':billing_id' => $data['billing_id']]);

      // Log action
      $this->logAction(
        $data['admin_user_id'] ?? 1,
        'delete',
        "Deleted billing #{$data['billing_id']}",
        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
      );

      $this->conn->commit();

      return $this->response(true, 'Billing deleted successfully', null);

    } catch (Exception $e) {
      $this->conn->rollBack();
      return $this->response(false, 'Error: ' . $e->getMessage(), null);
    }
  }

  public function updateStatus($json)
  {
    try {
      $data = json_decode($json, true);

      if (!isset($data['billing_id']) || !isset($data['status'])) {
        return $this->response(false, 'Billing ID and status are required', null);
      }

      $sql = "UPDATE billing SET status = :status WHERE billing_id = :billing_id";
      $stmt = $this->conn->prepare($sql);
      $stmt->execute([
        ':billing_id' => $data['billing_id'],
        ':status' => $data['status']
      ]);

      // Log action
      $this->logAction(
        $data['admin_user_id'] ?? 1,
        'update',
        "Updated billing #{$data['billing_id']} status to {$data['status']}",
        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
      );

      return $this->response(true, 'Status updated successfully', null);

    } catch (Exception $e) {
      return $this->response(false, 'Error: ' . $e->getMessage(), null);
    }
  }

  public function addPayment($json)
  {
    try {
      $data = json_decode($json, true);

      if (!isset($data['billing_id']) || !isset($data['amount_paid'])) {
        return $this->response(false, 'Billing ID and amount are required', null);
      }

      $this->conn->beginTransaction();

      // Insert payment record
      $sql = "INSERT INTO payments (billing_id, amount_paid, payment_method, reference_no)
                    VALUES (:billing_id, :amount_paid, :payment_method, :reference_no)";

      $stmt = $this->conn->prepare($sql);
      $stmt->execute([
        ':billing_id' => $data['billing_id'],
        ':amount_paid' => $data['amount_paid'],
        ':payment_method' => $data['payment_method'] ?? 'cash',
        ':reference_no' => $data['reference_no'] ?? null
      ]);

      // Check if billing is fully paid
      $checkSql = "SELECT b.net_amount, 
                               COALESCE(SUM(p.amount_paid), 0) as total_paid
                        FROM billing b
                        LEFT JOIN payments p ON b.billing_id = p.billing_id
                        WHERE b.billing_id = :billing_id
                        GROUP BY b.billing_id";

      $checkStmt = $this->conn->prepare($checkSql);
      $checkStmt->execute([':billing_id' => $data['billing_id']]);
      $result = $checkStmt->fetch(PDO::FETCH_ASSOC);

      if ($result && $result['total_paid'] >= $result['net_amount']) {
        $updateSql = "UPDATE billing SET status = 'paid' WHERE billing_id = :billing_id";
        $updateStmt = $this->conn->prepare($updateSql);
        $updateStmt->execute([':billing_id' => $data['billing_id']]);
      }

      // Log action
      $this->logAction(
        $data['admin_user_id'] ?? 1,
        'create',
        "Added payment for billing #{$data['billing_id']}: {$data['amount_paid']}",
        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
      );

      $this->conn->commit();

      return $this->response(true, 'Payment added successfully', null);

    } catch (Exception $e) {
      $this->conn->rollBack();
      return $this->response(false, 'Error: ' . $e->getMessage(), null);
    }
  }

  public function getPayments($json)
  {
    try {
      $data = json_decode($json, true);

      if (!isset($data['billing_id'])) {
        return $this->response(false, 'Billing ID is required', null);
      }

      $sql = "SELECT * FROM payments WHERE billing_id = :billing_id ORDER BY payment_date DESC";
      $stmt = $this->conn->prepare($sql);
      $stmt->bindParam(':billing_id', $data['billing_id'], PDO::PARAM_INT);
      $stmt->execute();

      $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

      return $this->response(true, 'Payments retrieved successfully', $payments);

    } catch (Exception $e) {
      return $this->response(false, 'Error: ' . $e->getMessage(), null);
    }
  }

  public function getStatistics($json)
  {
    try {
      $stats = [];

      // Total revenue
      $stmt = $this->conn->query("SELECT COALESCE(SUM(net_amount), 0) as total FROM billing WHERE status = 'paid'");
      $stats['total_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

      // Unpaid bills
      $stmt = $this->conn->query("SELECT COALESCE(SUM(net_amount), 0) as total FROM billing WHERE status = 'unpaid'");
      $stats['unpaid_amount'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

      // Total bills
      $stmt = $this->conn->query("SELECT COUNT(*) as total FROM billing");
      $stats['total_bills'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

      // Paid bills count
      $stmt = $this->conn->query("SELECT COUNT(*) as total FROM billing WHERE status = 'paid'");
      $stats['paid_bills'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

      // Unpaid bills count
      $stmt = $this->conn->query("SELECT COUNT(*) as total FROM billing WHERE status = 'unpaid'");
      $stats['unpaid_bills'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

      // Today's revenue
      $stmt = $this->conn->query("SELECT COALESCE(SUM(amount_paid), 0) as total FROM payments 
                                       WHERE DATE(payment_date) = CURDATE()");
      $stats['today_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

      return json_encode([
        'success' => true,
        'message' => 'Statistics retrieved successfully',
        'data' => $stats
      ]);

    } catch (Exception $e) {
      return $this->response(false, 'Error: ' . $e->getMessage(), null);
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
    } catch (Exception $e) {
      error_log("Logging failed: " . $e->getMessage());
    }
  }

  private function response($success, $message, $data, $extra = [])
  {
    $response = [
      'success' => $success,
      'message' => $message
    ];

    if ($data !== null) {
      $response['data'] = $data;
    }

    if (!empty($extra)) {
      $response = array_merge($response, $extra);
    }

    return json_encode($response);
  }
}
?>

