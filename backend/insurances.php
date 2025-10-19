<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

include(__DIR__ . '/../conn.php');

class Insurances
{
    private $conn;

    public function __construct()
    {
        $this->conn = DatabaseConnection::getInstance()->getConnection();
    }

    public function list()
    {
        try {
            $stmt = $this->conn->prepare('SELECT insurance_id, company_name, policy_no FROM insurance ORDER BY company_name');
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode(['success' => true, 'data' => $rows]);
        } catch (PDOException $e) {
            return json_encode(['error' => 'Database Error: ' . $e->getMessage()]);
        }
    }
}

$api = new Insurances();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo $api->list();
} else {
    echo json_encode(['error' => 'Invalid Request Method']);
}

?>
