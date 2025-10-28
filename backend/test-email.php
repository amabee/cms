<?php
/**
 * SMTP Email Test Script
 * Use this to test your email configuration
 */

// Include email service
include('email-service.php');

// Get hospital information from database
function getHospitalInfoForTest() {
    try {
        include_once('conn.php');
        $conn = DatabaseConnection::getInstance()->getConnection();
        
        $stmt = $conn->prepare('SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN (?, ?, ?, ?, ?)');
        $stmt->execute(['hospital_name', 'hospital_address', 'hospital_phone', 'hospital_website', 'hospital_email']);
        
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        return array_merge([
            'hospital_name' => 'Healthcare Management Clinic',
            'hospital_address' => 'Not configured yet',
            'hospital_phone' => 'Not configured yet',
            'hospital_website' => 'Not configured yet',
            'hospital_email' => 'Not configured yet'
        ], $settings);
        
    } catch (Exception $e) {
        return [
            'hospital_name' => 'Error loading',
            'hospital_address' => 'Error: ' . $e->getMessage(),
            'hospital_phone' => 'Error loading',
            'hospital_website' => 'Error loading',
            'hospital_email' => 'Error loading'
        ];
    }
}

$hospitalInfo = getHospitalInfoForTest();

// Test patient data - REPLACE WITH YOUR EMAIL
$testPatient = [
    'first_name' => 'John',
    'last_name' => 'Test',
    'email' => 'dancethenightaway.kr@gmail.com', // CHANGE THIS TO YOUR EMAIL
    'username' => 'johntest123',
    'phone_number' => '+1 (555) 123-4567',
    'date_of_birth' => '1990-01-01',
    'gender' => 'Male',
    'blood_type' => 'O+',
    'address' => '123 Test Street, Test City, TC 12345'
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMTP Email Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .container { background: #f8f9fa; padding: 30px; border-radius: 10px; }
        .header { color: #28a745; margin-bottom: 20px; }
        .test-info { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .button { background: #007bff; color: white; padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer; }
        .success { color: #28a745; background: #d4edda; padding: 15px; border-radius: 5px; }
        .error { color: #721c24; background: #f8d7da; padding: 15px; border-radius: 5px; }
        .config-check { margin: 20px 0; }
        .config-item { margin: 10px 0; padding: 10px; background: white; border-radius: 5px; }
        .status-ok { color: #28a745; }
        .status-error { color: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏥 SMTP Email Configuration Test</h1>
            <p>Test your email configuration for patient registration notifications.</p>
        </div>

        <div class="test-info">
            <h3>📋 Current Configuration</h3>
            <div class="config-check">
                <div class="config-item">
                    <strong>SMTP Host:</strong> <?php echo SMTPConfig::SMTP_HOST; ?>
                    <span class="<?php echo SMTPConfig::SMTP_HOST !== 'smtp.gmail.com' ? 'status-ok' : 'status-error'; ?>">
                        <?php echo SMTPConfig::SMTP_HOST !== 'smtp.gmail.com' ? '✅ Configured' : '⚠️ Default (Please configure)'; ?>
                    </span>
                </div>
                <div class="config-item">
                    <strong>SMTP Port:</strong> <?php echo SMTPConfig::SMTP_PORT; ?>
                </div>
                <div class="config-item">
                    <strong>From Email:</strong> <?php echo SMTPConfig::FROM_EMAIL; ?>
                    <span class="<?php echo SMTPConfig::FROM_EMAIL !== 'your-email@gmail.com' ? 'status-ok' : 'status-error'; ?>">
                        <?php echo SMTPConfig::FROM_EMAIL !== 'your-email@gmail.com' ? '✅ Configured' : '⚠️ Default (Please configure)'; ?>
                    </span>
                </div>
                <div class="config-item">
                    <strong>Hospital Name (DB):</strong> <?php echo htmlspecialchars($hospitalInfo['hospital_name']); ?>
                    <span class="<?php echo $hospitalInfo['hospital_name'] !== 'Healthcare Management Clinic' ? 'status-ok' : 'status-error'; ?>">
                        <?php echo $hospitalInfo['hospital_name'] !== 'Healthcare Management Clinic' ? '✅ Configured' : '⚠️ Default (Run migration)'; ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="test-info">
            <h3>🏥 Dynamic Hospital Information</h3>
            <div class="config-check">
                <div class="config-item">
                    <strong>Hospital Name:</strong> <?php echo htmlspecialchars($hospitalInfo['hospital_name']); ?>
                </div>
                <div class="config-item">
                    <strong>Hospital Address:</strong> <?php echo htmlspecialchars($hospitalInfo['hospital_address']); ?>
                </div>
                <div class="config-item">
                    <strong>Hospital Phone:</strong> <?php echo htmlspecialchars($hospitalInfo['hospital_phone']); ?>
                </div>
                <div class="config-item">
                    <strong>Hospital Website:</strong> <?php echo htmlspecialchars($hospitalInfo['hospital_website']); ?>
                </div>
                <div class="config-item">
                    <strong>Hospital Email:</strong> <?php echo htmlspecialchars($hospitalInfo['hospital_email']); ?>
                </div>
            </div>
            <p><em>This information is loaded from the database and will be used in patient emails.</em></p>
        </div>

        <div class="test-info">
            <h3>📧 Test Email Data</h3>
            <p><strong>Test Recipient:</strong> <?php echo htmlspecialchars($testPatient['email']); ?></p>
            <p><strong>Patient Name:</strong> <?php echo htmlspecialchars($testPatient['first_name'] . ' ' . $testPatient['last_name']); ?></p>
            <p><em>Make sure to change the test email to your own email address in this file!</em></p>
        </div>

        <?php if (isset($_POST['send_test'])): ?>
        <div class="test-info">
            <h3>🚀 Test Result</h3>
            <?php
            // Enable error reporting for debugging
            ini_set('display_errors', 1);
            error_reporting(E_ALL);
            
            try {
                // Test SMTP connection first
                echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;">';
                echo '<h5>🔧 SMTP Connection Test</h5>';
                
                $smtp_host = SMTPConfig::SMTP_HOST;
                $smtp_port = SMTPConfig::SMTP_PORT;
                
                echo '<p><strong>Testing connection to:</strong> ' . $smtp_host . ':' . $smtp_port . '</p>';
                
                $socket = @fsockopen($smtp_host, $smtp_port, $errno, $errstr, 10);
                if ($socket) {
                    echo '<p style="color: green;">✅ SMTP server connection successful!</p>';
                    fclose($socket);
                } else {
                    echo '<p style="color: red;">❌ Cannot connect to SMTP server: ' . $errstr . ' (' . $errno . ')</p>';
                }
                echo '</div>';
                
                // Now test email sending
                echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;">';
                echo '<h5>📧 Email Send Test</h5>';
                
                $result = EmailService::sendPatientRegistrationEmail($testPatient);
                
                if ($result) {
                    echo '<div class="success">';
                    echo '<h4>✅ Email Sent Successfully!</h4>';
                    echo '<p>Check your inbox for the welcome email. If you don\'t see it, check your spam folder.</p>';
                    echo '<p><strong>Next Steps:</strong></p>';
                    echo '<ul>';
                    echo '<li>Verify the email content and formatting</li>';
                    echo '<li>Test patient registration in the main system</li>';
                    echo '<li>Configure production email settings if needed</li>';
                    echo '</ul>';
                    echo '</div>';
                } else {
                    echo '<div class="error">';
                    echo '<h4>❌ Email Send Failed</h4>';
                    echo '<p>The email could not be sent. Please check:</p>';
                    echo '<ul>';
                    echo '<li>SMTP server settings in email-service.php</li>';
                    echo '<li>Your email credentials and app password</li>';
                    echo '<li>Internet connection and firewall settings</li>';
                    echo '<li>Server error logs for more details</li>';
                    echo '</ul>';
                    echo '</div>';
                }
                echo '</div>';
                
                // Show error log if it exists
                $error_log_file = __DIR__ . '/email_errors.log';
                if (file_exists($error_log_file)) {
                    echo '<div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0;">';
                    echo '<h5>📋 Recent Email Errors</h5>';
                    $errors = file_get_contents($error_log_file);
                    echo '<pre style="max-height: 200px; overflow-y: auto; background: white; padding: 10px; font-size: 12px;">';
                    echo htmlspecialchars(substr($errors, -2000)); // Show last 2KB
                    echo '</pre>';
                    echo '<p><small>Full log: ' . $error_log_file . '</small></p>';
                    echo '</div>';
                }
                
            } catch (Exception $e) {
                echo '<div class="error">';
                echo '<h4>❌ Test Error</h4>';
                echo '<p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '<p>Please check your SMTP configuration in email-service.php</p>';
                echo '</div>';
            }
            ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <button type="submit" name="send_test" class="button">
                📤 Send Full Registration Email
            </button>
            <button type="submit" name="send_simple_test" class="button" style="background: #28a745; margin-left: 10px;">
                ✉️ Send Simple Test Email
            </button>
        </form>
        
        <?php if (isset($_POST['send_simple_test'])): ?>
        <div class="test-info">
            <h3>✉️ Simple Test Result</h3>
            <?php
            try {
                $result = EmailService::sendTestEmail($testPatient['email']);
                
                if ($result) {
                    echo '<div class="success">';
                    echo '<h4>✅ Simple Email Sent Successfully!</h4>';
                    echo '<p>Check your inbox for the test email. This confirms your SMTP setup is working.</p>';
                    echo '</div>';
                } else {
                    echo '<div class="error">';
                    echo '<h4>❌ Simple Email Failed</h4>';
                    echo '<p>The simple email test also failed. Check your SMTP credentials and server settings.</p>';
                    echo '</div>';
                }
            } catch (Exception $e) {
                echo '<div class="error">';
                echo '<h4>❌ Simple Test Error</h4>';
                echo '<p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '</div>';
            }
            ?>
        </div>
        <?php endif; ?>

        <div class="test-info" style="margin-top: 30px;">
            <h3>🛠️ Configuration Instructions</h3>
            <ol>
                <li><strong>Edit email-service.php:</strong> Update SMTPConfig with your email settings</li>
                <li><strong>Gmail Users:</strong> Enable 2FA and generate an App Password</li>
                <li><strong>Update test email:</strong> Change the email in this file to your own</li>
                <li><strong>Run test:</strong> Click the button above to send a test email</li>
                <li><strong>Check result:</strong> Verify email delivery and formatting</li>
            </ol>
            
            <p><strong>📖 For detailed setup instructions, see:</strong> <code>SMTP_SETUP_GUIDE.md</code></p>
        </div>

        <div class="test-info" style="background: #fff3cd;">
            <h3>🔍 Troubleshooting Common Issues</h3>
            
            <h5>❌ "Cannot connect to SMTP server"</h5>
            <ul>
                <li>Check if port 587 is blocked by firewall</li>
                <li>Try port 465 with SSL instead of TLS</li>
                <li>Verify SMTP server hostname</li>
            </ul>
            
            <h5>❌ "Authentication failed"</h5>
            <ul>
                <li><strong>Gmail:</strong> Use App Password, not regular password</li>
                <li>Enable 2-Factor Authentication first</li>
                <li>Check username format (full email address)</li>
            </ul>
            
            <h5>❌ "Email not received"</h5>
            <ul>
                <li>Check spam/junk folder</li>
                <li>Verify recipient email address</li>
                <li>Check email service reputation</li>
            </ul>

            <h5>⚡ Quick Gmail Setup:</h5>
            <ol>
                <li>Go to Google Account → Security</li>
                <li>Enable 2-Step Verification</li>
                <li>Generate App Password for "Mail"</li>
                <li>Use the 16-character app password in SMTP_PASSWORD</li>
            </ol>
        </div>
    </div>
</body>
</html>
