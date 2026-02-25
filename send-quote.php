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

// --- Save quote to Supabase (best-effort, don't block on failure) ---
$supabasePath = __DIR__ . '/supabase-config.php';
if (!file_exists($supabasePath)) {
    $supabasePath = __DIR__ . '/../supabase-config.php';
}
if (file_exists($supabasePath)) {
    $supabaseConfig = require $supabasePath;
    array_walk($supabaseConfig, function(&$val) {
        if (is_string($val)) $val = preg_replace('/[^\x20-\x7E]/', '', $val);
    });

    $dreamClosetsBusinessId = '09ae0180-0532-4a0f-ac78-53ad526b97a1';

    try {
        $quoteData = json_encode([
            'business_id' => $dreamClosetsBusinessId,
            'name' => "$firstName $lastName",
            'email' => $email,
            'phone' => $phone,
            'service' => $serviceLabel,
            'budget' => $address,
            'timeline' => $preferredDate ?: 'Not specified',
            'message' => $description
        ]);

        $ch = curl_init($supabaseConfig['url'] . '/rest/v1/quotes');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $quoteData,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'apikey: ' . $supabaseConfig['service_role_key'],
                'Authorization: Bearer ' . $supabaseConfig['service_role_key'],
                'Prefer: return=minimal'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        if ($httpCode !== 201) {
            error_log("[Dream Closets] Supabase insert HTTP {$httpCode}: {$response} {$curlError}", 3, __DIR__ . '/quote-requests.log');
        }
    } catch (Exception $e) {
        error_log('[Dream Closets] Supabase insert failed: ' . $e->getMessage(), 3, __DIR__ . '/quote-requests.log');
    }

    // --- Dashboard user notifications (best-effort) ---
    try {
        $supabaseUrl = $supabaseConfig['url'];
        $serviceKey = $supabaseConfig['service_role_key'];
        $authHeaders = [
            'apikey: ' . $serviceKey,
            'Authorization: Bearer ' . $serviceKey,
        ];

        // 1. Get users associated with this business + admins
        $profilesUrl = $supabaseUrl . '/rest/v1/profiles?or=(business_id.eq.' . $dreamClosetsBusinessId . ',role.eq.admin)&select=id,full_name';
        $ch = curl_init($profilesUrl);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => $authHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);
        $profilesJson = curl_exec($ch);
        $profilesCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($profilesCode !== 200 || !$profilesJson) {
            throw new Exception("Profiles query HTTP {$profilesCode}");
        }

        $profiles = json_decode($profilesJson, true);
        if (empty($profiles)) {
            throw new Exception('No profiles found for notification');
        }

        $userIds = array_column($profiles, 'id');
        $nameMap = [];
        foreach ($profiles as $p) {
            $nameMap[$p['id']] = $p['full_name'] ?: 'there';
        }

        // 2. Check notification preferences — only users with notify_new_quote = true
        $idsParam = '(' . implode(',', $userIds) . ')';
        $prefsUrl = $supabaseUrl . '/rest/v1/notification_preferences?notify_new_quote=eq.true&user_id=in.' . $idsParam . '&select=user_id';
        $ch = curl_init($prefsUrl);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => $authHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);
        $prefsJson = curl_exec($ch);
        $prefsCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($prefsCode !== 200 || !$prefsJson) {
            throw new Exception("Prefs query HTTP {$prefsCode}");
        }

        $prefs = json_decode($prefsJson, true);

        // Also include users who have NO preferences row (default is opted-in)
        $optedOutIds = [];
        $prefsUrl2 = $supabaseUrl . '/rest/v1/notification_preferences?notify_new_quote=eq.false&user_id=in.' . $idsParam . '&select=user_id';
        $ch = curl_init($prefsUrl2);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => $authHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);
        $optedOutJson = curl_exec($ch);
        curl_close($ch);
        if ($optedOutJson) {
            $optedOut = json_decode($optedOutJson, true);
            if (is_array($optedOut)) {
                $optedOutIds = array_column($optedOut, 'user_id');
            }
        }

        // Final list: all user IDs minus those explicitly opted out
        $notifyIds = array_diff($userIds, $optedOutIds);

        if (empty($notifyIds)) {
            throw new Exception('No users opted in for new-quote notifications');
        }

        // 3 & 4. For each opted-in user: get email via Auth Admin API, then send notification
        $messagePreview = mb_substr(strip_tags($description), 0, 200);
        if (mb_strlen(strip_tags($description)) > 200) {
            $messagePreview .= '...';
        }

        $fullName = "$firstName $lastName";

        foreach ($notifyIds as $userId) {
            try {
                $authUrl = $supabaseUrl . '/auth/v1/admin/users/' . $userId;
                $ch = curl_init($authUrl);
                curl_setopt_array($ch, [
                    CURLOPT_HTTPHEADER => $authHeaders,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 5,
                ]);
                $authJson = curl_exec($ch);
                $authCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($authCode !== 200 || !$authJson) {
                    error_log("[Dream Closets] Auth API error for user {$userId}: HTTP {$authCode}", 3, __DIR__ . '/quote-requests.log');
                    continue;
                }

                $authUser = json_decode($authJson, true);
                $userEmail = $authUser['email'] ?? '';
                if (!$userEmail) {
                    continue;
                }

                // Skip if this is the same as the business admin email
                if ($userEmail === $smtpConfig['to_email']) {
                    continue;
                }

                $userName = $nameMap[$userId] ?? 'there';

                $notifSubject = "New Consultation Request — {$fullName}";

                $notifHtml = "
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #1a1a2e 0%, #2c2c44 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
        .header h1 { margin: 0; font-size: 24px; color: #c9a96e; }
        .content { background: #f8fafc; padding: 30px; border: 1px solid #e2e8f0; }
        .field { margin-bottom: 15px; padding-bottom: 12px; border-bottom: 1px solid #e2e8f0; }
        .field:last-child { border-bottom: none; margin-bottom: 0; }
        .label { font-weight: bold; color: #1a1a2e; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
        .value { font-size: 15px; color: #1e293b; }
        .message-box { background: white; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; margin-top: 8px; font-size: 14px; color: #475569; }
        .cta { text-align: center; margin: 25px 0 10px 0; }
        .cta a { background: #c9a96e; color: #1a1a2e; padding: 12px 28px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 14px; }
        .footer { background: #1a1a2e; color: #94a3b8; padding: 20px; text-align: center; font-size: 12px; border-radius: 0 0 8px 8px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>New Consultation Request</h1>
            <p style='margin: 10px 0 0 0; opacity: 0.9;'>IPW Dashboard</p>
        </div>
        <div class='content'>
            <p style='font-size: 16px; margin-top: 0;'>Hi {$userName},</p>
            <p>A new consultation request has been submitted for <strong>Dream Closets</strong>:</p>
            <div class='field'>
                <div class='label'>Name</div>
                <div class='value'>{$fullName}</div>
            </div>
            <div class='field'>
                <div class='label'>Email</div>
                <div class='value'><a href='mailto:{$email}'>{$email}</a></div>
            </div>
            <div class='field'>
                <div class='label'>Phone</div>
                <div class='value'>{$phone}</div>
            </div>
            <div class='field'>
                <div class='label'>Service</div>
                <div class='value'>{$serviceLabel}</div>
            </div>
            <div class='field'>
                <div class='label'>Address</div>
                <div class='value'>{$address}</div>
            </div>
            <div class='field'>
                <div class='label'>Preferred Date</div>
                <div class='value'>" . ($preferredDate ?: 'Not specified') . "</div>
            </div>
            <div class='field'>
                <div class='label'>Project Details</div>
                <div class='message-box'>{$messagePreview}</div>
            </div>
            <div class='cta'>
                <a href='https://aquamarine-peafowl-476925.hostingersite.com/dashboard/'>View in Dashboard</a>
            </div>
        </div>
        <div class='footer'>
            <p>IPW Dashboard &mdash; Dream Closets Notification</p>
            <p>You received this because you have New Quote Alerts enabled.</p>
        </div>
    </div>
</body>
</html>";

                $notifText = "NEW CONSULTATION REQUEST — IPW DASHBOARD
==========================================

Hi {$userName},

A new consultation request has been submitted for Dream Closets:

Name: {$fullName}
Email: {$email}
Phone: {$phone}
Service: {$serviceLabel}
Address: {$address}
Preferred Date: " . ($preferredDate ?: 'Not specified') . "

Project Details:
{$messagePreview}

Log in to your dashboard to review and respond.

---
IPW Dashboard — Dream Closets Notification
You received this because you have New Quote Alerts enabled.";

                $notifSent = $mailer->send(
                    $smtpConfig['username'],
                    'IPW Dashboard',
                    $userEmail,
                    $notifSubject,
                    $notifHtml,
                    $notifText,
                    $email,
                    $fullName
                );

                if ($notifSent) {
                    error_log("[Dream Closets] Notification sent to {$userEmail}\n", 3, __DIR__ . '/quote-requests.log');
                } else {
                    error_log("[Dream Closets] Notification send failed for {$userEmail}: " . $mailer->getLastError() . "\n", 3, __DIR__ . '/quote-requests.log');
                }

            } catch (Exception $e) {
                error_log("[Dream Closets] Notification email failed for user {$userId}: " . $e->getMessage(), 3, __DIR__ . '/quote-requests.log');
            }
        }

    } catch (Exception $e) {
        error_log('[Dream Closets] Dashboard notification failed: ' . $e->getMessage(), 3, __DIR__ . '/quote-requests.log');
    }
}

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
