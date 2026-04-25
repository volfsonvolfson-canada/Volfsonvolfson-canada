<?php
// Server-Side Rendering for Wellness page (massage.php)
require_once 'common.php';

if (!function_exists('btb_default_massage_pricing_map')) {
    /**
     * @return array<string, list<array{duration:int,label:string,price:string}>>
     */
    function btb_default_massage_pricing_map() {
        return [
            'relaxing' => [
                ['duration' => 60, 'label' => '60 minutes', 'price' => '110 CAD'],
                ['duration' => 90, 'label' => '90 minutes', 'price' => '160 CAD'],
            ],
            'deep_tissue' => [
                ['duration' => 60, 'label' => '60 minutes', 'price' => '120 CAD'],
                ['duration' => 90, 'label' => '90 minutes', 'price' => '170 CAD'],
            ],
            'reiki' => [
                ['duration' => 15, 'label' => '15 minutes on the go', 'price' => '25 CAD'],
                ['duration' => 30, 'label' => '30 minutes as an add-on', 'price' => '50 CAD'],
            ],
            'sauna' => [
                ['duration' => 60, 'label' => '1 hour', 'price' => '25 CAD'],
            ],
        ];
    }

    function btb_parse_massage_pricing($jsonOrNull, $key) {
        $defaults = btb_default_massage_pricing_map();
        $default = $defaults[$key] ?? [];
        $decoded = json_decode((string)($jsonOrNull ?? ''), true);
        if (!is_array($decoded) || $decoded === []) {
            return $default;
        }
        $byDur = [];
        foreach ($decoded as $row) {
            if (is_array($row) && isset($row['duration'])) {
                $byDur[(int)$row['duration']] = $row;
            }
        }
        $out = [];
        foreach ($default as $rowDef) {
            $dur = (int)($rowDef['duration'] ?? 0);
            if (isset($byDur[$dur]) && is_array($byDur[$dur])) {
                $r = $byDur[$dur];
                $label = trim((string)($r['label'] ?? $rowDef['label']));
                $price = trim((string)($r['price'] ?? $rowDef['price']));
                if ($label === '') {
                    $label = $rowDef['label'];
                }
                if ($price === '') {
                    $price = $rowDef['price'];
                }
                $out[] = ['duration' => $dur, 'label' => $label, 'price' => $price];
            } else {
                $out[] = $rowDef;
            }
        }
        return $out;
    }

    function btb_render_massage_price_lis($mType, $items) {
        $mTypeEsc = htmlspecialchars($mType, ENT_QUOTES, 'UTF-8');
        foreach ($items as $row) {
            $dur = (int)($row['duration'] ?? 0);
            $label = htmlspecialchars($row['label'] ?? '', ENT_QUOTES, 'UTF-8');
            $price = htmlspecialchars($row['price'] ?? '', ENT_QUOTES, 'UTF-8');
            $line = $label . ' — ' . $price;
            echo '<li data-m-type="' . $mTypeEsc . '" data-m-duration="' . $dur . '" role="button" tabindex="0" aria-pressed="false">' . $line . "</li>\n";
        }
    }
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

$pricingRelaxing = btb_parse_massage_pricing($content['massage_pricing_relaxing'] ?? null, 'relaxing');
$pricingDeepTissue = btb_parse_massage_pricing($content['massage_pricing_deep_tissue'] ?? null, 'deep_tissue');
$pricingReiki = btb_parse_massage_pricing($content['massage_pricing_reiki'] ?? null, 'reiki');
$pricingSauna = btb_parse_massage_pricing($content['massage_pricing_sauna'] ?? null, 'sauna');

// Load mini-hotel data from room_cards_settings if it exists, otherwise from content_settings
$miniHotelData = [];
$roomCardsTableCheck = $conn->query("SHOW TABLES LIKE 'room_cards_settings'");
if ($roomCardsTableCheck && $roomCardsTableCheck->num_rows > 0) {
    $includeMiniDesc = function_exists('dbTableHasColumn') && dbTableHasColumn($conn, 'room_cards_settings', 'mini_hotel_description');
    $roomSelectFields = 'mini_hotel_title'
        . ($includeMiniDesc ? ', mini_hotel_description' : '')
        . ', mini_hotel_description_1, mini_hotel_description_2, mini_hotel_image_url';
    $roomCardsResult = $conn->query("SELECT {$roomSelectFields} FROM room_cards_settings WHERE id = 1");
    if ($roomCardsResult && $roomCardsResult->num_rows > 0) {
        $miniHotelData = $roomCardsResult->fetch_assoc();
    }
}

// Load massage images from massage_settings if it exists, otherwise from content_settings
$massageImagesData = [];
$massageTableCheck = $conn->query("SHOW TABLES LIKE 'massage_settings'");
if ($massageTableCheck && $massageTableCheck->num_rows > 0) {
    $massageResult = $conn->query("SELECT massage_hero_image_url, massage_relaxing_image_url, massage_deep_tissue_image_url, massage_reiki_image_url, massage_sauna_image_url FROM massage_settings WHERE id = 1");
    if ($massageResult && $massageResult->num_rows > 0) {
        $massageImagesData = $massageResult->fetch_assoc();
    }
}

// Extract content with fallback values
// Note: safeOutput($x, 'Wellness') does not replace empty string — only null. DB often has ''.
$heroTitleRaw = trim((string)($content['massage_hero_title'] ?? ''));
$heroTitle = safeOutput($heroTitleRaw !== '' ? $heroTitleRaw : 'Wellness', '');

// Use massage_hero_subtitle if available, otherwise fall back to massage_intro; empty → long default
$heroSubtitleText = trim((string)($content['massage_hero_subtitle'] ?? $content['massage_intro'] ?? ''));
$heroSubtitle = safeOutputWithBreaks(
    $heroSubtitleText !== '' ? $heroSubtitleText : null,
    'Massage is available as an add-on to your apartment rental or as a stand-alone booking. Whether you want to release tension, restore energy, or simply relax, our experienced therapists are always ready to help.'
);
// Get hero image from massage_settings if available, otherwise from content_settings
$heroImageUrl = '';
if (!empty($massageImagesData) && isset($massageImagesData['massage_hero_image_url']) && !empty(trim($massageImagesData['massage_hero_image_url']))) {
    $heroImageUrl = safeOutput($massageImagesData['massage_hero_image_url'], '');
} elseif (isset($content['massage_hero_image_url']) && !empty(trim($content['massage_hero_image_url']))) {
    $heroImageUrl = safeOutput($content['massage_hero_image_url'], '');
}

// Relaxing Massage
$relaxingTitle = safeOutput($content['massage_relaxing_title'] ?? '', 'Relaxing Massage');
$relaxingDescription = safeOutputWithBreaks($content['massage_relaxing_description'] ?? '', 'This gentle massage, perfect for those who want to unwind and restore their energy, uses smooth strokes and calming techniques that relieve stress, improve circulation, and promote relaxation. After the session, you will feel refreshed and relaxed.');
$relaxingImageUrl = '';
if (!empty($massageImagesData) && !empty(trim($massageImagesData['massage_relaxing_image_url'] ?? ''))) {
    $relaxingImageUrl = safeOutput($massageImagesData['massage_relaxing_image_url'], '');
} elseif (isset($content['massage_relaxing_image_url']) && !empty(trim($content['massage_relaxing_image_url']))) {
    $relaxingImageUrl = safeOutput($content['massage_relaxing_image_url'], '');
}

// Deep Tissue Massage
$deepTissueTitle = safeOutput($content['massage_deep_tissue_title'] ?? '', 'Deep Tissue Massage');
$deepTissueDescription = safeOutputWithBreaks($content['massage_deep_tissue_description'] ?? '', 'For targeted relief of muscle tension and pain, we offer deep tissue massage, designed to address chronic stiffness and discomfort in the deeper layers of muscle. It is ideal for those experiencing pain or tightness in specific areas.');
$deepTissueImageUrl = '';
if (!empty($massageImagesData) && !empty(trim($massageImagesData['massage_deep_tissue_image_url'] ?? ''))) {
    $deepTissueImageUrl = safeOutput($massageImagesData['massage_deep_tissue_image_url'], '');
} elseif (isset($content['massage_deep_tissue_image_url']) && !empty(trim($content['massage_deep_tissue_image_url']))) {
    $deepTissueImageUrl = safeOutput($content['massage_deep_tissue_image_url'], '');
}

// Reiki Energy Healing
$reikiTitle = safeOutput($content['massage_reiki_title'] ?? '', 'Reiki Energy Healing');
$reikiDescription = safeOutputWithBreaks($content['massage_reiki_description'] ?? '', 'Experience the gentle yet powerful effect of Reiki — a Japanese energy healing technique that promotes relaxation and balances the body\'s energy. This hands-on healing method helps remove energy blockages, restore inner harmony, and reduce stress levels.');
$reikiImageUrl = '';
if (!empty($massageImagesData) && !empty(trim($massageImagesData['massage_reiki_image_url'] ?? ''))) {
    $reikiImageUrl = safeOutput($massageImagesData['massage_reiki_image_url'], '');
} elseif (isset($content['massage_reiki_image_url']) && !empty(trim($content['massage_reiki_image_url']))) {
    $reikiImageUrl = safeOutput($content['massage_reiki_image_url'], '');
}

// Sauna
$saunaTitle = safeOutput($content['massage_sauna_title'] ?? '', 'Sauna');
$saunaDescription = safeOutputWithBreaks($content['massage_sauna_description'] ?? '', 'After a day spent in nature, sometimes you just want to warm up. We understand how important comfort is, so we offer our guests access to a small sauna. It is located right in the house, on the basement floor.');
$saunaImageUrl = '';
if (!empty($massageImagesData) && !empty(trim($massageImagesData['massage_sauna_image_url'] ?? ''))) {
    $saunaImageUrl = safeOutput($massageImagesData['massage_sauna_image_url'], '');
} elseif (isset($content['massage_sauna_image_url']) && !empty(trim($content['massage_sauna_image_url']))) {
    $saunaImageUrl = safeOutput($content['massage_sauna_image_url'], '');
}

// Mini-hotel section - use room_cards_settings if available, otherwise content_settings
$miniHotelTitle = safeOutput(!empty($miniHotelData) ? ($miniHotelData['mini_hotel_title'] ?? '') : ($content['mini_hotel_title'] ?? ''), 'Book a room in our mini-hotel');
$miniHotelDescription1 = safeOutputWithBreaks(!empty($miniHotelData) ? ($miniHotelData['mini_hotel_description_1'] ?? '') : ($content['mini_hotel_description_1'] ?? ''), 'After your relaxing massage session, why not extend your stay? Our cozy mini-hotel offers comfortable rooms and apartments where you can fully unwind and enjoy the peaceful atmosphere of Back to Base.');
$miniHotelDescription2 = safeOutputWithBreaks(!empty($miniHotelData) ? ($miniHotelData['mini_hotel_description_2'] ?? '') : ($content['mini_hotel_description_2'] ?? ''), 'Located just 35 km from Nelson, BC, surrounded by forest near Kootenay Lake with beautiful views of Mount Loki. Easy online booking — perfect for a peaceful vacation and retreat in nature.');
$miniHotelUseSingleBlock = false;
$miniHotelBodyHtml = '';
$miniHotelDescSingleRaw = (string) (!empty($miniHotelData) ? ($miniHotelData['mini_hotel_description'] ?? '') : ($content['mini_hotel_description'] ?? ''));
if (trim($miniHotelDescSingleRaw) !== '') {
    $miniHotelUseSingleBlock = true;
    $miniHotelBodyHtml = safeOutputWithBreaks($miniHotelDescSingleRaw, '');
}
$miniHotelImageUrl = '';
if (!empty($miniHotelData) && isset($miniHotelData['mini_hotel_image_url']) && !empty(trim($miniHotelData['mini_hotel_image_url']))) {
    $miniHotelImageUrl = safeOutput($miniHotelData['mini_hotel_image_url'], '');
} elseif (isset($content['mini_hotel_image_url']) && !empty(trim($content['mini_hotel_image_url']))) {
    $miniHotelImageUrl = safeOutput($content['mini_hotel_image_url'], '');
} else {
    $miniHotelImageUrl = 'assets/hero.jpg';
}

$miniHotelGalleryTitlePlain = trim((string) (!empty($miniHotelData) ? ($miniHotelData['mini_hotel_title'] ?? '') : ($content['mini_hotel_title'] ?? '')));
if ($miniHotelGalleryTitlePlain === '') {
    $miniHotelGalleryTitlePlain = 'Book a room in our mini-hotel';
}

if (!function_exists('wellness_stay_gallery_hook')) {
    /** Wellness page — mini-hotel image gallery CTA (plain UTF-8). */
    function wellness_stay_gallery_hook(string $plainTitle): string {
        $t = trim($plainTitle);
        if ($t === '') {
            $t = 'the guesthouse';
        }
        $lines = [
            'Picture slow mornings at {t} — open gallery',
            'Forest-quiet rooms — take a peek inside',
            '{t}: your hideaway between lake & trail',
            'Beds, beams & birdsong — browse the tour',
        ];
        $i = abs(crc32('wellness-stay|' . $t)) % count($lines);
        $line = str_replace('{t}', $t, $lines[$i]);
        if (function_exists('mb_strlen') && function_exists('mb_substr') && mb_strlen($line) > 82) {
            return mb_substr($line, 0, 79) . '…';
        }
        if (strlen($line) > 82) {
            return substr($line, 0, 79) . '…';
        }
        return $line;
    }
}
$wellnessStayGalleryHook = wellness_stay_gallery_hook($miniHotelGalleryTitlePlain);

// Booking section title and short how-to (shown under the heading)
$bookingTitle = safeOutput($content['massage_booking_title'] ?? '', 'Book a Massage or Sauna');
$bookingIntroRaw = trim((string) ($content['massage_booking_intro'] ?? ''));
$bookingIntro = $bookingIntroRaw !== '' ? safeOutput($bookingIntroRaw) : '';
// Cache buster for images
$cacheBuster = '?v=' . time();

// Build hero background image style
$heroBackgroundStyle = '';
if (!empty($heroImageUrl) && trim($heroImageUrl) !== '') {
    $heroBackgroundStyle = "background-image: url('" . htmlspecialchars($heroImageUrl, ENT_QUOTES, 'UTF-8') . $cacheBuster . "');";
} else {
    // Fallback to default images
    $heroBackgroundStyle = "background-image: url('assets/massage-hero.jpg'), url('assets/massage-hero.jpeg'), url('assets/massage-hero.JPG'), url('https://images.unsplash.com/photo-1544161515-4ab6ce6db874?q=80&w=1600&auto=format&fit=crop');";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
<?php require_once __DIR__ . '/site-head-consent.php'; ?>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="light dark">
  <title>Wellness — Back to Base</title>
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
  <style>
    /* Wellness page specific styles */
    .massage-hero {
      position: relative;
      height: 60vh;
      min-height: 400px;
      display: flex;
      align-items: center;
      justify-content: center;
      background-size: cover;
      background-position: center;
      color: white;
      text-align: center;
      margin-bottom: 0;
    }
    .massage-hero::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0,0,0,0.5);
      z-index: 1;
    }
    .massage-hero-content {
      position: relative;
      z-index: 2;
      max-width: 800px;
      padding: 0 20px;
      text-align: center;
      margin: 0 auto;
    }
    .massage-hero h1 {
      font-size: clamp(2.5rem, 5vw, 4rem);
      margin-bottom: 1rem;
      color: white !important;
      text-shadow: 0 2px 10px rgba(0,0,0,0.8);
      text-align: center;
    }
    .massage-hero p {
      font-size: clamp(1.1rem, 2vw, 1.5rem);
      opacity: 0.95;
      color: white !important;
      text-shadow: 0 1px 5px rgba(0,0,0,0.8);
      text-align: center;
      margin-top: 1rem;
    }
    @media (max-width: 768px) {
      .massage-hero {
        height: 50vh;
        min-height: 300px;
      }
    }
    /* Extra space between hero image and first massage card */
    main.massage-page-main {
      padding-top: clamp(2.25rem, 6vw, 3.5rem);
    }
  </style>
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

  <div class="massage-hero" style="<?php echo $heroBackgroundStyle; ?>">
    <div class="container">
      <div class="massage-hero-content reveal">
        <h1><?php echo $heroTitle; ?></h1>
        <p><?php echo $heroSubtitle; ?></p>
      </div>
    </div>
  </div>

  <main class="section massage-page-main">
    <div class="container">
      <section class="card card-massage" data-massage-card-type="Relaxing Massage">
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
          <div class="massage-price-hover-zone">
          <ul class="massage-list">
            <?php btb_render_massage_price_lis('Relaxing Massage', $pricingRelaxing); ?>
          </ul>
          <p class="massage-price-tap-hint" aria-hidden="true">Click a price to add to your cart.</p>
          </div>
        </div>
      </section>

      <section class="card card-massage card-massage--alt" data-massage-card-type="Deep Tissue Massage">
        <div class="card-img">
          <?php if (!empty($deepTissueImageUrl)): ?>
            <img class="floor-photo media-43" src="<?php echo htmlspecialchars($deepTissueImageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8'); ?>" alt="Deep tissue massage" />
          <?php else: ?>
            <img class="floor-photo media-43" data-src-base="assets/Deep Tissue Massage|assets/deep|assets/deep-tissue-massage|assets/Deep_Tissue_Massage" alt="Deep tissue massage" />
          <?php endif; ?>
        </div>
        <div class="card-body">
          <h2><?php echo $deepTissueTitle; ?> <span class="massage-card-badge" data-massage-badge-for="Deep Tissue Massage" hidden aria-label="Added to booking request">0</span></h2>
          <p><?php echo $deepTissueDescription; ?></p>
          <div class="massage-price-hover-zone">
          <ul class="massage-list">
            <?php btb_render_massage_price_lis('Deep Tissue Massage', $pricingDeepTissue); ?>
          </ul>
          <p class="massage-price-tap-hint" aria-hidden="true">Click a price to add to your cart.</p>
          </div>
        </div>
      </section>

      <section class="card card-massage" data-massage-card-type="Reiki Energy Healing">
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
          <div class="massage-price-hover-zone">
          <ul class="massage-list">
            <?php btb_render_massage_price_lis('Reiki Energy Healing', $pricingReiki); ?>
          </ul>
          <p class="massage-price-tap-hint" aria-hidden="true">Click a price to add to your cart.</p>
          </div>
        </div>
      </section>

      <section class="card card-massage card-massage--alt" data-massage-card-type="Sauna">
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
          <div class="massage-price-hover-zone">
          <ul class="massage-list">
            <?php btb_render_massage_price_lis('Sauna', $pricingSauna); ?>
          </ul>
          <p class="massage-price-tap-hint" aria-hidden="true">Click a price to add to your cart.</p>
          </div>
        </div>
      </section>

      <section class="card" id="book">
        <h2 id="massage-booking-title"><?php echo $bookingTitle; ?></h2>
        <p class="massage-booking-intro"><?php echo $bookingIntro; ?></p>
        <div id="massage-cart-panel" class="massage-cart-panel" hidden>
          <h3 class="massage-cart-heading">Selected services</h3>
          <ul id="massage-cart-lines" class="massage-cart-lines" aria-live="polite"></ul>
        </div>
        <form id="massage-form" novalidate>
          <input type="hidden" id="type" name="type" value="" />
          <input type="hidden" id="duration" name="duration" value="" />
          <div class="form-row massage-booking-datetime-row">
            <div class="massage-booking-col-date">
              <label for="date">Date</label>
              <input id="date" name="date" type="date" required />
              <label for="name">Your name</label>
              <input id="name" name="name" type="text" placeholder="Full name" required />
            </div>
            <div class="massage-booking-col-time">
              <label for="time">Time</label>
              <input id="time" name="time" type="time" min="09:00" max="21:00" required />
              <small class="massage-time-hint">Available: 9:00 AM - 9:00 PM (30-minute intervals)</small>
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
          <button class="btn primary" id="massage-submit-btn" type="submit">Book service</button>
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
          <a class="btn outline" href="index.html#rooms">View rooms</a>
        </div>
      </section>

      <!-- Book a Room Section -->
      <section class="card" style="margin-top: 40px;">
        <div class="room-booking-section">
          <div class="room-booking-text">
            <h2><?php echo $miniHotelTitle; ?></h2>
            <?php if (!empty($miniHotelUseSingleBlock)) : ?>
            <div class="book-stay-description"><?php echo $miniHotelBodyHtml; ?></div>
            <?php else : ?>
            <p><?php echo $miniHotelDescription1; ?></p>
            <p><?php echo $miniHotelDescription2; ?></p>
            <?php endif; ?>
            <a class="btn outline" href="index.html#rooms" style="margin-top: 20px; display: inline-block;">View rooms</a>
          </div>
          <div class="room-booking-image" style="cursor: pointer;" onclick="openRoomGallery(0)" role="button" tabindex="0" aria-label="<?php echo safeOutput($wellnessStayGalleryHook, 'Browse rooms'); ?>" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openRoomGallery(0);}">
            <img id="room-gallery-main" 
                 src="<?php echo htmlspecialchars($miniHotelImageUrl . $cacheBuster, ENT_QUOTES, 'UTF-8'); ?>" 
                 alt="<?php echo safeOutput($miniHotelGalleryTitlePlain, ''); ?>" 
                 style="width: 100%; height: 100%; object-fit: cover; border-radius: 12px; transition: transform 0.3s ease;"
                 onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1566073771259-6a8506099945?q=80&w=1200&auto=format&fit=crop';"
                 onmouseover="this.style.transform='scale(1.02)'"
                 onmouseout="this.style.transform='scale(1)'">
            <div class="gallery-overlay" aria-hidden="true"><div class="gallery-overlay-text"><?php echo safeOutput($wellnessStayGalleryHook, 'Browse rooms'); ?></div></div>
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
    .room-booking-text .book-stay-description {
      margin: 0 0 0.85em;
      line-height: 1.55;
    }
    .room-booking-image {
      position: relative;
      flex: 1;
      width: 100%;
      aspect-ratio: 4/3;
      overflow: hidden;
      border-radius: 12px;
    }
    .room-booking-image .gallery-overlay {
      position: absolute;
      inset: 0;
      background: rgba(0, 0, 0, 0.6);
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0;
      transition: opacity 0.3s ease;
      z-index: 1;
      border-radius: 12px;
    }
    .room-booking-image:hover .gallery-overlay {
      opacity: 1;
    }
    .room-booking-image .gallery-overlay-text {
      color: white;
      font-size: 1rem;
      font-weight: 600;
      text-align: center;
      padding: 12px 20px;
      background: rgba(0, 0, 0, 0.5);
      border-radius: 8px;
      backdrop-filter: blur(4px);
      max-width: 94%;
      line-height: 1.3;
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
    .massage-list li[data-m-type] {
      transition: background-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
    }
    .massage-list li[data-m-type].massage-li-in-cart {
      background: var(--brand, #3b82f6);
      color: #fff;
      box-shadow: inset 0 0 0 2px rgba(255, 255, 255, 0.25);
    }
    body:has(#book) .card-massage .massage-price-hover-zone {
      display: block;
      margin: 0.5rem -1.75rem 0;
      padding: 1.4rem 1.75rem 0.9rem;
      border-radius: 12px;
    }
    body:has(#book) .card-massage .massage-price-hover-zone .massage-list {
      margin: 0;
    }
    @media (max-width: 520px) {
      body:has(#book) .card-massage .massage-price-hover-zone {
        margin: 0.4rem -0.8rem 0;
        padding: 1.1rem 0.9rem 0.75rem;
      }
    }
    /* Same vertical rhythm as form gap (12px) between name row and Email row */
    #massage-form > .form-row {
      margin-bottom: 0;
    }
    #massage-form .massage-booking-datetime-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px 1.25rem;
      align-items: start;
    }
    #massage-form .massage-booking-col-date,
    #massage-form .massage-booking-col-time {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    #massage-form .massage-booking-col-date label,
    #massage-form .massage-booking-col-time label {
      display: block;
      margin-bottom: 0;
    }
    #massage-form .massage-booking-col-time .massage-time-hint {
      color: var(--muted, #64748b);
      font-size: 0.875rem;
      margin: 0;
      display: block;
      line-height: 1.4;
    }
    #massage-form .massage-booking-col-date input#name {
      width: 100%;
    }
    @media (max-width: 560px) {
      #massage-form .massage-booking-datetime-row {
        grid-template-columns: 1fr;
      }
    }
    #massage-form > .form-row:not(.massage-booking-datetime-row) > div {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    #massage-form > .form-row:not(.massage-booking-datetime-row) label {
      margin-bottom: 0;
    }
    #book #massage-booking-title {
      margin-bottom: 1.5rem;
    }
    .massage-booking-intro {
      color: var(--muted, #64748b);
      font-size: 0.95rem;
      line-height: 1.55;
      margin: 0.25rem 0 0.75rem;
    }
    .massage-booking-intro:empty {
      display: none;
    }
    .massage-price-tap-hint {
      display: block;
      box-sizing: border-box;
      text-align: center;
      font-size: 0.82rem;
      line-height: 1.4;
      font-weight: 400;
      font-style: normal;
      margin: 0;
      padding: 0;
      border: none;
      color: var(--muted, #64748b);
      overflow: hidden;
      pointer-events: none;
      transform-origin: top;
      transition: opacity 0.2s ease, max-height 0.22s ease, margin 0.22s ease;
    }
    @supports (selector(:has(*))) {
      @media (hover: hover) {
        .card-massage:has(.massage-price-hover-zone) .massage-price-tap-hint {
          max-height: 0;
          opacity: 0;
        }
        .card-massage:has(.massage-price-hover-zone:hover) .massage-price-tap-hint,
        .card-massage:has(.massage-price-hover-zone:focus-within) .massage-price-tap-hint {
          max-height: 3.5rem;
          opacity: 1;
          margin: 0.4rem 0 0;
        }
      }
    }
    @media (hover: none) {
      .massage-price-tap-hint {
        display: none;
      }
    }
    @supports not (selector(:has(*))) {
      .massage-price-tap-hint {
        display: none;
      }
    }
    .massage-cart-panel {
      margin-bottom: 1.25rem;
      padding: 1rem 1.1rem;
      border-radius: 10px;
      border: 1px solid rgba(255, 255, 255, 0.12);
      background: #0f172a;
      color: #e2e8f0;
    }
    .massage-cart-heading {
      margin: 0 0 0.65rem;
      font-size: 1.05rem;
      color: #f8fafc;
    }
    .massage-cart-lines {
      list-style: none;
      margin: 0 0 0.75rem;
      padding: 0.55rem 0.7rem;
      border-radius: 8px;
      /* Slightly distinct from the panel so added lines are easy to spot */
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid rgba(255, 255, 255, 0.12);
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
    }
    [data-theme="light"] .massage-cart-panel .massage-cart-lines {
      background: rgba(45, 106, 79, 0.1);
      border-color: rgba(45, 106, 79, 0.22);
      box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.04);
    }
    .massage-cart-line {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 0.5rem 1rem;
      padding: 0.45rem 0;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
      font-size: 0.95rem;
    }
    .massage-cart-line:last-child {
      border-bottom: none;
    }
    .massage-cart-line-label {
      flex: 1 1 12rem;
      color: #e2e8f0;
    }
    .massage-cart-line-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 0.35rem;
      align-items: center;
    }
    .massage-cart-line-actions .btn {
      padding: 0.25rem 0.55rem;
      font-size: 0.82rem;
    }
    .massage-cart-panel .btn.outline {
      border-color: rgba(248, 250, 252, 0.35);
      color: #f8fafc;
    }
    .massage-cart-panel .btn.outline:hover {
      background: rgba(255, 255, 255, 0.08);
      border-color: rgba(248, 250, 252, 0.55);
    }
  </style>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js"></script>
  <script src="script.js?v=32"></script>
  <script src="booking.js"></script>
  <script src="auth.js?v=26"></script>
  <script>
    // Room Gallery
    const roomGalleryAltPrefix = <?php echo json_encode($miniHotelGalleryTitlePlain, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;

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
      modalImage.alt = roomGalleryAltPrefix + ' — slide ' + (currentRoomImageIndex + 1) + ' of ' + roomGalleryImages.length;
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
        modalImage.alt = roomGalleryAltPrefix + ' — slide ' + (currentRoomImageIndex + 1) + ' of ' + roomGalleryImages.length;
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


