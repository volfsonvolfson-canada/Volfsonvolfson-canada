<?php
/**
 * Тест создания бронирования через API
 * 
 * Использование:
 * 1. Загрузите этот файл на хостинг
 * 2. Откройте в браузере: https://new.backtobase.ca/test-booking-creation.php
 * 3. Проверьте результат
 */

require_once 'config.php';
require_once 'common.php';
require_once 'booking_api.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Тест создания бронирования через API</h1>";

// Симулируем данные бронирования
$_POST = [
    'action' => 'create_booking',
    'room_name' => 'Basement — Queen',
    'checkin_date' => date('Y-m-d', strtotime('+7 days')),
    'checkout_date' => date('Y-m-d', strtotime('+10 days')),
    'guest_name' => 'Test Guest',
    'email' => 'test@example.com',
    'phone' => '+1234567890',
    'guests_count' => '2',
    'pets' => '0',
    'special_requests' => 'Test booking from test script'
];

echo "<h2>1. Данные для создания бронирования:</h2>";
echo "<pre>";
print_r($_POST);
echo "</pre>";

echo "<h2>2. Попытка создать бронирование:</h2>";

try {
    // Вызываем API напрямую
    ob_start();
    include 'api.php';
    $output = ob_get_clean();
    
    echo "<p>Результат API:</p>";
    echo "<pre>";
    echo htmlspecialchars($output);
    echo "</pre>";
    
    // Парсим JSON ответ
    $json = json_decode($output, true);
    if ($json) {
        if ($json['success']) {
            echo "<p style='color: green;'><strong>✅ Бронирование создано успешно!</strong></p>";
            echo "<p>Booking ID: " . ($json['data']['booking_id'] ?? 'N/A') . "</p>";
            echo "<p>Confirmation Code: " . ($json['data']['confirmation_code'] ?? 'N/A') . "</p>";
        } else {
            echo "<p style='color: red;'><strong>❌ Ошибка создания бронирования!</strong></p>";
            echo "<p>Ошибка: " . htmlspecialchars($json['error'] ?? 'Unknown error') . "</p>";
        }
    } else {
        echo "<p style='color: orange;'><strong>⚠️ Не удалось распарсить ответ API</strong></p>";
        echo "<p>Ответ не является валидным JSON.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>❌ Исключение при создании бронирования:</strong></p>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h2>3. Проверка последних бронирований в базе:</h2>";

try {
    $query = "SELECT id, room_name, guest_name, email, status, created_at FROM bookings ORDER BY created_at DESC LIMIT 5";
    $result = fetchAll($conn, $query);
    
    if ($result && count($result) > 0) {
        echo "<p>Найдено бронирований: " . count($result) . "</p>";
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
        echo "<p>Бронирования не найдены в базе данных.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Ошибка при проверке бронирований: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><small>Для удаления этого файла после проверки: удалите test-booking-creation.php с хостинга</small></p>";
?>



