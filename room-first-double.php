<?php
// Server-Side Rendering for The Nouk page
require_once 'common.php';

// Redirect from .html to .php if accessed via old URL
if (basename($_SERVER['PHP_SELF']) === 'room-first-double.html' || 
    (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'room-first-double.html') !== false)) {
    header('Location: room-first-double.php', true, 301);
    exit;
}

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
$wellnessImagesEnabled = false;
if ($wellnessTableCheck && $wellnessTableCheck->num_rows > 0) {
    $wellnessImages = fetchOne($conn, "SELECT * FROM wellness_settings WHERE id = 1");
    if ($wellnessImages) {
        $wellnessImagesEnabled = true;
    } else {
        $wellnessImages = [];
    }
}

// Helper function for safe HTML output (allows specific tags like <strong>)
function safeHtmlOutput($value, $fallback = '') {
    if (empty($value)) return $fallback;
    // Allow specific HTML tags
    $allowedTags = '<strong><em><b><i><br>';
    return strip_tags($value, $allowedTags);
}

// Extract Wellness Experiences content with fallback values
$wellnessTitle = safeOutput($content['wellness_title'] ?? '', 'Wellness Experiences');
$wellnessDescription = safeOutputWithBreaks($content['wellness_description'] ?? '', 'Enhance your stay with optional massage: relaxing or deep tissue sessions with an experienced therapist — an easy way to make your time in the mountains feel even more restorative.');

$wellnessMassageTitle = safeOutput($content['wellness_massage_title'] ?? '', 'Wellness');
$wellnessMassageDescription = safeOutputWithBreaks($content['wellness_massage_description'] ?? '', 'Our guesthouse has a massage room with an experienced therapist who will be happy to make your stay even more enjoyable. Whether you prefer a relaxing massage or a therapeutic deep tissue session — the choice is yours.');
$wellnessMassageImageUrl = '';
if ($wellnessImagesEnabled && !empty(trim($wellnessImages['wellness_massage_image_url'] ?? ''))) {
    $wellnessMassageImageUrl = safeOutput($wellnessImages['wellness_massage_image_url'], '');
} elseif (isset($content['wellness_massage_image_url']) && !empty(trim($content['wellness_massage_image_url']))) {
    $wellnessMassageImageUrl = safeOutput($content['wellness_massage_image_url'], '');
}

// Extract content with fallback values
$title = safeOutput($content['room_ground_queen_title'] ?? '', 'The Nouk');
$roomGalleryTitlePlain = trim((string) ($content['room_ground_queen_title'] ?? ''));
if ($roomGalleryTitlePlain === '') {
    $roomGalleryTitlePlain = 'The Nouk';
}
$subtitle = safeOutputWithBreaks($content['room_ground_queen_subtitle'] ?? '', 'Bright room near the living room with fireplace. Ideal for two.');
$description = safeOutputWithBreaks($content['room_ground_queen_description'] ?? '', 'A small but bright room with a large double bed. A shared bathroom with a spacious bathtub is located nearby.');
$price = btb_room_price_line_html($content, 'ground_queen', btb_room_price_default_line_html('ground_queen'));
$capacity = isset($content['room_ground_queen_capacity']) && !empty(trim($content['room_ground_queen_capacity'])) 
    ? safeHtmlOutput($content['room_ground_queen_capacity']) 
    : '<strong>Capacity:</strong> up to 2 guests';
$note = safeOutputWithBreaks($content['room_ground_queen_note'] ?? '', '*All tenants may use the sauna and home theatre free of charge, as long as it does not disturb other guests.');

// Banner image
$bannerImageUrl = isset($content['room_ground_queen_banner_image_url']) && !empty(trim($content['room_ground_queen_banner_image_url'])) 
    ? safeOutput($content['room_ground_queen_banner_image_url'], '') 
    : '';

// Gallery
$galleryJson = $content['room_ground_queen_gallery'] ?? '[]';
$gallery = [];
try {
    $gallery = json_decode($galleryJson, true);
    if (!is_array($gallery)) {
        $gallery = [];
    }
} catch (Exception $e) {
    $gallery = [];
}

$tRoomGalH = trim((string) ($content['room_ground_queen_gallery_section_title'] ?? ''));
$roomGallerySectionHeading = htmlspecialchars($tRoomGalH !== '' ? $tRoomGalH : 'Room photos', ENT_QUOTES, 'UTF-8');
$tCommonGalH = trim((string) ($content['room_ground_queen_common_gallery_section_title'] ?? ''));
$commonGallerySectionHeading = htmlspecialchars($tCommonGalH !== '' ? $tCommonGalH : 'Common areas photos', ENT_QUOTES, 'UTF-8');
$commonGalleryJson = $content['room_ground_queen_common_gallery'] ?? '[]';
$commonGallery = [];
try {
    $commonGallery = json_decode($commonGalleryJson, true);
    if (!is_array($commonGallery)) {
        $commonGallery = [];
    }
} catch (Exception $e) {
    $commonGallery = [];
}
$commonGalleryAltPlain = trim((string) ($content['room_ground_queen_common_gallery_section_title'] ?? ''));
if ($commonGalleryAltPlain === '') {
    $commonGalleryAltPlain = 'Common areas';
}

$roomGalleryUrls = btb_room_gallery_valid_urls($gallery);
$roomGalleryTotal = count($roomGalleryUrls);
$roomGalleryPreview = $roomGalleryTotal > 5 ? array_slice($roomGalleryUrls, 0, 5) : $roomGalleryUrls;
$commonGalleryUrls = btb_room_gallery_valid_urls($commonGallery);
$commonGalleryTotal = count($commonGalleryUrls);
$commonGalleryPreview = $commonGalleryTotal > 5 ? array_slice($commonGalleryUrls, 0, 5) : $commonGalleryUrls;

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
  <title>The Nouk | Back to Base</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" onerror="this.onerror=null; this.href='';">
  <style>
    /* Fallback fonts if Google Fonts fails to load */
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
    /* Prevent old cached images from flashing */
    .hero-contained-img.basement-banner-cropped {
      background-image: none !important;
    }
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
  <link rel="stylesheet" href="styles.css">
</head>
<body>
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

  <section class="hero-contained" aria-label="Room banner">
    <div class="container">
      <?php if (!empty($bannerImageUrl)): ?>
        <img id="room-ground-queen-banner" 
             class="hero-contained-img basement-banner-cropped" 
             src="<?php echo htmlspecialchars($bannerImageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8'); ?>" 
             alt="Fox in forest"
             loading="eager"
             decoding="async"
             style="background-image: none !important;"
             data-ssr-loaded="true" />
      <?php else: ?>
        <!-- Fallback: use default image without data-src-base to prevent script.js from processing it -->
        <img id="room-ground-queen-banner" 
             class="hero-contained-img basement-banner-cropped" 
             src="assets/forest-fox.jpg" 
             alt="Fox in forest"
             loading="eager"
             onerror="this.onerror=null; this.src='assets/forest-fox.jpeg'; this.onerror=function(){this.src='assets/forest-fox.JPG'; this.onerror=function(){this.src='assets/forest-fox.JPEG';};};" />
      <?php endif; ?>
    </div>
  </section>

  <main class="section">
    <div class="container room-hero">
      <section>
        <h2 class="room-gallery-section-heading"><?php echo $roomGallerySectionHeading; ?></h2>
        <div class="gallery" id="room-ground-queen-gallery">
          <?php if (!empty($roomGalleryPreview)): ?>
            <?php foreach ($roomGalleryPreview as $index => $imageUrl): ?>
              <?php
              $thumbHints = ['Inside', 'Next', 'Also', 'Details', 'Another angle'];
              $thumbLine = $roomGalleryTitlePlain . ' · ' . $thumbHints[$index % count($thumbHints)] . ' (' . ($index + 1) . ')';
              ?>
              <img src="<?php echo htmlspecialchars($imageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo safeOutput($thumbLine, ''); ?>" loading="lazy" decoding="async" onclick="openGalleryModal('room', <?php echo (int) $index; ?>)" />
            <?php endforeach; ?>
            <?php if ($roomGalleryTotal > 5): ?>
              <button type="button" class="gallery-view-all-tile" onclick="openGalleryModal('room', 0)" aria-label="<?php echo htmlspecialchars('Open gallery, ' . $roomGalleryTotal . ' photos', ENT_QUOTES, 'UTF-8'); ?>">
                View all <?php echo (int) $roomGalleryTotal; ?> photos
              </button>
            <?php endif; ?>
          <?php endif; ?>
        </div>
        <h2 class="room-gallery-section-heading room-gallery-section-heading--below"><?php echo $commonGallerySectionHeading; ?></h2>
        <div class="gallery" id="room-ground-queen-common-gallery">
          <?php if (!empty($commonGalleryPreview)): ?>
            <?php foreach ($commonGalleryPreview as $index => $imageUrl): ?>
              <?php
              $commonHints = ['Shared space', 'Hall', 'Kitchen', 'Living area', 'Another view'];
              $commonThumb = $commonGalleryAltPlain . ' · ' . $commonHints[$index % count($commonHints)] . ' (' . ($index + 1) . ')';
              ?>
              <img src="<?php echo htmlspecialchars($imageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo safeOutput($commonThumb, ''); ?>" loading="lazy" decoding="async" onclick="openGalleryModal('common', <?php echo (int) $index; ?>)" />
            <?php endforeach; ?>
            <?php if ($commonGalleryTotal > 5): ?>
              <button type="button" class="gallery-view-all-tile" onclick="openGalleryModal('common', 0)" aria-label="<?php echo htmlspecialchars('Open gallery, ' . $commonGalleryTotal . ' photos', ENT_QUOTES, 'UTF-8'); ?>">
                View all <?php echo (int) $commonGalleryTotal; ?> photos
              </button>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </section>
      <aside class="card">
        <h2>Book this room</h2>
        <p><?php echo $price; ?></p>
        <p><?php echo $capacity; ?></p>
        <div style="margin-top: 32px;"><?php echo $description; ?></div>
        <p style="margin-top: 16px;"><?php echo $note; ?></p>
        <form class="booking-form" data-room="The Nouk" data-custom-handler novalidate style="margin-top: 40px;">
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
          <div class="form-row">
            <div>
              <label for="name">Name</label>
              <input id="name" name="name" type="text" placeholder="Full name" required />
            </div>
            <div>
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
                <option value="" disabled selected hidden>—</option>
                <option value="1">1</option>
                <option value="2">2</option>
              </select>
            </div>
            <div>
              <label for="pets">Pets</label>
              <select id="pets" name="pets" required>
                <option value="" disabled selected hidden>—</option>
                <option value="add">Add pets</option>
                <option value="no">No pets</option>
              </select>
            </div>
          </div>

          <button class="btn primary" type="submit">Book now</button>
          <p class="notice" style="margin-bottom: 0;">Prices are approximate. Confirmation will be sent by email.</p>
          <p class="notice" style="margin-top: 0;"><a href="#" id="checkin-conditions-link" style="color: var(--brand); text-decoration: underline; cursor: pointer;">Check-in conditions</a></p>
        </form>
      </aside>
    </div>
  </main>

  <section id="wellness-section" class="section alt" style="display:none;">
    <div class="container">
      <h2><?php echo $wellnessTitle; ?></h2>
      <p class="section-lead"><?php echo $wellnessDescription; ?></p>

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
  </section>

  <footer class="site-footer">
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
          <li><a href="index.html#rooms">Rooms</a></li>
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
        <p style="margin-top:1rem;font-size:0.9rem;"><a href="privacy.php">Privacy &amp; Cookies</a></p>
        <p style="margin-top:1rem;font-size:0.9rem;"><a href="#" id="btb-open-cookie-settings">Cookie settings</a></p>
      </div>
    </div>
    <div class="container copyright">© <span id="year"></span> Back to Base</div>
  </footer>

  <!-- Check-in Conditions Modal -->
  <div id="checkin-conditions-modal" class="checkin-modal">
    <div class="checkin-modal-overlay"></div>
    <div class="checkin-modal-content">
      <button class="checkin-modal-close" id="checkin-close">&times;</button>
      <h3>Check-in conditions</h3>
      <p>Check-in from 3:00 PM, Check-out until 11:00 AM.</p>
      <p>Detailed information on how to find our house and what is located nearby can be found on the About Us page</p>
    </div>
  </div>

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
      const banner = document.getElementById('room-ground-queen-banner');
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
  <script src="script.js?v=26"></script>
  <script>
    // Check-in conditions modal
    (function() {
      const modal = document.getElementById('checkin-conditions-modal');
      const link = document.getElementById('checkin-conditions-link');
      const closeBtn = document.getElementById('checkin-close');
      const overlay = modal.querySelector('.checkin-modal-overlay');
      
      if (!modal || !link) return;
      
      // Open modal
      link.addEventListener('click', (e) => {
        e.preventDefault();
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
      });
      
      // Close modal
      function closeModal() {
        modal.classList.remove('active');
        document.body.style.overflow = '';
      }
      
      closeBtn.addEventListener('click', closeModal);
      overlay.addEventListener('click', closeModal);
      
      // Close on Escape key
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.classList.contains('active')) {
          closeModal();
        }
      });
    })();
  </script>
  <script src="auth.js?v=26"></script>
  <script>
    // Gallery: The Nouk + common areas
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
      
      // Добавляем плавный переход
      modalImage.style.opacity = '0';
      
      setTimeout(() => {
        const cacheBuster = '?v=' + Date.now();
        modalImage.src = images[currentImageIndex] + cacheBuster;
        modalImage.alt = altPrefix + ' — slide ' + (currentImageIndex + 1) + ' of ' + images.length;
        if (counter) counter.textContent = `${currentImageIndex + 1} / ${images.length}`;
        
        // Показываем изображение с плавным появлением
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
  <!-- Flatpickr для визуальной блокировки занятых дат в календаре -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" onerror="this.onerror=null; this.href='';">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js" onerror="console.warn('Flatpickr failed to load, date inputs will use native browser picker');"></script>
  <script src="auth-menu.js"></script>
  <script src="booking.js"></script>
  <!-- Wellness Experiences content is now loaded via Server-Side Rendering (SSR) -->
</body>
</html>

