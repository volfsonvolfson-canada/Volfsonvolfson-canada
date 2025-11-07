<?php
/**
 * Проверка: Какой домен используется для отправки писем
 * 
 * Использование:
 * 1. Загрузите этот файл на хостинг
 * 2. Откройте в браузере: https://new.backtobase.ca/check_mailgun_domain.php
 * 3. Проверьте результат
 */

require_once 'config.php';

echo "<h1>Mailgun Domain Check</h1>";

echo "<h2>Текущие настройки в config.php:</h2>";
echo "<ul>";
echo "<li><strong>MAILGUN_DOMAIN:</strong> " . (defined('MAILGUN_DOMAIN') ? htmlspecialchars(MAILGUN_DOMAIN) : 'НЕ НАСТРОЕН') . "</li>";
echo "<li><strong>MAILGUN_FROM_EMAIL:</strong> " . (defined('MAILGUN_FROM_EMAIL') ? htmlspecialchars(MAILGUN_FROM_EMAIL) : 'НЕ НАСТРОЕН') . "</li>";
echo "<li><strong>MAILGUN_API_KEY:</strong> " . (defined('MAILGUN_API_KEY') && !empty(MAILGUN_API_KEY) ? 'Настроен (' . substr(MAILGUN_API_KEY, 0, 10) . '...)' : 'НЕ НАСТРОЕН') . "</li>";
echo "</ul>";

echo "<h2>Какой домен используется для отправки:</h2>";

if (defined('MAILGUN_DOMAIN') && !empty(MAILGUN_DOMAIN)) {
    echo "<p><strong>Текущий домен:</strong> <code>" . htmlspecialchars(MAILGUN_DOMAIN) . "</code></p>";
    
    if (strpos(MAILGUN_DOMAIN, 'sandbox') !== false) {
        echo "<p style='color: orange;'><strong>⚠️ Используется Sandbox Domain</strong></p>";
        echo "<p>Sandbox Domain имеет ограничения:</p>";
        echo "<ul>";
        echo "<li>Можно отправлять только на авторизованные email-адреса</li>";
        echo "<li>Письма могут попадать в спам</li>";
        echo "<li>Ограничения по доставляемости</li>";
        echo "</ul>";
        echo "<p><strong>Рекомендация:</strong> Если домен <code>new.backtobase.ca</code> верифицирован в Mailgun, переключитесь на него!</p>";
    } else {
        echo "<p style='color: green;'><strong>✅ Используется верифицированный домен</strong></p>";
        echo "<p>Это хорошо! Верифицированный домен имеет лучшую доставляемость.</p>";
    }
} else {
    echo "<p style='color: red;'><strong>❌ MAILGUN_DOMAIN не настроен!</strong></p>";
}

echo "<hr>";

echo "<h2>Проверка в Mailgun Dashboard:</h2>";
echo "<p>Проверьте статус домена <code>new.backtobase.ca</code> в Mailgun:</p>";
echo "<ol>";
echo "<li>Mailgun Dashboard → <strong>Sending</strong> → <strong>Domains</strong></li>";
echo "<li>Найдите домен <code>new.backtobase.ca</code></li>";
echo "<li>Проверьте статус:</li>";
echo "<ul>";
echo "<li>✅ <strong>Verified</strong> - домен верифицирован, можно использовать</li>";
echo "<li>⏳ <strong>Pending</strong> - ожидает верификации (подождите)</li>";
echo "<li>⚠️ <strong>Unverified</strong> - DNS записи не найдены или неправильные</li>";
echo "</ul>";
echo "</ol>";

echo "<h2>Рекомендация:</h2>";

if (defined('MAILGUN_DOMAIN') && strpos(MAILGUN_DOMAIN, 'sandbox') !== false) {
    echo "<p>Если домен <code>new.backtobase.ca</code> имеет статус <strong>Verified</strong> в Mailgun:</p>";
    echo "<ol>";
    echo "<li>Обновите <code>config.php</code>:</li>";
    echo "<pre>";
    echo "define('MAILGUN_DOMAIN', 'new.backtobase.ca'); // ✅ Вместо Sandbox\n";
    echo "define('MAILGUN_FROM_EMAIL', 'bookings@new.backtobase.ca'); // ✅ Опционально\n";
    echo "</pre>";
    echo "<li>Загрузите обновленный <code>config.php</code> на хостинг</li>";
    echo "<li>Протестируйте отправку писем</li>";
    echo "</ol>";
    echo "<p><strong>Преимущества верифицированного домена:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Можно отправлять на любые email-адреса (без ограничений)</li>";
    echo "<li>✅ Лучшая доставляемость (не попадает в спам)</li>";
    echo "<li>✅ Профессионально - письма от @new.backtobase.ca</li>";
    echo "</ul>";
} else {
    echo "<p>Текущая настройка использует верифицированный домен - это хорошо!</p>";
}

echo "<hr>";
echo "<p><small>Для удаления этого файла после проверки: удалите check_mailgun_domain.php с хостинга</small></p>";
?>



