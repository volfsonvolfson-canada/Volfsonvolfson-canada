<?php
/**
 * Cron Job to automatically sync Airbnb calendars
 * This script should be run on a schedule via cron
 * 
 * Setting up cron (for example, every hour):
 * 0 * * * * /usr/bin/php /path/to/cron_sync_airbnb.php
 * 
 * Or via web request (if cron is not available):
 * https://your-site.com/cron_sync_airbnb.php
 */

// Setting the maximum execution time
set_time_limit(300); // 5 minutes

// Download the necessary files
require_once 'config.php';
require_once 'common.php';
require_once 'airbnb_sync.php';

/**
 * Basic synchronization function
 */
function runAirbnbSync() {
    global $conn;
    
    try {
        logActivity("Starting scheduled Airbnb sync...");
        
        // Synchronizing all rooms
        $result = syncAirbnbCalendar(null);
        
        if ($result['success']) {
            $roomsSynced = count($result['synced_rooms']);
            $errorsCount = count($result['errors']);
            
            logActivity("Scheduled Airbnb sync completed: {$roomsSynced} rooms synced, {$errorsCount} errors");
            
            // If there are errors, we log them
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

// Checking if a script is running from the command line or via a web request
if (php_sapi_name() === 'cli') {
    // Run from command line (cron)
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
    // Launch via web request
    // You can add a simple authorization check via a token
    $token = $_GET['token'] ?? '';
    $expectedToken = 'your-secret-token-here'; // ⚠️ REPLACE with a secure token
    
    if ($token !== $expectedToken) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    // Performing synchronization
    $result = runAirbnbSync();
    
    header('Content-Type: application/json');
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Airbnb sync completed successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Airbnb sync failed']);
    }
}



