<?php
// Server-Side Rendering for Homepage with Wellness Experiences
require_once 'common.php';

// Public HTML: short CDN-friendly cache (content still updates via must-revalidate)
if (function_exists('btb_public_cms_cache_headers')) {
    btb_public_cms_cache_headers(120);
} else {
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
}

// Load content from database
$content = fetchOne($conn, "SELECT * FROM content_settings WHERE id = 1");
if (!$content) {
    $content = []; // Ensure $content is always an array
}
btb_merge_phase1_canonical_into_content_row($conn, $content);

// Load wellness images from dedicated table if available
$wellnessImages = [];
$wellnessTableCheck = $conn->query("SHOW TABLES LIKE 'wellness_settings'");
if ($wellnessTableCheck && $wellnessTableCheck->num_rows > 0) {
    $wellnessImages = fetchOne($conn, "SELECT * FROM wellness_settings WHERE id = 1");
    if (!$wellnessImages) {
        $wellnessImages = [];
    }
}

// Extract Home Page content with fallback values
$homepageDescription = safeOutputWithBreaks(
    $content['homepage_description'] ?? '',
    btb_default_text(
        'content_settings.homepage_description',
        'Back to Base is a countryside guesthouse in Nelson, British Columbia, where you can rent a room or book the entire house for a vacation, retreat, or wellness getaway. Guests can restore their energy with a relaxing massage and enjoy comfortable accommodation surrounded by mountains and forest.'
    )
);
$homepageSubtitle = safeOutputWithBreaks(
    $content['homepage_subtitle'] ?? '',
    btb_default_text(
        'content_settings.homepage_subtitle',
        'Our cozy rooms and inspiring atmosphere make this the perfect place for solitude, meditation, yoga retreats, or simply a peaceful holiday in nature.'
    )
);

// Rooms section title and subtitle - try rooms_settings table first, fall back to content_settings
$roomsTitle = btb_default_text('rooms_settings.rooms_title', 'Choose your room');
$roomsSubtitle = '';
$roomsTableCheck = $conn->query("SHOW TABLES LIKE 'rooms_settings'");
if ($roomsTableCheck && $roomsTableCheck->num_rows > 0) {
    $roomsData = fetchOne($conn, "SELECT * FROM rooms_settings WHERE id = 1");
    if ($roomsData) {
        $roomsTitle = safeOutput($roomsData['rooms_title'] ?? '', btb_default_text('rooms_settings.rooms_title', 'Choose your room'));
        $roomsSubtitle = safeOutputWithBreaks($roomsData['rooms_subtitle'] ?? '', '');
    }
} else {
    // Fall back to content_settings for backward compatibility
    $roomsTitle = safeOutput($content['rooms_title'] ?? '', btb_default_text('content_settings.rooms_title', 'Choose your room'));
    $roomsSubtitle = safeOutputWithBreaks($content['rooms_subtitle'] ?? '', '');
}

// Room cards content - try room_cards_settings table first, fall back to content_settings
$roomCardData = [];
$roomCardsTableCheck = $conn->query("SHOW TABLES LIKE 'room_cards_settings'");
if ($roomCardsTableCheck && $roomCardsTableCheck->num_rows > 0) {
    $roomCardData = fetchOne($conn, "SELECT * FROM room_cards_settings WHERE id = 1");
    if (!$roomCardData) {
        $roomCardData = [];
    }
} else {
    // Fall back to content_settings for backward compatibility
    $roomCardData = $content;
}

// Helper function for safe output of HTML content (for titles with <br/> tags)
function safeOutputHTML($value, $fallback = '') {
    $text = $value ?? $fallback;
    // Only allow <br> and <br/> tags, escape everything else
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    // Restore <br> tags
    $text = str_replace(['&lt;br&gt;', '&lt;br/&gt;', '&lt;br /&gt;'], '<br/>', $text);
    return $text;
}

// Extract room card content: non-empty from room_cards_settings, otherwise from merged $content (after merge - preserve from content_settings).
$rc = is_array($roomCardData) ? $roomCardData : [];
$roomBasementCardTitle = safeOutputHTML(btb_room_card_field_prefer_non_empty($rc, $content, 'room_basement_card_title'), btb_default_text('room_cards_settings.room_basement_card_title', 'Loki Suite'));
$roomBasementCardDescription = safeOutputWithBreaks(btb_room_card_field_prefer_non_empty($rc, $content, 'room_basement_card_description'), 'Cozy room next to the home cinema and sauna. Perfect for two.');
$roomBasementCardPrice = btb_room_price_line_html_stored_only($content, 'basement');
$roomBasementCardImageUrl = trim(btb_room_card_field_prefer_non_empty($rc, $content, 'room_basement_card_image_url'));
$roomBasementCardImageUrl = $roomBasementCardImageUrl !== '' ? safeOutput($roomBasementCardImageUrl, '') : '';

$roomGroundQueenCardTitle = safeOutputHTML(btb_room_card_field_prefer_non_empty($rc, $content, 'room_ground_queen_card_title'), btb_default_text('room_cards_settings.room_ground_queen_card_title', 'The Nouk'));
$roomGroundQueenCardDescription = safeOutputWithBreaks(btb_room_card_field_prefer_non_empty($rc, $content, 'room_ground_queen_card_description'), 'Compact, bright room with access to the fireplace lounge.');
$roomGroundQueenCardPrice = btb_room_price_line_html($content, 'ground_queen', btb_room_price_default_line_html('ground_queen'));
$roomGroundQueenCardImageUrl = trim(btb_room_card_field_prefer_non_empty($rc, $content, 'room_ground_queen_card_image_url'));
$roomGroundQueenCardImageUrl = $roomGroundQueenCardImageUrl !== '' ? safeOutput($roomGroundQueenCardImageUrl, '') : '';

$roomGroundTwinCardTitle = safeOutputHTML(btb_room_card_field_prefer_non_empty($rc, $content, 'room_ground_twin_card_title'), btb_default_text('room_cards_settings.room_ground_twin_card_title', 'Vrienden'));
$roomGroundTwinCardDescription = safeOutputWithBreaks(btb_room_card_field_prefer_non_empty($rc, $content, 'room_ground_twin_card_description'), 'Great for friends or colleagues. Close to the kitchen and massage hall.');
$roomGroundTwinCardPrice = btb_room_price_line_html_stored_only($content, 'ground_twin');
$roomGroundTwinCardImageUrl = trim(btb_room_card_field_prefer_non_empty($rc, $content, 'room_ground_twin_card_image_url'));
$roomGroundTwinCardImageUrl = $roomGroundTwinCardImageUrl !== '' ? safeOutput($roomGroundTwinCardImageUrl, '') : '';

$roomSecondCardTitle = safeOutputHTML(btb_room_card_field_prefer_non_empty($rc, $content, 'room_second_card_title'), btb_default_text('room_cards_settings.room_second_card_title', 'Kelder'));
$roomSecondCardDescription = safeOutputWithBreaks(btb_room_card_field_prefer_non_empty($rc, $content, 'room_second_card_description'), 'Separate kitchen and shower, study, and a balcony with lake view.');
$roomSecondCardPrice = btb_room_price_line_html_stored_only($content, 'second');
$roomSecondCardImageUrl = trim(btb_room_card_field_prefer_non_empty($rc, $content, 'room_second_card_image_url'));
$roomSecondCardImageUrl = $roomSecondCardImageUrl !== '' ? safeOutput($roomSecondCardImageUrl, '') : '';

$homepageBookAStayBtn = trim((string)($rc['homepage_book_a_stay_button_label'] ?? $content['homepage_book_a_stay_button_label'] ?? ''));
if ($homepageBookAStayBtn === '') {
    $homepageBookAStayBtn = 'Book a stay';
}
$homepageBookAStayBtn = htmlspecialchars($homepageBookAStayBtn, ENT_QUOTES, 'UTF-8');

$heroImageUrl = isset($content['hero_image_url']) && !empty(trim($content['hero_image_url'])) 
    ? safeOutput($content['hero_image_url'], '') 
    : '';

$hero2ImageUrl = isset($content['hero2_image_url']) && !empty(trim($content['hero2_image_url'])) 
    ? safeOutput($content['hero2_image_url'], '') 
    : '';

// Extract Wellness Experiences content with fallback values
$wellnessTitle = safeOutput($content['wellness_title'] ?? '', 'Wellness Experiences');
$wellnessDescription = safeOutputWithBreaks($content['wellness_description'] ?? '', 'Enhance your stay with optional massage: relaxing or deep tissue sessions with an experienced therapist — an easy way to make your time in the mountains feel even more restorative.');

$wellnessMassageTitle = safeOutput($content['wellness_massage_title'] ?? '', 'Wellness');
$wellnessMassageDescription = safeOutputWithBreaks($content['wellness_massage_description'] ?? '', 'Our guesthouse has a massage room with an experienced therapist who will be happy to make your stay even more enjoyable. Whether you prefer a relaxing massage or a therapeutic deep tissue session — the choice is yours.');
$wellnessMassageImageUrl = '';
if (!empty($wellnessImages) && !empty(trim($wellnessImages['wellness_massage_image_url'] ?? ''))) {
    $wellnessMassageImageUrl = safeOutput($wellnessImages['wellness_massage_image_url'], '');
} elseif (isset($content['wellness_massage_image_url']) && !empty(trim($content['wellness_massage_image_url']))) {
    $wellnessMassageImageUrl = safeOutput($content['wellness_massage_image_url'], '');
}

if (!function_exists('btb_guest_review_stars')) {
    function btb_guest_review_stars(int $rating): string
    {
        $r = max(0, min(5, $rating));
        $s = '';
        for ($i = 1; $i <= 5; $i++) {
            $s .= $i <= $r ? '★' : '☆';
        }
        return $s;
    }
}

if (!function_exists('btb_normalize_guest_reviews_list')) {
    /**
     * @return array<int, array{name: string, text: string, rating: int}>
     */
    function btb_normalize_guest_reviews_list(array $raw): array
    {
        $out = [];
        foreach ($raw as $r) {
            if (!is_array($r)) {
                continue;
            }
            $n = trim((string)($r['name'] ?? ''));
            $t = trim((string)($r['text'] ?? ''));
            if ($n === '' && $t === '') {
                continue;
            }
            $out[] = [
                'name' => $n,
                'text' => $t,
                'rating' => max(1, min(5, (int)($r['rating'] ?? 5))),
            ];
        }
        return $out;
    }
}

$grDef = btb_guest_reviews_default_payload();
$guestReviewsTitle = $grDef['section_title'];
$guestReviewsSubtitle = $grDef['section_subtitle'];
$vrboGuestReviews = $grDef['vrbo'];
$airbnbGuestReviews = $grDef['airbnb'];
if (function_exists('btb_db_table_exists') && btb_db_table_exists($conn, 'guest_reviews_settings')) {
    $d = $content;
    if (trim((string) ($d['section_title'] ?? '')) !== '') {
        $guestReviewsTitle = (string) $d['section_title'];
    }
    if (array_key_exists('section_subtitle', $d) && $d['section_subtitle'] !== null) {
        $guestReviewsSubtitle = (string) $d['section_subtitle'];
    }
    $vJson = json_decode((string) ($d['vrbo_reviews_json'] ?? '[]'), true);
    if (is_array($vJson)) {
        $vrboGuestReviews = btb_normalize_guest_reviews_list($vJson);
    }
    $aJson = json_decode((string) ($d['airbnb_reviews_json'] ?? '[]'), true);
    if (is_array($aJson)) {
        $airbnbGuestReviews = btb_normalize_guest_reviews_list($aJson);
    }
}
if (count($vrboGuestReviews) === 0) {
    $vrboGuestReviews = $grDef['vrbo'];
}
if (count($airbnbGuestReviews) === 0) {
    $airbnbGuestReviews = $grDef['airbnb'];
}
$guestReviewsTitle = safeOutput($guestReviewsTitle, 'Guest reviews');
$guestReviewsSubtitle = safeOutputWithBreaks($guestReviewsSubtitle, $grDef['section_subtitle']);

// Floor plan: same canonical row as phase1 merge (floorplan_settings), sliced for this template
$floorplan = function_exists('btb_floorplan_slice_from_content_row') ? btb_floorplan_slice_from_content_row($content) : [];
if ($floorplan === [] && function_exists('btb_db_table_exists') && btb_db_table_exists($conn, 'floorplan_settings')) {
    $floorplan = fetchOne($conn, "SELECT * FROM floorplan_settings WHERE id = 1") ?: [];
}

// Common areas (floor plan) section title and subtitle
$floorplanTitle = safeOutput($floorplan['floorplan_title'] ?? '', 'Common areas');
$floorplanSubtitle = safeOutputWithBreaks($floorplan['floorplan_subtitle'] ?? '', 'Basement calm, a welcoming main living level, and bright multifunctional rooms for workshops and cinema.');

// Extract Floor Plan content with fallback values
$basementSubtitle = safeOutputWithBreaks($floorplan['basement_subtitle'] ?? '', 'Private floor with a separate entrance.');
$basementDescription = safeOutputWithBreaks($floorplan['basement_description'] ?? '', 'A spacious bedroom with a king-size bed and a small study, a home theater with a fireplace, and a private bathroom featuring a shower and a sauna room.');
$basementImageUrl = isset($floorplan['basement_image_url']) && !empty(trim($floorplan['basement_image_url'])) 
    ? safeOutput($floorplan['basement_image_url'], '') 
    : 'assets/plan.jpg';

$groundSubtitle = safeOutputWithBreaks($floorplan['ground_subtitle'] ?? '', 'Open space with a separate entrance.');
$groundDescription = safeOutputWithBreaks($floorplan['ground_description'] ?? '', 'A large bright hall with a fireplace, a big dining table, a spacious modern kitchen, two rental rooms, a shared bathroom with a bathtub, and a separate room for massage and events.');
$groundImageUrl = isset($floorplan['ground_image_url']) && !empty(trim($floorplan['ground_image_url'])) 
    ? safeOutput($floorplan['ground_image_url'], '') 
    : (isset($floorplan['ground_queen_image']) && !empty(trim($floorplan['ground_queen_image'])) 
        ? safeOutput($floorplan['ground_queen_image'], '') 
        : 'assets/plan.jpg');

$loftSubtitle = safeOutputWithBreaks($floorplan['loft_subtitle'] ?? '', 'Multifunctional spaces & small cinema');
$loftDescription = safeOutputWithBreaks($floorplan['loft_description'] ?? '', 'Bright, adaptable rooms for yoga circles, workshops, and film nights — on the main living level beside the kitchen and hall, with generous windows and blackout curtains when you want the room dark.');
$loftImageUrl = isset($floorplan['loft_image_url']) && !empty(trim($floorplan['loft_image_url'])) 
    ? safeOutput($floorplan['loft_image_url'], '') 
    : 'assets/plan.jpg';

// Plain floor labels for gallery overlay, image alt, and modal captions (matches floor card subtitles when set)
$basementFloorLabelPlain = trim((string) ($floorplan['basement_subtitle'] ?? ''));
if ($basementFloorLabelPlain === '') {
    $basementFloorLabelPlain = 'Private floor with a separate entrance.';
}
$groundFloorLabelPlain = trim((string) ($floorplan['ground_subtitle'] ?? ''));
if ($groundFloorLabelPlain === '') {
    $groundFloorLabelPlain = 'Open space with a separate entrance.';
}
$loftFloorLabelPlain = trim((string) ($floorplan['loft_subtitle'] ?? ''));
if ($loftFloorLabelPlain === '') {
    $loftFloorLabelPlain = 'Multifunctional spaces & small cinema';
}

if (!function_exists('floorplan_gallery_overlay_hook')) {
    /**
     * Warm, inviting CTAs for Common areas floor galleries (plain UTF-8).
     */
    function floorplan_gallery_overlay_hook(string $floorKey, string $labelPlain): string {
        $banks = [
            'basement' => [
                'Fancy a peek downstairs? Tap to open the gallery',
                'Cozy corners down here — come see the photos',
                '{L} — tap through and see every corner',
                'Sauna glow & soft sofas — take a little tour',
            ],
            'ground' => [
                'Where coffee & chatter live — tap to explore',
                'The warm middle of the house — open the gallery',
                '{L} — pull up a chair and browse the snaps',
                'Kitchen, hearth & hello hugs — come take a look',
            ],
            'loft' => [
                'Workshops & movie nights live here — tap to browse',
                '{L} — we saved a friendlier photo tour for you',
                'Roll out mats or dim the lights — come see',
                'Flexible rooms for groups — open the gallery',
            ],
        ];
        $list = $banks[$floorKey] ?? $banks['basement'];
        $i = abs(crc32($floorKey . '|' . $labelPlain)) % count($list);
        $line = str_replace('{L}', $labelPlain, $list[$i]);
        if (function_exists('mb_strlen') && function_exists('mb_substr') && mb_strlen($line) > 90) {
            return mb_substr($line, 0, 87) . '…';
        }
        if (strlen($line) > 90) {
            return substr($line, 0, 87) . '…';
        }
        return $line;
    }
}

$ovB = trim((string)($floorplan['basement_gallery_overlay_text'] ?? ''));
$ovG = trim((string)($floorplan['ground_gallery_overlay_text'] ?? ''));
$ovL = trim((string)($floorplan['loft_gallery_overlay_text'] ?? ''));
$basementFloorGalleryHook = $ovB !== '' ? str_replace('{L}', $basementFloorLabelPlain, $ovB) : floorplan_gallery_overlay_hook('basement', $basementFloorLabelPlain);
$groundFloorGalleryHook = $ovG !== '' ? str_replace('{L}', $groundFloorLabelPlain, $ovG) : floorplan_gallery_overlay_hook('ground', $groundFloorLabelPlain);
$loftFloorGalleryHook = $ovL !== '' ? str_replace('{L}', $loftFloorLabelPlain, $ovL) : floorplan_gallery_overlay_hook('loft', $loftFloorLabelPlain);

// Load galleries for each floor
$basementGallery = [];
if (isset($floorplan['basement_gallery']) && !empty(trim($floorplan['basement_gallery']))) {
    $basementGalleryJson = trim($floorplan['basement_gallery']);
    if ($basementGalleryJson !== '' && $basementGalleryJson !== '[]') {
        $decoded = json_decode($basementGalleryJson, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $basementGallery = $decoded;
        }
    }
}

$groundGallery = [];
if (isset($floorplan['ground_gallery']) && !empty(trim($floorplan['ground_gallery']))) {
    $groundGalleryJson = trim($floorplan['ground_gallery']);
    if ($groundGalleryJson !== '' && $groundGalleryJson !== '[]') {
        $decoded = json_decode($groundGalleryJson, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $groundGallery = $decoded;
        }
    }
}

$loftGallery = [];
if (isset($floorplan['loft_gallery']) && !empty(trim($floorplan['loft_gallery']))) {
    $loftGalleryJson = trim($floorplan['loft_gallery']);
    if ($loftGalleryJson !== '' && $loftGalleryJson !== '[]') {
        $decoded = json_decode($loftGalleryJson, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $loftGallery = $decoded;
        }
    }
}

// Cache buster for images - use current timestamp to force browser to reload images
// This ensures new images from admin panel are displayed immediately without flickering
$cacheBuster = '?v=' . time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
<?php require_once __DIR__ . '/site-head-consent.php'; ?>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="light dark">
  <title>Back to Base — Boutique Retreat in British Columbia</title>
  <?php
  $__seo_title = 'Back to Base — Boutique Retreat in British Columbia';
  $__seo_desc = 'Back to Base — boutique forest retreat in British Columbia, Canada. Rooms for rent, wellness services, yoga, and nature immersion.';
  ?>
  <meta name="description" content="<?php echo htmlspecialchars($__seo_desc, ENT_QUOTES, 'UTF-8'); ?>">
  <?php
  btb_seo_emit_link_and_meta('/', $__seo_title, $__seo_desc, [
      'og_image' => '/assets/about_procter.jpg',
      'json_ld' => btb_seo_default_lodging_json_ld($__seo_desc),
  ]);
  ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="common.css">
  <link rel="stylesheet" href="styles.css">
  <script>
    // UNIVERSAL THEME INITIALIZATION - Works on ALL pages
    // This script runs synchronously in <head> before page render
    // User can land on ANY page first time - theme defaults to 'dark'
    (function() {
      'use strict';
      try {
        // Get saved theme and user preference flag
        const savedTheme = localStorage.getItem('btb_theme');
        const userSetTheme = localStorage.getItem('btb_theme_user') === '1';
        
        // Determine initial theme:
        // - If user explicitly set theme (userSetTheme=true) AND savedTheme is valid, use savedTheme
        // - Otherwise default to 'dark' (first-time visitor on any page)
        let initialTheme = 'dark';
        
        if (userSetTheme && savedTheme && (savedTheme === 'dark' || savedTheme === 'light' || savedTheme === 'twilight')) {
          initialTheme = savedTheme; // User has chosen theme - use it
        }
        // Else: first-time visitor or invalid theme - use default 'dark'
        
        // Set theme immediately before page renders
        document.documentElement.setAttribute('data-theme', initialTheme);
      } catch (error) {
        // If localStorage fails, default to dark
        document.documentElement.setAttribute('data-theme', 'dark');
      }
    })();
  </script>
  <script>
    // CRITICAL: Update image paths IMMEDIATELY when DOM loads
    // NOTE: Floor Plan content (text and images) is now loaded via Server-Side Rendering (SSR)
    // This script is kept for backward compatibility but is disabled for SSR pages
    (function() {
      // Check if page uses SSR for Floor Plan (has data-ssr-loaded attributes)
      const floorPlanImages = document.querySelectorAll('.floor-photo[data-ssr-loaded]');
      if (floorPlanImages.length > 0) {
        // Page uses SSR, skip JavaScript loading
        console.log('Floor Plan content loaded via SSR, skipping JavaScript update');
        return;
      }
      
      function updateImagePaths() {
        try {
          const storedData = localStorage.getItem('btb_floorplan_settings');
          if (storedData) {
            const data = JSON.parse(storedData);
            
            // Universal function for inline script - same as in script.js
            // All images are HTML <img> elements (display JPG/PNG/GIF files)
            function updateImageElementInline(selector, imageUrl) {
              const element = typeof selector === 'string' ? document.querySelector(selector) : selector;
              if (!element) return false;
              
              // All images are HTML <img> elements - displays JPG/PNG/GIF files directly
              if (element.tagName === 'IMG') {
                element.src = imageUrl;
                element.srcset = imageUrl;
                return true;
              }
              
              return false;
            }
            
            // Update all floor images - use same approach for all three sections
            // All sections use HTML <img> elements with class .floor-photo
            const floorCards = document.querySelectorAll('.floor-card');
            
            // Update basement (first card)
            if (floorCards.length >= 1 && data.basement_image_url) {
              const imageUrl = data.basement_image_url + '?v=' + Date.now();
              const basementImg = floorCards[0].querySelector('.floor-photo');
              if (updateImageElementInline(basementImg, imageUrl)) {
                console.log('Basement image updated to:', imageUrl);
                // Update sources
                const sources = floorCards[0].querySelectorAll('source');
                sources.forEach(source => source.srcset = imageUrl);
              }
            }
            
            // Update ground floor (second card) - same approach as basement
            if (floorCards.length >= 2) {
              const groundImage = data.ground_image_url || data.ground_queen_image || '';
              if (groundImage) {
                const imageUrl = groundImage + '?v=' + Date.now();
                const groundImg = floorCards[1].querySelector('.floor-photo');
                if (updateImageElementInline(groundImg, imageUrl)) {
                  console.log('Ground floor image updated to:', imageUrl);
                  // Update sources
                  const sources = floorCards[1].querySelectorAll('source');
                  sources.forEach(source => source.srcset = imageUrl);
                }
              }
            }
            
            // Update loft (third card) - same approach as basement
            if (floorCards.length >= 3 && data.loft_image_url) {
              const imageUrl = data.loft_image_url + '?v=' + Date.now();
              const loftImg = floorCards[2].querySelector('.floor-photo');
              if (updateImageElementInline(loftImg, imageUrl)) {
                console.log('Loft image updated to:', imageUrl);
                // Update sources
                const sources = floorCards[2].querySelectorAll('source');
                sources.forEach(source => source.srcset = imageUrl);
              }
            }
          }
        } catch(e) {
          console.error('Image update error:', e);
        }
      }
      
      // Home Page content (hero images, descriptions), Wellness Experiences, Floor Plan, and Room Cards
      // are now loaded via Server-Side Rendering (SSR)
      // Skip JavaScript updates for room cards if they have data-ssr-loaded attribute
      try {
        const contentData = localStorage.getItem('btb_content');
        if (contentData) {
          const data = JSON.parse(contentData);
          
          // Update room cards images only if they don't have data-ssr-loaded attribute
          const roomCards = document.querySelectorAll('.room-card');
          roomCards.forEach((card, index) => {
            const roomMedia = card.querySelector('.room-media');
            if (roomMedia && roomMedia.hasAttribute('data-ssr-loaded')) {
              // Skip update - image already loaded via SSR
              return;
            }
            
            // Update from localStorage only if not SSR-loaded
            if (index === 0 && data.roomBasementCardImageUrl) {
              if (roomMedia) {
                roomMedia.style.backgroundImage = `url('${data.roomBasementCardImageUrl}?v=${Date.now()}')`;
              }
            } else if (index === 1 && data.roomGroundQueenCardImageUrl) {
              if (roomMedia) {
                roomMedia.style.backgroundImage = `url('${data.roomGroundQueenCardImageUrl}?v=${Date.now()}')`;
              }
            } else if (index === 2 && data.roomGroundTwinCardImageUrl) {
              if (roomMedia) {
                roomMedia.style.backgroundImage = `url('${data.roomGroundTwinCardImageUrl}?v=${Date.now()}')`;
              }
            } else if (index === 3 && data.roomSecondCardImageUrl) {
              if (roomMedia) {
                roomMedia.style.backgroundImage = `url('${data.roomSecondCardImageUrl}?v=${Date.now()}')`;
              }
            }
          });
        }
      } catch(e) {
        console.error('Room cards image update error:', e);
      }
      
      // Call immediately if DOM is ready, otherwise wait
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', updateImagePaths);
      } else {
        // DOM already loaded, call immediately
        updateImagePaths();
      }
      
      // Also try after a short delay to catch elements if they load later
      setTimeout(updateImagePaths, 100);
    })();
  </script>
</head>
<body class="home">
  <header class="site-header">
    <div class="container header-inner">
      <a class="logo" href="index.php">
        <img alt="Back to Base" class="logo-img" />
        <span class="logo-text">Back to Base</span>
      </a>
      <nav class="nav">
        <a href="#rooms">Rooms</a>
        <a href="massage.php">Wellness</a>
        <a href="retreat-and-workshop.php">Retreats and Workshops</a>
        <a href="explore.php">Explore</a>
        <a href="special.php">Specials</a>
        <a href="about.php">About us</a>
      </nav>
      <button class="mobile-menu-toggle" id="mobile-menu-toggle" aria-label="Toggle mobile menu">
        ☰
      </button>
      <div class="header-actions">
        <button class="theme-toggle" id="theme-toggle" aria-label="Toggle theme">
          <svg class="theme-toggle-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 3v1m0 16v1m9-9h1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <span id="theme-text">Light</span>
        </button>
        <a href="login.html" class="btn-signin" id="header-signin">Guest login</a>
      </div>
    </div>
  </header>

  <!-- Mobile Navigation Overlay -->
  <div class="mobile-nav-overlay" id="mobile-nav-overlay"></div>
  
  <!-- Mobile Navigation Menu -->
  <nav class="mobile-nav" id="mobile-nav">
    <a href="index.php#rooms">Rooms</a>
    <a href="massage.php">Wellness</a>
    <a href="retreat-and-workshop.php">Retreats and Workshops</a>
    <a href="explore.php">Explore</a>
    <a href="special.php">Specials</a>
    <a href="about.php">About us</a>
    <a href="login.html" class="mobile-nav-signin" id="mobile-nav-signin">Guest login</a>
    <button class="theme-toggle" id="mobile-theme-toggle" aria-label="Toggle theme">
      <svg class="theme-toggle-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 3v1m0 16v1m9-9h1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <span id="mobile-theme-text">Light</span>
    </button>
  </nav>

  <section class="hero hero-full" aria-label="Back to Base hero photo">
    <?php if (!empty($heroImageUrl)): ?>
      <img class="hero-full-img" 
           src="<?php echo htmlspecialchars($heroImageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8'); ?>" 
           alt="Blue two‑storey house in the forest with mountains and a lake nearby" 
           loading="eager" 
           decoding="async"
           style="background-image: none !important; opacity: 1;"
           data-ssr-loaded="true"
           data-no-resolve="true" />
    <?php else: ?>
      <img class="hero-full-img" 
           src="assets/hero.jpg" 
           alt="Blue two‑storey house in the forest with mountains and a lake nearby" 
           loading="eager" 
           decoding="async"
           style="background-image: none !important;"
           data-ssr-loaded="true"
           data-no-resolve="true" />
    <?php endif; ?>
  </section>

  <main>
    <section class="section-tight home-intro" aria-label="Welcome message">
      <div class="container">
        <h1 class="home-main-title"><?php echo $homepageDescription; ?></h1>
        <p class="home-main-subtitle"><?php echo $homepageSubtitle; ?></p>
      </div>
    </section>

    <section class="hero-contained" aria-label="Aerial view between manifesto and plan">
      <div class="container">
        <?php if (!empty($hero2ImageUrl)): ?>
          <img class="hero-contained-img hero2-cropped" 
               src="<?php echo htmlspecialchars($hero2ImageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8'); ?>" 
               alt="Aerial view of Back to Base area with lake and mountains" 
               loading="eager" 
               decoding="async"
               style="background-image: none !important; opacity: 1;"
               data-ssr-loaded="true"
               data-no-resolve="true" />
        <?php else: ?>
          <img class="hero-contained-img hero2-cropped" 
               src="assets/hero2.jpg" 
               alt="Aerial view of Back to Base area with lake and mountains" 
               loading="eager" 
               decoding="async"
               style="background-image: none !important;"
               data-ssr-loaded="true"
               data-no-resolve="true" />
        <?php endif; ?>
      </div>
    </section>
  </main>
  
  <style>
    /* Prevent old cached images from flashing */
    .hero-full-img,
    .hero-contained-img.hero2-cropped {
      background-image: none !important;
    }
  </style>

    <section class="trust-badges" aria-label="Guest ratings">
      <div class="container trust-badges-inner">
        <div class="trust-badge vrbo" aria-label="Vacation rental rating 10 out of 10">
          <div class="trust-badge-brand">
            <span class="trust-badge-brand-text trust-badge-brand-text--vrbo">Vrbo</span>
          </div>
          <div class="trust-badge-scoreline" aria-hidden="true">
            <span class="trust-badge-score">10.0</span>
            <span class="trust-badge-stars">★</span>
            <span class="trust-badge-count">(13)</span>
          </div>
        </div>
        <div class="trust-badge airbnb" aria-label="Home-stay rating 5 out of 5">
          <div class="trust-badge-brand">
            <span class="trust-badge-brand-text trust-badge-brand-text--airbnb">Airbnb</span>
          </div>
          <div class="trust-badge-scoreline" aria-hidden="true">
            <span class="trust-badge-score">5.0</span>
            <span class="trust-badge-stars">★</span>
            <span class="trust-badge-count">(27)</span>
          </div>
        </div>
      </div>
    </section>

    <div class="page-header">
      <div class="container">
        <h1><?php echo htmlspecialchars($roomsTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="section-lead"><?php echo $roomsSubtitle; ?></p>
      </div>
    </div>

    <section id="rooms" class="section alt">
      <div class="container">
        <div class="rooms-grid">
          <article class="room-card reveal">
            <div class="room-media" style="background-image:url('<?php 
              if (!empty($roomBasementCardImageUrl)) {
                echo htmlspecialchars($roomBasementCardImageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8');
              } else {
                echo 'assets/basement1.jpeg' . $cacheBuster;
              }
            ?>');" data-ssr-loaded="true" data-no-resolve="true"></div>
            <div class="room-body">
              <h3><?php echo $roomBasementCardTitle; ?></h3>
              <div class="room-card-desc"><?php echo $roomBasementCardDescription; ?></div>
              <p class="notice"><?php echo $roomBasementCardPrice; ?></p>
              <a class="btn primary" href="room-basement.php"><?php echo $homepageBookAStayBtn; ?></a>
            </div>
          </article>

          <article class="room-card reveal">
            <div class="room-media" style="background-image:url('<?php 
              if (!empty($roomGroundQueenCardImageUrl)) {
                echo htmlspecialchars($roomGroundQueenCardImageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8');
              } else {
                echo 'assets/Ground floor — Queen bed1.jpg' . $cacheBuster;
              }
            ?>');" data-ssr-loaded="true" data-no-resolve="true"></div>
            <div class="room-body">
              <h3><?php echo $roomGroundQueenCardTitle; ?></h3>
              <div class="room-card-desc"><?php echo $roomGroundQueenCardDescription; ?></div>
              <p class="notice"><?php echo $roomGroundQueenCardPrice; ?></p>
              <a class="btn primary" href="room-first-double.php"><?php echo $homepageBookAStayBtn; ?></a>
            </div>
          </article>

          <article class="room-card reveal">
            <div class="room-media" style="background-image:url('<?php 
              if (!empty($roomGroundTwinCardImageUrl)) {
                echo htmlspecialchars($roomGroundTwinCardImageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8');
              } else {
                // Use a placeholder or empty - image should be uploaded via admin panel
                echo 'assets/basement1.jpeg' . $cacheBuster;
              }
            ?>');" data-ssr-loaded="true" data-no-resolve="true"></div>
            <div class="room-body">
              <h3><?php echo $roomGroundTwinCardTitle; ?></h3>
              <div class="room-card-desc"><?php echo $roomGroundTwinCardDescription; ?></div>
              <p class="notice"><?php echo $roomGroundTwinCardPrice; ?></p>
              <a class="btn primary" href="room-first-twin.php"><?php echo $homepageBookAStayBtn; ?></a>
            </div>
          </article>

          <article class="room-card reveal">
            <div class="room-media" style="background-image:url('<?php 
              if (!empty($roomSecondCardImageUrl)) {
                echo htmlspecialchars($roomSecondCardImageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8');
              } else {
                echo 'assets/loft5.jpg' . $cacheBuster;
              }
            ?>');" data-ssr-loaded="true" data-no-resolve="true"></div>
            <div class="room-body">
              <h3><?php echo $roomSecondCardTitle; ?></h3>
              <div class="room-card-desc"><?php echo $roomSecondCardDescription; ?></div>
              <p class="notice"><?php echo $roomSecondCardPrice; ?></p>
              <a class="btn primary" href="room-second-suite.php"><?php echo $homepageBookAStayBtn; ?></a>
            </div>
          </article>
        </div>
        <p class="plan-swipe-hint">Swipe to see more</p>
      </div>
    </section>

    <div class="page-header">
      <div class="container">
        <h1><?php echo htmlspecialchars($floorplanTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="section-lead"><?php echo $floorplanSubtitle; ?></p>
      </div>
    </div>

    <section id="plan" class="section">
      <div class="container">
        <div class="plan-grid reveal">
          <article class="plan-card floor-card">
            <h3 class="floor-sub"><?php echo $basementSubtitle; ?></h3>
            <div class="floor-desc"><?php echo $basementDescription; ?></div>
            <div class="floor-photo-wrapper" data-gallery-name="basement">
              <picture class="floor-photo-wrap">
                <source srcset="<?php echo htmlspecialchars($basementImageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8'); ?>" type="image/jpeg" />
                <img class="floor-photo" src="<?php echo htmlspecialchars($basementImageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo safeOutput($basementFloorLabelPlain, ''); ?>" loading="lazy" data-ssr-loaded="true" data-no-resolve="true" />
              </picture>
              <div class="gallery-overlay">
                <div class="gallery-overlay-text"><?php echo safeOutputWithBreaks($basementFloorGalleryHook, 'Open gallery'); ?></div>
              </div>
            </div>
          </article>
          <article class="plan-card floor-card">
            <h3 class="floor-sub"><?php echo $groundSubtitle; ?></h3>
            <div class="floor-desc"><?php echo $groundDescription; ?></div>
            <div class="floor-photo-wrapper" data-gallery-name="ground">
              <picture class="floor-photo-wrap">
                <source srcset="<?php echo htmlspecialchars($groundImageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8'); ?>" type="image/jpeg" />
                <img class="floor-photo" src="<?php echo htmlspecialchars($groundImageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo safeOutput($groundFloorLabelPlain, ''); ?>" loading="lazy" data-ssr-loaded="true" data-no-resolve="true" />
              </picture>
              <div class="gallery-overlay">
                <div class="gallery-overlay-text"><?php echo safeOutputWithBreaks($groundFloorGalleryHook, 'Open gallery'); ?></div>
              </div>
            </div>
          </article>
          <article class="plan-card floor-card">
            <h3 class="floor-sub"><?php echo $loftSubtitle; ?></h3>
            <div class="floor-desc"><?php echo $loftDescription; ?></div>
            <div class="floor-photo-wrapper" data-gallery-name="loft">
              <picture class="floor-photo-wrap">
                <source srcset="<?php echo htmlspecialchars($loftImageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8'); ?>" type="image/jpeg" />
                <img class="floor-photo" src="<?php echo htmlspecialchars($loftImageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo safeOutput($loftFloorLabelPlain, ''); ?>" loading="lazy" data-ssr-loaded="true" data-no-resolve="true" />
              </picture>
              <div class="gallery-overlay">
                <div class="gallery-overlay-text"><?php echo safeOutputWithBreaks($loftFloorGalleryHook, 'Open gallery'); ?></div>
              </div>
            </div>
          </article>
        </div>
        <p class="plan-swipe-hint">Swipe to see more</p>
      </div>
    </section>

    <section class="section guest-reviews" id="guest-reviews" aria-label="Guest reviews">
      <div class="container">
        <h2 class="guest-reviews-heading"><?php echo $guestReviewsTitle; ?></h2>
        <p class="guest-reviews-sub"><?php echo $guestReviewsSubtitle; ?></p>
        <div class="guest-reviews-grid">
          <div class="guest-reviews-column guest-reviews-column--vrbo" data-guest-reviews-group="vrbo">
            <div class="guest-reviews-column-top">
              <span class="guest-reviews-source-heading">Reviews from <span class="guest-reviews-source-name guest-reviews-source-name--vrbo">Vrbo</span></span>
            </div>
            <div class="guest-reviews-panel" id="guest-reviews-vrbo-panel">
              <?php
              foreach ($vrboGuestReviews as $idx => $gr) {
                  $name = htmlspecialchars($gr['name'] ?? '', ENT_QUOTES, 'UTF-8');
                  $text = htmlspecialchars($gr['text'] ?? '', ENT_QUOTES, 'UTF-8');
                  $r = (int) ($gr['rating'] ?? 5);
                  $label = 'Rated ' . $r . ' out of 5 stars';
                  $extra = (count($vrboGuestReviews) > 2 && $idx >= 2) ? ' guest-review--collapsed' : '';
                  echo '<article class="guest-review' . $extra . '">';
                  echo '<header class="guest-review-head"><span class="guest-review-stars" aria-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">' . btb_guest_review_stars($r) . '</span><span class="guest-review-name">' . $name . '</span></header>';
                  echo '<p class="guest-review-text">' . $text . '</p>';
                  echo '</article>';
              }
              ?>
            </div>
            <?php if (count($vrboGuestReviews) > 2): ?>
            <div class="guest-reviews-footer">
              <div class="guest-reviews-footer-external">
                <a
                  class="btn outline guest-reviews-all-link"
                  href="https://www.vrbo.com/en-ca/cottage-rental/p9600379"
                  target="_blank"
                  rel="noopener noreferrer"
                >See all reviews on Vrbo</a>
              </div>
              <button type="button" class="btn outline guest-reviews-more" data-guest-reviews-toggle="vrbo" aria-expanded="false" aria-controls="guest-reviews-vrbo-panel">See all guest reviews</button>
            </div>
            <?php endif; ?>
          </div>
          <div class="guest-reviews-column guest-reviews-column--airbnb" data-guest-reviews-group="airbnb">
            <div class="guest-reviews-column-top">
              <span class="guest-reviews-source-heading">Reviews from <span class="guest-reviews-source-name guest-reviews-source-name--airbnb">Airbnb</span></span>
            </div>
            <div class="guest-reviews-panel" id="guest-reviews-airbnb-panel">
              <?php
              foreach ($airbnbGuestReviews as $idx => $gr) {
                  $name = htmlspecialchars($gr['name'] ?? '', ENT_QUOTES, 'UTF-8');
                  $text = htmlspecialchars($gr['text'] ?? '', ENT_QUOTES, 'UTF-8');
                  $r = (int) ($gr['rating'] ?? 5);
                  $label = 'Rated ' . $r . ' out of 5 stars';
                  $extra = (count($airbnbGuestReviews) > 2 && $idx >= 2) ? ' guest-review--collapsed' : '';
                  echo '<article class="guest-review' . $extra . '">';
                  echo '<header class="guest-review-head"><span class="guest-review-stars" aria-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '">' . btb_guest_review_stars($r) . '</span><span class="guest-review-name">' . $name . '</span></header>';
                  echo '<p class="guest-review-text">' . $text . '</p>';
                  echo '</article>';
              }
              ?>
            </div>
            <?php if (count($airbnbGuestReviews) > 2): ?>
            <div class="guest-reviews-footer">
              <div class="guest-reviews-footer-external">
                <a
                  class="btn outline guest-reviews-all-link"
                  href="https://ru.airbnb.com/rooms/49811499?source_impression_id=p3_1777148513_P3PCOjJy9QBxSiMx"
                  target="_blank"
                  rel="noopener noreferrer"
                >See all reviews on Airbnb</a>
              </div>
              <button type="button" class="btn outline guest-reviews-more" data-guest-reviews-toggle="airbnb" aria-expanded="false" aria-controls="guest-reviews-airbnb-panel">See all guest reviews</button>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>

    <div class="page-header">
      <div class="container">
        <h1><?php echo $wellnessTitle; ?></h1>
        <p class="section-lead"><?php echo $wellnessDescription; ?></p>
      </div>
    </div>

    <section class="section alt" id="wellness-experiences">
      <div class="container">
        <section class="card card-massage">
          <div class="card-img">
            <?php if (!empty($wellnessMassageImageUrl)): ?>
              <img id="wellness-massage-img" class="floor-photo media-43" src="<?php echo htmlspecialchars($wellnessMassageImageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8'); ?>" alt="Wellness at Back to Base" />
            <?php else: ?>
              <img id="wellness-massage-img" class="floor-photo media-43" src="assets/massage.jpg" alt="Wellness at Back to Base" />
            <?php endif; ?>
          </div>
          <div class="card-body">
            <h3><?php echo $wellnessMassageTitle; ?></h3>
            <p><?php echo $wellnessMassageDescription; ?></p>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
              <a class="btn outline" href="massage.php">Explore wellness</a>
            </div>
          </div>
        </section>
      </div>
    </section>
  </main>

  <footer id="contacts" class="site-footer">
    <div class="container footer-grid">
      <div>
        <h4>Contact</h4>
        <p id="footer-contact-address">British Columbia, Canada</p>
        <p id="footer-contact-phone">Phone: +1 (555) 123‑4567</p>
        <p id="footer-contact-email">Email: hello@backtobase.example</p>
      </div>
      <div>
        <h4>Navigation</h4>
        <ul class="footer-nav">
          <li><a href="#rooms">Rooms</a></li>
          <li><a href="massage.php">Wellness</a></li>
          <li><a href="retreat-and-workshop.php">Retreats and Workshops</a></li>
          <li><a href="explore.php">Explore</a></li>
          <li><a href="special.php">Specials</a></li>
          <li><a href="about.php">About us</a></li>
        </ul>
      </div>
      <div>
        <h4>Quiet hours</h4>
        <p>22:00 — 07:00</p>
        <ul class="footer-nav footer-nav--legal">
          <li><a href="privacy.php">Privacy &amp; Cookies</a></li>
          <li><a href="#" id="btb-open-cookie-settings">Cookie settings</a></li>
        </ul>
      </div>
    </div>
    <div class="container copyright">© <span id="year"></span> Back to Base</div>
  </footer>

  <style>
    /* Scroll offset for anchor links to account for fixed header */
    #rooms,
    #plan {
      scroll-margin-top: 100px;
    }
    
    /* Additional JavaScript-based scroll adjustment for better positioning */
  </style>

  <script src="utils.js?v=26"></script>
  <script src="script.js?v=26"></script>
  <script src="auth.js?v=26"></script>
  <script>
    // Prevent script.js from updating hero images that are already loaded via SSR
    (function() {
      'use strict';
      // Protect hero image
      const heroImg = document.querySelector('.hero-full-img');
      if (heroImg && heroImg.hasAttribute('data-ssr-loaded')) {
        heroImg.setAttribute('data-ssr-loaded', 'true');
        heroImg.setAttribute('data-no-resolve', 'true');
        
        // Override src setter to prevent unwanted updates
        Object.defineProperty(heroImg, 'src', {
          get: function() {
            return this.getAttribute('src');
          },
          set: function(value) {
            // Only allow updates if not already loaded via SSR
            if (!this.hasAttribute('data-ssr-loaded') || value === this.getAttribute('src')) {
              this.setAttribute('src', value);
            }
          },
          configurable: true
        });
      }
      
      // Protect hero2 image
      const hero2Img = document.querySelector('.hero-contained-img.hero2-cropped');
      if (hero2Img && hero2Img.hasAttribute('data-ssr-loaded')) {
        hero2Img.setAttribute('data-ssr-loaded', 'true');
        hero2Img.setAttribute('data-no-resolve', 'true');
        
        // Override src setter to prevent unwanted updates
        Object.defineProperty(hero2Img, 'src', {
          get: function() {
            return this.getAttribute('src');
          },
          set: function(value) {
            // Only allow updates if not already loaded via SSR
            if (!this.hasAttribute('data-ssr-loaded') || value === this.getAttribute('src')) {
              this.setAttribute('src', value);
            }
          },
          configurable: true
        });
      }
    })();
  </script>
  <script>
    // Prevent script.js from updating Floor Plan images that are already loaded via SSR
    (function() {
      'use strict';
      const floorPlanImages = document.querySelectorAll('.floor-photo[data-ssr-loaded]');
      floorPlanImages.forEach(img => {
        // Override src setter to prevent unwanted updates
        Object.defineProperty(img, 'src', {
          get: function() {
            return this.getAttribute('src');
          },
          set: function(value) {
            // Only allow updates if not already loaded via SSR
            if (!this.hasAttribute('data-ssr-loaded') || value === this.getAttribute('src')) {
              this.setAttribute('src', value);
            }
          },
          configurable: true
        });
      });
    })();
  </script>
  <script>
    // Adjust scroll position for anchor links to place content at top
    (function() {
      function scrollToAnchor(hash, immediate) {
        const element = document.getElementById(hash);
        if (element) {
          const headerHeight = document.querySelector('.site-header')?.offsetHeight || 80;
          const elementPosition = element.getBoundingClientRect().top + window.pageYOffset;
          const offsetPosition = elementPosition - headerHeight - 20; // 20px extra spacing
          
          window.scrollTo({
            top: offsetPosition,
            behavior: immediate ? 'auto' : 'smooth'
          });
        }
      }
      
      // Handle hash on page load (when coming from another page)
      if (window.location.hash) {
        // Use immediate scroll for page load
        setTimeout(function() {
          const hash = window.location.hash.substring(1);
          scrollToAnchor(hash, true);
        }, 50);
      }
      
      // Handle anchor link clicks on same page
      document.querySelectorAll('a[href="#rooms"], a[href*="#rooms"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
          const href = this.getAttribute('href');
          const hash = href.includes('#') ? href.split('#')[1] : null;
          if (hash && hash === 'rooms') {
            e.preventDefault();
            scrollToAnchor(hash, false);
            // Update URL without triggering scroll
            history.pushState(null, null, '#' + hash);
          }
        });
      });
    })();
  </script>
  <script>
    /* Home intro: hide headline + subtitle until user scrolls, then fade in */
    (function () {
      var intro = document.querySelector('body.home .home-intro');
      if (!intro) return;
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        intro.classList.add('is-revealed');
        return;
      }
      function reveal() {
        if (intro.classList.contains('is-revealed')) return;
        intro.classList.add('is-revealed');
        window.removeEventListener('scroll', reveal, { passive: true });
      }
      function onScroll() {
        if (window.scrollY > 24 || document.documentElement.scrollTop > 24) {
          reveal();
        }
      }
      if (window.scrollY > 24 || document.documentElement.scrollTop > 24) {
        intro.classList.add('is-revealed');
        return;
      }
      window.addEventListener('scroll', onScroll, { passive: true });
    })();
  </script>

  <!-- Gallery Modal for Floor Plan -->
  <div id="floorplan-gallery-modal" class="gallery-modal">
    <span class="gallery-modal-close" onclick="closeFloorplanGallery()">&times;</span>
    <span class="gallery-modal-nav gallery-modal-prev" onclick="changeFloorplanGalleryImage(-1)">&#10094;</span>
    <span class="gallery-modal-nav gallery-modal-next" onclick="changeFloorplanGalleryImage(1)">&#10095;</span>
    <div class="gallery-modal-content">
      <img id="floorplan-gallery-modal-image" class="gallery-modal-image" src="" alt="">
    </div>
    <div class="gallery-modal-counter">
      <span id="floorplan-gallery-counter">1 / 1</span>
    </div>
  </div>

  <style>
    /* Floor Plan Gallery Styles */
    .floor-photo-wrapper {
      position: relative;
      width: 100%;
      aspect-ratio: 1 / 1;
      overflow: hidden;
      cursor: pointer;
      border-radius: 10px;
    }
    .floor-photo-wrap {
      position: relative;
      display: block;
      width: 100%;
      height: 100%;
    }
    .floor-photo-wrapper .floor-photo {
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center;
      display: block;
      transition: transform 0.3s ease;
    }
    .floor-photo-wrapper:hover .floor-photo {
      transform: scale(1.05);
    }
    
    /* Gallery overlay - appears on hover */
    .floor-photo-wrapper .gallery-overlay {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.6);
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0;
      transition: opacity 0.3s ease;
      z-index: 1;
      border-radius: 10px;
    }
    .floor-photo-wrapper:hover .gallery-overlay {
      opacity: 1;
    }
    .floor-photo-wrapper .gallery-overlay-text {
      color: white;
      font-size: 1.05rem;
      font-weight: 600;
      text-align: center;
      padding: 12px 20px;
      background: rgba(0, 0, 0, 0.5);
      border-radius: 8px;
      backdrop-filter: blur(4px);
      max-width: 96%;
      line-height: 1.28;
    }

    /* Gallery Modal Styles */
    .gallery-modal {
      display: none;
      position: fixed;
      z-index: 10000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.95);
      backdrop-filter: blur(10px);
    }
    .gallery-modal.active {
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .gallery-modal-content {
      position: relative;
      max-width: 90%;
      max-height: 90%;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .gallery-modal-image {
      max-width: 100%;
      max-height: 90vh;
      object-fit: contain;
      border-radius: 8px;
    }
    .gallery-modal-close {
      position: absolute;
      top: 20px;
      right: 30px;
      color: white;
      font-size: 40px;
      font-weight: bold;
      cursor: pointer;
      z-index: 10001;
      width: 50px;
      height: 50px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(0, 0, 0, 0.5);
      border-radius: 50%;
      transition: background 0.3s ease;
    }
    .gallery-modal-close:hover {
      background: rgba(0, 0, 0, 0.8);
    }
    .gallery-modal-nav {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      color: white;
      font-size: 30px;
      font-weight: bold;
      cursor: pointer;
      z-index: 10001;
      width: 50px;
      height: 50px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(0, 0, 0, 0.5);
      border-radius: 50%;
      transition: background 0.3s ease;
      user-select: none;
    }
    .gallery-modal-nav:hover {
      background: rgba(0, 0, 0, 0.8);
    }
    .gallery-modal-prev {
      left: 20px;
    }
    .gallery-modal-next {
      right: 20px;
    }
    .gallery-modal-counter {
      position: absolute;
      bottom: 20px;
      left: 50%;
      transform: translateX(-50%);
      color: white;
      background: rgba(0, 0, 0, 0.5);
      padding: 10px 20px;
      border-radius: 20px;
      font-size: 14px;
    }
  </style>

  <script>
    // Floor Plan Gallery functionality
    (function() {
      'use strict';
      
      // Initialize gallery variables
      let floorplanGalleries = {};
      let currentFloorplanGallery = null;
      let currentFloorplanImageIndex = 0;

      // Wait for DOM to be fully loaded
      document.addEventListener('DOMContentLoaded', function() {
        // Load gallery data from PHP
        <?php
        // Helper function to filter empty URLs
        function filterGalleryUrls($gallery) {
            return array_filter($gallery, function($url) {
                return !empty(trim($url));
            });
        }
        
        // Build gallery arrays (combine main image with gallery images)
        $basementGalleryFull = array_merge([$basementImageUrl], filterGalleryUrls($basementGallery));
        $groundGalleryFull = array_merge([$groundImageUrl], filterGalleryUrls($groundGallery));
        $loftGalleryFull = array_merge([$loftImageUrl], filterGalleryUrls($loftGallery));
        ?>
        
        floorplanGalleries = {
          basement: <?php echo json_encode(array_values($basementGalleryFull)); ?>,
          ground: <?php echo json_encode(array_values($groundGalleryFull)); ?>,
          loft: <?php echo json_encode(array_values($loftGalleryFull)); ?>
        };

        const floorplanGalleryAltPrefixes = <?php echo json_encode([
            'basement' => $basementFloorLabelPlain,
            'ground' => $groundFloorLabelPlain,
            'loft' => $loftFloorLabelPlain,
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;
        
        console.log('Loaded floorplan galleries:', floorplanGalleries);

        // Gallery functions - make them globally available
        window.openFloorplanGallery = function(galleryName, imageIndex) {
          console.log('openFloorplanGallery called:', galleryName, imageIndex);
          currentFloorplanGallery = galleryName;
          currentFloorplanImageIndex = imageIndex || 0;
          const gallery = floorplanGalleries[galleryName];
          
          if (!gallery || gallery.length === 0) {
            console.warn('Gallery is empty or not found:', galleryName);
            return;
          }
          
          const modal = document.getElementById('floorplan-gallery-modal');
          const modalImage = document.getElementById('floorplan-gallery-modal-image');
          const counter = document.getElementById('floorplan-gallery-counter');
          
          if (!modal || !modalImage || !counter) {
            console.error('Gallery modal elements not found');
            return;
          }
          
          modalImage.src = gallery[currentFloorplanImageIndex];
          const fpPrefix = floorplanGalleryAltPrefixes[galleryName] || 'Common areas';
          modalImage.alt = fpPrefix + ' — slide ' + (currentFloorplanImageIndex + 1) + ' of ' + gallery.length;
          counter.textContent = `${currentFloorplanImageIndex + 1} / ${gallery.length}`;
          modal.classList.add('active');
          
          // Prevent body scroll
          document.body.style.overflow = 'hidden';
        }

        window.closeFloorplanGallery = function() {
          const modal = document.getElementById('floorplan-gallery-modal');
          if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
          }
        }

        window.changeFloorplanGalleryImage = function(direction) {
          if (!currentFloorplanGallery) return;
          
          const gallery = floorplanGalleries[currentFloorplanGallery];
          if (!gallery || gallery.length === 0) return;
          
          currentFloorplanImageIndex += direction;
          
          if (currentFloorplanImageIndex < 0) {
            currentFloorplanImageIndex = gallery.length - 1;
          } else if (currentFloorplanImageIndex >= gallery.length) {
            currentFloorplanImageIndex = 0;
          }
          
          const modalImage = document.getElementById('floorplan-gallery-modal-image');
          const counter = document.getElementById('floorplan-gallery-counter');
          
          modalImage.src = gallery[currentFloorplanImageIndex];
          const fpPrefix2 = floorplanGalleryAltPrefixes[currentFloorplanGallery] || 'Common areas';
          modalImage.alt = fpPrefix2 + ' — slide ' + (currentFloorplanImageIndex + 1) + ' of ' + gallery.length;
          counter.textContent = `${currentFloorplanImageIndex + 1} / ${gallery.length}`;
        }

        // Close gallery on Escape key
        document.addEventListener('keydown', function(e) {
          const modal = document.getElementById('floorplan-gallery-modal');
          if (modal && modal.classList.contains('active')) {
            if (e.key === 'Escape') {
              window.closeFloorplanGallery();
            } else if (e.key === 'ArrowLeft') {
              window.changeFloorplanGalleryImage(-1);
            } else if (e.key === 'ArrowRight') {
              window.changeFloorplanGalleryImage(1);
            }
          }
        });

        // Close gallery when clicking outside image
        document.getElementById('floorplan-gallery-modal').addEventListener('click', function(e) {
          if (e.target === this || e.target.classList.contains('gallery-modal-content')) {
            window.closeFloorplanGallery();
          }
        });
        
        // Add click handlers to floor plan image wrappers
        document.querySelectorAll('.floor-photo-wrapper').forEach(function(wrapper) {
          wrapper.addEventListener('click', function(e) {
            // Don't trigger if clicking on the icon (it's just a visual indicator)
            const galleryName = this.getAttribute('data-gallery-name');
            if (galleryName) {
              console.log('Floor plan gallery wrapper clicked:', galleryName);
              window.openFloorplanGallery(galleryName, 0);
            }
          });
        });
      });
    })();
  </script>
  <script>
    (function () {
      document.querySelectorAll('.guest-reviews').forEach(function (section) {
        const buttons = Array.from(section.querySelectorAll('[data-guest-reviews-toggle]'));
        if (!buttons.length) {
          return;
        }
        const setExpandedState = function (expanded) {
          section.querySelectorAll('.guest-reviews-column').forEach(function (col) {
            col.classList.toggle('is-expanded', expanded);
          });
          buttons.forEach(function (b) {
            b.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            b.textContent = expanded ? 'Collapse reviews' : 'See all guest reviews';
            b.hidden = false;
          });
          section.setAttribute('data-guest-reviews-expanded', expanded ? '1' : '0');
        };
        setExpandedState(false);
        buttons.forEach(function (btn) {
          btn.addEventListener('click', function () {
            const expanded = section.getAttribute('data-guest-reviews-expanded') === '1';
            setExpandedState(!expanded);
          });
        });
      });
    })();
  </script>
  <!-- Wellness Experiences content is now loaded via Server-Side Rendering (SSR) -->
</body>
</html>
