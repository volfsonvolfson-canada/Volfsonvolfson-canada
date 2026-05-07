<?php
/**
 * Checking the sending of emails for a specific booking
 * 
 * Usage:
 * 1. Upload this file to your hosting
 * 2. Open in your browser: https://new.backtobase.ca/check-booking-emails.php?confirmation_code=482-903
 */

require_once 'config.php';
require_once 'common.php';
require_once 'email_service.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Checking the sending of reservation emails</h1>";

// We receive a confirmation code
$confirmationCode = isset($_GET['confirmation_code']) ? trim($_GET['confirmation_code']) : null;

if (!$confirmationCode) {
    echo "<p style='color: red;'>❌ Specify confirmation_code V URL</p>";
    echo "<p>Example: <code>?confirmation_code=482-903</code> (six digits, optional hyphen)</p>";
    exit;
}

// We receive a reservation
$booking = null;
$query = "SELECT b.* FROM bookings b 
          JOIN booking_confirmations bc ON b.id = bc.booking_id 
          WHERE bc.confirmation_code = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $confirmationCode);
$stmt->execute();
$result = $stmt->get_result();
$booking = $result->fetch_assoc();
$stmt->close();

if (!$booking) {
    echo "<p style='color: red;'>❌ Reservation not found</p>";
    exit;
}

echo "<h2>Booking information:</h2>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr><th>ID</th><td>" . htmlspecialchars($booking['id']) . "</td></tr>";
echo "<tr><th>Room</th><td>" . htmlspecialchars($booking['room_name']) . "</td></tr>";
echo "<tr><th>Guest</th><td>" . htmlspecialchars($booking['guest_name']) . "</td></tr>";
echo "<tr><th>Email</th><td>" . htmlspecialchars($booking['email']) . "</td></tr>";
echo "<tr><th>Status</th><td>" . htmlspecialchars($booking['status']) . "</td></tr>";
echo "<tr><th>Created</th><td>" . htmlspecialchars($booking['created_at']) . "</td></tr>";
echo "</table>";

echo "<h2>Checking settings Mailgun:</h2>";
echo "<ul>";
echo "<li>MAILGUN_DOMAIN: " . (empty(MAILGUN_DOMAIN) ? "❌ NOT CONFIGURED" : "✅ " . MAILGUN_DOMAIN) . "</li>";
echo "<li>MAILGUN_FROM_EMAIL: " . (empty(MAILGUN_FROM_EMAIL) ? "❌ NOT CONFIGURED" : "✅ " . MAILGUN_FROM_EMAIL) . "</li>";
echo "<li>MAILGUN_HOST_EMAIL: " . (empty(MAILGUN_HOST_EMAIL) ? "❌ NOT CONFIGURED" : "✅ " . MAILGUN_HOST_EMAIL) . "</li>";
echo "</ul>";

if (empty(MAILGUN_API_KEY)) {
    echo "<p style='color: red;'>❌ Mailgun not configured. Letters cannot be sent.</p>";
    exit;
}

echo "<h2>Email sending test:</h2>";

// Test 1: Letter to a guest
echo "<h3>1. Confirmation letter to guest:</h3>";
echo "<p>Send to: <strong>" . htmlspecialchars($booking['email']) . "</strong></p>";

$guestResult = sendBookingConfirmation($booking);
echo "<p><strong>Result:</strong></p>";
echo "<pre>";
echo json_encode($guestResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "</pre>";

if ($guestResult && $guestResult['success']) {
    echo "<p style='color: green;'>✅ The letter to the guest was sent successfully!</p>";
    echo "<p>Message ID: " . htmlspecialchars($guestResult['message_id'] ?? 'N/A') . "</p>";
} else {
    echo "<p style='color: red;'>❌ Error sending email to guest!</p>";
    echo "<p>Error: " . htmlspecialchars($guestResult['error'] ?? 'Unknown error') . "</p>";
}

// Test 2: Letter to the owner
echo "<h3>2. Notification letter to owner:</h3>";
echo "<p>Send to: <strong>" . htmlspecialchars(MAILGUN_HOST_EMAIL) . "</strong></p>";

$hostResult = sendBookingRequestToHost($booking);
echo "<p><strong>Result:</strong></p>";
echo "<pre>";
echo json_encode($hostResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "</pre>";

if ($hostResult && $hostResult['success']) {
    echo "<p style='color: green;'>✅ The letter to the owner was sent successfully!</p>";
    echo "<p>Message ID: " . htmlspecialchars($hostResult['message_id'] ?? 'N/A') . "</p>";
} else {
    echo "<p style='color: red;'>❌ Error sending email to owner!</p>";
    echo "<p>Error: " . htmlspecialchars($hostResult['error'] ?? 'Unknown error') . "</p>";
}

echo "<hr>";
echo "<p><small>To delete this file after verification: delete check-booking-emails.php from hosting</small></p>";
?>
