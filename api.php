<?php
// Отключаем вывод ошибок для API (чтобы не ломать JSON ответы)
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Include common utilities
require_once 'common.php';

// Check database connection
if (!$conn) {
    sendError('Database connection failed');
}

$action = getApiAction();

// Admin login handler - must be BEFORE auth_api.php to avoid "Invalid action" error
if ($action === 'admin_login') {
    header('Content-Type: application/json');
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Admin credentials (should match admin.js)
    $ADMIN_USERNAME = 'admin';
    $ADMIN_PASSWORD = 'backtobase2024';
    
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'error' => 'Username and password are required']);
        exit;
    }
    
    if ($username === $ADMIN_USERNAME && $password === $ADMIN_PASSWORD) {
        // Create JWT token for admin
        require_once 'jwt_helper.php';
        $token = createJWT([
            'user_id' => 0, // Admin user ID
            'email' => $ADMIN_USERNAME,
            'is_admin' => true
        ]);
        
        echo json_encode([
            'success' => true,
            'user' => [
                'id' => 0,
                'email' => $ADMIN_USERNAME,
                'name' => 'Administrator',
                'is_admin' => true
            ],
            'token' => $token
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid username or password']);
    }
    exit;
}

// Content handlers - must be BEFORE auth_api.php to avoid "Invalid action" error
if ($action === 'get_content') {
    header('Content-Type: application/json');
    try {
        $result = $conn->query("SELECT * FROM content_settings WHERE id = 1");
        
        if (!$result) {
            echo json_encode(['success' => false, 'error' => 'Query failed: ' . $conn->error]);
            exit;
        }
        
        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            echo json_encode([
                'success' => true,
                'data' => [
                    'homepageDescription' => $data['homepage_description'],
                    'homepageSubtitle' => $data['homepage_subtitle'],
                    'contactPhone' => $data['contact_phone'],
                    'contactEmail' => $data['contact_email'],
                    'contactAddress' => $data['contact_address'],
                    'heroImageUrl' => $data['hero_image_url'] ?? '',
                    'hero2ImageUrl' => $data['hero2_image_url'] ?? '',
                    // Wellness Experiences images
                    'wellnessMassageImageUrl' => $data['wellness_massage_image_url'] ?? '',
                    'wellnessYogaImageUrl' => $data['wellness_yoga_image_url'] ?? '',
                    'wellnessSaunaImageUrl' => $data['wellness_sauna_image_url'] ?? '',
                    // Room cards images
                    'roomBasementCardImageUrl' => $data['room_basement_card_image_url'] ?? '',
                    'roomGroundQueenCardImageUrl' => $data['room_ground_queen_card_image_url'] ?? '',
                    'roomGroundTwinCardImageUrl' => $data['room_ground_twin_card_image_url'] ?? '',
                    'roomSecondCardImageUrl' => $data['room_second_card_image_url'] ?? '',
                    // Room banners
                    'roomBasementBannerImageUrl' => $data['room_basement_banner_image_url'] ?? '',
                    'roomGroundQueenBannerImageUrl' => $data['room_ground_queen_banner_image_url'] ?? '',
                    'roomGroundTwinBannerImageUrl' => $data['room_ground_twin_banner_image_url'] ?? '',
                    'roomSecondBannerImageUrl' => $data['room_second_banner_image_url'] ?? '',
                    // Massage page images
                    'massageRelaxingImageUrl' => $data['massage_relaxing_image_url'] ?? '',
                    'massageDeepTissueImageUrl' => $data['massage_deep_tissue_image_url'] ?? '',
                    'massageReikiImageUrl' => $data['massage_reiki_image_url'] ?? '',
                    'massageSaunaImageUrl' => $data['massage_sauna_image_url'] ?? '',
                    'massageRoomBookingImageUrl' => $data['massage_room_booking_image_url'] ?? '',
                    // Retreat and Workshop page images
                    'retreatHeroImageUrl' => $data['retreat_hero_image_url'] ?? '',
                    'retreatForestImageUrl' => $data['retreat_forest_image_url'] ?? '',
                    'retreatIndoorImageUrl' => $data['retreat_indoor_image_url'] ?? '',
                    'retreatTheatreImageUrl' => $data['retreat_theatre_image_url'] ?? '',
                    // Special page images
                    'specialHeroImageUrl' => $data['special_hero_image_url'] ?? '',
                    'specialPoolsImageUrl' => $data['special_pools_image_url'] ?? '',
                    'specialDiningImageUrl' => $data['special_dining_image_url'] ?? '',
                    // About us page images
                    'aboutHeroImageUrl' => $data['about_hero_image_url'] ?? '',
                    'aboutFounderImageUrl' => $data['about_founder_image_url'] ?? '',
                    'aboutProcterImageUrl' => $data['about_procter_image_url'] ?? '',
                    // Retreat and Workshop page content
                    'retreatHeroTitle' => $data['retreat_hero_title'] ?? '',
                    'retreatHeroSubtitle' => $data['retreat_hero_subtitle'] ?? '',
                    'retreatIntroText' => $data['retreat_intro_text'] ?? '',
                    'retreatLocationsTitle' => $data['retreat_locations_title'] ?? '',
                    'retreatForestTitle' => $data['retreat_forest_title'] ?? '',
                    'retreatForestDescription' => $data['retreat_forest_description'] ?? '',
                    'retreatForestListLabel' => $data['retreat_forest_list_label'] ?? '',
                    'retreatForestListItems' => $data['retreat_forest_list_items'] ?? '',
                    'retreatIndoorTitle' => $data['retreat_indoor_title'] ?? '',
                    'retreatIndoorDescription' => $data['retreat_indoor_description'] ?? '',
                    'retreatIndoorAdditional' => $data['retreat_indoor_additional'] ?? '',
                    'retreatTheatreTitle' => $data['retreat_theatre_title'] ?? '',
                    'retreatTheatreDescription' => $data['retreat_theatre_description'] ?? '',
                    'retreatContactTitle' => $data['retreat_contact_title'] ?? '',
                    'retreatContactText' => $data['retreat_contact_text'] ?? '',
                    'retreatOrganizerTitle' => $data['retreat_organizer_title'] ?? '',
                    'retreatWorkshopsTitle' => $data['retreat_workshops_title'] ?? '',
                    'retreatWorkshopsIntro' => $data['retreat_workshops_intro'] ?? '',
                    'retreatWorkshopsList' => $data['retreat_workshops_list'] ?? '',
                    'retreatWorkshopsConclusion' => $data['retreat_workshops_conclusion'] ?? '',
                    'retreatCollaborationTitle' => $data['retreat_collaboration_title'] ?? '',
                    'retreatCollaborationIntro' => $data['retreat_collaboration_intro'] ?? '',
                    'retreatCollaborationList' => $data['retreat_collaboration_list'] ?? '',
                    'retreatCollaborationConclusion' => $data['retreat_collaboration_conclusion'] ?? '',
                    // Special page content
                    'specialHeroTitle' => $data['special_hero_title'] ?? '',
                    'specialHeroSubtitle' => $data['special_hero_subtitle'] ?? '',
                    'specialPoolsTitle' => $data['special_pools_title'] ?? '',
                    'specialPoolsDescription1' => $data['special_pools_description_1'] ?? '',
                    'specialPoolsDescription2' => $data['special_pools_description_2'] ?? '',
                    'specialDiningTitle' => $data['special_dining_title'] ?? '',
                    'specialDiningDescription1' => $data['special_dining_description_1'] ?? '',
                    'specialDiningDescription2' => $data['special_dining_description_2'] ?? '',
                    'specialOfferTitle' => $data['special_offer_title'] ?? '',
                    'specialOfferMainText' => $data['special_offer_main_text'] ?? '',
                    'specialOfferDescription' => $data['special_offer_description'] ?? '',
                    // About us page content
                    'aboutHeroTitle' => $data['about_hero_title'] ?? '',
                    'aboutHeroSubtitle' => $data['about_hero_subtitle'] ?? '',
                    'aboutIdeaTitle' => $data['about_idea_title'] ?? '',
                    'aboutIdeaIntro' => $data['about_idea_intro'] ?? '',
                    'aboutIdeaParagraph1' => $data['about_idea_paragraph_1'] ?? '',
                    'aboutIdeaParagraph2' => $data['about_idea_paragraph_2'] ?? '',
                    'aboutIdeaParagraph3' => $data['about_idea_paragraph_3'] ?? '',
                    'aboutIdeaSignature' => $data['about_idea_signature'] ?? '',
                    'aboutLocationTitle' => $data['about_location_title'] ?? '',
                    'aboutLocationParagraph1' => $data['about_location_paragraph_1'] ?? '',
                    'aboutLocationParagraph2' => $data['about_location_paragraph_2'] ?? '',
                    'aboutLocationParagraph3' => $data['about_location_paragraph_3'] ?? '',
                    'aboutLocationParagraph4' => $data['about_location_paragraph_4'] ?? '',
                    'aboutLocationCoordinates' => $data['about_location_coordinates'] ?? '',
                    'aboutLocationDeerWarning' => $data['about_location_deer_warning'] ?? '',
                    'aboutAttractionsTitle' => $data['about_attractions_title'] ?? '',
                    'aboutAttractionsLead' => $data['about_attractions_lead'] ?? '',
                    'aboutProcterTitle' => $data['about_procter_title'] ?? '',
                    'aboutProcterDistance' => $data['about_procter_distance'] ?? '',
                    'aboutProcterDescription' => $data['about_procter_description'] ?? '',
                    'aboutHalcyonTitle' => $data['about_halcyon_title'] ?? '',
                    'aboutHalcyonDistance' => $data['about_halcyon_distance'] ?? '',
                    'aboutHalcyonDescription' => $data['about_halcyon_description'] ?? '',
                    'aboutWhitewaterTitle' => $data['about_whitewater_title'] ?? '',
                    'aboutWhitewaterDistance' => $data['about_whitewater_distance'] ?? '',
                    'aboutWhitewaterDescription' => $data['about_whitewater_description'] ?? '',
                    'aboutNelsonTitle' => $data['about_nelson_title'] ?? '',
                    'aboutNelsonDistance' => $data['about_nelson_distance'] ?? '',
                    'aboutNelsonDescription' => $data['about_nelson_description'] ?? '',
                    'aboutParksTitle' => $data['about_parks_title'] ?? '',
                    'aboutParksIntro' => $data['about_parks_intro'] ?? '',
                    'aboutParksList' => $data['about_parks_list'] ?? '',
                    // Massage page content
                    'massageHeroTitle' => $data['massage_hero_title'] ?? '',
                    'massageIntro' => $data['massage_intro'] ?? '',
                    'massageRelaxingTitle' => $data['massage_relaxing_title'] ?? '',
                    'massageRelaxingDescription' => $data['massage_relaxing_description'] ?? '',
                    'massageDeepTissueTitle' => $data['massage_deep_tissue_title'] ?? '',
                    'massageDeepTissueDescription' => $data['massage_deep_tissue_description'] ?? '',
                    'massageReikiTitle' => $data['massage_reiki_title'] ?? '',
                    'massageReikiDescription' => $data['massage_reiki_description'] ?? '',
                    'massageSaunaTitle' => $data['massage_sauna_title'] ?? '',
                    'massageSaunaDescription' => $data['massage_sauna_description'] ?? '',
                    'massageBookingTitle' => $data['massage_booking_title'] ?? '',
                    // Room Second floor page content
                    'roomSecondTitle' => $data['room_second_title'] ?? '',
                    'roomSecondSubtitle' => $data['room_second_subtitle'] ?? '',
                    'roomSecondDescription' => $data['room_second_description'] ?? '',
                    'roomSecondPrice' => $data['room_second_price'] ?? '',
                    'roomSecondCapacity' => $data['room_second_capacity'] ?? '',
                    'roomSecondNote' => $data['room_second_note'] ?? '',
                    'roomSecondGallery' => $data['room_second_gallery'] ?? '[]',
                    'roomSecondBannerImageUrl' => $data['room_second_banner_image_url'] ?? '',
                    // Room Ground Twin beds page content
                    'roomGroundTwinTitle' => $data['room_ground_twin_title'] ?? '',
                    'roomGroundTwinSubtitle' => $data['room_ground_twin_subtitle'] ?? '',
                    'roomGroundTwinDescription' => $data['room_ground_twin_description'] ?? '',
                    'roomGroundTwinPrice' => $data['room_ground_twin_price'] ?? '',
                    'roomGroundTwinCapacity' => $data['room_ground_twin_capacity'] ?? '',
                    'roomGroundTwinNote' => $data['room_ground_twin_note'] ?? '',
                    'roomGroundTwinGallery' => $data['room_ground_twin_gallery'] ?? '[]',
                    'roomGroundTwinBannerImageUrl' => $data['room_ground_twin_banner_image_url'] ?? '',
                    // Room Ground Queen bed page content
                    'roomGroundQueenTitle' => $data['room_ground_queen_title'] ?? '',
                    'roomGroundQueenSubtitle' => $data['room_ground_queen_subtitle'] ?? '',
                    'roomGroundQueenDescription' => $data['room_ground_queen_description'] ?? '',
                    'roomGroundQueenPrice' => $data['room_ground_queen_price'] ?? '',
                    'roomGroundQueenCapacity' => $data['room_ground_queen_capacity'] ?? '',
                    'roomGroundQueenNote' => $data['room_ground_queen_note'] ?? '',
                    'roomGroundQueenGallery' => $data['room_ground_queen_gallery'] ?? '[]',
                    'roomGroundQueenBannerImageUrl' => $data['room_ground_queen_banner_image_url'] ?? '',
                    // Room Basement Queen bed page content
                    'roomBasementTitle' => $data['room_basement_title'] ?? '',
                    'roomBasementSubtitle' => $data['room_basement_subtitle'] ?? '',
                    'roomBasementDescription' => $data['room_basement_description'] ?? '',
                    'roomBasementPrice' => $data['room_basement_price'] ?? '',
                    'roomBasementCapacity' => $data['room_basement_capacity'] ?? '',
                    'roomBasementNote' => $data['room_basement_note'] ?? '',
                    'roomBasementGallery' => $data['room_basement_gallery'] ?? '[]',
                    'roomBasementBannerImageUrl' => $data['room_basement_banner_image_url'] ?? '',
                    // Wellness Experiences page content
                    'wellnessTitle' => $data['wellness_title'] ?? '',
                    'wellnessDescription' => $data['wellness_description'] ?? '',
                    'wellnessMassageTitle' => $data['wellness_massage_title'] ?? '',
                    'wellnessMassageDescription' => $data['wellness_massage_description'] ?? '',
                    'wellnessYogaTitle' => $data['wellness_yoga_title'] ?? '',
                    'wellnessYogaDescription' => $data['wellness_yoga_description'] ?? '',
                    'wellnessSaunaTitle' => $data['wellness_sauna_title'] ?? '',
                    'wellnessSaunaDescription' => $data['wellness_sauna_description'] ?? ''
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No data found in content_settings']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Content save handler - must be BEFORE auth_api.php to avoid "Invalid action" error
if ($action === 'save_content') {
    header('Content-Type: application/json');
    
    // Build dynamic UPDATE query based on provided fields
    $fields = [];
    $values = [];
    $types = '';
    
    // Homepage fields
    if (isset($_POST['homepage_description'])) {
        $fields[] = 'homepage_description = ?';
        $values[] = $_POST['homepage_description'];
        $types .= 's';
    }
    if (isset($_POST['homepage_subtitle'])) {
        $fields[] = 'homepage_subtitle = ?';
        $values[] = $_POST['homepage_subtitle'];
        $types .= 's';
    }
    if (isset($_POST['contact_phone'])) {
        $fields[] = 'contact_phone = ?';
        $values[] = $_POST['contact_phone'];
        $types .= 's';
    }
    if (isset($_POST['contact_email'])) {
        $fields[] = 'contact_email = ?';
        $values[] = $_POST['contact_email'];
        $types .= 's';
    }
    if (isset($_POST['contact_address'])) {
        $fields[] = 'contact_address = ?';
        $values[] = $_POST['contact_address'];
        $types .= 's';
    }
    if (isset($_POST['hero_image_url'])) {
        $fields[] = 'hero_image_url = ?';
        $values[] = $_POST['hero_image_url'];
        $types .= 's';
    }
    if (isset($_POST['hero2_image_url'])) {
        $fields[] = 'hero2_image_url = ?';
        $values[] = $_POST['hero2_image_url'];
        $types .= 's';
    }
    
    // Retreat and Workshop content fields
    $retreatFields = [
        'retreat_hero_title', 'retreat_hero_subtitle', 'retreat_intro_text',
        'retreat_locations_title', 'retreat_forest_title', 'retreat_forest_description',
        'retreat_forest_list_label', 'retreat_forest_list_items', 'retreat_indoor_title',
        'retreat_indoor_description', 'retreat_indoor_additional', 'retreat_theatre_title',
        'retreat_theatre_description', 'retreat_contact_title', 'retreat_contact_text',
        'retreat_organizer_title', 'retreat_workshops_title', 'retreat_workshops_intro',
        'retreat_workshops_list', 'retreat_workshops_conclusion', 'retreat_collaboration_title',
        'retreat_collaboration_intro', 'retreat_collaboration_list', 'retreat_collaboration_conclusion'
    ];
    
    // Special page content fields
    $specialFields = [
        'special_hero_title', 'special_hero_subtitle',
        'special_pools_title', 'special_pools_description_1', 'special_pools_description_2',
        'special_dining_title', 'special_dining_description_1', 'special_dining_description_2',
        'special_offer_title', 'special_offer_main_text', 'special_offer_description'
    ];
    
    // Massage page content fields
    $massageFields = [
        'massage_hero_title', 'massage_intro',
        'massage_relaxing_title', 'massage_relaxing_description',
        'massage_deep_tissue_title', 'massage_deep_tissue_description',
        'massage_reiki_title', 'massage_reiki_description',
        'massage_sauna_title', 'massage_sauna_description',
        'massage_booking_title'
    ];
    
    // Room Second floor page content fields
    $roomSecondFields = [
        'room_second_title', 'room_second_subtitle', 'room_second_description',
        'room_second_price', 'room_second_capacity', 'room_second_note',
        'room_second_gallery' // JSON array of image URLs
    ];
    
    // Room Ground Twin beds page content fields
    $roomGroundTwinFields = [
        'room_ground_twin_title', 'room_ground_twin_subtitle', 'room_ground_twin_description',
        'room_ground_twin_price', 'room_ground_twin_capacity', 'room_ground_twin_note',
        'room_ground_twin_gallery' // JSON array of image URLs
    ];
    
    // Room Ground Queen bed page content fields
    $roomGroundQueenFields = [
        'room_ground_queen_title', 'room_ground_queen_subtitle', 'room_ground_queen_description',
        'room_ground_queen_price', 'room_ground_queen_capacity', 'room_ground_queen_note',
        'room_ground_queen_gallery' // JSON array of image URLs
    ];
    
    // Room Basement Queen bed page content fields
    $roomBasementFields = [
        'room_basement_title', 'room_basement_subtitle', 'room_basement_description',
        'room_basement_price', 'room_basement_capacity', 'room_basement_note',
        'room_basement_gallery' // JSON array of image URLs
    ];
    
    // Wellness Experiences page content fields
    $wellnessFields = [
        'wellness_title', 'wellness_description',
        'wellness_massage_title', 'wellness_massage_description',
        'wellness_yoga_title', 'wellness_yoga_description',
        'wellness_sauna_title', 'wellness_sauna_description'
    ];
    
    // About us page content fields
    $aboutFields = [
        'about_hero_title', 'about_hero_subtitle',
        'about_idea_title', 'about_idea_intro', 'about_idea_paragraph_1', 'about_idea_paragraph_2',
        'about_idea_paragraph_3', 'about_idea_signature',
        'about_location_title', 'about_location_paragraph_1', 'about_location_paragraph_2',
        'about_location_paragraph_3', 'about_location_paragraph_4', 'about_location_coordinates',
        'about_location_deer_warning',
        'about_attractions_title', 'about_attractions_lead',
        'about_procter_title', 'about_procter_distance', 'about_procter_description',
        'about_halcyon_title', 'about_halcyon_distance', 'about_halcyon_description',
        'about_whitewater_title', 'about_whitewater_distance', 'about_whitewater_description',
        'about_nelson_title', 'about_nelson_distance', 'about_nelson_description',
        'about_parks_title', 'about_parks_intro', 'about_parks_list'
    ];
    
    foreach ($retreatFields as $field) {
        if (isset($_POST[$field])) {
            // Check if column exists, if not, add it
            $columnCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE '$field'");
            if ($columnCheck->num_rows === 0) {
                $alterTableSql = "ALTER TABLE content_settings ADD COLUMN $field TEXT NULL";
                if (!$conn->query($alterTableSql)) {
                    error_log('Failed to add column: ' . $conn->error);
                }
            }
            
            // Protect full texts from being overwritten by shortened versions
            // Get current value from database
            $currentResult = $conn->query("SELECT $field FROM content_settings WHERE id = 1");
            $currentRow = $currentResult->fetch_assoc();
            $currentValue = $currentRow[$field] ?? '';
            $newValue = $_POST[$field];
            
            // Don't overwrite full text with shortened version
            // Check if current value is longer and new value looks like a shortened version
            if (!empty($currentValue) && !empty($newValue)) {
                $currentLength = strlen($currentValue);
                $newLength = strlen($newValue);
                
                // If current text is significantly longer (more than 30% difference)
                // and new text contains ellipsis or is much shorter, skip update
                if ($currentLength > $newLength * 1.3) {
                    // Check if new value looks like a shortened version (contains ellipsis or ends with ...)
                    if (strpos($newValue, '...') !== false || 
                        strpos($newValue, '…') !== false ||
                        ($newLength < 100 && $currentLength > 150)) {
                        // Skip this update - keep the full text
                        error_log("Skipping update for $field: current text is longer and new text appears to be shortened");
                        continue;
                    }
                }
            }
            
            $fields[] = "$field = ?";
            $values[] = $newValue;
            $types .= 's';
        }
    }
    
    foreach ($specialFields as $field) {
        if (isset($_POST[$field])) {
            // Check if column exists, if not, add it
            $columnCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE '$field'");
            if ($columnCheck->num_rows === 0) {
                $alterTableSql = "ALTER TABLE content_settings ADD COLUMN $field TEXT NULL";
                if (!$conn->query($alterTableSql)) {
                    error_log('Failed to add column: ' . $conn->error);
                }
            }
            
            $fields[] = "$field = ?";
            $values[] = $_POST[$field];
            $types .= 's';
        }
    }
    
    foreach ($massageFields as $field) {
        if (isset($_POST[$field])) {
            // Check if column exists, if not, add it
            $columnCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE '$field'");
            if ($columnCheck->num_rows === 0) {
                $alterTableSql = "ALTER TABLE content_settings ADD COLUMN $field TEXT NULL";
                if (!$conn->query($alterTableSql)) {
                    error_log('Failed to add column: ' . $conn->error);
                }
            }
            
            $fields[] = "$field = ?";
            $values[] = $_POST[$field];
            $types .= 's';
        }
    }
    
    foreach ($roomSecondFields as $field) {
        if (isset($_POST[$field])) {
            // Check if column exists, if not, add it
            $columnCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE '$field'");
            if ($columnCheck->num_rows === 0) {
                $alterTableSql = "ALTER TABLE content_settings ADD COLUMN $field TEXT NULL";
                if (!$conn->query($alterTableSql)) {
                    error_log('Failed to add column: ' . $conn->error);
                }
            }
            
            $fields[] = "$field = ?";
            $values[] = $_POST[$field];
            $types .= 's';
        }
    }
    
    foreach ($roomGroundTwinFields as $field) {
        if (isset($_POST[$field])) {
            // Check if column exists, if not, add it
            $columnCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE '$field'");
            if ($columnCheck->num_rows === 0) {
                $alterTableSql = "ALTER TABLE content_settings ADD COLUMN $field TEXT NULL";
                if (!$conn->query($alterTableSql)) {
                    error_log('Failed to add column: ' . $conn->error);
                }
            }
            
            $fields[] = "$field = ?";
            $values[] = $_POST[$field];
            $types .= 's';
        }
    }
    
    foreach ($roomGroundQueenFields as $field) {
        if (isset($_POST[$field])) {
            // Check if column exists, if not, add it
            $columnCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE '$field'");
            if ($columnCheck->num_rows === 0) {
                $alterTableSql = "ALTER TABLE content_settings ADD COLUMN $field TEXT NULL";
                if (!$conn->query($alterTableSql)) {
                    error_log('Failed to add column: ' . $conn->error);
                }
            }
            
            $fields[] = "$field = ?";
            $values[] = $_POST[$field];
            $types .= 's';
        }
    }
    
    foreach ($roomBasementFields as $field) {
        if (isset($_POST[$field])) {
            // Check if column exists, if not, add it
            $columnCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE '$field'");
            if ($columnCheck->num_rows === 0) {
                $alterTableSql = "ALTER TABLE content_settings ADD COLUMN $field TEXT NULL";
                if (!$conn->query($alterTableSql)) {
                    error_log('Failed to add column: ' . $conn->error);
                }
            }
            
            $fields[] = "$field = ?";
            $values[] = $_POST[$field];
            $types .= 's';
        }
    }
    
    foreach ($wellnessFields as $field) {
        if (isset($_POST[$field])) {
            // Check if column exists, if not, add it
            $columnCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE '$field'");
            if ($columnCheck->num_rows === 0) {
                $alterTableSql = "ALTER TABLE content_settings ADD COLUMN $field TEXT NULL";
                if (!$conn->query($alterTableSql)) {
                    error_log('Failed to add column: ' . $conn->error);
                }
            }
            
            $fields[] = "$field = ?";
            $values[] = $_POST[$field];
            $types .= 's';
        }
    }
    
    foreach ($aboutFields as $field) {
        if (isset($_POST[$field])) {
            // Check if column exists, if not, add it
            $columnCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE '$field'");
            if ($columnCheck->num_rows === 0) {
                $alterTableSql = "ALTER TABLE content_settings ADD COLUMN $field TEXT NULL";
                if (!$conn->query($alterTableSql)) {
                    error_log('Failed to add column: ' . $conn->error);
                }
            }
            
            $fields[] = "$field = ?";
            $values[] = $_POST[$field];
            $types .= 's';
        }
    }
    
    // Image URL fields (wellness, rooms, massage, retreat, special, about)
    $imageFields = [
        'wellness_massage_image_url', 'wellness_yoga_image_url', 'wellness_sauna_image_url',
        'room_basement_card_image_url', 'room_ground_queen_card_image_url', 'room_ground_twin_card_image_url',
        'room_second_card_image_url', 'room_basement_banner_image_url', 'room_ground_queen_banner_image_url',
        'room_ground_twin_banner_image_url', 'room_second_banner_image_url',
        'massage_relaxing_image_url', 'massage_deep_tissue_image_url', 'massage_reiki_image_url',
        'massage_sauna_image_url', 'massage_room_booking_image_url',
        'retreat_hero_image_url', 'retreat_forest_image_url', 'retreat_indoor_image_url', 'retreat_theatre_image_url',
        'special_hero_image_url', 'special_pools_image_url', 'special_dining_image_url',
        'about_hero_image_url', 'about_founder_image_url', 'about_procter_image_url'
    ];
    
    foreach ($imageFields as $field) {
        if (isset($_POST[$field])) {
            // Check if column exists, if not, add it
            $columnCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE '$field'");
            if ($columnCheck->num_rows === 0) {
                $alterTableSql = "ALTER TABLE content_settings ADD COLUMN $field VARCHAR(255) NULL";
                if (!$conn->query($alterTableSql)) {
                    error_log('Failed to add column: ' . $conn->error);
                }
            }
            
            $fields[] = "$field = ?";
            $values[] = $_POST[$field];
            $types .= 's';
        }
    }
    
    if (empty($fields)) {
        echo json_encode(['success' => false, 'error' => 'No fields to update']);
        exit;
    }
    
    // Ensure record exists
    $checkResult = $conn->query("SELECT id FROM content_settings WHERE id = 1");
    if ($checkResult->num_rows === 0) {
        $conn->query("INSERT INTO content_settings (id) VALUES (1)");
    }
    
    $sql = "UPDATE content_settings SET " . implode(', ', $fields) . " WHERE id = 1";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Prepare failed: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param($types, ...$values);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    $stmt->close();
    exit;
}

// Include API handlers after action is defined
require_once 'floorplan_api.php';
require_once 'booking_api.php';
require_once 'auth_api.php';

if ($action === 'get_rooms') {
    $result = $conn->query("SELECT * FROM rooms ORDER BY created_at DESC");
    $rooms = [];
    while ($row = $result->fetch_assoc()) {
        $rooms[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $rooms]);
    exit;
}

if ($action === 'save_room') {
    $name = $_POST['name'] ?? '';
    $price = $_POST['price'] ?? 0;
    $capacity = $_POST['capacity'] ?? 0;
    $type = $_POST['type'] ?? '';
    $description = $_POST['description'] ?? '';
    $room_id = $_POST['room_id'] ?? null;
    
    if ($room_id) {
        // Обновление существующей комнаты
        $stmt = $conn->prepare("UPDATE rooms SET name = ?, price = ?, capacity = ?, type = ?, description = ? WHERE id = ?");
        $stmt->bind_param("siissi", $name, $price, $capacity, $type, $description, $room_id);
    } else {
        // Создание новой комнаты
        $stmt = $conn->prepare("INSERT INTO rooms (name, price, capacity, type, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("siiss", $name, $price, $capacity, $type, $description);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    $stmt->close();
    exit;
}

if ($action === 'delete_room') {
    $room_id = $_POST['room_id'] ?? 0;
    $stmt = $conn->prepare("DELETE FROM rooms WHERE id = ?");
    $stmt->bind_param("i", $room_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    $stmt->close();
    exit;
}

if ($action === 'get_floorplan') {
    $result = $conn->query("SELECT * FROM floorplan_settings WHERE id = 1");
    if ($result && $result->num_rows > 0) {
        $data = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'data' => [
                'basementSubtitle' => $data['basement_subtitle'] ?? '',
                'basementDescription' => $data['basement_description'] ?? '',
                'basementImageUrl' => $data['basement_image_url'] ?? '',
                'groundSubtitle' => $data['ground_subtitle'] ?? '',
                'groundDescription' => $data['ground_description'] ?? '',
                'groundQueenImage' => ($data['ground_image_url'] ?? $data['ground_queen_image'] ?? ''), // Universal: use ground_image_url first
                'groundTwinImage' => $data['ground_twin_image'] ?? '',
                'loftSubtitle' => $data['loft_subtitle'] ?? '',
                'loftDescription' => $data['loft_description'] ?? '',
                'loftImageUrl' => $data['loft_image_url'] ?? ''
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No floorplan data found']);
    }
    exit;
}

if ($action === 'save_floorplan') {
    $basement_subtitle = $_POST['basementSubtitle'] ?? '';
    $basement_description = $_POST['basementDescription'] ?? '';
    $basement_image_url = $_POST['basementImageUrl'] ?? '';
    $ground_subtitle = $_POST['groundSubtitle'] ?? '';
    $ground_description = $_POST['groundDescription'] ?? '';
    // Universal: support both ground_image_url and groundQueenImage
    $ground_queen_image = $_POST['groundQueenImage'] ?? $_POST['ground_image_url'] ?? '';
    $ground_twin_image = $_POST['groundTwinImage'] ?? '';
    $loft_subtitle = $_POST['loftSubtitle'] ?? '';
    $loft_description = $_POST['loftDescription'] ?? '';
    $loft_image_url = $_POST['loftImageUrl'] ?? '';
    
    $stmt = $conn->prepare("INSERT INTO floorplan_settings (
                           id, basement_subtitle, basement_description, basement_image_url,
                           ground_subtitle, ground_description, ground_queen_image, ground_twin_image,
                           loft_subtitle, loft_description, loft_image_url
                           ) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE
                           basement_subtitle = ?, basement_description = ?, basement_image_url = ?,
                           ground_subtitle = ?, ground_description = ?, ground_queen_image = ?, ground_twin_image = ?,
                           loft_subtitle = ?, loft_description = ?, loft_image_url = ?");
    $stmt->bind_param("ssssssssssssssssssss", 
        $basement_subtitle, $basement_description, $basement_image_url,
        $ground_subtitle, $ground_description, $ground_queen_image, $ground_twin_image,
        $loft_subtitle, $loft_description, $loft_image_url,
        $basement_subtitle, $basement_description, $basement_image_url,
        $ground_subtitle, $ground_description, $ground_queen_image, $ground_twin_image,
        $loft_subtitle, $loft_description, $loft_image_url);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    $stmt->close();
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);
$conn->close();
?>












error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

// Проверка подключения
if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

header('Content-Type: application/json');

$action = getApiAction();

if ($action === 'get_content') {
    try {
        $result = $conn->query("SELECT * FROM content_settings WHERE id = 1");
        
        if (!$result) {
            echo json_encode(['success' => false, 'error' => 'Query failed: ' . $conn->error]);
            exit;
        }
        
        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            echo json_encode([
                'success' => true,
                'data' => [
                    'homepageDescription' => $data['homepage_description'],
                    'homepageSubtitle' => $data['homepage_subtitle'],
                    'contactPhone' => $data['contact_phone'],
                    'contactEmail' => $data['contact_email'],
                    'contactAddress' => $data['contact_address'],
                    'heroImageUrl' => $data['hero_image_url'] ?? '',
                    'hero2ImageUrl' => $data['hero2_image_url'] ?? ''
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No data found in content_settings']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'save_content') {
    $homepage_desc = $_POST['homepage_description'] ?? '';
    $homepage_sub = $_POST['homepage_subtitle'] ?? '';
    $phone = $_POST['contact_phone'] ?? '';
    $email = $_POST['contact_email'] ?? '';
    $address = $_POST['contact_address'] ?? '';
    $heroImage = $_POST['hero_image_url'] ?? '';
    $hero2Image = $_POST['hero2_image_url'] ?? '';
    
    $stmt = $conn->prepare("UPDATE content_settings SET 
                           homepage_description = ?, 
                           homepage_subtitle = ?, 
                           contact_phone = ?, 
                           contact_email = ?, 
                           contact_address = ?,
                           hero_image_url = ?,
                           hero2_image_url = ? 
                           WHERE id = 1");
    $stmt->bind_param("sssssss", $homepage_desc, $homepage_sub, $phone, $email, $address, $heroImage, $hero2Image);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    $stmt->close();
    exit;
}

if ($action === 'get_rooms') {
    $result = $conn->query("SELECT * FROM rooms ORDER BY created_at DESC");
    $rooms = [];
    while ($row = $result->fetch_assoc()) {
        $rooms[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $rooms]);
    exit;
}

if ($action === 'save_room') {
    $name = $_POST['name'] ?? '';
    $price = $_POST['price'] ?? 0;
    $capacity = $_POST['capacity'] ?? 0;
    $type = $_POST['type'] ?? '';
    $description = $_POST['description'] ?? '';
    $room_id = $_POST['room_id'] ?? null;
    
    if ($room_id) {
        // Обновление существующей комнаты
        $stmt = $conn->prepare("UPDATE rooms SET name = ?, price = ?, capacity = ?, type = ?, description = ? WHERE id = ?");
        $stmt->bind_param("siissi", $name, $price, $capacity, $type, $description, $room_id);
    } else {
        // Создание новой комнаты
        $stmt = $conn->prepare("INSERT INTO rooms (name, price, capacity, type, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("siiss", $name, $price, $capacity, $type, $description);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    $stmt->close();
    exit;
}

if ($action === 'delete_room') {
    $room_id = $_POST['room_id'] ?? 0;
    $stmt = $conn->prepare("DELETE FROM rooms WHERE id = ?");
    $stmt->bind_param("i", $room_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    $stmt->close();
    exit;
}

if ($action === 'get_floorplan') {
    $result = $conn->query("SELECT * FROM floorplan_settings WHERE id = 1");
    if ($result && $result->num_rows > 0) {
        $data = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'data' => [
                'basementSubtitle' => $data['basement_subtitle'] ?? '',
                'basementDescription' => $data['basement_description'] ?? '',
                'basementImageUrl' => $data['basement_image_url'] ?? '',
                'groundSubtitle' => $data['ground_subtitle'] ?? '',
                'groundDescription' => $data['ground_description'] ?? '',
                'groundQueenImage' => ($data['ground_image_url'] ?? $data['ground_queen_image'] ?? ''), // Universal: use ground_image_url first
                'groundTwinImage' => $data['ground_twin_image'] ?? '',
                'loftSubtitle' => $data['loft_subtitle'] ?? '',
                'loftDescription' => $data['loft_description'] ?? '',
                'loftImageUrl' => $data['loft_image_url'] ?? ''
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No floorplan data found']);
    }
    exit;
}


echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);
$conn->close();
?>
















