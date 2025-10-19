<?php
require_once 'conn.php';
header('Content-Type: application/json');

class MedicalRecordsAPI {
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

        try {
            switch($action) {
                case 'getAll':
                    return $this->getAll($data);
                case 'getById':
                    return $this->getById($data);
                case 'getByPatient':
                    return $this->getByPatient($data);
                case 'create':
                    return $this->create($data);
                case 'update':
                    return $this->update($data);
                case 'delete':
                    return $this->delete($data);
                case 'getStatistics':
                    return $this->getStatistics($data);
                default:
                    return $this->response(false, 'Invalid action', null);
            }
        } catch (Exception $e) {
            return $this->response(false, 'Error: ' . $e->getMessage(), null);
        }
    }

    private function getAll($data) {
        $search = $data['search'] ?? '';
        $page = isset($data['page']) ? (int)$data['page'] : 1;
        $limit = isset($data['limit']) ? (int)$data['limit'] : 10;
        $offset = ($page - 1) * $limit;

        $sql = "SELECT 
                    mr.*,
                    CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                    p.patient_code,
                    CONCAT(up.first_name, ' ', up.last_name) as doctor_name,
                    doc.specialization,
                    a.appointment_date,
                    a.status as appointment_status
                FROM medical_records mr
                LEFT JOIN patients p ON mr.patient_id = p.patient_id
                LEFT JOIN doctors doc ON mr.doctor_id = doc.doctor_id
                LEFT JOIN users u ON doc.user_id = u.user_id
                LEFT JOIN user_profiles up ON u.user_id = up.user_id
                LEFT JOIN appointments a ON mr.appointment_id = a.appointment_id
                WHERE 1=1";

        if (!empty($search)) {
            $sql .= " AND (p.first_name LIKE :search 
                      OR p.last_name LIKE :search 
                      OR p.patient_code LIKE :search
                      OR up.first_name LIKE :search
                      OR up.last_name LIKE :search
                      OR mr.diagnosis LIKE :search)";
        }

        $sql .= " ORDER BY mr.record_date DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($sql);
        
        if (!empty($search)) {
            $searchParam = "%$search%";
            $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
        }
        
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM medical_records mr
                     LEFT JOIN patients p ON mr.patient_id = p.patient_id
                     LEFT JOIN doctors doc ON mr.doctor_id = doc.doctor_id
                     LEFT JOIN users d ON doc.user_id = d.user_id
                     WHERE 1=1";
        
        if (!empty($search)) {
            $countSql .= " AND (p.first_name LIKE :search 
                          OR p.last_name LIKE :search 
                          OR p.patient_code LIKE :search
                          OR d.first_name LIKE :search
                          OR d.last_name LIKE :search
                          OR mr.diagnosis LIKE :search)";
        }

        $countStmt = $this->conn->prepare($countSql);
        if (!empty($search)) {
            $countStmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        return $this->response(true, 'Medical records retrieved successfully', $records, [
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit
        ]);
    }

    private function getById($data) {
        $record_id = $data['record_id'] ?? 0;

        $sql = "SELECT 
                    mr.*,
                    CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                    p.patient_code,
                    p.date_of_birth,
                    p.gender,
                    p.blood_type,
                    CONCAT(up.first_name, ' ', up.last_name) as doctor_name,
                    doc.specialization,
                    a.appointment_date,
                    a.appointment_time,
                    a.status as appointment_status
                FROM medical_records mr
                LEFT JOIN patients p ON mr.patient_id = p.patient_id
                LEFT JOIN doctors doc ON mr.doctor_id = doc.doctor_id
                LEFT JOIN users u ON doc.user_id = u.user_id
                LEFT JOIN user_profiles up ON u.user_id = up.user_id
                LEFT JOIN appointments a ON mr.appointment_id = a.appointment_id
                WHERE mr.record_id = :record_id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':record_id', $record_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($record) {
            // Get prescriptions
            $prescSql = "SELECT * FROM prescriptions WHERE record_id = :record_id";
            $prescStmt = $this->conn->prepare($prescSql);
            $prescStmt->bindParam(':record_id', $record_id, PDO::PARAM_INT);
            $prescStmt->execute();
            $record['prescriptions'] = $prescStmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response(true, 'Medical record retrieved successfully', $record);
        }

        return $this->response(false, 'Medical record not found', null);
    }

    private function getByPatient($data) {
        $patient_id = $data['patient_id'] ?? 0;

        $sql = "SELECT 
                    mr.*,
                    CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                    CONCAT(up.first_name, ' ', up.last_name) as doctor_name,
                    doc.specialization,
                    a.appointment_date
                FROM medical_records mr
                LEFT JOIN patients p ON mr.patient_id = p.patient_id
                LEFT JOIN doctors doc ON mr.doctor_id = doc.doctor_id
                LEFT JOIN users u ON doc.user_id = u.user_id
                LEFT JOIN user_profiles up ON u.user_id = up.user_id
                LEFT JOIN appointments a ON mr.appointment_id = a.appointment_id
                WHERE mr.patient_id = :patient_id
                ORDER BY mr.record_date DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->response(true, 'Patient medical records retrieved successfully', $records);
    }

    private function create($data) {
        try {
            $this->conn->beginTransaction();

            $sql = "INSERT INTO medical_records (appointment_id, patient_id, doctor_id, diagnosis, treatment, notes) 
                    VALUES (:appointment_id, :patient_id, :doctor_id, :diagnosis, :treatment, :notes)";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':appointment_id', $data['appointment_id'], PDO::PARAM_INT);
            $stmt->bindParam(':patient_id', $data['patient_id'], PDO::PARAM_INT);
            $stmt->bindParam(':doctor_id', $data['doctor_id'], PDO::PARAM_INT);
            $stmt->bindParam(':diagnosis', $data['diagnosis'], PDO::PARAM_STR);
            $stmt->bindParam(':treatment', $data['treatment'], PDO::PARAM_STR);
            $stmt->bindParam(':notes', $data['notes'], PDO::PARAM_STR);
            $stmt->execute();

            $record_id = $this->conn->lastInsertId();

            // Add prescriptions if provided
            if (!empty($data['prescriptions']) && is_array($data['prescriptions'])) {
                foreach ($data['prescriptions'] as $prescription) {
                    $prescSql = "INSERT INTO prescriptions (record_id, medicine_name, dosage, frequency, duration, instructions) 
                                VALUES (:record_id, :medicine_name, :dosage, :frequency, :duration, :instructions)";
                    
                    $prescStmt = $this->conn->prepare($prescSql);
                    $prescStmt->bindParam(':record_id', $record_id, PDO::PARAM_INT);
                    $prescStmt->bindParam(':medicine_name', $prescription['medicine_name'], PDO::PARAM_STR);
                    $prescStmt->bindParam(':dosage', $prescription['dosage'], PDO::PARAM_STR);
                    $prescStmt->bindParam(':frequency', $prescription['frequency'], PDO::PARAM_STR);
                    $prescStmt->bindParam(':duration', $prescription['duration'], PDO::PARAM_STR);
                    $prescStmt->bindParam(':instructions', $prescription['instructions'], PDO::PARAM_STR);
                    $prescStmt->execute();
                }
            }

            // Log the action
            if (isset($_SESSION['user_id'])) {
                $this->logAction(
                    $_SESSION['user_id'],
                    'create',
                    'Created medical record #' . $record_id . ' for patient ID: ' . $data['patient_id'],
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                );
            }

            $this->conn->commit();
            return $this->response(true, 'Medical record created successfully', ['record_id' => $record_id]);

        } catch (Exception $e) {
            $this->conn->rollBack();
            return $this->response(false, 'Error creating medical record: ' . $e->getMessage(), null);
        }
    }

    private function update($data) {
        try {
            $this->conn->beginTransaction();

            $sql = "UPDATE medical_records SET 
                    diagnosis = :diagnosis,
                    treatment = :treatment,
                    notes = :notes
                    WHERE record_id = :record_id";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':record_id', $data['record_id'], PDO::PARAM_INT);
            $stmt->bindParam(':diagnosis', $data['diagnosis'], PDO::PARAM_STR);
            $stmt->bindParam(':treatment', $data['treatment'], PDO::PARAM_STR);
            $stmt->bindParam(':notes', $data['notes'], PDO::PARAM_STR);
            $stmt->execute();

            // Update prescriptions - delete old ones and insert new ones
            if (isset($data['prescriptions'])) {
                $deleteSql = "DELETE FROM prescriptions WHERE record_id = :record_id";
                $deleteStmt = $this->conn->prepare($deleteSql);
                $deleteStmt->bindParam(':record_id', $data['record_id'], PDO::PARAM_INT);
                $deleteStmt->execute();

                if (!empty($data['prescriptions']) && is_array($data['prescriptions'])) {
                    foreach ($data['prescriptions'] as $prescription) {
                        $prescSql = "INSERT INTO prescriptions (record_id, medicine_name, dosage, frequency, duration, instructions) 
                                    VALUES (:record_id, :medicine_name, :dosage, :frequency, :duration, :instructions)";
                        
                        $prescStmt = $this->conn->prepare($prescSql);
                        $prescStmt->bindParam(':record_id', $data['record_id'], PDO::PARAM_INT);
                        $prescStmt->bindParam(':medicine_name', $prescription['medicine_name'], PDO::PARAM_STR);
                        $prescStmt->bindParam(':dosage', $prescription['dosage'], PDO::PARAM_STR);
                        $prescStmt->bindParam(':frequency', $prescription['frequency'], PDO::PARAM_STR);
                        $prescStmt->bindParam(':duration', $prescription['duration'], PDO::PARAM_STR);
                        $prescStmt->bindParam(':instructions', $prescription['instructions'], PDO::PARAM_STR);
                        $prescStmt->execute();
                    }
                }
            }

            // Log the action
            if (isset($_SESSION['user_id'])) {
                $this->logAction(
                    $_SESSION['user_id'],
                    'update',
                    'Updated medical record #' . $data['record_id'],
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                );
            }

            $this->conn->commit();
            return $this->response(true, 'Medical record updated successfully', null);

        } catch (Exception $e) {
            $this->conn->rollBack();
            return $this->response(false, 'Error updating medical record: ' . $e->getMessage(), null);
        }
    }

    private function delete($data) {
        try {
            $record_id = $data['record_id'] ?? 0;

            // Delete prescriptions first (foreign key constraint)
            $deletePrescSql = "DELETE FROM prescriptions WHERE record_id = :record_id";
            $deletePrescStmt = $this->conn->prepare($deletePrescSql);
            $deletePrescStmt->bindParam(':record_id', $record_id, PDO::PARAM_INT);
            $deletePrescStmt->execute();

            // Delete medical record
            $sql = "DELETE FROM medical_records WHERE record_id = :record_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':record_id', $record_id, PDO::PARAM_INT);
            $stmt->execute();

            // Log the action
            if (isset($_SESSION['user_id'])) {
                $this->logAction(
                    $_SESSION['user_id'],
                    'delete',
                    'Deleted medical record #' . $record_id,
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                );
            }

            return $this->response(true, 'Medical record deleted successfully', null);

        } catch (Exception $e) {
            return $this->response(false, 'Error deleting medical record: ' . $e->getMessage(), null);
        }
    }

    private function getStatistics($data) {
        $stats = [];

        // Total records
        $totalSql = "SELECT COUNT(*) as total FROM medical_records";
        $totalStmt = $this->conn->prepare($totalSql);
        $totalStmt->execute();
        $stats['total_records'] = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Records this month
        $monthSql = "SELECT COUNT(*) as total FROM medical_records 
                     WHERE MONTH(record_date) = MONTH(CURRENT_DATE()) 
                     AND YEAR(record_date) = YEAR(CURRENT_DATE())";
        $monthStmt = $this->conn->prepare($monthSql);
        $monthStmt->execute();
        $stats['this_month'] = $monthStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Records today
        $todaySql = "SELECT COUNT(*) as total FROM medical_records WHERE DATE(record_date) = CURDATE()";
        $todayStmt = $this->conn->prepare($todaySql);
        $todayStmt->execute();
        $stats['today'] = $todayStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Total patients with records
        $patientsSql = "SELECT COUNT(DISTINCT patient_id) as total FROM medical_records";
        $patientsStmt = $this->conn->prepare($patientsSql);
        $patientsStmt->execute();
        $stats['total_patients'] = $patientsStmt->fetch(PDO::FETCH_ASSOC)['total'];

        return $this->response(true, 'Statistics retrieved successfully', $stats);
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
            'message' => $message,
            'data' => $data
        ];
        
        if (!empty($extra)) {
            $response = array_merge($response, $extra);
        }
        
        return json_encode($response);
    }
}

// Handle the request
session_start();
$api = new MedicalRecordsAPI();
echo $api->handleRequest();
?>
