# 📋 Как найти логи PHP на хостинге

## 🎯 Основные способы

### 1. Через cPanel (GreenGeeks использует cPanel)

**Шаги:**
1. Войдите в **cPanel** на хостинге
2. Найдите раздел **"Metrics"** или **"Статистика"**
3. Найдите **"Errors"** или **"Ошибки"**
4. Нажмите на **"Errors"**
5. Откроется список последних ошибок PHP

**Альтернативный путь:**
- В cPanel найдите **"File Manager"** (Менеджер файлов)
- Перейдите в папку **`logs/`** или **`public_html/logs/`**
- Найдите файлы:
  - `error_log`
  - `php_error_log`
  - `error_log-YYYY-MM-DD.txt`

---

### 2. Через File Manager (Менеджер файлов)

**Шаги:**
1. Войдите в **cPanel**
2. Откройте **"File Manager"** (Менеджер файлов)
3. Включите **"Show Hidden Files"** (Показать скрытые файлы)
4. Перейдите в корневую папку сайта (обычно `public_html/`)
5. Найдите файл **`error_log`** или **`.error_log`**
6. Нажмите правой кнопкой → **"View"** или **"Edit"**

**Также проверьте:**
- `public_html/error_log`
- `public_html/logs/error_log`
- `logs/error_log`
- `.logs/error_log`

---

### 3. Через SSH (если доступен)

**Если у вас есть SSH доступ:**

```bash
# Перейдите в корневую папку сайта
cd ~/public_html

# Просмотр последних 100 строк лога ошибок
tail -n 100 error_log

# Или поиск по ключевому слову
tail -n 500 error_log | grep "Booking API"

# Или поиск по Mailgun
tail -n 500 error_log | grep "Mailgun"
```

---

### 4. Через FTP

**Шаги:**
1. Подключитесь к хостингу через FTP клиент (FileZilla, WinSCP и т.д.)
2. Перейдите в корневую папку сайта (обычно `public_html/`)
3. Найдите файл `error_log` или `.error_log`
4. Скачайте файл на компьютер
5. Откройте в текстовом редакторе

---

## 🔍 Где обычно находятся логи

### На GreenGeeks / cPanel хостинге:

```
/home/username/public_html/error_log
/home/username/logs/error_log
/home/username/.logs/error_log
/var/log/apache2/error_log (только через SSH)
```

### На WordPress хостинге:

```
public_html/wp-content/debug.log (если включен WP_DEBUG)
public_html/error_log
```

---

## 📝 Как создать свой лог-файл

**Если не можете найти стандартные логи, создайте свой:**

**Создайте файл `view_logs.php`:**

```php
<?php
// view_logs.php - Просмотр логов
// ВАЖНО: Удалите этот файл после использования!

$logFile = __DIR__ . '/booking_debug.log';

if (isset($_GET['clear'])) {
    file_put_contents($logFile, '');
    echo "Log cleared!<br><br>";
}

if (file_exists($logFile)) {
    $logs = file_get_contents($logFile);
    echo "<h1>Booking Debug Logs</h1>";
    echo "<pre style='background: #f5f5f5; padding: 20px; border: 1px solid #ddd; max-height: 500px; overflow-y: auto;'>";
    echo htmlspecialchars($logs);
    echo "</pre>";
    echo "<br><a href='?clear=1'>Clear Log</a>";
} else {
    echo "No logs yet. Logs will appear here when bookings are created.";
}
?>
```

**И добавьте в `booking_api.php` в начало функции `handleCreateBooking`:**

```php
// Запись в лог
$logFile = __DIR__ . '/booking_debug.log';
$logMessage = date('Y-m-d H:i:s') . " - Booking created: ID " . ($bookingId ?? 'N/A') . "\n";
file_put_contents($logFile, $logMessage, FILE_APPEND);
```

---

## 🔧 Как включить логирование ошибок в PHP

**Если логи не пишутся, проверьте настройки PHP:**

**Создайте файл `phpinfo.php`:**

```php
<?php
phpinfo();
?>
```

**Откройте в браузере:** `https://new.backtobase.ca/phpinfo.php`

**Найдите:**
- `error_log` - путь к файлу логов
- `log_errors` - должно быть `On`
- `display_errors` - может быть `Off` (это нормально)

**Удалите `phpinfo.php` после проверки!**

---

## 📋 Специфично для GreenGeeks

**На GreenGeeks логи обычно находятся:**

1. **Через cPanel:**
   - Войдите в cPanel
   - **Metrics** → **Errors**
   - Или **File Manager** → `public_html/error_log`

2. **Через File Manager:**
   - Включите "Show Hidden Files"
   - Найдите файл `error_log` в корне `public_html/`

3. **Через SSH (если доступен):**
   ```bash
   tail -f ~/public_html/error_log
   ```

---

## 🎯 Что искать в логах

**После создания бронирования ищите:**

```
Booking API: Attempting to send confirmation email to guest: ...
Booking API: Guest confirmation email result: ...
Booking API: Attempting to send notification email to host: ...
Mailgun: Attempting to send email to ...
Mailgun: URL: ...
Mailgun: HTTP Code: ...
Mailgun: Response: ...
```

**Если есть ошибки:**
```
Booking API: Exception sending guest confirmation email: ...
Mailgun: API error (401): ...
Mailgun: cURL Error: ...
```

---

## ✅ Быстрый способ проверить

**Создайте файл `test_logging.php`:**

```php
<?php
error_log("TEST: This is a test log message at " . date('Y-m-d H:i:s'));

// Также попробуем записать в файл
$logFile = __DIR__ . '/test_log.txt';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Test log message\n", FILE_APPEND);

echo "Log message sent!<br>";
echo "Check error_log file in public_html/<br>";
echo "Check test_log.txt file in public_html/<br>";
echo "<br>";
echo "<a href='test_log.txt'>View test_log.txt</a>";
?>
```

**Использование:**
1. Загрузите `test_logging.php` на хостинг
2. Откройте в браузере: `https://new.backtobase.ca/test_logging.php`
3. Проверьте файлы:
   - `error_log` в `public_html/`
   - `test_log.txt` в `public_html/`

**Удалите файлы после проверки!**

---

## 🚨 Безопасность

**ВАЖНО:**
- **НЕ оставляйте** файлы `phpinfo.php`, `test_logging.php`, `view_logs.php` на хостинге
- Удалите их после использования
- Логи могут содержать чувствительную информацию
- Не публикуйте логи в открытом доступе

---

## 📞 Если не можете найти логи

**Обратитесь в поддержку хостинга:**

1. **GreenGeeks Support:**
   - Через Live Chat в cPanel
   - Или через тикет-систему
   - Спросите: "Where are PHP error logs located?"

2. **Укажите:**
   - Ваш домен
   - Что вы хотите найти логи ошибок PHP
   - Для диагностики отправки email

---

## 🎯 Итог

**Самый простой способ:**
1. Войдите в **cPanel**
2. Откройте **File Manager**
3. Включите **"Show Hidden Files"**
4. Найдите файл **`error_log`** в `public_html/`
5. Откройте и посмотрите последние записи

**Или используйте созданный файл `check-booking-emails.php` для проверки отправки писем!** 🚀



