<?php
/**
 * Debug file for checking email sending
 * 
 * Usage:
 * 1. Upload this file to your hosting
 * 2. Open in your browser: https://new.backtobase.ca/debug_email_sending.php
 * 3. Check the result
 */

require_once 'config.php';
require_once 'email_service.php';

echo "<h1>Email Sending Debug</h1>";

// Check 1: Mailgun Settings
echo "<h2>1. Checking settings Mailgun:</h2>";
echo "<ul>";

$checks = [];

// API Key Check
if (empty(MAILGUN_API_KEY)) {
    $checks[] = "❌ MAILGUN_API_KEY not configured";
} else {
    $checks[] = "✅ MAILGUN_API_KEY configured: " . substr(MAILGUN_API_KEY, 0, 10) . "...";
}

// Domain check
if (empty(MAILGUN_DOMAIN)) {
    $checks[] = "❌ MAILGUN_DOMAIN not configured";
} else {
    $checks[] = "✅ MAILGUN_DOMAIN configured: " . MAILGUN_DOMAIN;
}

// Check From Email
if (empty(MAILGUN_FROM_EMAIL)) {
    $checks[] = "❌ MAILGUN_FROM_EMAIL not configured";
} else {
    $checks[] = "✅ MAILGUN_FROM_EMAIL configured: " . MAILGUN_FROM_EMAIL;
}

foreach ($checks as $check) {
    echo "<li>" . $check . "</li>";
}
echo "</ul>";

// Test 2: Email sending test
echo "<h2>2. Letter sending test:</h2>";

if (!empty(MAILGUN_API_KEY) && !empty(MAILGUN_DOMAIN)) {
    $testEmail = MAILGUN_HOST_EMAIL ?: MAILGUN_FROM_EMAIL;
    
    echo "<p>Attempting to send a test email to: <strong>" . htmlspecialchars($testEmail) . "</strong></p>";
    echo "<p>Domain used: <strong>" . htmlspecialchars(MAILGUN_DOMAIN) . "</strong></p>";
    
    // Trying to send a letter directly via sendEmail
    $result = sendEmail(
        $testEmail,
        'Debug Test Email',
        '<h1>Debug Test</h1><p>This is a debug test email.</p>',
        'This is a debug test email.'
    );
    
    echo "<h3>Sending result:</h3>";
    echo "<pre>";
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "</pre>";
    
    if ($result['success']) {
        echo "<p style='color: green;'><strong>✅ The letter was sent successfully!</strong></p>";
        echo "<p>Check Mailgun Dashboard → Sending → Logs</p>";
    } else {
        echo "<p style='color: red;'><strong>❌ Error sending email!</strong></p>";
        echo "<p><strong>Error:</strong> " . htmlspecialchars($result['error'] ?? 'Unknown error') . "</p>";
    }
} else {
    echo "<p style='color: red;'><strong>❌ Not all settings are complete. Fill in config.php before testing.</strong></p>";
}

// Check 3: Checking recent bookings
echo "<h2>3. Checking recent bookings:</h2>";

try {
    require_once 'common.php';
    
    $query = "SELECT id, room_name, guest_name, email, status, created_at FROM bookings ORDER BY created_at DESC LIMIT 5";
    $result = fetchAll($conn, $query);
    
    if ($result && count($result) > 0) {
        echo "<p>Bookings found: " . count($result) . "</p>";
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Room</th><th>Guest</th><th>Email</th><th>Status</th><th>Created</th></tr>";
        foreach ($result as $booking) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($booking['id'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($booking['room_name'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($booking['guest_name'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($booking['email'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($booking['status'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($booking['created_at'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Reservations not found in the database.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error checking reservations: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Check 4: Checking activity logs
echo "<h2>4. Latest activity logs (if there is):</h2>";

try {
    if (function_exists('logActivity')) {
        // Checking if there is an activity_logs table
        $query = "SHOW TABLES LIKE 'activity_logs'";
        $tables = fetchAll($conn, $query);
        
        if ($tables && count($tables) > 0) {
            $logQuery = "SELECT * FROM activity_logs WHERE message LIKE '%email%' OR message LIKE '%Email%' OR message LIKE '%mail%' ORDER BY created_at DESC LIMIT 10";
            $logs = fetchAll($conn, $logQuery);
            
            if ($logs && count($logs) > 0) {
                echo "<p>Logs found: " . count($logs) . "</p>";
                echo "<table border='1' cellpadding='10' style='border-collapse: collapse; font-size: 12px;'>";
                echo "<tr><th>Time</th><th>Message</th><th>Level</th></tr>";
                foreach ($logs as $log) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($log['created_at'] ?? '') . "</td>";
                    echo "<td>" . htmlspecialchars($log['message'] ?? '') . "</td>";
                    echo "<td>" . htmlspecialchars($log['level'] ?? '') . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>Logs email not found.</p>";
            }
        } else {
            echo "<p>Table activity_logs not found.</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color: orange;'>Failed to check logs: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><small>To delete this file after verification: delete debug_email_sending.php from hosting</small></p>";
?>



