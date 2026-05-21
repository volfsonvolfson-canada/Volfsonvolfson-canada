<?php
// Server-Side Rendering for Loki Suite page
require_once 'common.php';

// Redirect from .html to .php if accessed via old URL
if (basename($_SERVER['PHP_SELF']) === 'room-basement.html' || 
    (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'room-basement.html') !== false)) {
    header('Location: room-basement.php', true, 301);
    exit;
}

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

$roomBookNowButtonLabel = trim((string)($content['room_book_now_button_label'] ?? ''));
if ($roomBookNowButtonLabel === '') {
    $roomBookNowButtonLabel = 'Book now';
}
$roomBookNowButtonLabel = htmlspecialchars($roomBookNowButtonLabel, ENT_QUOTES, 'UTF-8');

// Load wellness images from dedicated table if available
$wellnessImages = [];
$wellnessTableCheck = $conn->query("SHOW TABLES LIKE 'wellness_settings'");
$wellnessImagesEnabled = false;
if ($wellnessTableCheck && $wellnessTableCheck->num_rows > 0) {
    $wellnessImages = fetchOne($conn, "SELECT * FROM wellness_settings WHERE id = 1");
    if ($wellnessImages) {
        $wellnessImagesEnabled = true;
    } else {
        $wellnessImages = [];
    }
}

// Extract Wellness Experiences content with fallback values
$wellnessTitle = safeOutput(
    btb_field_or_default($content, 'wellness_title', 'content_settings.wellness_title', 'Wellness Experiences'),
    ''
);
$wellnessDescription = safeOutputWithBreaks(
    btb_field_or_default($content, 'wellness_description', 'content_settings.wellness_description', 'Enhance your stay with optional massage: relaxing or deep tissue sessions with an experienced therapist — an easy way to make your time in the mountains feel even more restorative.'),
    ''
);

$wellnessMassageTitle = safeOutput(
    btb_field_or_default($content, 'wellness_massage_title', 'content_settings.wellness_massage_title', 'Wellness'),
    ''
);
$wellnessMassageDescription = safeOutputWithBreaks(
    btb_field_or_default($content, 'wellness_massage_description', 'content_settings.wellness_massage_description', 'Our guesthouse has a massage room with an experienced therapist who will be happy to make your stay even more enjoyable. Whether you prefer a relaxing massage or a therapeutic deep tissue session — the choice is yours.'),
    ''
);
$wellnessMassageImageUrl = '';
if ($wellnessImagesEnabled && !empty(trim($wellnessImages['wellness_massage_image_url'] ?? ''))) {
    $wellnessMassageImageUrl = safeOutput($wellnessImages['wellness_massage_image_url'], '');
} elseif (isset($content['wellness_massage_image_url']) && !empty(trim($content['wellness_massage_image_url']))) {
    $wellnessMassageImageUrl = safeOutput($content['wellness_massage_image_url'], '');
}

// Extract content with fallback values
$title = safeOutput(
    btb_field_or_default($content, 'room_basement_title', 'content_settings.room_basement_title', 'Loki Suite'),
    ''
);
$roomGalleryTitlePlain = trim((string) ($content['room_basement_title'] ?? ''));
if ($roomGalleryTitlePlain === '') {
    $roomGalleryTitlePlain = btb_default_text('content_settings.room_basement_title', 'Loki Suite');
}
$subtitle = safeOutputWithBreaks(
    btb_field_or_default($content, 'room_basement_subtitle', 'content_settings.room_basement_subtitle', 'A cozy room next to the home cinema and sauna. Ideal for two.'),
    ''
);
$description = safeOutputWithBreaks(
    btb_field_or_default($content, 'room_basement_description', 'content_settings.room_basement_description', 'Next to this room there is a home theater lounge with a wood-burning stove and a large shower area with a sauna. The floor has a private exit from the house and a passage to the shared lounge on the first floor.'),
    ''
);
// Helper function for safe HTML output (allows specific tags like <strong>)
function safeHtmlOutput($value, $fallback = '') {
    if (empty($value)) return $fallback;
    // Allow specific HTML tags
    $allowedTags = '<strong><em><b><i><br>';
    return strip_tags($value, $allowedTags);
}

$price = btb_room_price_line_html_stored_only($content, 'basement');
$capacity = isset($content['room_basement_capacity']) && !empty(trim($content['room_basement_capacity'])) 
    ? safeHtmlOutput($content['room_basement_capacity']) 
    : '<strong>Capacity:</strong> up to 2 guests';
$note = safeOutputWithBreaks(
    btb_field_or_default($content, 'room_basement_note', 'content_settings.room_basement_note', '*All tenants may use the sauna and home theatre free of charge, as long as it does not disturb other guests.'),
    ''
);

// Banner image
$bannerImageUrl = isset($content['room_basement_banner_image_url']) && !empty(trim($content['room_basement_banner_image_url'])) 
    ? safeOutput($content['room_basement_banner_image_url'], '') 
    : '';

// Gallery (supports string URLs, object rows, double-encoded JSON)
$galleryJson = $content['room_basement_gallery'] ?? '[]';

$tRoomGalH = trim((string) ($content['room_basement_gallery_section_title'] ?? ''));
$roomGallerySectionHeading = htmlspecialchars(
    $tRoomGalH !== '' ? $tRoomGalH : btb_default_text('content_settings.room_basement_gallery_section_title', 'Room photos'),
    ENT_QUOTES,
    'UTF-8'
);
$tCommonGalH = trim((string) ($content['room_basement_common_gallery_section_title'] ?? ''));
$commonGallerySectionHeading = htmlspecialchars(
    $tCommonGalH !== '' ? $tCommonGalH : btb_default_text('content_settings.room_basement_common_gallery_section_title', 'Common areas photos'),
    ENT_QUOTES,
    'UTF-8'
);
$commonGalleryJson = $content['room_basement_common_gallery'] ?? '[]';
$commonGalleryAltPlain = trim((string) ($content['room_basement_common_gallery_section_title'] ?? ''));
if ($commonGalleryAltPlain === '') {
    $commonGalleryAltPlain = btb_default_text('content_settings.room_basement_common_gallery_section_title', 'Common areas');
}

$roomGalleryUrls = btb_room_gallery_urls_from_cms_json($galleryJson);
$roomGalleryTotal = count($roomGalleryUrls);
$roomGalleryPreview = btb_room_gallery_grid_preview_urls($roomGalleryUrls);
$commonGalleryUrls = btb_room_gallery_urls_from_cms_json($commonGalleryJson);
$commonGalleryTotal = count($commonGalleryUrls);
$commonGalleryPreview = btb_room_gallery_grid_preview_urls($commonGalleryUrls);

// Cache buster for images
$cacheBuster = '?v=' . time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
<?php require_once __DIR__ . '/site-head-consent.php'; ?>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="light dark">
  <title>Loki Suite | Back to Base</title>
  <?php
  $__seo_title = 'Loki Suite | Back to Base';
  $__seo_desc = 'Loki Suite at Back to Base — guest room near Nelson, BC next to home cinema and sauna; book your countryside stay.';
  ?>
  <meta name="description" content="<?php echo htmlspecialchars($__seo_desc, ENT_QUOTES, 'UTF-8'); ?>">
  <?php
  btb_seo_emit_link_and_meta('/room-basement.php', $__seo_title, $__seo_desc, [
      'og_image' => '/assets/deer.jpg',
  ]);
  ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" onerror="this.onerror=null; this.href='';">
  <style>
    /* Fallback fonts if Google Fonts fails to load */
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
  </style>
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
  <link rel="stylesheet" href="styles.css?v=47">
</head>
<body>
<?php require_once __DIR__ . '/gtm-body-noscript.php'; ?>
  <header class="site-header">
    <div class="container header-inner">
      <a class="logo" href="index.php">
        <img alt="Back to Base" class="logo-img" />
        <span class="logo-text">Back to Base</span>
      </a>
      <nav class="nav">
        <a href="index.php#rooms">Rooms</a>
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
    <a href="index.html#rooms">Rooms</a>
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

  <div class="page-header">
    <div class="container">
      <h1><?php echo $title; ?></h1>
      <p class="section-lead"><?php echo $subtitle; ?></p>
    </div>
  </div>

  <section class="hero-contained" aria-label="Basement banner">
    <div class="container">
      <?php if (!empty($bannerImageUrl)): ?>
        <img id="room-basement-banner" 
             class="hero-contained-img basement-banner-cropped" 
             src="<?php echo htmlspecialchars($bannerImageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8'); ?>" 
             alt="Deer in forest"
             loading="eager"
             decoding="async"
             style="background-image: none !important;"
             data-ssr-loaded="true" />
      <?php else: ?>
        <!-- Fallback: use default image without data-src-base to prevent script.js from processing it -->
        <img id="room-basement-banner" 
             class="hero-contained-img basement-banner-cropped" 
             src="assets/deer.jpg" 
             alt="Deer in forest"
             loading="eager"
             onerror="this.onerror=null; this.src='assets/deer.jpeg'; this.onerror=function(){this.src='assets/deer.JPG'; this.onerror=function(){this.src='assets/deer.JPEG';};};" />
      <?php endif; ?>
    </div>
  </section>
  <style>
    /* Prevent old cached images from flashing */
    .hero-contained-img.basement-banner-cropped {
      background-image: none !important;
    }
  </style>

  <main class="section">
    <div class="container room-hero">
      <section>
        <h2 class="room-gallery-section-heading"><?php echo $roomGallerySectionHeading; ?></h2>
        <div class="gallery" id="room-basement-gallery">
          <?php if (!empty($roomGalleryPreview)): ?>
            <?php foreach ($roomGalleryPreview as $index => $imageUrl): ?>
              <?php
              $thumbHints = ['Inside', 'Next', 'Also', 'Details', 'Another angle'];
              $thumbLine = $roomGalleryTitlePlain . ' · ' . $thumbHints[$index % count($thumbHints)] . ' (' . ($index + 1) . ')';
              ?>
              <?php $thumbClass = btb_room_gallery_thumb_class((int) $index); ?>
              <img<?php echo $thumbClass !== '' ? ' class="' . htmlspecialchars($thumbClass, ENT_QUOTES, 'UTF-8') . '"' : ''; ?> src="<?php echo htmlspecialchars($imageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo safeOutput($thumbLine, ''); ?>" loading="lazy" decoding="async" onclick="openGalleryModal('room', <?php echo (int) $index; ?>)" />
            <?php endforeach; ?>
            <?php if (btb_room_gallery_show_view_all($roomGalleryTotal)): ?>
              <button type="button" class="<?php echo htmlspecialchars(btb_room_gallery_view_all_tile_class($roomGalleryTotal), ENT_QUOTES, 'UTF-8'); ?>" onclick="openGalleryModal('room', 0)" aria-label="<?php echo htmlspecialchars('Open gallery, ' . $roomGalleryTotal . ' photos', ENT_QUOTES, 'UTF-8'); ?>">
                View all <?php echo (int) $roomGalleryTotal; ?> photos
              </button>
            <?php endif; ?>
          <?php endif; ?>
        </div>
        <h2 class="room-gallery-section-heading room-gallery-section-heading--below"><?php echo $commonGallerySectionHeading; ?></h2>
        <div class="gallery" id="room-basement-common-gallery">
          <?php if (!empty($commonGalleryPreview)): ?>
            <?php foreach ($commonGalleryPreview as $index => $imageUrl): ?>
              <?php
              $commonHints = ['Shared space', 'Hall', 'Kitchen', 'Living area', 'Another view'];
              $commonThumb = $commonGalleryAltPlain . ' · ' . $commonHints[$index % count($commonHints)] . ' (' . ($index + 1) . ')';
              ?>
              <?php $thumbClass = btb_room_gallery_thumb_class((int) $index); ?>
              <img<?php echo $thumbClass !== '' ? ' class="' . htmlspecialchars($thumbClass, ENT_QUOTES, 'UTF-8') . '"' : ''; ?> src="<?php echo htmlspecialchars($imageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo safeOutput($commonThumb, ''); ?>" loading="lazy" decoding="async" onclick="openGalleryModal('common', <?php echo (int) $index; ?>)" />
            <?php endforeach; ?>
            <?php if (btb_room_gallery_show_view_all($commonGalleryTotal)): ?>
              <button type="button" class="<?php echo htmlspecialchars(btb_room_gallery_view_all_tile_class($commonGalleryTotal), ENT_QUOTES, 'UTF-8'); ?>" onclick="openGalleryModal('common', 0)" aria-label="<?php echo htmlspecialchars('Open gallery, ' . $commonGalleryTotal . ' photos', ENT_QUOTES, 'UTF-8'); ?>">
                View all <?php echo (int) $commonGalleryTotal; ?> photos
              </button>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </section>
      <aside class="card room-booking-card" id="booking">
        <h2>Book this room</h2>
        <p class="room-booking-price"><?php echo $price; ?></p>
        <p class="room-booking-capacity"><?php echo $capacity; ?></p>
        <?php require __DIR__ . '/room_booking_description_mobile.php'; ?>
        <?php $btbRoomPricing = btb_room_booking_public_pricing_context($conn, $content, 'basement', 'Loki Suite'); ?>
        <form class="booking-form" data-room="Loki Suite" data-custom-handler novalidate style="margin-top: 24px;"<?php echo $btbRoomPricing['data_attrs']; ?>>
          <div class="form-row">
            <div>
              <label for="checkin">Check‑in</label>
              <input id="checkin" name="checkin" type="date" required />
            </div>
            <div>
              <label for="checkout">Check‑out</label>
              <input id="checkout" name="checkout" type="date" required />
            </div>
          </div>
          <?php require __DIR__ . '/room_booking_min_stay_hint.php'; ?>
          <div class="form-row form-row--full">
            <div>
              <label for="name">Name</label>
              <input id="name" name="name" type="text" placeholder="Full name" required />
            </div>
          </div>
          <div class="form-row">
            <div>
              <label for="phone">Phone</label>
              <input id="phone" name="phone" type="tel" placeholder="+1 555 123‑4567" required />
            </div>
            <div>
              <label for="email">Email</label>
              <input id="email" name="email" type="email" placeholder="you@example.com" required />
            </div>
          </div>
          <div class="form-row">
            <div>
              <label for="guests">Guests</label>
              <select id="guests" name="guests" required>
                <option value="" disabled selected hidden>Select guests</option>
                <option value="1">1</option>
                <option value="2">2</option>
              </select>
            </div>
            <div>
              <label for="pets">Dogs</label>
              <select id="pets" name="pets" required>
                <option value="" disabled selected hidden>Any dogs?</option>
                <option value="0">No dogs</option>
                <option value="1">1 dog</option>
                <option value="2">2 dogs</option>
              </select>
            </div>
          </div>

          <?php require __DIR__ . '/booking_guest_message_field.php'; ?>

          <?php require __DIR__ . '/room_booking_estimate.php'; ?>

          <button class="btn primary" type="submit"><?php echo $roomBookNowButtonLabel; ?></button>
          <p class="notice" style="margin-bottom: 0;">Prices are approximate. Confirmation will be sent by email.</p>
          <?php require __DIR__ . '/checkin_conditions_link.php'; ?>
        </form>
      </aside>
    </div>
  </main>

  <section id="wellness-section" class="section alt" style="display:none;">
    <div class="container">
      <h2><?php echo $wellnessTitle; ?></h2>
      <p class="section-lead"><?php echo $wellnessDescription; ?></p>

      <button type="button" class="btb-text-read-more wellness-section__read-more" aria-expanded="false" hidden>Read more</button>
      <div class="wellness-section__more">
      <section class="card card-massage">
        <div class="card-img">
          <?php if (!empty($wellnessMassageImageUrl)): ?>
            <img id="wellness-massage-img" class="floor-photo media-43" src="<?php echo htmlspecialchars($wellnessMassageImageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8'); ?>" alt="Wellness at Back to Base" />
          <?php else: ?>
            <img id="wellness-massage-img" class="floor-photo media-43" src="assets/massage.jpg" alt="Wellness at Back to Base" />
          <?php endif; ?>
        </div>
        <div class="card-body">
          <h2><?php echo $wellnessMassageTitle; ?></h2>
          <p><?php echo $wellnessMassageDescription; ?></p>
          <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a class="btn primary" href="massage.php">Explore wellness</a>
          </div>
        </div>
      </section>
      </div>
    </div>
  </section>

  <?php require __DIR__ . '/room_booking_sticky_bar.php'; ?>

<?php require __DIR__ . '/site_footer.php'; ?>

  <?php require __DIR__ . '/checkin_conditions_modal.php'; ?>

  <!-- Gallery Modal -->
  <div id="gallery-modal" class="gallery-modal">
    <div class="gallery-modal-content">
      <button class="gallery-modal-close" id="gallery-close">&times;</button>
      <img id="gallery-modal-image" class="gallery-modal-image" src="" alt="">
      <button class="gallery-modal-nav gallery-modal-prev" id="gallery-prev">‹</button>
      <button class="gallery-modal-nav gallery-modal-next" id="gallery-next">›</button>
      <div class="gallery-modal-counter" id="gallery-counter"></div>
    </div>
  </div>

  <script src="utils.js?v=26"></script>
  <script>
    // Prevent script.js from updating banner image if it's already loaded via SSR
    (function() {
      const banner = document.getElementById('room-basement-banner');
      if (banner && banner.hasAttribute('data-ssr-loaded')) {
        // Override any attempts to update the banner image
        const originalSrc = banner.src;
        Object.defineProperty(banner, 'src', {
          get: function() { return originalSrc; },
          set: function(value) {
            // Only allow updates if the new value is different and not from script.js processing
            if (value && !value.includes('data-src-base') && value !== originalSrc) {
              Object.defineProperty(banner, 'src', {
                value: value,
                writable: true,
                configurable: true
              });
            }
          },
          configurable: true
        });
      }
    })();
  </script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" onerror="this.onerror=null; this.href='';">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js" onerror="console.warn('Flatpickr failed to load, date inputs will use native browser picker');"></script>
  <script src="script.js?v=45"></script>
  <script src="auth.js?v=26"></script>
  <script>
    // Gallery functionality for Loki Suite (room + common areas)
    const roomGalleryImages = <?php echo json_encode($roomGalleryUrls, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG); ?>;
    const commonGalleryImages = <?php echo json_encode($commonGalleryUrls, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG); ?>;
    const roomGalleryAltPrefix = <?php echo json_encode($roomGalleryTitlePlain, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;
    const commonGalleryAltPrefix = <?php echo json_encode($commonGalleryAltPlain, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;
    let activeGallery = 'room';
    let currentImageIndex = 0;

    function getActiveGalleryImages() {
      return activeGallery === 'common' ? commonGalleryImages : roomGalleryImages;
    }

    function getActiveGalleryAltPrefix() {
      return activeGallery === 'common' ? commonGalleryAltPrefix : roomGalleryAltPrefix;
    }

    function openGalleryModal(galleryKey, index) {
      activeGallery = galleryKey === 'common' ? 'common' : 'room';
      const images = getActiveGalleryImages();
      if (images.length === 0) return;
      currentImageIndex = index;
      updateGalleryModal();
      const modal = document.getElementById('gallery-modal');
      if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
      }
    }
    
    function closeGalleryModal() {
      const modal = document.getElementById('gallery-modal');
      if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
      }
    }
    
    function showPreviousImage() {
      const images = getActiveGalleryImages();
      if (images.length === 0) return;
      currentImageIndex = (currentImageIndex - 1 + images.length) % images.length;
      updateGalleryModal();
    }
    
    function showNextImage() {
      const images = getActiveGalleryImages();
      if (images.length === 0) return;
      currentImageIndex = (currentImageIndex + 1) % images.length;
      updateGalleryModal();
    }
    
    function updateGalleryModal() {
      const modalImage = document.getElementById('gallery-modal-image');
      const counter = document.getElementById('gallery-counter');
      const images = getActiveGalleryImages();
      const altPrefix = getActiveGalleryAltPrefix();
      
      if (!modalImage || images.length === 0) return;
      
      // Adding a smooth transition
      modalImage.style.opacity = '0';
      
      setTimeout(() => {
        const cacheBuster = '?v=' + Date.now();
        modalImage.src = images[currentImageIndex] + cacheBuster;
        modalImage.alt = altPrefix + ' — slide ' + (currentImageIndex + 1) + ' of ' + images.length;
        if (counter) counter.textContent = `${currentImageIndex + 1} / ${images.length}`;
        
        // Showing the image with a smooth fade in
        modalImage.style.opacity = '1';
      }, 150);
    }
    
    // Initialize gallery handlers
    document.addEventListener('DOMContentLoaded', () => {
      const galleryClose = document.getElementById('gallery-close');
      const galleryModal = document.getElementById('gallery-modal');
      const galleryPrev = document.getElementById('gallery-prev');
      const galleryNext = document.getElementById('gallery-next');
      
      if (galleryClose) {
        galleryClose.addEventListener('click', closeGalleryModal);
      }
      
      if (galleryModal) {
        galleryModal.addEventListener('click', (e) => {
          if (e.target.id === 'gallery-modal') {
            closeGalleryModal();
          }
        });
      }
      
      if (galleryPrev) {
        galleryPrev.addEventListener('click', showPreviousImage);
      }
      
      if (galleryNext) {
        galleryNext.addEventListener('click', showNextImage);
      }
      
      // Keyboard navigation
      document.addEventListener('keydown', (e) => {
        if (galleryModal && galleryModal.classList.contains('active')) {
          if (e.key === 'Escape') closeGalleryModal();
          if (e.key === 'ArrowLeft') showPreviousImage();
          if (e.key === 'ArrowRight') showNextImage();
        }
      });
    });
  </script>
  <script src="auth-menu.js"></script>
  <script src="booking.js"></script>
  <!-- Wellness Experiences content is now loaded via Server-Side Rendering (SSR) -->
</body>
</html>

