<?php
/**
 * Отладочный файл для проверки отправки писем
 * 
 * Использование:
 * 1. Загрузите этот файл на хостинг
 * 2. Откройте в браузере: https://new.backtobase.ca/debug_email_sending.php
 * 3. Проверьте результат
 */

require_once 'config.php';
require_once 'email_service.php';

echo "<h1>Email Sending Debug</h1>";

// Проверка 1: Настройки Mailgun
echo "<h2>1. Проверка настроек Mailgun:</h2>";
echo "<ul>";

$checks = [];

// Проверка API Key
if (empty(MAILGUN_API_KEY)) {
    $checks[] = "❌ MAILGUN_API_KEY не настроен";
} else {
    $checks[] = "✅ MAILGUN_API_KEY настроен: " . substr(MAILGUN_API_KEY, 0, 10) . "...";
}

// Проверка Domain
if (empty(MAILGUN_DOMAIN)) {
    $checks[] = "❌ MAILGUN_DOMAIN не настроен";
} else {
    $checks[] = "✅ MAILGUN_DOMAIN настроен: " . MAILGUN_DOMAIN;
}

// Проверка From Email
if (empty(MAILGUN_FROM_EMAIL)) {
    $checks[] = "❌ MAILGUN_FROM_EMAIL не настроен";
} else {
    $checks[] = "✅ MAILGUN_FROM_EMAIL настроен: " . MAILGUN_FROM_EMAIL;
}

foreach ($checks as $check) {
    echo "<li>" . $check . "</li>";
}
echo "</ul>";

// Проверка 2: Тест отправки письма
echo "<h2>2. Тест отправки письма:</h2>";

if (!empty(MAILGUN_API_KEY) && !empty(MAILGUN_DOMAIN)) {
    $testEmail = MAILGUN_HOST_EMAIL ?: MAILGUN_FROM_EMAIL;
    
    echo "<p>Попытка отправить тестовое письмо на: <strong>" . htmlspecialchars($testEmail) . "</strong></p>";
    echo "<p>Используемый домен: <strong>" . htmlspecialchars(MAILGUN_DOMAIN) . "</strong></p>";
    
    // Пробуем отправить письмо напрямую через sendEmail
    $result = sendEmail(
        $testEmail,
        'Debug Test Email',
        '<h1>Debug Test</h1><p>This is a debug test email.</p>',
        'This is a debug test email.'
    );
    
    echo "<h3>Результат отправки:</h3>";
    echo "<pre>";
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "</pre>";
    
    if ($result['success']) {
        echo "<p style='color: green;'><strong>✅ Письмо отправлено успешно!</strong></p>";
        echo "<p>Проверьте Mailgun Dashboard → Sending → Logs</p>";
    } else {
        echo "<p style='color: red;'><strong>❌ Ошибка отправки письма!</strong></p>";
        echo "<p><strong>Ошибка:</strong> " . htmlspecialchars($result['error'] ?? 'Unknown error') . "</p>";
    }
} else {
    echo "<p style='color: red;'><strong>❌ Не все настройки заполнены. Заполните config.php перед тестированием.</strong></p>";
}

// Проверка 3: Проверка последних бронирований
echo "<h2>3. Проверка последних бронирований:</h2>";

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
    } else {
        echo "<p>Бронирования не найдены в базе данных.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Ошибка при проверке бронирований: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Проверка 4: Проверка логов активности
echo "<h2>4. Последние логи активности (если есть):</h2>";

try {
    if (function_exists('logActivity')) {
        // Проверяем, есть ли таблица activity_logs
        $query = "SHOW TABLES LIKE 'activity_logs'";
        $tables = fetchAll($conn, $query);
        
        if ($tables && count($tables) > 0) {
            $logQuery = "SELECT * FROM activity_logs WHERE message LIKE '%email%' OR message LIKE '%Email%' OR message LIKE '%mail%' ORDER BY created_at DESC LIMIT 10";
            $logs = fetchAll($conn, $logQuery);
            
            if ($logs && count($logs) > 0) {
                echo "<p>Найдено логов: " . count($logs) . "</p>";
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
                echo "<p>Логи email не найдены.</p>";
            }
        } else {
            echo "<p>Таблица activity_logs не найдена.</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color: orange;'>Не удалось проверить логи: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><small>Для удаления этого файла после проверки: удалите debug_email_sending.php с хостинга</small></p>";
?>



