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
        // Try to get rooms_title and rooms_subtitle from rooms_settings table first
        // If table doesn't exist, fall back to content_settings
        $roomsTitleValue = 'Choose your room';
        $roomsSubtitleValue = '';
        
        $roomsTableCheck = $conn->query("SHOW TABLES LIKE 'rooms_settings'");
        if ($roomsTableCheck && $roomsTableCheck->num_rows > 0) {
            // Use rooms_settings table
            $roomsResult = $conn->query("SELECT rooms_title, rooms_subtitle FROM rooms_settings WHERE id = 1");
            if ($roomsResult && $roomsResult->num_rows > 0) {
                $roomsData = $roomsResult->fetch_assoc();
                $roomsTitleValue = $roomsData['rooms_title'] ?? 'Choose your room';
                $roomsSubtitleValue = $roomsData['rooms_subtitle'] ?? '';
                error_log('get_content: Using rooms_settings table for rooms data');
            }
        } else {
            // Fall back to content_settings (for backward compatibility)
            $roomsTitleColumnCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE 'rooms_title'");
            $roomsTitleColumnExists = $roomsTitleColumnCheck && $roomsTitleColumnCheck->num_rows > 0;
            
            $roomsSubtitleColumnCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE 'rooms_subtitle'");
            $roomsSubtitleColumnExists = $roomsSubtitleColumnCheck && $roomsSubtitleColumnCheck->num_rows > 0;
            
            if ($roomsTitleColumnExists || $roomsSubtitleColumnExists) {
                $result = $conn->query("SELECT * FROM content_settings WHERE id = 1");
                if ($result && $result->num_rows > 0) {
                    $data = $result->fetch_assoc();
                    if ($roomsTitleColumnExists && array_key_exists('rooms_title', $data) && !empty($data['rooms_title'])) {
                        $roomsTitleValue = $data['rooms_title'];
                    }
                    if ($roomsSubtitleColumnExists && array_key_exists('rooms_subtitle', $data)) {
                        $roomsSubtitleValue = $data['rooms_subtitle'] ?? '';
                    }
                }
                error_log('get_content: Using content_settings table for rooms data (fallback)');
            }
        }
        
        // Try to get room card data and mini-hotel data from room_cards_settings table first
        // If table doesn't exist, fall back to content_settings
        $roomCardData = [];
        $miniHotelData = [];
        $roomCardsTableCheck = $conn->query("SHOW TABLES LIKE 'room_cards_settings'");
        if ($roomCardsTableCheck && $roomCardsTableCheck->num_rows > 0) {
            // Use room_cards_settings table
            // Check if mini-hotel columns exist before trying to select them
            $miniHotelColumnsExist = false;
            $columnCheck = $conn->query("SHOW COLUMNS FROM room_cards_settings LIKE 'mini_hotel_title'");
            if ($columnCheck && $columnCheck->num_rows > 0) {
                $miniHotelColumnsExist = true;
                error_log('get_content: mini-hotel columns exist in room_cards_settings');
            } else {
                error_log('get_content: mini-hotel columns do NOT exist in room_cards_settings');
            }
            
            $roomCardsResult = $conn->query("SELECT * FROM room_cards_settings WHERE id = 1");
            if ($roomCardsResult && $roomCardsResult->num_rows > 0) {
                $roomCardData = $roomCardsResult->fetch_assoc();
                error_log('get_content: Using room_cards_settings table for room card data');
                
                // Extract mini-hotel data from room_cards_settings only if columns exist
                if ($miniHotelColumnsExist) {
                    $miniHotelData = [
                        'mini_hotel_title' => $roomCardData['mini_hotel_title'] ?? '',
                        'mini_hotel_description' => $roomCardData['mini_hotel_description'] ?? '',
                        'mini_hotel_description_1' => $roomCardData['mini_hotel_description_1'] ?? '',
                        'mini_hotel_description_2' => $roomCardData['mini_hotel_description_2'] ?? '',
                        'mini_hotel_image_url' => $roomCardData['mini_hotel_image_url'] ?? '',
                    ];
                    error_log('get_content: Using room_cards_settings table for mini-hotel data: title="' . substr($miniHotelData['mini_hotel_title'] ?? '', 0, 50) . '", desc1="' . substr($miniHotelData['mini_hotel_description_1'] ?? '', 0, 50) . '"');
                } else {
                    error_log('get_content: mini-hotel columns do not exist in room_cards_settings, will try content_settings fallback');
                }
            }
        } else {
            error_log('get_content: room_cards_settings table does not exist, will use content_settings for mini-hotel');
        }
        
        // Try to get wellness images data from wellness_settings table first
        $wellnessImagesData = [];
        $wellnessTableCheck = $conn->query("SHOW TABLES LIKE 'wellness_settings'");
        if ($wellnessTableCheck && $wellnessTableCheck->num_rows > 0) {
            $wellnessResult = $conn->query("SELECT * FROM wellness_settings WHERE id = 1");
            if ($wellnessResult && $wellnessResult->num_rows > 0) {
                $wellnessImagesData = $wellnessResult->fetch_assoc();
                error_log('get_content: Using wellness_settings table for wellness images');
            }
        }
        
        // Try to get massage images data from massage_settings table first
        $massageImagesData = [];
        $massageTableCheck = $conn->query("SHOW TABLES LIKE 'massage_settings'");
        if ($massageTableCheck && $massageTableCheck->num_rows > 0) {
            $massageResult = $conn->query("SELECT * FROM massage_settings WHERE id = 1");
            if ($massageResult && $massageResult->num_rows > 0) {
                $massageImagesData = $massageResult->fetch_assoc();
                error_log('get_content: Using massage_settings table for massage images');
            }
        }
        
        // Try to get special page data from special_settings table first
        $specialData = [];
        $specialTableCheck = $conn->query("SHOW TABLES LIKE 'special_settings'");
        if ($specialTableCheck && $specialTableCheck->num_rows > 0) {
            $specialResult = $conn->query("SELECT * FROM special_settings WHERE id = 1");
            if ($specialResult && $specialResult->num_rows > 0) {
                $specialData = $specialResult->fetch_assoc();
                error_log('get_content: Using special_settings table for special page data');
            }
        }
        
        // Retreat collaboration card image: prefer retreat_settings (content_settings row often cannot grow)
        $retreatSettingsData = [];
        $retreatSettingsTableCheck = $conn->query("SHOW TABLES LIKE 'retreat_settings'");
        if ($retreatSettingsTableCheck && $retreatSettingsTableCheck->num_rows > 0) {
            $retreatSettingsResult = $conn->query("SELECT * FROM retreat_settings WHERE id = 1");
            if ($retreatSettingsResult && $retreatSettingsResult->num_rows > 0) {
                $retreatSettingsData = $retreatSettingsResult->fetch_assoc();
                error_log('get_content: Loaded retreat_settings row for retreat page extras');
            }
        }
        
        // Get other content from content_settings
        // Try to select all columns, but handle errors if some columns don't exist
        $data = [];
        try {
            $result = $conn->query("SELECT * FROM content_settings WHERE id = 1");
            if ($result && $result->num_rows > 0) {
                $data = $result->fetch_assoc();
            } else if (!$result) {
                // If SELECT * fails due to missing columns, try to select only known columns
                error_log('get_content: SELECT * failed, trying explicit columns: ' . $conn->error);
                // Fall back to selecting only columns that definitely exist
                $result = $conn->query("SELECT id, homepage_description, homepage_subtitle, contact_phone, contact_email, contact_address FROM content_settings WHERE id = 1");
                if ($result && $result->num_rows > 0) {
                    $data = $result->fetch_assoc();
                }
            }
        } catch (Exception $e) {
            error_log('get_content: Exception during SELECT: ' . $e->getMessage());
            // Try to select only known columns
            $result = $conn->query("SELECT id, homepage_description, homepage_subtitle, contact_phone, contact_email, contact_address FROM content_settings WHERE id = 1");
            if ($result && $result->num_rows > 0) {
                $data = $result->fetch_assoc();
            }
        }
        
        // Explore page hero: separate table when content_settings row is at size limit
        $exploreSettingsTableCheck = $conn->query("SHOW TABLES LIKE 'explore_settings'");
        if ($exploreSettingsTableCheck && $exploreSettingsTableCheck->num_rows > 0) {
            $exR = $conn->query('SELECT * FROM explore_settings WHERE id = 1');
            if ($exR && $exR->num_rows > 0) {
                $exRow = $exR->fetch_assoc();
                if (!is_array($data)) {
                    $data = [];
                }
                if (is_array($exRow)) {
                    foreach ($exRow as $k => $v) {
                        if ($k === 'id') {
                            continue;
                        }
                        if (strpos((string) $k, 'explore_') === 0) {
                            $data[$k] = $v;
                        }
                    }
                }
                error_log('get_content: Merged explore_settings into content');
            }
        }

        // Explore — Provincial parks block (separate table to avoid content_settings row growth)
        $exploreParksTableCheck = $conn->query("SHOW TABLES LIKE 'explore_parks_settings'");
        if ($exploreParksTableCheck && $exploreParksTableCheck->num_rows > 0) {
            $epR = $conn->query("SELECT title, intro, parks_list, map_lat, map_lng, hero_image_url, gallery, parks_cards FROM explore_parks_settings WHERE id = 1");
            if ($epR && $epR->num_rows > 0) {
                $epRow = $epR->fetch_assoc();
                if (!is_array($data)) {
                    $data = [];
                }
                if (array_key_exists('title', $epRow) && $epRow['title'] !== null) {
                    $data['about_parks_title'] = $epRow['title'];
                }
                if (array_key_exists('intro', $epRow) && $epRow['intro'] !== null) {
                    $data['about_parks_intro'] = $epRow['intro'];
                }
                if (array_key_exists('parks_list', $epRow) && $epRow['parks_list'] !== null) {
                    $data['about_parks_list'] = $epRow['parks_list'];
                }
                if (array_key_exists('map_lat', $epRow)) {
                    $data['about_parks_map_lat'] = $epRow['map_lat'];
                }
                if (array_key_exists('map_lng', $epRow)) {
                    $data['about_parks_map_lng'] = $epRow['map_lng'];
                }
                if (array_key_exists('hero_image_url', $epRow)) {
                    $data['about_parks_hero_image_url'] = $epRow['hero_image_url'];
                }
                if (array_key_exists('gallery', $epRow)) {
                    $data['about_parks_gallery'] = $epRow['gallery'];
                }
                if (array_key_exists('parks_cards', $epRow)) {
                    $data['about_parks_cards'] = $epRow['parks_cards'];
                }
                error_log('get_content: Merged explore_parks_settings into content');
            }
        }

        $exploreCommunityExtraTableCheck = $conn->query("SHOW TABLES LIKE 'explore_community_extra'");
        if ($exploreCommunityExtraTableCheck && $exploreCommunityExtraTableCheck->num_rows > 0) {
            $ecR = $conn->query('SELECT * FROM explore_community_extra WHERE id = 1');
            if ($ecR && $ecR->num_rows > 0) {
                $ecRow = $ecR->fetch_assoc();
                if (!is_array($data)) {
                    $data = [];
                }
                foreach ($ecRow as $k => $v) {
                    if ($k === 'id' || $v === null) {
                        continue;
                    }
                    if ($k === 'about_nelson_image_url') {
                        $cur = trim((string) ($data['about_nelson_image_url'] ?? ''));
                        if ($cur === '' && trim((string) $v) !== '') {
                            $data['about_nelson_image_url'] = $v;
                        }
                    } elseif (strpos((string) $k, 'about_kaslo_') === 0 || strpos((string) $k, 'about_crawford_') === 0 || strpos((string) $k, 'about_museum_') === 0) {
                        $data[$k] = $v;
                    } elseif (strpos((string) $k, 'explore_') === 0) {
                        $data[$k] = $v;
                    }
                }
                error_log('get_content: Merged explore_community_extra into content');
            }
        }
        
        // If mini-hotel columns exist but are not in $data, try explicit SELECT (same approach as Sauna)
        if (empty($data['mini_hotel_title']) && empty($data['mini_hotel_description']) && empty($data['mini_hotel_description_1']) && empty($data['mini_hotel_description_2'])) {
            $columnCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE 'mini_hotel_title'");
            if ($columnCheck && $columnCheck->num_rows > 0) {
                try {
                    $miniHotelResult = $conn->query("SELECT mini_hotel_title, mini_hotel_description_1, mini_hotel_description_2 FROM content_settings WHERE id = 1");
                    if ($miniHotelResult && $miniHotelResult->num_rows > 0) {
                        $miniHotelData = $miniHotelResult->fetch_assoc();
                        $data['mini_hotel_title'] = $miniHotelData['mini_hotel_title'] ?? '';
                        $data['mini_hotel_description_1'] = $miniHotelData['mini_hotel_description_1'] ?? '';
                        $data['mini_hotel_description_2'] = $miniHotelData['mini_hotel_description_2'] ?? '';
                        error_log('get_content: Loaded mini-hotel data via explicit SELECT');
                    }
                } catch (Exception $e) {
                    error_log('get_content: Exception while loading mini-hotel data via explicit SELECT: ' . $e->getMessage());
                }
            }
        }
        
        if (!empty($data)) {
            $roomPricePartsBasement = btb_room_price_parts_for_admin($data, 'basement');
            $roomPricePartsGroundQueen = btb_room_price_parts_for_admin($data, 'ground_queen');
            $roomPricePartsGroundTwin = btb_room_price_parts_for_admin($data, 'ground_twin');
            $roomPricePartsSecond = btb_room_price_parts_for_admin($data, 'second');
            
            // Use room card data from room_cards_settings if available, otherwise from content_settings
            $roomBasementCardTitle = !empty($roomCardData) ? ($roomCardData['room_basement_card_title'] ?? '') : ($data['room_basement_card_title'] ?? '');
            $roomBasementCardDescription = !empty($roomCardData) ? ($roomCardData['room_basement_card_description'] ?? '') : ($data['room_basement_card_description'] ?? '');
            $roomBasementCardPrice = btb_room_price_line_html($data, 'basement', btb_room_price_default_line_html('basement'));
            $roomBasementCardImageUrl = !empty($roomCardData) ? ($roomCardData['room_basement_card_image_url'] ?? '') : ($data['room_basement_card_image_url'] ?? '');
            
            $roomGroundQueenCardTitle = !empty($roomCardData) ? ($roomCardData['room_ground_queen_card_title'] ?? '') : ($data['room_ground_queen_card_title'] ?? '');
            $roomGroundQueenCardDescription = !empty($roomCardData) ? ($roomCardData['room_ground_queen_card_description'] ?? '') : ($data['room_ground_queen_card_description'] ?? '');
            $roomGroundQueenCardPrice = btb_room_price_line_html($data, 'ground_queen', btb_room_price_default_line_html('ground_queen'));
            $roomGroundQueenCardImageUrl = !empty($roomCardData) ? ($roomCardData['room_ground_queen_card_image_url'] ?? '') : ($data['room_ground_queen_card_image_url'] ?? '');
            
            $roomGroundTwinCardTitle = !empty($roomCardData) ? ($roomCardData['room_ground_twin_card_title'] ?? '') : ($data['room_ground_twin_card_title'] ?? '');
            $roomGroundTwinCardDescription = !empty($roomCardData) ? ($roomCardData['room_ground_twin_card_description'] ?? '') : ($data['room_ground_twin_card_description'] ?? '');
            $roomGroundTwinCardPrice = btb_room_price_line_html($data, 'ground_twin', btb_room_price_default_line_html('ground_twin'));
            $roomGroundTwinCardImageUrl = !empty($roomCardData) ? ($roomCardData['room_ground_twin_card_image_url'] ?? '') : ($data['room_ground_twin_card_image_url'] ?? '');
            
            $roomSecondCardTitle = !empty($roomCardData) ? ($roomCardData['room_second_card_title'] ?? '') : ($data['room_second_card_title'] ?? '');
            $roomSecondCardDescription = !empty($roomCardData) ? ($roomCardData['room_second_card_description'] ?? '') : ($data['room_second_card_description'] ?? '');
            $roomSecondCardPrice = btb_room_price_line_html($data, 'second', btb_room_price_default_line_html('second'));
            $roomSecondCardImageUrl = !empty($roomCardData) ? ($roomCardData['room_second_card_image_url'] ?? '') : ($data['room_second_card_image_url'] ?? '');
            
            error_log('get_content: Room basement card data - title: ' . substr($roomBasementCardTitle, 0, 50) . ' (isset: ' . (isset($roomCardData['room_basement_card_title']) || isset($data['room_basement_card_title']) ? 'yes' : 'no') . '), description: ' . substr($roomBasementCardDescription, 0, 50) . ', price: ' . substr($roomBasementCardPrice, 0, 50));
            
            // Log mini-hotel and sauna data for comparison
            error_log('get_content: Sauna data - title: ' . (isset($data['massage_sauna_title']) ? ('"' . substr($data['massage_sauna_title'], 0, 50) . '"') : 'NOT SET') . ', description: ' . (isset($data['massage_sauna_description']) ? ('"' . substr($data['massage_sauna_description'], 0, 50) . '"') : 'NOT SET'));
            
            // Use mini-hotel data from room_cards_settings if available, otherwise from content_settings
            $miniHotelTitle = !empty($miniHotelData) ? ($miniHotelData['mini_hotel_title'] ?? '') : ($data['mini_hotel_title'] ?? '');
            $miniHotelDesc1 = !empty($miniHotelData) ? ($miniHotelData['mini_hotel_description_1'] ?? '') : ($data['mini_hotel_description_1'] ?? '');
            $miniHotelDesc2 = !empty($miniHotelData) ? ($miniHotelData['mini_hotel_description_2'] ?? '') : ($data['mini_hotel_description_2'] ?? '');
            $miniHotelDescRaw = (string) (!empty($miniHotelData) ? ($miniHotelData['mini_hotel_description'] ?? '') : ($data['mini_hotel_description'] ?? ''));
            $miniHotelDescriptionMerged = trim($miniHotelDescRaw) !== ''
                ? $miniHotelDescRaw
                : (trim((string) $miniHotelDesc1) === '' && trim((string) $miniHotelDesc2) === ''
                    ? ''
                    : (trim((string) $miniHotelDesc1) !== '' && trim((string) $miniHotelDesc2) !== ''
                        ? (string) $miniHotelDesc1 . "\n\n" . (string) $miniHotelDesc2
                        : (trim((string) $miniHotelDesc2) !== '' ? (string) $miniHotelDesc2 : (string) $miniHotelDesc1)));
            
            error_log('get_content: Mini-hotel data - title: ' . ($miniHotelTitle ? ('"' . substr($miniHotelTitle, 0, 50) . '"') : 'EMPTY') . ', desc1: ' . ($miniHotelDesc1 ? ('"' . substr($miniHotelDesc1, 0, 50) . '"') : 'EMPTY') . ', desc2: ' . ($miniHotelDesc2 ? ('"' . substr($miniHotelDesc2, 0, 50) . '"') : 'EMPTY') . ' (source: ' . (!empty($miniHotelData) ? 'room_cards_settings' : 'content_settings') . ')');
            
            $retreatCollaborationImageUrlOut = trim($data['retreat_collaboration_image_url'] ?? '');
            if (!empty($retreatSettingsData) && array_key_exists('retreat_collaboration_image_url', $retreatSettingsData)) {
                $fromRs = trim((string)($retreatSettingsData['retreat_collaboration_image_url'] ?? ''));
                if ($fromRs !== '') {
                    $retreatCollaborationImageUrlOut = $fromRs;
                }
            }

            $exploreAccDescRaw = (string)($data['explore_accommodation_description'] ?? '');
            if (trim($exploreAccDescRaw) !== '') {
                $exploreAccommodationDescriptionOut = $exploreAccDescRaw;
            } else {
                $legacyA = trim((string)($data['explore_accommodation_description_1'] ?? ''));
                $legacyB = trim((string)($data['explore_accommodation_description_2'] ?? ''));
                if ($legacyA !== '' && $legacyB !== '') {
                    $exploreAccommodationDescriptionOut = $legacyA . "\n\n" . $legacyB;
                } else {
                    $exploreAccommodationDescriptionOut = $legacyB !== '' ? $legacyB : $legacyA;
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'homepageDescription' => $data['homepage_description'],
                    'homepageSubtitle' => $data['homepage_subtitle'],
                    'roomsTitle' => $roomsTitleValue,
                    'roomsSubtitle' => $roomsSubtitleValue,
                    'contactPhone' => $data['contact_phone'],
                    'contactEmail' => $data['contact_email'],
                    'contactAddress' => $data['contact_address'],
                    'heroImageUrl' => $data['hero_image_url'] ?? '',
                    'hero2ImageUrl' => $data['hero2_image_url'] ?? '',
                    // Wellness Experiences images
                    'wellnessMassageImageUrl' => !empty($wellnessImagesData) ? ($wellnessImagesData['wellness_massage_image_url'] ?? '') : ($data['wellness_massage_image_url'] ?? ''),
                    'wellnessYogaImageUrl' => !empty($wellnessImagesData) ? ($wellnessImagesData['wellness_yoga_image_url'] ?? '') : ($data['wellness_yoga_image_url'] ?? ''),
                    // Room cards images
                    'roomBasementCardImageUrl' => $roomBasementCardImageUrl,
                    'roomBasementCardTitle' => $roomBasementCardTitle,
                    'roomBasementCardDescription' => $roomBasementCardDescription,
                    'roomBasementCardPrice' => $roomBasementCardPrice,
                    'roomGroundQueenCardImageUrl' => $roomGroundQueenCardImageUrl,
                    'roomGroundQueenCardTitle' => $roomGroundQueenCardTitle,
                    'roomGroundQueenCardDescription' => $roomGroundQueenCardDescription,
                    'roomGroundQueenCardPrice' => $roomGroundQueenCardPrice,
                    'roomGroundTwinCardImageUrl' => $roomGroundTwinCardImageUrl,
                    'roomGroundTwinCardTitle' => $roomGroundTwinCardTitle,
                    'roomGroundTwinCardDescription' => $roomGroundTwinCardDescription,
                    'roomGroundTwinCardPrice' => $roomGroundTwinCardPrice,
                    'roomSecondCardImageUrl' => $roomSecondCardImageUrl,
                    'roomSecondCardTitle' => $roomSecondCardTitle,
                    'roomSecondCardDescription' => $roomSecondCardDescription,
                    'roomSecondCardPrice' => $roomSecondCardPrice,
                    // Room banners
                    'roomBasementBannerImageUrl' => $data['room_basement_banner_image_url'] ?? '',
                    'roomGroundQueenBannerImageUrl' => $data['room_ground_queen_banner_image_url'] ?? '',
                    'roomGroundTwinBannerImageUrl' => $data['room_ground_twin_banner_image_url'] ?? '',
                    'roomSecondBannerImageUrl' => $data['room_second_banner_image_url'] ?? '',
                    // Massage page images - use massage_settings if available, otherwise content_settings
                    'massageHeroImageUrl' => !empty($massageImagesData) ? ($massageImagesData['massage_hero_image_url'] ?? '') : ($data['massage_hero_image_url'] ?? ''),
                    'massageRelaxingImageUrl' => !empty($massageImagesData) ? ($massageImagesData['massage_relaxing_image_url'] ?? '') : ($data['massage_relaxing_image_url'] ?? ''),
                    'massageDeepTissueImageUrl' => !empty($massageImagesData) ? ($massageImagesData['massage_deep_tissue_image_url'] ?? '') : ($data['massage_deep_tissue_image_url'] ?? ''),
                    'massageReikiImageUrl' => !empty($massageImagesData) ? ($massageImagesData['massage_reiki_image_url'] ?? '') : ($data['massage_reiki_image_url'] ?? ''),
                    'massageSaunaImageUrl' => !empty($massageImagesData) ? ($massageImagesData['massage_sauna_image_url'] ?? '') : ($data['massage_sauna_image_url'] ?? ''),
                    'miniHotelImageUrl' => !empty($miniHotelData) ? ($miniHotelData['mini_hotel_image_url'] ?? '') : ($data['mini_hotel_image_url'] ?? ''),
                    // Retreat and Workshop page images
                    'retreatHeroImageUrl' => $data['retreat_hero_image_url'] ?? '',
                    'retreatForestImageUrl' => $data['retreat_forest_image_url'] ?? '',
                    'retreatIndoorImageUrl' => $data['retreat_indoor_image_url'] ?? '',
                    'retreatTheatreImageUrl' => $data['retreat_theatre_image_url'] ?? '',
                    'retreatCollaborationImageUrl' => $retreatCollaborationImageUrlOut,
                    // Special page images - use special_settings if available, otherwise content_settings
                    'specialHeroImageUrl' => !empty($specialData) ? ($specialData['special_hero_image_url'] ?? '') : ($data['special_hero_image_url'] ?? ''),
                    'specialPoolsImageUrl' => !empty($specialData) ? ($specialData['special_pools_image_url'] ?? '') : ($data['special_pools_image_url'] ?? ''),
                    'specialDiningImageUrl' => !empty($specialData) ? ($specialData['special_dining_image_url'] ?? '') : ($data['special_dining_image_url'] ?? ''),
                    'specialExtraImageUrl' => !empty($specialData) ? ($specialData['special_extra_image_url'] ?? '') : ($data['special_extra_image_url'] ?? ''),
                    // About us page images
                    'aboutHeroImageUrl' => $data['about_hero_image_url'] ?? '',
                    'aboutFounderImageUrl' => $data['about_founder_image_url'] ?? '',
                    'aboutProcterImageUrl' => $data['about_procter_image_url'] ?? '',
                    'aboutNelsonImageUrl' => $data['about_nelson_image_url'] ?? '',
                    'aboutProcterGallery' => $data['about_procter_gallery'] ?? '[]',
                    'aboutHalcyonGallery' => $data['about_halcyon_gallery'] ?? '[]',
                    'aboutWhitewaterGallery' => $data['about_whitewater_gallery'] ?? '[]',
                    'aboutNelsonGallery' => $data['about_nelson_gallery'] ?? '[]',
                    'aboutKasloImageUrl' => $data['about_kaslo_image_url'] ?? '',
                    'aboutKasloGallery' => $data['about_kaslo_gallery'] ?? '[]',
                    'aboutCrawfordImageUrl' => $data['about_crawford_image_url'] ?? '',
                    'aboutCrawfordGallery' => $data['about_crawford_gallery'] ?? '[]',
                    'aboutMuseumImageUrl' => $data['about_museum_image_url'] ?? '',
                    'aboutMuseumGallery' => $data['about_museum_gallery'] ?? '[]',
                    // Retreat and Workshop page content
                    'retreatHeroTitle' => $data['retreat_hero_title'] ?? '',
                    'retreatHeroSubtitle' => $data['retreat_hero_subtitle'] ?? '',
                    'retreatIntroText' => $data['retreat_intro_text'] ?? '',
                    'retreatLocationsTitle' => $data['retreat_locations_title'] ?? '',
                    'retreatForestTitle' => $data['retreat_forest_title'] ?? '',
                    'retreatForestDescription' => $data['retreat_forest_description'] ?? '',
                    'retreatForestListLabel' => $data['retreat_forest_list_label'] ?? '',
                    'retreatForestListItems' => $data['retreat_forest_list_items'] ?? '',
                    'retreatForestGallery' => $data['retreat_forest_gallery'] ?? '[]',
                    'retreatIndoorTitle' => $data['retreat_indoor_title'] ?? '',
                    'retreatIndoorDescription' => $data['retreat_indoor_description'] ?? '',
                    'retreatIndoorAdditional' => $data['retreat_indoor_additional'] ?? '',
                    'retreatIndoorGallery' => $data['retreat_indoor_gallery'] ?? '[]',
                    'retreatTheatreTitle' => $data['retreat_theatre_title'] ?? '',
                    'retreatTheatreDescription' => $data['retreat_theatre_description'] ?? '',
                    'retreatTheatreGallery' => $data['retreat_theatre_gallery'] ?? '[]',
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
                    // Special page content - use special_settings if available, otherwise content_settings
                    'specialHeroTitle' => !empty($specialData) ? ($specialData['special_hero_title'] ?? '') : ($data['special_hero_title'] ?? ''),
                    'specialHeroSubtitle' => !empty($specialData) ? ($specialData['special_hero_subtitle'] ?? '') : ($data['special_hero_subtitle'] ?? ''),
                    'specialPoolsTitle' => !empty($specialData) ? ($specialData['special_pools_title'] ?? '') : ($data['special_pools_title'] ?? ''),
                    'specialPoolsDescription1' => !empty($specialData) ? ($specialData['special_pools_description_1'] ?? '') : ($data['special_pools_description_1'] ?? ''),
                    'specialPoolsDescription2' => !empty($specialData) ? ($specialData['special_pools_description_2'] ?? '') : ($data['special_pools_description_2'] ?? ''),
                    'specialDiningTitle' => !empty($specialData) ? ($specialData['special_dining_title'] ?? '') : ($data['special_dining_title'] ?? ''),
                    'specialDiningDescription1' => !empty($specialData) ? ($specialData['special_dining_description_1'] ?? '') : ($data['special_dining_description_1'] ?? ''),
                    'specialDiningDescription2' => !empty($specialData) ? ($specialData['special_dining_description_2'] ?? '') : ($data['special_dining_description_2'] ?? ''),
                    'specialExtraTitle' => !empty($specialData) ? ($specialData['special_extra_title'] ?? '') : ($data['special_extra_title'] ?? ''),
                    'specialExtraDescription1' => !empty($specialData) ? ($specialData['special_extra_description_1'] ?? '') : ($data['special_extra_description_1'] ?? ''),
                    'specialExtraDescription2' => !empty($specialData) ? ($specialData['special_extra_description_2'] ?? '') : ($data['special_extra_description_2'] ?? ''),
                    'specialOfferTitle' => !empty($specialData) ? ($specialData['special_offer_title'] ?? '') : ($data['special_offer_title'] ?? ''),
                    'specialOfferMainText' => !empty($specialData) ? ($specialData['special_offer_main_text'] ?? '') : ($data['special_offer_main_text'] ?? ''),
                    'specialOfferDescription' => !empty($specialData) ? ($specialData['special_offer_description'] ?? '') : ($data['special_offer_description'] ?? ''),
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
                    'aboutContactFormTitle' => $data['about_contact_form_title'] ?? '',
                    'aboutContactFormDescription' => $data['about_contact_form_description'] ?? '',
                    // Legacy; admin no longer saves these — used if description empty on old DB rows
                    'aboutContactFormLead' => $data['about_contact_form_lead'] ?? '',
                    'aboutContactFormEmphasis' => $data['about_contact_form_emphasis'] ?? '',
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
                    'aboutKasloTitle' => $data['about_kaslo_title'] ?? '',
                    'aboutKasloDistance' => $data['about_kaslo_distance'] ?? '',
                    'aboutKasloDescription' => $data['about_kaslo_description'] ?? '',
                    'aboutCrawfordTitle' => $data['about_crawford_title'] ?? '',
                    'aboutCrawfordDistance' => $data['about_crawford_distance'] ?? '',
                    'aboutCrawfordDescription' => $data['about_crawford_description'] ?? '',
                    'aboutMuseumTitle' => $data['about_museum_title'] ?? '',
                    'aboutMuseumDistance' => $data['about_museum_distance'] ?? '',
                    'aboutMuseumDescription' => $data['about_museum_description'] ?? '',
                    'exploreCommunitiesH2' => $data['explore_communities_h2'] ?? '',
                    'exploreCultureH2' => $data['explore_culture_h2'] ?? '',
                    'exploreParksH2' => $data['explore_parks_h2'] ?? '',
                    'exploreActivitiesH2' => $data['explore_activities_h2'] ?? '',
                    'exploreCommunitiesIntro' => $data['explore_communities_intro'] ?? '',
                    'exploreCultureIntro' => $data['explore_culture_intro'] ?? '',
                    'exploreActivitiesIntro' => $data['explore_activities_intro'] ?? '',
                    'exploreCommunitiesCards' => $data['explore_communities_cards'] ?? '',
                    'exploreCultureCards' => $data['explore_culture_cards'] ?? '',
                    'exploreActivitiesCards' => $data['explore_activities_cards'] ?? '',
                    'aboutParksTitle' => $data['about_parks_title'] ?? '',
                    'aboutParksIntro' => $data['about_parks_intro'] ?? '',
                    'aboutParksList' => $data['about_parks_list'] ?? '',
                    'aboutParksMapLat' => $data['about_parks_map_lat'] ?? '',
                    'aboutParksMapLng' => $data['about_parks_map_lng'] ?? '',
                    'aboutParksHeroImageUrl' => $data['about_parks_hero_image_url'] ?? '',
                    'aboutParksGallery' => $data['about_parks_gallery'] ?? '[]',
                    'aboutParksCards' => $data['about_parks_cards'] ?? '',
                    'exploreHeroTitle' => $data['explore_hero_title'] ?? '',
                    'exploreHeroSubtitle' => $data['explore_hero_subtitle'] ?? '',
                    'exploreHeroImageUrl' => $data['explore_hero_image_url'] ?? '',
                    'exploreAccommodationTitle' => $data['explore_accommodation_title'] ?? '',
                    'exploreAccommodationDescription' => $exploreAccommodationDescriptionOut,
                    'exploreAccommodationImageUrl' => $data['explore_accommodation_image_url'] ?? '',
                    // Massage page content
                    'massageHeroTitle' => $data['massage_hero_title'] ?? '',
                    'massageHeroImageUrl' => $data['massage_hero_image_url'] ?? '',
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
                    'massageBookingIntro' => $data['massage_booking_intro'] ?? '',
                    'massagePricingRelaxing' => $data['massage_pricing_relaxing'] ?? '',
                    'massagePricingDeepTissue' => $data['massage_pricing_deep_tissue'] ?? '',
                    'massagePricingReiki' => $data['massage_pricing_reiki'] ?? '',
                    'massagePricingSauna' => $data['massage_pricing_sauna'] ?? '',
                    'miniHotelTitle' => $miniHotelTitle,
                    'miniHotelDescription' => $miniHotelDescriptionMerged,
                    'miniHotelDescription1' => $miniHotelDesc1,
                    'miniHotelDescription2' => $miniHotelDesc2,
                    // Room Second floor page content
                    'roomSecondTitle' => $data['room_second_title'] ?? '',
                    'roomSecondSubtitle' => $data['room_second_subtitle'] ?? '',
                    'roomSecondDescription' => $data['room_second_description'] ?? '',
                    'roomSecondPrice' => btb_room_price_line_html($data, 'second', btb_room_price_default_line_html('second')),
                    'roomSecondPricePrefix' => $roomPricePartsSecond['prefix'],
                    'roomSecondPriceAmount' => $roomPricePartsSecond['amount'],
                    'roomSecondPriceSuffix' => $roomPricePartsSecond['suffix'],
                    'roomSecondCapacity' => $data['room_second_capacity'] ?? '',
                    'roomSecondNote' => $data['room_second_note'] ?? '',
                    'roomSecondGallery' => $data['room_second_gallery'] ?? '[]',
                    'roomSecondGallerySectionTitle' => $data['room_second_gallery_section_title'] ?? '',
                    'roomSecondCommonGallery' => $data['room_second_common_gallery'] ?? '[]',
                    'roomSecondCommonGallerySectionTitle' => $data['room_second_common_gallery_section_title'] ?? '',
                    'roomSecondBannerImageUrl' => $data['room_second_banner_image_url'] ?? '',
                    // Room Ground Twin beds page content
                    'roomGroundTwinTitle' => $data['room_ground_twin_title'] ?? '',
                    'roomGroundTwinSubtitle' => $data['room_ground_twin_subtitle'] ?? '',
                    'roomGroundTwinDescription' => $data['room_ground_twin_description'] ?? '',
                    'roomGroundTwinPrice' => btb_room_price_line_html($data, 'ground_twin', btb_room_price_default_line_html('ground_twin')),
                    'roomGroundTwinPricePrefix' => $roomPricePartsGroundTwin['prefix'],
                    'roomGroundTwinPriceAmount' => $roomPricePartsGroundTwin['amount'],
                    'roomGroundTwinPriceSuffix' => $roomPricePartsGroundTwin['suffix'],
                    'roomGroundTwinCapacity' => $data['room_ground_twin_capacity'] ?? '',
                    'roomGroundTwinNote' => $data['room_ground_twin_note'] ?? '',
                    'roomGroundTwinGallery' => $data['room_ground_twin_gallery'] ?? '[]',
                    'roomGroundTwinGallerySectionTitle' => $data['room_ground_twin_gallery_section_title'] ?? '',
                    'roomGroundTwinCommonGallery' => $data['room_ground_twin_common_gallery'] ?? '[]',
                    'roomGroundTwinCommonGallerySectionTitle' => $data['room_ground_twin_common_gallery_section_title'] ?? '',
                    'roomGroundTwinBannerImageUrl' => $data['room_ground_twin_banner_image_url'] ?? '',
                    // Room Ground Queen bed page content
                    'roomGroundQueenTitle' => $data['room_ground_queen_title'] ?? '',
                    'roomGroundQueenSubtitle' => $data['room_ground_queen_subtitle'] ?? '',
                    'roomGroundQueenDescription' => $data['room_ground_queen_description'] ?? '',
                    'roomGroundQueenPrice' => btb_room_price_line_html($data, 'ground_queen', btb_room_price_default_line_html('ground_queen')),
                    'roomGroundQueenPricePrefix' => $roomPricePartsGroundQueen['prefix'],
                    'roomGroundQueenPriceAmount' => $roomPricePartsGroundQueen['amount'],
                    'roomGroundQueenPriceSuffix' => $roomPricePartsGroundQueen['suffix'],
                    'roomGroundQueenCapacity' => $data['room_ground_queen_capacity'] ?? '',
                    'roomGroundQueenNote' => $data['room_ground_queen_note'] ?? '',
                    'roomGroundQueenGallery' => $data['room_ground_queen_gallery'] ?? '[]',
                    'roomGroundQueenGallerySectionTitle' => $data['room_ground_queen_gallery_section_title'] ?? '',
                    'roomGroundQueenCommonGallery' => $data['room_ground_queen_common_gallery'] ?? '[]',
                    'roomGroundQueenCommonGallerySectionTitle' => $data['room_ground_queen_common_gallery_section_title'] ?? '',
                    'roomGroundQueenBannerImageUrl' => $data['room_ground_queen_banner_image_url'] ?? '',
                    // Room Basement Queen bed page content
                    'roomBasementTitle' => $data['room_basement_title'] ?? '',
                    'roomBasementSubtitle' => $data['room_basement_subtitle'] ?? '',
                    'roomBasementDescription' => $data['room_basement_description'] ?? '',
                    'roomBasementPrice' => btb_room_price_line_html($data, 'basement', btb_room_price_default_line_html('basement')),
                    'roomBasementPricePrefix' => $roomPricePartsBasement['prefix'],
                    'roomBasementPriceAmount' => $roomPricePartsBasement['amount'],
                    'roomBasementPriceSuffix' => $roomPricePartsBasement['suffix'],
                    'roomBasementCapacity' => $data['room_basement_capacity'] ?? '',
                    'roomBasementNote' => $data['room_basement_note'] ?? '',
                    'roomBasementGallery' => $data['room_basement_gallery'] ?? '[]',
                    'roomBasementGallerySectionTitle' => $data['room_basement_gallery_section_title'] ?? '',
                    'roomBasementCommonGallery' => $data['room_basement_common_gallery'] ?? '[]',
                    'roomBasementCommonGallerySectionTitle' => $data['room_basement_common_gallery_section_title'] ?? '',
                    'roomBasementBannerImageUrl' => $data['room_basement_banner_image_url'] ?? '',
                    // Wellness Experiences page content
                    'wellnessTitle' => $data['wellness_title'] ?? '',
                    'wellnessDescription' => $data['wellness_description'] ?? '',
                    'wellnessMassageTitle' => $data['wellness_massage_title'] ?? '',
                    'wellnessMassageDescription' => $data['wellness_massage_description'] ?? '',
                    'wellnessYogaTitle' => $data['wellness_yoga_title'] ?? '',
                    'wellnessYogaDescription' => $data['wellness_yoga_description'] ?? ''
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

// Floor plan get handler - must be BEFORE auth_api.php to avoid "Invalid action" error
if ($action === 'get_floorplan') {
    header('Content-Type: application/json');
    try {
        // Check if table exists first
        $tableCheck = $conn->query("SHOW TABLES LIKE 'floorplan_settings'");
        if ($tableCheck->num_rows === 0) {
            // Table doesn't exist, return default values
            $defaultData = [
                'floorplan_title' => 'Common areas',
                'floorplan_subtitle' => 'Basement calm, a welcoming main living level, and bright multifunctional rooms for workshops and cinema.',
                'basement_subtitle' => 'Private floor with a separate entrance.',
                'basement_description' => 'A spacious bedroom with a king-size bed and a small study, a home theater with a fireplace, and a private bathroom featuring a shower and a sauna room.',
                'basement_image_url' => 'assets/plan.jpg',
                'ground_subtitle' => 'Open space with a separate entrance.',
                'ground_description' => 'A large bright hall with a fireplace, a big dining table, a spacious modern kitchen, two rental rooms, a shared bathroom with a bathtub, and a separate room for massage and events.',
                'ground_queen_image' => 'assets/plan.jpg',
                'ground_image_url' => 'assets/plan.jpg',
                'loft_subtitle' => 'Multifunctional spaces & small cinema',
                'loft_description' => 'Bright, adaptable rooms for yoga circles, workshops, and film nights — on the main living level beside the kitchen and hall, with generous windows and blackout curtains when you want the room dark.',
                'loft_image_url' => 'assets/plan.jpg'
            ];
            echo json_encode(['success' => true, 'data' => $defaultData]);
            exit;
        }
        
        $result = $conn->query("SELECT * FROM floorplan_settings WHERE id = 1");
        
        if (!$result) {
            echo json_encode(['success' => false, 'error' => 'Query failed: ' . $conn->error]);
            exit;
        }
        
        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            // Universal: add ground_image_url as alias to ground_queen_image
            if (isset($data['ground_queen_image']) && !isset($data['ground_image_url'])) {
                $data['ground_image_url'] = $data['ground_queen_image'];
            }
            // Log loaded data
            error_log('get_floorplan: Loaded data - ground_description: ' . substr($data['ground_description'] ?? '', 0, 100));
            // Return in underscore format (as expected by loadFloorplanData)
            echo json_encode([
                'success' => true,
                'data' => [
                    'floorplan_title' => $data['floorplan_title'] ?? 'Common areas',
                    'floorplan_subtitle' => $data['floorplan_subtitle'] ?? 'Basement calm, a welcoming main living level, and bright multifunctional rooms for workshops and cinema.',
                    'basement_subtitle' => $data['basement_subtitle'] ?? '',
                    'basement_description' => $data['basement_description'] ?? '',
                    'basement_image_url' => $data['basement_image_url'] ?? '',
                    'ground_subtitle' => $data['ground_subtitle'] ?? '',
                    'ground_description' => $data['ground_description'] ?? '',
                    'ground_queen_image' => $data['ground_queen_image'] ?? '',
                    'ground_image_url' => $data['ground_image_url'] ?? $data['ground_queen_image'] ?? '',
                    'ground_twin_image' => $data['ground_twin_image'] ?? '',
                    'ground_gallery' => $data['ground_gallery'] ?? '[]',
                    'loft_subtitle' => $data['loft_subtitle'] ?? 'Multifunctional spaces & small cinema',
                    'loft_description' => $data['loft_description'] ?? 'Bright, adaptable rooms for yoga circles, workshops, and film nights — on the main living level beside the kitchen and hall, with generous windows and blackout curtains when you want the room dark.',
                    'loft_image_url' => $data['loft_image_url'] ?? '',
                    'basement_gallery' => $data['basement_gallery'] ?? '[]',
                    'ground_gallery' => $data['ground_gallery'] ?? '[]',
                    'loft_gallery' => $data['loft_gallery'] ?? '[]',
                    'basement_gallery' => $data['basement_gallery'] ?? '[]'
                ]
            ]);
        } else {
            // Return default values if no data exists
            $defaultData = [
                'basement_subtitle' => 'Private floor with a separate entrance.',
                'basement_description' => 'A spacious bedroom with a king-size bed and a small study, a home theater with a fireplace, and a private bathroom featuring a shower and a sauna room.',
                'basement_image_url' => 'assets/plan.jpg',
                'ground_subtitle' => 'Open space with a separate entrance.',
                'ground_description' => 'A large bright hall with a fireplace, a big dining table, a spacious modern kitchen, two rental rooms, a shared bathroom with a bathtub, and a separate room for massage and events.',
                'ground_queen_image' => 'assets/plan.jpg',
                'ground_image_url' => 'assets/plan.jpg',
                'loft_subtitle' => 'Multifunctional spaces & small cinema',
                'loft_description' => 'Bright, adaptable rooms for yoga circles, workshops, and film nights — on the main living level beside the kitchen and hall, with generous windows and blackout curtains when you want the room dark.',
                'loft_image_url' => 'assets/plan.jpg'
            ];
            echo json_encode(['success' => true, 'data' => $defaultData]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Floor plan save handler - must be BEFORE auth_api.php to avoid "Invalid action" error
if ($action === 'save_floorplan') {
    // Suppress all output and errors to ensure clean JSON response
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    header('Content-Type: application/json');
    ob_start(); // Start output buffering to prevent any output before JSON
    
    try {
        // Check if columns exist, if not, try to add them
        try {
            $columnsCheck = $conn->query("SHOW COLUMNS FROM floorplan_settings LIKE 'floorplan_title'");
            if ($columnsCheck && $columnsCheck->num_rows === 0) {
                // Try to add the columns
                $conn->query("ALTER TABLE floorplan_settings ADD COLUMN floorplan_title VARCHAR(255) DEFAULT 'Common areas'");
            }
            $columnsCheck = $conn->query("SHOW COLUMNS FROM floorplan_settings LIKE 'floorplan_subtitle'");
            if ($columnsCheck && $columnsCheck->num_rows === 0) {
                // Try to add the columns
                $conn->query("ALTER TABLE floorplan_settings ADD COLUMN floorplan_subtitle TEXT DEFAULT 'Basement calm, a welcoming main living level, and bright multifunctional rooms for workshops and cinema.'");
            }
        } catch (Exception $e) {
            // Silently continue - columns might already exist or will be handled by error handling
            error_log('save_floorplan: Column check error (non-critical): ' . $e->getMessage());
        }
        
        $floorplan_title = $_POST['floorplanTitle'] ?? '';
        $floorplan_subtitle = $_POST['floorplanSubtitle'] ?? '';
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
        
        // Gallery fields (JSON arrays)
        $basement_gallery = $_POST['basementGallery'] ?? '[]';
        $ground_gallery = $_POST['groundGallery'] ?? '[]';
        $loft_gallery = $_POST['loftGallery'] ?? '[]';
        
        // Log received data
        error_log('save_floorplan: Received data - ground_description: ' . substr($ground_description, 0, 100));
        error_log('save_floorplan: Received galleries - basement: ' . substr($basement_gallery, 0, 200) . ', ground: ' . substr($ground_gallery, 0, 200) . ', loft: ' . substr($loft_gallery, 0, 200));
        
        // Check if gallery columns exist, if not, try to add them
        try {
            $columnsCheck = $conn->query("SHOW COLUMNS FROM floorplan_settings LIKE 'basement_gallery'");
            if ($columnsCheck && $columnsCheck->num_rows === 0) {
                $conn->query("ALTER TABLE floorplan_settings ADD COLUMN basement_gallery TEXT DEFAULT NULL");
            }
            $columnsCheck = $conn->query("SHOW COLUMNS FROM floorplan_settings LIKE 'ground_gallery'");
            if ($columnsCheck && $columnsCheck->num_rows === 0) {
                $conn->query("ALTER TABLE floorplan_settings ADD COLUMN ground_gallery TEXT DEFAULT NULL");
            }
            $columnsCheck = $conn->query("SHOW COLUMNS FROM floorplan_settings LIKE 'loft_gallery'");
            if ($columnsCheck && $columnsCheck->num_rows === 0) {
                $conn->query("ALTER TABLE floorplan_settings ADD COLUMN loft_gallery TEXT DEFAULT NULL");
            }
        } catch (Exception $e) {
            error_log('save_floorplan: Column check error (non-critical): ' . $e->getMessage());
        }
        
        $stmt = $conn->prepare("INSERT INTO floorplan_settings (
                               id, floorplan_title, floorplan_subtitle, basement_subtitle, basement_description, basement_image_url,
                               basement_gallery, ground_subtitle, ground_description, ground_queen_image, ground_twin_image,
                               ground_gallery, loft_subtitle, loft_description, loft_image_url, loft_gallery
                               ) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                               ON DUPLICATE KEY UPDATE
                               floorplan_title = ?, floorplan_subtitle = ?, basement_subtitle = ?, basement_description = ?, basement_image_url = ?,
                               basement_gallery = ?, ground_subtitle = ?, ground_description = ?, ground_queen_image = ?, ground_twin_image = ?,
                               ground_gallery = ?, loft_subtitle = ?, loft_description = ?, loft_image_url = ?, loft_gallery = ?");
        
        if (!$stmt) {
            $error = $conn->error;
            error_log('save_floorplan: Prepare failed - ' . $error);
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Database prepare failed: ' . $error]);
            exit;
        }
        
        // Count: INSERT VALUES (15) + UPDATE (15) = 30 parameters
        // VALUES parameters: floorplan_title(1), floorplan_subtitle(2), basement_subtitle(3), basement_description(4), basement_image_url(5),
        //                    basement_gallery(6), ground_subtitle(7), ground_description(8), ground_queen_image(9), ground_twin_image(10),
        //                    ground_gallery(11), loft_subtitle(12), loft_description(13), loft_image_url(14), loft_gallery(15)
        // UPDATE parameters: floorplan_title(16), floorplan_subtitle(17), basement_subtitle(18), basement_description(19), basement_image_url(20),
        //                    basement_gallery(21), ground_subtitle(22), ground_description(23), ground_queen_image(24), ground_twin_image(25),
        //                    ground_gallery(26), loft_subtitle(27), loft_description(28), loft_image_url(29), loft_gallery(30)
        $paramTypes = str_repeat('s', 30); // 30 string parameters
        $result = $stmt->bind_param($paramTypes, 
            $floorplan_title, $floorplan_subtitle, $basement_subtitle, $basement_description, $basement_image_url,
            $basement_gallery, $ground_subtitle, $ground_description, $ground_queen_image, $ground_twin_image,
            $ground_gallery, $loft_subtitle, $loft_description, $loft_image_url, $loft_gallery,
            $floorplan_title, $floorplan_subtitle, $basement_subtitle, $basement_description, $basement_image_url,
            $basement_gallery, $ground_subtitle, $ground_description, $ground_queen_image, $ground_twin_image,
            $ground_gallery, $loft_subtitle, $loft_description, $loft_image_url, $loft_gallery);
        
        if (!$result) {
            $error = $stmt->error ?: 'Unknown bind_param error';
            error_log('save_floorplan: bind_param failed - ' . $error);
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'bind_param failed: ' . $error]);
            $stmt->close();
            exit;
        }
        
        ob_clean(); // Clear any output
        
        // Log gallery values for debugging
        error_log('save_floorplan: About to execute - basement_gallery = ' . substr($basement_gallery, 0, 200));
        error_log('save_floorplan: About to execute - ground_gallery = ' . substr($ground_gallery, 0, 200));
        error_log('save_floorplan: About to execute - loft_gallery = ' . substr($loft_gallery, 0, 200));
        
        if ($stmt->execute()) {
            // Verify data was saved including galleries
            $verify = $conn->query("SELECT ground_description, basement_gallery, ground_gallery, loft_gallery FROM floorplan_settings WHERE id = 1");
            if ($verify && $verify->num_rows > 0) {
                $saved = $verify->fetch_assoc();
                error_log('save_floorplan: Verified saved data - ground_description: ' . substr($saved['ground_description'] ?? '', 0, 100));
                error_log('save_floorplan: Verified basement_gallery: ' . substr($saved['basement_gallery'] ?? '', 0, 200));
                error_log('save_floorplan: Verified ground_gallery: ' . substr($saved['ground_gallery'] ?? '', 0, 200));
                error_log('save_floorplan: Verified loft_gallery: ' . substr($saved['loft_gallery'] ?? '', 0, 200));
            }
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } else {
            $error = $stmt->error ?: $conn->error;
            error_log('save_floorplan: Database error - ' . $error);
            error_log('save_floorplan: SQL state - ' . ($stmt->sqlstate ?? 'unknown'));
            
            // Check if error is about missing columns
            if (strpos($error, 'floorplan_title') !== false || strpos($error, 'floorplan_subtitle') !== false) {
                ob_end_clean();
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false, 
                    'error' => 'Database columns missing. Please run add_floorplan_title_fields.php to add the required columns.',
                    'error_details' => $error
                ]);
            } else {
                ob_end_clean();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $error, 'error_details' => $error]);
            }
        }
        if (isset($stmt)) {
            $stmt->close();
        }
    } catch (Throwable $e) {
        // Catch any fatal errors or exceptions
        error_log('save_floorplan: Fatal error - ' . $e->getMessage());
        error_log('save_floorplan: Stack trace - ' . $e->getTraceAsString());
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'error' => 'Fatal error: ' . $e->getMessage(),
            'error_type' => get_class($e),
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine()
        ]);
    }
    ob_end_flush();
    exit;
}

if ($action === 'get_guest_reviews') {
    header('Content-Type: application/json; charset=utf-8');
    $defaultsV = [
        ['name' => 'Emily R.', 'text' => 'A wonderful stay. The home is even better than the photos, surrounded by trees and so peaceful. We would happily return.', 'rating' => 5],
        ['name' => 'James K.', 'text' => 'Spotless, spacious, and thoughtfully equipped. The hosts were warm and the location is perfect for exploring Nelson.', 'rating' => 5],
        ['name' => 'Olivia T.', 'text' => 'Loved the quiet setting and the comfortable beds. Mornings on the deck with coffee were a highlight.', 'rating' => 5],
        ['name' => 'Michael P.', 'text' => 'Great for a group retreat. Kitchen and common areas are ideal for cooking together. Minor wish: faster Wi‑Fi, but that is a small point in such a restful place.', 'rating' => 4],
        ['name' => 'Anna L.', 'text' => 'Truly a place to slow down. Every detail made us feel welcome from check‑in to departure.', 'rating' => 5],
    ];
    $defaultsA = [
        ['name' => 'Sofia M.', 'text' => 'The house felt like a private lodge — cozy, light‑filled, and every room had character. We did not want to leave.', 'rating' => 5],
        ['name' => 'David C.', 'text' => 'Immaculate, relaxed vibe, and easy communication. Perfect base for ski days and evenings by the fire.', 'rating' => 5],
        ['name' => 'Rachel B.', 'text' => 'A gem in the Kootenays. Forest walks nearby and a comfortable, stylish interior.', 'rating' => 5],
        ['name' => 'Tom W.', 'text' => 'We booked the whole place for a long weekend. Everyone had their own space and the shared areas brought us together.', 'rating' => 5],
        ['name' => 'Nina F.', 'text' => 'Hospitality was top‑tier, and the setting is magical. Already recommending to friends.', 'rating' => 5],
    ];
    $pad = function (array $list) {
        $a = $list;
        for ($i = 0; $i < 5; $i++) {
            if (!isset($a[$i]) || !is_array($a[$i])) {
                $a[$i] = ['name' => '', 'text' => '', 'rating' => 5];
            }
            $a[$i]['name'] = (string)($a[$i]['name'] ?? '');
            $a[$i]['text'] = (string)($a[$i]['text'] ?? '');
            $a[$i]['rating'] = max(1, min(5, (int)($a[$i]['rating'] ?? 5)));
        }
        return array_slice($a, 0, 5);
    };
    $title = 'Guest reviews';
    $sub = 'What recent guests have shared on Vrbo and Airbnb.';
    $vr = $pad($defaultsV);
    $ar = $pad($defaultsA);
    $tc = $conn->query("SHOW TABLES LIKE 'guest_reviews_settings'");
    if ($tc && $tc->num_rows > 0) {
        $row = $conn->query("SELECT * FROM guest_reviews_settings WHERE id = 1");
        if ($row && $row->num_rows > 0) {
            $d = $row->fetch_assoc();
            if (!empty(trim((string)($d['section_title'] ?? '')))) {
                $title = (string) $d['section_title'];
            }
            if (array_key_exists('section_subtitle', $d) && $d['section_subtitle'] !== null) {
                $sub = (string) $d['section_subtitle'];
            }
            $jV = json_decode((string)($d['vrbo_reviews_json'] ?? '[]'), true);
            $jA = json_decode((string)($d['airbnb_reviews_json'] ?? '[]'), true);
            if (is_array($jV)) {
                $vr = $pad($jV);
            }
            if (is_array($jA)) {
                $ar = $pad($jA);
            }
        }
    }
    echo json_encode([
        'success' => true,
        'data' => [
            'section_title' => $title,
            'section_subtitle' => $sub,
            'vrbo' => $vr,
            'airbnb' => $ar,
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'save_guest_reviews') {
    header('Content-Type: application/json; charset=utf-8');
    $create = "CREATE TABLE IF NOT EXISTS `guest_reviews_settings` (
      `id` TINYINT UNSIGNED NOT NULL PRIMARY KEY,
      `section_title` VARCHAR(500) NOT NULL DEFAULT 'Guest reviews',
      `section_subtitle` TEXT NULL,
      `vrbo_reviews_json` LONGTEXT NULL,
      `airbnb_reviews_json` LONGTEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if (!$conn->query($create)) {
        echo json_encode(['success' => false, 'error' => 'Could not create guest_reviews_settings: ' . $conn->error]);
        exit;
    }

    $title = trim((string)($_POST['section_title'] ?? 'Guest reviews'));
    if ($title === '') {
        $title = 'Guest reviews';
    }
    $sub = (string)($_POST['section_subtitle'] ?? '');
    $jVr = (string)($_POST['vrbo_reviews'] ?? '[]');
    $jA = (string)($_POST['airbnb_reviews'] ?? '[]');
    json_decode($jVr, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'error' => 'Invalid vrbo_reviews JSON']);
        exit;
    }
    json_decode($jA, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'error' => 'Invalid airbnb_reviews JSON']);
        exit;
    }

    $sql = "INSERT INTO `guest_reviews_settings` (`id`, `section_title`, `section_subtitle`, `vrbo_reviews_json`, `airbnb_reviews_json`) VALUES (1, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE `section_title` = VALUES(`section_title`), `section_subtitle` = VALUES(`section_subtitle`), `vrbo_reviews_json` = VALUES(`vrbo_reviews_json`), `airbnb_reviews_json` = VALUES(`airbnb_reviews_json`)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => $conn->error]);
        exit;
    }
    $stmt->bind_param('ssss', $title, $sub, $jVr, $jA);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error ?: 'Save failed']);
    }
    $stmt->close();
    exit;
}

// Content save handler - must be BEFORE auth_api.php to avoid "Invalid action" error
if ($action === 'save_content') {
    // Set error handler to catch all errors and convert to JSON
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        error_log("PHP Error in save_content: [$errno] $errstr in $errfile on line $errline");
        return false; // Let PHP handle it normally, but we'll catch it
    });
    
    header('Content-Type: application/json');
    ob_start(); // Start output buffering to prevent any output before JSON
    
    try {
    btb_sync_room_price_legacy_fields_from_post($_POST);
    
    // Note: rooms_title and rooms_subtitle columns must be added manually using add_rooms_title_fields.php
    // We don't add them automatically here to avoid "Row size too large" errors
    
    // Build dynamic UPDATE query based on provided fields
    $fields = [];
    $values = [];
    $types = '';
    
    $exploreSettingsTableExists = false;
    $esTblChk = $conn->query("SHOW TABLES LIKE 'explore_settings'");
    if ($esTblChk && $esTblChk->num_rows > 0) {
        $exploreSettingsTableExists = true;
    }
    $exploreSettingsSaved = false;
    $esKeys = [
        'explore_hero_title',
        'explore_hero_subtitle',
        'explore_hero_image_url',
        'explore_accommodation_title',
        'explore_accommodation_description',
        'explore_accommodation_image_url',
    ];
    $exploreSettingsColNames = [];
    if ($exploreSettingsTableExists) {
        $esColChk = $conn->query('SHOW COLUMNS FROM explore_settings');
        if ($esColChk) {
            while ($cRow = $esColChk->fetch_assoc()) {
                if (!empty($cRow['Field'])) {
                    $exploreSettingsColNames[$cRow['Field']] = true;
                }
            }
        }
    }
    if ($exploreSettingsTableExists) {
        $esToUpdate = [];
        foreach ($esKeys as $k) {
            if (!array_key_exists($k, $_POST)) {
                continue;
            }
            if (empty($exploreSettingsColNames[$k])) {
                error_log("save_content: Skipping explore_settings — column not present: $k (run create_explore_settings_table.php to upgrade table)");
                continue;
            }
            $esToUpdate[$k] = $_POST[$k];
        }
        if (!empty($esToUpdate)) {
            $esRowChk = $conn->query("SELECT id FROM explore_settings WHERE id = 1");
            if (!$esRowChk || $esRowChk->num_rows === 0) {
                $conn->query("INSERT INTO explore_settings (id) VALUES (1)");
            }
            $esSets = [];
            $esVals = [];
            $esTypes = '';
            foreach ($esToUpdate as $k => $v) {
                $esSets[] = "$k = ?";
                $esVals[] = $v;
                $esTypes .= 's';
            }
            $esSql = "UPDATE explore_settings SET " . implode(', ', $esSets) . " WHERE id = 1";
            $esStmt = $conn->prepare($esSql);
            if ($esStmt) {
                $esStmt->bind_param($esTypes, ...$esVals);
                if ($esStmt->execute()) {
                    $exploreSettingsSaved = true;
                    error_log('save_content: Saved explore fields to explore_settings: ' . implode(', ', array_keys($esToUpdate)));
                } else {
                    error_log('save_content: explore_settings update failed: ' . $esStmt->error);
                }
                $esStmt->close();
            }
        }
    }
    // If explore_settings is missing, or a column exists only in content_settings (older explore_settings), save there.
    foreach ($esKeys as $ef) {
        if (!isset($_POST[$ef])) {
            continue;
        }
        if ($exploreSettingsTableExists && !empty($exploreSettingsColNames[$ef])) {
            continue;
        }
        $cEx = $conn->query("SHOW COLUMNS FROM content_settings LIKE '" . $conn->real_escape_string($ef) . "'");
        if ($cEx && $cEx->num_rows > 0) {
            $fields[] = "`$ef` = ?";
            $values[] = $_POST[$ef];
            $types .= 's';
            error_log("save_content: Explore field $ef → content_settings (not in explore_settings or table missing column)");
        }
    }

    $exploreParksTableExists = false;
    $epTblChk0 = $conn->query("SHOW TABLES LIKE 'explore_parks_settings'");
    if ($epTblChk0 && $epTblChk0->num_rows > 0) {
        $exploreParksTableExists = true;
    }
    $exploreParksSaved = false;
    if ($exploreParksTableExists) {
        $epPostMap = [
            'about_parks_title' => 'title',
            'about_parks_intro' => 'intro',
            'about_parks_list' => 'parks_list',
            'about_parks_map_lat' => 'map_lat',
            'about_parks_map_lng' => 'map_lng',
            'about_parks_hero_image_url' => 'hero_image_url',
            'about_parks_gallery' => 'gallery',
            'about_parks_cards' => 'parks_cards',
        ];
        $epToUpdate = [];
        foreach ($epPostMap as $postKey => $col) {
            if (array_key_exists($postKey, $_POST)) {
                $epToUpdate[$col] = $_POST[$postKey];
            }
        }
        if (!empty($epToUpdate)) {
            $epRowChk = $conn->query("SELECT id FROM explore_parks_settings WHERE id = 1");
            if (!$epRowChk || $epRowChk->num_rows === 0) {
                $conn->query("INSERT INTO explore_parks_settings (id) VALUES (1)");
            }
            $epSets = [];
            $epVals = [];
            $epTypes = '';
            foreach ($epToUpdate as $col => $v) {
                $epSets[] = "`$col` = ?";
                $epVals[] = $v;
                $epTypes .= 's';
            }
            $epSql = "UPDATE explore_parks_settings SET " . implode(', ', $epSets) . " WHERE id = 1";
            $epStmt = $conn->prepare($epSql);
            if ($epStmt) {
                $epStmt->bind_param($epTypes, ...$epVals);
                if ($epStmt->execute()) {
                    $exploreParksSaved = true;
                    error_log('save_content: Saved explore_parks_settings: ' . implode(', ', array_keys($epToUpdate)));
                } else {
                    error_log('save_content: explore_parks_settings update failed: ' . $epStmt->error);
                }
                $epStmt->close();
            }
        }
    }

    $exploreCommunityExtraTableExists = false;
    $ecTblChkCommunity = $conn->query("SHOW TABLES LIKE 'explore_community_extra'");
    if ($ecTblChkCommunity && $ecTblChkCommunity->num_rows > 0) {
        $exploreCommunityExtraTableExists = true;
    }
    $exploreCommunityExtraSaved = false;
    if ($exploreCommunityExtraTableExists) {
        $ecExtraKeys = [
            'about_nelson_image_url',
            'about_kaslo_title',
            'about_kaslo_distance',
            'about_kaslo_description',
            'about_kaslo_image_url',
            'about_kaslo_gallery',
            'about_crawford_title',
            'about_crawford_distance',
            'about_crawford_description',
            'about_crawford_image_url',
            'about_crawford_gallery',
            'about_museum_title',
            'about_museum_distance',
            'about_museum_description',
            'about_museum_image_url',
            'about_museum_gallery',
            'explore_communities_h2',
            'explore_culture_h2',
            'explore_parks_h2',
            'explore_activities_h2',
            'explore_communities_intro',
            'explore_culture_intro',
            'explore_activities_intro',
            'explore_communities_cards',
            'explore_culture_cards',
            'explore_activities_cards',
        ];
        $ecToUpdate = [];
        foreach ($ecExtraKeys as $k) {
            if (array_key_exists($k, $_POST)) {
                $ecToUpdate[$k] = $_POST[$k];
            }
        }
        if (!empty($ecToUpdate)) {
            $ecRowChk = $conn->query('SELECT id FROM explore_community_extra WHERE id = 1');
            if (!$ecRowChk || $ecRowChk->num_rows === 0) {
                $conn->query('INSERT IGNORE INTO explore_community_extra (id) VALUES (1)');
            }
            $ecSets = [];
            $ecVals = [];
            $ecTypes = '';
            foreach ($ecToUpdate as $col => $v) {
                $ecSets[] = "`$col` = ?";
                $ecVals[] = $v;
                $ecTypes .= 's';
            }
            $ecSql = 'UPDATE explore_community_extra SET ' . implode(', ', $ecSets) . ' WHERE id = 1';
            $ecStmt = $conn->prepare($ecSql);
            if ($ecStmt) {
                $ecStmt->bind_param($ecTypes, ...$ecVals);
                if ($ecStmt->execute()) {
                    $exploreCommunityExtraSaved = true;
                    error_log('save_content: Saved explore_community_extra: ' . implode(', ', array_keys($ecToUpdate)));
                } else {
                    error_log('save_content: explore_community_extra update failed: ' . $ecStmt->error);
                }
                $ecStmt->close();
            } else {
                error_log('save_content: explore_community_extra prepare failed: ' . ($conn->error ?: 'unknown'));
            }
        }
    }

    // If section card JSON (Explore Communities/Culture/Activities) did not persist to explore_community_extra,
    // also write to content_settings when those columns exist — otherwise the admin shows "Saved" from other
    // fields (about_*) but card text is lost on reload.
    $exploreCardJsonKeys = ['explore_communities_cards', 'explore_culture_cards', 'explore_activities_cards'];
    foreach ($exploreCardJsonKeys as $ek) {
        if (!array_key_exists($ek, $_POST)) {
            continue;
        }
        if (!empty($exploreCommunityExtraSaved)) {
            continue;
        }
        $cEx = $conn->query("SHOW COLUMNS FROM content_settings LIKE '" . $conn->real_escape_string($ek) . "'");
        if ($cEx && $cEx->num_rows > 0) {
            $fields[] = "`$ek` = ?";
            $values[] = $_POST[$ek];
            $types .= 's';
            error_log("save_content: Explore card JSON fallback → content_settings.$ek");
        } else {
            error_log("save_content: Explore card JSON not saved (no working explore_community_extra save and no content_settings.$ek column). Run create_explore_community_extra_table.php on the server.");
        }
    }
    
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
    // Save rooms_title and rooms_subtitle to rooms_settings table if it exists
    // This is done separately and doesn't require other fields to be present
    $roomsTitleToSave = isset($_POST['rooms_title']) ? $_POST['rooms_title'] : null;
    $roomsSubtitleToSave = isset($_POST['rooms_subtitle']) ? $_POST['rooms_subtitle'] : null;
    $roomsSaved = false;
    $roomsSubtitleColumnMissing = false;
    
    if ($roomsTitleToSave !== null || $roomsSubtitleToSave !== null) {
        $roomsTableCheck = $conn->query("SHOW TABLES LIKE 'rooms_settings'");
        if ($roomsTableCheck && $roomsTableCheck->num_rows > 0) {
            // Use rooms_settings table
            $roomsTitle = $roomsTitleToSave ?? 'Choose your room';
            $roomsSubtitle = $roomsSubtitleToSave ?? '';
            
            $stmtRooms = $conn->prepare("INSERT INTO rooms_settings (id, rooms_title, rooms_subtitle) VALUES (1, ?, ?) ON DUPLICATE KEY UPDATE rooms_title = ?, rooms_subtitle = ?");
            if ($stmtRooms) {
                $stmtRooms->bind_param("ssss", $roomsTitle, $roomsSubtitle, $roomsTitle, $roomsSubtitle);
                if ($stmtRooms->execute()) {
                    $roomsSaved = true;
                    error_log('save_content: Saved rooms_title and rooms_subtitle to rooms_settings table');
                } else {
                    error_log('save_content: Failed to save to rooms_settings: ' . $stmtRooms->error);
                }
                $stmtRooms->close();
            }
        } else {
            // Fall back to content_settings (for backward compatibility)
            if ($roomsTitleToSave !== null) {
                $columnsCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE 'rooms_title'");
                if ($columnsCheck && $columnsCheck->num_rows > 0) {
                    $fields[] = 'rooms_title = ?';
                    $values[] = $roomsTitleToSave;
                    $types .= 's';
                    error_log('save_content: rooms_title = ' . substr($roomsTitleToSave, 0, 100));
                } else {
                    error_log('save_content: rooms_title column does not exist in content_settings. Please run create_rooms_settings_table.php');
                }
            }
            if ($roomsSubtitleToSave !== null) {
                $columnsCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE 'rooms_subtitle'");
                if ($columnsCheck && $columnsCheck->num_rows > 0) {
                    $fields[] = 'rooms_subtitle = ?';
                    $values[] = $roomsSubtitleToSave;
                    $types .= 's';
                    error_log('save_content: rooms_subtitle = ' . substr($roomsSubtitleToSave, 0, 100) . ' (length: ' . strlen($roomsSubtitleToSave) . ')');
                } else {
                    error_log('save_content: rooms_subtitle column does not exist in content_settings. Please run create_rooms_settings_table.php');
                    $roomsSubtitleColumnMissing = true;
                }
            }
        }
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
        'retreat_forest_list_label', 'retreat_forest_list_items', 'retreat_forest_gallery', 'retreat_indoor_title',
        'retreat_indoor_description', 'retreat_indoor_additional', 'retreat_indoor_gallery', 'retreat_theatre_title',
        'retreat_theatre_description', 'retreat_theatre_gallery', 'retreat_contact_title', 'retreat_contact_text',
        'retreat_organizer_title',
        'retreat_workshops_title', 'retreat_workshops_intro',
        'retreat_workshops_list', 'retreat_workshops_conclusion', 'retreat_collaboration_title',
        'retreat_collaboration_intro', 'retreat_collaboration_list', 'retreat_collaboration_conclusion'
    ];
    
    // Special page content fields
    $specialFields = [
        'special_hero_title', 'special_hero_subtitle',
        'special_pools_title', 'special_pools_description_1', 'special_pools_description_2',
        'special_dining_title', 'special_dining_description_1', 'special_dining_description_2',
        'special_extra_title', 'special_extra_description_1', 'special_extra_description_2',
        'special_offer_title', 'special_offer_main_text', 'special_offer_description'
    ];
    
    // Massage page content fields
    $massageFields = [
        'massage_hero_title', 'massage_hero_image_url', 'massage_intro',
        'massage_relaxing_title', 'massage_relaxing_description',
        'massage_deep_tissue_title', 'massage_deep_tissue_description',
        'massage_reiki_title', 'massage_reiki_description',
        'massage_sauna_title', 'massage_sauna_description',
        'massage_booking_title', 'massage_booking_intro',
        'massage_pricing_relaxing', 'massage_pricing_deep_tissue', 'massage_pricing_reiki', 'massage_pricing_sauna',
        'mini_hotel_title', 'mini_hotel_description',
    ];
    
    // Room Second floor page content fields
    $roomSecondFields = [
        'room_second_title', 'room_second_subtitle', 'room_second_description',
        'room_second_price', 'room_second_price_prefix', 'room_second_price_amount', 'room_second_price_suffix',
        'room_second_capacity', 'room_second_note',
        'room_second_gallery', // JSON array of image URLs
        'room_second_gallery_section_title',
        'room_second_common_gallery',
        'room_second_common_gallery_section_title'
    ];
    
    // Room Ground Twin beds page content fields
    $roomGroundTwinFields = [
        'room_ground_twin_title', 'room_ground_twin_subtitle', 'room_ground_twin_description',
        'room_ground_twin_price', 'room_ground_twin_price_prefix', 'room_ground_twin_price_amount', 'room_ground_twin_price_suffix',
        'room_ground_twin_capacity', 'room_ground_twin_note',
        'room_ground_twin_gallery', // JSON array of image URLs
        'room_ground_twin_gallery_section_title',
        'room_ground_twin_common_gallery',
        'room_ground_twin_common_gallery_section_title'
    ];
    
    // Room Ground Queen bed page content fields
    $roomGroundQueenFields = [
        'room_ground_queen_title', 'room_ground_queen_subtitle', 'room_ground_queen_description',
        'room_ground_queen_price', 'room_ground_queen_price_prefix', 'room_ground_queen_price_amount', 'room_ground_queen_price_suffix',
        'room_ground_queen_capacity', 'room_ground_queen_note',
        'room_ground_queen_gallery', // JSON array of image URLs
        'room_ground_queen_gallery_section_title',
        'room_ground_queen_common_gallery',
        'room_ground_queen_common_gallery_section_title'
    ];
    
    // Room Basement Queen bed page content fields
    $roomBasementFields = [
        'room_basement_title', 'room_basement_subtitle', 'room_basement_description',
        'room_basement_price', 'room_basement_price_prefix', 'room_basement_price_amount', 'room_basement_price_suffix',
        'room_basement_capacity', 'room_basement_note',
        'room_basement_gallery', // JSON array of image URLs
        'room_basement_gallery_section_title',
        'room_basement_common_gallery',
        'room_basement_common_gallery_section_title',
        'about_procter_gallery', // JSON array of image URLs
        'about_halcyon_gallery', // JSON array of image URLs
        'about_whitewater_gallery', // JSON array of image URLs
        'about_nelson_gallery' // JSON array of image URLs
    ];
    
    // Wellness Experiences page content fields
    $wellnessFields = [
        'wellness_title', 'wellness_description',
        'wellness_massage_title', 'wellness_massage_description',
        'wellness_yoga_title', 'wellness_yoga_description'
    ];
    
    // About us page content fields (explore_hero_* live in explore_settings when that table exists)
    $aboutFields = [
        'about_hero_title', 'about_hero_subtitle',
        'about_idea_title', 'about_idea_intro', 'about_idea_paragraph_1', 'about_idea_paragraph_2',
        'about_idea_paragraph_3', 'about_idea_signature',
        'about_location_title', 'about_location_paragraph_1', 'about_location_paragraph_2',
        'about_location_paragraph_3', 'about_location_paragraph_4', 'about_location_coordinates',
        'about_location_deer_warning',
        'about_contact_form_title',
        'about_contact_form_description',
        'about_attractions_title', 'about_attractions_lead',
        'about_procter_title', 'about_procter_distance', 'about_procter_description',
        'about_halcyon_title', 'about_halcyon_distance', 'about_halcyon_description',
        'about_whitewater_title', 'about_whitewater_distance', 'about_whitewater_description',
        'about_nelson_title', 'about_nelson_distance', 'about_nelson_description',
        'about_parks_title', 'about_parks_intro', 'about_parks_list'
    ];
    
    // Homepage room cards content fields
    $roomCardFields = [
        'room_basement_card_title', 'room_basement_card_description', 'room_basement_card_price',
        'room_basement_card_image_url',
        'room_ground_queen_card_title', 'room_ground_queen_card_description', 'room_ground_queen_card_price',
        'room_ground_queen_card_image_url',
        'room_ground_twin_card_title', 'room_ground_twin_card_description', 'room_ground_twin_card_price',
        'room_ground_twin_card_image_url',
        'room_second_card_title', 'room_second_card_description', 'room_second_card_price',
        'room_second_card_image_url'
    ];
    
    // Mini-hotel fields that should be saved to room_cards_settings
    $miniHotelFields = [
        'mini_hotel_title',
        'mini_hotel_description',
        'mini_hotel_image_url',
    ];
    
    // Save room card fields and mini-hotel fields to room_cards_settings table if it exists
    // Otherwise, try to save to content_settings (for backward compatibility)
    $roomCardFieldsToSave = [];
    foreach ($roomCardFields as $field) {
        if (isset($_POST[$field])) {
            $roomCardFieldsToSave[$field] = $_POST[$field];
            error_log("save_content: Found room card field in POST: $field = " . substr($_POST[$field], 0, 50));
        }
    }
    
    // Add mini-hotel fields to room_cards_settings save
    foreach ($miniHotelFields as $field) {
        if (isset($_POST[$field])) {
            $roomCardFieldsToSave[$field] = $_POST[$field];
            error_log("save_content: Found mini-hotel field in POST: $field = " . substr($_POST[$field], 0, 50));
        }
    }
    
    $roomCardsSaved = false;
    if (!empty($roomCardFieldsToSave)) {
        error_log('save_content: Processing ' . count($roomCardFieldsToSave) . ' room card fields');
        $roomCardsTableCheck = $conn->query("SHOW TABLES LIKE 'room_cards_settings'");
        if ($roomCardsTableCheck && $roomCardsTableCheck->num_rows > 0) {
            error_log('save_content: room_cards_settings table exists, saving there');
            // Ensure CMS row exists — otherwise UPDATE matches 0 rows, site keeps reading empty values from room_cards
            $rcIdRow = $conn->query("SELECT id FROM room_cards_settings WHERE id = 1");
            if ($rcIdRow && $rcIdRow->num_rows === 0) {
                if ($conn->query("INSERT INTO room_cards_settings (id) VALUES (1)")) {
                    error_log('save_content: Created missing room_cards_settings row id=1');
                } else {
                    error_log('save_content: Could not insert room_cards_settings id=1: ' . $conn->error);
                }
            }
            // Use room_cards_settings table
            $updateFields = [];
            $updateValues = [];
            $updateTypes = '';
            
            $hasRoomMiniDescCol = function_exists('dbTableHasColumn') && dbTableHasColumn($conn, 'room_cards_settings', 'mini_hotel_description');
            foreach ($roomCardFieldsToSave as $field => $value) {
                if ($field === 'mini_hotel_description' && !$hasRoomMiniDescCol) {
                    error_log('save_content: Skipping mini_hotel_description — column missing in room_cards_settings. Run add_mini_hotel_description_column.php once.');
                    continue;
                }
                $updateFields[] = "$field = ?";
                $updateValues[] = $value;
                $updateTypes .= 's';
                error_log("save_content: Added room card field to save in room_cards_settings: $field (value length: " . strlen($value) . ")");
            }
            
            if (empty($updateFields)) {
                error_log('save_content: No room_cards_settings columns to update after filtering (optional column missing).');
            } else {
            $sql = "UPDATE room_cards_settings SET " . implode(', ', $updateFields) . " WHERE id = 1";
            error_log("save_content: Executing SQL: $sql");
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($updateTypes, ...$updateValues);
                if ($stmt->execute()) {
                    $ar = $stmt->affected_rows;
                    if ($ar === 0) {
                        error_log('save_content: room_cards_settings UPDATE returned affected_rows=0 (values may be unchanged, or no matching row; row id=1 was ensured above)');
                    }
                    $roomCardsSaved = true;
                    error_log('save_content: ✓ Successfully saved room card fields to room_cards_settings table');
                } else {
                    error_log('save_content: ✗ Failed to save to room_cards_settings: ' . $stmt->error);
                }
                $stmt->close();
            } else {
                error_log('save_content: ✗ Failed to prepare statement for room_cards_settings: ' . $conn->error);
            }
            }
        } else {
            error_log('save_content: room_cards_settings table does not exist, falling back to content_settings');
            // Fall back to content_settings (for backward compatibility)
            foreach ($roomCardFieldsToSave as $field => $fieldValue) {
                error_log("save_content: Processing room card field '$field' with value: " . substr($fieldValue, 0, 100));
                
                // Check if column exists - don't add automatically to avoid "Row size too large" errors
                $columnCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE '$field'");
                if ($columnCheck && $columnCheck->num_rows > 0) {
                    $fields[] = "$field = ?";
                    $values[] = $fieldValue;
                    $types .= 's';
                    error_log("save_content: Added room card field to save in content_settings: $field (value length: " . strlen($fieldValue) . ")");
                } else {
                    error_log("save_content: Column '$field' does not exist in content_settings. Skipping update to avoid 'Row size too large' error.");
                }
            }
        }
    } else {
        error_log('save_content: No room card fields found in POST data');
    }
    
    // Track which room card fields to skip in content_settings loops — only if they were written to room_cards_settings
    // Previously we excluded even when room_cards UPDATE failed, so the site (reading room_cards first) showed empty until a later save.
    $roomCardFieldsSaved = [];
    $roomCardsTableExists = false;
    $roomCardsTableCheck = $conn->query("SHOW TABLES LIKE 'room_cards_settings'");
    if ($roomCardsTableCheck && $roomCardsTableCheck->num_rows > 0) {
        $roomCardsTableExists = true;
        if (!empty($roomCardFieldsToSave) && $roomCardsSaved) {
            $roomCardFieldsSaved = array_keys($roomCardFieldsToSave);
            error_log('save_content: Excluding room card/mini-hotel fields from content_settings (stored in room_cards_settings): ' . implode(', ', $roomCardFieldsSaved));
        } elseif (!empty($roomCardFieldsToSave) && !$roomCardsSaved) {
            error_log('save_content: room_cards_settings save did not complete — mirroring to content_settings where columns exist (site can read from either table).');
            foreach ($roomCardFieldsToSave as $field => $fieldValue) {
                $columnCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE '" . $conn->real_escape_string($field) . "'");
                if ($columnCheck && $columnCheck->num_rows > 0) {
                    $fields[] = "$field = ?";
                    $values[] = $fieldValue;
                    $types .= 's';
                    error_log("save_content: Fallback: room card field → content_settings: $field");
                }
            }
        }
    }
    
    foreach ($retreatFields as $field) {
        // Skip room card fields that were already saved to room_cards_settings
        if (in_array($field, $roomCardFieldsSaved)) {
            continue;
        }
        
        if (isset($_POST[$field])) {
            // Log gallery fields specifically
            if (strpos($field, '_gallery') !== false) {
                error_log("Processing gallery field: $field = " . $_POST[$field]);
            }
            // Check if column exists - don't add automatically to avoid "Row size too large" errors
            $columnCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE '$field'");
            if ($columnCheck->num_rows === 0) {
                // Column doesn't exist - skip this field to avoid "Row size too large" error
                error_log("save_content: Column '$field' does not exist. Skipping update to avoid 'Row size too large' error.");
                continue;
            }
            
            // Protect full texts from being overwritten by shortened versions
            // Get current value from database
            $currentResult = $conn->query("SELECT $field FROM content_settings WHERE id = 1");
            $currentRow = $currentResult->fetch_assoc();
            $currentValue = $currentRow[$field] ?? '';
            $newValue = $_POST[$field];
            
            // Skip text length check for gallery fields (they are JSON arrays)
            if (strpos($field, '_gallery') !== false) {
                // For gallery fields, always save the new value
                $fields[] = "$field = ?";
                $values[] = $newValue;
                $types .= 's';
                error_log("Added gallery field to save: $field");
                continue;
            }
            
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
    
    // Check if special_settings table exists
    $specialTableCheck = $conn->query("SHOW TABLES LIKE 'special_settings'");
    $specialTableExists = $specialTableCheck && $specialTableCheck->num_rows > 0;
    
    $specialFieldsSaved = [];
    
    // Save special fields to special_settings if table exists
    if ($specialTableExists) {
        $specialFieldsToSave = [];
        foreach ($specialFields as $field) {
            if (isset($_POST[$field])) {
                $specialFieldsToSave[$field] = $_POST[$field];
            }
        }
        
        if (!empty($specialFieldsToSave)) {
            // Build UPDATE query for special_settings
            $specialUpdateFields = [];
            $specialUpdateValues = [];
            $specialUpdateTypes = '';
            
            foreach ($specialFieldsToSave as $field => $value) {
                // Check if column exists in special_settings
                $columnCheck = $conn->query("SHOW COLUMNS FROM special_settings LIKE '$field'");
                if ($columnCheck && $columnCheck->num_rows > 0) {
                    $specialUpdateFields[] = "$field = ?";
                    $specialUpdateValues[] = $value;
                    $specialUpdateTypes .= 's';
                    $specialFieldsSaved[] = $field;
                }
            }
            
            if (!empty($specialUpdateFields)) {
                // Ensure record exists
                $checkResult = $conn->query("SELECT id FROM special_settings WHERE id = 1");
                if ($checkResult->num_rows === 0) {
                    $conn->query("INSERT INTO special_settings (id) VALUES (1)");
                }
                
                $specialUpdateSql = "UPDATE special_settings SET " . implode(', ', $specialUpdateFields) . " WHERE id = 1";
                $specialUpdateStmt = $conn->prepare($specialUpdateSql);
                if ($specialUpdateStmt) {
                    $specialUpdateStmt->bind_param($specialUpdateTypes, ...$specialUpdateValues);
                    if ($specialUpdateStmt->execute()) {
                        error_log("save_content: Successfully saved special fields to special_settings: " . implode(', ', $specialFieldsSaved));
                    } else {
                        error_log("save_content: Failed to save special fields to special_settings: " . $specialUpdateStmt->error);
                    }
                    $specialUpdateStmt->close();
                }
            }
        }
    }
    
    foreach ($specialFields as $field) {
        // Skip room card fields that were already saved to room_cards_settings
        if (in_array($field, $roomCardFieldsSaved)) {
            continue;
        }
        
        // Skip special fields that were already saved to special_settings
        if (in_array($field, $specialFieldsSaved)) {
            error_log("save_content: Skipping special field '$field' - already saved to special_settings");
            continue;
        }
        
        if (isset($_POST[$field])) {
            // Check if column exists - don't add automatically to avoid "Row size too large" errors
            $columnCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE '$field'");
            if ($columnCheck->num_rows > 0) {
                $fields[] = "$field = ?";
                $values[] = $_POST[$field];
                $types .= 's';
            } else {
                error_log("save_content: Column '$field' does not exist. Skipping update to avoid 'Row size too large' error.");
            }
        }
    }
    
    // Check if massage_settings table exists
    $massageTableCheck = $conn->query("SHOW TABLES LIKE 'massage_settings'");
    $massageTableExists = $massageTableCheck && $massageTableCheck->num_rows > 0;
    
    // Massage image fields that should be saved to massage_settings if table exists
    $massageImageFields = ['massage_hero_image_url', 'massage_relaxing_image_url', 'massage_deep_tissue_image_url', 'massage_reiki_image_url', 'massage_sauna_image_url'];
    $massageImageFieldsSaved = [];
    
    // Save massage image fields to massage_settings if table exists
    if ($massageTableExists && isset($_POST['massage_hero_image_url'])) {
        $massageImageFieldsToSave = [];
        foreach ($massageImageFields as $field) {
            if (isset($_POST[$field])) {
                $massageImageFieldsToSave[$field] = $_POST[$field];
            }
        }
        
        if (!empty($massageImageFieldsToSave)) {
            // Build UPDATE query for massage_settings
            $massageUpdateFields = [];
            $massageUpdateValues = [];
            $massageUpdateTypes = '';
            
            foreach ($massageImageFieldsToSave as $field => $value) {
                // Check if column exists in massage_settings
                $columnCheck = $conn->query("SHOW COLUMNS FROM massage_settings LIKE '$field'");
                if ($columnCheck && $columnCheck->num_rows > 0) {
                    $massageUpdateFields[] = "$field = ?";
                    $massageUpdateValues[] = $value;
                    $massageUpdateTypes .= 's';
                    $massageImageFieldsSaved[] = $field;
                }
            }
            
            if (!empty($massageUpdateFields)) {
                $massageUpdateSql = "UPDATE massage_settings SET " . implode(', ', $massageUpdateFields) . " WHERE id = 1";
                $massageUpdateStmt = $conn->prepare($massageUpdateSql);
                if ($massageUpdateStmt) {
                    $massageUpdateStmt->bind_param($massageUpdateTypes, ...$massageUpdateValues);
                    if ($massageUpdateStmt->execute()) {
                        error_log("save_content: Successfully saved massage image fields to massage_settings: " . implode(', ', $massageImageFieldsSaved));
                    } else {
                        error_log("save_content: Failed to save massage image fields to massage_settings: " . $massageUpdateStmt->error);
                    }
                    $massageUpdateStmt->close();
                }
            }
        }
    }
    
    foreach ($massageFields as $field) {
        // Skip room card fields and mini-hotel fields that were already saved to room_cards_settings
        if (in_array($field, $roomCardFieldsSaved)) {
            error_log("save_content: Skipping massage field '$field' - already saved to room_cards_settings");
            continue;
        }
        
        // Skip massage image fields that were already saved to massage_settings
        if (in_array($field, $massageImageFieldsSaved)) {
            error_log("save_content: Skipping massage field '$field' - already saved to massage_settings");
            continue;
        }
        
        // Skip mini-hotel fields if room_cards_settings table exists (they should be saved there)
        if (strpos($field, 'mini_hotel') === 0) {
            $roomCardsTableCheck = $conn->query("SHOW TABLES LIKE 'room_cards_settings'");
            if ($roomCardsTableCheck && $roomCardsTableCheck->num_rows > 0) {
                error_log("save_content: Skipping mini-hotel field '$field' - should be saved to room_cards_settings, not content_settings");
                continue;
            }
        }
        
        if (isset($_POST[$field])) {
            $fieldValue = $_POST[$field];
            error_log("save_content: Processing massage field '$field' with value length: " . strlen($fieldValue) . ", value preview: " . substr($fieldValue, 0, 50));
            
            // Check if column exists - don't add automatically to avoid "Row size too large" errors
            $columnCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE '$field'");
            $columnExists = $columnCheck && $columnCheck->num_rows > 0;
            error_log("save_content: Column '$field' exists: " . ($columnExists ? 'yes' : 'no'));
            
            // For mini-hotel fields, try to create the column automatically if it doesn't exist (only if room_cards_settings doesn't exist)
            if (!$columnExists && strpos($field, 'mini_hotel') === 0) {
                $columnType = 'VARCHAR(255) DEFAULT NULL';
                if (strpos($field, 'description') !== false) {
                    $columnType = 'TEXT DEFAULT NULL';
                }
                error_log("save_content: Column '$field' does not exist. Attempting to create with type '$columnType'");
                try {
                    $alterSql = "ALTER TABLE content_settings ADD COLUMN $field $columnType";
                    $alterResult = $conn->query($alterSql);
                    if ($alterResult) {
                        error_log("save_content: Column '$field' created successfully");
                        $columnExists = true;
                        // Verify column was created
                        $verifyCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE '$field'");
                        if ($verifyCheck && $verifyCheck->num_rows > 0) {
                            error_log("save_content: Verified column '$field' exists after creation");
                        } else {
                            error_log("save_content: WARNING - Column '$field' creation reported success but column not found in verification");
                        }
                    } else {
                        $error = $conn->error;
                        error_log("save_content: Failed to create column '$field': $error");
                        // If column already exists, mark it as existing
                        if (strpos($error, 'Duplicate column name') !== false || strpos($error, 'already exists') !== false) {
                            error_log("save_content: Column '$field' already exists (from error message)");
                            $columnExists = true;
                        }
                    }
                } catch (Exception $e) {
                    error_log("save_content: Exception while creating column '$field': " . $e->getMessage());
                    if (strpos($e->getMessage(), 'Duplicate column name') !== false || strpos($e->getMessage(), 'already exists') !== false) {
                        error_log("save_content: Column '$field' already exists (from exception)");
                        $columnExists = true;
                    }
                }
            } elseif (!$columnExists && strpos($field, 'massage_booking_') === 0) {
                $columnType = 'TEXT DEFAULT NULL';
                if ($field === 'massage_booking_title') {
                    $columnType = 'VARCHAR(255) DEFAULT NULL';
                }
                error_log("save_content: Column '$field' does not exist. Attempting to create (massage booking) with type '$columnType'");
                try {
                    $alterSql = "ALTER TABLE content_settings ADD COLUMN $field $columnType";
                    $alterResult = $conn->query($alterSql);
                    if ($alterResult) {
                        $columnExists = true;
                    } else {
                        $error = $conn->error;
                        if (strpos($error, 'Duplicate column name') !== false || strpos($error, 'already exists') !== false) {
                            $columnExists = true;
                        } else {
                            error_log("save_content: Failed to create column '$field': $error");
                        }
                    }
                } catch (Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate column name') !== false || strpos($e->getMessage(), 'already exists') !== false) {
                        $columnExists = true;
                    } else {
                        error_log("save_content: Exception while creating column '$field': " . $e->getMessage());
                    }
                }
            } elseif (!$columnExists && strpos($field, 'massage_pricing_') === 0) {
                $columnType = 'TEXT DEFAULT NULL';
                error_log("save_content: Column '$field' does not exist. Attempting to create (massage pricing JSON) with type '$columnType'");
                try {
                    $alterSql = "ALTER TABLE content_settings ADD COLUMN $field $columnType";
                    $alterResult = $conn->query($alterSql);
                    if ($alterResult) {
                        $columnExists = true;
                    } else {
                        $error = $conn->error;
                        if (strpos($error, 'Duplicate column name') !== false || strpos($error, 'already exists') !== false) {
                            $columnExists = true;
                        } else {
                            error_log("save_content: Failed to create column '$field': $error");
                        }
                    }
                } catch (Exception $e) {
                    if (strpos($e->getMessage(), 'Duplicate column name') !== false || strpos($e->getMessage(), 'already exists') !== false) {
                        $columnExists = true;
                    } else {
                        error_log("save_content: Exception while creating column '$field': " . $e->getMessage());
                    }
                }
            }
            
            if ($columnExists) {
                $fields[] = "$field = ?";
                $values[] = $fieldValue;
                $types .= 's';
                error_log("save_content: Added field '$field' to update query with value length: " . strlen($fieldValue));
            } else {
                error_log("save_content: Column '$field' does not exist. Skipping update to avoid 'Row size too large' error.");
            }
        }
    }
    
    foreach ($roomSecondFields as $field) {
        // Skip room card fields that were already saved to room_cards_settings
        if (in_array($field, $roomCardFieldsSaved)) {
            continue;
        }
        
        if (isset($_POST[$field])) {
            // Check if column exists - don't add automatically to avoid "Row size too large" errors
            $columnCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE '$field'");
            if ($columnCheck->num_rows > 0) {
                $fields[] = "$field = ?";
                $values[] = $_POST[$field];
                $types .= 's';
            } else {
                error_log("save_content: Column '$field' does not exist. Skipping update to avoid 'Row size too large' error.");
            }
        }
    }
    
    foreach ($roomGroundTwinFields as $field) {
        // Skip room card fields that were already saved to room_cards_settings
        if (in_array($field, $roomCardFieldsSaved)) {
            continue;
        }
        
        if (isset($_POST[$field])) {
            // Check if column exists - don't add automatically to avoid "Row size too large" errors
            $columnCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE '$field'");
            if ($columnCheck->num_rows > 0) {
                $fields[] = "$field = ?";
                $values[] = $_POST[$field];
                $types .= 's';
            } else {
                error_log("save_content: Column '$field' does not exist. Skipping update to avoid 'Row size too large' error.");
            }
        }
    }
    
    foreach ($roomGroundQueenFields as $field) {
        // Skip room card fields that were already saved to room_cards_settings
        if (in_array($field, $roomCardFieldsSaved)) {
            continue;
        }
        
        if (isset($_POST[$field])) {
            // Check if column exists - don't add automatically to avoid "Row size too large" errors
            $columnCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE '$field'");
            if ($columnCheck->num_rows > 0) {
                $fields[] = "$field = ?";
                $values[] = $_POST[$field];
                $types .= 's';
            } else {
                error_log("save_content: Column '$field' does not exist. Skipping update to avoid 'Row size too large' error.");
            }
        }
    }
    
    foreach ($roomBasementFields as $field) {
        // Skip room card fields that were already saved to room_cards_settings
        if (in_array($field, $roomCardFieldsSaved)) {
            continue;
        }
        
        if (isset($_POST[$field])) {
            // Check if column exists - don't add automatically to avoid "Row size too large" errors
            $columnCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE '$field'");
            if ($columnCheck->num_rows > 0) {
                $fields[] = "$field = ?";
                $values[] = $_POST[$field];
                $types .= 's';
            } else {
                error_log("save_content: Column '$field' does not exist. Skipping update to avoid 'Row size too large' error.");
            }
        }
    }
    
    foreach ($wellnessFields as $field) {
        // Skip room card fields that were already saved to room_cards_settings
        if (in_array($field, $roomCardFieldsSaved)) {
            continue;
        }
        
        if (isset($_POST[$field])) {
            // Check if column exists - don't add automatically to avoid "Row size too large" errors
            $columnCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE '$field'");
            if ($columnCheck->num_rows > 0) {
                $fields[] = "$field = ?";
                $values[] = $_POST[$field];
                $types .= 's';
            } else {
                error_log("save_content: Column '$field' does not exist. Skipping update to avoid 'Row size too large' error.");
            }
        }
    }
    
    if (isset($_POST['about_contact_form_title'])) {
        $acftCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE 'about_contact_form_title'");
        if ($acftCheck && $acftCheck->num_rows === 0) {
            if ($conn->query("ALTER TABLE content_settings ADD COLUMN about_contact_form_title VARCHAR(255) DEFAULT NULL")) {
                error_log('save_content: Added column about_contact_form_title');
            } else {
                error_log('save_content: Could not add about_contact_form_title: ' . $conn->error);
            }
        }
    }
    if (isset($_POST['about_contact_form_description'])) {
        $acfdCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE 'about_contact_form_description'");
        if ($acfdCheck && $acfdCheck->num_rows === 0) {
            if ($conn->query("ALTER TABLE content_settings ADD COLUMN about_contact_form_description TEXT NULL")) {
                error_log('save_content: Added column about_contact_form_description');
            } else {
                error_log('save_content: Could not add about_contact_form_description: ' . $conn->error);
            }
        }
    }

    foreach ($aboutFields as $field) {
        // Skip room card fields that were already saved to room_cards_settings
        if (in_array($field, $roomCardFieldsSaved)) {
            continue;
        }
        if (!empty($exploreParksTableExists) && $exploreParksTableExists && in_array($field, ['about_parks_title', 'about_parks_intro', 'about_parks_list'], true)) {
            continue;
        }
        
        if (isset($_POST[$field])) {
            // Check if column exists - don't add automatically to avoid "Row size too large" errors
            $columnCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE '$field'");
            if ($columnCheck->num_rows > 0) {
                $fields[] = "$field = ?";
                $values[] = $_POST[$field];
                $types .= 's';
            } else {
                error_log("save_content: Column '$field' does not exist. Skipping update to avoid 'Row size too large' error.");
            }
        }
    }
    
    // Retreat "Invitation to Collaborate" image URL → retreat_settings when that table exists
    $retreatSettingsTableExists = false;
    $retreatCollaborationImageSaved = false;
    $retreatSettingsTblChk = $conn->query("SHOW TABLES LIKE 'retreat_settings'");
    if ($retreatSettingsTblChk && $retreatSettingsTblChk->num_rows > 0) {
        $retreatSettingsTableExists = true;
    }
    if ($retreatSettingsTableExists && isset($_POST['retreat_collaboration_image_url'])) {
        $colChkRs = $conn->query("SHOW COLUMNS FROM retreat_settings LIKE 'retreat_collaboration_image_url'");
        if ($colChkRs && $colChkRs->num_rows > 0) {
            $checkRsRow = $conn->query("SELECT id FROM retreat_settings WHERE id = 1");
            if ($checkRsRow && $checkRsRow->num_rows === 0) {
                $conn->query("INSERT INTO retreat_settings (id) VALUES (1)");
            }
            $rsUpdate = $conn->prepare("UPDATE retreat_settings SET retreat_collaboration_image_url = ? WHERE id = 1");
            if ($rsUpdate) {
                $rv = $_POST['retreat_collaboration_image_url'];
                $rsUpdate->bind_param('s', $rv);
                if ($rsUpdate->execute()) {
                    $retreatCollaborationImageSaved = true;
                    error_log('save_content: Saved retreat_collaboration_image_url to retreat_settings');
                } else {
                    error_log('save_content: retreat_settings update failed: ' . $rsUpdate->error);
                }
                $rsUpdate->close();
            }
        } else {
            error_log("save_content: retreat_settings missing column retreat_collaboration_image_url — run add_retreat_collaboration_image_column.php");
        }
    }
    
    // Image URL fields (wellness, rooms, massage, retreat, special, about)
    $imageFields = [
        'wellness_massage_image_url', 'wellness_yoga_image_url',
        'room_basement_card_image_url', 'room_ground_queen_card_image_url', 'room_ground_twin_card_image_url',
        'room_second_card_image_url', 'room_basement_banner_image_url', 'room_ground_queen_banner_image_url',
        'room_ground_twin_banner_image_url', 'room_second_banner_image_url',
        'massage_relaxing_image_url', 'massage_deep_tissue_image_url', 'massage_reiki_image_url',
        'massage_sauna_image_url', 'mini_hotel_image_url',
        'retreat_hero_image_url', 'retreat_forest_image_url', 'retreat_indoor_image_url', 'retreat_theatre_image_url',
        'retreat_collaboration_image_url',
        'special_hero_image_url', 'special_pools_image_url', 'special_dining_image_url', 'special_extra_image_url',
        'about_hero_image_url', 'about_founder_image_url', 'about_procter_image_url', 'about_nelson_image_url', 'explore_hero_image_url', 'explore_accommodation_image_url',
        'about_parks_hero_image_url'
    ];
    
    // Room card image fields that should NEVER be saved to content_settings if room_cards_settings exists
    $roomCardImageFields = [
        'room_basement_card_image_url',
        'room_ground_queen_card_image_url',
        'room_ground_twin_card_image_url',
        'room_second_card_image_url'
    ];
    
    foreach ($imageFields as $field) {
        // Skip room card image fields that were already saved to room_cards_settings
        if (in_array($field, $roomCardFieldsSaved)) {
            error_log("save_content: Skipping $field - already saved to room_cards_settings");
            continue;
        }
        
        // For room card image fields, NEVER try to save to content_settings if room_cards_settings table exists
        // This prevents "Row size too large" errors
        if ($roomCardsTableExists && in_array($field, $roomCardImageFields)) {
            error_log("save_content: Skipping $field in imageFields loop - room_cards_settings table exists, field is room card image");
            continue;
        }
        
        if ($retreatSettingsTableExists && $field === 'retreat_collaboration_image_url') {
            error_log("save_content: Skipping retreat_collaboration_image_url in content_settings (use retreat_settings)");
            continue;
        }
        
        if (isset($exploreSettingsTableExists) && $exploreSettingsTableExists && $field === 'explore_hero_image_url') {
            error_log("save_content: Skipping explore_hero_image_url in content_settings (use explore_settings)");
            continue;
        }
        if (isset($exploreSettingsTableExists) && $exploreSettingsTableExists && $field === 'explore_accommodation_image_url') {
            error_log("save_content: Skipping explore_accommodation_image_url in content_settings (use explore_settings)");
            continue;
        }

        if (!empty($exploreParksTableExists) && $exploreParksTableExists && $field === 'about_parks_hero_image_url') {
            error_log("save_content: Skipping about_parks_hero_image_url in content_settings (use explore_parks_settings)");
            continue;
        }
        
        if (isset($_POST[$field])) {
            
            // Check if column exists - don't add automatically to avoid "Row size too large" errors
            $columnCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE '$field'");
            if ($columnCheck->num_rows > 0) {
                // Column exists, add to update
                $fields[] = "$field = ?";
                $values[] = $_POST[$field];
                $types .= 's';
            } else {
                // Column doesn't exist - log but don't try to add (table might be full)
                error_log("save_content: Column '$field' does not exist. Skipping update to avoid 'Row size too large' error.");
            }
        }
    }
    
    // Log what fields we're about to save
    error_log('save_content: Fields to update: ' . count($fields) . ' fields');
    if (count($fields) > 0) {
        error_log('save_content: First few fields: ' . implode(', ', array_slice($fields, 0, 5)));
    }
    
    // If only rooms_title/rooms_subtitle were provided and they were saved to rooms_settings,
    // or if only room card fields were provided and they were saved to room_cards_settings,
    // and there are no other fields to update, we can return success immediately
    if (empty($fields) && ($roomsSaved || $roomCardsSaved || $retreatCollaborationImageSaved || $exploreSettingsSaved || $exploreParksSaved || $exploreCommunityExtraSaved)) {
        error_log('save_content: Only rooms/room cards / retreat collaboration image / explore_settings / explore_parks / explore_community_extra were saved, no content_settings update needed');
        ob_end_clean();
        $response = ['success' => true];
        if ($roomsSubtitleColumnMissing) {
            $response['warning'] = 'rooms_subtitle column does not exist in database. Please run create_rooms_settings_table.php to create the rooms_settings table.';
        }
        if ($roomCardsSaved) {
            $response['message'] = 'Room card fields saved to room_cards_settings table';
        }
        echo json_encode($response);
        exit;
    }
    
    // If no fields to update and rooms/room cards weren't saved, return error
    if (empty($fields) && !$roomsSaved && !$roomCardsSaved && !$retreatCollaborationImageSaved && !$exploreSettingsSaved && !$exploreParksSaved && !$exploreCommunityExtraSaved) {
        error_log('save_content: No fields to update and rooms/room cards / explore_settings / explore_parks / explore_community_extra were not saved');
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'No fields to update']);
        exit;
    }
    
    // Log what we're about to update in content_settings
    if (!empty($fields)) {
        error_log('save_content: About to update ' . count($fields) . ' fields in content_settings');
        error_log('save_content: Fields: ' . implode(', ', array_slice($fields, 0, 10)));
        error_log('save_content: Room card fields saved: ' . implode(', ', $roomCardFieldsSaved));
    }
    
    // Ensure record exists
    $checkResult = $conn->query("SELECT id FROM content_settings WHERE id = 1");
    if ($checkResult->num_rows === 0) {
        $conn->query("INSERT INTO content_settings (id) VALUES (1)");
    }
    
    // Log what we're about to update
    if (!empty($fields)) {
        error_log('save_content: About to update ' . count($fields) . ' fields: ' . implode(', ', array_slice($fields, 0, 10)));
        // Check if mini-hotel fields are in the update
        $miniHotelFieldsInUpdate = array_filter($fields, function($field) {
            return strpos($field, 'mini_hotel') !== false;
        });
        if (!empty($miniHotelFieldsInUpdate)) {
            error_log('save_content: Mini-hotel fields in update: ' . implode(', ', $miniHotelFieldsInUpdate));
        } else {
            error_log('save_content: WARNING - No mini-hotel fields in update query!');
        }
    }
    
    $sql = "UPDATE content_settings SET " . implode(', ', $fields) . " WHERE id = 1";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $error = $stmt->error ?: $conn->error;
        error_log('save_content: Prepare failed - ' . $error);
        
        // Check if error is about missing columns
        if (strpos($error, 'rooms_title') !== false || strpos($error, 'rooms_subtitle') !== false) {
            ob_end_clean();
            echo json_encode([
                'success' => false, 
                'error' => 'Database columns missing. Please run add_rooms_title_fields.php to add the required columns.',
                'error_details' => $error
            ]);
        } else {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Prepare failed: ' . $error]);
        }
        exit;
    }
    
    $stmt->bind_param($types, ...$values);
    ob_clean(); // Clear any output
    
    if ($stmt->execute()) {
        // Verify mini-hotel fields were saved - check room_cards_settings first, then content_settings
        if (isset($_POST['mini_hotel_title']) || isset($_POST['mini_hotel_description']) || isset($_POST['mini_hotel_description_1']) || isset($_POST['mini_hotel_description_2'])) {
            try {
                // Check room_cards_settings first
                $roomCardsTableCheck = $conn->query("SHOW TABLES LIKE 'room_cards_settings'");
                if ($roomCardsTableCheck && $roomCardsTableCheck->num_rows > 0) {
                    $vFields = 'mini_hotel_title';
                    if (function_exists('dbTableHasColumn') && dbTableHasColumn($conn, 'room_cards_settings', 'mini_hotel_description')) {
                        $vFields .= ', mini_hotel_description';
                    }
                    $vFields .= ', mini_hotel_description_1, mini_hotel_description_2';
                    $verifyMiniHotel = $conn->query("SELECT {$vFields} FROM room_cards_settings WHERE id = 1");
                    if ($verifyMiniHotel && $verifyMiniHotel->num_rows > 0) {
                        $saved = $verifyMiniHotel->fetch_assoc();
                        error_log('save_content: Verified mini-hotel data saved in room_cards_settings:');
                        error_log('  mini_hotel_title: ' . (isset($saved['mini_hotel_title']) && $saved['mini_hotel_title'] !== '' ? ('"' . substr($saved['mini_hotel_title'], 0, 50) . '"') : 'EMPTY/NULL'));
                        if (array_key_exists('mini_hotel_description', $saved)) {
                            error_log('  mini_hotel_description: ' . (isset($saved['mini_hotel_description']) && $saved['mini_hotel_description'] !== '' ? ('"' . substr($saved['mini_hotel_description'], 0, 50) . '"') : 'EMPTY/NULL'));
                        }
                        error_log('  mini_hotel_description_1: ' . (isset($saved['mini_hotel_description_1']) && $saved['mini_hotel_description_1'] !== '' ? ('"' . substr($saved['mini_hotel_description_1'], 0, 50) . '"') : 'EMPTY/NULL'));
                        error_log('  mini_hotel_description_2: ' . (isset($saved['mini_hotel_description_2']) && $saved['mini_hotel_description_2'] !== '' ? ('"' . substr($saved['mini_hotel_description_2'], 0, 50) . '"') : 'EMPTY/NULL'));
                    } else {
                        error_log('save_content: Could not verify mini-hotel data in room_cards_settings - query returned no rows or columns do not exist');
                    }
                } else {
                    // Fall back to content_settings
                    $verifyMiniHotel = $conn->query("SELECT mini_hotel_title, mini_hotel_description_1, mini_hotel_description_2 FROM content_settings WHERE id = 1");
                    if ($verifyMiniHotel && $verifyMiniHotel->num_rows > 0) {
                        $saved = $verifyMiniHotel->fetch_assoc();
                        error_log('save_content: Verified mini-hotel data saved in content_settings:');
                        error_log('  mini_hotel_title: ' . (isset($saved['mini_hotel_title']) && $saved['mini_hotel_title'] !== '' ? ('"' . substr($saved['mini_hotel_title'], 0, 50) . '"') : 'EMPTY/NULL'));
                        error_log('  mini_hotel_description_1: ' . (isset($saved['mini_hotel_description_1']) && $saved['mini_hotel_description_1'] !== '' ? ('"' . substr($saved['mini_hotel_description_1'], 0, 50) . '"') : 'EMPTY/NULL'));
                        error_log('  mini_hotel_description_2: ' . (isset($saved['mini_hotel_description_2']) && $saved['mini_hotel_description_2'] !== '' ? ('"' . substr($saved['mini_hotel_description_2'], 0, 50) . '"') : 'EMPTY/NULL'));
                    } else {
                        error_log('save_content: Could not verify mini-hotel data - query returned no rows or columns do not exist');
                    }
                }
            } catch (Exception $e) {
                error_log('save_content: Exception while verifying mini-hotel data: ' . $e->getMessage());
            }
        }
        
        // Also verify Sauna data for comparison
        if (isset($_POST['massage_sauna_title']) || isset($_POST['massage_sauna_description'])) {
            try {
                $verifySauna = $conn->query("SELECT massage_sauna_title, massage_sauna_description FROM content_settings WHERE id = 1");
                if ($verifySauna && $verifySauna->num_rows > 0) {
                    $saved = $verifySauna->fetch_assoc();
                    error_log('save_content: Verified sauna data saved:');
                    error_log('  massage_sauna_title: ' . (isset($saved['massage_sauna_title']) && $saved['massage_sauna_title'] !== '' ? ('"' . substr($saved['massage_sauna_title'], 0, 50) . '"') : 'EMPTY/NULL'));
                    error_log('  massage_sauna_description: ' . (isset($saved['massage_sauna_description']) && $saved['massage_sauna_description'] !== '' ? ('"' . substr($saved['massage_sauna_description'], 0, 50) . '"') : 'EMPTY/NULL'));
                }
            } catch (Exception $e) {
                error_log('save_content: Exception while verifying sauna data: ' . $e->getMessage());
            }
        }
        
        // Verify that room card fields were saved (check room_cards_settings table first)
        if (isset($_POST['room_basement_card_description'])) {
            $roomCardsTableCheck = $conn->query("SHOW TABLES LIKE 'room_cards_settings'");
            if ($roomCardsTableCheck && $roomCardsTableCheck->num_rows > 0) {
                $verify = $conn->query("SELECT room_basement_card_description FROM room_cards_settings WHERE id = 1");
                if ($verify && $verify->num_rows > 0) {
                    $saved = $verify->fetch_assoc();
                    $savedValue = $saved['room_basement_card_description'] ?? '';
                    error_log('save_content: Verified saved room_basement_card_description in room_cards_settings = ' . substr($savedValue, 0, 100) . ' (length: ' . strlen($savedValue) . ')');
                } else {
                    error_log('save_content: Could not verify room_basement_card_description in room_cards_settings - query failed or no rows');
                }
            } else {
                // Fall back to content_settings
                $verify = $conn->query("SELECT room_basement_card_description FROM content_settings WHERE id = 1");
                if ($verify && $verify->num_rows > 0) {
                    $saved = $verify->fetch_assoc();
                    $savedValue = $saved['room_basement_card_description'] ?? '';
                    error_log('save_content: Verified saved room_basement_card_description in content_settings = ' . substr($savedValue, 0, 100) . ' (length: ' . strlen($savedValue) . ')');
                } else {
                    error_log('save_content: Could not verify room_basement_card_description - query failed or no rows');
                }
            }
        }
        
        // Verify that rooms_subtitle was saved (check rooms_settings table first)
        $roomsSubtitleSaved = false;
        if (isset($_POST['rooms_subtitle'])) {
            $roomsTableCheck = $conn->query("SHOW TABLES LIKE 'rooms_settings'");
            if ($roomsTableCheck && $roomsTableCheck->num_rows > 0) {
                $verify = $conn->query("SELECT rooms_subtitle FROM rooms_settings WHERE id = 1");
                if ($verify && $verify->num_rows > 0) {
                    $saved = $verify->fetch_assoc();
                    $roomsSubtitleSaved = true;
                    error_log('save_content: Verified saved rooms_subtitle in rooms_settings = ' . substr($saved['rooms_subtitle'] ?? '', 0, 100) . ' (length: ' . strlen($saved['rooms_subtitle'] ?? '') . ')');
                }
            } else {
                // Fall back to content_settings
                $columnCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE 'rooms_subtitle'");
                if ($columnCheck && $columnCheck->num_rows > 0) {
                    $verify = $conn->query("SELECT rooms_subtitle FROM content_settings WHERE id = 1");
                    if ($verify && $verify->num_rows > 0) {
                        $saved = $verify->fetch_assoc();
                        $roomsSubtitleSaved = true;
                        error_log('save_content: Verified saved rooms_subtitle in content_settings = ' . substr($saved['rooms_subtitle'] ?? '', 0, 100) . ' (length: ' . strlen($saved['rooms_subtitle'] ?? '') . ')');
                    }
                } else {
                    error_log('save_content: rooms_subtitle column does not exist, skipping verification');
                }
            }
        }
        ob_end_clean();
        
        // Return warning if rooms_subtitle column is missing
        $response = ['success' => true];
        if (isset($roomsSubtitleColumnMissing) && $roomsSubtitleColumnMissing) {
            $response['warning'] = 'rooms_subtitle column does not exist in database. Please run add_rooms_title_fields.php to add it.';
        } elseif (isset($_POST['rooms_subtitle']) && !$roomsSubtitleSaved) {
            $response['warning'] = 'rooms_subtitle may not have been saved - column might not exist in database.';
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
        restore_error_handler(); // Restore error handler
        exit;
    } else {
        $error = $stmt->error ?: $conn->error;
        error_log('save_content: Database error - ' . $error);
        
        ob_end_clean();
        header('Content-Type: application/json');
        
        // Check if error is about missing columns or row size
        if (strpos($error, 'rooms_title') !== false || strpos($error, 'rooms_subtitle') !== false || strpos($error, 'Row size too large') !== false) {
            echo json_encode([
                'success' => false, 
                'error' => 'Database columns missing or table is full. Please run fix_rooms_subtitle_column.php first, then add_rooms_title_fields.php to add the required columns.',
                'error_details' => $error
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => $error]);
        }
        restore_error_handler();
        exit;
    }
    $stmt->close();
    ob_end_clean();
    restore_error_handler();
    exit;
    } catch (Exception $e) {
        ob_end_clean();
        header('Content-Type: application/json');
        error_log('save_content: Exception caught: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
        echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
        restore_error_handler();
        exit;
    } catch (Error $e) {
        ob_end_clean();
        header('Content-Type: application/json');
        error_log('save_content: Error caught: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
        echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
        restore_error_handler();
        exit;
    } catch (Throwable $e) {
        ob_end_clean();
        header('Content-Type: application/json');
        error_log('save_content: Throwable caught: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
        echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
        restore_error_handler();
        exit;
    }
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

// Floor plan handler moved above - see line ~270

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
















