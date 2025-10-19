<?php
/**
 * System Logger - Centralized logging for all CRUD operations
 * 
 * This class provides a standardized way to log all system activities
 * including create, update, delete, and other actions across all modules.
 * 
 * Usage example:
 * SystemLogger::log($user_id, 'create_doctor', 'Created doctor: Dr. John Smith');
 */

class SystemLogger
{

  public static function log($user_id, $action, $description, $ip_address = null)
  {
    try {
      if ($ip_address === null) {
        $ip_address = self::getClientIP();
      }

      $conn = DatabaseConnection::getInstance()->getConnection();
      
      $stmt = $conn->prepare("INSERT INTO system_logs (user_id, action, description, ip_address, created_at) 
                              VALUES (:user_id, :action, :description, :ip_address, NOW())");
      
      $result = $stmt->execute([
        ':user_id' => $user_id,
        ':action' => $action,
        ':description' => $description,
        ':ip_address' => $ip_address
      ]);

      return $result;
    } catch (PDOException $e) {
      // Log to error log but don't break the main operation
      error_log("System Logger Error: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Log a create action
   */
  public static function logCreate($user_id, $module, $item_name, $details = '')
  {
    $action = "create_" . strtolower($module);
    $description = "Created $module: $item_name";
    if ($details) {
      $description .= " - $details";
    }
    return self::log($user_id, $action, $description);
  }

  /**
   * Log an update action
   */
  public static function logUpdate($user_id, $module, $item_name, $changes = '')
  {
    $action = "update_" . strtolower($module);
    $description = "Updated $module: $item_name";
    if ($changes) {
      $description .= " - Changes: $changes";
    }
    return self::log($user_id, $action, $description);
  }

  /**
   * Log a delete action
   */
  public static function logDelete($user_id, $module, $item_name, $reason = '')
  {
    $action = "delete_" . strtolower($module);
    $description = "Deleted $module: $item_name";
    if ($reason) {
      $description .= " - Reason: $reason";
    }
    return self::log($user_id, $action, $description);
  }

  /**
   * Log a view action (for sensitive data access)
   */
  public static function logView($user_id, $module, $item_name)
  {
    $action = "view_" . strtolower($module);
    $description = "Viewed $module: $item_name";
    return self::log($user_id, $action, $description);
  }

  /**
   * Log a login action
   */
  public static function logLogin($user_id, $username, $success = true)
  {
    $action = $success ? 'login_success' : 'login_failed';
    $description = $success 
      ? "User logged in: $username" 
      : "Failed login attempt for: $username";
    return self::log($user_id, $action, $description);
  }

  /**
   * Log a logout action
   */
  public static function logLogout($user_id, $username)
  {
    $action = 'logout';
    $description = "User logged out: $username";
    return self::log($user_id, $action, $description);
  }

  /**
   * Log a settings change
   */
  public static function logSettings($user_id, $setting_name, $old_value = null, $new_value = null)
  {
    $action = 'settings_change';
    $description = "Changed setting: $setting_name";
    if ($old_value !== null && $new_value !== null) {
      $description .= " (from: $old_value to: $new_value)";
    }
    return self::log($user_id, $action, $description);
  }

  /**
   * Log a backup action
   */
  public static function logBackup($user_id, $backup_type, $file_name = '')
  {
    $action = 'backup_' . strtolower($backup_type);
    $description = "Backup $backup_type";
    if ($file_name) {
      $description .= ": $file_name";
    }
    return self::log($user_id, $action, $description);
  }

  /**
   * Log an export action
   */
  public static function logExport($user_id, $module, $format = 'CSV', $record_count = 0)
  {
    $action = 'export_' . strtolower($module);
    $description = "Exported $module data to $format";
    if ($record_count > 0) {
      $description .= " ($record_count records)";
    }
    return self::log($user_id, $action, $description);
  }

  /**
   * Log an import action
   */
  public static function logImport($user_id, $module, $format = 'CSV', $record_count = 0)
  {
    $action = 'import_' . strtolower($module);
    $description = "Imported $module data from $format";
    if ($record_count > 0) {
      $description .= " ($record_count records)";
    }
    return self::log($user_id, $action, $description);
  }

  /**
   * Log a system error
   */
  public static function logError($user_id, $error_message, $context = '')
  {
    $action = 'system_error';
    $description = "Error: $error_message";
    if ($context) {
      $description .= " - Context: $context";
    }
    return self::log($user_id, $action, $description);
  }

  /**
   * Get client IP address (handles proxies and load balancers)
   */
  private static function getClientIP()
  {
    $ip_keys = [
      'HTTP_CLIENT_IP',
      'HTTP_X_FORWARDED_FOR',
      'HTTP_X_FORWARDED',
      'HTTP_X_CLUSTER_CLIENT_IP',
      'HTTP_FORWARDED_FOR',
      'HTTP_FORWARDED',
      'REMOTE_ADDR'
    ];

    foreach ($ip_keys as $key) {
      if (array_key_exists($key, $_SERVER) === true) {
        foreach (explode(',', $_SERVER[$key]) as $ip) {
          $ip = trim($ip);
          
          // Validate IP
          if (filter_var($ip, FILTER_VALIDATE_IP, 
              FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
            return $ip;
          }
        }
      }
    }

    return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
  }

  /**
   * Batch log multiple actions (useful for bulk operations)
   */
  public static function logBatch($user_id, $actions)
  {
    $conn = DatabaseConnection::getInstance()->getConnection();
    $conn->beginTransaction();
    
    try {
      $stmt = $conn->prepare("INSERT INTO system_logs (user_id, action, description, ip_address, created_at) 
                              VALUES (:user_id, :action, :description, :ip_address, NOW())");
      
      $ip_address = self::getClientIP();
      
      foreach ($actions as $action_data) {
        $stmt->execute([
          ':user_id' => $user_id,
          ':action' => $action_data['action'],
          ':description' => $action_data['description'],
          ':ip_address' => $ip_address
        ]);
      }
      
      $conn->commit();
      return true;
    } catch (PDOException $e) {
      $conn->rollBack();
      error_log("System Logger Batch Error: " . $e->getMessage());
      return false;
    }
  }
}
?>
