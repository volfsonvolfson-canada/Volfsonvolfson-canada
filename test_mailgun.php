<?php
/**
 * Test file for checking sending emails via Mailgun
 * 
 * Usage:
 * 1. Upload this file to your hosting
 * 2. Open in your browser: https://new.backtobase.ca/test_mailgun.php
 * 3. Check the result
 */

require_once 'config.php';
require_once 'email_service.php';

// Checking the settings
$checks = [];

// Check 1: API Key
if (empty(MAILGUN_API_KEY)) {
    $checks[] = "❌ MAILGUN_API_KEY not configured";
} else {
    $checks[] = "✅ MAILGUN_API_KEY configured: " . substr(MAILGUN_API_KEY, 0, 10) . "...";
}

// Check 2: Domain
if (empty(MAILGUN_DOMAIN)) {
    $checks[] = "❌ MAILGUN_DOMAIN not configured";
} else {
    $checks[] = "✅ MAILGUN_DOMAIN configured: " . MAILGUN_DOMAIN;
}

// Check 3: From Email
if (empty(MAILGUN_FROM_EMAIL)) {
    $checks[] = "❌ MAILGUN_FROM_EMAIL not configured";
} else {
    $checks[] = "✅ MAILGUN_FROM_EMAIL configured: " . MAILGUN_FROM_EMAIL;
}

// Check 4: Host Email
if (empty(MAILGUN_HOST_EMAIL)) {
    $checks[] = "⚠️ MAILGUN_HOST_EMAIL not configured (optional)";
} else {
    $checks[] = "✅ MAILGUN_HOST_EMAIL configured: " . MAILGUN_HOST_EMAIL;
}

echo "<h1>Mailgun Configuration Test</h1>";
echo "<h2>Checking settings:</h2>";
echo "<ul>";
foreach ($checks as $check) {
    echo "<li>" . $check . "</li>";
}
echo "</ul>";

// If everything is set up, we try to send a test letter
if (!empty(MAILGUN_API_KEY) && !empty(MAILGUN_DOMAIN) && !empty(MAILGUN_FROM_EMAIL)) {
    echo "<h2>Letter sending test:</h2>";
    
    // We use an authorized email for the test
    $testEmail = MAILGUN_HOST_EMAIL ?: MAILGUN_FROM_EMAIL;
    
    echo "<p>Sending a test email to: <strong>" . htmlspecialchars($testEmail) . "</strong></p>";
    echo "<p><em>Note: For Sandbox Domain email must be on the list of authorized recipients!</em></p>";
    
    $result = sendEmail(
        $testEmail,
        'Test Email from Back to Base',
        '<h1>Test Email</h1><p>This is a test email from Back to Base Hotel booking system.</p><p>If you received this email, Mailgun is working correctly!</p>',
        'This is a test email from Back to Base Hotel booking system. If you received this email, Mailgun is working correctly!'
    );
    
    echo "<h3>Result:</h3>";
    echo "<pre>";
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "</pre>";
    
    if ($result['success']) {
        echo "<p style='color: green;'><strong>✅ The letter was sent successfully!</strong></p>";
        echo "<p>Check:</p>";
        echo "<ul>";
        echo "<li>Mailbox " . htmlspecialchars($testEmail) . " (and folder Spam)</li>";
        echo "<li>Mailgun Dashboard → Sending → Logs (a dispatch record should appear)</li>";
        echo "</ul>";
    } else {
        echo "<p style='color: red;'><strong>❌ Error sending email!</strong></p>";
        echo "<p><strong>Error:</strong> " . htmlspecialchars($result['error'] ?? 'Unknown error') . "</p>";
        echo "<p>Check:</p>";
        echo "<ul>";
        echo "<li>What API the key is correct</li>";
        echo "<li>What Domain correct</li>";
        echo "<li>What email recipient in the list of authorized (For Sandbox Domain)</li>";
        echo "<li>Logs PHP on hosting for details</li>";
        echo "</ul>";
    }
} else {
    echo "<p style='color: red;'><strong>❌ Not all settings are complete. Fill in config.php before testing.</strong></p>";
}

echo "<hr>";
echo "<p><small>To delete this file after testing: delete test_mailgun.php from hosting</small></p>";
?>



