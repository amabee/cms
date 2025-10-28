<?php

// Include the working SMTP client
include_once('working-smtp.php');

class SMTPConfig {
    // SMTP Server Settings
    const SMTP_HOST = 'smtp.gmail.com'; 
    const SMTP_PORT = 587; 
    const SMTP_SECURITY = 'tls'; 
    
    // Email Authentication
    const SMTP_USERNAME = 'charlzdummy5@gmail.com';
    const SMTP_PASSWORD = 'qjya pxcm bgfm xzts';
    
    // Email Settings
    const FROM_EMAIL = 'charlzdummy5@gmail.com';
    const FROM_NAME = 'Healthcare Management System';
    const REPLY_TO = 'noreply@yourhospital.com';
    
    // Hospital/Clinic Information
    const HOSPITAL_NAME = 'Your Hospital Name';
    const HOSPITAL_ADDRESS = 'Your Hospital Address';
    const HOSPITAL_PHONE = 'Your Hospital Phone';
    const HOSPITAL_WEBSITE = 'https://yourhospital.com';
}

/**
 * Simple SMTP Email Service
 * Sends emails using PHP's built-in mail functions with SMTP
 */
class EmailService {
    
    public static function sendPatientRegistrationEmail($patientData) {
        // Use the improved SMTP client
        return ImprovedEmailService::sendPatientRegistrationEmail($patientData);
    }
    
    /**
     * Get hospital information from database
     */
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
            
            // Fallback values if settings don't exist
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
            // Return fallback values on error
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
    
    private static function configureSMTP() {
        // Enable error reporting for debugging
        ini_set('log_errors', 1);
        ini_set('error_log', __DIR__ . '/email_errors.log');
        
        // Configure PHP mail settings (basic configuration)
        ini_set('SMTP', SMTPConfig::SMTP_HOST);
        ini_set('smtp_port', SMTPConfig::SMTP_PORT);
        ini_set('sendmail_from', SMTPConfig::FROM_EMAIL);
        
        // Note: PHP's mail() function has limited SMTP support
        // For Gmail and other secure SMTP servers, use the sendWithSocket() method
    }
    
    /**
     * Send email using socket connection (better for Gmail)
     */
    public static function sendWithSocket($to, $subject, $message, $headers) {
        $smtp_server = SMTPConfig::SMTP_HOST;
        $smtp_port = SMTPConfig::SMTP_PORT;
        $smtp_username = SMTPConfig::SMTP_USERNAME;
        $smtp_password = SMTPConfig::SMTP_PASSWORD;
        
        try {
            // Create socket connection
            $socket = fsockopen($smtp_server, $smtp_port, $errno, $errstr, 30);
            if (!$socket) {
                throw new Exception("Could not connect to SMTP server: $errstr ($errno)");
            }
            
            // Read server response
            $response = fgets($socket, 515);
            if (substr($response, 0, 3) != '220') {
                throw new Exception("Server did not respond correctly: $response");
            }
            
            // Send EHLO command
            fputs($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
            $response = fgets($socket, 515);
            
            // Start TLS
            fputs($socket, "STARTTLS\r\n");
            $response = fgets($socket, 515);
            if (substr($response, 0, 3) != '220') {
                throw new Exception("TLS not supported: $response");
            }
            
            // Enable crypto
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            
            // Send EHLO again after TLS
            fputs($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
            $response = fgets($socket, 515);
            
            // Authenticate
            fputs($socket, "AUTH LOGIN\r\n");
            $response = fgets($socket, 515);
            
            fputs($socket, base64_encode($smtp_username) . "\r\n");
            $response = fgets($socket, 515);
            
            fputs($socket, base64_encode($smtp_password) . "\r\n");
            $response = fgets($socket, 515);
            if (substr($response, 0, 3) != '235') {
                throw new Exception("Authentication failed: $response");
            }
            
            // Send email
            fputs($socket, "MAIL FROM: <" . SMTPConfig::FROM_EMAIL . ">\r\n");
            $response = fgets($socket, 515);
            
            fputs($socket, "RCPT TO: <$to>\r\n");
            $response = fgets($socket, 515);
            
            fputs($socket, "DATA\r\n");
            $response = fgets($socket, 515);
            
            // Send headers and message
            fputs($socket, "Subject: $subject\r\n");
            fputs($socket, $headers . "\r\n");
            fputs($socket, "\r\n");
            fputs($socket, $message . "\r\n");
            fputs($socket, ".\r\n");
            $response = fgets($socket, 515);
            
            // Quit
            fputs($socket, "QUIT\r\n");
            fclose($socket);
            
            return true;
            
        } catch (Exception $e) {
            error_log("SMTP Socket Error: " . $e->getMessage());
            return false;
        }
    }
    
    private static function getEmailHeaders() {
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=UTF-8';
        $headers[] = 'From: ' . SMTPConfig::FROM_NAME . ' <' . SMTPConfig::FROM_EMAIL . '>';
        $headers[] = 'Reply-To: ' . SMTPConfig::REPLY_TO;
        $headers[] = 'X-Mailer: PHP/' . phpversion();
        
        return implode("\r\n", $headers);
    }
    
    private static function getPatientWelcomeTemplate($patientData, $hospitalInfo) {
        $fullName = $patientData['first_name'] . ' ' . $patientData['last_name'];
        $username = $patientData['username'];
        
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
                .button { display: inline-block; background: #28a745; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; margin: 15px 0; }
                .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; }
                .hospital-info { background: #f1f3f4; padding: 20px; border-radius: 8px; margin: 20px 0; }
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
                        <p><strong>Portal:</strong> Patient Queue Status Checker</p>
                        <p class="text-info">ℹ️ Your password has been securely set during registration. Please contact our front desk if you need assistance.</p>
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
    
    public static function sendAppointmentConfirmation($patientEmail, $appointmentData) {
        // Future: Send appointment confirmation emails
        // Implementation can be added later
        return true;
    }
    
    public static function sendAppointmentReminder($patientEmail, $appointmentData) {
        // Future: Send appointment reminder emails
        // Implementation can be added later
        return true;
    }
    
    /**
     * Simple test email method for debugging
     */
    public static function sendTestEmail($to, $testMessage = "This is a test email from your healthcare system.") {
        // Use the improved SMTP client
        return ImprovedEmailService::sendTestEmail($to, $testMessage);
    }
}

// For production, consider using PHPMailer for better SMTP support:
/*
require_once 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

class ProductionEmailService {
    public static function sendEmail($to, $subject, $body) {
        $mail = new PHPMailer(true);
        
        try {
            $mail->isSMTP();
            $mail->Host = SMTPConfig::SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTPConfig::SMTP_USERNAME;
            $mail->Password = SMTPConfig::SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTPConfig::SMTP_PORT;
            
            $mail->setFrom(SMTPConfig::FROM_EMAIL, SMTPConfig::FROM_NAME);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Email send failed: " . $mail->ErrorInfo);
            return false;
        }
    }
}
*/
?>
