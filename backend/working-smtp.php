<?php
/**
 * Working SMTP Email Client for Gmail
 * This implementation properly handles Gmail's SMTP authentication
 */

class WorkingSMTPClient {
    private $host;
    private $port;
    private $username;
    private $password;
    private $timeout;
    private $socket;
    
    public function __construct($host, $port, $username, $password, $timeout = 30) {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->timeout = $timeout;
    }
    
    public function sendEmail($to, $subject, $message, $fromName = 'Healthcare System') {
        try {
            $this->connect();
            $this->authenticate();
            $this->sendMessage($to, $subject, $message, $fromName);
            $this->disconnect();
            return true;
        } catch (Exception $e) {
            error_log("SMTP Error: " . $e->getMessage());
            if ($this->socket) {
                fclose($this->socket);
            }
            return false;
        }
    }
    
    private function connect() {
        // Create connection context for SSL/TLS
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);
        
        // Connect to SMTP server
        $this->socket = stream_socket_client(
            "tcp://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );
        
        if (!$this->socket) {
            throw new Exception("Cannot connect to SMTP server: $errstr ($errno)");
        }
        
        // Read greeting
        $response = $this->readResponse();
        if (!$this->checkResponse($response, '220')) {
            throw new Exception("Server greeting failed: $response");
        }
    }
    
    private function authenticate() {
        // Send EHLO
        $this->sendCommand("EHLO localhost");
        $response = $this->readResponse();
        if (!$this->checkResponse($response, '250')) {
            throw new Exception("EHLO failed: $response");
        }
        
        // Start TLS
        $this->sendCommand("STARTTLS");
        $response = $this->readResponse();
        if (!$this->checkResponse($response, '220')) {
            throw new Exception("STARTTLS failed: $response");
        }
        
        // Enable TLS encryption
        if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new Exception("Failed to enable TLS encryption");
        }
        
        // Send EHLO again after TLS
        $this->sendCommand("EHLO localhost");
        $response = $this->readResponse();
        if (!$this->checkResponse($response, '250')) {
            throw new Exception("EHLO after TLS failed: $response");
        }
        
        // Authenticate with LOGIN method
        $this->sendCommand("AUTH LOGIN");
        $response = $this->readResponse();
        if (!$this->checkResponse($response, '334')) {
            throw new Exception("AUTH LOGIN failed: $response");
        }
        
        // Send username
        $this->sendCommand(base64_encode($this->username));
        $response = $this->readResponse();
        if (!$this->checkResponse($response, '334')) {
            throw new Exception("Username authentication failed: $response");
        }
        
        // Send password
        $this->sendCommand(base64_encode($this->password));
        $response = $this->readResponse();
        if (!$this->checkResponse($response, '235')) {
            throw new Exception("Password authentication failed: $response");
        }
    }
    
    private function sendMessage($to, $subject, $message, $fromName) {
        // MAIL FROM
        $this->sendCommand("MAIL FROM:<{$this->username}>");
        $response = $this->readResponse();
        if (!$this->checkResponse($response, '250')) {
            throw new Exception("MAIL FROM failed: $response");
        }
        
        // RCPT TO
        $this->sendCommand("RCPT TO:<$to>");
        $response = $this->readResponse();
        if (!$this->checkResponse($response, '250')) {
            throw new Exception("RCPT TO failed: $response");
        }
        
        // DATA
        $this->sendCommand("DATA");
        $response = $this->readResponse();
        if (!$this->checkResponse($response, '354')) {
            throw new Exception("DATA command failed: $response");
        }
        
        // Send email headers and body
        $emailData = $this->buildEmailData($to, $subject, $message, $fromName);
        $this->sendCommand($emailData);
        $this->sendCommand(".");
        
        $response = $this->readResponse();
        if (!$this->checkResponse($response, '250')) {
            throw new Exception("Message sending failed: $response");
        }
    }
    
    private function buildEmailData($to, $subject, $message, $fromName) {
        $headers = [];
        $headers[] = "From: $fromName <{$this->username}>";
        $headers[] = "To: $to";
        $headers[] = "Subject: $subject";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: text/html; charset=UTF-8";
        $headers[] = "Date: " . date('r');
        $headers[] = "Message-ID: <" . uniqid() . "@{$this->host}>";
        
        return implode("\r\n", $headers) . "\r\n\r\n" . $message;
    }
    
    private function disconnect() {
        $this->sendCommand("QUIT");
        if ($this->socket) {
            fclose($this->socket);
        }
    }
    
    private function sendCommand($command) {
        fwrite($this->socket, $command . "\r\n");
    }
    
    private function readResponse() {
        $response = '';
        while (($line = fgets($this->socket, 512)) !== false) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') {
                break;
            }
        }
        return trim($response);
    }
    
    private function checkResponse($response, $expectedCode) {
        return substr($response, 0, 3) == $expectedCode;
    }
}

// Update the EmailService class to use the working SMTP client
class ImprovedEmailService {
    
    public static function sendPatientRegistrationEmail($patientData) {
        try {
            // Get hospital information from database
            $hospitalInfo = self::getHospitalInfo();
            
            // Create SMTP client
            $smtp = new WorkingSMTPClient(
                SMTPConfig::SMTP_HOST,
                SMTPConfig::SMTP_PORT,
                SMTPConfig::SMTP_USERNAME,
                SMTPConfig::SMTP_PASSWORD
            );
            
            $to = $patientData['email'];
            $subject = "Welcome to " . $hospitalInfo['hospital_name'] . " - Registration Successful";
            $message = self::getPatientWelcomeTemplate($patientData, $hospitalInfo);
            $fromName = SMTPConfig::FROM_NAME;
            
            $result = $smtp->sendEmail($to, $subject, $message, $fromName);
            
            if ($result) {
                error_log("✅ Registration email sent successfully to: " . $to);
                return true;
            } else {
                error_log("❌ Failed to send registration email to: " . $to);
                return false;
            }
            
        } catch (Exception $e) {
            error_log("Email service error: " . $e->getMessage());
            return false;
        }
    }
    
    public static function sendTestEmail($to, $testMessage = "This is a test email from your healthcare system.") {
        try {
            $smtp = new WorkingSMTPClient(
                SMTPConfig::SMTP_HOST,
                SMTPConfig::SMTP_PORT,
                SMTPConfig::SMTP_USERNAME,
                SMTPConfig::SMTP_PASSWORD
            );
            
            $subject = "Test Email from " . SMTPConfig::FROM_NAME;
            
            $message = "
            <html>
            <body style='font-family: Arial, sans-serif; padding: 20px;'>
                <div style='background: #f8f9fa; padding: 20px; border-radius: 10px;'>
                    <h2 style='color: #28a745;'>🏥 Email Test Successful!</h2>
                    <p style='font-size: 16px;'>$testMessage</p>
                    
                    <div style='background: white; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                        <h3>📧 SMTP Configuration</h3>
                        <ul>
                            <li><strong>Server:</strong> " . SMTPConfig::SMTP_HOST . ":" . SMTPConfig::SMTP_PORT . "</li>
                            <li><strong>From:</strong> " . SMTPConfig::FROM_EMAIL . "</li>
                            <li><strong>Security:</strong> " . SMTPConfig::SMTP_SECURITY . "</li>
                        </ul>
                    </div>
                    
                    <p style='color: #28a745; font-weight: bold;'>✅ If you received this email, your SMTP configuration is working correctly!</p>
                    
                    <hr style='margin: 20px 0;'>
                    <p style='color: #666; font-size: 12px;'>Sent at: " . date('Y-m-d H:i:s') . "</p>
                </div>
            </body>
            </html>";
            
            return $smtp->sendEmail($to, $subject, $message, SMTPConfig::FROM_NAME);
            
        } catch (Exception $e) {
            error_log("Test email error: " . $e->getMessage());
            return false;
        }
    }
    
    private static function getHospitalInfo() {
        try {
            include_once('conn.php');
            $conn = DatabaseConnection::getInstance()->getConnection();
            
            $stmt = $conn->prepare('SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                'hospital_name', 'hospital_address', 'hospital_phone', 'hospital_website',
                'hospital_email', 'hospital_fax', 'hospital_description', 'hospital_logo_url'
            ]);
            
            $settings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            
            return array_merge([
                'hospital_name' => 'Healthcare Management Clinic',
                'hospital_address' => '123 Healthcare St, Medical City, HC 12345',
                'hospital_phone' => '+1 (555) 123-4567',
                'hospital_website' => 'https://yourhospital.com',
                'hospital_email' => 'info@yourhospital.com',
                'hospital_fax' => '+1 (555) 123-4568',
                'hospital_description' => 'Providing quality healthcare services to our community',
                'hospital_logo_url' => '/cms/assets/images/hospital-logo.png'
            ], $settings);
            
        } catch (Exception $e) {
            error_log("Failed to get hospital info: " . $e->getMessage());
            return [
                'hospital_name' => 'Healthcare Management Clinic',
                'hospital_address' => '123 Healthcare St, Medical City, HC 12345',
                'hospital_phone' => '+1 (555) 123-4567',
                'hospital_website' => 'https://yourhospital.com',
                'hospital_email' => 'info@yourhospital.com',
                'hospital_fax' => '+1 (555) 123-4568',
                'hospital_description' => 'Providing quality healthcare services to our community',
                'hospital_logo_url' => '/cms/assets/images/hospital-logo.png'
            ];
        }
    }
    
    private static function getPatientWelcomeTemplate($patientData, $hospitalInfo) {
        $fullName = $patientData['first_name'] . ' ' . $patientData['last_name'];
        $username = $patientData['username'];
        $password = $patientData['password'] ?? 'Not provided';
        
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Welcome to ' . htmlspecialchars($hospitalInfo['hospital_name']) . '</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .welcome-box { background: white; padding: 25px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #28a745; }
                .credentials { background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0; }
                .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 20px 0; }
                .info-item { background: white; padding: 15px; border-radius: 6px; }
                .hospital-info { background: #f1f3f4; padding: 20px; border-radius: 8px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🏥 Welcome to ' . htmlspecialchars($hospitalInfo['hospital_name']) . '</h1>
                    <p>Your registration has been completed successfully!</p>
                </div>
                
                <div class="content">
                    <div class="welcome-box">
                        <h2>Dear ' . htmlspecialchars($fullName) . ',</h2>
                        <p>Welcome to our healthcare management system! Your patient account has been successfully created by our administrative staff.</p>
                    </div>
                    
                    <div class="credentials">
                        <h3>🔐 Your Login Credentials</h3>
                        <p><strong>Username:</strong> ' . htmlspecialchars($username) . '</p>
                        <p><strong>Password:</strong> ' . htmlspecialchars($password) . '</p>
                        <p><strong>Portal:</strong> Patient Queue Status Checker</p>
                        <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 10px; margin-top: 10px;">
                            <p style="color: #856404; margin: 0; font-size: 14px;">
                                <strong>🔒 Security Notice:</strong> Please keep this information secure and consider changing your password after your first login. 
                                Never share your login credentials with anyone.
                            </p>
                        </div>
                    </div>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <h4>📋 Patient Information</h4>
                            <p><strong>Name:</strong> ' . htmlspecialchars($fullName) . '</p>
                            <p><strong>Phone:</strong> ' . htmlspecialchars($patientData['phone_number']) . '</p>
                            <p><strong>Email:</strong> ' . htmlspecialchars($patientData['email']) . '</p>
                        </div>
                        <div class="info-item">
                            <h4>🩺 Medical Profile</h4>
                            <p><strong>Blood Type:</strong> ' . htmlspecialchars($patientData['blood_type'] ?? 'Not specified') . '</p>
                            <p><strong>DOB:</strong> ' . htmlspecialchars($patientData['date_of_birth']) . '</p>
                            <p><strong>Gender:</strong> ' . htmlspecialchars($patientData['gender']) . '</p>
                        </div>
                    </div>
                    
                    <div class="hospital-info">
                        <h3>🏢 Hospital Information</h3>
                        <p><strong>Address:</strong> ' . htmlspecialchars($hospitalInfo['hospital_address']) . '</p>
                        <p><strong>Phone:</strong> ' . htmlspecialchars($hospitalInfo['hospital_phone']) . '</p>
                        <p><strong>Website:</strong> <a href="' . htmlspecialchars($hospitalInfo['hospital_website']) . '">' . htmlspecialchars($hospitalInfo['hospital_website']) . '</a></p>
                        <p><strong>Email:</strong> ' . htmlspecialchars($hospitalInfo['hospital_email']) . '</p>
                    </div>
                    
                    <div style="text-align: center;">
                        <h3>📱 What You Can Do Now:</h3>
                        <ul style="text-align: left; display: inline-block;">
                            <li>Check your queue status online</li>
                            <li>View your appointment history</li>
                            <li>Contact our front desk for appointments</li>
                            <li>Update your contact information</li>
                        </ul>
                    </div>
                    
                    <div class="footer">
                        <p>Thank you for choosing ' . htmlspecialchars($hospitalInfo['hospital_name']) . ' for your healthcare needs!</p>
                        <p><em>' . htmlspecialchars($hospitalInfo['hospital_description']) . '</em></p>
                        <p><small>This is an automated message. Please do not reply to this email.</small></p>
                        <p><small>If you have any questions, please contact our front desk at ' . htmlspecialchars($hospitalInfo['hospital_phone']) . '</small></p>
                    </div>
                </div>
            </div>
        </body>
        </html>';
    }
}
?>
