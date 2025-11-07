<?php
/**
 * Cron Job для автоматической синхронизации календарей Airbnb
 * Этот скрипт должен запускаться по расписанию через cron
 * 
 * Настройка cron (например, каждый час):
 * 0 * * * * /usr/bin/php /path/to/cron_sync_airbnb.php
 * 
 * Или через веб-запрос (если cron недоступен):
 * https://your-site.com/cron_sync_airbnb.php
 */

// Устанавливаем максимальное время выполнения
set_time_limit(300); // 5 минут

// Загружаем необходимые файлы
require_once 'config.php';
require_once 'common.php';
require_once 'airbnb_sync.php';

/**
 * Основная функция синхронизации
 */
function runAirbnbSync() {
    global $conn;
    
    try {
        logActivity("Starting scheduled Airbnb sync...");
        
        // Синхронизируем все комнаты
        $result = syncAirbnbCalendar(null);
        
        if ($result['success']) {
            $roomsSynced = count($result['synced_rooms']);
            $errorsCount = count($result['errors']);
            
            logActivity("Scheduled Airbnb sync completed: {$roomsSynced} rooms synced, {$errorsCount} errors");
            
            // Если есть ошибки, логируем их
            if (!empty($result['errors'])) {
                foreach ($result['errors'] as $error) {
                    $room = $error['room'] ?? 'unknown';
                    $errorMsg = $error['error'] ?? 'Unknown error';
                    logActivity("Airbnb sync error for room {$room}: {$errorMsg}", 'WARNING');
                }
            }
            
            return true;
        } else {
            logActivity("Scheduled Airbnb sync failed", 'ERROR');
            return false;
        }
        
    } catch (Exception $e) {
        logActivity("Scheduled Airbnb sync exception: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

// Проверка, запущен ли скрипт из командной строки или через веб-запрос
if (php_sapi_name() === 'cli') {
    // Запуск из командной строки (cron)
    echo "Starting Airbnb sync...\n";
    $result = runAirbnbSync();
    
    if ($result) {
        echo "Airbnb sync completed successfully\n";
        exit(0);
    } else {
        echo "Airbnb sync failed\n";
        exit(1);
    }
} else {
    // Запуск через веб-запрос
    // Можно добавить простую проверку авторизации через токен
    $token = $_GET['token'] ?? '';
    $expectedToken = 'your-secret-token-here'; // ⚠️ ЗАМЕНИТЕ на безопасный токен
    
    if ($token !== $expectedToken) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    // Выполняем синхронизацию
    $result = runAirbnbSync();
    
    header('Content-Type: application/json');
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Airbnb sync completed successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Airbnb sync failed']);
    }
}



