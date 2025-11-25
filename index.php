<?php
// Server-Side Rendering for Homepage with Wellness Experiences
require_once 'common.php';

// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Load content from database
$content = fetchOne($conn, "SELECT * FROM content_settings WHERE id = 1");
if (!$content) {
    $content = []; // Ensure $content is always an array
}

// Load wellness images from dedicated table if available
$wellnessImages = [];
$wellnessTableCheck = $conn->query("SHOW TABLES LIKE 'wellness_settings'");
if ($wellnessTableCheck && $wellnessTableCheck->num_rows > 0) {
    $wellnessImages = fetchOne($conn, "SELECT * FROM wellness_settings WHERE id = 1");
    if (!$wellnessImages) {
        $wellnessImages = [];
    }
}

// Helper function for safe output with fallback
function safeOutput($value, $fallback = '') {
    return htmlspecialchars($value ?? $fallback, ENT_QUOTES, 'UTF-8');
}

// Helper function for safe output with line breaks preserved
function safeOutputWithBreaks($value, $fallback = '') {
    $text = $value ?? $fallback;
    // Replace newlines with <br> tags
    $text = nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
    return $text;
}

// Extract Home Page content with fallback values
$homepageDescription = safeOutputWithBreaks($content['homepage_description'] ?? '', 'Back to Base is a countryside guesthouse in Nelson, British Columbia, where you can rent a room or book the entire house for a vacation, retreat, or wellness getaway. Guests can restore their energy with a relaxing massage and enjoy comfortable accommodation surrounded by mountains and forest.');
$homepageSubtitle = safeOutputWithBreaks($content['homepage_subtitle'] ?? '', 'Our cozy rooms and inspiring atmosphere make this the perfect place for solitude, meditation, yoga retreats, or simply a peaceful holiday in nature.');

// Rooms section title and subtitle - try rooms_settings table first, fall back to content_settings
$roomsTitle = 'Choose your room';
$roomsSubtitle = '';
$roomsTableCheck = $conn->query("SHOW TABLES LIKE 'rooms_settings'");
if ($roomsTableCheck && $roomsTableCheck->num_rows > 0) {
    $roomsData = fetchOne($conn, "SELECT * FROM rooms_settings WHERE id = 1");
    if ($roomsData) {
        $roomsTitle = safeOutput($roomsData['rooms_title'] ?? '', 'Choose your room');
        $roomsSubtitle = safeOutput($roomsData['rooms_subtitle'] ?? '');
    }
} else {
    // Fall back to content_settings for backward compatibility
    $roomsTitle = safeOutput($content['rooms_title'] ?? '', 'Choose your room');
    $roomsSubtitle = safeOutput($content['rooms_subtitle'] ?? '');
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

// Extract room card content with fallback values
$roomBasementCardTitle = safeOutputHTML($roomCardData['room_basement_card_title'] ?? '', 'Basement<br/>Queen bed');
$roomBasementCardDescription = safeOutputWithBreaks($roomCardData['room_basement_card_description'] ?? '', 'Cozy room next to the home cinema and sauna. Perfect for two.');
$roomBasementCardPrice = safeOutput($roomCardData['room_basement_card_price'] ?? '', 'From 140 CAD/night');
$roomBasementCardImageUrl = isset($roomCardData['room_basement_card_image_url']) && !empty(trim($roomCardData['room_basement_card_image_url'])) 
    ? safeOutput($roomCardData['room_basement_card_image_url'], '') 
    : '';

$roomGroundQueenCardTitle = safeOutputHTML($roomCardData['room_ground_queen_card_title'] ?? '', 'Ground floor<br/>Queen bed');
$roomGroundQueenCardDescription = safeOutputWithBreaks($roomCardData['room_ground_queen_card_description'] ?? '', 'Compact, bright room with access to the fireplace lounge.');
$roomGroundQueenCardPrice = safeOutput($roomCardData['room_ground_queen_card_price'] ?? '', 'From 130 CAD/night');
$roomGroundQueenCardImageUrl = isset($roomCardData['room_ground_queen_card_image_url']) && !empty(trim($roomCardData['room_ground_queen_card_image_url'])) 
    ? safeOutput($roomCardData['room_ground_queen_card_image_url'], '') 
    : '';

$roomGroundTwinCardTitle = safeOutputHTML($roomCardData['room_ground_twin_card_title'] ?? '', 'Ground floor<br/>Twin beds');
$roomGroundTwinCardDescription = safeOutputWithBreaks($roomCardData['room_ground_twin_card_description'] ?? '', 'Great for friends or colleagues. Close to the kitchen and massage hall.');
$roomGroundTwinCardPrice = safeOutput($roomCardData['room_ground_twin_card_price'] ?? '', 'From 125 CAD/night');
$roomGroundTwinCardImageUrl = isset($roomCardData['room_ground_twin_card_image_url']) && !empty(trim($roomCardData['room_ground_twin_card_image_url'])) 
    ? safeOutput($roomCardData['room_ground_twin_card_image_url'], '') 
    : '';

$roomSecondCardTitle = safeOutputHTML($roomCardData['room_second_card_title'] ?? '', 'Second floor (entire)<br/>Queen bed');
$roomSecondCardDescription = safeOutputWithBreaks($roomCardData['room_second_card_description'] ?? '', 'Separate kitchen and shower, study, and a balcony with lake view.');
$roomSecondCardPrice = safeOutput($roomCardData['room_second_card_price'] ?? '', 'From 210 CAD/night (entire floor)');
$roomSecondCardImageUrl = isset($roomCardData['room_second_card_image_url']) && !empty(trim($roomCardData['room_second_card_image_url'])) 
    ? safeOutput($roomCardData['room_second_card_image_url'], '') 
    : '';

$heroImageUrl = isset($content['hero_image_url']) && !empty(trim($content['hero_image_url'])) 
    ? safeOutput($content['hero_image_url'], '') 
    : '';

$hero2ImageUrl = isset($content['hero2_image_url']) && !empty(trim($content['hero2_image_url'])) 
    ? safeOutput($content['hero2_image_url'], '') 
    : '';

// Extract Wellness Experiences content with fallback values
$wellnessTitle = safeOutput($content['wellness_title'] ?? '', 'Wellness Experiences');
$wellnessDescription = safeOutputWithBreaks($content['wellness_description'] ?? '', 'Enhance your stay with our additional wellness services. Enjoy yoga sessions in the forest, relaxing or deep tissue massages, and the warmth of our private sauna — all designed to make your vacation even more restorative.');

$wellnessMassageTitle = safeOutput($content['wellness_massage_title'] ?? '', 'Massage');
$wellnessMassageDescription = safeOutputWithBreaks($content['wellness_massage_description'] ?? '', 'Our guesthouse has a massage room with an experienced therapist who will be happy to make your stay even more enjoyable. Whether you prefer a relaxing massage or a therapeutic deep tissue session — the choice is yours.');
$wellnessMassageImageUrl = '';
if (!empty($wellnessImages) && !empty(trim($wellnessImages['wellness_massage_image_url'] ?? ''))) {
    $wellnessMassageImageUrl = safeOutput($wellnessImages['wellness_massage_image_url'], '');
} elseif (isset($content['wellness_massage_image_url']) && !empty(trim($content['wellness_massage_image_url']))) {
    $wellnessMassageImageUrl = safeOutput($content['wellness_massage_image_url'], '');
}

$wellnessYogaTitle = safeOutput($content['wellness_yoga_title'] ?? '', 'Yoga');
$wellnessYogaDescription = safeOutputWithBreaks($content['wellness_yoga_description'] ?? '', 'On the property, surrounded by a cozy forest, you\'ll find platforms for yoga and meditation. An experienced instructor will guide you towards harmony with yourself and the world, while the soothing sound of a mountain stream nearby will be your soundtrack along the way.');
$wellnessYogaImageUrl = '';
if (!empty($wellnessImages) && !empty(trim($wellnessImages['wellness_yoga_image_url'] ?? ''))) {
    $wellnessYogaImageUrl = safeOutput($wellnessImages['wellness_yoga_image_url'], '');
} elseif (isset($content['wellness_yoga_image_url']) && !empty(trim($content['wellness_yoga_image_url']))) {
    $wellnessYogaImageUrl = safeOutput($content['wellness_yoga_image_url'], '');
}

$wellnessSaunaTitle = safeOutput($content['wellness_sauna_title'] ?? '', 'Sauna');
$wellnessSaunaDescription = safeOutputWithBreaks($content['wellness_sauna_description'] ?? '', 'After a day spent in nature, sometimes you just want to warm up. We understand how important comfort is, so we offer our guests free access to a small sauna. It is located right in the house, on the basement floor.');
$wellnessSaunaImageUrl = '';
if (!empty($wellnessImages) && !empty(trim($wellnessImages['wellness_sauna_image_url'] ?? ''))) {
    $wellnessSaunaImageUrl = safeOutput($wellnessImages['wellness_sauna_image_url'], '');
} elseif (isset($content['wellness_sauna_image_url']) && !empty(trim($content['wellness_sauna_image_url']))) {
    $wellnessSaunaImageUrl = safeOutput($content['wellness_sauna_image_url'], '');
}

// Load Floor Plan content from database
$floorplan = fetchOne($conn, "SELECT * FROM floorplan_settings WHERE id = 1");
if (!$floorplan) {
    $floorplan = []; // Ensure $floorplan is always an array
}

// Floor Plan section title and subtitle
$floorplanTitle = safeOutput($floorplan['floorplan_title'] ?? '', 'Floor plan');
$floorplanSubtitle = safeOutput($floorplan['floorplan_subtitle'] ?? '', 'Three levels of comfort: basement, ground floor and a cozy loft under the roof.');

// Extract Floor Plan content with fallback values
$basementSubtitle = safeOutput($floorplan['basement_subtitle'] ?? '', 'Private floor with a separate entrance.');
$basementDescription = safeOutputWithBreaks($floorplan['basement_description'] ?? '', 'A spacious bedroom with a king-size bed and a small study, a home theater with a fireplace, and a private bathroom featuring a shower and a sauna room.');
$basementImageUrl = isset($floorplan['basement_image_url']) && !empty(trim($floorplan['basement_image_url'])) 
    ? safeOutput($floorplan['basement_image_url'], '') 
    : 'assets/plan.jpg';

$groundSubtitle = safeOutput($floorplan['ground_subtitle'] ?? '', 'Open space with a separate entrance.');
$groundDescription = safeOutputWithBreaks($floorplan['ground_description'] ?? '', 'A large bright hall with a fireplace, a big dining table, a spacious modern kitchen, two rental rooms, a shared bathroom with a bathtub, and a separate room for massage and events.');
$groundImageUrl = isset($floorplan['ground_image_url']) && !empty(trim($floorplan['ground_image_url'])) 
    ? safeOutput($floorplan['ground_image_url'], '') 
    : (isset($floorplan['ground_queen_image']) && !empty(trim($floorplan['ground_queen_image'])) 
        ? safeOutput($floorplan['ground_queen_image'], '') 
        : 'assets/plan.jpg');

$loftSubtitle = safeOutput($floorplan['loft_subtitle'] ?? '', 'Private top-floor space under the roof.');
$loftDescription = safeOutputWithBreaks($floorplan['loft_description'] ?? '', 'A large bedroom with a king-size bed, a bright study, a small kitchen, a private bathroom with a shower, and a spacious balcony with stunning views of the lake and mountains.');
$loftImageUrl = isset($floorplan['loft_image_url']) && !empty(trim($floorplan['loft_image_url'])) 
    ? safeOutput($floorplan['loft_image_url'], '') 
    : 'assets/plan.jpg';

// Cache buster for images - use current timestamp to force browser to reload images
// This ensures new images from admin panel are displayed immediately without flickering
$cacheBuster = '?v=' . time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="light dark">
  <title>Back to Base — Boutique Retreat in British Columbia</title>
  <meta name="description" content="Back to Base — boutique forest retreat in British Columbia, Canada. Rooms for rent, massage services, yoga, and nature immersion.">
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
<body>
  <header class="site-header">
    <div class="container header-inner">
      <a class="logo" href="index.php">
        <img alt="Back to Base" class="logo-img" />
        <span class="logo-text">Back to Base</span>
      </a>
      <nav class="nav">
        <a href="#rooms">Rooms</a>
        <a href="massage.php">Massage</a>
        <a href="retreat-and-workshop.php">Retreats and Workshops</a>
        <a href="special.php">Special</a>
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
        <a href="login.html" class="btn-signin" id="header-signin">Sign In</a>
      </div>
    </div>
  </header>

  <!-- Mobile Navigation Overlay -->
  <div class="mobile-nav-overlay" id="mobile-nav-overlay"></div>
  
  <!-- Mobile Navigation Menu -->
  <nav class="mobile-nav" id="mobile-nav">
    <a href="index.php#rooms">Rooms</a>
    <a href="massage.php">Massage</a>
    <a href="retreat-and-workshop.php">Retreats and Workshops</a>
    <a href="special.php">Special</a>
    <a href="about.php">About us</a>
    <a href="login.html" class="mobile-nav-signin" id="mobile-nav-signin">Sign In</a>
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
    <section class="section-tight">
      <div class="container">
        <p><?php echo $homepageDescription; ?></p>
        <p><?php echo $homepageSubtitle; ?></p>
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

    <div class="page-header">
      <div class="container">
        <h1><?php echo htmlspecialchars($floorplanTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="section-lead"><?php echo htmlspecialchars($floorplanSubtitle, ENT_QUOTES, 'UTF-8'); ?></p>
      </div>
    </div>

    <section id="plan" class="section">
      <div class="container">
        <div class="plan-grid reveal">
          <article class="plan-card floor-card">
            <h3>Basement</h3>
            <p class="floor-sub"><?php echo $basementSubtitle; ?></p>
            <div class="floor-desc"><?php echo $basementDescription; ?></div>
            <picture class="floor-photo-wrap">
              <source srcset="<?php echo htmlspecialchars($basementImageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8'); ?>" type="image/jpeg" />
              <img class="floor-photo" src="<?php echo htmlspecialchars($basementImageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8'); ?>" alt="Basement floor plan" loading="lazy" data-ssr-loaded="true" data-no-resolve="true" />
            </picture>
          </article>
          <article class="plan-card floor-card">
            <h3>Ground floor</h3>
            <p class="floor-sub"><?php echo $groundSubtitle; ?></p>
            <div class="floor-desc"><?php echo $groundDescription; ?></div>
            <picture class="floor-photo-wrap">
              <source srcset="<?php echo htmlspecialchars($groundImageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8'); ?>" type="image/jpeg" />
              <img class="floor-photo" src="<?php echo htmlspecialchars($groundImageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8'); ?>" alt="Ground floor plan" loading="lazy" data-ssr-loaded="true" data-no-resolve="true" />
            </picture>
          </article>
          <article class="plan-card floor-card">
            <h3>Second floor (loft)</h3>
            <p class="floor-sub"><?php echo $loftSubtitle; ?></p>
            <div class="floor-desc"><?php echo $loftDescription; ?></div>
            <picture class="floor-photo-wrap">
              <source srcset="<?php echo htmlspecialchars($loftImageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8'); ?>" type="image/jpeg" />
              <img class="floor-photo" src="<?php echo htmlspecialchars($loftImageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8'); ?>" alt="Second floor plan" loading="lazy" data-ssr-loaded="true" data-no-resolve="true" />
            </picture>
          </article>
        </div>
        <p class="plan-swipe-hint">Swipe to see more</p>
      </div>
    </section>

    <div class="page-header">
      <div class="container">
        <h1><?php echo htmlspecialchars($roomsTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="section-lead"><?php echo htmlspecialchars($roomsSubtitle, ENT_QUOTES, 'UTF-8'); ?></p>
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
              <p><?php echo $roomBasementCardDescription; ?></p>
              <p class="notice"><?php echo $roomBasementCardPrice; ?></p>
              <a class="btn primary" href="room-basement.php">Details & booking</a>
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
              <p><?php echo $roomGroundQueenCardDescription; ?></p>
              <p class="notice"><?php echo $roomGroundQueenCardPrice; ?></p>
              <a class="btn primary" href="room-first-double.php">Details & booking</a>
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
              <p><?php echo $roomGroundTwinCardDescription; ?></p>
              <p class="notice"><?php echo $roomGroundTwinCardPrice; ?></p>
              <a class="btn primary" href="room-first-twin.php">Details & booking</a>
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
              <p><?php echo $roomSecondCardDescription; ?></p>
              <p class="notice"><?php echo $roomSecondCardPrice; ?></p>
              <a class="btn primary" href="room-second-suite.php">Details & booking</a>
            </div>
          </article>
        </div>
        <p class="plan-swipe-hint">Swipe to see more</p>
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
              <img id="wellness-massage-img" class="floor-photo media-43" src="<?php echo htmlspecialchars($wellnessMassageImageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8'); ?>" alt="Massage at Back to Base" />
            <?php else: ?>
              <img id="wellness-massage-img" class="floor-photo media-43" src="assets/massage.jpg" alt="Massage at Back to Base" />
            <?php endif; ?>
          </div>
          <div class="card-body">
            <h2><?php echo $wellnessMassageTitle; ?></h2>
            <p><?php echo $wellnessMassageDescription; ?></p>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
              <a class="btn primary" href="massage.php">Book massage</a>
            </div>
          </div>
        </section>

        <section class="card card-massage card-massage--alt">
          <div class="card-img">
            <?php if (!empty($wellnessYogaImageUrl)): ?>
              <img id="wellness-yoga-img" class="floor-photo media-43" src="<?php echo htmlspecialchars($wellnessYogaImageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8'); ?>" alt="Yoga at Back to Base" />
            <?php else: ?>
              <img id="wellness-yoga-img" class="floor-photo media-43" src="assets/yoga.jpg" alt="Yoga at Back to Base" />
            <?php endif; ?>
          </div>
          <div class="card-body">
            <h2><?php echo $wellnessYogaTitle; ?></h2>
            <p><?php echo $wellnessYogaDescription; ?></p>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
              <a class="btn primary" href="retreat-and-workshop.php">Book retreat</a>
            </div>
          </div>
        </section>

        <section class="card card-massage">
          <div class="card-img">
            <?php if (!empty($wellnessSaunaImageUrl)): ?>
              <img id="wellness-sauna-img" class="floor-photo media-43" src="<?php echo htmlspecialchars($wellnessSaunaImageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8'); ?>" alt="Sauna at Back to Base" />
            <?php else: ?>
              <img id="wellness-sauna-img" class="floor-photo media-43" src="assets/plan-basement-bedroom.jpg" alt="Sauna at Back to Base" />
            <?php endif; ?>
          </div>
          <div class="card-body">
            <h2><?php echo $wellnessSaunaTitle; ?></h2>
            <p><?php echo $wellnessSaunaDescription; ?></p>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
              <a class="btn primary" href="massage.php">Book sauna session</a>
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
          <li><a href="massage.php">Massage</a></li>
          <li><a href="retreat-and-workshop.php">Retreats and Workshops</a></li>
          <li><a href="special.php">Special</a></li>
          <li><a href="about.php">About us</a></li>
        </ul>
      </div>
      <div>
        <h4>Quiet hours</h4>
        <p>22:00 — 07:00</p>
      </div>
    </div>
    <div class="container copyright">© <span id="year"></span> Back to Base</div>
  </footer>

  <style>
    /* Scroll offset for anchor links to account for fixed header */
    #rooms {
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
  <!-- Wellness Experiences content is now loaded via Server-Side Rendering (SSR) -->
</body>
</html>
