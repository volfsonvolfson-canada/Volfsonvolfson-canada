<?php
/**
 * Checking Mailgun settings on hosting
 * 
 * Usage:
 * 1. Upload this file to your hosting
 * 2. Open in your browser: https://new.backtobase.ca/check-mailgun-config.php
 * 3. Check the result
 */

require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Checking settings Mailgun on hosting</h1>";

echo "<h2>1. Checking constants:</h2>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";

$checks = [];

// Checking MAILGUN_DOMAIN
if (defined('MAILGUN_DOMAIN')) {
    $domain = MAILGUN_DOMAIN;
    $checks[] = [
        'name' => 'MAILGUN_DOMAIN',
        'value' => $domain,
        'status' => ($domain === 'new.backtobase.ca') ? '✅ Right' : '❌ Wrong (there must be: new.backtobase.ca)'
    ];
} else {
    $checks[] = [
        'name' => 'MAILGUN_DOMAIN',
        'value' => 'NOT DEFINED',
        'status' => '❌ NOT CONFIGURED'
    ];
}

// Checking MAILGUN_FROM_EMAIL
if (defined('MAILGUN_FROM_EMAIL')) {
    $fromEmail = MAILGUN_FROM_EMAIL;
    $checks[] = [
        'name' => 'MAILGUN_FROM_EMAIL',
        'value' => $fromEmail,
        'status' => ($fromEmail === 'bookings@new.backtobase.ca') ? '✅ Right' : '❌ Wrong (there must be: bookings@new.backtobase.ca)'
    ];
} else {
    $checks[] = [
        'name' => 'MAILGUN_FROM_EMAIL',
        'value' => 'NOT DEFINED',
        'status' => '❌ NOT CONFIGURED'
    ];
}

// Checking MAILGUN_API_KEY
if (defined('MAILGUN_API_KEY')) {
    $apiKey = MAILGUN_API_KEY;
    $checks[] = [
        'name' => 'MAILGUN_API_KEY',
        'value' => substr($apiKey, 0, 10) . '...',
        'status' => (!empty($apiKey)) ? '✅ Configured' : '❌ Empty'
    ];
} else {
    $checks[] = [
        'name' => 'MAILGUN_API_KEY',
        'value' => 'NOT DEFINED',
        'status' => '❌ NOT CONFIGURED'
    ];
}

foreach ($checks as $check) {
    echo "<tr>";
    echo "<th>" . htmlspecialchars($check['name']) . "</th>";
    echo "<td>" . htmlspecialchars($check['value']) . "</td>";
    echo "<td>" . $check['status'] . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h2>2. Checking recent bookings:</h2>";

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
        
        echo "<p><strong>Last booking created:</strong> " . htmlspecialchars($result[0]['created_at'] ?? 'N/A') . "</p>";
    } else {
        echo "<p>Reservations not found in the database.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error checking reservations: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<h2>3. What to check:</h2>";
echo "<ul>";
echo "<li>✅ <strong>MAILGUN_DOMAIN</strong> must be: <code>new.backtobase.ca</code></li>";
echo "<li>✅ <strong>MAILGUN_FROM_EMAIL</strong> must be: <code>bookings@new.backtobase.ca</code></li>";
echo "<li>✅ Create a new booking AFTER the update config.php</li>";
echo "<li>✅ Check the logs PHP on the hosting after creating a reservation</li>";
echo "</ul>";

echo "<hr>";
echo "<p><small>To delete this file after verification: delete check-mailgun-config.php from hosting</small></p>";
?>



