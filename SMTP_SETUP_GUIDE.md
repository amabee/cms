# SMTP Email Configuration Guide

## 📧 Email Setup for Patient Registration

This guide will help you configure SMTP email sending for patient registration notifications.

## 🛠️ Configuration Steps

### 1. Update Email Settings

Edit `backend/email-service.php` and update the SMTPConfig class:

```php
class SMTPConfig {
    // SMTP Server Settings
    const SMTP_HOST = 'your-smtp-server.com'; // Gmail: smtp.gmail.com
    const SMTP_PORT = 587; // 587 for TLS, 465 for SSL
    const SMTP_SECURITY = 'tls'; // 'tls' or 'ssl'
    
    // Email Authentication
    const SMTP_USERNAME = 'your-email@gmail.com';
    const SMTP_PASSWORD = 'your-app-password'; // See security note below
    
    // Email Settings
    const FROM_EMAIL = 'your-email@gmail.com';
    const FROM_NAME = 'Your Hospital Name';
    const REPLY_TO = 'noreply@yourhospital.com';
    
    // Hospital Information
    const HOSPITAL_NAME = 'Your Hospital Name';
    const HOSPITAL_ADDRESS = 'Your Complete Hospital Address';
    const HOSPITAL_PHONE = '+1 (555) 123-4567';
    const HOSPITAL_WEBSITE = 'https://yourhospital.com';
}
```

### 2. Gmail Configuration (Most Common)

If using Gmail:

1. **Enable 2-Factor Authentication** on your Google account
2. **Generate App Password**:
   - Go to Google Account Settings
   - Security → 2-Step Verification → App passwords
   - Generate password for "Mail"
   - Use this password in SMTP_PASSWORD (not your regular password)

3. **Gmail Settings**:
```php
const SMTP_HOST = 'smtp.gmail.com';
const SMTP_PORT = 587;
const SMTP_SECURITY = 'tls';
const SMTP_USERNAME = 'youremail@gmail.com';
const SMTP_PASSWORD = 'generated-app-password'; // 16-character app password
```

### 3. Other Email Providers

#### Outlook/Hotmail:
```php
const SMTP_HOST = 'smtp-mail.outlook.com';
const SMTP_PORT = 587;
const SMTP_SECURITY = 'tls';
```

#### Yahoo Mail:
```php
const SMTP_HOST = 'smtp.mail.yahoo.com';
const SMTP_PORT = 587;
const SMTP_SECURITY = 'tls';
```

#### Custom SMTP Server:
```php
const SMTP_HOST = 'mail.yourdomain.com';
const SMTP_PORT = 587; // Check with your provider
const SMTP_SECURITY = 'tls';
```

### 4. Security Considerations

⚠️ **Important Security Notes:**

1. **Never commit passwords to version control**
2. **Use environment variables for production:**

```php
// Better approach for production:
const SMTP_USERNAME = $_ENV['SMTP_USERNAME'] ?? 'fallback@email.com';
const SMTP_PASSWORD = $_ENV['SMTP_PASSWORD'] ?? 'fallback-password';
```

3. **Use App Passwords, not regular passwords**
4. **Consider using dedicated email services like SendGrid, Mailgun, or Amazon SES for production**

## 🧪 Testing Email Configuration

### Test Script

Create `backend/test-email.php`:

```php
<?php
include('email-service.php');

// Test data
$testPatient = [
    'first_name' => 'John',
    'last_name' => 'Doe',
    'email' => 'test@example.com', // Use your email for testing
    'username' => 'johndoe123',
    'phone_number' => '+1234567890',
    'date_of_birth' => '1990-01-01',
    'gender' => 'Male',
    'blood_type' => 'O+'
];

$result = EmailService::sendPatientRegistrationEmail($testPatient);

if ($result) {
    echo "✅ Test email sent successfully!";
} else {
    echo "❌ Failed to send test email. Check your SMTP configuration.";
}
?>
```

Run: `http://localhost/cms/backend/test-email.php`

## 🚀 Production Recommendations

### For Production Use, Consider:

1. **PHPMailer Library** (More robust):
```bash
composer require phpmailer/phpmailer
```

2. **Email Services**:
   - SendGrid (Free tier: 100 emails/day)
   - Mailgun (Free tier: 5,000 emails/month)
   - Amazon SES (Very cheap, high volume)

3. **Environment Variables**:
```php
// .env file approach
const SMTP_USERNAME = getenv('SMTP_USERNAME');
const SMTP_PASSWORD = getenv('SMTP_PASSWORD');
```

## 📋 Email Features Included

✅ **Patient Registration Welcome Email**
- Professional HTML template
- Patient information summary
- Login credentials
- Hospital contact information
- Responsive design

🔄 **Future Enhancements Available**:
- Appointment confirmation emails
- Appointment reminder emails
- Password reset emails
- Lab result notifications

## 🐛 Troubleshooting

### Common Issues:

1. **"Failed to send email"**
   - Check SMTP credentials
   - Verify server settings
   - Check firewall/port blocking

2. **"Authentication failed"**
   - Use app password for Gmail
   - Enable "Less secure apps" (not recommended)
   - Check username/password

3. **"Connection timeout"**
   - Check SMTP host and port
   - Verify TLS/SSL settings
   - Check firewall rules

### Debug Mode:

Add to email-service.php:
```php
// Add before mail() function
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/php-errors.log');
```

## 📱 Email Template Features

The registration email includes:
- 🎨 Professional responsive design
- 🔐 Login credentials
- 📋 Patient information summary
- 🏥 Hospital contact details
- 📊 Clean, medical-themed layout
- 📧 Proper email headers and MIME types

## ✅ Setup Complete!

After configuration, the system will automatically:
1. Send welcome emails to new patients
2. Show email status in success notifications
3. Log email attempts for debugging
4. Handle email failures gracefully (registration still succeeds)

Your patients will receive professional welcome emails with their login information! 🎉
