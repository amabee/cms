<?php
require_once 'conn.php';
header('Content-Type: application/json');

class QueueAPI {
    private $conn;

    public function __construct() {
        $this->conn = DatabaseConnection::getInstance()->getConnection();
    }

    public function handleRequest() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['action'])) {
            return $this->response(false, 'Invalid request', null);
        }

        $action = $data['action'];

        switch ($action) {
            case 'getAll':
                return $this->getAll($data);
            case 'getById':
                return $this->getById($data);
            case 'getActive':
                return $this->getActive($data);
            case 'getByDate':
                return $this->getByDate($data);
            case 'create':
                return $this->create($data);
            case 'update':
                return $this->update($data);
            case 'delete':
                return $this->delete($data);
            case 'updateStatus':
                return $this->updateStatus($data);
            case 'callNext':
                return $this->callNext($data);
            case 'getStatistics':
                return $this->getStatistics($data);
            default:
                return $this->response(false, 'Invalid action', null);
        }
    }

    private function getAll($data) {
        try {
            $page = isset($data['page']) ? (int)$data['page'] : 1;
            $limit = isset($data['limit']) ? (int)$data['limit'] : 10;
            $offset = ($page - 1) * $limit;
            $search = isset($data['search']) ? $data['search'] : '';
            $status = isset($data['status']) ? $data['status'] : '';
            $date_filter = isset($data['date_filter']) ? $data['date_filter'] : '';

            $sql = "SELECT q.*, 
                           CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                           p.phone as patient_phone,
                           CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
                           a.appointment_time
                    FROM queue q
                    LEFT JOIN patients p ON q.patient_id = p.patient_id
                    LEFT JOIN doctors d ON q.doctor_id = d.doctor_id
                    LEFT JOIN appointments a ON q.appointment_id = a.appointment_id
                    WHERE 1=1";

            $params = [];

            if (!empty($search)) {
                $sql .= " AND (p.first_name LIKE :search OR p.last_name LIKE :search 
                          OR q.queue_number LIKE :search)";
                $params[':search'] = "%$search%";
            }

            if (!empty($status)) {
                $sql .= " AND q.status = :status";
                $params[':status'] = $status;
            }

            if (!empty($date_filter)) {
                $sql .= " AND DATE(q.queue_date) = :date_filter";
                $params[':date_filter'] = $date_filter;
            } else {
                // Default to today if no date filter
                $sql .= " AND DATE(q.queue_date) = CURDATE()";
            }

            // Get total count
            $countStmt = $this->conn->prepare("SELECT COUNT(*) as total FROM ($sql) as t");
            $countStmt->execute($params);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Get paginated results
            $sql .= " ORDER BY q.queue_number ASC LIMIT :limit OFFSET :offset";
            $stmt = $this->conn->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            $queues = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response(true, 'Queue entries retrieved successfully', $queues, [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => ceil($total / $limit)
            ]);

        } catch (Exception $e) {
            return $this->response(false, 'Error: ' . $e->getMessage(), null);
        }
    }

    private function getById($data) {
        try {
            if (!isset($data['queue_id'])) {
                return $this->response(false, 'Queue ID is required', null);
            }

            $sql = "SELECT q.*, 
                           CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                           p.phone as patient_phone,
                           p.email as patient_email,
                           CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
                           d.specialization,
                           a.appointment_time,
                           a.reason as appointment_reason
                    FROM queue q
                    LEFT JOIN patients p ON q.patient_id = p.patient_id
                    LEFT JOIN doctors d ON q.doctor_id = d.doctor_id
                    LEFT JOIN appointments a ON q.appointment_id = a.appointment_id
                    WHERE q.queue_id = :queue_id";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':queue_id', $data['queue_id'], PDO::PARAM_INT);
            $stmt->execute();

            $queue = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$queue) {
                return $this->response(false, 'Queue entry not found', null);
            }

            return $this->response(true, 'Queue entry retrieved successfully', $queue);

        } catch (Exception $e) {
            return $this->response(false, 'Error: ' . $e->getMessage(), null);
        }
    }

    private function getActive($data) {
        try {
            $sql = "SELECT q.*, 
                           CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                           CONCAT(d.first_name, ' ', d.last_name) as doctor_name
                    FROM queue q
                    LEFT JOIN patients p ON q.patient_id = p.patient_id
                    LEFT JOIN doctors d ON q.doctor_id = d.doctor_id
                    WHERE q.status IN ('waiting', 'called') 
                    AND DATE(q.queue_date) = CURDATE()
                    ORDER BY q.queue_number ASC";

            $stmt = $this->conn->query($sql);
            $queues = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response(true, 'Active queue entries retrieved successfully', $queues);

        } catch (Exception $e) {
            return $this->response(false, 'Error: ' . $e->getMessage(), null);
        }
    }

    private function getByDate($data) {
        try {
            if (!isset($data['queue_date'])) {
                return $this->response(false, 'Queue date is required', null);
            }

            $sql = "SELECT q.*, 
                           CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                           CONCAT(d.first_name, ' ', d.last_name) as doctor_name
                    FROM queue q
                    LEFT JOIN patients p ON q.patient_id = p.patient_id
                    LEFT JOIN doctors d ON q.doctor_id = d.doctor_id
                    WHERE DATE(q.queue_date) = :queue_date
                    ORDER BY q.queue_number ASC";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':queue_date', $data['queue_date']);
            $stmt->execute();

            $queues = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response(true, 'Queue entries retrieved successfully', $queues);

        } catch (Exception $e) {
            return $this->response(false, 'Error: ' . $e->getMessage(), null);
        }
    }

    private function create($data) {
        try {
            // Validate required fields
            $required = ['patient_id', 'doctor_id'];
            foreach ($required as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return $this->response(false, ucfirst(str_replace('_', ' ', $field)) . ' is required', null);
                }
            }

            // Generate queue number
            $queue_date = isset($data['queue_date']) ? $data['queue_date'] : date('Y-m-d');
            
            $queueNumberSql = "SELECT COALESCE(MAX(queue_number), 0) + 1 as next_number 
                              FROM queue 
                              WHERE DATE(queue_date) = :queue_date";
            $queueStmt = $this->conn->prepare($queueNumberSql);
            $queueStmt->bindParam(':queue_date', $queue_date);
            $queueStmt->execute();
            $queue_number = $queueStmt->fetch(PDO::FETCH_ASSOC)['next_number'];

            $sql = "INSERT INTO queue (queue_number, patient_id, doctor_id, appointment_id, queue_date, status, notes)
                    VALUES (:queue_number, :patient_id, :doctor_id, :appointment_id, :queue_date, :status, :notes)";

            $stmt = $this->conn->prepare($sql);
            $appointment_id = isset($data['appointment_id']) ? $data['appointment_id'] : null;
            $status = 'waiting';
            $notes = isset($data['notes']) ? $data['notes'] : null;

            $stmt->execute([
                ':queue_number' => $queue_number,
                ':patient_id' => $data['patient_id'],
                ':doctor_id' => $data['doctor_id'],
                ':appointment_id' => $appointment_id,
                ':queue_date' => $queue_date,
                ':status' => $status,
                ':notes' => $notes
            ]);

            $queue_id = $this->conn->lastInsertId();

            // Log the action
            if (isset($_SESSION['user_id'])) {
                $this->logAction(
                    $_SESSION['user_id'],
                    'create',
                    'Created queue entry #' . $queue_number . ' for patient ID: ' . $data['patient_id'],
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                );
            }

            return $this->response(true, 'Queue entry created successfully', [
                'queue_id' => $queue_id,
                'queue_number' => $queue_number
            ]);

        } catch (Exception $e) {
            return $this->response(false, 'Error: ' . $e->getMessage(), null);
        }
    }

    private function update($data) {
        try {
            if (!isset($data['queue_id'])) {
                return $this->response(false, 'Queue ID is required', null);
            }

            // Check if queue entry exists
            $checkSql = "SELECT queue_id FROM queue WHERE queue_id = :queue_id";
            $checkStmt = $this->conn->prepare($checkSql);
            $checkStmt->bindParam(':queue_id', $data['queue_id'], PDO::PARAM_INT);
            $checkStmt->execute();

            if ($checkStmt->rowCount() === 0) {
                return $this->response(false, 'Queue entry not found', null);
            }

            $sql = "UPDATE queue SET 
                    patient_id = :patient_id,
                    doctor_id = :doctor_id,
                    appointment_id = :appointment_id,
                    status = :status,
                    notes = :notes
                    WHERE queue_id = :queue_id";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':patient_id' => $data['patient_id'],
                ':doctor_id' => $data['doctor_id'],
                ':appointment_id' => $data['appointment_id'] ?? null,
                ':status' => $data['status'],
                ':notes' => $data['notes'] ?? null,
                ':queue_id' => $data['queue_id']
            ]);

            // Log the action
            if (isset($_SESSION['user_id'])) {
                $this->logAction(
                    $_SESSION['user_id'],
                    'update',
                    'Updated queue entry #' . $data['queue_id'],
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                );
            }

            return $this->response(true, 'Queue entry updated successfully', null);

        } catch (Exception $e) {
            return $this->response(false, 'Error: ' . $e->getMessage(), null);
        }
    }

    private function delete($data) {
        try {
            if (!isset($data['queue_id'])) {
                return $this->response(false, 'Queue ID is required', null);
            }

            $queue_id = $data['queue_id'];

            // Check if queue entry exists and get queue number
            $checkSql = "SELECT queue_number FROM queue WHERE queue_id = :queue_id";
            $checkStmt = $this->conn->prepare($checkSql);
            $checkStmt->bindParam(':queue_id', $queue_id, PDO::PARAM_INT);
            $checkStmt->execute();

            if ($checkStmt->rowCount() === 0) {
                return $this->response(false, 'Queue entry not found', null);
            }

            $queue_number = $checkStmt->fetch(PDO::FETCH_ASSOC)['queue_number'];

            // Delete queue entry
            $sql = "DELETE FROM queue WHERE queue_id = :queue_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':queue_id', $queue_id, PDO::PARAM_INT);
            $stmt->execute();

            // Log the action
            if (isset($_SESSION['user_id'])) {
                $this->logAction(
                    $_SESSION['user_id'],
                    'delete',
                    'Deleted queue entry #' . $queue_number,
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                );
            }

            return $this->response(true, 'Queue entry deleted successfully', null);

        } catch (Exception $e) {
            return $this->response(false, 'Error: ' . $e->getMessage(), null);
        }
    }

    private function updateStatus($data) {
        try {
            if (!isset($data['queue_id']) || !isset($data['status'])) {
                return $this->response(false, 'Queue ID and status are required', null);
            }

            $validStatuses = ['waiting', 'called', 'done', 'skipped'];
            if (!in_array($data['status'], $validStatuses)) {
                return $this->response(false, 'Invalid status', null);
            }

            // Get current queue number
            $getSql = "SELECT queue_number FROM queue WHERE queue_id = :queue_id";
            $getStmt = $this->conn->prepare($getSql);
            $getStmt->bindParam(':queue_id', $data['queue_id'], PDO::PARAM_INT);
            $getStmt->execute();
            $queue_number = $getStmt->fetch(PDO::FETCH_ASSOC)['queue_number'];

            $sql = "UPDATE queue SET status = :status WHERE queue_id = :queue_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':status' => $data['status'],
                ':queue_id' => $data['queue_id']
            ]);

            // Log the action
            if (isset($_SESSION['user_id'])) {
                $this->logAction(
                    $_SESSION['user_id'],
                    'update',
                    'Updated queue #' . $queue_number . ' status to ' . $data['status'],
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                );
            }

            return $this->response(true, 'Queue status updated successfully', null);

        } catch (Exception $e) {
            return $this->response(false, 'Error: ' . $e->getMessage(), null);
        }
    }

    private function callNext($data) {
        try {
            $doctor_id = isset($data['doctor_id']) ? $data['doctor_id'] : null;
            
            $sql = "SELECT queue_id, queue_number FROM queue 
                    WHERE status = 'waiting' 
                    AND DATE(queue_date) = CURDATE()";
            
            if ($doctor_id) {
                $sql .= " AND doctor_id = :doctor_id";
            }
            
            $sql .= " ORDER BY queue_number ASC LIMIT 1";

            $stmt = $this->conn->prepare($sql);
            if ($doctor_id) {
                $stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
            }
            $stmt->execute();

            $next = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$next) {
                return $this->response(false, 'No waiting queue entries found', null);
            }

            // Update status to 'called'
            $updateSql = "UPDATE queue SET status = 'called' WHERE queue_id = :queue_id";
            $updateStmt = $this->conn->prepare($updateSql);
            $updateStmt->bindParam(':queue_id', $next['queue_id'], PDO::PARAM_INT);
            $updateStmt->execute();

            // Log the action
            if (isset($_SESSION['user_id'])) {
                $this->logAction(
                    $_SESSION['user_id'],
                    'update',
                    'Called queue #' . $next['queue_number'],
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                );
            }

            return $this->response(true, 'Next queue called successfully', [
                'queue_id' => $next['queue_id'],
                'queue_number' => $next['queue_number']
            ]);

        } catch (Exception $e) {
            return $this->response(false, 'Error: ' . $e->getMessage(), null);
        }
    }

    private function getStatistics($data) {
        try {
            $stats = [];

            // Total queue entries today
            $stmt = $this->conn->query("SELECT COUNT(*) as total FROM queue 
                                       WHERE DATE(queue_date) = CURDATE()");
            $stats['total_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Waiting
            $stmt = $this->conn->query("SELECT COUNT(*) as total FROM queue 
                                       WHERE status = 'waiting' AND DATE(queue_date) = CURDATE()");
            $stats['waiting'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Called
            $stmt = $this->conn->query("SELECT COUNT(*) as total FROM queue 
                                       WHERE status = 'called' AND DATE(queue_date) = CURDATE()");
            $stats['called'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Done
            $stmt = $this->conn->query("SELECT COUNT(*) as total FROM queue 
                                       WHERE status = 'done' AND DATE(queue_date) = CURDATE()");
            $stats['done'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Skipped
            $stmt = $this->conn->query("SELECT COUNT(*) as total FROM queue 
                                       WHERE status = 'skipped' AND DATE(queue_date) = CURDATE()");
            $stats['skipped'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            return $this->response(true, 'Statistics retrieved successfully', $stats);

        } catch (Exception $e) {
            return $this->response(false, 'Error: ' . $e->getMessage(), null);
        }
    }

    private function logAction($user_id, $action, $description, $ip_address) {
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

    private function response($success, $message, $data, $extra = []) {
        $response = [
            'success' => $success,
            'message' => $message
        ];

        if ($data !== null) {
            if (isset($extra['total'])) {
                $response['queues'] = $data;
            } else {
                $response[is_array($data) && isset($data[0]) ? 'queues' : 'queue'] = $data;
            }
        }
        
        if (!empty($extra)) {
            $response = array_merge($response, $extra);
        }
        
        return json_encode($response);
    }
}

// Handle the request
session_start();
$api = new QueueAPI();
echo $api->handleRequest();
?>
