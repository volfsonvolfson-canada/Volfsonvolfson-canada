<?php
/**
 * Тестовый файл для проверки отправки писем через Mailgun
 * 
 * Использование:
 * 1. Загрузите этот файл на хостинг
 * 2. Откройте в браузере: https://new.backtobase.ca/test_mailgun.php
 * 3. Проверьте результат
 */

require_once 'config.php';
require_once 'email_service.php';

// Проверяем настройки
$checks = [];

// Проверка 1: API Key
if (empty(MAILGUN_API_KEY)) {
    $checks[] = "❌ MAILGUN_API_KEY не настроен";
} else {
    $checks[] = "✅ MAILGUN_API_KEY настроен: " . substr(MAILGUN_API_KEY, 0, 10) . "...";
}

// Проверка 2: Domain
if (empty(MAILGUN_DOMAIN)) {
    $checks[] = "❌ MAILGUN_DOMAIN не настроен";
} else {
    $checks[] = "✅ MAILGUN_DOMAIN настроен: " . MAILGUN_DOMAIN;
}

// Проверка 3: From Email
if (empty(MAILGUN_FROM_EMAIL)) {
    $checks[] = "❌ MAILGUN_FROM_EMAIL не настроен";
} else {
    $checks[] = "✅ MAILGUN_FROM_EMAIL настроен: " . MAILGUN_FROM_EMAIL;
}

// Проверка 4: Host Email
if (empty(MAILGUN_HOST_EMAIL)) {
    $checks[] = "⚠️ MAILGUN_HOST_EMAIL не настроен (опционально)";
} else {
    $checks[] = "✅ MAILGUN_HOST_EMAIL настроен: " . MAILGUN_HOST_EMAIL;
}

echo "<h1>Mailgun Configuration Test</h1>";
echo "<h2>Проверка настроек:</h2>";
echo "<ul>";
foreach ($checks as $check) {
    echo "<li>" . $check . "</li>";
}
echo "</ul>";

// Если все настроено, пробуем отправить тестовое письмо
if (!empty(MAILGUN_API_KEY) && !empty(MAILGUN_DOMAIN) && !empty(MAILGUN_FROM_EMAIL)) {
    echo "<h2>Тест отправки письма:</h2>";
    
    // Используем авторизованный email для теста
    $testEmail = MAILGUN_HOST_EMAIL ?: MAILGUN_FROM_EMAIL;
    
    echo "<p>Отправка тестового письма на: <strong>" . htmlspecialchars($testEmail) . "</strong></p>";
    echo "<p><em>Примечание: Для Sandbox Domain email должен быть в списке авторизованных получателей!</em></p>";
    
    $result = sendEmail(
        $testEmail,
        'Test Email from Back to Base',
        '<h1>Test Email</h1><p>This is a test email from Back to Base Hotel booking system.</p><p>If you received this email, Mailgun is working correctly!</p>',
        'This is a test email from Back to Base Hotel booking system. If you received this email, Mailgun is working correctly!'
    );
    
    echo "<h3>Результат:</h3>";
    echo "<pre>";
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "</pre>";
    
    if ($result['success']) {
        echo "<p style='color: green;'><strong>✅ Письмо отправлено успешно!</strong></p>";
        echo "<p>Проверьте:</p>";
        echo "<ul>";
        echo "<li>Почтовый ящик " . htmlspecialchars($testEmail) . " (и папку Spam)</li>";
        echo "<li>Mailgun Dashboard → Sending → Logs (должна появиться запись об отправке)</li>";
        echo "</ul>";
    } else {
        echo "<p style='color: red;'><strong>❌ Ошибка отправки письма!</strong></p>";
        echo "<p><strong>Ошибка:</strong> " . htmlspecialchars($result['error'] ?? 'Unknown error') . "</p>";
        echo "<p>Проверьте:</p>";
        echo "<ul>";
        echo "<li>Что API ключ правильный</li>";
        echo "<li>Что Domain правильный</li>";
        echo "<li>Что email получателя в списке авторизованных (для Sandbox Domain)</li>";
        echo "<li>Логи PHP на хостинге для подробностей</li>";
        echo "</ul>";
    }
} else {
    echo "<p style='color: red;'><strong>❌ Не все настройки заполнены. Заполните config.php перед тестированием.</strong></p>";
}

echo "<hr>";
echo "<p><small>Для удаления этого файла после тестирования: удалите test_mailgun.php с хостинга</small></p>";
?>



