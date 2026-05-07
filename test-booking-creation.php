<?php
/**
 * API booking test
 * 
 * Usage:
 * 1. Upload this file to your hosting
 * 2. Open in your browser: https://new.backtobase.ca/test-booking-creation.php
 * 3. Check the result
 */

require_once 'config.php';
require_once 'common.php';
require_once 'booking_api.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Test of creating a reservation via API</h1>";

// Simulating booking data
$_POST = [
    'action' => 'create_booking',
    'room_name' => 'Loki Suite',
    'checkin_date' => date('Y-m-d', strtotime('+7 days')),
    'checkout_date' => date('Y-m-d', strtotime('+10 days')),
    'guest_name' => 'Test Guest',
    'email' => 'test@example.com',
    'phone' => '+1234567890',
    'guests_count' => '2',
    'pets' => '0',
    'special_requests' => 'Test booking from test script'
];

echo "<h2>1. Data for creating a reservation:</h2>";
echo "<pre>";
print_r($_POST);
echo "</pre>";

echo "<h2>2. Trying to create a reservation:</h2>";

try {
    // Calling the API directly
    ob_start();
    include 'api.php';
    $output = ob_get_clean();
    
    echo "<p>Result API:</p>";
    echo "<pre>";
    echo htmlspecialchars($output);
    echo "</pre>";
    
    // Parsing JSON response
    $json = json_decode($output, true);
    if ($json) {
        if ($json['success']) {
            echo "<p style='color: green;'><strong>✅ Reservation created successfully!</strong></p>";
            echo "<p>Booking ID: " . ($json['data']['booking_id'] ?? 'N/A') . "</p>";
            echo "<p>Confirmation Code: " . ($json['data']['confirmation_code'] ?? 'N/A') . "</p>";
        } else {
            echo "<p style='color: red;'><strong>❌ Error creating reservation!</strong></p>";
            echo "<p>Error: " . htmlspecialchars($json['error'] ?? 'Unknown error') . "</p>";
        }
    } else {
        echo "<p style='color: orange;'><strong>⚠️ Failed to parse response API</strong></p>";
        echo "<p>The answer is not valid JSON.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>❌ Exception when creating a reservation:</strong></p>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h2>3. Checking the latest bookings in the database:</h2>";

try {
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

echo "<hr>";
echo "<p><small>To delete this file after verification: delete test-booking-creation.php from hosting</small></p>";
?>



