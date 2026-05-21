<?php
// Server-Side Rendering for Special page
require_once 'common.php';

if (function_exists('btb_public_cms_cache_headers')) {
    btb_public_cms_cache_headers(120);
}

// Load content from database
// List of all special page fields
$specialFields = [
    'special_hero_title', 'special_hero_subtitle', 'special_hero_image_url',
    'special_pools_title', 'special_pools_description_1', 'special_pools_description_2', 'special_pools_image_url',
    'special_dining_title', 'special_dining_description_1', 'special_dining_image_url',
    'special_extra_title', 'special_extra_description_1', 'special_extra_description_2', 'special_extra_image_url',
    'special_offer_title', 'special_offer_main_text', 'special_offer_rooms_cta_label',
];
if (function_exists('btb_ensure_special_block2_columns')) {
    btb_ensure_special_block2_columns($conn);
}
if (function_exists('btb_ensure_special_addon_panels_json_column')) {
    btb_ensure_special_addon_panels_json_column($conn);
}
if (function_exists('btb_special_addon_panels_json_column_name')) {
    $specialFields[] = btb_special_addon_panels_json_column_name();
}
if (function_exists('btb_special_block2_column_sql_definitions')) {
    $specialFields = array_merge($specialFields, array_keys(btb_special_block2_column_sql_definitions()));
}

$content = [];
try {
    $sql = 'SELECT ' . implode(', ', $specialFields) . ' FROM content_settings WHERE id = 1';
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $content = $result->fetch_assoc();
        error_log('special.php: Loaded special_* from content_settings (explicit columns)');
    }
} catch (Exception $e) {
    error_log('special.php: Exception loading content_settings: ' . $e->getMessage());
    $content = [];
}

if (function_exists('btb_merge_phase1_canonical_into_content_row')) {
    btb_merge_phase1_canonical_into_content_row($conn, $content);
}

$specialKeyIndex = array_flip($specialFields);
$content = array_intersect_key($content, $specialKeyIndex);

// Extract content with fallback values
$heroTitle = safeOutputWithBreaks(
    btb_field_or_default($content, 'special_hero_title', 'special_settings.special_hero_title', btb_default_text('content_settings.special_hero_title', 'Soak & Savor at Ainsworth Hot Springs')),
    ''
);
$heroSubtitle = safeOutputWithBreaks(
    btb_field_or_default($content, 'special_hero_subtitle', 'special_settings.special_hero_subtitle', btb_default_text('content_settings.special_hero_subtitle', 'Back to Base offers its guests a unique relaxation experience. See the details below.')),
    ''
);
$heroImageUrl = isset($content['special_hero_image_url']) && !empty(trim($content['special_hero_image_url'])) ? safeOutput($content['special_hero_image_url'], '') : '';

$poolsTitle = safeOutput(
    btb_field_or_default($content, 'special_pools_title', 'special_settings.special_pools_title', btb_default_text('content_settings.special_pools_title', 'Mineral-Rich Pools & Limestone Cave')),
    ''
);
[$poolsRaw1, $poolsRaw2] = btb_special_twin_description_fields_from_row(
    $content,
    'special_pools_description_1',
    'special_settings.special_pools_description_1',
    btb_default_text('content_settings.special_pools_description_1', 'The Ainsworth Hot Springs are located just a thirty-minute scenic drive from the Back to Base lodge.'),
    'special_pools_description_2',
    'special_settings.special_pools_description_2',
    btb_default_text('content_settings.special_pools_description_2', 'Relax in the mineral-rich waters of the pools and explore the unique limestone cave, where warm geothermal water flows along the grotto walls, creating a truly one-of-a-kind atmosphere for deep relaxation.')
);
[$poolsRaw1, $poolsRaw2] = btb_special_dedupe_description_pair($poolsRaw1, $poolsRaw2);
$poolsDesc1 = safeOutputWithBreaks($poolsRaw1, '');
$poolsDesc2 = safeOutputWithBreaks($poolsRaw2, '');
$poolsImageUrl = isset($content['special_pools_image_url']) && !empty(trim($content['special_pools_image_url'])) ? safeOutput($content['special_pools_image_url'], '') : 'https://images.unsplash.com/photo-1519824145371-296894a0daa9?q=80&w=1600&auto=format&fit=crop';

$diningTitle = safeOutput(
    btb_field_or_default($content, 'special_dining_title', 'special_settings.special_dining_title', btb_default_text('content_settings.special_dining_title', 'Dining & Spa Experience')),
    ''
);
// Single body field for Dining / Spa card (no second DB column or paragraph).
$diningBodyDefault = btb_default_text(
    'content_settings.special_dining_description_1',
    'After your soak, enjoy a meal at the Ktunaxa Grill restaurant located on site. The menu features fresh regional ingredients and creative preparation, making every dish a real delight. Consider visiting the Spirit Water Spa, where experienced therapists offer a full range of treatments.'
);
$diningRaw1 = btb_field_or_default($content, 'special_dining_description_1', 'special_settings.special_dining_description_1', $diningBodyDefault);
$diningDesc1 = safeOutputWithBreaks($diningRaw1, '');
$diningImageUrl = isset($content['special_dining_image_url']) && !empty(trim($content['special_dining_image_url'])) ? safeOutput($content['special_dining_image_url'], '') : 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?q=80&w=1600&auto=format&fit=crop';

$extraTitle = safeOutput(
    btb_field_or_default($content, 'special_extra_title', 'special_settings.special_extra_title', btb_default_text('content_settings.special_extra_title', 'Discover Nelson & the Kootenays')),
    ''
);
[$extraRaw1, $extraRaw2] = btb_special_twin_description_fields_from_row(
    $content,
    'special_extra_description_1',
    'special_settings.special_extra_description_1',
    btb_default_text('content_settings.special_extra_description_1', 'Beyond the hot springs, the lively town of Nelson offers galleries, cafés, and lakefront strolls — an ideal complement to your retreat.'),
    'special_extra_description_2',
    'special_settings.special_extra_description_2',
    btb_default_text('content_settings.special_extra_description_2', 'Ask us for tips on hikes, paddling on Kootenay Lake, or seasonal events during your stay.')
);
[$extraRaw1, $extraRaw2] = btb_special_dedupe_description_pair($extraRaw1, $extraRaw2);
$extraDesc1 = safeOutputWithBreaks($extraRaw1, '');
$extraDesc2 = safeOutputWithBreaks($extraRaw2, '');
$extraImageUrl = isset($content['special_extra_image_url']) && !empty(trim($content['special_extra_image_url'])) ? safeOutput($content['special_extra_image_url'], '') : 'https://images.unsplash.com/photo-1469474968028-56623f02e42e?q=80&w=1600&auto=format&fit=crop';

$offerTitle = safeOutput(
    btb_field_or_default($content, 'special_offer_title', 'special_settings.special_offer_title', btb_default_text('content_settings.special_offer_title', 'Free Hot Springs Access')),
    ''
);
// Single body block for the offer card (legacy second column merged via migrate_special_offer_merge_description.php).
$offerBodyDefault = btb_default_text(
    'content_settings.special_offer_main_text',
    "Book a minimum 5-night stay at Kelder and receive one free visit per person to Ainsworth Hot Springs pools, courtesy of us!\n\nThis exclusive offer includes access to the mineral-rich pools and the natural limestone cave. A perfect way to enhance your stay at Back to Base with a truly restorative experience."
);
$offerBody = safeOutputWithBreaks(
    btb_field_or_default($content, 'special_offer_main_text', 'special_settings.special_offer_main_text', $offerBodyDefault),
    ''
);
$offerRoomsCtaLabelRaw = trim((string) btb_field_or_default(
    $content,
    'special_offer_rooms_cta_label',
    'special_settings.special_offer_rooms_cta_label',
    btb_default_text('content_settings.special_offer_rooms_cta_label', 'Choose your room')
));
if ($offerRoomsCtaLabelRaw === '') {
    $offerRoomsCtaLabelRaw = 'Choose your room';
}

// --- Extra panels (0..10): JSON in DB, legacy special_b2_* when JSON unset ---
$addonPanels = function_exists('btb_special_addon_panels_decode_from_content')
    ? btb_special_addon_panels_decode_from_content($content)
    : [];

// Build hero background image style
$heroBackgroundStyle = '';
if (!empty($heroImageUrl) && trim($heroImageUrl) !== '') {
    $heroBackgroundStyle = "background-image: url('" . htmlspecialchars($heroImageUrl, ENT_QUOTES, 'UTF-8') . "');";
} else {
    // Fallback to default images
    $heroBackgroundStyle = "background-image: url('assets/ainsworth-hot-springs.jpg'), url('assets/ainsworth-hot-springs.jpeg'), url('assets/ainsworth-hot-springs.JPG'), url('https://images.unsplash.com/photo-1519824145371-296894a0daa9?q=80&w=1600&auto=format&fit=crop');";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
<?php require_once __DIR__ . '/site-head-consent.php'; ?>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="light dark">
  <title>Specials — Back to Base</title>
  <?php
  $__seo_title = 'Specials — Back to Base';
  $__seo_desc = 'Hot springs, dining, and extra experiences near Back to Base — specials and trip ideas in Nelson, BC.';
  ?>
  <meta name="description" content="<?php echo htmlspecialchars($__seo_desc, ENT_QUOTES, 'UTF-8'); ?>">
  <?php
  btb_seo_emit_link_and_meta('/special.php', $__seo_title, $__seo_desc, [
      'og_image' => '/assets/ainsworth-hot-springs.jpg',
  ]);
  ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <script>
    // UNIVERSAL THEME INITIALIZATION - Works on ALL pages
    (function() {
      'use strict';
      try {
        const savedTheme = localStorage.getItem('btb_theme');
        const userSetTheme = localStorage.getItem('btb_theme_user') === '1';
        let initialTheme = 'dark';
        if (userSetTheme && savedTheme && (savedTheme === 'dark' || savedTheme === 'light' || savedTheme === 'twilight')) {
          initialTheme = savedTheme;
        }
        document.documentElement.setAttribute('data-theme', initialTheme);
      } catch (error) {
        document.documentElement.setAttribute('data-theme', 'dark');
      }
    })();
  </script>
  <link rel="stylesheet" href="styles.css">
  <style>
    /* Special page specific styles */
    .special-hero {
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
    .special-hero::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0,0,0,0.5);
      z-index: 1;
    }
    .special-hero-content {
      position: relative;
      z-index: 2;
      max-width: 800px;
      padding: 0 20px;
    }
    .special-hero h1 {
      font-size: clamp(2.5rem, 5vw, 4rem);
      margin-bottom: 1rem;
      color: white !important;
      text-shadow: 0 2px 10px rgba(0,0,0,0.8);
    }
    .special-hero p {
      font-size: clamp(1.1rem, 2vw, 1.5rem);
      opacity: 0.95;
      color: white !important;
      text-shadow: 0 1px 5px rgba(0,0,0,0.8);
    }

    .special-section {
      padding: 50px 0;
    }
    .special-section.alt {
      background: var(--bg-alt);
    }

    /* One rounded panel: offer + pools / dining / extra (no full-width alt strip under offer) */
    .special-hot-springs-unified.special-section {
      padding: clamp(1.5rem, 4vw, 2.75rem) 0 clamp(2.5rem, 5vw, 3.5rem);
      background: transparent;
    }
    .special-hot-springs-unified .explore-content-band {
      background: transparent;
      padding: 0;
    }
    .special-hot-springs-unified .explore-section-panel {
      background: var(--card);
      border: 1px solid rgba(255, 255, 255, 0.06);
      border-radius: 14px;
      padding: clamp(1.25rem, 3vw, 2rem) clamp(1.1rem, 2.5vw, 2rem) clamp(1.35rem, 3.2vw, 2.35rem);
      box-sizing: border-box;
      text-align: left;
    }
    [data-theme="light"] .special-hot-springs-unified .explore-section-panel {
      border-color: rgba(0, 0, 0, 0.08);
    }
    .special-hot-springs-unified .explore-section-h2 {
      font-size: 2.5rem;
      margin: 0 0 0.5rem;
      color: var(--text, #1e293b);
      text-align: left;
    }
    .special-hot-springs-unified .section-lead {
      text-align: left;
      margin-top: 0;
      margin-bottom: clamp(0.85rem, 2vw, 1.25rem);
      color: var(--text-muted);
      font-size: 1.05rem;
      line-height: 1.8;
    }
    .special-hot-springs-unified .section-lead:last-of-type {
      margin-bottom: clamp(1rem, 2.5vw, 1.5rem);
    }
    .special-hot-springs-unified .section-lead strong {
      color: inherit;
      font-weight: 600;
    }
    .special-hot-springs-unified .special-offer-cta-wrap {
      margin-top: 0.25rem;
      text-align: left;
    }
    .special-hot-springs-unified .special-offer-cta-wrap .btn {
      display: inline-block;
      text-decoration: none;
    }
    .special-hot-springs-unified .special-unified-cards {
      margin-top: clamp(1.25rem, 3vw, 2rem);
      padding-top: clamp(1.1rem, 2.8vw, 1.75rem);
      border-top: 1px solid rgba(255, 255, 255, 0.08);
    }
    [data-theme="light"] .special-hot-springs-unified .special-unified-cards {
      border-top-color: rgba(0, 0, 0, 0.08);
    }
    .special-hot-springs-unified .hot-springs-card {
      margin-bottom: clamp(2rem, 4vw, 2.75rem);
    }
    .special-hot-springs-unified .hot-springs-card:last-child {
      margin-bottom: 0;
    }
    .special-hot-springs-unified .hot-springs-card.reverse {
      margin-bottom: clamp(2rem, 4vw, 2.75rem);
    }
    .special-hot-springs-unified .hot-springs-image {
      box-shadow: 0 8px 28px rgba(0, 0, 0, 0.12);
    }


    .info-section-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 40px;
      align-items: center;
      margin: 40px 0;
    }
    .info-text-content {
      display: flex;
      flex-direction: column;
      gap: 20px;
    }
    .info-text-content h2 {
      font-size: 2.5rem;
      margin-bottom: 10px;
    }
    .info-text-content p {
      font-size: 1.1rem;
      line-height: 1.8;
      color: var(--text-muted);
    }
    .info-image {
      width: 100%;
      border-radius: 16px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.15);
      object-fit: cover;
      aspect-ratio: 4/3;
    }

    .hot-springs-card {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 60px;
      align-items: center;
      margin-bottom: 50px;
    }
    .hot-springs-card.reverse {
      direction: rtl;
      margin-bottom: 5px;
    }
    .hot-springs-card.reverse > * {
      direction: ltr;
    }
    .hot-springs-content {
      display: flex;
      flex-direction: column;
      gap: 20px;
    }
    .hot-springs-content h3 {
      font-size: 2rem;
      margin-bottom: 15px;
      color: var(--text);
      margin-top: 0;
    }
    .hot-springs-content p {
      font-size: 1.1rem;
      line-height: 1.8;
      color: var(--text-muted);
      margin-bottom: 0;
    }
    .hot-springs-image {
      width: 100%;
      border-radius: 16px;
      object-fit: cover;
      aspect-ratio: 4/3;
      box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    }

    @media (max-width: 900px) {
      .info-section-grid {
        grid-template-columns: 1fr;
      }
      .hot-springs-card,
      .hot-springs-card.reverse {
        grid-template-columns: 1fr;
        direction: ltr;
      }
      .hot-springs-card.reverse > * {
        direction: ltr;
      }
      .special-hot-springs-unified .explore-section-h2 {
        font-size: 1.85rem;
      }
    }
  </style>
</head>
<body>
<?php require_once __DIR__ . '/gtm-body-noscript.php'; ?>
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

  <div class="special-hero" style="<?php echo $heroBackgroundStyle; ?>">
    <div class="special-hero-content">
      <h1 id="special-hero-title"><?php echo $heroTitle; ?></h1>
      <p id="special-hero-subtitle"><?php echo $heroSubtitle; ?></p>
    </div>
  </div>

  <main>
    <section class="special-section special-hot-springs-unified" aria-labelledby="special-offer-title">
      <div class="explore-content-band">
        <div class="container">
          <div class="explore-section-panel">
            <h2 id="special-offer-title" class="reveal explore-section-h2"><?php echo $offerTitle; ?></h2>
            <p class="section-lead reveal" id="special-offer-body"><?php echo $offerBody; ?></p>
            <div class="special-offer-cta-wrap">
              <a class="btn primary" id="special-offer-rooms-cta" href="index.php#rooms"><?php echo htmlspecialchars($offerRoomsCtaLabelRaw, ENT_QUOTES, 'UTF-8'); ?></a>
            </div>

            <div class="special-unified-cards">
              <div class="hot-springs-card">
                <div class="hot-springs-content">
                  <h3 id="special-pools-title"><?php echo $poolsTitle; ?></h3>
                  <p id="special-pools-description-1"><?php echo $poolsDesc1; ?></p>
                  <?php if (trim($poolsRaw2) !== '') { ?>
                  <p id="special-pools-description-2"><?php echo $poolsDesc2; ?></p>
                  <?php } ?>
                </div>
                <div>
                  <img class="hot-springs-image" src="<?php echo $poolsImageUrl; ?>" alt="Ainsworth Hot Springs pools" />
                </div>
              </div>

              <div class="hot-springs-card reverse">
                <div class="hot-springs-content">
                  <h3 id="special-dining-title"><?php echo $diningTitle; ?></h3>
                  <p id="special-dining-description-1"><?php echo $diningDesc1; ?></p>
                </div>
                <div>
                  <img class="hot-springs-image" src="<?php echo $diningImageUrl; ?>" alt="Ktunaxa Grill restaurant and spa" />
                </div>
              </div>

              <div class="hot-springs-card">
                <div class="hot-springs-content">
                  <h3 id="special-extra-title"><?php echo $extraTitle; ?></h3>
                  <p id="special-extra-description-1"><?php echo $extraDesc1; ?></p>
                  <?php if (trim($extraRaw2) !== '') { ?>
                  <p id="special-extra-description-2"><?php echo $extraDesc2; ?></p>
                  <?php } ?>
                </div>
                <div>
                  <img class="hot-springs-image" src="<?php echo $extraImageUrl; ?>" alt="" />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <?php foreach ($addonPanels as $panel) {
        if (!is_array($panel)) {
            continue;
        }
        $pidRaw = isset($panel['id']) ? (string) $panel['id'] : 'panel';
        $pidAttr = htmlspecialchars(preg_replace('/[^a-zA-Z0-9_-]/', '_', $pidRaw), ENT_QUOTES, 'UTF-8');
        $adOfferTitle = safeOutput(trim((string) ($panel['offerTitle'] ?? '')), '');
        $adOfferBodyRaw = trim((string) ($panel['offerMainText'] ?? ''));
        $adOfferBody = $adOfferBodyRaw !== '' ? safeOutputWithBreaks($adOfferBodyRaw, '') : '';
        $adRoomsCtaRaw = trim((string) ($panel['offerRoomsCtaLabel'] ?? ''));
        if ($adRoomsCtaRaw === '') {
            $adRoomsCtaRaw = 'Choose your room';
        }
        $adPoolsTitle = safeOutput(trim((string) ($panel['poolsTitle'] ?? '')), '');
        $adPoolsRaw1 = trim((string) ($panel['poolsDescription1'] ?? ''));
        $adPoolsRaw2 = trim((string) ($panel['poolsDescription2'] ?? ''));
        [$adPoolsRaw1, $adPoolsRaw2] = btb_special_dedupe_description_pair($adPoolsRaw1, $adPoolsRaw2);
        $adPoolsDesc1 = safeOutputWithBreaks($adPoolsRaw1, '');
        $adPoolsDesc2 = safeOutputWithBreaks($adPoolsRaw2, '');
        $adPoolsImageUrl = isset($panel['poolsImageUrl']) && trim((string) $panel['poolsImageUrl']) !== ''
            ? safeOutput($panel['poolsImageUrl'], '') : '';
        $adDiningTitle = safeOutput(trim((string) ($panel['diningTitle'] ?? '')), '');
        $adDiningRaw1 = trim((string) ($panel['diningDescription1'] ?? ''));
        $adDiningDesc1 = $adDiningRaw1 !== '' ? safeOutputWithBreaks($adDiningRaw1, '') : '';
        $adDiningImageUrl = isset($panel['diningImageUrl']) && trim((string) $panel['diningImageUrl']) !== ''
            ? safeOutput($panel['diningImageUrl'], '') : '';
        $adExtraTitle = safeOutput(trim((string) ($panel['extraTitle'] ?? '')), '');
        $adExtraRaw1 = trim((string) ($panel['extraDescription1'] ?? ''));
        $adExtraRaw2 = trim((string) ($panel['extraDescription2'] ?? ''));
        [$adExtraRaw1, $adExtraRaw2] = btb_special_dedupe_description_pair($adExtraRaw1, $adExtraRaw2);
        $adExtraDesc1 = safeOutputWithBreaks($adExtraRaw1, '');
        $adExtraDesc2 = safeOutputWithBreaks($adExtraRaw2, '');
        $adExtraImageUrl = isset($panel['extraImageUrl']) && trim((string) $panel['extraImageUrl']) !== ''
            ? safeOutput($panel['extraImageUrl'], '') : '';
        $adOfferHeadingId = 'special-addon-' . $pidAttr . '-offer-title';
        ?>
    <section class="special-section special-hot-springs-unified" aria-labelledby="<?php echo $adOfferHeadingId; ?>">
      <div class="explore-content-band">
        <div class="container">
          <div class="explore-section-panel">
            <h2 id="<?php echo $adOfferHeadingId; ?>" class="reveal explore-section-h2"><?php echo $adOfferTitle; ?></h2>
            <?php if ($adOfferBody !== '') { ?>
            <p class="section-lead reveal" id="special-addon-<?php echo $pidAttr; ?>-offer-body"><?php echo $adOfferBody; ?></p>
            <?php } ?>
            <div class="special-offer-cta-wrap">
              <a class="btn primary" id="special-addon-<?php echo $pidAttr; ?>-offer-rooms-cta" href="index.php#rooms"><?php echo htmlspecialchars($adRoomsCtaRaw, ENT_QUOTES, 'UTF-8'); ?></a>
            </div>

            <div class="special-unified-cards">
              <div class="hot-springs-card">
                <div class="hot-springs-content">
                  <h3 id="special-addon-<?php echo $pidAttr; ?>-pools-title"><?php echo $adPoolsTitle; ?></h3>
                  <?php if (trim($adPoolsRaw1) !== '') { ?>
                  <p id="special-addon-<?php echo $pidAttr; ?>-pools-description-1"><?php echo $adPoolsDesc1; ?></p>
                  <?php } ?>
                  <?php if (trim($adPoolsRaw2) !== '') { ?>
                  <p id="special-addon-<?php echo $pidAttr; ?>-pools-description-2"><?php echo $adPoolsDesc2; ?></p>
                  <?php } ?>
                </div>
                <div>
                  <?php if ($adPoolsImageUrl !== '') { ?>
                  <img class="hot-springs-image" src="<?php echo $adPoolsImageUrl; ?>" alt="" />
                  <?php } ?>
                </div>
              </div>

              <div class="hot-springs-card reverse">
                <div class="hot-springs-content">
                  <h3 id="special-addon-<?php echo $pidAttr; ?>-dining-title"><?php echo $adDiningTitle; ?></h3>
                  <?php if ($adDiningDesc1 !== '') { ?>
                  <p id="special-addon-<?php echo $pidAttr; ?>-dining-description-1"><?php echo $adDiningDesc1; ?></p>
                  <?php } ?>
                </div>
                <div>
                  <?php if ($adDiningImageUrl !== '') { ?>
                  <img class="hot-springs-image" src="<?php echo $adDiningImageUrl; ?>" alt="" />
                  <?php } ?>
                </div>
              </div>

              <div class="hot-springs-card">
                <div class="hot-springs-content">
                  <h3 id="special-addon-<?php echo $pidAttr; ?>-extra-title"><?php echo $adExtraTitle; ?></h3>
                  <?php if (trim($adExtraRaw1) !== '') { ?>
                  <p id="special-addon-<?php echo $pidAttr; ?>-extra-description-1"><?php echo $adExtraDesc1; ?></p>
                  <?php } ?>
                  <?php if (trim($adExtraRaw2) !== '') { ?>
                  <p id="special-addon-<?php echo $pidAttr; ?>-extra-description-2"><?php echo $adExtraDesc2; ?></p>
                  <?php } ?>
                </div>
                <div>
                  <?php if ($adExtraImageUrl !== '') { ?>
                  <img class="hot-springs-image" src="<?php echo $adExtraImageUrl; ?>" alt="" />
                  <?php } ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
    <?php } ?>

  </main>

<?php require __DIR__ . '/site_footer.php'; ?>

  <script src="script.js?v=26"></script>
  <script src="auth.js?v=26"></script>
</body>
</html>

