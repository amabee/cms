<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once 'conn.php';

$db = DatabaseConnection::getInstance();
$conn = $db->getConnection();

$operation = $_POST['operation'] ?? $_GET['operation'] ?? null;

try {
    switch ($operation) {
        case 'getAll':
            getAll($conn);
            break;
        
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid operation']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function getAll($conn) {
    try {
        $sql = "SELECT insurance_id, company_name, policy_no, coverage_details 
                FROM insurance 
                ORDER BY company_name ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $insurances = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $insurances
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}
?>
