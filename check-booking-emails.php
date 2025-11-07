<?php
/**
 * Проверка отправки писем для конкретного бронирования
 * 
 * Использование:
 * 1. Загрузите этот файл на хостинг
 * 2. Откройте в браузере: https://new.backtobase.ca/check-booking-emails.php?confirmation_code=BTB-1762237489-0003
 */

require_once 'config.php';
require_once 'common.php';
require_once 'email_service.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Проверка отправки писем для бронирования</h1>";

// Получаем код подтверждения
$confirmationCode = isset($_GET['confirmation_code']) ? trim($_GET['confirmation_code']) : null;

if (!$confirmationCode) {
    echo "<p style='color: red;'>❌ Укажите confirmation_code в URL</p>";
    echo "<p>Пример: <code>?confirmation_code=BTB-1762237489-0003</code></p>";
    exit;
}

// Получаем бронирование
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
    echo "<p style='color: red;'>❌ Бронирование не найдено</p>";
    exit;
}

echo "<h2>Информация о бронировании:</h2>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr><th>ID</th><td>" . htmlspecialchars($booking['id']) . "</td></tr>";
echo "<tr><th>Room</th><td>" . htmlspecialchars($booking['room_name']) . "</td></tr>";
echo "<tr><th>Guest</th><td>" . htmlspecialchars($booking['guest_name']) . "</td></tr>";
echo "<tr><th>Email</th><td>" . htmlspecialchars($booking['email']) . "</td></tr>";
echo "<tr><th>Status</th><td>" . htmlspecialchars($booking['status']) . "</td></tr>";
echo "<tr><th>Created</th><td>" . htmlspecialchars($booking['created_at']) . "</td></tr>";
echo "</table>";

echo "<h2>Проверка настроек Mailgun:</h2>";
echo "<ul>";
echo "<li>MAILGUN_DOMAIN: " . (empty(MAILGUN_DOMAIN) ? "❌ НЕ НАСТРОЕН" : "✅ " . MAILGUN_DOMAIN) . "</li>";
echo "<li>MAILGUN_FROM_EMAIL: " . (empty(MAILGUN_FROM_EMAIL) ? "❌ НЕ НАСТРОЕН" : "✅ " . MAILGUN_FROM_EMAIL) . "</li>";
echo "<li>MAILGUN_HOST_EMAIL: " . (empty(MAILGUN_HOST_EMAIL) ? "❌ НЕ НАСТРОЕН" : "✅ " . MAILGUN_HOST_EMAIL) . "</li>";
echo "</ul>";

if (empty(MAILGUN_API_KEY)) {
    echo "<p style='color: red;'>❌ Mailgun не настроен. Письма не могут быть отправлены.</p>";
    exit;
}

echo "<h2>Тест отправки писем:</h2>";

// Тест 1: Письмо гостю
echo "<h3>1. Письмо подтверждения гостю:</h3>";
echo "<p>Отправка на: <strong>" . htmlspecialchars($booking['email']) . "</strong></p>";

$guestResult = sendBookingConfirmation($booking);
echo "<p><strong>Результат:</strong></p>";
echo "<pre>";
echo json_encode($guestResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "</pre>";

if ($guestResult && $guestResult['success']) {
    echo "<p style='color: green;'>✅ Письмо гостю отправлено успешно!</p>";
    echo "<p>Message ID: " . htmlspecialchars($guestResult['message_id'] ?? 'N/A') . "</p>";
} else {
    echo "<p style='color: red;'>❌ Ошибка отправки письма гостю!</p>";
    echo "<p>Ошибка: " . htmlspecialchars($guestResult['error'] ?? 'Unknown error') . "</p>";
}

// Тест 2: Письмо хозяину
echo "<h3>2. Письмо уведомления хозяину:</h3>";
echo "<p>Отправка на: <strong>" . htmlspecialchars(MAILGUN_HOST_EMAIL) . "</strong></p>";

$hostResult = sendBookingRequestToHost($booking);
echo "<p><strong>Результат:</strong></p>";
echo "<pre>";
echo json_encode($hostResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "</pre>";

if ($hostResult && $hostResult['success']) {
    echo "<p style='color: green;'>✅ Письмо хозяину отправлено успешно!</p>";
    echo "<p>Message ID: " . htmlspecialchars($hostResult['message_id'] ?? 'N/A') . "</p>";
} else {
    echo "<p style='color: red;'>❌ Ошибка отправки письма хозяину!</p>";
    echo "<p>Ошибка: " . htmlspecialchars($hostResult['error'] ?? 'Unknown error') . "</p>";
}

echo "<hr>";
echo "<p><small>Для удаления этого файла после проверки: удалите check-booking-emails.php с хостинга</small></p>";
?>
