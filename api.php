<?php
// Disable error display for API responses so JSON output is not corrupted
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
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    try {
        if (function_exists('btb_ensure_special_block2_columns')) {
            btb_ensure_special_block2_columns($conn);
        }
        if (function_exists('btb_ensure_special_addon_panels_json_column')) {
            btb_ensure_special_addon_panels_json_column($conn);
        }
        if (function_exists('btb_ensure_booking_button_label_columns')) {
            btb_ensure_booking_button_label_columns($conn);
        }
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
        
        if (function_exists('btb_ensure_massage_service_cards_json_column')) {
            btb_ensure_massage_service_cards_json_column($conn);
        }
        // Explore + other Phase 1 canonical tables (explore merge is first inside btb_merge_phase1_canonical_into_content_row)
        if (function_exists('btb_merge_phase1_canonical_into_content_row')) {
            btb_merge_phase1_canonical_into_content_row($conn, $data);
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
            
            // Main cards: text/image - non-empty from room_cards, otherwise from merged $data (after preserve from content_settings).
            $rc = is_array($roomCardData) ? $roomCardData : [];
            $roomBasementCardTitle = btb_room_card_field_prefer_non_empty($rc, $data, 'room_basement_card_title');
            $roomBasementCardDescription = btb_room_card_field_prefer_non_empty($rc, $data, 'room_basement_card_description');
            // Home cards: the same price line as on the room's detail page (as index.php).
            $roomBasementCardPrice = btb_room_price_line_html_stored_only($data, 'basement');
            $roomBasementCardImageUrl = trim(btb_room_card_field_prefer_non_empty($rc, $data, 'room_basement_card_image_url'));
            
            $roomGroundQueenCardTitle = btb_room_card_field_prefer_non_empty($rc, $data, 'room_ground_queen_card_title');
            $roomGroundQueenCardDescription = btb_room_card_field_prefer_non_empty($rc, $data, 'room_ground_queen_card_description');
            $roomGroundQueenCardPrice = btb_room_price_line_html_stored_only($data, 'ground_queen');
            $roomGroundQueenCardImageUrl = trim(btb_room_card_field_prefer_non_empty($rc, $data, 'room_ground_queen_card_image_url'));
            
            $roomGroundTwinCardTitle = btb_room_card_field_prefer_non_empty($rc, $data, 'room_ground_twin_card_title');
            $roomGroundTwinCardDescription = btb_room_card_field_prefer_non_empty($rc, $data, 'room_ground_twin_card_description');
            $roomGroundTwinCardPrice = btb_room_price_line_html_stored_only($data, 'ground_twin');
            $roomGroundTwinCardImageUrl = trim(btb_room_card_field_prefer_non_empty($rc, $data, 'room_ground_twin_card_image_url'));
            
            $roomSecondCardTitle = btb_room_card_field_prefer_non_empty($rc, $data, 'room_second_card_title');
            $roomSecondCardDescription = btb_room_card_field_prefer_non_empty($rc, $data, 'room_second_card_description');
            $roomSecondCardPrice = btb_room_price_line_html_stored_only($data, 'second');
            $roomSecondCardImageUrl = trim(btb_room_card_field_prefer_non_empty($rc, $data, 'room_second_card_image_url'));
            
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
            
            $retreatCollaborationImageUrlOut = trim((string) ($data['retreat_collaboration_image_url'] ?? ''));

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

            // Special offer: admin uses one body field; merge legacy description into main for JSON (source indicator + clients).
            $specialOfferMainRaw = trim((string) ($data['special_offer_main_text'] ?? ''));
            $specialOfferDescRaw = trim((string) ($data['special_offer_description'] ?? ''));
            if ($specialOfferMainRaw !== '' && $specialOfferDescRaw !== '') {
                $specialOfferMainTextOut = $specialOfferMainRaw . "\n\n" . $specialOfferDescRaw;
            } elseif ($specialOfferMainRaw === '' && $specialOfferDescRaw !== '') {
                $specialOfferMainTextOut = $specialOfferDescRaw;
            } else {
                $specialOfferMainTextOut = $specialOfferMainRaw;
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
                    'wellnessMassageImageUrl' => $data['wellness_massage_image_url'] ?? '',
                    'wellnessYogaImageUrl' => $data['wellness_yoga_image_url'] ?? '',
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
                    'homepageBookAStayButtonLabel' => trim((string) ($data['homepage_book_a_stay_button_label'] ?? '')) !== ''
                        ? $data['homepage_book_a_stay_button_label']
                        : 'Book a stay',
                    'roomBookNowButtonLabel' => trim((string) ($data['room_book_now_button_label'] ?? '')) !== ''
                        ? $data['room_book_now_button_label']
                        : 'Book now',
                    'massageBookServiceButtonLabel' => trim((string) ($data['massage_book_service_button_label'] ?? '')) !== ''
                        ? $data['massage_book_service_button_label']
                        : 'Book service',
                    'massageCartSubmitButtonLabel' => (string) ($data['massage_cart_submit_button_label'] ?? ''),
                    // Room banners
                    'roomBasementBannerImageUrl' => $data['room_basement_banner_image_url'] ?? '',
                    'roomGroundQueenBannerImageUrl' => $data['room_ground_queen_banner_image_url'] ?? '',
                    'roomGroundTwinBannerImageUrl' => $data['room_ground_twin_banner_image_url'] ?? '',
                    'roomSecondBannerImageUrl' => $data['room_second_banner_image_url'] ?? '',
                    // Massage page images - use massage_settings if available, otherwise content_settings
                    'massageHeroImageUrl' => $data['massage_hero_image_url'] ?? '',
                    'massageRelaxingImageUrl' => $data['massage_relaxing_image_url'] ?? '',
                    'massageDeepTissueImageUrl' => $data['massage_deep_tissue_image_url'] ?? '',
                    'massageReikiImageUrl' => $data['massage_reiki_image_url'] ?? '',
                    'massageSaunaImageUrl' => $data['massage_sauna_image_url'] ?? '',
                    'miniHotelImageUrl' => !empty($miniHotelData) ? ($miniHotelData['mini_hotel_image_url'] ?? '') : ($data['mini_hotel_image_url'] ?? ''),
                    // Retreat and Workshop page images
                    'retreatHeroImageUrl' => $data['retreat_hero_image_url'] ?? '',
                    'retreatForestImageUrl' => $data['retreat_forest_image_url'] ?? '',
                    'retreatIndoorImageUrl' => $data['retreat_indoor_image_url'] ?? '',
                    'retreatTheatreImageUrl' => $data['retreat_theatre_image_url'] ?? '',
                    'retreatCollaborationImageUrl' => $retreatCollaborationImageUrlOut,
                    // Special page images - use special_settings if available, otherwise content_settings
                    'specialHeroImageUrl' => $data['special_hero_image_url'] ?? '',
                    'specialPoolsImageUrl' => $data['special_pools_image_url'] ?? '',
                    'specialDiningImageUrl' => $data['special_dining_image_url'] ?? '',
                    'specialExtraImageUrl' => $data['special_extra_image_url'] ?? '',
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
                    'retreatGalleryOverlayForest' => $data['retreat_gallery_overlay_forest'] ?? '',
                    'retreatGalleryOverlayIndoor' => $data['retreat_gallery_overlay_indoor'] ?? '',
                    'retreatGalleryOverlayTheatre' => $data['retreat_gallery_overlay_theatre'] ?? '',
                    // Special page content - use special_settings if available, otherwise content_settings
                    'specialHeroTitle' => $data['special_hero_title'] ?? '',
                    'specialHeroSubtitle' => $data['special_hero_subtitle'] ?? '',
                    'specialPoolsTitle' => $data['special_pools_title'] ?? '',
                    'specialPoolsDescription1' => $data['special_pools_description_1'] ?? '',
                    'specialPoolsDescription2' => $data['special_pools_description_2'] ?? '',
                    'specialDiningTitle' => $data['special_dining_title'] ?? '',
                    'specialDiningDescription1' => $data['special_dining_description_1'] ?? '',
                    'specialExtraTitle' => $data['special_extra_title'] ?? '',
                    'specialExtraDescription1' => $data['special_extra_description_1'] ?? '',
                    'specialExtraDescription2' => $data['special_extra_description_2'] ?? '',
                    'specialOfferTitle' => $data['special_offer_title'] ?? '',
                    'specialOfferMainText' => $specialOfferMainTextOut,
                    'specialOfferRoomsCtaLabel' => $data['special_offer_rooms_cta_label'] ?? '',
                    'specialAddonPanels' => function_exists('btb_special_addon_panels_decode_from_content')
                        ? btb_special_addon_panels_decode_from_content($data)
                        : [],
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
                    // Legacy; admin no longer saves these â€” used if description empty on old DB rows
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
                    'exploreGalleryOverlayCommunity' => $data['explore_gallery_overlay_community'] ?? '',
                    'exploreGalleryOverlayCulture' => $data['explore_gallery_overlay_culture'] ?? '',
                    'exploreGalleryOverlayPark' => $data['explore_gallery_overlay_park'] ?? '',
                    'exploreGalleryOverlayActivity' => $data['explore_gallery_overlay_activity'] ?? '',
                    'exploreGalleryOverlayStay' => $data['explore_gallery_overlay_stay'] ?? '',
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
                    'wellnessStayGalleryOverlay' => $data['wellness_stay_gallery_overlay'] ?? '',
                    'massagePricingRelaxing' => $data['massage_pricing_relaxing'] ?? '',
                    'massagePricingDeepTissue' => $data['massage_pricing_deep_tissue'] ?? '',
                    'massagePricingReiki' => $data['massage_pricing_reiki'] ?? '',
                    'massagePricingSauna' => $data['massage_pricing_sauna'] ?? '',
                    'massageServiceCardsJson' => function_exists('btb_massage_service_cards_json_column_name')
                        ? (string) ($data[btb_massage_service_cards_json_column_name()] ?? '')
                        : '',
                    'miniHotelTitle' => $miniHotelTitle,
                    'miniHotelDescription' => $miniHotelDescriptionMerged,
                    'miniHotelDescription1' => $miniHotelDesc1,
                    'miniHotelDescription2' => $miniHotelDesc2,
                    // Room Second floor page content (same fallbacks as room-second-suite.php for admin parity with public site)
                    'roomSecondTitle' => btb_field_or_default($data, 'room_second_title', 'content_settings.room_second_title', 'Kelder'),
                    'roomSecondSubtitle' => btb_field_or_default($data, 'room_second_subtitle', 'content_settings.room_second_subtitle', 'A private loft under the roof: bedroom, kitchenette, shower, study and balcony.'),
                    'roomSecondDescription' => btb_field_or_default($data, 'room_second_description', 'content_settings.room_second_description', 'A fully private floor featuring a large living area with a king-size bed, a separate kitchen, a private bathroom with a shower, a bright workspace, and a spacious balcony with stunning views of the lake and mountains.'),
                    'roomSecondPrice' => btb_room_price_line_html_stored_only($data, 'second'),
                    'roomSecondPricePrefix' => $roomPricePartsSecond['prefix'],
                    'roomSecondPriceAmount' => $roomPricePartsSecond['amount'],
                    'roomSecondPriceSuffix' => $roomPricePartsSecond['suffix'],
                    'roomSecondCapacity' => $data['room_second_capacity'] ?? '',
                    'roomSecondNote' => btb_field_or_default($data, 'room_second_note', 'content_settings.room_second_note', '*All tenants may use the sauna and home theatre free of charge, as long as it does not disturb other guests.'),
                    'roomSecondGallery' => $data['room_second_gallery'] ?? '[]',
                    'roomSecondGallerySectionTitle' => $data['room_second_gallery_section_title'] ?? '',
                    'roomSecondCommonGallery' => $data['room_second_common_gallery'] ?? '[]',
                    'roomSecondCommonGallerySectionTitle' => $data['room_second_common_gallery_section_title'] ?? '',
                    'roomSecondBannerImageUrl' => $data['room_second_banner_image_url'] ?? '',
                    // Room Ground Twin beds page content (same fallbacks as room-first-twin.php)
                    'roomGroundTwinTitle' => btb_field_or_default($data, 'room_ground_twin_title', 'content_settings.room_ground_twin_title', 'Vrienden'),
                    'roomGroundTwinSubtitle' => btb_field_or_default($data, 'room_ground_twin_subtitle', 'content_settings.room_ground_twin_subtitle', 'Perfect for friends or colleagues. Near the kitchen and massage hall.'),
                    'roomGroundTwinDescription' => btb_field_or_default($data, 'room_ground_twin_description', 'content_settings.room_ground_twin_description', 'This first-floor room features two single beds, making it ideal for friends or colleagues traveling together. A shared bathroom with a large bathtub is located nearby.'),
                    'roomGroundTwinPrice' => btb_room_price_line_html_stored_only($data, 'ground_twin'),
                    'roomGroundTwinPricePrefix' => $roomPricePartsGroundTwin['prefix'],
                    'roomGroundTwinPriceAmount' => $roomPricePartsGroundTwin['amount'],
                    'roomGroundTwinPriceSuffix' => $roomPricePartsGroundTwin['suffix'],
                    'roomGroundTwinCapacity' => $data['room_ground_twin_capacity'] ?? '',
                    'roomGroundTwinNote' => btb_field_or_default($data, 'room_ground_twin_note', 'content_settings.room_ground_twin_note', '*All tenants may use the sauna and home theatre free of charge, as long as it does not disturb other guests.'),
                    'roomGroundTwinGallery' => $data['room_ground_twin_gallery'] ?? '[]',
                    'roomGroundTwinGallerySectionTitle' => $data['room_ground_twin_gallery_section_title'] ?? '',
                    'roomGroundTwinCommonGallery' => $data['room_ground_twin_common_gallery'] ?? '[]',
                    'roomGroundTwinCommonGallerySectionTitle' => $data['room_ground_twin_common_gallery_section_title'] ?? '',
                    'roomGroundTwinBannerImageUrl' => $data['room_ground_twin_banner_image_url'] ?? '',
                    // Room Ground Queen bed page content (same fallbacks as room-first-double.php)
                    'roomGroundQueenTitle' => btb_field_or_default($data, 'room_ground_queen_title', 'content_settings.room_ground_queen_title', 'The Nouk'),
                    'roomGroundQueenSubtitle' => btb_field_or_default($data, 'room_ground_queen_subtitle', 'content_settings.room_ground_queen_subtitle', 'Bright room near the living room with fireplace. Ideal for two.'),
                    'roomGroundQueenDescription' => btb_field_or_default($data, 'room_ground_queen_description', 'content_settings.room_ground_queen_description', 'A small but bright room with a large double bed. A shared bathroom with a spacious bathtub is located nearby.'),
                    'roomGroundQueenPrice' => btb_room_price_line_html_stored_only($data, 'ground_queen'),
                    'roomGroundQueenPricePrefix' => $roomPricePartsGroundQueen['prefix'],
                    'roomGroundQueenPriceAmount' => $roomPricePartsGroundQueen['amount'],
                    'roomGroundQueenPriceSuffix' => $roomPricePartsGroundQueen['suffix'],
                    'roomGroundQueenCapacity' => $data['room_ground_queen_capacity'] ?? '',
                    'roomGroundQueenNote' => btb_field_or_default($data, 'room_ground_queen_note', 'content_settings.room_ground_queen_note', '*All tenants may use the sauna and home theatre free of charge, as long as it does not disturb other guests.'),
                    'roomGroundQueenGallery' => $data['room_ground_queen_gallery'] ?? '[]',
                    'roomGroundQueenGallerySectionTitle' => $data['room_ground_queen_gallery_section_title'] ?? '',
                    'roomGroundQueenCommonGallery' => $data['room_ground_queen_common_gallery'] ?? '[]',
                    'roomGroundQueenCommonGallerySectionTitle' => $data['room_ground_queen_common_gallery_section_title'] ?? '',
                    'roomGroundQueenBannerImageUrl' => $data['room_ground_queen_banner_image_url'] ?? '',
                    // Room Basement / Loki Suite page content (same fallbacks as room-basement.php)
                    'roomBasementTitle' => btb_field_or_default($data, 'room_basement_title', 'content_settings.room_basement_title', 'Loki Suite'),
                    'roomBasementSubtitle' => btb_field_or_default($data, 'room_basement_subtitle', 'content_settings.room_basement_subtitle', 'A cozy room next to the home cinema and sauna. Ideal for two.'),
                    'roomBasementDescription' => btb_field_or_default($data, 'room_basement_description', 'content_settings.room_basement_description', 'Next to this room there is a home theater lounge with a wood-burning stove and a large shower area with a sauna. The floor has a private exit from the house and a passage to the shared lounge on the first floor.'),
                    'roomBasementPrice' => btb_room_price_line_html_stored_only($data, 'basement'),
                    'roomBasementPricePrefix' => $roomPricePartsBasement['prefix'],
                    'roomBasementPriceAmount' => $roomPricePartsBasement['amount'],
                    'roomBasementPriceSuffix' => $roomPricePartsBasement['suffix'],
                    'roomBasementCapacity' => $data['room_basement_capacity'] ?? '',
                    'roomBasementNote' => btb_field_or_default($data, 'room_basement_note', 'content_settings.room_basement_note', '*All tenants may use the sauna and home theatre free of charge, as long as it does not disturb other guests.'),
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
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    try {
        $tableCheck = $conn->query("SHOW TABLES LIKE 'floorplan_settings'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            $payload = function_exists('btb_floorplan_api_payload_from_row') ? btb_floorplan_api_payload_from_row([]) : [];
            echo json_encode(['success' => true, 'data' => $payload]);
            exit;
        }

        $result = $conn->query("SELECT * FROM floorplan_settings WHERE id = 1");
        if (!$result) {
            echo json_encode(['success' => false, 'error' => 'Query failed: ' . $conn->error]);
            exit;
        }

        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            error_log('get_floorplan: Loaded data - ground_description: ' . substr($data['ground_description'] ?? '', 0, 100));
            $payload = function_exists('btb_floorplan_api_payload_from_row') ? btb_floorplan_api_payload_from_row(is_array($data) ? $data : []) : [];
            echo json_encode(['success' => true, 'data' => $payload]);
        } else {
            $payload = function_exists('btb_floorplan_api_payload_from_row') ? btb_floorplan_api_payload_from_row([]) : [];
            echo json_encode(['success' => true, 'data' => $payload]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Floor plan save handler - must be BEFORE auth_api.php to avoid "Invalid action" error
if ($action === 'save_floorplan') {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    header('Content-Type: application/json');
    ob_start();

    try {
        $result = function_exists('btb_save_floorplan_from_post')
            ? btb_save_floorplan_from_post($conn, $_POST)
            : ['success' => false, 'error' => 'btb_save_floorplan_from_post missing'];
        ob_end_clean();
        echo json_encode($result);
    } catch (Throwable $e) {
        error_log('save_floorplan: Fatal error - ' . $e->getMessage());
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Fatal error: ' . $e->getMessage(),
            'error_type' => get_class($e),
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine(),
        ]);
    }
    exit;
}

if ($action === 'get_guest_reviews') {
    header('Content-Type: application/json; charset=utf-8');
    $data = function_exists('btb_guest_reviews_admin_api_data') ? btb_guest_reviews_admin_api_data($conn) : [
        'section_title' => 'Guest reviews',
        'section_subtitle' => 'What recent guests have shared on Vrbo and Airbnb.',
        'vrbo' => [],
        'airbnb' => [],
    ];
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'save_guest_reviews') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $result = function_exists('btb_save_guest_reviews_from_post')
            ? btb_save_guest_reviews_from_post($conn, $_POST)
            : ['success' => false, 'error' => 'btb_save_guest_reviews_from_post missing'];
        echo json_encode($result);
    } catch (Throwable $e) {
        error_log('save_guest_reviews: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Fatal error: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'get_booking_success_banner') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    $data = function_exists('btb_booking_success_banner_api_data')
        ? btb_booking_success_banner_api_data($conn)
        : array_merge(
            [
                'heading' => 'Your booking has been submitted!',
                'paragraph' => "We've sent you a confirmation email. Once your booking is approved, you'll be able to proceed with the payment.\n\nYou can also make changes to your booking in your personal account.",
                'button_label' => 'My Account',
                'button_url' => 'dashboard.html',
            ],
            function_exists('btb_booking_success_auth_login_defaults') ? btb_booking_success_auth_login_defaults() : [
                'auth_login_message' => "Welcome back!\n\nAll your bookings are available in your personal account.\n\nYou can find it in the menu in the top right corner of the site",
                'auth_login_close_label' => 'Close',
                'auth_login_account_label' => 'To my account',
                'auth_login_account_url' => 'dashboard.html',
            ],
        );
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'save_booking_success_banner') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $result = function_exists('btb_save_booking_success_banner_from_post')
            ? btb_save_booking_success_banner_from_post($conn, $_POST)
            : ['success' => false, 'error' => 'btb_save_booking_success_banner_from_post missing'];
        echo json_encode($result);
    } catch (Throwable $e) {
        error_log('save_booking_success_banner: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Fatal error: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'get_email_templates') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    $data = function_exists('btb_email_templates_api_data')
        ? btb_email_templates_api_data($conn)
        : [
            'templates' => [],
            'branding' => function_exists('btb_email_branding_api_data')
                ? btb_email_branding_api_data($conn)
                : (function_exists('btb_email_branding_defaults') ? btb_email_branding_defaults() : []),
        ];
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'save_email_template') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $result = function_exists('btb_save_email_template_from_post')
            ? btb_save_email_template_from_post($conn, $_POST)
            : ['success' => false, 'error' => 'btb_save_email_template_from_post missing'];
        echo json_encode($result);
    } catch (Throwable $e) {
        error_log('save_email_template: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Fatal error: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'save_email_branding') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $result = function_exists('btb_save_email_branding_from_post')
            ? btb_save_email_branding_from_post($conn, $_POST)
            : ['success' => false, 'error' => 'btb_save_email_branding_from_post missing'];
        echo json_encode($result);
    } catch (Throwable $e) {
        error_log('save_email_branding: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Fatal error: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'get_email_delivery_overview') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    require_once __DIR__ . '/email_service.php';
    $data = function_exists('btb_email_delivery_overview')
        ? btb_email_delivery_overview($conn)
        : [];
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'get_email_delivery_log') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    require_once __DIR__ . '/email_service.php';
    $limit = intval($_GET['limit'] ?? $_POST['limit'] ?? 50);
    $offset = intval($_GET['offset'] ?? $_POST['offset'] ?? 0);
    $data = function_exists('btb_email_delivery_log')
        ? btb_email_delivery_log($conn, $limit, $offset)
        : ['rows' => [], 'total' => 0];
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'retry_email_delivery') {
    header('Content-Type: application/json; charset=utf-8');
    require_once __DIR__ . '/email_service.php';
    $id = intval($_POST['id'] ?? 0);
    try {
        $result = function_exists('btb_email_delivery_retry')
            ? btb_email_delivery_retry($conn, $id)
            : ['success' => false, 'error' => 'btb_email_delivery_retry missing'];
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('retry_email_delivery: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Fatal error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'get_my_bookings_pricing') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    $data = function_exists('btb_my_bookings_pricing_api_data')
        ? btb_my_bookings_pricing_api_data($conn)
        : [
            'cleaning_label' => 'Cleaning fee',
            'cleaning_amount_cad' => 60.0,
            'cleaning_kelder_amount_cad' => 100.0,
            'pets_label' => 'Dogs',
            'pets_max_qty' => 2,
            'pets_amount_per_dog_cad' => 75.0,
            'tax1_label' => 'GST',
            'tax1_percent' => 0.0,
            'tax2_label' => 'PST',
            'tax2_percent' => 0.0,
        ];
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'save_my_bookings_pricing') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $result = function_exists('btb_save_my_bookings_pricing_from_post')
            ? btb_save_my_bookings_pricing_from_post($conn, $_POST)
            : ['success' => false, 'error' => 'btb_save_my_bookings_pricing_from_post missing'];
        echo json_encode($result);
    } catch (Throwable $e) {
        error_log('save_my_bookings_pricing: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Fatal error: ' . $e->getMessage()]);
    }
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
    if (function_exists('btb_ensure_special_block2_columns')) {
        btb_ensure_special_block2_columns($conn);
    }
    if (function_exists('btb_ensure_special_addon_panels_json_column')) {
        btb_ensure_special_addon_panels_json_column($conn);
    }
    if (function_exists('btb_ensure_booking_button_label_columns')) {
        btb_ensure_booking_button_label_columns($conn);
    }
    if (function_exists('btb_dual_write_post_keys_to_table_id1')) {
        btb_dual_write_post_keys_to_table_id1($conn, 'rooms_settings', ['room_book_now_button_label']);
    }
    if (isset($_POST['special_addon_panels_json']) && function_exists('btb_special_addon_panels_normalize_json_string')) {
        $_POST['special_addon_panels_json'] = btb_special_addon_panels_normalize_json_string(
            (string) ($_POST['special_addon_panels_json'] ?? '[]')
        );
    }
    btb_sync_room_price_legacy_fields_from_post($_POST);
    
    // Note: rooms_title and rooms_subtitle columns must be added manually using add_rooms_title_fields.php
    // We don't add them automatically here to avoid "Row size too large" errors
    
    // Build dynamic UPDATE query based on provided fields
    $fields = [];
    $values = [];
    $types = '';
    
    $exploreOpts = ['explore_settings_saved' => false, 'explore_parks_saved' => false, 'explore_community_extra_saved' => false];
    if (function_exists('btb_dual_write_explore_canonical_from_post')) {
        $exploreOpts = btb_dual_write_explore_canonical_from_post($conn, $fields, $values, $types);
    }
    $exploreSettingsSaved = $exploreOpts['explore_settings_saved'];
    $exploreParksSaved = $exploreOpts['explore_parks_saved'];
    $exploreCommunityExtraSaved = $exploreOpts['explore_community_extra_saved'];
    $exploreSettingsTableExists = function_exists('btb_db_table_exists') && btb_db_table_exists($conn, 'explore_settings');
    $exploreParksTableExists = function_exists('btb_db_table_exists') && btb_db_table_exists($conn, 'explore_parks_settings');

    /** True when POST was persisted only to canonical section tables (no content_settings UPDATE). */
    $canonicalTablesWritten = false;

    
    // Homepage fields (skip content_settings when homepage_settings owns the column — dual-write fills canonical table)
    $homepageSettingsTableExists = false;
    $homepageTblChk = $conn->query("SHOW TABLES LIKE 'homepage_settings'");
    if ($homepageTblChk && $homepageTblChk->num_rows > 0) {
        $homepageSettingsTableExists = true;
    }
    if (isset($_POST['homepage_description'])) {
        if (!($homepageSettingsTableExists && function_exists('dbTableHasColumn') && dbTableHasColumn($conn, 'homepage_settings', 'homepage_description'))) {
            $fields[] = 'homepage_description = ?';
            $values[] = $_POST['homepage_description'];
            $types .= 's';
        } else {
            error_log('save_content: Skipping homepage_description — column on homepage_settings (canonical)');
        }
    }
    if (isset($_POST['homepage_subtitle'])) {
        if (!($homepageSettingsTableExists && function_exists('dbTableHasColumn') && dbTableHasColumn($conn, 'homepage_settings', 'homepage_subtitle'))) {
            $fields[] = 'homepage_subtitle = ?';
            $values[] = $_POST['homepage_subtitle'];
            $types .= 's';
        } else {
            error_log('save_content: Skipping homepage_subtitle — column on homepage_settings (canonical)');
        }
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
    $contactSettingsTableExists = false;
    $contactTblChk = $conn->query("SHOW TABLES LIKE 'contact_settings'");
    if ($contactTblChk && $contactTblChk->num_rows > 0) {
        $contactSettingsTableExists = true;
    }
    if (isset($_POST['contact_phone'])) {
        if (!($contactSettingsTableExists && function_exists('dbTableHasColumn') && dbTableHasColumn($conn, 'contact_settings', 'contact_phone'))) {
            $fields[] = 'contact_phone = ?';
            $values[] = $_POST['contact_phone'];
            $types .= 's';
        } else {
            error_log('save_content: Skipping contact_phone — column on contact_settings (canonical)');
        }
    }
    if (isset($_POST['contact_email'])) {
        if (!($contactSettingsTableExists && function_exists('dbTableHasColumn') && dbTableHasColumn($conn, 'contact_settings', 'contact_email'))) {
            $fields[] = 'contact_email = ?';
            $values[] = $_POST['contact_email'];
            $types .= 's';
        } else {
            error_log('save_content: Skipping contact_email — column on contact_settings (canonical)');
        }
    }
    if (isset($_POST['contact_address'])) {
        if (!($contactSettingsTableExists && function_exists('dbTableHasColumn') && dbTableHasColumn($conn, 'contact_settings', 'contact_address'))) {
            $fields[] = 'contact_address = ?';
            $values[] = $_POST['contact_address'];
            $types .= 's';
        } else {
            error_log('save_content: Skipping contact_address — column on contact_settings (canonical)');
        }
    }
    if (isset($_POST['hero_image_url'])) {
        if (!($homepageSettingsTableExists && function_exists('dbTableHasColumn') && dbTableHasColumn($conn, 'homepage_settings', 'hero_image_url'))) {
            $fields[] = 'hero_image_url = ?';
            $values[] = $_POST['hero_image_url'];
            $types .= 's';
        } else {
            error_log('save_content: Skipping hero_image_url — column on homepage_settings (canonical)');
        }
    }
    if (isset($_POST['hero2_image_url'])) {
        if (!($homepageSettingsTableExists && function_exists('dbTableHasColumn') && dbTableHasColumn($conn, 'homepage_settings', 'hero2_image_url'))) {
            $fields[] = 'hero2_image_url = ?';
            $values[] = $_POST['hero2_image_url'];
            $types .= 's';
        } else {
            error_log('save_content: Skipping hero2_image_url — column on homepage_settings (canonical)');
        }
    }

    // Homepage row: single canonical table (not duplicated via tail flush)
    if ($homepageSettingsTableExists && function_exists('dbTableHasColumn')) {
        $hpBatch = [];
        foreach (['homepage_description', 'homepage_subtitle', 'hero_image_url', 'hero2_image_url'] as $hpKey) {
            if (!array_key_exists($hpKey, $_POST)) {
                continue;
            }
            if (!dbTableHasColumn($conn, 'homepage_settings', $hpKey)) {
                continue;
            }
            $hpBatch[$hpKey] = $_POST[$hpKey];
        }
        if (!empty($hpBatch)) {
            $conn->query('INSERT IGNORE INTO homepage_settings (id) VALUES (1)');
            $hpSets = [];
            $hpVals = [];
            $hpTypes = '';
            foreach ($hpBatch as $kH => $vH) {
                $ks = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $kH);
                if ($ks === '') {
                    continue;
                }
                $hpSets[] = "`{$ks}` = ?";
                $hpVals[] = (string) ($vH ?? '');
                $hpTypes .= 's';
            }
            if (!empty($hpSets)) {
                $hpSql = 'UPDATE homepage_settings SET ' . implode(', ', $hpSets) . ' WHERE id = 1';
                $hpSt = $conn->prepare($hpSql);
                if ($hpSt) {
                    $hpSt->bind_param($hpTypes, ...$hpVals);
                    if ($hpSt->execute()) {
                        $canonicalTablesWritten = true;
                    }
                    $hpSt->close();
                    error_log('save_content: homepage_settings batch: ' . implode(', ', array_keys($hpBatch)));
                }
            }
        }
    }

    // Contact row: single canonical table
    if ($contactSettingsTableExists && function_exists('dbTableHasColumn')) {
        $ctBatch = [];
        foreach (['contact_phone', 'contact_email', 'contact_address'] as $ctKey) {
            if (!array_key_exists($ctKey, $_POST)) {
                continue;
            }
            if (!dbTableHasColumn($conn, 'contact_settings', $ctKey)) {
                continue;
            }
            $ctBatch[$ctKey] = $_POST[$ctKey];
        }
        if (!empty($ctBatch)) {
            $conn->query('INSERT IGNORE INTO contact_settings (id) VALUES (1)');
            $ctSets = [];
            $ctVals = [];
            $ctTypes = '';
            foreach ($ctBatch as $kC => $vC) {
                $ks = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $kC);
                if ($ks === '') {
                    continue;
                }
                $ctSets[] = "`{$ks}` = ?";
                $ctVals[] = (string) ($vC ?? '');
                $ctTypes .= 's';
            }
            if (!empty($ctSets)) {
                $ctSql = 'UPDATE contact_settings SET ' . implode(', ', $ctSets) . ' WHERE id = 1';
                $ctSt = $conn->prepare($ctSql);
                if ($ctSt) {
                    $ctSt->bind_param($ctTypes, ...$ctVals);
                    if ($ctSt->execute()) {
                        $canonicalTablesWritten = true;
                    }
                    $ctSt->close();
                    error_log('save_content: contact_settings batch: ' . implode(', ', array_keys($ctBatch)));
                }
            }
        }
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
    $specialFields = array_merge([
        'special_hero_title', 'special_hero_subtitle',
        'special_pools_title', 'special_pools_description_1', 'special_pools_description_2',
        'special_dining_title', 'special_dining_description_1',
        'special_extra_title', 'special_extra_description_1', 'special_extra_description_2',
        'special_offer_title', 'special_offer_main_text', 'special_offer_rooms_cta_label',
        function_exists('btb_special_addon_panels_json_column_name') ? btb_special_addon_panels_json_column_name() : 'special_addon_panels_json',
    ], array_keys(function_exists('btb_special_block2_column_sql_definitions') ? btb_special_block2_column_sql_definitions() : []));
    
    // Massage page content fields
    $massageFields = [
        'massage_hero_title', 'massage_hero_image_url', 'massage_intro',
        'massage_relaxing_title', 'massage_relaxing_description',
        'massage_deep_tissue_title', 'massage_deep_tissue_description',
        'massage_reiki_title', 'massage_reiki_description',
        'massage_sauna_title', 'massage_sauna_description',
        'massage_booking_title', 'massage_booking_intro',
        'massage_book_service_button_label',
        'massage_cart_submit_button_label',
        'massage_pricing_relaxing', 'massage_pricing_deep_tissue', 'massage_pricing_reiki', 'massage_pricing_sauna',
        'mini_hotel_title', 'mini_hotel_description',
        'wellness_stay_gallery_overlay',
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
    // Price on card = from detail page (room_page_settings/merge); room_*_card_price is not entered into the database from the CMS.
    $roomCardFields = [
        'room_basement_card_title', 'room_basement_card_description',
        'room_basement_card_image_url',
        'room_ground_queen_card_title', 'room_ground_queen_card_description',
        'room_ground_queen_card_image_url',
        'room_ground_twin_card_title', 'room_ground_twin_card_description',
        'room_ground_twin_card_image_url',
        'room_second_card_title', 'room_second_card_description',
        'room_second_card_image_url',
        'homepage_book_a_stay_button_label',
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
            // Ensure CMS row exists â€” otherwise UPDATE matches 0 rows, site keeps reading empty values from room_cards
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
                    error_log('save_content: Skipping mini_hotel_description â€” column missing in room_cards_settings. Run add_mini_hotel_description_column.php once.');
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
                    error_log('save_content: âœ“ Successfully saved room card fields to room_cards_settings table');
                } else {
                    error_log('save_content: âœ— Failed to save to room_cards_settings: ' . $stmt->error);
                }
                $stmt->close();
            } else {
                error_log('save_content: âœ— Failed to prepare statement for room_cards_settings: ' . $conn->error);
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
    
    // Track which room card fields to skip in content_settings loops â€” only if they were written to room_cards_settings
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
            error_log('save_content: room_cards_settings save did not complete â€” mirroring to content_settings where columns exist (site can read from either table).');
            foreach ($roomCardFieldsToSave as $field => $fieldValue) {
                if (function_exists('btb_room_field_skip_content_settings_for_room_card_price_column') && btb_room_field_skip_content_settings_for_room_card_price_column($conn, $field)) {
                    error_log("save_content: Fallback skip $field — homepage card price is derived from room detail pricing, not room_*_card_price");
                    continue;
                }
                $columnCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE '" . $conn->real_escape_string($field) . "'");
                if ($columnCheck && $columnCheck->num_rows > 0) {
                    $fields[] = "$field = ?";
                    $values[] = $fieldValue;
                    $types .= 's';
                    error_log("save_content: Fallback: room card field â†’ content_settings: $field");
                }
            }
        }
    }

    // Retreat: write text + hero/location images to retreat_settings first; skip mirroring those columns into content_settings.
    $retreatCollaborationImageSaved = false;
    $retreatFieldsSaved = [];
    $retreatSettingsForSaveExists = false;
    $retreatTblEarly = $conn->query("SHOW TABLES LIKE 'retreat_settings'");
    if ($retreatTblEarly && $retreatTblEarly->num_rows > 0) {
        $retreatSettingsForSaveExists = true;
    }
    if ($retreatSettingsForSaveExists) {
        $retreatKeysForSectionTable = array_merge($retreatFields, [
            'retreat_hero_image_url', 'retreat_forest_image_url', 'retreat_indoor_image_url', 'retreat_theatre_image_url',
            'retreat_collaboration_image_url',
        ]);
        $retreatToSave = [];
        foreach ($retreatKeysForSectionTable as $rf) {
            if (!isset($_POST[$rf])) {
                continue;
            }
            $ccR = $conn->query("SHOW COLUMNS FROM retreat_settings LIKE '" . $conn->real_escape_string($rf) . "'");
            if ($ccR && $ccR->num_rows > 0) {
                $retreatToSave[$rf] = $_POST[$rf];
            }
        }
        if (!empty($retreatToSave)) {
            $chkR = $conn->query('SELECT id FROM retreat_settings WHERE id = 1');
            if (!$chkR || $chkR->num_rows === 0) {
                $conn->query('INSERT INTO retreat_settings (id) VALUES (1)');
            }
            $rSets = [];
            $rVals = [];
            $rTypes = '';
            foreach ($retreatToSave as $kR => $vR) {
                $kSafe = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $kR);
                if ($kSafe === '') {
                    continue;
                }
                $rSets[] = "`{$kSafe}` = ?";
                $rVals[] = (string) $vR;
                $rTypes .= 's';
            }
            if (!empty($rSets)) {
                $rSql = 'UPDATE retreat_settings SET ' . implode(', ', $rSets) . ' WHERE id = 1';
                $rSt = $conn->prepare($rSql);
                if ($rSt) {
                    $rSt->bind_param($rTypes, ...$rVals);
                    if ($rSt->execute()) {
                        $retreatFieldsSaved = array_keys($retreatToSave);
                        if (isset($retreatToSave['retreat_collaboration_image_url'])) {
                            $retreatCollaborationImageSaved = true;
                        }
                        error_log('save_content: Saved retreat fields to retreat_settings: ' . implode(', ', $retreatFieldsSaved));
                    } else {
                        error_log('save_content: retreat_settings batch update failed: ' . $rSt->error);
                    }
                    $rSt->close();
                }
            }
        }
    }
    $retreatSettingsTableExists = $retreatSettingsForSaveExists;
    
    foreach ($retreatFields as $field) {
        // Skip room card fields that were already saved to room_cards_settings
        if (in_array($field, $roomCardFieldsSaved)) {
            continue;
        }
        if (in_array($field, $retreatFieldsSaved, true)) {
            error_log("save_content: Skipping retreat field '$field' - stored in retreat_settings");
            continue;
        }
        if ($retreatSettingsTableExists && function_exists('dbTableHasColumn') && dbTableHasColumn($conn, 'retreat_settings', $field)) {
            error_log("save_content: Skipping retreat field '$field' — column on retreat_settings (canonical, dual-write)");
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
                        strpos($newValue, 'â€¦') !== false ||
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
    
    // Retreat: gallery hover line (per location) â€” store in retreat_settings
    $retreatRsTbl = $conn->query("SHOW TABLES LIKE 'retreat_settings'");
    if ($retreatRsTbl && $retreatRsTbl->num_rows > 0 && function_exists('dbTableHasColumn')) {
        foreach (['retreat_gallery_overlay_forest' => 'TEXT', 'retreat_gallery_overlay_indoor' => 'TEXT', 'retreat_gallery_overlay_theatre' => 'TEXT'] as $rGcol => $rGtype) {
            if (!dbTableHasColumn($conn, 'retreat_settings', $rGcol)) {
                @$conn->query("ALTER TABLE `retreat_settings` ADD COLUMN `{$rGcol}` {$rGtype} NULL");
            }
        }
        $rGUpdate = [];
        foreach (['retreat_gallery_overlay_forest', 'retreat_gallery_overlay_indoor', 'retreat_gallery_overlay_theatre'] as $rGk) {
            if (array_key_exists($rGk, $_POST)) {
                $rGUpdate[$rGk] = $_POST[$rGk];
            }
        }
        if (!empty($rGUpdate)) {
            $rGChk = $conn->query("SELECT id FROM retreat_settings WHERE id = 1");
            if (!$rGChk || $rGChk->num_rows === 0) {
                $conn->query("INSERT INTO retreat_settings (id) VALUES (1)");
            }
            $rGSets = [];
            $rGVals = [];
            $rGTypes = '';
            foreach ($rGUpdate as $kG => $vG) {
                $rGSets[] = "`{$kG}` = ?";
                $rGVals[] = $vG;
                $rGTypes .= 's';
            }
            $rGSql = "UPDATE retreat_settings SET " . implode(', ', $rGSets) . " WHERE id = 1";
            $rGSt = $conn->prepare($rGSql);
            if ($rGSt) {
                $rGSt->bind_param($rGTypes, ...$rGVals);
                if ($rGSt->execute()) {
                    error_log('save_content: Saved retreat gallery overlay fields: ' . implode(', ', array_keys($rGUpdate)));
                } else {
                    error_log('save_content: retreat gallery overlay update failed: ' . $rGSt->error);
                }
                $rGSt->close();
            }
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
                        // Mirror into content_settings when columns exist so reads never resurrect stale text
                        // after a clear, and merge_special_settings_into_data can safely skip empty canonical values.
                        foreach ($specialFieldsSaved as $mirrorField) {
                            if (!in_array($mirrorField, $specialFields, true)) {
                                continue;
                            }
                            $mf = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $mirrorField);
                            if ($mf === '') {
                                continue;
                            }
                            $csCol = $conn->query("SHOW COLUMNS FROM content_settings LIKE '" . $conn->real_escape_string($mf) . "'");
                            if (!$csCol || $csCol->num_rows === 0) {
                                continue;
                            }
                            if (!array_key_exists($mirrorField, $specialFieldsToSave)) {
                                continue;
                            }
                            $mirrorVal = (string) ($specialFieldsToSave[$mirrorField] ?? '');
                            $mirrorSql = "UPDATE content_settings SET `{$mf}` = ? WHERE id = 1";
                            $mirrorSt = $conn->prepare($mirrorSql);
                            if ($mirrorSt) {
                                $mirrorSt->bind_param('s', $mirrorVal);
                                if ($mirrorSt->execute()) {
                                    error_log("save_content: Mirrored special field to content_settings: {$mirrorField}");
                                } else {
                                    error_log("save_content: Mirror to content_settings failed for {$mirrorField}: " . $mirrorSt->error);
                                }
                                $mirrorSt->close();
                            }
                        }
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
        if ($specialTableExists && function_exists('dbTableHasColumn') && dbTableHasColumn($conn, 'special_settings', $field)) {
            error_log("save_content: Skipping special field '$field' — column on special_settings (canonical, dual-write)");
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

    $wellnessTableExists = false;
    $wellnessTblChkEarly = $conn->query("SHOW TABLES LIKE 'wellness_settings'");
    if ($wellnessTblChkEarly && $wellnessTblChkEarly->num_rows > 0) {
        $wellnessTableExists = true;
    }

    // Massage: single batch into massage_settings (canonical — tail flush does not repeat this table)
    $massageSettingsFieldsSaved = [];
    if ($massageTableExists && function_exists('btb_massage_service_cards_normalize_json_string') && function_exists('btb_massage_service_cards_json_column_name')) {
        $mj = btb_massage_service_cards_json_column_name();
        if (isset($_POST[$mj])) {
            if (function_exists('btb_ensure_massage_service_cards_json_column')) {
                btb_ensure_massage_service_cards_json_column($conn);
            }
            $_POST[$mj] = btb_massage_service_cards_normalize_json_string((string) ($_POST[$mj] ?? ''));
        }
    }
    if ($massageTableExists && function_exists('btb_cms_massage_dual_write_post_keys')) {
        $massageKeys = btb_cms_massage_dual_write_post_keys();
        $massageToSave = [];
        foreach ($massageKeys as $mk) {
            if (!array_key_exists($mk, $_POST)) {
                continue;
            }
            if (!function_exists('dbTableHasColumn') || !dbTableHasColumn($conn, 'massage_settings', $mk)) {
                continue;
            }
            $massageToSave[$mk] = $_POST[$mk];
        }
        if (!empty($massageToSave)) {
            $chkM = $conn->query('SELECT id FROM massage_settings WHERE id = 1');
            if (!$chkM || $chkM->num_rows === 0) {
                $conn->query('INSERT INTO massage_settings (id) VALUES (1)');
            }
            $mSets = [];
            $mVals = [];
            $mTypes = '';
            foreach ($massageToSave as $kM => $vM) {
                $kSafe = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $kM);
                if ($kSafe === '') {
                    continue;
                }
                $mSets[] = "`{$kSafe}` = ?";
                $mVals[] = (string) ($vM ?? '');
                $mTypes .= 's';
            }
            if (!empty($mSets)) {
                $mSql = 'UPDATE massage_settings SET ' . implode(', ', $mSets) . ' WHERE id = 1';
                $mSt = $conn->prepare($mSql);
                if ($mSt) {
                    $mSt->bind_param($mTypes, ...$mVals);
                    if ($mSt->execute()) {
                        $massageSettingsFieldsSaved = array_keys($massageToSave);
                        $canonicalTablesWritten = true;
                        error_log('save_content: Saved massage_settings fields: ' . implode(', ', $massageSettingsFieldsSaved));
                    } else {
                        error_log('save_content: massage_settings batch failed: ' . $mSt->error);
                    }
                    $mSt->close();
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
        
        // Skip massage fields already persisted to massage_settings in batch
        if (in_array($field, $massageSettingsFieldsSaved, true)) {
            error_log("save_content: Skipping massage field '$field' - already saved to massage_settings");
            continue;
        }
        if ($massageTableExists && function_exists('dbTableHasColumn') && dbTableHasColumn($conn, 'massage_settings', $field)) {
            error_log("save_content: Skipping massage field '$field' - column exists on massage_settings (canonical)");
            continue;
        }
        if ($wellnessTableExists && $field === 'wellness_stay_gallery_overlay' && function_exists('dbTableHasColumn') && dbTableHasColumn($conn, 'wellness_settings', $field)) {
            error_log("save_content: Skipping massage field '$field' — column on wellness_settings (canonical)");
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
            } elseif (!$columnExists && $field === 'wellness_stay_gallery_overlay') {
                $columnType = 'TEXT DEFAULT NULL';
                error_log("save_content: Column '$field' does not exist. Attempting to create (wellness mini-hotel gallery hover) with type '$columnType'");
                try {
                    $alterResult = $conn->query("ALTER TABLE content_settings ADD COLUMN wellness_stay_gallery_overlay $columnType");
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
        if (function_exists('btb_room_field_skip_content_settings_for_room_pricing') && btb_room_field_skip_content_settings_for_room_pricing($conn, $field)) {
            error_log("save_content: Skipping room_second field '$field' — stored in room_page_settings (canonical)");
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
        if (function_exists('btb_room_field_skip_content_settings_for_room_pricing') && btb_room_field_skip_content_settings_for_room_pricing($conn, $field)) {
            error_log("save_content: Skipping room_ground_twin field '$field' — stored in room_page_settings (canonical)");
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
        if (function_exists('btb_room_field_skip_content_settings_for_room_pricing') && btb_room_field_skip_content_settings_for_room_pricing($conn, $field)) {
            error_log("save_content: Skipping room_ground_queen field '$field' — stored in room_page_settings (canonical)");
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
        if (function_exists('btb_room_field_skip_content_settings_for_room_pricing') && btb_room_field_skip_content_settings_for_room_pricing($conn, $field)) {
            error_log("save_content: Skipping room_basement field '$field' — stored in room_page_settings (canonical)");
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
    
    // Wellness: batch into wellness_settings (canonical)
    $wellnessSettingsFieldsSaved = [];
    if ($wellnessTableExists && function_exists('btb_cms_wellness_dual_write_post_keys')) {
        $wellnessToSave = [];
        foreach (btb_cms_wellness_dual_write_post_keys() as $wk) {
            if (!array_key_exists($wk, $_POST)) {
                continue;
            }
            if (!function_exists('dbTableHasColumn') || !dbTableHasColumn($conn, 'wellness_settings', $wk)) {
                continue;
            }
            $wellnessToSave[$wk] = $_POST[$wk];
        }
        if (!empty($wellnessToSave)) {
            $chkW = $conn->query('SELECT id FROM wellness_settings WHERE id = 1');
            if (!$chkW || $chkW->num_rows === 0) {
                $conn->query('INSERT INTO wellness_settings (id) VALUES (1)');
            }
            $wSets = [];
            $wVals = [];
            $wTypes = '';
            foreach ($wellnessToSave as $kW => $vW) {
                $kSafe = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $kW);
                if ($kSafe === '') {
                    continue;
                }
                $wSets[] = "`{$kSafe}` = ?";
                $wVals[] = (string) ($vW ?? '');
                $wTypes .= 's';
            }
            if (!empty($wSets)) {
                $wSql = 'UPDATE wellness_settings SET ' . implode(', ', $wSets) . ' WHERE id = 1';
                $wSt = $conn->prepare($wSql);
                if ($wSt) {
                    $wSt->bind_param($wTypes, ...$wVals);
                    if ($wSt->execute()) {
                        $wellnessSettingsFieldsSaved = array_keys($wellnessToSave);
                        $canonicalTablesWritten = true;
                        error_log('save_content: Saved wellness_settings fields: ' . implode(', ', $wellnessSettingsFieldsSaved));
                    } else {
                        error_log('save_content: wellness_settings batch failed: ' . $wSt->error);
                    }
                    $wSt->close();
                }
            }
        }
    }

    foreach ($wellnessFields as $field) {
        // Skip room card fields that were already saved to room_cards_settings
        if (in_array($field, $roomCardFieldsSaved)) {
            continue;
        }
        if (in_array($field, $wellnessSettingsFieldsSaved, true)) {
            error_log("save_content: Skipping wellness field '$field' - already saved to wellness_settings");
            continue;
        }
        if ($wellnessTableExists && function_exists('dbTableHasColumn') && dbTableHasColumn($conn, 'wellness_settings', $field)) {
            error_log("save_content: Skipping wellness field '$field' - column exists on wellness_settings (canonical)");
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

    $aboutSettingsTableExists = false;
    $aboutSettingsTblChk = $conn->query("SHOW TABLES LIKE 'about_settings'");
    if ($aboutSettingsTblChk && $aboutSettingsTblChk->num_rows > 0) {
        $aboutSettingsTableExists = true;
    }

    if (isset($_POST['about_contact_form_title'])) {
        $skipAcft = $aboutSettingsTableExists && function_exists('dbTableHasColumn') && dbTableHasColumn($conn, 'about_settings', 'about_contact_form_title');
        if (!$skipAcft) {
            $acftCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE 'about_contact_form_title'");
            if ($acftCheck && $acftCheck->num_rows === 0) {
                if ($conn->query("ALTER TABLE content_settings ADD COLUMN about_contact_form_title VARCHAR(255) DEFAULT NULL")) {
                    error_log('save_content: Added column about_contact_form_title');
                } else {
                    error_log('save_content: Could not add about_contact_form_title: ' . $conn->error);
                }
            }
        } else {
            error_log('save_content: Skipping ALTER about_contact_form_title — column on about_settings (canonical)');
        }
    }
    if (isset($_POST['about_contact_form_description'])) {
        $skipAcfd = $aboutSettingsTableExists && function_exists('dbTableHasColumn') && dbTableHasColumn($conn, 'about_settings', 'about_contact_form_description');
        if (!$skipAcfd) {
            $acfdCheck = $conn->query("SHOW COLUMNS FROM content_settings LIKE 'about_contact_form_description'");
            if ($acfdCheck && $acfdCheck->num_rows === 0) {
                if ($conn->query("ALTER TABLE content_settings ADD COLUMN about_contact_form_description TEXT NULL")) {
                    error_log('save_content: Added column about_contact_form_description');
                } else {
                    error_log('save_content: Could not add about_contact_form_description: ' . $conn->error);
                }
            }
        } else {
            error_log('save_content: Skipping ALTER about_contact_form_description — column on about_settings (canonical)');
        }
    }

    // About: batch into about_settings (canonical)
    $aboutFieldsSaved = [];
    if ($aboutSettingsTableExists && function_exists('btb_cms_about_dual_write_post_keys')) {
        $aboutToSave = [];
        foreach (btb_cms_about_dual_write_post_keys() as $ak) {
            if (!array_key_exists($ak, $_POST)) {
                continue;
            }
            if (!empty($exploreParksTableExists) && $exploreParksTableExists && function_exists('btb_explore_parks_post_field_names') && in_array($ak, btb_explore_parks_post_field_names(), true)) {
                continue;
            }
            if (!function_exists('dbTableHasColumn') || !dbTableHasColumn($conn, 'about_settings', $ak)) {
                continue;
            }
            $aboutToSave[$ak] = $_POST[$ak];
        }
        if (!empty($aboutToSave)) {
            $chkAb = $conn->query('SELECT id FROM about_settings WHERE id = 1');
            if (!$chkAb || $chkAb->num_rows === 0) {
                $conn->query('INSERT INTO about_settings (id) VALUES (1)');
            }
            $abSets = [];
            $abVals = [];
            $abTypes = '';
            foreach ($aboutToSave as $kAb => $vAb) {
                $kSafe = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $kAb);
                if ($kSafe === '') {
                    continue;
                }
                $abSets[] = "`{$kSafe}` = ?";
                $abVals[] = (string) ($vAb ?? '');
                $abTypes .= 's';
            }
            if (!empty($abSets)) {
                $abSql = 'UPDATE about_settings SET ' . implode(', ', $abSets) . ' WHERE id = 1';
                $abSt = $conn->prepare($abSql);
                if ($abSt) {
                    $abSt->bind_param($abTypes, ...$abVals);
                    if ($abSt->execute()) {
                        $aboutFieldsSaved = array_keys($aboutToSave);
                        $canonicalTablesWritten = true;
                        error_log('save_content: Saved about_settings fields: ' . implode(', ', $aboutFieldsSaved));
                    } else {
                        error_log('save_content: about_settings batch failed: ' . $abSt->error);
                    }
                    $abSt->close();
                }
            }
        }
    }

    foreach ($aboutFields as $field) {
        // Skip room card fields that were already saved to room_cards_settings
        if (in_array($field, $roomCardFieldsSaved)) {
            continue;
        }
        if (!empty($exploreParksTableExists) && $exploreParksTableExists && function_exists('btb_explore_parks_post_field_names') && in_array($field, btb_explore_parks_post_field_names(), true)) {
            continue;
        }
        if (in_array($field, $aboutFieldsSaved, true)) {
            error_log("save_content: Skipping about field '$field' - already saved to about_settings");
            continue;
        }
        if ($aboutSettingsTableExists && function_exists('dbTableHasColumn') && dbTableHasColumn($conn, 'about_settings', $field)) {
            error_log("save_content: Skipping about field '$field' - column exists on about_settings (canonical)");
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
    
    // Retreat collaboration image (fallback if batch above did not persist it)
    if ($retreatSettingsTableExists && !$retreatCollaborationImageSaved && isset($_POST['retreat_collaboration_image_url'])) {
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
            error_log("save_content: retreat_settings missing column retreat_collaboration_image_url â€” run add_retreat_collaboration_image_column.php");
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
        
        if (function_exists('btb_room_field_skip_content_settings_for_room_pricing') && btb_room_field_skip_content_settings_for_room_pricing($conn, $field)) {
            error_log("save_content: Skipping $field in imageFields (room_page_settings canonical)");
            continue;
        }
        
        if (in_array($field, $retreatFieldsSaved, true)) {
            error_log("save_content: Skipping $field in content_settings (already written to retreat_settings)");
            continue;
        }
        if ($retreatSettingsTableExists && preg_match('/^retreat_.*_image_url$/', $field) && function_exists('dbTableHasColumn') && dbTableHasColumn($conn, 'retreat_settings', $field)) {
            error_log("save_content: Skipping $field in content_settings (retreat_settings canonical)");
            continue;
        }
        
        if ($massageTableExists && strpos($field, 'massage_') === 0 && strpos($field, '_image_url') !== false && function_exists('dbTableHasColumn') && dbTableHasColumn($conn, 'massage_settings', $field)) {
            error_log("save_content: Skipping $field in content_settings (massage_settings canonical)");
            continue;
        }
        if (!empty($wellnessTableExists) && in_array($field, ['wellness_massage_image_url', 'wellness_yoga_image_url'], true) && function_exists('dbTableHasColumn') && dbTableHasColumn($conn, 'wellness_settings', $field)) {
            error_log("save_content: Skipping $field in content_settings (wellness_settings canonical)");
            continue;
        }
        if (!empty($aboutSettingsTableExists) && strpos($field, 'about_') === 0 && strpos($field, '_image_url') !== false && $field !== 'about_parks_hero_image_url' && function_exists('dbTableHasColumn') && dbTableHasColumn($conn, 'about_settings', $field)) {
            error_log("save_content: Skipping $field in content_settings (about_settings canonical)");
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
        if (!empty($specialTableExists) && strpos($field, 'special_') === 0 && strpos($field, '_image_url') !== false && function_exists('dbTableHasColumn') && dbTableHasColumn($conn, 'special_settings', $field)) {
            error_log("save_content: Skipping $field in content_settings (special_settings canonical)");
            continue;
        }
        if ($roomCardsTableExists && $field === 'mini_hotel_image_url' && function_exists('dbTableHasColumn') && dbTableHasColumn($conn, 'room_cards_settings', 'mini_hotel_image_url')) {
            error_log("save_content: Skipping $field in content_settings (room_cards_settings canonical)");
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
    
    $saveContentPostLooksLikeCms = false;
    foreach (array_keys($_POST) as $_cmsKey) {
        if (!is_string($_cmsKey) || $_cmsKey === '') {
            continue;
        }
        if (strpos($_cmsKey, 'massage_') === 0 || strpos($_cmsKey, 'wellness_') === 0 || strpos($_cmsKey, 'about_') === 0
            || strpos($_cmsKey, 'retreat_') === 0 || strpos($_cmsKey, 'special_') === 0
            || strpos($_cmsKey, 'homepage_') === 0 || strpos($_cmsKey, 'contact_') === 0
            || strpos($_cmsKey, 'explore_') === 0 || strpos($_cmsKey, 'room_') === 0
            || strpos($_cmsKey, 'mini_hotel') === 0 || $_cmsKey === 'hero_image_url' || $_cmsKey === 'hero2_image_url') {
            $saveContentPostLooksLikeCms = true;
            break;
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
    if (empty($fields) && ($roomsSaved || $roomCardsSaved || $retreatCollaborationImageSaved || !empty($retreatFieldsSaved) || $exploreSettingsSaved || $exploreParksSaved || $exploreCommunityExtraSaved || $canonicalTablesWritten || !empty($specialFieldsSaved))) {
        error_log('save_content: Only rooms/room cards / retreat collaboration image / explore_settings / explore_parks / explore_community_extra were saved, no content_settings update needed');
        if (function_exists('btb_dual_write_phase1_canonical_from_post')) {
            btb_dual_write_phase1_canonical_from_post($conn);
        }
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
    
    // If no fields to update and rooms/room cards weren't saved, return error (unless room_page_settings tail or CMS-only POST)
    if (empty($fields) && !$roomsSaved && !$roomCardsSaved && !$retreatCollaborationImageSaved && empty($retreatFieldsSaved) && !$exploreSettingsSaved && !$exploreParksSaved && !$exploreCommunityExtraSaved && !$canonicalTablesWritten && empty($specialFieldsSaved)) {
        if ($saveContentPostLooksLikeCms && function_exists('btb_dual_write_phase1_canonical_from_post')) {
            error_log('save_content: No content_settings rows to update; writing Phase-1 canonical tables from POST only');
            btb_dual_write_phase1_canonical_from_post($conn);
            ob_end_clean();
            echo json_encode(['success' => true]);
            exit;
        }
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
        if (function_exists('btb_dual_write_phase1_canonical_from_post')) {
            btb_dual_write_phase1_canonical_from_post($conn);
        }
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

// Batch audit: image path + image_type → heavy / limits (admin badges). Same auth exposure as get_content.
if ($action === 'audit_image_assets') {
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    $items = null;
    if (!empty($_POST['items'])) {
        $decoded = json_decode((string) $_POST['items'], true);
        $items = is_array($decoded) ? $decoded : null;
    }
    if (!is_array($items) || count($items) === 0) {
        $raw = file_get_contents('php://input');
        if ($raw) {
            $j = json_decode($raw, true);
            if (is_array($j) && isset($j['items']) && is_array($j['items'])) {
                $items = $j['items'];
            }
        }
    }
    if (!is_array($items)) {
        echo json_encode(['success' => false, 'error' => 'Invalid items']);
        exit;
    }
    if (count($items) > 100) {
        echo json_encode(['success' => false, 'error' => 'Too many items (max 100)']);
        exit;
    }
    $out = [];
    foreach ($items as $it) {
        if (!is_array($it)) {
            continue;
        }
        $path = $it['path'] ?? $it['url'] ?? '';
        $type = $it['imageType'] ?? $it['image_type'] ?? '';
        $key = (string) $path . '|' . (string) $type;
        $out[$key] = btb_admin_audit_image_asset($path, $type);
    }
    echo json_encode(['success' => true, 'data' => $out]);
    exit;
}

// Include API handlers after action is defined
require_once 'floorplan_api.php';
require_once 'booking_api.php';
require_once 'host_chat_api.php';
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
        // ÐžÐ±Ð½Ð¾Ð²Ð»ÐµÐ½Ð¸Ðµ ÑÑƒÑ‰ÐµÑÑ‚Ð²ÑƒÑŽÑ‰ÐµÐ¹ ÐºÐ¾Ð¼Ð½Ð°Ñ‚Ñ‹
        $stmt = $conn->prepare("UPDATE rooms SET name = ?, price = ?, capacity = ?, type = ?, description = ? WHERE id = ?");
        $stmt->bind_param("siissi", $name, $price, $capacity, $type, $description, $room_id);
    } else {
        // Ð¡Ð¾Ð·Ð´Ð°Ð½Ð¸Ðµ Ð½Ð¾Ð²Ð¾Ð¹ ÐºÐ¾Ð¼Ð½Ð°Ñ‚Ñ‹
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

echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);
$conn->close();
exit;
