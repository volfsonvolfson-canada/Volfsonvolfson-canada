<?php
/**
 * Проверка настроек Mailgun на хостинге
 * 
 * Использование:
 * 1. Загрузите этот файл на хостинг
 * 2. Откройте в браузере: https://new.backtobase.ca/check-mailgun-config.php
 * 3. Проверьте результат
 */

require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Проверка настроек Mailgun на хостинге</h1>";

echo "<h2>1. Проверка констант:</h2>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";

$checks = [];

// Проверка MAILGUN_DOMAIN
if (defined('MAILGUN_DOMAIN')) {
    $domain = MAILGUN_DOMAIN;
    $checks[] = [
        'name' => 'MAILGUN_DOMAIN',
        'value' => $domain,
        'status' => ($domain === 'new.backtobase.ca') ? '✅ Правильно' : '❌ Неправильно (должно быть: new.backtobase.ca)'
    ];
} else {
    $checks[] = [
        'name' => 'MAILGUN_DOMAIN',
        'value' => 'НЕ ОПРЕДЕЛЕНО',
        'status' => '❌ НЕ НАСТРОЕНО'
    ];
}

// Проверка MAILGUN_FROM_EMAIL
if (defined('MAILGUN_FROM_EMAIL')) {
    $fromEmail = MAILGUN_FROM_EMAIL;
    $checks[] = [
        'name' => 'MAILGUN_FROM_EMAIL',
        'value' => $fromEmail,
        'status' => ($fromEmail === 'bookings@new.backtobase.ca') ? '✅ Правильно' : '❌ Неправильно (должно быть: bookings@new.backtobase.ca)'
    ];
} else {
    $checks[] = [
        'name' => 'MAILGUN_FROM_EMAIL',
        'value' => 'НЕ ОПРЕДЕЛЕНО',
        'status' => '❌ НЕ НАСТРОЕНО'
    ];
}

// Проверка MAILGUN_API_KEY
if (defined('MAILGUN_API_KEY')) {
    $apiKey = MAILGUN_API_KEY;
    $checks[] = [
        'name' => 'MAILGUN_API_KEY',
        'value' => substr($apiKey, 0, 10) . '...',
        'status' => (!empty($apiKey)) ? '✅ Настроено' : '❌ Пустой'
    ];
} else {
    $checks[] = [
        'name' => 'MAILGUN_API_KEY',
        'value' => 'НЕ ОПРЕДЕЛЕНО',
        'status' => '❌ НЕ НАСТРОЕНО'
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

echo "<h2>2. Проверка последних бронирований:</h2>";

try {
    require_once 'common.php';
    
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
        
        echo "<p><strong>Последнее бронирование создано:</strong> " . htmlspecialchars($result[0]['created_at'] ?? 'N/A') . "</p>";
    } else {
        echo "<p>Бронирования не найдены в базе данных.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Ошибка при проверке бронирований: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<h2>3. Что проверить:</h2>";
echo "<ul>";
echo "<li>✅ <strong>MAILGUN_DOMAIN</strong> должен быть: <code>new.backtobase.ca</code></li>";
echo "<li>✅ <strong>MAILGUN_FROM_EMAIL</strong> должен быть: <code>bookings@new.backtobase.ca</code></li>";
echo "<li>✅ Создайте новое бронирование ПОСЛЕ обновления config.php</li>";
echo "<li>✅ Проверьте логи PHP на хостинге после создания бронирования</li>";
echo "</ul>";

echo "<hr>";
echo "<p><small>Для удаления этого файла после проверки: удалите check-mailgun-config.php с хостинга</small></p>";
?>



