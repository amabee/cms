<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

include('conn.php');

class BackupManager
{
  private $conn;
  private $backupDir;

  public function __construct()
  {
    $this->conn = DatabaseConnection::getInstance()->getConnection();
    $this->backupDir = __DIR__ . '/backups/';

    // Create backup directory if it doesn't exist
    if (!is_dir($this->backupDir)) {
      mkdir($this->backupDir, 0755, true);
    }
  }

  public function createBackup($json)
  {
    $data = json_decode($json, true);
    $adminUserId = $data['admin_user_id'] ?? 1;

    try {
      // Get database name from connection
      $dbName = $this->conn->query('SELECT DATABASE()')->fetchColumn();

      // Create backup filename with timestamp
      $timestamp = date('Y-m-d_H-i-s');
      $backupFile = $this->backupDir . "backup_{$dbName}_{$timestamp}.sql";

      // Get all tables
      $tables = [];
      $result = $this->conn->query('SHOW TABLES');
      while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
      }

      // Start backup content
      $backup = "-- Database Backup\n";
      $backup .= "-- Database: {$dbName}\n";
      $backup .= "-- Date: " . date('Y-m-d H:i:s') . "\n\n";
      $backup .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

      // Loop through tables
      foreach ($tables as $table) {
        // Get CREATE TABLE statement
        $createTable = $this->conn->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
        $backup .= "-- Table: {$table}\n";
        $backup .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $backup .= $createTable['Create Table'] . ";\n\n";

        // Get table data
        $rows = $this->conn->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rows)) {
          $backup .= "-- Data for table: {$table}\n";

          foreach ($rows as $row) {
            $columns = array_keys($row);
            $values = array_map(function ($value) {
              return $value === null ? 'NULL' : $this->conn->quote($value);
            }, array_values($row));

            $backup .= "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n";
          }
          $backup .= "\n";
        }
      }

      $backup .= "SET FOREIGN_KEY_CHECKS=1;\n";

      // Save backup to file
      file_put_contents($backupFile, $backup);

      // Get file size
      $fileSize = filesize($backupFile);
      $fileSizeMB = round($fileSize / 1024 / 1024, 2);

      // Log the action
      $this->logAction($adminUserId, 'backup', "Created database backup: " . basename($backupFile), $_SERVER['REMOTE_ADDR'] ?? 'unknown');

      return json_encode([
        'success' => true,
        'message' => 'Backup created successfully',
        'data' => [
          'filename' => basename($backupFile),
          'size' => $fileSizeMB . ' MB',
          'date' => date('M d, Y h:i A'),
          'path' => $backupFile
        ]
      ]);

    } catch (PDOException $e) {
      return json_encode(['error' => 'Backup failed: ' . $e->getMessage()]);
    }
  }

  public function listBackups()
  {
    try {
      $backups = [];
      $files = glob($this->backupDir . 'backup_*.sql');

      // Sort by modification time (newest first)
      usort($files, function ($a, $b) {
        return filemtime($b) - filemtime($a);
      });

      foreach ($files as $file) {
        $fileSize = filesize($file);
        $fileSizeMB = round($fileSize / 1024 / 1024, 2);

        $backups[] = [
          'filename' => basename($file),
          'size' => $fileSizeMB . ' MB',
          'date' => date('M d, Y h:i A', filemtime($file)),
          'timestamp' => filemtime($file)
        ];
      }

      return json_encode([
        'success' => true,
        'data' => $backups
      ]);

    } catch (Exception $e) {
      return json_encode(['error' => 'Failed to list backups: ' . $e->getMessage()]);
    }
  }

  public function downloadBackup($json)
  {
    $data = json_decode($json, true);
    $filename = $data['filename'] ?? '';

    if (empty($filename)) {
      return json_encode(['error' => 'Filename is required']);
    }

    $filepath = $this->backupDir . $filename;

    if (!file_exists($filepath)) {
      return json_encode(['error' => 'Backup file not found']);
    }

    // Set headers for download
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
  }

  public function deleteBackup($json)
  {
    $data = json_decode($json, true);
    $filename = $data['filename'] ?? '';
    $adminUserId = $data['admin_user_id'] ?? 1;

    if (empty($filename)) {
      return json_encode(['error' => 'Filename is required']);
    }

    $filepath = $this->backupDir . $filename;

    if (!file_exists($filepath)) {
      return json_encode(['error' => 'Backup file not found']);
    }

    try {
      unlink($filepath);

      // Log the action
      $this->logAction($adminUserId, 'backup', "Deleted backup: {$filename}", $_SERVER['REMOTE_ADDR'] ?? 'unknown');

      return json_encode([
        'success' => true,
        'message' => 'Backup deleted successfully'
      ]);

    } catch (Exception $e) {
      return json_encode(['error' => 'Failed to delete backup: ' . $e->getMessage()]);
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

$api = new BackupManager();

if (in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
  $operation = $_REQUEST['operation'] ?? 'list';
  $json = $_REQUEST['json'] ?? '';

  switch ($operation) {
    case 'create':
      echo $api->createBackup($json);
      break;
    case 'list':
      echo $api->listBackups();
      break;
    case 'download':
      echo $api->downloadBackup($json);
      break;
    case 'delete':
      echo $api->deleteBackup($json);
      break;
    default:
      echo json_encode(['error' => 'Invalid Operation']);
      break;
  }
} else {
  echo json_encode(['error' => 'Invalid Request Method']);
}

?>

