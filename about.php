<?php
// Server-Side Rendering for About us page
require_once 'common.php';

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

// Helper function for safe HTML output (allows certain tags)
function safeHtmlOutput($value, $fallback = '') {
    $html = $value ?? $fallback;
    // Allow only safe HTML tags
    $allowedTags = '<strong><em><b><i><br><p><a>';
    $out = strip_tags($html, $allowedTags);
    // Text from CMS textareas uses literal newlines; HTML ignores them unless converted
    $out = preg_replace('/\r\n|\r|\n/', '<br>', $out);
    return $out;
}

// Extract content with fallback values
$heroTitle = safeOutput(
    btb_field_or_default($content, 'about_hero_title', 'about_settings.about_hero_title', btb_default_text('content_settings.about_hero_title', 'About Back to Base')),
    ''
);
$heroSubtitle = safeOutputWithBreaks(
    btb_field_or_default($content, 'about_hero_subtitle', 'about_settings.about_hero_subtitle', btb_default_text('content_settings.about_hero_subtitle', 'A personal retreat in the heart of British Columbia')),
    ''
);
$heroImageUrl = isset($content['about_hero_image_url']) && !empty(trim($content['about_hero_image_url'])) ? safeOutput($content['about_hero_image_url'], '') : '';

$ideaTitle = safeOutput(
    btb_field_or_default($content, 'about_idea_title', 'about_settings.about_idea_title', btb_default_text('content_settings.about_idea_title', 'Idea and Origins')),
    ''
);

// Visible teaser vs collapsed continuation:
// - By default: intro is always visible; paragraphs 1–3 merge into one collapsed body (safeOutputWithBreaks).
// - Optional: put [[READ_MORE]] on its own line inside intro — text BEFORE stays visible, text AFTER joins the collapsed section (still merged with paragraphs).
$ideaIntroFallback = 'Hi! My name is <strong>Rob Vuik</strong>. I founded Back to Base after twenty years of working as a co-owner of a large hotel in Nelson. When I retired, I realized something simple: many people — just like me — need a quiet place where they can rest, recover, and feel better.';
$introRaw = $content['about_idea_intro'] ?? '';
$continuationPrefixFromIntro = '';
if (preg_match('/\[\[READ_MORE\]\]/u', $introRaw)) {
    $chunks = preg_split('/\s*\[\[READ_MORE\]\]\s*/u', $introRaw, 2);
    $ideaIntro = safeHtmlOutput(trim($chunks[0] ?? ''), $ideaIntroFallback);
    $continuationPrefixFromIntro = isset($chunks[1]) ? trim($chunks[1]) : '';
} else {
    $ideaIntro = safeHtmlOutput($introRaw, $ideaIntroFallback);
}

$p1 = trim((string)($content['about_idea_paragraph_1'] ?? ''));
$p2 = trim((string)($content['about_idea_paragraph_2'] ?? ''));
$p3 = trim((string)($content['about_idea_paragraph_3'] ?? ''));
$continuationPieces = array_values(array_filter(
    [$continuationPrefixFromIntro, $p1, $p2, $p3],
    static function ($s) {
        return $s !== '';
    }
));
$continuationPlain = implode("\n\n", $continuationPieces);

$ideaContinuationHtml = '';
$hasIdeaContinuation = ($continuationPlain !== '');
if ($hasIdeaContinuation) {
    $ideaContinuationHtml = safeOutputWithBreaks($continuationPlain, '');
}

$ideaSignature = safeOutputWithBreaks(
    btb_field_or_default($content, 'about_idea_signature', 'about_settings.about_idea_signature', btb_default_text('content_settings.about_idea_signature', 'I look forward to welcoming you!')),
    ''
);
$founderImageUrl = isset($content['about_founder_image_url']) && !empty(trim($content['about_founder_image_url'])) ? safeOutput($content['about_founder_image_url'], '') : 'assets/Rob Vuik.jpg';

$locTitle = safeOutput(
    btb_field_or_default($content, 'about_location_title', 'about_settings.about_location_title', btb_default_text('content_settings.about_location_title', 'How to Find Us')),
    ''
);
$locP1 = safeOutputWithBreaks(
    btb_field_or_default($content, 'about_location_paragraph_1', 'about_settings.about_location_paragraph_1', btb_default_text('content_settings.about_location_paragraph_1', 'Back to Base is located in the village of Procter, 35 km from Nelson, B.C.')),
    ''
);
$locP2 = safeOutputWithBreaks(
    btb_field_or_default($content, 'about_location_paragraph_2', 'about_settings.about_location_paragraph_2', btb_default_text('content_settings.about_location_paragraph_2', 'You\'ll need to take the 24/7 Harrop–Procter ferry,')),
    ''
);
$locP3 = safeOutputWithBreaks(
    btb_field_or_default($content, 'about_location_paragraph_3', 'about_settings.about_location_paragraph_3', btb_default_text('content_settings.about_location_paragraph_3', 'then continue straight for another 6 minutes until you see the Back to Base sign on the right side of the road.')),
    ''
);
$locP4 = safeOutputWithBreaks(
    btb_field_or_default($content, 'about_location_paragraph_4', 'about_settings.about_location_paragraph_4', btb_default_text('content_settings.about_location_paragraph_4', 'From there, it\'s just a 3-minute drive up the mountain road — and you\'re here!')),
    ''
);
$locCoords = safeOutput(
    btb_field_or_default($content, 'about_location_coordinates', 'about_settings.about_location_coordinates', btb_default_text('content_settings.about_location_coordinates', 'Coordinates: 49.6125, -116.9579')),
    ''
);
$locDeerWarning = safeHtmlOutput(
    btb_field_or_default($content, 'about_location_deer_warning', 'about_settings.about_location_deer_warning', btb_default_text('content_settings.about_location_deer_warning', '🦌 <strong>Be careful</strong> — we have a lot of deer in the area!')),
    ''
);

$contactFormTitle = safeOutput(
    btb_field_or_default($content, 'about_contact_form_title', 'about_settings.about_contact_form_title', btb_default_text('content_settings.about_contact_form_title', 'Contact us')),
    ''
);

$rawContactFormDescription = trim((string) ($content['about_contact_form_description'] ?? ''));
if ($rawContactFormDescription === '') {
    $legacyL = trim((string) ($content['about_contact_form_lead'] ?? ''));
    $legacyE = trim((string) ($content['about_contact_form_emphasis'] ?? ''));
    if ($legacyL !== '' || $legacyE !== '') {
        $rawContactFormDescription = $legacyL . ($legacyL !== '' && $legacyE !== '' ? "\n\n" : '') . $legacyE;
    }
}
$contactFormDescriptionDefault = btb_default_text(
    'about_settings.about_contact_form_description',
    btb_default_text(
        'content_settings.about_contact_form_description',
        "At Back to Base, you can find exactly the kind of rest you need.\n\nWe'll be happy to help you plan your stay and answer any questions!"
    )
);
$contactFormDescription = safeOutputWithBreaks(
    $rawContactFormDescription !== '' ? $rawContactFormDescription : $contactFormDescriptionDefault,
    ''
);

// Build hero background image style
$heroBackgroundStyle = '';
if (!empty($heroImageUrl) && trim($heroImageUrl) !== '') {
    $heroBackgroundStyle = "background-image: url('" . htmlspecialchars($heroImageUrl, ENT_QUOTES, 'UTF-8') . "');";
} else {
    // Fallback to default images
    $heroBackgroundStyle = "background-image: url('assets/about_procter.jpg'), url('assets/about_procter.jpeg'), url('assets/about_procter.JPG'), url('assets/about_procter.png');";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
<?php require_once __DIR__ . '/site-head-consent.php'; ?>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="light dark">
  <title>About us — Back to Base</title>
  <?php
  $__seo_title = 'About us — Back to Base';
  $__seo_desc = 'Meet the host and story of Back to Base — a personal retreat guesthouse near Nelson, British Columbia, with rooms, wellness, and nature.';
  ?>
  <meta name="description" content="<?php echo htmlspecialchars($__seo_desc, ENT_QUOTES, 'UTF-8'); ?>">
  <?php
  btb_seo_emit_link_and_meta('/about.php', $__seo_title, $__seo_desc, [
      'og_image' => '/assets/about_procter.jpg',
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
    /* About page specific styles */
    .about-hero {
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
    .about-hero::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0,0,0,0.5);
      z-index: 1;
    }
    .about-hero-content {
      position: relative;
      z-index: 2;
      max-width: 800px;
      padding: 0 20px;
    }
    .about-hero h1 {
      font-size: clamp(2.5rem, 5vw, 4rem);
      margin-bottom: 1rem;
      color: white !important;
      text-shadow: 0 2px 10px rgba(0,0,0,0.8);
    }
    .about-hero p {
      font-size: clamp(1.1rem, 2vw, 1.5rem);
      opacity: 0.95;
      color: white !important;
      text-shadow: 0 1px 5px rgba(0,0,0,0.8);
    }

    .about-section {
      padding: 50px 0;
    }
    .about-section.alt {
      background: var(--bg-alt);
    }

    .founder-card {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 60px;
      align-items: center;
      margin-bottom: 0;
    }
    .founder-photo {
      width: 100%;
      max-width: 350px;
      border-radius: 16px;
      object-fit: cover;
      aspect-ratio: 3/4;
      box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    }
    .founder-content {
      display: flex;
      flex-direction: column;
      gap: 20px;
    }
    .founder-content h2,
    .about-section h2 {
      font-size: 2.5rem;
      margin-bottom: 10px;
    }
    .founder-content p {
      font-size: 1.1rem;
      line-height: 1.8;
      color: var(--text-muted);
    }
    .founder-expandable-inner {
      font-size: 1.1rem;
      line-height: 1.8;
      color: var(--text-muted);
    }
    .founder-content-expandable {
      position: relative;
      max-height: 3em;
      overflow: hidden;
      transition: max-height 0.4s ease-out;
    }
    .founder-content-expandable::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      height: 2em;
      background: var(--bg);
      pointer-events: none;
      transition: opacity 0.3s ease;
    }
    .founder-content-expandable.expanded {
      max-height: 2000px;
      transition: max-height 0.6s ease-in;
    }
    .founder-content-expandable.expanded::after {
      opacity: 0;
    }
    [data-theme="light"] .founder-content-expandable::after {
      background: var(--bg);
    }
    .read-more-btn {
      margin-top: 20px;
      padding: 10px 16px;
      background: var(--brand);
      color: white;
      border: 1px solid var(--brand);
      border-radius: 10px;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: 0.2s ease;
      display: inline-block;
      text-decoration: none;
    }
    .read-more-btn:hover {
      background: var(--brand-600);
      transform: translateY(-1px);
      border-color: var(--brand-600);
    }
    .read-more-btn:active {
      transform: translateY(0);
    }
    .founder-signature {
      margin-top: 20px;
      font-style: italic;
      color: var(--brand);
    }

    .location-section-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 40px;
      align-items: stretch;
      margin-top: 30px;
    }
    .location-text-content {
      display: flex;
      flex-direction: column;
      gap: 20px;
    }
    .location-map-wrapper {
      display: flex;
      flex-direction: column;
      gap: 20px;
    }
    .location-map-button-wrapper {
      display: flex;
      align-items: flex-start;
      margin-top: auto;
    }
    .location-map-container {
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 10px 40px rgba(0,0,0,0.15);
      flex: 1;
      min-height: 0;
      background: var(--card);
    }
    #location-map {
      width: 100%;
      height: 100%;
      border: none;
      border-radius: 16px;
    }
    #map-modal-map {
      width: 100%;
      height: 100%;
      border: none;
    }
    .open-map-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 12px 20px;
      background: var(--brand);
      color: white;
      border: 1px solid var(--brand);
      border-radius: 10px;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: 0.2s ease;
    }
    .open-map-btn:hover {
      background: var(--brand-600);
      transform: translateY(-1px);
      border-color: var(--brand-600);
    }
    .open-map-btn:active {
      transform: translateY(0);
    }
    .open-map-btn svg {
      flex-shrink: 0;
    }
    
    /* Map Modal Styles */
    .map-modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.7);
      z-index: 10000;
      align-items: center;
      justify-content: center;
      padding: 20px;
      backdrop-filter: blur(4px);
    }
    .map-modal.active {
      display: flex;
    }
    .map-modal-content {
      position: relative;
      width: calc(100% - 40px);
      height: calc(100% - 40px);
      max-width: 100%;
      max-height: 100%;
      background: var(--bg);
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 20px 60px rgba(0,0,0,0.5);
      border: 2px solid var(--border);
      display: flex;
      flex-direction: column;
    }
    .map-modal-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 20px 24px;
      border-bottom: 1px solid var(--border);
      background: var(--card);
      flex-shrink: 0;
    }
    .map-modal-header h3 {
      margin: 0;
      font-size: 1.5rem;
      color: var(--text);
    }
    .map-modal-actions {
      display: flex;
      gap: 12px;
      align-items: center;
    }
    .map-modal-close {
      background: rgba(239, 68, 68, 0.9);
      border: 2px solid rgba(239, 68, 68, 1);
      color: white;
      font-size: 1.8rem;
      font-weight: bold;
      cursor: pointer;
      padding: 8px 12px;
      border-radius: 8px;
      transition: 0.2s ease;
      line-height: 1;
      min-width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
    }
    .map-modal-close:hover {
      background: rgba(239, 68, 68, 1);
      transform: scale(1.1);
      box-shadow: 0 6px 16px rgba(239, 68, 68, 0.6);
    }
    .map-modal-close:active {
      transform: scale(0.95);
    }
    .map-modal-body {
      flex: 1;
      position: relative;
      min-height: 0;
      height: 100%;
      overflow: hidden;
    }
    .map-modal-body iframe,
    .map-modal-body #map-modal-map {
      width: 100%;
      height: 100%;
      border: none;
      display: block;
    }
    .open-google-maps-btn {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 10px 18px;
      background: var(--bg-alt);
      color: var(--text);
      border: 1px solid var(--border);
      border-radius: 8px;
      font-size: 0.95rem;
      font-weight: 600;
      cursor: pointer;
      transition: 0.2s ease;
      text-decoration: none;
    }
    .open-google-maps-btn:hover {
      background: var(--brand);
      color: white;
      border-color: var(--brand);
    }
    .open-google-maps-btn svg {
      width: 18px;
      height: 18px;
    }
    
    @media (max-width: 768px) {
      .map-modal {
        padding: 10px;
      }
      .map-modal-content {
        width: calc(100% - 20px);
        height: calc(100% - 20px);
        border-radius: 8px;
      }
      .map-modal-body {
        height: 100%;
        min-height: 0;
      }
      .map-modal-header {
        padding: 16px 20px;
      }
      .map-modal-header h3 {
        font-size: 1.25rem;
      }
      .map-modal-close {
        min-width: 36px;
        height: 36px;
        font-size: 1.5rem;
        padding: 6px 10px;
      }
      .location-map-container {
        position: relative;
        min-height: 300px;
      }
      .location-map-wrapper {
        gap: 20px;
      }
    }

    .directions-box {
      background: var(--card);
      padding: 30px;
      border-radius: 16px;
      border: 1px solid var(--border);
    }
    .directions-box p {
      font-size: 1.1rem;
      line-height: 1.8;
      color: var(--text-muted);
      margin-bottom: 20px;
    }
    .directions-box p:last-of-type {
      margin-bottom: 0;
    }
    .coordinates {
      background: var(--bg-alt);
      padding: 15px 20px;
      border-radius: 8px;
      margin-top: 20px;
      font-family: 'Courier New', monospace;
      font-size: 0.95rem;
      display: inline-block;
    }
    .deer-warning {
      background: rgba(245, 158, 11, 0.1);
      border-left: 4px solid #f59e0b;
      padding: 15px 20px;
      border-radius: 8px;
      margin-top: 20px;
      font-size: 1rem;
      line-height: 1.6;
      color: var(--text);
    }

    .attractions-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 30px;
      margin-top: 30px;
      margin-bottom: 0;
    }
    .attraction-card {
      background: var(--card);
      padding: 0;
      border-radius: 16px;
      border: 1px solid var(--border);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }
    .attraction-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    }
    .attraction-card-image-wrapper {
      position: relative;
      width: 100%;
      height: 250px;
      overflow: hidden;
      cursor: pointer;
    }
    .attraction-card-image {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.3s ease;
    }
    .attraction-card[data-gallery="procter"] .attraction-card-image {
      object-position: center 90%;
    }
    .attraction-card-image-wrapper:hover .attraction-card-image {
      transform: scale(1.05);
    }
    
    /* Gallery overlay - appears on hover */
    .gallery-overlay {
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
    }
    .attraction-card-image-wrapper:hover .gallery-overlay {
      opacity: 1;
    }
    .gallery-overlay-text {
      color: white;
      font-size: 1.1rem;
      font-weight: 600;
      text-align: center;
      padding: 12px 20px;
      background: rgba(0, 0, 0, 0.5);
      border-radius: 8px;
      backdrop-filter: blur(4px);
    }
    .attraction-card-content {
      padding: 30px;
    }
    .attraction-card h4 {
      color: var(--brand);
      margin-bottom: 10px;
      font-size: 1.3rem;
    }
    .attraction-card .distance {
      color: var(--text-muted);
      font-size: 0.9rem;
      margin-bottom: 15px;
    }
    .attraction-card p {
      line-height: 1.7;
      color: var(--text-muted);
    }

    /* Gallery Modal */
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

    /* Contact Form Styles */
    .contact-section-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0;
      align-items: start;
      background: var(--card);
      border-radius: 16px;
      border: 1px solid var(--border);
      overflow: hidden;
    }
    .contact-text {
      padding: 30px;
    }
    .contact-form-wrapper {
      padding: 30px;
      border-left: 1px solid var(--border);
    }
    .contact-form {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .form-group {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .form-group label {
      font-weight: 600;
      font-size: 14px;
      color: var(--text);
    }
    .required {
      color: #ff4d4d;
    }
    .form-input {
      padding: 12px;
      border: 1px solid var(--border);
      border-radius: 8px;
      background: var(--bg);
      color: var(--text);
      font-size: 14px;
      font-family: inherit;
      transition: border-color 0.3s ease;
    }
    .form-input:focus {
      outline: none;
      border-color: var(--brand);
      box-shadow: 0 0 0 3px rgba(44, 123, 229, 0.1);
    }
    .form-input:invalid:not(:placeholder-shown) {
      border-color: #ff4d4d;
    }
    .contact-submit-btn {
      width: 100%;
      margin-top: 10px;
    }
    .form-message {
      margin-top: 15px;
      padding: 12px;
      border-radius: 8px;
      font-size: 14px;
      display: none;
      opacity: 0;
      transition: opacity 0.3s ease;
    }
    .form-message.success,
    .form-message.error {
      display: block;
      opacity: 1;
    }
    .form-message.success {
      background: rgba(34, 197, 94, 0.1);
      color: #22c55e;
      border: 1px solid rgba(34, 197, 94, 0.3);
    }
    .form-message.error {
      background: rgba(239, 68, 68, 0.1);
      color: #ef4444;
      border: 1px solid rgba(239, 68, 68, 0.3);
    }
    @media (max-width: 768px) {
      .contact-section-grid {
        grid-template-columns: 1fr;
      }
      .contact-form-wrapper {
        border-left: none;
        border-top: 1px solid var(--border);
      }
    }

    .parks-list {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 15px;
      margin-top: 30px;
      margin-bottom: 0;
    }
    .park-item {
      background: var(--card);
      padding: 20px;
      border-radius: 12px;
      border: 1px solid var(--border);
      display: flex;
      align-items: center;
      gap: 15px;
      color: #0f172a;
    }
    .park-item::before {
      content: "🌲";
      font-size: 1.5rem;
    }


    @media (max-width: 768px) {
      .founder-card {
        grid-template-columns: 1fr;
        gap: 30px;
      }
      .founder-photo {
        max-width: 300px;
        margin: 0 auto;
      }
      .location-section-grid {
        grid-template-columns: 1fr;
        gap: 30px;
      }
      .location-map-container {
        height: 400px;
        position: relative;
      }
      .about-hero {
        height: 50vh;
        min-height: 300px;
      }
      .attractions-grid {
        grid-template-columns: 1fr;
      }
      .parks-list {
        grid-template-columns: 1fr;
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

  <!-- Hero Section -->
  <section class="about-hero" style="<?php echo $heroBackgroundStyle; ?>">
    <div class="about-hero-content reveal">
      <h1><?php echo $heroTitle; ?></h1>
      <p><?php echo $heroSubtitle; ?></p>
    </div>
  </section>

  <main>
    <!-- Idea and Origins Section -->
    <section class="about-section">
      <div class="container">
        <div class="founder-card reveal">
          <div class="founder-photo-wrap">
            <img src="<?php echo $founderImageUrl; ?>" 
                 alt="Rob Vuik, founder of Back to Base" 
                 class="founder-photo reveal"
                 onerror="this.onerror=null; this.src='assets/Rob Vuik.jpeg'; this.onerror=function(){this.onerror=null; this.src='assets/Rob Vuik.png'; this.onerror=function(){this.onerror=null; this.src='assets/Rob%20Vuik.jpg'; this.onerror=function(){this.onerror=null; this.src='assets/rob-vuik.jpg'; this.onerror=function(){this.src='https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?q=80&w=800&auto=format&fit=crop';};};};};">
          </div>
          <div class="founder-content reveal">
            <h2><?php echo $ideaTitle; ?></h2>
            <p><?php echo $ideaIntro; ?></p>
            <?php if ($hasIdeaContinuation): ?>
            <div class="founder-content-expandable" id="founder-expandable">
              <div class="founder-expandable-inner"><?php echo $ideaContinuationHtml; ?></div>
              <p class="founder-signature"><?php echo $ideaSignature; ?></p>
            </div>
            <button type="button" class="read-more-btn" id="read-more-btn" onclick="toggleFounderText()">Read more</button>
            <?php else: ?>
            <p class="founder-signature"><?php echo $ideaSignature; ?></p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>

    <!-- How to Find Us Section -->
    <section class="about-section alt">
      <div class="container">
        <h2 class="reveal"><?php echo $locTitle; ?></h2>
        
        <div class="location-section-grid reveal">
          <div class="location-text-content">
            <div class="directions-box">
              <p><?php echo $locP1; ?></p>
              <p><?php echo $locP2; ?></p>
              <p><?php echo $locP3; ?></p>
              <p><?php echo $locP4; ?></p>
              <div class="coordinates">
                <strong><?php echo $locCoords; ?></strong>
              </div>
            </div>
            <div class="deer-warning">
              <?php echo $locDeerWarning; ?>
            </div>
          </div>
          
          <div class="location-map-wrapper">
            <div class="location-map-container">
              <iframe 
                id="location-map"
                src="https://www.google.com/maps?q=Back+to+Base+BnB,+Procter,+BC,+Canada&hl=en&z=12&output=embed"
                width="100%" 
                height="100%" 
                style="border:0;" 
                allowfullscreen="" 
                loading="lazy" 
                referrerpolicy="no-referrer-when-downgrade">
              </iframe>
            </div>
            <div class="location-map-button-wrapper">
              <button class="open-map-btn" onclick="openMapModal()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                  <circle cx="12" cy="10" r="3"></circle>
                </svg>
                Open the map in full size
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Contact us Section -->
    <section class="about-section">
      <div class="container">
        <h2 class="reveal" id="about-contact-section-heading"><?php echo $contactFormTitle; ?></h2>
          <div class="contact-section-grid" style="margin-top: 30px;">
            <div class="contact-text">
              <div id="about-contact-description" class="about-contact-form-copy" style="font-size: 1.2rem; line-height: 1.8;">
                <?php echo $contactFormDescription; ?>
              </div>
            </div>
            <div class="contact-form-wrapper">
              <form id="contact-form" class="contact-form">
                <div class="form-group">
                  <label for="contact-message">Message</label>
                  <textarea id="contact-message" name="message" rows="4" 
                            placeholder="Your message..." 
                            class="form-input" required></textarea>
                  <span class="field-error" id="message-error"></span>
                </div>
                <div class="form-group">
                  <label for="contact-email">Email</label>
                  <input type="email" id="contact-email" name="email" required 
                         placeholder="your.email@example.com" 
                         class="form-input">
                  <span class="field-error" id="email-error"></span>
                </div>
                <div class="form-group">
                  <label for="contact-phone">Phone</label>
                  <input type="tel" id="contact-phone" name="phone" required 
                         placeholder="+1 (555) 123-4567" 
                         class="form-input">
                  <span class="field-error" id="phone-error"></span>
                </div>
                <div class="form-group">
                  <label for="contact-captcha"><span id="captcha-question">What is 5 + 3?</span></label>
                  <input type="number" id="contact-captcha" name="captcha" required 
                         placeholder="Enter the answer" 
                         class="form-input">
                  <input type="hidden" id="captcha-answer" name="captcha_answer" value="8">
                  <span class="field-error" id="captcha-error"></span>
                </div>
                <button type="submit" class="btn primary contact-submit-btn">
                  Send Message
                </button>
                <div id="contact-form-message" class="form-message"></div>
              </form>
            </div>
          </div>
      </div>
    </section>
  </main>

<?php require __DIR__ . '/site_footer.php'; ?>

  <script src="utils.js?v=26"></script>
  <script src="script.js?v=26"></script>
  <script src="auth.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
    // Generate random captcha
    function generateCaptcha() {
      const num1 = Math.floor(Math.random() * 10) + 1;
      const num2 = Math.floor(Math.random() * 10) + 1;
      const answer = num1 + num2;
      
      document.getElementById('captcha-question').textContent = `What is ${num1} + ${num2}?`;
      document.getElementById('captcha-answer').value = answer;
      document.getElementById('contact-captcha').value = '';
    }

    // Initialize captcha on page load
    generateCaptcha();

    // Contact form handler - try multiple approaches
    function initContactForm() {
      const contactForm = document.getElementById('contact-form');
      if (!contactForm) {
        console.error('Contact form not found!');
        return false;
      }
      
      console.log('Contact form found, attaching submit handler');
      
      // Mark form as having handler attached
      contactForm.setAttribute('data-handler-attached', 'true');
      
      // Also attach handler to submit button directly as backup
      const submitBtn = contactForm.querySelector('button[type="submit"]');
      if (submitBtn) {
        console.log('Submit button found, attaching click handler');
        submitBtn.addEventListener('click', async function(e) {
          e.preventDefault();
          e.stopPropagation();
          console.log('Submit button clicked directly!');
          
          // Call the form handler directly instead of dispatching event
          const form = contactForm;
          const formData = new FormData(form);
          const messageDiv = document.getElementById('contact-form-message');
          const btn = form.querySelector('button[type="submit"]');
          
          console.log('Form data:', {
            email: formData.get('email'),
            phone: formData.get('phone'),
            message: formData.get('message'),
            captcha: formData.get('captcha')
          });
          
          // Check if messageDiv exists
          if (!messageDiv) {
            console.error('Message div not found!');
            alert('Form error: message container not found');
            return;
          }
        
          // Clear previous errors
          document.querySelectorAll('.field-error').forEach(el => el.textContent = '');
          messageDiv.className = 'form-message';
          messageDiv.textContent = '';
          messageDiv.style.display = 'none';
          
          // Validate message (first)
          const message = formData.get('message');
          if (!message || message.trim().length === 0) {
            document.getElementById('message-error').textContent = 'Please enter your message.';
            return;
          }
          
          // Validate email (second)
          const email = formData.get('email');
          if (!email || !email.includes('@')) {
            document.getElementById('email-error').textContent = 'Please enter a valid email address.';
            return;
          }
          
          // Validate phone (third)
          const phone = formData.get('phone');
          if (!phone || phone.trim().length < 5) {
            document.getElementById('phone-error').textContent = 'Please enter a valid phone number.';
            return;
          }
          
          // Validate captcha (last)
          const captchaAnswer = parseInt(document.getElementById('captcha-answer').value);
          const captchaInput = parseInt(formData.get('captcha'));
          
          if (captchaInput !== captchaAnswer) {
            document.getElementById('captcha-error').textContent = 'Incorrect answer. Please try again.';
            generateCaptcha();
            return;
          }
          
          // Disable submit button
          btn.disabled = true;
          btn.textContent = 'Sending...';
          
          try {
            const response = await fetch('contact_form.php', {
              method: 'POST',
              body: formData
            });
            
            // Check if response is OK
            if (!response.ok) {
              throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Get response text first to check if it's valid JSON
            const responseText = await response.text();
            let result;
            
            try {
              result = JSON.parse(responseText);
            } catch (parseError) {
              console.error('Failed to parse JSON response:', parseError);
              console.error('Response text:', responseText);
              messageDiv.className = 'form-message error';
              messageDiv.textContent = 'Server error. Please try again later.';
              messageDiv.style.display = 'block';
              btn.disabled = false;
              btn.textContent = 'Send Message';
              return;
            }
            
            if (result.success) {
              messageDiv.className = 'form-message success';
              messageDiv.textContent = 'Thank you! Your message has been sent successfully.';
              messageDiv.style.display = 'block';
              form.reset();
              generateCaptcha();
              
              // Scroll to message
              messageDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } else {
              messageDiv.className = 'form-message error';
              messageDiv.textContent = result.error || 'An error occurred. Please try again.';
              messageDiv.style.display = 'block';
              
              // Scroll to message
              messageDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
          } catch (error) {
            console.error('Error sending form:', error);
            messageDiv.className = 'form-message error';
            messageDiv.textContent = 'Network error. Please try again later.';
            messageDiv.style.display = 'block';
          } finally {
            btn.disabled = false;
            btn.textContent = 'Send Message';
          }
        });
      }
      
      // Attach submit handler directly
      contactForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        console.log('Form submit event triggered');
        
        const form = this;
        const formData = new FormData(form);
        const messageDiv = document.getElementById('contact-form-message');
        const submitBtn = form.querySelector('button[type="submit"]');
        
        console.log('Form data:', {
          email: formData.get('email'),
          phone: formData.get('phone'),
          message: formData.get('message'),
          captcha: formData.get('captcha')
        });
        
        // Check if messageDiv exists
        if (!messageDiv) {
          console.error('Message div not found!');
          alert('Form error: message container not found');
          return;
        }
      
      // Clear previous errors
      document.querySelectorAll('.field-error').forEach(el => el.textContent = '');
      messageDiv.className = 'form-message';
      messageDiv.textContent = '';
      messageDiv.style.display = 'none';
      
      // Validate message (first)
      const message = formData.get('message');
      if (!message || message.trim().length === 0) {
        document.getElementById('message-error').textContent = 'Please enter your message.';
        return;
      }
      
      // Validate email (second)
      const email = formData.get('email');
      if (!email || !email.includes('@')) {
        document.getElementById('email-error').textContent = 'Please enter a valid email address.';
        return;
      }
      
      // Validate phone (third)
      const phone = formData.get('phone');
      if (!phone || phone.trim().length < 5) {
        document.getElementById('phone-error').textContent = 'Please enter a valid phone number.';
        return;
      }
      
      // Validate captcha (last)
      const captchaAnswer = parseInt(document.getElementById('captcha-answer').value);
      const captchaInput = parseInt(formData.get('captcha'));
      
      if (captchaInput !== captchaAnswer) {
        document.getElementById('captcha-error').textContent = 'Incorrect answer. Please try again.';
        generateCaptcha();
        return;
      }
      
      // Disable submit button
      submitBtn.disabled = true;
      submitBtn.textContent = 'Sending...';
      
      try {
        const response = await fetch('contact_form.php', {
          method: 'POST',
          body: formData
        });
        
        // Check if response is OK
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        // Get response text first to check if it's valid JSON
        const responseText = await response.text();
        let result;
        
        try {
          result = JSON.parse(responseText);
        } catch (parseError) {
          console.error('Failed to parse JSON response:', parseError);
          console.error('Response text:', responseText);
          messageDiv.className = 'form-message error';
          messageDiv.textContent = 'Server error. Please try again later.';
          messageDiv.style.display = 'block';
          submitBtn.disabled = false;
          submitBtn.textContent = 'Send Message';
          return;
        }
        
        if (result.success) {
          messageDiv.className = 'form-message success';
          messageDiv.textContent = 'Thank you! Your message has been sent successfully.';
          messageDiv.style.display = 'block';
          form.reset();
          generateCaptcha();
          
          // Scroll to message
          messageDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
          messageDiv.className = 'form-message error';
          messageDiv.textContent = result.error || 'An error occurred. Please try again.';
          messageDiv.style.display = 'block';
          
          // Scroll to message
          messageDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      } catch (error) {
        console.error('Error sending form:', error);
        messageDiv.className = 'form-message error';
        messageDiv.textContent = 'Network error. Please try again later.';
        messageDiv.style.display = 'block';
      } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Send Message';
      }
      });
      
      return true;
    }
    
    // Try to initialize immediately
    console.log('Attempting to initialize contact form...');
    if (!initContactForm()) {
      // If form not ready, try again after a short delay
      console.log('Form not found, retrying in 100ms...');
      setTimeout(function() {
        if (!initContactForm()) {
          console.error('Failed to initialize contact form after retry');
          // Last resort: try to attach handler to button directly
          const submitBtn = document.querySelector('#contact-form button[type="submit"]');
          if (submitBtn) {
            console.log('Found submit button, attaching click handler');
            submitBtn.addEventListener('click', function(e) {
              e.preventDefault();
              console.log('Button clicked!');
              const form = document.getElementById('contact-form');
              if (form) {
                form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
              }
            });
          }
        }
      }, 100);
    }
    
    // Also try on window load as backup
    window.addEventListener('load', function() {
      console.log('Window loaded, checking contact form again...');
      if (!document.getElementById('contact-form').hasAttribute('data-handler-attached')) {
        initContactForm();
      }
    });

    // Toggle founder text expand/collapse - make it globally available
    window.toggleFounderText = function() {
      const expandable = document.getElementById('founder-expandable');
      const button = document.getElementById('read-more-btn');
      if (!expandable || !button) return;

      if (expandable.classList.contains('expanded')) {
        expandable.classList.remove('expanded');
        button.textContent = 'Read more';
      } else {
        expandable.classList.add('expanded');
        button.textContent = 'Read less';
      }
    }
    
    // Google Maps Embed API - no JavaScript API needed!
    // Maps are loaded via iframe, which supports search by name without API key
    // The iframe uses Google Maps Embed API which can search by place name using the 'q' parameter
    
    // Map Modal Functions - make them globally available
    window.openMapModal = function() {
      const modal = document.getElementById('map-modal');
      if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden'; // Prevent background scrolling
        // Map is already loaded via iframe, no initialization needed
      }
    }
    
    window.closeMapModal = function() {
      const modal = document.getElementById('map-modal');
      if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = ''; // Restore scrolling
      }
    }
    
    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        window.closeMapModal();
      }
    });

    // Close modal when clicking on backdrop
    document.addEventListener('click', function(e) {
      const modal = document.getElementById('map-modal');
      if (modal && e.target === modal) {
        window.closeMapModal();
      }
    });

    // Initialize reveal animations
    if (window.AnimationUtils && window.AnimationUtils.initReveal) {
      window.AnimationUtils.initReveal();
    } else {
      const revealElements = document.querySelectorAll('.reveal');
      const reveal = () => {
        const trigger = window.innerHeight * 0.88;
        revealElements.forEach(el => {
          const rect = el.getBoundingClientRect();
          if (rect.top < trigger) {
            el.classList.add('visible');
          }
        });
      };
      reveal();
      window.addEventListener('scroll', reveal, { passive: true });
    }
    }); // End of DOMContentLoaded
  </script>
  
  <!-- Map Modal -->
  <div id="map-modal" class="map-modal">
    <div class="map-modal-content">
      <div class="map-modal-header">
        <h3>Back to Base Location</h3>
        <div class="map-modal-actions">
          <a href="https://www.google.com/maps?q=49.6125,-116.9579&hl=en" 
             target="_blank" 
             rel="noopener noreferrer"
             class="open-google-maps-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
              <polyline points="15 3 21 3 21 9"></polyline>
              <line x1="10" y1="14" x2="21" y2="3"></line>
            </svg>
            Open in Google Maps
          </a>
          <button class="map-modal-close" onclick="closeMapModal()" aria-label="Close map">
            ×
          </button>
        </div>
      </div>
      <div class="map-modal-body">
        <iframe 
          id="map-modal-map"
          src="https://www.google.com/maps?q=Back+to+Base+BnB,+Procter,+BC,+Canada&hl=en&z=14&output=embed"
          width="100%" 
          height="100%" 
          style="border:0;" 
          allowfullscreen="" 
          loading="lazy" 
          referrerpolicy="no-referrer-when-downgrade">
        </iframe>
      </div>
    </div>
  </div>
</body>
</html>

