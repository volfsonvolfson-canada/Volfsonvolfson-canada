<?php
/**
 * Check for conflicting bookings and blocked dates
 * 
 * Usage:
 * 1. Upload this file to your hosting
 * 2. Open in your browser: https://new.backtobase.ca/check-booking-conflicts.php?room=Loki%20Suite&checkin=2025-11-06&checkout=2025-11-21
 */

require_once 'config.php';
require_once 'common.php';

header('Content-Type: text/html; charset=utf-8');

$roomName = isset($_GET['room']) ? $_GET['room'] : 'Loki Suite';
$checkinDate = isset($_GET['checkin']) ? $_GET['checkin'] : '2025-11-06';
$checkoutDate = isset($_GET['checkout']) ? $_GET['checkout'] : '2025-11-21';

echo "<h1>Checking conflicts for bookings</h1>";
echo "<p><strong>Room:</strong> " . htmlspecialchars($roomName) . "</p>";
echo "<p><strong>Check-in:</strong> " . htmlspecialchars($checkinDate) . "</p>";
echo "<p><strong>Check-out:</strong> " . htmlspecialchars($checkoutDate) . "</p>";

echo "<h2>1. Conflicting bookings:</h2>";

try {
    // Checking existing bookings
    $sql = "SELECT id, checkin_date, checkout_date, status, guest_name, email FROM bookings 
            WHERE room_name = ? 
            AND status IN ('pending', 'confirmed')
            AND (
                (checkin_date <= ? AND checkout_date > ?) OR
                (checkin_date < ? AND checkout_date >= ?) OR
                (checkin_date >= ? AND checkout_date <= ?)
            )";
    
    $result = fetchAll($conn, $sql, [
        $roomName, 
        $checkinDate, $checkinDate,
        $checkoutDate, $checkoutDate,
        $checkinDate, $checkoutDate
    ]);
    
    if ($result && count($result) > 0) {
        echo "<p style='color: red;'><strong>Conflicting bookings found: " . count($result) . "</strong></p>";
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Check-in</th><th>Check-out</th><th>Status</th><th>Guest</th><th>Email</th></tr>";
        foreach ($result as $booking) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($booking['id'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($booking['checkin_date'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($booking['checkout_date'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($booking['status'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($booking['guest_name'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($booking['email'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: green;'>✅ No conflicting bookings found</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error checking reservations: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h2>2. Blocked dates (manually):</h2>";

try {
    $sql = "SELECT blocked_date, reason FROM blocked_dates 
            WHERE room_name = ? 
            AND blocked_date >= ? 
            AND blocked_date < ?";
    
    $result = fetchAll($conn, $sql, [$roomName, $checkinDate, $checkoutDate]);
    
    if ($result && count($result) > 0) {
        echo "<p style='color: red;'><strong>Blocked dates found: " . count($result) . "</strong></p>";
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
        echo "<tr><th>Date</th><th>Cause</th></tr>";
        foreach ($result as $blocked) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($blocked['blocked_date'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($blocked['reason'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: green;'>✅ No blocked dates found</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error checking blocked dates: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h2>3. Blocked dates (Airbnb):</h2>";

try {
    $sql = "SELECT date, is_available, last_synced_at FROM airbnb_calendar 
            WHERE room_name = ? 
            AND date >= ? 
            AND date < ? 
            AND is_available = 0";
    
    $result = fetchAll($conn, $sql, [$roomName, $checkinDate, $checkoutDate]);
    
    if ($result && count($result) > 0) {
        echo "<p style='color: red;'><strong>Blocked dates found Airbnb: " . count($result) . "</strong></p>";
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
        echo "<tr><th>Date</th><th>Available</th><th>Last Synced</th></tr>";
        foreach ($result as $airbnb) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($airbnb['date'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($airbnb['is_available'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($airbnb['last_synced_at'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: green;'>✅ Blocked dates Airbnb not found</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Validation error Airbnb dates: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h2>4. All bookings for this room:</h2>";

try {
    $sql = "SELECT id, checkin_date, checkout_date, status, guest_name, email, created_at FROM bookings 
            WHERE room_name = ? 
            ORDER BY checkin_date ASC";
    
    $result = fetchAll($conn, $sql, [$roomName]);
    
    if ($result && count($result) > 0) {
        echo "<p>Bookings found: " . count($result) . "</p>";
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Check-in</th><th>Check-out</th><th>Status</th><th>Guest</th><th>Email</th><th>Created</th></tr>";
        foreach ($result as $booking) {
            $isConflict = false;
            if (in_array($booking['status'], ['pending', 'confirmed'])) {
                $checkin = $booking['checkin_date'];
                $checkout = $booking['checkout_date'];
                // Checking the intersection
                if (($checkin <= $checkoutDate && $checkout > $checkinDate) ||
                    ($checkin < $checkoutDate && $checkout >= $checkinDate) ||
                    ($checkin >= $checkinDate && $checkout <= $checkoutDate)) {
                    $isConflict = true;
                }
            }
            
            $rowStyle = $isConflict ? "background-color: #ffebee;" : "";
            echo "<tr style='{$rowStyle}'>";
            echo "<td>" . htmlspecialchars($booking['id'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($booking['checkin_date'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($booking['checkout_date'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($booking['status'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($booking['guest_name'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($booking['email'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($booking['created_at'] ?? '') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        if ($isConflict) {
            echo "<p><small style='color: red;'>* Highlighted in red - conflicting bookings</small></p>";
        }
    } else {
        echo "<p>No bookings found for this room.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error when receiving reservations: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><small>To delete this file after verification: delete check-booking-conflicts.php from hosting</small></p>";
?>



