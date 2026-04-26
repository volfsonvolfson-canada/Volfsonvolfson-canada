<?php
// Image upload handler for admin panel
require_once 'common.php';

// Simple authentication check - you can enhance this
function isAdminAuthenticated() {
    // For now, just return true - you can add proper authentication later
    return true;
}

// Check if user is authenticated
if (!isAdminAuthenticated()) {
    sendError('Unauthorized access');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    error_log('Upload request received');
    error_log('Files: ' . print_r($_FILES, true));
    error_log('POST data: ' . print_r($_POST, true));
    
    $uploadDir = 'assets/';
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    
    $file = $_FILES['image'];
    $imageType = $_POST['image_type'] ?? ''; // basement, ground, loft
    
    error_log('File info: ' . print_r($file, true));
    error_log('Image type: ' . $imageType);
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        sendError('Upload error: ' . $file['error']);
        exit;
    }
    
    if (!in_array($file['type'], $allowedTypes)) {
        sendError('Invalid file type. Only JPEG, PNG, and GIF are allowed.');
        exit;
    }
    
    if ($file['size'] > $maxFileSize) {
        sendError('File too large. Maximum size is 5MB.');
        exit;
    }
    
    // Generate unique filename - normalize extension to lowercase .jpg
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    // Normalize JPEG extensions to jpg (jpeg -> jpg)
    if ($extension === 'jpeg') {
        $extension = 'jpg';
    }
    // Accept only jpg, png, gif formats
    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
        sendError('Invalid file extension. Only JPG, PNG, and GIF are allowed.');
        exit;
    }
    // Normalize image type for filename (remove 'homepage-' prefix for hero images)
    $normalizedType = $imageType;
    if ($imageType === 'homepage-hero') {
        $normalizedType = 'hero';
    } elseif ($imageType === 'homepage-hero2') {
        $normalizedType = 'hero2';
    }
    $filename = $normalizedType . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        error_log('File uploaded successfully to: ' . $filepath);
        
        // Verify file exists and is readable
        if (!file_exists($filepath) || !is_readable($filepath)) {
            sendError('File uploaded but not accessible');
            exit;
        }
        
        error_log('File verified: exists and readable');
        
        // Update database with new image path
        try {
            error_log('Connecting to database...');
            
            // Check if database connection exists
            if (!isset($conn) || !$conn) {
                error_log('Database connection not available');
                sendError('Database connection not available');
                exit;
            }
            
            error_log('Database connection OK');
            
            // Universal field name mapping - all use {section}_image_url format
            $fieldName = '';
            $tableName = '';
            $isHomepage = false;
            
            // Universal field name mapping - all use {section}_image_url format
            $fieldName = '';
            $tableName = '';
            $isHomepage = false;
            
            if (in_array($imageType, ['basement', 'ground', 'loft'])) {
                $fieldName = $imageType . '_image_url';
                $tableName = 'floorplan_settings';
            } elseif (in_array($imageType, ['hero', 'hero2', 'homepage-hero', 'homepage-hero2'])) {
                // Map hero types to database field names
                if ($imageType === 'hero' || $imageType === 'homepage-hero') {
                    $fieldName = 'hero_image_url';
                } else {
                    $fieldName = 'hero2_image_url';
                }
                $tableName = 'content_settings';
                $isHomepage = true;
            } elseif (in_array($imageType, ['wellness-massage', 'wellness-yoga'])) {
                // Wellness experiences images - store in wellness_settings table if it exists
                $wellnessType = str_replace('wellness-', '', $imageType);
                $fieldName = 'wellness_' . $wellnessType . '_image_url';
                
                $wellnessTableCheck = $conn->query("SHOW TABLES LIKE 'wellness_settings'");
                if ($wellnessTableCheck && $wellnessTableCheck->num_rows > 0) {
                    $tableName = 'wellness_settings';
                    $isHomepage = false;
                    error_log("upload_image: Using wellness_settings table for $fieldName");
                } else {
                    $tableName = 'content_settings';
                    $isHomepage = true;
                    error_log("upload_image: wellness_settings table not found, using content_settings for $fieldName");
                }
            } elseif (in_array($imageType, ['room-basement-card', 'room-ground-queen-card', 'room-ground-twin-card', 'room-second-card'])) {
                // Homepage room cards - store in room_cards_settings if it exists, otherwise content_settings
                $roomType = str_replace(['room-', '-card'], '', $imageType);
                $fieldName = 'room_' . str_replace('-', '_', $roomType) . '_card_image_url';
                
                // Check if room_cards_settings table exists
                $roomCardsTableCheck = $conn->query("SHOW TABLES LIKE 'room_cards_settings'");
                if ($roomCardsTableCheck && $roomCardsTableCheck->num_rows > 0) {
                    $tableName = 'room_cards_settings';
                    $isHomepage = false;
                    error_log("upload_image: Using room_cards_settings table for $fieldName");
                } else {
                    $tableName = 'content_settings';
                    $isHomepage = true;
                    error_log("upload_image: room_cards_settings table not found, using content_settings for $fieldName");
                }
            } elseif (in_array($imageType, ['basement-banner', 'ground-queen-banner', 'ground-twin-banner', 'second-banner'])) {
                // Room page banners - store in content_settings
                $bannerType = str_replace('-banner', '', $imageType);
                $fieldName = 'room_' . str_replace('-', '_', $bannerType) . '_banner_image_url';
                $tableName = 'content_settings';
                $isHomepage = true;
            } elseif (in_array($imageType, [
                'room-second-gallery',
                'room-second-common-gallery',
                'room-ground-twin-gallery',
                'room-ground-twin-common-gallery',
                'room-ground-queen-gallery',
                'room-ground-queen-common-gallery',
                'room-basement-gallery',
                'room-basement-common-gallery',
            ], true)) {
                // Room page gallery JSON — upload file only; URLs are saved via save_content.
                $fieldName = null;
                $tableName = null;
                $isHomepage = false;
            } elseif ($imageType === 'massage-hero') {
                // Massage hero image - store in massage_settings if it exists, otherwise content_settings
                $fieldName = 'massage_hero_image_url';
                $massageTableCheck = $conn->query("SHOW TABLES LIKE 'massage_settings'");
                if ($massageTableCheck && $massageTableCheck->num_rows > 0) {
                    $tableName = 'massage_settings';
                    $isHomepage = false;
                    error_log("upload_image: Processing massage-hero image, using massage_settings table");
                } else {
                    $tableName = 'content_settings';
                    $isHomepage = true;
                    error_log("upload_image: Processing massage-hero image, massage_settings not found, using content_settings");
                }
                error_log("upload_image: Processing massage-hero image, fieldName=$fieldName, tableName=$tableName");
            } elseif (in_array($imageType, ['massage-relaxing', 'massage-deep-tissue', 'massage-reiki', 'massage-sauna'])) {
                // Massage page images - store in massage_settings table if it exists
                $massageType = str_replace('massage-', '', $imageType);
                $fieldName = 'massage_' . str_replace('-', '_', $massageType) . '_image_url';
                
                $massageTableCheck = $conn->query("SHOW TABLES LIKE 'massage_settings'");
                if ($massageTableCheck && $massageTableCheck->num_rows > 0) {
                    $tableName = 'massage_settings';
                    $isHomepage = false;
                    error_log("upload_image: Using massage_settings table for $fieldName");
                } else {
                    $tableName = 'content_settings';
                    $isHomepage = true;
                    error_log("upload_image: massage_settings table not found, using content_settings for $fieldName");
                }
                error_log("Processing massage image: imageType=$imageType, massageType=$massageType, fieldName=$fieldName, tableName=$tableName");
            } elseif ($imageType === 'mini-hotel') {
                // Mini-hotel image - store in room_cards_settings if it exists, otherwise content_settings
                $fieldName = 'mini_hotel_image_url';
                
                // Check if room_cards_settings table exists
                $roomCardsTableCheck = $conn->query("SHOW TABLES LIKE 'room_cards_settings'");
                if ($roomCardsTableCheck && $roomCardsTableCheck->num_rows > 0) {
                    $tableName = 'room_cards_settings';
                    $isHomepage = false;
                    error_log("upload_image: Using room_cards_settings table for mini-hotel image");
                } else {
                    $tableName = 'content_settings';
                    $isHomepage = true;
                    error_log("upload_image: room_cards_settings table not found, using content_settings for mini-hotel image");
                }
                error_log("Processing mini-hotel image: imageType=$imageType, fieldName=$fieldName, tableName=$tableName");
            } elseif (in_array($imageType, ['retreat-hero', 'retreat-forest', 'retreat-indoor', 'retreat-theatre', 'retreat-collaboration'])) {
                // Retreat page images: collaboration card URL lives in retreat_settings when that table exists
                // (content_settings row is often at MySQL row-size limit and cannot add more columns).
                $retreatType = str_replace('retreat-', '', $imageType);
                $fieldName = 'retreat_' . str_replace('-', '_', $retreatType) . '_image_url';
                if ($imageType === 'retreat-collaboration') {
                    $rsTbl = $conn->query("SHOW TABLES LIKE 'retreat_settings'");
                    if ($rsTbl && $rsTbl->num_rows > 0) {
                        $tableName = 'retreat_settings';
                        $isHomepage = false;
                    } else {
                        $tableName = 'content_settings';
                        $isHomepage = true;
                    }
                } else {
                    $tableName = 'content_settings';
                    $isHomepage = true;
                }
            } elseif (in_array($imageType, ['special-hero', 'special-pools', 'special-dining', 'special-extra'])) {
                $specialType = str_replace('special-', '', $imageType);
                $fieldName = 'special_' . str_replace('-', '_', $specialType) . '_image_url';
                $ssTbl = $conn->query("SHOW TABLES LIKE 'special_settings'");
                if ($ssTbl && $ssTbl->num_rows > 0) {
                    $tableName = 'special_settings';
                    $isHomepage = false;
                } else {
                    $tableName = 'content_settings';
                    $isHomepage = true;
                }
            } elseif ($imageType === 'explore-hero') {
                $fieldName = 'explore_hero_image_url';
                $esTbl = $conn->query("SHOW TABLES LIKE 'explore_settings'");
                if ($esTbl && $esTbl->num_rows > 0) {
                    $tableName = 'explore_settings';
                    $isHomepage = false;
                    error_log("upload_image: explore-hero using explore_settings (content_settings row full)");
                } else {
                    $tableName = 'content_settings';
                    $isHomepage = true;
                }
            } elseif ($imageType === 'explore-accommodation') {
                $fieldName = 'explore_accommodation_image_url';
                $esTbl2 = $conn->query("SHOW TABLES LIKE 'explore_settings'");
                if ($esTbl2 && $esTbl2->num_rows > 0) {
                    $tableName = 'explore_settings';
                    $isHomepage = false;
                    error_log('upload_image: explore-accommodation using explore_settings');
                } else {
                    sendError('Database error: explore_settings table missing. Run create_explore_settings_table.php and add_explore_accommodation_columns.php.');
                    exit;
                }
            } elseif ($imageType === 'about-parks-hero') {
                $fieldName = 'hero_image_url';
                $epTbl = $conn->query("SHOW TABLES LIKE 'explore_parks_settings'");
                if ($epTbl && $epTbl->num_rows > 0) {
                    $tableName = 'explore_parks_settings';
                    $isHomepage = false;
                    error_log('upload_image: about-parks-hero → explore_parks_settings.hero_image_url');
                } else {
                    sendError('Database error: explore_parks_settings table missing. Run create_explore_parks_settings_table.php on the server.');
                    exit;
                }
            } elseif (in_array($imageType, ['about-nelson', 'about-kaslo', 'about-crawford', 'about-museum'])) {
                $aboutType = str_replace('about-', '', $imageType);
                $fieldName = 'about_' . str_replace('-', '_', $aboutType) . '_image_url';
                $ecTbl = $conn->query("SHOW TABLES LIKE 'explore_community_extra'");
                if ($ecTbl && $ecTbl->num_rows > 0) {
                    $tableName = 'explore_community_extra';
                    $isHomepage = false;
                    error_log("upload_image: $imageType → explore_community_extra.$fieldName");
                } else {
                    $tableName = 'content_settings';
                    $isHomepage = true;
                }
            } elseif (in_array($imageType, ['about-hero', 'about-founder', 'about-procter'])) {
                // About us page images - store in content_settings
                $aboutType = str_replace('about-', '', $imageType);
                $fieldName = 'about_' . str_replace('-', '_', $aboutType) . '_image_url';
                $tableName = 'content_settings';
                $isHomepage = true;
            } elseif (in_array($imageType, ['basement', 'ground', 'loft'])) {
                // Floor plan images - store in floorplan_settings table
                $fieldName = $imageType . '_image_url';
                $tableName = 'floorplan_settings';
                $isHomepage = false;
            } elseif (preg_match('/^about-park-card-(\d+)-(hero|gallery)$/', (string) $imageType, $parkCardUploadMatch) && (int) $parkCardUploadMatch[1] >= 1 && (int) $parkCardUploadMatch[1] <= 30) {
                $tableName = null;
                $isHomepage = false;
                sendSuccess([
                    'message' => 'Image uploaded successfully',
                    'filepath' => $filepath,
                    'imageUrl' => $filepath
                ]);
                exit;
            } elseif (preg_match('/^explore-(communities|culture|activities)-card-(\d+)-(hero|gallery)$/', (string) $imageType, $exCardMatch) && (int) $exCardMatch[2] >= 1 && (int) $exCardMatch[2] <= 30) {
                $tableName = null;
                $isHomepage = false;
                sendSuccess([
                    'message' => 'Image uploaded successfully',
                    'filepath' => $filepath,
                    'imageUrl' => $filepath
                ]);
                exit;
            } elseif (in_array($imageType, ['about-procter-gallery', 'about-halcyon-gallery', 'about-whitewater-gallery', 'about-nelson-gallery', 'about-kaslo-gallery', 'about-crawford-gallery', 'about-museum-gallery'])) {
                // About us page gallery images - just upload, don't update database (handled by api.php)
                $tableName = null;
                $isHomepage = false;
                // Return immediately - gallery images are managed via JSON arrays in api.php
                sendSuccess([
                    'message' => 'Gallery image uploaded successfully',
                    'filepath' => $filepath,
                    'imageUrl' => $filepath
                ]);
                exit;
            } elseif (in_array($imageType, ['floorplan-basement-gallery', 'floorplan-ground-gallery', 'floorplan-loft-gallery'])) {
                // Floor plan gallery images - just upload, don't update database (handled by api.php)
                $tableName = null;
                $isHomepage = false;
                // Return immediately - gallery images are managed via JSON arrays in api.php
                sendSuccess([
                    'message' => 'Gallery image uploaded successfully',
                    'filepath' => $filepath,
                    'imageUrl' => $filepath
                ]);
                exit;
            } elseif (in_array($imageType, ['retreat-forest-gallery', 'retreat-indoor-gallery', 'retreat-theatre-gallery'])) {
                // Retreat and Workshop page gallery images - just upload, don't update database (handled by api.php)
                $tableName = null;
                $isHomepage = false;
                // Return immediately - gallery images are managed via JSON arrays in api.php
                sendSuccess([
                    'message' => 'Gallery image uploaded successfully',
                    'filepath' => $filepath,
                    'imageUrl' => $filepath
                ]);
                exit;
            } else {
                sendError('Invalid image type: ' . $imageType);
                exit;
            }
            
            error_log("Field name determined: $fieldName, table: $tableName");
            
            // Update database (skip for gallery images - they're stored as JSON array)
            if (in_array($imageType, [
                'room-second-gallery',
                'room-second-common-gallery',
                'room-ground-twin-gallery',
                'room-ground-twin-common-gallery',
                'room-ground-queen-gallery',
                'room-ground-queen-common-gallery',
                'room-basement-gallery',
                'room-basement-common-gallery',
            ], true)) {
                // Gallery images are stored as JSON arrays; save_content persists them.
                sendSuccess([
                    'message' => 'Image uploaded successfully',
                    'filepath' => $filepath,
                    'imageUrl' => $filepath,
                    'image_type' => $imageType
                ]);
            } else {
                // Check if table exists
                if (!$tableName) {
                    sendError('Invalid image type: table name is missing');
                    exit;
                }
                
                $tableCheck = $conn->query("SHOW TABLES LIKE '$tableName'");
                if ($tableCheck->num_rows === 0) {
                    error_log("Table $tableName does not exist");
                    sendError('Database table does not exist');
                    exit;
                }
                
                error_log("Table $tableName exists");
                
                // Check if record exists
                $recordCheck = $conn->query("SELECT id FROM $tableName WHERE id = 1");
                if ($recordCheck->num_rows === 0) {
                    error_log('No record with id=1, creating one');
                    $insertStmt = $conn->prepare("INSERT INTO $tableName (id) VALUES (1)");
                    if (!$insertStmt->execute()) {
                        error_log('Failed to create record: ' . $conn->error);
                        sendError('Failed to create database record');
                        exit;
                    }
                    $insertStmt->close();
                }
                
                error_log('Record with id=1 exists');
                // Check if column exists (for dynamic columns like wellness images)
                $columnExists = false;
                
                // For room_cards_settings and massage_settings, columns should already exist, so skip column creation
                if ($tableName === 'room_cards_settings' || $tableName === 'massage_settings') {
                    $columnCheck = $conn->query("SHOW COLUMNS FROM $tableName LIKE '$fieldName'");
                    $columnExists = $columnCheck->num_rows > 0;
                    if (!$columnExists) {
                        error_log("Column $fieldName does not exist in $tableName");
                        $scriptName = $tableName === 'room_cards_settings' ? 'create_room_cards_table.php' : 'create_massage_settings_table.php';
                        sendError('Database error: Column ' . $fieldName . ' does not exist in ' . $tableName . ' table. Please run ' . $scriptName);
                        exit;
                    }
                    error_log("Column $fieldName exists in $tableName");
                } elseif ($tableName === 'special_settings') {
                    $columnCheck = $conn->query("SHOW COLUMNS FROM $tableName LIKE '$fieldName'");
                    $columnExists = $columnCheck && $columnCheck->num_rows > 0;
                    if (!$columnExists) {
                        error_log("Column $fieldName does not exist in special_settings");
                        sendError('Database error: Column ' . $fieldName . ' does not exist in special_settings. Run add_special_extra_columns.php or create_page_tables.php / recreate columns.');
                        exit;
                    }
                    error_log("Column $fieldName exists in special_settings");
                } elseif ($tableName === 'explore_settings') {
                    $columnCheck = $conn->query("SHOW COLUMNS FROM $tableName LIKE '$fieldName'");
                    $columnExists = $columnCheck && $columnCheck->num_rows > 0;
                    if (!$columnExists) {
                        error_log("Column $fieldName does not exist in explore_settings");
                        sendError('Database error: run create_explore_settings_table.php (or add_explore_columns.php) to create the explore_settings table.');
                        exit;
                    }
                    error_log("Column $fieldName exists in explore_settings");
                } elseif ($tableName === 'explore_parks_settings') {
                    $columnCheck = $conn->query("SHOW COLUMNS FROM $tableName LIKE '$fieldName'");
                    $columnExists = $columnCheck && $columnCheck->num_rows > 0;
                    if (!$columnExists) {
                        error_log("Column $fieldName does not exist in explore_parks_settings");
                        sendError('Database error: run create_explore_parks_settings_table.php on the server.');
                        exit;
                    }
                    error_log("Column $fieldName exists in explore_parks_settings");
                } elseif ($tableName === 'explore_community_extra') {
                    $columnCheck = $conn->query("SHOW COLUMNS FROM $tableName LIKE '$fieldName'");
                    $columnExists = $columnCheck && $columnCheck->num_rows > 0;
                    if (!$columnExists) {
                        error_log("Column $fieldName does not exist in explore_community_extra");
                        sendError('Database error: run create_explore_community_extra_table.php on the server.');
                        exit;
                    }
                    error_log("Column $fieldName exists in explore_community_extra");
                } elseif ($tableName === 'retreat_settings') {
                    $columnCheck = $conn->query("SHOW COLUMNS FROM $tableName LIKE '$fieldName'");
                    $columnExists = $columnCheck && $columnCheck->num_rows > 0;
                    if (!$columnExists) {
                        error_log("Column $fieldName missing in retreat_settings, attempting ALTER");
                        try {
                            $alterStmt = $conn->prepare("ALTER TABLE $tableName ADD COLUMN $fieldName VARCHAR(255) DEFAULT NULL");
                            if ($alterStmt && $alterStmt->execute()) {
                                error_log("Column $fieldName added to retreat_settings");
                                $columnExists = true;
                                $alterStmt->close();
                            } else {
                                $err = $alterStmt ? $alterStmt->error : $conn->error;
                                if ($alterStmt) {
                                    $alterStmt->close();
                                }
                                error_log("ALTER retreat_settings failed: $err");
                                sendError('Database error: Column ' . $fieldName . ' is missing in retreat_settings. Run add_retreat_collaboration_image_column.php or create_page_tables.php.');
                                exit;
                            }
                        } catch (Exception $e) {
                            error_log('ALTER retreat_settings exception: ' . $e->getMessage());
                            sendError('Database error: Could not add column ' . $fieldName . ' to retreat_settings.');
                            exit;
                        }
                    }
                    if ($columnExists) {
                        error_log("Column $fieldName exists in $tableName");
                    }
                } elseif ($fieldName && $tableName && $isHomepage && in_array($imageType, ['hero', 'hero2', 'homepage-hero', 'homepage-hero2', 'wellness-massage', 'wellness-yoga', 'basement-banner', 'ground-queen-banner', 'ground-twin-banner', 'second-banner', 'mini-hotel', 'retreat-hero', 'retreat-forest', 'retreat-indoor', 'retreat-theatre', 'retreat-collaboration', 'special-hero', 'special-pools', 'special-dining', 'special-extra', 'about-hero', 'about-founder', 'about-procter', 'explore-hero', 'massage-hero'])) {
                    // For content_settings, check if column exists
                    // DO NOT create column automatically to avoid "Row size too large" errors
                    $columnCheck = $conn->query("SHOW COLUMNS FROM $tableName LIKE '$fieldName'");
                    $columnExists = $columnCheck->num_rows > 0;
                    
                    if (!$columnExists) {
                        error_log("Column $fieldName does not exist in $tableName");
                        // Special handling for massage-hero: try to use massage_settings table
                        if ($imageType === 'massage-hero') {
                            $massageTableCheck = $conn->query("SHOW TABLES LIKE 'massage_settings'");
                            if ($massageTableCheck && $massageTableCheck->num_rows > 0) {
                                // Try to use massage_settings table instead
                                $tableName = 'massage_settings';
                                $isHomepage = false;
                                $columnCheck = $conn->query("SHOW COLUMNS FROM $tableName LIKE '$fieldName'");
                                $columnExists = $columnCheck->num_rows > 0;
                                
                                if (!$columnExists) {
                                    // Try to create column in massage_settings
                                    error_log("Attempting to create column $fieldName in massage_settings");
                                    try {
                                        $alterStmt = $conn->prepare("ALTER TABLE $tableName ADD COLUMN $fieldName VARCHAR(255) DEFAULT NULL");
                                        if ($alterStmt && $alterStmt->execute()) {
                                            error_log("Column $fieldName created successfully in massage_settings");
                                            $columnExists = true;
                                        } else {
                                            $error = $alterStmt ? $alterStmt->error : $conn->error;
                                            error_log("Failed to create column $fieldName in massage_settings: $error");
                                            sendError('Database error: Column ' . $fieldName . ' does not exist. Please contact administrator to add this column to the database.');
                                            exit;
                                        }
                                    } catch (Exception $e) {
                                        error_log("Exception creating column in massage_settings: " . $e->getMessage());
                                        sendError('Database error: Column ' . $fieldName . ' does not exist. Please contact administrator to add this column to the database.');
                                        exit;
                                    }
                                }
                            } else {
                                sendError('Database error: Column massage_hero_image_url does not exist in content_settings table. The table is full (148 columns). Please run add_massage_hero_column.php script to add this column.');
                                exit;
                            }
                        } else {
                            // For other image types, try to create column (but this might fail for content_settings)
                            error_log("Column $fieldName does not exist, attempting to create it");
                            try {
                                $alterStmt = $conn->prepare("ALTER TABLE $tableName ADD COLUMN $fieldName VARCHAR(255) DEFAULT NULL");
                                if ($alterStmt && $alterStmt->execute()) {
                                    error_log("Column $fieldName created successfully");
                                    $columnExists = true;
                                } else {
                                    $error = $alterStmt ? $alterStmt->error : $conn->error;
                                    error_log("Failed to create column $fieldName: $error");
                                    $rowTooBig = (strpos($error, 'Row size too large') !== false || stripos($error, 'table is full') !== false);
                                    if ($rowTooBig) {
                                        if ($imageType === 'retreat-collaboration') {
                                            sendError('Database error: content_settings is full. Create table retreat_settings (run create_page_tables.php on the server), deploy the latest upload_image.php, then upload again.');
                                        } else {
                                            sendError('Database error: Cannot add column ' . $fieldName . ' - table is full. Please contact administrator.');
                                        }
                                    } else {
                                        sendError('Database error: Failed to create column ' . $fieldName . ': ' . $error);
                                    }
                                    exit;
                                }
                            } catch (Exception $e) {
                                error_log("Exception creating column: " . $e->getMessage());
                                $rowTooBig = (strpos($e->getMessage(), 'Row size too large') !== false || stripos($e->getMessage(), 'table is full') !== false);
                                if ($rowTooBig) {
                                    if ($imageType === 'retreat-collaboration') {
                                        sendError('Database error: content_settings is full. Create table retreat_settings (run create_page_tables.php on the server), deploy the latest upload_image.php, then upload again.');
                                    } else {
                                        sendError('Database error: Cannot add column ' . $fieldName . ' - table is full. Please contact administrator.');
                                    }
                                } else {
                                    sendError('Database error: ' . $e->getMessage());
                                }
                                exit;
                            }
                        }
                    }
                    if ($columnExists) {
                        error_log("Column $fieldName exists in $tableName");
                    }
                } else {
                    // For existing columns (like floorplan_settings), assume they exist
                    $columnExists = true;
                }
                
                // Update database
                // Ground floor: original schema has ground_queen_image only; ground_image_url may be missing
                $verifyFieldName = $fieldName;
                if ($imageType === 'ground') {
                    $chkGroundUrl = $conn->query("SHOW COLUMNS FROM floorplan_settings LIKE 'ground_image_url'");
                    $hasGroundImageUrl = ($chkGroundUrl && $chkGroundUrl->num_rows > 0);
                    if ($hasGroundImageUrl) {
                        $stmt = $conn->prepare("UPDATE floorplan_settings SET ground_image_url = ?, ground_queen_image = ? WHERE id = 1");
                        $stmt->bind_param("ss", $filepath, $filepath);
                        $verifyFieldName = 'ground_image_url';
                    } else {
                        $stmt = $conn->prepare("UPDATE floorplan_settings SET ground_queen_image = ? WHERE id = 1");
                        $stmt->bind_param("s", $filepath);
                        $verifyFieldName = 'ground_queen_image';
                    }
                } else {
                    if (!$fieldName || !$tableName) {
                        sendError('Invalid image type: field name or table name is missing');
                        exit;
                    }
                    $stmt = $conn->prepare("UPDATE $tableName SET $fieldName = ? WHERE id = 1");
                    $stmt->bind_param("s", $filepath);
                }
                
                error_log("Updating database: table=$tableName, field=$verifyFieldName, path=$filepath");
                
                if ($stmt->execute()) {
                    error_log('Database updated successfully');
                    
                    // Verify the update (use actual column name — critical for ground without ground_image_url)
                    $verifyStmt = $conn->prepare("SELECT `$verifyFieldName` FROM $tableName WHERE id = 1");
                    $verifyStmt->execute();
                    $result = $verifyStmt->get_result();
                    $row = $result->fetch_assoc();
                    error_log("Verification: field value = " . ($row[$verifyFieldName] ?? 'NULL'));
                    
                    sendSuccess([
                        'message' => 'Image uploaded successfully',
                        'filepath' => $filepath,
                        'imageUrl' => $filepath,
                        'image_type' => $imageType,
                        'field_updated' => $verifyFieldName,
                        'verified_value' => $row[$verifyFieldName] ?? 'NULL'
                    ]);
                } else {
                    error_log('Database update failed: ' . $conn->error);
                    sendError('Database update failed: ' . $conn->error);
                }
                
                $stmt->close();
            }
            
        } catch (Exception $e) {
            sendError('Database error: ' . $e->getMessage());
        }
    } else {
        sendError('Failed to move uploaded file');
    }
} else {
    sendError('Invalid request');
}

$conn->close();
?>
