<?php
/**
 * Dream Closets - Consultation Form Handler
 * Sends consultation requests via Gmail SMTP
 */

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

$allowedOrigins = [
    'http://localhost:8888',
    'https://skyblue-porpoise-169119.hostingersite.com'
];
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}

header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Config path: same dir on MAMP, one level up on Hostinger
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    $configPath = __DIR__ . '/../config.php';
}
$smtpConfig = require $configPath;

// Sanitize config values (Hostinger file editor can inject invisible chars)
array_walk($smtpConfig, function(&$val) {
    if (is_string($val)) $val = preg_replace('/[^\x20-\x7E]/', '', $val);
});

// Get form data
$firstName = isset($_POST['firstName']) ? htmlspecialchars(trim($_POST['firstName']), ENT_QUOTES, 'UTF-8') : '';
$lastName = isset($_POST['lastName']) ? htmlspecialchars(trim($_POST['lastName']), ENT_QUOTES, 'UTF-8') : '';
$email = isset($_POST['email']) ? filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL) : '';
$phone = isset($_POST['phone']) ? htmlspecialchars(trim($_POST['phone']), ENT_QUOTES, 'UTF-8') : '';
$address = isset($_POST['address']) ? htmlspecialchars(trim($_POST['address']), ENT_QUOTES, 'UTF-8') : '';
$service = isset($_POST['service']) ? htmlspecialchars(trim($_POST['service']), ENT_QUOTES, 'UTF-8') : '';
$description = isset($_POST['description']) ? htmlspecialchars(trim($_POST['description']), ENT_QUOTES, 'UTF-8') : '';
$preferredDate = isset($_POST['preferredDate']) ? htmlspecialchars(trim($_POST['preferredDate']), ENT_QUOTES, 'UTF-8') : '';

// Validate required fields
$errors = [];
if (empty($firstName)) $errors[] = 'First name is required';
if (empty($lastName)) $errors[] = 'Last name is required';
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';
if (empty($phone)) $errors[] = 'Phone number is required';
if (empty($service)) $errors[] = 'Service type is required';
if (empty($description)) $errors[] = 'Description is required';

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit();
}

// Service type labels
$serviceLabels = [
    'walk-in-closet' => 'Walk-In Closet Design',
    'reach-in-closet' => 'Reach-In Closet Design',
    'pantry' => 'Pantry & Kitchen Organization',
    'garage' => 'Garage Organization',
    'murphy-bed' => 'Home Office & Murphy Bed',
    'laundry' => 'Laundry Room Solutions',
    'other' => 'Other'
];
$serviceLabel = $serviceLabels[$service] ?? $service;

// Build email content
$subject = "New Consultation Request from {$firstName} {$lastName} - {$serviceLabel}";

$htmlBody = "
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #1a1a2e, #2c2c44); color: white; padding: 20px; text-align: center; }
        .header h1 { color: #c9a96e; }
        .content { padding: 20px; background: #f9f9f9; }
        .field { margin-bottom: 15px; }
        .label { font-weight: bold; color: #333; }
        .value { color: #666; }
        .footer { padding: 20px; text-align: center; font-size: 12px; color: #999; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>New Consultation Request</h1>
        </div>
        <div class='content'>
            <div class='field'>
                <span class='label'>Name:</span>
                <span class='value'>{$firstName} {$lastName}</span>
            </div>
            <div class='field'>
                <span class='label'>Email:</span>
                <span class='value'><a href='mailto:{$email}'>{$email}</a></span>
            </div>
            <div class='field'>
                <span class='label'>Phone:</span>
                <span class='value'><a href='tel:{$phone}'>{$phone}</a></span>
            </div>
            <div class='field'>
                <span class='label'>Address:</span>
                <span class='value'>{$address}</span>
            </div>
            <div class='field'>
                <span class='label'>Service:</span>
                <span class='value'>{$serviceLabel}</span>
            </div>
            <div class='field'>
                <span class='label'>Preferred Date:</span>
                <span class='value'>" . ($preferredDate ?: 'Not specified') . "</span>
            </div>
            <div class='field'>
                <span class='label'>Project Details:</span>
                <p class='value'>{$description}</p>
            </div>
        </div>
        <div class='footer'>
            <p>This consultation request was submitted from the Dream Closets website.</p>
        </div>
    </div>
</body>
</html>
";

$textBody = "
NEW CONSULTATION REQUEST
========================

Name: {$firstName} {$lastName}
Email: {$email}
Phone: {$phone}
Address: {$address}
Service: {$serviceLabel}
Preferred Date: " . ($preferredDate ?: 'Not specified') . "

Project Details:
{$description}

---
This consultation request was submitted from the Dream Closets website.
";

require_once __DIR__ . '/smtp-mailer.php';

$mailer = new SMTPMailer(
    $smtpConfig['host'],
    $smtpConfig['port'],
    $smtpConfig['username'],
    $smtpConfig['password']
);

$sent = $mailer->send(
    $smtpConfig['from_email'],
    $smtpConfig['from_name'],
    $smtpConfig['to_email'],
    $subject,
    $htmlBody,
    $textBody,
    $email,
    "{$firstName} {$lastName}"
);

if ($sent) {
    // Send confirmation email to customer
    $customerSubject = "Dream Closets - We Received Your Consultation Request";

    $customerHtmlBody = "
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #1a1a2e, #2c2c44); color: white; padding: 20px; text-align: center; }
        .header h1 { color: #c9a96e; }
        .content { padding: 20px; background: #f9f9f9; }
        .summary { background: white; padding: 15px; border-radius: 8px; margin: 15px 0; }
        .field { margin-bottom: 10px; }
        .label { font-weight: bold; color: #333; }
        .value { color: #666; }
        .footer { padding: 20px; text-align: center; font-size: 12px; color: #999; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Thank You, {$firstName}!</h1>
            <p>We received your consultation request</p>
        </div>
        <div class='content'>
            <p>Thank you for contacting Dream Closets. We have received your request and will get back to you as soon as possible.</p>

            <p><strong>What happens next?</strong></p>
            <ul>
                <li>Our design team will review your request</li>
                <li>We'll contact you within 24 hours to schedule your free consultation</li>
                <li>A designer will visit your home to measure and discuss your vision</li>
                <li>You'll receive a custom 3D design and quote</li>
            </ul>

            <div class='summary'>
                <h3>Your Request Summary:</h3>
                <div class='field'>
                    <span class='label'>Service:</span>
                    <span class='value'>{$serviceLabel}</span>
                </div>
                <div class='field'>
                    <span class='label'>Preferred Date:</span>
                    <span class='value'>" . ($preferredDate ?: 'Not specified') . "</span>
                </div>
            </div>

            <p>Need to reach us sooner? Give us a call!</p>
            <p style='text-align: center;'>
                <a href='tel:+17705551234' style='background: #c9a96e; color: #1a1a1a; padding: 16px 32px; text-decoration: none; border-radius: 8px; display: inline-block; margin: 15px 0; font-size: 20px; font-weight: bold;'>Call (770) 555-1234</a>
            </p>

            <p><em>Simply reply to this email if you have any questions.</em></p>
        </div>
        <div class='footer'>
            <p><strong>Dream Closets</strong></p>
            <p>Atlanta, GA | (770) 555-1234</p>
            <p>Custom closets designed for your lifestyle.</p>
        </div>
    </div>
</body>
</html>
";

    $customerTextBody = "
Thank You, {$firstName}!

We received your consultation request and will get back to you as soon as possible.

What happens next?
- Our design team will review your request
- We'll contact you within 24 hours to schedule your free consultation
- A designer will visit your home to measure and discuss your vision
- You'll receive a custom 3D design and quote

YOUR REQUEST SUMMARY:
Service: {$serviceLabel}
Preferred Date: " . ($preferredDate ?: 'Not specified') . "

Need to reach us sooner? Call us at (770) 555-1234

Simply reply to this email if you have any questions.

---
Dream Closets
Atlanta, GA | (770) 555-1234
Custom closets designed for your lifestyle.
";

    $mailer->send(
        $smtpConfig['from_email'],
        $smtpConfig['from_name'],
        $email,
        $customerSubject,
        $customerHtmlBody,
        $customerTextBody,
        $smtpConfig['from_email'],
        $smtpConfig['from_name']
    );

    echo json_encode([
        'success' => true,
        'message' => 'Thank you! Your consultation request has been sent. We will contact you within 24 hours. A confirmation has been sent to your email.'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send email. Please call us directly at (770) 555-1234.',
        'error' => $mailer->getLastError()
    ]);
}
?>
