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
    $filename = $imageType . '_' . time() . '.' . $extension;
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
            } elseif (in_array($imageType, ['hero', 'hero2'])) {
                // Map hero types to database field names
                if ($imageType === 'hero') {
                    $fieldName = 'hero_image_url';
                } else {
                    $fieldName = 'hero2_image_url';
                }
                $tableName = 'content_settings';
                $isHomepage = true;
            } elseif (in_array($imageType, ['wellness-massage', 'wellness-yoga', 'wellness-sauna'])) {
                // Wellness experiences images - store in content_settings or new wellness_settings table
                // For now, use content_settings with prefixed field names
                $wellnessType = str_replace('wellness-', '', $imageType);
                $fieldName = 'wellness_' . $wellnessType . '_image_url';
                $tableName = 'content_settings';
                $isHomepage = true;
            } elseif (in_array($imageType, ['room-basement-card', 'room-ground-queen-card', 'room-ground-twin-card', 'room-second-card'])) {
                // Homepage room cards - store in content_settings
                $roomType = str_replace(['room-', '-card'], '', $imageType);
                $fieldName = 'room_' . str_replace('-', '_', $roomType) . '_card_image_url';
                $tableName = 'content_settings';
                $isHomepage = true;
            } elseif (in_array($imageType, ['basement-banner', 'ground-queen-banner', 'ground-twin-banner', 'second-banner'])) {
                // Room page banners - store in content_settings
                $bannerType = str_replace('-banner', '', $imageType);
                $fieldName = 'room_' . str_replace('-', '_', $bannerType) . '_banner_image_url';
                $tableName = 'content_settings';
                $isHomepage = true;
            } elseif (in_array($imageType, ['massage-relaxing', 'massage-deep-tissue', 'massage-reiki', 'massage-sauna', 'massage-room-booking'])) {
                // Massage page images - store in content_settings
                $massageType = str_replace('massage-', '', $imageType);
                $fieldName = 'massage_' . str_replace('-', '_', $massageType) . '_image_url';
                $tableName = 'content_settings';
                $isHomepage = true;
            } elseif (in_array($imageType, ['retreat-hero', 'retreat-forest', 'retreat-indoor', 'retreat-theatre'])) {
                // Retreat and Workshop page images - store in content_settings
                $retreatType = str_replace('retreat-', '', $imageType);
                $fieldName = 'retreat_' . str_replace('-', '_', $retreatType) . '_image_url';
                $tableName = 'content_settings';
                $isHomepage = true;
            } elseif (in_array($imageType, ['special-hero', 'special-pools', 'special-dining'])) {
                // Special page images - store in content_settings
                $specialType = str_replace('special-', '', $imageType);
                $fieldName = 'special_' . str_replace('-', '_', $specialType) . '_image_url';
                $tableName = 'content_settings';
                $isHomepage = true;
            } elseif (in_array($imageType, ['about-hero', 'about-founder', 'about-procter'])) {
                // About us page images - store in content_settings
                $aboutType = str_replace('about-', '', $imageType);
                $fieldName = 'about_' . str_replace('-', '_', $aboutType) . '_image_url';
                $tableName = 'content_settings';
                $isHomepage = true;
            } else {
                sendError('Invalid image type: ' . $imageType);
                exit;
            }
            
            error_log("Field name determined: $fieldName, table: $tableName");
            
            // Check if table exists
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
            if ($isHomepage && in_array($imageType, ['wellness-massage', 'wellness-yoga', 'wellness-sauna', 'room-basement-card', 'room-ground-queen-card', 'room-ground-twin-card', 'room-second-card', 'basement-banner', 'ground-queen-banner', 'ground-twin-banner', 'second-banner', 'massage-relaxing', 'massage-deep-tissue', 'massage-reiki', 'massage-sauna', 'massage-room-booking', 'retreat-hero', 'retreat-forest', 'retreat-indoor', 'retreat-theatre', 'special-hero', 'special-pools', 'special-dining', 'about-hero', 'about-founder', 'about-procter'])) {
                $columnCheck = $conn->query("SHOW COLUMNS FROM $tableName LIKE '$fieldName'");
                $columnExists = $columnCheck->num_rows > 0;
                
                if (!$columnExists) {
                    error_log("Column $fieldName does not exist, creating it");
                    $alterStmt = $conn->prepare("ALTER TABLE $tableName ADD COLUMN $fieldName VARCHAR(255) DEFAULT NULL");
                    if (!$alterStmt->execute()) {
                        error_log('Failed to create column: ' . $conn->error);
                        sendError('Database error: Failed to create column ' . $fieldName . ': ' . $conn->error);
                        exit;
                    }
                    $alterStmt->close();
                    error_log("Column $fieldName created successfully");
                }
            } else {
                // For existing columns, assume they exist
                $columnExists = true;
            }
            
            // Update database
            // Universal: for ground, also update ground_queen_image for compatibility
            if ($imageType === 'ground') {
                $stmt = $conn->prepare("UPDATE floorplan_settings SET ground_image_url = ?, ground_queen_image = ? WHERE id = 1");
                $stmt->bind_param("ss", $filepath, $filepath);
            } else {
                $stmt = $conn->prepare("UPDATE $tableName SET $fieldName = ? WHERE id = 1");
                $stmt->bind_param("s", $filepath);
            }
            
            error_log("Updating database: table=$tableName, field=$fieldName, path=$filepath");
            
            if ($stmt->execute()) {
                error_log('Database updated successfully');
                
                // Verify the update
                $verifyStmt = $conn->prepare("SELECT $fieldName FROM $tableName WHERE id = 1");
                $verifyStmt->execute();
                $result = $verifyStmt->get_result();
                $row = $result->fetch_assoc();
                error_log("Verification: field value = " . ($row[$fieldName] ?? 'NULL'));
                
                sendSuccess([
                    'message' => 'Image uploaded successfully',
                    'filepath' => $filepath,
                    'image_type' => $imageType,
                    'field_updated' => $fieldName,
                    'verified_value' => $row[$fieldName] ?? 'NULL'
                ]);
            } else {
                error_log('Database update failed: ' . $conn->error);
                sendError('Database update failed: ' . $conn->error);
            }
            
            $stmt->close();
            
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
