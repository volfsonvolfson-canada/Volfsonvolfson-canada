<?php
/**
 * iCal Parser
 * Parsing iCal calendars from Airbnb
 */

require_once 'config.php';
require_once 'common.php';

/**
 * Parse iCal URL and extract busy dates
 * 
 * @param string $icalUrl iCal calendar URL
 * @return array Array of busy dates in the format ['YYYY-MM-DD' => true, ...]
 */
function parseIcalUrl($icalUrl) {
    if (empty($icalUrl)) {
        return [];
    }
    
    try {
        // Loading iCal data
        $icalData = fetchIcalData($icalUrl);
        
        if (empty($icalData)) {
            logActivity("Failed to fetch iCal data from: " . $icalUrl, 'WARNING');
            return [];
        }
        
        // Parse iCal format
        $blockedDates = parseIcalData($icalData);
        
        logActivity("Parsed iCal calendar: " . count($blockedDates) . " blocked dates found");
        
        return $blockedDates;
        
    } catch (Exception $e) {
        logActivity("Error parsing iCal URL: " . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * Loading iCal data by URL
 * 
 * @param string $icalUrl iCal calendar URL
 * @return string Contents of iCal file or empty string
 */
function fetchIcalData($icalUrl) {
    if (empty($icalUrl)) {
        return '';
    }
    
    try {
        // Using cURL to download
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
 * Parse iCal data and extract busy dates
 * 
 * @param string $icalData Contents of the iCal file
 * @return array Array of busy dates in the format ['YYYY-MM-DD' => true, ...]
 */
function parseIcalData($icalData) {
    if (empty($icalData)) {
        return [];
    }
    
    $blockedDates = [];
    
    try {
        // Break it down into events (VEVENT)
        $events = extractIcalEvents($icalData);
        
        foreach ($events as $event) {
            // Retrieving start and end dates
            $startDate = extractIcalDate($event, 'DTSTART');
            $endDate = extractIcalDate($event, 'DTEND');
            
            if ($startDate && $endDate) {
                // Generate all dates between start and end
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
 * Extracting Events from iCal Data
 * 
 * @param string $icalData Contents of the iCal file
 * @return array Array of events
 */
function extractIcalEvents($icalData) {
    $events = [];
    $lines = explode("\n", $icalData);
    $currentEvent = [];
    $inEvent = false;
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Start of the event
        if (strpos($line, 'BEGIN:VEVENT') !== false) {
            $inEvent = true;
            $currentEvent = [];
            continue;
        }
        
        // End of event
        if (strpos($line, 'END:VEVENT') !== false) {
            if ($inEvent && !empty($currentEvent)) {
                $events[] = implode("\n", $currentEvent);
            }
            $inEvent = false;
            $currentEvent = [];
            continue;
        }
        
        // Collecting event strings (processing multi-line values)
        if ($inEvent) {
            // If a line begins with a space, it is a continuation of the previous line
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
 * Extracting a date from an event
 * 
 * @param string $event Event string
 * @param string $property Property name (DTSTART, DTEND)
 * @return string|null Date in YYYYMMDD or null format
 */
function extractIcalDate($event, $property) {
    // Looking for a property in an event
    $pattern = '/' . preg_quote($property, '/') . '(?:;.*?)?:(.+)/';
    if (preg_match($pattern, $event, $matches)) {
        $dateStr = trim($matches[1]);
        
        // Remove TZID if any
        $dateStr = preg_replace('/;.*$/', '', $dateStr);
        
        // Extract the date (first 8 characters YYYYMMDD or YYYYMMDDTHHMMSS)
        if (preg_match('/^(\d{8})/', $dateStr, $dateMatch)) {
            return $dateMatch[1];
        }
    }
    
    return null;
}

/**
 * Generating an array of dates between start and end (inclusive start, excluding end)
 * 
 * @param string $startDate Start date in YYYYMMDD format
 * @param string $endDate End date in YYYYMMDD format
 * @return array Array of dates in YYYY-MM-DD format
 */
function generateDateRange($startDate, $endDate) {
    $dates = [];
    
    try {
        // Convert YYYYMMDD to timestamp
        // If the date is already in the YYYY-MM-DD format, use it as is
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
        
        // Generate dates (inclusive of the beginning, excluding the end - as in iCal)
        $current = $start;
        while ($current < $end) {
            $dates[] = date('Y-m-d', $current);
            $current = strtotime('+1 day', $current);
            
            // Infinite loop protection
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
 * Converting a date from one format to another
 * 
 * @param string $date Date in any format
 * @param string $format Output format (default 'Y-m-d')
 * @return string|null Date in the required format or null
 */
function convertIcalDate($date, $format = 'Y-m-d') {
    if (empty($date)) {
        return null;
    }
    
    try {
        // Trying different formats
        $formats = ['Ymd', 'Y-m-d', 'Y/m/d', 'Ymd\THis', 'Y-m-d H:i:s'];
        
        foreach ($formats as $fmt) {
            $dateObj = DateTime::createFromFormat($fmt, $date);
            if ($dateObj !== false) {
                return $dateObj->format($format);
            }
        }
        
        // Let's try strtotime
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

