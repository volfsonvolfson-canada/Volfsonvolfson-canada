<?php
// Server-Side Rendering for Massage page
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

// Load mini-hotel data from room_cards_settings if it exists, otherwise from content_settings
$miniHotelData = [];
$roomCardsTableCheck = $conn->query("SHOW TABLES LIKE 'room_cards_settings'");
if ($roomCardsTableCheck && $roomCardsTableCheck->num_rows > 0) {
    $roomCardsResult = $conn->query("SELECT mini_hotel_title, mini_hotel_description_1, mini_hotel_description_2, mini_hotel_image_url FROM room_cards_settings WHERE id = 1");
    if ($roomCardsResult && $roomCardsResult->num_rows > 0) {
        $miniHotelData = $roomCardsResult->fetch_assoc();
    }
}

// Helper functions for safe output with fallback
function safeOutput($value, $fallback = '') {
    return htmlspecialchars($value ?? $fallback, ENT_QUOTES, 'UTF-8');
}

function safeOutputWithBreaks($value, $fallback = '') {
    $text = $value ?? $fallback;
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    return nl2br($text);
}

// Extract content with fallback values
$heroTitle = safeOutput($content['massage_hero_title'] ?? '', 'Massage services');
$intro = safeOutputWithBreaks($content['massage_intro'] ?? '', 'Massage is available as an add-on to your apartment rental or as a stand-alone booking. Whether you want to release tension, restore energy, or simply relax, our experienced therapists are always ready to help.');

// Relaxing Massage
$relaxingTitle = safeOutput($content['massage_relaxing_title'] ?? '', 'Relaxing Massage');
$relaxingDescription = safeOutputWithBreaks($content['massage_relaxing_description'] ?? '', 'This gentle massage, perfect for those who want to unwind and restore their energy, uses smooth strokes and calming techniques that relieve stress, improve circulation, and promote relaxation. After the session, you will feel refreshed and relaxed.');
$relaxingImageUrl = isset($content['massage_relaxing_image_url']) && !empty(trim($content['massage_relaxing_image_url'])) ? safeOutput($content['massage_relaxing_image_url'], '') : '';

// Deep Tissue Massage
$deepTissueTitle = safeOutput($content['massage_deep_tissue_title'] ?? '', 'Deep Tissue Massage');
$deepTissueDescription = safeOutputWithBreaks($content['massage_deep_tissue_description'] ?? '', 'For targeted relief of muscle tension and pain, we offer deep tissue massage, designed to address chronic stiffness and discomfort in the deeper layers of muscle. It is ideal for those experiencing pain or tightness in specific areas.');
$deepTissueImageUrl = isset($content['massage_deep_tissue_image_url']) && !empty(trim($content['massage_deep_tissue_image_url'])) ? safeOutput($content['massage_deep_tissue_image_url'], '') : '';

// Reiki Energy Healing
$reikiTitle = safeOutput($content['massage_reiki_title'] ?? '', 'Reiki Energy Healing');
$reikiDescription = safeOutputWithBreaks($content['massage_reiki_description'] ?? '', 'Experience the gentle yet powerful effect of Reiki — a Japanese energy healing technique that promotes relaxation and balances the body\'s energy. This hands-on healing method helps remove energy blockages, restore inner harmony, and reduce stress levels.');
$reikiImageUrl = isset($content['massage_reiki_image_url']) && !empty(trim($content['massage_reiki_image_url'])) ? safeOutput($content['massage_reiki_image_url'], '') : '';

// Sauna
$saunaTitle = safeOutput($content['massage_sauna_title'] ?? '', 'Sauna');
$saunaDescription = safeOutputWithBreaks($content['massage_sauna_description'] ?? '', 'After a day spent in nature, sometimes you just want to warm up. We understand how important comfort is, so we offer our guests access to a small sauna. It is located right in the house, on the basement floor.');
$saunaImageUrl = isset($content['massage_sauna_image_url']) && !empty(trim($content['massage_sauna_image_url'])) ? safeOutput($content['massage_sauna_image_url'], '') : '';

// Mini-hotel section - use room_cards_settings if available, otherwise content_settings
$miniHotelTitle = safeOutput(!empty($miniHotelData) ? ($miniHotelData['mini_hotel_title'] ?? '') : ($content['mini_hotel_title'] ?? ''), 'Book a room in our mini-hotel');
$miniHotelDescription1 = safeOutputWithBreaks(!empty($miniHotelData) ? ($miniHotelData['mini_hotel_description_1'] ?? '') : ($content['mini_hotel_description_1'] ?? ''), 'After your relaxing massage session, why not extend your stay? Our cozy mini-hotel offers comfortable rooms and apartments where you can fully unwind and enjoy the peaceful atmosphere of Back to Base.');
$miniHotelDescription2 = safeOutputWithBreaks(!empty($miniHotelData) ? ($miniHotelData['mini_hotel_description_2'] ?? '') : ($content['mini_hotel_description_2'] ?? ''), 'Located just 35 km from Nelson, BC, surrounded by forest near Kootenay Lake with beautiful views of Mount Loki. Easy online booking — perfect for a peaceful vacation and retreat in nature.');
$miniHotelImageUrl = '';
if (!empty($miniHotelData) && isset($miniHotelData['mini_hotel_image_url']) && !empty(trim($miniHotelData['mini_hotel_image_url']))) {
    $miniHotelImageUrl = safeOutput($miniHotelData['mini_hotel_image_url'], '');
} elseif (isset($content['mini_hotel_image_url']) && !empty(trim($content['mini_hotel_image_url']))) {
    $miniHotelImageUrl = safeOutput($content['mini_hotel_image_url'], '');
} else {
    $miniHotelImageUrl = 'assets/hero.jpg';
}

// Booking section title
$bookingTitle = safeOutput($content['massage_booking_title'] ?? '', 'Book a Massage or Sauna');

// Cache buster for images
$cacheBuster = '?v=' . time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="light dark">
  <title>Massage — Back to Base</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
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
      <a class="logo" href="index.html">
        <img alt="Back to Base" class="logo-img" />
        <span class="logo-text">Back to Base</span>
      </a>
      <nav class="nav">
        <a href="index.html#rooms">Rooms</a>
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
    <a href="index.html#rooms">Rooms</a>
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

  <div class="page-header">
    <div class="container">
      <div class="breadcrumbs"><a href="index.html">Home</a> / Massage</div>
      <h1><?php echo $heroTitle; ?></h1>
      <p class="section-lead"><?php echo $intro; ?></p>
    </div>
  </div>

  <main class="section">
    <div class="container">
      <section class="card card-massage">
        <div class="card-img">
          <?php if (!empty($relaxingImageUrl)): ?>
            <img class="floor-photo media-43" src="<?php echo htmlspecialchars($relaxingImageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8'); ?>" alt="Relaxation massage" />
          <?php else: ?>
            <img class="floor-photo media-43" data-src-base="assets/Relaxation Massage|assets/relaxation|assets/relaxation-massage|assets/Relaxation_Massage|assets/Relaxing Massage|assets/Relaxing_Massage|assets/RelaxationMassage|assets/RelaxingMassage|assets/relax" alt="Relaxation massage" />
          <?php endif; ?>
        </div>
        <div class="card-body">
          <h2><?php echo $relaxingTitle; ?></h2>
          <p><?php echo $relaxingDescription; ?></p>
          <ul class="massage-list">
            <li>60 minutes — 110 CAD</li>
            <li>90 minutes — 160 CAD</li>
          </ul>
        </div>
      </section>

      <section class="card card-massage card-massage--alt">
        <div class="card-img">
          <?php if (!empty($deepTissueImageUrl)): ?>
            <img class="floor-photo media-43" src="<?php echo htmlspecialchars($deepTissueImageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8'); ?>" alt="Deep tissue massage" />
          <?php else: ?>
            <img class="floor-photo media-43" data-src-base="assets/Deep Tissue Massage|assets/deep|assets/deep-tissue-massage|assets/Deep_Tissue_Massage" alt="Deep tissue massage" />
          <?php endif; ?>
        </div>
        <div class="card-body">
          <h2><?php echo $deepTissueTitle; ?></h2>
          <p><?php echo $deepTissueDescription; ?></p>
          <ul class="massage-list">
            <li>60 minutes — 120 CAD</li>
            <li>90 minutes — 170 CAD</li>
          </ul>
        </div>
      </section>

      <section class="card card-massage">
        <div class="card-img">
          <?php if (!empty($reikiImageUrl)): ?>
            <img class="floor-photo media-43" src="<?php echo htmlspecialchars($reikiImageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8'); ?>" alt="Reiki energy healing" />
          <?php else: ?>
            <img class="floor-photo media-43" data-src-base="assets/Reiki Energy Healing|assets/reiki|assets/reiki-energy-healing|assets/Reiki_Energy_Healing" alt="Reiki energy healing" />
          <?php endif; ?>
        </div>
        <div class="card-body">
          <h2><?php echo $reikiTitle; ?></h2>
          <p><?php echo $reikiDescription; ?></p>
          <ul class="massage-list">
            <li>15 minutes on the go — 25 CAD</li>
            <li>30 minutes as an add-on — 50 CAD</li>
          </ul>
        </div>
      </section>

      <section class="card card-massage card-massage--alt">
        <div class="card-img">
          <?php if (!empty($saunaImageUrl)): ?>
            <img class="floor-photo media-43" src="<?php echo htmlspecialchars($saunaImageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8'); ?>" alt="Sauna at Back to Base" />
          <?php else: ?>
            <img class="floor-photo media-43" data-src-base="assets/plan-basement-bedroom|assets/sauna|assets/Sauna" alt="Sauna at Back to Base" />
          <?php endif; ?>
        </div>
        <div class="card-body">
          <h2><?php echo $saunaTitle; ?></h2>
          <p><?php echo $saunaDescription; ?></p>
          <ul class="massage-list">
            <li>1 hour — 25 CAD</li>
          </ul>
        </div>
      </section>

      <section class="card" id="book">
        <h2><?php echo $bookingTitle; ?></h2>
        <form id="massage-form" novalidate>
          <div class="form-row">
            <div>
              <label for="type">Service type</label>
              <select id="type" name="type" required>
                <option>Relaxing Massage</option>
                <option>Deep Tissue Massage</option>
                <option>Reiki Energy Healing</option>
                <option>Sauna</option>
              </select>
            </div>
            <div>
              <label for="duration">Duration</label>
              <select id="duration" name="duration" required>
                <option value="60">60 minutes</option>
                <option value="90">90 minutes</option>
              </select>
            </div>
            <div>
              <label for="date">Date</label>
              <input id="date" name="date" type="date" required />
            </div>
          </div>
          <div class="form-row">
            <div>
              <label for="time">Time</label>
              <input id="time" name="time" type="time" min="09:00" max="21:00" required />
              <small style="color: var(--muted); font-size: 0.875rem; margin-top: 4px; display: block;">Available: 9:00 AM - 9:00 PM (30-minute intervals)</small>
            </div>
            <div>
              <label for="name">Your name</label>
              <input id="name" name="name" type="text" placeholder="Full name" required />
            </div>
          </div>
          <div class="form-row">
            <div>
              <label for="email">Email</label>
              <input id="email" name="email" type="email" placeholder="you@example.com" required />
            </div>
            <div>
              <label for="phone">Phone</label>
              <input id="phone" name="phone" type="tel" placeholder="+1 555 123‑4567" required />
            </div>
          </div>
          <button class="btn primary" type="submit">Book service</button>
        </form>
      </section>

      <section id="room-reminder" class="card card-massage" style="display:none;">
        <div class="card-img">
          <img class="floor-photo media-43" src="assets/hero.jpg" alt="Back to Base house near Kootenay Lake" />
        </div>
        <div class="card-body">
          <h2>Remember to book your room</h2>
          <p>If you haven't booked your stay in our mini-hotel yet, now is the best time.</p>
          <p>Just 35 km from Nelson, BC, our guesthouse offers cozy rooms and apartments for rent, surrounded by forest near Kootenay Lake with views of Mount Loki. Easy booking online — enjoy a peaceful vacation and retreat in nature.</p>
          <a class="btn primary" href="index.html#rooms">View rooms</a>
        </div>
      </section>

      <!-- Book a Room Section -->
      <section class="card" style="margin-top: 40px;">
        <div class="room-booking-section">
          <div class="room-booking-text">
            <h2><?php echo $miniHotelTitle; ?></h2>
            <p><?php echo $miniHotelDescription1; ?></p>
            <p><?php echo $miniHotelDescription2; ?></p>
            <a class="btn primary" href="index.html#rooms" style="margin-top: 20px; display: inline-block;">View rooms</a>
          </div>
          <div class="room-booking-image" style="cursor: pointer;" onclick="openRoomGallery(0)">
            <img id="room-gallery-main" 
                 src="<?php echo htmlspecialchars($miniHotelImageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8'); ?>" 
                 alt="Back to Base rooms" 
                 style="width: 100%; height: 100%; object-fit: cover; border-radius: 12px; transition: transform 0.3s ease;"
                 onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1566073771259-6a8506099945?q=80&w=1200&auto=format&fit=crop';"
                 onmouseover="this.style.transform='scale(1.02)'"
                 onmouseout="this.style.transform='scale(1)'">
          </div>
        </div>
      </section>
    </div>
  </main>

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

  <!-- Gallery Modal -->
  <div id="gallery-modal" class="gallery-modal">
    <span class="gallery-modal-close" onclick="closeRoomGallery()">&times;</span>
    <span class="gallery-modal-nav gallery-modal-prev" onclick="changeRoomGalleryImage(-1)">&#10094;</span>
    <span class="gallery-modal-nav gallery-modal-next" onclick="changeRoomGalleryImage(1)">&#10095;</span>
    <div class="gallery-modal-content">
      <img id="gallery-modal-image" class="gallery-modal-image" src="" alt="">
    </div>
    <div class="gallery-modal-counter">
      <span id="gallery-counter">1 / 1</span>
    </div>
  </div>

  <style>
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
      transition: opacity 0.3s ease;
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
    /* Room Booking Section */
    .room-booking-section {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 40px;
      align-items: center;
    }
    .room-booking-text {
      flex: 1;
    }
    .room-booking-image {
      flex: 1;
      width: 100%;
      aspect-ratio: 4/3;
      overflow: hidden;
      border-radius: 12px;
    }
    @media (max-width: 768px) {
      .room-booking-section {
        grid-template-columns: 1fr;
        gap: 30px;
      }
      .card-body {
        grid-template-columns: 1fr !important;
      }
    }
  </style>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js"></script>
  <script src="script.js?v=32"></script>
  <script src="booking.js"></script>
  <script src="auth.js?v=26"></script>
  <script>
    // Room Gallery
    const roomGalleryImages = [
      '<?php echo htmlspecialchars($miniHotelImageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8'); ?>',
      'https://images.unsplash.com/photo-1566073771259-6a8506099945?q=80&w=1200&auto=format&fit=crop',
      'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?q=80&w=1200&auto=format&fit=crop',
      'https://images.unsplash.com/photo-1590490360182-c33d57733427?q=80&w=1200&auto=format&fit=crop',
      'https://images.unsplash.com/photo-1522771739844-6a9f6d5f14af?q=80&w=1200&auto=format&fit=crop',
      'https://images.unsplash.com/photo-1578683010236-d716f9a3f461?q=80&w=1200&auto=format&fit=crop'
    ];

    let currentRoomImageIndex = 0;

    function openRoomGallery(index) {
      currentRoomImageIndex = index || 0;
      const modal = document.getElementById('gallery-modal');
      const modalImage = document.getElementById('gallery-modal-image');
      const counter = document.getElementById('gallery-counter');
      
      modalImage.src = roomGalleryImages[currentRoomImageIndex];
      counter.textContent = `${currentRoomImageIndex + 1} / ${roomGalleryImages.length}`;
      modal.classList.add('active');
      document.body.style.overflow = 'hidden';
    }

    function closeRoomGallery() {
      const modal = document.getElementById('gallery-modal');
      modal.classList.remove('active');
      document.body.style.overflow = '';
    }

    function changeRoomGalleryImage(direction) {
      currentRoomImageIndex += direction;
      
      if (currentRoomImageIndex < 0) {
        currentRoomImageIndex = roomGalleryImages.length - 1;
      } else if (currentRoomImageIndex >= roomGalleryImages.length) {
        currentRoomImageIndex = 0;
      }
      
      const modalImage = document.getElementById('gallery-modal-image');
      const counter = document.getElementById('gallery-counter');
      
      modalImage.style.opacity = '0';
      
      setTimeout(() => {
        modalImage.src = roomGalleryImages[currentRoomImageIndex];
        counter.textContent = `${currentRoomImageIndex + 1} / ${roomGalleryImages.length}`;
        modalImage.style.opacity = '1';
      }, 150);
    }

    // Close gallery when clicking outside
    document.getElementById('gallery-modal').addEventListener('click', function(e) {
      if (e.target === this || e.target.classList.contains('gallery-modal-content')) {
        closeRoomGallery();
      }
    });

    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
      const modal = document.getElementById('gallery-modal');
      if (modal.classList.contains('active')) {
        if (e.key === 'Escape') {
          closeRoomGallery();
        } else if (e.key === 'ArrowLeft') {
          changeRoomGalleryImage(-1);
        } else if (e.key === 'ArrowRight') {
          changeRoomGalleryImage(1);
        }
      }
    });

    // Set main image on page load
    window.addEventListener('load', function() {
      const mainImage = document.getElementById('room-gallery-main');
      if (mainImage && roomGalleryImages.length > 0) {
        mainImage.src = roomGalleryImages[0];
      }
    });
  </script>
</body>
</html>


