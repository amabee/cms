<?php
require_once 'conn.php';
header('Content-Type: application/json');

class AppointmentsAPI {
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
            case 'getByPatient':
                return $this->getByPatient($data);
            case 'getByDoctor':
                return $this->getByDoctor($data);
            case 'create':
                return $this->create($data);
            case 'update':
                return $this->update($data);
            case 'delete':
                return $this->delete($data);
            case 'updateStatus':
                return $this->updateStatus($data);
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
            $patient_id = isset($data['patient_id']) ? $data['patient_id'] : '';
            $doctor_id = isset($data['doctor_id']) ? $data['doctor_id'] : '';
            $date_filter = isset($data['date_filter']) ? $data['date_filter'] : '';

            // Convert user_id to patient_id if needed and patient_id is provided
            if (!empty($patient_id)) {
                $patientCheckSql = "SELECT patient_id FROM patients WHERE user_id = :user_id";
                $patientCheckStmt = $this->conn->prepare($patientCheckSql);
                $patientCheckStmt->execute([':user_id' => $patient_id]);
                $patientRecord = $patientCheckStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($patientRecord) {
                    $patient_id = $patientRecord['patient_id'];
                }
            }

            // Convert user_id to doctor_id if needed and doctor_id is provided
            if (!empty($doctor_id)) {
                $doctorCheckSql = "SELECT doctor_id FROM doctors WHERE user_id = :user_id";
                $doctorCheckStmt = $this->conn->prepare($doctorCheckSql);
                $doctorCheckStmt->execute([':user_id' => $doctor_id]);
                $doctorRecord = $doctorCheckStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($doctorRecord) {
                    $doctor_id = $doctorRecord['doctor_id'];
                }
            }

            $sql = "SELECT a.*, 
                           CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                           CONCAT(up.first_name, ' ', up.last_name) as doctor_name,
                           doc.specialization
                    FROM appointments a
                    LEFT JOIN patients p ON a.patient_id = p.patient_id
                    LEFT JOIN doctors doc ON a.doctor_id = doc.doctor_id
                    LEFT JOIN users u ON doc.user_id = u.user_id
                    LEFT JOIN user_profiles up ON u.user_id = up.user_id
                    WHERE 1=1";

            $params = [];

            if (!empty($search)) {
                $sql .= " AND (p.first_name LIKE :search OR p.last_name LIKE :search 
                          OR up.first_name LIKE :search OR up.last_name LIKE :search 
                          OR a.reason LIKE :search)";
                $params[':search'] = "%$search%";
            }

            if (!empty($status)) {
                $sql .= " AND a.status = :status";
                $params[':status'] = $status;
            }

            if (!empty($patient_id)) {
                $sql .= " AND a.patient_id = :patient_id";
                $params[':patient_id'] = $patient_id;
            }

            if (!empty($doctor_id)) {
                $sql .= " AND a.doctor_id = :doctor_id";
                $params[':doctor_id'] = $doctor_id;
            }

            if (!empty($date_filter)) {
                $sql .= " AND DATE(a.appointment_date) = :date_filter";
                $params[':date_filter'] = $date_filter;
            }

            // Get total count
            $countStmt = $this->conn->prepare("SELECT COUNT(*) as total FROM ($sql) as t");
            $countStmt->execute($params);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Get paginated results
            $sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT :limit OFFSET :offset";
            $stmt = $this->conn->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response(true, 'Appointments retrieved successfully', $appointments, [
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
            if (!isset($data['appointment_id'])) {
                return $this->response(false, 'Appointment ID is required', null);
            }

            $sql = "SELECT a.*, 
                           CONCAT(pup.first_name, ' ', pup.last_name) as patient_name,
                           pup.phone as patient_phone,
                           pu.email as patient_email,
                           CONCAT(up.first_name, ' ', up.last_name) as doctor_name,
                           doc.specialization,
                           doc.contact_number as doctor_phone
                    FROM appointments a
                    LEFT JOIN patients p ON a.patient_id = p.patient_id
                    LEFT JOIN users pu ON p.user_id = pu.user_id
                    LEFT JOIN user_profiles pup ON pu.user_id = pup.user_id
                    LEFT JOIN doctors doc ON a.doctor_id = doc.doctor_id
                    LEFT JOIN users u ON doc.user_id = u.user_id
                    LEFT JOIN user_profiles up ON u.user_id = up.user_id
                    WHERE a.appointment_id = :appointment_id";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':appointment_id', $data['appointment_id'], PDO::PARAM_INT);
            $stmt->execute();

            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$appointment) {
                return $this->response(false, 'Appointment not found', null);
            }

            return $this->response(true, 'Appointment retrieved successfully', $appointment);

        } catch (Exception $e) {
            return $this->response(false, 'Error: ' . $e->getMessage(), null);
        }
    }

    private function getByPatient($data) {
        try {
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

            $sql = "SELECT a.*, 
                           CONCAT(up.first_name, ' ', up.last_name) as doctor_name,
                           doc.specialization
                    FROM appointments a
                    LEFT JOIN doctors doc ON a.doctor_id = doc.doctor_id
                    LEFT JOIN users u ON doc.user_id = u.user_id
                    LEFT JOIN user_profiles up ON u.user_id = up.user_id
                    WHERE a.patient_id = :patient_id
                    ORDER BY a.appointment_date DESC, a.appointment_time DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':patient_id', $data['patient_id'], PDO::PARAM_INT);
            $stmt->execute();

            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response(true, 'Patient appointments retrieved successfully', $appointments);

        } catch (Exception $e) {
            return $this->response(false, 'Error: ' . $e->getMessage(), null);
        }
    }

    private function getByDoctor($data) {
        try {
            if (!isset($data['doctor_id'])) {
                return $this->response(false, 'Doctor ID is required', null);
            }

            $sql = "SELECT a.*, 
                           CONCAT(pup.first_name, ' ', pup.last_name) as patient_name,
                           pup.phone as patient_phone,
                           pu.email as patient_email
                    FROM appointments a
                    LEFT JOIN patients p ON a.patient_id = p.patient_id
                    LEFT JOIN users pu ON p.user_id = pu.user_id
                    LEFT JOIN user_profiles pup ON pu.user_id = pup.user_id
                    WHERE a.doctor_id = :doctor_id
                    ORDER BY a.appointment_date DESC, a.appointment_time DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':doctor_id', $data['doctor_id'], PDO::PARAM_INT);
            $stmt->execute();

            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response(true, 'Doctor appointments retrieved successfully', $appointments);

        } catch (Exception $e) {
            return $this->response(false, 'Error: ' . $e->getMessage(), null);
        }
    }

    private function create($data) {
        try {
            // Validate required fields
            $required = ['patient_id', 'doctor_id', 'appointment_date', 'appointment_time', 'reason'];
            foreach ($required as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return $this->response(false, ucfirst(str_replace('_', ' ', $field)) . ' is required', null);
                }
            }

            // Convert user_id to patient_id if needed
            // Check if the patient_id is actually a user_id (for patient portal)
            $patientCheckSql = "SELECT patient_id FROM patients WHERE user_id = :user_id";
            $patientCheckStmt = $this->conn->prepare($patientCheckSql);
            $patientCheckStmt->execute([':user_id' => $data['patient_id']]);
            $patientRecord = $patientCheckStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($patientRecord) {
                // It's a user_id, convert to patient_id
                $data['patient_id'] = $patientRecord['patient_id'];
            } else {
                return $this->response(false, 'Patient record not found. Please ensure you have a complete patient profile.', null);
            }

            // Convert user_id to doctor_id if needed
            // Check if the doctor_id is actually a user_id (for patient portal)
            $doctorCheckSql = "SELECT doctor_id FROM doctors WHERE user_id = :user_id";
            $doctorCheckStmt = $this->conn->prepare($doctorCheckSql);
            $doctorCheckStmt->execute([':user_id' => $data['doctor_id']]);
            $doctorRecord = $doctorCheckStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($doctorRecord) {
                // It's a user_id, convert to doctor_id
                $data['doctor_id'] = $doctorRecord['doctor_id'];
            } else {
                return $this->response(false, 'Doctor not found.', null);
            }

            // Check for conflicting appointments
            $checkSql = "SELECT appointment_id FROM appointments 
                         WHERE doctor_id = :doctor_id 
                         AND appointment_date = :appointment_date 
                         AND appointment_time = :appointment_time
                         AND status != 'cancelled'";
            $checkStmt = $this->conn->prepare($checkSql);
            $checkStmt->execute([
                ':doctor_id' => $data['doctor_id'],
                ':appointment_date' => $data['appointment_date'],
                ':appointment_time' => $data['appointment_time']
            ]);

            if ($checkStmt->rowCount() > 0) {
                return $this->response(false, 'This time slot is already booked for the selected doctor', null);
            }

            $sql = "INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, reason, status, notes)
                    VALUES (:patient_id, :doctor_id, :appointment_date, :appointment_time, :reason, :status, :notes)";

            $stmt = $this->conn->prepare($sql);
            $status = isset($data['status']) ? $data['status'] : 'pending';
            $notes = isset($data['notes']) ? $data['notes'] : null;

            $stmt->execute([
                ':patient_id' => $data['patient_id'],
                ':doctor_id' => $data['doctor_id'],
                ':appointment_date' => $data['appointment_date'],
                ':appointment_time' => $data['appointment_time'],
                ':reason' => $data['reason'],
                ':status' => $status,
                ':notes' => $notes
            ]);

            $appointment_id = $this->conn->lastInsertId();

            // Log the action
            if (isset($_SESSION['user_id'])) {
                $this->logAction(
                    $_SESSION['user_id'],
                    'create',
                    'Created appointment #' . $appointment_id . ' for patient ID: ' . $data['patient_id'],
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                );
            }

            return $this->response(true, 'Appointment created successfully', ['appointment_id' => $appointment_id]);

        } catch (Exception $e) {
            return $this->response(false, 'Error: ' . $e->getMessage(), null);
        }
    }

    private function update($data) {
        try {
            if (!isset($data['appointment_id'])) {
                return $this->response(false, 'Appointment ID is required', null);
            }

            // Check if appointment exists
            $checkSql = "SELECT appointment_id FROM appointments WHERE appointment_id = :appointment_id";
            $checkStmt = $this->conn->prepare($checkSql);
            $checkStmt->bindParam(':appointment_id', $data['appointment_id'], PDO::PARAM_INT);
            $checkStmt->execute();

            if ($checkStmt->rowCount() === 0) {
                return $this->response(false, 'Appointment not found', null);
            }

            // Check for conflicting appointments (excluding current appointment)
            $conflictSql = "SELECT appointment_id FROM appointments 
                           WHERE doctor_id = :doctor_id 
                           AND appointment_date = :appointment_date 
                           AND appointment_time = :appointment_time
                           AND appointment_id != :appointment_id
                           AND status != 'cancelled'";
            $conflictStmt = $this->conn->prepare($conflictSql);
            $conflictStmt->execute([
                ':doctor_id' => $data['doctor_id'],
                ':appointment_date' => $data['appointment_date'],
                ':appointment_time' => $data['appointment_time'],
                ':appointment_id' => $data['appointment_id']
            ]);

            if ($conflictStmt->rowCount() > 0) {
                return $this->response(false, 'This time slot is already booked for the selected doctor', null);
            }

            $sql = "UPDATE appointments SET 
                    patient_id = :patient_id,
                    doctor_id = :doctor_id,
                    appointment_date = :appointment_date,
                    appointment_time = :appointment_time,
                    reason = :reason,
                    status = :status,
                    notes = :notes
                    WHERE appointment_id = :appointment_id";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':patient_id' => $data['patient_id'],
                ':doctor_id' => $data['doctor_id'],
                ':appointment_date' => $data['appointment_date'],
                ':appointment_time' => $data['appointment_time'],
                ':reason' => $data['reason'],
                ':status' => $data['status'],
                ':notes' => $data['notes'] ?? null,
                ':appointment_id' => $data['appointment_id']
            ]);

            // Log the action
            if (isset($_SESSION['user_id'])) {
                $this->logAction(
                    $_SESSION['user_id'],
                    'update',
                    'Updated appointment #' . $data['appointment_id'],
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                );
            }

            return $this->response(true, 'Appointment updated successfully', null);

        } catch (Exception $e) {
            return $this->response(false, 'Error: ' . $e->getMessage(), null);
        }
    }

    private function delete($data) {
        try {
            if (!isset($data['appointment_id'])) {
                return $this->response(false, 'Appointment ID is required', null);
            }

            $appointment_id = $data['appointment_id'];

            // Check if appointment exists
            $checkSql = "SELECT appointment_id FROM appointments WHERE appointment_id = :appointment_id";
            $checkStmt = $this->conn->prepare($checkSql);
            $checkStmt->bindParam(':appointment_id', $appointment_id, PDO::PARAM_INT);
            $checkStmt->execute();

            if ($checkStmt->rowCount() === 0) {
                return $this->response(false, 'Appointment not found', null);
            }

            // Delete appointment
            $sql = "DELETE FROM appointments WHERE appointment_id = :appointment_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':appointment_id', $appointment_id, PDO::PARAM_INT);
            $stmt->execute();

            // Log the action
            if (isset($_SESSION['user_id'])) {
                $this->logAction(
                    $_SESSION['user_id'],
                    'delete',
                    'Deleted appointment #' . $appointment_id,
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                );
            }

            return $this->response(true, 'Appointment deleted successfully', null);

        } catch (Exception $e) {
            return $this->response(false, 'Error: ' . $e->getMessage(), null);
        }
    }

    private function updateStatus($data) {
        try {
            if (!isset($data['appointment_id']) || !isset($data['status'])) {
                return $this->response(false, 'Appointment ID and status are required', null);
            }

            $validStatuses = ['pending', 'confirmed', 'completed', 'cancelled'];
            if (!in_array($data['status'], $validStatuses)) {
                return $this->response(false, 'Invalid status', null);
            }

            $sql = "UPDATE appointments SET status = :status WHERE appointment_id = :appointment_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':status' => $data['status'],
                ':appointment_id' => $data['appointment_id']
            ]);

            // Log the action
            if (isset($_SESSION['user_id'])) {
                $this->logAction(
                    $_SESSION['user_id'],
                    'update',
                    'Updated appointment #' . $data['appointment_id'] . ' status to ' . $data['status'],
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                );
            }

            return $this->response(true, 'Appointment status updated successfully', null);

        } catch (Exception $e) {
            return $this->response(false, 'Error: ' . $e->getMessage(), null);
        }
    }

    private function getStatistics($data) {
        try {
            $stats = [];
            
            // Check if filtering by doctor
            $doctorFilter = "";
            $params = [];
            if (isset($data['doctor_id'])) {
                $doctorFilter = " WHERE doctor_id = :doctor_id";
                $params = [':doctor_id' => $data['doctor_id']];
            }

            // Total appointments
            $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM appointments" . $doctorFilter);
            $stmt->execute($params);
            $stats['total_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Today's appointments
            $whereClause = isset($data['doctor_id']) ? " WHERE DATE(appointment_date) = CURDATE() AND doctor_id = :doctor_id" : " WHERE DATE(appointment_date) = CURDATE()";
            $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM appointments" . $whereClause);
            if (isset($data['doctor_id'])) {
                $stmt->execute($params);
            } else {
                $stmt->execute();
            }
            $stats['today_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Pending appointments
            $whereClause = isset($data['doctor_id']) ? " WHERE status = 'pending' AND doctor_id = :doctor_id" : " WHERE status = 'pending'";
            $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM appointments" . $whereClause);
            if (isset($data['doctor_id'])) {
                $stmt->execute($params);
            } else {
                $stmt->execute();
            }
            $stats['pending_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Confirmed appointments
            $whereClause = isset($data['doctor_id']) ? " WHERE status = 'confirmed' AND doctor_id = :doctor_id" : " WHERE status = 'confirmed'";
            $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM appointments" . $whereClause);
            if (isset($data['doctor_id'])) {
                $stmt->execute($params);
            } else {
                $stmt->execute();
            }
            $stats['confirmed_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Completed appointments
            $whereClause = isset($data['doctor_id']) ? " WHERE status = 'completed' AND doctor_id = :doctor_id" : " WHERE status = 'completed'";
            $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM appointments" . $whereClause);
            if (isset($data['doctor_id'])) {
                $stmt->execute($params);
            } else {
                $stmt->execute();
            }
            $stats['completed_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Cancelled appointments
            $whereClause = isset($data['doctor_id']) ? " WHERE status = 'cancelled' AND doctor_id = :doctor_id" : " WHERE status = 'cancelled'";
            $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM appointments" . $whereClause);
            if (isset($data['doctor_id'])) {
                $stmt->execute($params);
            } else {
                $stmt->execute();
            }
            $stats['cancelled_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Return stats directly in the response, not through the response() method
            return json_encode([
                'success' => true,
                'message' => 'Statistics retrieved successfully',
                'data' => $stats
            ]);

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
                $response['appointments'] = $data;
            } else {
                $response[is_array($data) && isset($data[0]) ? 'appointments' : 'appointment'] = $data;
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
$api = new AppointmentsAPI();
echo $api->handleRequest();
?>
