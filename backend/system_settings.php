<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

include('conn.php');

class SystemSettings
{
    private $conn;

    public function __construct()
    {
        $this->conn = DatabaseConnection::getInstance()->getConnection();
    }

    public function all()
    {
        try {
            $stmt = $this->conn->prepare('SELECT setting_key, setting_value FROM system_settings');
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $out = [];
            foreach ($rows as $r) {
                $out[$r['setting_key']] = $r['setting_value'];
            }
            return json_encode(['success' => true, 'data' => $out]);
        } catch (PDOException $e) {
            return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
        }
    }

    public function update($json)
    {
        $data = json_decode($json, true);
        
        if (empty($data) || !is_array($data)) {
            return json_encode(['error' => 'Invalid settings data']);
        }

        $this->conn->beginTransaction();

        try {
            foreach ($data as $key => $value) {
                // Check if setting exists
                $checkStmt = $this->conn->prepare('SELECT setting_id FROM system_settings WHERE setting_key = :key');
                $checkStmt->execute([':key' => $key]);

                if ($checkStmt->rowCount() > 0) {
                    // Update existing setting
                    $stmt = $this->conn->prepare('UPDATE system_settings SET setting_value = :value WHERE setting_key = :key');
                } else {
                    // Insert new setting
                    $stmt = $this->conn->prepare('INSERT INTO system_settings (setting_key, setting_value) VALUES (:key, :value)');
                }

                $stmt->execute([
                    ':key' => $key,
                    ':value' => is_array($value) ? json_encode($value) : $value
                ]);
            }

            // Log the action
            $this->logAction($data['admin_user_id'] ?? 1, 'settings', 'Updated system settings', $_SERVER['REMOTE_ADDR'] ?? 'unknown');

            $this->conn->commit();

            return json_encode(['success' => true, 'message' => 'Settings updated successfully']);
        } catch (PDOException $e) {
            $this->conn->rollBack();
            return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
        }
    }

    public function get($json)
    {
        $data = json_decode($json, true);
        $key = $data['setting_key'] ?? null;

        if (!$key) {
            return json_encode(['error' => 'Setting key is required']);
        }

        try {
            $stmt = $this->conn->prepare('SELECT setting_value FROM system_settings WHERE setting_key = :key');
            $stmt->execute([':key' => $key]);
            $value = $stmt->fetchColumn();

            if ($value === false) {
                return json_encode(['error' => 'Setting not found']);
            }

            return json_encode(['success' => true, 'value' => $value]);
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

$api = new SystemSettings();

if (in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
    $operation = $_REQUEST['operation'] ?? 'all';
    $json = $_REQUEST['json'] ?? '';

    switch ($operation) {
        case 'all':
            echo $api->all();
            break;
        case 'get':
            echo $api->get($json);
            break;
        case 'update':
            echo $api->update($json);
            break;
        default:
            echo json_encode(['error' => 'Invalid Operation']);
            break;
    }
} else {
    echo json_encode(['error' => 'Invalid Request Method']);
}

?>
