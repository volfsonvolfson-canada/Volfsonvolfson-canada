<?php
/**
 * Airbnb Calendar Sync Service
 * Синхронизация календарей Airbnb с базой данных
 */

require_once 'config.php';
require_once 'common.php';
require_once 'ical_parser.php';

/**
 * Синхронизация календаря Airbnb для всех комнат или конкретной комнаты
 * 
 * @param string|null $roomName Название комнаты (null для всех комнат)
 * @return array Результат синхронизации
 */
function syncAirbnbCalendar($roomName = null) {
    global $conn;
    
    $results = [
        'success' => true,
        'synced_rooms' => [],
        'errors' => []
    ];
    
    try {
        // Получаем список комнат для синхронизации
        $roomsToSync = [];
        
        // Получаем массив iCal URL из config.php
        global $AIRBNB_ICAL_URLS;
        
        if ($roomName) {
            // Синхронизация одной комнаты
            if (isset($AIRBNB_ICAL_URLS) && is_array($AIRBNB_ICAL_URLS) && isset($AIRBNB_ICAL_URLS[$roomName])) {
                $icalUrl = $AIRBNB_ICAL_URLS[$roomName];
                if (!empty($icalUrl)) {
                    $roomsToSync[$roomName] = $icalUrl;
                }
            }
        } else {
            // Синхронизация всех комнат
            if (isset($AIRBNB_ICAL_URLS) && is_array($AIRBNB_ICAL_URLS)) {
                foreach ($AIRBNB_ICAL_URLS as $room => $icalUrl) {
                    if (!empty($icalUrl)) {
                        $roomsToSync[$room] = $icalUrl;
                    }
                }
            }
        }
        
        if (empty($roomsToSync)) {
            $results['success'] = false;
            $results['errors'][] = 'No rooms configured for Airbnb sync';
            return $results;
        }
        
        // Синхронизируем каждую комнату
        foreach ($roomsToSync as $room => $icalUrl) {
            try {
                $roomResult = syncSingleRoom($conn, $room, $icalUrl);
                
                if ($roomResult['success']) {
                    $results['synced_rooms'][] = [
                        'room' => $room,
                        'blocked_dates_count' => $roomResult['blocked_dates_count'],
                        'sync_time' => $roomResult['sync_time']
                    ];
                } else {
                    $results['errors'][] = [
                        'room' => $room,
                        'error' => $roomResult['error']
                    ];
                }
                
            } catch (Exception $e) {
                $results['errors'][] = [
                    'room' => $room,
                    'error' => $e->getMessage()
                ];
                logActivity("Error syncing room {$room}: " . $e->getMessage(), 'ERROR');
            }
        }
        
        // Если были ошибки, но хотя бы одна комната синхронизирована успешно
        if (!empty($results['errors']) && !empty($results['synced_rooms'])) {
            $results['success'] = true; // Частичный успех
        }
        
        logActivity("Airbnb sync completed: " . count($results['synced_rooms']) . " rooms synced, " . count($results['errors']) . " errors");
        
        return $results;
        
    } catch (Exception $e) {
        logActivity("Airbnb sync error: " . $e->getMessage(), 'ERROR');
        $results['success'] = false;
        $results['errors'][] = ['error' => $e->getMessage()];
        return $results;
    }
}

/**
 * Синхронизация одной комнаты
 * 
 * @param mysqli $conn Соединение с БД
 * @param string $roomName Название комнаты
 * @param string $icalUrl URL iCal календаря
 * @return array Результат синхронизации
 */
function syncSingleRoom($conn, $roomName, $icalUrl) {
    $result = [
        'success' => false,
        'blocked_dates_count' => 0,
        'sync_time' => null,
        'error' => null
    ];
    
    try {
        // Парсим iCal календарь
        $blockedDates = parseIcalUrl($icalUrl);
        
        if (!is_array($blockedDates)) {
            $result['error'] = 'Failed to parse iCal calendar';
            logActivity("Failed to parse iCal calendar for room: {$roomName}", 'ERROR');
            return $result;
        }
        
        $blockedDatesCount = count($blockedDates);
        
        // Удаляем старые записи для этой комнаты
        $deleteQuery = "DELETE FROM airbnb_calendar WHERE room_name = ?";
        $stmt = $conn->prepare($deleteQuery);
        $stmt->bind_param("s", $roomName);
        
        if (!$stmt->execute()) {
            $result['error'] = 'Failed to delete old calendar data';
            return $result;
        }
        $stmt->close();
        
        // Вставляем новые записи
        if (!empty($blockedDates)) {
            $insertQuery = "INSERT INTO airbnb_calendar (room_name, date, is_available, last_synced_at) VALUES (?, ?, 0, NOW()) 
                           ON DUPLICATE KEY UPDATE is_available = 0, last_synced_at = NOW()";
            $stmt = $conn->prepare($insertQuery);
            
            foreach ($blockedDates as $date => $blocked) {
                // $blockedDates - это ассоциативный массив ['YYYY-MM-DD' => true, ...]
                // где ключи - это даты, а значения - true (все даты в массиве заблокированы)
                if ($blocked) {
                    $stmt->bind_param("ss", $roomName, $date);
                    if (!$stmt->execute()) {
                        logActivity("Failed to insert blocked date: {$roomName} - {$date}", 'WARNING');
                    }
                }
            }
            
            $stmt->close();
        }
        
        // Также обновляем доступные даты (даты, которых нет в blockedDates - доступны)
        // Но для этого нужно знать диапазон дат, поэтому оставим как есть
        // Можно добавить логику для очистки старых записей
        
        $result['success'] = true;
        $result['blocked_dates_count'] = $blockedDatesCount;
        $result['sync_time'] = date('Y-m-d H:i:s');
        
        logActivity("Successfully synced Airbnb calendar for room: {$roomName}, blocked dates: {$blockedDatesCount}");
        
        return $result;
        
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
        logActivity("Error syncing room {$roomName}: " . $e->getMessage(), 'ERROR');
        return $result;
    }
}

/**
 * Проверка доступности даты с учетом Airbnb синхронизации
 * 
 * @param mysqli $conn Соединение с БД
 * @param string $roomName Название комнаты
 * @param string $date Дата в формате YYYY-MM-DD
 * @return bool true если дата доступна, false если заблокирована
 */
function checkAirbnbAvailability($conn, $roomName, $date) {
    try {
        // Проверяем таблицу airbnb_calendar
        $query = "SELECT is_available FROM airbnb_calendar WHERE room_name = ? AND date = ? LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $roomName, $date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            // Если is_available = 0, значит дата заблокирована в Airbnb
            return $row['is_available'] == 1;
        }
        
        // Если записи нет, считаем дату доступной
        // (может быть, синхронизация еще не выполнялась)
        return true;
        
    } catch (Exception $e) {
        logActivity("Error checking Airbnb availability: " . $e->getMessage(), 'ERROR');
        // В случае ошибки считаем дату доступной (не блокируем бронирование)
        return true;
    }
}

/**
 * Получение всех занятых дат из Airbnb для комнаты
 * 
 * @param mysqli $conn Соединение с БД
 * @param string $roomName Название комнаты
 * @param string|null $dateFrom Начальная дата (опционально)
 * @param string|null $dateTo Конечная дата (опционально)
 * @return array Массив занятых дат
 */
function getAirbnbBlockedDates($conn, $roomName, $dateFrom = null, $dateTo = null) {
    try {
        $query = "SELECT date FROM airbnb_calendar WHERE room_name = ? AND is_available = 0";
        $params = [$roomName];
        $types = "s";
        
        if ($dateFrom) {
            $query .= " AND date >= ?";
            $params[] = $dateFrom;
            $types .= "s";
        }
        
        if ($dateTo) {
            $query .= " AND date <= ?";
            $params[] = $dateTo;
            $types .= "s";
        }
        
        $query .= " ORDER BY date ASC";
        
        $stmt = $conn->prepare($query);
        if (count($params) > 1) {
            $stmt->bind_param($types, ...$params);
        } else {
            $stmt->bind_param($types, $params[0]);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $blockedDates = [];
        while ($row = $result->fetch_assoc()) {
            $blockedDates[] = $row['date'];
        }
        
        $stmt->close();
        
        return $blockedDates;
        
    } catch (Exception $e) {
        logActivity("Error getting Airbnb blocked dates: " . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * Получение информации о последней синхронизации
 * 
 * @param mysqli $conn Соединение с БД
 * @param string|null $roomName Название комнаты (null для всех)
 * @return array Информация о синхронизации
 */
function getAirbnbSyncStatus($conn, $roomName = null) {
    try {
        if ($roomName) {
            $query = "SELECT room_name, MAX(last_synced_at) as last_synced, COUNT(*) as blocked_count 
                     FROM airbnb_calendar 
                     WHERE room_name = ? AND is_available = 0 
                     GROUP BY room_name";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $roomName);
        } else {
            $query = "SELECT room_name, MAX(last_synced_at) as last_synced, COUNT(*) as blocked_count 
                     FROM airbnb_calendar 
                     WHERE is_available = 0 
                     GROUP BY room_name";
            $stmt = $conn->prepare($query);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $status = [];
        while ($row = $result->fetch_assoc()) {
            $status[] = [
                'room_name' => $row['room_name'],
                'last_synced' => $row['last_synced'],
                'blocked_count' => $row['blocked_count']
            ];
        }
        
        $stmt->close();
        
        return $status;
        
    } catch (Exception $e) {
        logActivity("Error getting Airbnb sync status: " . $e->getMessage(), 'ERROR');
        return [];
    }
}

