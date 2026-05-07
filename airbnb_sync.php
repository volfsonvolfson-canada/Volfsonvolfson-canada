<?php
/**
 * Airbnb Calendar Sync Service
 * Synchronizing Airbnb calendars with the database
 */

require_once 'config.php';
require_once 'common.php';
require_once 'ical_parser.php';

/**
 * Airbnb calendar sync for all rooms or a specific room
 * 
 * @param string|null $roomName Room name (null for all rooms)
 * @return array Synchronization result
 */
function syncAirbnbCalendar($roomName = null) {
    global $conn;
    
    $results = [
        'success' => true,
        'synced_rooms' => [],
        'errors' => []
    ];
    
    try {
        // We get a list of rooms to synchronize
        $roomsToSync = [];
        
        // Getting the iCal URL array from config.php
        global $AIRBNB_ICAL_URLS;
        
        if ($roomName) {
            // Single room synchronization
            if (isset($AIRBNB_ICAL_URLS) && is_array($AIRBNB_ICAL_URLS) && isset($AIRBNB_ICAL_URLS[$roomName])) {
                $icalUrl = $AIRBNB_ICAL_URLS[$roomName];
                if (!empty($icalUrl)) {
                    $roomsToSync[$roomName] = $icalUrl;
                }
            }
        } else {
            // Sync all rooms
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
        
        // Synchronizing each room
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
        
        // If there were errors, but at least one room was synchronized successfully
        if (!empty($results['errors']) && !empty($results['synced_rooms'])) {
            $results['success'] = true; // Partial success
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
 * Single room synchronization
 * 
 * @param mysqli $conn Database connection
 * @param string $roomName Room name
 * @param string $icalUrl iCal calendar URL
 * @return array Synchronization result
 */
function syncSingleRoom($conn, $roomName, $icalUrl) {
    $result = [
        'success' => false,
        'blocked_dates_count' => 0,
        'sync_time' => null,
        'error' => null
    ];
    
    try {
        // Parsim iCal calendar
        $blockedDates = parseIcalUrl($icalUrl);
        
        if (!is_array($blockedDates)) {
            $result['error'] = 'Failed to parse iCal calendar';
            logActivity("Failed to parse iCal calendar for room: {$roomName}", 'ERROR');
            return $result;
        }
        
        $blockedDatesCount = count($blockedDates);
        
        // Deleting old entries for this room
        $deleteQuery = "DELETE FROM airbnb_calendar WHERE room_name = ?";
        $stmt = $conn->prepare($deleteQuery);
        $stmt->bind_param("s", $roomName);
        
        if (!$stmt->execute()) {
            $result['error'] = 'Failed to delete old calendar data';
            return $result;
        }
        $stmt->close();
        
        // Inserting new records
        if (!empty($blockedDates)) {
            $insertQuery = "INSERT INTO airbnb_calendar (room_name, date, is_available, last_synced_at) VALUES (?, ?, 0, NOW()) 
                           ON DUPLICATE KEY UPDATE is_available = 0, last_synced_at = NOW()";
            $stmt = $conn->prepare($insertQuery);
            
            foreach ($blockedDates as $date => $blocked) {
                // $blockedDates is an associative array ['YYYY-MM-DD' => true, ...]
                // where the keys are dates and the values ​​are true (all dates in the array are locked)
                if ($blocked) {
                    $stmt->bind_param("ss", $roomName, $date);
                    if (!$stmt->execute()) {
                        logActivity("Failed to insert blocked date: {$roomName} - {$date}", 'WARNING');
                    }
                }
            }
            
            $stmt->close();
        }
        
        // We also update the available dates (dates that are not in blockedDates are available)
        // But for this you need to know the date range, so let's leave it as is
        // Logic can be added to clean up old entries
        
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
 * Checking date availability based on Airbnb synchronization
 * 
 * @param mysqli $conn Database connection
 * @param string $roomName Room name
 * @param string $date Date in YYYY-MM-DD format
 * @return bool true if the date is available, false if blocked
 */
function checkAirbnbAvailability($conn, $roomName, $date) {
    try {
        // Checking the airbnb_calendar table
        $query = "SELECT is_available FROM airbnb_calendar WHERE room_name = ? AND date = ? LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $roomName, $date);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            // If is_available = 0, then the date is blocked in Airbnb
            return $row['is_available'] == 1;
        }
        
        // If there is no entry, we consider the date available
        // (maybe sync hasn't happened yet)
        return true;
        
    } catch (Exception $e) {
        logActivity("Error checking Airbnb availability: " . $e->getMessage(), 'ERROR');
        // In case of an error, we consider the date available (we do not block the reservation)
        return true;
    }
}

/**
 * Getting all occupied dates from Airbnb for a room
 * 
 * @param mysqli $conn Database connection
 * @param string $roomName Room name
 * @param string|null $dateFrom Start date (optional)
 * @param string|null $dateTo End date (optional)
 * @return array Array of busy dates
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
 * Getting information about the last synchronization
 * 
 * @param mysqli $conn Database connection
 * @param string|null $roomName Room name (null for all)
 * @return array Synchronization information
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

