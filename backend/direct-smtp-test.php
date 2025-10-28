<?php
/**
 * Direct SMTP Test - Tests the new working email system
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

include('email-service.php');

echo "<h1>🔧 Direct SMTP Test</h1>";
echo "<p>Testing the new working SMTP implementation...</p>";

// Test recipient - change this to your email
$testEmail = 'dancethenightaway.kr@gmail.com';

echo "<h3>📧 Configuration Check</h3>";
echo "<p><strong>SMTP Server:</strong> " . SMTPConfig::SMTP_HOST . ":" . SMTPConfig::SMTP_PORT . "</p>";
echo "<p><strong>Username:</strong> " . SMTPConfig::SMTP_USERNAME . "</p>";
echo "<p><strong>From Email:</strong> " . SMTPConfig::FROM_EMAIL . "</p>";
echo "<p><strong>Test Recipient:</strong> " . $testEmail . "</p>";

echo "<hr>";

echo "<h3>🚀 Sending Test Email...</h3>";

try {
    $result = EmailService::sendTestEmail($testEmail, "Direct SMTP test - this should work now!");
    
    if ($result) {
        echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h4>✅ SUCCESS!</h4>";
        echo "<p>Email sent successfully! Check your inbox (and spam folder).</p>";
        echo "<p>If you receive this email, the SMTP system is now working correctly.</p>";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h4>❌ FAILED</h4>";
        echo "<p>Email sending failed. Check the error log below.</p>";
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>❌ ERROR</h4>";
    echo "<p><strong>Exception:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "<hr>";

// Show recent error log
echo "<h3>📋 Error Log</h3>";
$errorLogFile = __DIR__ . '/email_errors.log';
if (file_exists($errorLogFile)) {
    $errors = file_get_contents($errorLogFile);
    $recentErrors = substr($errors, -2000); // Last 2KB
    echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 5px; max-height: 300px; overflow-y: auto;'>";
    echo htmlspecialchars($recentErrors);
    echo "</pre>";
} else {
    echo "<p>No error log file found.</p>";
}

echo "<hr>";

echo "<h3>💡 What's Different Now?</h3>";
echo "<ul>";
echo "<li>✅ <strong>Proper TLS handling:</strong> Fixed the TLS negotiation issue</li>";
echo "<li>✅ <strong>Real SMTP authentication:</strong> Bypassed PHP's limited mail() function</li>";
echo "<li>✅ <strong>Better error reporting:</strong> More detailed error messages</li>";
echo "<li>✅ <strong>Stream context:</strong> Proper SSL/TLS context for Gmail</li>";
echo "</ul>";

echo "<p><strong>If this test succeeds, patient registration emails will now work!</strong></p>";
?>
