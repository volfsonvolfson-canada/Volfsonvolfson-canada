<?php
/**
 * iCal Parser
 * Парсинг iCal календарей от Airbnb
 */

require_once 'config.php';
require_once 'common.php';

/**
 * Парсинг iCal URL и извлечение занятых дат
 * 
 * @param string $icalUrl URL iCal календаря
 * @return array Массив занятых дат в формате ['YYYY-MM-DD' => true, ...]
 */
function parseIcalUrl($icalUrl) {
    if (empty($icalUrl)) {
        return [];
    }
    
    try {
        // Загружаем iCal данные
        $icalData = fetchIcalData($icalUrl);
        
        if (empty($icalData)) {
            logActivity("Failed to fetch iCal data from: " . $icalUrl, 'WARNING');
            return [];
        }
        
        // Парсим iCal формат
        $blockedDates = parseIcalData($icalData);
        
        logActivity("Parsed iCal calendar: " . count($blockedDates) . " blocked dates found");
        
        return $blockedDates;
        
    } catch (Exception $e) {
        logActivity("Error parsing iCal URL: " . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * Загрузка iCal данных по URL
 * 
 * @param string $icalUrl URL iCal календаря
 * @return string Содержимое iCal файла или пустая строка
 */
function fetchIcalData($icalUrl) {
    if (empty($icalUrl)) {
        return '';
    }
    
    try {
        // Используем cURL для загрузки
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $icalUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'BackToBase/1.0');
        
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            logActivity("cURL error fetching iCal: " . $error, 'ERROR');
            return '';
        }
        
        if ($httpCode !== 200) {
            logActivity("HTTP error fetching iCal: " . $httpCode, 'WARNING');
            return '';
        }
        
        if (empty($data)) {
            logActivity("Empty iCal data received from: " . $icalUrl, 'WARNING');
            return '';
        }
        
        return $data;
        
    } catch (Exception $e) {
        logActivity("Exception fetching iCal data: " . $e->getMessage(), 'ERROR');
        return '';
    }
}

/**
 * Парсинг iCal данных и извлечение занятых дат
 * 
 * @param string $icalData Содержимое iCal файла
 * @return array Массив занятых дат в формате ['YYYY-MM-DD' => true, ...]
 */
function parseIcalData($icalData) {
    if (empty($icalData)) {
        return [];
    }
    
    $blockedDates = [];
    
    try {
        // Разбиваем на события (VEVENT)
        $events = extractIcalEvents($icalData);
        
        foreach ($events as $event) {
            // Извлекаем даты начала и конца
            $startDate = extractIcalDate($event, 'DTSTART');
            $endDate = extractIcalDate($event, 'DTEND');
            
            if ($startDate && $endDate) {
                // Генерируем все даты между началом и концом
                $dates = generateDateRange($startDate, $endDate);
                
                foreach ($dates as $date) {
                    $blockedDates[$date] = true;
                }
            }
        }
        
        return $blockedDates;
        
    } catch (Exception $e) {
        logActivity("Error parsing iCal data: " . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * Извлечение событий из iCal данных
 * 
 * @param string $icalData Содержимое iCal файла
 * @return array Массив событий
 */
function extractIcalEvents($icalData) {
    $events = [];
    $lines = explode("\n", $icalData);
    $currentEvent = [];
    $inEvent = false;
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Начало события
        if (strpos($line, 'BEGIN:VEVENT') !== false) {
            $inEvent = true;
            $currentEvent = [];
            continue;
        }
        
        // Конец события
        if (strpos($line, 'END:VEVENT') !== false) {
            if ($inEvent && !empty($currentEvent)) {
                $events[] = implode("\n", $currentEvent);
            }
            $inEvent = false;
            $currentEvent = [];
            continue;
        }
        
        // Собираем строки события (обрабатываем многострочные значения)
        if ($inEvent) {
            // Если строка начинается с пробела - это продолжение предыдущей строки
            if (!empty($line) && $line[0] === ' ') {
                if (!empty($currentEvent)) {
                    $lastIndex = count($currentEvent) - 1;
                    $currentEvent[$lastIndex] .= substr($line, 1);
                }
            } else {
                $currentEvent[] = $line;
            }
        }
    }
    
    return $events;
}

/**
 * Извлечение даты из события
 * 
 * @param string $event Строка события
 * @param string $property Название свойства (DTSTART, DTEND)
 * @return string|null Дата в формате YYYYMMDD или null
 */
function extractIcalDate($event, $property) {
    // Ищем свойство в событии
    $pattern = '/' . preg_quote($property, '/') . '(?:;.*?)?:(.+)/';
    if (preg_match($pattern, $event, $matches)) {
        $dateStr = trim($matches[1]);
        
        // Удаляем TZID если есть
        $dateStr = preg_replace('/;.*$/', '', $dateStr);
        
        // Извлекаем дату (первые 8 символов YYYYMMDD или YYYYMMDDTHHMMSS)
        if (preg_match('/^(\d{8})/', $dateStr, $dateMatch)) {
            return $dateMatch[1];
        }
    }
    
    return null;
}

/**
 * Генерация массива дат между началом и концом (включительно начало, исключая конец)
 * 
 * @param string $startDate Дата начала в формате YYYYMMDD
 * @param string $endDate Дата конца в формате YYYYMMDD
 * @return array Массив дат в формате YYYY-MM-DD
 */
function generateDateRange($startDate, $endDate) {
    $dates = [];
    
    try {
        // Конвертируем YYYYMMDD в timestamp
        // Если дата уже в формате YYYY-MM-DD, используем её как есть
        if (strlen($startDate) === 8) {
            $start = strtotime(substr($startDate, 0, 4) . '-' . substr($startDate, 4, 2) . '-' . substr($startDate, 6, 2));
        } else {
            $start = strtotime($startDate);
        }
        
        if (strlen($endDate) === 8) {
            $end = strtotime(substr($endDate, 0, 4) . '-' . substr($endDate, 4, 2) . '-' . substr($endDate, 6, 2));
        } else {
            $end = strtotime($endDate);
        }
        
        if ($start === false || $end === false) {
            logActivity("Invalid date range: {$startDate} to {$endDate}", 'WARNING');
            return [];
        }
        
        // Генерируем даты (включительно начало, исключая конец - как в iCal)
        $current = $start;
        while ($current < $end) {
            $dates[] = date('Y-m-d', $current);
            $current = strtotime('+1 day', $current);
            
            // Защита от бесконечного цикла
            if (count($dates) > 1000) {
                logActivity("Date range too large: {$startDate} to {$endDate}", 'WARNING');
                break;
            }
        }
        
        return $dates;
        
    } catch (Exception $e) {
        logActivity("Error generating date range: " . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * Конвертация даты из одного формата в другой
 * 
 * @param string $date Дата в любом формате
 * @param string $format Выходной формат (по умолчанию 'Y-m-d')
 * @return string|null Дата в нужном формате или null
 */
function convertIcalDate($date, $format = 'Y-m-d') {
    if (empty($date)) {
        return null;
    }
    
    try {
        // Пробуем разные форматы
        $formats = ['Ymd', 'Y-m-d', 'Y/m/d', 'Ymd\THis', 'Y-m-d H:i:s'];
        
        foreach ($formats as $fmt) {
            $dateObj = DateTime::createFromFormat($fmt, $date);
            if ($dateObj !== false) {
                return $dateObj->format($format);
            }
        }
        
        // Пробуем strtotime
        $timestamp = strtotime($date);
        if ($timestamp !== false) {
            return date($format, $timestamp);
        }
        
        return null;
        
    } catch (Exception $e) {
        logActivity("Error converting date: " . $e->getMessage(), 'ERROR');
        return null;
    }
}

